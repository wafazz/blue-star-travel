@extends('layouts.agent')
@section('title', 'Leaderboard')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">🏆 Leaderboard</div><div class="sub">{{ $period }} · by sales</div></div>
  </div>

  <div class="wrap">
    @if ($board->isEmpty())
      <div class="empty">No ranked sales this month yet.<br>Close a booking to climb the board!</div>
    @else
      @foreach ($board as $row)
        <div class="brow {{ $row->user_id === ($me->user_id ?? 0) ? '' : '' }}" style="{{ $row->user_id === ($me->user_id ?? 0) ? 'border:2px solid var(--blue)' : '' }}">
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:30px;text-align:center;font-weight:800;color:{{ $row->rank <= 3 ? 'var(--gold)' : 'var(--muted)' }}">
              {{ $row->rank == 1 ? '🥇' : ($row->rank == 2 ? '🥈' : ($row->rank == 3 ? '🥉' : '#' . $row->rank)) }}
            </div>
            <div style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px">{{ strtoupper(substr($row->name, 0, 2)) }}</div>
            <div>
              <div class="n">{{ $row->user_id === ($me->user_id ?? 0) ? 'You (' . strtok($row->name, ' ') . ')' : $row->name }}</div>
              <div class="m">{{ ucfirst($row->agent_tier) }} · {{ $row->bookings }} booking(s)</div>
            </div>
          </div>
          <div style="text-align:right"><div style="font-weight:800">RM {{ number_format($row->sales, 0) }}</div><div class="m" style="font-size:11px">this month</div></div>
        </div>
      @endforeach
    @endif
    <div class="empty" style="padding:16px;font-size:11px">🔔 Rank updates as sales are confirmed and paid.</div>
  </div>
@endsection
