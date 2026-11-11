<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal client for the Gemini API (generativelanguage.googleapis.com).
 * Settings live in config/services.php → gemini.
 */
class GeminiClient
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function configured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Ask the text model for JSON that matches $schema (OpenAPI subset).
     *
     * @param  array<int, array{mime: string, data: string}>  $images  raw bytes, not base64
     */
    public function json(string $system, string $prompt, array $schema, array $images = []): array
    {
        $response = $this->call(config('services.gemini.text_model'), [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => $this->parts($prompt, $images)]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
                'temperature' => 0.6,
                'thinkingConfig' => ['thinkingLevel' => 'low'],
            ],
        ]);

        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn ($p) => ! empty($p['thought']))
            ->pluck('text')->filter()->implode('');
        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new RuntimeException('The AI returned an unreadable answer. Please try again.');
        }

        return $data;
    }

    /**
     * Generate one image. Returns ['mime' => ..., 'data' => raw bytes].
     *
     * @param  array<int, array{mime: string, data: string}>  $images  reference photos
     */
    public function image(string $prompt, array $images = [], string $aspectRatio = '1:1'): array
    {
        $response = $this->call(config('services.gemini.image_model'), [
            'contents' => [['role' => 'user', 'parts' => $this->parts($prompt, $images)]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => ['aspectRatio' => $aspectRatio],
            ],
        ]);

        foreach ($response->json('candidates.0.content.parts', []) as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if ($inline && ! empty($inline['data'])) {
                return ['mime' => $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png', 'data' => base64_decode($inline['data'])];
            }
        }

        $reason = $response->json('candidates.0.finishReason') ?? $response->json('promptFeedback.blockReason');
        throw new RuntimeException('The AI did not return an image'.($reason ? " ({$reason})" : '').'. Try different image details.');
    }

    private function parts(string $prompt, array $images): array
    {
        $parts = [];
        foreach ($images as $image) {
            $image = $this->shrink($image);
            $parts[] = ['inlineData' => ['mimeType' => $image['mime'], 'data' => base64_encode($image['data'])]];
        }
        $parts[] = ['text' => $prompt];

        return $parts;
    }

    /** Phone photos can be 5+ MB; 1024px is plenty for the AI and uploads much faster. */
    private function shrink(array $image): array
    {
        $src = @imagecreatefromstring($image['data']);
        if (! $src) {
            return $image;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 1024 / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 85);
        imagedestroy($src);
        imagedestroy($dst);

        return ['mime' => 'image/jpeg', 'data' => ob_get_clean()];
    }

    /** Tries each model in turn (comma-separated in .env) while Google is overloaded or too slow. */
    private function call(string $models, array $payload): Response
    {
        if (! $this->configured()) {
            throw new RuntimeException('Add GEMINI_API_KEY to the .env file to use the AI writer.');
        }

        $response = null;
        foreach (array_filter(array_map('trim', explode(',', $models))) as $model) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    // Some networks have a broken IPv6 route to Google (requests just hang).
                    ->withOptions(['force_ip_resolve' => 'v4'])
                    ->timeout(config('services.gemini.timeout', 90))
                    ->connectTimeout(15)
                    ->acceptJson()
                    // "High demand" (503) and internal errors are usually gone within seconds.
                    ->retry(2, 1500, fn ($e) => $e instanceof RequestException && in_array($e->response->status(), [500, 503], true), throw: false)
                    ->post(self::BASE.rawurlencode($model).':generateContent', $payload);
            } catch (ConnectionException) {
                $response = null; // timed out: try the next model

                continue;
            }

            if (! in_array($response->status(), [500, 503], true)) {
                break;
            }
        }

        if (! $response) {
            throw new RuntimeException('Google AI took too long to answer. Please try again in a minute.');
        }
        if ($response->failed()) {
            throw new RuntimeException($this->explain($response, $model));
        }

        return $response;
    }

    /** Short, admin-friendly error text. */
    private function explain(Response $response, string $model): string
    {
        $message = (string) $response->json('error.message', $response->body());

        return match (true) {
            $response->status() === 429 && str_contains($message, 'limit: 0') => "Your Google AI plan has no quota for {$model}. Turn on billing for the API key's project in Google AI Studio (aistudio.google.com) to use it.",
            $response->status() === 429 => 'The Google AI usage limit was reached. Wait a minute and try again.',
            in_array($response->status(), [500, 503], true) => 'Google AI is busy right now. Please try again in a minute.',
            in_array($response->status(), [401, 403], true) => 'Google rejected the API key. Check GEMINI_API_KEY in the .env file.',
            $response->status() === 404 => "The AI model {$model} is not available. Set another model in the .env file.",
            default => 'AI request failed: '.str($message)->before("\n")->limit(220),
        };
    }
}
