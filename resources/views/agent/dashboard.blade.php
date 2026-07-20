<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Blue Travel — Agent Portal</title>
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
.screen{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;padding-bottom:96px}
.screen::-webkit-scrollbar{display:none}
.page{display:none;animation:fade .35s ease}
.page.active{display:block}
@keyframes fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.hero{background:linear-gradient(160deg,#1466ff 0%,#0b3fd1 60%,#082aa0 100%);padding:22px 20px 78px;
  border-radius:0 0 34px 34px;color:#fff;position:relative}
.hero .top{display:flex;align-items:center;gap:12px;margin-top:8px}
.avatar{width:46px;height:46px;border-radius:14px;background:rgba(255,255,255,.16);display:flex;align-items:center;
  justify-content:center;font-weight:800;font-size:18px;border:1.5px solid rgba(255,255,255,.35)}
.hi{flex:1}
.hi .s{font-size:12px;opacity:.8}
.hi .n{font-size:17px;font-weight:800;margin-top:1px}
.bell{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.14);display:flex;align-items:center;
  justify-content:center;font-size:17px;position:relative}
.bell .badge{position:absolute;top:-3px;right:-3px;background:var(--danger);width:16px;height:16px;border-radius:50%;
  font-size:9px;display:flex;align-items:center;justify-content:center;font-weight:800;border:2px solid #0b3fd1}
.rankpill{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:5px 11px;
  border-radius:20px;font-size:12px;font-weight:700;margin-top:16px}
.wallet{margin:-58px 16px 0;background:linear-gradient(135deg,#14264f 0%,#1d3a7a 55%,#2456c7 100%);border-radius:24px;
  padding:20px;color:#fff;box-shadow:0 20px 40px rgba(13,40,110,.35);position:relative;overflow:hidden}
.wallet::after{content:"";position:absolute;right:-40px;top:-40px;width:150px;height:150px;border-radius:50%;
  background:radial-gradient(circle,rgba(56,189,248,.4),transparent 70%)}
.wallet .lbl{font-size:12px;opacity:.75;display:flex;align-items:center;gap:6px}
.wallet .amt{font-size:33px;font-weight:800;margin-top:4px;letter-spacing:-.5px}
.wallet .amt small{font-size:16px;opacity:.7;font-weight:600}
.wallet .sub{font-size:12px;opacity:.8;margin-top:3px}
.wallet .acts{display:flex;gap:10px;margin-top:18px;position:relative;z-index:2}
.wallet .acts button{flex:1;background:rgba(255,255,255,.14);border:none;color:#fff;padding:11px;border-radius:14px;
  font-size:12px;font-weight:700;display:flex;flex-direction:column;align-items:center;gap:5px;cursor:pointer;
  transition:.15s;backdrop-filter:blur(4px)}
.wallet .acts button:active{transform:scale(.94);background:rgba(255,255,255,.25)}
.wallet .acts .ic{font-size:17px}
.wallet .chip{position:absolute;top:20px;right:20px;font-size:11px;font-weight:700;background:rgba(245,179,1,.25);
  color:#ffdd8a;padding:4px 9px;border-radius:8px;z-index:2}
.sec{padding:0 16px;margin-top:22px}
.sec-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 2px}
.sec-h h3{font-size:15px;font-weight:800}
.sec-h a{font-size:12px;color:var(--blue);font-weight:700;text-decoration:none}
.qa{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.qa .q{background:var(--card);border-radius:18px;padding:13px 6px;text-align:center;box-shadow:var(--shadow);
  cursor:pointer;transition:.15s}
.qa .q:active{transform:scale(.93)}
.qa .q .ic{width:42px;height:42px;border-radius:13px;margin:0 auto 7px;display:flex;align-items:center;justify-content:center;font-size:19px}
.qa .q .t{font-size:10.5px;font-weight:700;color:#3a4668;line-height:1.2}
.i-blue{background:#e6efff;color:#1466ff}.i-green{background:#e3f9ed;color:#16b364}
.i-orange{background:#fff1e0;color:#f79009}.i-purple{background:#efe9ff;color:#7c5cff}
.i-pink{background:#ffe9f2;color:#ec4899}.i-teal{background:#e0f7f6;color:#14b8a6}
.i-red{background:#ffe8e6;color:#f04438}.i-gold{background:#fff6d9;color:#e0a800}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.stat{background:var(--card);border-radius:18px;padding:14px;box-shadow:var(--shadow)}
.stat .row{display:flex;align-items:center;justify-content:space-between}
.stat .ic{width:34px;height:34px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:16px}
.stat .up{font-size:11px;font-weight:800;color:var(--ok);background:#e3f9ed;padding:2px 7px;border-radius:8px}
.stat .down{font-size:11px;font-weight:800;color:var(--danger);background:#ffe8e6;padding:2px 7px;border-radius:8px}
.stat .v{font-size:22px;font-weight:800;margin-top:11px;letter-spacing:-.5px}
.stat .l{font-size:11.5px;color:var(--muted);font-weight:600;margin-top:1px}
.target{background:var(--card);border-radius:20px;padding:18px;box-shadow:var(--shadow);display:flex;align-items:center;gap:18px}
.ring{width:96px;height:96px;flex-shrink:0;position:relative}
.ring svg{transform:rotate(-90deg)}
.ring .mid{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ring .mid b{font-size:21px;font-weight:800}
.ring .mid span{font-size:10px;color:var(--muted);font-weight:600}
.target .info{flex:1}
.target .info .k{font-size:12px;color:var(--muted);font-weight:600}
.target .info .val{font-size:19px;font-weight:800;margin:2px 0 3px}
.target .info .val small{font-size:13px;color:var(--muted);font-weight:600}
.bonusnote{background:#fff8e6;border:1px solid #ffe8ad;border-radius:11px;padding:8px 10px;font-size:11.5px;
  color:#8a6400;font-weight:600;margin-top:10px;display:flex;align-items:center;gap:6px}
.mission{background:var(--card);border-radius:16px;padding:13px 14px;box-shadow:var(--shadow);display:flex;
  align-items:center;gap:12px;margin-bottom:10px}
.mcheck{width:26px;height:26px;border-radius:9px;border:2px solid #d7deee;flex-shrink:0;display:flex;
  align-items:center;justify-content:center;font-size:14px;color:#fff;cursor:pointer;transition:.2s}
.mission.done .mcheck{background:var(--ok);border-color:var(--ok)}
.mission.done .mtxt{text-decoration:line-through;color:var(--muted)}
.mtxt{flex:1;font-size:13px;font-weight:700}
.mpts{font-size:11px;font-weight:800;color:var(--warn);background:#fff4e2;padding:3px 9px;border-radius:9px}
.streak-card{background:linear-gradient(135deg,#ff8a3d,#f6511d);border-radius:20px;padding:18px;color:#fff;
  box-shadow:0 16px 34px rgba(240,80,20,.28);position:relative;overflow:hidden}
.streak-card::after{content:"🔥";position:absolute;right:-6px;bottom:-14px;font-size:96px;opacity:.18}
.streak-card .big{font-size:30px;font-weight:800}
.streak-card .cap{font-size:12.5px;opacity:.92;font-weight:600}
.days{display:flex;gap:7px;margin-top:15px;position:relative;z-index:2}
.day{flex:1;text-align:center;background:rgba(255,255,255,.16);border-radius:11px;padding:8px 0}
.day.on{background:#fff;color:#f6511d}
.day.today{outline:2px solid #fff;outline-offset:2px}
.day .d{font-size:9px;font-weight:700;opacity:.8}
.day.on .d{opacity:1}
.day .p{font-size:11px;font-weight:800;margin-top:3px}
.checkin-btn{width:100%;margin-top:15px;background:#fff;color:#f6511d;border:none;padding:12px;border-radius:13px;
  font-size:14px;font-weight:800;cursor:pointer;position:relative;z-index:2;transition:.15s}
.checkin-btn:active{transform:scale(.97)}
.checkin-btn.claimed{background:rgba(255,255,255,.25);color:#fff}
.lb-item{background:var(--card);border-radius:16px;padding:12px 14px;box-shadow:var(--shadow);display:flex;
  align-items:center;gap:12px;margin-bottom:10px}
.lb-item.me{background:linear-gradient(135deg,#eef4ff,#e0ecff);border:1.5px solid #bcd4ff}
.rankn{width:30px;text-align:center;font-weight:800;font-size:15px;color:var(--muted)}
.rankn.top{color:var(--gold)}
.lb-av{width:40px;height:40px;border-radius:12px;background:#dfe6f5;display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:15px;color:#41527d}
.lb-name{flex:1}
.lb-name b{font-size:14px;font-weight:800}
.lb-name span{font-size:11.5px;color:var(--muted);font-weight:600;display:block}
.lb-amt{text-align:right}
.lb-amt b{font-size:14px;font-weight:800;color:var(--blue)}
.lb-amt span{font-size:10.5px;color:var(--muted);display:block}
.podium{display:flex;align-items:flex-end;justify-content:center;gap:12px;padding:8px 0 4px}
.pod{text-align:center;flex:1}
.pod .pav{width:56px;height:56px;border-radius:18px;margin:0 auto;display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:20px;color:#fff;position:relative}
.pod .crown{position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:20px}
.pod.p1 .pav{background:linear-gradient(135deg,#f5b301,#e0900a);width:66px;height:66px}
.pod.p2 .pav{background:linear-gradient(135deg,#a9b6cf,#8493b3)}
.pod.p3 .pav{background:linear-gradient(135deg,#e08a54,#c26a34)}
.pod .pn{font-size:12px;font-weight:800;margin-top:8px}
.pod .pa{font-size:11px;color:var(--blue);font-weight:800}
.pod .bar{background:rgba(255,255,255,.6);border-radius:12px 12px 0 0;margin-top:8px;box-shadow:var(--shadow)}
.pod.p1 .bar{height:64px}.pod.p2 .bar{height:44px}.pod.p3 .bar{height:32px}
.badges{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.badge-i{text-align:center}
.badge-i .circ{width:56px;height:56px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;
  justify-content:center;font-size:24px;background:#fff;box-shadow:var(--shadow)}
.badge-i.locked .circ{filter:grayscale(1);opacity:.4}
.badge-i .bn{font-size:10px;font-weight:700;color:#3a4668;line-height:1.2}
.bk{background:var(--card);border-radius:16px;padding:13px 14px;box-shadow:var(--shadow);margin-bottom:11px}
.bk .r1{display:flex;align-items:center;justify-content:space-between}
.bk .cust{font-size:14px;font-weight:800}
.bk .pkg{font-size:12px;color:var(--muted);font-weight:600;margin-top:3px;display:flex;align-items:center;gap:5px}
.bk .r2{display:flex;align-items:center;justify-content:space-between;margin-top:11px;padding-top:11px;border-top:1px dashed var(--line)}
.bk .price{font-size:15px;font-weight:800;color:var(--ink)}
.tag{font-size:10.5px;font-weight:800;padding:4px 9px;border-radius:8px}
.t-confirm{background:#e3f9ed;color:#0f9a53}.t-pend{background:#fff1e0;color:#c9760a}
.t-verify{background:#e6efff;color:#1466ff}.t-reject{background:#ffe8e6;color:#d13c30}
.seg{display:flex;background:#e5eaf6;border-radius:13px;padding:4px;margin-bottom:14px}
.seg button{flex:1;border:none;background:none;padding:8px;border-radius:10px;font-size:12px;font-weight:700;
  color:var(--muted);cursor:pointer;transition:.2s}
.seg button.on{background:#fff;color:var(--ink);box-shadow:0 2px 8px rgba(0,0,0,.06)}
.rp{background:linear-gradient(135deg,#7c5cff,#5a2fd6);border-radius:20px;padding:18px;color:#fff;
  box-shadow:0 16px 34px rgba(90,47,214,.28);display:flex;align-items:center;gap:16px;position:relative;overflow:hidden}
.rp::after{content:"💎";position:absolute;right:-4px;top:-10px;font-size:84px;opacity:.16}
.rp .pts{font-size:30px;font-weight:800}
.rp .l{font-size:12px;opacity:.9}
.rp button{margin-left:auto;background:#fff;color:#5a2fd6;border:none;padding:10px 15px;border-radius:12px;
  font-weight:800;font-size:12.5px;cursor:pointer;position:relative;z-index:2}
.lrow{background:var(--card);border-radius:15px;padding:14px;box-shadow:var(--shadow);display:flex;align-items:center;
  gap:13px;margin-bottom:10px;cursor:pointer;transition:.15s}
.lrow:active{transform:scale(.98)}
.lrow .ic{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.lrow .tx{flex:1}
.lrow .tx b{font-size:13.5px;font-weight:800}
.lrow .tx span{font-size:11.5px;color:var(--muted);font-weight:600}
.lrow .arw{color:#c2cbe0;font-size:18px}
.lrow.logout b{color:var(--danger)}
.phead{text-align:center;padding:6px 0 4px}
.phead .pav{width:82px;height:82px;border-radius:26px;margin:0 auto;background:linear-gradient(135deg,#1466ff,#0b3fd1);
  display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#fff;box-shadow:0 12px 26px rgba(20,102,255,.3)}
.phead h2{font-size:19px;font-weight:800;margin-top:12px}
.phead .role{font-size:12px;color:var(--muted);font-weight:600;margin-top:2px}
.pstats{display:flex;background:var(--card);border-radius:18px;box-shadow:var(--shadow);margin:16px 0;overflow:hidden}
.pstats div{flex:1;text-align:center;padding:14px 6px}
.pstats div+div{border-left:1px solid var(--line)}
.pstats b{font-size:17px;font-weight:800;display:block}
.pstats span{font-size:10.5px;color:var(--muted);font-weight:600}
.nav{position:absolute;bottom:0;left:0;right:0;height:78px;background:rgba(255,255,255,.94);backdrop-filter:blur(14px);
  border-top:1px solid #e7ecf7;display:flex;align-items:center;justify-content:space-around;padding:0 8px 12px;z-index:40}
.nav button{background:none;border:none;display:flex;flex-direction:column;align-items:center;gap:3px;font-size:10px;
  font-weight:700;color:#a3adca;cursor:pointer;flex:1;padding-top:12px;transition:.15s;position:relative}
.nav button .ic{font-size:20px;transition:.2s}
.nav button.on{color:var(--blue)}
.nav button.on .ic{transform:translateY(-2px) scale(1.08)}
.nav .fabwrap{flex:0 0 auto;margin-top:-30px}
.nav .fab{width:56px;height:56px;border-radius:20px;background:linear-gradient(135deg,#1466ff,#0b3fd1);
  display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff;box-shadow:0 10px 24px rgba(20,102,255,.42);
  border:4px solid #fff;cursor:pointer;transition:.15s}
.nav .fab:active{transform:scale(.92)}
.toast{position:absolute;bottom:100px;left:50%;transform:translateX(-50%) translateY(20px);background:#0d1b3e;
  color:#fff;padding:11px 20px;border-radius:14px;font-size:13px;font-weight:700;opacity:0;pointer-events:none;
  transition:.3s;z-index:70;box-shadow:0 10px 30px rgba(0,0,0,.3);white-space:nowrap}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.pagetitle{padding:16px 18px 4px;font-size:22px;font-weight:800}
.hint{text-align:center;color:#5c6a90;font-size:12px;margin-top:14px;font-weight:600}
</style>
</head>
<body>
@php $agent = auth()->user(); @endphp
<div class="phone">
  <div class="screen" id="screen">

    <!-- HOME -->
    <div class="page active" id="page-home">
      <div class="hero">
        <div class="top">
          <div class="avatar">{{ $agent->initials() }}</div>
          <div class="hi">
            <div class="s">🎉 Good day,</div>
            <div class="n">{{ $agent->name }}</div>
          </div>
          <div class="bell" onclick="location.href='{{ route('agent.notifications') }}'" style="cursor:pointer">🔔@if($unreadCount)<span class="badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif</div>
        </div>
        <div class="rankpill">🔥 {{ $rankInfo['rank'] ? 'Ranked #' . $rankInfo['rank'] . ' out of ' . $rankInfo['total'] . ' agents' : 'Make a sale to enter the leaderboard' }}</div>
      </div>

      @php
        $tierIcon = ['silver' => '🥈', 'gold' => '⭐', 'platinum' => '💎'][$user->agent_tier] ?? '⭐';
        $toGo = max(0, $salesTarget - $achievedThisMonth);
      @endphp
      <div class="wallet">
        <div class="chip">{{ $tierIcon }} {{ ucfirst($user->agent_tier) }} Agent</div>
        <div class="lbl">💰 Monthly Commission</div>
        <div class="amt">RM {{ number_format($monthlyCommission, 0) }}<small>.{{ substr(number_format($monthlyCommission, 2), -2) }}</small></div>
        <div class="sub">@if($toGo > 0)🎯 RM{{ number_format($toGo, 0) }} more in sales to hit your target @else🎉 Monthly target reached — bonus unlocked!@endif</div>
        <div class="acts">
          <button onclick="location.href='{{ route('agent.wallet.index') }}'"><span class="ic">⬇️</span>Withdraw</button>
          <button onclick="location.href='{{ route('agent.commissions') }}'"><span class="ic">📜</span>History</button>
          <button onclick="go('rewards')"><span class="ic">🎁</span>Rewards</button>
          <button onclick="location.href='{{ route('agent.network') }}'"><span class="ic">🌐</span>Network</button>
        </div>
      </div>

      @if ($banner)
      <div class="sec">
        <div onclick="{{ $banner->link_url ? "location.href='" . $banner->link_url . "'" : '' }}" style="border-radius:18px;overflow:hidden;box-shadow:var(--shadow);{{ $banner->link_url ? 'cursor:pointer' : '' }};background:linear-gradient(135deg,#1466ff,#0b3fd1)">
          @if ($banner->image)
            <img src="{{ asset('storage/' . $banner->image) }}" style="width:100%;display:block" alt="{{ $banner->title }}">
          @else
            <div style="padding:20px;color:#fff"><div style="font-size:16px;font-weight:800">{{ $banner->title }}</div><div style="font-size:12.5px;opacity:.9;margin-top:3px">{{ $banner->subtitle }}</div></div>
          @endif
        </div>
      </div>
      @endif

      <div class="sec">
        <div class="sec-h"><h3>Quick Actions</h3></div>
        <div class="qa">
          <div class="q" onclick="location.href='{{ route('agent.bookings.create') }}'"><div class="ic i-blue">🧳</div><div class="t">New Booking</div></div>
          <div class="q" onclick="location.href='{{ route('agent.bookings.index') }}'"><div class="ic i-purple">📋</div><div class="t">Bookings</div></div>
          <div class="q" onclick="location.href='{{ route('agent.wallet.index') }}'"><div class="ic i-orange">💳</div><div class="t">Wallet</div></div>
          <div class="q" onclick="location.href='{{ route('agent.commissions') }}'"><div class="ic i-gold">💰</div><div class="t">Commissions</div></div>
          <div class="q" onclick="location.href='{{ route('agent.leaderboard') }}'"><div class="ic i-green">🏆</div><div class="t">Leaderboard</div></div>
          <div class="q" onclick="go('rewards')"><div class="ic i-pink">🎁</div><div class="t">Rewards</div></div>
          <div class="q" onclick="location.href='{{ route('agent.network') }}'"><div class="ic i-teal">🌐</div><div class="t">Network</div></div>
          <div class="q" onclick="location.href='{{ route('agent.achievements') }}'"><div class="ic i-red">🏅</div><div class="t">Badges</div></div>
        </div>
      </div>

      @if ($attention['unpaid'] || $attention['awaiting'])
      <div class="sec">
        <div class="sec-h"><h3>Needs Your Attention</h3></div>
        @if ($attention['unpaid'])
        <div class="lrow" onclick="location.href='{{ route('agent.bookings.index') }}'">
          <div class="ic i-orange">💳</div>
          <div class="tx"><b>{{ $attention['unpaid'] }} booking(s) with outstanding balance</b><span>Collect payment to close the sale</span></div>
          <div class="arw">›</div>
        </div>
        @endif
        @if ($attention['awaiting'])
        <div class="lrow" onclick="location.href='{{ route('agent.bookings.index', ['status' => 'waiting_provider_confirmation']) }}'">
          <div class="ic i-blue">⏳</div>
          <div class="tx"><b>{{ $attention['awaiting'] }} booking(s) awaiting confirmation</b><span>Provider response pending</span></div>
          <div class="arw">›</div>
        </div>
        @endif
      </div>
      @endif

      <div class="sec">
        <div class="sec-h"><h3>Sales Overview</h3><a onclick="toast('Full sales dashboard')">View all</a></div>
        <div class="stats">
          <div class="stat"><div class="row"><div class="ic i-blue">📅</div></div><div class="v">RM {{ number_format($stats['today_sales'], 0) }}</div><div class="l">Today's Sales</div></div>
          <div class="stat"><div class="row"><div class="ic i-green">📈</div></div><div class="v">RM {{ number_format($stats['week_sales'], 0) }}</div><div class="l">This Week</div></div>
          <div class="stat"><div class="row"><div class="ic i-purple">💼</div></div><div class="v">{{ $stats['month_bookings'] }}</div><div class="l">Bookings (Mo)</div></div>
          <div class="stat"><div class="row"><div class="ic i-orange">👥</div></div><div class="v">{{ $stats['customers'] }}</div><div class="l">My Customers</div></div>
        </div>
      </div>

      <div class="sec">
        <div class="sec-h"><h3>Monthly Sales Target</h3></div>
        <div class="target">
          <div class="ring">
            <svg width="96" height="96">
              <circle cx="48" cy="48" r="40" stroke="#eef1f8" stroke-width="10" fill="none"/>
              <circle cx="48" cy="48" r="40" stroke="url(#g1)" stroke-width="10" fill="none"
                stroke-linecap="round" stroke-dasharray="251.2" stroke-dashoffset="{{ 251.2 * (1 - $targetPct / 100) }}"/>
              <defs><linearGradient id="g1" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#38bdf8"/><stop offset="1" stop-color="#1466ff"/>
              </linearGradient></defs>
            </svg>
            <div class="mid"><b>{{ $targetPct }}%</b><span>Completed</span></div>
          </div>
          <div class="info">
            <div class="k">Achieved</div>
            <div class="val">RM {{ number_format($achievedThisMonth, 0) }} <small>/ RM {{ number_format($salesTarget, 0) }}</small></div>
            <div class="bonusnote">🎁 Hit RM{{ number_format($salesTarget, 0) }} in monthly sales to unlock your bonus</div>
          </div>
        </div>
      </div>

      <div class="sec">
        <div class="sec-h"><h3>Today's Missions</h3><a>+{{ $missionPointsAvailable }} pts left</a></div>
        @forelse ($missions as $m)
          @php $done = in_array($m->id, $doneToday); @endphp
          @if ($done)
            <div class="mission done"><div class="mcheck">✓</div><div class="mtxt">{{ $m->title }}</div><div class="mpts">+{{ $m->points }}</div></div>
          @else
            <form method="POST" action="{{ route('agent.missions.complete', $m) }}" style="margin:0">
              @csrf
              <button type="submit" class="mission" style="width:100%;border:none;background:var(--card);text-align:left;cursor:pointer">
                <div class="mcheck"></div><div class="mtxt">{{ $m->title }}@if($m->auto)<span style="font-size:10px;color:var(--muted)"> · auto</span>@endif</div><div class="mpts">+{{ $m->points }}</div>
              </button>
            </form>
          @endif
        @empty
          <div class="hint">No missions configured yet.</div>
        @endforelse
      </div>

      <div class="sec">
        <div class="sec-h"><h3>Daily Check-in</h3></div>
        @php
          $sd = $streak->current;
          $milestones = [['D1',10,1],['D2',20,2],['D3',30,3],['D7','🎟️',7],['D14','★',14],['D30','🎁',30]];
        @endphp
        <div class="streak-card">
          <div class="big">🔥 {{ $sd }} Day{{ $sd == 1 ? '' : 's' }}</div>
          <div class="cap">{{ $checkedInToday ? 'Checked in today ✓ — keep the streak alive!' : "Active streak — don't miss today or it resets!" }}</div>
          <div class="days">
            @foreach ($milestones as [$label, $reward, $dayNum])
              <div class="day {{ $sd >= $dayNum ? 'on' : ($sd + 1 == $dayNum ? 'today' : '') }}"><div class="d">{{ $label }}</div><div class="p">{{ $reward }}</div></div>
            @endforeach
          </div>
          @if ($checkedInToday)
            <button class="checkin-btn claimed" disabled>✓ Checked in — see you tomorrow!</button>
          @else
            <form method="POST" action="{{ route('agent.checkin') }}" style="margin:0">
              @csrf
              <button type="submit" class="checkin-btn" style="border:none;width:100%">Check in today &nbsp;+{{ $sd >= 2 ? 30 : ($sd + 1) * 10 }} pts</button>
            </form>
          @endif
        </div>
      </div>

      <div class="sec">
        <div class="sec-h"><h3>Leaderboard</h3><a onclick="location.href='{{ route('agent.leaderboard') }}'">Full board</a></div>
        @forelse ($topBoard as $row)
          <div class="lb-item {{ $row->user_id === $user->id ? 'me' : '' }}">
            <div class="rankn {{ $row->rank == 1 ? 'top' : '' }}">{{ $row->rank }}</div>
            <div class="lb-av">{{ strtoupper(substr($row->name, 0, 2)) }}</div>
            <div class="lb-name"><b>{{ $row->user_id === $user->id ? 'You (' . strtok($row->name, ' ') . ')' : $row->name }}</b><span>{{ ucfirst($row->agent_tier) }} Agent</span></div>
            <div class="lb-amt"><b>RM {{ number_format($row->sales, 0) }}</b><span>this month</span></div>
          </div>
        @empty
          <div class="hint">No sales ranked yet this month. Close a booking to appear here!</div>
        @endforelse
        @if ($myRow && $myRow->rank > 5)
          <div class="lb-item me"><div class="rankn">{{ $myRow->rank }}</div><div class="lb-av">{{ $user->initials() }}</div><div class="lb-name"><b>You ({{ strtok($user->name, ' ') }})</b><span>{{ ucfirst($user->agent_tier) }} Agent</span></div><div class="lb-amt"><b>RM {{ number_format($myRow->sales, 0) }}</b><span>this month</span></div></div>
        @endif
      </div>

      <div class="hint">— Blue Travel Agent Portal —</div>
    </div>

    <!-- BOOKINGS -->
    <div class="page" id="page-bookings">
      <div class="pagetitle">📋 My Bookings</div>
      <div class="sec">
        <div class="seg">
          <button class="on" onclick="segf(this)">All</button>
          <button onclick="segf(this)">Pending</button>
          <button onclick="segf(this)">Confirmed</button>
          <button onclick="segf(this)">Done</button>
        </div>
        <div class="bk"><div class="r1"><div><div class="cust">Aisyah Kamal</div><div class="pkg">🕋 Umrah Package · 12 Days</div></div><span class="tag t-verify">Verifying</span></div><div class="r2"><div class="price">RM 8,900</div><div class="pkg">📅 20 Aug 2026</div></div></div>
        <div class="bk"><div class="r1"><div><div class="cust">Daniel Tan</div><div class="pkg">🏝️ Bali Free & Easy · 5D4N</div></div><span class="tag t-pend">Pending</span></div><div class="r2"><div class="price">RM 3,200</div><div class="pkg">📅 02 Sep 2026</div></div></div>
        <div class="bk"><div class="r1"><div><div class="cust">Farah Idris</div><div class="pkg">🚢 Langkawi Cruise · 3D2N</div></div><span class="tag t-confirm">Confirmed</span></div><div class="r2"><div class="price">RM 2,450</div><div class="pkg">📅 15 Jul 2026</div></div></div>
        <div class="bk"><div class="r1"><div><div class="cust">Hafiz Rahman</div><div class="pkg">🗼 Tokyo Tour · 7D6N</div></div><span class="tag t-confirm">Confirmed</span></div><div class="r2"><div class="price">RM 11,800</div><div class="pkg">📅 28 Aug 2026</div></div></div>
        <div class="bk"><div class="r1"><div><div class="cust">Nurul Huda</div><div class="pkg">🏖️ Phuket Getaway · 4D3N</div></div><span class="tag t-reject">Rejected</span></div><div class="r2"><div class="price">RM 2,100</div><div class="pkg">📅 10 Jul 2026</div></div></div>
        <div class="hint">Tap a booking to view timeline, upload payment &amp; download voucher.</div>
      </div>
    </div>

    <!-- REWARDS -->
    <div class="page" id="page-rewards">
      <div class="pagetitle">🎁 Rewards</div>
      <div class="sec">
        <div class="rp"><div><div class="pts">{{ number_format($user->reward_points) }}</div><div class="l">Reward Points available</div></div><button onclick="location.href='{{ route('agent.wallet.index') }}'">Wallet</button></div>
      </div>
      <div class="sec">
        <div class="sec-h"><h3>Redeem Points For</h3></div>
        <div class="qa">
          @foreach (\App\Models\Redemption::CATALOG as $type => [$cost, $cash, $icon])
            <form method="POST" action="{{ route('agent.redeem') }}" style="margin:0" onsubmit="return confirm('Redeem {{ \App\Models\Redemption::TYPES[$type] }} for {{ $cost }} pts?')">
              @csrf
              <input type="hidden" name="type" value="{{ $type }}">
              <button type="submit" class="q" style="width:100%;border:none;cursor:pointer;{{ $user->reward_points < $cost ? 'opacity:.45' : '' }}" {{ $user->reward_points < $cost ? 'disabled' : '' }}>
                <div class="ic i-blue">{{ $icon }}</div><div class="t">{{ \App\Models\Redemption::TYPES[$type] }}</div>
                <div style="font-size:10px;color:var(--muted);font-weight:700;margin-top:2px">{{ number_format($cost) }} pts</div>
              </button>
            </form>
          @endforeach
        </div>
      </div>
      <div class="sec">
        <div class="sec-h"><h3>Achievements</h3><a onclick="location.href='{{ route('agent.achievements') }}'">{{ count($unlockedIds) }}/{{ $totalAchievements }}</a></div>
        <div class="badges">
          @foreach (\App\Models\Achievement::orderBy('sort')->get() as $ach)
            <div class="badge-i {{ in_array($ach->id, $unlockedIds) ? '' : 'locked' }}"><div class="circ">{{ $ach->icon }}</div><div class="bn">{{ $ach->name }}</div></div>
          @endforeach
        </div>
      </div>
      <div class="sec">
        <div class="sec-h"><h3>Referral Program</h3></div>
        <div class="lrow" onclick="copyRef()"><div class="ic i-blue">🔗</div><div class="tx"><b>My Referral Code</b><span>{{ $user->agent_code ?? 'Not assigned' }} · tap to copy link</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.network') }}'"><div class="ic i-purple">📊</div><div class="tx"><b>Referral Tracking</b><span>View your downline network</span></div><div class="arw">›</div></div>
      </div>
      <div class="hint">Earn points from bookings, check-ins &amp; missions.</div>
    </div>

    <!-- LEADERBOARD -->
    <div class="page" id="page-rank">
      <div class="pagetitle">🏆 Leaderboard</div>
      <div class="sec">
        <div class="seg">
          <button onclick="segp(this)">Daily</button>
          <button onclick="segp(this)">Weekly</button>
          <button class="on" onclick="segp(this)">Monthly</button>
          <button onclick="segp(this)">Yearly</button>
        </div>
        <div class="podium">
          <div class="pod p2"><div class="pav">RA<span class="crown">🥈</span></div><div class="pn">Rizal A.</div><div class="pa">RM 10,900</div><div class="bar"></div></div>
          <div class="pod p1"><div class="pav">SA<span class="crown">👑</span></div><div class="pn">Sarah Aziz</div><div class="pa">RM 12,400</div><div class="bar"></div></div>
          <div class="pod p3"><div class="pav">ML<span class="crown">🥉</span></div><div class="pn">Mei Ling</div><div class="pa">RM 9,600</div><div class="bar"></div></div>
        </div>
      </div>
      <div class="sec">
        <div class="lb-item me"><div class="rankn">4</div><div class="lb-av">{{ $agent->initials() }}</div><div class="lb-name"><b>You ({{ strtok($agent->name, ' ') }})</b><span>Gold Agent · 🔥12-day streak</span></div><div class="lb-amt"><b>RM 8,450</b><span>this month</span></div></div>
        <div class="lb-item"><div class="rankn">5</div><div class="lb-av">FZ</div><div class="lb-name"><b>Farid Zaki</b><span>Gold Agent</span></div><div class="lb-amt"><b>RM 7,980</b><span>this month</span></div></div>
        <div class="lb-item"><div class="rankn">6</div><div class="lb-av">NA</div><div class="lb-name"><b>Nadia Amin</b><span>Silver Agent</span></div><div class="lb-amt"><b>RM 7,210</b><span>this month</span></div></div>
        <div class="lb-item"><div class="rankn">7</div><div class="lb-av">KL</div><div class="lb-name"><b>Kevin Lim</b><span>Silver Agent</span></div><div class="lb-amt"><b>RM 6,540</b><span>this month</span></div></div>
        <div class="lb-item"><div class="rankn">8</div><div class="lb-av">SH</div><div class="lb-name"><b>Siti Hajar</b><span>Silver Agent</span></div><div class="lb-amt"><b>RM 6,120</b><span>this month</span></div></div>
        <div class="hint">🔔 You'll get a push notification when your rank changes.</div>
      </div>
    </div>

    <!-- PROFILE -->
    <div class="page" id="page-profile">
      <div class="pagetitle">👤 Profile</div>
      <div class="sec">
        <div class="phead">
          <div class="pav">{{ $agent->initials() }}</div>
          <h2>{{ $agent->name }}</h2>
          <div class="role">{{ $tierIcon }} {{ ucfirst($user->agent_tier) }} Agent · {{ $user->agent_code ?? '#AG-' . str_pad($agent->id, 4, '0', STR_PAD_LEFT) }}{{ $rankInfo['rank'] ? ' · Ranked #' . $rankInfo['rank'] : '' }}</div>
        </div>
        <div class="pstats">
          <div><b>{{ $stats['customers'] }}</b><span>Customers</span></div>
          <div><b>{{ $stats['month_bookings'] }}</b><span>Bookings (Mo)</span></div>
          <div><b>{{ number_format($user->reward_points) }}</b><span>Points</span></div>
        </div>
      </div>
      <div class="sec">
        <div class="lrow" onclick="location.href='{{ route('agent.bookings.index') }}'"><div class="ic i-blue">📋</div><div class="tx"><b>My Bookings</b><span>Create &amp; track bookings</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.wallet.index') }}'"><div class="ic i-green">💰</div><div class="tx"><b>Commission &amp; Withdrawal</b><span>RM {{ number_format($wallet->balance, 2) }} available</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.network') }}'"><div class="ic i-purple">🌐</div><div class="tx"><b>My Network</b><span>Downline &amp; referrals</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.achievements') }}'"><div class="ic i-gold">🏅</div><div class="tx"><b>Achievements</b><span>{{ count($unlockedIds) }} of {{ $totalAchievements }} unlocked</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.leaderboard') }}'"><div class="ic i-teal">🏆</div><div class="tx"><b>Leaderboard</b><span>See where you rank</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.marketing.index') }}'"><div class="ic i-purple">📢</div><div class="tx"><b>Marketing Center</b><span>Posters &amp; materials to download</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.tickets.index') }}'"><div class="ic i-orange">🎧</div><div class="tx"><b>Help &amp; Support</b><span>Open a ticket</span></div><div class="arw">›</div></div>
        <div class="lrow" onclick="location.href='{{ route('agent.notifications') }}'"><div class="ic i-blue">🔔</div><div class="tx"><b>Notifications</b><span>{{ $unreadCount ? $unreadCount . ' unread' : 'All caught up' }}</span></div><div class="arw">›</div></div>
        <div class="lrow logout" onclick="logout()"><div class="ic i-red">🚪</div><div class="tx"><b>Log Out</b><span>Sign out of your agent account</span></div><div class="arw">›</div></div>
      </div>
      <div class="hint">Blue Travel · TAMS Agent Portal v1.0</div>
    </div>

  </div><!-- /screen -->

  <div class="nav" id="nav">
    <button class="on" data-p="home" onclick="go('home')"><span class="ic">🏠</span>Home</button>
    <button data-p="bookings" onclick="location.href='{{ route('agent.bookings.index') }}'"><span class="ic">📋</span>Bookings</button>
    <div class="fabwrap"><div class="fab" onclick="location.href='{{ route('agent.bookings.create') }}'">＋</div></div>
    <button data-p="rewards" onclick="go('rewards')"><span class="ic">🎁</span>Rewards</button>
    <button data-p="profile" onclick="go('profile')"><span class="ic">👤</span>Me</button>
  </div>

  <div class="toast" id="toast"></div>
</div>

<form id="logoutForm" method="POST" action="{{ route('agent.logout') }}" style="display:none">@csrf</form>

<script>
function go(p){
  document.querySelectorAll('.page').forEach(el=>el.classList.remove('active'));
  var pg=document.getElementById('page-'+p);
  if(pg)pg.classList.add('active');
  document.querySelectorAll('.nav button').forEach(b=>b.classList.remove('on'));
  var navP = (p==='rank')?'rewards':p;
  var btn=document.querySelector('.nav button[data-p="'+navP+'"]');
  if(btn)btn.classList.add('on');
  document.getElementById('screen').scrollTop=0;
}
function tog(el){
  el.classList.toggle('done');
  el.querySelector('.mcheck').textContent = el.classList.contains('done')?'✓':'';
  toast(el.classList.contains('done')?'Mission complete! 🎉':'Mission unchecked');
}
function checkin(btn){
  if(btn.classList.contains('claimed'))return;
  btn.classList.add('claimed');
  btn.textContent='✓ Checked in — see you tomorrow!';
  toast('🔥 Streak extended · +30 points');
}
function segf(btn){
  btn.parentElement.querySelectorAll('button').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on');
  toast('Filter: '+btn.textContent);
}
function segp(btn){
  btn.parentElement.querySelectorAll('button').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on');
  toast(btn.textContent+' ranking');
}
function logout(){
  toast('Signing out…');
  setTimeout(function(){document.getElementById('logoutForm').submit();},600);
}
var tt;
function toast(msg){
  var t=document.getElementById('toast');
  t.textContent=msg;t.classList.add('show');
  clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('show'),2600);
}
function copyRef(){
  var link = '{{ url('/agent/login') }}?ref={{ $user->agent_code }}';
  if(navigator.clipboard){navigator.clipboard.writeText(link);}
  toast('🔗 Referral link copied!');
}
@if (session('ok'))
  window.addEventListener('DOMContentLoaded', function(){ toast(@json(session('ok'))); });
@endif
</script>
</body>
</html>
