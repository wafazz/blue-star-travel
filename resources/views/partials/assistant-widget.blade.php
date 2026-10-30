@if (app(\App\Services\Assistant\AssistantSettings::class)->enabled())
<section class="card p-3 mb-3" id="bst-assistant" aria-labelledby="bst-assistant-title">
  <h2 id="bst-assistant-title" class="h6 fw-bold mb-1">Ask our travel assistant</h2>
  <p class="text-secondary small mb-2">Tell us your trip, budget or group size and we will suggest packages from our catalogue.</p>

  <div id="bst-assistant-log" class="mb-2" aria-live="polite" style="max-height:320px;overflow-y:auto"></div>

  <form id="bst-assistant-form" class="d-flex gap-2" autocomplete="off">
    <label for="bst-assistant-input" class="visually-hidden">Your question</label>
    <input id="bst-assistant-input" type="text" class="form-control" maxlength="{{ \App\Services\Assistant\PromptSanitiser::MAX_USER_CHARS }}"
           placeholder="e.g. Family trip to Langkawi for 4 people, under RM3,000" required>
    <button class="btn btn-primary" type="submit">Ask</button>
  </form>
</section>

<script>
(function () {
  const endpoint = @json(route('assistant.message'));
  const token = @json(csrf_token());
  const log = document.getElementById('bst-assistant-log');
  const form = document.getElementById('bst-assistant-form');
  const input = document.getElementById('bst-assistant-input');

  // Every value is written with textContent or set as a property, never as HTML.
  function bubble(text, who) {
    const row = document.createElement('div');
    row.className = who === 'you' ? 'text-end mb-2' : 'mb-2';
    const body = document.createElement('div');
    body.className = who === 'you' ? 'd-inline-block px-3 py-2 rounded-3 bg-primary text-white' : 'd-inline-block px-3 py-2 rounded-3 bg-body-secondary';
    body.textContent = text;
    row.appendChild(body);
    log.appendChild(row);
    return row;
  }

  function suggestionList(items) {
    const list = document.createElement('div');
    list.className = 'mb-2';
    items.forEach(function (s) {
      const card = document.createElement('div');
      card.className = 'border rounded-3 p-2 mb-2';
      const link = document.createElement('a');
      link.href = s.url;
      link.className = 'fw-semibold';
      link.textContent = s.name;
      const meta = document.createElement('div');
      meta.className = 'small text-secondary';
      meta.textContent = [s.destination, s.days ? s.days + ' days' : null, s.price].filter(Boolean).join(' · ');
      const reason = document.createElement('div');
      reason.className = 'small';
      reason.textContent = s.reason;
      card.append(link, meta, reason);
      list.appendChild(card);
    });
    return list;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    const question = input.value.trim();
    if (!question) return;

    bubble(question, 'you');
    input.value = '';
    const pending = bubble('…', 'bot');

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ message: question }),
      });
      const data = await response.json().catch(function () { return {}; });
      pending.querySelector('div').textContent = data.text || 'Something went wrong. Please try again.';
      if (Array.isArray(data.suggestions) && data.suggestions.length) {
        pending.appendChild(suggestionList(data.suggestions));
      }
    } catch (error) {
      pending.querySelector('div').textContent = 'We could not reach the assistant. Please check your connection and try again.';
    }
    log.scrollTop = log.scrollHeight;
  });
})();
</script>
@endif
