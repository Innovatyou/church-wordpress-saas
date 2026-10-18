# SEO playbook: ranking Ministry OS for church searches in Nigeria

No one can guarantee a position on Google. What wins is a technically clean site, pages that answer exactly what churches search for, and trust signals from other sites (links, reviews, mentions). The plugin (Ministry OS Cloud 1.9.0+) handles the on-site part. This playbook covers the rest, which only you can do.

## 1. What the plugin already does

- **Title, description, canonical, `en-NG` hreflang and geo tags** on the landing page and six solution pages.
- **Social previews** (Open Graph and Twitter) for WhatsApp, Facebook, X and LinkedIn.
- **Structured data**: Organization (serving Nigeria), WebSite, SoftwareApplication with plan prices in NGN and USD, FAQPage and BreadcrumbList.
- **Sitemap** at `/wp-sitemap.xml`. It holds the landing and solution pages and blog posts. The main site's demo pages are left out.
- **robots.txt** keeps `/platform/`, `/login/`, `/billing/` and `/wp-admin/` out of search. Sign-up, login, thank-you and dashboard pages are `noindex`.
- **Six intent pages**, each targeting one search:

| Page | Main searches |
|---|---|
| `/church-website-builder/` | church website builder Nigeria, church website design Nigeria, how to create a church website, church website cost Nigeria |
| `/church-app/` | church app Nigeria, church mobile app, app for my church |
| `/online-giving-for-churches/` | online giving for churches Nigeria, receive tithe online, church payment platform Nigeria, Paystack for churches |
| `/church-management-software/` | church management software Nigeria, church software, church database software |
| `/church-partnership-management/` | ministry partnership management, partners portal for ministries |
| `/church-live-streaming/` | church live streaming website, stream church service on website |

- Landing page: "church website, online giving & church app", "for Nigerian churches", plus city names (Lagos, Abuja, Port Harcourt, Ibadan, Enugu, Benin City, Kano, Uyo, Warri).

## 2. First week: set up (about 1 hour)

1. **Platform → Settings → Search & sharing (SEO)**:
   - Add a sharing image: 1200 × 630, your logo plus a phone showing a church site.
   - Add your business phone (+234…) and your social profile links.
2. **Google Search Console** (search.google.com/search-console):
   - Add the property `https://ministryos.com.ng/`.
   - Choose **HTML tag** and paste only the `content="…"` value into *Google Search Console verification code*. Save, then click Verify.
   - Go to **Sitemaps** and submit `wp-sitemap.xml`.
   - Under **URL inspection**, request indexing for the landing page and each of the six solution pages.
3. **Bing Webmaster Tools**: import from Search Console, or verify with the Bing code field, then submit the same sitemap.
4. **Google Analytics 4**: create a property, then paste the `G-…` ID.
   - Mark "sign_up" as a key event: sign-up completes on `/signup/` with a redirect to the church site.
5. **Google Business Profile**:
   - Create one for Ministry OS: category "Software company", service area Nigeria, with your phone and website.
   - Post an update weekly. This is the strongest local trust signal you can get for free.
6. **Network Admin → Settings**: make sure "Search engine visibility" is **not** blocked on the main site (Settings → Reading → uncheck "Discourage search engines").

## 3. Links and mentions (months 1–6)

Links from Nigerian and Christian websites are what move rankings for competitive terms. Aim for 2–4 good links a month.

- **Directories**: VConnect, BusinessList.com.ng, Nigeria Galleria business directory, Finelib, Connect Nigeria, Clutch, G2, Capterra, GetApp, SaaSHub and Product Hunt (launch day).
- **Christian media and blogs**: offer free guest articles to Nigerian Christian blogs and magazines, for example "How churches in Nigeria can receive tithes online safely" or "Church website checklist for 2026".
- **Denominations and fellowships**: offer free or discounted setup to dioceses, districts, PFN or CAN chapters, and campus fellowships in exchange for a "Website by Ministry OS" mention or link.
- **Customer sites**:
  - Every church site already credits the platform in its footer; keep that credit on the plans where it applies.
  - Ask happy churches to mention you on their social pages.
- **Payment partners**: apply to be listed in the Paystack and Flutterwave partner and integration directories.
- **PR**: send a short press release to Nigerian tech media (Techpoint Africa, TechCabal, Benjamin Dada, Techeconomy) when you reach milestones, such as your first 100 churches.

## 4. Content (2 articles a month)

Publish on the main site's blog (Posts on the main site; they are in the sitemap automatically). Each article should link to the matching solution page. Starter list:

1. How much does a church website cost in Nigeria? (2026 guide)
2. How to receive tithes and offerings online in Nigeria
3. Paystack vs Flutterwave for churches
4. Church website checklist: 12 pages every church needs
5. How to live stream your church service on your website with only a phone
6. Bank transfer giving: how to confirm and record it properly
7. How to grow ministry partnership online
8. WhatsApp for churches: following up first-time visitors
9. Free church app vs. building a Play Store app
10. Church data protection in Nigeria (NDPA 2023) — what churches should know
11. Case study: [church name] — how they doubled online giving (use real customers and get their permission)
12. City guides, only with real local content: "Church websites in Lagos", "Church websites in Abuja"

Write for pastors and church administrators in simple English. Answer the question in the first paragraph, and add a short FAQ at the end.

## 5. Reviews and trust

- Ask each church, after their first successful month, for a Google review and a G2 or Capterra review.
- Put 3–6 real testimonials, with church names and permission, on the landing page.
- Never buy reviews or links; Google penalises it.

## 6. Social and WhatsApp

- Share each article and new church launch on Facebook, Instagram, LinkedIn, X and WhatsApp Status and Channels. The sharing previews are already set up.
- A short YouTube walkthrough, "Build a church website in 2 minutes", can rank on YouTube and in Google video results. Link it from the landing page.

## 7. What to measure (monthly)

| KPI | Where | Target to begin with |
|---|---|---|
| Pages indexed | Search Console → Pages | All 7 platform pages |
| Impressions and clicks for "church website", "church app", "online giving" queries | Search Console → Performance, country Nigeria | Growing month on month |
| Average position for the six main terms | Search Console | Top 20 in 3 months, top 10 in 6–12 months |
| Sign-ups from organic search | GA4 → Traffic acquisition | Growing month on month |
| New referring domains | Search Console → Links | 2–4 a month |

Expect first movement in 4–8 weeks and solid positions for competitive terms in 6–12 months, depending on how many quality links you earn.
