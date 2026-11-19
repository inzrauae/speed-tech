<?php

namespace App\Services\Ai;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Creates a unique, square product image with the SpeedTech logo.
 * The picture comes from Gemini; the logo is stamped on afterwards with GD
 * so it is always the real, sharp logo (AI models garble logos and text).
 */
class ProductImageMaker
{
    public const DIR = 'uploads/products';
    private const SIZE = 1200;
    /** Share of the square the product (with its shadow) takes up on its longest side. */
    private const FILL = 0.8;

    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  array{mime: string, data: string}|null  $photo  reference photo of the real product
     * @return array{path: string, ai: bool, warning: ?string}  path relative to public/
     */
    public function make(string $name, string $visual = '', string $details = '', ?array $photo = null, string $specs = ''): array
    {
        try {
            // Own model setting (cheapest image model by default). The AI only draws the product
            // on plain white; the line art is added by brand(), so every model gives the same look.
            $image = $this->gemini->image($this->prompt($name, $visual, $details, (bool) $photo, $specs), $photo ? [$photo] : [],
                models: config('services.gemini.product_image_model'));

            return ['path' => $this->brand($image['data'], $name, false), 'ai' => true, 'warning' => null];
        } catch (Throwable $e) {
            // Without AI, still give a usable branded image from the photo the admin attached.
            if ($photo) {
                return ['path' => $this->brand($photo['data'], $name, true), 'ai' => false,
                    'warning' => 'AI image not generated: '.$e->getMessage().' Your photo was used with the logo instead.'];
            }

            throw $e;
        }
    }

    /** The admin's own photo on a white square with the logo (no AI). */
    public function fromPhoto(array $photo, string $name): string
    {
        return $this->brand($photo['data'], $name, true);
    }

    private function prompt(string $name, string $visual, string $details, bool $hasPhoto, string $specs = ''): string
    {
        return implode("\n", array_filter([
            "Professional e-commerce product photograph of: {$name}.",
            $specs ? "Exact specification of this product: {$specs}" : null,
            $visual ? "What it looks like: {$visual}" : null,
            $hasPhoto ? 'The attached photo shows the real product. Copy its true shape, proportions, colours and every connector exactly (connector type, number of pins or contacts, connector and cable colours, notch and port positions), but do not return the photo as it is: re-shoot it as a new, original studio photo with fresh lighting, from an angle that still shows the connectors clearly. Remove hands, clutter, price stickers, badges, labels added on top of the photo (like "Genuine" or "Original"), and any existing watermarks or shop logos.' : null,
            $details ? "Shop's instructions for this image (follow these closely): {$details}" : null,
            'Technical accuracy matters most: the picture must match the specification above exactly. Show the correct form factor and size, the right connector type with the right number of pins, contacts and ports in the right places and colours (e.g. SATA, M.2 key notch, SO-DIMM edge contacts, eDP/LVDS pin count, USB/HDMI ports). Never add, remove or change ports, pins, notches or features, and never mix in parts of a different model.',
            'Style: photorealistic product, soft studio lighting, gentle natural shadow, product centred and large, filling about 75% of the frame, with the whole product (and every connector) fully inside the picture and a clear white margin on every side, sharp focus, high detail.',
            'Background: pure plain white (#FFFFFF) all the way to every edge, seamless, with no pattern, texture, gradient, props or frame.',
            'Do not add any text, captions, watermarks, logos or people.',
        ]));
    }

    /** Fit the picture on a white square, stamp the logo, save as JPEG. */
    private function brand(string $bytes, string $name, bool $contain): string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw new RuntimeException('The image could not be read.');
        }

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        if ($contain) {
            $this->drawLineArt($canvas); // shows in the margin around the admin's photo
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        // AI images are on plain white: cut away the empty white and enlarge the product so every
        // product fills the same share of the square. Plain photos: fit inside with a margin.
        [$sx, $sy, $sw, $sh] = $contain ? [0, 0, $w, $h] : ($this->contentBox($src) ?? [0, 0, $w, $h]);
        $box = $contain ? self::SIZE * 0.86 : self::SIZE * self::FILL;
        $scale = min($box / $sw, $box / $sh, $contain ? INF : 4);
        [$dw, $dh] = [(int) round($sw * $scale), (int) round($sh * $scale)];
        imagecopyresampled($canvas, $src, (int) ((self::SIZE - $dw) / 2), (int) ((self::SIZE - $dh) / 2), $sx, $sy, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        if (! $contain) {
            $this->addLineArtBehind($canvas);
        }
        $this->stampLogo($canvas);

        $file = Str::slug(Str::limit($name, 60, '')).'-'.Str::lower(Str::random(6)).'.jpg';
        File::ensureDirectoryExists(public_path(self::DIR));
        imagejpeg($canvas, public_path(self::DIR.'/'.$file), 88);
        imagedestroy($canvas);

        return self::DIR.'/'.$file;
    }

    /**
     * Faint bands of parallel stripes that turn at right angles, modelled on
     * the logo's mark, coming in from three corners in a random arrangement.
     * The bottom-right corner is left for the logo. Call on a plain white canvas.
     */
    private function drawLineArt(GdImage $canvas): void
    {
        $colours = [imagecolorallocate($canvas, 218, 30, 40), imagecolorallocate($canvas, 30, 30, 30)];
        $thick = 5;
        imagesetthickness($canvas, $thick);

        foreach ([[0, 0], [self::SIZE, 0], [0, self::SIZE]] as $i => [$cx, $cy]) {
            $dx = $cx ? -1 : 1;
            $dy = $cy ? -1 : 1;
            $a = random_int(200, 420);
            $b = random_int(200, 420);

            // Corner-local path (u = in from the side edge, v = in from the top/bottom edge).
            $path = match (random_int(0, 2)) {
                0 => [[$a, 0], [$a, $b], [0, $b]],                                          // L round the corner
                1 => [[$a, 0], [$a, $b], [(int) ($a * 0.4), $b], [(int) ($a * 0.4), (int) ($b * 0.45)]], // hook
                2 => [[0, $b], [$a, $b], [$a, (int) ($b * 0.45)], [(int) ($a * 0.4), (int) ($b * 0.45)]], // hook, other way
            };

            $colour = $colours[($i + random_int(0, 1)) % 2];
            $lines = random_int(4, 6);
            $gap = $thick * 2 + 1;
            for ($k = 0; $k < $lines; $k++) {
                $points = $this->offsetPath($path, $k * $gap);
                for ($p = 1; $p < count($points); $p++) {
                    [$u1, $v1] = $points[$p - 1];
                    [$u2, $v2] = $points[$p];
                    imageline($canvas, $cx + $dx * $u1, $cy + $dy * $v1, $cx + $dx * $u2, $cy + $dy * $v2, $colour);
                }
            }
        }

        imagesetthickness($canvas, 1);

        // Wash it out to a light watermark: a mostly-opaque white layer on top.
        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, 0, 0, self::SIZE, self::SIZE, imagecolorallocatealpha($canvas, 255, 255, 255, 28));
    }

    /**
     * The rectangle around everything that is not plain white background (product + shadow),
     * as [x, y, width, height], or null when the picture has no clear white border to trim.
     *
     * @return array{int, int, int, int}|null
     */
    private function contentBox(GdImage $src): ?array
    {
        [$w, $h] = [imagesx($src), imagesy($src)];
        $step = 2;
        $cols = array_fill(0, $w, 0);
        $rows = array_fill(0, $h, 0);
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($src, $x, $y);
                [$r, $g, $b] = [$c >> 16 & 255, $c >> 8 & 255, $c & 255];
                if (min($r, $g, $b) < 232 || max($r, $g, $b) - min($r, $g, $b) > 24) {
                    $cols[$x]++;
                    $rows[$y]++;
                }
            }
        }

        // Ignore stray specks: a row/column needs a few non-white samples to count.
        $edges = function (array $counts) {
            $hits = array_keys(array_filter($counts, fn ($n) => $n >= 3));

            return $hits ? [min($hits), max($hits)] : null;
        };
        [$x0, $x1] = $edges($cols) ?? [null, null];
        [$y0, $y1] = $edges($rows) ?? [null, null];
        if ($x0 === null || $y0 === null) {
            return null;
        }

        $pad = (int) round(max($x1 - $x0, $y1 - $y0) * 0.02);
        $x0 = max(0, $x0 - $pad);
        $y0 = max(0, $y0 - $pad);
        $x1 = min($w - 1, $x1 + $pad + $step);
        $y1 = min($h - 1, $y1 + $pad + $step);

        return [$x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1];
    }

    /**
     * Put the line art on an AI picture's white background only: the white area is found by
     * flood-filling from the edges, so the product (even a white box inside it) is never drawn over.
     */
    private function addLineArtBehind(GdImage $canvas): void
    {
        $layer = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($layer, 0, 0, imagecolorallocate($layer, 255, 255, 255));
        $this->drawLineArt($layer);

        $cell = 4;
        $n = intdiv(self::SIZE, $cell);
        $mask = $this->backgroundMask($canvas, $n);

        for ($y = 0; $y < self::SIZE; $y++) {
            $row = intdiv($y, $cell) * $n;
            for ($x = 0; $x < self::SIZE; $x++) {
                $l = imagecolorat($layer, $x, $y) & 0xFFFFFF;
                if ($l === 0xFFFFFF || ! $mask[$row + intdiv($x, $cell)]) {
                    continue;
                }
                $c = imagecolorat($canvas, $x, $y);
                // "Darken" blend: on white it shows the line art as drawn.
                imagesetpixel($canvas, $x, $y,
                    (min($c >> 16 & 255, $l >> 16 & 255) << 16) | (min($c >> 8 & 255, $l >> 8 & 255) << 8) | min($c & 255, $l & 255));
            }
        }
        imagedestroy($layer);
    }

    /**
     * Near-white cells connected to the picture's edges, on an $n×$n grid, shrunk by one cell
     * so the line art keeps clear of the product's outline.
     *
     * @return list<bool>
     */
    private function backgroundMask(GdImage $canvas, int $n): array
    {
        $small = imagecreatetruecolor($n, $n);
        imagecopyresampled($small, $canvas, 0, 0, 0, 0, $n, $n, self::SIZE, self::SIZE);

        $white = [];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $c = imagecolorat($small, $x, $y);
                [$r, $g, $b] = [$c >> 16 & 255, $c >> 8 & 255, $c & 255];
                $white[] = min($r, $g, $b) >= 228 && max($r, $g, $b) - min($r, $g, $b) <= 20;
            }
        }
        imagedestroy($small);

        $filled = array_fill(0, $n * $n, false);
        $stack = [];
        for ($i = 0; $i < $n; $i++) {
            array_push($stack, $i, ($n - 1) * $n + $i, $i * $n, $i * $n + $n - 1);
        }
        while ($stack) {
            $i = array_pop($stack);
            if ($filled[$i] || ! $white[$i]) {
                continue;
            }
            $filled[$i] = true;
            [$x, $y] = [$i % $n, intdiv($i, $n)];
            if ($x > 0) $stack[] = $i - 1;
            if ($x < $n - 1) $stack[] = $i + 1;
            if ($y > 0) $stack[] = $i - $n;
            if ($y < $n - 1) $stack[] = $i + $n;
        }

        $mask = [];
        foreach ($filled as $i => $bg) {
            [$x, $y] = [$i % $n, intdiv($i, $n)];
            $mask[] = $bg
                && ($x === 0 || $filled[$i - 1]) && ($x === $n - 1 || $filled[$i + 1])
                && ($y === 0 || $filled[$i - $n]) && ($y === $n - 1 || $filled[$i + $n]);
        }

        return $mask;
    }

    /**
     * Shift a right-angled polyline sideways by $o pixels so repeated calls give
     * evenly spaced, neatly nested parallel stripes.
     *
     * @param  list<array{int, int}>  $path
     * @return list<array{int, int}>
     */
    private function offsetPath(array $path, int $o): array
    {
        $normals = [];
        for ($i = 1; $i < count($path); $i++) {
            $du = $path[$i][0] <=> $path[$i - 1][0];
            $dv = $path[$i][1] <=> $path[$i - 1][1];
            $normals[] = [-$dv, $du];
        }

        $out = [];
        foreach ($path as $i => [$u, $v]) {
            $prev = $normals[$i - 1] ?? [0, 0];
            $next = $normals[$i] ?? [0, 0];
            $out[] = [$u + $o * ($prev[0] + $next[0]), $v + $o * ($prev[1] + $next[1])];
        }

        return $out;
    }

    private function stampLogo(GdImage $canvas): void
    {
        $logoPath = public_path('assets/logo.png');
        $logo = is_file($logoPath) ? @imagecreatefrompng($logoPath) : false;
        if (! $logo) {
            return;
        }

        $lw = (int) (self::SIZE * 0.24);
        $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
        $margin = 36;
        $x = self::SIZE - $lw - $margin;
        $y = self::SIZE - $lh - $margin;

        // Transparent PNG straight onto the picture (no plate behind it).
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
}
