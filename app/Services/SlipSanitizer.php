<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Makes customer payment slips safe to keep and to open in the admin.
 *
 * Images are decoded and re-drawn into a brand-new JPEG, which drops anything hidden in the
 * original (scripts appended to the file, EXIF/metadata payloads, polyglot tricks).
 * PDFs cannot be re-drawn without extra software, so they are scanned instead and rejected
 * if they contain scripts, launch actions, embedded files, forms or encryption.
 *
 * Files are stored on the private disk under a random name; the customer's file name is never used.
 */
class SlipSanitizer
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_SIDE = 8000;          // reject absurd dimensions (decompression bombs)
    private const MAX_PIXELS = 40_000_000;
    private const SAVE_SIDE = 2400;         // plenty to read a slip; keeps storage small
    private const DIR = 'payment-slips';

    // PDF features that can run code, open programs or carry other files.
    // Plain web links (/URI) are allowed: bank e-receipts often contain one and a link cannot run anything.
    private const PDF_BLOCKED = [
        '/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/EmbeddedFiles', '/RichMedia',
        '/XFA', '/SubmitForm', '/ImportData', '/GoToR', '/GoToE', '/AA', '/Encrypt',
    ];

    // Markers of scripts or server code that have no place in a slip.
    private const CODE_MARKERS = ['<?php', '<?=', '<script', '<html', '<svg', '<iframe', 'javascript:', 'eval('];

    /** Validates, cleans and stores the slip. Returns the stored path on the local disk. */
    public function store(UploadedFile $file): string
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            $this->fail('The file could not be uploaded. Please try again with a file under 5 MB.');
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            $this->fail('The file is empty.');
        }

        if (str_starts_with($bytes, '%PDF-')) {
            return $this->storePdf($bytes);
        }

        return $this->storeImage($file->getRealPath(), $bytes);
    }

    private function storeImage(string $path, string $bytes): string
    {
        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $this->fail('Please upload a photo (JPG, PNG, WebP) or a PDF of the payment slip.');
        }

        [$width, $height] = $info;
        if ($width < 50 || $height < 50 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            $this->fail('That image size is not supported. Please upload a normal photo or screenshot of the slip.');
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            $this->fail('The image could not be read. Please upload a different photo of the slip.');
        }

        // Re-draw onto a fresh white canvas (also flattens PNG transparency), scaled down if huge.
        $scale = min(1, self::SAVE_SIDE / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));
        $clean = imagecreatetruecolor($w, $h);
        imagefill($clean, 0, 0, imagecolorallocate($clean, 255, 255, 255));
        imagecopyresampled($clean, $source, 0, 0, 0, 0, $w, $h, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($clean, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($clean);

        return $this->put($jpeg, 'jpg');
    }

    private function storePdf(string $bytes): string
    {
        if (! str_contains(substr($bytes, -2048), '%%EOF')) {
            $this->fail('The PDF looks damaged or incomplete. Please save it again and re-upload.');
        }

        // Look inside compressed streams too, and undo #xx escapes used to disguise names (/J#61vaScript).
        $content = $bytes;
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $streams)) {
            foreach (array_slice($streams[1], 0, 500) as $stream) {
                $inflated = @gzuncompress($stream) ?: @gzinflate($stream);
                if ($inflated !== false && $inflated !== '') {
                    $content .= "\n".substr($inflated, 0, 2_000_000);
                }
            }
        }
        $content = preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($m) => chr(hexdec($m[1])), $content);

        foreach (self::PDF_BLOCKED as $name) {
            // Match the whole PDF name: /JS must not also match /JSON-like longer names.
            if (preg_match('#'.preg_quote($name, '#').'(?![A-Za-z0-9])#', $content)) {
                $this->fail('For security we only accept plain PDF slips (no scripts, attachments, password protection or interactive actions). Please upload a photo or screenshot of the slip instead.');
            }
        }

        $this->rejectCode($content);

        return $this->put($bytes, 'pdf');
    }

    private function rejectCode(string $content): void
    {
        $lower = strtolower($content);
        foreach (self::CODE_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                $this->fail('This file contains content that is not allowed. Please upload a photo or screenshot of the slip.');
            }
        }
    }

    private function put(string $contents, string $extension): string
    {
        $path = self::DIR.'/'.Str::random(40).'.'.$extension;
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['slip' => $message]);
    }
}
