<?php

namespace App\Services\Ai;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\ModelGeneration;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * The "AI product writer" agent: from a product title (and optionally a photo)
 * it writes SEO copy, picks or creates the category / brand / laptop models,
 * and describes the product so ProductImageMaker can draw it.
 */
class ProductWriter
{
    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  string|null  $type  accessory | spare_part | null (let the AI decide)
     * @param  array{mime: string, data: string}|null  $photo
     */
    public function write(string $title, ?string $type = null, ?array $photo = null, ?string $notes = null): array
    {
        $prompt = "Product title: {$title}\n"
            .($type ? 'Product type (fixed by the shop): '.$type."\n" : "Product type: decide (accessory or spare_part).\n")
            .($notes ? "Extra information from the shop: {$notes}\n" : '')
            .($photo ? "A photo of the actual product is attached. Use what you can see (colour, connectors, labels, part numbers) but do not invent details you cannot see.\n" : '')
            ."\nExisting shop categories (accessories): ".$this->names(Category::orderBy('name')->pluck('name'))
            ."\nExisting spare part types: ".$this->names(PartCategory::orderBy('name')->pluck('name'))
            ."\nExisting laptop brands: ".$this->names(Brand::orderBy('name')->pluck('name'));

        $data = $this->gemini->json($this->systemPrompt(), $prompt, $this->schema(), $photo ? [$photo] : []);

        return $this->clean($data, $title, $type);
    }

    /**
     * Find the category, brand and laptop models the AI chose. Anything missing
     * is added as "pending approval" (hidden from the store) for an admin to
     * approve, rename, replace or reject on the Pending approvals page.
     * Returns human-readable notes about anything that was suggested.
     */
    public function resolveTaxonomy(array $draft): array
    {
        $created = [];
        $result = ['category_id' => null, 'part_category_id' => null, 'generation_ids' => []];

        if ($draft['type'] === Product::TYPE_SPARE_PART) {
            $category = $this->findOrCreate(PartCategory::class, $draft['category'] ?: 'Other parts', $created, 'part type');
            $result['part_category_id'] = $category->id;

            foreach ($draft['compatible'] as $fit) {
                $brand = $this->findOrCreate(Brand::class, $fit['brand'], $created, 'laptop brand');
                $model = LaptopModel::where('brand_id', $brand->id)->get()->first(fn ($m) => $this->same($m->name, $fit['model']))
                    ?? tap(LaptopModel::create(['brand_id' => $brand->id, 'name' => $fit['model'], 'is_active' => false, 'pending_approval' => true]),
                        function ($m) use (&$created, $brand) { $created[] = "laptop model “{$brand->name} {$m->name}”"; });

                $matches = $this->matchGenerations($model->generations()->get(), $fit['years']);
                if ($matches->isEmpty()) {
                    $label = $fit['years'] ?: 'All years';
                    [$from, $to] = $this->years($label);
                    $matches = collect([ModelGeneration::create(['laptop_model_id' => $model->id, 'label' => $label, 'year_from' => $from, 'year_to' => $to, 'pending_approval' => true])]);
                    $created[] = "year “{$label}” for {$brand->name} {$model->name}";
                }
                array_push($result['generation_ids'], ...$matches->pluck('id'));
            }
        } else {
            $category = $this->findOrCreate(Category::class, $draft['category'] ?: 'Accessories', $created, 'shop category');
            $result['category_id'] = $category->id;
        }

        $result['generation_ids'] = array_values(array_unique($result['generation_ids']));
        $result['created'] = $created;

        return $result;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
        You are the product content writer for SpeedTech IT Solutions, a computer repair shop and online store in Kaduwela, Sri Lanka.
        It sells computer accessories (type "accessory") and laptop spare parts (type "spare_part": screens, keyboards, batteries, fans, hinges, chargers for specific laptop models).

        Write accurate, helpful, people-first product content in clear British/Sri Lankan English that follows Google Search Essentials and Google's helpful content guidance: written for shoppers first, original, specific to this exact product, no keyword stuffing, no exaggerated or unverifiable claims.
        - focus_keyword: the main search phrase a Sri Lankan shopper would type for this product (e.g. "Dell Inspiron 15 3520 keyboard"). 2-6 words.
        - name: a clean, searchable product name built from the title (brand, product, key spec, part number). Max 90 characters. Keep part numbers exactly as given.
        - summary: one sentence (max 160 characters) for product cards, containing the focus keyword naturally.
        - description: AT LEAST 400 words (aim for 450-600) of plain text (no HTML, no markdown except the "## " headings below). Count carefully; a short description is a failure. Structure:
            1. An opening of two paragraphs (3-4 sentences each) that uses the focus keyword in the first sentence, says what the product is, who it is for, and the common problems it solves.
            2. "## Key features" followed by 5-7 lines that each start with "• " (concrete benefits, not vague praise).
            3. "## Compatibility" (spare parts: which laptops it fits, and to match the part number / connector or send the laptop's model or service tag to SpeedTech on WhatsApp to confirm) or "## Who it is for" (accessories: typical uses and setups). 4-6 sentences.
            4. "## Installation and care" (spare parts: signs you need a replacement, what fitting involves, a safety tip, fitting service in Kaduwela) or "## Setup and use" (accessories: how to connect and get the best from it). 4-6 sentences.
            5. "## What's in the box" only if you are confident, otherwise skip it. Do not guess included cables or screws.
            6. "## Why buy from SpeedTech" — one short paragraph: genuine/quality-checked stock, island-wide delivery across Sri Lanka, collection or professional fitting at the Kaduwela store, help on WhatsApp.
           Separate every paragraph, heading and list with a blank line. Use related keywords and synonyms naturally (e.g. replacement, compatible, Sri Lanka, price in Sri Lanka). Never state prices or stock levels.
        - faqs: 3-5 genuinely useful questions shoppers ask about THIS product (fit, installation, warranty, delivery in Sri Lanka, differences between versions) with short, accurate answers (max 300 characters each). Do not promise specific warranty lengths or prices.
        - meta_title: 50-60 characters, starts with the focus keyword, no "| SpeedTech" suffix (the site adds the shop name).
        - meta_description: 140-155 characters, a persuasive summary with the focus keyword, "Sri Lanka" and a call to action (e.g. order online or ask on WhatsApp).
        - image_alt: 8-15 word description of the product photo for screen readers and Google Images, including the product name. No "image of".
        - specification: short comma-separated key specs (max 120 characters), e.g. 15.6", FHD 1920x1080, 30-pin eDP.
        - size, connector_type: only if they apply, else empty string.
        - tag: short card label in capitals, e.g. GENUINE, OEM-GRADE, NEW, BESTSELLER. Empty if unsure.
        - category: for accessories, a shop category; for spare parts, a spare part type. Reuse an existing name EXACTLY when one fits. Only suggest a new, short, general name (e.g. "Webcams", "Cooling Fan") when none fits.
        - brand: the product's manufacturer brand, or empty.
        - compatible: spare parts only (empty array for accessories). Laptop brand, model and years/generation the part fits, only when the title or photo makes it clear. Reuse existing brand names exactly. Max 6 entries.
        - image_prompt: a detailed visual description of what the physical product looks like (shape, colour, material, ports, angle), for an image generator. Do not describe text or logos on it.
        - review_notes: one or two short sentences telling the shop staff what to double-check (e.g. a guessed spec). Empty if nothing.
        Do not invent technical specifications you are not confident about; leave them out instead.
        TXT;
    }

    private function schema(): array
    {
        $str = ['type' => 'STRING'];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'type' => ['type' => 'STRING', 'enum' => array_keys(Product::TYPES)],
                'name' => $str, 'summary' => $str, 'description' => $str,
                'meta_title' => $str, 'meta_description' => $str,
                'focus_keyword' => $str, 'image_alt' => $str,
                'faqs' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['q' => $str, 'a' => $str],
                    'required' => ['q', 'a'],
                ]],
                'specification' => $str, 'size' => $str, 'connector_type' => $str, 'tag' => $str,
                'category' => $str, 'brand' => $str,
                'compatible' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['brand' => $str, 'model' => $str, 'years' => $str],
                    'required' => ['brand', 'model', 'years'],
                ]],
                'image_prompt' => $str, 'review_notes' => $str,
            ],
            'required' => ['type', 'name', 'summary', 'description', 'meta_title', 'meta_description', 'focus_keyword', 'image_alt', 'faqs', 'specification', 'category', 'image_prompt'],
        ];
    }

    /** Trim everything to the column sizes and fill gaps. */
    private function clean(array $d, string $title, ?string $type): array
    {
        $s = fn ($key, $max) => Str::limit(trim((string) ($d[$key] ?? '')), $max, '');
        $type = $type ?: (in_array($d['type'] ?? null, array_keys(Product::TYPES), true) ? $d['type'] : Product::TYPE_ACCESSORY);

        $compatible = [];
        if ($type === Product::TYPE_SPARE_PART) {
            foreach (array_slice((array) ($d['compatible'] ?? []), 0, 6) as $fit) {
                $brand = Str::limit(trim((string) ($fit['brand'] ?? '')), 60, '');
                $model = Str::limit(trim((string) ($fit['model'] ?? '')), 100, '');
                if ($brand !== '' && $model !== '') {
                    $compatible[] = ['brand' => $brand, 'model' => $model, 'years' => Str::limit(trim((string) ($fit['years'] ?? '')), 60, '')];
                }
            }
        }

        $faqs = [];
        foreach (array_slice((array) ($d['faqs'] ?? []), 0, 6) as $faq) {
            $q = Str::limit(trim((string) ($faq['q'] ?? '')), 200, '');
            $a = Str::limit(trim((string) ($faq['a'] ?? '')), 600, '');
            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }

        return [
            'type' => $type,
            'name' => $s('name', 200) ?: Str::limit($title, 200, ''),
            'summary' => $s('summary', 500),
            'description' => $s('description', 10000),
            'faqs' => $faqs,
            'meta_title' => Str::limit(trim(preg_replace('/\s*\|\s*SpeedTech.*$/i', '', (string) ($d['meta_title'] ?? ''))), 120, ''),
            'meta_description' => $s('meta_description', 300),
            'focus_keyword' => $s('focus_keyword', 100),
            'image_alt' => $s('image_alt', 200),
            'specification' => $s('specification', 255),
            'size' => $s('size', 255),
            'connector_type' => $s('connector_type', 255),
            'tag' => Str::upper($s('tag', 40)),
            'category' => $s('category', 100),
            'brand' => $s('brand', 60),
            'compatible' => $compatible,
            'image_prompt' => $s('image_prompt', 1500),
            'review_notes' => $s('review_notes', 400),
        ];
    }

    private function findOrCreate(string $class, string $name, array &$created, string $label)
    {
        $existing = $class::all()->first(fn ($item) => $this->same($item->name, $name));
        if ($existing) {
            return $existing;
        }

        $item = $class::create(['name' => $name, 'is_active' => false, 'pending_approval' => true, 'sort_order' => 100]);
        $created[] = "{$label} “{$item->name}”";

        return $item;
    }

    /** "HP" = "hp", "Cooling fan" = "Cooling Fans". */
    private function same(string $a, string $b): bool
    {
        $norm = fn ($v) => preg_replace('/s$/', '', preg_replace('/[^a-z0-9]/', '', Str::lower($v)));

        return $norm($a) === $norm($b);
    }

    /**
     * Existing years of a model that the AI's "years" text refers to:
     * no years given → all of them; same label → that one; else any overlapping range.
     */
    private function matchGenerations($generations, string $years)
    {
        if ($years === '') {
            return $generations;
        }
        $exact = $generations->filter(fn ($g) => $this->same($g->label, $years));
        if ($exact->isNotEmpty()) {
            return $exact;
        }
        [$from, $to] = $this->years($years);

        return $from ? $generations->filter(fn ($g) => $g->year_from && $g->year_from <= $to && ($g->year_to ?? $g->year_from) >= $from) : collect();
    }

    private function years(string $label): array
    {
        preg_match_all('/(19|20)\d{2}/', $label, $m);
        $years = array_map('intval', $m[0]);

        return [$years[0] ?? null, $years[1] ?? ($years[0] ?? null)];
    }

    private function names($names): string
    {
        return $names->isEmpty() ? '(none yet)' : $names->implode(', ');
    }
}
