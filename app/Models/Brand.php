<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasSlug;

    protected $fillable = ['name', 'slug', 'sort_order', 'is_active', 'pending_approval'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'pending_approval' => 'boolean', 'sort_order' => 'integer'];
    }

    public function laptopModels(): HasMany
    {
        return $this->hasMany(LaptopModel::class)->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
