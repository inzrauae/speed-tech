<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\LaptopModel;
use App\Models\ModelGeneration;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Laptop spare parts: Brand → Model → Year / Generation → Item.
 */
class SparePartController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'brand' => ['nullable', 'integer'],
            'model' => ['nullable', 'integer'],
            'generation' => ['nullable', 'integer'],
            'part' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $brands = Brand::active()->get();
        $partCategories = PartCategory::active()->get();

        // Resolve each level only if it belongs to the level above, so a
        // tampered URL cannot show a mismatched selection.
        $brand = $brands->firstWhere('id', (int) $request->query('brand'));
        $model = $brand ? LaptopModel::active()->where('brand_id', $brand->id)->find($request->query('model')) : null;
        $generation = $model ? ModelGeneration::where('laptop_model_id', $model->id)->where('pending_approval', false)->find($request->query('generation')) : null;
        $part = $partCategories->firstWhere('id', (int) $request->query('part'));
        $search = trim((string) $request->query('q'));

        $hasFilter = $brand || $part || $search !== '';

        $products = null;
        if ($hasFilter) {
            $products = Product::active()->spareParts()
                ->with(['partCategory', 'generations.laptopModel.brand'])
                ->compatibleWith($brand?->id, $model?->id, $generation?->id)
                ->when($part, fn ($q) => $q->where('part_category_id', $part->id))
                ->search($search)
                ->orderByRaw('stock_qty = 0')->orderBy('name')
                ->paginate(20)->withQueryString();
        }

        return view('spare-parts', [
            'brands' => $brands,
            'partCategories' => $partCategories,
            'models' => $brand ? LaptopModel::active()->where('brand_id', $brand->id)->get() : collect(),
            'generations' => $model ? $model->generations->reject->pending_approval->values() : collect(),
            'selected' => compact('brand', 'model', 'generation', 'part'),
            'search' => $search,
            'products' => $products,
            'selectionLabel' => collect([$brand?->name, $model?->name, $generation?->label, $part?->name])->filter()->implode(' / '),
        ]);
    }
}
