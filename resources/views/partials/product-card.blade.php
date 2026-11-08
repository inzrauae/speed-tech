{{-- $product --}}
<article class="product">
  <a href="{{ $product->url() }}" aria-label="View {{ $product->name }}" @class(['product-image', 'is-contain' => ! $product->image || $product->isSparePart() || str_contains($product->image, 'ram') || str_contains($product->image, 'processor')])>
    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" width="480" height="360">
    @if ($product->tag)<span class="product-label">{{ $product->tag }}</span>@endif
  </a>
  <div class="product-body">
    <small>{{ $product->groupLabel() }}</small>
    <h3><a class="detail-btn" href="{{ $product->url() }}">{{ $product->name }}</a></h3>
    @if ($product->oem_part_number)<p class="product-pn">P/N {{ $product->oem_part_number }}</p>@endif
    <p>{{ $product->summary }}</p>
    <div class="product-foot">
      <div class="price-row">
        <b class="product-price">{{ $product->priceLabel() }}</b>
        @if ($product->price !== null)<span class="stock stock-{{ $product->stockState() }}">{{ $product->stockLabel() }}</span>@endif
      </div>
      @if ($product->isPurchasable())
        <form action="{{ route('cart.add') }}" method="post" class="js-add-to-cart">
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->id }}">
          <button class="add product-add" type="submit"><svg class="icon"><use href="#icon-cart"/></svg><span>Add to cart</span></button>
        </form>
      @else
        <button class="add product-add is-enquiry" type="button" data-enquire="{{ $product->id }}" data-enquire-name="{{ $product->name }}">
          <svg class="icon"><use href="#icon-whatsapp"/></svg><span>{{ $product->price === null ? 'Ask for price' : 'Check availability' }}</span>
        </button>
      @endif
    </div>
  </div>
</article>
