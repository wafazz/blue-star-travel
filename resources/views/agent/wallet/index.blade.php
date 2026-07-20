@extends('layouts.agent')
@section('title', 'My Wallet')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">My Wallet</div><div class="sub">Commission earnings</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <div class="wrap">
    <div class="card" style="background:linear-gradient(160deg,#1466ff,#0b3fd1 70%,#082aa0);color:#fff">
      <div style="font-size:12px;opacity:.85;font-weight:700">AVAILABLE BALANCE</div>
      <div style="font-size:32px;font-weight:800;margin:4px 0">RM {{ number_format($wallet->balance, 2) }}</div>
      <div style="display:flex;gap:18px;margin-top:8px;font-size:12px;opacity:.9">
        <div>Earned: <b>RM {{ number_format($wallet->total_earned, 2) }}</b></div>
        <div>Withdrawn: <b>RM {{ number_format($wallet->total_withdrawn, 2) }}</b></div>
      </div>
    </div>

    <div class="card" style="display:flex;justify-content:space-between;align-items:center">
      <div><div class="lbl" style="margin:0">Pending commission</div><div style="font-size:18px;font-weight:800;color:var(--warn)">RM {{ number_format($pendingCommission, 2) }}</div></div>
      <a href="{{ route('agent.commissions') }}" class="btn ghost" style="width:auto;padding:10px 16px">View all →</a>
    </div>

    <div class="card">
      <h3>Withdraw Funds</h3>
      <form method="POST" action="{{ route('agent.wallet.withdraw') }}">
        @csrf
        <label class="lbl">Amount (RM)</label>
        <input type="number" name="amount" step="0.01" min="1" max="{{ number_format($wallet->balance, 2, '.', '') }}" class="inp" placeholder="0.00" required>
        <div class="row2">
          <div><label class="lbl">Bank</label><input type="text" name="bank_name" class="inp" placeholder="Maybank" required></div>
          <div><label class="lbl">Account No</label><input type="text" name="bank_account_no" class="inp" placeholder="1234567890" required></div>
        </div>
        <label class="lbl">Account Holder Name</label>
        <input type="text" name="bank_account_name" class="inp" value="{{ auth()->user()->name }}" required>
        <button class="btn" @disabled($wallet->balance <= 0)>Request Withdrawal</button>
      </form>
    </div>

    <div class="card">
      <h3>Recent Withdrawals</h3>
      @forelse ($withdrawals as $w)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line)">
          <div><div style="font-weight:700;font-size:13px">{{ $w->withdrawal_no }}</div><div class="m" style="font-size:11px">{{ $w->created_at->format('d M Y') }}</div></div>
          <div style="text-align:right"><div style="font-weight:800">RM {{ number_format($w->amount, 2) }}</div><span class="badge b-{{ $w->statusBadge() }}">{{ ucfirst($w->status) }}</span></div>
        </div>
      @empty
        <div class="empty" style="padding:20px">No withdrawals yet.</div>
      @endforelse
    </div>

    <div class="card">
      <h3>Wallet Activity</h3>
      @forelse ($wallet->transactions->take(12) as $t)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--line)">
          <div style="font-size:12.5px">{{ $t->description }}<div class="m" style="font-size:11px">{{ $t->created_at->format('d M Y, H:i') }}</div></div>
          <div style="font-weight:800;color:{{ $t->type === 'credit' ? 'var(--ok)' : 'var(--danger)' }}">{{ $t->type === 'credit' ? '+' : '−' }}RM {{ number_format($t->amount, 2) }}</div>
        </div>
      @empty
        <div class="empty" style="padding:20px">No activity yet.</div>
      @endforelse
    </div>
  </div>
@endsection
