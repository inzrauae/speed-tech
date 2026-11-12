<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\LaptopModel;
use App\Models\ModelGeneration;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints that feed the cascading spare-part finder.
 */
class FinderApiController extends Controller
{
    public function brands(): JsonResponse
    {
        return response()->json(Brand::active()->get(['id', 'name']));
    }

    public function models(Request $request): JsonResponse
    {
        $request->validate(['brand' => ['required', 'integer']]);

        return response()->json(
            LaptopModel::active()->where('brand_id', $request->integer('brand'))->get(['id', 'name'])
        );
    }

    public function generations(Request $request): JsonResponse
    {
        $request->validate(['model' => ['required', 'integer']]);

        return response()->json(
            ModelGeneration::where('laptop_model_id', $request->integer('model'))->where('pending_approval', false)
                ->orderByDesc('year_from')->orderBy('label')->get(['id', 'label'])
        );
    }

    /** Part categories with the number of active parts for the current selection. */
    public function parts(Request $request): JsonResponse
    {
        $request->validate([
            'brand' => ['nullable', 'integer'],
            'model' => ['nullable', 'integer'],
            'generation' => ['nullable', 'integer'],
        ]);

        $counts = Product::active()->spareParts()
            ->compatibleWith($request->integer('brand') ?: null, $request->integer('model') ?: null, $request->integer('generation') ?: null)
            ->selectRaw('part_category_id, count(*) as aggregate')
            ->groupBy('part_category_id')
            ->pluck('aggregate', 'part_category_id');

        return response()->json(
            PartCategory::active()->get(['id', 'name'])->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'count' => (int) ($counts[$c->id] ?? 0),
            ])
        );
    }
}
