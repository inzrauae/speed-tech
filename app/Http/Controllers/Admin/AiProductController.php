<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ProductImageMaker;
use App\Services\Ai\ProductWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * AI product writer: title (+ optional photo) → hidden draft product with SEO copy,
 * a branded image and any missing category / brand / laptop model created.
 * Staff review the draft on the normal product form and publish it there.
 */
class AiProductController extends Controller
{
    public function __construct(private ProductWriter $writer, private ProductImageMaker $images) {}

    public function create(GeminiClient $gemini): View
    {
        return view('admin.products.ai', [
            'configured' => $gemini->configured(),
            'drafts' => Product::where('ai_draft', true)->latest()->take(8)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(array_keys(Product::TYPES))],
            'photo' => ['nullable', 'required_if:image_mode,photo', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'image_mode' => ['required', Rule::in(['ai', 'photo', 'none'])],
            'image_details' => ['nullable', 'string', 'max:1500'],
        ]);
        @set_time_limit(300);

        $photo = $this->photo($request->file('photo'));

        try {
            $draft = $this->writer->write($data['title'], $data['type'] ?? null, $photo, $data['notes'] ?? null);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $product = DB::transaction(function () use ($draft, &$taxonomy) {
            $taxonomy = $this->writer->resolveTaxonomy($draft);
            $product = Product::create(collect($draft)->only([
                'type', 'name', 'summary', 'description', 'meta_title', 'meta_description',
                'specification', 'size', 'connector_type', 'tag',
            ])->map(fn ($v) => $v === '' ? null : $v)->all() + [
                'category_id' => $taxonomy['category_id'],
                'part_category_id' => $taxonomy['part_category_id'],
                'stock_qty' => 0,
                'reorder_level' => 3,
                'is_active' => false,
            ]);
            $product->forceFill(['ai_draft' => true])->save();
            $product->generations()->sync($taxonomy['generation_ids']);

            return $product;
        });

        $warnings = [];
        if ($data['image_mode'] !== 'none') {
            try {
                $result = $data['image_mode'] === 'ai'
                    ? $this->images->make($product->name, $draft['image_prompt'], (string) ($data['image_details'] ?? ''), $photo)
                    : ['path' => $this->images->fromPhoto($photo, $product->name), 'warning' => null];
                $product->forceFill(['image' => $result['path']])->save();
                if ($result['warning']) {
                    $warnings[] = $result['warning'];
                }
            } catch (Throwable $e) {
                $warnings[] = 'No image yet: '.$e->getMessage();
            }
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'AI draft created. Review it, then tick “Visible in the store” and save to publish.')
            ->with('ai_report', [
                'created' => $taxonomy['created'],
                'notes' => $draft['review_notes'],
                'brand' => $draft['brand'],
                'warnings' => $warnings,
            ]);
    }

    /** New image for an existing product, with optional extra details / new reference photo. */
    public function image(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'image_details' => ['nullable', 'string', 'max:1500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'use_current' => ['nullable', 'boolean'],
        ]);
        @set_time_limit(300);

        $photo = $this->photo($request->file('photo'))
            ?? ($request->boolean('use_current') ? $this->currentImage($product) : null);

        try {
            $visual = trim(implode('. ', array_filter([$product->specification, $product->summary])));
            $result = $this->images->make($product->name, $visual, (string) ($data['image_details'] ?? ''), $photo);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $old = $product->image;
        $product->forceFill(['image' => $result['path']])->save();
        ProductController::deleteUploadedImage($old);

        return back()->with($result['warning'] ? 'error' : 'status', $result['warning'] ?? 'New image created. Check it, then save the product.');
    }

    /** Rewrite the SEO copy of an existing product. */
    public function text(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1500']]);
        @set_time_limit(180);

        $notes = trim(implode("\n", array_filter([
            $product->specification ? "Specification: {$product->specification}" : null,
            $product->oem_part_number ? "OEM part number: {$product->oem_part_number}" : null,
            $data['notes'] ?? null,
        ])));

        try {
            $draft = $this->writer->write($product->name, $product->type, $this->currentImage($product), $notes ?: null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $product->fill(collect($draft)->only(['summary', 'description', 'meta_title', 'meta_description'])->all());
        foreach (['specification', 'size', 'connector_type', 'tag'] as $field) {
            if (blank($product->{$field}) && filled($draft[$field])) {
                $product->{$field} = $draft[$field];
            }
        }
        $product->save();

        return back()->with('status', 'Description and SEO text rewritten. Review them, then save.'.($draft['review_notes'] ? ' AI note: '.$draft['review_notes'] : ''));
    }

    private function photo(?UploadedFile $file): ?array
    {
        return $file ? ['mime' => $file->getMimeType() ?: 'image/jpeg', 'data' => $file->get()] : null;
    }

    /** The product's own uploaded image, as a reference for the AI. */
    private function currentImage(Product $product): ?array
    {
        if (! $product->image || Str::startsWith($product->image, ['http://', 'https://'])) {
            return null;
        }
        $path = public_path($product->image);
        if (! is_file($path)) {
            return null;
        }

        return ['mime' => mime_content_type($path) ?: 'image/jpeg', 'data' => file_get_contents($path)];
    }
}
