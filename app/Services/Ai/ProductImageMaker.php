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
            'Style: clean white seamless background, soft studio lighting, gentle natural shadow, product centred and filling about 75% of the frame, sharp focus, photorealistic, high detail.',
            'Do not add any text, captions, watermarks, logos or people. Keep the bottom-right corner plain, a shop logo will be placed there.',
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

    private function stampLogo(GdImage $canvas): void
    {
        $logoPath = public_path('assets/logo.png');
        $logo = is_file($logoPath) ? @imagecreatefrompng($logoPath) : false;
        if (! $logo) {
            return;
        }

        $lw = (int) (self::SIZE * 0.24);
        $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
        $pad = 18;
        $margin = 36;
        $x = self::SIZE - $lw - $margin;
        $y = self::SIZE - $lh - $margin;

        // Soft white plate so the logo reads on any background.
        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, $x - $pad, $y - $pad, $x + $lw + $pad, $y + $lh + $pad, imagecolorallocatealpha($canvas, 255, 255, 255, 25));
        imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
}
