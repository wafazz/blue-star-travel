{{-- Share button for a digital card. Opens the phone's share sheet where the browser has one,
     otherwise copies the card link. $shareUrl, $shareName, $class (and optional $style). --}}
<button type="button" class="{{ $class }}" style="{{ $style ?? '' }}"
        data-share-url="{{ $shareUrl }}" data-share-title="{{ $shareName }}"
        onclick="shareCard(this)">Share card</button>

@once
<script>
  function shareCard(btn){
    const url = btn.dataset.shareUrl;
    const title = btn.dataset.shareTitle;
    const label = btn.textContent;
    if (navigator.share) {
      // A cancelled share sheet rejects with AbortError — that is not an error to show.
      navigator.share({ title: title, text: title + ' — Blue Star Travel & Tour', url: url }).catch(() => {});
      return;
    }
    // Desktop fallback: copy the link. navigator.clipboard is undefined on plain http.
    const done = () => { btn.textContent = 'Link copied ✓'; setTimeout(() => btn.textContent = label, 1800); };
    const ta = document.createElement('textarea');
    ta.value = url; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    done();
  }
</script>
@endonce
