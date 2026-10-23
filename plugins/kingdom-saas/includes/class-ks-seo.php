<?php
/**
 * Search engine optimisation for the platform site: meta tags, social previews, structured data,
 * sitemap, robots.txt and the solution pages that answer how churches search
 * ("church website builder Nigeria", "church app", "online giving for churches" …).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Platform SEO.
 */
class KS_SEO {

	/**
	 * Hooks (main site only).
	 */
	public static function init() {
		if ( ! is_main_site() ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_action( 'init', array( __CLASS__, 'sitemap_provider' ), 20 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_providers' ), 10, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots_meta' ) );
	}

	/**
	 * Main-site pages are demo/system pages, not the product: the landing and solution pages
	 * (platform sitemap) replace them. Blog posts stay, for articles.
	 *
	 * @param array $types Post types.
	 * @return array
	 */
	public static function sitemap_post_types( $types ) {
		unset( $types['page'] );
		return $types;
	}

	/**
	 * No author sitemaps on the platform.
	 *
	 * @param WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                     $name     Name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public static function sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Keep system pages on the platform site (thank-you, dashboards, logins …) out of search.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots_meta( $robots ) {
		$system = array( 'thank-you', 'partner-dashboard', 'partner-login', 'become-a-partner', 'church-admin', 'sample-page' );
		if ( is_page( $system ) || is_author() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Solution page addresses.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array_keys( self::solutions() );
	}

	/**
	 * Pretty URLs for the solution pages.
	 */
	public static function rewrites() {
		foreach ( self::slugs() as $slug ) {
			add_rewrite_rule( '^' . $slug . '/?$', 'index.php?ks_route=solution&ks_solution=' . $slug, 'top' );
		}
		$rules = get_option( 'rewrite_rules' );
		$first = self::slugs()[0];
		if ( is_array( $rules ) && ! isset( $rules[ '^' . $first . '/?$' ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'ks_solution';
		return $vars;
	}

	/**
	 * The solution pages (slug => content). Each targets one way churches search.
	 *
	 * @return array
	 */
	public static function solutions() {
		$name = ks_get( 'platform_name' );
		return array(
			'church-website-builder'         => array(
				'nav'     => __( 'Church website', 'kingdom-saas' ),
				'title'   => __( 'Church Website Builder in Nigeria — Ready in 2 Minutes', 'kingdom-saas' ),
				'desc'    => __( 'Create a beautiful, mobile-friendly church website in minutes. Sermons, events, live stream, online giving in naira and WhatsApp chat. Built for churches in Nigeria. Free trial.', 'kingdom-saas' ),
				'eyebrow' => __( 'Church website builder', 'kingdom-saas' ),
				'h1'      => __( 'A church website your members will love — ready in 2 minutes', 'kingdom-saas' ),
				'intro'   => __( 'No web designer, no coding and no long wait. Pick your church name and address, and your website is live with ready-made pages for services, sermons, events, giving, prayer requests and more. Change words, photos and colours from your phone.', 'kingdom-saas' ),
				'points'  => array(
					array( '⛪', __( '25+ ready-made pages', 'kingdom-saas' ), __( 'Home, About, Our Pastors, Ministries, Sermons, Events, Visit Us, Statement of Faith, Prayer, Testimonies, Give and Contact — written for churches, ready to edit.', 'kingdom-saas' ) ),
					array( '📱', __( 'Made for phones', 'kingdom-saas' ), __( 'Most visitors will find you on a phone. Every page is fast and easy to use on any smartphone, even on slow data.', 'kingdom-saas' ) ),
					array( '🎨', __( 'Your church, your look', 'kingdom-saas' ), __( 'Your logo, colours, fonts and photos. Six homepage styles, dark mode and a beautiful mobile menu.', 'kingdom-saas' ) ),
					array( '🔎', __( 'Found on Google', 'kingdom-saas' ), __( 'Search-friendly pages, your service times and address marked up for Google, and fast loading — so people nearby can find your church.', 'kingdom-saas' ) ),
					array( '▶', __( 'Live stream & sermons', 'kingdom-saas' ), __( 'Paste your YouTube channel once: your site shows the live service when you go live, and replays the rest of the time.', 'kingdom-saas' ) ),
					array( '⌘', __( 'Your own domain', 'kingdom-saas' ), __( 'Start on a free address and connect yourchurch.org or yourchurch.com.ng whenever you are ready.', 'kingdom-saas' ) ),
				),
				'faqs'    => array(
					array( __( 'How much does a church website cost in Nigeria?', 'kingdom-saas' ), sprintf( /* translators: %s: platform name */ __( 'With %s you pay a small monthly or yearly subscription in naira instead of a large one-off design fee — and you can try it free first. Hosting, security, updates and support are included.', 'kingdom-saas' ), $name ) ),
					array( __( 'Can I build my church website myself?', 'kingdom-saas' ), __( 'Yes. Everything is ready on day one. You only replace the sample words and photos with your own from the Church Admin dashboard — no technical skills needed.', 'kingdom-saas' ) ),
					array( __( 'Will my church website work on phones?', 'kingdom-saas' ), __( 'Yes. It is designed for phones first, with an app-style menu and a bottom bar for Watch, Give and Prayer.', 'kingdom-saas' ) ),
				),
			),
			'church-app'                     => array(
				'nav'     => __( 'Church app', 'kingdom-saas' ),
				'title'   => __( 'Church App for Nigerian Churches — Members Install It in One Tap', 'kingdom-saas' ),
				'desc'    => __( 'Give your church its own app with your logo: live service, sermons, giving, prayer requests and push notifications. No app store needed. Built for churches in Nigeria.', 'kingdom-saas' ),
				'eyebrow' => __( 'Church app', 'kingdom-saas' ),
				'h1'      => __( 'Your own church app — with your logo, no app store needed', 'kingdom-saas' ),
				'intro'   => __( 'Members add your church app to their home screen in one tap, on Android or iPhone. It opens like an app, works on weak networks, and lets you send notifications about services, events and announcements.', 'kingdom-saas' ),
				'points'  => array(
					array( '📲', __( 'One-tap install', 'kingdom-saas' ), __( 'An “Install our app” button appears for visitors. No Play Store or App Store approval and no developer fees.', 'kingdom-saas' ) ),
					array( '🔔', __( 'Push notifications', 'kingdom-saas' ), __( 'Remind members about Sunday service, vigils, programmes and new sermons — straight to their phones.', 'kingdom-saas' ) ),
					array( '▶', __( 'Watch live', 'kingdom-saas' ), __( 'The app shows your live stream when you are live, and replays when you are not.', 'kingdom-saas' ) ),
					array( '₦', __( 'Give in the app', 'kingdom-saas' ), __( 'Tithe, offering, thanksgiving and partnership in naira or dollars with Paystack, Flutterwave or bank transfer.', 'kingdom-saas' ) ),
					array( '🙏', __( 'Prayer requests', 'kingdom-saas' ), __( 'Members send prayer requests privately to your prayer team from their phones.', 'kingdom-saas' ) ),
					array( '📶', __( 'Works on weak networks', 'kingdom-saas' ), __( 'Pages members have opened are saved, so the app still opens when the network is poor.', 'kingdom-saas' ) ),
				),
				'faqs'    => array(
					array( __( 'Do we need to publish on the Play Store or App Store?', 'kingdom-saas' ), __( 'No. Your church app installs from your website in one tap, so there are no store fees, reviews or waiting.', 'kingdom-saas' ) ),
					array( __( 'Does the church app work on iPhone?', 'kingdom-saas' ), __( 'Yes. Android phones get an install button; iPhone users get simple “Add to Home Screen” steps.', 'kingdom-saas' ) ),
					array( __( 'Can we send notifications to members?', 'kingdom-saas' ), __( 'Yes. Members who allow notifications receive your messages instantly, from the Church Admin dashboard.', 'kingdom-saas' ) ),
				),
			),
			'online-giving-for-churches'     => array(
				'nav'     => __( 'Online giving', 'kingdom-saas' ),
				'title'   => __( 'Online Giving for Churches in Nigeria — Tithes & Offerings in Naira', 'kingdom-saas' ),
				'desc'    => __( 'Receive tithes, offerings, seeds and partnership online in naira and dollars. Paystack, Flutterwave, bank transfer with confirmation, automatic receipts. Money goes straight to your church account.', 'kingdom-saas' ),
				'eyebrow' => __( 'Online giving', 'kingdom-saas' ),
				'h1'      => __( 'Receive tithes and offerings online — in naira, straight to your church account', 'kingdom-saas' ),
				'intro'   => __( 'Make it easy for members at home, in the diaspora and on the go to give. Card, bank transfer, USSD and mobile money through Paystack or Flutterwave, or a simple bank transfer that your treasurer confirms.', 'kingdom-saas' ),
				'points'  => array(
					array( '₦', __( 'Naira and dollars', 'kingdom-saas' ), __( 'Members in Nigeria give in naira; members abroad give in dollars with Stripe or PayPal.', 'kingdom-saas' ) ),
					array( '🏦', __( 'Bank transfer, confirmed', 'kingdom-saas' ), __( 'Show your church account numbers. Givers say when they have paid (with a receipt) and your admin confirms in one tap.', 'kingdom-saas' ) ),
					array( '🧾', __( 'Automatic receipts', 'kingdom-saas' ), __( 'Every gift gets an email receipt, and your team is notified.', 'kingdom-saas' ) ),
					array( '📊', __( 'Clear records', 'kingdom-saas' ), __( 'See tithes, offerings, thanksgiving, building fund and partnership by month, and download them for your accounts.', 'kingdom-saas' ) ),
					array( '🔒', __( 'Your account, your money', 'kingdom-saas' ), __( 'You connect your own Paystack or Flutterwave account. We never hold your church’s money.', 'kingdom-saas' ) ),
					array( '✚', __( 'Tithe calculator', 'kingdom-saas' ), __( 'Members enter their income and see their tithe instantly.', 'kingdom-saas' ) ),
				),
				'faqs'    => array(
					array( __( 'How can my church receive tithes online in Nigeria?', 'kingdom-saas' ), __( 'Connect your church’s Paystack or Flutterwave account (or add your bank account for transfers) and share your giving page. Members can give by card, transfer, USSD or mobile money.', 'kingdom-saas' ) ),
					array( __( 'Who holds the money?', 'kingdom-saas' ), __( 'Your church. Payments go directly into your own Paystack, Flutterwave or bank account.', 'kingdom-saas' ) ),
					array( __( 'Can members abroad give?', 'kingdom-saas' ), __( 'Yes. They can give in US dollars by card through Stripe, PayPal or Paystack.', 'kingdom-saas' ) ),
				),
			),
			'church-management-software'     => array(
				'nav'     => __( 'Church management', 'kingdom-saas' ),
				'title'   => __( 'Church Management Software for Nigerian Churches', 'kingdom-saas' ),
				'desc'    => __( 'Manage partners, giving, prayer requests, first-time visitors, testimonies and announcements from one simple dashboard that works on your phone. Built for churches in Nigeria.', 'kingdom-saas' ),
				'eyebrow' => __( 'Church management software', 'kingdom-saas' ),
				'h1'      => __( 'Run your church from one simple dashboard — on your phone', 'kingdom-saas' ),
				'intro'   => __( 'Church Admin brings giving, partners, prayer requests, first-timers, testimonies and messages together, so your pastors, admin and treasurer can see what is happening and follow up quickly.', 'kingdom-saas' ),
				'points'  => array(
					array( '♥', __( 'Partners & members', 'kingdom-saas' ), __( 'Every partner’s plan, payments, due dates and contact details in one place, with WhatsApp follow-up.', 'kingdom-saas' ) ),
					array( '₦', __( 'Giving records', 'kingdom-saas' ), __( 'Tithes, offerings and partnership by purpose and month, with bank transfers waiting to be confirmed.', 'kingdom-saas' ) ),
					array( '🙏', __( 'Prayer requests', 'kingdom-saas' ), __( 'Private requests go to your prayer team; mark them as prayed for and follow up.', 'kingdom-saas' ) ),
					array( '⌂', __( 'First-time visitors', 'kingdom-saas' ), __( '“Visit Us” bookings arrive with the visitor’s phone number so your welcome team can reach out.', 'kingdom-saas' ) ),
					array( '✉', __( 'Notify & newsletter', 'kingdom-saas' ), __( 'Email partners by plan or status, send newsletters and push notifications.', 'kingdom-saas' ) ),
					array( '👥', __( 'Staff access', 'kingdom-saas' ), __( 'Give church staff their own login without access to settings or money confirmation.', 'kingdom-saas' ) ),
				),
				'faqs'    => array(
					array( __( 'What is the best church management software in Nigeria?', 'kingdom-saas' ), sprintf( /* translators: %s: platform name */ __( 'Choose software built for how Nigerian churches work: naira giving, bank transfer confirmation, WhatsApp follow-up and phone-friendly screens. %s includes these with your church website — try it free.', 'kingdom-saas' ), $name ) ),
					array( __( 'Can several people manage the church dashboard?', 'kingdom-saas' ), __( 'Yes. Add staff with their own logins; administrators decide who can confirm payments and change settings.', 'kingdom-saas' ) ),
					array( __( 'Does it work on phones?', 'kingdom-saas' ), __( 'Yes. Church Admin is designed for phones first.', 'kingdom-saas' ) ),
				),
			),
			'church-partnership-management'  => array(
				'nav'     => __( 'Partnership', 'kingdom-saas' ),
				'title'   => __( 'Church Partnership Management — Plans, Dues & Partner Portal', 'kingdom-saas' ),
				'desc'    => __( 'Run your ministry partnership online: partnership plans in naira and dollars, automatic reminders, partner dashboards and WhatsApp to pastors. Built for ministries in Nigeria.', 'kingdom-saas' ),
				'eyebrow' => __( 'Partnership management', 'kingdom-saas' ),
				'h1'      => __( 'Grow your ministry partnership — plans, reminders and a partner portal', 'kingdom-saas' ),
				'intro'   => __( 'Partners choose a plan, give monthly or yearly, and get their own dashboard with updates and resources. Reminders go out before each due date, and you always know who is up to date.', 'kingdom-saas' ),
				'points'  => array(
					array( '◈', __( 'Partnership plans', 'kingdom-saas' ), __( 'Seed, Builder, Covenant or your own names — with prices in naira and dollars, or any amount.', 'kingdom-saas' ) ),
					array( '⏰', __( 'Automatic reminders', 'kingdom-saas' ), __( 'Partners are reminded by email before their seed is due.', 'kingdom-saas' ) ),
					array( '👤', __( 'Partner dashboard', 'kingdom-saas' ), __( 'Each partner sees their giving history, receipts, announcements and resources for their plan.', 'kingdom-saas' ) ),
					array( '✆', __( 'Talk to pastors', 'kingdom-saas' ), __( 'Partners reach their pastors on WhatsApp from their dashboard.', 'kingdom-saas' ) ),
					array( '🔒', __( 'Benefits by plan', 'kingdom-saas' ), __( 'Show content only to partners who are up to date.', 'kingdom-saas' ) ),
					array( '📣', __( 'Updates to partners', 'kingdom-saas' ), __( 'Send announcements and events to all partners or a single plan.', 'kingdom-saas' ) ),
				),
				'faqs'    => array(
					array( __( 'How do we manage ministry partners online?', 'kingdom-saas' ), __( 'Create your partnership plans, share your partnership page, and partners sign up and give online. Their dashboard and reminders run automatically.', 'kingdom-saas' ) ),
					array( __( 'Can partners abroad join?', 'kingdom-saas' ), __( 'Yes. International partners give in US dollars; local partners in naira.', 'kingdom-saas' ) ),
					array( __( 'Can partners pay by bank transfer?', 'kingdom-saas' ), __( 'Yes. They tell you when they have paid and your admin confirms it, which renews their partnership.', 'kingdom-saas' ) ),
				),
			),
			'church-live-streaming'          => array(
				'nav'     => __( 'Live streaming', 'kingdom-saas' ),
				'title'   => __( 'Church Live Streaming Website — Show Your YouTube Service Live', 'kingdom-saas' ),
				'desc'    => __( 'Stream your church service on your own website: it goes live automatically with YouTube, shows a countdown to the next service and replays sermons. Built for churches in Nigeria.', 'kingdom-saas' ),
				'eyebrow' => __( 'Church live streaming', 'kingdom-saas' ),
				'h1'      => __( 'Stream your services live on your church website', 'kingdom-saas' ),
				'intro'   => __( 'Stream to YouTube as you already do. Your website and app show the service live automatically, count down to the next service, and play your latest sermon in between — with giving and prayer requests right beside the video.', 'kingdom-saas' ),
				'points'  => array(
					array( '🔴', __( 'Goes live by itself', 'kingdom-saas' ), __( 'Add your YouTube channel once. When you go live, your website shows it — no copying links each Sunday.', 'kingdom-saas' ) ),
					array( '⏳', __( 'Next-service countdown', 'kingdom-saas' ), __( 'Between services, visitors see when you are next live.', 'kingdom-saas' ) ),
					array( '💬', __( 'Live chat', 'kingdom-saas' ), __( 'Show the YouTube live chat beside the service.', 'kingdom-saas' ) ),
					array( '₦', __( 'Give while watching', 'kingdom-saas' ), __( 'Online worshippers can give tithes and offerings without leaving the page.', 'kingdom-saas' ) ),
					array( '🎧', __( 'Sermon replays & audio', 'kingdom-saas' ), __( 'Your latest messages as video and audio for members who missed the service.', 'kingdom-saas' ) ),
					array( '📲', __( 'In the church app', 'kingdom-saas' ), __( 'Members watch from your church app and get a notification when you go live.', 'kingdom-saas' ) ),
				),
				'faqs'    => array(
					array( __( 'How do I put my church live stream on my website?', 'kingdom-saas' ), __( 'Paste your YouTube channel link in Church Admin. Your website then shows the live service automatically whenever you go live.', 'kingdom-saas' ) ),
					array( __( 'Do we need special streaming equipment?', 'kingdom-saas' ), __( 'No. If you can stream to YouTube — even from a phone — your website can show it.', 'kingdom-saas' ) ),
					array( __( 'Can people give during the live service?', 'kingdom-saas' ), __( 'Yes. Giving buttons sit beside the video.', 'kingdom-saas' ) ),
				),
			),
		);
	}

	/**
	 * FAQs on the landing page (also marked up for Google).
	 *
	 * @return array[] array( question, answer ).
	 */
	public static function landing_faqs() {
		$name = ks_get( 'platform_name' );
		return array(
			array( __( 'Do I need technical skills?', 'kingdom-saas' ), __( 'No. Your site is ready with pages, colours and sample content. You change text, photos and settings from the Church Admin dashboard — even from your phone.', 'kingdom-saas' ) ),
			array( __( 'How much does it cost in Nigeria?', 'kingdom-saas' ), __( 'Plans are priced in naira (and in dollars for churches abroad), monthly or yearly, and every plan starts with a free trial. Hosting, security, updates and support are included.', 'kingdom-saas' ) ),
			array( __( 'Who receives the tithes and partnership payments?', 'kingdom-saas' ), __( 'Your church does. You connect your own Paystack, Flutterwave, Stripe or PayPal account — or your bank account for transfers — so money goes straight to your church.', 'kingdom-saas' ) ),
			array( __( 'Can members give by bank transfer?', 'kingdom-saas' ), __( 'Yes. Add your church accounts; givers tell you when they have paid (with a receipt) and your admin confirms it in one tap.', 'kingdom-saas' ) ),
			array( __( 'Does my church get an app?', 'kingdom-saas' ), __( 'Yes. Members install your church app from your website in one tap — with your logo, push notifications, live service and giving. No app store needed.', 'kingdom-saas' ) ),
			array( __( 'Can I use my own domain?', 'kingdom-saas' ), __( 'Yes, on Growth and Ministry. Connect yourchurch.org or yourchurch.com.ng from Church Admin → Custom domain.', 'kingdom-saas' ) ),
			array( __( 'Is it suitable for any denomination?', 'kingdom-saas' ), sprintf( /* translators: %s: platform name */ __( 'Yes. %s works for Pentecostal, Anglican, Catholic, Baptist, Methodist and independent churches and ministries of every size — from new parishes to large ministries with many partners.', 'kingdom-saas' ), $name ) ),
			array( __( 'What happens after the free trial?', 'kingdom-saas' ), __( 'Choose a plan and pay by card, bank transfer or USSD. If you do not, your site stays online for a short grace period and is then paused — your content is kept safe.', 'kingdom-saas' ) ),
		);
	}

	/**
	 * Meta tags and structured data for a platform page.
	 *
	 * @param string $route    Route.
	 * @param array  $vars     Template variables.
	 * @param string $fallback Fallback title.
	 * @return string HTML for the <head>.
	 */
	public static function head( $route, $vars, $fallback ) {
		$name   = ks_get( 'platform_name' );
		$home   = ks_platform_url();
		$index  = in_array( $route, array( 'landing', 'solution' ), true );
		$title  = $fallback;
		$desc   = (string) ks_get( 'seo_description' );
		$url    = $home;
		$faqs   = array();
		$crumbs = array();
		if ( 'landing' === $route ) {
			$title = ks_get( 'seo_title' ) ? ks_get( 'seo_title' ) : sprintf( /* translators: %s: platform name */ __( '%s — Church Website, App & Online Giving for Nigerian Churches', 'kingdom-saas' ), $name );
			$desc  = $desc ? $desc : __( 'Create your church website in 2 minutes: online giving in naira (Paystack, Flutterwave, bank transfer), partnership portal, church app, live streaming and a simple admin dashboard. Free trial.', 'kingdom-saas' );
			$faqs  = self::landing_faqs();
		} elseif ( 'solution' === $route && ! empty( $vars['solution'] ) ) {
			$s      = $vars['solution'];
			$title  = $s['title'] . ' | ' . $name;
			$desc   = $s['desc'];
			$url    = ks_platform_url( $vars['slug'] . '/' );
			$faqs   = $s['faqs'];
			$crumbs = array( array( $name, $home ), array( $s['nav'], $url ) );
		} elseif ( 'signup' === $route ) {
			$url = ks_platform_url( 'signup/' );
		}
		$image = ks_get( 'seo_image' ) ? ks_get( 'seo_image' ) : content_url( 'themes/kingdom/screenshot.png' );
		$out   = '<title>' . esc_html( $title ) . "</title>\n";
		$out  .= '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
		$out  .= '<meta name="robots" content="' . ( $index ? 'index, follow, max-image-preview:large' : 'noindex, follow' ) . '">' . "\n";
		if ( $index ) {
			$out .= '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			$out .= '<link rel="alternate" hreflang="en-ng" href="' . esc_url( $url ) . '">' . "\n";
			$out .= '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '">' . "\n";
		}
		$out .= '<meta name="geo.region" content="NG"><meta name="geo.placename" content="Nigeria">' . "\n";
		$out .= '<meta property="og:type" content="website"><meta property="og:locale" content="en_NG"><meta property="og:site_name" content="' . esc_attr( $name ) . '">' . "\n";
		$out .= '<meta property="og:title" content="' . esc_attr( $title ) . '"><meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '"><meta property="og:url" content="' . esc_url( $url ) . '"><meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		$out .= '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . esc_attr( $title ) . '"><meta name="twitter:description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '"><meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
		if ( ks_get( 'seo_google' ) ) {
			$out .= '<meta name="google-site-verification" content="' . esc_attr( ks_get( 'seo_google' ) ) . '">' . "\n";
		}
		if ( ks_get( 'seo_bing' ) ) {
			$out .= '<meta name="msvalidate.01" content="' . esc_attr( ks_get( 'seo_bing' ) ) . '">' . "\n";
		}
		if ( $index ) {
			$out .= '<script type="application/ld+json">' . wp_json_encode( self::schema( $route, $url, $title, $desc, $faqs, $crumbs ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
		}
		$ga = preg_replace( '/[^A-Z0-9-]/', '', strtoupper( (string) ks_get( 'seo_ga4' ) ) );
		if ( $ga ) {
			$out .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga ) . '"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $ga ) . ');</script>' . "\n";
		}
		return $out;
	}

	/**
	 * Structured data (schema.org JSON-LD graph).
	 *
	 * @param string $route  Route.
	 * @param string $url    Page URL.
	 * @param string $title  Title.
	 * @param string $desc   Description.
	 * @param array  $faqs   FAQs.
	 * @param array  $crumbs Breadcrumbs.
	 * @return array
	 */
	private static function schema( $route, $url, $title, $desc, $faqs, $crumbs ) {
		$name  = ks_get( 'platform_name' );
		$home  = ks_platform_url();
		$org   = array(
			'@type'        => 'Organization',
			'@id'          => $home . '#org',
			'name'         => $name,
			'url'          => $home,
			'logo'         => ks_get( 'seo_image' ) ? ks_get( 'seo_image' ) : content_url( 'themes/kingdom/screenshot.png' ),
			'email'        => ks_get( 'support_email' ),
			'areaServed'   => array( '@type' => 'Country', 'name' => 'Nigeria' ),
		);
		if ( ks_get( 'seo_phone' ) ) {
			$org['telephone'] = ks_get( 'seo_phone' );
		}
		$same = array_values(
			array_filter(
				array_map( 'trim', explode( "\n", (string) ks_get( 'seo_profiles' ) ) ),
				function ( $u ) {
					return (bool) preg_match( '#^https?://[^\s/]+\.[^\s]+$#i', $u );
				}
			)
		);
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		$offers = array();
		$local  = strtoupper( (string) ks_get( 'local_currency' ) );
		foreach ( KS_Plans::all() as $id => $p ) {
			foreach ( array_filter( array( 'international', $local ? 'local' : '' ) ) as $r ) {
				$pr = KS_Plans::price( $id, 'month', $r );
				if ( $pr['amount'] > 0 ) {
					$offers[] = array(
						'@type'         => 'Offer',
						'name'          => $p['name'],
						'price'         => (string) $pr['amount'],
						'priceCurrency' => $pr['currency'],
						'url'           => add_query_arg( 'plan', $id, ks_platform_url( 'signup/' ) ),
					);
				}
			}
		}
		$graph = array(
			$org,
			array(
				'@type'     => 'WebSite',
				'@id'       => $home . '#site',
				'url'       => $home,
				'name'      => $name,
				'publisher' => array( '@id' => $home . '#org' ),
				'inLanguage' => 'en-NG',
			),
			array(
				'@type'               => 'SoftwareApplication',
				'@id'                 => $home . '#app',
				'name'                => $name,
				'applicationCategory' => 'BusinessApplication',
				'applicationSubCategory' => 'Church management software',
				'operatingSystem'     => 'Web, Android, iOS',
				'description'         => wp_strip_all_tags( $desc ),
				'url'                 => $home,
				'publisher'           => array( '@id' => $home . '#org' ),
				'offers'              => $offers,
				'audience'            => array( '@type' => 'Audience', 'audienceType' => 'Churches and ministries', 'geographicArea' => array( '@type' => 'Country', 'name' => 'Nigeria' ) ),
			),
			array(
				'@type'       => 'WebPage',
				'@id'         => $url . '#page',
				'url'         => $url,
				'name'        => $title,
				'description' => wp_strip_all_tags( $desc ),
				'isPartOf'    => array( '@id' => $home . '#site' ),
				'about'       => array( '@id' => $home . '#app' ),
				'inLanguage'  => 'en-NG',
			),
		);
		if ( $faqs ) {
			$graph[] = array(
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'mainEntity' => array_map(
					function ( $f ) {
						return array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) );
					},
					$faqs
				),
			);
		}
		if ( $crumbs ) {
			$items = array();
			foreach ( $crumbs as $i => $c ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] );
			}
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
		}
		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	/**
	 * robots.txt on the platform: keep private areas out of search, point to the sitemap.
	 *
	 * @param string $output Output.
	 * @param bool   $public Site is public.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$rows = '';
		foreach ( array( 'platform/', 'login/', 'billing/', 'wp-admin/' ) as $p ) {
			if ( false === strpos( $output, 'Disallow: ' . $path . $p ) ) {
				$rows .= 'Disallow: ' . $path . $p . "\n";
			}
		}
		$output = preg_replace( '/(User-agent: \*\n)/', '$1' . $rows, $output, 1 );
		$map    = home_url( '/wp-sitemap.xml' );
		if ( false === strpos( $output, $map ) ) {
			$output .= "\nSitemap: " . $map . "\n";
		}
		return $output;
	}

	/**
	 * Add the landing and solution pages to WordPress's sitemap.
	 */
	public static function sitemap_provider() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once KS_PATH . 'includes/class-ks-sitemap.php';
		wp_register_sitemap_provider( 'platform', new KS_Sitemap() );
	}
}
