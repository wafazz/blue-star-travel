# Blue Travel (TAMS) — Deployment Guide

> **Client:** Blue Star Travel And Tours Sdn Bhd · **Vendor:** CodexLure Technology
> Laravel 12 · PHP 8.2+ · MySQL 8 · Bootstrap 5 (Vite)

---

## 1. Requirements

| Component | Version |
|-----------|---------|
| PHP | 8.2+ (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`) |
| MySQL | 8.0+ (dev uses port **3307**, db `blue_travel`) |
| Composer | 2.x |
| Node | 20+ (build only — not needed at runtime) |
| Web server | Nginx or Apache, docroot must be **`public/`** |

`maatwebsite/excel` needs `gd` + `zip`; `barryvdh/laravel-dompdf` needs `gd`.

---

## 2. First deploy

```bash
git clone <repo> blue-travel && cd blue-travel

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # compiles resources/scss → public/build

cp .env.example .env
php artisan key:generate
# → edit .env (see §3) before continuing

php artisan migrate --force
php artisan db:seed --force      # ONLY on a fresh demo/staging box — see §6
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Writable by the web user: `storage/`, `bootstrap/cache/`.

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## 3. Environment

```dotenv
APP_NAME="Blue Travel"
APP_ENV=production          # MUST be production — gates the sandbox payment screen
APP_DEBUG=false             # MUST be false — stack traces leak DB credentials
APP_TIMEZONE=Asia/Kuala_Lumpur
APP_URL=https://your-domain.com   # used by the notification redirect allow-list

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306                # 3307 in local dev
DB_DATABASE=blue_travel
DB_USERNAME=<user>
DB_PASSWORD=<strong-password>

SESSION_DRIVER=database
SESSION_ENCRYPT=true        # recommended in production
SESSION_SECURE_COOKIE=true  # HTTPS only
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp            # currently `log` — notifications are stubs until a vendor is set
```

**Never commit `.env`.** It is already in `.gitignore`.

---

## 4. Queue & scheduler

Notifications and PDF generation run inline today, but the queue driver is `database`, so
run a worker if you move any of it to jobs:

```bash
php artisan queue:work --tries=3 --daemon     # supervise with systemd/supervisor
```

No scheduled commands are registered yet — `php artisan schedule:run` is not required.

---

## 5. Security checklist (verified 2026-07-19)

- [x] `APP_DEBUG=false` and `APP_ENV=production` in the production `.env`
- [x] Login (all 4 portals) + register throttled to **5 requests/min**; payment webhook 60/min
- [x] Payment webhook authenticated by vendor HMAC signature; booking credited only on a server-to-server confirmation from the vendor
- [x] Booking documents (invoice/voucher/receipt) and **payment slips** live on the **private**
      disk and are served only through `BookingDocumentController`, which checks ownership.
      They must **never** be moved to the `public` disk — they contain passport numbers,
      pax names and banking data.
- [x] Marketing uploads are mime-restricted (no scripts into the web-served docroot)
- [x] Every portal scopes its queries to the caller; a provider account with no linked
      provider row is denied rather than matching `provider_id IS NULL`
- [x] CSRF enabled everywhere except `pay/webhook/*` (server-to-server, authenticated by vendor HMAC instead — documented in `bootstrap/app.php`)
- [x] Passwords bcrypt (`BCRYPT_ROUNDS=12`), session regenerated on login
- [x] Notification redirects restricted to `APP_URL`

**Before going live, rotate:** `APP_KEY`, all DB credentials, and every demo account password
(see §6) — the seeded logins all use `password`.

---

## 6. Demo data ⚠️

`php artisan db:seed` creates demo accounts **all with the password `password`**, including
`super@bluetravel.com`. Seed a real production database **only** with:

```bash
php artisan migrate --force        # no --seed
```

…then create the first super admin manually via `php artisan tinker`.

`php artisan migrate:fresh --seed` **drops every table** — never run it against production.

---

## 7. Payment gateway — Billplz

The app ships a pluggable gateway layer. `PAYMENT_GATEWAY` selects the driver; adding
another vendor later means one new class + one config block, nothing else changes.

| Driver | Use |
|--------|-----|
| `sandbox` | Local simulator. Never credits a booking on its own (payments land pending for staff verification) and its screens 404 in production or whenever a real driver is active. |
| `billplz` | **Live FPX / online banking.** |

### Billplz setup

1. Create a **Collection** in the Billplz dashboard (sandbox: <https://www.billplz-sandbox.com>).
2. Dashboard → **Settings → Keys & Integration**: copy the **API Secret Key** and the **X Signature Key**.
3. Fill in `.env`:

```dotenv
PAYMENT_GATEWAY=billplz
BILLPLZ_KEY=<api secret key>
BILLPLZ_X_SIGNATURE=<x signature key>
BILLPLZ_COLLECTION_ID=<collection id>
BILLPLZ_SANDBOX=true      # false for the live portal
```

4. No callback URL needs configuring in the dashboard — the app sends `callback_url`
   and `redirect_url` with every bill:
   - webhook: `POST https://your-domain.com/pay/webhook/billplz`
   - return:  `GET  https://your-domain.com/pay/return/billplz`
5. `php artisan config:cache` after changing `.env`.

### How a payment is trusted

- The **webhook** is CSRF-exempt (server-to-server, no browser). Its only authentication
  is the Billplz **X-Signature** (HMAC-SHA256). An unsigned or tampered payload is
  rejected with 403 and nothing is credited.
- After the signature passes, the app **re-queries the Billplz API** for that bill and
  settles on the vendor's answer — the posted amount is never trusted.
- A booking is credited (and commission fired) only when the vendor confirms `paid`
  **and** the amount covers the payment. A short payment is logged for manual review.
- The redirect back from Billplz is also signature-checked, but only triggers the same
  re-query; the webhook stays the source of truth. Repeat callbacks are idempotent.

Covered by `tests/Unit/BillplzSignatureTest.php` and `tests/Feature/BillplzWebhookTest.php`
(forged signature, unsigned payload, vendor-says-unpaid, short payment, replay, unknown ref).

### Going live

Set `BILLPLZ_SANDBOX=false` and swap in live keys. Run one real low-value transaction
end to end and confirm the booking shows **Paid** with the commission cascade recorded.

### Adding another gateway later

Implement `App\Services\Gateways\PaymentGatewayDriver` (`start`, `verifySignature`,
`fetchStatus`, `referenceFrom`), register it in `config/payments.php`, point
`PAYMENT_GATEWAY` at it. The webhook/return routes are already driver-generic
(`/pay/webhook/{driver}`).

---

## 8. Compliance ⚠️

The commission engine is **MLM-shaped** (dynamic-depth cascade). A **KPDN Direct Sales
licence** is required before paying real commission in Malaysia. The safeguard is already in
place: commissions land `pending` and require explicit HQ approval before crediting a wallet.
Do not remove that gate without legal sign-off.

---

## 9. Post-deploy smoke test

```bash
php artisan about                       # config sanity
curl -I https://your-domain.com/up      # health endpoint → 200
```

Then log in to each portal and confirm the dashboard loads:
`/admin/login` (staff) · `/agent/login` · `/provider/login` · `/login` (customer) · `/packages` (public).

---

## 10. Updating an existing deployment

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
