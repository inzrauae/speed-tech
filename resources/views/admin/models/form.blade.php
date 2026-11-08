@extends('admin.layout', [
  'title' => $laptopModel->exists ? $laptopModel->brand?->name.' '.$laptopModel->name : 'New laptop model',
  'breadcrumb' => '<a href="'.route('admin.models.index').'">Laptop models</a> /',
])

@section('content')
  <form class="card" method="post" action="{{ $laptopModel->exists ? route('admin.models.update', $laptopModel) : route('admin.models.store') }}" style="max-width:760px">
    @csrf
    @if ($laptopModel->exists) @method('PUT') @endif
    <h2>Model</h2>
    <div class="form-grid">
      <label class="field">Brand
        <select name="brand_id" required><option value="">Choose…</option>@foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected(old('brand_id', $laptopModel->brand_id) == $brand->id)>{{ $brand->name }}</option>@endforeach</select>
      </label>
      <label class="field">Model name<input name="name" value="{{ old('name', $laptopModel->name) }}" required maxlength="150" placeholder="Inspiron 15 3520"></label>
      @unless ($laptopModel->exists)
        <label class="field">First year / generation <small>(optional)</small><input name="generation_label" value="{{ old('generation_label') }}" maxlength="100" placeholder="2022–2024"></label>
        <div class="form-grid" style="gap:10px">
          <label class="field">From year<input type="number" name="year_from" value="{{ old('year_from') }}" min="1990" max="2100"></label>
          <label class="field">To year<input type="number" name="year_to" value="{{ old('year_to') }}" min="1990" max="2100"></label>
        </div>
      @endunless
      <div class="span-2"><input type="hidden" name="is_active" value="0"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $laptopModel->is_active))> Visible in the finder</label></div>
    </div>
    <div class="form-actions" style="margin-top:16px">
      <button class="btn primary" type="submit">{{ $laptopModel->exists ? 'Save model' : 'Create model' }}</button>
      <a class="btn" href="{{ route('admin.models.index') }}">Back</a>
    </div>
  </form>

  @if ($laptopModel->exists)
    <section class="card" style="max-width:760px">
      <h2>Years / generations <small>finder step 3</small></h2>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Label / from / to</th><th class="num">Parts</th><th></th></tr></thead>
        <tbody>
          @forelse ($laptopModel->generations as $generation)
            <tr>
              <td>
                <form class="inline-form" method="post" action="{{ route('admin.generations.update', $generation) }}">
                  @csrf @method('PUT')
                  <input class="w-lg" name="label" value="{{ $generation->label }}" required maxlength="100" aria-label="Label">
                  <input class="w-sm" type="number" name="year_from" value="{{ $generation->year_from }}" min="1990" max="2100" aria-label="From year">
                  <input class="w-sm" type="number" name="year_to" value="{{ $generation->year_to }}" min="1990" max="2100" aria-label="To year">
                  <button class="btn sm" type="submit">Save</button>
                </form>
              </td>
              <td class="num">{{ $generation->products_count }}</td>
              <td class="num">
                <form method="post" action="{{ route('admin.generations.destroy', $generation) }}" data-confirm="Delete “{{ $generation->label }}”? Parts will no longer be linked to it.">
                  @csrf @method('DELETE')
                  <button class="btn sm danger" type="submit" aria-label="Delete {{ $generation->label }}"><svg class="icon"><use href="#icon-trash"/></svg></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="3" class="empty-row">No years yet. Add one below.</td></tr>
          @endforelse
        </tbody>
      </table></div>
      <form class="inline-form" method="post" action="{{ route('admin.generations.store', $laptopModel) }}" style="margin-top:28px">
        @csrf
        <input class="w-lg" name="label" required maxlength="100" placeholder="e.g. 2022–2024 or 11th Gen">
        <input class="w-sm" type="number" name="year_from" placeholder="From" min="1990" max="2100">
        <input class="w-sm" type="number" name="year_to" placeholder="To" min="1990" max="2100">
        <button class="btn primary" type="submit"><svg class="icon"><use href="#icon-plus"/></svg>Add year</button>
      </form>
    </section>

    <form method="post" action="{{ route('admin.models.destroy', $laptopModel) }}" data-confirm="Delete this model and all its years? Parts themselves are kept.">
      @csrf @method('DELETE')
      <button class="btn danger" type="submit"><svg class="icon"><use href="#icon-trash"/></svg>Delete model</button>
    </form>
  @endif
@endsection
