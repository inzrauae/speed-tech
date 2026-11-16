@extends('admin.layout', [
  'title' => 'Facebook ad post',
  'breadcrumb' => '<a href="'.route('admin.products.index').'">Products</a> / <a href="'.route('admin.products.edit', $product).'">'.e(\Illuminate\Support\Str::limit($product->name, 40)).'</a> /',
])

@php
  // Sinhala display fonts from Google Fonts, with the heaviest weight each one has.
  $googleFonts = [
      'Noto Sans Sinhala' => ['label' => 'Noto Sans Black (strong)', 'weight' => 900],
      'Gemunu Libre' => ['label' => 'Gemunu (tall, bold)', 'weight' => 800],
      'Stick No Bills' => ['label' => 'Stick No Bills (poster)', 'weight' => 800],
      'Noto Serif Sinhala' => ['label' => 'Noto Serif Black (classic)', 'weight' => 900],
      'Abhaya Libre' => ['label' => 'Abhaya (elegant)', 'weight' => 800],
      'Yaldevi' => ['label' => 'Yaldevi (modern)', 'weight' => 700],
  ];
@endphp

@section('content')
  {{-- Sinhala needs real Sinhala fonts with proper shaping; the canvas uses these. --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Abhaya+Libre:wght@500;800&family=Gemunu+Libre:wght@500;800&family=Noto+Sans+Sinhala:wght@500;700;900&family=Noto+Serif+Sinhala:wght@500;900&family=Montserrat:ital,wght@0,700;0,800;0,900;1,900&family=Poppins:wght@500;700;800&family=Stick+No+Bills:wght@500;800&family=Yaldevi:wght@500;700&display=swap">
  <style>
    .fbad{display:grid;grid-template-columns:370px minmax(0,1fr);gap:18px;align-items:start}
    .fbad-side{position:sticky;top:12px;max-height:calc(100vh - 24px);overflow:auto}
    .fbad .field{margin-top:12px}
    .fbad .field:first-child{margin-top:0}
    .fbad [hidden]{display:none!important}
    .fbad .fbad-row .field{margin-top:12px}
    .fbad-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .fbad-colors{display:flex;flex-wrap:wrap;gap:12px;margin-top:12px}
    .fbad-colors label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600}
    .fbad-colors input{width:36px;height:30px;border:1px solid #d9dae0;border-radius:6px;padding:2px;background:#fff}
    .fbad-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
    .fbad-status{font-size:12px;margin:10px 0 0;color:var(--muted)}
    .fbad-status.err{color:var(--red-dark)}
    .fbad-styles{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:6px}
    .fbad-styles label{display:flex;align-items:center;justify-content:center;height:40px;border-radius:8px;cursor:pointer;font:800 14px/1 "Poppins",sans-serif;border:2px solid transparent;background:#13151c;color:#fff}
    .fbad-styles input{position:absolute;opacity:0;pointer-events:none}
    .fbad-styles label:has(input:checked){border-color:var(--red);box-shadow:0 0 0 2px #fff inset}
    .fbad-styles label:has(input:focus-visible){outline:2px solid var(--red);outline-offset:2px}
    .st-pop{color:#fff!important;text-shadow:0 2px 0 #000}.st-pop b{color:#ffd400;-webkit-text-stroke:.6px #000}
    .st-fire{background:linear-gradient(#fff4a8,#ffb300 50%,#ff4d00)!important;-webkit-background-clip:text!important;color:transparent!important;background-color:#13151c}
    .st-fire-wrap{background:#13151c!important}
    .st-neon{text-shadow:0 0 4px #22e1ff,0 0 10px #22e1ff}
    .st-sticker span{background:#fff;color:#13151c;padding:3px 6px;border-radius:4px;transform:rotate(-3deg)}.st-sticker b{background:#ffd400;color:#13151c;padding:3px 6px;border-radius:4px;margin-left:3px;transform:rotate(2deg)}
    .st-3d{text-shadow:1px 1px 0 #8a0a10,2px 2px 0 #8a0a10,3px 3px 0 #8a0a10}
    .st-clean{font-weight:700!important}
    .st-promo b{font:900 15px "Montserrat",sans-serif;color:#ffd400;-webkit-text-stroke:1px #1a1406;text-shadow:0 0 8px rgba(255,212,0,.8)}
    .fbad-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;align-items:start}
    .fbad-card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px}
    .fbad-card canvas{display:block;width:100%;height:auto;border-radius:6px;background:var(--surface)}
    .fbad-card header{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}
    .fbad-card h3{margin:0;font-size:14px}
    .fbad-card small{display:block;color:var(--muted);font-size:11px;font-weight:400}
    .fbad-wait{position:relative}
    .fbad-wait::after{content:"";position:absolute;inset:0;background:rgba(255,255,255,.55);border-radius:10px}
    .fbad textarea.caption{min-height:150px;font-family:"Noto Sans Sinhala",system-ui,sans-serif}
    .fbad input.si,.fbad textarea.si{font-family:"Noto Sans Sinhala",system-ui,sans-serif}
    .fbad-fonts{margin-top:10px;font-size:12px}
    .fbad-fonts summary{cursor:pointer;font-weight:600}
    .fbad-fonts li{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:4px 0}
    .fbad-fonts ul{list-style:none;padding:0;margin:8px 0}
    @media(max-width:1100px){.fbad{grid-template-columns:1fr}.fbad-side{position:static;max-height:none}}
    @media(max-width:700px){.fbad-grid{grid-template-columns:1fr}}
  </style>

  @unless ($configured)
    <div class="alert err" role="alert">The AI is not set up yet. Add <b>GEMINI_API_KEY</b> to the server's <code>.env</code> file. You can still type the text yourself and download the posts.</div>
  @endunless

  <div class="fbad" id="fbad">
    <div class="fbad-side">
      <form class="card" id="ideaForm">
        @csrf
        <h2>1. Ad idea</h2>
        <label class="field">What is this ad about? <small>— offer, message, mood, who it is for</small>
          <textarea name="idea" class="si" rows="4" maxlength="1500" placeholder="e.g. අලුත් stock ආවා. Island-wide delivery, 6 month warranty. Dark premium look for gamers."></textarea>
        </label>
        <div class="field">Language
          <div class="segmented" role="radiogroup" style="margin-top:6px">
            @foreach (\App\Services\Ai\FbAdMaker::LANGUAGES as $key => $label)
              <label><input type="radio" name="language" value="{{ $key }}" @checked($key === 'si')>{{ $label }}</label>
            @endforeach
          </div>
        </div>
        @if ($product->price !== null)
          <label class="check" style="margin-top:12px"><input type="checkbox" name="with_price" value="1" checked> Show the price ({{ $product->priceLabel() }})</label>
        @endif
        <div class="fbad-actions">
          <button class="btn primary" type="submit" data-parts="all" @disabled(! $configured)><svg class="icon"><use href="#icon-bolt"/></svg>Create ad with AI</button>
          <button class="btn sm" type="submit" data-parts="text" @disabled(! $configured)>New text only</button>
          <button class="btn sm" type="submit" data-parts="image" @disabled(! $configured)>New picture only</button>
        </div>
        <p class="fbad-status" id="status" role="status"></p>
      </form>

      <section class="card" id="editForm">
        <h2>2. Style &amp; text</h2>

        <div class="field">Layout
          <div class="segmented" role="radiogroup" style="margin-top:6px">
            @foreach (\App\Services\Ai\FbAdMaker::LAYOUTS as $key => $label)
              <label><input type="radio" name="layout" value="{{ $key }}" @checked($key === 'tech')>{{ $label }}</label>
            @endforeach
          </div>
        </div>

        <div class="field" data-layouts="poster classic">Text style <small>— the AI picks one; click to try others</small>
          <div class="fbad-styles" role="radiogroup">
            <label class="st-promo"><input type="radio" name="style" value="promo" checked><b>PROMO</b></label>
            <label class="st-pop"><input type="radio" name="style" value="pop">Bold&nbsp;<b>pop</b></label>
            <label class="st-fire-wrap"><input type="radio" name="style" value="fire"><span class="st-fire">FIRE</span></label>
            <label class="st-neon"><input type="radio" name="style" value="neon">Neon</label>
            <label class="st-sticker" style="background:#ed171f"><input type="radio" name="style" value="sticker"><span>Sticker</span></label>
            <label class="st-3d"><input type="radio" name="style" value="3d">3D</label>
            <label class="st-clean"><input type="radio" name="style" value="clean">Clean</label>
          </div>
        </div>

        <div class="fbad-colors">
          <label><input type="color" name="bg" value="#0b1033"> Background</label>
          <label><input type="color" name="accent" value="#e3121b"> Brush / badge</label>
          <label><input type="color" name="hl" value="#ffd400"> Highlight</label>
        </div>

        <div class="fbad-row">
          <label class="field">Sinhala font
            <select name="font">
              @foreach ($googleFonts as $family => $f)
                <option value="{{ $family }}">{{ $f['label'] }}</option>
              @endforeach
              @if ($fonts)
                <optgroup label="Your fonts">
                  @foreach ($fonts as $f)<option value="{{ $f['family'] }}">{{ $f['family'] }}</option>@endforeach
                </optgroup>
              @endif
            </select>
          </label>
          <label class="field">Logo
            <select name="corner">
              <option value="tl">Top left corner</option>
              <option value="tc">Top centre</option>
              <option value="tr">Top right corner</option>
              <option value="bl">Bottom left corner</option>
              <option value="br">Bottom right corner</option>
            </select>
          </label>
        </div>

        <details class="fbad-fonts">
          <summary>Add your own Sinhala font</summary>
          <p class="help" style="margin:6px 0">Upload a <b>Unicode</b> Sinhala font (.ttf, .otf, .woff). Old “FM” fonts are not Unicode and will show wrong letters.</p>
          <form method="post" action="{{ route('admin.fb-ad.font') }}" enctype="multipart/form-data" style="display:flex;gap:6px">
            @csrf
            <input type="file" name="font" accept=".ttf,.otf,.woff,.woff2" required style="min-width:0;flex:1;font-size:12px">
            <button class="btn sm" type="submit">Upload</button>
          </form>
          @if ($fonts)
            <ul>
              @foreach ($fonts as $f)
                <li>{{ $f['family'] }}
                  <form method="post" action="{{ route('admin.fb-ad.font.delete', $f['file']) }}" data-confirm="Remove the font {{ $f['family'] }}?">@csrf @method('DELETE')<button class="btn sm danger" type="submit">Remove</button></form>
                </li>
              @endforeach
            </ul>
          @endif
        </details>

        <label class="field" data-layouts="tech">Top hook <small>— in the red paint-brush banner</small><input name="hook" class="si" maxlength="60" value="සුපිරිම Offer එකක්!"></label>
        <label class="field">Headline <small>— *stars* make words yellow</small><input name="headline" class="si" maxlength="90" value="{{ $product->name }} *අඩුම මිලට!*"></label>
        <label class="field">Sticker <small>(optional) — yellow comic badge, e.g. LIMITED STOCK!</small><input name="offer" class="si" maxlength="40" value="100% BRAND NEW"></label>
        <label class="field" data-layouts="poster">Hero word <small>— the huge word in the middle, e.g. නොමිලේ!</small><input name="hero" class="si" maxlength="30" value="දැන්ම!"></label>
        <label class="field" data-layouts="poster classic">Feature boxes <small>— one per line as <code>icon: text</code>, up to 3</small>
          <textarea name="features" class="si" rows="3" placeholder="truck: Island-wide Delivery&#10;shield: මාස 6ක Warranty">{{ $product->warranty ? 'shield: '.$product->warranty.' Warranty' : '' }}&#10;truck: Island-wide Delivery</textarea>
        </label>
        <p class="help" style="margin:4px 0 0" data-layouts="poster classic">Icons: {{ implode(', ', \App\Services\Ai\FbAdMaker::ICONS) }}</p>
        <label class="field" data-layouts="poster classic">Offer line <small>— under the price, *stars* highlight</small><input name="subline" class="si" maxlength="160" value="*Island-wide* Delivery"></label>
        <div class="fbad-row">
          <label class="field">Price tag<input name="price" maxlength="30" value="{{ $product->price !== null ? $product->priceLabel() : '' }}"></label>
          <label class="field">Next to price<input name="price_note" class="si" maxlength="16" value="පමණයි!"></label>
        </div>
        <label class="field" data-layouts="tech">Warranty / freebie badge<input name="freebie" class="si" maxlength="70" value="{{ $product->warranty ? $product->warranty.' Warranty' : 'Genuine Warranty' }} + Free Delivery"></label>
        <div class="fbad-row" data-layouts="tech">
          <label class="field">Card 1 title<input name="card1_title" class="si" maxlength="30" value="Core Specs"></label>
          <label class="field">Card 2 title<input name="card2_title" class="si" maxlength="30" value="ඇයි අපෙන් ගන්නේ?"></label>
        </div>
        <div class="fbad-row" data-layouts="tech">
          <label class="field">Card 1 points<textarea name="card1" class="si" rows="3">{{ collect([$product->brand, $product->specification, $product->warranty ? $product->warranty.' Warranty' : null])->filter()->implode("
") }}</textarea></label>
          <label class="field">Card 2 points<textarea name="card2" class="si" rows="3">Island-wide Cash on Delivery&#10;Genuine Warranty&#10;ඉක්මන් Delivery</textarea></label>
        </div>
        <label class="field">Shop name &amp; contact <small>— in the footer</small><input name="contact" maxlength="80" value="{{ $contact }}"></label>
        <input type="hidden" name="was">
        <label class="field" style="margin-top:16px">Post caption <small>— paste this as the Facebook post text</small>
          <textarea name="caption" class="caption" rows="6"></textarea>
        </label>
        <button class="btn sm" type="button" id="copyCaption" style="margin-top:8px">Copy caption</button>
      </section>
    </div>

    <div>
      <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0">3. Download</h2>
          <p class="help" style="margin:4px 0 0">Every Facebook &amp; Instagram ad size at its exact pixel size, PNG.</p>
        </div>
        <button class="btn primary" type="button" id="downloadAll"><svg class="icon"><use href="#icon-arrow"/></svg>Download all sizes</button>
      </div>
      <div class="fbad-grid" id="previews"></div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(() => {
  const FORMATS = [
    { key: 'square', name: 'Feed square', note: '1080 × 1080 · feed, carousel', w: 1080, h: 1080, layout: 'side' },
    { key: 'portrait', name: 'Feed portrait', note: '1080 × 1350 · 4:5, best in mobile feed', w: 1080, h: 1350, layout: 'stack' },
    { key: 'story', name: 'Stories & Reels', note: '1080 × 1920 · 9:16, text inside the safe zone', w: 1080, h: 1920, layout: 'stack', safeTop: 270, safeBottom: 380 },
    { key: 'landscape', name: 'Link / landscape', note: '1200 × 628 · 1.91:1, link ads & right column', w: 1200, h: 628, layout: 'side' },
  ];
  const SLUG = @json(\Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($product->name, 40, '')));
  const PRODUCT_IMAGE = @json($productImage ? parse_url($productImage, PHP_URL_PATH) : null);
  const ENDPOINT = @json(route('admin.fb-ad.generate', $product));
  // Path-only URLs keep the images same-origin, so the canvas can be read and exported.
  const LOGOS = { dark: @json(parse_url(asset('assets/logo.png'), PHP_URL_PATH)), light: @json(parse_url(asset('assets/logo-light.png'), PHP_URL_PATH)) };
  const WEIGHTS = @json(collect($googleFonts)->map(fn ($f) => $f['weight']));
  const CUSTOM_FONTS = @json($fonts);

  // 24×24 line icons for the feature boxes (names match FbAdMaker::ICONS).
  const ICONS = {
    laptop: 'M4 5h16v10H4z M2 18.5h20 M12 7.5a2.5 2.5 0 1 0 0.01 0',
    chat: 'M4 5h16v11H10l-5 4v-4H4z M8.5 10.5h.01 M12 10.5h.01 M15.5 10.5h.01',
    truck: 'M2 6h12v10H2z M14 9h4l3 3.5V16h-7z M5 18a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0 M15.5 18a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0',
    shield: 'M12 3l8 3v6c0 5-3.5 8-8 9c-4.5-1-8-4-8-9V6z M8.5 12l2.5 2.5l4.5-5',
    bolt: 'M13 2L4 14h7l-1 8l9-12h-7z',
    gift: 'M3 9h18v4H3z M5 13h14v8H5z M12 9v12 M12 9C10 5 6 5 6 7.5S9 9 12 9c3 0 6 0 6-1.5S14 5 12 9',
    tag: 'M3 12V4h8l10 10l-8 8z M7.5 7.5h.01',
    percent: 'M19 5L5 19 M5 7a2 2 0 1 0 4 0a2 2 0 1 0-4 0 M15 17a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
    wrench: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
    chip: 'M6 6h12v12H6z M9.5 9.5h5v5h-5z M9 2v4 M15 2v4 M9 18v4 M15 18v4 M2 9h4 M2 15h4 M18 9h4 M18 15h4',
    drive: 'M3 7h18v10H3z M7 12h.01 M11 12h6',
    battery: 'M3 8h15v8H3z M21 11v2 M10.5 9.5l-2 3h3l-2 3',
    keyboard: 'M2 6h20v12H2z M6 10h.01 M10 10h.01 M14 10h.01 M18 10h.01 M7 14h10',
    mouse: 'M12 3a6 6 0 0 1 6 6v6a6 6 0 0 1-12 0V9a6 6 0 0 1 6-6z M12 7v4',
    headphones: 'M3 18v-6a9 9 0 0 1 18 0v6 M3 14h4v6H3z M17 14h4v6h-4z',
    wifi: 'M2 9a15 15 0 0 1 20 0 M5 12.5a10 10 0 0 1 14 0 M8.5 16a5 5 0 0 1 7 0 M12 19.5h.01',
    clock: 'M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0 M12 7v5l3 2',
    star: 'M12 3l2.8 5.7l6.2.9l-4.5 4.4l1 6.2L12 17.3l-5.5 2.9l1-6.2L3 9.6l6.2-.9z',
    phone: 'M7 2h10v20H7z M11 18h2',
    cart: 'M3 4h2l2.5 11h11l2-8H6.5 M9 20h.01 M17 20h.01',
    check: 'M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0 M8 12l3 3l5-6',
  };
  const iconPaths = Object.fromEntries(Object.entries(ICONS).map(([k, d]) => [k, new Path2D(d)]));

  const ideaForm = document.getElementById('ideaForm');
  const edit = document.getElementById('editForm');
  const status = document.getElementById('status');
  const field = name => edit.querySelector(`[name="${name}"]`);

  // ---------- colour helpers ----------
  const rgb = hex => { const n = parseInt(hex.slice(1), 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; };
  const toHex = c => '#' + c.map(v => Math.round(v).toString(16).padStart(2, '0')).join('');
  const lum = ([r, g, b]) => { const f = v => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }; return .2126 * f(r) + .7152 * f(g) + .0722 * f(b); };
  const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);
  const rgba = (c, a = 1) => `rgba(${c.map(Math.round).join(',')},${a})`;
  const onColour = c => lum(c) > .45 ? [20, 22, 28] : [255, 255, 255];
  const WHITE = [255, 255, 255], BLACK = [0, 0, 0];

  // ---------- images ----------
  const load = src => new Promise((ok, fail) => { const i = new Image(); i.onload = () => ok(i); i.onerror = fail; i.src = src; });
  const logos = {};
  let picture = null, crop = 0;

  /** Average colour round the picture's outer edge, so the ad background blends into it. */
  function edgeColour(img) {
    const c = document.createElement('canvas'); c.width = c.height = 40;
    const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(img, 0, 0, 40, 40);
    const d = x.getImageData(0, 0, 40, 40).data; const sum = [0, 0, 0]; let n = 0;
    for (let y = 0; y < 40; y++) for (let i = 0; i < 40; i++) {
      if (y > 2 && y < 37 && i > 2 && i < 37) continue;
      const p = (y * 40 + i) * 4; sum[0] += d[p]; sum[1] += d[p + 1]; sum[2] += d[p + 2]; n++;
    }
    return sum.map(v => v / n);
  }

  /** cropEdge trims the shop's product photo so the logo already stamped on it is not shown twice. */
  async function setPicture(src, adoptColour, cropEdge = 0) {
    if (!src) { picture = null; return; }
    picture = await load(src + (src.includes('?') ? '&' : '?') + 't=' + Date.now());
    crop = cropEdge;
    if (adoptColour) field('bg').value = toHex(edgeColour(picture));
  }

  // ---------- state ----------
  /** "truck: Island-wide Delivery" lines → [{icon, text}]. */
  const parseFeatures = text => text.split('\n').map(l => l.trim()).filter(Boolean).slice(0, 3).map(l => {
    const m = l.match(/^([a-z]+)\s*:\s*(.+)$/i);
    return m && ICONS[m[1].toLowerCase()] ? { icon: m[1].toLowerCase(), text: m[2].trim() } : { icon: 'check', text: l };
  });

  const state = () => ({
    offer: field('offer').value.trim(), headline: field('headline').value.trim(), hero: field('hero').value.trim(),
    subline: field('subline').value.trim(), features: parseFeatures(field('features').value),
    price: field('price').value.trim(), was: field('was').value.trim(), priceNote: field('price_note').value.trim(),
    contact: field('contact').value.trim(), font: field('font').value, corner: field('corner').value,
    hook: field('hook').value.trim(), freebie: field('freebie').value.trim(),
    cards: [1, 2].map(n => ({
      title: field(`card${n}_title`).value.trim(),
      points: field(`card${n}`).value.split('\n').map(l => l.trim()).filter(Boolean).slice(0, 3),
    })).filter(c => c.title || c.points.length),
    layout: edit.querySelector('[name="layout"]:checked')?.value || 'tech',
    style: edit.querySelector('[name="style"]:checked')?.value || 'promo',
    bg: rgb(field('bg').value), accent: rgb(field('accent').value), hl: rgb(field('hl').value),
  });

  // ---------- fonts & words ----------
  // Every word picks its own font: Sinhala words use the chosen Sinhala font (so vowel signs and
  // conjuncts like ශ්‍රී shape correctly), English words and numbers use heavy Montserrat like
  // professional Sri Lankan promo posters.
  const SI = /[඀-෿]/;
  const heavy = s => WEIGHTS[s.font] || 800;
  const font = (s, weight, size) => `${weight} ${Math.round(size)}px "${s.font}", "Noto Sans Sinhala", sans-serif`;
  const latin = (weight, size, italic = false) => `${italic ? 'italic ' : ''}${weight >= 700 ? 900 : weight} ${Math.round(size * .92)}px "Montserrat", "Poppins", sans-serif`;
  const wordFont = (s, weight, size, text, italic = false) => SI.test(text) ? font(s, weight, size) : latin(weight, size, italic);

  /**
   * "Laptop එක *Super Fast* කරමු" → tokens, with each starred phrase kept as one
   * unit (key = true) so a highlight never breaks across two lines.
   */
  function tokens(text, allKey = false) {
    let on = false; const out = [];
    for (const w of text.split(/\s+/).filter(Boolean)) {
      const start = w.startsWith('*'), end = w.length > 1 && w.endsWith('*') || (w === '*' && on);
      const t = w.replace(/^\*+|\*+$/g, '');
      const key = on || start;
      if (t) {
        const last = out[out.length - 1];
        if (key && on && last?.key) last.t += ' ' + t; else out.push({ t, key });
      }
      on = key && !end;
    }
    return allKey ? out.map(w => ({ ...w, key: true })) : out;
  }

  const space = (ctx, s, size) => { ctx.font = font(s, 700, size); return ctx.measureText(' ').width * .9; };

  /** Width of one token (it may hold several words of a starred phrase). */
  function tokenWidth(ctx, s, tok, weight, size, italic = false) {
    const parts = tok.t.split(' ');
    return parts.reduce((w, p, i) => { ctx.font = wordFont(s, weight, size, p, italic); return w + ctx.measureText(p).width + (i ? space(ctx, s, size) : 0); }, 0);
  }

  /** Consecutive words with the same highlight state, for sticker boxes. */
  const segments = words => words.reduce((out, w) => {
    const last = out[out.length - 1];
    if (last && last.key === w.key) last.t += ' ' + w.t; else out.push({ ...w });
    return out;
  }, []);

  function lineWidth(ctx, s, words, weight, size, sticker = false, italicKey = false) {
    if (sticker) return segments(words).reduce((w, seg, i) => w + tokenWidth(ctx, s, seg, weight, size) + size * .44 + (i ? size * .14 : 0), 0);
    return words.reduce((w, tok, i) => w + tokenWidth(ctx, s, tok, weight, size, italicKey && tok.key) + (i ? space(ctx, s, size) : 0), 0);
  }

  /** Greedy word wrap on whole words, so shaped Sinhala clusters are never split. */
  function wrapWords(ctx, s, words, width, weight, size, sticker = false) {
    const lines = []; let line = [];
    for (const w of words) {
      const test = [...line, w];
      if (line.length && lineWidth(ctx, s, test, weight, size, sticker) > width) { lines.push(line); line = [w]; } else line = test;
    }
    if (line.length) lines.push(line);
    return lines;
  }

  /** Headline looks. Each returns how plain words and highlighted words are painted. */
  function looks(s) {
    const dark = lum(s.bg) < .45, ink = onColour(s.bg);
    const hlLight = rgba(mix(s.hl, WHITE, .45)), hl = rgba(s.hl), hlDeep = rgba(mix(s.hl, s.accent, .5));
    const outline = rgba(mix(s.hl, BLACK, .82));
    switch (s.style) {
      case 'fire': return {
        head: { fill: ['#fff8c9', '#ffd84a', '#ff8a00', '#e8360c'], stroke: '#2a0700', strokeW: .12, glow: 'rgba(255,110,0,.85)', glowBlur: .35 },
        key: { fill: ['#ffffff', hlLight, hl], stroke: '#2a0700', strokeW: .12, glow: rgba(s.hl, .9), glowBlur: .45 },
      };
      case 'neon': return {
        head: { fill: dark ? '#ffffff' : rgba(mix(s.accent, BLACK, .2)), glow: rgba(s.accent), glowBlur: .5, double: true },
        key: { fill: dark ? hlLight : hl, glow: hl, glowBlur: .6, double: true },
      };
      case 'sticker': return {
        head: { fill: '#14161c', box: '#ffffff' },
        key: { fill: rgba(onColour(s.hl)), box: hl },
      };
      case '3d': return {
        head: { fill: dark ? ['#ffffff', '#ffeec2'] : [rgba(s.accent), rgba(mix(s.accent, BLACK, .25))], depth: .1, depthColor: rgba(mix(s.accent, BLACK, dark ? .35 : .6)), shadow: true },
        key: { fill: [hlLight, hl], depth: .1, depthColor: rgba(mix(s.hl, BLACK, .6)), shadow: true },
      };
      case 'clean': return {
        head: { fill: rgba(ink) },
        key: { fill: hl },
      };
      case 'pop': return {
        head: { fill: rgba(ink), stroke: dark ? 'rgba(0,0,0,.55)' : 'rgba(255,255,255,.95)', strokeW: .07, shadow: true },
        key: { fill: [hlLight, hl, hlDeep], stroke: '#120d05', strokeW: .13, shadow: true },
      };
      default: return { // promo: heavy white with a soft dark shadow; keywords saturated with a dark outline and glow
        head: { fill: rgba(ink), shadow: true, shadowDark: true },
        key: { fill: [rgba(mix(s.hl, WHITE, .3)), hl, rgba(mix(s.hl, BLACK, .08))], stroke: outline, strokeW: .085, glow: rgba(s.hl, .8), glowBlur: .38 },
      };
    }
  }

  /** A gradient top-to-bottom across one line of text, or a plain colour. */
  const paint = (ctx, fill, y, size) => {
    if (!Array.isArray(fill)) return fill;
    const g = ctx.createLinearGradient(0, y - size * .55, 0, y + size * .55);
    fill.forEach((c, i) => g.addColorStop(i / (fill.length - 1), c));
    return g;
  };

  /** One word with its effects: 3D depth, glow or drop shadow, outline, then the fill on top. */
  function paintWord(ctx, text, x, y, size, look) {
    ctx.save();
    if (look.depth) {
      const d = Math.max(2, Math.round(size * look.depth));
      ctx.fillStyle = look.depthColor;
      for (let i = d; i >= 1; i--) ctx.fillText(text, x + i * .55, y + i);
    }
    if (look.glow) { ctx.shadowColor = look.glow; ctx.shadowBlur = size * look.glowBlur; }
    else if (look.drop) { ctx.shadowColor = 'rgba(0,0,0,.85)'; ctx.shadowBlur = size * .06; ctx.shadowOffsetX = size * .03; ctx.shadowOffsetY = size * .07; }
    else if (look.shadow) { ctx.shadowColor = look.shadowDark ? 'rgba(0,0,0,.7)' : 'rgba(0,0,0,.45)'; ctx.shadowBlur = size * .12; ctx.shadowOffsetY = size * .05; }
    if (look.stroke) {
      ctx.lineJoin = 'round'; ctx.miterLimit = 2; ctx.lineWidth = size * look.strokeW; ctx.strokeStyle = look.stroke;
      ctx.strokeText(text, x, y);
      ctx.shadowColor = 'transparent';
    }
    ctx.fillStyle = paint(ctx, look.fill, y, size);
    ctx.fillText(text, x, y);
    if (look.double) { ctx.shadowColor = look.glow; ctx.shadowBlur = size * look.glowBlur * .35; ctx.fillText(text, x, y); }
    ctx.restore();
  }

  /**
   * Draw a line of tokens from x (textBaseline middle at y), each word in its own font.
   * lookOf(token) gives the paint look; returns the x after the last word.
   */
  function drawWords(ctx, s, words, x, y, weight, size, lookOf, italicKey = false) {
    ctx.textBaseline = 'middle'; ctx.textAlign = 'left';
    words.forEach((tok, i) => {
      if (i) x += space(ctx, s, size);
      tok.t.split(' ').forEach((p, j) => {
        if (j) x += space(ctx, s, size);
        const f = wordFont(s, weight, size, p, italicKey && tok.key);
        ctx.font = f; paintWord(ctx, p, x, y, size, lookOf(tok));
        ctx.font = f; x += ctx.measureText(p).width;
      });
    });
    return x;
  }

  function drawHeadLine(ctx, s, L, words, x, y, size, index) {
    const sticker = s.style === 'sticker', weight = heavy(s);
    ctx.save();
    ctx.translate(x, y);
    if (sticker) {
      ctx.rotate(index % 2 ? .018 : -.03);
      let cx = 0; const padX = size * .22, h = size * 1.24;
      segments(words).forEach((seg, i) => {
        const look = seg.key ? L.key : L.head, w = tokenWidth(ctx, s, seg, weight, size);
        if (i) cx += size * .14;
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = size * .2; ctx.shadowOffsetY = size * .07;
        ctx.fillStyle = look.box; ctx.beginPath(); ctx.roundRect(cx, -h / 2, w + padX * 2, h, size * .14); ctx.fill(); ctx.restore();
        drawWords(ctx, s, [seg], cx + padX, 0, weight, size, () => ({ fill: look.fill }));
        cx += w + padX * 2;
      });
    } else {
      drawWords(ctx, s, words, 0, 0, weight, size, w => w.key ? L.key : L.head);
    }
    ctx.restore();
  }

  function drawIcon(ctx, name, x, y, size, colour, weight = 2) {
    ctx.save(); ctx.translate(x, y); ctx.scale(size / 24, size / 24);
    ctx.strokeStyle = colour; ctx.lineWidth = weight; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.stroke(iconPaths[name] || iconPaths.check);
    ctx.restore();
  }

  function roundRect(ctx, x, y, w, h, r) { ctx.beginPath(); ctx.roundRect(x, y, w, h, r); }

  /** Picture covering its box, edges faded into the background (feather = share of each side). */
  function drawPicture(ctx, img, r, feather = .16, maxZoom = Infinity) {
    if (!img || r.w <= 0 || r.h <= 0) return;
    const c = document.createElement('canvas'); c.width = Math.round(r.w); c.height = Math.round(r.h);
    const x = c.getContext('2d');
    const sx = img.width * crop, sy = img.height * crop, sw = img.width - sx * 2, sh = img.height - sy * 2;
    // Cover the box, but never zoom past maxZoom × "fit inside" so the product is not cut off.
    const scale = Math.min(Math.max(r.w / sw, r.h / sh), Math.min(r.w / sw, r.h / sh) * maxZoom);
    const dw = sw * scale, dh = sh * scale, dx = (r.w - dw) / 2, dy = (r.h - dh) / 2;
    x.drawImage(img, sx, sy, sw, sh, dx, dy, dw, dh);
    x.globalCompositeOperation = 'destination-in';
    // Fade the edges of whatever is visible: the image itself when it is narrower/shorter than the box.
    const L = Math.max(0, dx), Rr = Math.min(r.w, dx + dw), T = Math.max(0, dy), B = Math.min(r.h, dy + dh);
    const fx = dx > 0 ? .14 : Math.min(.16, 90 / r.w), fy = dy > 0 ? Math.max(feather, .14) : feather;
    for (const [x0, y0, x1, y1, f0] of [[L, 0, Rr, 0, fx], [0, T, 0, B, fy]]) {
      const g = x.createLinearGradient(x0, y0, x1, y1);
      g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(f0, '#000'); g.addColorStop(1 - f0, '#000'); g.addColorStop(1, 'rgba(0,0,0,0)');
      x.fillStyle = g; x.fillRect(0, 0, r.w, r.h);
    }
    ctx.drawImage(c, r.x, r.y);
  }

  /** Small logo; dark or light version picked from the colour behind it, with a calm plate if the spot is busy. */
  function drawLogo(ctx, s, x, y, w) {
    const logo = logos.dark; if (!logo) return;
    const h = w * logo.height / logo.width;
    const d = ctx.getImageData(Math.round(x), Math.round(y), Math.round(w), Math.round(h)).data;
    let sum = 0, sq = 0, n = 0;
    for (let p = 0; p < d.length; p += 16) { const l = lum([d[p], d[p + 1], d[p + 2]]); sum += l; sq += l * l; n++; }
    const mean = sum / n, busy = Math.sqrt(Math.max(0, sq / n - mean * mean)) > .12;
    if (busy) {
      const p = h * .35; ctx.fillStyle = rgba(s.bg, .88); roundRect(ctx, x - p, y - p, w + p * 2, h + p * 2, p); ctx.fill();
    }
    const light = (busy ? lum(s.bg) : mean) < .4;
    ctx.drawImage(light ? logos.light : logos.dark, x, y, w, h);
  }
  const logoRatio = () => logos.dark ? logos.dark.height / logos.dark.width : .2;

  /** Glossy pill with a gradient and glow. */
  function glowPill(ctx, x, y, w, h, colour, glow) {
    ctx.save();
    ctx.shadowColor = rgba(glow, .75); ctx.shadowBlur = h * .45;
    const g = ctx.createLinearGradient(0, y, 0, y + h);
    g.addColorStop(0, rgba(mix(colour, WHITE, .35))); g.addColorStop(.5, rgba(colour)); g.addColorStop(1, rgba(mix(colour, BLACK, .12)));
    ctx.fillStyle = g; roundRect(ctx, x, y, w, h, h / 2); ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = 'rgba(255,255,255,.22)'; roundRect(ctx, x + h * .25, y + h * .08, w - h * .5, h * .32, h * .16); ctx.fill();
    ctx.restore();
  }

  // ================= POSTER layout =================

  /** Stack the poster blocks top to bottom; shrink everything until the picture keeps enough room. */
  function posterPlan(ctx, s, F, R, withPicture) {
    const m = Math.min(F.w, F.h), L = looks(s), tall = F.h / F.w, W = heavy(s);
    // The picture is the star: it keeps 42–50% of the height and the text shrinks around it.
    const minPic = withPicture ? R.h * (tall > 1.5 ? .5 : tall > 1.1 ? .46 : .42) : 0;
    let plan;
    for (let k = 1; k >= .4; k -= .03) {
      const u = m / 1080 * k, g = 22 * u, blocks = [];
      if (s.offer) blocks.push({ kind: 'badge', h: 56 * u });
      if (s.headline) {
        let size = 82 * u, lines = wrapWords(ctx, s, tokens(s.headline), R.w * .96, W, size);
        while (lines.length > 2 && size > 16) { size -= 2; lines = wrapWords(ctx, s, tokens(s.headline), R.w * .96, W, size); }
        blocks.push({ kind: 'top', size, lines, h: lines.length * size * 1.3 });
      }
      if (s.features.length) blocks.push({ kind: 'features', h: 150 * u });
      if (s.hero) {
        const w100 = lineWidth(ctx, s, tokens(s.hero, true), W, 100, s.style === 'sticker') || 1;
        const size = Math.min(250 * u, 100 * R.w * .76 / w100);
        blocks.push({ kind: 'hero', size, h: size * 1.3 + (s.style === 'sticker' ? 0 : 30 * u) });
      }
      if (withPicture) blocks.push({ kind: 'picture', h: 0 });
      if (s.price) blocks.push({ kind: 'price', h: 92 * u });
      if (s.subline) blocks.push({ kind: 'offer', h: 82 * u });
      if (s.contact) blocks.push({ kind: 'contact', h: 48 * u });
      const fixed = blocks.reduce((t, b) => t + b.h, 0) + g * (blocks.length - 1);
      plan = { blocks, u, L, g, free: R.h - fixed };
      if (plan.free >= minPic) break;
    }
    // Give the picture the free space, or centre the stack when there is no picture.
    let y = R.y + (withPicture ? 0 : Math.max(0, plan.free / 2));
    for (const b of plan.blocks) {
      if (b.kind === 'picture') b.h = Math.max(0, plan.free);
      b.y = y; y += b.h + plan.g;
    }
    return plan;
  }

  function posterBackground(ctx, s, F) {
    const m = Math.min(F.w, F.h);
    ctx.fillStyle = rgba(s.bg); ctx.fillRect(0, 0, F.w, F.h);
    for (const [x, y, r, a] of [[0, 0, m * .75, .2], [F.w, F.h * .62, m * .85, .16], [F.w * .5, F.h * .55, m * .7, .1]]) {
      const g = ctx.createRadialGradient(x, y, 0, x, y, r);
      g.addColorStop(0, rgba(s.hl, a)); g.addColorStop(1, rgba(s.hl, 0));
      ctx.fillStyle = g; ctx.fillRect(0, 0, F.w, F.h);
    }
  }

  function posterTop(ctx, s, b, cx, L) {
    const W = heavy(s);
    b.lines.forEach((line, i) => {
      const w = lineWidth(ctx, s, line, W, b.size), y = b.y + i * b.size * 1.3 + b.size * .65;
      drawWords(ctx, s, line, cx - w / 2, y, W, b.size, tok => tok.key ? { ...L.key, glowBlur: (L.key.glowBlur || 0) * .6 } : L.head);
    });
  }

  function posterFeatures(ctx, s, b, R, u) {
    const n = s.features.length, two = n === 2, gap = two ? 80 * u : 22 * u, W = heavy(s);
    const bw = Math.min((R.w - gap * (n - 1)) / n, 480 * u), total = bw * n + gap * (n - 1);
    const dark = lum(s.bg) < .45, ink = rgba(onColour(s.bg));
    let x = R.x + (R.w - total) / 2;
    s.features.forEach((f, i) => {
      ctx.save();
      ctx.shadowColor = rgba(s.hl, .85); ctx.shadowBlur = 30 * u;
      ctx.fillStyle = dark ? rgba(mix(s.bg, BLACK, .4), .8) : 'rgba(255,255,255,.85)';
      roundRect(ctx, x, b.y, bw, b.h, 26 * u); ctx.fill();
      ctx.lineWidth = 4 * u; ctx.strokeStyle = rgba(s.hl); ctx.stroke();
      ctx.shadowBlur = 0; ctx.lineWidth = 1.5 * u; ctx.strokeStyle = rgba(mix(s.hl, WHITE, .5), .7); ctx.stroke();
      ctx.restore();

      const isz = Math.min(b.h * .5, bw * .26), ix = x + b.h * .16, dx = ix + isz + b.h * .14;
      drawIcon(ctx, f.icon, ix, b.y + (b.h - isz) / 2, isz, ink, 1.9);
      ctx.strokeStyle = rgba(onColour(s.bg), .55); ctx.lineWidth = 3 * u;
      ctx.beginPath(); ctx.moveTo(dx, b.y + b.h * .2); ctx.lineTo(dx, b.y + b.h * .8); ctx.stroke();

      const tx = dx + b.h * .14, tw = x + bw - tx - b.h * .12, words = tokens(f.text);
      let size = 60 * u, lines;
      for (; size > 12; size -= 1) {
        lines = wrapWords(ctx, s, words, tw, W, size);
        if (lines.length <= 3 && lines.length * size * 1.18 <= b.h * .84 && lines.every(l => lineWidth(ctx, s, l, W, size) <= tw)) break;
      }
      const top = b.y + (b.h - lines.length * size * 1.18) / 2;
      lines.forEach((l, j) => drawWords(ctx, s, l, tx, top + j * size * 1.18 + size * .6, W, size, tok => ({ fill: tok.key ? rgba(s.hl) : ink })));

      if (two && i === 0) { // the "+" between two boxes
        const px = x + bw + gap / 2, py = b.y + b.h / 2, a = gap * .3;
        ctx.save(); ctx.strokeStyle = rgba(s.hl); ctx.lineWidth = 15 * u; ctx.lineCap = 'round';
        ctx.shadowColor = rgba(s.hl, .7); ctx.shadowBlur = 16 * u;
        ctx.beginPath(); ctx.moveTo(px - a, py); ctx.lineTo(px + a, py); ctx.moveTo(px, py - a); ctx.lineTo(px, py + a); ctx.stroke(); ctx.restore();
      }
      x += bw + gap;
    });
  }

  function posterHero(ctx, s, b, cx, u, L) {
    const sticker = s.style === 'sticker', words = tokens(s.hero, true), W = heavy(s);
    const w = lineWidth(ctx, s, words, W, b.size, sticker), y = b.y + b.size * .68, x = cx - w / 2;
    drawHeadLine(ctx, s, { head: L.key, key: L.key }, words, x, y, b.size, 0);

    // Burst marks on both sides.
    ctx.save(); ctx.strokeStyle = rgba(s.hl); ctx.lineCap = 'round'; ctx.lineWidth = Math.max(5, b.size * .06);
    ctx.shadowColor = rgba(s.hl, .6); ctx.shadowBlur = 12 * u;
    for (const side of [-1, 1]) {
      const ax = cx + side * (w / 2 + b.size * .14), ay = y - b.size * .12;
      ctx.beginPath();
      ctx.moveTo(ax, ay - b.size * .16); ctx.lineTo(ax + side * b.size * .17, ay - b.size * .44);
      ctx.moveTo(ax + side * b.size * .04, ay + b.size * .03); ctx.lineTo(ax + side * b.size * .32, ay - b.size * .05);
      ctx.stroke();
    }
    ctx.restore();

    if (!sticker) { // swoosh under the word
      const ys = b.y + b.h - 6 * u, xl = cx - w * .5, xr = cx + w * .56;
      ctx.save(); ctx.fillStyle = rgba(s.hl); ctx.shadowColor = rgba(s.hl, .6); ctx.shadowBlur = 14 * u;
      ctx.beginPath(); ctx.moveTo(xl, ys);
      ctx.quadraticCurveTo(cx, ys - 54 * u, xr, ys - 34 * u);
      ctx.quadraticCurveTo(cx, ys - 18 * u, xl, ys);
      ctx.fill(); ctx.restore();
    }
  }

  function posterPrice(ctx, s, b, cx, R, u) {
    const ink = onColour(s.hl), padX = 40 * u;
    let size = 66 * u, noteSize, wasW, priceW, noteW, total;
    for (; size > 14; size -= 1) {
      ctx.font = latin(800, size * .55); wasW = s.was ? ctx.measureText(s.was).width + 18 * u : 0;
      ctx.font = latin(900, size); priceW = ctx.measureText(s.price).width;
      noteSize = size * .5; ctx.font = wordFont(s, 900, noteSize, s.priceNote); noteW = s.priceNote ? ctx.measureText(s.priceNote).width + noteSize * 1.5 + 16 * u : 0;
      total = padX * 2 + wasW + priceW + noteW;
      if (total <= R.w * .9) break;
    }
    let x = cx - total / 2; const y = b.y + b.h / 2;
    glowPill(ctx, x, b.y, total, b.h, s.hl, s.hl);
    ctx.textBaseline = 'middle'; ctx.textAlign = 'left'; x += padX;
    if (s.was) {
      ctx.font = latin(800, size * .55); ctx.fillStyle = rgba(ink, .7); ctx.fillText(s.was, x, y);
      ctx.strokeStyle = rgba(s.accent); ctx.lineWidth = Math.max(2, size * .07);
      ctx.beginPath(); ctx.moveTo(x - 2, y); ctx.lineTo(x + wasW - 16 * u, y); ctx.stroke();
      x += wasW;
    }
    ctx.font = latin(900, size); ctx.fillStyle = rgba(ink); ctx.fillText(s.price, x, y + size * .03);
    x += priceW;
    if (s.priceNote) { // dark "ONLY" pill with white text, like the price tags on promo posters
      ctx.font = wordFont(s, 900, noteSize, s.priceNote);
      const w = ctx.measureText(s.priceNote).width + noteSize * 1.3, h = noteSize * 1.75;
      x += 16 * u;
      ctx.fillStyle = rgba(mix(ink, BLACK, .2)); roundRect(ctx, x, y - h / 2, w, h, h / 2); ctx.fill();
      ctx.fillStyle = rgba(onColour(ink)); ctx.fillText(s.priceNote, x + noteSize * .65, y);
    }
  }

  function posterOffer(ctx, s, b, cx, R, u) {
    const words = tokens(s.subline), dark = lum(s.bg) < .45, W = heavy(s), ink = rgba(onColour(s.bg));
    let size = 56 * u;
    while (lineWidth(ctx, s, words, W, size, false, true) > R.w * .66 && size > 12) size -= 1;
    const tw = lineWidth(ctx, s, words, W, size, false, true), pw = tw + 90 * u, px = cx - pw / 2;
    ctx.save();
    ctx.shadowColor = rgba(s.hl, .75); ctx.shadowBlur = 20 * u;
    ctx.fillStyle = dark ? 'rgba(0,0,0,.45)' : 'rgba(255,255,255,.75)'; roundRect(ctx, px, b.y, pw, b.h, b.h / 2); ctx.fill();
    ctx.strokeStyle = rgba(s.hl); ctx.lineWidth = 4 * u; ctx.stroke();
    ctx.restore();
    // Rules either side of the pill.
    const y = b.y + b.h / 2, left = R.x + R.w * .02, right = R.x + R.w * .98;
    ctx.save(); ctx.strokeStyle = rgba(s.hl); ctx.lineWidth = 4.5 * u; ctx.lineCap = 'round';
    if (px - 26 * u - left > 30 * u) { ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(px - 26 * u, y); ctx.moveTo(px + pw + 26 * u, y); ctx.lineTo(right, y); ctx.stroke(); }
    ctx.restore();
    // Highlighted words: heavy italic in the highlight colour ("FREE Customization").
    drawWords(ctx, s, words, cx - tw / 2, y + size * .02, W, size, tok => tok.key ? { fill: rgba(s.hl), shadow: true, shadowDark: true } : { fill: ink, shadow: true, shadowDark: true }, true);
  }

  /** Small contact line at the bottom: a phone icon in a highlight circle, then the details. */
  function posterContact(ctx, s, b, cx, R, u) {
    const words = tokens(s.contact);
    let size = 34 * u;
    while (lineWidth(ctx, s, words, 800, size) > R.w * .82 && size > 10) size -= 1;
    const tw = lineWidth(ctx, s, words, 800, size), r = size * .72, total = r * 2 + size * .5 + tw;
    const x = cx - total / 2, y = b.y + b.h / 2;
    ctx.save(); ctx.fillStyle = rgba(s.hl); ctx.shadowColor = rgba(s.hl, .6); ctx.shadowBlur = 10 * u;
    ctx.beginPath(); ctx.arc(x + r, y, r, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    drawIcon(ctx, 'phone', x + r - r * .6, y - r * .6, r * 1.2, rgba(onColour(s.hl)), 2.4);
    drawWords(ctx, s, words, x + r * 2 + size * .5, y + size * .03, 800, size, tok => ({ fill: tok.key ? rgba(s.hl) : rgba(onColour(s.bg), .92), shadow: true, shadowDark: true }));
  }

  function posterBadge(ctx, s, b, cx, u) {
    const size = b.h * .52, words = tokens(s.offer), W = 800, tw = lineWidth(ctx, s, words, W, size), w = tw + size * 1.8;
    ctx.save(); ctx.translate(cx, b.y + b.h / 2); if (s.style !== 'clean') ctx.rotate(-.035);
    const g = ctx.createLinearGradient(0, -b.h / 2, 0, b.h / 2); g.addColorStop(0, rgba(mix(s.accent, WHITE, .2))); g.addColorStop(1, rgba(mix(s.accent, BLACK, .15)));
    ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 12 * u; ctx.fillStyle = g;
    roundRect(ctx, -w / 2, -b.h / 2, w, b.h, b.h / 2); ctx.fill();
    ctx.shadowColor = 'transparent';
    drawWords(ctx, s, words, -tw / 2, size * .02, W, size, () => ({ fill: rgba(onColour(s.accent)) }));
    ctx.restore();
  }

  function renderPoster(F, ctx, s) {
    const m = Math.min(F.w, F.h), pad = Math.round(m * .055), wide = F.w > F.h * 1.4;
    let top = F.safeTop || pad, bottom = F.h - (F.safeBottom || pad);
    const logoW = Math.round(m * (wide ? .24 : .26)), logoH = logoW * logoRatio();
    const logoTop = s.corner[0] === 't';
    const logoY = logoTop ? top : bottom - logoH;
    if (logoTop) top += logoH + m * .03; else bottom -= logoH + m * .03;

    posterBackground(ctx, s, F);

    const R = wide ? { x: pad, y: top, w: F.w * .5 - pad, h: bottom - top } : { x: pad, y: top, w: F.w - pad * 2, h: bottom - top };
    const plan = posterPlan(ctx, s, F, R, !wide);
    const cx = R.x + R.w / 2, u = plan.u, at = k => plan.blocks.find(b => b.kind === k);

    // Picture first, reaching up behind the hero word and down behind the price.
    if (wide) drawPicture(ctx, picture, { x: F.w * .4, y: 0, w: F.w * .6, h: F.h }, .12);
    else {
      const pic = at('picture'), i = plan.blocks.indexOf(pic), prev = plan.blocks[i - 1], next = plan.blocks[i + 1];
      const y0 = pic.y - (prev ? prev.h * .5 : 0), y1 = pic.y + pic.h + (next ? next.h * .6 : 0);
      drawPicture(ctx, picture, { x: 0, y: y0, w: F.w, h: y1 - y0 }, .22);
    }

    for (const b of plan.blocks) {
      if (b.kind === 'badge') posterBadge(ctx, s, b, cx, u);
      if (b.kind === 'top') posterTop(ctx, s, b, cx, plan.L);
      if (b.kind === 'features') posterFeatures(ctx, s, b, R, u);
      if (b.kind === 'hero') posterHero(ctx, s, b, cx, u, plan.L);
      if (b.kind === 'price') posterPrice(ctx, s, b, cx, R, u);
      if (b.kind === 'offer') posterOffer(ctx, s, b, cx, R, u);
      if (b.kind === 'contact') posterContact(ctx, s, b, cx, R, u);
    }

    const lx = s.corner[1] === 'l' ? pad : s.corner[1] === 'r' ? F.w - pad - logoW : (wide ? cx : F.w / 2) - logoW / 2;
    drawLogo(ctx, s, lx, logoY, logoW);
  }

  // ================= TECH PROMO layout =================
  // Brush strokes, comic sticker, price slash, glass badge, neon spec cards and a gradient footer
  // on a navy → cosmic purple background, like vibrant Sri Lankan computer-shop ads.

  const CYAN = [34, 211, 238], MAGENTA = [217, 70, 239], PURPLE = [109, 40, 217], GREEN = [34, 197, 94], INK = [17, 17, 20];

  /** Seeded random numbers, so brush edges and bokeh stay put while you type. */
  const seeded = seed => () => {
    seed = seed + 0x6D2B79F5 | 0; let t = Math.imul(seed ^ seed >>> 15, 1 | seed);
    t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296;
  };

  /** Ragged paint-brush stroke: wobbly long edges and bristle spikes at both ends. */
  function brushPath(ctx, x, y, w, h, rnd) {
    const steps = Math.max(8, Math.round(w / (h * .4))), inset = h * .12;
    ctx.beginPath();
    ctx.moveTo(x + inset, y + rnd() * h * .08);
    for (let i = 1; i <= steps; i++) ctx.lineTo(x + inset + (w - inset * 2) * i / steps, y + (rnd() - .35) * h * .1);
    for (let j = 1; j <= 7; j++) ctx.lineTo(x + w - (j % 2 ? -rnd() * h * .1 : h * (.12 + rnd() * .22)), y + h * j / 8);
    for (let i = steps; i >= 0; i--) ctx.lineTo(x + inset + (w - inset * 2) * i / steps, y + h + (rnd() - .65) * h * .1);
    for (let j = 7; j >= 1; j--) ctx.lineTo(x + (j % 2 ? -rnd() * h * .1 : h * (.12 + rnd() * .22)), y + h * j / 8);
    ctx.closePath();
  }

  function paintBrush(ctx, x, y, w, h, colour, rnd, u) {
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 18 * u; ctx.shadowOffsetY = 6 * u;
    brushPath(ctx, x, y, w, h, rnd);
    const g = ctx.createLinearGradient(0, y, 0, y + h);
    g.addColorStop(0, rgba(mix(colour, WHITE, .18))); g.addColorStop(.55, rgba(colour)); g.addColorStop(1, rgba(mix(colour, BLACK, .3)));
    ctx.fillStyle = g; ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.clip();
    for (let k = 0; k < 12; k++) { // bristle streaks
      const yy = y + rnd() * h;
      ctx.strokeStyle = k % 2 ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.14)'; ctx.lineWidth = h * (.02 + rnd() * .03);
      ctx.beginPath(); ctx.moveTo(x, yy); ctx.lineTo(x + w, yy + (rnd() - .5) * h * .2); ctx.stroke();
    }
    ctx.restore();
  }

  /** White comic speed lines fanning out from a point, between two angles. */
  function speedLines(ctx, cx, cy, r0, r1, from, to, n, u, rnd) {
    ctx.save(); ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.lineCap = 'round';
    for (let i = 0; i < n; i++) {
      const a = from + (to - from) * (i + .5) / n, len = r1 - r0 * (1 - rnd() * .25);
      ctx.lineWidth = (3 + rnd() * 3) * u;
      ctx.beginPath(); ctx.moveTo(cx + Math.cos(a) * r0, cy + Math.sin(a) * r0); ctx.lineTo(cx + Math.cos(a) * (r0 + len * .45), cy + Math.sin(a) * (r0 + len * .45)); ctx.stroke();
    }
    ctx.restore();
  }

  const drop = { fill: '#ffffff', drop: true };

  function techBackground(ctx, s, F) {
    const m = Math.min(F.w, F.h), rnd = seeded(7);
    const g = ctx.createLinearGradient(0, 0, F.w * .3, F.h);
    g.addColorStop(0, rgba(s.bg)); g.addColorStop(.55, rgba(mix(s.bg, PURPLE, .35))); g.addColorStop(1, rgba(mix(s.bg, [45, 12, 80], .75)));
    ctx.fillStyle = g; ctx.fillRect(0, 0, F.w, F.h);
    for (const [x, y, r, c, a] of [[F.w * .05, F.h * .45, m * .7, CYAN, .18], [F.w, F.h * .15, m * .75, MAGENTA, .2], [F.w * .5, F.h * .55, m * .6, PURPLE, .22]]) {
      const rg = ctx.createRadialGradient(x, y, 0, x, y, r); rg.addColorStop(0, rgba(c, a)); rg.addColorStop(1, rgba(c, 0));
      ctx.fillStyle = rg; ctx.fillRect(0, 0, F.w, F.h);
    }
    // Circuit traces along the edges.
    ctx.save(); ctx.lineWidth = Math.max(1.5, m * .002); ctx.strokeStyle = rgba(CYAN, .16); ctx.fillStyle = rgba(CYAN, .3);
    for (let i = 0; i < 14; i++) {
      const left = i % 2 === 0, y0 = rnd() * F.h, x0 = left ? 0 : F.w, run = m * (.06 + rnd() * .12), dir = left ? 1 : -1, dy = (rnd() - .5) * m * .1;
      ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x0 + dir * run, y0); ctx.lineTo(x0 + dir * (run + Math.abs(dy)), y0 + dy); ctx.stroke();
      ctx.beginPath(); ctx.arc(x0 + dir * (run + Math.abs(dy)), y0 + dy, m * .005, 0, Math.PI * 2); ctx.fill();
    }
    // Bokeh.
    for (let i = 0; i < 28; i++) {
      const c = [CYAN, MAGENTA, WHITE][i % 3], x = rnd() * F.w, y = rnd() * F.h, r = m * (.006 + rnd() * .03);
      const bg = ctx.createRadialGradient(x, y, 0, x, y, r); bg.addColorStop(0, rgba(c, .05 + rnd() * .14)); bg.addColorStop(1, rgba(c, 0));
      ctx.fillStyle = bg; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
  }

  /** Words fitted on up to maxLines lines inside width; returns {size, lines}. */
  function fitLines(ctx, s, text, width, weight, size, maxLines, minSize = 10) {
    let lines = wrapWords(ctx, s, tokens(text), width, weight, size);
    while ((lines.length > maxLines || lines.some(l => lineWidth(ctx, s, l, weight, size) > width)) && size > minSize) {
      size -= 1; lines = wrapWords(ctx, s, tokens(text), width, weight, size);
    }
    return { size, lines };
  }

  function techBanner(ctx, s, b, cx, R, u) {
    const W = heavy(s), { size, lines } = fitLines(ctx, s, s.hook, R.w * .74, W, b.size, 1);
    const tw = lineWidth(ctx, s, lines[0] || [], W, size), w = Math.min(R.w, tw + size * 1.6), x = cx - w / 2;
    paintBrush(ctx, x, b.y, w, b.h, s.accent, seeded(11), u);
    drawWords(ctx, s, lines[0] || [], cx - tw / 2, b.y + b.h / 2 + size * .04, W, size, () => drop);
  }

  function techSub(ctx, s, b, cx, u) {
    const W = heavy(s);
    // Nothing starred? Make the last word yellow so the line is always two-tone.
    if (!s.headline.includes('*') && b.lines.length) { const l = b.lines[b.lines.length - 1]; l[l.length - 1] = { ...l[l.length - 1], key: true }; }
    ctx.save(); ctx.translate(cx, b.y + b.h / 2); ctx.rotate(-.04);
    b.lines.forEach((line, i) => {
      const w = lineWidth(ctx, s, line, W, b.size), y = (i - (b.lines.length - 1) / 2) * b.size * 1.22;
      drawWords(ctx, s, line, -w / 2, y, W, b.size, tok => tok.key
        ? { fill: [rgba(mix(s.hl, WHITE, .3)), rgba(s.hl), rgba(mix(s.hl, BLACK, .1))], stroke: rgba(INK), strokeW: .1, drop: true }
        : { fill: '#ffffff', stroke: rgba(INK), strokeW: .1, drop: true });
    });
    ctx.restore();
  }

  function techSticker(ctx, s, cx, cy, r, u) {
    const rnd = seeded(23), W = heavy(s);
    speedLines(ctx, cx, cy, r * 1.08, r * 1.5, Math.PI * .72, Math.PI * 1.28, 7, u, rnd);
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(.14);
    ctx.beginPath();
    const n = 14;
    for (let i = 0; i < n * 2; i++) { const a = Math.PI * i / n, rr = (i % 2 ? r * .76 : r) * (1 + (rnd() - .5) * .1); ctx.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); }
    ctx.closePath();
    ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 16 * u; ctx.shadowOffsetY = 6 * u;
    const g = ctx.createLinearGradient(0, -r, 0, r); g.addColorStop(0, rgba(mix(s.hl, WHITE, .3))); g.addColorStop(1, rgba(mix(s.hl, [255, 150, 0], .25)));
    ctx.fillStyle = g; ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.lineJoin = 'round'; ctx.lineWidth = 7 * u; ctx.strokeStyle = rgba(INK); ctx.stroke();
    const { size, lines } = fitLines(ctx, s, s.offer, r * 1.25, W, r * .42, 2, 8);
    lines.forEach((l, i) => {
      const w = lineWidth(ctx, s, l, W, size);
      drawWords(ctx, s, l, -w / 2, (i - (lines.length - 1) / 2) * size * 1.08, W, size, () => ({ fill: rgba(INK) }));
    });
    ctx.restore();
  }

  /** "Rs. 14,500" → "රු. 14,500/-" as on Sri Lankan price tags. */
  const lkPrice = p => { const t = p.replace(/^Rs\.?\s*/i, 'රු. '); return /\d$/.test(t) ? t + '/-' : t; };

  function techPrice(ctx, s, x, y, h, maxW, u) {
    const W = heavy(s), words = tokens(lkPrice(s.price));
    let size = h * .5;
    while (lineWidth(ctx, s, words, W, size) + h * 1.1 > maxW && size > 10) size -= 1;
    const tw = lineWidth(ctx, s, words, W, size), w = tw + h * 1.1;
    ctx.save(); ctx.translate(x + w / 2, y + h / 2); ctx.rotate(-.07);
    paintBrush(ctx, -w / 2, -h / 2, w, h, s.accent, seeded(31), u);
    drawWords(ctx, s, words, -tw / 2, size * .04, W, size, () => drop);
    if (s.priceNote) { // yellow pill under the slash
      const ns = h * .3, nw = lineWidth(ctx, s, tokens(s.priceNote), W, ns) + ns * 1.6, nh = ns * 1.7, nx = w / 2 - nw - h * .1, ny = h / 2 - nh * .25;
      ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 10 * u; ctx.fillStyle = rgba(s.hl);
      roundRect(ctx, nx, ny, nw, nh, nh / 2); ctx.fill(); ctx.restore();
      ctx.lineWidth = 3 * u; ctx.strokeStyle = rgba(INK); ctx.stroke();
      drawWords(ctx, s, tokens(s.priceNote), nx + ns * .8, ny + nh / 2 + ns * .04, W, ns, () => ({ fill: rgba(INK) }));
    }
    ctx.restore();
    speedLines(ctx, x + w * .1, y + h * .1, h * .55, h * 1.1, Math.PI * 1.05, Math.PI * 1.45, 4, u, seeded(41));
    return w;
  }

  function techFreebie(ctx, s, x, y, w, h, u) {
    if (!s.freebie || w < 80 * u) return;
    ctx.save();
    ctx.shadowColor = rgba(MAGENTA, .85); ctx.shadowBlur = 24 * u;
    ctx.fillStyle = rgba(mix(PURPLE, BLACK, .45), .72); roundRect(ctx, x, y, w, h, h * .28); ctx.fill();
    ctx.shadowBlur = 0; ctx.lineWidth = 3 * u; ctx.strokeStyle = rgba(mix(MAGENTA, WHITE, .3)); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.08)'; roundRect(ctx, x + 6 * u, y + 6 * u, w - 12 * u, h * .38, h * .2); ctx.fill(); // glass shine
    ctx.restore();
    const isz = h * .46, W = heavy(s);
    drawIcon(ctx, 'gift', x + h * .22, y + (h - isz) / 2, isz, rgba(s.hl), 2.2);
    const tx = x + h * .22 + isz + h * .18, { size, lines } = fitLines(ctx, s, s.freebie, x + w - tx - h * .18, W, h * .3, 2, 8);
    const top = y + (h - lines.length * size * 1.18) / 2;
    lines.forEach((l, i) => drawWords(ctx, s, l, tx, top + size * .6 + i * size * 1.18, W, size, tok => ({ fill: tok.key ? rgba(s.hl) : '#ffffff' })));
  }

  /** Size the two spec cards so every line fits; returns the shared height. */
  function cardMetrics(ctx, s, cw, u) {
    const W = heavy(s), pad = 22 * u;
    let title = 40 * u, line = 31 * u;
    for (; line > 9; title -= 1, line -= .8) {
      const ok = s.cards.every(c => lineWidth(ctx, s, tokens(c.title), W, title) <= cw - pad * 2
        && c.points.every(p => lineWidth(ctx, s, tokens(p), 700, line) <= cw - pad * 2 - line * 1.4));
      if (ok) break;
    }
    const rows = Math.max(...s.cards.map(c => c.points.length), 1);
    return { title, line, pad, h: pad * 2 + title * 1.35 + rows * line * 1.55 };
  }

  function techCards(ctx, s, b, R, u) {
    const n = s.cards.length, gap = 20 * u, cw = (R.w - gap * (n - 1)) / n, M = b.metrics, W = heavy(s);
    s.cards.forEach((c, i) => {
      const x = R.x + i * (cw + gap), edge = i ? MAGENTA : CYAN;
      ctx.save();
      ctx.shadowColor = rgba(edge, .85); ctx.shadowBlur = 22 * u;
      ctx.fillStyle = 'rgba(8,20,64,.88)'; roundRect(ctx, x, b.y, cw, b.h, 22 * u); ctx.fill();
      ctx.shadowBlur = 0; ctx.lineWidth = 3 * u; ctx.strokeStyle = rgba(edge); ctx.stroke();
      ctx.restore();
      let y = b.y + M.pad + M.title * .62;
      drawWords(ctx, s, tokens(c.title), x + M.pad, y, W, M.title, () => ({ fill: rgba(s.hl), drop: true }));
      y += M.title * .7 + M.line * .95;
      for (const p of c.points) {
        const r = M.line * .5;
        ctx.fillStyle = rgba(GREEN); ctx.beginPath(); ctx.arc(x + M.pad + r, y, r, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = '#fff'; ctx.lineWidth = r * .28; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        ctx.beginPath(); ctx.moveTo(x + M.pad + r * .5, y); ctx.lineTo(x + M.pad + r * .9, y + r * .38); ctx.lineTo(x + M.pad + r * 1.5, y - r * .38); ctx.stroke();
        drawWords(ctx, s, tokens(p), x + M.pad + M.line * 1.4, y + M.line * .04, 700, M.line, () => ({ fill: '#ffffff' }));
        y += M.line * 1.55;
      }
    });
  }

  function techFooter(ctx, s, x, y, w, h, u) {
    ctx.save();
    ctx.shadowColor = rgba(MAGENTA, .8); ctx.shadowBlur = 26 * u;
    const g = ctx.createLinearGradient(x, 0, x + w, 0);
    g.addColorStop(0, rgba(s.accent)); g.addColorStop(.5, rgba(MAGENTA)); g.addColorStop(1, rgba(PURPLE));
    ctx.fillStyle = g; roundRect(ctx, x, y, w, h, h / 2); ctx.fill();
    ctx.shadowBlur = 0; ctx.fillStyle = 'rgba(255,255,255,.18)'; roundRect(ctx, x + h * .3, y + h * .08, w - h * .6, h * .34, h * .17); ctx.fill();
    ctx.restore();
    const words = tokens(s.contact), W = heavy(s);
    let size = h * .42;
    while (lineWidth(ctx, s, words, W, size) > w - h * 2 && size > 9) size -= 1;
    const tw = lineWidth(ctx, s, words, W, size), isz = size * 1.1, total = isz + size * .5 + tw, sx = x + (w - total) / 2;
    drawIcon(ctx, 'phone', sx, y + (h - isz) / 2, isz, '#ffffff', 2.4);
    drawWords(ctx, s, words, sx + isz + size * .5, y + h / 2 + size * .04, W, size, () => drop);
  }

  function renderTech(F, ctx, s) {
    const m = Math.min(F.w, F.h), pad = Math.round(m * .045), wide = F.w > F.h * 1.4, tall = F.h / F.w;
    let top = F.safeTop || pad, bottom = F.h - (F.safeBottom || pad);
    const logoW = Math.round(m * (wide ? .2 : .22)), logoH = logoW * logoRatio(), logoTop = s.corner[0] === 't';
    const logoY = logoTop ? top : bottom - logoH;
    if (logoTop) top += logoH + m * .02; else bottom -= logoH + m * .02;

    techBackground(ctx, s, F);

    const R = wide ? { x: pad, y: top, w: F.w * .52 - pad, h: bottom - top } : { x: pad, y: top, w: F.w - pad * 2, h: bottom - top };
    const minPic = wide ? 0 : R.h * (tall > 1.5 ? .46 : tall > 1.1 ? .42 : .38);
    let plan;
    for (let k = 1; k >= .4; k -= .03) {
      const u = m / 1080 * k, g = 18 * u, blocks = [];
      if (s.hook) blocks.push({ kind: 'banner', size: 62 * u, h: 112 * u });
      if (s.headline) {
        const f = fitLines(ctx, s, s.headline, R.w * .92, heavy(s), 66 * u, 2, 12);
        blocks.push({ kind: 'sub', size: f.size, lines: f.lines, h: f.lines.length * f.size * 1.22 + 10 * u });
      }
      if (!wide) blocks.push({ kind: 'picture', h: 0 });
      if (wide && (s.price || s.freebie)) blocks.push({ kind: 'pricerow', h: 104 * u });
      if (s.cards.length) {
        const metrics = cardMetrics(ctx, s, (R.w - 20 * u * (s.cards.length - 1)) / s.cards.length, u);
        blocks.push({ kind: 'cards', metrics, h: metrics.h });
      }
      if (s.contact) blocks.push({ kind: 'footer', h: 76 * u });
      const fixed = blocks.reduce((t, b) => t + b.h, 0) + g * (blocks.length - 1);
      plan = { blocks, u, g, free: R.h - fixed };
      if (plan.free >= minPic) break;
    }
    let y = R.y + (wide ? Math.max(0, plan.free / 2) : 0);
    for (const b of plan.blocks) { if (b.kind === 'picture') b.h = Math.max(0, plan.free); b.y = y; y += b.h + plan.g; }
    const u = plan.u, cx = R.x + R.w / 2, at = k => plan.blocks.find(b => b.kind === k);

    // Product picture: large and central (right half on the landscape size).
    const pic = at('picture');
    // The picture reaches up behind the sub-headline and down behind the price, so the product stays big.
    const sub = at('sub'), cards = at('cards');
    const y0 = sub ? sub.y : pic?.y, y1 = cards ? cards.y : bottom;
    const picBox = wide ? { x: F.w * .44, y: 0, w: F.w * .56, h: F.h } : { x: 0, y: y0, w: F.w, h: y1 - y0 };
    drawPicture(ctx, picture, picBox, .12, wide ? Infinity : 1.3);

    for (const b of plan.blocks) {
      if (b.kind === 'banner') techBanner(ctx, s, b, cx, R, u);
      if (b.kind === 'sub') techSub(ctx, s, b, cx, u);
      if (b.kind === 'cards') techCards(ctx, s, b, R, u);
      if (b.kind === 'footer') techFooter(ctx, s, R.x, b.y, R.w, b.h, u);
    }

    // Price slash + glass badge overlap the lower-left of the product.
    const ph = 100 * u, row = wide ? at('pricerow') : null;
    const py = row ? row.y : pic.y + pic.h - ph - 22 * u;
    let px = R.x;
    if (s.price) px += techPrice(ctx, s, R.x, py, ph, R.w * .55, u) + 24 * u;
    if (s.freebie) techFreebie(ctx, s, px, py + ph * .08, Math.min(R.x + R.w - px, 420 * u), ph * .84, u);

    // Comic sticker at the upper right of the product.
    if (s.offer) {
      const r = (wide ? 92 : 108) * u;
      if (wide) techSticker(ctx, s, F.w - pad - r, top + r * .9, r, u);
      else techSticker(ctx, s, R.x + R.w - r * .95, pic.y + r * .9, r, u);
    }

    const lx = s.corner[1] === 'l' ? pad : s.corner[1] === 'r' ? F.w - pad - logoW : F.w / 2 - logoW / 2;
    drawLogo(ctx, s, lx, logoY, logoW);
  }

  // ================= CLASSIC layout =================

  /** Lay out badge, headline, sub line and features; shrink until they fit the box. */
  function textBlock(ctx, s, box, maxSize) {
    const L = looks(s), sticker = s.style === 'sticker', W = heavy(s);
    const slack = sticker ? .1 : Math.max(L.head.strokeW || 0, L.key.strokeW || 0) + (L.head.depth || 0);
    for (let size = maxSize; size >= 18; size -= 2) {
      const items = []; let h = 0;
      const add = (kind, lines, lh, gapAfter, extra = {}) => { items.push({ kind, lines, lh, y: h, ...extra }); h += lines.length * lh + gapAfter; };
      if (s.offer) add('badge', [tokens(s.offer)], size * .34 * 2.1, size * .42, { size: size * .34, pad: size * .34 * .95 });
      const words = [...tokens(s.headline), ...tokens(s.hero, true)];
      if (words.length) {
        const l = wrapWords(ctx, s, words, box.w - size * (slack + .1), W, size, sticker);
        if (l.length > 4) continue;
        add('head', l, size * (sticker ? 1.48 : 1.3 + slack), size * .3);
      }
      if (s.subline) add('sub', wrapWords(ctx, s, tokens(s.subline), box.w, 700, size * .45), size * .45 * 1.5, size * .34, { size: size * .45 });
      for (const ft of s.features) add('bullet', wrapWords(ctx, s, tokens(ft.text), box.w - size * .62, 700, size * .4), size * .4 * 1.5, size * .12, { size: size * .4, icon: ft.icon });
      if (h <= box.h) return { items, h, size, L };
    }
    return { items: [], h: 0, size: 18, L };
  }

  function geometry(F) {
    const m = Math.min(F.w, F.h), pad = Math.round(m * .065);
    const top = F.safeTop || pad, bottom = F.h - (F.safeBottom || pad);
    const footH = Math.round(m * (F.layout === 'side' ? .085 : .075));
    const footY = bottom - footH;
    if (F.layout === 'side') {
      return {
        pad, footY, footH, maxSize: m * .1,
        text: { x: pad, y: top, w: F.w * .5 - pad, h: footY - top - pad * .6 },
        image: { x: F.w * .40, y: 0, w: F.w * .60, h: footY - pad * .3 },
        priceAt: [F.w - pad - m * .1, top + m * .1],
      };
    }
    const textH = (bottom - top) * (F.safeTop ? .48 : .42);
    return {
      pad, footY, footH, maxSize: m * (F.safeTop ? .15 : .13),
      text: { x: pad, y: top, w: F.w - pad * 2, h: textH },
      image: { x: 0, y: top + textH, w: F.w, h: footY - top - textH - pad * .4 },
      priceAt: [F.w - pad - m * .1, top + textH + m * .13],
    };
  }

  /** Brand stripes (from the logo mark) in the top-right corner, kept clear of full-width text. */
  function stripes(ctx, F, s, G) {
    const m = Math.min(F.w, F.h), t = m * .007, gap = t * 2.6, len = m * .2;
    let drop = len * .55;
    if (F.layout === 'stack') drop = Math.min(drop, G.text.y - m * .03 - 5 * gap - t * 2);
    if (drop < m * .02) return;
    ctx.save(); ctx.strokeStyle = rgba(s.accent, .35); ctx.lineWidth = t; ctx.lineCap = 'square';
    for (let k = 0; k < 5; k++) {
      const o = m * .03 + k * gap;
      ctx.beginPath(); ctx.moveTo(F.w - len - o, 0); ctx.lineTo(F.w - len - o, drop + o); ctx.lineTo(F.w, drop + o); ctx.stroke();
    }
    ctx.restore();
  }

  /** Starburst "sale sticker" for the bold styles, a ringed circle for neon and clean. */
  function drawPrice(ctx, s, F, [cx, cy]) {
    if (!s.price) return;
    const r = Math.min(F.w, F.h) * .1, burst = ['promo', 'pop', 'fire', 'sticker', '3d'].includes(s.style);
    const fill = burst ? s.hl : s.accent, ink = onColour(fill);
    ctx.save();
    ctx.translate(cx, cy); ctx.rotate(burst ? -.12 : 0);
    ctx.shadowColor = s.style === 'neon' ? rgba(s.accent) : 'rgba(0,0,0,.35)'; ctx.shadowBlur = r * .3; ctx.shadowOffsetY = s.style === 'neon' ? 0 : r * .06;
    const g = ctx.createLinearGradient(0, -r, 0, r); g.addColorStop(0, rgba(mix(fill, WHITE, .25))); g.addColorStop(1, rgba(mix(fill, BLACK, .12)));
    ctx.fillStyle = g; ctx.beginPath();
    if (burst) {
      const n = 18;
      for (let i = 0; i < n * 2; i++) { const a = Math.PI * i / n, rr = i % 2 ? r * .86 : r * 1.04; ctx.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); }
      ctx.closePath();
    } else ctx.arc(0, 0, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.strokeStyle = rgba(ink, .5); ctx.lineWidth = r * .035; ctx.beginPath(); ctx.arc(0, 0, r * .74, 0, Math.PI * 2); ctx.stroke();
    ctx.fillStyle = rgba(ink); ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    let size = r * .5; ctx.font = latin(900, size);
    while (ctx.measureText(s.price).width > r * 1.36 && size > 10) { size -= 1; ctx.font = latin(900, size); }
    ctx.fillText(s.price, 0, s.was ? r * .14 : 0);
    if (s.was) {
      ctx.font = latin(800, size * .55); ctx.fillStyle = rgba(ink, .8);
      const wy = -r * .3, ww = ctx.measureText(s.was).width;
      ctx.fillText(s.was, 0, wy);
      ctx.strokeStyle = rgba(s.accent); ctx.lineWidth = Math.max(2, size * .08);
      ctx.beginPath(); ctx.moveTo(-ww / 2 - 2, wy); ctx.lineTo(ww / 2 + 2, wy); ctx.stroke();
    }
    ctx.restore();
  }

  function drawBadge(ctx, s, it, x, y, line) {
    const tw = lineWidth(ctx, s, line, 800, it.size), w = tw + it.pad * 2, h = it.lh, tilt = s.style !== 'clean' && s.style !== 'neon';
    ctx.save(); ctx.translate(x, y); if (tilt) ctx.rotate(-.04);
    let fill;
    if (s.style === 'neon') {
      ctx.shadowColor = rgba(s.hl); ctx.shadowBlur = h * .5; ctx.strokeStyle = rgba(s.hl); ctx.lineWidth = h * .08;
      roundRect(ctx, 0, 0, w, h, h / 2); ctx.stroke(); fill = rgba(mix(s.hl, WHITE, .4));
    } else {
      const g = ctx.createLinearGradient(0, 0, 0, h); g.addColorStop(0, rgba(mix(s.accent, WHITE, .18))); g.addColorStop(1, rgba(mix(s.accent, BLACK, .18)));
      ctx.shadowColor = 'rgba(0,0,0,.3)'; ctx.shadowBlur = h * .35; ctx.shadowOffsetY = h * .08;
      ctx.fillStyle = g; roundRect(ctx, 0, 0, w, h, s.style === 'sticker' ? h * .18 : h / 2); ctx.fill();
      fill = rgba(onColour(s.accent));
    }
    ctx.shadowColor = 'transparent';
    drawWords(ctx, s, line, it.pad, h / 2, 800, it.size, () => ({ fill }));
    ctx.restore();
  }

  function drawText(ctx, s, F, G) {
    const block = textBlock(ctx, s, G.text, G.maxSize), L = block.L;
    const ink = onColour(s.bg), keyCol = rgba(lum(s.bg) < .45 ? s.hl : mix(s.hl, BLACK, .35));
    const y0 = G.text.y + (F.layout === 'side' ? (G.text.h - block.h) / 2 : 0);
    for (const it of block.items) {
      it.lines.forEach((line, i) => {
        const y = y0 + it.y + i * it.lh + it.lh / 2;
        if (it.kind === 'badge') return drawBadge(ctx, s, it, G.text.x, y0 + it.y, line);
        if (it.kind === 'head') return drawHeadLine(ctx, s, L, line, G.text.x + (s.style === 'sticker' ? 4 : 0), y, block.size, i);
        const sub = it.kind === 'sub', size = it.size, indent = sub ? 0 : block.size * .62;
        if (!sub && i === 0) {
          const cx = G.text.x + size * .55, cy = y, r = size * .62;
          ctx.fillStyle = rgba(s.accent); ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
          if (it.icon === 'check') {
            ctx.strokeStyle = rgba(onColour(s.accent)); ctx.lineWidth = size * .14; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            ctx.beginPath(); ctx.moveTo(cx - size * .24, cy); ctx.lineTo(cx - size * .05, cy + size * .2); ctx.lineTo(cx + size * .27, cy - size * .2); ctx.stroke();
          } else drawIcon(ctx, it.icon, cx - r * .62, cy - r * .62, r * 1.24, rgba(onColour(s.accent)));
        }
        // Starred words in the sub line and features take the highlight colour.
        drawWords(ctx, s, line, G.text.x + indent, y, 700, size, tok => ({ fill: tok.key ? keyCol : rgba(ink, sub ? .86 : .95) }));
      });
    }
  }

  function drawFooter(ctx, s, F, G) {
    const m = Math.min(F.w, F.h), logoW = Math.round(m * (F.w > F.h ? .3 : .24));
    const right = s.corner[1] !== 'l';
    const logoX = right ? F.w - G.pad - logoW : G.pad;
    let x = right ? G.pad : G.pad + logoW + G.pad * .6;
    const room = F.w - G.pad * 2 - logoW - G.pad * .6;

    if (s.contact) {
      const words = tokens(s.contact), r = G.footH * .3, y = G.footY + G.footH / 2;
      let size = G.footH * .36;
      while (lineWidth(ctx, s, words, 800, size) + r * 3 > room && size > 10) size -= 1;
      ctx.fillStyle = rgba(s.accent); ctx.beginPath(); ctx.arc(x + r, y, r, 0, Math.PI * 2); ctx.fill();
      drawIcon(ctx, 'phone', x + r * .4, y - r * .6, r * 1.2, rgba(onColour(s.accent)), 2.4);
      drawWords(ctx, s, words, x + r * 2 + size * .45, y, 800, size, tok => ({ fill: tok.key ? rgba(s.hl) : rgba(onColour(s.bg), .9) }));
    }
    drawLogo(ctx, s, logoX, G.footY + (G.footH - logoW * logoRatio()) / 2, logoW);
  }

  function renderClassic(F, ctx, s) {
    const G = geometry(F);
    ctx.fillStyle = rgba(s.bg); ctx.fillRect(0, 0, F.w, F.h);
    const I = G.image, glow = ctx.createRadialGradient(I.x + I.w / 2, I.y + I.h / 2, 0, I.x + I.w / 2, I.y + I.h / 2, Math.max(I.w, I.h) * .7);
    glow.addColorStop(0, rgba(mix(s.bg, lum(s.bg) > .45 ? WHITE : s.accent, .22))); glow.addColorStop(1, rgba(s.bg, 0));
    ctx.fillStyle = glow; ctx.fillRect(0, 0, F.w, F.h);
    stripes(ctx, F, s, G);
    drawPicture(ctx, picture, I);
    drawText(ctx, s, F, G);
    drawPrice(ctx, s, F, G.priceAt);
    drawFooter(ctx, s, F, G);
  }

  function render(F, canvas) {
    const s = state(), ctx = canvas.getContext('2d', { willReadFrequently: true });
    canvas.width = F.w; canvas.height = F.h;
    ({ classic: renderClassic, poster: renderPoster }[s.layout] || renderTech)(F, ctx, s);
  }

  // ---------- previews ----------
  const previews = document.getElementById('previews');
  const canvases = FORMATS.map(F => {
    const card = document.createElement('div'); card.className = 'fbad-card';
    card.innerHTML = `<header><h3>${F.name}<small>${F.note}</small></h3><button class="btn sm" type="button">Download</button></header><canvas aria-label="${F.name} ad preview"></canvas>`;
    previews.append(card);
    const canvas = card.querySelector('canvas');
    card.querySelector('button').addEventListener('click', () => download(F, canvas));
    return [F, canvas];
  });

  let queued = false;
  const redraw = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; canvases.forEach(([F, c]) => render(F, c)); }); };

  function download(F, canvas) {
    return new Promise(ok => canvas.toBlob(blob => {
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
      a.download = `speedtech-${SLUG}-${F.key}-${F.w}x${F.h}.png`; a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); ok(); }, 400);
    }, 'image/png'));
  }
  document.getElementById('downloadAll').addEventListener('click', async () => { for (const [F, c] of canvases) await download(F, c); });

  // Show only the fields the chosen layout uses.
  const syncFields = () => {
    const layout = state().layout;
    edit.querySelectorAll('[data-layouts]').forEach(el => { el.hidden = !el.dataset.layouts.split(' ').includes(layout); });
  };
  edit.addEventListener('change', e => { if (e.target.name === 'layout') syncFields(); });
  syncFields();

  edit.addEventListener('input', redraw);
  edit.addEventListener('change', async e => { if (e.target.name === 'font') await fontsReady(); redraw(); });

  document.getElementById('copyCaption').addEventListener('click', async e => {
    await navigator.clipboard.writeText(field('caption').value);
    e.target.textContent = 'Copied'; setTimeout(() => { e.target.textContent = 'Copy caption'; }, 1500);
  });

  // ---------- AI ----------
  let lastScene = '';
  ideaForm.addEventListener('submit', async e => {
    e.preventDefault();
    const parts = e.submitter?.dataset.parts || 'all', s = state();
    const body = new FormData(ideaForm);
    body.set('parts', parts); body.set('scene', lastScene); body.set('bg', field('bg').value); body.set('hl', field('hl').value); body.set('layout', s.layout);
    const buttons = ideaForm.querySelectorAll('button'); buttons.forEach(b => { b.disabled = true; });
    status.className = 'fbad-status';
    status.textContent = parts === 'text' ? 'Writing the ad text…' : 'Writing the text and drawing the picture… this can take up to a minute.';
    previews.classList.add('fbad-wait');
    try {
      const res = await fetch(ENDPOINT, { method: 'POST', body, headers: { Accept: 'application/json' } });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || (res.status === 429 ? 'Too many tries. Wait a minute.' : 'Something went wrong. Please try again.'));
      if (data.copy) {
        const c = data.copy; lastScene = c.scene;
        for (const k of ['offer', 'headline', 'hero', 'subline', 'caption', 'price_note']) field(k).value = c[k] || '';
        field('features').value = (c.features || []).map(f => `${f.icon}: ${f.text}`).join('\n');
        field('hook').value = c.hook || ''; field('freebie').value = c.freebie || '';
        (c.cards || []).slice(0, 2).forEach((card, i) => {
          field(`card${i + 1}_title`).value = card.title || '';
          field(`card${i + 1}`).value = (card.points || []).join('\n');
        });
        field('price').value = c.price || ''; field('was').value = c.was || '';
        if (!c.price) field('price_note').value = '';
        field('bg').value = c.bg; field('accent').value = c.accent; field('hl').value = c.hl;
        // Posters keep the chosen look (Promo by default); the AI's style pick only applies to Classic.
        const style = edit.querySelector(`[name="style"][value="${c.style}"]`); if (style && s.layout === 'classic') style.checked = true;
      }
      if (data.image) await setPicture(data.image, true);
      else if (data.warning && parts !== 'text') await setPicture(PRODUCT_IMAGE, true, .08).catch(() => {});
      status.textContent = data.warning || 'Done. Check the Sinhala text, try other styles, then download.';
      if (data.warning) status.className = 'fbad-status err';
    } catch (err) {
      status.className = 'fbad-status err'; status.textContent = err.message;
    } finally {
      buttons.forEach(b => { b.disabled = @json(! $configured); });
      previews.classList.remove('fbad-wait');
      redraw();
    }
  });

  // ---------- start ----------
  // Uploaded fonts: one file covers every weight, so the browser never fakes bold on them.
  for (const f of CUSTOM_FONTS) {
    try { document.fonts.add(new FontFace(f.family, `url("${f.url}")`, { weight: '100 900' })); } catch {}
  }

  const SAMPLE = 'සිංහල අකුරු ශ්‍රී Aa 0123';
  const fontsReady = () => {
    const family = field('font').value;
    return Promise.all([
      document.fonts.load(`${WEIGHTS[family] || 800} 40px "${family}"`, SAMPLE),
      document.fonts.load(`700 40px "${family}"`, SAMPLE),
      document.fonts.load(`900 40px "Noto Sans Sinhala"`, SAMPLE), document.fonts.load(`700 40px "Noto Sans Sinhala"`, SAMPLE),
      document.fonts.load('900 40px "Montserrat"', 'Aa 0123'), document.fonts.load('800 40px "Montserrat"', 'Aa'),
      document.fonts.load('italic 900 40px "Montserrat"', 'Aa'), document.fonts.load('700 40px "Montserrat"', 'Aa'),
    ]).catch(() => {});
  };

  Promise.all([
    fontsReady(),
    load(LOGOS.dark).then(i => { logos.dark = i; }),
    load(LOGOS.light).then(i => { logos.light = i; }),
    setPicture(PRODUCT_IMAGE, true, .08).catch(() => {}),
  ]).finally(redraw);
})();
</script>
@endpush
