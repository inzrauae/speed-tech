<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place stock levels change. Every change writes a StockMovement
 * with the resulting balance, inside a row lock.
 */
class Inventory
{
    /**
     * Change stock by $quantity (+ in / - out). Throws if it would go below zero
     * unless $clamp is true (then it removes only what is there).
     */
    public static function move(Product|int $product, int $quantity, string $type, array $meta = [], bool $clamp = false): ?StockMovement
    {
        return DB::transaction(function () use ($product, $quantity, $type, $meta, $clamp) {
            $product = Product::lockForUpdate()->findOrFail($product instanceof Product ? $product->id : $product);

            if ($product->stock_qty + $quantity < 0) {
                if (! $clamp) {
                    throw ValidationException::withMessages([
                        'quantity' => "Only {$product->stock_qty} of “{$product->name}” in stock; cannot remove ".abs($quantity).'.',
                    ]);
                }
                $quantity = -$product->stock_qty;
            }
            if ($quantity === 0) {
                return null;
            }

            $product->stock_qty += $quantity;
            if (($meta['unit_cost'] ?? null) !== null && $quantity > 0) {
                $product->cost_price = $meta['unit_cost'];   // latest purchase cost
            }
            $product->save();

            return StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $product->stock_qty,
                'unit_cost' => $meta['unit_cost'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'note' => $meta['note'] ?? null,
                'user_id' => $meta['user_id'] ?? auth()->id(),
            ]);
        });
    }

    /** Set stock to an exact counted number, recording the difference. */
    public static function setCount(Product $product, int $count, array $meta = []): ?StockMovement
    {
        $current = (int) Product::whereKey($product->id)->value('stock_qty');

        return self::move($product, $count - $current, 'adjustment', $meta + ['note' => 'Stock count']);
    }
}
