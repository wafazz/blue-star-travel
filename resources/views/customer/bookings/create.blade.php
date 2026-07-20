@extends('layouts.customer')
@section('title', 'Book Trip')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('catalog.show', $package->slug) }}">‹</a>
    <div><div class="t">Book This Trip</div><div class="sub">{{ \Illuminate\Support\Str::limit($package->title, 30) }}</div></div>
  </div>

  @if ($errors->any())<div class="alert err">⚠️ {{ $errors->first() }}</div>@endif

  <div class="wrap">
    <form method="POST" action="{{ route('customer.bookings.store') }}">
      @csrf
      <input type="hidden" name="package_id" value="{{ $package->id }}">

      <div class="card">
        <h3>Trip Details</h3>

        <label class="lbl">Package tier</label>
        <select name="package_pricing_id" class="inp">
          @foreach ($package->pricings as $p)
            <option value="{{ $p->id }}" @selected($p->is_default)>{{ $p->tier_name }} — RM {{ number_format($p->promo_price ?? $p->adult_price, 2) }}/adult</option>
          @endforeach
        </select>

        <label class="lbl">Departure</label>
        <select name="package_date_id" class="inp">
          <option value="">Flexible / contact me</option>
          @foreach ($package->dates->where('status', 'open') as $d)
            <option value="{{ $d->id }}">{{ $d->depart_date->format('d M Y') }} ({{ $d->seats_total - $d->seats_booked }} seats left)</option>
          @endforeach
        </select>

        <label class="lbl">Preferred travel date (optional)</label>
        <input type="date" name="travel_date" value="{{ old('travel_date') }}" class="inp">
      </div>

      <div class="card">
        <h3>Travellers</h3>
        <div class="row2">
          <div>
            <label class="lbl">Adults</label>
            <input type="number" name="adults" min="1" max="20" value="{{ old('adults', 1) }}" class="inp" required>
          </div>
          <div>
            <label class="lbl">Children</label>
            <input type="number" name="children" min="0" max="20" value="{{ old('children', 0) }}" class="inp" required>
          </div>
          <div>
            <label class="lbl">Infants</label>
            <input type="number" name="infants" min="0" max="20" value="{{ old('infants', 0) }}" class="inp" required>
          </div>
        </div>
      </div>

      <div class="card">
        <h3>Extras</h3>
        <label class="lbl">Promo code (optional)</label>
        <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" class="inp" placeholder="e.g. RAYA2026">
        <label class="lbl">Notes / special requests</label>
        <textarea name="notes" rows="3" class="inp" placeholder="Dietary needs, room preference…">{{ old('notes') }}</textarea>
      </div>

      <div style="padding-bottom:20px">
        <button class="btn">🧳 Submit Booking</button>
        <div style="text-align:center;font-size:11.5px;color:var(--muted);margin-top:12px">
          You'll be able to pay by FPX or bank slip on the next screen.
        </div>
      </div>
    </form>
  </div>
@endsection
