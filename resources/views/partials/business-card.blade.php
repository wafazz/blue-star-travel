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
  /* Fixed 85 x 55 mm business card (1.545 : 1). Text is sized in cqw, relative to the card's
     own width, so the layout keeps its proportions from a phone to a desktop. */
  .bcard{display:flex;background:#fff;border-radius:3.5cqw;overflow:hidden;container-type:inline-size;
    aspect-ratio:85 / 55;width:100%;max-width:520px;margin:0 auto;box-shadow:0 12px 32px rgba(16,42,110,.18);
    border-top:1.2cqw solid #1466ff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#0d1b3e;box-sizing:border-box}
  .bc-brand{flex:0 0 34%;display:flex;align-items:center;justify-content:center;padding:4cqw;
    background:linear-gradient(160deg,#eaf2ff 0%,#fff 70%);border-right:.2cqw solid #e3ebf8}
  .bc-logo{width:100%;max-width:23cqw;height:auto}
  .bc-body{flex:1;padding:4.5cqw 4.5cqw 4cqw;min-width:0;display:flex;flex-direction:column;justify-content:center}
  .bc-name{font-family:Georgia,"Times New Roman",serif;font-size:5cqw;font-weight:700;color:#0b3fd1;line-height:1.1;overflow-wrap:anywhere}
  .bc-title{font-size:2.3cqw;letter-spacing:.18em;text-transform:uppercase;color:#4a5a7a;margin-top:1.4cqw;font-weight:600}
  .bc-rule{height:.4cqw;background:#1466ff;margin:2.6cqw 0 2.2cqw;width:85%;border-radius:1cqw}
  .bc-list{list-style:none;padding:0;margin:0;font-size:2.7cqw}
  .bc-list li{display:flex;align-items:center;gap:1.8cqw;margin-bottom:1.5cqw;min-width:0}
  .bc-list a,.bc-list span{color:#0d1b3e;text-decoration:none;overflow-wrap:anywhere}
  .bc-ic{flex:0 0 4.6cqw;height:4.6cqw;border-radius:50%;background:#1466ff;color:#fff;font-size:2.2cqw;
    display:inline-flex;align-items:center;justify-content:center}
</style>
