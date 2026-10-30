# Design — AI Assistant

## 1. Architecture

```
Browser (public site, Blade + JS)
   │  POST /assistant/message   (CSRF, rate-limited)
   ▼
AssistantController
   │  validates input length and shape
   ▼
AssistantService
   ├── IntentGuard         → routes booking/payment/complaint requests to human handoff (REQ-AI-005)
   ├── ContextBuilder      → reads active packages + optional customer prefs; strips PII (REQ-AI-002, 004, 011)
   ├── PromptSanitiser     → cleans user text and catalogue text (REQ-AI-012)
   ├── AiGateway           → provider interface + fallback chain (REQ-AI-020, 021, 023)
   │      ├── Anthropic adapter
   │      ├── OpenAI adapter
   │      └── Gemini adapter
   ├── ResponseValidator   → strict JSON schema, package-ID whitelist (REQ-AI-014, 015)
   └── UsageLogger         → tokens, cost, provider, conversation (REQ-AI-016, 022)
```

The assistant returns **package IDs**, not free text prices. The view looks up the real name,
price, and link from the database. This is the main control for REQ-AI-002 and REQ-AI-015.

## 2. Decisions

### ADR-AI-001 — Provider-neutral gateway
- **Decision:** All AI calls go through `AiGateway`, an interface with one method per capability (`complete`, with structured output).
- **Reason:** REQ-AI-020, 023. Lets the team switch or add providers by config.
- **Alternatives rejected:** calling one SDK directly from the controller (locks the code to one vendor; fallback becomes a rewrite).
- **Status:** Proposed · **Requirements:** REQ-AI-020, REQ-AI-023

### ADR-AI-002 — Recommend by ID, validate against the database
- **Decision:** The model must reply with `{ "suggestions": [{ "package_id": int, "reason": string }] }`. Any `package_id` not in the supplied catalogue is dropped. Prices and names are never taken from model text.
- **Reason:** REQ-AI-002, 015. Stops invented packages and wrong prices.
- **Alternatives rejected:** letting the model write prices in prose (hallucination risk on money).
- **Status:** Proposed · **Requirements:** REQ-AI-002, REQ-AI-003, REQ-AI-015

### ADR-AI-003 — Human handoff for money and bookings
- **Decision:** `IntentGuard` runs before the model. Keywords and a small classifier send booking changes, payments, refunds, complaints, and cancellations to a fixed reply plus a support-ticket or contact action. The model never answers these.
- **Reason:** REQ-AI-005. Keeps financial decisions with staff.
- **Alternatives rejected:** letting the model answer and rely on its own caution (not reliable enough for money).
- **Status:** Proposed · **Requirements:** REQ-AI-005

### ADR-AI-004 — Minimal customer data in prompts
- **Decision:** Only the customer's first name (optional), the last 5 bookings' package categories, and stated preferences are sent. No contact details, IDs, documents, or payment data.
- **Reason:** REQ-AI-011, REQ-AI-004. Data minimisation across third-party providers.
- **Alternatives rejected:** sending full profile for "better" answers (unnecessary exposure).
- **Status:** Proposed · **Requirements:** REQ-AI-004, REQ-AI-011

### ADR-AI-005 — Prompt injection defence
- **Decision:** User text is length-limited (e.g. 500 chars), control characters stripped, and placed only in the user turn. System instructions are a fixed string in code, never built from user input. Catalogue text is escaped and placed in a delimited data block marked "data, not instructions".
- **Reason:** REQ-AI-012, 013.
- **Alternatives rejected:** string-concatenating everything into one prompt (classic injection path).
- **Status:** Proposed · **Requirements:** REQ-AI-012, REQ-AI-013

### ADR-AI-006 — Fallback chain on timeout or quota
- **Decision:** Config lists providers in order. On timeout, 429, or 5xx, the next provider is tried once. If all fail, the customer sees the REQ-AI-006 message.
- **Reason:** REQ-AI-021, REQ-AI-006.
- **Alternatives rejected:** retry the same provider in a loop (slows the page and burns quota).
- **Status:** Proposed · **Requirements:** REQ-AI-006, REQ-AI-021

### ADR-AI-007 — Rate limits and kill switch
- **Decision:** Laravel rate limiter: e.g. 10 messages per customer per hour and 200 per day site-wide (values to confirm, see plan OQ-2). A feature flag in the database (`assistant_enabled`) lets admin turn the feature off instantly.
- **Reason:** REQ-AI-017, REQ-AI-031.
- **Alternatives rejected:** config-file flag (needs deploy to change).
- **Status:** Proposed · **Requirements:** REQ-AI-017, REQ-AI-031

### ADR-AI-008 — Models and prices are not hardcoded
- **Decision:** Model names live in `config/ai.php` read from `.env`. Model IDs and prices are verified against the `claude-api` skill (or the relevant provider's current docs) at implementation time.
- **Reason:** REQ-AI-023. The CS AI protocol forbids stale model tables.
- **Alternatives rejected:** a model constant in the code (goes stale; causes runtime 404s).
- **Status:** Proposed · **Requirements:** REQ-AI-023

### ADR-AI-009 — Log conversations and usage
- **Decision:** Two tables: `assistant_conversations` (customer ref or anonymous session hash, timestamps, provider, status) and `assistant_messages` (role, text, package IDs suggested, prompt/completion tokens, estimated cost). Anonymous users are stored with a salted hash, not raw IP.
- **Reason:** REQ-AI-016, 022, 030.
- **Alternatives rejected:** logging to Laravel log file only (not queryable for staff view or cost reports).
- **Status:** Proposed · **Requirements:** REQ-AI-016, REQ-AI-022, REQ-AI-030

## 2a. Amendments from owner answers (2026-10-31)

These amend the decisions above. Each one is recorded here, not silently changed.

### ADR-AI-010 — Provider keys stored in HQ settings (amends REQ-AI-010)
- **Decision:** Keys are entered in HQ > AI Assistant (OQ-1). They are stored encrypted with the app key (`Crypt`), shown masked, and never returned in full. `.env` is kept as a fallback.
- **Reason:** Owner asked to update providers later without a deploy.
- **Trade-off:** Keys now live in the database as well as `.env`. Protected by encryption, HQ/super-admin-only access, and a log entry on every change (without the key).
- **Status:** Accepted (owner request) · **Amends:** ADR-AI-001, REQ-AI-010

### ADR-AI-011 — No customer personal data to providers (amends REQ-AI-004)
- **Decision:** Customer booking history and preferences are NOT sent to any AI provider. The prompt contains the visitor's question and the public catalogue only.
- **Reason:** OQ-6 answer: the client has not accepted third-party processing of customer data, so it must not leave this system.
- **Effect:** REQ-AI-004 is Withdrawn. Personalisation is out of scope until the client accepts provider terms.
- **Status:** Accepted · **Amends:** ADR-AI-004

### ADR-AI-012 — Provider adapters: official SDK where one exists (amends ADR-AI-001)
- **Decision:** Anthropic uses the official `anthropic-ai/sdk` for PHP (required by the claude-api skill). OpenAI and Gemini use Laravel's HTTP client, because neither has an official Laravel SDK.
- **Retries:** The Anthropic SDK's own retries are off (`maxRetries: 0`). The gateway owns retries and fallback, so waits do not stack.
- **Status:** Accepted

### ADR-AI-013 — Hourly limit uses the rate limiter, daily limit uses the log (amends ADR-AI-007)
- **Decision:** The per-visitor hourly limit is a Laravel rate-limiter key (user ID when logged in, IP for visitors). The site-wide daily total counts today's visitor messages in `assistant_messages`.
- **Reason:** A limit keyed on the session cookie resets when the cookie is cleared. The test suite caught this.
- **Status:** Accepted

### Model defaults (ADR-AI-008 applied)
- Anthropic: `claude-opus-5-5` (claude-api skill default).
- Gemini: `gemini-3.6-flash` (Google cloud docs, found by search). Confirm before go-live.
- OpenAI: **no default**. Search results conflicted on current IDs and prices, so the admin must enter one from OpenAI's pricing page before the assistant can use OpenAI.

## 3. Data changes

| Table | Change | Notes |
|-------|--------|-------|
| `assistant_conversations` | New | Migration needed → **T2 schema change**, requires review gate |
| `assistant_messages` | New | Same as above |
| `settings` (or equivalent) | `assistant_enabled` flag | Reuses existing settings mechanism if present; confirm (OQ-4) |

## 4. Security notes

- Keys in `.env` only; `.env` stays blocked by nginx (already verified in server logs).
- Endpoint requires CSRF token and rate limiting; no auth required for public use, but logged-in data is used only when the session is valid.
- Responses are rendered with Blade escaping; no raw HTML from the model is ever output.
- Logs must not contain full prompts with personal data.
