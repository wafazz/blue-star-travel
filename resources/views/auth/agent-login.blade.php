<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Blue Travel — Agent Login</title>
@include('partials.favicon')
@include('partials.pwa', ['portal' => 'agent'])
@include('auth.partials.mobile-styles')
</head>
<body>
<div class="app">

  <div class="hero">
    <div class="logo"><img src="{{ asset('images/logo.png') }}" alt="Blue Star Travel &amp; Tours"></div>
    <p>Agent Portal · Travel &amp; Tour Management</p>
    <div class="badge">🔒 Secure Agent Sign In</div>
  </div>

  <div class="card">
    <h2>Welcome back 👋</h2>
    <div class="sub">Sign in to your agent account to continue</div>

    @if ($errors->any())
      <div class="err">⚠️ <span>{{ $errors->first() }}</span></div>
    @endif

    <form id="loginForm" method="POST" action="{{ route('agent.login') }}" onsubmit="return doLogin()">
      @csrf
      <div class="field">
        <label>Agent ID / Email</label>
        <div class="inp">
          <span class="ic">👤</span>
          <input type="text" name="email" value="{{ old('email') }}" placeholder="agent@bluetravel.com" autocomplete="username">
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="inp">
          <span class="ic">🔑</span>
          <input type="password" name="password" id="pass" placeholder="Enter your password" autocomplete="current-password">
          <span class="eye" onclick="togPass(this)">👁️</span>
        </div>
      </div>

      <div class="row">
        <label class="remember on" id="remember" onclick="togRemember()">
          <span class="box">✓</span> Remember me
          <input type="checkbox" name="remember" id="rememberInput" checked hidden>
        </label>
        <a class="forgot" onclick="toast('Password reset requires HQ — contact support 📧')">Forgot password?</a>
      </div>

      <button type="submit" class="btn" id="loginBtn">
        <span class="lbl">Sign In →</span>
        <span class="spin"></span>
      </button>
    </form>

    <div class="divider">or continue with</div>
    <div class="socials">
      <div class="soc" onclick="toast('Google sign in — coming soon')">🟢 Google</div>
      <div class="soc" onclick="toast('WhatsApp OTP — coming soon')">💬 WhatsApp</div>
    </div>
  </div>

  <div style="padding:0 20px">
    <button type="button" data-install-app hidden
            style="width:100%;border:none;border-radius:14px;padding:13px;font-size:14px;font-weight:800;
                   background:#fff;color:#0b3fd1;box-shadow:0 8px 20px rgba(16,42,110,.14);cursor:pointer">
      ⬇️ Install Agent app
    </button>
  </div>

  <div class="hintbox" data-ios-install hidden style="display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;margin-top:2px">
    <span>Install Agent app: tap</span>
    <svg width="13" height="16" viewBox="0 0 20 24" fill="none" aria-hidden="true" style="vertical-align:-2px">
      <path d="M10 1.5v13" stroke="#2a55b8" stroke-width="2" stroke-linecap="round"/>
      <path d="M6 5.2 10 1.4l4 3.8" stroke="#2a55b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M4.2 9.5H3a1.5 1.5 0 0 0-1.5 1.5v10A1.5 1.5 0 0 0 3 22.5h14a1.5 1.5 0 0 0 1.5-1.5V11A1.5 1.5 0 0 0 17 9.5h-1.2"
            stroke="#2a55b8" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>Share, then <b>Add to Home Screen</b></span>
  </div>

  <div class="hintbox">💡 Demo: agent@bluetravel.com / password</div>

  <div class="foot">
    New agent? <a onclick="toast('Registration requires HQ approval')">Request an account</a>
  </div>
</div>

<div class="toast" id="toast"></div>
</body>
</html>
