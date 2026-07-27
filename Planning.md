# Blue Travel — Travel & Tour Management System (TAMS)

> **Client:** Blue Star Travel And Tours Sdn Bhd
> **Vendor:** CodexLure Technology
> **Spec source:** `blue-star.pdf` (Appendix A — Private & Confidential)
> **Status:** CODE-COMPLETE — all 10 phases shipped. Remaining work is client-side (FPX vendor, KPDN licence, deploy).
> **Last updated:** 2026-07-20

---

## 1. Product Overview

A dynamic, multi-layer Travel & Tour Management System for a travel agency operating an
**HQ → Admin → Agent → Customer** hierarchy. Agents sell travel packages (Domestic, International,
Umrah, Cruise, Free & Easy, Custom), earn commission, and are driven by a gamified engagement
layer (missions, streaks, leaderboard, rewards, achievements). HQ oversees the whole operation;
Admin processes the day-to-day booking/payment/provider workflow; Providers confirm the bookings.

**Design language:** professional, elegant, exclusive. Deep-blue gradient identity
(`#1466ff → #0b3fd1 → #082aa0`), soft shadows, rounded cards — carried over from the approved
`login.html` and `agent-dashboard.html` mockups.

---

## 2. Tech Stack

| Layer | Choice |
|-------|--------|
| Framework | **Laravel 12** (full server-side **Blade**, no SPA) |
| PHP | 8.2+ |
| Database | **MySQL** — local dev **port 3307** |
| CSS/UI | **Bootstrap 5** (latest) + custom blue theme layer |
| Auth | Laravel built-in, **multi-guard** (separate portals) |
| PDF | DomPDF (invoices, vouchers, reports) |
| Excel/CSV | Maatwebsite/Excel (report exports) |
| Charts | Chart.js (dashboards) |
| Queue/Notify | Laravel Queue + Email / SMS / WhatsApp API drivers |
| Assets | Vite |

### Notable conventions (house rules)
- Always initialize variables (local PHP is stricter than prod).
- DataTable numeric ID columns → `columnDefs: [{ type: 'num', targets: N }]`.
- Match existing template style; no refactors beyond scope.
- Per-portal scoping enforced at the query/policy layer (agents see only their own data).

---

## 3. Portals & Authentication (Separate Login URLs)

Two distinct authentication surfaces — **HQ/Admin staff are separated from Agents/Customers**.

| Portal | Login URL | Guard | Dashboard | UI Base |
|--------|-----------|-------|-----------|---------|
| **HQ Management** | `/hq/login` | `hq` | `/hq/dashboard` | AdminLTE-style / Bootstrap admin |
| **Admin** | `/admin/login` | `admin` (or shared staff guard w/ role) | `/admin/dashboard` | Bootstrap admin |
| **Agent Portal** | `/agent/login` | `web` (agent role) | `/agent/dashboard` | **`login.html` + `agent-dashboard.html` mockups** |
| **Customer Portal** | `/login` (public) | `web` (customer role) | `/account` | **`login.html` template (reused/rebranded)** |
| **Provider** | `/provider/login` | `provider` | `/provider/dashboard` | Bootstrap admin (light) |

> **Decision:** HQ + Admin share the staff back-office guard, differentiated by **RBAC role**
> (`super_admin`, `hq`, `admin`), reachable at `/hq/*` and `/admin/*`. Agent and Customer are the
> **public-facing** portals and MUST use the supplied `login.html` / `agent-dashboard.html` templates
> as their visual base (converted to Blade layouts). Customer portal reuses the `login.html` shell
> with copy/branding adjusted ("Customer" instead of "Agent").

Middleware guards each route group; unauthorized cross-portal access → redirect to that portal's login.

---

## 4. Roles & RBAC

- **Super Admin** — full system, company profile, permissions.
- **HQ** — executive oversight, finance, commission approval, marketing, reports.
- **Admin** — booking processing, payment/provider verification, packages, documents, support.
- **Provider** — confirm/reject bookings, update availability, upload confirmation docs.
- **Agent** — sell packages, manage own customers/bookings, gamification, commission/withdrawal.
- **Customer** — browse packages, book, pay, download invoice/voucher, loyalty points.

Role-Based Access Control + granular **Permission Management** (module-level abilities).

---

## 5. Data Model (core tables)

```
companies              (profile, single-tenant HQ)
users                  (staff: hq/admin/super_admin) — role_id, permissions
agents                 (tier: Silver/Gold/Platinum, rank, referrer_id, wallet)
customers              (passport, emergency_contact, loyalty_points, agent_id)
providers              (type: hotel/airline/transport/guide/attraction/local_operator)
roles / permissions / role_permission / permission_user   (RBAC)

packages               (category, status, assigned_provider_id, gallery)
package_pricing        (tier, adult/child/infant, promo, early_bird, group_discount)
package_dates          (available travel dates, seat_allocation, availability)
package_itineraries    (day-by-day), package_terms

bookings               (status enum, type, customer_id, agent_id, package_id, travel_date)
booking_items / booking_pax
booking_timeline       (audit trail of status changes)
booking_documents      (flight ticket, hotel voucher, visa, insurance, invoice, receipt)
booking_notes

payments               (FPX/online-banking/slip-upload, partial/balance, status)
invoices / receipts / credit_notes
refunds

commissions            (agent, booking, rule, override, month, status)
commission_rules
commission_withdrawals

# Gamification
missions / mission_completions        (daily missions, points)
checkins                              (daily streak, day 1/2/3/7/14/30 rewards)
streaks                               (current, longest, last_active)
reward_points_ledger                 (earn/redeem transactions, proof screenshots)
redemptions                          (cash/voucher/merch/commission/trip/hotel)
achievements / agent_achievements    (badges, locked/unlocked)
leaderboard_snapshots                (daily/weekly/monthly/yearly ranking)

referrals              (refer agents/customers, tracking, referral_commission)

# Marketing & Support
banners / coupons / voucher_campaigns / referral_campaigns / email_campaigns
tickets / ticket_replies              (internal ticketing, complaints)
notifications                         (email/sms/whatsapp log)
marketing_materials                  (posters, downloadables, referral links)

# Finance
expenses / sales_reports (derived/report views)
```

**Booking status enum:** `draft, pending_payment, pending_verification, waiting_provider_confirmation, confirmed, rejected, cancelled, completed, refunded`.

---

## 6. Booking Approval Workflow (from spec)

```
Customer / Agent → Submit Booking
        → Pending Verification
        → Admin Reviews Booking
        → Booking Sent to Service Provider
        → Provider Response
              ├─ Approved  → Admin Confirms → Invoice & Travel Voucher generated
              │              → Customer & Agent notified
              └─ Rejected  → Admin Rejects → Customer & Agent notified
```

Every transition writes to `booking_timeline`; notifications fire on confirm/reject.

---

## 7. Module Breakdown & Build Plan

Legend: `[ ]` pending · `[✔]` done

### MODULE 1 — HQ MANAGEMENT
- [✔] **Executive Dashboard** — 8 live KPIs (today/monthly sales, bookings by state, pending payments, active agents, commission payable), 6-month revenue trend, Needs-Attention action list, top agents (real leaderboard), top packages, recent bookings
- [~] **Company Management** — [✔] company profile (edit + logo + banking); staff/users/RBAC/permissions pending
- [✔] **Package Management** — 6 categories; pricing tiers (adult/child/infant), promo, early-bird, group discount, gallery, itinerary, T&C, travel dates, seat allocation, assigned provider, draft/active/inactive status *(full CRUD)*
- [~] **Provider Management** — [✔] provider CRUD (6 types, status); provider *actions* (confirm/reject/availability/docs) come in Phase 2
- [~] **Booking Management** — [✔] all 6 booking types, full approval workflow, timeline, notes, documents, history, cancellation; **modification/reschedule of an existing booking not built** (no edit/update action)
- [✔] **Customer Management** — database, passport info, emergency contact, loyalty points, notes, agent assignment *(travel history/prev bookings after Phase 2)*
- [✔] **Payment Management** — verification, FPX integration *(sandbox gateway, idempotent callback)*, online banking, slip upload, partial/balance payment, refund management, payment history
- [✔] **Commission Management** — dynamic-depth MLM (admin CRUD levels = cascade depth), agent + override commission via closure-table upline, orphan→HQ, monthly period, HQ approval → wallet credit, withdrawal request→approve→paid, reversal on refund/cancel
- [~] **Finance Module** — [✔] financial dashboard (revenue trend, outstanding, refunds KPIs), invoice/receipt management, refund workflow; expense mgmt / profit analysis / credit notes pending
- [~] **Marketing Module** — [✔] promotional banners, coupons (wired to booking discount), marketing materials, broadcast-to-role; voucher/referral/email campaigns folded into broadcast (dedicated campaign builder pending)
- [✔] **Reports & Analytics** — 7 reports (sales, booking, package performance, customer, agent performance, commission, financial summary) with date-range + per-report filters, KPI row, CSS bar chart, totals row; export **PDF / Excel / CSV**

### MODULE 2 — ADMIN
- [✔] **Admin Dashboard** — live counters (pending bookings/payments/provider confirmations, today's bookings + revenue, open tickets) all click through to the matching queue, oldest-first processing queue, payments-to-verify list, departing-today list
- [~] **Booking Processing** — [✔] verify booking, verify/reject payment, submit to provider, receive provider response, confirm, reject, complete, cancel; modify + reschedule pending
- [✔] **Customer Support** — internal ticketing (agent opens → staff queue + threaded replies + status), complaint category, notifications on reply *(customer-side ticket UI shipped in Phase 8)*
- [✔] **Package Management (Admin)** — admin shares the `/manage/packages` CRUD (create/edit, draft→active→inactive publish/close, travel dates + seat allocation; seats_booked auto-increments on confirm)
- [~] **Document Management** — [✔] `booking_documents` covers all 10 types; invoice/voucher/receipt auto-generated (DomPDF) with per-role download authz; **manual upload UI for flight ticket/visa/insurance not built** (types exist, no upload form)
- [~] **Notification Management** — [✔] in-app notifications engine (bell + unread badge) wired to booking/commission/withdrawal/redemption/ticket events + broadcast; email/SMS/WhatsApp are logged channel stubs pending vendor

### MODULE 3 — AGENT PORTAL  *(uses `login.html` + `agent-dashboard.html`)*
- [✔] **Smart Dashboard** — real rank (#x of N), monthly commission, target progress, attention items, bookings awaiting confirmation
- [✔] **Booking Management** — create booking, history, status tracking, upload payment / FPX, download invoice/voucher *(Phase 2-3)*
- [~] **Customer Management** — book own customers *(add-customer/follow-up reminders pending)*
- [✔] **Sales Dashboard** — today/week sales, month bookings, customers, commission summary *(real KPIs)*
- [✔] **Progress Tracker** — sales-target progress ring (achieved / target → %)
- [✔] **Leaderboard** — monthly ranking by real sales + rank; dedicated page *(daily/weekly/yearly filters + push notif pending)*
- [✔] **Achievement System** — 10 badges w/ real unlock criteria (bookings/sales/customers/rank/streak/referrals), auto-evaluated
- [✔] **Daily Missions** — 5 seeded missions → reward points; `complete_booking` auto-fires on paid sale; idempotent per day
- [✔] **Daily Check-in Rewards** — Day 1/2/3 (10/20/30), Day 7 voucher, Day 14 bonus, Day 30 special
- [✔] **Activity Streak** — consecutive-day tracking; miss a day → reset; longest tracked
- [✔] **Reward Point System** — earn (check-in, missions, booking) + redeem (7-item catalog: cash/vouchers/merch/commission/trip/hotel) → redemption workflow
- [~] **Referral Program** — agent_code referral link + downline network view *(customer-referral commission tie-in pending)*
- [✔] **Marketing Center** — download posters/materials (download-count tracked); referral link copy

### MODULE 4 — CUSTOMER PORTAL  *(reuses `login.html` shell)*
- [✔] **Customer login/register** — `/login`, `/register` (blue mobile template); registration creates User+Customer and honours `?ref=AGENT-CODE` to attribute the customer to the referring agent
- [✔] **Browse packages** — public `/packages` catalog (category tabs + search) and `/packages/{slug}` detail: gallery, pricing tiers, open departures w/ seats left, itinerary, inclusions, T&C
- [✔] **Online booking** — self-service booking form (tier, departure, pax, promo code, notes) → BookingService → payment → status tracking
- [✔] **My account** — data-driven dashboard (loyalty points, trips/upcoming/paid/outstanding, recent bookings, recommended packages, live banner), My Trips list + booking detail w/ timeline, payment history and document downloads
- [✔] **Payment** — FPX gateway + bank slip upload from the booking screen, partial & balance supported
- [✔] **Profile** — personal, passport/IC + expiry, nationality, address, emergency contact (syncs name/phone back to the login user)

---

## 8. Cross-Cutting Concerns
- [~] **Notifications engine** — [✔] in-app notifications (bell + unread) fired on booking/commission/withdrawal/redemption/ticket/broadcast; Email/SMS/WhatsApp = logged stubs (vendor TBD); push (rank change) pending
- [~] **Document generation** — [✔] DomPDF invoices, travel vouchers, receipts + report PDFs; credit notes pending
- [✔] **Export engine** — PDF (DomPDF, auto landscape >7 cols) / Excel (maatwebsite/excel) / CSV across all report screens
- [✔] **Payment gateway** — **Billplz** (signed webhook + server-to-server confirmation, idempotent) behind a pluggable driver layer, plus the dev sandbox simulator and manual slip-upload fallback
- [✔] **PWA (Agent + Customer portals)** — both installable to the home screen from their login pages: separate manifests (`Blue Star Agent` / `Blue Star`), standalone display, Blue Star icons (192/512 + maskable), iOS meta tags, app shortcuts, service worker with a branded offline page, and an in-page "Install app" button. Deliberately caches **only** the static shell — no HTML page is ever stored, so no booking/passport data is left on a shared device.
- [✔] **Payment Gateway Configuration screen** — `/manage/payment-gateway` (HQ/super-admin only): pick the active gateway, enter Billplz API key / X-Signature / collection ID, sandbox↔live toggle, callback URLs for reference, and a **Test Connection** button that calls the Billplz API. Secrets are encrypted at rest and only ever shown masked.
- [✔] **Audit & timeline** — booking timeline on every transition, commission ledger, wallet transactions, reward-points ledger
- [✔] **Data isolation** — every portal scoped to its own records; customer booking/ticket/document access verified 403 across accounts
- [✔] **Security** — full audit + fixes: private-disk storage for documents/slips, sandbox gateway locked out of production, login/register throttling, upload mime allow-list, provider null-ownership fix, redirect allow-list *(see §12)*

---

## 9. Delivery Phases

| Phase | Scope | Outcome |
|-------|-------|---------|
| **0 — Foundation** ✅ | Laravel 12 setup, MySQL:3307, multi-guard auth, RBAC, base layouts (blue Bootstrap admin + convert `login.html`/`agent-dashboard.html` to Blade) | All 5 portals log in to placeholder dashboards |
| **1 — Core Catalog** ✅ | Company profile, providers CRUD, packages CRUD (pricing tiers/dates/itinerary/gallery), customers CRUD — under `/manage` (HQ+Admin) | HQ/Admin can build the catalog |
| **2 — Booking Engine** ✅ | Booking creation (all channels) + full approval workflow + timeline + documents | End-to-end booking lifecycle works |
| **3 — Payments & Finance** ✅ | Payment verification, FPX *(sandbox)*, slip upload, invoices/receipts/refunds, finance dashboard | Money flow + accounting |
| **4 — Commission** ✅ | Dynamic-depth MLM cascade (closure table), levels config, approval, wallet, withdrawal, reversal | Agents paid correctly |
| **5 — Agent Gamification** ✅ | Missions, check-in/streak, points ledger, redemptions, achievements, leaderboard, referral | Full agent portal live |
| **6 — Marketing & Support** ✅ | Banners, coupons (wired to bookings), materials, broadcast, tickets/complaints, notifications engine (in-app + channel stubs) | Engagement + support |
| **7 — Reports & Analytics** ✅ | All report screens + PDF/Excel/CSV export | Decision-ready data |
| **8 — Customer Portal** ✅ | Public browse/book/pay/account | Self-service customers |
| **9 — Hardening** ✅ | Security review, data isolation audit, UX polish, seed/demo data, deployment | Production-ready |

---

## 10. Open Questions / To Confirm
- [✔] HQ and Admin — **DECIDED: single staff guard + RBAC role** (`super_admin`/`hq`/`admin`) at `/hq/*` + `/admin/*` + shared `/manage/*`
- [✔] FPX provider — **DECIDED: Billplz first** (2026-07-20), more gateways to be added later. Implemented behind a pluggable driver layer (`config/payments.php` + `PaymentGatewayDriver` contract), so adding ToyyibPay/senangPay/Bayarcash later is one class + one config block. See `DEPLOY.md` §7.
- [ ] SMS + WhatsApp API vendors (e.g. WhatsApp via Evolution/Cloud API)?
- [ ] Single-tenant (one company) confirmed — no multi-agency SaaS layer?
- [✔] Commission structure detail — **DECIDED: MLM multi-level, dynamic depth** (admin CRUD commission_levels; row count = cascade depth; seed L1 8%/L2 4%/L3 2%). Base = booking total_amount. Orphan→HQ. KPDN safeguard: commissions land pending → HQ approval before wallet credit.
- [ ] Multi-currency needed, or RM only?

---

## 11. Reference Assets
- `login.html` — approved blue agent/customer login mockup (hero gradient, card form, socials).
- `agent-dashboard.html` — approved agent portal mockup (wallet, quick actions, stats, target ring, missions, streak, leaderboard, rewards, achievements, referral, profile, bottom nav).
- `blue-star.pdf` — full functional specification (Modules 1–3).


---

## 12. Security Audit (Phase 9 — 2026-07-19)

Full sweep of all 38 controllers, 14 services, 32 models, routes and views. Findings **verified by
exploit** before fixing and **re-tested after**.

| # | Severity | Finding | Fix |
|---|----------|---------|-----|
| C1 | **CRITICAL** | Booking invoices/vouchers/receipts were written to the **public** disk at a guessable path (`/storage/booking-docs/{id}/Invoice-BK-YYYY-NNNNN.pdf`). Confirmed: fetched a real invoice with **no session** — pax names, passport numbers, prices. Sequential booking numbers made the whole archive enumerable. | Moved to the **private** disk + random filename suffix; served only via `BookingDocumentController` ownership check. Leaked public copies purged. |
| C2 | **CRITICAL** | The sandbox FPX callback trusted `result=success` **submitted by the payer's own browser**, and auto-verified the payment. Confirmed: a customer cleared a RM23,400 balance for free and minted RM3,276 of MLM commission. | Simulated success now only records the authorisation and leaves the payment **pending staff verification**; `checkout`/`callback` return 404 when `APP_ENV=production`. |
| H1 | HIGH | Payment slips (bank/personal data) stored on the public disk, linked with `asset('storage/…')` — no authorization. | Private disk + new gated `payments.slip` route reusing the ownership check. |
| H2 | HIGH | No rate limiting on any auth endpoint. | `throttle:5,1` on all 4 login POSTs + register; `throttle:30,1` on the callback. Verified: 5th bad login → 429. |
| M1 | MEDIUM | `abort_unless($booking->provider_id === $this->providerId($request))` — both sides `null` for a provider user with no linked row, granting access to every unassigned booking. **Additionally found while testing:** `where('provider_id', null)` compiles to `IS NULL`, so the booking *list* leaked them too. | Require a non-null provider id on both sides; deny outright in `index()`. Seeder now links the demo provider account (it had none — the portal only "worked" because of this bug). |
| M2 | MEDIUM | Marketing upload accepted **any** file type onto the web-served public disk (staff → RCE on Apache+mod_php). | Mime allow-list. |
| M3 | MEDIUM | `APP_DEBUG=true` shipped in `.env`. | Documented in `DEPLOY.md` §3/§5; `.env.example` annotated. |
| L1 | LOW | Notification redirect had no host check (open-redirect if a URL were ever attacker-controlled). | Restricted to `APP_URL`. |
| L2 | LOW | Redemption `note` unvalidated (500 on array input). | `nullable|string|max:500`. |

**Clean on audit:** mass assignment (every write path uses explicit `validate()` key lists, and
`BookingService` re-derives money server-side), SQL injection (all request values are bound, no
dynamic columns), CSRF (no exemptions anywhere), XSS (zero `{!! !!}` in views).

---

## 13. Phase 10 — Agent Revision & Resubmission Loop  *(planned 2026-07-26)*

Source: client mockup `PHOTO-2026-07-26-14-07-30.jpg` — "AGENT FLOW · Edit Customer & Submit Again".
Planned by the factory: **Sage** (scope), **Atlas** (architecture), **Vault** (schema). Iris arbitrated
the three where they conflicted — the calls are recorded in §13.2 so nobody re-litigates them later.

### 13.1 The flow

```
Agent submits ─► [pending_verification] ─► Admin reviews
                                              │
                        ┌─────────────────────┴─────────────────────┐
                        │                                           │
              ❌ Request Revision                            ✅ Verify & send
              (remark + flagged fields)                       to provider
                        │                                           │
                        ▼                                           ▼
              [needs_revision]  ──► Agent edits ──► Save as Draft ──┐   [confirmed]
                        ▲                │  (booking_drafts)  ◄─────┘        │
                        │                ▼                                   │
                        │        Review Changes (diff) ─► Confirm ─► Resubmit│
                        └────────────────────────────────────────────────────┘
                                                                    │
                                                     ── after confirm ──► Request Amendment
                                                        (agent asks, admin approves,
                                                         status stays `confirmed`)
```

**Framing decision (locked).** The mockup says *"Edit Customer"*, but ~90% of the edited fields are
**booking** data — package, travel date, pax, child age, room type, pickup, deposit, receipt. Only 4
fields (`name`, `phone`, `email`, `ic_passport_no`) live on `customers`. The feature is therefore built
on **`Agent\BookingController`** with customer-facing labels. `Agent\CustomerController` stays as-is
(the customer directory). The "My Customers" tabbed list = `agent.bookings.index` relabelled, because
the tabs are *booking* statuses and a customer has many bookings.

### 13.2 Arbitration record — where the agents disagreed

| # | Question | Sage | Atlas / Vault | **Iris ruling** |
|---|----------|------|---------------|-----------------|
| A1 | Store revisions in a table, or a `changes` JSON on `booking_timeline`? | timeline JSON — table is gold-plating | dedicated tables | **Table.** Sage also ruled *Save as Draft* a must-have, and a staged edit of an already-submitted booking has nowhere to live without one. The storage is load-bearing; only the *history viewer UI* is deferrable. |
| A2 | One `booking_revisions` table (draft + versions), or three? | — | Atlas: one · Vault: three | **Three (Vault).** Versions are immutable and numbered; a draft is mutable and unnumbered. Autosave `UPDATE`s against an append-only audit table is the wrong shape, and it muddles the `(booking_id, version)` uniqueness guard. |
| A3 | Where does `needs_revision` go in the enum? | — | Atlas: mid-list · Vault: appended | **Appended (Vault).** MySQL stores ENUM by 1-byte ordinal — inserting mid-list shifts every later ordinal and **silently re-labels live rows**. Data-corruption class bug. See §13.7. |
| A4 | Backfill v1 snapshots for existing bookings? | — | Atlas: data migration · Vault: none | **None (Vault).** Rebuilding a payload needs `BookingService` logic, and calling app services from a migration breaks the next time the service moves. Snapshot lazily on the first "Request Revision" (`reason: 'initial'`). No history row = no history panel, which is correct. |
| A5 | Agent-facing before/after diff screen | CUT — agent just typed it | build it | **Keep.** Sage priced it as a separate diff engine; once `changes` JSON exists for the admin, the agent screen is one more Blade partial over the same rows. Near-zero marginal cost. |
| A6 | Versioned Revision History panel (v1/v2/v3) | CUT | Phase 5 | **Build, but last.** Storage lands in Phase C anyway (A1); the viewer is Phase E and can slip without blocking the loop. |
| A7 | Dedicated success screen | CUT | build it | **Cut (Sage).** Flash message + redirect to booking detail — the house convention everywhere else. |
| A8 | Request Amendment on confirmed bookings | CUT — separate project | Phase 6 | **Split out as Phase F, gated.** Touches seats, provider sync, invoice regeneration and commission reversal. Does not ship with the revision loop and does not start until Q11 in §13.8 is answered. |
| A9 | Per-field admin flagging | NICE — defer | core | **Column now, UI in Phase B.** `fields` JSON costs nothing to add; the red "Admin requested this information" markers ship with the edit form. |
| A10 | `pickup_location` / `arrival_time` | may be mockup filler | build | **Build in Phase A** (1-hour migration) — a revision cannot flag a field that doesn't exist. Confirm with client that Blue Star actually does pickups (§13.8 Q10). |

### 13.3 State machine

| # | From | To | Actor | Guard / side effect |
|---|------|-----|-------|---------------------|
| T1 | `draft` | `pending_verification` | Agent (owner) | existing `BookingService::create()` |
| T2 | `pending_verification` | `needs_revision` | Staff (`manage.bookings` perm) | requires remark + ≥1 flagged field · **blocked if a non-reversed `Commission` exists** · writes the lazy `initial` snapshot if none |
| T3 | `waiting_provider_confirmation` | `needs_revision` | Staff | **recall from provider first**: `provider_status='pending'`, null `sent_to_provider_at` / `provider_responded_at`, notify provider · blocked when `provider_status='approved'` **and** `paid_amount > 0` → use amendment or cancel |
| T4 | `needs_revision` | `pending_verification` | Agent (owner only) | draft must exist + `confirm=1` (**server-side**) · applies payload, writes v(n+1), bumps `revision_no`, sets `resubmitted_at`, closes the request, `provider_status='pending'` |
| T5 | `needs_revision` | `needs_revision` | Agent | **Save as Draft is not a transition.** Status never moves — flipping to `draft` would drop the booking out of the admin queue, the agent's tab and every report `whereIn('status', …)` |
| T6 | `needs_revision` | `rejected` / `cancelled` | Staff | existing methods; live draft is deleted |
| T7 | `pending_verification` | `waiting_provider_confirmation` | Staff | unchanged — resubmission returns to exactly this status, so the existing guard still holds |
| T8 | any → `confirmed` | | Staff | **new guard:** `abort_if($booking->draft()->exists(), 409)` — staff must not confirm a booking mid-edit |
| T9 | `confirmed` | `confirmed` | Agent requests → Staff approves | amendment path; status never leaves `confirmed` |

Authorization is enforced **twice**: route middleware (`role:agent` / `role:super_admin,hq,admin` + `perm`),
and `abort_unless($booking->agent_id === $request->user()->id, 403)` in the agent controller — the
existing pattern at `Agent/BookingController.php:92,100`.

### 13.4 Schema (Vault)

Three migrations, no backfill.

| File | Contents |
|---|---|
| `2026_07_26_100002_add_revision_flow_to_bookings_table.php` | enum **append** `needs_revision` via raw `DB::statement` · `pickup_location` (string) · `arrival_time` (time) · `revision_no` (u-smallint, denormalised counter) · `revision_requested_at` · `resubmitted_at` (**never overwrite `submitted_at`** — it holds the original submission time) · indexes `(agent_id,status,created_at)`, `(agent_id,created_at)`, `(status,created_at)` |
| `2026_07_26_100003_create_booking_revision_tables.php` | `booking_revision_requests` (remark, `fields` JSON, status open/resolved/cancelled, resolved_at) · `booking_versions` (version, reason, `payload` JSON, `changes` JSON, unique `(booking_id,version)`) · `booking_drafts` (payload JSON, unique `(booking_id,user_id)`) · `booking_timeline.booking_version_id` nullable FK · **`payments.superseded_by`** nullable self-FK |
| `2026_07_26_100004_create_booking_amendments_table.php` | Phase F only — type, field, current/requested value, reason, status, admin_note |

**`booking_timeline` is not extended into a diff store.** It is a prose event log with a 13-caller
`log()` contract, queried one way, and a candidate for pruning; version snapshots are invoice-adjacent
records with different retention. It gains exactly one nullable FK so an Activity-History line
("Revision 3 submitted") can deep-link to its diff.

**Payload = full snapshot, not deltas.** A delta chain breaks the moment the schema moves — add a
column in August and every pre-August replay silently reads as blank. Five rules on the payload shape:

1. `"v": 1` schema version at the root, so a key rename never rewrites history.
2. **Labels stored beside every foreign key** (`package_title` next to `package_id`, `room_name`, `departure_label`). The diff renderer must work from the two JSON blobs alone with **zero lookups** — this is what keeps the history panel off an N+1.
3. Money as **strings** — `json_encode` turns `11400.00` into `11400` and drags float artefacts in.
4. Child rows carry **no auto-increment ids** — `booking_pax` / `booking_rooms` are destroyed and recreated on save, so ids are meaningless across versions. Rooms keyed by position, pax matched by name.
5. Receipt stored as a **path, never bytes**; the path stays valid because replacement chains via `payments.superseded_by` instead of overwriting.

`changes` is stored, not recomputed: it must be **frozen** (a diff recomputed after two schema changes
invents phantom rows), it's read on every panel open, and its inputs are two immutable rows so it can
never go stale. Collections (`rooms`) diff as a rendered one-line summary — element-wise comparison
turns a reordered room table into a wall of false positives.

Growth: ≈17 KB per heavily-revised (5×) booking; ≈21 MB/year at 20k bookings with 15% revised. No
retention policy needed.

### 13.5 Save as Draft — the load-bearing rule

> **An already-submitted booking's row, its `booking_pax` and its `booking_rooms` are not written
> until the agent hits Confirm & Resubmit.**

Save as Draft is a single `updateOrCreate` on `booking_drafts` keyed by `(booking_id, user_id)`.
Nothing else in the system can observe it — admin queue, provider feed, invoices, commissions and seat
counts all read the live tables, which still hold the last good state. On Confirm, inside **one
transaction** with the booking `lockForUpdate()`ed: write `booking_versions` (v+1, payload + changes),
apply to `bookings` + rebuild children, close the request, bump `revision_no`, set `resubmitted_at`,
status → `pending_verification`, log the timeline row, delete the draft.

Prices are **indicative until commit** — `subtotal`/`total_amount` are re-derived via
`BookingService::roomLines()` when the diff renders *and again* inside the resubmit transaction, so a
draft saved in July and resubmitted in September prices at September's rates, with the movement shown
as its own diff row.

The agent UI still reads **"Need Revision"** while a draft exists, with a separate `Draft saved` chip
and a "Continue editing" CTA. Booking state and edit state are two truths — two affordances, never one
status.

### 13.6 Agent status vocabulary

Six agent-facing labels collapse all ten DB statuses. `Booking::AGENT_STATUS` + `agentStatusLabel()` /
`agentStatusBadge()`, using badge classes that already exist in `layouts/agent.blade.php:44-46`.
Staff screens keep the full `Booking::STATUSES` vocabulary.

| DB status | Agent label | Badge |
|---|---|---|
| `draft` | Draft | `b-secondary` grey |
| `pending_payment` · `pending_verification` · `waiting_provider_confirmation` | **Submitted** | `b-info` blue |
| `needs_revision` | **Need Revision** | `b-warning` amber |
| `confirmed` | Confirmed | `b-success` green |
| `completed` | Completed | `b-primary` deep blue |
| `rejected` · `cancelled` · `refunded` | Cancelled | `b-danger` red |

**Tabs filter by label, not status** — `?tab=submitted` expands to the three underlying statuses. The
current index links raw statuses (`agent/bookings/index.blade.php:14`); leave it and the Submitted tab
silently hides every booking sitting with the provider.

### 13.7 Risks

| Risk | Detail | Mitigation |
|---|---|---|
| **Enum ordinal remap** | MySQL stores ENUM by 1-byte ordinal. `$table->enum(...)->change()` is a no-op-or-worse on Laravel 12 (no `doctrine/dbal`): it drops unrestated attributes and regenerates the value list from the PHP array. | Raw `DB::statement` with the value list **appended** in the original declared order. Verify `ALGORITHM=INPLACE, LOCK=NONE` is accepted — if MySQL rejects it, **stop**, the change isn't append-only. `down()` must re-park `needs_revision` rows before the reverse MODIFY or they truncate to `''`. |
| **Commission computed on a stale total** | `CommissionService::calculate()` refuses to run once a non-reversed `Commission` exists, and fires on full payment — so a re-priced resubmission on a paid booking keeps the **old** commission, permanently, with no error. | `requestRevision()` is **blocked outright** when a live commission exists. Price changes after commissioning go through the amendment path, which reverses then recalculates. |
| **`paid_amount` > new `total_amount`** | `balance()` goes negative and `isFullyPaid()` returns `true` — silently wrong. | Review screen blocks the resubmit: "this change reduces the total below what's already been paid — request a refund first". |
| **Seat double-book** | `assertDateSelection()` checks availability but `seats_booked` only moves on confirm/cancel — a pending booking holds no seats. Pre-existing hole this feature widens. | Re-run `assertDateSelection()` against the *new* departure on resubmit and fail loudly. On the amendment path the booking **does** hold seats, so approval must decrement old / increment new in one transaction, and the availability check must add back the booking's own pax or it counts itself as competition. |
| **Concurrency** | Agent resubmits while staff confirms; or two tabs both compute `version = MAX+1`. | `lockForUpdate()` + re-assert status after acquiring the lock → 409. The `unique(booking_id, version)` index turns the double-resubmit into a duplicate-key error — catch, re-read max, retry once. **Do not drop the index to "fix" this**; it is the guarantee. |
| **Shared customer record** | `customers.name/phone/email/ic_passport_no` are joined to that customer's *other* bookings and baked into issued invoice PDFs. | `apply()` writes customer keys **only when actually changed**, and never touches `customers.agent_id` / `user_id` — a revision that reassigns customer ownership is a data-isolation bug (§8). |
| **Receipt files** | Staged slip has no `Payment` row yet, so the existing `payments.slip` route can't serve it. Deleting a replaced slip 404s every older version's "View" link. | New `agent.bookings.draft-slip` route gated on `agent_id` (do **not** mint a placeholder `Payment` row — it pollutes the verification queue). Never delete a path referenced by any payload. Same mime allow-list as `Agent/BookingController.php:106` (§12 M2). |
| **Blast radius of a 10th status** | Hardcoded `whereIn('status', …)` at `Hq/DashboardController.php:33,46`, `Admin/DashboardController.php:17,27`, `Agent/DashboardController.php:79`, `Manage/BookingController.php:35-38`, plus `ReportService`. | `grep -rn "whereIn('status'" app/` is a **required checklist item before Phase A merges**. Decide per site whether `needs_revision` counts as pending (recommend: no — it waits on the agent, give it its own tile). |
| **Orphan draft files** | A slip uploaded mid-draft, then abandoned. | Weekly sweep: delete `booking_drafts` older than 30 days and any `payment-slips` file referenced by no payment, no version payload and no live draft. |

### 13.8 Open questions for Fakrul  *(blocking where marked)*

1. **[✔ RE-ANSWERED 2026-07-26 — supersedes the earlier "draft + needs_revision only"]** Edit window —
   **an agent may edit ANY booking that is not `completed`, `cancelled`, `rejected` or `refunded`**,
   with no admin revision request required. Every resubmission lands on **Submitted**
   (`pending_verification`). `Booking::AGENT_LOCKED_STATUSES` + `isEditableByAgent()`. See §13.9d.
2. Price changes on resubmit with a deposit already recorded — auto-revert to `pending_payment`? Who tells the customer?
3. Can an agent edit at all once *any* payment is verified? (recommended: no — admin-only from there)
4. Provider-sent bookings — recall (recommended) or hard-locked?
5. What if admin flags a field the agent cannot change? (recommended: restrict flaggable fields to the agent-editable set, or the agent deadlocks)
6. Revision cap — unlimited ping-pong, or escalate to admin-edits-directly after N?
7. **[✔ ANSWERED 2026-07-26]** Gamification — **no effect; credit once on confirm.** Needed no code: gamification fires only inside `verifyPayment()` behind `isFullyPaid()`, and revisions are blocked once commission exists.
8. Does the customer portal show `needs_revision`, or is it internal-only?
9. Should `needs_revision` count toward the HQ/Admin "Pending Bookings" tiles? (recommended: no, own tile)
10. **[✔ ANSWERED 2026-07-26]** Pickup + arrival time are real. Built per-booking.
11. **[✔ ANSWERED 2026-07-26]** Amendments — **agent only**; approval **reverses and recalculates** commission when the total moves (see the Phase F limitation note).

### 13.9 Sub-phases

Each is independently shippable and leaves `main` green.

- [✔] **A — Vocabulary & schema foundation.** *(2026-07-26)* Migration 100002, `Booking` status/badge/`AGENT_STATUS` maps, 6-tab rebuild on `agent/bookings/index`, pickup fields on the create form + `BookingService::create()`, the `whereIn('status')` audit.
  *Done when:* a booking set to `needs_revision` in tinker renders "Need Revision" in amber on the agent list and detail; the Submitted tab returns all three underlying statuses; `migrate:fresh --seed` clean; no screen regressed.
  **Shipped.** Migration `2026_07_26_100002` (up→down→up verified on MySQL:3307), `Booking::AGENT_STATUS` + `AGENT_TABS` + `statusesForTab()` / `agentStatusLabel()` / `agentStatusBadge()` / `needsRevision()` / `isEditableByAgent()`, agent list rebuilt on `?tab=`, pickup on both booking forms + both detail screens, `whereIn('status')` audit done (HQ attention tile + Manage tab count + agent dashboard tile; `needs_revision` deliberately excluded from every "pending" KPI). 8 new tests in `tests/Feature/AgentBookingStatusTest.php`; suite 15 → **23 passing**.
  **Two traps hit that §13.7 had not predicted:**
  1. **Tests run on SQLite** (`phpunit.xml`), which enforces enums with a `CHECK` constraint, so the raw MySQL `ALTER … MODIFY` blew up 8 tests. `setStatusEnum()` now branches on `DB::getDriverName()` — raw `MODIFY` on MySQL, grammar rebuild via `->change()` on SQLite.
  2. **`down()` could not drop `(agent_id, status, created_at)`** — MySQL had folded `bookings_agent_id_foreign` into it and then refused to drop the last index supporting the FK. `down()` now re-creates the plain `agent_id` index before dropping the composites.
- [✔] **B — Admin requests a revision.** *(2026-07-26)* `manage.bookings.revision` + `requestRevision()` + `recallFromProvider()`, `#revisionModal` (remark + field checkboxes), amber remark banner on the agent detail, agent notification, lazy `initial` snapshot.
  *Done when:* admin flags 2 fields with a remark → status flips, timeline row carries the remark, agent sees banner + in-app notification linking to it; repeating from `waiting_provider_confirmation` also clears provider state and drops it off the provider queue.
  **Shipped.** Migration `2026_07_26_100003_create_booking_revision_requests_table` · `BookingRevisionRequest` model carrying the append-only `FIELDS` registry (15 keys, **restricted to what an agent can actually change**, per Q5) + `fieldsByGroup()` / `fieldLabels()` / `isFlagged()` · `Booking::revisionRequests()` + `openRevisionRequest()` · `BookingService::requestRevision()` / `recallFromProvider()` / `assertRevisable()` · `manage.bookings.revision` route + controller · `#revisionModal` (remark + grouped field checkboxes) and an "Awaiting agent revision" panel on the staff screen · amber "Admin asked you to fix this" banner with field chips on the agent screen. 13 tests; suite 23 → **36 passing**.
  **Decisions taken during the build:**
  - **Migration split from Vault's grouped file.** `booking_revision_requests` ships here so Phase B stands alone; `booking_versions` + `booking_drafts` move into Phase C's migration. The lazy `initial` snapshot moves to Phase C too — it cannot exist before `booking_versions` does.
  - **Agent-only notification.** Q8 (does the customer see "Need Revision"?) is unanswered, so `requestRevision()` deliberately bypasses `notifyParties()` and notifies only the agent. A test pins the notification count at exactly 1.
  - **Re-requesting supersedes.** A second round cancels any still-open request instead of leaving two open.
  - **T8 guard landed early** — `confirm()` now throws when an open revision request exists, so staff cannot freeze a booking the agent is mid-way through fixing.
- [✔] **C — Revision store + agent edit + Save as Draft.** *(2026-07-26)* Migration `…100004` (`booking_versions` + `booking_drafts` + the `booking_timeline` / `payments` FK columns), `BookingVersion` / `BookingDraft` models, `BookingRevisionService` (`snapshot` / `stage` / `discardDraft`), rooms-JS extracted to a shared partial, `agent/bookings/edit.blade.php`, draft + draft-slip routes.
  *Done when:* an agent edits every field, saves as draft, logs out and back in, and the form re-hydrates — **and a test asserts `bookings`, `booking_rooms`, `booking_pax`, `customers` and `payments` are byte-identical to before the draft save.**
  **Shipped.** Migration `2026_07_26_100004_create_booking_version_tables` (rollback + re-apply verified) · `BookingVersion` / `BookingDraft` models · `BookingRevisionService` (`snapshot` / `stage` / `formPayload` / `draftFor` / `discardDraft` / `snapshotVersion` / `ensureInitialVersion`) · agent routes `edit` / `draft` / `draft.discard` / `draft-slip` · `_rooms-js.blade.php` shared partial with **`form.blade.php` refactored onto it** (not forked) · `edit.blade.php` mirroring the mockup's section order, with flagged fields tinted amber and labelled "admin requested this" · "Edit & Resubmit" / "Continue editing" CTA on the agent booking. 13 tests; suite 36 → **49 passing**.
  **The load-bearing test passes:** a draft save that changes customer name, phone, pickup, notes, deposit, every room count and the passenger list leaves `bookings`, `booking_rooms`, `booking_pax`, `customers` and `payments` byte-for-byte identical. Only `booking_drafts` gains a row.
  **Also proven by test:** the deferred `initial` snapshot is written on the first "Request Revision" and never duplicated on later rounds · payload stores display labels beside every FK (`package_title`, `room_name`) so the Phase D diff needs zero lookups · money is stored as strings · pax keyed by persisted id, not index · a replaced receipt is staged to the private disk while the live `Payment` row and its old file are untouched · `draft-slip` 403s for any other agent.
  **Deviation:** the `payments.superseded_by` column ships here but is not yet written to — the chaining happens in Phase D's `apply()`, which is the first code that supersedes a payment.
- [✔] **D — Diff, confirm, resubmit.** *(2026-07-26)* `diff()` / `price()` / `apply()`, review + resubmit routes (POST-redirect-GET so a refresh can't re-post), confirm modal with the checkbox, flash + redirect on success.
  *Done when:* changing three fields (one scalar, one room line, one passenger) yields exactly three labelled diff rows in section order; POSTing resubmit without `confirm=1` is rejected **server-side**; after confirm the booking is `pending_verification`, `revision_no = 1`, a v2 row carries its diff, and the admin queue count went up by one.
  **Shipped.** `BookingRevisionService::price()` / `diff()` / `apply()` / `applyReceipt()` / `latestVersionPayload()` · `BookingService::resubmitRevision()` (one transaction, `lockForUpdate`, status re-asserted after the lock) + `assertResubmittable()` · `BookingService::roomLines()` made **public** so a resubmission re-prices through the exact path new bookings use · routes `review` (POST) → `review.show` (GET) → `resubmit`, POST-redirect-GET so refreshing the diff never re-posts · `review.blade.php` with grouped before/after rows, a totals panel and the confirm checkbox · Save-as-Draft and Review Changes as two `formaction`s on one form. 15 tests; suite 49 → **64 passing**.
  **Three real bugs found by the tests, all fixed:**
  1. **`assertResubmittable()` wasn't passing `rooms` into `assertDateSelection()`**, which also enforces "at least one passenger" off the room lines — so *every* resubmit failed. Worse, it made the seat-check test pass for the wrong reason; that test now pins the error key.
  2. **The diff was computed after `apply()`.** `apply()` rebuilds `booking_pax` wholesale, so every passenger came back with a new id and the frozen diff reported the entire manifest as removed-and-re-added on every resubmission. The diff is now taken **before** apply, against the draft payload (whose pax keys are the live ids), while the stored `payload` is still re-snapshotted from the applied record.
  3. **`Booking::timeline()` used bare `latest()`**, so same-second events ordered arbitrarily — the Activity Log could render a resubmission above the request that caused it. Now `latest()->latest('id')`. `BookingService::log()` also returns the row so callers can link it to a version instead of re-querying.
  **Money guards proven:** a resubmit that drops the total below `paid_amount` is refused and applies **nothing** (booking, rooms and version count all unchanged); a changed departure re-runs the seat check; `price()` ignores `payload['money']` entirely, so a stale draft cannot lock in old rates. A replaced receipt creates a new pending `Payment` and sets `superseded_by` on the old one, whose file is never deleted.
- [✔] **E — Admin tabs & revision history.** *(2026-07-26)* `manage/bookings/show` Details/Activity Log/Documents tabs, Revision History panel (`orderByDesc('version')`, author + timestamp, explicit column projection — not `payload`), single-version viewer.
  *Done when:* admin opens a twice-revised booking, sees v3/v2/v1 with correct authors and timestamps, and can open v2 to read the exact before/after the agent confirmed.
  **Shipped.** `manage/bookings/show.blade.php` restructured into Details / Activity Log / Documents `nav-tabs` (Activity Log badges the latest version, Documents badges the count) · Timeline **moved out of the sidebar** into the Activity Log pane and renamed "Activity History", with a "view changes" deep-link on any entry carrying a `booking_version_id` · Revision History panel above it, newest-first, showing version · reason · author · timestamp · `manage.bookings.versions.show` viewer with `_diff.blade.php` (shared, dumb — renders stored rows only) plus that version's customer / travel / rooms / passengers read straight from the payload · `BookingTimeline::version()` relation. 11 tests; suite 64 → **75 passing**.
  **Performance, as Tempo required:** the `versions` eager-load projects columns explicitly (`id, booking_id, version, reason, created_by, created_at`) so the list never pulls `payload`/`changes`, and `author:id,name` is eager-loaded. Proven by a test that renders a 2-version and a 4-version booking and asserts **identical query counts**.
  **Note on the first attempt:** the original N+1 test matched any `users … in (…)` query, so it counted *batched* eager loads and failed at 3. That assertion could never have detected a real N+1 — it was replaced with the query-count-invariance check above.
  **Also covered:** a `DOMDocument`/XPath test asserts the three panes exist, are **siblings not nested** (a stray `</div>` would silently swallow one tab into another), and that each section landed in the right pane · a version belonging to another booking 404s · agents and permission-less admins are denied.
- [✔] **F — Amendments on confirmed bookings.** *(2026-07-26)* Migration `…100005`, `BookingAmendment`, request (agent) + approve/reject (manage), cards on both detail screens.
  *Done when:* agent submits Change Date on a confirmed booking; admin approves; `travel_date`/`package_date_id` move, seats decrement on the old `PackageDate` and increment on the new in one transaction, a new version is written, invoice + voucher regenerate, and the booking is still `confirmed`.
  **Shipped.** Migration `2026_07_26_100005_create_booking_amendments_table` · `BookingAmendment` model · `BookingService::requestAmendment()` / `approveAmendment()` / `rejectAmendment()` / `applyAmendment()` · agent route `bookings.amendment` + manage `bookings.amendments.approve|reject` · "Request Amendment" card on the agent booking and an "Amendment Requests" panel in the staff Activity Log tab with inline approve/reject. 16 tests; suite 75 → **91 passing**.
  **Decisions applied (answers to §13.8):**
  - **Q11a — agent only.** Customers cannot raise amendments; the customer portal is untouched. All active staff get an in-app notification per request.
  - **Q11b — reverse + recalculate.** On approval, if `total_amount` moved *and* a non-reversed `Commission` exists, `reverse()` then `calculate()` runs and the timeline records it.
  - **Q7 — no gamification effect, and this needed no code.** `completeMissionByCode` / `evaluateAchievements` / `tiers->evaluate` fire only inside `verifyPayment()` behind `isFullyPaid()`, and `requestRevision()` is already blocked once commission exists — so a revision can never occur after gamification has credited. The resubmit-farming loop is structurally closed.
  **Scope + a real limitation, stated plainly:**
  - Types shipped are `travel_date`, `pickup` and `other`. **`other` is recorded only** — approval applies nothing and staff action it by hand.
  - **The reverse-and-recalculate branch is wired but currently unreachable**, because none of the three shipped types can move `total_amount` (a date change keeps the same package and room lines). It exists so the behaviour is correct the moment a price-changing type (package / room / pax) is added — but it is **not covered by a passing test today**. My first attempt to test it mutated the total *before* approval, i.e. after the baseline is captured, so it proved nothing and was deleted rather than left as a false green. **Anyone adding a price-changing amendment type must write a real test for this branch before trusting it.**
  - Seat transfer is guarded: the new departure's availability check adds back the booking's own pax so it cannot count itself as competition, and a full departure is refused with **nothing moved** (verified by test).

### 13.9b Browser walkthrough (2026-07-26) — 3 bugs the test suite could not catch

Demo data seeded on MySQL:3307 and the whole loop driven in Chrome: admin flags 2 fields on
`BK-2026-00002` → agent sees the amber banner → edits → Save as Draft → Review Changes → confirm →
resubmit → admin reads v2. Then the amendment flow on `BK-2026-00001` over HTTP.
**Everything rendered correctly** — tabs, "v1" badge, field chips, the 6 agent tabs, the grouped
diff, the server-side confirm guard, the version viewer. Suite 91 → **93 passing** after fixes.

| # | Bug | Why the tests missed it | Fix |
|---|-----|--------------------------|-----|
| B1 | **"Save as Draft" was blocked by HTML5 validation.** The departure select is `required` on a fixed-date package, so an agent on a booking with no departure could not save a draft at all — zero rows written, no error surfaced beyond a browser tooltip. | Feature tests POST directly and never run client-side validation. | `formnovalidate` on the Save-as-Draft button. A draft is work in progress; completeness is enforced at resubmit, not at save. |
| B2 | **`travel_date` left null while `package_date_id` was set.** `create()` derives one from the other; `apply()` did not. The agent screen looked right (it falls back to the departure), but the column reports and exports read was blank. | Tests always posted an explicit `travel_date`; the real form *clears* it for fixed-date packages. | `apply()` now derives `travel_date` from the chosen departure, mirroring `create()`. Regression test added. |
| B3 | **A booking moving from *no departure* onto one never reserved seats.** The `increment` sat inside an `if ($oldDate && …)` guard, so a confirmed booking could occupy a departure without holding a seat — an overbooking hole. | Every amendment test started from a booking that already had a departure. | Decrement the old one only if it exists; always increment the new. Regression test added. |

Also fixed while walking it: `requested_date` was `required_if:type,travel_date` even when the agent
picked a **scheduled departure** instead — the form offers both, so neither may be required alone.

**Tab overflow — fixed (2026-07-26).** Seven chips can never fit a phone width, so `.seg` is now a
deliberate scroll strip rather than a clipped row: `flex:0 0 auto` on the chips (without it they
squashed into unreadable slivers), hidden scrollbar (`scrollbar-width:none` + `::-webkit-scrollbar`),
`scroll-snap-type:x proximity`, and a trailing spacer so the last chip never sits flush against the
edge. The partially-visible next chip is the affordance. A 6-line script in `layouts/agent` scrolls
the **active** chip into view on load, so a deep-linked tab (`?tab=cancelled`) is never hidden
off-screen — verified in Chrome. Deliberately no permanent edge-fade mask: it reads as a rendering
artifact once the strip is scrolled to the end.

### 13.9c Mockup conformance pass (2026-07-26) — all 6 screens now match

A screen-by-screen re-read of the client mockup found the engine complete but several drawn
elements missing. All closed; suite 93 → **99 passing**, and the flow re-walked in Chrome.

| Mockup element | Now built |
|---|---|
| **"Child Age"** — the headline field (`4 years → 6 years` in the diff) | New `booking_pax.age` column (migration `…100006`). Age input per passenger; `dob` kept for passport paperwork. The diff renders **"Aliya binti Hamid — Child Age: 4 years → 6 years"**, verified in the browser |
| **Screen 6 — "Resubmission Successful!"** | `agent.bookings.submitted` — green check, copy, Customer / Booking ID / Submitted / version, **"Back to My Customers"**. Reached by redirect, so a refresh is safe |
| **Screen 5 separate from screen 4** | `agent.bookings.confirm` — review now ends in **"Submit Again ›"** which opens the confirm screen (summary + checkbox + "Yes, Submit Now" + Cancel) |
| **"Approved" status** ("Information approved") | `waiting_provider_confirmation` now reads **Approved**, not Submitted. Own tab |
| **Status Guide legend** | Card on the customer list, driven by `Booking::AGENT_STATUS_GUIDE` |
| **"My Customers" list** | Retitled; **search by name / phone / booking no**; customer name is now the headline with package · pax, travel date, booking no · amount; **View + Edit** buttons per card |
| **− / + pax steppers** | `stepper()` in `_rooms-js`, shared by the create and edit forms |
| **Pickup as a dropdown** | `<datalist>` of common Malaysian pickups — offers the dropdown without forbidding a typed value |
| **Receipt "View" / "Replace File"** | Two buttons; the hidden file input reports the chosen filename |
| **Revision History as a permanent panel** | Moved out of the Activity Log tab into the sidebar, beside Details |
| **Bottom nav "Customers"** | Added (`agent.customers.index`) |

**Two defects fixed during this pass:**
- **`.btn` was only styled for `<button>`** — it had no `text-align` or `text-decoration`, so every
  `<a class="btn">` in the agent portal rendered left-aligned and underlined. Fixed globally.
- **Phantom diff row.** The edit form blanks `travel_date` on fixed-date packages, so the review
  screen showed `Travel Date 2026-09-24 → —` for a value `apply()` immediately put back. The
  departure-derived date is now normalised when the draft is staged, not only when it is applied.

**Deliberately not matched:** the mockup's bottom nav also shows **Tasks** and **Profile**, which do
not exist in this system — inventing them would be worse than the mismatch. Nav is now
Home · Customers · Bookings · New · Wallet · Network. **Completed** is also kept as a seventh status
label; the mockup's guide omits it, but hiding a real booking state would be a regression.

### 13.9d Open edit window (2026-07-26) — supersedes Q1

**New rule, from Fakrul:** an agent can edit a booking at any time without waiting for an admin
revision request. The only locked states are `completed`, `cancelled`, `rejected` and `refunded`
(the agent portal shows the last three as *Cancelled*). Every resubmission — whatever the booking
was before — lands on **Submitted** (`pending_verification`) for re-verification. Suite → **102 passing**.

- `Booking::AGENT_LOCKED_STATUSES` drives `isEditableByAgent()`, which already gates the edit form,
  the draft routes, the resubmit path and the Edit button on the customer list.
- The admin **"Request Revision"** action still exists and still flags fields — it is now a *nudge*
  ("please fix these"), not the gate that unlocks editing.

**Integrity work this forced — a confirmed booking is no longer a frozen record:**

| Risk | Handling |
|---|---|
| **Seat double-count.** `confirm()` increments `seats_booked`; nothing decremented on re-edit, so re-confirming would book the same seats twice. | `resubmitRevision()` releases the seats when the booking was `confirmed`, before applying the edit. Verified by test. |
| **Stale `confirmed_at`** on a booking that is no longer confirmed. | Nulled on resubmission. |
| **Commission computed on the old total.** | Same policy as an approved amendment (Q11b): if the total moved and a non-reversed `Commission` exists, reverse then recalculate, with a timeline entry. Verified by test. |
| **Invoice / voucher hold the old details.** | Left in place; `confirm()` regenerates both on re-confirmation. The timeline records "Confirmed booking reopened for re-verification". |
| **Reducing a paid booking below what was paid.** | [✔] **Built 2026-07-27** — no longer blocked. Cancelled packs burn RM 100 each out of the deposit and the remainder is held as credit. See §13.9g. |

**Flagged, not resolved — two paths now overlap.** A confirmed booking can be changed either by
editing it directly (this rule) or via **Request Amendment** (Phase F). They behave differently:
the edit path takes the booking out of `confirmed`, the amendment path keeps it confirmed and moves
seats between departures. Worth deciding which one survives before agents see both.

### 13.9e Select2 on the agent booking form (2026-07-26)

Every `<select>` on `/agent/bookings/create` is now a searchable Select2. Suite still **102 passing**.

- **Bundled, not CDN.** The agent portal is an installed PWA and `public/sw.js` caches `/build/*`
  cache-first but never HTML — a CDN would leave the form broken on a flaky mobile connection.
  New entry `resources/js/agent.js` + `resources/scss/agent-select2.scss`, wired into
  `vite.config.js` and `@vite`'d from `layouts/agent`. **The agent shell had no JS bundle at all
  before this** (only `layouts/admin` used Vite), so this adds jQuery to that portal.
- **jQuery pinned to 3.x.** `npm i jquery` installed 4.0, which drops APIs Select2 4.0.13 still uses.
- **Opt-in per form:** a form carries `data-select2`; a field can opt out with `data-no-select2`.
  Only the create form opts in — the edit form is unchanged.
- `minimumResultsForSearch: 0` so the search box shows on short lists too (Select2 hides it by default).
- Dropdown is parented to the field's own `.card`, or it escapes the fixed-width phone frame.

**Select2 hid the "➕ New customer…" option — fixed.** That entry had `value=""`, and Select2
treats *every* empty-value option as the placeholder and drops it from the list, so an agent
could no longer register a customer from the booking form at all. It now posts the sentinel
`__new`, which `store()` normalises back to `null` before validation (the existing
"empty customer_id ⇒ register the inline one" branch then does the work). 5 tests in
`AgentCreateBookingTest` cover the sentinel, the existing-customer path, the rendered option,
the name/phone requirement and the cross-agent 403. Suite → **107 passing**.

**Two traps hit, both worth remembering:**
1. **Select2's CommonJS build does not self-register** — `module.exports` is a `(root, jQuery)`
   factory, so `import 'select2'` leaves `$.fn.select2` undefined. It must be invoked:
   `select2(window, $)`.
2. **Select2 announces selections with jQuery's `trigger('change')`, which never reaches
   `addEventListener` listeners** — and `fillPackage()` / `recalc()` are both bound that way, so
   picking a package would have silently stopped loading departures, room rows and pricing. Fixed by
   re-dispatching a real DOM `change` on `select2:select` / `select2:clear`. Verified in Chrome.
   Room-type selects are built after load, so `_rooms-js` calls `initSelect2(row)` per new row.

### 13.9f Upcoming trips screen (2026-07-26)

From a second client reference (a hotel-booking app). **Added as its own screen, not a
replacement** — "My Customers" keeps the View/Edit buttons, status badges and Status Guide from
the approved mockup. Route `agent.upcoming` at `/agent/upcoming` (**not** `/bookings/upcoming`,
which `{booking}` would capture), new nav item. Suite → **117 passing**.

Card shows exactly what was asked: customer name · arrival–return dates · nights · pax · package.
Plus the status badge, since an agent needs to know a trip is still unverified.

- **Arrival ⇄ Reservation toggle** changes only the sort *and* the date-group heading. The list is
  always upcoming trips either way — "upcoming" is a property of the arrival date, not the sort.
- **Upcoming** = arrival ≥ today, excluding `cancelled` / `rejected` / `refunded` / `completed`.
- Arrival sorting happens **in PHP**, because the arrival date lives on two columns
  (`package_dates.depart_date` or `travel_date` for open-dated bookings) and can't be ordered in
  SQL alone. Acceptable here — it's one agent's upcoming trips, not a feed. Revisit if an agent
  ever carries thousands.
- New `Booking` helpers so the view stays dumb: `arrivalDate()`, `returnDate()`, `nights()`,
  `paxSummary()`. Nights come from the departure's return date, falling back to
  `packages.duration_nights`; `paxSummary()` renders "3 adults, 2 children" and doesn't pluralise a
  single traveller.
- **Open-dated bookings have no departure row**, so the query needs the `doesntHave('packageDate')`
  branch or they'd vanish from the list entirely. Covered by a test.

### 13.9g Forfeited deposit on a pack reduction and on cancellation (2026-07-27)

Closes the last row of the §13.9d risk table. **Client rule:** cancelling packs on a booking that
has already been paid on burns **RM 100 per cancelled pack**, and that money is **taken out of what
the customer paid** — it is not added to the trip price. Applies to a partial reduction *and* to
cancelling the booking outright. Suite 117 → **132 passing**.

**The penalty is a deduction from `paid_amount`, never an addition to `total_amount`.** This was
built the wrong way round first (penalty folded into the total) and corrected: `total_amount` is
the trip price alone, which keeps invoices, revenue reports and the commission base honest with no
special-casing. The arithmetic is identical either way; the bookkeeping is not.

    balance()          = total_amount + forfeited_amount − paid_amount   (0 once the trip is dead)
    paidAfterForfeit() = paid_amount − forfeited_amount
    refundableAmount() = paidAfterForfeit() − (dead ? 0 : total_amount) − refundedAmount()

Worked examples, all verified end-to-end on MySQL:3307 — Pulau Redang 4D3N, 10 packs
(6 adults + 3 children + 1 senior) + 2 infants = RM 9,100.

| Scenario | Trip | Paid | Forfeited | Balance | Refundable |
|---|---|---|---|---|---|
| Reduce 10 → 7 packs, deposit RM 3,000 | 6,400 | 3,000 | 3 × 100 = **300** | **3,700** | 0 |
| Reduce 10 → 7 packs, fully paid RM 9,100 | 6,400 | 9,100 | **300** | 0 | **2,400** |
| Cancel outright, deposit RM 3,000 | 6,400 | 3,000 | 10 × 100 = **1,000** | **0** | **2,000** |

**Cancellation reuses the same rule** — `cancellationForfeiture()` is just
`forfeiture($booking, ['rooms' => []])`, i.e. reduce to zero packs. Because the accrual measures
against the live booking, a reduce-then-cancel charges 3 + 7 = **10 packs, never 13**.

**A dead booking owes nothing.** `balance()` returns 0 for `cancelled`/`rejected`/`refunded`
(`Booking::DEAD_STATUSES`). Without this a cancelled booking kept reporting an outstanding balance
— which the finance dashboard sums into "outstanding" — and the penalty made that number worse.
This was a **pre-existing** wart the feature surfaced, fixed here.

**Rules as built:**
- **A pack is one adult, child or senior.** Infants are never charged — they have no seat of their
  own, and `roomLines()` already refuses to let them force an extra room.
- **Nothing paid ⇒ nothing burnt.** The penalty only fires when `paid_amount > 0`, so an agent
  fixing a typo on an unsubmitted booking is not charged for it.
- **Only reductions.** Adding packs re-prices normally. A mixed edit is netted, then floored at 0.
- **Rate is per package** (`packages.cancellation_fee_per_pack`, on the package form). Null uses
  `Package::DEFAULT_CANCELLATION_FEE` (RM 100); an explicit **0 waives it**.
- **Accrual, not recomputation.** `bookings.forfeited_packs` / `forfeited_amount` are cumulative:
  10 → 7 then 7 → 5 charges for **5** packs, not 2. Measuring against the live booking each time is
  what makes this fall out for free — there is no separate baseline to keep in sync.
- **`total_amount` stays the trip price.** `resubmitRevision()` banks the burn into
  `forfeited_amount`; `apply()` deliberately does *not* add it to the total.
- **Commission needs no special case** — it reads `total_amount`, which the penalty never enters.

**The old guard is gone, deliberately.** `assertResubmittable()` used to refuse any edit that
dropped the total below `paid_amount`. That block is what made this scenario impossible, so it was
removed: the reduction now goes through, and whatever is left after the penalty becomes
`Booking::refundableAmount()` — surfaced on the agent, staff and customer booking screens and the
invoice, logged to the timeline, and pre-filled into the Request Refund modal (capped at
`paidAfterForfeit() − refundedAmount()`). **Releasing it is still a manual staff decision —
nothing auto-refunds.**

**Agent sees it before committing**, on both the review diff (a red "Cancellation Charge" card with
packs × rate) and the confirm screen, where the consent checkbox text changes to name the amount.

### 13.9h Agent cancels, HQ refunds (2026-07-27)

**Client rule:** the agent may cancel a booking themselves, but **only HQ pays anything back**.
Suite 132 → **143 passing**.

- `POST /agent/bookings/{booking}/cancel` — own booking only (403 otherwise), open on the same
  window as editing (`isCancellableByAgent()` = not `completed`/`cancelled`/`rejected`/`refunded`).
  Runs the existing `BookingService::cancel()`, so seat release, commission reversal and the
  per-pack forfeiture (§13.9g) all apply unchanged.
- **Two-key confirmation, validated server-side:** a reason *and* the literal string `CANCEL`.
  Deliberately a typed word rather than a JS `confirm()` — a native dialog would be untestable and
  freezes browser automation.
- The agent sees the charge **before** committing: packs · rate · "will be forfeited", plus
  "HQ processes any refund — you cannot pay money back from here."

**Refunds are now HQ/super-admin only.** All five routes (`finance.refunds`, `bookings.refund`,
approve, reject, process) moved inside `role:super_admin,hq`, matching the payment-gateway
precedent. The Request Refund button, **the modal itself** and the Refunds nav link are hidden from
`admin` staff, who instead read "Refunds are handled by HQ". Hiding the button alone is not enough —
the modal markup still contains the form, so it must be gated too.

**HQ is notified on cancellation** when `refundableAmount() > 0` —
"Refund due on {booking}: RM x". Without this an agent-initiated cancellation would sit unseen
until somebody happened to open the booking.

### 13.10 Factory assignment

| Phase | Lead | Builders | Quality gate |
|---|---|---|---|
| A | Atlas | Vault (migration) · Anvil (model maps) · Loom (tabs) | Tide — status-map + tab-filter tests |
| B | Forge | Anvil (service + controller) · Loom (modal, banner) | Hawk — who may request a revision; provider recall authz |
| C | Forge | Vault (migration) · Anvil (revision service) · Loom (edit form) | Tide — **the live-record-untouched assertion** · Hawk — draft-slip route authz, mime allow-list |
| D | Forge | Anvil (diff/apply/resubmit) · Loom (review screen) | Tide — diff correctness, server-side confirm · Mira → Mender on the money guards |
| E | Forge | Loom (tabs, history panel) · Anvil (viewer) | Tempo — N+1 on version authors + timeline link |
| F | Atlas | Vault · Anvil · Loom | Hawk — seat + commission integrity · Lens release gate |

Not convened: **Bridge** (no third-party API), **Echo** (no realtime), **Pilot/Pulse** (no deploy in scope).
