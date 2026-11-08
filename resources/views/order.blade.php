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
          <div><dt>Payment status</dt><dd>{{ $order->paymentStatusLabel() }}</dd></div>
        </dl>
        @if ($order->payment_method === 'cod')
          <p class="detail-note">Please keep <b>{{ money($order->total) }}</b> ready in cash for the courier.</p>
        @endif
        <a class="button whatsapp-link full" href="{{ whatsapp_url("Hi SpeedTech, I just placed order {$order->number}.") }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Message us about this order</a>
      </div>
    </div>

    @if ($order->payment_method === 'bank_transfer' && ! $order->isPaid() && $order->status !== 'cancelled')
      <div class="form-card bank-pay" id="pay">
        <h2>Complete your bank transfer</h2>
        <div class="bank-pay-grid">
          <div>
            <dl class="detail-list">
              <div><dt>Amount</dt><dd><b>{{ money($order->total) }}</b></dd></div>
              <div><dt>Reference</dt><dd><b>{{ $order->number }}</b></dd></div>
              @foreach ($bankDetails as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
            </dl>
            @if ($bankInstructions)<p class="detail-note">{{ $bankInstructions }}</p>@endif
          </div>
          <form class="slip-form" action="{{ $slipUploadUrl }}" method="post" enctype="multipart/form-data">
            @csrf
            @if ($order->payment_slip)
              <p class="slip-done"><svg class="icon"><use href="#icon-check"/></svg>Payment slip received. We'll confirm your payment soon. You can upload a new one if needed.</p>
            @endif
            <label>Upload your payment slip <small>(photo or PDF, max 5 MB)</small>
              <input type="file" name="slip" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            </label>
            @error('slip')<span class="field-error">{{ $message }}</span>@enderror
            <button class="button red" type="submit"><svg class="icon"><use href="#icon-check"/></svg>{{ $order->payment_slip ? 'Replace slip' : 'Upload slip' }}</button>
            <p class="help-text">Prefer WhatsApp? Send the slip to {{ config('speedtech.phone_display') }} with your order number.</p>
          </form>
        </div>
      </div>
    @elseif ($order->isPaid())
      <p class="detail-note">Payment received{{ $order->paid_at ? ' on '.$order->paid_at->format('d M Y') : '' }}. Thank you!</p>
    @endif
    <a class="text-link" href="{{ route('home') }}">Back to the store<svg class="icon"><use href="#icon-arrow"/></svg></a>
  </section>
@endsection
