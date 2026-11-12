<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaptopModel extends Model
{
    use HasSlug;

    protected $fillable = ['brand_id', 'name', 'slug', 'is_active', 'pending_approval'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'pending_approval' => 'boolean'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(ModelGeneration::class)->orderByDesc('year_from')->orderBy('label');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('name');
    }

    /** Model slugs only need to be unique within their brand. */
    protected function slugQuery()
    {
        return static::query()->where('brand_id', $this->brand_id);
    }
}
