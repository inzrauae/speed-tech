<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPlacer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::withCount('items')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
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
}
