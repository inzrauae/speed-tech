<?php

namespace App\Services\Ai;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Square SpeedTech product images from a REAL photo: the manufacturer's original
 * photo found online, or the shop's own photo. Nothing is drawn by AI, so the ports,
 * pins and connectors are always the real ones. The photo is put in the shop layout:
 * white square, product centred and enlarged, branded line art and the logo.
 */
class ProductImageMaker
{
    public const DIR = 'uploads/products';

    private const SIZE = 1200;
    /** Share of the square the product (with its shadow) takes up on its longest side. */
    private const FILL = 0.8;
    /** A photo whose border is at least this white counts as "on white" and is trimmed and enlarged. */
    private const WHITE_BACKGROUND = 0.85;

    public function __construct(private ManufacturerPhotoFinder $finder) {}

    /**
     * Find the manufacturer's photo online and put it in the shop layout.
     *
     * @return array{path: string, source: string}  path relative to public/
     */
    public function fromWeb(string $name, string $brand = '', string $query = '', string $specs = '', ?string $url = null): array
    {
        $photo = $this->finder->find($name, $brand, $query, $specs, $url);

        return ['path' => $this->brand($photo['data'], $name), 'source' => $photo['source']];
    }

    /** The admin's own photo in the shop layout. */
    public function fromPhoto(array $photo, string $name): string
    {
        return $this->brand($photo['data'], $name);
    }

    /**
     * Fit the picture on a white square, stamp the logo, save as JPEG.
     * Photos on white are trimmed and enlarged to fill the square, with the line art
     * behind the product; other photos are fitted inside, with the line art around them.
     */
    private function brand(string $bytes, string $name): string
    {
        $src = $this->flatten($bytes);
        $onWhite = ManufacturerPhotoFinder::whiteBorder($src) >= self::WHITE_BACKGROUND;

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        if (! $onWhite) {
            $this->drawLineArt($canvas); // shows in the margin around the photo
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        [$sx, $sy, $sw, $sh] = $onWhite ? ($this->contentBox($src) ?? [0, 0, $w, $h]) : [0, 0, $w, $h];
        $box = $onWhite ? self::SIZE * self::FILL : self::SIZE * 0.86;
        $scale = min($box / $sw, $box / $sh, 4);
        [$dw, $dh] = [(int) round($sw * $scale), (int) round($sh * $scale)];
        imagecopyresampled($canvas, $src, (int) ((self::SIZE - $dw) / 2), (int) ((self::SIZE - $dh) / 2), $sx, $sy, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        if ($onWhite) {
            $this->addLineArtBehind($canvas);
        }
        $this->stampLogo($canvas);

        $file = Str::slug(Str::limit($name, 60, '')).'-'.Str::lower(Str::random(6)).'.jpg';
        File::ensureDirectoryExists(public_path(self::DIR));
        imagejpeg($canvas, public_path(self::DIR.'/'.$file), 90);
        imagedestroy($canvas);

        return self::DIR.'/'.$file;
    }

    /** Decode the photo onto white, so transparent PNG/WebP backgrounds become white. */
    private function flatten(string $bytes): GdImage
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            throw new RuntimeException('The image could not be read.');
        }
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagedestroy($img);

        return $flat;
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
     * Put the line art on a photo's white background only: the white area is found by
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
