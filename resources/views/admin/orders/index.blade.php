@extends('admin.layout', ['title' => 'Orders'])

@section('content')
  <form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Order number, name or phone…">
    <select name="status" data-autosubmit><option value="">All statuses</option>@foreach (\App\Models\Order::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-wrap"><table class="data">
    <thead><tr><th>Order</th><th>Customer</th><th>Fulfilment</th><th>Payment</th><th>Status</th><th class="num">Items</th><th class="num">Total</th></tr></thead>
    <tbody>
      @forelse ($orders as $order)
        <tr>
          <td><a class="link" href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a><small>{{ $order->created_at->format('d M Y, H:i') }}</small></td>
          <td>{{ $order->customer_name }}<small>{{ $order->phone }}</small></td>
          <td>{{ $order->fulfilment === 'pickup' ? 'Pickup' : 'Delivery' }}<small>{{ $order->city }}</small></td>
          <td>{{ $order->paymentLabel() }}</td>
          <td><span class="badge {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
          <td class="num">{{ $order->items_count }}</td>
          <td class="num">{{ money($order->total) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty-row">No orders found.</td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $orders->links() }}
@endsection
