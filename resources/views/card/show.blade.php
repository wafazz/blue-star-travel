<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $user->name }} — Blue Star Travel &amp; Tour</title>
@include('partials.favicon')
<style>
  :root{color-scheme:light}
  body{margin:0;min-height:100vh;background:linear-gradient(160deg,#1466ff 0%,#0b3fd1 60%,#082aa0 100%);
    display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 16px;box-sizing:border-box;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  .actions{width:100%;max-width:520px;margin-top:18px;display:flex;flex-direction:column;gap:10px}
  .actions a{display:block;text-align:center;border-radius:14px;padding:14px;font-size:15px;font-weight:800;text-decoration:none}
  .save{background:#fff;color:#0b3fd1}
  .alt{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.35)}
  .actions button{display:block;width:100%;border-radius:14px;padding:14px;font:inherit;font-size:15px;font-weight:800;cursor:pointer;
    background:#fff;color:#0b3fd1;border:none}
  .foot{color:rgba(255,255,255,.75);font-size:12px;margin-top:18px}
</style>
</head>
<body>
  @include('partials.business-card', ['user' => $user])

  <div class="actions">
    @php $shareUrl = url()->current(); @endphp
    @include('partials.share-card-button', ['shareUrl' => $shareUrl, 'shareName' => $user->name, 'class' => 'share'])
    <a class="save" href="{{ $user->agent_code ? route('card.vcard', $user->agent_code) : route('card.staff.vcard', $user) }}">Save contact</a>
    @if ($user->phone)
      <a class="alt" href="tel:{{ preg_replace('/[^0-9+]/', '', $user->phone) }}">Call {{ $user->name }}</a>
    @endif
    @if ($user->email)
      <a class="alt" href="mailto:{{ $user->email }}">Email {{ $user->name }}</a>
    @endif
  </div>
  <div class="foot">Blue Star Travel &amp; Tour</div>
</body>
</html>
