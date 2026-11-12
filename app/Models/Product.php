<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Services\SkuGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasSlug;

    public const TYPE_ACCESSORY = 'accessory';
    public const TYPE_SPARE_PART = 'spare_part';

    public const TYPES = [
        self::TYPE_ACCESSORY => 'Computer accessory',
        self::TYPE_SPARE_PART => 'Laptop spare part',
    ];

    public const LOW_STOCK = 3;

    protected $fillable = [
        'type', 'category_id', 'part_category_id', 'name', 'brand', 'slug', 'sku', 'oem_part_number',
        'tag', 'summary', 'description', 'meta_title', 'meta_description', 'focus_keyword', 'faqs', 'image_alt', 'specification', 'connector_type', 'size',
        'price', 'compare_at_price', 'cost_price', 'stock_qty', 'reorder_level', 'warranty', 'image', 'is_featured', 'is_active',
    ];

    protected static function booted(): void
    {
        // Every product gets a stock code (e.g. LCD-00012) unless one was typed in.
        static::saving(function (Product $product) {
            $product->sku = filled($product->sku) ? Str::upper(trim($product->sku)) : app(SkuGenerator::class)->for($product);
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'reorder_level' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'ai_draft' => 'boolean',
            'faqs' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function partCategory(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class);
    }

    public function generations(): BelongsToMany
    {
        return $this->belongsToMany(ModelGeneration::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /** At or below its reorder level (admin inventory view). */
    public function needsReorder(): bool
    {
        return $this->stock_qty <= $this->reorder_level;
    }

    public function scopeNeedsReorder(Builder $query): Builder
    {
        return $query->whereColumn('stock_qty', '<=', 'reorder_level');
    }

    public function stockValue(): float
    {
        return round((float) $this->cost_price * $this->stock_qty, 2);
    }

    // ----- Scopes -----

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSpareParts(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SPARE_PART);
    }

    public function scopeAccessories(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ACCESSORY);
    }

    /** Matches name, SKU, OEM part number, specification and summary. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $words = array_slice(preg_split('/\s+/', $term), 0, 6);

        return $query->where(function (Builder $outer) use ($words) {
            foreach ($words as $word) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $word).'%';
                $outer->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('oem_part_number', 'like', $like)
                    ->orWhere('specification', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('tag', 'like', $like));
            }
        });
    }

    /** Parts that fit any generation of the given model / a specific generation. */
    public function scopeCompatibleWith(Builder $query, ?int $brandId, ?int $modelId, ?int $generationId): Builder
    {
        if ($generationId) {
            return $query->whereHas('generations', fn (Builder $q) => $q->whereKey($generationId));
        }
        if ($modelId) {
            return $query->whereHas('generations', fn (Builder $q) => $q->where('laptop_model_id', $modelId));
        }
        if ($brandId) {
            return $query->whereHas('generations.laptopModel', fn (Builder $q) => $q->where('brand_id', $brandId));
        }

        return $query;
    }

    // ----- Presentation helpers -----

    public function isSparePart(): bool
    {
        return $this->type === self::TYPE_SPARE_PART;
    }

    /** Only priced, in-stock items can go into the cart; everything else is enquiry-only. */
    public function isPurchasable(): bool
    {
        return $this->is_active && $this->price !== null && $this->stock_qty > 0;
    }

    public function maxOrderQuantity(): int
    {
        return max(0, min($this->stock_qty, (int) config('speedtech.max_line_quantity')));
    }

    public function priceLabel(): string
    {
        return $this->price === null ? 'Price on request' : money($this->price);
    }

    public function stockLabel(): string
    {
        return match (true) {
            $this->stock_qty > self::LOW_STOCK => 'In stock',
            $this->stock_qty > 0 => "Only {$this->stock_qty} left",
            default => 'Available to order',
        };
    }

    public function stockState(): string
    {
        return match (true) {
            $this->stock_qty > self::LOW_STOCK => 'in',
            $this->stock_qty > 0 => 'low',
            default => 'out',
        };
    }

    public function imageUrl(): string
    {
        if (! $this->image) {
            return asset('assets/part-placeholder.svg');
        }
        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }

        return asset($this->image);
    }

    public function groupLabel(): string
    {
        return $this->isSparePart()
            ? ($this->partCategory?->name ?? 'Spare part')
            : ($this->category?->name ?? 'Accessory');
    }

    public function url(): string
    {
        return route('products.show', $this);
    }

    public function imageAlt(): string
    {
        return $this->image_alt ?: $this->name;
    }

    /** @return list<array{q: string, a: string}> */
    public function faqList(): array
    {
        return array_values(array_filter((array) $this->faqs, fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null)));
    }

    /** Meta description for Google: the written one, else the summary, else the description start. */
    public function seoDescription(): string
    {
        $text = $this->meta_description ?: ($this->summary ?: preg_replace('/^##\s*/m', '', (string) $this->description));

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($text))), 160);
    }

    /**
     * Description as safe HTML. Plain-text format: blank line = new paragraph,
     * "## Heading" = sub-heading, lines starting with "• " or "- " = bullet list.
     */
    public function descriptionHtml(): \Illuminate\Support\HtmlString
    {
        $html = '';
        $blocks = preg_split('/\R\s*\R/', trim((string) $this->description));

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $block)), 'strlen'));
            $text = [];
            $flush = function () use (&$text, &$html) {
                if ($text) {
                    $html .= '<p>'.implode('<br>', array_map('e', $text)).'</p>';
                    $text = [];
                }
            };

            for ($i = 0; $i < count($lines); $i++) {
                $line = $lines[$i];
                if (str_starts_with($line, '## ')) {
                    $flush();
                    $html .= '<h3>'.e(trim(substr($line, 3))).'</h3>';
                } elseif (preg_match('/^(•|-|\*)\s+/u', $line)) {
                    $flush();
                    $items = [];
                    while ($i < count($lines) && preg_match('/^(•|-|\*)\s+/u', $lines[$i])) {
                        $items[] = '<li>'.e(preg_replace('/^(•|-|\*)\s+/u', '', $lines[$i])).'</li>';
                        $i++;
                    }
                    $i--;
                    $html .= '<ul>'.implode('', $items).'</ul>';
                } else {
                    $text[] = $line;
                }
            }
            $flush();
        }

        return new \Illuminate\Support\HtmlString($html);
    }

    /**
     * schema.org JSON-LD for Google: Product (+ Offer), BreadcrumbList and,
     * when the page shows FAQs, FAQPage. Built from live data so price and
     * stock in the markup always match the page.
     *
     * @param  \Illuminate\Support\Collection<string, list<string>>|null  $compatibility  "Brand Model" => [year labels]
     */
    public function structuredData($compatibility = null): array
    {
        $shop = config('speedtech');
        $url = $this->url();
        $seller = ['@type' => 'Organization', 'name' => $shop['legal_name'], 'url' => url('/')];

        $product = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $url.'#product',
            'name' => $this->name,
            'url' => $url,
            'image' => [$this->imageUrl()],
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', preg_replace('/^##\s*|^[•*-]\s+/mu', '', strip_tags((string) ($this->description ?: $this->summary))))), 5000),
            'sku' => $this->sku,
            'mpn' => $this->oem_part_number,
            'brand' => $this->brand ? ['@type' => 'Brand', 'name' => $this->brand] : null,
            'category' => $this->groupLabel(),
            'size' => $this->size,
            'additionalProperty' => array_values(array_filter([
                $this->specification ? ['@type' => 'PropertyValue', 'name' => 'Specification', 'value' => $this->specification] : null,
                $this->connector_type ? ['@type' => 'PropertyValue', 'name' => 'Connector', 'value' => $this->connector_type] : null,
                $this->warranty ? ['@type' => 'PropertyValue', 'name' => 'Warranty', 'value' => $this->warranty] : null,
            ])) ?: null,
            'isAccessoryOrSparePartFor' => $compatibility && $compatibility->isNotEmpty()
                ? $compatibility->keys()->map(fn ($model) => ['@type' => 'Product', 'name' => $model])->values()->all()
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($this->price !== null) {
            $product['offers'] = [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'LKR',
                'price' => number_format((float) $this->price, 2, '.', ''),
                'availability' => $this->stock_qty > 0 ? 'https://schema.org/InStock' : 'https://schema.org/BackOrder',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => $seller,
                'shippingDetails' => [
                    '@type' => 'OfferShippingDetails',
                    'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => number_format((float) $shop['delivery_fee'], 2, '.', ''), 'currency' => 'LKR'],
                    'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'LK'],
                ],
            ];
        }

        $crumbs = [['Home', route('home')]];
        if ($this->isSparePart()) {
            $crumbs[] = ['Laptop spare parts', route('spare-parts')];
            if ($this->partCategory) {
                $crumbs[] = [$this->partCategory->name, route('spare-parts', ['part' => $this->part_category_id])];
            }
        } else {
            $crumbs[] = ['Accessories', route('shop')];
            if ($this->category) {
                $crumbs[] = [$this->category->name, route('shop', ['category' => $this->category->slug])];
            }
        }
        $crumbs[] = [$this->name, $url];

        $graphs = [$product, [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]])->all(),
        ]];

        if ($faqs = $this->faqList()) {
            $graphs[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs),
            ];
        }

        return $graphs;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Compact representation for the JSON API. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'group' => $this->groupLabel(),
            'summary' => $this->summary,
            'oem_part_number' => $this->oem_part_number,
            'specification' => $this->specification,
            'price' => $this->price === null ? null : (float) $this->price,
            'price_label' => $this->priceLabel(),
            'stock' => $this->stockState(),
            'stock_label' => $this->stockLabel(),
            'warranty' => $this->warranty,
            'image' => $this->imageUrl(),
            'url' => $this->url(),
            'purchasable' => $this->isPurchasable(),
        ];
    }
}
