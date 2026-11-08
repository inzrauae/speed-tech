@extends('admin.layout', ['title' => 'Enquiries & repairs'])

@section('content')
  <form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Reference, name, phone or device…">
    <select name="type" data-autosubmit><option value="">All types</option>@foreach (\App\Models\Enquiry::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select>
    <select name="status" data-autosubmit><option value="">All statuses</option>@foreach (\App\Models\Enquiry::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-wrap"><table class="data">
    <thead><tr><th>Reference</th><th>Type</th><th>Customer</th><th>About</th><th>Status</th></tr></thead>
    <tbody>
      @forelse ($enquiries as $enquiry)
        <tr>
          <td><a class="link" href="{{ route('admin.enquiries.show', $enquiry) }}">{{ $enquiry->reference }}</a><small>{{ $enquiry->created_at->format('d M Y, H:i') }}</small></td>
          <td>{{ $enquiry->typeLabel() }}</td>
          <td>{{ $enquiry->name }}<small>{{ $enquiry->phone }}</small></td>
          <td>{{ Str::limit($enquiry->product?->name ?? $enquiry->device ?? collect($enquiry->details)->only(['brand', 'model', 'part'])->filter()->implode(' / ') ?: $enquiry->message, 60) }}<small>{{ $enquiry->service }}</small></td>
          <td><span class="badge {{ $enquiry->status }}">{{ \App\Models\Enquiry::STATUSES[$enquiry->status] ?? $enquiry->status }}</span></td>
        </tr>
      @empty
        <tr><td colspan="5" class="empty-row">No enquiries found.</td></tr>
      @endforelse
    </tbody>
  </table></div>
  {{ $enquiries->links() }}
@endsection
