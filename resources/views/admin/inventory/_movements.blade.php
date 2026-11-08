{{-- $movements, $showProduct --}}
@php($orderIds = \App\Models\Order::whereIn('number', $movements->pluck('reference')->filter()->values())->pluck('id', 'number'))
<div class="table-wrap"><table class="data">
  <thead><tr><th>When</th>@if ($showProduct)<th>Product</th>@endif<th>Movement</th><th class="num">Change</th><th class="num">Balance</th><th>Reference / note</th><th>By</th></tr></thead>
  <tbody>
    @forelse ($movements as $m)
      <tr>
        <td>{{ $m->created_at->format('d M Y') }}<small>{{ $m->created_at->format('H:i') }}</small></td>
        @if ($showProduct)
          <td>@if ($m->product)<a class="link" href="{{ route('admin.inventory.movements', ['product' => $m->product_id]) }}">{{ $m->product->name }}</a>@else<span class="badge muted">deleted</span>@endif</td>
        @endif
        <td>{{ $m->typeLabel() }}</td>
        <td class="num"><span class="badge {{ $m->quantity > 0 ? 'in' : 'out' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</span></td>
        <td class="num">{{ $m->balance_after }}</td>
        <td>
          @if ($m->reference && isset($orderIds[$m->reference]))
            <a class="link" href="{{ route('admin.orders.show', $orderIds[$m->reference]) }}">{{ $m->reference }}</a>
          @else
            {{ $m->reference }}
          @endif
          <small>{{ $m->note }}@if ($m->unit_cost !== null) · cost {{ money($m->unit_cost) }}@endif</small>
        </td>
        <td>{{ $m->user?->name ?? ($m->type === 'sale' ? 'Online order' : '—') }}</td>
      </tr>
    @empty
      <tr><td colspan="7" class="empty-row">No stock movements yet.</td></tr>
    @endforelse
  </tbody>
</table></div>
