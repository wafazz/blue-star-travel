# Session Memory - Blue Travel (TAMS)
> Last updated: 2026-07-19

## Session Context
- **Project**: Blue Travel — Travel & Tour Management System (Blue Star Travel And Tours)
- **Profile**: ~/Desktop/MemoryCore Project/Projects/41-blue-travel.md
- **Branch**: (no git yet)
- **Status**: active
- **Focus**: **CODE-COMPLETE** — all 10 phases (0–9) shipped. Remaining = client-side (FPX vendor, KPDN licence, deploy).

## Current Tasks
- [✔] Phase 0: Foundation — Laravel 12 + BS5 (blue) + role-based multi-portal auth, mockups→Blade.
- [✔] Phase 1: Core Catalog — company/providers/packages/customers CRUD under `/manage`.
- [✔] Phase 2: Booking Engine — creation, approval workflow, timeline, provider respond, documents.
- [✔] Phase 3: Payments & Finance — FPX sandbox gateway, payments screen, receipts, refunds, finance dashboard.
- [✔] Phase 4: Commission — dynamic-depth MLM cascade, wallet, withdrawals, reversal.
- [✔] Phase 5: Agent Gamification — missions, check-in/streak, points ledger, 7-item redeem catalog, 10 achievements (auto-unlock), monthly leaderboard, referral/network. Agent dashboard mockup now fully data-driven.
- [✔] Phase 6: Marketing & Support — notifications engine (in-app bell + channel stubs, wired to booking/commission/withdrawal/redemption/ticket/broadcast events), coupons (wired to booking discount), banners, marketing materials + agent download center, support ticketing (agent open → staff queue + threaded reply + status).
- [✔] Phase 7: Reports & Analytics — 7 reports (sales/booking/package/customer/agent/commission/financial) via ReportService, unified filter+KPI+chart+totals view, PDF/Excel/CSV export.
- [✔] Phase 8: Customer Portal — register (+referral `?ref=`), public package catalog, self-service booking, FPX/slip payment, my-trips + documents, passport profile, customer support tickets. Demo state now fully seeded (ShowcaseSeeder + CustomerPortalSeeder).
- [✔] Phase 9: Hardening — security audit (2 CRITICAL, 2 HIGH, 3 MED, 2 LOW — all fixed & re-tested), HQ + Admin dashboards wired to real data, DEPLOY.md, .env.example.

## Working Memory
### Active Context
- **DB live on MySQL:3307**, db `blue_travel`, root/no-pass. `php artisan migrate:fresh --seed` rebuilds clean demo.
- **Demo data is fully seeded** — `migrate:fresh --seed` rebuilds the whole showcase (ShowcaseSeeder = agent sale → provider approve → confirm → paid → MLM cascade → approved wallets; CustomerPortalSeeder = customer profile, referred customer, online booking + deposit, support ticket). Never hand-make demo records in tinker again.
- Staff back-office = single guard, RBAC role (super_admin/hq/admin) at `/hq/*` + `/admin/*` + shared `/manage/*`. Agent + Customer are public portals on the blue mockups; Provider uses admin layout w/ custom nav.
- Two Blade layouts: `layouts/admin` (BS5 staff) + `layouts/agent` (mobile phone shell for agent portal).
- Verification pattern: `php artisan tinker --execute="..."` for service flows + `php artisan serve --port=8901` + curl-login for HTTP/view checks. `grep -viE "warning|redis"` to strip noise.

### Decisions Made
- Commission = **MLM multi-level, dynamic depth** (admin CRUD commission_levels = cascade depth; L1 8/L2 4/L3 2). Base = booking total. Orphan→HQ. Pending→HQ approve→wallet (KPDN safeguard).
- **FPX vendor = Billplz** (decided 2026-07-20, more gateways later) — built behind a pluggable driver layer so adding ToyyibPay/senangPay/Bayarcash is one class + one config block.
- Finance + report charts = pure CSS bars (no Chart.js dependency).
- Reports = one `ReportService::build(key, filters)` returning a uniform payload (columns/rows/totals/kpis/chart) so the view, PDF and Excel/CSV exports all consume the same shape.

### Blockers / Open Questions
- FPX real vendor, SMS/WhatsApp vendor — still parked (Planning §10). Sandbox gateway swappable.
- KPDN Direct-Sales licence needed before real MLM payouts (compliance) — commission default-deny gate already in place.

## Demo Logins (all password: `password`)
- Staff: super@ / hq@ / admin@bluetravel.com · Provider: provider@bluetravel.com (linked to Provider #1)
- Agents: agent@bluetravel.com (root BT-AG001) · nadia.agent@ (BT-AG002) · imran.agent@ (BT-AG003)
- Customer: customer@bluetravel.com · Demo booking BK-2026-00001 (confirmed+paid showcase)

## Session Recap
> This section survives resets. Keep it under 30 lines.

### What Was Done
- Iris init → Planning.md (4 modules, 10 phases), MemoryCore #41, session memory.
- **Phases 0–8 built & verified** on MySQL:3307:
  - P0 Foundation · P1 Core Catalog · P2 Booking Engine · P3 Payments & Finance (FPX sandbox, receipts, refunds) · P4 Commission (dynamic-depth MLM) · P5 Agent Gamification · P6 Marketing & Support · P7 Reports & Analytics · P8 Customer Portal.
- Full per-phase detail (files, services, verification) lives in MemoryCore profile #41.

### Where We Left Off
- **Agent + Customer portals are now PWAs (2026-07-20)** — installable from the login page, as asked.
  - `public/manifest-agent.webmanifest` (name "Blue Star Agent", scope `/agent/`, start `/agent/login`, shortcuts: New Booking / Wallet / Leaderboard) and `public/manifest-customer.webmanifest` (name "Blue Star Travel & Tours", scope `/`, start `/login`, shortcuts: Browse Packages / My Trips). Separate manifests = two distinct home-screen apps.
  - Icons generated with PHP GD from `logo-icon.png`, **flattened onto white** (iOS renders PNG alpha as black): `icon-192`, `icon-512`, `icon-maskable-512` (logo at 72% for the Android circular safe zone).
  - `resources/views/partials/pwa.blade.php` — manifest link + `theme-color` + iOS `apple-mobile-web-app-*` tags + SW registration + `beforeinstallprompt` capture. Included in `layouts/agent`, `layouts/customer`, `auth/agent-login`, `auth/customer-login`, `auth/customer-register`. "⬇️ Install … app" button (`data-install-app`, hidden until the prompt fires) on both login pages.
  - **`public/sw.js` — security-shaped caching.** These portals show passports, bookings and commission on shared phones, so **HTML responses are NEVER cached**: navigations are network-only with `/offline.html` as the failure fallback; only `/images/*`, `/build/*` and static file extensions are cache-first. `/pay/*`, `/download` and `/export` are skipped entirely. Verified in Chrome: cache contained only 5 static assets + offline.html, zero HTML pages.
  - Verified live: SW registers and activates (scope `/`), both manifests parse, `beforeinstallprompt` fires (Chrome considers them installable), install buttons appear, and with the **server killed** a navigation renders the branded offline page from cache. 18-URL regression + 15 tests still pass.
  - **iOS Add-to-Home-Screen hint** on both login pages (`data-ios-install`, ships `hidden`): iOS has no `beforeinstallprompt`, so it shows written instructions with an inline iOS share glyph — "Install …: tap ⎋ Share, then **Add to Home Screen**". Revealed only when iOS **and** not already installed (`navigator.standalone` / `display-mode: standalone`), so it never double-ups with the Android install button and disappears once installed. Detection covers iPhone, old iPad, and **iPadOS 13+ which reports as MacIntel** (disambiguated by `maxTouchPoints > 1`) — verified true for iPhone/iPad/iPadOS and false for Android, macOS and Windows.


- **Payment Gateway Configuration page added (2026-07-20)** — `/manage/payment-gateway`, `Manage\PaymentGatewayController` + `manage/settings/payment-gateway.blade.php`, nav under Company. **HQ/super_admin only** (`role:super_admin,hq` inside /manage) since these are money credentials — the `admin` role gets 403 and the nav link is hidden.
  - Settings live in the `settings` table (`payment.driver`, `payment.billplz.key|x_signature|collection_id|sandbox`) and **override .env**; `PaymentGatewayManager::configFor()` merges file/env defaults with the DB values, so no redeploy is needed to change gateway.
  - **Secrets encrypted at rest** (`Crypt::encryptString`, decrypt wrapped in try/catch so a rotated APP_KEY degrades to the env fallback instead of crashing). The form only ever shows `••••••••3456`; submitting blank keeps the stored value.
  - Guard: switching the active gateway to Billplz is refused unless key + X-Signature + collection ID are all present. **Test Connection** hits `GET /v3/collections/{id}` and reports 401 (bad key) / 404 (bad collection) / OK with the collection title + sandbox-vs-live.
  - Verified: page 200 for super_admin, 403 for admin, nav hidden for admin · half-configured switch refused · save → encrypted rows (grep for plaintext = none) · blank-secret keeps value · manager resolves BillplzGateway with the DB creds · **webhook then validated against the DB-stored X-Signature (correctly signed → accepted, forged → 403)** · sandbox checkout 404s while a live driver is active. Dev env reset to `sandbox` with the dummy credentials cleared afterwards. 15 tests still pass.


- **Billplz integrated (2026-07-20)** — Fakrul chose Billplz first, more vendors later, so it was built as a **driver layer**: `config/payments.php` (`PAYMENT_GATEWAY` selects the driver) + `App\Services\Gateways\PaymentGatewayDriver` contract + `BillplzGateway` / `SandboxGateway` + `PaymentGatewayManager`. Adding a vendor later = one class + one config block; the routes are already driver-generic (`/pay/webhook/{driver}`, `/pay/return/{driver}`).
  - Flow: `initiate` creates the pending payment → Billplz **create bill** (amount in cents, HTTP Basic with API key as username) → redirect to Billplz → signed webhook → **re-query the Billplz API** → settle. Credited only when the VENDOR confirms `paid` AND the amount covers it; short payments are logged for manual review, repeat callbacks idempotent.
  - Signature: HMAC-SHA256 over `key.value` pairs joined by `|`, where the **concatenated strings are sorted, not the bare keys** — that's what puts `paid_amount…` before `paid…` in Billplz's own example. Verified against their documented example in a test.
  - `pay/webhook/*` is CSRF-exempt (server-to-server, no browser) — the signature IS the authentication. Documented in `bootstrap/app.php`.
  - Tests: `tests/Unit/BillplzSignatureTest` (6) + `tests/Feature/BillplzWebhookTest` (7) — forged signature, unsigned payload, tampered amount, vendor-says-unpaid, short payment, replay, unknown ref. **15 tests pass** overall.
  - `.env` keys: `PAYMENT_GATEWAY`, `BILLPLZ_KEY`, `BILLPLZ_X_SIGNATURE`, `BILLPLZ_COLLECTION_ID`, `BILLPLZ_SANDBOX`. Still `sandbox` locally — **Fakrul must paste real sandbox credentials to run a live end-to-end test**. `DEPLOY.md` §7 rewritten with setup + how-a-payment-is-trusted + how to add the next gateway.


- **Official Blue Star logo applied system-wide (2026-07-20).** Source `LOGO-02.png` → `public/images/`: `logo.png` (1000px, UI), `logo-icon.png` (512px, star cropped — sidebar/app icon), `logo-print.jpg` (420px flattened, PDFs), `apple-touch-icon.png` (180), `public/favicon.png` (64). Built with `sips` (`-c H W --cropOffset y x` for the star crop).
  - Placed: all 4 login screens + register (white tile on the blue hero, replacing the 🌏/✈️ emoji + "Blue Travel" wordmark — the logo carries the name), admin sidebar (icon + "Blue Star"), landing nav, and all 4 PDF templates (invoice/receipt/report letterheads; voucher gets a white plate on its navy banner). Favicon partial `partials/favicon.blade.php` included in all 10 standalone `<head>`s.
  - `Company::logoPath()` returns a print-safe path for DomPDF, preferring the uploaded company logo.
- **Bug found & fixed while doing it:** Fakrul had uploaded the logo via Manage → Company Profile as a 3200×3200 alpha PNG. DomPDF decodes PNG alpha uncompressed (~41MB) and **every PDF in the app died with a memory-limit fatal**. `Company::logoPath()` now builds and caches a downscaled flattened JPEG beside the upload (GD, memory limit lifted only for that one-off conversion) and falls back to the bundled logo. Any large logo upload is now safe.
- Verified: 16-URL regression across all portals 200 · invoice/voucher/receipt/report PDFs regenerate and visually show the logo · report PDF export 200.


- Phase 9 done — **project is code-complete**. Ran a full security audit (all controllers/services/models/routes/views), verified each finding **by exploit** before fixing and re-tested after:
  - **C1 CRITICAL** booking PDFs were on the PUBLIC disk at a guessable path → fetched a real invoice with no session (passport numbers, prices). Now private disk + random filename suffix, served only through the ownership-checked controller; leaked public copies purged.
  - **C2 CRITICAL** sandbox FPX callback trusted `result=success` from the payer's own browser → a customer cleared RM23,400 for free and minted RM3,276 commission. Simulated success now leaves the payment **pending staff verification**; checkout/callback 404 when `APP_ENV=production`.
  - **H1** payment slips → private disk + gated `payments.slip` route. **H2** throttle:5,1 on all logins + register (verified 429 on 5th).
  - **M1** provider `null === null` ownership bug; **I also found the list had the same bug** (`where('provider_id', null)` → `IS NULL`). Fixed both. Discovered the demo provider account was never linked to a Provider row — the portal only appeared to work *because* of the bug. Seeder now links it to Saudi Umrah Operator (2 bookings).
  - **M2** marketing upload mime allow-list · **L1** notification redirect restricted to APP_URL · **L2** redemption note validated.
- HQ Executive + Admin dashboards (the last two mockup screens) now fully data-driven; `Package::bookings()` added.
- `DEPLOY.md` written (requirements, first deploy, env, queue, security checklist, demo-data warning, **real-gateway integration steps**, KPDN note, smoke test, update procedure). `.env.example` made project-accurate.
- Verified: 39-URL regression across all 5 portals all 200 · cross-portal + cross-account isolation all 403 · public doc path 403 · legit staff payment-verify flow intact.
- **Remaining (client-side, not code):** pick the real FPX vendor and wire it per DEPLOY.md §7 · KPDN Direct Sales licence before real payouts · rotate APP_KEY/DB creds/demo passwords at deploy.
- Known deferred: booking modify/reschedule · manual document upload UI (flight ticket/visa/insurance) · credit notes · commission maturity-hold · leaderboard daily/weekly/yearly filters · real email/SMS/WhatsApp vendor · campaign builder · scheduled/emailed reports · customer loyalty-point auto-award.
