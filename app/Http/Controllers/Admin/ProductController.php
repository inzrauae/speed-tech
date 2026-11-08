<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PartCategory;
use App\Models\Product;
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
            'specification' => ['nullable', 'string', 'max:255'],
            'connector_type' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock_qty' => ['required', 'integer', 'min:0', 'max:1000000'],
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

        $product->fill(collect($data)->except(['image', 'remove_image', 'generation_ids'])->all());
        $product->is_featured = $request->boolean('is_featured');
        $product->is_active = $request->boolean('is_active');
        $product->category_id = $isPart ? null : $data['category_id'];
        $product->part_category_id = $isPart ? $data['part_category_id'] : null;
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
    }

    /** Only removes files this admin uploaded, never the bundled theme assets. */
    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, self::UPLOAD_DIR.'/')) {
            File::delete(public_path($path));
        }
    }
}
