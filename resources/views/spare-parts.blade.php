@extends('layouts.app', [
  'title' => $selectionLabel ? "{$selectionLabel} spare parts" : 'Laptop spare parts',
  'description' => 'Find laptop spare parts by brand, model and year: displays, keyboards, batteries, fans, hinges, DC jacks and more. OEM part numbers checked by SpeedTech, Kaduwela.',
])

@section('content')
  <div class="wrap page-head">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><b>Laptop spare parts</b></nav>
    <div class="eyebrow brand-text">BRAND → MODEL → YEAR → PART</div>
    <h1 class="page-title">Laptop spare parts</h1>
    <p class="page-sub">Select your laptop to see parts that fit. Not listed? Send us a request — we source parts for most models.</p>
  </div>

  <section class="wrap finder-section"><div class="finder-panel">
    @include('partials.finder')
    <form class="pn-search" action="{{ route('spare-parts') }}" method="get">
      <label for="pn">Know the part number?</label>
      <div class="pn-field"><input id="pn" name="q" value="{{ $search }}" placeholder="e.g. NV156FHM-N48 or HT03XL" maxlength="100"><button class="button button-outline" type="submit"><svg class="icon"><use href="#icon-search"/></svg>Search</button></div>
    </form>
  </div></section>

  @if ($products === null)
    <section class="wrap browse-block" aria-labelledby="brands-title">
      <h2 id="brands-title">Laptop brands</h2>
      <div class="brand-grid">@foreach ($brands as $brand)<a href="{{ route('spare-parts', ['brand' => $brand->id]) }}">{{ $brand->name }}</a>@endforeach</div>
    </section>
    <section class="wrap browse-block" aria-labelledby="types-title">
      <h2 id="types-title">Laptop component categories</h2>
      <div class="part-type-grid">@foreach ($partCategories as $category)<a href="{{ route('spare-parts', ['part' => $category->id]) }}"><svg class="icon"><use href="#icon-cpu"/></svg><span>{{ $category->name }}</span></a>@endforeach</div>
    </section>
  @else
    <section class="wrap parts-results" aria-labelledby="results-title">
      <div class="results-head">
        <div>
          <h2 id="results-title">{{ $products->total() }} {{ Str::plural('part', $products->total()) }}{{ $selectionLabel ? ' for '.$selectionLabel : '' }}{{ $search !== '' ? ' matching “'.$search.'”' : '' }}</h2>
          <p>Parts can differ within the same model and year. We confirm the OEM part number, connector and size with you before dispatch.</p>
        </div>
        <a class="text-link" href="{{ route('spare-parts') }}">Clear selection<svg class="icon"><use href="#icon-close"/></svg></a>
      </div>

      @if ($products->isEmpty())
        <div class="empty request-part">
          <svg class="icon"><use href="#icon-box"/></svg>
          <h3>We don't have this listed yet</h3>
          <p>Send a request and we'll check suppliers for {{ $selectionLabel ?: 'this part' }}.</p>
          <button class="button red" type="button" data-request-part
                  data-brand="{{ $selected['brand']?->name }}" data-model="{{ $selected['model']?->name }}"
                  data-generation="{{ $selected['generation']?->label }}" data-part="{{ $selected['part']?->name }}">Request this part<svg class="icon"><use href="#icon-arrow"/></svg></button>
        </div>
      @else
        <div class="part-list">
          @foreach ($products as $part)
            <article class="part-row">
              <a class="part-thumb" href="{{ $part->url() }}"><img src="{{ $part->imageUrl() }}" alt="" loading="lazy" width="120" height="90"></a>
              <div class="part-info">
                <small>{{ $part->partCategory?->name }}</small>
                <h3><a href="{{ $part->url() }}">{{ $part->name }}</a></h3>
                <dl class="spec-inline">
                  @if ($part->oem_part_number)<div><dt>P/N</dt><dd>{{ $part->oem_part_number }}</dd></div>@endif
                  @if ($part->specification)<div><dt>Spec</dt><dd>{{ $part->specification }}</dd></div>@endif
                  @if ($part->warranty)<div><dt>Warranty</dt><dd>{{ $part->warranty }}</dd></div>@endif
                </dl>
                @php($fits = $part->generations->map(fn ($g) => $g->laptopModel?->brand?->name.' '.$g->laptopModel?->name)->unique()->values())
                @if ($fits->isNotEmpty())<p class="fits">Fits: {{ $fits->take(4)->implode(', ') }}@if ($fits->count() > 4) +{{ $fits->count() - 4 }} more @endif</p>@endif
              </div>
              <div class="part-buy">
                <b class="product-price">{{ $part->priceLabel() }}</b>
                @if ($part->price !== null)<span class="stock stock-{{ $part->stockState() }}">{{ $part->stockLabel() }}</span>@endif
                @if ($part->isPurchasable())
                  <form action="{{ route('cart.add') }}" method="post" class="js-add-to-cart">@csrf<input type="hidden" name="product_id" value="{{ $part->id }}"><button class="button red" type="submit"><svg class="icon"><use href="#icon-cart"/></svg>Add to cart</button></form>
                @else
                  <button class="button button-outline" type="button" data-enquire="{{ $part->id }}" data-enquire-name="{{ $part->name }}">{{ $part->price === null ? 'Ask for price' : 'Check availability' }}</button>
                @endif
              </div>
            </article>
          @endforeach
        </div>
        {{ $products->links() }}
        <p class="finder-note">Can't see the exact part? <button class="link-button" type="button" data-request-part data-brand="{{ $selected['brand']?->name }}" data-model="{{ $selected['model']?->name }}" data-generation="{{ $selected['generation']?->label }}" data-part="{{ $selected['part']?->name }}">Send us a part request</button> and we'll source it.</p>
      @endif
    </section>
  @endif
@endsection
