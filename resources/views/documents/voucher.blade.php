<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #1f2937; font-size: 12px; margin: 0; }
  .banner { background: #0d1b3e; color: #fff; padding: 26px 36px; }
  .banner .brand { font-size: 12px; font-weight: bold; }
  .banner .vt { font-size: 24px; font-weight: bold; margin-top: 4px; }
  .banner .muted { color: #aab4d4; font-size: 11px; }
  .wrap { padding: 26px 36px; }
  table { width: 100%; border-collapse: collapse; }
  .card { background: #f5f7fc; border: 1px solid #e7ecf7; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; }
  .k { color: #6b7280; font-size: 10px; text-transform: uppercase; }
  .v { font-size: 13px; font-weight: bold; color: #0d1b3e; }
  .pill { display: inline-block; padding: 3px 12px; border-radius: 12px; background: #e3f9ed; color: #0f9a53; font-size: 11px; font-weight: bold; }
  .pax th { text-align: left; background: #eef2fb; padding: 6px 10px; font-size: 10px; color: #6b7280; }
  .pax td { padding: 6px 10px; border-bottom: 1px solid #eef1f8; }
  .foot { margin-top: 22px; border-top: 1px solid #eef1f8; padding-top: 12px; color: #6b7280; font-size: 10px; }
</style>
</head>
<body>
<div class="banner">
  <table style="width:auto;margin-bottom:8px"><tr>
    <td style="background:#fff;padding:6px 10px;border-radius:6px">
      <img src="{{ $company->logoPath() }}" alt="" style="height:64px;width:auto;display:block">
    </td>
  </tr></table>
  <div class="brand">{{ $company->name }}</div>
  <div class="vt">TRAVEL VOUCHER</div>
  <div class="muted">Voucher No: TV-{{ $booking->booking_no }} · Issued {{ now()->format('d M Y') }}</div>
</div>

<div class="wrap">
  <table style="margin-bottom:14px">
    <tr>
      <td style="width:70%">
        <div class="k">Package</div>
        <div class="v" style="font-size:16px">{{ $booking->package->title }}</div>
        <div class="muted">{{ $booking->package->destination }} · {{ $booking->package->duration_days }}D{{ $booking->package->duration_nights }}N · {{ $booking->package->categoryLabel() }}</div>
      </td>
      <td style="width:30%; text-align:right">
        <span class="pill">CONFIRMED</span>
      </td>
    </tr>
  </table>

  <table>
    <tr>
      <td style="width:50%; padding-right:8px">
        <div class="card">
          <div class="k">Lead Traveller</div>
          <div class="v">{{ $booking->customer->name }}</div>
          <div class="muted">{{ $booking->customer->phone }} · {{ $booking->customer->email }}</div>
        </div>
      </td>
      <td style="width:50%; padding-left:8px">
        <div class="card">
          <div class="k">Travel Date</div>
          <div class="v">{{ $booking->travel_date?->format('d M Y') ?? 'To be advised' }}</div>
          <div class="muted">{{ $booking->total_pax }} traveller(s) · {{ $booking->adults }}A {{ $booking->children }}C {{ $booking->infants }}I</div>
        </div>
      </td>
    </tr>
  </table>

  @if ($booking->provider)
    <div class="card">
      <div class="k">Service Provider</div>
      <div class="v">{{ $booking->provider->name }}</div>
      <div class="muted">{{ $booking->provider->typeLabel() }}</div>
    </div>
  @endif

  @if ($booking->pax->count())
    <div class="k" style="margin:6px 0">Passenger Manifest</div>
    <table class="pax">
      <thead><tr><th>Name</th><th>Type</th><th>IC / Passport</th></tr></thead>
      <tbody>
        @foreach ($booking->pax as $p)
          <tr><td>{{ $p->name }} @if ($p->is_lead) <strong>(Lead)</strong> @endif</td><td>{{ ucfirst($p->type) }}</td><td>{{ $p->ic_passport_no ?: '—' }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if ($booking->package->itinerary)
    <div class="k" style="margin:14px 0 4px">Itinerary</div>
    <div style="white-space:pre-line; font-size:11px">{{ $booking->package->itinerary }}</div>
  @endif

  <div class="foot">
    Present this voucher upon check-in. Issued by {{ $company->legal_name ?? $company->name }} · {{ $company->phone }}.
  </div>
</div>
</body>
</html>
