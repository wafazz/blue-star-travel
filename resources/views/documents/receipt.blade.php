<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #1f2937; font-size: 12px; margin: 0; }
  .wrap { padding: 32px 36px; }
  .head td { vertical-align: top; }
  .brand { font-size: 12px; font-weight: bold; color: #0d1b3e; }
  .muted { color: #6b7280; font-size: 11px; }
  .title { font-size: 26px; font-weight: bold; color: #16b364; text-align: right; }
  .pill { display: inline-block; padding: 3px 10px; border-radius: 10px; background: #e4f7ec; color: #0e9455; font-size: 11px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  .meta td { padding: 2px 0; }
  .items { margin-top: 18px; }
  .items th { background: #0d1b3e; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; }
  .items td { padding: 8px 10px; border-bottom: 1px solid #eef1f8; }
  .right { text-align: right; }
  .totals td { padding: 4px 10px; }
  .grand { font-size: 15px; font-weight: bold; color: #16b364; }
  .stamp { margin-top: 20px; display: inline-block; border: 2px solid #16b364; color: #16b364; font-weight: bold; padding: 6px 16px; border-radius: 8px; transform: rotate(-4deg); font-size: 14px; }
  .foot { margin-top: 28px; border-top: 1px solid #eef1f8; padding-top: 12px; color: #6b7280; font-size: 10px; }
</style>
</head>
<body>
<div class="wrap">
  <table class="head">
    <tr>
      <td>
        <img src="{{ $company->logoPath() }}" alt="" style="height:92px; width:auto; margin-bottom:6px">
        <div class="brand">{{ $company->name }}</div>
        <div class="muted">
          {{ $company->address }}<br>
          {{ $company->postcode }} {{ $company->city }}, {{ $company->state }}<br>
          {{ $company->phone }} · {{ $company->email }}<br>
          @if ($company->license_no) License: {{ $company->license_no }} @endif
        </div>
      </td>
      <td>
        <div class="title">RECEIPT</div>
        <table class="meta" style="margin-top:8px">
          <tr><td class="muted right">Receipt No</td><td class="right"><strong>RC-{{ $booking->booking_no }}</strong></td></tr>
          <tr><td class="muted right">Booking No</td><td class="right">{{ $booking->booking_no }}</td></tr>
          <tr><td class="muted right">Date</td><td class="right">{{ now()->format('d M Y') }}</td></tr>
          <tr><td class="muted right">Status</td><td class="right"><span class="pill">PAID</span></td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-top:22px">
    <tr>
      <td style="width:50%">
        <div class="muted">RECEIVED FROM</div>
        <strong>{{ $booking->customer->name }}</strong><br>
        <span class="muted">{{ $booking->customer->email }}<br>{{ $booking->customer->phone }}</span>
      </td>
      <td style="width:50%">
        <div class="muted">FOR</div>
        <strong>{{ $booking->package->title }}</strong><br>
        <span class="muted">{{ $booking->package->destination }} · {{ $booking->travel_date?->format('d M Y') }}</span>
      </td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr><th>Payment</th><th class="right">Method</th><th class="right">Date</th><th class="right">Amount (RM)</th></tr>
    </thead>
    <tbody>
      @foreach ($booking->payments->where('status', 'verified')->sortBy('paid_at') as $pay)
        <tr>
          <td>{{ ucfirst($pay->type) }} payment @if($pay->reference)<br><span class="muted">Ref: {{ $pay->reference }}</span>@endif</td>
          <td class="right">{{ $pay->methodLabel() }}</td>
          <td class="right">{{ optional($pay->paid_at)->format('d M Y') }}</td>
          <td class="right">{{ number_format($pay->amount, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table style="margin-top:10px">
    <tr>
      <td style="width:55%"><div class="stamp">PAID IN FULL</div></td>
      <td style="width:45%">
        <table class="totals">
          <tr><td class="muted">Total Amount</td><td class="right">RM {{ number_format($booking->total_amount, 2) }}</td></tr>
          <tr><td class="grand" style="border-top:1px solid #ddd">Total Paid</td><td class="right grand" style="border-top:1px solid #ddd">RM {{ number_format($booking->paid_amount, 2) }}</td></tr>
          <tr><td class="muted">Balance</td><td class="right">RM {{ number_format($booking->balance(), 2) }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="foot">
    This is a computer-generated official receipt issued by {{ $company->legal_name ?? $company->name }}. Thank you for your payment.
  </div>
</div>
</body>
</html>
