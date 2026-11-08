@extends('layouts.app', [
  'title' => 'Laptop & computer repair services in Kaduwela',
  'description' => 'Laptop and PC repairs, motherboard repair, RAM and SSD upgrades, data recovery, virus removal, PC assembly, networking and IT support at SpeedTech, Kaduwela. Quote before any work.',
])

@php($services = config('speedtech.repair_services'))

@section('content')
  <section class="repair-hero">
    <div class="wrap repair-hero-inner">
      <div>
        <nav class="breadcrumbs light" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><b>Repair services</b></nav>
        <div class="eyebrow hero-anim"><span class="status-dot"></span>REPAIR. UPGRADE. GO FURTHER.</div>
        <h1 class="hero-anim">Expert laptop &amp; computer <em>repair services.</em></h1>
        <p class="hero-anim">From a cracked screen to a dead motherboard, our Kaduwela team diagnoses the problem, explains your options and gives you a clear quote before any work starts.</p>
        <div class="buttons hero-anim">
          <button class="button red" type="button" data-repair><svg class="icon"><use href="#icon-wrench"/></svg>Book a repair<svg class="icon"><use href="#icon-arrow"/></svg></button>
          <a class="button whatsapp-link" href="{{ whatsapp_url('Hi SpeedTech, I need help with a repair.') }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>Ask on WhatsApp</a>
        </div>
      </div>
      <ul class="repair-promises hero-anim">
        <li><svg class="icon"><use href="#icon-search"/></svg><span><b>Inspection first</b>We find the real fault</span></li>
        <li><svg class="icon"><use href="#icon-tag"/></svg><span><b>Quote before work</b>No surprise charges</span></li>
        <li><svg class="icon"><use href="#icon-shield"/></svg><span><b>Parts warranty</b>Written on your bill</span></li>
        <li><svg class="icon"><use href="#icon-clock"/></svg><span><b>Quick turnaround</b>Many jobs same day</span></li>
      </ul>
    </div>
  </section>

  <nav class="wrap service-jump" aria-label="Jump to a service">
    @foreach ($services as $service)<a href="#{{ $service['slug'] }}">{{ $service['name'] }}</a>@endforeach
  </nav>

  <section class="wrap repair-services" aria-labelledby="services-heading">
    <div class="section-heading"><div><div class="eyebrow brand-text">WHAT WE FIX</div><h2 id="services-heading">Our repair services</h2></div><p>Every job starts with an inspection.<br>You approve the quote before we begin.</p></div>
    <div class="service-grid">
      @foreach ($services as $service)
        <article class="service-card" id="{{ $service['slug'] }}">
          <div class="service-card-head">
            <span class="service-icon"><svg class="icon"><use href="#icon-{{ $service['icon'] }}"/></svg></span>
            <h3><a href="{{ route('repairs.show', $service['slug']) }}">{{ $service['name'] }}</a></h3>
          </div>
          <p>{{ $service['summary'] }}</p>
          <ul class="service-includes">
            @foreach ($service['includes'] as $item)<li><svg class="icon"><use href="#icon-check"/></svg>{{ $item }}</li>@endforeach
          </ul>
          <div class="service-foot">
            <span class="turnaround"><svg class="icon"><use href="#icon-clock"/></svg>{{ $service['turnaround'] }}</span>
            <div class="service-actions"><a class="button button-outline" href="{{ route('repairs.show', $service['slug']) }}">View details</a><button class="button red" type="button" data-service="{{ $service['name'] }}">Book</button></div>
          </div>
        </article>
      @endforeach
    </div>
    <p class="finder-note">Turnaround times are typical estimates. We confirm the time and price with your quote.</p>
  </section>

  <section class="wrap" aria-labelledby="process-heading"><div class="repair-process">
    <div class="eyebrow brand-text">HOW IT WORKS</div>
    <h2 id="process-heading">Simple, honest, four-step repairs</h2>
    <ol class="process-steps">
      @foreach (config('speedtech.repair_process') as $i => [$step, $text])
        <li><span class="step-number">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $step }}</h3><p>{{ $text }}</p></li>
      @endforeach
    </ol>
  </div></section>

  <section class="wrap" aria-labelledby="brands-heading"><div class="repair-brands">
    <div>
      <h2 id="brands-heading">We repair all major brands</h2>
      <p>Laptops, desktops and all-in-ones. Need a replacement part? <a class="text-link" href="{{ route('spare-parts') }}">Find it in our spare parts section</a> and we can fit it for you.</p>
    </div>
    <div class="chip-row">
      @foreach (['Dell', 'HP', 'Lenovo', 'ASUS', 'Acer', 'MSI', 'Apple', 'Toshiba', 'Samsung'] as $brand)<span class="chip">{{ $brand }}</span>@endforeach
    </div>
  </div></section>

  <section class="wrap repair-faq" aria-labelledby="faq-heading">
    <div class="faq-intro">
      <div class="eyebrow brand-text">GOOD TO KNOW</div>
      <h2 id="faq-heading">Repair questions, answered</h2>
      <p>Can't find your answer? Call us on <a class="text-link" href="tel:{{ config('speedtech.phone') }}">{{ config('speedtech.phone_display') }}</a> or send a WhatsApp message.</p>
    </div>
    <div class="faq-list">
      @foreach (config('speedtech.repair_faqs') as [$question, $answer])
        <details><summary>{{ $question }}<svg class="icon"><use href="#icon-plus"/></svg></summary><p>{{ $answer }}</p></details>
      @endforeach
    </div>
  </section>

  <section class="wrap"><div class="repair-cta">
    <div>
      <h2>Laptop trouble? Let's get it sorted.</h2>
      <p>Book online and we'll call you back, or visit us at {{ config('speedtech.address') }}.</p>
    </div>
    <div class="buttons">
      <button class="button red" type="button" data-repair>Book a repair<svg class="icon"><use href="#icon-arrow"/></svg></button>
      <a class="button white" href="{{ config('speedtech.maps_url') }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-pin"/></svg>Get directions</a>
    </div>
  </div></section>
@endsection
