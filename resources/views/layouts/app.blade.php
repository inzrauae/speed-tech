@php($shop = config('speedtech'))
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ed171f">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script>document.documentElement.classList.add('js')</script>
  <title>{{ isset($title) ? $title.' | '.$shop['name'] : $shop['name'].' | Repairs, Upgrades, Spare Parts & Accessories' }}</title>
  <meta name="description" content="{{ $description ?? 'Your local IT repair and upgrade partner in Kaduwela. Shop laptop spare parts by brand, model and year, computer accessories, or book a repair with SpeedTech.' }}">
  <link rel="icon" href="{{ asset('assets/favicon.png') }}" type="image/png">
  <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
  <link rel="stylesheet" href="{{ asset('shop.css') }}?v={{ filemtime(public_path('shop.css')) }}">
  <link rel="stylesheet" href="{{ asset('pages.css') }}?v={{ filemtime(public_path('pages.css')) }}">
</head>
<body>
  @include('partials.icons')
  <a class="skip-link" href="#main">Skip to content</a>

  <div class="topbar"><div class="wrap topbar-inner"><div class="top-contact">
    <a href="{{ $shop['maps_url'] }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-pin"/></svg>Kaduwela, Sri Lanka</a>
    <a href="tel:{{ $shop['phone'] }}"><svg class="icon"><use href="#icon-phone"/></svg>{{ $shop['phone_display'] }}</a>
    <a class="top-email" href="mailto:{{ $shop['email'] }}"><svg class="icon"><use href="#icon-mail"/></svg>{{ $shop['email'] }}</a>
  </div><span class="top-tagline">Your local people. Your tech, sorted.</span></div></div>

  <header class="masthead"><div class="wrap header">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $shop['name'] }} home"><img class="logo" src="{{ asset('assets/logo.png') }}" width="681" height="284" alt="{{ $shop['name'] }}"></a>
    <form class="search" action="{{ route('shop') }}" method="get" role="search">
      <input name="q" type="search" value="{{ request()->routeIs('shop') ? request('q') : '' }}" aria-label="Search products" placeholder="Search parts, part numbers, accessories…" autocomplete="off" maxlength="100">
      <button type="submit" aria-label="Search"><svg class="icon"><use href="#icon-search"/></svg></button>
    </form>
    <a class="header-help" href="tel:{{ $shop['phone'] }}"><svg class="icon"><use href="#icon-headphones"/></svg><span><small>Need expert advice?</small><b>{{ $shop['phone_display'] }}</b></span></a>
    <a class="bag" href="{{ route('cart') }}" aria-label="Cart, {{ $cartCount }} {{ Str::plural('item', $cartCount) }}" data-cart-link>
      <span class="bag-icon"><svg class="icon"><use href="#icon-cart"/></svg><span id="count">{{ $cartCount }}</span></span>
      <span class="bag-label"><b>My cart</b><small>Review &amp; checkout</small></span>
    </a>
  </div></header>

  <nav class="main-nav" aria-label="Main navigation"><div class="wrap nav">
    <a class="home-link" href="{{ route('home') }}" aria-label="Home"><svg class="icon"><use href="#icon-home"/></svg></a>
    <a href="{{ route('spare-parts') }}" @class(['active' => request()->routeIs('spare-parts')])>Laptop spare parts</a>
    <a href="{{ route('shop') }}" @class(['active' => request()->routeIs('shop') && ! request('category')])>Accessories</a>
    <a href="{{ route('repairs') }}" @class(['active' => request()->routeIs('repairs*')])>Repair services</a>
    <a href="{{ route('about') }}" @class(['active' => request()->routeIs('about')])>About us</a>
    <a href="{{ route('contact') }}" @class(['active' => request()->routeIs('contact')])>Contact us</a>
    <a class="whatsapp-link" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Chat on WhatsApp</a>
  </div></nav>

  <main id="main">
    @if (session('status') || session('error'))
      <div class="wrap"><div @class(['flash', 'flash-error' => session('error')]) role="status">
        <svg class="icon"><use href="#icon-{{ session('error') ? 'info' : 'check' }}"/></svg>
        <span>{{ session('status') ?? session('error') }}</span>
        @if (session('whatsapp'))<a class="text-link" href="{{ session('whatsapp') }}" target="_blank" rel="noopener">Follow up on WhatsApp<svg class="icon"><use href="#icon-arrow"/></svg></a>@endif
      </div></div>
    @endif

    @yield('content')
  </main>

  <section class="benefits" aria-label="Why shop with SpeedTech">
    <div class="wrap benefit-row">
      <div><svg class="icon"><use href="#icon-check"/></svg><span><b>Right part, first time</b>Part numbers checked before dispatch</span></div>
      <div><svg class="icon"><use href="#icon-truck"/></svg><span><b>Island-wide delivery</b>Or collect free in Kaduwela</span></div>
      <div><svg class="icon"><use href="#icon-shield"/></svg><span><b>Parts warranty</b>Shown on every product</span></div>
      <div><svg class="icon"><use href="#icon-wrench"/></svg><span><b>Expert fitting</b>We can install it for you</span></div>
    </div>
  </section>

  <footer><div class="wrap footer-top">
    <div class="footer-brand"><a href="{{ route('home') }}" aria-label="SpeedTech home"><img src="{{ asset('assets/logo-light.png') }}" width="681" height="284" alt="{{ $shop['name'] }}"></a><p>Repair <span>•</span> Upgrade <span>•</span> Shop</p></div>
    <div class="footer-links"><h2>Quick links</h2><a href="{{ route('spare-parts') }}">Laptop spare parts</a><a href="{{ route('shop') }}">Computer accessories</a><a href="{{ route('repairs') }}">Repair services</a><a href="{{ route('about') }}">About us</a><a href="{{ route('contact') }}">Contact us</a><a href="{{ route('cart') }}">My cart</a></div>
    <div class="footer-links" id="contact"><h2>Contact us</h2><a href="tel:{{ $shop['phone'] }}"><svg class="icon"><use href="#icon-phone"/></svg>{{ $shop['phone_display'] }}</a><a href="mailto:{{ $shop['email'] }}"><svg class="icon"><use href="#icon-mail"/></svg>{{ $shop['email'] }}</a><a href="{{ $shop['maps_url'] }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-pin"/></svg>{{ $shop['address'] }}</a><a href="{{ $shop['reviews_url'] }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-check"/></svg>Read our Google reviews</a></div>
    <div class="footer-connect"><h2>Let's talk tech.</h2><p>Good advice is just a message away.</p><a class="button whatsapp-link" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Chat on WhatsApp<svg class="icon"><use href="#icon-arrow"/></svg></a></div>
  </div><div class="wrap footer-bottom"><span>© {{ date('Y') }} {{ $shop['name'] }}. All rights reserved.</span><span>Your tech. In good hands.</span></div></footer>

  {{-- Repair booking --}}
  <dialog id="repairDialog" aria-labelledby="repair-title">
    <button class="close" type="button" data-close aria-label="Close repair form"><svg class="icon"><use href="#icon-close"/></svg></button>
    <div class="dialog-icon"><svg class="icon"><use href="#icon-wrench"/></svg></div>
    <div class="eyebrow brand-text">LET'S GET IT SORTED</div>
    <h2 id="repair-title">Tell us about your tech.</h2>
    <p>Book a repair and we'll call you to confirm the next steps and availability.</p>
    <form class="js-enquiry" action="{{ route('enquiries.store') }}" method="post">
      @csrf
      <input type="hidden" name="type" value="repair">
      @include('partials.honeypot')
      <label>Your name<input name="name" required autocomplete="name" placeholder="Your name" maxlength="100"></label>
      <label>Phone number<input name="phone" type="tel" required autocomplete="tel" placeholder="e.g. 077 123 4567" maxlength="20"></label>
      <label>Device / model<input name="device" required placeholder="e.g. Dell Inspiron 15 3520" maxlength="160"></label>
      <label>Service<select name="service" id="service">@foreach ($shop['services'] as $service)<option>{{ $service }}</option>@endforeach</select></label>
      <label>What's happening?<textarea name="message" required placeholder="Describe the issue…" maxlength="2000"></textarea></label>
      <div class="form-error" hidden></div>
      <button class="button red" type="submit"><svg class="icon"><use href="#icon-wrench"/></svg>Book my repair<svg class="icon"><use href="#icon-arrow"/></svg></button>
    </form>
    <div class="enquiry-done" hidden></div>
  </dialog>

  {{-- Product / part enquiry --}}
  <dialog id="enquiryDialog" aria-labelledby="enquiry-title">
    <button class="close" type="button" data-close aria-label="Close enquiry form"><svg class="icon"><use href="#icon-close"/></svg></button>
    <div class="dialog-icon"><svg class="icon"><use href="#icon-search"/></svg></div>
    <div class="eyebrow brand-text">THE RIGHT PART. THE FIRST TIME.</div>
    <h2 id="enquiry-title">Ask us about this item.</h2>
    <p class="enquiry-subject"></p>
    <form class="js-enquiry" action="{{ route('enquiries.store') }}" method="post">
      @csrf
      <input type="hidden" name="type" value="product">
      <input type="hidden" name="product_id">
      <input type="hidden" name="details[brand]"><input type="hidden" name="details[model]"><input type="hidden" name="details[generation]"><input type="hidden" name="details[part]">
      @include('partials.honeypot')
      <label>Your name<input name="name" required autocomplete="name" placeholder="Your name" maxlength="100"></label>
      <label>Phone number<input name="phone" type="tel" required autocomplete="tel" placeholder="e.g. 077 123 4567" maxlength="20"></label>
      <label>OEM part number <small>(optional, from the old part or laptop label)</small><input name="details[part_number]" maxlength="100" placeholder="e.g. NV156FHM-N48"></label>
      <label>Anything else? <small>(optional)</small><textarea name="message" maxlength="2000" placeholder="Service tag / serial, connector type, quantity…"></textarea></label>
      <div class="form-error" hidden></div>
      <button class="button red" type="submit">Send enquiry<svg class="icon"><use href="#icon-arrow"/></svg></button>
    </form>
    <div class="enquiry-done" hidden></div>
  </dialog>

  {{-- Floating WhatsApp button (tablet & desktop; phones use the bar below) --}}
  <a class="wa-float" href="{{ whatsapp_url('Hi SpeedTech, I need some help.') }}" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <svg class="icon" aria-hidden="true"><use href="#icon-whatsapp"/></svg><span>Chat with us</span>
  </a>

  {{-- Mobile quick actions --}}
  <div class="mobile-actions" aria-label="Quick contact">
    <a class="ma-call" href="tel:{{ $shop['phone'] }}"><svg class="icon"><use href="#icon-phone"/></svg><span>Call us</span></a>
    <a class="ma-whatsapp" href="{{ whatsapp_url('Hi SpeedTech, I need some help.') }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg><span>WhatsApp</span></a>
  </div>

  <div id="toast" role="status" aria-live="polite"></div>
  <script src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}" defer></script>
</body>
</html>
