# Veltrix (email, SMS & WhatsApp) for churches

Each church connects its **own** Veltrix account. Churches cannot install plugins, so the
platform owner installs the plugin once for the whole network.

## Platform owner: install once

1. Download `https://veltrix.ng/downloads/veltrix-wordpress.zip` ("Veltrix for WordPress" **1.3.1** or later: 1.3.1 stops a new network user being added to a church's contacts before joining that church).
2. Network Admin → Plugins → Add New → Upload → **Network Activate**.
3. Do **not** enter an API key at network level.
4. Optional: Platform dashboard → Settings → **Veltrix** → paste your Veltrix customer ID so new
   church sign-ups are credited to you.

Ministry OS Cloud sets the plugin's constants for you, so `wp-config.php` needs no changes:

| Constant | Value |
|---|---|
| `VELTRIX_PLATFORM_NAME` | The platform name from Platform → Settings (e.g. "Ministry OS") |
| `VELTRIX_REFERRAL` | The Veltrix customer ID from Platform → Settings → Veltrix (only when filled in) |
| `VELTRIX_API_URL` | Not set (the plugin's default, `https://veltrix.ng/api/v1`) |

They are defined on every request (front end, wp-admin, `admin-post.php` where the Connect
button runs, cron and WP-CLI), as soon as Ministry OS Cloud loads, which is before the Veltrix
plugin loads. If `wp-config.php` already defines either constant, that value wins.

**Server cron (needed for automatic contact sync).** WP-Cron runs per site and only when that
site has visitors, so a quiet church's new members would wait. Run every site's due jobs from a
server cron job every 5 minutes. Platform → Settings → Veltrix shows the line with your server's
path. On Hostinger, add it in hPanel → Advanced → Cron Jobs → Custom:

```
cd /home/USER/domains/ministryos.com.ng/public_html && wp site list --field=url --deleted=0 --archived=0 | xargs -I{} wp cron event run --due-now --url={} --quiet
```

If the job reports `wp: command not found`, run `which wp` over SSH and use that full path
(e.g. `/usr/local/bin/wp`) in both places. This also keeps church email queues, partner
reminders and scheduled posts on time.

**Who can connect.** The page needs `manage_options`. Church owners are the administrator of
their own site, so they have it. Church staff (the "Church Admin" role) do not, and they don't
see the page. Leave it this way: the page can send email and manage contacts on the church's
Veltrix account. No mu-plugin or `veltrix_capability` filter is needed.

Email routing uses `pre_wp_mail`, so with "Send site emails through Veltrix" on, Veltrix sends
all MinistryOS emails (they all go through `wp_mail`) and the SMTP settings are skipped. If
Veltrix can't carry a message and the plugin's fallback is on, WordPress sends it the normal way,
through the platform or church SMTP.

**Where churches find it.** Church Admin → Messages → **Email & SMS (Veltrix)**. You can also
search for "Veltrix" or "SMS". The page explains the steps, and its button opens the plugin's
page (`/wp-admin/options-general.php?page=veltrix`). It appears only while the plugin is active.

Church sites must be served over **https** (the connect flow requires it).

## Churches: connect your Veltrix account

1. Church Admin → Messages → **Email & SMS (Veltrix)** → **Open Veltrix settings** →
   **Connect with Veltrix**.
2. Sign in to Veltrix, or create a free account (the link is on the same page).
3. Press **Allow and connect**. You come back automatically.
4. Press **Send test email to me**. When it arrives, tick **Send site emails through Veltrix**
   and save.
5. Optional: under **Contact sync**, add rules (for example "Users with the role: Member" →
   "Members" list) and press **Export**. Tick **Automatically add new contacts** to keep the
   lists up to date.
6. Optional: add `[veltrix_subscribe]` to any page for a newsletter sign-up form.

## Test before rolling out (staging church site)

- [ ] Connect: you go to veltrix.ng, the approval screen names the church and the platform,
      and you come back showing "Connected to Veltrix as …".
- [ ] "Send test email to me" arrives.
- [ ] With email routing on, a password reset email and a giving receipt arrive. All Ministry OS
      emails go through `wp_mail`.
- [ ] Disconnect: the page shows the Connect button again.
- [ ] Two church sites connected to two different Veltrix accounts stay fully separate.

## Don'ts

- Don't share one Veltrix API key across churches.
- Don't modify the plugin's files. Use the constants and the filter.

## Local staging results (Veltrix for WordPress 1.3.1)

Done:
- Network-activated, version 1.3.1.
- Settings → Veltrix opens from Church Admin for the church owner.
- "Create a free account" link carries `?ref=` with the referral code.
- Connect goes to `veltrix.ng/customer/connect/authorize` with `client_id=wordpress`,
  `site_name` = the church name, `platform=Ministry OS`, `ref` = the referral code, PKCE S256,
  and the church's own `admin-post.php?action=veltrix_connect_callback` return address.
- Church Admin staff don't see the link; opening the page directly gives WordPress's
  "not allowed" message.
- The cron command runs every site's due jobs on the subfolder network.

Still to do on a real https staging church (needs Veltrix accounts):
- Approve on veltrix.ng.
- Test email.
- Password reset with routing on.
- Disconnect.
- A second church on a different Veltrix account.
