<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPlacer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::withCount('items')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('payment'), fn ($q, $payment) => $q->where('payment_status', $payment))
            ->when($request->query('method'), fn ($q, $method) => $q->where('payment_method', $method))
            ->when($request->query('slip'), fn ($q) => $q->whereNotNull('payment_slip')->where('payment_status', 'unpaid'))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->latest()
            ->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load('items.product')]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $order->admin_notes = $data['admin_notes'] ?? null;
        $previous = $order->status;
        OrderPlacer::changeStatus($order, $data['status']);

        $message = 'Order updated.';
        if ($previous !== 'cancelled' && $data['status'] === 'cancelled') {
            $message = 'Order cancelled and stock returned.';
        } elseif ($previous === 'cancelled' && $data['status'] !== 'cancelled') {
            $message = 'Order restored and stock reserved again.';
        }

        return back()->with('status', $message);
    }

    /** Record payment: mark paid / unpaid / refunded, with an optional reference. */
    public function payment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys(Order::PAYMENT_STATUSES))],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $order->payment_status = $data['payment_status'];
        $order->payment_reference = $data['payment_reference'] ?? $order->payment_reference;
        $order->paid_at = $data['payment_status'] === 'paid' ? ($order->paid_at ?? now()) : null;
        $order->save();

        return back()->with('status', "Payment marked as {$order->paymentStatusLabel()}.");
    }

    /** Admin-only view of the customer's uploaded bank slip. */
    public function slip(Order $order): StreamedResponse
    {
        abort_unless($order->payment_slip && Storage::disk('local')->exists($order->payment_slip), 404);

        // Only cleaned JPEGs open in the browser; PDFs (and any older upload) download instead.
        // nosniff + a sandboxing CSP stop the browser from ever running the file as a page or script.
        $extension = strtolower(pathinfo($order->payment_slip, PATHINFO_EXTENSION));
        $inline = in_array($extension, ['jpg', 'jpeg'], true);
        $type = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return Storage::disk('local')->response(
            $order->payment_slip,
            "payment-slip-{$order->number}.{$extension}",
            [
                'Content-Type' => $type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
                'Cache-Control' => 'private, no-store',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }
}
