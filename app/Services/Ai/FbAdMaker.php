<?php

namespace App\Services\Ai;

use App\Models\Product;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Facebook ad posts for a product.
 *
 * Gemini writes the ad copy (spoken Sinhala by default) and draws a text-free
 * scene picture. The words and the logo are put on in the browser, because
 * Sinhala needs proper text shaping (vowel signs, conjuncts) that image models
 * and PHP GD both get wrong, while the browser renders it correctly.
 */
class FbAdMaker
{
    public const DIR = 'uploads/ads';

    public const LANGUAGES = [
        'si' => 'සිංහල (spoken)',
        'mix' => 'සිංහල + English',
        'en' => 'English',
    ];

    /** Headline looks drawn by the ad page (gradient, outline, glow, 3D…). */
    public const STYLES = ['promo' => 'Promo', 'pop' => 'Bold pop', 'fire' => 'Fire', 'neon' => 'Neon', 'sticker' => 'Sticker', '3d' => '3D', 'clean' => 'Clean'];

    /** Icons the ad page can draw next to a feature (keep in sync with ICONS in fb-ad.blade.php). */
    public const ICONS = ['laptop', 'chat', 'truck', 'shield', 'bolt', 'gift', 'tag', 'percent', 'wrench', 'chip', 'drive', 'battery', 'keyboard', 'mouse', 'headphones', 'wifi', 'clock', 'star', 'phone', 'cart', 'check'];

    public const LAYOUTS = ['tech' => 'Tech promo', 'poster' => 'Poster', 'classic' => 'Classic'];

    /** Admin-uploaded Sinhala Unicode fonts for the ads. */
    public const FONT_DIR = 'uploads/fonts';

    public function __construct(private GeminiClient $gemini) {}

    /** Ad copy + colours for one idea. */
    public function copy(Product $product, string $idea, string $language, bool $withPrice): array
    {
        $price = $withPrice && $product->price !== null ? $product->priceLabel() : null;
        $was = $price && $product->compare_at_price > $product->price ? money($product->compare_at_price) : null;

        $prompt = implode("\n", array_filter([
            "Product: {$product->name}",
            $product->brand ? "Brand: {$product->brand}" : null,
            $product->summary ? "About it: {$product->summary}" : null,
            $product->specification ? "Specification: {$product->specification}" : null,
            $product->warranty ? "Warranty: {$product->warranty}" : null,
            $price ? "Price: {$price}".($was ? " (was {$was})" : '') : 'Do not mention a price.',
            '',
            'The shop owner\'s idea for this ad (follow it closely, it decides the angle, offer and mood):',
            $idea !== '' ? $idea : '(none given: make a strong, friendly product ad that drives WhatsApp orders)',
            '',
            'Language: '.$this->languageRule($language),
            'Shop WhatsApp number for the caption: '.config('speedtech.phone_display'),
        ], fn ($line) => $line !== null));

        $data = $this->gemini->json($this->systemPrompt(), $prompt, $this->schema(), $this->reference($product));

        $str = fn ($key, $max) => Str::limit(trim((string) ($data[$key] ?? '')), $max, '');

        return [
            'headline' => $str('headline', 90),
            'subline' => $str('subline', 160),
            'offer' => $str('offer', 40),
            'hero' => $str('hero', 30),
            'features' => collect($data['features'] ?? [])
                ->map(fn ($f) => [
                    'icon' => in_array($f['icon'] ?? '', self::ICONS, true) ? $f['icon'] : 'check',
                    'text' => Str::limit(trim((string) ($f['text'] ?? '')), 50, ''),
                ])
                ->filter(fn ($f) => $f['text'] !== '')->take(3)->values()->all(),
            'price_note' => $str('price_note', 16),
            'hook' => $str('hook', 60),
            'freebie' => $str('freebie', 70),
            'cards' => collect($data['cards'] ?? [])->take(2)->map(fn ($c) => [
                'title' => Str::limit(trim((string) ($c['title'] ?? '')), 30, ''),
                'points' => collect($c['points'] ?? [])->map(fn ($p) => Str::limit(trim((string) $p), 40, ''))->filter()->take(3)->values()->all(),
            ])->values()->all(),
            'caption' => $str('caption', 2000),
            'scene' => $str('scene', 600),
            'bg' => $this->hex($data['bg_color'] ?? '', '#101318'),
            'accent' => $this->hex($data['accent_color'] ?? '', '#ed171f'),
            'hl' => $this->hex($data['highlight_color'] ?? '', '#ffd400'),
            'style' => array_key_exists($data['style'] ?? '', self::STYLES) ? $data['style'] : 'promo',
            'price' => $price,
            'was' => $was,
        ];
    }

    /**
     * Text-free square scene for the ad, saved under public/uploads/ads.
     *
     * @return string path relative to public/
     */
    public function scene(Product $product, string $scene, string $bg, string $idea = '', string $layout = 'poster', string $hl = '#ffd400'): string
    {
        $reference = $this->reference($product);
        $poster = $layout === 'poster';
        $tech = $layout === 'tech';

        $prompt = implode("\n", array_filter([
            "Eye-catching Facebook advertisement photo for: {$product->name}.",
            $reference ? 'The attached image shows the real product. Keep its true shape, colour and details exactly, but ignore its background, any logo and any watermark.' : null,
            $idea !== '' ? "Ad idea from the shop: {$idea}" : null,
            $scene !== '' ? "Scene: {$scene}" : null,
            $tech
                ? "Square commercial hero shot: an extra-large, ultra-detailed, studio-lit 3D-style product shot of the product, angled dynamically toward the viewer, dominating the centre and occupying about 55% of the frame, resting on a reflective dark glass pedestal with subtle neon cyan and magenta rim lighting. Background: deep dark-navy and cosmic purple tech backdrop with subtle circuit-board bokeh and soft light flares, getting darker and calmer towards every edge so text can be placed around it. Crisp commercial product photography, ultra-sharp."
                : ($poster
                ? "Wide hero shot: the product (and, if it suits the idea, one or two related devices beside it) stands on a dark glossy surface inside a large, glowing {$hl} neon light ring that circles the product like a halo platform, with soft {$hl} light streaks, bokeh and reflections. Background: deep, dark {$bg} studio fading to plain {$bg} at every edge. The product group sits in the middle, filling about 55% of the width, with empty dark space above and below it."
                : "Background colour: mainly {$bg}, as a smooth studio backdrop with soft light, gentle depth and a few tasteful props or light effects that suit the idea. The outer edges of the picture must be calm, plain {$bg} with nothing important there, because the picture is blended into a {$bg} ad layout."),
            $poster || $tech
                ? 'Product: photorealistic, sharp, premium and desirable, with glossy reflections.'
                : 'Product: photorealistic, sharp, centred, filling about 60% of the frame, with a soft natural shadow. Make it look premium and desirable.',
            'Absolutely no text, letters, numbers, prices, logos, watermarks, stickers or people\'s faces anywhere in the picture. Headlines and the logo are added later.',
        ]));

        $image = $this->gemini->image($prompt, $reference, $poster ? '16:9' : '1:1');

        File::ensureDirectoryExists(public_path(self::DIR));
        $this->prune($product);

        $src = @imagecreatefromstring($image['data']);
        if (! $src) {
            throw new RuntimeException('The AI picture could not be read. Please try again.');
        }
        $file = self::DIR.'/ad-'.$product->id.'-'.Str::lower(Str::random(8)).'.jpg';
        imagejpeg($src, public_path($file), 92);
        imagedestroy($src);

        return $file;
    }

    /** The product's own uploaded image (local only, so the browser canvas can export it). */
    public function productImage(Product $product): ?string
    {
        if (! $product->image || Str::startsWith($product->image, ['http://', 'https://']) || ! is_file(public_path($product->image))) {
            return null;
        }

        return $product->image;
    }

    /** @return list<array{family: string, url: string, file: string}> */
    public function fonts(): array
    {
        return collect(File::glob(public_path(self::FONT_DIR.'/*.{ttf,otf,woff,woff2}'), GLOB_BRACE) ?: [])
            ->sort()
            ->map(fn ($path) => [
                'family' => Str::of(pathinfo($path, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->title()->value(),
                'url' => parse_url(asset(self::FONT_DIR.'/'.basename($path)), PHP_URL_PATH),
                'file' => basename($path),
            ])->values()->all();
    }

    /** True when the bytes really are a font file (not a script renamed to .ttf). */
    public static function isFont(string $bytes): bool
    {
        return in_array(substr($bytes, 0, 4), ["\x00\x01\x00\x00", 'true', 'OTTO', 'wOFF', 'wOF2'], true);
    }

    private function reference(Product $product): array
    {
        $path = $this->productImage($product);

        return $path ? [['mime' => mime_content_type(public_path($path)) ?: 'image/jpeg', 'data' => file_get_contents(public_path($path))]] : [];
    }

    /** Keep only the newest few scene pictures per product. */
    private function prune(Product $product): void
    {
        collect(File::glob(public_path(self::DIR.'/ad-'.$product->id.'-*.jpg')))
            ->sortByDesc(fn ($f) => filemtime($f))
            ->slice(5)
            ->each(fn ($f) => File::delete($f));
    }

    private function languageRule(string $language): string
    {
        return match ($language) {
            'en' => 'Write everything in simple, friendly English for Sri Lankan buyers.',
            'mix' => 'Headline, hero word and badge in spoken Sinhala (Sinhala Unicode script). Subline and features in short English. Caption in spoken Sinhala with key English tech words.',
            default => 'Everything in natural SPOKEN Sinhala (කථන සිංහල) written in Sinhala Unicode script, exactly how popular Sri Lankan Facebook shop pages write: warm, direct, using "ඔයාගේ", "දැන්ම", "ගන්න", "එක" style everyday words. Never formal or literary Sinhala (avoid "ඔබගේ", "මිලදී ගැනීමට", "සඳහා", "තිබේ", "ලබා ගත හැක" style; say "ඔයාගේ", "ගන්න", "තියෙනවා", "පුළුවන්"). Keep product names, brands and common tech words (Laptop, Keyboard, SSD, Battery, Warranty, WhatsApp, Delivery, Original) in English letters, as people really say them. Never write Singlish (Sinhala words in Latin letters).',
        };
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You are the top social media ad copywriter for SpeedTech IT Solutions, a Sri Lankan laptop spare parts, computer accessories and repair shop that sells island-wide with WhatsApp ordering.
You write scroll-stopping Facebook ad posts that sound like a real local shop talking to customers, never like a translation.

Rules:
The ad is a vibrant, high-converting Sri Lankan computer-shop Facebook poster. The same copy feeds several layouts, so fill every field:
- hook: the top hook in a red paint-brush banner, max 5 words, punchy spoken Sinhala with "!" (e.g. "සුපිරිම Offer එකක්!", "මේ අවස්ථාව මග අරින්න එපා!", "අලුත්ම Stock ආවා!").
- headline: the tilted sub-headline under the hook, max 6 words (e.g. "Gaming & Office Laptops *අඩුම මිලට!*"). Wrap the key words in *asterisks*; they print in yellow, the rest in white.
- offer: the yellow comic sticker, max 3 words, often English capitals (e.g. "LIMITED STOCK!", "100% BRAND NEW", "අලුත්ම Stock"). Empty if nothing fits.
- freebie: the glowing badge next to the price: warranty and/or free gifts, max 7 words (e.g. "මාස 6ක Warranty + Free Installation"). Only facts from the idea or product.
- cards: exactly 2 spec cards. Card 1 title like "Core Specs" or "ප්‍රධාන විශේෂාංග" with 3 short real specs of this product (max 5 words each). Card 2 title like "ඇයි අපෙන් ගන්නේ?" with 3 shop benefits (e.g. "Island-wide Cash on Delivery", "Genuine Warranty", "ඉක්මන් Delivery"). Never invent specs: if unsure, use benefits that are true.
Other layouts also use: a HERO word, feature boxes, a subline offer line. Keep all text short so the picture stays big.
- headline: max 6 words, the top line that sets up the hero word (e.g. "සියලුම POS පද්ධති සමග", "ඔයාගේ පරණ Laptop එකට"). You may wrap one key word in *asterisks* to print it in the highlight colour.
- hero: the HUGE shout word in the middle of the poster: 1 or 2 words only, punchy spoken Sinhala, often with "!" (e.g. "නොමිලේ!", "අලුත්ම!", "Super Fast!", "අඩුම මිලට!", "ඉක්මනින්!"). It must be true to the idea and the facts.
- features: 2 or 3 short feature boxes (max 4 words each, e.g. "Website එකක්", "Island-wide Delivery", "මාස 6ක Warranty"), each with the best matching icon.
- subline: max 8 words, an offer line under the price (e.g. "*FREE* Installation", "*Island-wide* Delivery"). Wrap the key word in *asterisks*.
- price_note: 1 short word under or beside the price, e.g. "පමණයි!", "විතරයි" or "ONLY!". Empty if there is no price.
- Every element must say something different: never repeat the same fact in the badge, headline, hero, features and subline.
- offer: max 4 words for a badge, e.g. "විශේෂ මිලට", "අලුත්ම Stock", "Island-wide Delivery". Empty string if the idea has no offer.
- caption: the Facebook post text that goes with the image: 3 to 6 short lines, a few fitting emojis, ends with a call to WhatsApp the shop number given and 3 to 5 hashtags.
- Spoken Sinhala only. These formal words are BANNED anywhere in the ad or caption: ඔබ, ඔබගේ, ඔබේ, තිබේ, ලබාගත හැක, ලබා ගත හැක, ලබා ගැනීමට, මිලදී ගැනීමට, සඳහා, වේ, හැකිය, කරනු ලැබේ. Use instead: ඔයා, ඔයාගේ, තියෙනවා, ගන්න පුළුවන්, ගන්න, එක්ක, වෙනවා, පුළුවන්. Before answering, re-read every Sinhala line and rewrite anything that sounds like a newspaper or a government notice.
- Sinhala must be perfectly spelled in correct Unicode (correct vowel signs, hal kirima, ZWJ conjuncts like ශ්‍රී). Re-read every Sinhala word before answering.
- Never invent discounts, prices, free gifts, warranty, speed multipliers ("10X") or other numbers that are not in the shop's idea or product facts. Benefits without made-up numbers are fine.
- The price (if any) is already shown on a separate price tag: do not repeat it in the headline, hero, subline or features.
- scene: in English, describe a picture background and props that fit the idea and product (no text in it).
- bg_color: a hex colour for the ad background. For tech products use a deep dark navy like #0B1033 (the tech promo layout blends it into cosmic purple).
- accent_color: the paint-brush banner and price slash colour. SpeedTech red #E3121B is the default.
- highlight_color: a bright, eye-catching hex colour for the *highlighted* words that contrasts strongly with bg_color and accent_color (e.g. sunny yellow #FFD400 or electric cyan #22E1FF on dark backgrounds).
- style: the headline look that best fits the idea. Use "promo" (the classic Sri Lankan promo poster look: heavy white text, a huge yellow-style hero word with a dark outline and glow) unless the idea clearly suits another: "pop" (bold white with outlined colour keywords, all-round), "fire" (hot orange gradient, sales and big offers), "neon" (glowing, gaming and night looks, dark backgrounds only), "sticker" (words on colourful label boxes, playful and friendly), "3d" (bold 3D letters, premium and strong), "clean" (simple and elegant).
TXT;
    }

    private function schema(): array
    {
        $str = ['type' => 'STRING'];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'headline' => $str, 'hero' => $str, 'subline' => $str, 'offer' => $str, 'price_note' => $str,
                'features' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['icon' => ['type' => 'STRING', 'enum' => self::ICONS], 'text' => $str],
                    'required' => ['icon', 'text'],
                ]],
                'hook' => $str, 'freebie' => $str,
                'cards' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['title' => $str, 'points' => ['type' => 'ARRAY', 'items' => $str]],
                    'required' => ['title', 'points'],
                ]],
                'caption' => $str, 'scene' => $str,
                'bg_color' => $str, 'accent_color' => $str, 'highlight_color' => $str,
                'style' => ['type' => 'STRING', 'enum' => array_keys(self::STYLES)],
            ],
            'required' => ['hook', 'freebie', 'cards', 'headline', 'hero', 'subline', 'offer', 'features', 'price_note', 'caption', 'scene', 'bg_color', 'accent_color', 'highlight_color', 'style'],
        ];
    }

    private function hex(string $value, string $fallback): string
    {
        return preg_match('/^#?([0-9a-f]{6})$/i', trim($value), $m) ? '#'.strtolower($m[1]) : $fallback;
    }
}
