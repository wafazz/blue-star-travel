@extends('layouts.admin')
@section('title', 'Notifications')
@section('console', 'Management')
@section('heading', 'Notifications')

@section('content')
  <div class="d-flex justify-content-end mb-3">
    <form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark all read</button></form>
  </div>
  <div class="card">
    <div class="list-group list-group-flush">
      @forelse ($items as $n)
        <a href="{{ route('notifications.read', $n) }}" class="list-group-item list-group-item-action d-flex gap-3 {{ $n->isUnread() ? 'bg-primary bg-opacity-10' : '' }}">
          <span class="fs-4">{{ $n->icon }}</span>
          <div class="flex-fill">
            <div class="fw-semibold">{{ $n->title }} @if($n->isUnread())<span class="badge text-bg-primary ms-1">New</span>@endif</div>
            @if ($n->body)<div class="small text-secondary">{{ $n->body }}</div>@endif
            <div class="text-secondary" style="font-size:.72rem">{{ $n->created_at->diffForHumans() }}</div>
          </div>
        </a>
      @empty
        <div class="text-center text-secondary py-5">No notifications.</div>
      @endforelse
    </div>
  </div>
  <div class="mt-3">{{ $items->links() }}</div>
@endsection
