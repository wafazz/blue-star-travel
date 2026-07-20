@extends('layouts.agent')
@section('title', 'My Network')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">My Network</div><div class="sub">Your downline</div></div>
  </div>

  <div class="wrap">
    <div class="card" style="display:flex;text-align:center;padding:0;overflow:hidden">
      <div style="flex:1;padding:16px 6px"><div style="font-size:22px;font-weight:800;color:var(--blue)">{{ $directCount }}</div><div class="m" style="font-size:11px">Direct</div></div>
      <div style="flex:1;padding:16px 6px;border-left:1px solid var(--line)"><div style="font-size:22px;font-weight:800;color:var(--blue)">{{ $totalCount }}</div><div class="m" style="font-size:11px">Total Downline</div></div>
      <div style="flex:1;padding:16px 6px;border-left:1px solid var(--line)"><div style="font-size:22px;font-weight:800;color:var(--blue)">{{ auth()->user()->agent_code ?? '—' }}</div><div class="m" style="font-size:11px">My Code</div></div>
    </div>

    <div class="card">
      <h3>Downline Members</h3>
      @forelse ($rows as $r)
        <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--line)">
          <div style="width:{{ 6 + $r->depth * 14 }}px"></div>
          <div style="width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex:0 0 auto">{{ strtoupper(substr($r->name, 0, 1)) }}</div>
          <div style="flex:1"><div style="font-weight:700;font-size:13.5px">{{ $r->name }}</div><div class="m" style="font-size:11px">{{ $r->agent_code ?? 'no code' }} · {{ ucfirst($r->agent_tier) }}</div></div>
          <span class="badge b-{{ $r->depth == 1 ? 'success' : 'secondary' }}">L{{ $r->depth }}</span>
        </div>
      @empty
        <div class="empty" style="padding:26px">No downline yet.<br>Recruit agents to grow your network.</div>
      @endforelse
    </div>
  </div>
@endsection
