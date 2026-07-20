@extends('layouts.admin')
@section('title', 'Support Tickets')
@section('console', 'Management')
@section('heading', 'Support Tickets')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-primary">{{ $counts['open'] }}</div><div class="text-secondary small">Open</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="fs-4 fw-bold text-warning">{{ $counts['pending'] }}</div><div class="text-secondary small">Awaiting Customer</div></div></div>
  </div>

  <form class="d-flex gap-2 mb-3" method="GET">
    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any status</option>
      @foreach (\App\Models\Ticket::STATUS_BADGE as $k => $b)<option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>@endforeach
    </select>
    <select name="category" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="">Any category</option>
      @foreach (\App\Models\Ticket::CATEGORIES as $k => $label)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $label }}</option>@endforeach
    </select>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Ticket</th><th>From</th><th>Category</th><th>Priority</th><th>Last reply</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($tickets as $t)
            <tr>
              <td class="fw-semibold small">{{ $t->ticket_no }}<div class="text-secondary" style="font-size:.72rem">{{ Str::limit($t->subject, 40) }}</div></td>
              <td class="small">{{ $t->user?->name }}</td>
              <td class="small">{{ \App\Models\Ticket::CATEGORIES[$t->category] ?? $t->category }}</td>
              <td><span class="badge text-bg-{{ $t->priorityBadge() }}">{{ ucfirst($t->priority) }}</span></td>
              <td class="small">{{ optional($t->last_reply_at)->diffForHumans() }}</td>
              <td><span class="badge text-bg-{{ $t->statusBadge() }}">{{ ucfirst($t->status) }}</span></td>
              <td class="text-end"><a href="{{ route('manage.tickets.show', $t) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-secondary py-5">No tickets.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $tickets->links() }}</div>
@endsection
