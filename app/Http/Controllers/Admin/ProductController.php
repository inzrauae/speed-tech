<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PartCategory;
use App\Models\Product;
use App\Services\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const UPLOAD_DIR = 'uploads/products';

    public function index(Request $request): View
    {
        $products = Product::with(['category', 'partCategory'])->withCount('generations')
            ->search($request->query('q'))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('stock') === 'out', fn ($q) => $q->where('stock_qty', 0))
            ->when($request->query('stock') === 'low', fn ($q) => $q->whereBetween('stock_qty', [1, Product::LOW_STOCK]))
            ->when($request->query('status') === 'hidden', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'ai', fn ($q) => $q->where('ai_draft', true))
            ->latest('updated_at')
            ->paginate(25)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.form', $this->formData(new Product([
            'type' => request('type', Product::TYPE_SPARE_PART),
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $product = new Product;
        $this->save($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', $this->formData($product->load('generations')));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->save($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteImage($product->image);
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', "“{$product->name}” deleted.");
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'partCategories' => PartCategory::orderBy('sort_order')->orderBy('name')->get(),
            'brands' => Brand::with('laptopModels.generations')->orderBy('sort_order')->orderBy('name')->get(),
            'selectedGenerations' => old('generation_ids', $product->exists ? $product->generations->pluck('id')->all() : []),
        ];
    }

    private function save(Request $request, Product $product): void
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Product::TYPES))],
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'alpha_dash', 'max:200', Rule::unique('products')->ignore($product->id)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'oem_part_number' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'required_if:type,accessory', 'exists:categories,id'],
            'part_category_id' => ['nullable', 'required_if:type,spare_part', 'exists:part_categories,id'],
            'tag' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:60'],
            'faqs' => ['nullable', 'array', 'max:8'],
            'faqs.*.q' => ['nullable', 'string', 'max:200'],
            'faqs.*.a' => ['nullable', 'string', 'max:600'],
            'specification' => ['nullable', 'string', 'max:255'],
            'connector_type' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock_qty' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'warranty' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'generation_ids' => ['nullable', 'array'],
            'generation_ids.*' => ['integer', 'exists:model_generations,id'],
        ], [
            'category_id.required_if' => 'Choose a shop category for an accessory.',
            'part_category_id.required_if' => 'Choose a part type for a spare part.',
        ]);

        $isPart = $data['type'] === Product::TYPE_SPARE_PART;

        // Stock is changed through the inventory log, not set directly.
        $newStock = (int) $data['stock_qty'];
        $product->fill(collect($data)->except(['image', 'remove_image', 'generation_ids', 'stock_qty'])->all());
        $faqs = collect($data['faqs'] ?? [])
            ->map(fn ($f) => ['q' => trim((string) ($f['q'] ?? '')), 'a' => trim((string) ($f['a'] ?? ''))])
            ->filter(fn ($f) => $f['q'] !== '' && $f['a'] !== '')->values()->all();
        $product->faqs = $faqs ?: null;
        $product->reorder_level = (int) ($data['reorder_level'] ?? 3);
        $isNew = ! $product->exists;
        $product->is_featured = $request->boolean('is_featured');
        $product->is_active = $request->boolean('is_active');
        if ($product->is_active) {
            $product->ai_draft = false; // reviewed and published
        }
        $product->category_id = $isPart ? null : $data['category_id'];
        $product->part_category_id = $isPart ? $data['part_category_id'] : null;
        if ($product->is_active && ($pending = $this->pendingTaxonomy($product, $isPart ? ($data['generation_ids'] ?? []) : []))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'is_active' => 'This product uses '.implode(', ', $pending).', still waiting for approval. Approve or replace '.(count($pending) > 1 ? 'them' : 'it').' on the Pending approvals page first, or save it hidden for now.',
            ]);
        }
        if (blank($data['slug'] ?? null) && ! $product->exists) {
            $product->slug = null; // HasSlug fills it from the name
        } elseif (blank($data['slug'] ?? null)) {
            $product->slug = $product->getOriginal('slug');
        }

        if ($request->boolean('remove_image') && $product->image) {
            $this->deleteImage($product->image);
            $product->image = null;
        }
        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $file = $request->file('image');
            $name = Str::slug(Str::limit($data['name'], 60, '')).'-'.Str::lower(Str::random(6)).'.'.$file->extension();
            File::ensureDirectoryExists(public_path(self::UPLOAD_DIR));
            $file->move(public_path(self::UPLOAD_DIR), $name);
            $product->image = self::UPLOAD_DIR.'/'.$name;
        }

        $product->save();
        $product->generations()->sync($isPart ? ($data['generation_ids'] ?? []) : []);

        if ($isNew && $newStock > 0) {
            Inventory::move($product, $newStock, 'opening', ['note' => 'Opening stock', 'unit_cost' => $data['cost_price'] ?? null]);
        } elseif (! $isNew && $newStock !== (int) $product->stock_qty) {
            Inventory::setCount($product, $newStock, ['note' => 'Changed on the product form']);
        }
    }

    /** Names of not-yet-approved catalog entries this product would use. */
    private function pendingTaxonomy(Product $product, array $generationIds): array
    {
        $pending = [];
        if ($product->category_id && Category::whereKey($product->category_id)->where('pending_approval', true)->exists()) {
            $pending[] = 'a new shop category';
        }
        if ($product->part_category_id && PartCategory::whereKey($product->part_category_id)->where('pending_approval', true)->exists()) {
            $pending[] = 'a new part type';
        }
        $generations = \App\Models\ModelGeneration::with('laptopModel.brand')->whereKey($generationIds)->get();
        if ($generations->contains(fn ($g) => $g->pending_approval || $g->laptopModel?->pending_approval || $g->laptopModel?->brand?->pending_approval)) {
            $pending[] = 'new laptop brands, models or years';
        }

        return $pending;
    }

    private function deleteImage(?string $path): void
    {
        self::deleteUploadedImage($path);
    }

    /** Only removes files this admin uploaded, never the bundled theme assets. */
    public static function deleteUploadedImage(?string $path): void
    {
        if ($path && str_starts_with($path, self::UPLOAD_DIR.'/')) {
            File::delete(public_path($path));
        }
    }
}
