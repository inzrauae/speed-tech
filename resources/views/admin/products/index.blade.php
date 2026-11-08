@extends('admin.layout', ['title' => 'Products'])

@section('actions')
  <a class="btn primary" href="{{ route('admin.products.create', ['type' => 'spare_part']) }}"><svg class="icon"><use href="#icon-plus"/></svg>Add spare part</a>
  <a class="btn" href="{{ route('admin.products.create', ['type' => 'accessory']) }}"><svg class="icon"><use href="#icon-plus"/></svg>Add accessory</a>
@endsection

@section('content')
  <form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, SKU, OEM part number…">
    <select name="type" data-autosubmit><option value="">All types</option>@foreach (\App\Models\Product::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select>
    <select name="stock" data-autosubmit><option value="">Any stock</option><option value="low" @selected(request('stock') === 'low')>Low stock</option><option value="out" @selected(request('stock') === 'out')>Out of stock</option></select>
    <select name="status" data-autosubmit><option value="">Any visibility</option><option value="hidden" @selected(request('status') === 'hidden')>Hidden only</option></select>
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-wrap"><table class="data">
    <thead><tr><th></th><th>Product</th><th>Type / category</th><th>Fits</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($products as $product)
        <tr>
          <td><img class="thumb" src="{{ $product->imageUrl() }}" alt=""></td>
          <td><a class="link" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a><small>{{ collect([$product->oem_part_number ? 'P/N '.$product->oem_part_number : null, $product->sku])->filter()->implode(' · ') }}</small></td>
          <td>{{ $product->groupLabel() }}<small>{{ \App\Models\Product::TYPES[$product->type] }}</small></td>
          <td>{{ $product->isSparePart() ? $product->generations_count.' '.Str::plural('model year', $product->generations_count) : '—' }}</td>
          <td class="num">{{ $product->priceLabel() }}</td>
          <td class="num"><span class="badge {{ $product->stockState() }}">{{ $product->stock_qty }}</span></td>
          <td>@if ($product->is_active)<span class="badge in">Visible</span>@else<span class="badge muted">Hidden</span>@endif @if ($product->is_featured)<span class="badge">Featured</span>@endif</td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty-row">No products match.</td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $products->links() }}
@endsection
