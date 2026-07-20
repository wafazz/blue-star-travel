@php
    // $portal = 'agent' | 'customer'
    $portal = $portal ?? 'customer';
    $appTitle = $portal === 'agent' ? 'Blue Star Agent' : 'Blue Star';
@endphp

<link rel="manifest" href="{{ asset('manifest-' . $portal . '.webmanifest') }}">
<meta name="theme-color" content="#1466ff">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS ignores the manifest, so the standalone/app behaviour needs these too. --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $appTitle }}">
<link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' }).catch(function () {});
    });
  }

  // Chrome/Android: hold the install prompt so we can offer it on our own button.
  window.deferredInstallPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    window.deferredInstallPrompt = e;
    document.querySelectorAll('[data-install-app]').forEach(function (el) {
      el.hidden = false;
      el.addEventListener('click', function () {
        var prompt = window.deferredInstallPrompt;
        if (!prompt) return;
        prompt.prompt();
        prompt.userChoice.finally(function () {
          window.deferredInstallPrompt = null;
          el.hidden = true;
        });
      });
    });
  });

  window.addEventListener('appinstalled', function () {
    window.deferredInstallPrompt = null;
    document.querySelectorAll('[data-install-app]').forEach(function (el) { el.hidden = true; });
  });

  // iOS has no install prompt — Safari only offers Share → Add to Home Screen — so
  // show written instructions instead, and only when we're not already installed.
  document.addEventListener('DOMContentLoaded', function () {
    var ua = navigator.userAgent;
    var isIos = /iPad|iPhone|iPod/.test(ua)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS 13+
    var installed = navigator.standalone === true
      || window.matchMedia('(display-mode: standalone)').matches;

    if (isIos && !installed) {
      document.querySelectorAll('[data-ios-install]').forEach(function (el) { el.hidden = false; });
    }
  });
</script>
