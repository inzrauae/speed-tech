@extends('admin.layout', ['title' => 'Dashboard'])

@section('actions')
  <a class="btn primary" href="{{ route('admin.products.create', ['type' => 'spare_part']) }}"><svg class="icon"><use href="#icon-plus"/></svg>Add spare part</a>
  <a class="btn" href="{{ route('admin.products.create', ['type' => 'accessory']) }}"><svg class="icon"><use href="#icon-plus"/></svg>Add accessory</a>
@endsection

@section('content')
  <div class="stats">
    @foreach ($stats as $label => $value)<div class="stat"><span>{{ $label }}</span><b>{{ $value }}</b></div>@endforeach
  </div>

  <div class="grid-2">
    <section class="card">
      <h2>Recent orders <a class="link" href="{{ route('admin.orders.index') }}">All orders</a></h2>
      <div class="table-wrap"><table class="data">
        <tbody>
          @forelse ($recentOrders as $order)
            <tr>
              <td><a class="link" href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a><small>{{ $order->created_at->diffForHumans() }}</small></td>
              <td>{{ $order->customer_name }}<small>{{ $order->phone }}</small></td>
              <td><span class="badge {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
              <td class="num">{{ money($order->total) }}</td>
            </tr>
          @empty
            <tr><td class="empty-row">No orders yet.</td></tr>
          @endforelse
        </tbody>
      </table></div>
    </section>

    <section class="card">
      <h2>Recent enquiries <a class="link" href="{{ route('admin.enquiries.index') }}">All enquiries</a></h2>
      <div class="table-wrap"><table class="data">
        <tbody>
          @forelse ($recentEnquiries as $enquiry)
            <tr>
              <td><a class="link" href="{{ route('admin.enquiries.show', $enquiry) }}">{{ $enquiry->reference }}</a><small>{{ $enquiry->created_at->diffForHumans() }}</small></td>
              <td>{{ $enquiry->name }}<small>{{ $enquiry->typeLabel() }}</small></td>
              <td><span class="badge {{ $enquiry->status }}">{{ \App\Models\Enquiry::STATUSES[$enquiry->status] ?? $enquiry->status }}</span></td>
            </tr>
          @empty
            <tr><td class="empty-row">No enquiries yet.</td></tr>
          @endforelse
        </tbody>
      </table></div>
    </section>
  </div>

  <section class="card">
    <h2>Low stock <small>priced items with {{ \App\Models\Product::LOW_STOCK }} or fewer left</small></h2>
    <div class="table-wrap"><table class="data">
      <tbody>
        @forelse ($lowStock as $product)
          <tr>
            <td><a class="link" href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a><small>{{ $product->oem_part_number ?: $product->sku }}</small></td>
            <td class="num"><span class="badge {{ $product->stockState() }}">{{ $product->stock_qty }} left</span></td>
          </tr>
        @empty
          <tr><td class="empty-row">Stock levels look healthy.</td></tr>
        @endforelse
      </tbody>
    </table></div>
  </section>
@endsection
