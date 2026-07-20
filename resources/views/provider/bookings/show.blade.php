@extends('layouts.admin')
@section('title', 'Booking ' . $booking->booking_no)
@section('console', 'Provider')
@section('heading', 'Booking ' . $booking->booking_no)

@section('nav')
  <a class="nav-link px-2 py-2" href="{{ route('provider.dashboard') }}">🏠 Dashboard</a>
  <a class="nav-link active px-2 py-2" href="{{ route('provider.bookings.index') }}">📋 Incoming Bookings</a>
@endsection

@section('content')
  <div class="d-flex gap-2 align-items-center mb-3">
    <a href="{{ route('provider.bookings.index') }}" class="btn btn-sm btn-outline-secondary">← Back</a>
    <span class="badge text-bg-{{ $booking->statusBadge() }} fs-6">{{ $booking->statusLabel() }}</span>
    <span class="badge text-bg-{{ $booking->provider_status === 'approved' ? 'success' : ($booking->provider_status === 'rejected' ? 'danger' : 'warning') }}">Your response: {{ ucfirst($booking->provider_status) }}</span>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 p-lg-4 mb-3">
        <div class="row g-3">
          <div class="col-md-6"><div class="text-secondary small">Package</div><div class="fw-semibold">{{ $booking->package?->title }}</div></div>
          <div class="col-md-6"><div class="text-secondary small">Customer</div><div class="fw-semibold">{{ $booking->customer?->name }}</div></div>
          <div class="col-md-4"><div class="text-secondary small">Departure</div><div class="fw-semibold">{{ optional($booking->packageDate?->depart_date)->format('d M Y') ?? optional($booking->travel_date)->format('d M Y') ?? '—' }}</div></div>
          <div class="col-md-4"><div class="text-secondary small">Pax</div><div class="fw-semibold">{{ $booking->adults }}A · {{ $booking->children }}C · {{ $booking->infants }}I</div></div>
          <div class="col-md-4"><div class="text-secondary small">Tier</div><div class="fw-semibold">{{ $booking->pricing?->tier_name ?? '—' }}</div></div>
        </div>
        @if ($booking->notes)<div class="mt-3 p-2 bg-light rounded small"><strong>Notes:</strong> {{ $booking->notes }}</div>@endif
      </div>

      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Passengers</h6>
        @if ($booking->pax->isEmpty())
          <div class="text-secondary small">No passenger details provided.</div>
        @else
          <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Type</th><th>IC / Passport</th></tr></thead>
            <tbody>@foreach ($booking->pax as $p)<tr><td>{{ $p->name }}</td><td class="text-capitalize">{{ $p->type }}</td><td>{{ $p->ic_passport_no ?? '—' }}</td></tr>@endforeach</tbody>
          </table></div>
        @endif
      </div>
    </div>

    <div class="col-lg-4">
      @if ($booking->status === 'waiting_provider_confirmation')
        <div class="card p-3 p-lg-4 mb-3">
          <h6 class="fw-bold mb-3">Respond</h6>
          <form method="POST" action="{{ route('provider.bookings.respond', $booking) }}">@csrf
            <input type="hidden" name="decision" id="decision" value="approved">
            <label class="form-label small fw-semibold">Note (optional)</label>
            <textarea name="note" rows="2" class="form-control mb-3" placeholder="Confirmation reference, remarks…"></textarea>
            <div class="d-grid gap-2">
              <button class="btn btn-success" onclick="document.getElementById('decision').value='approved'">✔ Approve Booking</button>
              <button class="btn btn-outline-danger" onclick="document.getElementById('decision').value='rejected'">✕ Reject</button>
            </div>
          </form>
        </div>
      @endif

      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Timeline</h6>
        <ul class="list-unstyled mb-0">
          @foreach ($booking->timeline as $t)
            <li class="d-flex gap-2 pb-3">
              <div class="rounded-circle bg-primary" style="width:10px;height:10px;margin-top:5px;flex:0 0 auto"></div>
              <div><div class="small fw-semibold">{{ $t->action }}</div>@if($t->note)<div class="small text-secondary">{{ $t->note }}</div>@endif<div class="text-secondary" style="font-size:.72rem">{{ $t->created_at->format('d M Y, H:i') }}</div></div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
@endsection
