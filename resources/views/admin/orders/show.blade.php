@extends('admin.layout', [
  'title' => 'Order '.$order->number,
  'breadcrumb' => '<a href="'.route('admin.orders.index').'">Orders</a> /',
])

@section('actions')
  <a class="btn" href="tel:{{ $order->phone }}"><svg class="icon"><use href="#icon-phone"/></svg>Call</a>
  <a class="btn" href="https://wa.me/{{ preg_replace('/^0/', '94', preg_replace('/\D/', '', $order->phone)) }}?text={{ rawurlencode("Hi {$order->customer_name}, this is SpeedTech about your order {$order->number}.") }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>WhatsApp</a>
@endsection

@section('content')
  <div class="layout-form">
    <div>
      <section class="card">
        <h2>Items</h2>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Product</th><th class="num">Unit</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>
          <tbody>
            @foreach ($order->items as $item)
              <tr>
                <td>
                  @if ($item->product)<a class="link" href="{{ route('admin.products.edit', $item->product) }}">{{ $item->product_name }}</a>@else{{ $item->product_name }}@endif
                  <small>{{ collect([$item->oem_part_number ? 'P/N '.$item->oem_part_number : null, $item->sku])->filter()->implode(' · ') }}</small>
                </td>
                <td class="num">{{ money($item->unit_price) }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">{{ money($item->line_total) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
        <div class="totals" style="margin-top:28px">
          <div><span>Subtotal</span><b>{{ money($order->subtotal) }}</b></div>
          <div><span>Delivery</span><b>{{ money($order->delivery_fee) }}</b></div>
          <div class="grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
        </div>
      </section>

      <section class="card">
        <h2>Customer</h2>
        <dl class="dl">
          <dt>Name</dt><dd>{{ $order->customer_name }}</dd>
          <dt>Phone</dt><dd><a class="link" href="tel:{{ $order->phone }}">{{ $order->phone }}</a></dd>
          <dt>E-mail</dt><dd>{{ $order->email ?: '—' }}</dd>
          <dt>Fulfilment</dt><dd>{{ $order->fulfilmentLabel() }}</dd>
          @if ($order->address)<dt>Address</dt><dd>{{ $order->address }}<br>{{ $order->city }}</dd>@endif
          <dt>Payment</dt><dd>{{ $order->paymentLabel() }}</dd>
          <dt>Placed</dt><dd>{{ $order->created_at->format('d M Y, H:i') }}</dd>
        </dl>
        @if ($order->notes)<h3 style="font-size:13px;margin:16px 0 8px">Customer notes</h3><div class="message-box">{{ $order->notes }}</div>@endif
      </section>
    </div>

    <form class="card" method="post" action="{{ route('admin.orders.update', $order) }}">
      @csrf @method('PATCH')
      <h2>Status <span class="badge {{ $order->status }}">{{ $order->statusLabel() }}</span></h2>
      <label class="field">Change status
        <select name="status">@foreach (\App\Models\Order::STATUSES as $key => $label)<option value="{{ $key }}" @selected($order->status === $key)>{{ $label }}</option>@endforeach</select>
      </label>
      <p class="help" style="margin-top:8px">Cancelling returns the items to stock. Restoring a cancelled order reserves them again.</p>
      <label class="field">Internal notes<textarea name="admin_notes" rows="5" maxlength="5000">{{ $order->admin_notes }}</textarea></label>
      <div class="form-actions" style="margin-top:14px"><button class="btn primary" type="submit">Update order</button></div>
    </form>
  </div>
@endsection
