<?php

namespace App\Services\Ai;

use GdImage;
use Throwable;

/**
 * Cuts the product out of a staff photo, in code: the product's own pixels are kept
 * exactly as photographed (nothing is redrawn), so ports and pins stay real.
 *
 * 1. The AI traces the product's outline roughly (a polygon). Well inside it is surely
 *    product, well outside it is surely background (clutter, other objects).
 * 2. In the thin band between, the background is filled in through colours like the
 *    background, but it cannot cross the product's outline (strong edges), so the exact
 *    edge comes from the photo itself.
 * 3. Specks and texture lines are cleaned away; the largest pieces are the product.
 * Without the AI (no key, quota, odd answer) step 2 starts from the photo's edge.
 *
 * The mask is a greyscale picture the size of the photo: 255 = product, 0 = background.
 */
class BackgroundRemover
{
    /** The cut-out is worked out at this size, then scaled up smoothly (soft, clean edges). */
    private const WORK = 520;

    public function __construct(private GeminiClient $gemini) {}

    public function mask(GdImage $photo, string $name): GdImage
    {
        // Pixel-by-pixel work in PHP arrays needs about 120 MB; give it room on small hosting limits.
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && $this->bytes($limit) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }

        $work = $this->scaled($photo, self::WORK);

        try {
            $outline = $this->outline($work, $name);
        } catch (Throwable) {
            $outline = null; // no AI: the photo's edge is the background
        }
        $mask = $this->cutout($work, $outline);
        imagedestroy($work);

        // Soft edge, then tighten it so no rim of the old background shows.
        imagefilter($mask, IMG_FILTER_GAUSSIAN_BLUR);
        $this->levels($mask, 80, 210);

        $full = imagecreatetruecolor(imagesx($photo), imagesy($photo));
        imagecopyresampled($full, $mask, 0, 0, 0, 0, imagesx($photo), imagesy($photo), imagesx($mask), imagesy($mask));
        imagedestroy($mask);

        return $full;
    }

    /**
     * The AI's rough outline of the product, as "inside" per pixel of $img, or null.
     *
     * @return list<bool>|null
     */
    private function outline(GdImage $img, string $name): ?array
    {
        ob_start();
        imagejpeg($img, null, 90);
        $answer = $this->gemini->json(
            'You trace product outlines in photos for a computer parts shop.',
            implode("\n", [
                "Trace the outline of the product in this photo: {$name}.",
                'Include every part, cable and connector attached to it; not the table, background, shadows, reflections, hands, packaging or other objects.',
                "Give the outline as a polygon: 'points' is a list of [y, x] points on a 0-1000 scale, going round the product's edge in order, with a point at every corner and enough points (20-80) to follow curves closely.",
            ]),
            ['type' => 'OBJECT', 'properties' => ['points' => ['type' => 'ARRAY', 'items' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']]]], 'required' => ['points']],
            [['mime' => 'image/jpeg', 'data' => ob_get_clean()]],
        );

        [$w, $h] = [imagesx($img), imagesy($img)];
        $xy = [];
        foreach ((array) ($answer['points'] ?? []) as $p) {
            if (is_array($p) && count($p) >= 2 && is_numeric($p[0]) && is_numeric($p[1])) {
                $xy[] = (int) round(max(0, min(1000, $p[1])) * ($w - 1) / 1000);
                $xy[] = (int) round(max(0, min(1000, $p[0])) * ($h - 1) / 1000);
            }
        }
        if (count($xy) < 6) {
            return null;
        }

        $canvas = imagecreatetruecolor($w, $h);
        imagefilledpolygon($canvas, $xy, imagecolorallocate($canvas, 255, 255, 255));
        $inside = [];
        $on = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $in = (imagecolorat($canvas, $x, $y) & 255) > 127;
                $inside[] = $in;
                $on += $in ? 1 : 0;
            }
        }
        imagedestroy($canvas);

        // A real outline covers some of the photo, not (almost) nothing or all of it.
        return $on >= $w * $h * 0.01 && $on <= $w * $h * 0.97 ? $inside : null;
    }

    /** @param  list<bool>|null  $outline */
    private function cutout(GdImage $img, ?array $outline): GdImage
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $n = $w * $h;

        $px = $this->pixels($img);
        $smooth = imagecreatetruecolor($w, $h);
        imagecopy($smooth, $img, 0, 0, 0, 0, $w, $h);
        imagefilter($smooth, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($smooth, IMG_FILTER_GAUSSIAN_BLUR);
        $sm = $this->pixels($smooth);
        imagedestroy($smooth);

        // Edge strength per pixel (on the smoothed photo, so fine texture counts less).
        $grad = array_fill(0, $n, 0);
        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                $i = $y * $w + $x;
                $grad[$i] = $this->diff($sm[$i - 1], $sm[$i + 1]) + $this->diff($sm[$i - $w], $sm[$i + $w]);
            }
        }
        unset($sm);

        // Seeds (surely background): the photo's edge, and everything well outside the AI's outline.
        // Surely product: everything a little inside the outline.
        $seed = array_fill(0, $n, false);
        for ($x = 0; $x < $w; $x++) {
            $seed[$x] = $seed[($h - 1) * $w + $x] = true;
        }
        for ($y = 0; $y < $h; $y++) {
            $seed[$y * $w] = $seed[$y * $w + $w - 1] = true;
        }
        $sure = array_fill(0, $n, false);
        $ring = null;
        if ($outline) {
            $out = (int) round(max($w, $h) * 0.03);
            $in = (int) round(max($w, $h) * 0.015);
            $grown = $this->repeat('grow', $outline, $out, $w, $h);
            $sure = $this->repeat('shrink', $outline, $in, $w, $h);
            $far = $this->repeat('grow', $grown, $out, $w, $h);
            $ring = [];
            foreach ($grown as $i => $g) {
                if (! $g) {
                    $seed[$i] = true;
                }
                $ring[] = ! $g && $far[$i]; // the background right next to the product
            }
            unset($grown, $far);
        }
        unset($outline);

        // How textured the background is decides how strong an edge must be to count as the product's outline.
        $bgGrad = [];
        foreach ($seed as $i => $s) {
            if ($s && $i % 3 === 0) {
                $bgGrad[] = $grad[$i];
            }
        }
        sort($bgGrad);
        $edgeAt = max(28, ($bgGrad[(int) (count($bgGrad) * 0.97)] ?? 0) * 1.4);
        $wall = array_map(fn ($g) => $g > $edgeAt, $grad);
        // Where the product is close in colour to the background its outline is faint and has gaps.
        // The first fill uses walls 3 pixels thicker, which closes those gaps.
        $thick = $this->grow($this->grow($this->grow($wall, $w, $h), $w, $h), $w, $h);
        unset($grad, $bgGrad);

        // Background colours: the seed pixels next to the product area, grouped into typical colours.
        $bg = $this->typicalColours($px, $seed, $ring);
        unset($ring);
        $nearBg = function (int $c, float $limit) use ($bg): bool {
            foreach ($bg as $b) {
                if ($this->dist($c, $b) < $limit) {
                    return true;
                }
            }

            return false;
        };

        // Background colour, or smooth shading of it (soft shadows).
        $joins = fn (int $j, int $from) => $nearBg($px[$j], 60) || ($this->dist($px[$j], $px[$from]) < 7 && $nearBg($px[$j], 120));

        // 1. Fill the background from the seeds; it stops at the (thickened) outline.
        $isBg = $seed;
        $stack = array_keys(array_filter($seed));
        unset($seed);
        while ($stack) {
            $i = array_pop($stack);
            $x = $i % $w;
            foreach ([$x > 0 ? $i - 1 : -1, $x < $w - 1 ? $i + 1 : -1, $i >= $w ? $i - $w : -1, $i + $w < $n ? $i + $w : -1] as $j) {
                if ($j >= 0 && ! $isBg[$j] && ! $thick[$j] && ! $sure[$j] && $joins($j, $i)) {
                    $isBg[$j] = true;
                    $stack[] = $j;
                }
            }
        }

        unset($thick, $stack);

        // 2. Win back the background the thick walls covered: at most 4 pixels further, up to
        //    the real outline, so it cannot travel through a gap into the product.
        $depth = array_fill(0, $n, 0);
        $queue = array_keys(array_filter($isBg));
        for ($q = 0; $q < count($queue); $q++) {
            $i = $queue[$q];
            if ($depth[$i] >= 4) {
                continue;
            }
            $x = $i % $w;
            foreach ([$x > 0 ? $i - 1 : -1, $x < $w - 1 ? $i + 1 : -1, $i >= $w ? $i - $w : -1, $i + $w < $n ? $i + $w : -1] as $j) {
                if ($j >= 0 && ! $isBg[$j] && ! $wall[$j] && ! $sure[$j] && $joins($j, $i)) {
                    $isBg[$j] = true;
                    $depth[$j] = $depth[$i] + 1;
                    $queue[] = $j;
                }
            }
        }

        // Product = the big pieces of what is left. Pieces are found first, so parts joined to
        // the product only by a thin line (a key, a cable) stay. Then thin texture lines are
        // opened away (shrink, grow) and one pixel comes off the outline, so no rim of the
        // background is left.
        unset($depth, $queue, $wall, $px);
        $fg = array_map(fn ($b) => ! $b, $isBg);
        unset($isBg);
        $keep = array_map(fn ($v) => $v > 0, $this->largestPieces($fg, $w, $h));
        $keep = $this->shrink($this->grow($this->shrink($keep, $w, $h), $w, $h), $w, $h);
        foreach ($sure as $i => $s) {
            $keep[$i] = $keep[$i] || $s;
        }
        $values = array_map(fn ($on) => $on ? 255 : 0, $keep);

        if (! array_filter($values)) {
            $values = array_fill(0, $n, 255); // nothing found: keep the whole photo
        }

        return $this->toImage($values, $w, $h);
    }

    /**
     * Typical colours among the seed pixels (only those in $near when given), as a few averaged colours.
     *
     * @param  list<int>  $px
     * @param  list<bool>  $seed
     * @return list<int>
     */
    private function typicalColours(array $px, array $seed, ?array $near): array
    {
        $buckets = [];
        $total = 0;
        foreach ($seed as $i => $s) {
            if (! $s) {
                continue;
            }
            if ($near && ! $near[$i]) {
                continue;
            }
            $c = $px[$i];
            $key = ($c >> 20 & 15) << 8 | ($c >> 12 & 15) << 4 | ($c >> 4 & 15);
            $buckets[$key][] = $c;
            $total++;
        }
        if ($near && $total < 30) {
            return $this->typicalColours($px, $seed, null); // product fills the photo: use all background
        }

        $out = [];
        foreach ($buckets as $list) {
            if (count($list) >= max(3, $total * 0.01)) {
                $r = $g = $b = 0;
                foreach ($list as $c) {
                    $r += $c >> 16 & 255;
                    $g += $c >> 8 & 255;
                    $b += $c & 255;
                }
                $k = count($list);
                $out[] = ((int) ($r / $k) << 16) | ((int) ($g / $k) << 8) | (int) ($b / $k);
            }
        }

        return $out ?: [0xFFFFFF];
    }

    /**
     * 255 for pixels in the large connected pieces (at least 5% of the biggest), else 0.
     *
     * @param  list<bool>  $fg
     * @return list<int>
     */
    private function largestPieces(array $fg, int $w, int $h): array
    {
        $n = $w * $h;
        $label = array_fill(0, $n, 0);
        $sizes = [];
        $next = 0;
        for ($s = 0; $s < $n; $s++) {
            if (! $fg[$s] || $label[$s]) {
                continue;
            }
            $label[$s] = ++$next;
            $stack = [$s];
            $size = 0;
            while ($stack) {
                $i = array_pop($stack);
                $size++;
                $x = $i % $w;
                foreach ([$x > 0 ? $i - 1 : -1, $x < $w - 1 ? $i + 1 : -1, $i >= $w ? $i - $w : -1, $i + $w < $n ? $i + $w : -1] as $j) {
                    if ($j >= 0 && $fg[$j] && ! $label[$j]) {
                        $label[$j] = $next;
                        $stack[] = $j;
                    }
                }
            }
            $sizes[$next] = $size;
        }
        $keepFrom = $sizes ? max(max($sizes) * 0.05, $n * 0.002) : PHP_INT_MAX;

        return array_map(fn ($l) => $l && $sizes[$l] >= $keepFrom ? 255 : 0, $label);
    }

    /** Grow or shrink a pixel set $times pixels. */
    private function repeat(string $how, array $m, int $times, int $w, int $h): array
    {
        for ($k = 0; $k < $times; $k++) {
            $m = $this->{$how}($m, $w, $h);
        }

        return $m;
    }

    /** @param  list<bool>  $m */
    private function shrink(array $m, int $w, int $h): array
    {
        $out = $m;
        foreach ($m as $i => $on) {
            if ($on) {
                $x = $i % $w;
                $out[$i] = ($x > 0 && $m[$i - 1]) && ($x < $w - 1 && $m[$i + 1]) && ($i >= $w && $m[$i - $w]) && ($i + $w < $w * $h && $m[$i + $w]);
            }
        }

        return $out;
    }

    /** @param  list<bool>  $m */
    private function grow(array $m, int $w, int $h): array
    {
        $out = $m;
        foreach ($m as $i => $on) {
            if ($on) {
                $x = $i % $w;
                if ($x > 0) $out[$i - 1] = true;
                if ($x < $w - 1) $out[$i + 1] = true;
                if ($i >= $w) $out[$i - $w] = true;
                if ($i + $w < $w * $h) $out[$i + $w] = true;
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function pixels(GdImage $img): array
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $px = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $px[] = imagecolorat($img, $x, $y) & 0xFFFFFF;
            }
        }

        return $px;
    }

    private function dist(int $a, int $b): float
    {
        $dr = ($a >> 16 & 255) - ($b >> 16 & 255);
        $dg = ($a >> 8 & 255) - ($b >> 8 & 255);
        $db = ($a & 255) - ($b & 255);

        return sqrt($dr * $dr + $dg * $dg + $db * $db);
    }

    private function diff(int $a, int $b): int
    {
        return abs(($a >> 16 & 255) - ($b >> 16 & 255)) + abs(($a >> 8 & 255) - ($b >> 8 & 255)) + abs(($a & 255) - ($b & 255));
    }

    /** @param  list<int>  $values  0..255 per pixel, row by row */
    private function toImage(array $values, int $w, int $h): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        foreach ($values as $i => $v) {
            if ($v) {
                imagesetpixel($img, $i % $w, intdiv($i, $w), $v << 16 | $v << 8 | $v);
            }
        }

        return $img;
    }

    /** Stretch the mask so $lo → 0 and $hi → 255 (a crisper edge). */
    private function levels(GdImage $mask, int $lo, int $hi): void
    {
        [$w, $h] = [imagesx($mask), imagesy($mask)];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $v = imagecolorat($mask, $x, $y) & 255;
                $v = (int) max(0, min(255, round(($v - $lo) * 255 / ($hi - $lo))));
                imagesetpixel($mask, $x, $y, $v << 16 | $v << 8 | $v);
            }
        }
    }

    private function bytes(string $v): int
    {
        $n = (int) $v;

        return match (strtolower(substr(trim($v), -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private function scaled(GdImage $img, int $max): GdImage
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $k = min(1, $max / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
        imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);

        return $out;
    }
}
