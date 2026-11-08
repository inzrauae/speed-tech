<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock levels, stock-in / stock-out / counts, and the movement history.
 */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $products = $this->filtered($request)->with(['category', 'partCategory'])
            ->paginate(30)->withQueryString();

        $all = Product::query();
        $summary = [
            'Products tracked' => (clone $all)->count(),
            'Units in stock' => number_format((int) (clone $all)->sum('stock_qty')),
            'Stock value (cost)' => money((float) (clone $all)->selectRaw('coalesce(sum(stock_qty * coalesce(cost_price, 0)), 0) as v')->value('v')),
            'Need reorder' => (clone $all)->where('is_active', true)->needsReorder()->where('stock_qty', '>', 0)->count(),
            'Out of stock' => (clone $all)->where('is_active', true)->where('stock_qty', 0)->count(),
        ];

        return view('admin.inventory.index', [
            'products' => $products,
            'summary' => $summary,
            'recent' => StockMovement::with(['product', 'user'])->latest('id')->take(8)->get(),
        ]);
    }

    /** Receive stock, remove stock, or set an exact count for one product. */
    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['receive', 'remove', 'count'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['nullable', Rule::in(['damaged', 'adjustment'])],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $meta = [
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'unit_cost' => $data['action'] === 'receive' ? ($data['unit_cost'] ?? null) : null,
        ];

        $movement = match ($data['action']) {
            'receive' => $data['quantity'] > 0 ? Inventory::move($product, $data['quantity'], 'received', $meta) : null,
            'remove' => $data['quantity'] > 0 ? Inventory::move($product, -$data['quantity'], $data['reason'] ?? 'adjustment', $meta) : null,
            'count' => Inventory::setCount($product, $data['quantity'], array_filter($meta, fn ($v) => $v !== null)),
        };

        $product->refresh();
        $message = $movement
            ? "“{$product->name}”: ".($movement->quantity > 0 ? '+' : '').$movement->quantity." ({$movement->typeLabel()}). Now {$product->stock_qty} in stock."
            : "“{$product->name}”: no change. {$product->stock_qty} in stock.";

        return back()->with('status', $message);
    }

    public function movements(Request $request): View
    {
        $movements = StockMovement::with(['product', 'user'])
            ->when($request->query('product'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%")
                ->orWhereHas('product', fn ($p) => $p->search($term))))
            ->latest('id')
            ->paginate(40)->withQueryString();

        return view('admin.inventory.movements', [
            'movements' => $movements,
            'product' => $request->query('product') ? Product::find($request->query('product')) : null,
        ]);
    }

    /** Download the current stock list as CSV (opens in Excel). */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with(['category', 'partCategory'])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows symbols correctly
            fputcsv($out, ['Name', 'Type', 'Category', 'SKU', 'OEM part number', 'Stock', 'Reorder level', 'Cost price', 'Selling price', 'Stock value (cost)', 'Visible']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->name, Product::TYPES[$p->type] ?? $p->type, $p->groupLabel(), $p->sku, $p->oem_part_number,
                    $p->stock_qty, $p->reorder_level, $p->cost_price, $p->price, $p->stockValue(), $p->is_active ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, 'speedtech-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request)
    {
        return Product::query()
            ->search($request->query('q'))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('stock') === 'reorder', fn ($q) => $q->needsReorder()->where('stock_qty', '>', 0))
            ->when($request->query('stock') === 'out', fn ($q) => $q->where('stock_qty', 0))
            ->when($request->query('stock') === 'in', fn ($q) => $q->where('stock_qty', '>', 0))
            ->orderByRaw('stock_qty > reorder_level')   // problems first
            ->orderBy('stock_qty')
            ->orderBy('name');
    }
}
