@extends('layouts.agent')
@section('title', 'Marketing Center')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('agent.dashboard') }}">‹</a>
    <div><div class="t">📢 Marketing Center</div><div class="sub">Posters & materials</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif

  <div class="wrap">
    @forelse ($materials as $m)
      <div class="brow" style="cursor:default">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="font-size:26px">{{ $m->icon() }}</div>
          <div><div class="n">{{ $m->title }}</div><div class="m">{{ $m->categoryLabel() }}@if($m->description) · {{ Str::limit($m->description, 40) }}@endif</div></div>
        </div>
        <a href="{{ route('agent.marketing.download', $m) }}" class="btn ghost" style="width:auto;padding:9px 15px">⬇️</a>
      </div>
    @empty
      <div class="empty">No marketing materials yet.<br>Check back soon!</div>
    @endforelse
  </div>
@endsection
