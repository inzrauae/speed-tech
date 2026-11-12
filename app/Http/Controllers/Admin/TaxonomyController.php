<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PartCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Simple name / slug / order / active lists: shop categories, spare-part types and laptop brands.
 */
class TaxonomyController extends Controller
{
    private const TYPES = [
        'categories' => ['model' => Category::class, 'title' => 'Shop categories', 'singular' => 'category', 'count' => 'products', 'help' => 'Categories for universal computer accessories (Storage, RAM, Chargers…).'],
        'part-categories' => ['model' => PartCategory::class, 'title' => 'Spare part types', 'singular' => 'part type', 'count' => 'products', 'help' => 'Item types shown in step 4 of the spare-part finder (LCD display, Keyboard, Battery…).'],
        'brands' => ['model' => Brand::class, 'title' => 'Laptop brands', 'singular' => 'brand', 'count' => 'laptopModels', 'help' => 'Step 1 of the spare-part finder. Add models and years under Laptop models.'],
    ];

    public function index(string $type): View
    {
        $config = $this->config($type);
        $items = $config['model']::withCount($config['count'])->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.catalog.index', compact('type', 'config', 'items'));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->config($type);
        $item = new $config['model'];
        $item->fill($this->validated($request, $item, $type))->save();

        return back()->with('status', ucfirst($config['singular'])." “{$item->name}” added.");
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $item = $config['model']::findOrFail($id);
        $item->fill($this->validated($request, $item, $type))->save();

        return back()->with('status', "“{$item->name}” saved.");
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $item = $config['model']::withCount($config['count'])->findOrFail($id);

        if ($item->{str($config['count'])->snake().'_count'} > 0) {
            return back()->with('error', "“{$item->name}” is still in use. Move or remove its items first, or hide it instead.");
        }

        $item->delete();

        return back()->with('status', "“{$item->name}” deleted.");
    }

    private function config(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function validated(Request $request, Model $item, string $type): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique($item->getTable())->ignore($item->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ] + ($type === 'brands' ? [] : [
            // Must not clash with any other category or part type (both feed product codes).
            'sku_prefix' => ['nullable', 'alpha', 'min:2', 'max:4', function ($attr, $value, $fail) use ($item) {
                $value = strtoupper($value);
                $clash = Category::where('sku_prefix', $value)->when($item instanceof Category, fn ($q) => $q->whereKeyNot($item->id))->exists()
                    || PartCategory::where('sku_prefix', $value)->when($item instanceof PartCategory, fn ($q) => $q->whereKeyNot($item->id))->exists();
                if ($clash) {
                    $fail("The code {$value} is already used by another category or part type.");
                }
            }],
        ]));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        if ($data['is_active']) {
            $data['pending_approval'] = false; // an admin publishing it approves an AI suggestion
        }
        if ($type !== 'brands' && blank($data['sku_prefix'] ?? null)) {
            $data['sku_prefix'] = $item->sku_prefix; // keep existing; new ones are derived from the name
        }
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = $item->slug; // keep existing; HasSlug fills new ones
        }

        return $data;
    }
}
