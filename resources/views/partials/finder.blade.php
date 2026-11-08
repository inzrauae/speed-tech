{{--
  Cascading spare-part finder: Brand → Model → Year / Generation → Part.
  Expects $brands, $partCategories; optionally $models, $generations, $selected.
--}}
@php
  $selected = $selected ?? [];
  $models = $models ?? collect();
  $generations = $generations ?? collect();
@endphp
<form class="finder" action="{{ route('spare-parts') }}" method="get" data-finder
      data-models-url="{{ route('api.finder.models') }}" data-generations-url="{{ route('api.finder.generations') }}" data-parts-url="{{ route('api.finder.parts') }}">
  <label><span>01 <b>Brand</b></span>
    <select name="brand" data-level="brand">
      <option value="">Select brand</option>
      @foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected(($selected['brand'] ?? null)?->id === $brand->id)>{{ $brand->name }}</option>@endforeach
    </select>
  </label>
  <label><span>02 <b>Model</b></span>
    <select name="model" data-level="model" @disabled($models->isEmpty())>
      <option value="">{{ $models->isEmpty() ? 'Select model' : 'All models' }}</option>
      @foreach ($models as $model)<option value="{{ $model->id }}" @selected(($selected['model'] ?? null)?->id === $model->id)>{{ $model->name }}</option>@endforeach
    </select>
  </label>
  <label><span>03 <b>Year / generation</b></span>
    <select name="generation" data-level="generation" @disabled($generations->isEmpty())>
      <option value="">{{ $generations->isEmpty() ? 'Select year' : 'All years' }}</option>
      @foreach ($generations as $generation)<option value="{{ $generation->id }}" @selected(($selected['generation'] ?? null)?->id === $generation->id)>{{ $generation->label }}</option>@endforeach
    </select>
  </label>
  <label><span>04 <b>Spare part</b></span>
    <select name="part" data-level="part">
      <option value="">All parts</option>
      @foreach ($partCategories as $category)<option value="{{ $category->id }}" @selected(($selected['part'] ?? null)?->id === $category->id)>{{ $category->name }}</option>@endforeach
    </select>
  </label>
  <button class="button red" type="submit">Find spare parts<svg class="icon"><use href="#icon-search"/></svg></button>
</form>
