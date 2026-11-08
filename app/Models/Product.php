<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'type', 'category_id', 'part_category_id', 'name', 'slug', 'sku', 'oem_part_number',
        'tag', 'summary', 'description', 'specification', 'connector_type', 'size',
        'price', 'compare_at_price', 'stock_qty', 'warranty', 'image', 'is_featured', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
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
