@extends('admin.layout', [
  'title' => $enquiry->typeLabel().' '.$enquiry->reference,
  'breadcrumb' => '<a href="'.route('admin.enquiries.index').'">Enquiries</a> /',
])

@section('actions')
  <a class="btn" href="tel:{{ $enquiry->phone }}"><svg class="icon"><use href="#icon-phone"/></svg>Call</a>
  <a class="btn" href="https://wa.me/{{ preg_replace('/^0/', '94', preg_replace('/\D/', '', $enquiry->phone)) }}?text={{ rawurlencode("Hi {$enquiry->name}, this is SpeedTech about your enquiry {$enquiry->reference}.") }}" target="_blank" rel="noopener"><svg class="icon"><use href="#icon-whatsapp"/></svg>WhatsApp</a>
@endsection

@section('content')
  <div class="layout-form">
    <section class="card">
      <h2>Details</h2>
      <dl class="dl">
        <dt>Received</dt><dd>{{ $enquiry->created_at->format('d M Y, H:i') }}</dd>
        <dt>Name</dt><dd>{{ $enquiry->name }}</dd>
        <dt>Phone</dt><dd><a class="link" href="tel:{{ $enquiry->phone }}">{{ $enquiry->phone }}</a></dd>
        @if ($enquiry->email)<dt>E-mail</dt><dd>{{ $enquiry->email }}</dd>@endif
        @if ($enquiry->device)<dt>Device</dt><dd>{{ $enquiry->device }}</dd>@endif
        @if ($enquiry->service)<dt>Service</dt><dd>{{ $enquiry->service }}</dd>@endif
        @if ($enquiry->product)<dt>Product</dt><dd><a class="link" href="{{ route('admin.products.edit', $enquiry->product) }}">{{ $enquiry->product->name }}</a></dd>@endif
        @foreach ($enquiry->details ?? [] as $key => $value)
          <dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt><dd>{{ $value }}</dd>
        @endforeach
      </dl>
      @if ($enquiry->message)<h3 style="font-size:13px;margin:18px 0 8px">Message</h3><div class="message-box">{{ $enquiry->message }}</div>@endif
    </section>

    <form class="card" method="post" action="{{ route('admin.enquiries.update', $enquiry) }}">
      @csrf @method('PATCH')
      <h2>Follow-up</h2>
      <label class="field">Status
        <select name="status">@foreach (\App\Models\Enquiry::STATUSES as $key => $label)<option value="{{ $key }}" @selected($enquiry->status === $key)>{{ $label }}</option>@endforeach</select>
      </label>
      <label class="field" style="margin-top:12px">Internal notes<textarea name="admin_notes" rows="6" maxlength="5000">{{ $enquiry->admin_notes }}</textarea></label>
      <div class="form-actions" style="margin-top:14px"><button class="btn primary" type="submit">Save</button></div>
    </form>
  </div>
@endsection
