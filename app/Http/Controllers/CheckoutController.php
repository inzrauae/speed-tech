<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Notifications\ShopNotifier;
use App\Services\Cart;
use App\Services\OrderPlacer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private Cart $cart) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        return view('checkout', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'deliveryFee' => $this->cart->deliveryFee('delivery'),
        ]);
    }

    public function store(Request $request, OrderPlacer $placer): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{9,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'fulfilment' => ['required', Rule::in(array_keys(Order::FULFILMENT))],
            'address' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:500'],
            'city' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'website' => ['prohibited'], // honeypot
        ], [
            'phone.regex' => 'Please enter a valid phone number, e.g. 077 123 4567.',
            'address.required_if' => 'Please enter a delivery address.',
            'city.required_if' => 'Please enter your city.',
        ]);

        // Cash on delivery only makes sense for delivery; paying at the store only for pickup.
        if ($data['fulfilment'] === 'pickup' && $data['payment_method'] === 'cod') {
            $data['payment_method'] = 'pay_at_store';
        }
        if ($data['fulfilment'] === 'delivery' && $data['payment_method'] === 'pay_at_store') {
            $data['payment_method'] = 'cod';
        }

        $order = $placer->place($data);
        ShopNotifier::orderPlaced($order);

        return redirect()->to(URL::signedRoute('orders.confirmation', $order));
    }

    public function confirmation(Order $order): View
    {
        return view('order', ['order' => $order->load('items')]);
    }
}
