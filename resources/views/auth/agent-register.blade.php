<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Blue Travel — Become an Agent</title>
@include('partials.favicon')
@include('partials.pwa', ['portal' => 'agent'])
@include('auth.partials.mobile-styles')
</head>
<body>
<div class="app">

  <div class="hero">
    <div class="logo"><img src="{{ asset('images/logo.png') }}" alt="Blue Star Travel &amp; Tours"></div>
    <p>Agent Portal · Travel &amp; Tour Management</p>
    <div class="badge">🤝 Join Our Agent Network</div>
  </div>

  <div class="card">
    <h2>Become an agent 🚀</h2>
    <div class="sub">Sell packages, build your network &amp; earn commission</div>

    @if ($errors->any())
      <div class="err">⚠️ <span>{{ $errors->first() }}</span></div>
    @endif

    <form method="POST" action="{{ route('agent.register') }}">
      @csrf
      <input type="hidden" name="ref" value="{{ old('ref', $ref) }}">

      <div class="field">
        <label>Full Name</label>
        <div class="inp">
          <span class="ic">👤</span>
          <input type="text" name="name" value="{{ old('name') }}" placeholder="Your name as per IC">
        </div>
      </div>

      <div class="field">
        <label>Email</label>
        <div class="inp">
          <span class="ic">📧</span>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="you@email.com" autocomplete="username">
        </div>
      </div>

      <div class="field">
        <label>Phone</label>
        <div class="inp">
          <span class="ic">📱</span>
          <input type="text" name="phone" value="{{ old('phone') }}" placeholder="01X-XXX XXXX">
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="inp">
          <span class="ic">🔑</span>
          <input type="password" name="password" id="pass" placeholder="Minimum 8 characters" autocomplete="new-password">
          <span class="eye" onclick="togPass(this)">👁️</span>
        </div>
      </div>

      <div class="field">
        <label>Confirm Password</label>
        <div class="inp">
          <span class="ic">🔒</span>
          <input type="password" name="password_confirmation" placeholder="Re-enter your password" autocomplete="new-password">
        </div>
      </div>

      @if ($upline)
        <div class="hintbox" style="margin:0 0 14px">🤝 Sponsored by <b>{{ $upline->name }}</b> ({{ $upline->agent_code }})</div>
      @elseif ($ref)
        <div class="hintbox" style="margin:0 0 14px">⚠️ Referral code <b>{{ $ref }}</b> was not recognised — you will be registered without an upline.</div>
      @endif

      <div class="hintbox" style="margin:0 0 14px">⏳ Applications are reviewed by HQ. You can sign in once your account is approved.</div>

      <button type="submit" class="btn">
        <span class="lbl">Submit Application →</span>
        <span class="spin"></span>
      </button>
    </form>
  </div>

  <div class="foot">
    Already an agent? <a href="{{ route('agent.login') }}">Sign in</a>
  </div>
</div>

<div class="toast" id="toast"></div>
</body>
</html>
