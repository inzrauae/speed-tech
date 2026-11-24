<?php

namespace App\Services\Ai;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Square SpeedTech product images from a REAL photo: the manufacturer's original
 * photo from its official website, or the shop's own photo (retouched first).
 * Nothing is drawn by AI, so the ports, pins and connectors are always the real ones.
 * Layout: white square, product at 70% of the square, a faint SpeedTech logo watermark
 * across the background and the website logo in the bottom-left corner.
 */
class ProductImageMaker
{
    public const DIR = 'uploads/products';

    private const SIZE = 1200;
    /** Share of the square the product takes up on its longest side. */
    private const FILL = 0.7;
    /** A photo whose border is at least this white counts as "on white" and is trimmed to the product. */
    private const WHITE_BACKGROUND = 0.85;
    /** How strongly the watermark logos show (0 = invisible, 1 = full colour). */
    private const WATERMARK_STRENGTH = 0.07;

    public function __construct(private ManufacturerPhotoFinder $finder) {}

    /**
     * Find the manufacturer's photo on its official website and put it in the shop layout.
     *
     * @return array{path: string, source: string}  path relative to public/
     */
    public function fromWeb(string $name, string $brand = '', string $query = '', string $specs = '', ?string $url = null): array
    {
        $photo = $this->finder->find($name, $brand, $query, $specs, $url);

        return ['path' => $this->brand($this->flatten($photo['data']), $name), 'source' => $photo['source']];
    }

    /** The admin's own photo, retouched, in the shop layout. */
    public function fromPhoto(array $photo, string $name): string
    {
        return $this->brand($this->retouch($this->flatten($photo['data'])), $name);
    }

    /**
     * Fit the product at 70% of a white square over the logo watermark, stamp the logo, save as JPEG.
     * Photos on white are trimmed to the product and the watermark shows behind it;
     * other photos are fitted whole, with the watermark around them.
     */
    private function brand(GdImage $src, string $name): string
    {
        $onWhite = ManufacturerPhotoFinder::whiteBorder($src) >= self::WHITE_BACKGROUND;

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        if (! $onWhite) {
            $this->drawWatermark($canvas); // shows in the margin around the photo
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        [$sx, $sy, $sw, $sh] = $onWhite ? ($this->contentBox($src) ?? [0, 0, $w, $h]) : [0, 0, $w, $h];
        $box = self::SIZE * self::FILL;
        $scale = min($box / $sw, $box / $sh, 4);
        [$dw, $dh] = [(int) round($sw * $scale), (int) round($sh * $scale)];
        imagecopyresampled($canvas, $src, (int) ((self::SIZE - $dw) / 2), (int) ((self::SIZE - $dh) / 2), $sx, $sy, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        if ($onWhite) {
            $this->addWatermarkBehind($canvas);
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
     * Clean up a shop photo in code (no AI, so the product itself is never changed):
     * correct the colour using the light backdrop as the white reference, stretch the
     * levels so it is not dull, make the light backdrop around the product pure white,
     * and sharpen a little.
     */
    private function retouch(GdImage $img): GdImage
    {
        // Large phone photos: work at most 1600 px, more than the 1200 px result needs.
        [$w, $h] = [imagesx($img), imagesy($img)];
        if (max($w, $h) > 1600) {
            $k = 1600 / max($w, $h);
            $small = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
            imagecopyresampled($small, $img, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
            imagedestroy($img);
            $img = $small;
            [$w, $h] = [imagesx($img), imagesy($img)];
        }

        // White reference: the brightest third of the border (the backdrop the product stands on).
        $border = [];
        for ($i = 0; $i < 80; $i++) {
            $x = (int) ($i * ($w - 1) / 79);
            $y = (int) ($i * ($h - 1) / 79);
            foreach ([[$x, 0], [$x, $h - 1], [0, $y], [$w - 1, $y]] as [$px, $py]) {
                $c = imagecolorat($img, $px, $py);
                $border[] = [$c >> 16 & 255, $c >> 8 & 255, $c & 255];
            }
        }
        usort($border, fn ($a, $b) => array_sum($b) <=> array_sum($a));
        $top = array_slice($border, 0, max(1, intdiv(count($border), 3)));
        $ref = [];
        foreach ([0, 1, 2] as $ch) {
            $ref[$ch] = array_sum(array_column($top, $ch)) / count($top);
        }
        // A dark or strongly coloured backdrop is no white reference: then only the levels are stretched.
        $useRef = min($ref) >= 120 && max($ref) - min($ref) <= 60;

        // Dark point: the darkest 1% of the picture becomes black (capped, so dark products stay natural).
        $hist = array_fill(0, 256, 0);
        $n = 0;
        for ($y = 0; $y < $h; $y += 3) {
            for ($x = 0; $x < $w; $x += 3) {
                $c = imagecolorat($img, $x, $y);
                $hist[intdiv(($c >> 16 & 255) * 3 + ($c >> 8 & 255) * 6 + ($c & 255), 10)]++;
                $n++;
            }
        }
        [$low, $sum] = [0, 0];
        for ($v = 0; $v < 256; $v++) {
            $sum += $hist[$v];
            if ($sum >= $n * 0.01) {
                $low = min($v, 40);
                break;
            }
        }

        // One lookup table per channel: backdrop colour → white, dark point → black.
        $lut = [];
        foreach ([0, 1, 2] as $ch) {
            $white = $useRef ? max($ref[$ch], $low + 60) : 255;
            for ($v = 0; $v < 256; $v++) {
                $lut[$ch][$v] = (int) max(0, min(255, round(($v - $low) * 255 / ($white - $low))));
            }
        }
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                imagesetpixel($img, $x, $y, ($lut[0][$c >> 16 & 255] << 16) | ($lut[1][$c >> 8 & 255] << 8) | $lut[2][$c & 255]);
            }
        }

        if ($useRef) {
            $this->whitenBackground($img);
        }

        // Gentle sharpen.
        imageconvolution($img, [[0, -1, 0], [-1, 12, -1], [0, -1, 0]], 8, 0);

        return $img;
    }

    /**
     * Turn the light, almost-white area connected to the picture's edges pure white,
     * so a photo on a white sheet or table looks like a studio shot. Light parts inside
     * the product are left alone (they are not connected to the edge).
     */
    private function whitenBackground(GdImage $img): void
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $seen = array_fill(0, $w * $h, false);
        $stack = [];
        for ($x = 0; $x < $w; $x++) {
            array_push($stack, $x, ($h - 1) * $w + $x);
        }
        for ($y = 0; $y < $h; $y++) {
            array_push($stack, $y * $w, $y * $w + $w - 1);
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        while ($stack) {
            $i = array_pop($stack);
            if ($seen[$i]) {
                continue;
            }
            $seen[$i] = true;
            [$x, $y] = [$i % $w, intdiv($i, $w)];
            $c = imagecolorat($img, $x, $y);
            [$r, $g, $b] = [$c >> 16 & 255, $c >> 8 & 255, $c & 255];
            if (min($r, $g, $b) < 215 || max($r, $g, $b) - min($r, $g, $b) > 28) {
                continue;
            }
            imagesetpixel($img, $x, $y, $white);
            if ($x > 0) $stack[] = $i - 1;
            if ($x < $w - 1) $stack[] = $i + 1;
            if ($y > 0) $stack[] = $i - $w;
            if ($y < $h - 1) $stack[] = $i + $w;
        }
    }

    /** Faint SpeedTech logos repeated across the square in staggered rows. Draws on a plain white canvas. */
    private function drawWatermark(GdImage $canvas): void
    {
        $logo = $this->logo();
        if (! $logo) {
            return;
        }

        // The logo washed out to a pale tint on white, once, then tiled.
        $lw = 300;
        $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
        $tile = imagecreatetruecolor($lw, $lh);
        imagefill($tile, 0, 0, imagecolorallocate($tile, 255, 255, 255));
        imagealphablending($tile, true);
        imagecopyresampled($tile, $logo, 0, 0, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
        imagefilledrectangle($tile, 0, 0, $lw, $lh, imagecolorallocatealpha($tile, 255, 255, 255, (int) round(127 * self::WATERMARK_STRENGTH)));

        // Staggered rows of whole logos (3, then 2, …), none cut off at the edge.
        $rows = 6;
        $gapY = intdiv(self::SIZE - $rows * $lh, $rows);
        for ($row = 0; $row < $rows; $row++) {
            $y = intdiv($gapY, 2) + $row * ($lh + $gapY);
            $count = $row % 2 ? 2 : 3;
            $gapX = intdiv(self::SIZE - $count * $lw, $count);
            for ($i = 0; $i < $count; $i++) {
                $x = intdiv($gapX, 2) + $i * ($lw + $gapX);
                // Leave the corner with the real logo clear.
                if ($x < self::SIZE * 0.4 && $y + $lh > self::SIZE - 160) {
                    continue;
                }
                imagecopy($canvas, $tile, $x, $y, 0, 0, $lw, $lh);
            }
        }
        imagedestroy($tile);
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
     * Put the watermark on a photo's white background only: the white area is found by
     * flood-filling from the edges, so the product (even a white box inside it) is never drawn over.
     */
    private function addWatermarkBehind(GdImage $canvas): void
    {
        $layer = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($layer, 0, 0, imagecolorallocate($layer, 255, 255, 255));
        $this->drawWatermark($layer);

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
                // "Darken" blend: on white it shows the watermark as drawn.
                imagesetpixel($canvas, $x, $y,
                    (min($c >> 16 & 255, $l >> 16 & 255) << 16) | (min($c >> 8 & 255, $l >> 8 & 255) << 8) | min($c & 255, $l & 255));
            }
        }
        imagedestroy($layer);
    }

    /**
     * Near-white cells connected to the picture's edges, on an $n×$n grid, shrunk by one cell
     * so the watermark keeps clear of the product's outline.
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

    private function logo(): GdImage|false
    {
        $path = public_path('assets/logo.png');

        return is_file($path) ? @imagecreatefrompng($path) : false;
    }

    /** The website logo (transparent PNG) in the bottom-left corner, on a soft white patch so it reads over the watermark. */
    private function stampLogo(GdImage $canvas): void
    {
        $logo = $this->logo();
        if (! $logo) {
            return;
        }

        $lw = (int) (self::SIZE * 0.26);
        $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
        $margin = 36;
        $x = $margin;
        $y = self::SIZE - $lh - $margin;

        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, $x - 14, $y - 12, $x + $lw + 14, $y + $lh + 12, imagecolorallocatealpha($canvas, 255, 255, 255, 20));
        imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
}
