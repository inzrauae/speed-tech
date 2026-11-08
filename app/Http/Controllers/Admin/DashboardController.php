<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $monthStart = now()->startOfMonth();

        return view('admin.dashboard', [
            'stats' => [
                'Pending orders' => Order::where('status', 'pending')->count(),
                'New enquiries' => Enquiry::where('status', 'new')->count(),
                'Sales this month' => money(Order::where('status', '!=', 'cancelled')->where('created_at', '>=', $monthStart)->sum('total')),
                'Active products' => Product::active()->count(),
            ],
            'recentOrders' => Order::latest()->take(8)->get(),
            'recentEnquiries' => Enquiry::latest()->take(8)->get(),
            'lowStock' => Product::active()->whereNotNull('price')->where('stock_qty', '<=', Product::LOW_STOCK)
                ->orderBy('stock_qty')->take(10)->get(),
        ]);
    }
}
