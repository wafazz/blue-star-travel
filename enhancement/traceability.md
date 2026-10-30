# Traceability Matrix — AI Assistant

Columns: requirement → decision(s) → task(s) → test(s). A blank cell is a gap and must be closed before the phase gate.

| Requirement | Priority | Decision(s) | Task(s) | Test(s) |
|-------------|----------|-------------|---------|---------|
| REQ-AI-001 | Must | — | TASK-AI-020 | Manual: widget on public page |
| REQ-AI-002 | Must | ADR-AI-002 | TASK-AI-012 | Inactive package excluded from context |
| REQ-AI-003 | Must | ADR-AI-002 | TASK-AI-020 | Suggestion link resolves to package page |
| REQ-AI-004 | Should | ADR-AI-004 | TASK-AI-030 | Only allowed history fields present |
| REQ-AI-005 | Must | ADR-AI-003 | TASK-AI-015 | Booking/refund message never reaches provider |
| REQ-AI-006 | Must | ADR-AI-006 | TASK-AI-020, TASK-AI-021 | Provider down → friendly message |
| REQ-AI-010 | Must | — | TASK-AI-010 | Keys absent from rendered HTML and repo |
| REQ-AI-011 | Must | ADR-AI-004 | TASK-AI-012, TASK-AI-030 | PII fields absent from prompt |
| REQ-AI-012 | Must | ADR-AI-005 | TASK-AI-013 | Injection strings do not alter system prompt |
| REQ-AI-013 | Must | ADR-AI-005 | TASK-AI-013 | System prompt fixed in code |
| REQ-AI-014 | Must | ADR-AI-002 | TASK-AI-014 | Bad JSON → safe default |
| REQ-AI-015 | Must | ADR-AI-002 | TASK-AI-014 | Unknown package ID dropped |
| REQ-AI-016 | Must | ADR-AI-009 | TASK-AI-016, TASK-AI-017 | One message → one conversation and message row |
| REQ-AI-017 | Must | ADR-AI-007 | TASK-AI-018 | 11th message in an hour → 429 |
| REQ-AI-020 | Must | ADR-AI-001 | TASK-AI-011 | Adapter tested with mock HTTP |
| REQ-AI-021 | Should | ADR-AI-006 | TASK-AI-021 | Primary timeout → secondary answers |
| REQ-AI-022 | Should | ADR-AI-009 | TASK-AI-017, TASK-AI-032 | Totals match logged tokens |
| REQ-AI-023 | Must | ADR-AI-008 | TASK-AI-002, TASK-AI-010 | Model name comes from config |
| REQ-AI-030 | Should | ADR-AI-009 | TASK-AI-031 | Admin sees conversation; role check |
| REQ-AI-031 | Must | ADR-AI-007 | TASK-AI-019 | Flag off → no provider call |
| REQ-AI-032 | Could | — | TASK-AI-040 | Deferred |

## Gaps to close before Phase 1 gate

- REQ-AI-001 and REQ-AI-003 rely on manual tests only. Add a browser test (Probe, phase 4 gate) for one real journey.
- REQ-AI-010 needs a repo scan step in the security gate (grep for key patterns).
- REQ-AI-016 depends on PDPA sign-off (OQ-6).
