@extends('layouts.agent')
@section('title', 'Notifications')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">🔔 Notifications</div><div class="sub">{{ $items->total() }} total</div></div>
  </div>

  <div class="wrap">
    <form method="POST" action="{{ route('notifications.readAll') }}" style="margin-bottom:12px"><button class="btn ghost">Mark all read</button>@csrf</form>
    @forelse ($items as $n)
      <a class="brow" href="{{ route('notifications.read', $n) }}" style="{{ $n->isUnread() ? 'border-left:3px solid var(--blue)' : '' }}">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="font-size:22px">{{ $n->icon }}</div>
          <div><div class="n">{{ $n->title }}</div><div class="m">{{ $n->body ? Str::limit($n->body, 44) . ' · ' : '' }}{{ $n->created_at->diffForHumans() }}</div></div>
        </div>
        @if ($n->isUnread())<span class="badge b-info">New</span>@endif
      </a>
    @empty
      <div class="empty">No notifications yet.</div>
    @endforelse
    <div style="padding:10px 0">{{ $items->links() }}</div>
  </div>
@endsection
