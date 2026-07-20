@php
  $catArt = [
    'domestic'      => ['🏝️', '#0ea5e9', '#0369a1'],
    'international' => ['🗺️', '#6366f1', '#3730a3'],
    'umrah'         => ['🕋', '#0f766e', '#064e3b'],
    'cruise'        => ['🛳️', '#0891b2', '#155e75'],
    'free_easy'     => ['🎒', '#f59e0b', '#b45309'],
    'custom'        => ['✨', '#8b5cf6', '#5b21b6'],
  ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $company?->name ?? 'Blue Star Travel And Tours' }} — Travel &amp; Tour Specialists</title>
<meta name="description" content="Umrah, cruise, international and domestic tour packages by {{ $company?->name ?? 'Blue Star Travel And Tours' }}. Book online, pay securely, travel beautifully.">
<meta name="theme-color" content="#0b3fd1">
@include('partials.favicon')
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
<style>
  html { scroll-behavior: smooth; }
  body { background: #fff; overflow-x: hidden; }
  .lp-nav {
    position: fixed; inset: 0 0 auto 0; z-index: 1030; padding: 18px 0;
    transition: padding .3s ease, background .3s ease, box-shadow .3s ease;
  }
  .lp-nav.stuck { padding: 10px 0; background: rgba(255,255,255,.92); backdrop-filter: blur(14px); box-shadow: 0 6px 24px rgba(8,42,160,.10); }
  .lp-nav .lp-link { color: rgba(255,255,255,.86); font-weight: 600; font-size: .93rem; padding: .4rem .8rem; border-radius: 8px; }
  .lp-nav .lp-link:hover { color: #fff; background: rgba(255,255,255,.14); }
  .lp-nav.stuck .lp-link { color: #4a5878; }
  .lp-nav.stuck .lp-link:hover { color: var(--bt-blue); background: rgba(20,102,255,.08); }
  .lp-nav .lp-plate { background: #fff; border-radius: 12px; padding: 6px 10px; box-shadow: 0 8px 20px rgba(8,42,160,.16); }
  .lp-nav .lp-ghost { border: 1.5px solid rgba(255,255,255,.55); color: #fff; font-weight: 700; }
  .lp-nav .lp-ghost:hover { background: #fff; color: var(--bt-blue-2); }
  .lp-nav.stuck .lp-ghost { border-color: rgba(20,102,255,.35); color: var(--bt-blue); }
  .lp-nav.stuck .lp-ghost:hover { background: var(--bt-blue); color: #fff; }

  .lp-hero {
    position: relative; overflow: hidden; color: #fff;
    background: linear-gradient(155deg, #1466ff 0%, #0b3fd1 52%, #061f7a 100%);
    padding: 168px 0 120px;
  }
  .lp-hero::before, .lp-hero::after {
    content: ''; position: absolute; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle at 30% 30%, rgba(56,189,248,.55), transparent 62%);
  }
  .lp-hero::before { width: 620px; height: 620px; top: -220px; right: -140px; animation: float 16s ease-in-out infinite; }
  .lp-hero::after  { width: 460px; height: 460px; bottom: -220px; left: -120px; opacity: .5; animation: float 21s ease-in-out infinite reverse; }
  @keyframes float { 50% { transform: translate3d(0, 34px, 0) scale(1.06); } }
  .lp-hero .container { position: relative; z-index: 2; }
  .lp-eyebrow {
    display: inline-flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 700;
    letter-spacing: .09em; text-transform: uppercase; padding: .45rem .95rem; border-radius: 999px;
    background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24);
  }
  .lp-title { font-size: clamp(2.4rem, 5.4vw, 4.1rem); font-weight: 800; line-height: 1.06; letter-spacing: -.02em; }
  .lp-title em { font-style: normal; background: linear-gradient(100deg, #a5f3fc, #7dd3fc 55%, #fff); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .lp-lead { font-size: 1.12rem; color: rgba(255,255,255,.82); max-width: 540px; }

  .lp-search {
    background: rgba(255,255,255,.96); border-radius: 18px; padding: 10px;
    box-shadow: 0 24px 60px rgba(4,20,80,.34); display: flex; gap: 8px; flex-wrap: wrap;
  }
  .lp-search input, .lp-search select {
    flex: 1 1 190px; min-width: 0; border: 0; background: #f2f5fd; border-radius: 12px;
    padding: .85rem 1rem; font-size: .95rem; color: var(--bt-ink); outline: none;
  }
  .lp-search input:focus, .lp-search select:focus { box-shadow: 0 0 0 3px rgba(20,102,255,.22); }
  .lp-search button { flex: 0 0 auto; border-radius: 12px; padding: .85rem 1.7rem; }

  .lp-glass {
    background: rgba(255,255,255,.11); border: 1px solid rgba(255,255,255,.2);
    border-radius: 22px; backdrop-filter: blur(12px); padding: 26px;
  }
  .lp-portal { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: .8rem 1rem; border-radius: 14px; background: rgba(255,255,255,.1); color: #fff; font-weight: 600; transition: transform .2s ease, background .2s ease; }
  .lp-portal:hover { background: #fff; color: var(--bt-blue-2); transform: translateX(6px); }

  .lp-stats { margin-top: -46px; position: relative; z-index: 3; }
  .lp-stat { background: #fff; border-radius: 18px; padding: 24px 18px; text-align: center; box-shadow: 0 18px 44px rgba(16,42,110,.12); height: 100%; }
  .lp-stat b { display: block; font-size: 2rem; font-weight: 800; color: var(--bt-blue); line-height: 1; }
  .lp-stat span { font-size: .8rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--bt-muted); }

  .lp-sec { padding: 84px 0; }
  .lp-kicker { font-size: .76rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--bt-blue); }
  .lp-h2 { font-size: clamp(1.7rem, 3.2vw, 2.5rem); font-weight: 800; letter-spacing: -.02em; }

  .lp-chip { display: inline-flex; align-items: center; gap: .5rem; padding: .55rem 1.05rem; border-radius: 999px; background: #eef2fb; color: #3b4a70; font-weight: 700; font-size: .9rem; transition: .2s ease; }
  .lp-chip b { font-size: .74rem; background: #fff; color: var(--bt-blue); padding: .1rem .45rem; border-radius: 999px; }
  .lp-chip:hover { background: var(--bt-blue); color: #fff; transform: translateY(-2px); box-shadow: 0 10px 22px rgba(20,102,255,.3); }
  .lp-chip:hover b { background: rgba(255,255,255,.22); color: #fff; }

  .lp-card { display: block; border-radius: 20px; overflow: hidden; background: #fff; box-shadow: 0 14px 40px rgba(16,42,110,.10); height: 100%; transition: transform .3s ease, box-shadow .3s ease; }
  .lp-card:hover { transform: translateY(-8px); box-shadow: 0 26px 60px rgba(16,42,110,.20); }
  .lp-card .art { position: relative; height: 190px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-size: 3.2rem; }
  .lp-card .art::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.42)); }
  .lp-card .tag { position: absolute; top: 12px; left: 12px; z-index: 2; background: rgba(255,255,255,.94); color: var(--bt-blue-2); font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: .3rem .65rem; border-radius: 999px; }
  .lp-card .hot { position: absolute; top: 12px; right: 12px; z-index: 2; background: linear-gradient(135deg,#f79009,#f04438); color: #fff; font-size: .7rem; font-weight: 800; padding: .3rem .6rem; border-radius: 999px; }
  .lp-card .bd { padding: 18px 18px 20px; }
  .lp-card h5 { font-size: 1.05rem; font-weight: 800; color: var(--bt-ink); margin-bottom: .35rem; }
  .lp-card .meta { font-size: .84rem; color: var(--bt-muted); }
  .lp-card .price { font-size: 1.2rem; font-weight: 800; color: var(--bt-blue); }
  .lp-card .price small { font-size: .72rem; font-weight: 600; color: var(--bt-muted); }
  .lp-card .go { font-size: .82rem; font-weight: 700; color: var(--bt-blue); }

  .lp-dest { position: relative; display: flex; align-items: flex-end; border-radius: 18px; overflow: hidden; height: 150px; color: #fff; padding: 16px; font-weight: 700; transition: transform .3s ease; }
  .lp-dest:hover { transform: scale(1.03); color: #fff; }
  .lp-dest span { position: relative; z-index: 2; text-shadow: 0 2px 10px rgba(0,0,0,.4); }
  .lp-dest::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.05), rgba(0,0,0,.45)); }

  .lp-feature { padding: 26px; border-radius: 20px; background: #fff; box-shadow: 0 12px 34px rgba(16,42,110,.09); height: 100%; }
  .lp-feature .ic { width: 52px; height: 52px; border-radius: 15px; display: grid; place-items: center; font-size: 1.5rem; background: linear-gradient(135deg, rgba(20,102,255,.14), rgba(56,189,248,.2)); margin-bottom: 14px; }

  .lp-step { position: relative; padding-left: 62px; }
  .lp-step .no { position: absolute; left: 0; top: 0; width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; font-weight: 800; color: #fff; background: linear-gradient(135deg,#1466ff,#0b3fd1); box-shadow: 0 10px 22px rgba(20,102,255,.32); }

  .lp-dep { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px; background: #fff; box-shadow: 0 10px 28px rgba(16,42,110,.09); transition: .25s ease; }
  .lp-dep:hover { transform: translateX(5px); box-shadow: 0 16px 36px rgba(16,42,110,.16); }
  .lp-dep .d { flex: 0 0 62px; text-align: center; border-radius: 12px; padding: 8px 0; background: linear-gradient(135deg,#eef2fb,#dbe6ff); color: var(--bt-blue-2); }
  .lp-dep .d b { display: block; font-size: 1.2rem; font-weight: 800; line-height: 1; }
  .lp-dep .d span { font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

  .lp-cta { border-radius: 28px; padding: 56px 40px; color: #fff; position: relative; overflow: hidden; background: linear-gradient(135deg, #1466ff, #0b3fd1 55%, #061f7a); box-shadow: 0 30px 70px rgba(8,42,160,.32); }
  .lp-cta::after { content: '✈'; position: absolute; right: -10px; bottom: -40px; font-size: 15rem; opacity: .08; line-height: 1; }

  .lp-foot { background: #071540; color: rgba(255,255,255,.68); padding: 60px 0 28px; }
  .lp-foot a { color: rgba(255,255,255,.68); font-size: .92rem; }
  .lp-foot a:hover { color: #fff; }
  .lp-foot h6 { color: #fff; font-size: .78rem; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1rem; }

  .reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s cubic-bezier(.2,.7,.3,1), transform .7s cubic-bezier(.2,.7,.3,1); }
  .reveal.in { opacity: 1; transform: none; }
  @media (prefers-reduced-motion: reduce) { .reveal { opacity: 1; transform: none; transition: none; } .lp-hero::before, .lp-hero::after { animation: none; } }
  @media (max-width: 991px) { .lp-hero { padding: 132px 0 96px; } .lp-sec { padding: 60px 0; } }
</style>
</head>
<body>

{{-- ── Nav ─────────────────────────────────────────────── --}}
<nav class="lp-nav" id="lpNav">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="{{ route('home') }}" class="lp-plate d-inline-flex"><img src="{{ asset('images/logo.png') }}" alt="{{ $company?->name ?? 'Blue Star Travel And Tours' }}" style="height:42px;width:auto;display:block"></a>
    <div class="d-none d-lg-flex align-items-center gap-1">
      <a href="#packages" class="lp-link">Packages</a>
      <a href="#destinations" class="lp-link">Destinations</a>
      <a href="#how" class="lp-link">How It Works</a>
      <a href="#agents" class="lp-link">Become an Agent</a>
      <a href="#contact" class="lp-link">Contact</a>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('login') }}" class="btn btn-sm lp-ghost px-3">Sign In</a>
      <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-light fw-bold px-3 d-none d-sm-inline-flex">Book Now</a>
    </div>
  </div>
</nav>

{{-- ── Hero ────────────────────────────────────────────── --}}
<header class="lp-hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="lp-eyebrow">✦ Licensed Travel &amp; Tour Specialists</span>
        <h1 class="lp-title mt-4 mb-3">Your next journey,<br><em>beautifully arranged.</em></h1>
        <p class="lp-lead mb-4">
          Umrah, cruises, international escapes and local getaways — curated by
          {{ $company?->name ?? 'Blue Star Travel And Tours' }}, booked online in minutes and
          supported by real people from deposit to departure.
        </p>

        <form class="lp-search mb-4" method="GET" action="{{ route('catalog.index') }}">
          <input type="text" name="q" placeholder="Where would you like to go?" aria-label="Search destination">
          <select name="category" aria-label="Package type">
            <option value="">All package types</option>
            @foreach ($categories as $c)
              <option value="{{ $c['key'] }}">{{ $c['label'] }}</option>
            @endforeach
          </select>
          <button class="btn btn-brand" type="submit">Search</button>
        </form>

        <div class="d-flex flex-wrap gap-4 small" style="color:rgba(255,255,255,.78)">
          <span>✓ Secure FPX &amp; online banking</span>
          <span>✓ Instant e-invoice &amp; voucher</span>
          <span>✓ Trusted local &amp; overseas partners</span>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="lp-glass">
          <div class="fw-bold mb-1">Portal access</div>
          <p class="small mb-3" style="color:rgba(255,255,255,.7)">Sign in to the workspace that belongs to you.</p>
          <div class="d-flex flex-column gap-2">
            <a href="{{ route('login') }}" class="lp-portal"><span>🌏 Customer Portal</span><span>›</span></a>
            <a href="{{ route('agent.login') }}" class="lp-portal"><span>✈️ Agent Portal</span><span>›</span></a>
            <a href="{{ route('provider.login') }}" class="lp-portal"><span>🤝 Provider Portal</span><span>›</span></a>
            <a href="{{ route('admin.login') }}" class="lp-portal"><span>🔒 HQ / Admin Console</span><span>›</span></a>
          </div>
          <hr style="border-color:rgba(255,255,255,.2)">
          <div class="small" style="color:rgba(255,255,255,.72)">
            New here? <a href="{{ route('register') }}" class="text-white fw-bold text-decoration-underline">Create a customer account</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

{{-- ── Stats ───────────────────────────────────────────── --}}
<section class="lp-stats">
  <div class="container">
    <div class="row g-3">
      @foreach ([
        ['📦', $stats['packages'], 'Tour Packages'],
        ['📍', $stats['destinations'], 'Destinations'],
        ['🧳', $stats['travellers'], 'Happy Travellers'],
        ['🤝', $stats['providers'], 'Trusted Partners'],
      ] as $s)
        <div class="col-6 col-lg-3 reveal">
          <div class="lp-stat">
            <div class="mb-2" style="font-size:1.4rem">{{ $s[0] }}</div>
            <b data-count="{{ $s[1] }}">0</b>
            <span>{{ $s[2] }}</span>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ── Categories + featured packages ──────────────────── --}}
<section class="lp-sec" id="packages">
  <div class="container">
    <div class="row align-items-end g-3 mb-4 reveal">
      <div class="col-lg-7">
        <div class="lp-kicker mb-2">Curated Journeys</div>
        <h2 class="lp-h2 mb-0">Featured packages, live from our catalogue</h2>
      </div>
      <div class="col-lg-5 text-lg-end">
        <a href="{{ route('catalog.index') }}" class="btn btn-brand px-4">View all {{ $stats['packages'] }} packages →</a>
      </div>
    </div>

    @if ($categories)
      <div class="d-flex flex-wrap gap-2 mb-4 reveal">
        <a href="{{ route('catalog.index') }}" class="lp-chip">All packages <b>{{ $stats['packages'] }}</b></a>
        @foreach ($categories as $c)
          <a href="{{ route('catalog.index', ['category' => $c['key']]) }}" class="lp-chip">
            {{ $catArt[$c['key']][0] ?? '✈️' }} {{ $c['label'] }} <b>{{ $c['count'] }}</b>
          </a>
        @endforeach
      </div>
    @endif

    <div class="row g-4">
      @forelse ($featured as $package)
        @php $art = $catArt[$package->category] ?? ['✈️', '#1466ff', '#0b3fd1']; @endphp
        <div class="col-md-6 col-lg-4 reveal">
          <a class="lp-card" href="{{ route('catalog.show', $package->slug) }}">
            <div class="art"
                 style="@if ($package->cover_image) background-image:url('{{ asset('storage/' . $package->cover_image) }}') @else background:linear-gradient(150deg,{{ $art[1] }},{{ $art[2] }}) @endif">
              <span class="tag">{{ $package->categoryLabel() }}</span>
              @if ($package->featured)<span class="hot">★ Featured</span>@endif
              @unless ($package->cover_image)<span style="position:relative;z-index:1">{{ $art[0] }}</span>@endunless
            </div>
            <div class="bd">
              <h5>{{ $package->title }}</h5>
              <div class="meta mb-3">📍 {{ $package->destination ?: 'Multiple stops' }} · {{ $package->duration_days }}D{{ $package->duration_nights }}N</div>
              <div class="d-flex align-items-center justify-content-between">
                <div class="price">RM {{ number_format($package->fromPrice(), 0) }} <small>/ pax</small></div>
                <div class="go">View details ›</div>
              </div>
            </div>
          </a>
        </div>
      @empty
        <div class="col-12"><div class="lp-feature text-center text-muted">New packages are being prepared — please check back soon.</div></div>
      @endforelse
    </div>
  </div>
</section>

{{-- ── Destinations + next departures ──────────────────── --}}
<section class="lp-sec pt-0" id="destinations">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-7">
        <div class="lp-kicker mb-2 reveal">Where We Fly</div>
        <h2 class="lp-h2 mb-4 reveal">Popular destinations</h2>
        <div class="row g-3">
          @forelse ($destinations as $d)
            @php $art = $catArt[$d->category] ?? ['✈️', '#1466ff', '#0b3fd1']; @endphp
            <div class="col-6 col-md-3 reveal">
              <a class="lp-dest" href="{{ route('catalog.index', ['q' => $d->destination]) }}"
                 style="@if ($d->cover_image) background-image:url('{{ asset('storage/' . $d->cover_image) }}');background-size:cover;background-position:center @else background:linear-gradient(150deg,{{ $art[1] }},{{ $art[2] }}) @endif">
                <span>{{ $art[0] }}<br>{{ $d->destination }}</span>
              </a>
            </div>
          @empty
            <div class="col-12 text-muted">Destinations will appear here once packages are published.</div>
          @endforelse
        </div>
      </div>

      <div class="col-lg-5">
        <div class="lp-kicker mb-2 reveal">Seats Open</div>
        <h2 class="lp-h2 mb-4 reveal">Next departures</h2>
        <div class="d-flex flex-column gap-3">
          @forelse ($departures as $dep)
            <a class="lp-dep reveal" href="{{ route('catalog.show', $dep->package->slug) }}">
              <div class="d">
                <b>{{ $dep->depart_date->format('d') }}</b>
                <span>{{ $dep->depart_date->format('M') }}</span>
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold" style="color:var(--bt-ink)">{{ $dep->package->title }}</div>
                <div class="small text-muted">
                  {{ $dep->package->destination }} ·
                  <span class="fw-semibold text-brand">{{ $dep->seats_total - $dep->seats_booked }} seats left</span>
                </div>
              </div>
              <div class="text-brand fw-bold">›</div>
            </a>
          @empty
            <div class="lp-dep reveal text-muted">Departure dates are being finalised. Talk to us for custom dates.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── Why us ──────────────────────────────────────────── --}}
<section class="lp-sec" style="background:#f5f8ff">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="lp-kicker mb-2">Why Travel With Us</div>
      <h2 class="lp-h2">Everything handled, end to end</h2>
    </div>
    <div class="row g-4">
      @foreach ([
        ['🕋', 'Umrah specialists', 'Experienced guidance, vetted hotels near the Haram and a schedule built around your ibadah.'],
        ['💳', 'Secure online payment', 'Pay by FPX or online banking, or upload a bank slip — every payment is verified before it is receipted.'],
        ['📄', 'Instant documents', 'Invoice, receipt and travel voucher generated the moment your booking is confirmed.'],
        ['🤝', 'Vetted partners', 'Hotels, airlines, transport and ground operators confirmed directly through our provider network.'],
        ['🧭', 'Real human support', 'A dedicated agent for every booking, plus in-app support tickets you can track.'],
        ['🔒', 'Your data protected', 'Passports and travel documents are stored privately and released only to you.'],
      ] as $f)
        <div class="col-md-6 col-lg-4 reveal">
          <div class="lp-feature">
            <div class="ic">{{ $f[0] }}</div>
            <h5 class="fw-bold mb-2" style="font-size:1.05rem">{{ $f[1] }}</h5>
            <p class="mb-0 small text-muted">{{ $f[2] }}</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ── How it works ────────────────────────────────────── --}}
<section class="lp-sec" id="how">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5 reveal">
        <div class="lp-kicker mb-2">How It Works</div>
        <h2 class="lp-h2 mb-3">Four steps from browsing to boarding</h2>
        <p class="text-muted">No queues, no paperwork runs. Book online, pay securely and carry your documents in your pocket.</p>
        <a href="{{ route('catalog.index') }}" class="btn btn-brand px-4 mt-2">Start browsing →</a>
      </div>
      <div class="col-lg-7">
        <div class="d-flex flex-column gap-4">
          @foreach ([
            ['Choose your package', 'Browse Umrah, cruise, international and domestic packages with live pricing and seat availability.'],
            ['Book in minutes', 'Add your travellers and passport details — your agent reviews and confirms with the provider.'],
            ['Pay securely', 'Settle in full or by deposit through FPX, online banking or bank-slip upload.'],
            ['Travel with everything ready', 'Invoice, receipt and voucher land in your portal. Track your trip until you are home.'],
          ] as $i => $step)
            <div class="lp-step reveal">
              <div class="no">{{ $i + 1 }}</div>
              <h5 class="fw-bold mb-1" style="font-size:1.05rem">{{ $step[0] }}</h5>
              <p class="mb-0 text-muted small">{{ $step[1] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── Agent programme ─────────────────────────────────── --}}
<section class="lp-sec pt-0" id="agents">
  <div class="container">
    <div class="lp-cta reveal">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <span class="lp-eyebrow">Partner Programme</span>
          <h2 class="lp-h2 mt-3 mb-3">Sell travel. Earn commission. Build your network.</h2>
          <p class="mb-4" style="color:rgba(255,255,255,.82);max-width:640px">
            Join the {{ $company?->name ?? 'Blue Star' }} agent network — a mobile-first portal with your own
            customers and bookings, multi-level commission credited straight to your wallet, missions,
            streaks, rewards and a monthly leaderboard.
          </p>
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('agent.login') }}" class="btn btn-light btn-lg fw-bold px-4">Agent Portal →</a>
            <a href="#contact" class="btn btn-outline-light btn-lg fw-bold px-4">Enquire to join</a>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="d-flex flex-column gap-2">
            @foreach (['💰 Multi-level commission', '📱 Installable mobile app', '🎯 Missions & daily rewards', '🏆 Monthly leaderboard'] as $perk)
              <div class="lp-portal" style="pointer-events:none">{{ $perk }}</div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── Footer ──────────────────────────────────────────── --}}
<footer class="lp-foot" id="contact">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="bg-white d-inline-flex rounded-3 p-2 mb-3"><img src="{{ asset('images/logo.png') }}" alt="{{ $company?->name ?? 'Blue Star Travel And Tours' }}" style="height:48px;width:auto;display:block"></div>
        <p class="small mb-2">{{ $company?->legal_name ?? $company?->name ?? 'Blue Star Travel And Tours Sdn Bhd' }}</p>
        @if ($company?->registration_no)<p class="small mb-1">Company No. {{ $company->registration_no }}</p>@endif
        @if ($company?->license_no)<p class="small mb-0">Licence No. {{ $company->license_no }}</p>@endif
      </div>

      <div class="col-6 col-lg-2">
        <h6>Explore</h6>
        <div class="d-flex flex-column gap-2">
          <a href="{{ route('catalog.index') }}">All packages</a>
          @foreach (array_slice($categories, 0, 4) as $c)
            <a href="{{ route('catalog.index', ['category' => $c['key']]) }}">{{ $c['label'] }}</a>
          @endforeach
        </div>
      </div>

      <div class="col-6 col-lg-2">
        <h6>Portals</h6>
        <div class="d-flex flex-column gap-2">
          <a href="{{ route('login') }}">Customer</a>
          <a href="{{ route('agent.login') }}">Agent</a>
          <a href="{{ route('provider.login') }}">Provider</a>
          <a href="{{ route('admin.login') }}">HQ / Admin</a>
        </div>
      </div>

      <div class="col-lg-4">
        <h6>Get in touch</h6>
        <div class="d-flex flex-column gap-2">
          @if ($company?->phone)<a href="tel:{{ $company->phone }}">📞 {{ $company->phone }}</a>@endif
          @if ($company?->email)<a href="mailto:{{ $company->email }}">✉️ {{ $company->email }}</a>@endif
          @if ($company?->website)<a href="{{ $company->website }}" target="_blank" rel="noopener">🌐 {{ $company->website }}</a>@endif
          @if ($company?->address)
            <span class="small">📍 {{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}{{ $company->postcode ? ' ' . $company->postcode : '' }}{{ $company->state ? ', ' . $company->state : '' }}</span>
          @endif
        </div>
      </div>
    </div>

    <hr style="border-color:rgba(255,255,255,.14);margin:36px 0 20px">
    <div class="d-flex flex-wrap justify-content-between gap-2 small">
      <span>© {{ date('Y') }} {{ $company?->name ?? 'Blue Star Travel And Tours' }}. All rights reserved.</span>
      <span>Powered by CodexLure Technology</span>
    </div>
  </div>
</footer>

<script>
  (function () {
    var nav = document.getElementById('lpNav');
    var onScroll = function () { nav.classList.toggle('stuck', window.scrollY > 40); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    var reveals = document.querySelectorAll('.reveal');
    if (! ('IntersectionObserver' in window)) {
      reveals.forEach(function (el) { el.classList.add('in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e, i) {
          if (! e.isIntersecting) return;
          setTimeout(function () { e.target.classList.add('in'); }, i * 70);
          io.unobserve(e.target);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
      reveals.forEach(function (el) { io.observe(el); });
    }

    var counters = document.querySelectorAll('[data-count]');
    var runCounter = function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10) || 0;
      var started = null;
      var step = function (ts) {
        if (started === null) started = ts;
        var p = Math.min((ts - started) / 1100, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };
    if (! ('IntersectionObserver' in window)) {
      counters.forEach(function (el) { el.textContent = el.getAttribute('data-count'); });
    } else {
      var co = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (! e.isIntersecting) return;
          runCounter(e.target);
          co.unobserve(e.target);
        });
      }, { threshold: 0.5 });
      counters.forEach(function (el) { co.observe(el); });
    }
  })();
</script>
</body>
</html>
