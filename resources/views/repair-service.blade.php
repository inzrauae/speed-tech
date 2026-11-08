@extends('layouts.app', [
  'title' => $service['name'].' in Kaduwela',
  'description' => $service['summary'].' SpeedTech IT Solutions, Kaduwela. Inspection first, quote before any work.',
])

@section('content')
  <section class="repair-hero service-hero">
    <div class="wrap repair-hero-inner">
      <div>
        <nav class="breadcrumbs light" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('repairs') }}">Repair services</a><span>/</span><b>{{ $service['name'] }}</b></nav>
        <span class="service-icon large hero-anim"><svg class="icon"><use href="#icon-{{ $service['icon'] }}"/></svg></span>
        <h1 class="hero-anim">{{ $service['name'] }}</h1>
        <p class="hero-anim">{{ $service['summary'] }}</p>
        <div class="buttons hero-anim">
          <button class="button red" type="button" data-service="{{ $service['name'] }}"><svg class="icon"><use href="#icon-wrench"/></svg>Book this service<svg class="icon"><use href="#icon-arrow"/></svg></button>
          <a class="button whatsapp-link" href="{{ whatsapp_url("Hi SpeedTech, I'd like to ask about {$service['name']}.") }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Ask on WhatsApp</a>
        </div>
      </div>
      <aside class="service-facts hero-anim" aria-label="Service summary">
        <div><svg class="icon"><use href="#icon-clock"/></svg><span><b>Typical turnaround</b>{{ $service['turnaround'] }}</span></div>
        <div><svg class="icon"><use href="#icon-tag"/></svg><span><b>Price</b>Quoted after inspection, before any work</span></div>
        <div><svg class="icon"><use href="#icon-shield"/></svg><span><b>Warranty</b>Parts warranty written on your bill</span></div>
        <div><svg class="icon"><use href="#icon-pin"/></svg><span><b>Where</b>Our Kaduwela store{{ $service['slug'] === 'networking' ? ' or on-site' : '' }}</span></div>
      </aside>
    </div>
  </section>

  <section class="wrap service-detail">
    <div class="service-detail-main">
      <div class="detail-block">
        <h2>About this service</h2>
        <p>{{ $service['description'] }}</p>
      </div>
      <div class="service-columns">
        <div class="detail-block">
          <h2>Signs you need it</h2>
          <ul class="service-includes">@foreach ($service['signs'] as $sign)<li><svg class="icon"><use href="#icon-info"/></svg>{{ $sign }}</li>@endforeach</ul>
        </div>
        <div class="detail-block">
          <h2>What's included</h2>
          <ul class="service-includes">@foreach ($service['includes'] as $item)<li><svg class="icon"><use href="#icon-check"/></svg>{{ $item }}</li>@endforeach</ul>
        </div>
      </div>
      <div class="detail-block">
        <h2>How it works</h2>
        <ol class="mini-steps">
          @foreach (config('speedtech.repair_process') as $i => [$step, $text])
            <li><span class="step-number">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $step }}</h3><p>{{ $text }}</p></div></li>
          @endforeach
        </ol>
      </div>
    </div>

    <aside class="service-book">
      <div class="dialog-icon"><svg class="icon"><use href="#icon-wrench"/></svg></div>
      <h2>Book {{ Str::lower($service['name']) }}</h2>
      <p>Tell us about your device and we'll call you to confirm the next steps.</p>
      <button class="button red full" type="button" data-service="{{ $service['name'] }}">Book online<svg class="icon"><use href="#icon-arrow"/></svg></button>
      <a class="button button-outline full" href="tel:{{ config('speedtech.phone') }}"><svg class="icon"><use href="#icon-phone"/></svg>{{ config('speedtech.phone_display') }}</a>
      <a class="button whatsapp-link full" href="{{ whatsapp_url("Hi SpeedTech, I'd like to book {$service['name']}.") }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>WhatsApp us</a>
      <p class="detail-note">{{ config('speedtech.address') }}</p>
    </aside>
  </section>

  <section class="wrap related-services" aria-labelledby="other-services">
    <div class="section-heading"><div><div class="eyebrow brand-text">MORE WAYS WE CAN HELP</div><h2 id="other-services">Other repair services</h2></div><a class="text-link" href="{{ route('repairs') }}">All repair services<svg class="icon"><use href="#icon-arrow"/></svg></a></div>
    <div class="service-tiles">@foreach ($others->take(4) as $other)@include('partials.service-card', ['service' => $other])@endforeach</div>
  </section>
@endsection
