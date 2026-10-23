# Veltrix (email, SMS & WhatsApp) for churches

Each church connects its **own** Veltrix account. Churches cannot install plugins, so the
platform owner installs the plugin once for the whole network.

## Platform owner: install once

1. Download `https://veltrix.ng/downloads/veltrix-wordpress.zip` ("Veltrix for WordPress" 1.3.0).
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

If `wp-config.php` already defines either constant, that value wins.

**Who can connect.** The page needs `manage_options`. Church owners are the administrator of
their own site, so they have it. Church staff (the "Church Admin" role) do not, and they don't
see the page. Leave it this way: the page can send email and manage contacts on the church's
Veltrix account. No mu-plugin or `veltrix_capability` filter is needed.

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
- [ ] With email routing on and an SMTP mailbox also set (Platform → Settings → Email, or the
      church's Settings → Email), check which one sends.
- [ ] Disconnect: the page shows the Connect button again.
- [ ] Two church sites connected to two different Veltrix accounts stay fully separate.

## Don'ts

- Don't share one Veltrix API key across churches.
- Don't modify the plugin's files. Use the constants and the filter.
