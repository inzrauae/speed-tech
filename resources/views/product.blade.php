@extends('layouts.app', ['title' => $product->meta_title ? Str::before($product->meta_title, ' | ') : $product->name, 'description' => $product->seoDescription()])

@push('head')
  @php($pageTitle = ($product->meta_title ? Str::before($product->meta_title, ' | ') : $product->name).' | '.config('speedtech.name'))
  <link rel="canonical" href="{{ $product->url() }}">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta property="og:type" content="product">
  <meta property="og:site_name" content="{{ config('speedtech.name') }}">
  <meta property="og:locale" content="en_LK">
  <meta property="og:title" content="{{ $pageTitle }}">
  <meta property="og:description" content="{{ $product->seoDescription() }}">
  <meta property="og:url" content="{{ $product->url() }}">
  <meta property="og:image" content="{{ $product->imageUrl() }}">
  <meta property="og:image:alt" content="{{ $product->imageAlt() }}">
  @if ($product->price !== null)
    <meta property="product:price:amount" content="{{ number_format((float) $product->price, 2, '.', '') }}">
    <meta property="product:price:currency" content="LKR">
  @endif
  <meta property="product:availability" content="{{ $product->stock_qty > 0 ? 'in stock' : 'available for order' }}">
  <meta property="product:condition" content="new">
  @if ($product->brand)<meta property="product:brand" content="{{ $product->brand }}">@endif
  @if ($product->sku)<meta property="product:retailer_item_id" content="{{ $product->sku }}">@endif
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $pageTitle }}">
  <meta name="twitter:description" content="{{ $product->seoDescription() }}">
  <meta name="twitter:image" content="{{ $product->imageUrl() }}">
  <meta name="twitter:image:alt" content="{{ $product->imageAlt() }}">
  @foreach ($product->structuredData($compatibility) as $graph)
    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @endforeach
@endpush

@section('content')
  <div class="wrap page-head">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="{{ route('home') }}">Home</a><span>/</span>
      @if ($product->isSparePart())
        <a href="{{ route('spare-parts') }}">Laptop spare parts</a><span>/</span>
        @if ($product->partCategory)<a href="{{ route('spare-parts', ['part' => $product->part_category_id]) }}">{{ $product->partCategory->name }}</a><span>/</span>@endif
      @else
        <a href="{{ route('shop') }}">Accessories</a><span>/</span>
        @if ($product->category)<a href="{{ route('shop', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a><span>/</span>@endif
      @endif
      <b>{{ $product->name }}</b>
    </nav>
  </div>

  <section class="wrap product-page">
    <div class="product-gallery"><img src="{{ $product->imageUrl() }}" alt="{{ $product->imageAlt() }}" width="640" height="480"></div>

    <div class="product-summary">
      <div class="eyebrow brand-text">{{ strtoupper($product->groupLabel()) }}</div>
      <h1 class="page-title">{{ $product->name }}</h1>
      @if ($product->summary)<p class="lead">{{ $product->summary }}</p>@endif

      <div class="buy-box">
        <div class="price-row">
          <b class="price-big">{{ $product->priceLabel() }}</b>
          @if ($product->compare_at_price && $product->price && $product->compare_at_price > $product->price)<s class="was">{{ money($product->compare_at_price) }}</s>@endif
          @if ($product->price !== null)<span class="stock stock-{{ $product->stockState() }}">{{ $product->stockLabel() }}</span>@endif
        </div>
        @if ($product->isPurchasable())
          <form action="{{ route('cart.add') }}" method="post" class="js-add-to-cart buy-form">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <label class="qty">Qty <input type="number" name="quantity" value="1" min="1" max="{{ $product->maxOrderQuantity() }}"></label>
            <button class="button red" type="submit"><svg class="icon"><use href="#icon-cart"/></svg>Add to cart</button>
          </form>
        @else
          <button class="button red" type="button" data-enquire="{{ $product->id }}" data-enquire-name="{{ $product->name }}">{{ $product->price === null ? 'Ask for price' : 'Check availability' }}<svg class="icon"><use href="#icon-arrow"/></svg></button>
        @endif
        <a class="button button-outline" target="_blank" rel="noopener" href="{{ whatsapp_url("Hi SpeedTech, I'm interested in {$product->name}".($product->oem_part_number ? " (P/N {$product->oem_part_number})" : '').'. '.$product->url()) }}"><svg class="icon"><use href="#icon-whatsapp"/></svg>Ask on WhatsApp</a>
        <ul class="assurances">
          <li><svg class="icon"><use href="#icon-shield"/></svg>{{ $product->warranty ? $product->warranty.' warranty' : 'Warranty confirmed on enquiry' }}</li>
          <li><svg class="icon"><use href="#icon-truck"/></svg>Island-wide delivery or collect in Kaduwela</li>
          @if ($product->isSparePart())<li><svg class="icon"><use href="#icon-wrench"/></svg>Professional fitting available in store</li>@endif
        </ul>
      </div>

      <table class="spec-table">
        <caption>Specifications</caption>
        <tbody>
          @if ($product->oem_part_number)<tr><th>OEM part number</th><td>{{ $product->oem_part_number }}</td></tr>@endif
          @if ($product->sku)<tr><th>Product code</th><td>{{ $product->sku }}</td></tr>@endif
          @if ($product->specification)<tr><th>Specification</th><td>{{ $product->specification }}</td></tr>@endif
          @if ($product->connector_type)<tr><th>Connector</th><td>{{ $product->connector_type }}</td></tr>@endif
          @if ($product->size)<tr><th>Size</th><td>{{ $product->size }}</td></tr>@endif
          <tr><th>Item category</th><td>{{ $product->groupLabel() }}</td></tr>
          <tr><th>Warranty</th><td>{{ $product->warranty ?: 'Ask us' }}</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="wrap product-details">
    @if ($product->description)
      <div class="detail-block rich-text"><h2>Details</h2>{{ $product->descriptionHtml() }}</div>
    @endif
    @if ($faqs = $product->faqList())
      <div class="detail-block faq-block">
        <h2>Frequently asked questions</h2>
        @foreach ($faqs as $faq)
          <details @if ($loop->first) open @endif><summary>{{ $faq['q'] }}</summary><p>{{ $faq['a'] }}</p></details>
        @endforeach
      </div>
    @endif
    @if ($compatibility->isNotEmpty())
      <div class="detail-block">
        <h2>Compatible laptops</h2>
        <ul class="compat-list">
          @foreach ($compatibility as $model => $labels)<li><b>{{ $model }}</b><span>{{ implode(', ', $labels) }}</span></li>@endforeach
        </ul>
        <p class="detail-note">Parts can vary within the same model and year. Check the part number on your existing part or send us your laptop's service tag — we'll confirm the fit.</p>
      </div>
    @endif
  </section>

  @if ($related->isNotEmpty())
    <section class="wrap related" aria-labelledby="related-title">
      <h2 id="related-title">You may also need</h2>
      <div class="products">@each('partials.product-card', $related, 'product')</div>
    </section>
  @endif
@endsection
