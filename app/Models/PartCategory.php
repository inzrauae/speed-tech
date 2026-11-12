<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Services\SkuGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    use HasSlug;

    protected $fillable = ['name', 'slug', 'sku_prefix', 'icon', 'sort_order', 'is_active', 'pending_approval'];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->sku_prefix = filled($item->sku_prefix)
                ? strtoupper($item->sku_prefix)
                : SkuGenerator::derivePrefix($item->name, SkuGenerator::takenPrefixes());
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'pending_approval' => 'boolean', 'sort_order' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
