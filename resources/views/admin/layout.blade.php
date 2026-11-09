<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>{{ $title ?? 'Dashboard' }} | SpeedTech Admin</title>
  <link rel="icon" href="{{ asset('assets/favicon.png') }}" type="image/png">
  <link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ filemtime(public_path('admin.css')) }}">
</head>
<body>
  @include('partials.icons')
  <div class="admin">
    <aside class="sidebar">
      <a class="side-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('assets/logo-light.png') }}" alt="SpeedTech" width="874" height="169"><span>Admin</span></a>
      <nav aria-label="Admin">
        @php($nav = [
          ['admin.dashboard', 'Dashboard', 'home', 'admin.dashboard'],
          ['admin.orders.index', 'Orders', 'cart', 'admin.orders.*'],
          ['admin.enquiries.index', 'Enquiries & repairs', 'mail', 'admin.enquiries.*'],
          ['admin.products.index', 'Products', 'box', 'admin.products.*'],
          ['admin.inventory.index', 'Inventory', 'storage', 'admin.inventory.*'],
          ['admin.settings', 'Payments & delivery', 'tag', 'admin.settings*'],
        ])
        @foreach ($nav as [$route, $label, $icon, $pattern])
          <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])><svg class="icon"><use href="#icon-{{ $icon }}"/></svg>{{ $label }}</a>
        @endforeach
        <span class="side-label">Spare-part finder</span>
        <a href="{{ route('admin.catalog.index', 'brands') }}" @class(['active' => request()->is('admin/catalog/brands')])><svg class="icon"><use href="#icon-tag"/></svg>1. Brands</a>
        <a href="{{ route('admin.models.index') }}" @class(['active' => request()->routeIs('admin.models.*')])><svg class="icon"><use href="#icon-laptop"/></svg>2–3. Models &amp; years</a>
        <a href="{{ route('admin.catalog.index', 'part-categories') }}" @class(['active' => request()->is('admin/catalog/part-categories')])><svg class="icon"><use href="#icon-cpu"/></svg>4. Part types</a>
        <span class="side-label">Accessories</span>
        <a href="{{ route('admin.catalog.index', 'categories') }}" @class(['active' => request()->is('admin/catalog/categories')])><svg class="icon"><use href="#icon-storage"/></svg>Shop categories</a>
      </nav>
      <div class="side-foot">
        <a href="{{ route('home') }}" target="_blank" rel="noopener">View store ↗</a>
        <a href="{{ route('admin.account') }}">{{ auth()->user()->email }}</a>
        <form action="{{ route('admin.logout') }}" method="post">@csrf<button type="submit">Sign out</button></form>
      </div>
    </aside>

    <main class="content">
      <header class="content-head">
        <div>
          @isset($breadcrumb)<div class="crumb">{!! $breadcrumb !!}</div>@endisset
          <h1>{{ $title ?? 'Dashboard' }}</h1>
        </div>
        <div class="head-actions">@yield('actions')</div>
      </header>

      @if (session('status'))<div class="alert ok" role="status">{{ session('status') }}</div>@endif
      @if (session('error'))<div class="alert err" role="alert">{{ session('error') }}</div>@endif
      @if ($errors->any())
        <div class="alert err" role="alert"><b>Please fix the following:</b><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
      @endif

      @yield('content')
    </main>
  </div>
  <script>
    document.addEventListener('submit', e => {
      const msg = e.target.dataset.confirm;
      if (msg && !confirm(msg)) e.preventDefault();
    });
    document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));
  </script>
  @stack('scripts')
</body>
</html>
