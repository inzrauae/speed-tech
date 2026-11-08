@extends('layouts.app', [
  'title' => 'About us',
  'description' => 'SpeedTech IT Solutions is a local IT repair, upgrade and spare parts specialist in Kaduwela, Sri Lanka. Honest advice, the right part and careful repairs.',
])

@section('content')
  <section class="page-hero">
    <div class="wrap page-hero-inner">
      <nav class="breadcrumbs light" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><b>About us</b></nav>
      <div class="eyebrow hero-anim"><span class="status-dot"></span>ABOUT SPEEDTECH</div>
      <h1 class="hero-anim">Your local people.<br><em>Your tech, sorted.</em></h1>
      <p class="hero-anim">SpeedTech IT Solutions is a computer repair, upgrade and spare parts specialist based in Kaduwela. We help homes, students and small businesses keep their technology working.</p>
    </div>
  </section>

  <section class="wrap about-story">
    <div>
      <div class="eyebrow brand-text">WHO WE ARE</div>
      <h2>Good tech deserves a second chance.</h2>
      <p>A broken screen, a tired battery or a slow laptop shouldn't mean buying a new computer. We started SpeedTech to give people in our neighbourhood a place they can trust for honest advice, quality parts and careful repairs.</p>
      <p>We look at the real cause of a fault, explain your options in plain language, and give you a clear price before any work starts. If a repair isn't worth it, we'll tell you.</p>
      <div class="buttons">
        <a class="button red" href="{{ route('repairs') }}">Our repair services<svg class="icon"><use href="#icon-arrow"/></svg></a>
        <a class="button button-outline" href="{{ route('spare-parts') }}">Find a spare part</a>
      </div>
    </div>
    <img src="{{ asset('assets/hero-repair.png') }}" alt="Laptop, motherboard, memory and repair tools on a workbench" loading="lazy" width="720" height="480">
  </section>

  <section class="wrap about-offer" aria-labelledby="offer-title">
    <div class="eyebrow brand-text">WHAT WE DO</div>
    <h2 id="offer-title">Everything your tech needs, in one place</h2>
    <div class="offer-grid">
      <a class="offer-card" href="{{ route('repairs') }}"><span class="service-icon"><svg class="icon"><use href="#icon-wrench"/></svg></span><h3>Repairs</h3><p>Laptop and PC repairs, board-level motherboard repair, data recovery and virus removal.</p></a>
      <a class="offer-card" href="{{ route('spare-parts') }}"><span class="service-icon"><svg class="icon"><use href="#icon-laptop"/></svg></span><h3>Laptop spare parts</h3><p>Displays, keyboards, batteries, fans, hinges and more, matched by brand, model, year and part number.</p></a>
      <a class="offer-card" href="{{ route('repairs.show', 'ram-ssd-upgrades') }}"><span class="service-icon"><svg class="icon"><use href="#icon-ram"/></svg></span><h3>Upgrades</h3><p>RAM and SSD upgrades and custom PC builds that give you more speed for less money.</p></a>
      <a class="offer-card" href="{{ route('shop') }}"><span class="service-icon"><svg class="icon"><use href="#icon-headphones"/></svg></span><h3>Accessories</h3><p>Storage, memory, chargers, cables and everyday accessories for work and play.</p></a>
    </div>
  </section>

  <section class="about-values" aria-labelledby="values-title">
    <div class="wrap">
      <div class="eyebrow">HOW WE WORK</div>
      <h2 id="values-title">What you can expect from us</h2>
      <div class="values-grid">
        <div><svg class="icon"><use href="#icon-search"/></svg><h3>Honest diagnosis</h3><p>We find the real fault and explain it clearly, without jargon.</p></div>
        <div><svg class="icon"><use href="#icon-tag"/></svg><h3>Clear prices</h3><p>You approve the quote before we start. No surprise charges.</p></div>
        <div><svg class="icon"><use href="#icon-check"/></svg><h3>The right part</h3><p>We check OEM part numbers and compatibility before you buy.</p></div>
        <div><svg class="icon"><use href="#icon-shield"/></svg><h3>Work we stand behind</h3><p>Replacement parts come with a warranty written on your bill.</p></div>
      </div>
    </div>
  </section>

  <section class="wrap"><div class="repair-cta">
    <div>
      <h2>Have a question? Talk to us.</h2>
      <p>Call, WhatsApp or visit our store in Kaduwela.</p>
    </div>
    <div class="buttons">
      <a class="button red" href="{{ route('contact') }}">Contact us<svg class="icon"><use href="#icon-arrow"/></svg></a>
      <a class="button white" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>WhatsApp</a>
    </div>
  </div></section>
@endsection
