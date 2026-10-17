# Kingdom Cloud — sell church websites as a service

Kingdom Cloud turns the Kingdom Church theme and Kingdom Church Suite plugin into a SaaS you can sell to many churches. Each church gets:

- its own website on `theirchurch.yourplatform.com`, with their own domain available later
- its own partners, payments and Church Admin dashboard
- its own subscription to your platform

It runs on **WordPress Multisite** (subdomain install). Every church is a separate site, so their data never mixes.

## What it does

| | |
|---|---|
| **Landing & pricing page** | Your platform's home page: features, how it works, plans with an International (USD) / Local (e.g. NGN) and Monthly / Yearly switch, FAQ. |
| **Self-service sign-up** (`/signup/`) | The church enters its name and picks a web address (checked live; reserved and taken names refused), website colours, account and plan. Its site is created in about two seconds, with the theme, Church Suite, starter pages, menus and Church Admin. The owner lands signed in on their new site. A free trial starts with no card needed. |
| **Subscription screen** (Church Admin → Subscription) | Plan, trial or renewal date, partner usage, plan picker in USD or local currency (monthly or yearly), checkout, cancel/resume, billing history. A banner on every Church Admin screen shows the trial countdown or overdue notice. |
| **Billing** | Paystack and Stripe hosted checkout, verified server-side. Signed webhooks (Paystack HMAC-SHA512, Stripe signatures with timestamps) are handled. Amounts and currency are checked against the invoice, and each invoice can only be applied once. Bank transfer: list up to 3 bank accounts in Settings; the church sees the account for its currency and the invoice reference, presses “I have made this transfer” (optional receipt, kept private), and you are emailed. The plan starts when you confirm it in Payments; rejecting it emails the church your reason. Test mode adds a simulated checkout. |
| **Lifecycle** (twice a day, or "Run checks now") | Trial ends → payment due (site stays up for the grace period) → suspended. Reminder emails go out a few days before the trial ends or the plan renews. "Cancel at period end" leads to cancelled. Paying brings the site back instantly. |
| **Suspension** | Visitors see a polite "taking a short break" page (HTTP 503) and the church's public REST API is closed. Church staff can still log in but only reach the Subscription screen. You (super admin) can always see everything. |
| **Plan limits** | Maximum partners (new sign-ups are blocked politely and the owner is emailed), custom domain, and white-label (removes "Powered by …" from the footer). |
| **Custom domains** (Church Admin → Custom domain, Growth and up) | The church adds `theirchurch.org` and sees the exact DNS records to add. "Check now" verifies the domain (the TXT record proves the church owns it, the address record shows it reaches the platform) and makes it the site's main address. An unverified claim never blocks the real owner and is dropped after 7 days. The old subdomain and `www.` redirect to it, and logins work on the new domain. Removing the domain or downgrading the plan moves the site back to its subdomain, and pending domains are rechecked automatically. |
| **HTTPS for custom domains** | `GET /wp-json/ks/v1/tls?domain=` is the "ask" endpoint for Caddy on-demand TLS. Certificates are only issued for verified church domains and church subdomains. |
| **Platform dashboard** (`/platform/`, super admins) | Overview: churches, MRR per currency, revenue in the last 30 days, churches needing attention. Churches: search and filter, open any church's admin (one-time login), change plan, extend days, suspend/reactivate, delete. Add a church manually (for offline sales). Payments: bank transfers waiting for you (receipt, Confirm received, Reject with a reason), and all invoices. Domains: check DNS, remove. Plans & pricing editor. Settings: brand, domains, trial and grace days, gateways, test mode. |
| **Emails** | Welcome, trial ending, trial ended, payment due, paused, ended, payment receipt, partner limit, domain connected. |
| **REST** (`ks/v1`) | `GET plans`, `GET subdomain?name=`, `GET billing/return`, `POST webhooks/paystack`, `POST webhooks/stripe`, `GET tls?domain=`. |

Churches still receive their own **tithes, offerings and partnership payments** directly, through their own Paystack, Flutterwave, Stripe or PayPal accounts in Church Suite settings. The platform only bills the church for its subscription.

## Going live

### The easy way (shared hosting such as Hostinger, cPanel, Plesk)

1. **Fresh WordPress** on your platform domain (e.g. `yourplatform.com`), installed at the root of the domain. Turn on SSL for the domain.
2. **Upload the three zips** from [`dist/`](../../dist): *Plugins → Add New → Upload Plugin* for `kingdom-church-suite.zip` and `kingdom-saas.zip`, and *Appearance → Themes → Add New → Upload Theme* for `kingdom-theme.zip`. Activate Kingdom Cloud.
3. **One click**: a yellow "Kingdom Cloud is almost ready" message appears. Click **Set up my platform** (or go to *Tools → Kingdom Cloud setup*), choose the church address style and click **Set up my platform now**.
   - **Subfolders** — `yourplatform.com/gracechapel` (default, **use this on shared hosting** such as Hostinger, cPanel or Plesk: works everywhere, no wildcard DNS, your SSL already covers it).
   - **Subdomains** — `gracechapel.yourplatform.com` (needs a server that sends every `*.yourplatform.com` to WordPress — a VPS or hosting with wildcard subdomains — plus a `*` DNS record and SSL for `*.yourplatform.com`).
   Either way churches can connect their own domain later. While no church has signed up you can switch style in *Network Admin → Kingdom Cloud*. This backs up and writes `wp-config.php` and `.htaccess`, switches on Multisite with subdomains, installs `sunrise.php`, network-activates both plugins and enables the theme. No code to copy. (If a file cannot be written, the page shows the exact text to paste.)
4. **Log in again.** You land on *Network Admin → Kingdom Cloud*, a setup check that lists anything still missing with the fix next to it.
5. **Subdomain style only** (skip for subfolders): a DNS **A record** for `*` (i.e. `*.yourplatform.com`) pointing to your server IP, a wildcard subdomain / server alias sending every subdomain to the WordPress folder, and SSL covering the subdomains (e.g. Cloudflare). The setup check tells you which part is missing.
6. **Purge caches** (LiteSpeed Cache → Purge All, and your host's cache) and open your domain: you should see the sales landing page.
7. **Platform settings**: *Open platform dashboard → Settings* (`/platform/?tab=settings`): platform name, brand colour, support email, Paystack/Stripe keys or bank accounts; add Cloudflare Turnstile keys; turn **test mode off**.
8. **Webhooks** in Paystack and Stripe (URLs on the Settings tab), and a **real cron job** (see [`deploy/wp-config-snippet.php`](deploy/wp-config-snippet.php)).
9. Edit **Plans & pricing**, then sign up a test church from your landing page.

After setup, plugins and themes are uploaded in **My Sites → Network Admin → Plugins / Themes** (the normal dashboard no longer has "Add New" — that is how WordPress Multisite works).

### On your own server (VPS)

1. **Server**: PHP 8.1+ with PHP-FPM, MySQL 8 or MariaDB 10.6+, and Caddy (recommended) or nginx. Use [`deploy/Caddyfile`](deploy/Caddyfile) or [`deploy/nginx.conf`](deploy/nginx.conf).
2. **DNS**: point `yourplatform.com` **and** `*.yourplatform.com` at the server.
3. Then follow steps 1–4 and 6–9 above (the one-click setup works on any server; on nginx it skips `.htaccess`). Prefer the command line? `wp core multisite-convert --subdomains`, then copy `deploy/sunrise.php` to `wp-content/` and add the lines from `deploy/wp-config-snippet.php`.

Behind Cloudflare or a load balancer, add `define( 'KCS_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );` to wp-config.php so rate limits see each visitor's own address.

**Billing rules**: paying ahead on the same plan adds a period after the current one; paying for a different plan starts it now and credits unused paid time; paying during the free trial keeps the remaining trial days; a church suspended by the platform owner stays suspended after paying until it is reactivated.

## Tests

See [`tests/README.md`](tests/README.md). The end-to-end suite covers sign-up, billing, custom domains, limits, the suspension lifecycle, platform actions, gateways and webhooks, and the security checks.
