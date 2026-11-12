@extends('admin.layout', ['title' => 'Pending approvals'])

@php($total = $brands->count() + $models->count() + $years->count() + $partTypes->count() + $categories->count())

@section('actions')
  @if ($total)
    <form method="post" action="{{ route('admin.approvals.approve-all') }}" data-confirm="Approve all {{ $total }} suggestions and add them to the store?">
      @csrf<button class="btn primary" type="submit"><svg class="icon"><use href="#icon-check"/></svg>Approve all</button>
    </form>
  @endif
@endsection

@section('content')
  <p class="help">When the AI product writer needs a brand, laptop model, year, part type or shop category that doesn't exist yet, it suggests one here instead of adding it to the store. Suggestions stay hidden from customers until you approve them. You can correct the name before approving, use an existing entry instead, or reject the suggestion. Draft products that use a suggestion can't be published until it's approved or replaced.</p>

  @if (! $total)
    <section class="card"><p class="empty-row">Nothing waiting for approval.</p></section>
  @endif

  @php($groups = [
    ['brands', 'New laptop brands', $brands, 'name'],
    ['models', 'New laptop models', $models, 'name'],
    ['years', 'New years / generations', $years, 'label'],
    ['part-types', 'New part types', $partTypes, 'name'],
    ['categories', 'New shop categories', $categories, 'name'],
  ])

  @foreach ($groups as [$type, $heading, $items, $field])
    @continue($items->isEmpty())
    <section class="card approval-group">
      <h2>{{ $heading }} <small>({{ $items->count() }})</small></h2>
      @foreach ($items as $item)
        @php($formId = "approve-{$type}-{$item->id}")
        <div class="approval-row">
          <div>
            <form class="inline-form" method="post" action="{{ route('admin.approvals.approve', [$type, $item->id]) }}" id="{{ $formId }}">
              @csrf
              <input class="w-lg" name="{{ $field }}" value="{{ $item->{$field} }}" required maxlength="100" aria-label="Name">
            </form>
            <div class="meta">
              @if ($type === 'brands')
                {{ $item->laptop_models_count }} {{ Str::plural('model', $item->laptop_models_count) }} suggested under it
              @elseif ($type === 'models')
                Brand: {{ $item->brand?->name }} @if ($item->brand?->pending_approval)<span class="badge pending">new brand</span>@endif
              @elseif ($type === 'years')
                For {{ trim($item->laptopModel?->brand?->name.' '.$item->laptopModel?->name) }} @if ($item->laptopModel?->pending_approval)<span class="badge pending">new model</span>@endif · used by {{ $item->products_count }} {{ Str::plural('product', $item->products_count) }}
              @else
                @php($users = $drafts->where($type === 'part-types' ? 'part_category_id' : 'category_id', $item->id))
                Used by {{ $item->products_count }} {{ Str::plural('product', $item->products_count) }}@if ($users->isNotEmpty()): @foreach ($users->take(3) as $p)<a class="link" href="{{ route('admin.products.edit', $p) }}">{{ Str::limit($p->name, 40) }}</a>@if (! $loop->last), @endif @endforeach @endif
              @endif
            </div>
          </div>
          <div class="approval-actions">
            <button class="btn sm primary" type="submit" form="{{ $formId }}"><svg class="icon"><use href="#icon-check"/></svg>Approve</button>
            @if (isset($existing[$type]) && $existing[$type]->isNotEmpty())
              <form class="inline-form" method="post" action="{{ route('admin.approvals.replace', [$type, $item->id]) }}" data-confirm="Use the selected existing entry instead of “{{ $item->{$field} }}”?">
                @csrf
                <select name="replace_with" required aria-label="Existing entry to use instead">
                  <option value="">Use existing instead…</option>
                  @foreach ($existing[$type] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
                <button class="btn sm" type="submit">Replace</button>
              </form>
            @endif
            <form method="post" action="{{ route('admin.approvals.reject', [$type, $item->id]) }}" data-confirm="Reject “{{ $item->{$field} }}”? It will be deleted{{ in_array($type, ['brands', 'models']) ? ', with the models and years suggested under it' : '' }}.">
              @csrf @method('DELETE')
              <button class="btn sm danger" type="submit"><svg class="icon"><use href="#icon-trash"/></svg>Reject</button>
            </form>
          </div>
        </div>
      @endforeach
    </section>
  @endforeach
@endsection
