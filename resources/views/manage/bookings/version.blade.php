@extends('layouts.admin')
@section('title', $booking->booking_no . ' — Version ' . $version->version)
@section('console', 'Management')
@section('heading', 'Version ' . $version->version . ' — ' . $booking->booking_no)

@section('content')
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="{{ route('manage.bookings.show', $booking) }}" class="btn btn-sm btn-outline-secondary">← Back to booking</a>
    <span class="badge text-bg-light border">{{ $version->reasonLabel() }}</span>
    <span class="text-secondary small">
      {{ $version->author?->name ?? 'System' }} · {{ $version->created_at->format('d M Y, H:i') }}
    </span>
  </div>

  @if ($version->revisionRequest)
    <div class="card p-3 p-lg-4 mb-3 border-warning">
      <h6 class="fw-bold mb-2">Answered this revision request</h6>
      <div class="small">{{ $version->revisionRequest->remark }}</div>
      <div class="text-secondary small mt-1">
        Fields asked for: {{ implode(', ', $version->revisionRequest->fieldLabels()) }}
        · {{ $version->revisionRequest->requester?->name ?? 'System' }}
      </div>
    </div>
  @endif

  <div class="card p-3 p-lg-4 mb-3">
    <h6 class="fw-bold mb-3">What changed in this version</h6>
    @include('manage.bookings._diff', ['rows' => $version->changes ?? []])
  </div>

  @php $p = $version->payload; @endphp

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Customer as at this version</h6>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Name</span><span>{{ data_get($p, 'customer.name') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Phone</span><span>{{ data_get($p, 'customer.phone') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Email</span><span>{{ data_get($p, 'customer.email') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small"><span class="text-secondary">IC / Passport</span><span>{{ data_get($p, 'customer.ic_passport_no') ?? '—' }}</span></div>
      </div>

      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Travel &amp; pickup</h6>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Package</span><span>{{ data_get($p, 'booking.package_title') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Departure</span><span>{{ data_get($p, 'booking.departure_label') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Travel date</span><span>{{ data_get($p, 'booking.travel_date') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Pickup</span><span>{{ data_get($p, 'booking.pickup_location') ?? '—' }}</span></div>
        <div class="d-flex justify-content-between small"><span class="text-secondary">Arrival time</span><span>{{ data_get($p, 'booking.arrival_time') ?? '—' }}</span></div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card p-3 p-lg-4 mb-3">
        <h6 class="fw-bold mb-3">Rooms</h6>
        @forelse (data_get($p, 'rooms', []) as $room)
          <div class="d-flex justify-content-between small mb-1">
            <span>{{ $room['room_name'] ?? 'Room' }}</span>
            <span class="text-secondary">{{ $room['adults'] ?? 0 }}A · {{ $room['children'] ?? 0 }}C · {{ $room['seniors'] ?? 0 }}S · {{ $room['infants'] ?? 0 }}I</span>
          </div>
        @empty
          <div class="text-secondary small">No room lines.</div>
        @endforelse
        <hr class="my-2">
        <div class="d-flex justify-content-between"><span class="fw-semibold">Total</span><span class="fw-bold">RM {{ number_format((float) data_get($p, 'money.total_amount', 0), 2) }}</span></div>
      </div>

      <div class="card p-3 p-lg-4">
        <h6 class="fw-bold mb-3">Passengers</h6>
        @forelse (data_get($p, 'pax', []) as $pax)
          <div class="d-flex justify-content-between small mb-1">
            <span>{{ $pax['name'] ?? '—' }}</span>
            @php
              $bits = array_filter([
                $pax['type'] ?? 'adult',
                isset($pax['age']) && $pax['age'] !== null ? $pax['age'] . ' yrs' : null,
                $pax['dob'] ?? null,
              ]);
            @endphp
            <span class="text-secondary text-capitalize">{{ implode(' · ', $bits) }}</span>
          </div>
        @empty
          <div class="text-secondary small">No passenger details captured.</div>
        @endforelse
      </div>
    </div>
  </div>
@endsection
