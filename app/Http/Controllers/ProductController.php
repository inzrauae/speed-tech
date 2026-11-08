<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load(['category', 'partCategory', 'generations.laptopModel.brand']);

        $related = Product::active()->with(['category', 'partCategory'])
            ->whereKeyNot($product->id)
            ->where($product->isSparePart()
                ? ['part_category_id' => $product->part_category_id]
                : ['category_id' => $product->category_id])
            ->inRandomOrder()->take(4)->get();

        // Group compatibility as Brand Model → [generation labels].
        $compatibility = $product->generations
            ->groupBy(fn ($g) => trim($g->laptopModel?->brand?->name.' '.$g->laptopModel?->name))
            ->map(fn ($gens) => $gens->pluck('label')->all())
            ->sortKeys();

        return view('product', compact('product', 'related', 'compatibility'));
    }
}
