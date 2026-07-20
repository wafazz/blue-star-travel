<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FPX Payment — Blue Travel</title>
@include('partials.favicon')
<style>
  *{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  body{background:#dfe6f2;color:#0d1b3e;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .box{width:100%;max-width:440px;background:#fff;border-radius:20px;box-shadow:0 20px 50px rgba(16,42,110,.18);overflow:hidden}
  .top{background:linear-gradient(160deg,#1466ff,#0b3fd1 60%,#082aa0);color:#fff;padding:22px 24px}
  .top .fpx{font-size:12px;font-weight:700;opacity:.85;letter-spacing:.1em}
  .top .amt{font-size:30px;font-weight:800;margin-top:6px}
  .top .ref{font-size:12px;opacity:.85;margin-top:4px}
  .body{padding:22px 24px}
  .lbl{font-size:12px;font-weight:700;color:#7a86a8;margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em}
  .banks{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
  .bank{border:1.5px solid #e3e9f5;border-radius:12px;padding:12px;font-size:13px;font-weight:600;cursor:pointer;text-align:center;transition:.15s}
  .bank:hover{border-color:#1466ff}
  .bank.on{border-color:#1466ff;background:#eef4ff;color:#0b3fd1}
  .row{display:flex;gap:10px;margin-top:6px}
  .btn{flex:1;border:none;border-radius:14px;padding:14px;font-size:14px;font-weight:800;cursor:pointer}
  .btn.pay{background:linear-gradient(135deg,#16b364,#0e9455);color:#fff}
  .btn.cancel{background:#eef2fb;color:#7a86a8}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  .note{font-size:11px;color:#a3adca;text-align:center;margin-top:16px;line-height:1.5}
  .cust{font-size:13px;color:#5c6a90;margin-bottom:18px}
</style>
</head>
<body>
<div class="box">
  <div class="top">
    <div class="fpx">⚡ FPX ONLINE BANKING · SANDBOX</div>
    <div class="amt">RM {{ number_format($payment->amount, 2) }}</div>
    <div class="ref">Ref: {{ $payment->gateway_ref }} · {{ $payment->booking->booking_no }}</div>
  </div>
  <div class="body">
    <div class="cust">Paying for <strong>{{ $payment->booking->customer?->name }}</strong></div>
    <div class="lbl">Select your bank</div>
    <div class="banks">
      @foreach ($banks as $code => $name)
        <div class="bank" data-name="{{ $name }}" onclick="pick(this)">{{ $name }}</div>
      @endforeach
    </div>
    <form method="POST" action="{{ route('gateway.callback', $payment->gateway_ref) }}">
      @csrf
      <input type="hidden" name="bank" id="bank">
      <div class="row">
        <button class="btn pay" id="payBtn" name="result" value="success" disabled>Pay Now</button>
        <button class="btn cancel" name="result" value="fail" formnovalidate>Cancel</button>
      </div>
    </form>
    <div class="note">This is a sandbox gateway for development. No real bank transaction occurs.<br>Select a bank, then choose Pay to simulate a successful authorisation.</div>
  </div>
</div>
<script>
  function pick(el){
    document.querySelectorAll('.bank').forEach(b=>b.classList.remove('on'));
    el.classList.add('on');
    document.getElementById('bank').value = el.dataset.name;
    document.getElementById('payBtn').disabled = false;
  }
</script>
</body>
</html>
