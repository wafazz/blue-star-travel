@extends('layouts.agent')
@section('title', 'My Business Card')

@section('content')
  @php $shareUrl = route('card.public', $user->agent_code); @endphp

  <div class="abar">
    <a class="back" href="{{ route('agent.network') }}">‹</a>
    <div><div class="t">My Business Card</div><div class="sub">Share it with customers and recruits</div></div>
  </div>

  @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <div class="wrap">
    <div style="margin-bottom:14px">
      @include('partials.business-card', ['user' => $user])
    </div>

    <div class="card">
      <h3>Share</h3>
      <div class="m" style="font-size:12px;margin-bottom:8px">Anyone with this link can see your card and save your contact.</div>
      <div style="display:flex;gap:8px;align-items:center">
        <input id="cardLink" type="text" readonly value="{{ $shareUrl }}"
               style="flex:1;min-width:0;border:1px solid var(--line);border-radius:10px;padding:9px 11px;font-size:11.5px;background:#f7f9fc;color:#334">
        <button type="button" onclick="copyCardLink(this)"
                style="border:none;border-radius:10px;padding:10px 13px;font-size:12px;font-weight:800;background:var(--blue);color:#fff;flex:0 0 auto">Copy</button>
      </div>
      @include('partials.share-card-button', ['shareUrl' => $shareUrl, 'shareName' => $user->name, 'class' => 'btn', 'style' => 'margin-top:10px'])
      <a href="{{ route('card.vcard', $user->agent_code) }}" class="btn ghost" style="margin-top:10px;text-align:center">Download contact (.vcf)</a>
    </div>

    <form method="POST" action="{{ route('agent.card.update') }}">
      @csrf
      @method('PUT')
      <div class="card">
        <h3>Card Details</h3>

        <label class="lbl">Name</label>
        <input type="text" value="{{ $user->name }}" class="inp" disabled>
        <div class="m" style="font-size:11px;margin:4px 0 12px">Edit your name on <a href="{{ route('customer.profile.edit') }}">My Profile</a>.</div>

        <label class="lbl">Title / Designation</label>
        <input type="text" name="title" value="{{ old('title', $user->title) }}" class="inp" maxlength="80" placeholder="e.g. Sales Executive">

        <label class="lbl">Phone No.</label>
        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="inp" maxlength="30" placeholder="01X-XXX XXXX">

        <label class="lbl">Email</label>
        <input type="email" value="{{ $user->email }}" class="inp" disabled>
        <div class="m" style="font-size:11px;margin:4px 0 12px">Changed by HQ — it is also your login.</div>

        <label class="lbl">Member ID</label>
        <input type="text" value="{{ $user->agent_code }}" class="inp" disabled>
      </div>

      <button class="btn" style="margin-bottom:20px">Save Card</button>
    </form>
  </div>

  <script>
    function copyCardLink(btn){
      const f = document.getElementById('cardLink');
      const done = () => { btn.textContent = 'Copied ✓'; setTimeout(() => btn.textContent = 'Copy', 1800); };
      f.select(); f.setSelectionRange(0, 99999);
      if (navigator.clipboard) {
        navigator.clipboard.writeText(f.value).then(done, () => { document.execCommand('copy'); done(); });
      } else {
        document.execCommand('copy'); done();
      }
    }
  </script>
@endsection
