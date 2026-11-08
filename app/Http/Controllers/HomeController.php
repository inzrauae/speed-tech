<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featured = Product::active()->with(['category', 'partCategory'])
            ->orderByDesc('is_featured')->latest('updated_at')->take(8)->get();

        return view('home', [
            'featured' => $featured,
            'categories' => Category::active()->get(),
            'brands' => Brand::active()->get(),
            'brandCards' => Brand::active()
                ->withCount(['laptopModels' => fn ($q) => $q->where('is_active', true)])
                ->with(['laptopModels' => fn ($q) => $q->active()->limit(3)])
                ->get(),
            'partCategories' => PartCategory::active()->get(),
        ]);
    }
}
