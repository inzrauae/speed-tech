@extends('layouts.app', ['title' => 'Order '.$order->number])

@section('content')
  <section class="wrap confirmation">
    <div class="dialog-icon"><svg class="icon"><use href="#icon-check"/></svg></div>
    <div class="eyebrow brand-text">ORDER RECEIVED</div>
    <h1 class="page-title">Thank you, {{ $order->customer_name }}.</h1>
    <p class="lead">Your order number is <b>{{ $order->number }}</b>. We'll call you on {{ $order->phone }} to confirm the details{{ $order->fulfilment === 'pickup' ? ' and let you know when it is ready to collect' : ' and arrange delivery' }}.</p>

    <div class="confirmation-grid">
      <div class="form-card">
        <h2>Order summary</h2>
        <ul class="summary-lines">
          @foreach ($order->items as $item)
            <li><span>{{ $item->quantity }} × {{ $item->product_name }}@if ($item->oem_part_number)<small>P/N {{ $item->oem_part_number }}</small>@endif</span><b>{{ money($item->line_total) }}</b></li>
          @endforeach
        </ul>
        <dl>
          <div><dt>Subtotal</dt><dd>{{ money($order->subtotal) }}</dd></div>
          <div><dt>Delivery</dt><dd>{{ $order->delivery_fee > 0 ? money($order->delivery_fee) : 'Free' }}</dd></div>
          <div class="total"><dt>Total</dt><dd>{{ money($order->total) }}</dd></div>
        </dl>
      </div>
      <div class="form-card">
        <h2>Details</h2>
        <dl class="detail-list">
          <div><dt>Status</dt><dd>{{ $order->statusLabel() }}</dd></div>
          <div><dt>Fulfilment</dt><dd>{{ $order->fulfilmentLabel() }}</dd></div>
          @if ($order->address)<div><dt>Deliver to</dt><dd>{{ $order->address }}, {{ $order->city }}</dd></div>@endif
          <div><dt>Payment</dt><dd>{{ $order->paymentLabel() }}</dd></div>
        </dl>
        @if ($order->payment_method === 'bank_transfer')<p class="detail-note">{{ config('speedtech.bank_details') }} Please use <b>{{ $order->number }}</b> as the payment reference.</p>@endif
        <a class="button whatsapp-link full" href="{{ whatsapp_url("Hi SpeedTech, I just placed order {$order->number}.") }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Message us about this order</a>
      </div>
    </div>
    <a class="text-link" href="{{ route('home') }}">Back to the store<svg class="icon"><use href="#icon-arrow"/></svg></a>
  </section>
@endsection
