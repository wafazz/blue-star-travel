<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blue Travel — Provider Portal</title>
@include('partials.favicon')
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-body">
<div class="container-fluid">
  <div class="row vh-100">

    <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-between text-white p-5 bg-brand-gradient">
      <div class="d-flex align-items-center gap-2">
        <span class="bg-white rounded-4 d-inline-flex p-3"><img src="{{ asset('images/logo.png') }}" alt="Blue Star Travel &amp; Tours" style="height:118px;width:auto;display:block"></span>
        <span class="fs-5 fw-bold">Providers</span>
      </div>
      <div>
        <h1 class="display-6 fw-bold mb-3">Service Provider<br>Portal</h1>
        <p class="opacity-75 fs-5 mb-0" style="max-width:420px">
          Confirm bookings, update availability and upload confirmation documents.
        </p>
      </div>
      <div class="opacity-75 small">Hotels · Airlines · Transport · Tour Guides · Attractions</div>
    </div>

    <div class="col-lg-6 d-flex align-items-center justify-content-center p-4">
      <div class="w-100" style="max-width:400px">
        <div class="text-center mb-4 d-lg-none">
          <span class="fs-1">🤝</span>
          <h4 class="fw-bold mt-2 mb-0">Provider Portal</h4>
        </div>
        <span class="badge text-bg-primary bg-opacity-10 text-primary mb-2">🔒 Provider Access</span>
        <h3 class="fw-bold mb-1">Provider sign in</h3>
        <p class="text-secondary mb-4">Manage your bookings &amp; availability.</p>

        @if ($errors->any())
          <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('provider.login') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-semibold small">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" placeholder="provider@bluetravel.com" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Password</label>
            <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" autocomplete="current-password" required>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remember" id="remember" checked>
              <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <a href="#" class="small text-decoration-none">Forgot password?</a>
          </div>
          <button type="submit" class="btn btn-brand btn-lg w-100">Sign In →</button>
        </form>

        <p class="text-center text-secondary small mt-4 mb-0">Demo: provider@bluetravel.com / password</p>
      </div>
    </div>

  </div>
</div>
</body>
</html>
