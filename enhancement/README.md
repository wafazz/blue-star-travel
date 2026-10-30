# Enhancement: AI Assistant for Blue Travel

> **Project:** Blue Travel (TAMS) — Laravel 12, MySQL, Blade
> **Created:** 2026-10-31
> **Status:** PLANNING — no code written yet
> **Task tier:** T2 Full (customer personal data, public-facing surface, payments-adjacent booking flow)

## Why T2

Per the CS tiering rules, T2 is required for anything touching auth, tenant scoping, payments,
public API, or personal data. This feature sends customer questions and booking context to
third-party AI providers and can influence bookings, so it is T2. All gates apply.

## Scope of this folder

| File | Purpose |
|------|---------|
| `README.md` | This index, tier, and status |
| `requirements.md` | What the feature must do (`REQ-AI-###`) |
| `design.md` | How it is built, with decisions (`ADR-AI-###`) |
| `plan.md` | Phased tasks (`TASK-AI-###`), risks, open questions |
| `traceability.md` | Matrix linking requirement → decision → task → test |

## Traceability rules

1. Every requirement has an ID and is never renumbered. Retired ones are marked `Withdrawn`.
2. Every task names the requirement(s) it satisfies (`Satisfies: REQ-AI-...`).
3. Every design decision names the requirement(s) that caused it and the alternatives rejected.
4. Every test in the traceability matrix names the requirement it proves.
5. A change to scope is recorded as a new decision in `design.md` before code changes.

## Sources

- Project context: `../Planning.md` (product overview, tech stack, roles)
- CS AI protocol: `/Users/wafazztechnology/Desktop/CS/17-ai-protocol.md`
  - Provider abstraction and fallback chain
  - Prompt-injection defence and input hygiene
  - Token metering and strict response validation
  - Model IDs and prices come from the `claude-api` skill at build time, never from memory
- CS decision ledger: `/Users/wafazztechnology/Desktop/CS/08-decision-ledger-protocol.md`

## Status log

| Date | Change |
|------|--------|
| 2026-10-31 | Folder created; requirements, design, plan, traceability drafted (proposed) |
| 2026-10-31 | Owner answers received (OQ-1 to OQ-6). Built HQ settings page, Anthropic (official SDK) / OpenAI / Gemini adapters, fallback gateway, public widget, migrations, 32 tests. Amendments recorded in design.md ADR-AI-010 to ADR-AI-013. |
| 2026-10-31 | Built provider-free pieces: `PromptSanitiser`, `ResponseValidator`, `IntentGuard` with 29 passing unit tests. Provider, schema, and UI work waits on owner answers (OQ-1 to OQ-6). |
