@extends('admin.layout', ['title' => $config['title']])

@section('content')
  <p class="help">{{ $config['help'] }} Lower sort numbers appear first. Hidden items stay in the database but don't show in the store.</p>

  <section class="card">
    <h2>Add {{ $config['singular'] }}</h2>
    <form class="inline-form" method="post" action="{{ route('admin.catalog.store', $type) }}">
      @csrf
      <input class="w-lg" name="name" placeholder="Name" required maxlength="100">
      <input class="w-md" name="slug" placeholder="slug (optional)" maxlength="100">
      <input class="w-sm" type="number" name="sort_order" placeholder="Sort" min="0" value="{{ $items->max('sort_order') + 1 }}">
      <input type="hidden" name="is_active" value="1">
      <button class="btn primary" type="submit"><svg class="icon"><use href="#icon-plus"/></svg>Add</button>
    </form>
  </section>

  <div class="table-wrap"><table class="data">
    <thead><tr><th>Name / slug / sort / visible</th><th class="num">{{ $type === 'brands' ? 'Models' : 'Products' }}</th><th></th></tr></thead>
    <tbody>
      @forelse ($items as $item)
        <tr>
          <td>
            <form class="inline-form" method="post" action="{{ route('admin.catalog.update', [$type, $item->id]) }}" id="row-{{ $item->id }}">
              @csrf @method('PUT')
              <input class="w-lg" name="name" value="{{ $item->name }}" required maxlength="100" aria-label="Name">
              <input class="w-md" name="slug" value="{{ $item->slug }}" maxlength="100" aria-label="Slug">
              <input class="w-sm" type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" aria-label="Sort order">
              <label class="check"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Visible</label>
              <button class="btn sm" type="submit">Save</button>
            </form>
          </td>
          <td class="num">
            @if ($type === 'brands')<a class="link" href="{{ route('admin.models.index', ['brand' => $item->id]) }}">{{ $item->laptop_models_count }}</a>@else{{ $item->products_count }}@endif
          </td>
          <td class="num">
            <form method="post" action="{{ route('admin.catalog.destroy', [$type, $item->id]) }}" data-confirm="Delete “{{ $item->name }}”?">
              @csrf @method('DELETE')
              <button class="btn sm danger" type="submit" aria-label="Delete {{ $item->name }}"><svg class="icon"><use href="#icon-trash"/></svg></button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="empty-row">Nothing here yet.</td></tr>
      @endforelse
    </tbody>
  </table></div>
@endsection
