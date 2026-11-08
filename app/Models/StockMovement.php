<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'opening' => 'Opening stock',
        'received' => 'Stock received',
        'sale' => 'Sold (order)',
        'returned' => 'Returned to stock',
        'adjustment' => 'Stock count adjustment',
        'damaged' => 'Damaged / lost',
    ];

    protected $fillable = ['product_id', 'type', 'quantity', 'balance_after', 'unit_cost', 'reference', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer', 'unit_cost' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
