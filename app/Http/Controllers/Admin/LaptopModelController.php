<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\LaptopModel;
use App\Models\ModelGeneration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Laptop models (step 2 of the finder) and their years / generations (step 3).
 */
class LaptopModelController extends Controller
{
    public function index(Request $request): View
    {
        $models = LaptopModel::with('brand')->withCount('generations')
            ->when($request->query('brand'), fn ($q, $brand) => $q->where('brand_id', $brand))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy(Brand::select('name')->whereColumn('brands.id', 'laptop_models.brand_id'))
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('admin.models.index', ['models' => $models, 'brands' => Brand::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.models.form', [
            'laptopModel' => new LaptopModel(['brand_id' => request('brand'), 'is_active' => true]),
            'brands' => Brand::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $model = LaptopModel::create($data);

        $generation = $request->validate([
            'generation_label' => ['nullable', 'string', 'max:100'],
            'year_from' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1990', 'max:2100', 'gte:year_from'],
        ]);
        if (filled($generation['generation_label'] ?? null)) {
            $model->generations()->create([
                'label' => $generation['generation_label'],
                'year_from' => $generation['year_from'] ?? null,
                'year_to' => $generation['year_to'] ?? null,
            ]);
        }

        return redirect()->route('admin.models.edit', $model)->with('status', 'Model created. Add its years / generations below.');
    }

    public function edit(LaptopModel $laptopModel): View
    {
        return view('admin.models.form', [
            'laptopModel' => $laptopModel->load(['generations' => fn ($q) => $q->withCount('products')]),
            'brands' => Brand::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, LaptopModel $laptopModel): RedirectResponse
    {
        $laptopModel->update($this->validated($request, $laptopModel));

        return back()->with('status', 'Model saved.');
    }

    public function destroy(LaptopModel $laptopModel): RedirectResponse
    {
        $laptopModel->delete();

        return redirect()->route('admin.models.index')->with('status', "“{$laptopModel->name}” and its years were deleted. Parts are kept.");
    }

    public function storeGeneration(Request $request, LaptopModel $laptopModel): RedirectResponse
    {
        $laptopModel->generations()->create($this->validatedGeneration($request));

        return back()->with('status', 'Year / generation added.');
    }

    public function updateGeneration(Request $request, ModelGeneration $generation): RedirectResponse
    {
        $generation->update($this->validatedGeneration($request) + ['pending_approval' => false]);

        return back()->with('status', 'Year / generation saved.');
    }

    public function destroyGeneration(ModelGeneration $generation): RedirectResponse
    {
        $generation->delete();

        return back()->with('status', "“{$generation->label}” deleted.");
    }

    private function validated(Request $request, ?LaptopModel $model = null): array
    {
        $data = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if ($data['is_active']) {
            $data['pending_approval'] = false; // an admin publishing it approves an AI suggestion
        }
        if ($model && (int) $data['brand_id'] !== $model->brand_id) {
            $data['slug'] = null; // regenerate within the new brand
        }

        return $data;
    }

    private function validatedGeneration(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'year_from' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1990', 'max:2100', 'gte:year_from'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
