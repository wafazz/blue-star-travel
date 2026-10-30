# Plan — AI Assistant

Each task lists the requirements it satisfies. Tasks are not started until the design is accepted.

## Phase 0 — Confirm (no code)

| Task | Satisfies | Done when |
|------|-----------|-----------|
| TASK-AI-001 Confirm open questions OQ-1 to OQ-5 with the owner | all | Answers recorded in this file |
| TASK-AI-002 Read the `claude-api` skill and each chosen provider's current docs for model names, prices, and limits | REQ-AI-023 | Model names and prices written into `design.md` ADR-AI-008 notes |
| TASK-AI-003 Confirm the project's phase gate outcome for schema change | REQ-AI-016 | Review sign-off on the two new tables |

## Progress

| Task | State | Evidence |
|------|-------|----------|
| TASK-AI-013 (sanitiser part) | Partial — `PromptSanitiser` built; fixed system prompt not yet written | `app/Services/Assistant/PromptSanitiser.php`, `tests/Unit/Assistant/PromptSanitiserTest.php` |
| TASK-AI-014 | Done (unit level) | `app/Services/Assistant/ResponseValidator.php`, `tests/Unit/Assistant/ResponseValidatorTest.php` |
| TASK-AI-015 | Done (routing + fixed reply); ticket/contact action not yet wired | `app/Services/Assistant/IntentGuard.php`, `tests/Unit/Assistant/IntentGuardTest.php` |

Unit suite: 29 tests passing (local, 2026-10-31).

### Built 2026-10-31 after owner answers (OQ-1 to OQ-6)

| Task | State | Evidence |
|------|-------|----------|
| TASK-AI-001 owner answers | Done | Recorded in this file and design.md ADR-AI-010 to ADR-AI-013 |
| TASK-AI-002 model names | Partly done: Claude default from the claude-api skill; Gemini from Google docs; OpenAI left blank for the admin to set | config/ai.php, design.md "Model defaults" |
| TASK-AI-010 config and keys | Done: keys in HQ settings (encrypted), env fallback | config/ai.php, app/Services/Assistant/AssistantSettings.php |
| TASK-AI-011 gateway and adapters | Done: Anthropic (official SDK), OpenAI and Gemini (HTTP) | app/Services/Assistant/Providers/*.php, AiGateway.php |
| TASK-AI-012 ContextBuilder | Done: active packages, public fields only, no customer data | app/Services/Assistant/ContextBuilder.php |
| TASK-AI-013 sanitiser and fixed system prompt | Done | PromptSanitiser.php, AssistantService::SYSTEM_PROMPT |
| TASK-AI-014 validator | Done | ResponseValidator.php |
| TASK-AI-015 intent guard | Done (routing and reply). Staff ticket action not yet wired | IntentGuard.php |
| TASK-AI-016 migrations | Written (T2 schema change). Not yet run on the server | database/migrations/2026_10_31_100000_create_assistant_tables.php |
| TASK-AI-017 service and logging | Done | AssistantService.php, models AssistantConversation / AssistantMessage |
| TASK-AI-018 endpoint and limits | Done: hourly limit by rate limiter, daily total by log | AssistantController.php, route `assistant.message` |
| TASK-AI-019 kill switch | Done: HQ on/off setting | AssistantSettings::enabled(), manage.ai-assistant |
| TASK-AI-020 widget | Done on the public package listing | resources/views/partials/assistant-widget.blade.php |
| TASK-AI-021 fallback chain | Done (tested with Http::fake) | AiGateway.php, AssistantProvidersTest / AssistantTest |
| TASK-AI-031 staff conversation view | Done: HQ/super-admin list and detail, visitor text escaped | app/Http/Controllers/Manage/AiConversationController.php, resources/views/manage/ai-assistant/ |
| TASK-AI-032 usage and cost summary | Done: calls, tokens, per day, cost only where prices are entered | app/Services/Assistant/UsageReport.php, manage/ai-assistant/usage |
| TASK-AI-040 staff reply drafting (OQ-5: yes) | Done: drafts only, never sent automatically; contact details redacted; respects the on/off switch | app/Services/Assistant/StaffDraftService.php |
| OQ-6 redaction (new) | Done: visitor questions and staff drafts are redacted before any provider call | app/Services/Assistant/PiiRedactor.php |

Tests: 55 assistant tests (unit and feature) passing locally. Full suite 232 tests, with 1 failure: the pre-existing date-based `AgentUpcomingTest`.

Not verified against live providers: the Anthropic call was smoke-tested only with a dummy key (it reached the API and was classified correctly). OpenAI and Gemini are tested with faked HTTP. The "Test Primary Provider" button on the settings page checks a real key.

Pre-existing failure, not caused by this work: `tests/Feature/AgentUpcomingTest.php::test_the_card_shows_name_dates_nights_pax_and_package` uses fixed August 2026 dates. The date has passed, so the "upcoming" list no longer shows them.

Owner answers are in (see the section above). Remaining before go-live: OpenAI model ID (TASK-AI-002), the T2 review, test and security gates, `php artisan migrate` on the server, and a live check of each provider from the settings page.

## Phase 1 — Core assistant (customer-facing, one provider)

| Task | Satisfies | Done when |
|------|-----------|-----------|
| TASK-AI-010 Add `config/ai.php` and `.env.example` keys (no real keys) | REQ-AI-010, 023 | Config loads; keys absent from repo |
| TASK-AI-011 Build `AiGateway` interface and first provider adapter | REQ-AI-020 | Unit test calls adapter with mock HTTP |
| TASK-AI-012 Build `ContextBuilder` (active packages only, PII stripped) | REQ-AI-002, 011 | Test: inactive package never in context; PII fields absent |
| TASK-AI-013 Build `PromptSanitiser` and fixed system prompt | REQ-AI-012, 013 | Test: injection strings in input do not change system prompt |
| TASK-AI-014 Build `ResponseValidator` (schema + package-ID whitelist) | REQ-AI-014, 015 | Test: unknown package ID dropped; bad JSON gives safe default |
| TASK-AI-015 Build `IntentGuard` with handoff reply | REQ-AI-005 | Test: booking/refund messages never reach the provider |
| TASK-AI-016 Migrations for `assistant_conversations` and `assistant_messages` | REQ-AI-016, 022 | Migration runs up and down on a test DB |
| TASK-AI-017 `AssistantService` wiring + `UsageLogger` | REQ-AI-016, 022 | Feature test: one message creates one conversation row and one message row |
| TASK-AI-018 Endpoint with CSRF, validation, and rate limiter | REQ-AI-017 | Test: 11th message in an hour returns 429 |
| TASK-AI-019 Kill switch read from database flag | REQ-AI-031 | Test: flag off → fixed "unavailable" reply, no provider call |
| TASK-AI-020 Blade chat widget on public site; links to real package pages | REQ-AI-001, 003, 006 | Manual check on phone width; provider failure shows message |
| TASK-AI-021 Fallback chain (second adapter) | REQ-AI-021, 006 | Test: primary timeout → secondary answers |

## Phase 2 — Personalisation and staff visibility

| Task | Satisfies | Done when |
|------|-----------|-----------|
| TASK-AI-030 Add minimal customer-history context (ADR-AI-004) | REQ-AI-004, 011 | Test: only allowed fields present |
| TASK-AI-031 Staff view of conversation log in admin panel | REQ-AI-030 | Admin can open a conversation; role check tested |
| TASK-AI-032 Usage and cost summary page for HQ/admin | REQ-AI-022 | Totals match logged tokens in a test fixture |

## Phase 3 — Optional

| Task | Satisfies | Done when |
|------|-----------|-----------|
| TASK-AI-040 Staff reply-drafting | REQ-AI-032 | Only if phase 1–2 accepted and OQ-5 answered yes |

## Test strategy (summary)

- Unit: gateway adapters (mocked HTTP), validator, sanitiser, intent guard.
- Feature: endpoint, rate limit, kill switch, logging, handoff path.
- Security: injection cases in `PromptSanitiser`; PII absence in `ContextBuilder`; keys never in rendered HTML.
- E2E (phase gate, Probe): one real customer journey on staging with a test provider key.

## Risks

| ID | Risk | Mitigation | Requirement |
|----|------|------------|-------------|
| RISK-AI-1 | Model invents a package or price | ADR-AI-002 whitelist; no prices from model text | REQ-AI-002, 015 |
| RISK-AI-2 | Prompt injection makes assistant promise refunds | ADR-AI-003 handoff; ADR-AI-005 defence | REQ-AI-005, 012 |
| RISK-AI-3 | Runaway API cost | ADR-AI-007 limits; REQ-AI-022 monitoring | REQ-AI-017, 022 |
| RISK-AI-4 | Personal data sent to third party | ADR-AI-004 minimisation; provider terms review | REQ-AI-011 |
| RISK-AI-5 | Stale model name causes 404 at runtime | ADR-AI-008; verify at build time | REQ-AI-023 |
| RISK-AI-6 | Server has Node 18 and PHP extension gaps (current site issues) | Fix deploy issues before Phase 1 starts | — |

## Open questions

| ID | Question | Why it matters |
|----|----------|----------------|
| OQ-1 | Which provider accounts exist already (Anthropic, OpenAI, Google)? | Chooses primary and fallback |
| OQ-2 | Acceptable limits: messages per customer per hour, and daily site budget in MYR? | Sets ADR-AI-007 numbers |
| OQ-3 | Is the assistant for public visitors, logged-in customers, or both? | Affects REQ-AI-004 and auth |
| OQ-4 | Does the project already have a settings table for flags? | Decides where `assistant_enabled` lives |
| OQ-5 | Is staff reply-drafting wanted, or only customer chat? | Decides whether Phase 3 is planned |
| OQ-6 | Provider data-processing terms accepted by the client (PDPA)? | Gate for REQ-AI-011 sign-off |

## Next step

Answer OQ-1 to OQ-6. Then TASK-AI-001 closes and Phase 1 can start.
