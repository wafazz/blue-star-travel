<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>@yield('title', 'Agent') — Blue Travel</title>
@include('partials.favicon')
@include('partials.pwa', ['portal' => 'agent'])
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
.wrap{padding:16px 16px 0}
.card{background:var(--card);border-radius:18px;box-shadow:var(--shadow);padding:16px;margin-bottom:14px}
.card h3{font-size:14px;font-weight:800;margin-bottom:12px}
.lbl{font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;display:block}
.inp,select.inp,textarea.inp{width:100%;border:1.5px solid #e3e9f5;border-radius:12px;padding:11px 13px;font-size:14px;
  font-family:inherit;color:var(--ink);background:#fff;margin-bottom:12px}
.inp:focus{outline:none;border-color:var(--blue)}
.row2{display:flex;gap:10px}.row2>*{flex:1}
.btn{display:block;width:100%;border:none;border-radius:14px;padding:14px;font-size:15px;font-weight:800;
  background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff;cursor:pointer;box-shadow:0 10px 24px rgba(20,102,255,.32)}
.btn:active{transform:scale(.98)}
.btn.ghost{background:#eef2fb;color:var(--muted);box-shadow:none}
.btn.ok{background:linear-gradient(135deg,#16b364,#0e9455);box-shadow:0 10px 24px rgba(22,179,100,.3)}
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
    <a href="{{ route('agent.dashboard') }}" class="{{ request()->routeIs('agent.dashboard') ? 'on' : '' }}"><span class="ic">🏠</span>Home</a>
    <a href="{{ route('agent.bookings.index') }}" class="{{ request()->routeIs('agent.bookings.*') ? 'on' : '' }}"><span class="ic">📋</span>Bookings</a>
    <a href="{{ route('agent.bookings.create') }}"><span class="ic">➕</span>New</a>
    <a href="{{ route('agent.wallet.index') }}" class="{{ request()->routeIs('agent.wallet.*') ? 'on' : '' }}"><span class="ic">💰</span>Wallet</a>
    <a href="{{ route('agent.network') }}" class="{{ request()->routeIs('agent.network') ? 'on' : '' }}"><span class="ic">🌐</span>Network</a>
  </div>
</div>
</body>
</html>
