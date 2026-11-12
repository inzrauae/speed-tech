<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\ModelGeneration;
use App\Models\PartCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Brands, laptop models, years, part types and shop categories suggested by the
 * AI product writer wait here (hidden from the store) until an admin approves,
 * renames, replaces with an existing entry, or rejects them.
 */
class ApprovalController extends Controller
{
    /** URL segment → model class and labels. */
    public const TYPES = [
        'brands' => [Brand::class, 'Laptop brand', 'name'],
        'models' => [LaptopModel::class, 'Laptop model', 'name'],
        'years' => [ModelGeneration::class, 'Year / generation', 'label'],
        'part-types' => [PartCategory::class, 'Part type', 'name'],
        'categories' => [Category::class, 'Shop category', 'name'],
    ];

    public static function pendingCount(): int
    {
        return collect(self::TYPES)->sum(fn ($t) => $t[0]::where('pending_approval', true)->count());
    }

    public function index(): View
    {
        return view('admin.approvals', [
            'brands' => Brand::where('pending_approval', true)->withCount('laptopModels')->orderBy('name')->get(),
            'models' => LaptopModel::where('pending_approval', true)->with('brand')->orderBy('name')->get(),
            'years' => ModelGeneration::where('pending_approval', true)->with('laptopModel.brand')->withCount('products')->orderBy('label')->get(),
            'partTypes' => PartCategory::where('pending_approval', true)->withCount('products')->orderBy('name')->get(),
            'categories' => Category::where('pending_approval', true)->withCount('products')->orderBy('name')->get(),
            'existing' => [
                'brands' => Brand::where('pending_approval', false)->orderBy('name')->get(['id', 'name']),
                'part-types' => PartCategory::where('pending_approval', false)->orderBy('name')->get(['id', 'name']),
                'categories' => Category::where('pending_approval', false)->orderBy('name')->get(['id', 'name']),
            ],
            'drafts' => Product::where('ai_draft', true)->get(['id', 'name', 'slug', 'part_category_id', 'category_id']),
        ]);
    }

    /** Approve (optionally renamed). Approving a model or year also approves what it belongs to. */
    public function approve(Request $request, string $type, int $id): RedirectResponse
    {
        [$class, $label, $field] = $this->type($type);
        $item = $class::where('pending_approval', true)->findOrFail($id);
        $data = $request->validate([$field => ['required', 'string', 'max:100']]);

        DB::transaction(function () use ($item, $field, $data) {
            if ($item->{$field} !== $data[$field]) {
                $item->{$field} = $data[$field];
                if ($item->isFillable('slug')) {
                    $item->slug = null; // HasSlug rebuilds it from the new name
                }
            }
            $this->markApproved($item);

            $model = $item instanceof ModelGeneration ? $item->laptopModel : ($item instanceof LaptopModel ? $item : null);
            if ($model && $model->pending_approval) {
                $this->markApproved($model);
            }
            if ($model?->brand?->pending_approval) {
                $this->markApproved($model->brand);
            }
        });

        return back()->with('status', "{$label} “{$item->{$field}}” approved and added to the store.");
    }

    /** Use an existing brand / part type / shop category instead of the suggested one. */
    public function replace(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['brands', 'part-types', 'categories'], true), 404);
        [$class, $label] = $this->type($type);
        $item = $class::where('pending_approval', true)->findOrFail($id);
        $data = $request->validate(['replace_with' => ['required', 'integer', Rule::exists($item->getTable(), 'id')->where('pending_approval', 0)]]);
        $target = $class::findOrFail($data['replace_with']);

        DB::transaction(function () use ($type, $item, $target) {
            match ($type) {
                'brands' => $this->mergeBrand($item, $target),
                'part-types' => Product::where('part_category_id', $item->id)->update(['part_category_id' => $target->id]),
                'categories' => Product::where('category_id', $item->id)->update(['category_id' => $target->id]),
            };
            $item->delete();
        });

        return back()->with('status', "{$label} “{$item->name}” replaced with the existing “{$target->name}”.");
    }

    /** Reject: delete the suggestion. Draft products simply lose the link and can be re-assigned. */
    public function reject(string $type, int $id): RedirectResponse
    {
        [$class, $label, $field] = $this->type($type);
        $item = $class::where('pending_approval', true)->findOrFail($id);
        $item->delete(); // models/years cascade; products' category links are set to null

        return back()->with('status', "{$label} “{$item->{$field}}” rejected. Fix any affected draft products before publishing them.");
    }

    public function approveAll(): RedirectResponse
    {
        DB::transaction(function () {
            foreach (self::TYPES as [$class]) {
                $class::where('pending_approval', true)->get()->each(fn ($item) => $this->markApproved($item));
            }
        });

        return back()->with('status', 'All suggestions approved and added to the store.');
    }

    private function markApproved(Model $item): void
    {
        $item->pending_approval = false;
        if (! $item instanceof ModelGeneration) {
            $item->is_active = true;
        }
        $item->save();
    }

    /** Move the suggested brand's models onto an existing brand, merging models with the same name. */
    private function mergeBrand(Brand $from, Brand $to): void
    {
        foreach ($from->laptopModels()->get() as $model) {
            $same = $to->laptopModels()->get()->first(fn ($m) => mb_strtolower($m->name) === mb_strtolower($model->name));
            if (! $same) {
                $model->update(['brand_id' => $to->id, 'slug' => null]);
                continue;
            }
            foreach ($model->generations()->get() as $generation) {
                $twin = $same->generations()->get()->first(fn ($g) => mb_strtolower($g->label) === mb_strtolower($generation->label));
                if ($twin) {
                    foreach ($generation->products()->pluck('products.id') as $productId) {
                        $twin->products()->syncWithoutDetaching([$productId]);
                    }
                    $generation->delete();
                } else {
                    $generation->update(['laptop_model_id' => $same->id]);
                }
            }
            $model->delete();
        }
    }

    private function type(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }
}
