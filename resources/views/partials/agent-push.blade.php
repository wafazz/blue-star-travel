{{-- Web push opt-in for the agent portal. Include inside <head>.
     Silently re-attaches an already-granted device to whichever agent is signed in,
     so a shared phone never pushes one agent's activity to another. --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
<script>
(function () {
  function init() {
    var key = document.querySelector('meta[name="vapid-public-key"]').content;
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var subscribeUrl = @json(route('agent.push.subscribe'));
    var button = document.querySelector('[data-push-enable]');

    if (!key || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      return; // Unsupported browser, or iOS Safari outside an installed home-screen app.
    }

    function toBytes(base64url) {
      var padded = (base64url + '==='.slice((base64url.length + 3) % 4)).replace(/-/g, '+').replace(/_/g, '/');
      var raw = atob(padded);
      var out = new Uint8Array(raw.length);
      for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
      return out;
    }

    function register(subscription) {
      var json = subscription.toJSON();
      return fetch(subscribeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys }),
      });
    }

    // Permission already granted: keep this device attached to the signed-in agent.
    if (Notification.permission === 'granted') {
      navigator.serviceWorker.ready
        .then(function (reg) { return reg.pushManager.getSubscription(); })
        .then(function (sub) { if (sub) return register(sub); })
        .catch(function () { /* best effort — the next page load retries */ });
    }

    if (!button || Notification.permission === 'denied') return;

    button.hidden = Notification.permission === 'granted';
    button.addEventListener('click', function () {
      Notification.requestPermission().then(function (permission) {
        if (permission !== 'granted') return;
        return navigator.serviceWorker.ready
          .then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
              return existing || reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: toBytes(key),
              });
            });
          })
          .then(register)
          .then(function () { button.hidden = true; });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
