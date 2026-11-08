@extends('admin.layout', ['title' => 'Payments & delivery'])

@section('content')
  @php($v = fn ($key) => old($key, $settings[$key] ?? ''))
  <form method="post" action="{{ route('admin.settings.update') }}" class="settings-form">
    @csrf @method('PUT')

    <section class="card">
      <h2>Payment methods <small>shown to customers at checkout</small></h2>
      <div class="pay-toggles">
        <label class="pay-toggle">
          <input type="hidden" name="payment_cod_enabled" value="0">
          <input type="checkbox" name="payment_cod_enabled" value="1" @checked($v('payment_cod_enabled'))>
          <span><b>Cash on delivery (COD)</b><small>Customer pays the courier in cash. Delivery orders only.</small></span>
        </label>
        <label class="pay-toggle">
          <input type="hidden" name="payment_bank_transfer_enabled" value="0">
          <input type="checkbox" name="payment_bank_transfer_enabled" value="1" @checked($v('payment_bank_transfer_enabled'))>
          <span><b>Bank transfer</b><small>Customer transfers to your account and can upload the slip. Delivery or pickup.</small></span>
        </label>
        <label class="pay-toggle">
          <input type="hidden" name="payment_pay_at_store_enabled" value="0">
          <input type="checkbox" name="payment_pay_at_store_enabled" value="1" @checked($v('payment_pay_at_store_enabled'))>
          <span><b>Pay at the store</b><small>Cash or card when collecting in Kaduwela. Pickup orders only.</small></span>
        </label>
      </div>
      <label class="field" style="margin-top:16px;max-width:320px">Cash on delivery limit (Rs.) <small>— hide COD for orders above this; 0 = no limit</small>
        <input type="number" name="cod_max_order" value="{{ $v('cod_max_order') }}" min="0" step="1">
      </label>
    </section>

    <section class="card">
      <h2>Bank account for transfers <small>shown at checkout and on the order confirmation</small></h2>
      <div class="form-grid">
        <label class="field">Bank name<input name="bank_name" value="{{ $v('bank_name') }}" maxlength="100" placeholder="e.g. Commercial Bank"></label>
        <label class="field">Branch<input name="bank_branch" value="{{ $v('bank_branch') }}" maxlength="100" placeholder="e.g. Kaduwela"></label>
        <label class="field">Account name<input name="bank_account_name" value="{{ $v('bank_account_name') }}" maxlength="100"></label>
        <label class="field">Account number<input name="bank_account_number" value="{{ $v('bank_account_number') }}" maxlength="50"></label>
        <label class="field span-2">Instructions for the customer<textarea name="bank_instructions" maxlength="500" rows="2">{{ $v('bank_instructions') }}</textarea></label>
      </div>
    </section>

    <section class="card">
      <h2>Delivery charges</h2>
      <div class="form-grid">
        <label class="field">Delivery fee (Rs.)<input type="number" name="delivery_fee" value="{{ $v('delivery_fee') }}" min="0" step="1" required></label>
        <label class="field">Free delivery for orders over (Rs.) <small>0 = never free</small><input type="number" name="free_delivery_over" value="{{ $v('free_delivery_over') }}" min="0" step="1"></label>
      </div>
    </section>

    <div class="form-actions"><button class="btn primary" type="submit">Save settings</button></div>
  </form>
@endsection
