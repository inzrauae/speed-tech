<?php

namespace App\Services\Ai;

use Closure;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Finds the manufacturer's original product photo on the web: Google Search (through
 * Gemini) finds the official product page, the photo is taken from that page
 * (og:image, product data, gallery), and the AI only checks which photo shows
 * exactly this product. No picture is generated, so ports and pins stay real.
 */
class ManufacturerPhotoFinder
{
    private const MAX_PAGES = 6;
    private const MAX_IMAGES = 12;
    private const CHECK_IMAGES = 4;
    private const MIN_SIDE = 300;
    /** Seconds for all page and photo downloads together, so one slow site cannot hang the admin. */
    private const BUDGET = 60;
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /** Tests swap the DNS lookup: fn (string $host) => list<string> of IPs. */
    public static ?Closure $resolveUsing = null;

    private float $deadline = 0;

    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  string  $brand  manufacturer, to recognise its own website
     * @param  string  $query  exact product words (brand, model, part number)
     * @param  string|null  $url  a page or image link chosen by staff: used instead of searching
     * @return array{mime: string, data: string, source: string}
     */
    public function find(string $name, string $brand = '', string $query = '', string $specs = '', ?string $url = null): array
    {
        $pages = $url ? [$url] : $this->searchPages($query ?: $name, $name, $specs);
        $this->deadline = microtime(true) + self::BUDGET;

        $candidates = [];
        foreach (array_slice(array_unique($pages), 0, self::MAX_PAGES) as $page) {
            foreach ($this->imagesOn($page, $name) as $image) {
                $candidates[$image['url']] = $image;
                if (count($candidates) >= self::MAX_IMAGES) {
                    break 2;
                }
            }
        }

        $photos = [];
        foreach ($candidates as $candidate) {
            if ($photo = $this->download($candidate['url'])) {
                $photos[] = $photo + ['source' => $candidate['page'], 'score' => $this->score($photo, $candidate, $brand ?: Str::before(trim($name), ' '))];
            }
        }
        if (! $photos) {
            throw new RuntimeException($url
                ? 'No usable product photo was found at that link. Paste the direct image link, or upload the photo.'
                : 'No manufacturer photo was found online for this product. Paste a link to the product page, or upload a photo.');
        }

        usort($photos, fn ($a, $b) => $b['score'] <=> $a['score']);
        $photos = array_slice($photos, 0, self::CHECK_IMAGES);
        $best = $this->pick($photos, $name, $specs, (bool) $url);

        return ['mime' => $best['mime'], 'data' => $best['data'], 'source' => $best['source']];
    }

    /** @return list<string> product page or image URLs, best first */
    private function searchPages(string $query, string $name, string $specs): array
    {
        $result = $this->gemini->search(implode("\n", array_filter([
            "Find the original manufacturer product photo for this exact product: {$query}",
            $query !== $name ? "Shop's product name: {$name}" : null,
            $specs ? "Specification: {$specs}" : null,
            'Search the web. List the pages that show this EXACT model (same part number / variant, same connectors), best first:',
            "1) the product page on the manufacturer's own official website; 2) the manufacturer's support or datasheet page; 3) large well-known retailers (e.g. Amazon, Newegg, B&H) only if the manufacturer has no page.",
            'If you found direct links to the product photo files (.jpg, .png, .webp), list them first.',
            'Reply with URLs only, one per line, at most 6. No other text.',
        ])));

        preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $result['text'], $m);
        $urls = array_map(fn ($u) => rtrim($u, '.,;'), $m[0]);

        return array_values(array_unique([...$urls, ...$result['sources']]));
    }

    /**
     * Product photos on a page, or the URL itself when it is an image.
     *
     * @return list<array{url: string, page: string, kind: string}>
     */
    private function imagesOn(string $url, string $name): array
    {
        if (preg_match('/\.(jpe?g|png|webp)(\?|$)/i', parse_url($url, PHP_URL_PATH) ?? '')) {
            return [['url' => $url, 'page' => $url, 'kind' => 'direct']];
        }

        $page = $this->fetch($url, 3_000_000);
        if (! $page) {
            return [];
        }
        if (str_starts_with($page['type'], 'image/')) {
            return [['url' => $page['url'], 'page' => $page['url'], 'kind' => 'direct']];
        }
        if (! str_contains($page['type'], 'html')) {
            return [];
        }

        $html = $page['body'];
        $base = $page['url'];
        $found = [];
        $add = function (?string $src, string $kind) use (&$found, $base) {
            $abs = $src ? $this->absolute(html_entity_decode(trim($src)), $base) : null;
            if ($abs && ! preg_match('/\.(svg|gif)(\?|$)|logo|icon|sprite|placeholder|banner/i', $abs)) {
                $found[$abs] ??= ['url' => $abs, 'page' => $base, 'kind' => $kind];
            }
        };

        // Product data (JSON-LD) first: it names the product's own photos.
        preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $scripts);
        foreach ($scripts[1] as $json) {
            foreach ($this->jsonImages(json_decode(html_entity_decode($json), true)) as $src) {
                $add($src, 'product-data');
            }
        }

        foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src'] as $prop) {
            if (preg_match('#<meta[^>]+(?:property|name)=["\']'.preg_quote($prop, '#').'["\'][^>]*content=["\']([^"\']+)#i', $html, $mm)
                || preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']'.preg_quote($prop, '#').'["\']#i', $html, $mm)) {
                $add($mm[1], 'og');
            }
        }

        // Gallery pictures whose file name or alt text mentions the model.
        $tokens = $this->modelTokens($name);
        if ($tokens) {
            preg_match_all('#<img\b[^>]*>#i', $html, $imgs);
            foreach (array_slice($imgs[0], 0, 400) as $tag) {
                preg_match('#\b(?:data-zoom-image|data-large|data-src|src)=["\']([^"\']+)#i', $tag, $src);
                preg_match('#\balt=["\']([^"\']*)#i', $tag, $alt);
                $hay = Str::lower(($src[1] ?? '').' '.($alt[1] ?? ''));
                if (! empty($src[1]) && collect($tokens)->contains(fn ($t) => str_contains($hay, $t))) {
                    $add($src[1], 'gallery');
                }
            }
        }

        return array_slice(array_values($found), 0, 5);
    }

    /** @return list<string> */
    private function jsonImages(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $key => $value) {
            if ($key === 'image') {
                foreach ((array) $value as $img) {
                    $out[] = is_array($img) ? ($img['url'] ?? $img['contentUrl'] ?? null) : $img;
                }
            } elseif (is_array($value)) {
                $out = [...$out, ...$this->jsonImages($value)];
            }
        }

        return array_values(array_filter($out, 'is_string'));
    }

    /** Words in the name that look like a model or part number (contain a digit). */
    private function modelTokens(string $name): array
    {
        return collect(preg_split('/[\s,\/()]+/', Str::lower($name)))
            ->filter(fn ($t) => strlen($t) >= 3 && preg_match('/\d/', $t) && ! preg_match('/^\d+(gb|tb|mhz|w|v|mm|hz|inch|")?$/', $t))
            ->values()->all();
    }

    /** @return array{mime: string, data: string, w: int, h: int, white: float}|null */
    private function download(string $url): ?array
    {
        $file = $this->fetch($url, 15_000_000);
        if (! $file || ! str_starts_with($file['type'], 'image/')) {
            return null;
        }
        $img = @imagecreatefromstring($file['body']);
        if (! $img) {
            return null;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $white = self::whiteBorder($img);
        imagedestroy($img);

        return min($w, $h) >= self::MIN_SIDE && max($w, $h) / min($w, $h) <= 3
            ? ['mime' => $file['type'], 'data' => $file['body'], 'w' => $w, 'h' => $h, 'white' => $white]
            : null;
    }

    /** Clean white-background shots from the manufacturer first. */
    private function score(array $photo, array $candidate, string $brand): float
    {
        $brand = Str::lower(preg_replace('/[^a-z0-9]/i', '', $brand));
        $host = Str::lower((string) parse_url($candidate['page'], PHP_URL_HOST));

        return $photo['white'] * 2
            + min(1, min($photo['w'], $photo['h']) / 1000)
            + (strlen($brand) >= 2 && str_contains($host, $brand) ? 1 : 0)
            + ['product-data' => 0.6, 'gallery' => 0.5, 'direct' => 0.4, 'og' => 0.3][$candidate['kind']];
    }

    /** The AI looks at the best few and says which one is exactly this product. */
    private function pick(array $photos, string $name, string $specs, bool $staffLink): array
    {
        if (count($photos) === 1 && $staffLink) {
            return $photos[0];
        }

        try {
            $answer = $this->gemini->json(
                'You check product photos for a computer parts shop. Be strict about the exact model.',
                implode("\n", array_filter([
                    'Product: '.$name,
                    $specs ? 'Specification: '.$specs : null,
                    'The attached photos are numbered from 0 in the order given. Which ONE is the best shop photo of exactly this product?',
                    'It must show the same model and variant: same form factor, connectors, number of pins/ports, colours and size as the product and specification.',
                    'Reject: a different or similar-looking model; lifestyle or scene photos; collages; banners or pictures mostly of text; drawings; photos with another shop\'s logo or a watermark across the product; packaging only.',
                    'Prefer: a sharp photo of the product alone on a plain white background, showing its connectors.',
                    'Answer best = the photo number, or -1 if none of them is right.',
                ])),
                ['type' => 'OBJECT', 'properties' => ['best' => ['type' => 'INTEGER'], 'reason' => ['type' => 'STRING']], 'required' => ['best']],
                array_map(fn ($p) => ['mime' => $p['mime'], 'data' => $p['data']], $photos),
            );
        } catch (Throwable) {
            return $photos[0]; // the check is a bonus: fall back to the best-scored photo
        }

        $best = (int) ($answer['best'] ?? -1);
        if (! isset($photos[$best])) {
            throw new RuntimeException('Photos were found online, but none clearly shows this exact product'
                .(! empty($answer['reason']) ? ' ('.Str::limit($answer['reason'], 160).')' : '')
                .'. Paste a link to the right product page, or upload a photo.');
        }

        return $photos[$best];
    }

    /** Share of the picture's outer border that is plain white (0..1). */
    public static function whiteBorder(GdImage $img): float
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $points = [];
        for ($i = 0; $i < 60; $i++) {
            $x = (int) ($i * ($w - 1) / 59);
            $y = (int) ($i * ($h - 1) / 59);
            array_push($points, [$x, 1], [$x, $h - 2], [1, $y], [$w - 2, $y]);
        }
        $white = 0;
        foreach ($points as [$x, $y]) {
            $c = imagecolorsforindex($img, imagecolorat($img, max(0, $x), max(0, $y)));
            if ($c['alpha'] > 100 || min($c['red'], $c['green'], $c['blue']) >= 235) {
                $white++;
            }
        }

        return $white / count($points);
    }

    /**
     * GET a public web address. Blocks local/private addresses (also after redirects)
     * and stops at $max bytes, so a search result can never reach the server's own network.
     *
     * @return array{url: string, type: string, body: string}|null
     */
    private function fetch(string $url, int $max): ?array
    {
        for ($hop = 0; $hop < 4; $hop++) {
            $left = $this->deadline - microtime(true);
            if ($left < 2) {
                return null;
            }
            $parts = parse_url($url);
            $host = $parts['host'] ?? null;
            if (! in_array(Str::lower($parts['scheme'] ?? ''), ['http', 'https'], true) || ! $host || isset($parts['user'])) {
                return null;
            }
            $ips = self::$resolveUsing ? (self::$resolveUsing)($host) : (gethostbynamel($host) ?: []);
            if (! $ips || collect($ips)->contains(fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                return null;
            }
            $port = $parts['port'] ?? (Str::lower($parts['scheme']) === 'https' ? 443 : 80);

            try {
                $response = Http::withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html,image/avif,image/webp,image/*,*/*;q=0.8'])
                    ->withOptions([
                        'allow_redirects' => false,
                        'force_ip_resolve' => 'v4',
                        // Connect to the IP checked above (no second DNS lookup).
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ips[0]}"]],
                        'on_headers' => function ($r) use ($max) {
                            if ((int) $r->getHeaderLine('Content-Length') > $max) {
                                throw new RuntimeException('Too large');
                            }
                        },
                    ])
                    ->timeout((int) min(10, $left))->connectTimeout(5)
                    ->get($url);
            } catch (Throwable) {
                return null;
            }

            if ($response->redirect() && $response->header('Location')) {
                $url = $this->absolute($response->header('Location'), $url) ?? '';
                continue;
            }
            if (! $response->successful() || strlen($response->body()) > $max) {
                return null;
            }

            return ['url' => $url, 'type' => Str::lower(trim(Str::before($response->header('Content-Type'), ';'))), 'body' => $response->body()];
        }

        return null;
    }

    private function absolute(string $src, string $base): ?string
    {
        if (str_starts_with($src, 'data:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        $b = parse_url($base);
        if (! isset($b['scheme'], $b['host'])) {
            return null;
        }
        $root = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($src, '//')) {
            return $b['scheme'].':'.$src;
        }
        if (str_starts_with($src, '/')) {
            return $root.$src;
        }

        return $root.rtrim(Str::beforeLast($b['path'] ?? '/', '/'), '/').'/'.$src;
    }
}
