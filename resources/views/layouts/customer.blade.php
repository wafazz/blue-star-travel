<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>@yield('title', 'My Account') — Blue Travel</title>
@include('partials.favicon')
@include('partials.pwa', ['portal' => 'customer'])
<style>
:root{
  --blue:#1466ff; --blue-2:#0b3fd1; --sky:#38bdf8; --ink:#0d1b3e; --muted:#7a86a8;
  --bg:#eef2fb; --card:#ffffff; --line:#eef1f8; --ok:#16b364; --warn:#f79009; --danger:#f04438;
  --gold:#f5b301; --shadow:0 10px 30px rgba(16,42,110,.10);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#dfe6f2;color:var(--ink)}
.phone{width:100%;max-width:480px;height:100vh;height:100dvh;margin:0 auto;background:var(--bg);
  position:relative;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 0 60px rgba(20,50,110,.12)}
.screen{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;padding:0 0 96px}
.screen::-webkit-scrollbar{display:none}
.abar{background:linear-gradient(160deg,#1466ff 0%,#0b3fd1 60%,#082aa0 100%);color:#fff;padding:18px 18px 20px;
  border-radius:0 0 26px 26px;display:flex;align-items:center;gap:12px}
.abar a.back{color:#fff;text-decoration:none;font-size:22px;font-weight:700;line-height:1}
.abar .t{font-size:18px;font-weight:800}
.abar .sub{font-size:12px;opacity:.85;margin-top:1px}
.hero{background:linear-gradient(160deg,#1466ff 0%,#0b3fd1 60%,#082aa0 100%);color:#fff;padding:26px 22px 60px;border-radius:0 0 32px 32px}
.hero .top{display:flex;align-items:center;gap:12px}
.av{width:48px;height:48px;border-radius:15px;background:rgba(255,255,255,.16);border:1.5px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;font-weight:800}
.hero h1{font-size:18px;font-weight:800}.hero p{font-size:12px;opacity:.85}
.wrap{padding:16px 16px 0}
.sec{padding:0 18px;margin-top:20px}
.sec h3{font-size:15px;font-weight:800;margin-bottom:12px}
.card{background:var(--card);border-radius:18px;box-shadow:var(--shadow);padding:16px;margin-bottom:14px}
.card.pull{margin:-40px 18px 0}
.card h3{font-size:14px;font-weight:800;margin-bottom:12px}
.lbl{font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;display:block}
.inp,select.inp,textarea.inp{width:100%;border:1.5px solid #e3e9f5;border-radius:12px;padding:11px 13px;font-size:14px;
  font-family:inherit;color:var(--ink);background:#fff;margin-bottom:12px}
.inp:focus{outline:none;border-color:var(--blue)}
.row2{display:flex;gap:10px}.row2>*{flex:1}
.btn{display:block;width:100%;border:none;border-radius:14px;padding:14px;font-size:15px;font-weight:800;
  background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff;cursor:pointer;box-shadow:0 10px 24px rgba(20,102,255,.32);text-align:center;text-decoration:none}
.btn:active{transform:scale(.98)}
.btn.ghost{background:#eef2fb;color:var(--muted);box-shadow:none}
.btn.ok{background:linear-gradient(135deg,#16b364,#0e9455);box-shadow:0 10px 24px rgba(22,179,100,.3)}
.qa{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.qa .q{background:var(--card);border-radius:16px;padding:14px 6px;text-align:center;box-shadow:var(--shadow);text-decoration:none;color:inherit;display:block}
.qa .q .ic{font-size:22px}.qa .q .t{font-size:10.5px;font-weight:700;color:#3a4668;margin-top:6px}
.lrow{background:var(--card);border-radius:15px;padding:14px;box-shadow:var(--shadow);display:flex;align-items:center;gap:13px;margin-bottom:10px;text-decoration:none;color:inherit}
.lrow .ic{font-size:20px}.lrow .tx{flex:1}.lrow .tx b{font-size:13.5px;font-weight:800;display:block}.lrow .tx span{font-size:11.5px;color:var(--muted)}
.lrow.logout b{color:#f04438}
.btn-out{width:100%;background:none;border:none;text-align:left;cursor:pointer;padding:0}
.brow{display:flex;justify-content:space-between;align-items:center;padding:13px 15px;background:var(--card);
  border-radius:15px;box-shadow:var(--shadow);margin-bottom:11px;text-decoration:none;color:inherit}
.brow .n{font-weight:800;font-size:14px}.brow .m{font-size:11.5px;color:var(--muted);margin-top:2px}
.badge{font-size:10.5px;font-weight:800;padding:4px 10px;border-radius:20px;white-space:nowrap}
.b-info{background:#e6f0ff;color:#1466ff}.b-primary{background:#e6f0ff;color:#0b3fd1}
.b-success{background:#e4f7ec;color:#0e9455}.b-warning{background:#fdf1e0;color:#b26a00}
.b-danger{background:#fdeaea;color:#d13b3b}.b-secondary{background:#eef1f8;color:#7a86a8}.b-dark{background:#e0e4ee;color:#0d1b3e}
.sum{display:flex;justify-content:space-between;font-size:13px;margin-bottom:7px}
.sum.total{font-size:18px;font-weight:800;color:var(--blue);border-top:1px solid var(--line);padding-top:10px;margin-top:4px}
.seg{display:flex;gap:7px;overflow-x:auto;padding:14px 16px 4px}
.seg a{white-space:nowrap;text-decoration:none;font-size:12px;font-weight:700;padding:7px 14px;border-radius:20px;
  background:#fff;color:var(--muted);box-shadow:var(--shadow)}
.seg a.on{background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff}
.empty{text-align:center;color:var(--muted);font-size:13px;padding:40px 20px;font-weight:600}
.tl{list-style:none;padding:0;margin:0}
.tl li{display:flex;gap:10px;padding-bottom:14px}
.tl .dot{width:9px;height:9px;border-radius:50%;background:var(--blue);margin-top:5px;flex:0 0 auto}
.tl .a{font-size:13px;font-weight:700}.tl .nt{font-size:12px;color:var(--muted)}.tl .tm{font-size:10.5px;color:#a3adca}
.alert{background:#e4f7ec;color:#0e9455;font-size:13px;font-weight:700;padding:11px 14px;border-radius:12px;margin:14px 16px 0}
.alert.err{background:#fdeaea;color:#d13b3b}
.pk{background:var(--card);border-radius:18px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:14px;text-decoration:none;color:inherit;display:block}
.pk .img{height:130px;background:linear-gradient(135deg,#1466ff,#082aa0);display:flex;align-items:center;justify-content:center;font-size:40px;background-size:cover;background-position:center}
.pk .bd{padding:13px 15px}
.pk .cat{font-size:10.5px;font-weight:800;color:var(--blue);text-transform:uppercase;letter-spacing:.04em}
.pk .n{font-size:14.5px;font-weight:800;margin:3px 0 4px}
.pk .m{font-size:11.5px;color:var(--muted)}
.pk .pr{font-size:16px;font-weight:800;color:var(--blue);margin-top:8px}
.pk .pr small{font-size:11px;color:var(--muted);font-weight:600}
.bub{max-width:80%;padding:11px 13px;border-radius:14px;font-size:13px;margin-bottom:10px;line-height:1.45}
.bub.me{background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff;margin-left:auto;border-bottom-right-radius:4px}
.bub.them{background:#fff;box-shadow:var(--shadow);border-bottom-left-radius:4px}
.bub .who{font-size:10.5px;font-weight:800;opacity:.75;margin-bottom:3px}
.nav{position:absolute;bottom:0;left:0;right:0;height:70px;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);
  border-top:1px solid #e7ecf7;display:flex;align-items:center;justify-content:space-around;padding:0 8px 10px;z-index:40;max-width:480px;margin:0 auto}
.nav a{text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:3px;font-size:10px;font-weight:700;color:#a3adca;flex:1;padding-top:12px}
.nav a .ic{font-size:20px}.nav a.on{color:var(--blue)}
</style>
</head>
<body>
<div class="phone">
  <div class="screen">
    @yield('content')
  </div>
  <div class="nav">
    <a href="{{ route('catalog.index') }}" class="{{ request()->routeIs('catalog.*') ? 'on' : '' }}"><span class="ic">🗺️</span>Packages</a>
    @auth
      <a href="{{ route('customer.dashboard') }}" class="{{ request()->routeIs('customer.dashboard') ? 'on' : '' }}"><span class="ic">🏠</span>Home</a>
      <a href="{{ route('customer.bookings.index') }}" class="{{ request()->routeIs('customer.bookings.*') ? 'on' : '' }}"><span class="ic">🧳</span>My Trips</a>
      <a href="{{ route('customer.tickets.index') }}" class="{{ request()->routeIs('customer.tickets.*') ? 'on' : '' }}"><span class="ic">🎧</span>Support</a>
      <a href="{{ route('customer.profile.edit') }}" class="{{ request()->routeIs('customer.profile.*') ? 'on' : '' }}"><span class="ic">🛂</span>Profile</a>
    @else
      <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'on' : '' }}"><span class="ic">🔑</span>Sign In</a>
      <a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'on' : '' }}"><span class="ic">✨</span>Register</a>
    @endauth
  </div>
</div>
</body>
</html>
