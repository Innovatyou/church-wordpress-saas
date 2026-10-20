# Ministry OS — WordPress Theme + Church Suite Plugin

A complete church website kit:

| Package | Path | What it does |
|---|---|---|
| **Ministry OS** theme | `themes/kingdom/` | Modern, responsive design with **25 page templates**, 20 reusable sections, dark mode, Customizer options and a one-click starter site |
| **Ministry OS Church Suite** plugin | `plugins/kingdom-church-suite/` | Partnerships, partner login and dashboard, admin partner/payment management, payment gateways, REST API, email notifications, newsletter, YouTube live, sermons, events, prayer and advanced SEO |
| **Ministry OS Cloud** (SaaS) plugin | `plugins/kingdom-saas/` | Sell the theme + plugin to many churches on WordPress Multisite: sign-up with free trial, a site per church, custom domains, subscription billing (Paystack, Flutterwave, Stripe, bank transfer), plan limits, automatic suspension and a platform dashboard. See [its README](plugins/kingdom-saas/README.md). |

> **Theme name clash:** WordPress.org has an unrelated free theme called "Kingdom" (by BestWebLayout) that uses the same `kingdom` folder. Older copies of this theme could be "updated" to it by WordPress, which makes church sites look broken. From theme 1.2.0 and plugins 1.2.1 / 1.5.3 an `Update URI` header stops this. If Network Admin → Ministry OS Cloud says *"The wrong theme is installed in the kingdom folder"*, delete that theme and upload `dist/kingdom-theme.zip` again.

The theme works on its own. The plugin holds all data and business logic, so your content and partners survive a theme change.

**Two ways to use it:** install the theme and Church Suite on one church's WordPress site (below), or run **Ministry OS Cloud** on a Multisite network and sell church websites as a service, where each church signs up, gets its own site and pays you monthly or yearly.

**Selling to many churches (Ministry OS Cloud)?** Install a fresh WordPress, upload all three zips, activate Ministry OS Cloud and click **Set up my platform** — it switches on Multisite for you (no editing wp-config.php or .htaccess). Full steps: [plugins/kingdom-saas/README.md](plugins/kingdom-saas/README.md#going-live).

---

## Installation

1. Download the ready-to-install zips (no need to unzip them):
   - Theme: [kingdom-theme.zip](https://github.com/Innovatyou/church-wordpress-saas/raw/main/dist/kingdom-theme.zip) → **Appearance → Themes → Add New → Upload Theme** → Activate
   - Plugin: [kingdom-church-suite.zip](https://github.com/Innovatyou/church-wordpress-saas/raw/main/dist/kingdom-church-suite.zip) → **Plugins → Add New → Upload Plugin** → Activate
   - SaaS (only for selling to many churches on Multisite): [kingdom-saas.zip](https://github.com/Innovatyou/church-wordpress-saas/raw/main/dist/kingdom-saas.zip) → see [its README](plugins/kingdom-saas/README.md)

   Updating: upload the newer zip the same way and choose **Replace current with uploaded**. Settings, partners and payments are kept.
2. Go to **Appearance → Ministry OS Setup**, pick a homepage layout and click **Create starter site**. This creates every page with its template, the main and footer menus, the homepage and the blog page. It never deletes content.
3. Go to **Church Suite → Settings** and fill in church details, service schedule, payment gateways, email, YouTube and SEO.
4. Go to **Appearance → Customize → Ministry OS Theme** to set colours, fonts, header style, hero, sections, contact and social links.
5. Install an SMTP plugin (WP Mail SMTP, FluentSMTP…) with Mailgun, SendGrid, SES, Brevo or Postmark for reliable email.
6. Use a real server cron for timely emails and reminders: `*/5 * * * * curl -s https://yoursite.com/wp-cron.php >/dev/null`

Requirements: WordPress 6.0+, PHP 7.4+ with the GD extension (app icons), HTTPS (required by the payment providers and the church app).

**For developers:** `themes/` and `plugins/` mirror `wp-content/`, so you can also copy or symlink the folders into a WordPress install. `bin/build-zips.sh` rebuilds `dist/`; GitHub Actions checks PHP syntax and builds the zips on every push, and attaches them to a release when you push a tag such as `v1.1.0`.

---

## Theme: page templates

Choose a template under **Page → Template**:

| Homepages (6) | Church pages | Partnership & giving | Layouts |
|---|---|---|---|
| Home — Classic | About Us | Give Online | Full Width |
| Home — Modern Video | Leadership | Partnership | Sidebar Left |
| Home — Minimal | What We Believe | Partner Portal | Sidebar Right |
| Home — Mega Church | Ministries | Prayer Request | Landing Page (no header/footer) |
| Home — Live Stream First | Sermons | Watch Live | |
| Home — Split Hero | Events · Testimonies · Contact · Plan Your Visit · I'm New | | |

There are also dedicated templates for single and archive views of sermons (video, audio with speed control, notes, scripture link, series list, filters), events (countdown, map, register, Google/Apple/Outlook calendar), ministries, leaders and testimonies.

**Sections** (`template-parts/sections/`) can be reordered on a plain front page via *Customize → Homepage Sections*:
`hero` (6 variants), `service-times`, `welcome`, `live`, `countdown`, `sermons`, `events`, `ministries`, `stats`, `partnership`, `give`, `leadership`, `testimonies`, `verse`, `prayer`, `blog`, `newsletter`, `visit`, `contact`, `cta`.

**Branding & colours**: 9 ready-made palettes (Royal Purple, Ocean Blue, Emerald Grace, Crimson Fire, Sunrise, Midnight, Rose of Sharon, River Jordan, Heritage Gold) or **custom brand colours** (pick any main, highlight and dark colour — also in *Church Admin → Design & live stream → Church colours*, no code). The optional visitor colour picker offers the palettes plus **Custom**, a free colour picker for main and highlight colours (text on buttons switches between white and dark automatically so it stays readable); light logo for dark mode and transparent headers; tagline, motto and button style (pill, rounded, square). Set these in *Customize → Church Branding & Colour Palettes*.

**Church fonts**: 38 Google fonts (classic serif, modern sans and bold display) with 13 ready-made pairings — Classic, Traditional, Heritage, Elegant, Warm, Editorial, Modern, Friendly, Fresh, Youth, Bold, Grace and Simple — or any heading and body font you like. Change them in *Church Admin → Design & live stream → Church fonts* (with a live preview) or the Customizer. Choose "Device fonts only" to load nothing from Google.

**Mobile menu**: a full-screen menu in the church colours with the church logo, large menu items with boxed arrows that slide open sub-menus (with Back), quick actions (Watch live, Partner login, Church admin for staff, Install app, Notifications) ending in a bold Give button, then Contact us (service times, phone, address with map link, email) and Follow us. It works with the keyboard and screen readers, and switches to a dark background in dark mode.

**3D UI & mobile-first**: tilt-and-glare cards, layered 3D hero (floating cross, orbs, orbiting ring) with mouse and scroll parallax, pressable 3D buttons, glass panels, and an app-style bottom tab bar on phones (Home, Watch, Give, Prayer, Menu). Both can be toggled in *Customize → 3D Effects & Mobile*, and motion is reduced automatically for visitors who ask for it.

**Church images**: the theme ships with original illustrated African church scenes (worship, church building, choir in kente, preacher, fellowship, kids church) as defaults. Replace any of them with your own photos in *Customize → Church Images & Gallery*, with no code. A six-photo gallery section is included.

**Social connect**: branded cards for Facebook, Instagram, YouTube, X, TikTok, WhatsApp and Telegram, with a church hashtag, plus share buttons on every sermon, event and post.

**Show / hide sections**: every homepage section (announcement strip, welcome, stats, gallery, sermons, events, partnership, testimonies, visit us, social, newsletter, and so on), plus the top bar, footer newsletter and mobile tab bar, can be switched off under *Appearance → Customize → Show / Hide Sections* or in *Church Admin → Design & live stream*. No code needed.

**YouTube live stream section (always live)**: paste your YouTube **channel link or @handle** once (for example `https://www.youtube.com/@yourchurch`). The website then:
- finds your channel ID by itself (no need to look up `UC…`);
- checks about once a minute whether you are live, scheduled or offline. All visitors share one cached check, so traffic never multiplies requests to YouTube;
- **when you go live**: shows your live stream with a red "Live now" badge, plus YouTube live chat if switched on;
- **when a stream is scheduled**: shows YouTube's waiting room with "Starts today at 9:00 am" and a ticking countdown;
- **the rest of the time**: shows your replay video (default `xzoaPmLegBE`) with "Not live right now · Next: Sunday 9:00 am" taken from your service times;
- **switches open pages automatically** when the stream starts or ends, with no refresh needed (`GET /wp-json/kingdom/v1/live`).
- **shows how many people are watching** ("Live now · 1,234 watching"), the same number YouTube shows, refreshed every minute. It can be switched off. People watching on your website use YouTube's own player, so they **count in your YouTube live viewer numbers and views** like viewers on YouTube itself. (Muted autoplay views may not count until the viewer taps play, so autoplay is off by default.)

**Not live? The last livestream plays.** Between services the section (and the Watch Live page) plays the church's most recent livestream, titled “Replay of our last service”, with the next service time; a fixed video can be chosen instead, or the replay switched off (Church Admin → Design & live stream → Live stream, video & audio). No API key is needed: the theme reads your channel's public live page. For the most reliable detection, add a free YouTube Data API key (or reuse the one in Church Suite settings). Church Admin → Design & live stream shows whether the channel is connected and its current status, with a "Check now" link. Without a channel, the section simply shows the one video you enter.

**Videos section**: a large player with a side list. Add up to 4 YouTube or Vimeo links with titles, or leave them empty to show your latest sermon videos. Videos open in a pop-up player.

**Audio section**: up to 4 MP3 messages with players and download buttons (or your latest sermon recordings), plus an optional Spotify, SoundCloud or Apple Podcasts player.

**Every section has an on/off switch**: all homepage sections, plus site elements (top bar, header Give button, search, dark-mode button, live badge, colour picker, footer newsletter, back-to-top button, phone tab bar).

**Video homepage banner**: under *Customize → Homepage Banner (image or video)* or *Church Admin → Design & live stream*, paste a YouTube, Vimeo or MP4/WebM link to use a video as the banner background. It plays muted and on a loop, and the banner image shows while it loads. Options:
- **Hide all banner text**: show the video on its own. A hidden heading is kept for screen readers and SEO.
- **"Watch with sound" button**: opens the YouTube video with sound in a pop-up.
- **Banner height**: full screen, tall, medium, or the video's own 16:9 shape.
- **Overlay darkness**: 0–90%.

Other theme features: three header styles (classic, centered, transparent over hero), sticky header, top bar, live "LIVE NOW" badge, dark mode toggle (remembers the visitor's choice), video-modal hero, animated counters, scroll reveal, floating WhatsApp button, back-to-top, breadcrumbs and accessible markup (skip link, focus styles, ARIA, reduced-motion support).

---

## Plugin: features

### Partnership & partner portal
- Plans (fixed or custom amount; weekly, monthly, quarterly, yearly, one-time; featured "Most popular"), managed under **Church Suite → Plans**
- `[kcs_partner_register]` creates a WordPress account (role *Church Partner*) and a partner record, logs the partner in, and takes them to make their first gift
- `[kcs_partner_login]` and `[kcs_partner_dashboard]`: overview (plan, benefits, commitment, next due date, total given), give, **Upgrade plan** (side-by-side plan comparison with benefits; upgrade, downgrade or switch, with custom amounts), giving history, profile, pause or resume, change password
- Plan changes are recorded in the partner's notes, emailed to the partner (editable "Partnership plan changed" template) and to admins, and fire `kcs_partner_plan_changed` and the `partner.plan_changed` webhook
- Logged-in partners see "Your current plan" and "Upgrade/Switch to this plan" on the public plans grid
- Partners are kept out of wp-admin and the toolbar is hidden for them
- Daily cron emails reminders N days before the due date and flags partners **overdue** after 7 days' grace

### Local and international partners (USD by default)
- Every plan has an **international price** (USD by default) and a **local price** (e.g. NGN), set under *Church Admin → Partnership plans* or *Church Suite → Plans*
- The partnership page, registration form, partner dashboard, Give page and Tithe & Offering page show an **International / Local** switch. International (USD) is selected by default and visitors can tap Local
- A partner's choice decides the currency of their commitment, renewals and receipts. Partners can switch later from **Upgrade plan → Pay in …**, and church admins can change it on the partner's record ("Partner type")
- Payment methods follow the currency automatically: for example PayPal and Stripe for USD; Paystack, Flutterwave and bank transfer for NGN
- Settings: international currency, local currency (or "no local option"), labels for both (e.g. "Nigeria"), and quick-pick amounts for each. If a plan has no local price, local partners pay the international price
- REST: `GET /kcs/v1/plans` returns `prices` for each region; `PUT /kcs/v1/me/plan` and registration accept `region` (`international` or `local`)

### Tithe & Offering page
- `[kcs_tithe]` (page created automatically; the theme's **Tithe & Offering** page template adds the scripture, giving explanations and an FAQ around it)
- Giving types as cards: Tithe, Offering, Thanksgiving, First Fruit and Seed (editable under *Settings → Giving & partnership → Tithe & Offering page options*). Link to a type with `?type=offering` or `[kcs_tithe type="offering"]`
- International (USD) / Local switch with quick amounts for each currency, a 10% **tithe calculator**, an optional prayer note, and an emailed receipt
- **Paystack is the featured payment method**: shown first, marked "Recommended" and preselected for both USD and local givers (card, bank transfer, USSD, QR or mobile money). Change or turn off the featured method under *Church Admin → Partnership plans* or *Settings → Giving & partnership*. Add your Paystack keys under *Church Suite → Settings → Payments*; to take USD through Paystack, USD must also be enabled on your Paystack account
- *Church Admin → Giving received* shows this month's totals per purpose (tithe, offering, partnership…) and filters payments by purpose

### Church Admin dashboard (front end, no wp-admin)
- **Delete a partner** (administrators): open the partner → *Delete this partner*. They leave the partners list, reminders and the partner dashboard; past payments stay in Giving received. Optionally also delete their website login (only a partner-only login, never staff).
- **Simple layout**: a grouped menu (People · Giving · Messages · Website & app · Account) with plain names; on phones it is one *Menu* button that opens the full list. Every page starts with one sentence saying what it is for.
- **Search box** (top of the menu, or press `/`): pages and every setting appear as you type — e.g. “service times”, “logo”, “Paystack” — and open right at that setting. Press Enter to also search partners, givers and payment references, prayer requests and first-time visitors.
- **Home**: *Needs your attention* (bank transfers to confirm, new prayer requests, first-time visitors, testimonies to approve, partners behind on payments — or "All caught up"), a *Get your website ready* checklist with progress (logo, service times, address and phone, online giving, YouTube, WhatsApp — each links to the exact place), quick actions and the month at a glance.
A full church-management dashboard on the **Church Admin** page (`[kcs_church_admin]`), built for church staff with the **Church Admin** role. That role never sees WordPress: logging in goes straight to the dashboard, and wp-admin redirects back to it.
- **Overview**: active vs overdue (locked) partners, money received, payments due this week, partners by plan
- **Partners**: search, filter, edit details, plan, amount, status and due date; WhatsApp a partner
- **Payments**: mark payments received (unlocks the partner and sends the receipt), re-check gateway payments
- **Bank transfers**: turn on offline giving by adding up to 3 bank accounts (bank, account name, number, currency, optional sort code/IBAN/SWIFT), choose whether it is offered for partnership and/or tithes, offerings and other giving, and choose who can confirm (church administrators only by default, or all staff). Givers see the account for their currency and the reference to use (with copy buttons), then press **I have made this transfer** and can attach a receipt (JPG, PNG or PDF, kept private). The church is emailed, the transfer waits under *Bank transfers* until someone allowed confirms it (receipt sent, partner renewed) or rejects it with a reason (the giver is emailed)
- **Prayer requests**, **Visits** ("Plan your visit" bookings with WhatsApp follow-up) and **Testimonies** (publish or remove)
- **Message partners**: by plan and status, by email and/or as an update on their dashboard (announcement, event or resource with a link)
- **Website WhatsApp chat**: turn the floating "Chat with us" button on or off, and set the greeting, office hours and contacts (e.g. General enquiries, Prayer line, First-time visitors), each with its own number and pre-filled message (`{church}` and `{page}` are filled in automatically)
- **Pictures & text**: upload the logo, white logo, homepage banner, pastor, building, stats and gallery photos (with captions) straight from a phone, edit the homepage banner and welcome texts, and the address, phone, email, map and social links
- **Newsletter**: write to subscribers, partners or everyone (with a test email first), see recent emails, search, unsubscribe or delete subscribers and download them as CSV
- **Settings** (church administrators): every Church Suite setting from wp-admin (church details and service times, payment gateways and their keys, emails and templates, live streaming, SEO, REST API), with a test-email button
- Church owners and staff land on Church Admin after logging in; the old wp-admin overview redirects there, and the WordPress toolbar is hidden on it (the platform super admin keeps wp-admin)
- **Footer** (under *Website*): turn the footer off, choose a layout (four columns, centred or minimal one-line) and colours (dark, light or the church's main colour), show or hide each part (newsletter, about & social, service times, quick links, contact, copyright line) and edit the about and copyright text. The same options are under *Customize → Ministry OS Theme → Footer*. On platform plans without white label, the "Powered by" credit stays visible even when the footer is off
- **Website**: banner video link, hide banner text, sound button, banner height and overlay, and an on/off switch for every website section
- **Plans & currency**: international and local currency settings, and each plan's USD and local price, benefits, level and status
- **Pastors & WhatsApp**: add pastors with WhatsApp numbers and choose which plans can reach them; the highest plan always reaches every pastor

### Partner access by plan & payment lockout
- Each plan's **level** follows the plan order (Plans → Level order; higher number = higher level). The dashboard shows a star badge for the partner's level.
- Partners see the pastors and partner updates for their level and every level below. The top plan sees every pastor and every update.
- The dashboard is **locked** until the first payment, and again when a payment is more than *N* days overdue (Settings → General → grace days, default 7). Locked partners only see a renewal screen with the payment form; it unlocks automatically when the payment is confirmed.
- A banner warns partners five days before their due date.

### Admin dashboard (Church Suite menu)
- **Dashboard**: KPIs, 12-month giving chart, partners by status, recent payments, shortcode reference
- **Partners**: search, filter by status, sort, add/edit, bulk activate, deactivate, email or delete, CSV export
- **Payments**: filter by status, gateway, purpose and date; re-verify with the gateway; mark offline payments paid (sends receipt); mark refunded or cancelled; CSV export
- **Email Partners**: compose to all, active or overdue partners, subscribers, or selected partners; queue status
- **Newsletter**: campaigns (draft, test send, schedule, send), subscribers (import, export, bulk actions)
- **Prayer Requests**: moderate and approve for the public prayer wall, mark answered
- **Settings**: General · Payment Gateways · Email & Notifications · Email Templates (editable) · Live Streaming · SEO · REST API

### Payment gateways
- **Zenith GlobalPay** (automatic): turn it on in *Settings → Payments → Zenith GlobalPay* with the API key (public key) from the GlobalPay dashboard and the currencies on your account. Givers pay on GlobalPay's page and return; each payment is confirmed with GlobalPay's API (status *Successful*, matching amount and currency) before it counts — otherwise it waits in *Needs review*. GlobalPay asks for the giver's phone number, so the phone field becomes required when it is chosen.
- **Payment links from any provider** (Paystack page, Flutterwave link, Zenith GlobalPay form, Remita, Opay, Moniepoint, Stripe, PayPal…): up to three, in *Settings → Payments → Payment link 1–3*. Each appears to givers as its own method, named after the provider automatically (or a name you choose), with optional currencies. Givers see the amount and reference, open the page, pay, then press *I have paid* (optional receipt and transaction ID). The payment waits in *Church Admin → Confirm payments* until the church confirms it, like a bank transfer.
- **Order of payment methods**: *Settings → Payments → Order of payment methods* (↑ ↓); the first one switched on is preselected.
| Gateway | Integration | Webhook verification |
|---|---|---|
| **Flutterwave** | Standard checkout (v3) | `verif-hash` secret hash |
| **Paystack** | Hosted checkout | HMAC-SHA512 (`x-paystack-signature`) |
| **PayPal** | Orders v2, capture on return | PayPal verify-webhook-signature API |
| **Stripe** | Checkout Sessions (cards, Apple Pay, Google Pay) | `Stripe-Signature` HMAC-SHA256 with timestamp tolerance |
| **Razorpay** | Payment Links | HMAC-SHA256 (`x-razorpay-signature`) |
| **Bank transfer / offline** | The church's bank accounts; the giver says when they have paid (optional receipt) and the church confirms | — |
| **Custom API gateway** | Any provider with a hosted checkout (Monnify, Interswitch, Remita, Squad, Chapa, PesaPal…), configured entirely in settings: endpoints, headers, JSON body templates with placeholders, response paths, success values, minor units | HMAC SHA-256/512/1 or shared secret |

Security model:
- Every payment is **verified server to server** with the provider before it is marked paid. The redirect alone is never trusted.
- Amount and currency must match what was expected; a mismatch is flagged *Needs review*.
- Completion is an atomic, idempotent status change, so a callback and a webhook racing each other only credit once.

Webhook URL for each gateway: `https://yoursite.com/wp-json/kcs/v1/webhooks/{gateway}` (also shown in settings).

**Add your own gateway** in code:
```php
class My_Gateway extends KCS_Gateway { /* fields(), initialize(), verify(), parse_webhook() */ }
add_filter( 'kcs_payment_gateways', function ( $g ) { $g['mine'] = new My_Gateway(); return $g; } );
```

> **Recurring partnerships** are handled with due dates plus reminder emails and a one-click "Give now" link. This works the same across every gateway. Card auto-debit (gateway-native subscriptions) is not included.

### Email notifications & newsletter
- **Email sending (SMTP)**: *Settings → Email → Sending (SMTP)* sends through a real mailbox (Hostinger email `smtp.hostinger.com` 465 SSL, Gmail/Workspace 587 TLS with an app password, Zoho, Brevo…) so receipts and reminders reach inboxes. Saved passwords are never shown; *Send a test email* explains any error. On Ministry OS Cloud the platform mailbox is used when the church has not set its own.
- Branded HTML emails: partner welcome, receipt, due-date reminder, admin new-partner and new-payment alerts, newsletter confirm and welcome, prayer received. All are editable with placeholders.
- Newsletter with double opt-in, one-click unsubscribe (with `List-Unsubscribe` header), audiences (subscribers, all/active/overdue partners, everyone), scheduling, test sends, and optional auto-email when a sermon is published
- Batch queue (default 50 per 5 minutes) with retries, to protect sender reputation

### Live streaming
- Paste the channel link, @handle or channel ID: live detection works with no API key, at any time of day (not only service times), and the website, live badge, Watch Live page and app all show the same status
- Optional YouTube Data API key: a backup when YouTube does not answer the server (checks the latest uploads, 2 quota units) plus viewer counts; a manual video override and an offline fallback video are also available
- `[kcs_livestream chat="yes"]` player plus YouTube live chat; Facebook Live or any custom embed (Vimeo, Boxcast…)
- Countdown to the next service and a live badge that refreshes itself

### Church app (PWA) — Android and iPhone
- **Browser tab icon (favicon)**: every church page (and its login screens) shows the church's icon — its logo on a tile in its colour, the same as the app icon — even when the app is switched off. Change it under *Church Admin → Church app → App & browser tab icon* (empty uses the logo); a WordPress Site icon, if set, takes priority.
Every church site is also an installable app, with **its own name, logo and church colours** (each SaaS church gets its own app on its own address or custom domain). No app store needed.
- **Install**: Android/Chrome shows an *Install app* invitation and a button in the menu; iPhone/iPad Safari shows a step-by-step *Add to Home Screen* guide.
- **Branding**: the icon, maskable Android icon, iPhone home-screen icon and 11 iPhone launch screens are drawn automatically from the church logo (App icon setting → site icon → theme logo → Church Suite logo) on the church's main colour, and are redrawn when the logo or colours change.
- **Feels native**: full-screen with no browser bars, safe-area padding for notched phones, a back button in the header, pull to refresh, a page-loading bar, external links open outside the app, and shortcuts (long-press the icon) to Give, Watch live, Prayer and the partner dashboard.
- **Works offline**: public pages people visit are saved; with no connection they open instantly, and anything else shows a branded offline page with service times. Admin, dashboards, checkout and logged-in pages are never saved.
- **Push notifications** (Android and iPhone iOS 16.4+ from the Home Screen app): a *Turn on notifications* button in the menu, and a one-time offer inside the app. **Automatic "We're live" notification** when the YouTube stream starts (checked every 5 minutes in the background). Self-hosted Web Push (VAPID + RFC 8291 encryption), no Firebase account or fees; sent in parallel in the background; removed devices are cleaned up automatically.
- **Church Admin → Church app**: turn the app on/off, app name and short name, optional icon, install invitation and live-notification switches, the number of phones with notifications, **send a notification to every phone**, and delivery history. *Message partners* can also send to partners' phones.

### Other digital features
Sermons (series, speakers, topics, video, audio, notes), **podcast RSS feed** for Apple Podcasts and Spotify (`/feed/sermon-podcast/`), events with `.ics` and Google Calendar links, ministries, leadership, testimonies, prayer requests and an "I prayed" prayer wall, a public testimony submission form (`[kcs_testimony_form]`, moderated), social connect buttons (`[kcs_social]`) and verse of the day, and a signed outgoing webhook to CRMs or Zapier.

### Advanced SEO
- Per-post SEO title, meta description, focus keyword, canonical URL, social image, noindex/nofollow
- Live Google preview and on-page analysis (length, keyword placement, density, headings, alt text, internal links)
- Open Graph and Twitter cards; Google and Bing verification; geo meta
- JSON-LD graph: **Church** (address, geo, service times, social profiles), WebSite with SearchAction, WebPage, BreadcrumbList, **Event**, **VideoObject** and **PodcastEpisode** for sermons, BlogPosting, Person for leaders
- Breadcrumbs; noindex for search/date/author archives; sitemap cleanup (noindex posts and the users sitemap removed); robots.txt sitemap line; **301 redirect manager**
- Switches itself off automatically when Yoast, Rank Math, SEOPress or AIOSEO is active, so tags are never duplicated

---

## REST API — `/wp-json/kcs/v1`

**Authentication for admin endpoints** (any one of):
- the `X-KCS-API-Key: <key>` header (Settings → REST API)
- a logged-in user with the `manage_kcs` capability (cookie + `X-WP-Nonce`)
- WordPress Application Passwords (HTTP Basic)

Allowed CORS origins can be set in settings.

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| GET | `/plans` | public | Active partnership plans |
| GET | `/gateways?currency=NGN` | public | Enabled gateways for a currency |
| POST | `/payments/initialize` | public (partner if logged in) | `{gateway, amount, currency, purpose, name, email, phone}` → `{reference, redirect_url}` |
| GET | `/payments/status?reference=` | public | Payment status (re-verifies pending payments) |
| GET | `/payments/callback/{gateway}` | public | Return URL from the provider (verifies, then redirects) |
| POST | `/webhooks/{gateway}` | signature | Provider webhooks |
| POST | `/subscribe` · `/unsubscribe` | public | Newsletter |
| GET | `/live` · `/services/next` · `/verse` · `/prayer-wall` | public | Live status, schedule, verse, prayer wall |
| POST | `/prayer-requests` · `/prayer-requests/{id}/pray` | public | Prayer |
| GET/PUT | `/me` · GET `/me/payments` | partner | Partner self-service |
| PUT | `/me/plan` | partner | Upgrade, downgrade or switch: `{plan_id, amount?, frequency?}` |
| POST | `/testimonies` | public | Submit a testimony (held as pending for review) |
| POST | `/push/subscribe` · `/push/unsubscribe` | public | App notifications: browser `PushSubscription` JSON (Google, Mozilla, Apple, Microsoft push services only) |
| GET | `/stats` | admin | Dashboard stats |
| GET/POST | `/partners` | admin | List (search, status, plan_id, page, per_page, orderby, order) or create |
| GET/PUT/DELETE | `/partners/{id}` · GET `/partners/{id}/payments` | admin | Manage a partner |
| GET | `/payments` · `/payments/{id}` | admin | List (search, status, gateway, purpose, date_from, date_to) or view |
| POST | `/payments/{id}/complete` · `/payments/{id}/verify` | admin | Manual confirm or re-verify |
| POST/PUT/DELETE | `/plans` · `/plans/{id}` | admin | Manage plans |
| POST | `/notifications/email` | admin | Email partners: `{subject, message, audience \| partner_ids[] \| emails[], send_now}` |
| GET/POST/DELETE | `/subscribers` · `/subscribers/{id}` | admin | Subscribers |
| GET/POST | `/campaigns` · `/campaigns/{id}` · POST `/campaigns/{id}/send` | admin | Newsletter campaigns |
| GET/PUT | `/prayer-requests` · `/prayer-requests/{id}` | admin | Moderate prayer requests |

List endpoints return `X-WP-Total` and `X-WP-TotalPages` headers.

```bash
# Email all active partners
curl -X POST https://yoursite.com/wp-json/kcs/v1/notifications/email \
  -H "X-KCS-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"audience":"partners_active","subject":"Partners meeting","message":"<p>Dear {first_name}, see you Friday!</p>"}'
```

**Outgoing webhooks**: set a URL in Settings → REST API to receive `partner.created`, `payment.completed`, `subscriber.created` and `prayer.created`. Each is signed with `X-KCS-Signature` (HMAC-SHA256 of the body using your API key).

### Developer hooks
Actions: `kcs_partner_created`, `kcs_partner_updated`, `kcs_partner_deleted`, `kcs_payment_started`, `kcs_payment_completed`, `kcs_webhook_processed`, `kcs_subscriber_created`, `kcs_subscriber_unsubscribed`, `kcs_prayer_created`, `kingdom_ytlive_went_live` (theme, fired once per live broadcast).
Filters: `kcs_payment_gateways`, `kcs_currencies`, `kcs_giving_purposes`, `kcs_email_templates`, `kcs_campaign_recipients`, `kcs_live_status`, `kcs_schema_graph`, `kcs_seo_description`, `kcs_breadcrumb_items`, `kcs_seo_post_types`, `kcs_meta_fields`, `kcs_settings_schema`, `kcs_verses`, `kcs_pwa_manifest`, `kcs_pwa_colours`.
Templates: copy any file from `plugins/kingdom-church-suite/templates/` to `yourtheme/kingdom-church-suite/` to override it.

---

## Going live checklist
- [ ] HTTPS enabled
- [ ] Gateway **live** keys entered and webhook URLs registered in each provider's dashboard
- [ ] SMTP configured and a test email sent (Settings → Email)
- [ ] Real server cron set up
- [ ] YouTube channel ID (and optionally an API key) added
- [ ] Church address, geo coordinates and social links filled in (SEO tab)
- [ ] Search Console verification and sitemap submitted (`/wp-sitemap.xml`)
- [ ] Behind Cloudflare or a load balancer: `define( 'KCS_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );` in wp-config.php (so login and payment rate limits see each visitor, not the proxy)
- [ ] Using the outgoing webhook? Set an API key (20+ characters) first: events are signed with it and are not sent without one
- [ ] Church app: open the site on an Android phone and an iPhone, install it, turn on notifications and send a test from *Church Admin → Church app*

---

## Replacing the church images with real or AI-generated photos

The default images are illustrations bundled in `themes/kingdom/assets/img/`. To use photos, go to **Appearance → Customize → Ministry OS Theme → Church Images & Gallery**, click a slot and upload. Remove a photo to bring the illustration back. No code is needed.

If you want AI-generated photos first, these prompts work well in Midjourney, DALL·E, Ideogram or Adobe Firefly (landscape 16:10, 1920 px or wider):

| Slot | Prompt |
|---|---|
| Homepage hero | *Wide photo of a joyful Nigerian church congregation worshipping with raised hands in a modern auditorium, warm golden stage lights, a glowing cross on the stage, women in colourful gele headwraps, shallow depth of field, documentary style* |
| Pastor / welcome | *Portrait of a smiling West African pastor couple in their 50s in elegant traditional attire, standing in a bright church foyer, soft natural light, 4:5* |
| Church building | *Modern African church building at golden hour, cream walls, red roof, a bell tower with a cross, palm trees, families walking in on a Sunday morning, Lagos* |
| Stats background | *Church choir in matching kente-pattern robes singing on stage, motion, vibrant colours, wide angle* |
| Gallery | *Youth outreach serving food in a Lagos community* · *Kids church class with happy children and balloons* · *Pastor preaching passionately from a wooden pulpit with an open Bible* · *Small group Bible study in a living room, Ghana* |

Use photos of your own members only with their permission.
