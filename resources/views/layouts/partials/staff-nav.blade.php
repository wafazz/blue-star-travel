@php
  $u = auth()->user();
  $homeRoute = $u->isStaff() && $u->hasRole('admin') && ! $u->hasRole('super_admin') ? 'admin.dashboard' : 'hq.dashboard';
@endphp

<a class="nav-link px-2 py-2 {{ request()->routeIs($homeRoute) ? 'active' : '' }}" href="{{ route($homeRoute) }}">🏠 Dashboard</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Catalog</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.packages.*') ? 'active' : '' }}" href="{{ route('manage.packages.index') }}">🗺️ Packages</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.providers.*') ? 'active' : '' }}" href="{{ route('manage.providers.index') }}">🤝 Providers</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.customers.*') ? 'active' : '' }}" href="{{ route('manage.customers.index') }}">👥 Customers</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Operations</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.bookings.*') ? 'active' : '' }}" href="{{ route('manage.bookings.index') }}">📋 Bookings</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.payments.*') ? 'active' : '' }}" href="{{ route('manage.payments.index') }}">💳 Payments</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Finance</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.finance.dashboard') ? 'active' : '' }}" href="{{ route('manage.finance.dashboard') }}">📈 Finance</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.finance.refunds') ? 'active' : '' }}" href="{{ route('manage.finance.refunds') }}">↩️ Refunds</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Commission</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.commission.index') ? 'active' : '' }}" href="{{ route('manage.commission.index') }}">💰 Commission Ledger</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.commission.levels') ? 'active' : '' }}" href="{{ route('manage.commission.levels') }}">⚙️ Level Config</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.withdrawals.*') ? 'active' : '' }}" href="{{ route('manage.withdrawals.index') }}">🏧 Withdrawals</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.redemptions.*') ? 'active' : '' }}" href="{{ route('manage.redemptions.index') }}">🎁 Redemptions</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Reports</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.reports.*') ? 'active' : '' }}" href="{{ route('manage.reports.index') }}">📊 Reports &amp; Analytics</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Marketing</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.banners.*') ? 'active' : '' }}" href="{{ route('manage.banners.index') }}">🖼️ Banners</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.coupons.*') ? 'active' : '' }}" href="{{ route('manage.coupons.index') }}">🏷️ Coupons</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.materials.*') ? 'active' : '' }}" href="{{ route('manage.materials.index') }}">📢 Materials</a>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.broadcast.*') ? 'active' : '' }}" href="{{ route('manage.broadcast.create') }}">📣 Broadcast</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Support</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.tickets.*') ? 'active' : '' }}" href="{{ route('manage.tickets.index') }}">🎧 Tickets</a>

<div class="text-uppercase small opacity-50 px-2 mt-3 mb-1" style="font-size:.7rem;letter-spacing:.05em">Company</div>
<a class="nav-link px-2 py-2 {{ request()->routeIs('manage.company.*') ? 'active' : '' }}" href="{{ route('manage.company.edit') }}">🏢 Company Profile</a>
@if ($u->hasRole('super_admin', 'hq'))
  <a class="nav-link px-2 py-2 {{ request()->routeIs('manage.payment-gateway.*') ? 'active' : '' }}" href="{{ route('manage.payment-gateway.edit') }}">💳 Payment Gateway</a>
@endif
