{{-- Compact repair service card. Expects $service (from config speedtech.repair_services). --}}
<article class="service-tile">
  <a class="service-tile-link" href="{{ route('repairs.show', $service['slug']) }}">
    <span class="service-icon"><svg class="icon"><use href="#icon-{{ $service['icon'] }}"/></svg></span>
    <h3>{{ $service['name'] }}</h3>
    <p>{{ $service['summary'] }}</p>
    <span class="turnaround"><svg class="icon"><use href="#icon-clock"/></svg>{{ $service['turnaround'] }}</span>
    <span class="text-link">View details<svg class="icon"><use href="#icon-arrow"/></svg></span>
  </a>
</article>
