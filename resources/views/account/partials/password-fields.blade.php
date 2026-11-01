{{-- Current / new / confirm password fields with show-hide toggles, a strength meter and a
     live requirements list. $inputClass and $labelClass pick the host layout's styling. --}}
@php
  $ic = $inputClass ?? 'form-control';
  $lc = $labelClass ?? 'form-label small fw-semibold';
@endphp

<div class="pw-field">
  <label class="{{ $lc }}" for="current_password">Current password</label>
  <div class="pw-wrap">
    <input type="password" id="current_password" name="current_password" class="{{ $ic }}" autocomplete="current-password" required>
    @include('account.partials.password-eye', ['target' => 'current_password'])
  </div>
  @error('current_password')<div class="pw-err">{{ $message }}</div>@enderror
</div>

<div class="pw-field">
  <label class="{{ $lc }}" for="password">New password</label>
  <div class="pw-wrap">
    <input type="password" id="password" name="password" class="{{ $ic }}" autocomplete="new-password" minlength="8" required>
    @include('account.partials.password-eye', ['target' => 'password'])
  </div>
  <div class="pw-meter"><div class="pw-bar" id="pw_bar"></div></div>
  <div class="pw-label" id="pw_label"></div>
  <ul class="pw-reqs">
    <li id="pw_req_len">At least 8 characters</li>
    <li id="pw_req_case">Upper and lower case letters</li>
    <li id="pw_req_num">A number</li>
    <li id="pw_req_sym">A symbol (e.g. ! @ # $)</li>
  </ul>
  @error('password')<div class="pw-err">{{ $message }}</div>@enderror
</div>

<div class="pw-field">
  <label class="{{ $lc }}" for="password_confirmation">Confirm new password</label>
  <div class="pw-wrap">
    <input type="password" id="password_confirmation" name="password_confirmation" class="{{ $ic }}" autocomplete="new-password" required>
    @include('account.partials.password-eye', ['target' => 'password_confirmation'])
  </div>
  <div class="pw-match" id="pw_match"></div>
</div>

<style>
  .pw-field{margin-bottom:14px}
  .pw-wrap{position:relative}
  .pw-wrap input{padding-right:46px}
  .pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:0;padding:8px;color:#7a86a8;cursor:pointer;line-height:0}
  .pw-eye .i-off{display:none}
  .pw-eye[data-shown="1"] .i-on{display:none}
  .pw-eye[data-shown="1"] .i-off{display:inline}
  .pw-meter{height:6px;border-radius:6px;background:#e3e9f5;overflow:hidden;margin:10px 0 4px}
  .pw-bar{height:100%;width:0;transition:width .2s,background-color .2s}
  .pw-label{font-size:12px;font-weight:700;min-height:16px}
  .pw-reqs{list-style:none;padding:0;margin:6px 0 0;font-size:12px;color:#7a86a8}
  .pw-reqs li::before{content:"○ "}
  .pw-reqs li.ok{color:#16b364}
  .pw-reqs li.ok::before{content:"✓ "}
  .pw-match{font-size:12px;font-weight:700;margin-top:4px;min-height:16px}
  .pw-err{font-size:12px;color:#f04438;margin-top:4px}
</style>

<script>
  (function () {
    // Show / hide toggles (event delegation, so it works wherever the partial is rendered).
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.pw-eye');
      if (!btn) return;
      const input = document.getElementById(btn.dataset.target);
      const shown = input.type === 'text';
      input.type = shown ? 'password' : 'text';
      btn.dataset.shown = shown ? '0' : '1';
      btn.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
    });

    const pw = document.getElementById('password');
    const confirm = document.getElementById('password_confirmation');
    const bar = document.getElementById('pw_bar');
    const label = document.getElementById('pw_label');
    const match = document.getElementById('pw_match');
    const levels = [
      { text: '', color: '#e3e9f5', width: 0 },
      { text: 'Very weak', color: '#f04438', width: 20 },
      { text: 'Weak', color: '#f04438', width: 40 },
      { text: 'Fair', color: '#f79009', width: 60 },
      { text: 'Good', color: '#1466ff', width: 80 },
      { text: 'Strong', color: '#16b364', width: 100 },
    ];

    function setReq(id, ok) {
      document.getElementById(id).classList.toggle('ok', ok);
    }

    function updateStrength() {
      const v = pw.value;
      const hasLen = v.length >= 8;
      const hasCase = /[a-z]/.test(v) && /[A-Z]/.test(v);
      const hasNum = /\d/.test(v);
      const hasSym = /[^A-Za-z0-9]/.test(v);
      setReq('pw_req_len', hasLen);
      setReq('pw_req_case', hasCase);
      setReq('pw_req_num', hasNum);
      setReq('pw_req_sym', hasSym);

      // Five signals: length 8, length 12, mixed case, a number, a symbol.
      const score = [hasLen, v.length >= 12, hasCase, hasNum, hasSym].filter(Boolean).length;
      const level = v.length === 0 ? levels[0] : levels[Math.min(score + 1, 5)];
      bar.style.width = level.width + '%';
      bar.style.backgroundColor = level.color;
      label.textContent = level.text;
      label.style.color = level.color;
      updateMatch();
    }

    function updateMatch() {
      if (!confirm.value) { match.textContent = ''; return; }
      const same = confirm.value === pw.value;
      match.textContent = same ? 'Passwords match ✓' : 'Passwords do not match';
      match.style.color = same ? '#16b364' : '#f04438';
    }

    pw.addEventListener('input', updateStrength);
    confirm.addEventListener('input', updateMatch);
  })();
</script>
