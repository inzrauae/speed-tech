@extends('layouts.app', ['title' => $search !== '' ? "Search: {$search}" : ($category?->name ?? 'Computer accessories')])

@section('content')
  <div class="wrap page-head">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span>@if ($category)<a href="{{ route('shop') }}">Accessories</a><span>/</span><b>{{ $category->name }}</b>@else<b>{{ $search !== '' ? 'Search' : 'Accessories' }}</b>@endif</nav>
    <div class="eyebrow brand-text">UNIVERSAL COMPUTER ACCESSORIES</div>
    <h1 class="page-title">
      @if ($search !== '') Results for “{{ $search }}” @else {{ $category?->name ?? 'Computer accessories' }} @endif
    </h1>
    @if ($search !== '')<p class="page-sub">Searching all accessories and laptop spare parts by name, SKU and OEM part number.</p>@endif
  </div>

  <section class="wrap shop-page">
    <aside class="filter-panel" aria-label="Filter products">
      <h2>Categories</h2>
      <ul class="filter-list">
        <li><a href="{{ route('shop', array_filter(['q' => $search ?: null])) }}" @class(['active' => ! $category])>All accessories</a></li>
        @foreach ($categories as $item)
          <li><a href="{{ route('shop', array_filter(['category' => $item->slug, 'q' => $search ?: null])) }}" @class(['active' => $category?->id === $item->id])>{{ $item->name }} <small>{{ $item->products_count }}</small></a></li>
        @endforeach
      </ul>
      <a class="filter-cta" href="{{ route('spare-parts') }}"><svg class="icon"><use href="#icon-laptop"/></svg><span>Need a laptop spare part?<b>Search by brand, model &amp; year →</b></span></a>
    </aside>

    <div>
      <form class="toolbar" method="get" action="{{ route('shop') }}">
        @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
        @if ($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
        <span class="result-count">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</span>
        <label class="check"><input type="checkbox" name="in_stock" value="1" @checked($inStock) data-autosubmit> In stock only</label>
        <label class="sort">Sort
          <select name="sort" data-autosubmit>@foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach</select>
        </label>
        <noscript><button class="button button-outline" type="submit">Apply</button></noscript>
      </form>

      @if ($products->isEmpty())
        <div class="empty"><svg class="icon"><use href="#icon-search"/></svg><h3>No products found</h3><p>Try another search, or <a class="text-link" href="{{ whatsapp_url('Hi SpeedTech, I am looking for: '.$search) }}" target="_blank" rel="noopener">ask us on WhatsApp</a> — we can often source it.</p></div>
      @else
        <div class="products">@each('partials.product-card', $products, 'product')</div>
        {{ $products->links() }}
      @endif
    </div>
  </section>
@endsection
