@extends('layouts.app')

@section('content')
  <section class="hero" aria-labelledby="hero-title">
    <img class="hero-art" src="{{ asset('assets/hero-repair.png') }}" alt="Laptop, motherboard, memory and repair tools in a red-lit hardware studio" fetchpriority="high">
    <div class="hero-shade"></div>
    <div class="wrap hero-inner"><div class="hero-copy">
      <div class="eyebrow hero-anim"><span class="status-dot"></span>YOUR LOCAL IT SPECIALISTS</div>
      <h1 id="hero-title" class="hero-anim">Your trusted<br><em>IT repair & upgrade</em><br>partner.</h1>
      <p class="hero-anim">Laptop spare parts by brand, model and year. Reliable accessories.<br>Expert repairs, right here in Kaduwela.</p>
      <div class="buttons hero-anim">
        <a class="button red" href="{{ route('spare-parts') }}"><svg class="icon"><use href="#icon-laptop"/></svg>Find a spare part<svg class="icon"><use href="#icon-arrow"/></svg></a>
        <button class="button white" type="button" data-repair><svg class="icon"><use href="#icon-wrench"/></svg>Book a repair<svg class="icon"><use href="#icon-arrow"/></svg></button>
      </div>
      <div class="hero-assurance hero-anim"><svg class="icon"><use href="#icon-shield"/></svg>Part numbers checked before you buy. Personal service.</div>
    </div><div class="hero-stamp hero-float"><svg class="icon"><use href="#icon-bolt"/></svg><span>Less downtime.<b>More possibilities.</b></span></div></div>
  </section>

  <section class="wrap category-section" aria-label="Shop by category"><div class="category-strip">
    <a href="{{ route('spare-parts') }}"><svg class="icon"><use href="#icon-laptop"/></svg><span>Laptop spare parts</span></a>
    <a href="{{ route('shop', ['category' => 'laptops']) }}"><svg class="icon"><use href="#icon-box"/></svg><span>Laptops</span></a>
    <a href="{{ route('shop', ['category' => 'storage']) }}"><svg class="icon"><use href="#icon-storage"/></svg><span>Storage <small>SSD / HDD</small></span></a>
    <a href="{{ route('shop', ['category' => 'ram']) }}"><svg class="icon"><use href="#icon-ram"/></svg><span>Memory / RAM</span></a>
    <a href="{{ route('shop') }}"><svg class="icon"><use href="#icon-headphones"/></svg><span>Accessories</span></a>
    <a href="{{ route('repairs') }}"><svg class="icon"><use href="#icon-wrench"/></svg><span>Repair services</span></a>
  </div></section>

  <section class="wrap finder-section" id="finder" aria-labelledby="finder-title"><div class="finder-panel">
    <div class="section-heading"><div><div class="eyebrow brand-text">THE RIGHT PART. THE FIRST TIME.</div><h2 id="finder-title">Find a part for your laptop.</h2></div><p>Brand → Model → Year → Part.<br>We confirm the OEM part number before you buy.</p></div>
    @include('partials.finder')
    <div class="quick-pick">
      <span>Popular brands</span>
      <div class="chip-row">@foreach ($brands as $brand)<a class="chip" href="{{ route('spare-parts', ['brand' => $brand->id]) }}">{{ $brand->name }}</a>@endforeach</div>
    </div>
  </div></section>

  <section class="wrap shop-section" id="shop" aria-labelledby="shop-title"><div class="store-layout">
    <aside class="service-sidebar" id="services" aria-labelledby="services-title">
      <div class="sidebar-heading"><svg class="icon"><use href="#icon-wrench"/></svg><h2 id="services-title">Our services</h2></div>
      <p>Good tech deserves <br>a second chance.</p>
      <ul class="service-list">
        @foreach (config('speedtech.services') as $service)
          <li><button type="button" data-service="{{ $service }}"><svg class="icon"><use href="#icon-check"/></svg>{{ $service }}</button></li>
        @endforeach
      </ul>
      <button class="button sidebar-button" type="button" data-repair>Book a repair<svg class="icon"><use href="#icon-arrow"/></svg></button>
      <a class="sidebar-more" href="{{ route('repairs') }}">See all service details <span aria-hidden="true">→</span></a>
      <div class="sidebar-part"><svg class="icon"><use href="#icon-laptop"/></svg><span>Looking for a spare part?<a href="{{ route('spare-parts') }}">Find the right fit <span aria-hidden="true">→</span></a></span></div>
    </aside>
    <div class="shop-main">
      <div class="section-heading"><div><div class="eyebrow brand-text">BETTER TECH STARTS HERE</div><h2 id="shop-title">Featured products</h2></div><a class="text-link" href="{{ route('shop') }}">Shop all accessories<svg class="icon"><use href="#icon-arrow"/></svg></a></div>
      @if ($featured->isEmpty())
        <div class="empty"><svg class="icon"><use href="#icon-box"/></svg><h3>New stock coming soon</h3><p>Ask us on WhatsApp for current models and prices.</p></div>
      @else
        <div class="products">@each('partials.product-card', $featured, 'product')</div>
      @endif
    </div>
  </div></section>

  <section class="home-repairs" aria-labelledby="home-repairs-title">
    <div class="wrap">
      <div class="home-repairs-head">
        <div>
          <div class="eyebrow brand-text">REPAIR. UPGRADE. GO FURTHER.</div>
          <h2 id="home-repairs-title">Expert repair services</h2>
          <p>Inspection first, a clear quote before any work, and a parts warranty on every repair. Choose a service to see what's included.</p>
        </div>
        <div class="buttons">
          <button class="button red" type="button" data-repair><svg class="icon"><use href="#icon-wrench"/></svg>Book a repair</button>
          <a class="button button-outline" href="{{ route('repairs') }}">All repair services<svg class="icon"><use href="#icon-arrow"/></svg></a>
        </div>
      </div>
      <div class="service-tiles">
        @foreach (config('speedtech.repair_services') as $service)@include('partials.service-card', ['service' => $service])@endforeach
      </div>
      <ul class="home-repair-steps">
        @foreach (config('speedtech.repair_process') as $i => [$step, $text])
          <li><span class="step-number">{{ $i + 1 }}</span><b>{{ $step }}</b></li>
        @endforeach
      </ul>
    </div>
  </section>

  <section class="wrap part-types" aria-labelledby="part-types-title">
    <div class="section-heading"><div><div class="eyebrow brand-text">LAPTOP COMPONENTS</div><h2 id="part-types-title">Shop spare parts by type</h2></div><a class="text-link" href="{{ route('spare-parts') }}">All spare parts<svg class="icon"><use href="#icon-arrow"/></svg></a></div>
    <div class="part-type-grid">
      @foreach ($partCategories as $category)
        <a href="{{ route('spare-parts', ['part' => $category->id]) }}"><svg class="icon"><use href="#icon-cpu"/></svg><span>{{ $category->name }}</span></a>
      @endforeach
    </div>
  </section>

  <section class="wrap support-section" aria-label="Expert repairs and support"><div class="support-grid">
    <div class="repair-promo"><span class="eyebrow">REPAIR. UPGRADE. GO FURTHER.</span><h2>Laptop trouble?<br>Let's get it sorted.</h2><p>From a simple upgrade to a complex repair,<br>we'll help you find the next step.</p><div class="buttons"><button class="button red" type="button" data-repair>Book a repair<svg class="icon"><use href="#icon-arrow"/></svg></button><a class="button outline-light" href="{{ route('repairs') }}">Our services</a></div></div>
    <div class="why-card"><h2>Why choose SpeedTech?</h2><p>A little expertise makes a big difference.</p><div class="why-grid"><div><svg class="icon"><use href="#icon-shield"/></svg><b>Reliable<br>repairs</b></div><div><svg class="icon"><use href="#icon-tag"/></svg><b>Options for<br>your budget</b></div><div><svg class="icon"><use href="#icon-bolt"/></svg><b>Practical<br>solutions</b></div><div><svg class="icon"><use href="#icon-headphones"/></svg><b>Personal<br>support</b></div></div></div>
    <div class="advice-card"><div class="advice-icon"><svg class="icon"><use href="#icon-whatsapp"/></svg></div><h2>Not sure what you need?</h2><p>Tell us about your setup. We'll help you choose the right part or repair.</p><a class="text-link" href="{{ whatsapp_url() }}" target="_blank" rel="noopener">Talk to our team<svg class="icon"><use href="#icon-arrow"/></svg></a></div>
  </div></section>

  <section class="wrap brand-shop" aria-labelledby="brand-shop-title">
    <div class="section-heading"><div><div class="eyebrow brand-text">SPARE PARTS BY BRAND</div><h2 id="brand-shop-title">Shop parts for your laptop brand</h2></div><a class="text-link" href="{{ route('spare-parts') }}">Open the part finder<svg class="icon"><use href="#icon-arrow"/></svg></a></div>
    <div class="brand-cards">
      @foreach ($brandCards as $brand)
        <article class="brand-card">
          <div class="brand-card-head">
            <h3><a href="{{ route('spare-parts', ['brand' => $brand->id]) }}">{{ $brand->name }}</a></h3>
            <span>{{ $brand->laptop_models_count }} {{ Str::plural('model', $brand->laptop_models_count) }}</span>
          </div>
          @if ($brand->laptopModels->isNotEmpty())
            <ul>
              @foreach ($brand->laptopModels as $model)
                <li><a href="{{ route('spare-parts', ['brand' => $brand->id, 'model' => $model->id]) }}">{{ $model->name }}</a></li>
              @endforeach
            </ul>
          @else
            <p>Ask us about parts for your {{ $brand->name }} laptop.</p>
          @endif
          <a class="text-link" href="{{ route('spare-parts', ['brand' => $brand->id]) }}">All {{ $brand->name }} parts<svg class="icon"><use href="#icon-arrow"/></svg></a>
        </article>
      @endforeach
      <article class="brand-card brand-card-cta">
        <h3>Other brand or model?</h3>
        <p>We source parts for most laptops. Tell us the model and we'll find it.</p>
        <button class="button red" type="button" data-request-part>Request a part<svg class="icon"><use href="#icon-arrow"/></svg></button>
      </article>
    </div>
  </section>

  <section class="wrap care-tips" aria-labelledby="tips-title">
    <div class="section-heading"><div><div class="eyebrow brand-text">TECH TIPS</div><h2 id="tips-title">Make your laptop last longer</h2></div><p>Simple habits that prevent the most common repairs we see.</p></div>
    <div class="tip-grid">
      @foreach (config('speedtech.care_tips') as $tip)
        @php
          $tipUrl = isset($tip['part'])
            ? route('spare-parts', array_filter(['part' => $partCategories->firstWhere('slug', $tip['part'])?->id]))
            : route($tip['route'][0], $tip['route'][1] ?? []);
        @endphp
        <article class="tip-card">
          <span class="service-icon"><svg class="icon"><use href="#icon-{{ $tip['icon'] }}"/></svg></span>
          <h3>{{ $tip['title'] }}</h3>
          <p>{{ $tip['text'] }}</p>
          <a class="text-link" href="{{ $tipUrl }}">{{ $tip['cta'] }}<svg class="icon"><use href="#icon-arrow"/></svg></a>
        </article>
      @endforeach
    </div>
  </section>

  <section class="wrap shop-help" aria-labelledby="shop-faq-title">
    <div class="shop-faq">
      <div class="eyebrow brand-text">SHOPPING WITH SPEEDTECH</div>
      <h2 id="shop-faq-title">Questions before you buy</h2>
      <div class="faq-list">
        @foreach (config('speedtech.shop_faqs') as [$question, $answer])
          <details><summary>{{ $question }}<svg class="icon"><use href="#icon-plus"/></svg></summary><p>{{ str_replace(':delivery_text', delivery_text(), $answer) }}</p></details>
        @endforeach
      </div>
    </div>
    <aside class="help-panel">
      <div class="advice-icon"><svg class="icon"><use href="#icon-search"/></svg></div>
      <h2>Can't find your part?</h2>
      <p>Send us your laptop model or a photo of the old part's label. We'll check our stock and suppliers and get back to you.</p>
      <div class="help-actions">
        <button class="button red" type="button" data-request-part>Request a part<svg class="icon"><use href="#icon-arrow"/></svg></button>
        <a class="button whatsapp-link" href="{{ whatsapp_url('Hi SpeedTech, I am looking for a laptop spare part.') }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Send a photo</a>
      </div>
      <a class="review-link" href="{{ config('speedtech.reviews_url') }}" target="_blank" rel="noopener"><span class="google-mark" aria-hidden="true">G</span>See what customers say on Google<svg class="icon"><use href="#icon-arrow"/></svg></a>
    </aside>
  </section>
@endsection
