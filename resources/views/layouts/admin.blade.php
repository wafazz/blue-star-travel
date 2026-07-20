<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Console') — Blue Travel</title>
@include('partials.favicon')
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
<style>
  .bt-sidebar{width:260px;min-height:100vh;background:linear-gradient(180deg,#0d1b3e,#0b2a6b);color:#cdd7ef}
  .bt-sidebar .brand{color:#fff}
  .bt-sidebar a{color:#cdd7ef;border-radius:.6rem;font-weight:600;font-size:.9rem}
  .bt-sidebar a:hover{background:rgba(255,255,255,.08);color:#fff}
  .bt-sidebar a.active{background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff}
  .bt-main{flex:1;min-width:0;background:#eef2fb}
  .bt-topbar{background:#fff;border-bottom:1px solid #e7ecf7}
  @media (max-width: 991.98px){ .bt-sidebar{position:fixed;z-index:1040;left:-260px;transition:.2s} .bt-sidebar.open{left:0} }
</style>
</head>
<body>
<div class="d-flex">

  <!-- Sidebar -->
  <aside class="bt-sidebar p-3 d-flex flex-column" id="sidebar">
    <div class="brand d-flex align-items-center gap-2 px-2 py-2 mb-3">
      <span class="bg-white rounded-3 d-inline-flex p-1"><img src="{{ asset('images/logo-icon.png') }}" alt="Blue Star Travel &amp; Tours" style="width:34px;height:34px;display:block"></span>
      <div>
        <div class="fw-bold lh-1">Blue Star</div>
        <small class="opacity-75">@yield('console', 'Console')</small>
      </div>
    </div>
    <nav class="nav flex-column gap-1 overflow-auto">
      @hasSection('nav')
        @yield('nav')
      @else
        @include('layouts.partials.staff-nav')
      @endif
    </nav>
    <div class="mt-auto pt-3 border-top border-light border-opacity-10">
      <form method="POST" action="{{ route('staff.logout') }}">
        @csrf
        <button class="btn btn-sm w-100 text-start text-white-50">🚪 Log out</button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <div class="bt-main d-flex flex-column">
    <header class="bt-topbar d-flex align-items-center justify-content-between px-3 px-lg-4 py-3">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-light d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
        <h5 class="mb-0 fw-bold">@yield('heading', 'Dashboard')</h5>
      </div>
      <div class="d-flex align-items-center gap-3">
        @php $unread = auth()->user()->unreadNotificationsCount(); @endphp
        <a href="{{ route('notifications.index') }}" class="position-relative text-decoration-none fs-5" title="Notifications">
          🔔@if ($unread)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" style="font-size:.6rem">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
        </a>
        <div class="d-flex align-items-center gap-2">
          <span class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:38px;height:38px">{{ auth()->user()->initials() }}</span>
          <div class="d-none d-sm-block lh-1">
            <div class="fw-semibold small">{{ auth()->user()->name }}</div>
            <small class="text-secondary text-capitalize">{{ str_replace('_',' ', auth()->user()->role) }}</small>
          </div>
        </div>
      </div>
    </header>

    <main class="p-3 p-lg-4">
      @if (session('ok'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          ✅ {{ session('ok') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <strong>Please fix the following:</strong>
          <ul class="mb-0 mt-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif
      @yield('content')
    </main>
  </div>

</div>
</body>
</html>
