@extends('layouts.app', [
  'title' => 'Contact us',
  'description' => 'Contact SpeedTech IT Solutions in Kaduwela. Call '.config('speedtech.phone_display').', WhatsApp us, or visit 300/2/1 Samagi Mawatha, Kaduwela for laptop repairs, spare parts and accessories.',
])

@php($shop = config('speedtech'))

@section('content')
  <section class="page-hero">
    <div class="wrap page-hero-inner">
      <nav class="breadcrumbs light" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><b>Contact us</b></nav>
      <div class="eyebrow hero-anim"><span class="status-dot"></span>ONLINE & IN YOUR NEIGHBOURHOOD</div>
      <h1 class="hero-anim">Real people. <em>Right here.</em></h1>
      <p class="hero-anim">Questions about a repair, a spare part or an order? Call, message or drop in. Our Kaduwela team is happy to help.</p>
    </div>
  </section>

  <section class="wrap contact-methods" aria-label="Ways to contact us">
    <a class="method-card" href="tel:{{ $shop['phone'] }}">
      <span class="service-icon"><svg class="icon"><use href="#icon-phone"/></svg></span>
      <b>Call us</b><span>{{ $shop['phone_display'] }}</span>
    </a>
    <a class="method-card method-whatsapp" href="{{ whatsapp_url('Hi SpeedTech, I have a question.') }}" target="_blank" rel="noopener">
      <span class="service-icon"><svg class="icon"><use href="#icon-whatsapp"/></svg></span>
      <b>WhatsApp</b><span>Send a message or a photo</span>
    </a>
    <a class="method-card" href="mailto:{{ $shop['email'] }}">
      <span class="service-icon"><svg class="icon"><use href="#icon-mail"/></svg></span>
      <b>E-mail</b><span>{{ $shop['email'] }}</span>
    </a>
    <a class="method-card" href="{{ $shop['maps_url'] }}" target="_blank" rel="noopener">
      <span class="service-icon"><svg class="icon"><use href="#icon-pin"/></svg></span>
      <b>Visit the store</b><span>Samagi Mawatha, Kaduwela</span>
    </a>
  </section>

  <section class="wrap contact-main">
    <div class="form-card contact-form" data-enquiry-wrap>
      <h2>Send us a message</h2>
      <p class="help-text">We'll reply by phone or WhatsApp. For a repair, you can also <button class="link-button" type="button" data-repair>book a repair</button> directly.</p>
      <form class="js-enquiry" action="{{ route('enquiries.store') }}" method="post">
        @csrf
        <input type="hidden" name="type" value="general">
        @include('partials.honeypot')
        <div class="form-grid">
          <label>Your name<input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"></label>
          <label>Phone number<input name="phone" type="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" placeholder="077 123 4567"></label>
          <label class="span-2">E-mail <small>(optional)</small><input name="email" type="email" value="{{ old('email') }}" maxlength="150" autocomplete="email"></label>
          <label class="span-2">How can we help?<textarea name="message" required maxlength="2000" rows="5" placeholder="Your laptop model, the part you need, or your question…">{{ old('message') }}</textarea></label>
        </div>
        @if ($errors->any())<div class="form-error">{{ $errors->first() }}</div>@else<div class="form-error" hidden></div>@endif
        <button class="button red" type="submit">Send message<svg class="icon"><use href="#icon-arrow"/></svg></button>
      </form>
      <div class="enquiry-done" hidden></div>
    </div>

    <div class="contact-side">
      <div class="map-card">
        <iframe src="{{ $shop['maps_embed'] }}" title="Map showing SpeedTech IT Solutions in Kaduwela" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
      <div class="form-card visit-card">
        <h2>Visit SpeedTech IT Solutions</h2>
        <p>{{ $shop['address'] }}</p>
        <div class="help-actions">
          <a class="button red" href="{{ $shop['maps_url'] }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-pin"/></svg>Get directions</a>
        </div>
        <div class="follow-us"><span>Follow us</span>@include('partials.social', ['class' => 'social-brand', 'labels' => true])</div>
        <p class="detail-note">Bringing a device in? Call ahead and we'll make sure the right technician is free.</p>
      </div>
    </div>
  </section>
@endsection
