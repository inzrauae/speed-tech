<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign in | SpeedTech Admin</title>
  <link rel="icon" href="{{ asset('assets/favicon.png') }}" type="image/png">
  <link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ filemtime(public_path('admin.css')) }}">
</head>
<body class="login-page">
  <form class="login-card" method="post" action="{{ route('admin.login') }}">
    @csrf
    <img src="{{ asset('assets/logo.png') }}" alt="SpeedTech IT Solutions" width="681" height="284">
    <h1>Admin sign in</h1>
    @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <label class="field">E-mail<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
    <label class="field">Password<input type="password" name="password" required autocomplete="current-password"></label>
    <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    <button class="btn primary" type="submit">Sign in</button>
  </form>
</body>
</html>
