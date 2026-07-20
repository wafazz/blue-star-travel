<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blue Travel — Travel &amp; Tour Management</title>
@include('partials.favicon')
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="text-white" style="min-height:100vh;background:linear-gradient(160deg,#1466ff 0%,#0b3fd1 55%,#082aa0 100%)">
<div class="container py-5">

  <nav class="d-flex align-items-center justify-content-between mb-5">
    <a href="{{ route('home') }}" class="d-inline-flex bg-white rounded-3 p-2 text-decoration-none"><img src="{{ asset('images/logo.png') }}" alt="Blue Star Travel &amp; Tours" style="height:74px;width:auto;display:block"></a>
    <a href="{{ route('login') }}" class="btn btn-light btn-sm fw-semibold">Customer Sign In</a>
  </nav>

  <div class="row align-items-center g-5 py-4">
    <div class="col-lg-6">
      <span class="badge bg-white bg-opacity-25 mb-3">Travel &amp; Tour Management System</span>
      <h1 class="display-4 fw-bold mb-3">Travel, sold smarter.</h1>
      <p class="fs-5 opacity-75 mb-4" style="max-width:520px">
        One elegant platform connecting HQ, admins, agents, providers and customers —
        packages, bookings, payments, commissions and a gamified agent experience.
      </p>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('catalog.index') }}" class="btn btn-light btn-lg fw-semibold">🗺️ Browse Packages</a>
        <a href="{{ route('agent.login') }}" class="btn btn-outline-light btn-lg fw-semibold">Agent Portal →</a>
        <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg fw-semibold">Customer Login</a>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card text-body p-4">
        <h6 class="fw-bold text-secondary mb-3">Sign in to your portal</h6>
        <div class="d-flex flex-column gap-2">
          <a href="{{ route('login') }}" class="btn btn-light text-start d-flex justify-content-between align-items-center"><span>🌏 Customer Portal</span><span>›</span></a>
          <a href="{{ route('agent.login') }}" class="btn btn-light text-start d-flex justify-content-between align-items-center"><span>✈️ Agent Portal</span><span>›</span></a>
          <a href="{{ route('provider.login') }}" class="btn btn-light text-start d-flex justify-content-between align-items-center"><span>🤝 Provider Portal</span><span>›</span></a>
          <a href="{{ route('admin.login') }}" class="btn btn-brand text-start d-flex justify-content-between align-items-center"><span>🔒 HQ / Admin Console</span><span>›</span></a>
        </div>
      </div>
    </div>
  </div>

  <div class="text-center opacity-75 small mt-5">Blue Star Travel And Tours Sdn Bhd · Powered by CodexLure Technology</div>
</div>
</body>
</html>
