<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Notifications\ShopNotifier;
use App\Services\Cart;
use App\Services\OrderPlacer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $subtotal = $this->cart->subtotal();

        return view('checkout', [
            'lines' => $this->cart->lines(),
            'subtotal' => $subtotal,
            'deliveryFee' => $this->cart->deliveryFee('delivery'),
            'freeDeliveryOver' => (float) Setting::read('free_delivery_over'),
            // Which methods each delivery option allows, for this cart total.
            'paymentOptions' => [
                'delivery' => Order::allowedPaymentMethods('delivery', $subtotal),
                'pickup' => Order::allowedPaymentMethods('pickup', $subtotal),
            ],
            'bankDetails' => Setting::bankDetails(),
            'bankInstructions' => Setting::read('bank_instructions'),
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
            'payment_method' => ['required', Rule::in(Order::enabledPaymentMethods())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'website' => ['prohibited'], // honeypot
        ], [
            'phone.regex' => 'Please enter a valid phone number, e.g. 077 123 4567.',
            'address.required_if' => 'Please enter a delivery address.',
            'city.required_if' => 'Please enter your city.',
            'payment_method.in' => 'Please choose an available payment method.',
        ]);
        // OrderPlacer checks the method suits the delivery option and order value.

        $order = $placer->place($data);
        ShopNotifier::orderPlaced($order);

        return redirect()->to(URL::signedRoute('orders.confirmation', $order));
    }

    public function confirmation(Order $order): View
    {
        return view('order', [
            'order' => $order->load('items'),
            'bankDetails' => Setting::bankDetails(),
            'bankInstructions' => Setting::read('bank_instructions'),
            'slipUploadUrl' => URL::signedRoute('orders.slip', $order),
        ]);
    }

    /** Customer uploads a bank transfer slip from the confirmation page (signed link). */
    public function uploadSlip(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->payment_method === 'bank_transfer', 404);

        $request->validate([
            'slip' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'slip.mimes' => 'Please upload a photo (JPG, PNG) or a PDF of the payment slip.',
            'slip.max' => 'The file is too large. Please keep it under 5 MB.',
        ]);

        if ($order->payment_slip) {
            Storage::disk('local')->delete($order->payment_slip);
        }
        $order->update([
            'payment_slip' => $request->file('slip')->store('payment-slips', 'local'),
        ]);
        ShopNotifier::slipUploaded($order);

        return redirect()->to(URL::signedRoute('orders.confirmation', $order))
            ->with('status', 'Thank you. We received your payment slip and will confirm your payment shortly.');
    }
}
