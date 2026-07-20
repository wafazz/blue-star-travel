<p align="center">
  <img src="public/images/logo.png" width="220" alt="Blue Star Travel And Tours">
</p>

<h1 align="center">Blue Travel — Travel & Tour Management System</h1>

<p align="center">
  A dynamic, multi-layer travel agency platform — <b>HQ → Admin → Agent → Customer</b> — with
  MLM commission, agent gamification, provider workflow, FPX payments and full reporting.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white" alt="Bootstrap 5">
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/PWA-installable-1466ff" alt="PWA">
  <img src="https://img.shields.io/badge/status-code--complete-16a34a" alt="Status">
</p>

---

**Client:** Blue Star Travel And Tours Sdn Bhd · **Vendor:** CodexLure Technology

Agents sell travel packages (Domestic, International, Umrah, Cruise, Free & Easy, Custom), earn
multi-level commission, and are driven by a gamified engagement layer. HQ oversees the operation,
Admin processes the day-to-day booking/payment workflow, Providers confirm the bookings, and
Customers book and pay online.

---

## ✨ Features

| Module | What it does |
|--------|--------------|
| 🗂 **Core Catalog** | Company profile, providers, packages (pricing tiers, dates/seats, itineraries, terms), customers |
| 🧾 **Booking Engine** | Booking creation, approval workflow, status timeline, provider respond, invoice/voucher documents |
| 💳 **Payments & Finance** | Pluggable FPX gateway (Billplz + sandbox driver), payment slips, receipts, refunds, finance dashboard |
| 🌐 **Commission (MLM)** | Dynamic-depth cascade (admin-editable levels), agent wallet, withdrawals, reversal on refund |
| 🎮 **Agent Gamification** | Missions, daily check-in & streaks, points ledger, reward redemption, 10 auto-unlock achievements, monthly leaderboard, referral network |
| 📣 **Marketing & Support** | Notification engine (in-app bell + channel drivers), coupons, banners, marketing material download centre, threaded support ticketing |
| 📊 **Reports & Analytics** | 7 reports (sales, booking, package, customer, agent, commission, financial) with unified filters, KPIs, charts and PDF / Excel / CSV export |
| 🧳 **Customer Portal** | Public catalog, self-service booking, online payment, my-trips + documents, passport profile, support |

## 🔐 Portals

| Portal | Login | Access |
|--------|-------|--------|
| **HQ Management** | `/hq/login` | Executive oversight, finance, commission approval, reports |
| **Admin** | `/admin/login` | Bookings, payments, packages, documents, support |
| **Provider** | `/provider/login` | Confirm / reject bookings, upload confirmation docs |
| **Agent** | `/agent/login` | Sell packages, own customers & bookings, wallet, gamification |
| **Customer** | `/login` | Browse, book, pay, download documents |

> HQ + Admin share one staff guard, separated by RBAC role (`super_admin` / `hq` / `admin`).
> Agent and Customer portals are **installable PWAs** (separate manifests, offline fallback,
> HTML responses are never cached — these portals show passports and commission on shared phones).

## 🧱 Tech Stack

- **Laravel 12** · PHP 8.2+ · full server-side **Blade** (no SPA)
- **MySQL** (local dev on port **3307**)
- **Bootstrap 5** + custom blue theme (`#1466ff → #0b3fd1 → #082aa0`), Vite
- **DomPDF** (invoices, vouchers, receipts, reports) · **maatwebsite/excel** (exports)
- Charts rendered as pure CSS bars — no JS charting dependency

## 🚀 Getting Started

```bash
composer setup          # install, .env, key:generate, migrate, npm install, npm run build
php artisan migrate:fresh --seed
composer dev            # serve + queue + logs + vite
```

Or step by step:

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_PORT=3307 and create the `blue_travel` database
php artisan migrate:fresh --seed
npm install && npm run dev
php artisan serve
```

`migrate:fresh --seed` rebuilds the entire demo showcase — agent sale → provider approve →
confirm → paid → MLM commission cascade → approved wallets, plus a customer profile, online
booking, deposit payment and a support ticket.

## 👤 Demo Logins

All demo accounts use the password `password`.

| Role | Email |
|------|-------|
| Super Admin | `super@bluetravel.com` |
| HQ | `hq@bluetravel.com` |
| Admin | `admin@bluetravel.com` |
| Provider | `provider@bluetravel.com` |
| Agent (root) | `agent@bluetravel.com` · downlines `nadia.agent@` / `imran.agent@` |
| Customer | `customer@bluetravel.com` |

> ⚠️ Demo data and demo passwords must **never** be seeded on production. See `DEPLOY.md`.

## 💳 Payment Gateways

Payments run through a driver layer — `config/payments.php` + `PaymentGatewayDriver` contract.

- `sandbox` — local simulator (auto-404s when `APP_ENV=production`)
- `billplz` — FPX via Billplz: create bill → redirect → signed webhook → **re-query the vendor API**
  before crediting. Short payments are flagged for manual review; repeat callbacks are idempotent.

Credentials can be managed at **`/manage/payment-gateway`** (HQ / super admin only) — stored
encrypted in the `settings` table and overriding `.env`, with a built-in *Test Connection*.
Adding another vendor (ToyyibPay, senangPay, Bayarcash) = one driver class + one config block.

## 🧪 Tests

```bash
composer test           # or: php artisan test
```

Covers the Billplz signature algorithm and the webhook trust boundary — forged signature,
unsigned payload, tampered amount, vendor-says-unpaid, short payment, replay, unknown reference.

## 🛡 Security Notes

- Booking documents and payment slips live on the **private** disk and are served only through
  ownership-checked controllers — never expose `FILESYSTEM_DISK=public`.
- Login and registration routes are rate limited (`throttle:5,1`).
- Commission payouts are default-deny gated pending the **KPDN Direct Sales licence**.
- Rotate `APP_KEY`, DB credentials and all demo passwords before going live.

## 📁 Project Layout

```
app/
  Http/Controllers/{Hq,Admin,Manage,Agent,Provider,Customer,Auth}
  Services/            BookingService, CommissionService, GamificationService,
                       ReportService, WalletService, Gateways/…
  Models/              32 models (bookings, packages, commissions, wallets, …)
database/
  migrations/          25 migrations
  seeders/             Catalog, Commission, Gamification, MarketingSupport,
                       Showcase, CustomerPortal
resources/views/
  layouts/admin        Bootstrap 5 staff back-office
  layouts/agent        mobile phone-shell agent portal
public/
  sw.js, manifest-agent.webmanifest, manifest-customer.webmanifest
```

## 📚 Further Docs

- [`Planning.md`](Planning.md) — full product spec, data model, and the 10 build phases
- [`DEPLOY.md`](DEPLOY.md) — server requirements, first deploy, env, queue, security checklist,
  gateway integration, smoke test and update procedure

---

<p align="center"><sub>Built by <b>CodexLure Technology</b> · Private &amp; Confidential</sub></p>
