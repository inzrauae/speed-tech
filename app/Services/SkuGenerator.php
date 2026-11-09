<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Internal product codes (SKUs) in the usual retail format: CATEGORY-SEQUENCE, e.g. LCD-00012.
 *
 * - The prefix is the category's / part type's `sku_prefix` (LCD, KBD, RAM…).
 * - The sequence is per prefix, zero-padded to 5 digits, and never reused.
 * - No price, brand or other changeable data is encoded, so a code stays valid for the product's life.
 * The manufacturer's own number is kept separately in `oem_part_number`.
 */
class SkuGenerator
{
    public const DIGITS = 5;
    private const FALLBACK = ['spare_part' => 'SPR', 'accessory' => 'ACC'];

    public function for(Product $product): string
    {
        return $this->next($this->prefixFor($product));
    }

    public function prefixFor(Product $product): string
    {
        $group = $product->isSparePart()
            ? ($product->part_category_id ? PartCategory::find($product->part_category_id) : null)
            : ($product->category_id ? Category::find($product->category_id) : null);

        if ($group && blank($group->sku_prefix)) {
            $group->sku_prefix = self::derivePrefix($group->name, self::takenPrefixes());
            $group->saveQuietly();
        }

        return $group?->sku_prefix ?: self::FALLBACK[$product->type] ?? 'GEN';
    }

    /** Next free code for a prefix: one above the highest existing PREFIX-NNNNN. */
    public function next(string $prefix): string
    {
        $max = Product::where('sku', 'like', $prefix.'-%')->pluck('sku')
            ->map(fn ($sku) => preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $sku, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        do {
            $sku = $prefix.'-'.str_pad((string) ++$max, self::DIGITS, '0', STR_PAD_LEFT);
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /** Three-letter code from a name: first letter + following consonants ("Speaker" → SPK). */
    public static function derivePrefix(string $name, array $taken = []): string
    {
        $letters = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($name))) ?: 'GEN';
        $consonants = preg_replace('/[AEIOU]/', '', substr($letters, 1));
        $base = substr(str_pad(substr($letters, 0, 1).$consonants.substr($letters, 1), 3, 'X'), 0, 3);

        $prefix = $base;
        for ($i = 2; in_array($prefix, $taken, true); $i++) {
            $prefix = substr($base, 0, 2).$i;
        }

        return $prefix;
    }

    public static function takenPrefixes(): array
    {
        return array_merge(
            PartCategory::whereNotNull('sku_prefix')->pluck('sku_prefix')->all(),
            Category::whereNotNull('sku_prefix')->pluck('sku_prefix')->all(),
        );
    }
}
