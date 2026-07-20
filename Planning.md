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
