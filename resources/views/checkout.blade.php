@extends('layouts.app', ['title' => 'Checkout'])

@section('content')
  <div class="wrap page-head">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('cart') }}">Cart</a><span>/</span><b>Checkout</b></nav>
    <h1 class="page-title">Checkout</h1>
  </div>

  <form class="wrap checkout-layout" action="{{ route('checkout.store') }}" method="post" id="checkoutForm"
        data-subtotal="{{ $subtotal }}" data-delivery-fee="{{ $deliveryFee }}" data-currency="{{ config('speedtech.currency') }}">
    @csrf
    @include('partials.honeypot')
    <div class="checkout-main">
      @if ($errors->any())
        <div class="flash flash-error" role="alert"><svg class="icon"><use href="#icon-info"/></svg><span>{{ $errors->first('cart') ?: 'Please check the highlighted fields.' }}</span></div>
      @endif

      <fieldset class="form-card">
        <legend>Your details</legend>
        <div class="form-grid">
          <label>Full name<input name="customer_name" value="{{ old('customer_name') }}" required maxlength="100" autocomplete="name">@error('customer_name')<span class="field-error">{{ $message }}</span>@enderror</label>
          <label>Phone number<input name="phone" type="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" placeholder="077 123 4567">@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
          <label class="span-2">E-mail <small>(optional, for your order confirmation)</small><input name="email" type="email" value="{{ old('email') }}" maxlength="150" autocomplete="email">@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
        </div>
      </fieldset>

      @php
        $fulfilmentChoices = array_keys(array_filter($paymentOptions));      // options with at least one payment method
        $chosenFulfilment = old('fulfilment', in_array('pickup', $fulfilmentChoices) ? 'pickup' : ($fulfilmentChoices[0] ?? 'delivery'));
        $methodsFor = [];                                                       // method => [fulfilment options]
        foreach ($paymentOptions as $fulfilment => $methods) { foreach ($methods as $m) { $methodsFor[$m][] = $fulfilment; } }
        $preferred = ['pickup' => 'pay_at_store', 'delivery' => 'cod'][$chosenFulfilment] ?? null;
        $chosenMethod = old('payment_method', in_array($preferred, $paymentOptions[$chosenFulfilment] ?? []) ? $preferred : ($paymentOptions[$chosenFulfilment][0] ?? null));
        $methodInfo = [
          'cod' => ['Cash on delivery', 'Pay the courier in cash when your order arrives.', 'truck'],
          'bank_transfer' => ['Bank transfer', 'Transfer to our bank account. We dispatch once the payment is received.', 'tag'],
          'pay_at_store' => ['Pay at the store', 'Cash or card when you collect from our Kaduwela store.', 'pin'],
        ];
      @endphp

      <fieldset class="form-card">
        <legend>Collection or delivery</legend>
        @foreach (\App\Models\Order::FULFILMENT as $value => $label)
          @continue(! in_array($value, $fulfilmentChoices))
          <label class="option-card">
            <input type="radio" name="fulfilment" value="{{ $value }}" @checked($chosenFulfilment === $value) required>
            <span><b>{{ $label }}</b><small>{{ $value === 'pickup' ? 'Free · 300/2/1 Samagi Mawatha, Kaduwela' : ($freeDeliveryOver > 0 ? money($deliveryFee).' · free over '.money($freeDeliveryOver) : ($deliveryFee > 0 ? money($deliveryFee) : 'Free delivery')) }}</small></span>
          </label>
        @endforeach
        <div class="form-grid" data-delivery-fields>
          <label class="span-2">Delivery address<textarea name="address" maxlength="500" autocomplete="street-address" rows="2">{{ old('address') }}</textarea>@error('address')<span class="field-error">{{ $message }}</span>@enderror</label>
          <label>City<input name="city" value="{{ old('city') }}" maxlength="100" autocomplete="address-level2">@error('city')<span class="field-error">{{ $message }}</span>@enderror</label>
        </div>
      </fieldset>

      <fieldset class="form-card">
        <legend>Payment</legend>
        @foreach ($methodInfo as $value => [$label, $help, $icon])
          @continue(! isset($methodsFor[$value]))
          <label class="option-card pay-option" data-payment="{{ $value }}" data-for="{{ implode(' ', $methodsFor[$value]) }}">
            <input type="radio" name="payment_method" value="{{ $value }}" @checked($chosenMethod === $value) required>
            <span><b><svg class="icon"><use href="#icon-{{ $icon }}"/></svg>{{ $label }}</b><small>{{ $help }}</small></span>
          </label>
        @endforeach
        @if (isset($methodsFor['bank_transfer']))
          <div class="bank-box" data-bank-details>
            <b>Our bank account</b>
            @if ($bankDetails)
              <dl>@foreach ($bankDetails as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
            @endif
            @if ($bankInstructions)<p>{{ $bankInstructions }}</p>@endif
            <p>You'll get your order number on the next page, with an option to upload the payment slip.</p>
          </div>
        @endif
        @error('payment_method')<span class="field-error">{{ $message }}</span>@enderror
      </fieldset>

      <fieldset class="form-card">
        <legend>Order notes <small>(optional)</small></legend>
        <textarea name="notes" maxlength="1000" rows="3" placeholder="Laptop service tag, preferred call time, fitting request…">{{ old('notes') }}</textarea>
      </fieldset>
    </div>

    <aside class="order-summary">
      <h2>Your order</h2>
      <ul class="summary-lines">
        @foreach ($lines as $line)
          <li><span>{{ $line['quantity'] }} × {{ $line['product']->name }}</span><b>{{ money($line['line_total']) }}</b></li>
        @endforeach
      </ul>
      <dl>
        <div><dt>Subtotal</dt><dd>{{ money($subtotal) }}</dd></div>
        <div><dt>Delivery</dt><dd data-delivery-output>{{ old('fulfilment') === 'delivery' ? money($deliveryFee) : 'Free' }}</dd></div>
        <div class="total"><dt>Total</dt><dd data-total-output>{{ money($subtotal + (old('fulfilment') === 'delivery' ? $deliveryFee : 0)) }}</dd></div>
      </dl>
      <button class="button red full" type="submit">Place order<svg class="icon"><use href="#icon-arrow"/></svg></button>
      <p class="detail-note">We'll call you to confirm your order and the part's compatibility before dispatch or collection.</p>
    </aside>
  </form>
@endsection
