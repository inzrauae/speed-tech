<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the cart into an order: re-checks price and stock under a row lock,
 * reserves stock, and stores a snapshot of each line.
 */
class OrderPlacer
{
    public function __construct(private Cart $cart) {}

    /** @param array<string,mixed> $customer validated checkout data */
    public function place(array $customer): Order
    {
        $lines = $this->cart->lines();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $order = DB::transaction(function () use ($lines, $customer) {
            $products = Product::whereKey($lines->pluck('product.id'))->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0;
            $items = [];
            foreach ($lines as $line) {
                $product = $products->get($line['product']->id);
                if (! $product || ! $product->isPurchasable() || $product->stock_qty < $line['quantity']) {
                    throw ValidationException::withMessages([
                        'cart' => "Sorry, “{$line['product']->name}” is no longer available in that quantity. Please review your cart.",
                    ]);
                }
                $lineTotal = round((float) $product->price * $line['quantity'], 2);
                $subtotal += $lineTotal;
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'oem_part_number' => $product->oem_part_number,
                    'unit_price' => $product->price,
                    'quantity' => $line['quantity'],
                    'line_total' => $lineTotal,
                ];
                $product->decrement('stock_qty', $line['quantity']);
            }

            $deliveryFee = $this->cart->deliveryFee($customer['fulfilment'], $subtotal);

            $order = Order::create([
                'number' => Order::generateNumber(),
                'customer_name' => $customer['customer_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'] ?? null,
                'fulfilment' => $customer['fulfilment'],
                'address' => $customer['fulfilment'] === 'delivery' ? $customer['address'] : null,
                'city' => $customer['fulfilment'] === 'delivery' ? $customer['city'] : null,
                'payment_method' => $customer['payment_method'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $subtotal + $deliveryFee,
                'notes' => $customer['notes'] ?? null,
            ]);
            $order->items()->createMany($items);

            return $order;
        });

        $this->cart->clear();

        return $order;
    }

    /** Moves an order between statuses, returning stock when it is cancelled (and taking it again if restored). */
    public static function changeStatus(Order $order, string $status): void
    {
        DB::transaction(function () use ($order, $status) {
            $wasHolding = $order->holdsStock();
            $order->status = $status;
            $nowHolding = $order->holdsStock();

            if ($wasHolding !== $nowHolding) {
                foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if (! $product) {
                        continue;
                    }
                    $nowHolding
                        ? $product->decrement('stock_qty', min($item->quantity, $product->stock_qty))
                        : $product->increment('stock_qty', $item->quantity);
                }
            }

            $order->save();
        });
    }
}
