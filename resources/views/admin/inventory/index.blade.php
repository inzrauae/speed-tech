@extends('admin.layout', ['title' => 'Inventory'])

@section('actions')
  <a class="btn" href="{{ route('admin.inventory.movements') }}"><svg class="icon"><use href="#icon-storage"/></svg>Stock history</a>
  <a class="btn" href="{{ route('admin.inventory.export', request()->query()) }}"><svg class="icon"><use href="#icon-arrow"/></svg>Export CSV</a>
@endsection

@section('content')
  <div class="stats stats-5">
    @foreach ($summary as $label => $value)
      <div @class(['stat', 'stat-warn' => in_array($label, ['Need reorder', 'Out of stock']) && $value > 0])><span>{{ $label }}</span><b>{{ $value }}</b></div>
    @endforeach
  </div>

  <form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, SKU, OEM part number…">
    <select name="stock" data-autosubmit>
      <option value="">All stock levels</option>
      <option value="reorder" @selected(request('stock') === 'reorder')>Need reorder</option>
      <option value="out" @selected(request('stock') === 'out')>Out of stock</option>
      <option value="in" @selected(request('stock') === 'in')>In stock</option>
    </select>
    <select name="type" data-autosubmit><option value="">All types</option>@foreach (\App\Models\Product::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select>
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-wrap"><table class="data inventory-table">
    <thead><tr><th>Product</th><th class="num">In stock</th><th class="num">Reorder at</th><th class="num">Cost</th><th class="num">Stock value</th><th>Update stock</th></tr></thead>
    <tbody>
      @forelse ($products as $product)
        <tr>
          <td>
            <a class="link" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a>
            <small>{{ collect([$product->groupLabel(), $product->sku, $product->oem_part_number ? 'P/N '.$product->oem_part_number : null])->filter()->implode(' · ') }}@unless ($product->is_active) · hidden @endunless</small>
          </td>
          <td class="num">
            <span class="badge {{ $product->stock_qty === 0 ? 'out' : ($product->needsReorder() ? 'low' : 'in') }}">{{ $product->stock_qty }}</span>
          </td>
          <td class="num">{{ $product->reorder_level }}</td>
          <td class="num">{{ $product->cost_price !== null ? money($product->cost_price) : '—' }}</td>
          <td class="num">{{ $product->cost_price !== null ? money($product->stockValue()) : '—' }}</td>
          <td>
            <details class="adjust">
              <summary class="btn sm">Update</summary>
              <form method="post" action="{{ route('admin.inventory.adjust', $product) }}" class="adjust-form" data-adjust>
                @csrf
                <div class="segmented">
                  <label><input type="radio" name="action" value="receive" checked>Receive</label>
                  <label><input type="radio" name="action" value="remove">Remove</label>
                  <label><input type="radio" name="action" value="count">Set count</label>
                </div>
                <label class="field">Quantity<input type="number" name="quantity" min="0" required value="1"></label>
                <label class="field" data-show="receive">Unit cost (Rs.) <small>optional</small><input type="number" name="unit_cost" min="0" step="0.01" value="{{ $product->cost_price }}"></label>
                <label class="field" data-show="remove">Reason
                  <select name="reason"><option value="damaged">Damaged / lost</option><option value="adjustment">Correction</option></select>
                </label>
                <label class="field">Reference <small>supplier invoice, etc.</small><input name="reference" maxlength="100"></label>
                <label class="field">Note<input name="note" maxlength="500"></label>
                <button class="btn primary" type="submit">Save</button>
              </form>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty-row">No products match.</td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $products->links() }}

  <section class="card" style="margin-top:20px">
    <h2>Latest stock movements <a class="link" href="{{ route('admin.inventory.movements') }}">All</a></h2>
    @include('admin.inventory._movements', ['movements' => $recent, 'showProduct' => true])
  </section>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-adjust]').forEach(form => {
    const sync = () => {
      const action = form.querySelector('[name=action]:checked').value;
      form.querySelectorAll('[data-show]').forEach(el => { el.hidden = el.dataset.show !== action; });
      form.querySelector('[name=quantity]').previousSibling.textContent = action === 'count' ? 'Counted quantity' : 'Quantity';
    };
    form.addEventListener('change', sync);
    sync();
  });
</script>
@endpush
