<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills a unique `slug` from `name` when one is not given.
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = $model->uniqueSlug(Str::slug($model->name) ?: 'item');
            }
        });
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while ($this->slugQuery()->where('slug', $slug)->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /** Scope in which the slug must be unique. */
    protected function slugQuery()
    {
        return static::query();
    }
}
