<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-backed shopping cart. Stores only product ids and quantities;
 * prices and stock are always re-read from the database.
 */
class Cart
{
    private const KEY = 'cart';

    private ?Collection $lines = null;

    public function __construct(private Session $session) {}

    /** @return array<int,int> product id => quantity */
    public function raw(): array
    {
        $raw = $this->session->get(self::KEY, []);

        return is_array($raw) ? $raw : [];
    }

    /** Adds to the existing quantity, capped at stock. Returns the new quantity. */
    public function add(Product $product, int $quantity = 1): int
    {
        $raw = $this->raw();

        return $this->set($product, ($raw[$product->id] ?? 0) + max(1, $quantity));
    }

    /** Sets the quantity, capped at stock. A quantity of 0 removes the line. */
    public function set(Product $product, int $quantity): int
    {
        $raw = $this->raw();
        $quantity = min(max(0, $quantity), $product->maxOrderQuantity());

        if ($quantity === 0 || ! $product->isPurchasable()) {
            unset($raw[$product->id]);
            $quantity = 0;
        } else {
            $raw[$product->id] = $quantity;
        }

        $this->session->put(self::KEY, $raw);
        $this->lines = null;

        return $quantity;
    }

    public function remove(int $productId): void
    {
        $raw = $this->raw();
        unset($raw[$productId]);
        $this->session->put(self::KEY, $raw);
        $this->lines = null;
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
        $this->lines = null;
    }

    /**
     * Current lines with fresh product data. Lines whose product is gone,
     * inactive, unpriced or out of stock are dropped; quantities are capped at stock.
     *
     * @return Collection<int, array{product: Product, quantity: int, line_total: float}>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $raw = $this->raw();
        $products = Product::with(['category', 'partCategory'])->whereKey(array_keys($raw))->get()->keyBy('id');

        $clean = [];
        $lines = collect();
        foreach ($raw as $id => $quantity) {
            $product = $products->get($id);
            if (! $product || ! $product->isPurchasable()) {
                continue;
            }
            $quantity = min((int) $quantity, $product->maxOrderQuantity());
            if ($quantity < 1) {
                continue;
            }
            $clean[$id] = $quantity;
            $lines->push([
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => round((float) $product->price * $quantity, 2),
            ]);
        }

        if ($clean !== $raw) {
            $this->session->put(self::KEY, $clean);
        }

        return $this->lines = $lines;
    }

    public function count(): int
    {
        return (int) $this->lines()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function subtotal(): float
    {
        return round((float) $this->lines()->sum('line_total'), 2);
    }

    public function deliveryFee(string $fulfilment, ?float $subtotal = null): float
    {
        if ($fulfilment !== 'delivery') {
            return 0.0;
        }
        $freeOver = (float) config('speedtech.free_delivery_over');

        return $freeOver > 0 && ($subtotal ?? $this->subtotal()) >= $freeOver ? 0.0 : (float) config('speedtech.delivery_fee');
    }

    public function summary(): array
    {
        return [
            'count' => $this->count(),
            'subtotal' => $this->subtotal(),
            'subtotal_label' => money($this->subtotal()),
        ];
    }
}
