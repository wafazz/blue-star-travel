@php
  $u = auth()->user();
  $homeRoute = $u->isStaff() && $u->hasRole('admin') && ! $u->hasRole('super_admin') ? 'admin.dashboard' : 'hq.dashboard';
  $any = fn (...$keys) => collect($keys)->contains(fn ($k) => $u->hasAccess($k));
@endphp

<a class="nav-link px-2 py-2 {{ request()->routeIs($homeRoute) ? 'active' : '' }}" href="{{ route($homeRoute) }}">🏠 Dashboard</a>

@if ($any('packages', 'providers', 'customers', 'agents'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Catalog</div>
  @if ($u->hasAccess('packages'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.packages.*') ? 'active' : '' }}" href="{{ route('manage.packages.index') }}">🗺️ Packages</a>@endif
  @if ($u->hasAccess('providers'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.providers.*') ? 'active' : '' }}" href="{{ route('manage.providers.index') }}">🤝 Providers</a>@endif
  @if ($u->hasAccess('customers'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.customers.*') ? 'active' : '' }}" href="{{ route('manage.customers.index') }}">👥 Customers</a>@endif
  @if ($u->hasAccess('agents'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.agents.*') ? 'active' : '' }}" href="{{ route('manage.agents.index') }}">🧑‍💼 Agents</a>@endif
@endif

@if ($any('bookings', 'payments'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Operations</div>
  @if ($u->hasAccess('bookings'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.bookings.*') ? 'active' : '' }}" href="{{ route('manage.bookings.index') }}">📋 Bookings</a>@endif
  @if ($u->hasAccess('payments'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.payments.*') ? 'active' : '' }}" href="{{ route('manage.payments.index') }}">💳 Payments</a>@endif
@endif

@if ($u->hasAccess('finance'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Finance</div>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.finance.dashboard') ? 'active' : '' }}" href="{{ route('manage.finance.dashboard') }}">📈 Finance</a>
  {{-- Refunds move money out, so the queue is HQ/super-admin only. --}}
  @if ($u->hasRole('super_admin', 'hq'))
    <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.finance.refunds') ? 'active' : '' }}" href="{{ route('manage.finance.refunds') }}">↩️ Refunds</a>
  @endif
@endif

@if ($u->hasAccess('commission'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Commission</div>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.commission.index') ? 'active' : '' }}" href="{{ route('manage.commission.index') }}">💰 Commission Ledger</a>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.commission.levels') ? 'active' : '' }}" href="{{ route('manage.commission.levels') }}">⚙️ Level Config</a>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.withdrawals.*') ? 'active' : '' }}" href="{{ route('manage.withdrawals.index') }}">🏧 Withdrawals</a>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.redemptions.*') ? 'active' : '' }}" href="{{ route('manage.redemptions.index') }}">🎁 Redemptions</a>
@endif

@if ($u->hasAccess('reports'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Reports</div>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.reports.*') ? 'active' : '' }}" href="{{ route('manage.reports.index') }}">📊 Reports &amp; Analytics</a>
@endif

@if ($u->hasAccess('marketing'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Marketing</div>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.banners.*') ? 'active' : '' }}" href="{{ route('manage.banners.index') }}">🖼️ Banners</a>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.coupons.*') ? 'active' : '' }}" href="{{ route('manage.coupons.index') }}">🏷️ Coupons</a>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.materials.*') ? 'active' : '' }}" href="{{ route('manage.materials.index') }}">📢 Materials</a>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.broadcast.*') ? 'active' : '' }}" href="{{ route('manage.broadcast.create') }}">📣 Broadcast</a>
@endif

@if ($u->hasAccess('tickets'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Support</div>
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.tickets.*') ? 'active' : '' }}" href="{{ route('manage.tickets.index') }}">🎧 Tickets</a>
@endif

@if ($u->hasAccess('company') || $u->hasRole('super_admin', 'hq'))
  <div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Company</div>
  @if ($u->hasAccess('company'))<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.company.*') ? 'active' : '' }}" href="{{ route('manage.company.edit') }}">🏢 Company Profile</a>@endif
  @if ($u->hasRole('super_admin', 'hq'))
    <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.payment-gateway.*') ? 'active' : '' }}" href="{{ route('manage.payment-gateway.edit') }}">💳 Payment Gateway</a>
    <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.staff.*') ? 'active' : '' }}" href="{{ route('manage.staff.index') }}">🔐 Staff &amp; Access</a>
  @endif
@endif
