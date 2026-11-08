@extends('admin.layout', [
  'title' => $product ? 'Stock history: '.$product->name : 'Stock history',
  'breadcrumb' => '<a href="'.route('admin.inventory.index').'">Inventory</a> /',
])

@section('actions')
  @if ($product)<a class="btn" href="{{ route('admin.products.edit', $product) }}">Edit product</a>@endif
@endsection

@section('content')
  @if ($product)
    <p class="help">Currently <b>{{ $product->stock_qty }}</b> in stock · reorder at {{ $product->reorder_level }}. <a class="link" href="{{ route('admin.inventory.movements') }}">Show all products</a></p>
  @endif
  <form class="filters" method="get">
    @if ($product)<input type="hidden" name="product" value="{{ $product->id }}">@endif
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Order number, reference, note or product…">
    <select name="type" data-autosubmit><option value="">All movements</option>@foreach (\App\Models\StockMovement::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select>
    <button class="btn" type="submit">Filter</button>
  </form>
  @include('admin.inventory._movements', ['movements' => $movements, 'showProduct' => ! $product])
  {{ $movements->links() }}
@endsection
