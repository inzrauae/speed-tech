@extends('admin.layout', [
  'title' => $product->exists ? $product->name : 'New product',
  'breadcrumb' => '<a href="'.route('admin.products.index').'">Products</a> /',
])

@section('actions')
  @if ($product->exists)<a class="btn" href="{{ $product->url() }}" target="_blank" rel="noopener">View in store ↗</a>@endif
@endsection

@section('content')
  @php($type = old('type', $product->type))
  <form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" id="productForm">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="layout-form">
      <div>
        <section class="card">
          <h2>Product type</h2>
          <div class="segmented" role="radiogroup">
            @foreach (\App\Models\Product::TYPES as $key => $label)
              <label><input type="radio" name="type" value="{{ $key }}" @checked($type === $key)>{{ $label }}</label>
            @endforeach
          </div>
        </section>

        <section class="card">
          <h2>Basics</h2>
          <div class="form-grid">
            <label class="field span-2">Name<input name="name" value="{{ old('name', $product->name) }}" required maxlength="200"></label>
            <label class="field" data-for="spare_part">Part type (finder step 4)
              <select name="part_category_id"><option value="">Choose…</option>@foreach ($partCategories as $c)<option value="{{ $c->id }}" @selected(old('part_category_id', $product->part_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
            </label>
            <label class="field" data-for="accessory">Shop category
              <select name="category_id"><option value="">Choose…</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
            </label>
            <label class="field">Card label <small>(e.g. GENUINE / OEM-GRADE)</small><input name="tag" value="{{ old('tag', $product->tag) }}" maxlength="40"></label>
            <label class="field span-2">Short summary <small>(shown on product cards)</small><input name="summary" value="{{ old('summary', $product->summary) }}" maxlength="500"></label>
            <label class="field span-2">Description<textarea name="description" rows="5" maxlength="10000">{{ old('description', $product->description) }}</textarea></label>
          </div>
        </section>

        <section class="card">
          <h2>Part details <small>Don't rely on model and year alone — record the OEM number, connector and size.</small></h2>
          <div class="form-grid three">
            <label class="field">OEM part number<input name="oem_part_number" value="{{ old('oem_part_number', $product->oem_part_number) }}" maxlength="100"></label>
            <label class="field">SKU <small>(your stock code)</small><input name="sku" value="{{ old('sku', $product->sku) }}" maxlength="100"></label>
            <label class="field">Warranty<input name="warranty" value="{{ old('warranty', $product->warranty) }}" maxlength="100" placeholder="3 months"></label>
            <label class="field span-2">Specification<input name="specification" value="{{ old('specification', $product->specification) }}" maxlength="255" placeholder='15.6", FHD, 30-pin'></label>
            <label class="field">Size<input name="size" value="{{ old('size', $product->size) }}" maxlength="255" placeholder='15.6"'></label>
            <label class="field span-2">Connector type<input name="connector_type" value="{{ old('connector_type', $product->connector_type) }}" maxlength="255" placeholder="30-pin eDP"></label>
          </div>
        </section>

        <section class="card" data-for="spare_part">
          <h2>Compatible laptops (finder steps 1–3) <small><span id="compatCount">{{ count($selectedGenerations) }}</span> selected</small></h2>
          <p class="help">Tick every model year this part fits. Missing a model? <a class="link" href="{{ route('admin.models.create') }}" target="_blank">Add it</a> and reload this page.</p>
          <div class="filters"><input type="search" id="compatFilter" placeholder="Filter brands or models…"></div>
          <div class="compat" id="compat">
            @foreach ($brands as $brand)
              @php($brandSelected = $brand->laptopModels->flatMap->generations->whereIn('id', $selectedGenerations)->count())
              <details @if ($brandSelected) open @endif data-search="{{ Str::lower($brand->name.' '.$brand->laptopModels->pluck('name')->implode(' ')) }}">
                <summary>{{ $brand->name }} <small>{{ $brandSelected ? $brandSelected.' selected' : $brand->laptopModels->count().' models' }}</small></summary>
                @forelse ($brand->laptopModels as $model)
                  <div class="model" data-search="{{ Str::lower($brand->name.' '.$model->name) }}">
                    <b>{{ $model->name }}</b>
                    <div class="gens">
                      @forelse ($model->generations as $generation)
                        <label class="check"><input type="checkbox" name="generation_ids[]" value="{{ $generation->id }}" @checked(in_array($generation->id, $selectedGenerations))> {{ $generation->label }}</label>
                      @empty
                        <small class="help">No years yet — <a class="link" href="{{ route('admin.models.edit', $model) }}" target="_blank">add one</a>.</small>
                      @endforelse
                    </div>
                  </div>
                @empty
                  <p class="help">No models yet.</p>
                @endforelse
              </details>
            @endforeach
          </div>
        </section>
      </div>

      <div>
        <section class="card">
          <h2>Price &amp; stock</h2>
          <div class="form-grid" style="grid-template-columns:1fr">
            <label class="field">Price (Rs.) <small>— leave empty for "Price on request"</small><input type="number" name="price" value="{{ old('price', $product->price) }}" min="0" step="0.01"></label>
            <label class="field">Compare-at price <small>(optional, shown struck through)</small><input type="number" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" min="0" step="0.01"></label>
            <label class="field">Cost price (Rs.) <small>— what you pay; used for stock value</small><input type="number" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" min="0" step="0.01"></label>
            <label class="field">{{ $product->exists ? 'Stock quantity' : 'Opening stock' }} <small>— changes are logged in the stock history</small><input type="number" name="stock_qty" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}" min="0" required></label>
            <label class="field">Reorder level <small>— warn me when stock is at or below</small><input type="number" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level ?? 3) }}" min="0"></label>
          </div>
          <p class="help" style="margin-top:12px">Customers can buy online only when there is a price and stock above 0. Otherwise they send an enquiry.</p>
          @if ($product->exists)
            <a class="link" href="{{ route('admin.inventory.index', ['q' => $product->sku ?: $product->name]) }}">Receive or adjust stock →</a>
          @endif
        </section>

        @if ($product->exists)
          <section class="card">
            <h2>Stock history <a class="link" href="{{ route('admin.inventory.movements', ['product' => $product->id]) }}">All</a></h2>
            @php($history = $product->stockMovements()->with('user')->take(6)->get())
            @forelse ($history as $m)
              <div class="mini-move">
                <span class="badge {{ $m->quantity > 0 ? 'in' : 'out' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</span>
                <span>{{ $m->typeLabel() }}@if ($m->reference) · {{ $m->reference }}@endif<small>{{ $m->created_at->format('d M Y, H:i') }} · balance {{ $m->balance_after }}</small></span>
              </div>
            @empty
              <p class="help">No stock movements yet.</p>
            @endforelse
          </section>
        @endif

        <section class="card">
          <h2>Visibility</h2>
          <input type="hidden" name="is_active" value="0"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> Visible in the store</label><br><br>
          <input type="hidden" name="is_featured" value="0"><label class="check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Featured on the home page</label>
          <label class="field" style="margin-top:14px">URL slug <small>(optional)</small><input name="slug" value="{{ old('slug', $product->slug) }}" maxlength="200"></label>
        </section>

        <section class="card">
          <h2>Image</h2>
          <img class="image-preview" src="{{ $product->imageUrl() }}" alt="" id="imagePreview">
          <label class="field">Upload JPG, PNG or WebP (max 4 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" id="imageInput"></label>
          @if ($product->image)<label class="check" style="margin-top:10px"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif
        </section>

        <div class="form-actions">
          <button class="btn primary" type="submit">{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
          <a class="btn" href="{{ route('admin.products.index') }}">Cancel</a>
        </div>
      </div>
    </div>
  </form>

  @if ($product->exists)
    <form method="post" action="{{ route('admin.products.destroy', $product) }}" data-confirm="Delete “{{ $product->name }}” permanently? Past orders keep their copy of the name and price." style="margin-top:24px">
      @csrf @method('DELETE')
      <button class="btn danger" type="submit"><svg class="icon"><use href="#icon-trash"/></svg>Delete product</button>
      <span class="help">Tip: untick "Visible in the store" to hide it without deleting.</span>
    </form>
  @endif
@endsection

@push('scripts')
<script>
  (() => {
    const form = document.getElementById('productForm');
    const syncType = () => {
      const type = form.querySelector('input[name="type"]:checked')?.value;
      form.querySelectorAll('[data-for]').forEach(el => { el.hidden = el.dataset.for !== type; });
    };
    form.addEventListener('change', e => {
      if (e.target.name === 'type') syncType();
      if (e.target.name === 'generation_ids[]') document.getElementById('compatCount').textContent = form.querySelectorAll('input[name="generation_ids[]"]:checked').length;
    });
    syncType();

    const filter = document.getElementById('compatFilter');
    filter?.addEventListener('input', () => {
      const term = filter.value.trim().toLowerCase();
      document.querySelectorAll('#compat details').forEach(d => {
        const brandMatch = d.dataset.search.includes(term);
        d.hidden = term && !brandMatch;
        d.querySelectorAll('.model').forEach(m => { m.hidden = term && !m.dataset.search.includes(term) && !d.querySelector('summary').textContent.toLowerCase().includes(term); });
        if (term && brandMatch) d.open = true;
      });
    });

    document.getElementById('imageInput')?.addEventListener('change', e => {
      const file = e.target.files[0];
      if (file) document.getElementById('imagePreview').src = URL.createObjectURL(file);
    });
  })();
</script>
@endpush
