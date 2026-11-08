{{-- Social media links. Optional: $class (extra CSS class), $labels (show text labels). --}}
@php
  $links = array_filter([
    'facebook' => config('speedtech.facebook_url') ? ['Facebook', config('speedtech.facebook_url')] : null,
    'google' => config('speedtech.reviews_url') ? ['Google', config('speedtech.reviews_url')] : null,
  ]);
@endphp
@if ($links)
  <div class="social {{ $class ?? '' }}">
    @foreach ($links as $network => [$label, $url])
      <a class="social-{{ $network }}" href="{{ $url }}" target="_blank" rel="noopener" aria-label="SpeedTech on {{ $label }}" title="SpeedTech on {{ $label }}">
        <svg class="social-icon" aria-hidden="true"><use href="#social-{{ $network }}"/></svg>
        @if (! empty($labels))<span>{{ $label === 'Google' ? 'Google reviews' : $label }}</span>@endif
      </a>
    @endforeach
  </div>
@endif
