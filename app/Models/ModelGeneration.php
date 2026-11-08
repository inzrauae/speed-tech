<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModelGeneration extends Model
{
    protected $fillable = ['laptop_model_id', 'label', 'year_from', 'year_to', 'notes'];

    protected function casts(): array
    {
        return ['year_from' => 'integer', 'year_to' => 'integer'];
    }

    public function laptopModel(): BelongsTo
    {
        return $this->belongsTo(LaptopModel::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** "Dell Inspiron 15 3520 (2022–2024)" */
    public function fullName(): string
    {
        $model = $this->laptopModel;

        return trim(($model?->brand?->name ?? '').' '.($model?->name ?? '')).' ('.$this->label.')';
    }
}
