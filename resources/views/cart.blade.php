@extends('layouts.app', ['title' => 'Your cart'])

@section('content')
  <div class="wrap page-head">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><b>Cart</b></nav>
    <h1 class="page-title">Your cart</h1>
  </div>

  <section class="wrap checkout-layout">
    @if ($lines->isEmpty())
      <div class="empty wide">
        <svg class="icon"><use href="#icon-cart"/></svg>
        <h3>Your cart is empty</h3>
        <p>Find a spare part for your laptop or browse our accessories.</p>
        <div class="buttons center"><a class="button red" href="{{ route('spare-parts') }}">Find a spare part</a><a class="button button-outline" href="{{ route('shop') }}">Shop accessories</a></div>
      </div>
    @else
      <div class="cart-lines">
        @foreach ($lines as $line)
          @php($product = $line['product'])
          <div class="cart-line">
            <a class="part-thumb" href="{{ $product->url() }}"><img src="{{ $product->imageUrl() }}" alt="" width="96" height="72"></a>
            <div class="cart-info">
              <a href="{{ $product->url() }}"><b>{{ $product->name }}</b></a>
              <small>{{ $product->groupLabel() }}@if ($product->oem_part_number) · P/N {{ $product->oem_part_number }}@endif</small>
              <small>{{ money($product->price) }} each</small>
            </div>
            <form class="qty-form" action="{{ route('cart.update', $product) }}" method="post">
              @csrf @method('PATCH')
              <label class="visually-hidden" for="qty-{{ $product->id }}">Quantity for {{ $product->name }}</label>
              <input id="qty-{{ $product->id }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="{{ $product->maxOrderQuantity() }}" data-autosubmit>
              <noscript><button class="link-button" type="submit">Update</button></noscript>
            </form>
            <b class="line-total">{{ money($line['line_total']) }}</b>
            <form action="{{ route('cart.remove', $product) }}" method="post">
              @csrf @method('DELETE')
              <button class="icon-button" type="submit" aria-label="Remove {{ $product->name }}"><svg class="icon"><use href="#icon-trash"/></svg></button>
            </form>
          </div>
        @endforeach
      </div>

      <aside class="order-summary">
        <h2>Order summary</h2>
        <dl>
          <div><dt>Subtotal</dt><dd>{{ money($subtotal) }}</dd></div>
          <div><dt>Delivery</dt><dd>Calculated at checkout</dd></div>
        </dl>
        <a class="button red full" href="{{ route('checkout') }}">Proceed to checkout<svg class="icon"><use href="#icon-arrow"/></svg></a>
        <a class="text-link" href="{{ route('shop') }}">Continue shopping</a>
        <p class="detail-note">Collect free from our Kaduwela store, or island-wide delivery. {{ delivery_text() }}</p>
      </aside>
    @endif
  </section>
@endsection
