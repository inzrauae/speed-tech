<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private Cart $cart) {}

    public function show(): View
    {
        return view('cart', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function add(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::active()->find($data['product_id']);
        if (! $product || ! $product->isPurchasable()) {
            return $this->respond($request, false, 'This item is available on request. Send us an enquiry and we will confirm price and availability.', 422);
        }

        $before = $this->cart->raw()[$product->id] ?? 0;
        $quantity = $this->cart->add($product, $data['quantity'] ?? 1);

        $message = $quantity > $before
            ? "Added “{$product->name}” to your cart."
            : "You already have the maximum available quantity of “{$product->name}” in your cart.";

        return $this->respond($request, $quantity > $before, $message);
    }

    public function update(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);
        $quantity = $this->cart->set($product, $data['quantity']);

        $message = match (true) {
            $quantity === 0 => 'Item removed from your cart.',
            $quantity < $data['quantity'] => "Only {$quantity} available — quantity updated.",
            default => 'Cart updated.',
        };

        return $this->respond($request, true, $message);
    }

    public function remove(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->cart->remove($product->id);

        return $this->respond($request, true, 'Item removed from your cart.');
    }

    private function respond(Request $request, bool $ok, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'cart' => $this->cart->summary()], $status);
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }
}
