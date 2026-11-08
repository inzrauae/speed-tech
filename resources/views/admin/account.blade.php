@extends('admin.layout', ['title' => 'My account'])

@section('content')
  <form class="card" method="post" action="{{ route('admin.account.update') }}" style="max-width:520px">
    @csrf @method('PUT')
    <h2>Change password <small>{{ auth()->user()->email }}</small></h2>
    <div class="form-grid" style="grid-template-columns:1fr">
      <label class="field">Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
      <label class="field">New password <small>(at least 10 characters)</small><input type="password" name="password" required autocomplete="new-password"></label>
      <label class="field">Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    </div>
    <div class="form-actions" style="margin-top:16px"><button class="btn primary" type="submit">Update password</button></div>
  </form>
@endsection
