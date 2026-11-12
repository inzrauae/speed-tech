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

    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  array{mime: string, data: string}|null  $photo  reference photo of the real product
     * @return array{path: string, ai: bool, warning: ?string}  path relative to public/
     */
    public function make(string $name, string $visual = '', string $details = '', ?array $photo = null): array
    {
        try {
            $image = $this->gemini->image($this->prompt($name, $visual, $details, (bool) $photo), $photo ? [$photo] : []);

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

    private function prompt(string $name, string $visual, string $details, bool $hasPhoto): string
    {
        return implode("\n", array_filter([
            "Professional e-commerce product photograph of: {$name}.",
            $visual ? "What it looks like: {$visual}" : null,
            $hasPhoto ? 'The attached photo shows the real product. Keep its true shape, colour, ports and details, but create a new, original studio shot with a fresh camera angle and lighting. Remove hands, clutter, price stickers and any existing watermarks or shop logos.' : null,
            $details ? "Shop's instructions for this image (follow these closely): {$details}" : null,
            'Style: photorealistic product, soft studio lighting, gentle natural shadow, product centred and filling about 70% of the frame, sharp focus, high detail.',
            'Background: clean white with a very faint, subtle line-art pattern in pale red and light grey-black. The pattern is bands of 4 to 6 straight parallel stripes, evenly spaced, that run in from the edges and turn at sharp 90-degree right angles, forming squared hook and square-spiral shapes, like a squared maze. No curves, no circles, no dots, no circuit nodes. Keep it very low contrast, like a light watermark (about 10 to 15% opacity), so the product stands out clearly. Arrange the bands differently from any other image, keep them mostly near the edges and corners, behind the product, and never let lines cross over the product.',
            'Do not add any text, captions, watermarks, logos or people. A shop logo will be placed in the bottom-right corner later, so let the line art fade out naturally before that corner, leaving it as the same open white background. Do not draw any box, panel, frame or blank rectangle there.',
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
        // AI images are already square: fill. Plain photos: fit inside with a margin.
        $box = $contain ? self::SIZE * 0.86 : self::SIZE;
        $scale = $contain ? min($box / $w, $box / $h) : max($box / $w, $box / $h);
        [$dw, $dh] = [(int) round($w * $scale), (int) round($h * $scale)];
        imagecopyresampled($canvas, $src, (int) ((self::SIZE - $dw) / 2), (int) ((self::SIZE - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
        imagedestroy($src);

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
