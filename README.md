# Square Customer Analyser

A CakePHP dashboard for **Willow and Sage Wellness** that syncs customer, booking and payment data from Square and presents it through a single management portal with its own independent login.

## Installation

1. Install [PHP 8.2+](https://www.php.net/) and [Composer](https://getcomposer.org/).
2. Clone the repository and run `composer install`.
3. Configure the database connection — either edit `config/app_local.php` (set `host`, `username`, `password`, `database`) or set `DATABASE_URL` in `config/.env`.
4. Create `config/.env` by copying `config/.env.example` and fill in the Square credentials (see [Environment variables](#environment-variables)).
5. Run the migrations: `bin/cake migrations migrate`
6. Seed the database: `bin/cake seeds run`

> **After every `git pull`**, if `config/Migrations/` contains new files, run `bin/cake migrations migrate` again before using the app.

## Environment variables

Copy `config/.env.example` to `config/.env` and fill in:

| Variable | Purpose |
|----------|---------|
| `SQUARE_CLIENT_ID` / `SQUARE_CLIENT_SECRET` | Your Square application's credentials (sandbox or production). |
| `SQUARE_AUTHORIZED_MERCHANT_ID` | The merchant id the app is allowed to connect to. |
| `SQUARE_REDIRECT_URI` | Must match the redirect URI registered on the Square app, including the port. |
| `TOKEN_ENCRYPTION_KEY` | Secret used to encrypt Square tokens in the database. |
| `SECURITY_SALT` | Must match the salt used to encrypt any existing tokens (otherwise they cannot be decrypted). |
| `SQUARE_SCOPE` | The OAuth scopes requested during connection. |

`SQUARE_SCOPE` must include one scope per synced resource:

| Resource | Scope |
|----------|-------|
| Customers | `CUSTOMERS_READ` |
| Bookings / appointments | `APPOINTMENTS_READ` |
| Payments | `PAYMENTS_READ` |
| Merchant profile | `MERCHANT_PROFILE_READ` |

Default: `CUSTOMERS_READ,MERCHANT_PROFILE_READ,APPOINTMENTS_READ,PAYMENTS_READ`

> **Scopes only take effect when connecting.** If you change `SQUARE_SCOPE`, disconnect and reconnect Square for the new scopes to be authorised.

## Running the app

For development, use the built-in server:

```bash
bin/cake server
```

This serves the app at `http://localhost:8765`. You can also serve it through Apache (e.g. on port 8080) — but keep `SQUARE_REDIRECT_URI` consistent with the port you actually use.

## Usage

The default credentials are `wendy@example.com` / `admin123`.

Log in and go to the dashboard, then connect Square and run a sync from **Settings**.

## Connecting with Square in Sandbox

1. Go to <https://app.squareup.com/launchpad>
2. Click View Dashboard for our account.
3. Go to <https://developer.squareup.com/apps>
4. Click Sandbox test accounts.
5. Click Square Dashboard for the `Auth` user.

### Notes

- **Bookings require Appointments onboarding.** The sandbox merchant must have Square Appointments enabled, otherwise syncing bookings returns `Merchant not onboarded to Appointments`. Enable it from the sandbox merchant's Square dashboard (sidebar → "Add more" → Appointments, or under Settings).
- **Don't use "Replace" on the access token** in the Square developer console. Replacing the token revokes the access *and* refresh token stored in the database, and the app cannot recover on its own. If you replace it, reconnect Square to save the new tokens.
- **The redirect URI must match** between `SQUARE_REDIRECT_URI` and the Square app's registered redirect URI (including the port).

## Scheduled tasks

The Square access token must be refreshed periodically (Square recommends every 7 days or less), independent of whether anyone is using the app. Wire the following into the server's crontab:

```
0 3 * * * cd /path/to/app && bin/cake refresh_square_token >> logs/refresh_square_token.log 2>&1
```

> `refresh_square_token` is a safety net — normal usage (syncs, admins viewing Settings) already refreshes the token on demand via `SquareAuthService`. This only covers periods where the app sits unused, as recommended by Square's docs.

## Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `Table ... doesn't exist` | Pending migrations — run `bin/cake migrations migrate`. |
| `INSUFFICIENT_SCOPES` | A scope for a synced resource is missing — add it to `SQUARE_SCOPE` and reconnect. |
| `Merchant not onboarded to Appointments` | Enable Square Appointments on the sandbox merchant. |
| `Invalid token` / `Invalid refresh token` | The token was revoked or replaced — reconnect Square. |
| `Required option not passed: "access_token"` | Square rejected a token refresh (usually the refresh token is invalid) — reconnect Square. |
