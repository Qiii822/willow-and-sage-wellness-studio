# Willow and Sage Wellness Studio

A CakePHP 5.4 web application for **Willow and Sage Wellness Studio** — a public website with online booking for customers, plus a private studio portal that mirrors the studio's Square data (customers, bookings, payments, treatment menu and offers) and lets staff edit the site's content.

Square is the system of record. Customers and appointments can be created in either place, and every sync pulls Square's copy back into this app.

## Contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Installation](#installation)
- [Environment variables](#environment-variables)
- [Running the app](#running-the-app)
- [Signing in](#signing-in)
- [Square connection and sync](#square-connection-and-sync)
- [Scheduled tasks](#scheduled-tasks)
- [Tests and code quality](#tests-and-code-quality)
- [Troubleshooting](#troubleshooting)

## Features

### Public site

| Page | What it does |
| ---- | ------------ |
| `/` | Landing page assembled from editable sections: hero, current offers, studio intro, service cards, About Wendy, comfort and hygiene, location and hours, and the treatment menu. Staff choose which sections show, their order, and a layout variant for each. |
| `/services` | Read-only treatment menu synced from Square — names, descriptions, prices, durations and categories. Each treatment links into the booking flow. |
| `/book` | Four-step booking flow: choose treatments (with a cart), choose a therapist, choose a time, then enter contact details and an optional discount code. The appointment is written to Square and copied into the local bookings table immediately. |
| `/my-bookings` | Customer self-service without an account. The customer proves the mobile number they booked with using a six-digit SMS code, then sees upcoming and past appointments, can cancel, move a booking made on this site, or book the same treatment again. |
| Gift card | Links out to the studio's own Square gift-card page. Buying a gift card needs an order and a card payment, neither of which this site takes. |

The site header shows **Offers / Book / My bookings / Gift card**. There is deliberately no "Log in" link on the customer-facing pages; staff reach the sign-in page at `/login` directly.

### Studio portal

| Area | What it does |
| ---- | ------------ |
| Dashboard (`/dashboard`) | Configurable widget dashboard: total customers, new customers, total bookings, total income, customer growth, booking trends, booking status, recent customers and the customer rebooking list. Widgets can be added, removed, resized and reordered; the layout is saved in the browser's local storage. |
| Bookings | Filterable booking list, a week calendar, and a booking detail page. Staff can record a cash or card payment against a booking, refund/undo a payment, mark a booking unpaid, and cancel a booking. |
| Customers | Square customers in one list, filtered and searched. Records that share a mobile number are shown as one person, because Square keys customers on email and a repeat booker with a second address otherwise becomes two rows. |
| Payments | All synced payments, filterable by status and date, including bookings with no payment at all ("Unpaid"). |
| Admins | Staff accounts. Roles are `admin` and `superadmin`; public registration can only create the `user` role. |
| Website | Three editors under one entry: the words and photos on the home page, the section layout, and the service and offer lists. |
| Settings | Square connection status, connect/disconnect, manual sync, clear-data, the last sync time, and the wording of the rebooking reminder email. |

Account screens are available to any signed-in user: profile, account settings, password change. Contact-detail changes require a verification code and can be undone from a warning email.

### Square integration

- **OAuth2 with PKCE.** The app keeps one shared Square connection, stored as a single active `Identity` row with encrypted tokens. Every admin API call reuses it; there is not one connection per user.
- **Token refresh.** Access tokens are refreshed on retrieval once they are seven days old, and a cron command acts as a safety net for periods when nobody uses the app.
- **Full reimports.** Customers, bookings, payments and the service menu are replaced wholesale from Square on every run. Bookings are fetched from six months back to twelve months ahead; payments are fetched from 2010.
- **Offers are merged, not replaced.** A Square discount is only a name and a percentage, so local copy, images and before/after prices survive a sync. An offer whose discount is deleted in Square is removed.
- **Manual sync.** **Settings → Sync** runs every resource at once.

### Security notes

- CakePHP Authentication sessions, CSRF protection and host-header validation are enabled.
- Roles gate the sensitive screens: Payments, Customers, Admins, Website and Settings require `admin`/`superadmin`. The dashboard and bookings list are visible to any signed-in account.
- The login page and booking-details step render a Cloudflare Turnstile widget. **Server-side verification of the Turnstile token is not wired up yet** — the widget is a deterrent, not a control.
- Image uploads are inspected rather than trusted: the reported MIME type and original filename are ignored, only real JPEG/PNG/WebP files are kept, the stored name is generated, and the size is capped at 5 MB.

## Tech stack

- PHP 8.2+ (developed and tested on 8.4 and 8.5), CakePHP 5.4, MySQL
- `cakephp/authentication`, `cakephp/migrations`
- `square/square` SDK and `league/oauth2-client` for Square
- Twilio REST API for SMS (optional; the default driver logs instead)
- Cloudflare Turnstile on the login and booking forms
- Vanilla CSS/JavaScript in `webroot/` (no front-end build step)
- PHPUnit, PHPCS (CakePHP standard), PHPStan configuration at level 8

## Installation

1. Install [PHP 8.4](https://www.php.net/), [Composer](https://getcomposer.org/) and MySQL. The code requires PHP 8.2 or newer; it is developed and tested on 8.4 and 8.5.
2. Clone the repository and run `composer install`.
3. Configure the database connection — either edit `config/app_local.php` (set `host`, `username`, `password`, `database`) or set `DATABASE_URL` in `config/.env`.
4. Copy `config/.env.example` to `config/.env` and fill in the Square credentials (see [Environment variables](#environment-variables)).
5. Run the migrations: `bin/cake migrations migrate`
6. Seed the database: `bin/cake seeds run`
7. Start the app: `bin/cake server` (see [Running the app](#running-the-app)).

> **After every `git pull`**, if `config/Migrations/` contains new files, run `bin/cake migrations migrate` again before using the app.

## Environment variables

Copy `config/.env.example` to `config/.env` and fill in:

### Application and database

| Variable | Purpose |
| -------- | ------- |
| `APP_NAME` | Application name. Must be set for the `.env` loader to treat the environment as configured. |
| `DEBUG` | `true` shows errors and deprecation notices in the page. Also the fallback for `SQUARE_ENVIRONMENT` when that is unset. |
| `APP_FULL_BASE_URL` | The site's public URL. Required in production for host-header validation and password-reset links. |
| `SECURITY_SALT` | CakePHP security salt. Must match the salt used to encrypt any existing Square tokens, otherwise they cannot be decrypted. |
| `DATABASE_URL` | Database connection as a URL, e.g. `mysql://user:secret@localhost/app?encoding=utf8`. Alternative to editing `config/app_local.php`. |
| `APP_DEFAULT_TIMEZONE` | CakePHP's configured timezone, but `config/bootstrap.php` pins the process to `Australia/Adelaide` — the studio's own clock. Changing it means shifting the stored datetimes, as migration `20261022000000_ShiftDatetimesToAdelaide` did. |

### Square

| Variable | Purpose |
| -------- | ------- |
| `SQUARE_CLIENT_ID` / `SQUARE_CLIENT_SECRET` | The Square application's credentials. |
| `SQUARE_AUTHORIZED_MERCHANT_ID` | The merchant id the app is allowed to connect to. |
| `SQUARE_REDIRECT_URI` | Must match the redirect URI registered on the Square app, including the port. |
| `TOKEN_ENCRYPTION_KEY` | Secret used to encrypt Square tokens in the database. |
| `SQUARE_SCOPE` | The OAuth scopes requested during connection. See below. |
| `SQUARE_ENVIRONMENT` | `sandbox` or `production`. Left unset it follows `DEBUG`. Must match the client id/secret pair, and Square must be reconnected after a change. |
| `SQUARE_LOCATION_ID` | The studio's Square location, for an account with more than one. Left unset, the first location Square returns is used. |

`SQUARE_SCOPE` must include one scope per synced resource:

| Resource | Scope |
| -------- | ----- |
| Customers | `CUSTOMERS_READ`, `CUSTOMERS_WRITE` |
| Bookings | `APPOINTMENTS_READ`, `APPOINTMENTS_WRITE` |
| Payments | `PAYMENTS_READ`, `PAYMENTS_WRITE` |
| Merchant profile | `MERCHANT_PROFILE_READ` |
| Menu (items) | `ITEMS_READ` |
| Business settings | `APPOINTMENTS_BUSINESS_SETTINGS_READ` |

The write scopes are needed for online booking and rebooking reminders. `PAYMENTS_WRITE` records a cash or card payment against a booking from the booking page. Default:

`CUSTOMERS_READ,MERCHANT_PROFILE_READ,APPOINTMENTS_READ,PAYMENTS_READ,ITEMS_READ,APPOINTMENTS_WRITE,CUSTOMERS_WRITE,APPOINTMENTS_BUSINESS_SETTINGS_READ,PAYMENTS_WRITE`

> **Do not add `APPOINTMENTS_ALL_WRITE`.** It makes Square treat every Bookings write as seller-level, which requires an Appointments Plus or Premium subscription. Without one, every booking, change and cancellation returns `403 Merchant subscription does not support write operations`, while reads keep working — the site looks fine until someone books.

> **Scopes only take effect when connecting.** If you change `SQUARE_SCOPE`, disconnect and reconnect Square for the new scopes to be authorised.

### CAPTCHA

| Variable | Purpose |
| -------- | ------- |
| `TURNSTILE_SITE_KEY` | Cloudflare Turnstile site key for the widget on the login and booking-details pages. |

### SMS and Twilio

| Variable | Purpose |
| -------- | ------- |
| `SMS_TRANSPORT` | How verification codes are sent: `log` (default; writes to `logs/debug.log`), `none` (always fails, for testing the error path), or `twilio` (really sends). |
| `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN` | Twilio credentials, for `SMS_TRANSPORT=twilio`. |
| `TWILIO_FROM` | The Twilio number texts come from (`+61...`), or a Messaging Service SID (`MG...`). |
| `TWILIO_VERIFY_SERVICE_SID` | Optional Twilio Verify service (`VA...`). When set, Twilio Verify generates, sends and checks the My bookings sign-in code instead of this site. It also works on a Twilio trial account. |

With `SMS_TRANSPORT=log` and `DEBUG` on, the generated code is also shown on the page, so the whole journey can be walked without a phone.

### Email

| Variable | Purpose |
| -------- | ------- |
| `EMAIL_TRANSPORT_DEFAULT_URL` | The account password resets and verification codes are sent from, as a URL. E.g. `smtp://user:app-password@smtp.gmail.com:587?tls=true`. Set by developers — there is no email-account setup in the admin UI. |
| `EMAIL_FROM` / `EMAIL_FROM_NAME` | The sender address and name those emails show. |
| `REBOOKING_SMTP_USERNAME` / `REBOOKING_SMTP_PASSWORD` | Gmail SMTP credentials used to send rebooking reminder emails. |
| `REBOOKING_SEND_REAL` | Set to `true` to email the real customer; default `false` sends to `REBOOKING_TEST_EMAIL`. |
| `REBOOKING_TEST_EMAIL` | Test recipient for rebooking reminders while `REBOOKING_SEND_REAL` is `false`. |

## Running the app

For development, use the built-in server:

```bash
bin/cake server
```

This serves the app at `http://localhost:8765`. You can also serve it through Apache (e.g. on port 8080) — but keep `SQUARE_REDIRECT_URI` consistent with the port you actually use. Connecting Square only works on the port that `SQUARE_REDIRECT_URI` names, so a mismatch will fail the OAuth callback.

Two environment notes:

- **Use one PHP version across environments.** The built-in server and Apache can resolve to different PHP binaries (`php@8.4` for one, 8.5 for the other). Because deprecation notices are rendered into the page while `DEBUG` is on, the same code can look different on each. Pin a single version for development, and make sure the deployment target matches it.
- **The studio's timezone is Adelaide.** `config/bootstrap.php` calls `date_default_timezone_set('Australia/Adelaide')`, and datetimes are stored as wall-clock time in that zone. Running the app in another timezone shifts every appointment shown.

## Signing in

The seeded account is `wendy@example.com` with the password `admin123`.

> **Change this before the app is used with real data.** The seeded account is a superadmin, and its password is recorded in this file and in `config/Seeds/UsersSeed.php` — anyone with the repository can sign in. Change the password, or remove the account, before connecting a live Square account.

After signing in, staff land on `/dashboard`. Customers who register at `/register` land on the public home page instead.

## Square connection and sync

1. Sign in as a superadmin and open **Settings**.
2. Use **Connect Square** to authorise the app. Only the merchant named by `SQUARE_AUTHORIZED_MERCHANT_ID` is accepted.
3. Press **Sync** to pull customers, bookings, payments, the service menu and offers.
4. **Disconnect** revokes only this site's token. **Clear data** removes the locally synced rows without touching Square.

### Connecting with Square in Sandbox

1. Go to [https://app.squareup.com/launchpad](https://app.squareup.com/launchpad)
2. Click View Dashboard for the account.
3. Go to [https://developer.squareup.com/apps](https://developer.squareup.com/apps)
4. Click **Sandbox test accounts**.
5. Click **Square Dashboard** for the `Auth` user.

Notes:

- **Bookings require Appointments onboarding.** The sandbox merchant must have Square Appointments enabled, otherwise syncing bookings returns `Merchant not onboarded to Appointments`. Enable it from the sandbox merchant's Square dashboard (sidebar → "Add more" → Appointments, or under Settings).
- **Don't use "Replace" on the access token** in the Square developer console. Replacing the token revokes the access *and* refresh token stored in the database, and the app cannot recover on its own. If you replace it, reconnect Square to save the new tokens.
- **The redirect URI must match** between `SQUARE_REDIRECT_URI` and the Square app's registered redirect URI, including the port.
- **`SQUARE_ENVIRONMENT` must match the credentials.** Sandbox credentials pointed at the production sign-in page answer `Unable to find client by that client_id` without explaining why.

## Scheduled tasks

The Square access token must be refreshed periodically (Square recommends every 7 days or less), independent of whether anyone is using the app. Wire the following into the server's crontab:

```
0 3 * * * cd /path/to/app && bin/cake refresh_square_token >> logs/refresh_square_token.log 2>&1
```

> `refresh_square_token` is a safety net — normal usage (syncs, admins viewing Settings) already refreshes the token on demand via `SquareAuthService`. This only covers periods where the app sits unused.

## Tests and code quality

```bash
composer test       # PHPUnit
composer cs-check   # PHPCS, CakePHP standard
composer cs-fix     # auto-fix style
```

`phpstan.neon` is configured at level 8, but PHPStan is not installed by default; add `phpstan/phpstan` as a dev dependency to run it.

## Troubleshooting

| Symptom | Cause / fix |
| ------- | ----------- |
| `Table ... doesn't exist` | Pending migrations — run `bin/cake migrations migrate`. |
| `INSUFFICIENT_SCOPES` | A scope for a synced resource is missing — add it to `SQUARE_SCOPE` and reconnect. |
| `Merchant not onboarded to Appointments` | Enable Square Appointments on the sandbox merchant. |
| `Invalid token` / `Invalid refresh token` | The token was revoked or replaced — reconnect Square. |
| `Required option not passed: "access_token"` | Square rejected a token refresh (usually the refresh token is invalid) — reconnect Square. |
| `403 Merchant subscription does not support write operations` | `APPOINTMENTS_ALL_WRITE` is in `SQUARE_SCOPE` — remove it and reconnect, or move the merchant to an Appointments Plus/Premium plan. |
| `Unable to find client by that client_id` | `SQUARE_ENVIRONMENT` does not match the credentials — set it to `sandbox` or `production`. |
| Booking a treatment offers no times | The treatment has no Square variation, or Square cannot be reached. Check the service's Square catalogue entry. |
| My bookings says no code was sent | `SMS_TRANSPORT=log` writes the code to `logs/debug.log`; with `DEBUG` on it is also shown on the page. Use `twilio` or Twilio Verify for a real text. |
| Dashboard widgets are missing, or arranged differently on another URL | The layout is saved per browser and per web address, not per account — click **Reset layout** on the dashboard toolbar. |
| Deprecation notices printed at the top of pages | The server is running a newer PHP than the code was tested against — match the PHP version between development and deployment. These notices only display while `DEBUG` is on. |
