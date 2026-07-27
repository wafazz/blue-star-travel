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
  .title { font-size: 26px; font-weight: bold; color: #0d1b3e; text-align: right; }
  .pill { display: inline-block; padding: 3px 10px; border-radius: 10px; background: #e6efff; color: #1466ff; font-size: 11px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  .meta td { padding: 2px 0; }
  .items { margin-top: 18px; }
  .items th { background: #0d1b3e; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; }
  .items td { padding: 8px 10px; border-bottom: 1px solid #eef1f8; }
  .right { text-align: right; }
  .totals td { padding: 4px 10px; }
  .grand { font-size: 15px; font-weight: bold; color: #0d1b3e; }
  .foot { margin-top: 28px; border-top: 1px solid #eef1f8; padding-top: 12px; color: #6b7280; font-size: 10px; }
  .bankbox { margin-top: 16px; background: #f5f7fc; border: 1px solid #e7ecf7; border-radius: 8px; padding: 10px 12px; font-size: 11px; }
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
        <div class="title">INVOICE</div>
        <table class="meta" style="margin-top:8px">
          <tr><td class="muted right">Invoice No</td><td class="right"><strong>INV-{{ $booking->booking_no }}</strong></td></tr>
          <tr><td class="muted right">Booking No</td><td class="right">{{ $booking->booking_no }}</td></tr>
          <tr><td class="muted right">Date</td><td class="right">{{ $booking->confirmed_at?->format('d M Y') ?? now()->format('d M Y') }}</td></tr>
          <tr><td class="muted right">Status</td><td class="right"><span class="pill">{{ $booking->statusLabel() }}</span></td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-top:22px">
    <tr>
      <td style="width:50%">
        <div class="muted">BILL TO</div>
        <strong>{{ $booking->customer->name }}</strong><br>
        <span class="muted">{{ $booking->customer->email }}<br>{{ $booking->customer->phone }}</span>
      </td>
      <td style="width:50%">
        <div class="muted">SOLD BY</div>
        <strong>{{ $booking->agent->name ?? $company->name }}</strong><br>
        <span class="muted">Travel Consultant</span>
      </td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr><th>Description</th><th class="right">Pax</th><th class="right">Unit (RM)</th><th class="right">Amount (RM)</th></tr>
    </thead>
    <tbody>
      @php
        // Rooms carry snapshotted rates. Pre-room bookings fall back to the booking's own columns.
        $lines = $booking->rooms->isNotEmpty()
          ? $booking->rooms
          : collect([(object) [
              'room_name' => $booking->pricing?->tier_name ?? 'Standard', 'rooms' => 1,
              'adults' => $booking->adults, 'children' => $booking->children,
              'seniors' => $booking->seniors, 'infants' => $booking->infants,
              'adult_price' => $booking->adult_price, 'child_price' => $booking->child_price,
              'senior_price' => $booking->senior_price, 'infant_price' => $booking->infant_price,
            ]]);
        $first = true;
      @endphp
      @foreach ($lines as $line)
        @foreach (['adult' => 'adults', 'child' => 'children', 'senior' => 'seniors', 'infant' => 'infants'] as $type => $column)
          @php $count = (int) $line->{$column}; $rate = (float) $line->{$type . '_price'}; @endphp
          @if ($count > 0)
            <tr>
              <td>
                {{ $booking->package->title }} — {{ $line->room_name }} · {{ ucfirst($type) }}
                @if ($first)
                  <br><span class="muted">{{ $booking->package->destination }} · {{ $booking->travel_date?->format('d M Y') }}</span>
                  @php $first = false; @endphp
                @endif
              </td>
              <td class="right">{{ $count }}</td>
              <td class="right">{{ number_format($rate, 2) }}</td>
              <td class="right">{{ number_format($count * $rate, 2) }}</td>
            </tr>
          @endif
        @endforeach
      @endforeach
    </tbody>
  </table>

  <table style="margin-top:10px">
    <tr>
      <td style="width:55%"></td>
      <td style="width:45%">
        <table class="totals">
          <tr><td class="muted">Subtotal</td><td class="right">RM {{ number_format($booking->subtotal, 2) }}</td></tr>
          <tr><td class="muted">Discount</td><td class="right">- RM {{ number_format($booking->discount, 2) }}</td></tr>
          <tr><td class="grand" style="border-top:1px solid #ddd">Total</td><td class="right grand" style="border-top:1px solid #ddd">RM {{ number_format($booking->total_amount, 2) }}</td></tr>
          <tr><td class="muted">Paid</td><td class="right">RM {{ number_format($booking->paid_amount, 2) }}</td></tr>
          @if ($booking->forfeited_amount > 0)
            <tr><td class="muted">Cancellation charge ({{ $booking->forfeited_packs }} pack)</td><td class="right">- RM {{ number_format($booking->forfeited_amount, 2) }}</td></tr>
          @endif
          <tr><td class="muted">Balance</td><td class="right"><strong>RM {{ number_format(max(0, $booking->balance()), 2) }}</strong></td></tr>
          @if ($booking->refundableAmount() > 0)
            <tr><td class="muted">Refundable</td><td class="right"><strong>RM {{ number_format($booking->refundableAmount(), 2) }}</strong></td></tr>
          @endif
        </table>
      </td>
    </tr>
  </table>

  @if ($company->bank_name)
    <div class="bankbox">
      <strong>Payment to:</strong> {{ $company->bank_name }} · {{ $company->bank_account_no }} · {{ $company->bank_account_name }}
    </div>
  @endif

  <div class="foot">
    This is a computer-generated invoice issued by {{ $company->legal_name ?? $company->name }}. Thank you for booking with us.
  </div>
</div>
</body>
</html>
