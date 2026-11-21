@extends('admin.layout', [
  'title' => 'AI product writer',
  'breadcrumb' => '<a href="'.route('admin.products.index').'">Products</a> /',
])

@section('content')
  @unless ($configured)
    <div class="alert err" role="alert">The AI writer is not set up yet. Add <b>GEMINI_API_KEY</b> to the server's <code>.env</code> file.</div>
  @endunless

  <form method="post" enctype="multipart/form-data" action="{{ route('admin.ai.store') }}" id="aiForm">
    @csrf
    <div class="layout-form">
      <div>
        <section class="card">
          <h2>1. Product</h2>
          <div class="form-grid" style="grid-template-columns:1fr">
            <label class="field">Product title <small>— as you would type it, e.g. “Dell Inspiron 15 3520 keyboard backlit US” or “Logitech M331 silent wireless mouse”</small>
              <input name="title" value="{{ old('title') }}" required maxlength="200" autofocus placeholder="Product title">
            </label>
            <div class="field">Type
              <div class="segmented" role="radiogroup" style="margin-top:6px">
                @foreach (['' => 'Let AI decide'] + \App\Models\Product::TYPES as $key => $label)
                  <label><input type="radio" name="type" value="{{ $key }}" @checked(old('type', '') === $key)>{{ $label }}</label>
                @endforeach
              </div>
            </div>
            <label class="field">Extra facts <small>(optional) — part number, specs, compatible models, condition, warranty. The AI uses these and avoids guessing.</small>
              <textarea name="notes" rows="3" maxlength="1500">{{ old('notes') }}</textarea>
            </label>
          </div>
        </section>

        <section class="card">
          <h2>2. Image</h2>
          <div class="form-grid" style="grid-template-columns:1fr">
            <div class="field">Where should the image come from?
              <div style="display:grid;gap:8px;margin-top:8px">
                <label class="check"><input type="radio" name="image_mode" value="web" @checked(old('image_mode', 'web') === 'web')> Find the manufacturer's original photo online <small class="help" style="margin:0">(real photo, so ports and pins are exact — put in the SpeedTech layout with the logo)</small></label>
                <label class="check"><input type="radio" name="image_mode" value="photo" @checked(old('image_mode') === 'photo')> Use my photo, in the SpeedTech layout with the logo</label>
                <label class="check"><input type="radio" name="image_mode" value="none" @checked(old('image_mode') === 'none')> No image for now</label>
              </div>
            </div>

            <label class="field" id="urlField">Manufacturer page or photo link <small>(optional) — paste it if you already know the right page; otherwise it is searched for you</small>
              <input type="url" name="image_url" value="{{ old('image_url') }}" maxlength="2000" placeholder="https://www.manufacturer.com/product-page">
            </label>

            <label class="field">Photo of the product <small>(optional) — the AI reads it to write the text (part numbers, connectors). Used as the image only with “Use my photo”, or if no manufacturer photo is found.</small>
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" id="photoInput">
            </label>
            <img class="image-preview" id="photoPreview" alt="" hidden>
          </div>
        </section>
      </div>

      <div>
        <section class="card">
          <h2>How it works</h2>
          <ol class="help" style="padding-left:18px;margin:0">
            <li>The AI writes the name, summary, SEO description, meta title and meta description.</li>
            <li>It picks the right category, part type, brand and compatible laptop models, and <b>creates any that are missing</b>.</li>
            <li>It finds the manufacturer's original product photo online and puts it in the SpeedTech layout with the logo. No image is drawn by AI, so connectors and pins are always the real ones.</li>
            <li>Everything is saved as a <b>hidden draft</b>. You review it, add price and stock, then tick “Visible in the store” to publish.</li>
          </ol>
        </section>

        <div class="form-actions">
          <button class="btn primary" type="submit" id="aiSubmit" @disabled(! $configured)><svg class="icon"><use href="#icon-bolt"/></svg>Write product draft</button>
        </div>
        <p class="help" id="aiWait" hidden style="margin-top:10px">Writing and finding the photo… this can take up to a minute. Please keep this page open.</p>

        @if ($drafts->isNotEmpty())
          <section class="card" style="margin-top:18px">
            <h2>Drafts to review <a class="link" href="{{ route('admin.products.index', ['status' => 'ai']) }}">All</a></h2>
            @foreach ($drafts as $draft)
              <div class="mini-move">
                <img class="thumb" src="{{ $draft->imageUrl() }}" alt="">
                <span><a class="link" href="{{ route('admin.products.edit', $draft) }}">{{ $draft->name }}</a><small>{{ $draft->groupLabel() }} · {{ $draft->created_at->diffForHumans() }}</small></span>
              </div>
            @endforeach
          </section>
        @endif
      </div>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  (() => {
    const form = document.getElementById('aiForm');
    const url = document.getElementById('urlField');
    const sync = () => { url.hidden = form.querySelector('input[name="image_mode"]:checked')?.value !== 'web'; };
    form.addEventListener('change', e => { if (e.target.name === 'image_mode') sync(); });
    sync();

    document.getElementById('photoInput').addEventListener('change', e => {
      const file = e.target.files[0], img = document.getElementById('photoPreview');
      img.hidden = !file;
      if (file) img.src = URL.createObjectURL(file);
    });

    form.addEventListener('submit', () => {
      const btn = document.getElementById('aiSubmit');
      btn.disabled = true; btn.lastChild.textContent = 'Working…';
      document.getElementById('aiWait').hidden = false;
    });
  })();
</script>
@endpush
