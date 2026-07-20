@extends('layouts.agent')
@section('title', 'Achievements')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">🏅 Achievements</div><div class="sub">{{ count($unlockedIds) }} of {{ $achievements->count() }} unlocked</div></div>
  </div>

  <div class="wrap">
    <div class="card">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
        @foreach ($achievements as $ach)
          @php $unlocked = in_array($ach->id, $unlockedIds); @endphp
          <div style="text-align:center;{{ $unlocked ? '' : 'opacity:.4;filter:grayscale(1)' }}">
            <div style="width:60px;height:60px;border-radius:20px;margin:0 auto;display:flex;align-items:center;justify-content:center;font-size:26px;background:{{ $unlocked ? 'linear-gradient(135deg,#fef3c7,#fde68a)' : '#eef1f8' }};box-shadow:{{ $unlocked ? '0 8px 18px rgba(245,179,1,.25)' : 'none' }}">{{ $ach->icon }}</div>
            <div style="font-size:11px;font-weight:700;margin-top:7px;line-height:1.2">{{ $ach->name }}</div>
            @if (! $unlocked)<div style="font-size:9.5px;color:var(--muted);margin-top:2px">🔒 locked</div>@endif
          </div>
        @endforeach
      </div>
    </div>
    <div class="empty" style="padding:16px;font-size:11px">Unlock badges by making sales, recruiting agents, and keeping your streak alive.</div>
  </div>
@endsection
