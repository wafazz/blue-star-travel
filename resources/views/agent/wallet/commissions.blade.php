@extends('layouts.agent')
@section('title', 'My Commissions')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.wallet.index') }}">‹</a>
    <div><div class="t">Commissions</div><div class="sub">{{ $commissions->total() }} entries</div></div>
  </div>

  <div class="wrap">
    @forelse ($commissions as $c)
      <div class="brow" style="cursor:default">
        <div>
          <div class="n">{{ $c->booking?->booking_no }} · L{{ $c->level }}</div>
          <div class="m">from {{ $c->sourceAgent?->name ?? '—' }} · {{ $c->period }} · {{ rtrim(rtrim(number_format($c->percent, 2), '0'), '.') }}% of RM {{ number_format($c->base_amount, 0) }}</div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:800">RM {{ number_format($c->amount, 2) }}</div>
          <span class="badge b-{{ $c->statusBadge() }}">{{ ucfirst($c->status) }}</span>
        </div>
      </div>
    @empty
      <div class="empty">No commissions yet.<br>Earnings appear once your sales are fully paid.</div>
    @endforelse
    <div style="padding:10px 0">{{ $commissions->links() }}</div>
  </div>
@endsection
