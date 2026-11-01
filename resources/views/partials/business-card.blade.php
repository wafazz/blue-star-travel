{{-- Digital business card. $user is the agent. Shows only the five card fields. --}}
<div class="bcard">
  <div class="bc-brand">
    <img src="{{ asset('images/logo.png') }}" alt="Blue Star Travel &amp; Tour" class="bc-logo">
  </div>
  <div class="bc-body">
    <div class="bc-name">{{ $user->name }}</div>
    @if ($user->title)
      <div class="bc-title">{{ $user->title }}</div>
    @endif
    <div class="bc-rule"></div>
    <ul class="bc-list">
      @if ($user->phone)
        <li><span class="bc-ic">☎</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', $user->phone) }}">{{ $user->phone }}</a></li>
      @endif
      @if ($user->email)
        <li><span class="bc-ic">✉</span><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></li>
      @endif
      @if ($user->agent_code)
        <li><span class="bc-ic">#</span><span>Member ID: <strong>{{ $user->agent_code }}</strong></span></li>
      @endif
    </ul>
  </div>
</div>

<style>
  .bcard{display:flex;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 12px 32px rgba(16,42,110,.18);
    border-top:6px solid #1466ff;max-width:520px;width:100%;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#0d1b3e}
  .bc-brand{flex:0 0 34%;display:flex;align-items:center;justify-content:center;padding:14px;
    background:linear-gradient(160deg,#eaf2ff 0%,#fff 70%);border-right:1px solid #e3ebf8}
  .bc-logo{width:100%;max-width:120px;height:auto}
  .bc-body{flex:1;padding:18px 18px 16px;min-width:0}
  .bc-name{font-family:Georgia,"Times New Roman",serif;font-size:24px;font-weight:700;color:#0b3fd1;line-height:1.1;overflow-wrap:anywhere}
  .bc-title{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#4a5a7a;margin-top:6px;font-weight:600}
  .bc-rule{height:2px;background:#1466ff;margin:12px 0 10px;width:85%;border-radius:2px}
  .bc-list{list-style:none;padding:0;margin:0;font-size:13.5px}
  .bc-list li{display:flex;align-items:center;gap:9px;margin-bottom:7px;min-width:0}
  .bc-list a,.bc-list span{color:#0d1b3e;text-decoration:none;overflow-wrap:anywhere}
  .bc-ic{flex:0 0 22px;height:22px;border-radius:50%;background:#1466ff;color:#fff;font-size:11px;
    display:inline-flex;align-items:center;justify-content:center}
  @media (max-width:420px){ .bcard{flex-direction:column} .bc-brand{flex:none;border-right:0;border-bottom:1px solid #e3ebf8} }
</style>
