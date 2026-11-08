@extends('admin.layout', ['title' => 'Laptop models & years'])

@section('actions')
  <a class="btn primary" href="{{ route('admin.models.create', ['brand' => request('brand')]) }}"><svg class="icon"><use href="#icon-plus"/></svg>Add model</a>
@endsection

@section('content')
  <p class="help">Steps 2 and 3 of the spare-part finder. Each model has one or more years / generations (e.g. "2022–2024" or "11th Gen"); parts are linked to those.</p>
  <form class="filters" method="get">
    <select name="brand" data-autosubmit><option value="">All brands</option>@foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected(request('brand') == $brand->id)>{{ $brand->name }}</option>@endforeach</select>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search model name…">
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-wrap"><table class="data">
    <thead><tr><th>Brand</th><th>Model</th><th class="num">Years / generations</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($models as $model)
        <tr>
          <td>{{ $model->brand?->name }}</td>
          <td><a class="link" href="{{ route('admin.models.edit', $model) }}">{{ $model->name }}</a></td>
          <td class="num">{{ $model->generations_count }}</td>
          <td>@if ($model->is_active)<span class="badge in">Visible</span>@else<span class="badge muted">Hidden</span>@endif</td>
        </tr>
      @empty
        <tr><td colspan="4" class="empty-row">No models found.</td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $models->links() }}
@endsection
