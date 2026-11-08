<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Universal computer accessories, plus site-wide search across every product
 * (including spare parts, by name, SKU or OEM part number).
 */
class ShopController extends Controller
{
    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name' => 'Name A–Z',
    ];

    public function __invoke(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
        ]);

        $categories = Category::active()->withCount(['products' => fn ($q) => $q->active()])->get();
        $category = $categories->firstWhere('slug', $request->query('category'));
        $search = trim((string) $request->query('q'));
        $sort = $request->query('sort', 'featured');
        $inStock = $request->boolean('in_stock');

        $query = Product::active()->with(['category', 'partCategory'])->search($search);

        // Searching looks across the whole store; browsing shows accessories.
        if ($category) {
            $query->where('category_id', $category->id);
        } elseif ($search === '') {
            $query->accessories();
        }
        if ($inStock) {
            $query->where('stock_qty', '>', 0);
        }

        match ($sort) {
            'newest' => $query->latest(),
            // Priced items first, then by price; "price on request" items go last.
            'price_asc' => $query->orderByRaw('price is null')->orderBy('price'),
            'price_desc' => $query->orderByRaw('price is null')->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderByDesc('stock_qty')->orderBy('name'),
        };

        return view('shop', [
            'products' => $query->paginate(16)->withQueryString(),
            'categories' => $categories,
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
            'inStock' => $inStock,
            'sorts' => self::SORTS,
        ]);
    }
}
