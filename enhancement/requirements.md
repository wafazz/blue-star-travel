# Requirements — AI Assistant

Status values: `Proposed` · `Accepted` · `Withdrawn`
Priority: `Must` · `Should` · `Could`

## Customer-facing

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| REQ-AI-001 | A customer can type a natural-language travel question on the public site and receive a reply. | Must | Proposed |
| REQ-AI-002 | Package suggestions come only from packages that exist and are active in the database, with real prices. | Must | Proposed |
| REQ-AI-003 | Each suggestion links to the real package page so the customer can book through the normal flow. | Must | Proposed |
| REQ-AI-004 | ~~For a logged-in customer, suggestions may use that customer's past bookings and saved preferences.~~ | Should | Withdrawn (OQ-6: no customer data to third-party providers; see design.md ADR-AI-011) |
| REQ-AI-005 | Requests about bookings, payments, refunds, complaints, or cancellations are handed to a human (support ticket or staff contact) and the assistant does not decide them. | Must | Proposed |
| REQ-AI-006 | If the AI is unavailable, the customer sees a clear message and the rest of the site keeps working. | Must | Proposed |

## Safety and data

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| REQ-AI-010 | Provider API keys exist only on the server (`.env`), never in the browser or in Blade output. | Must | Proposed |
| REQ-AI-011 | Passwords, IC/passport numbers, card or bank data, and full addresses are never sent to any AI provider. | Must | Proposed |
| REQ-AI-012 | Customer text and catalogue content are sanitised before being placed into prompts (prompt-injection defence). | Must | Proposed |
| REQ-AI-013 | System instructions are kept separate from user input and cannot be overridden by it. | Must | Proposed |
| REQ-AI-014 | AI replies are parsed into a strict schema; malformed output falls back to a safe default message. | Must | Proposed |
| REQ-AI-015 | Assistant responses are checked so they cannot show a package, price, or availability that is not in the database. | Must | Proposed |
| REQ-AI-016 | Each AI conversation is logged with timestamp, customer ID (or anonymous session), provider, and token usage. | Must | Proposed |
| REQ-AI-017 | Per-customer and per-day request limits prevent runaway cost and abuse. | Must | Proposed |

## Cost and operations

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| REQ-AI-020 | Provider access goes through one internal interface; business logic does not call an SDK directly. | Must | Proposed |
| REQ-AI-021 | A provider chain (primary → secondary) is configurable and used on timeout or quota error. | Should | Proposed |
| REQ-AI-022 | Token usage and estimated cost are visible per provider and per day to HQ/admin. | Should | Proposed |
| REQ-AI-023 | Provider and model are set in configuration, not hardcoded in code. | Must | Proposed |

## Staff-facing

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| REQ-AI-030 | Staff can see the assistant's conversation log for a customer, including the packages it suggested. | Should | Proposed |
| REQ-AI-031 | Staff can switch the assistant off from the admin panel without a deploy. | Must | Proposed |
| REQ-AI-032 | Staff-assist drafting (reply suggestions) is out of scope for phase 1 and is a candidate for phase 3. | Could | Proposed |

## Out of scope (phase 1)

- The AI creating, changing, or cancelling bookings or payments.
- The AI issuing refunds or discounts.
- Multiple cooperating AI agents. Phase 1 uses one assistant with one provider chain.
- Voice input.
