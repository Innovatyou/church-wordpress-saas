<?php
/**
 * Landing & pricing page.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

$img   = content_url( 'themes/kingdom/assets/img/' );
$local = strtoupper( (string) ks_get( 'local_currency' ) );
$trial = (int) ks_get( 'trial_days' );
$view  = $local ? 'local' : 'international';
?>
<section class="ks-hero">
	<div class="ks-wrap ks-hero__in">
		<div>
			<span class="ks-eyebrow"><?php esc_html_e( 'Church app & church website builder', 'kingdom-saas' ); ?></span>
			<h1><?php esc_html_e( 'Your church website and church app, with online giving — ready in 2 minutes', 'kingdom-saas' ); ?></h1>
			<p class="ks-lead"><?php echo esc_html( ks_get( 'tagline' ) ); ?></p>
			<div class="ks-cta">
				<a class="ks-btn ks-btn--lg" href="<?php echo esc_url( ks_platform_url( 'signup/' ) ); ?>"><?php /* translators: %d: days */ printf( esc_html__( 'Start your %d-day free trial', 'kingdom-saas' ), (int) $trial ); ?></a>
				<a class="ks-btn ks-btn--ghost ks-btn--lg" href="#pricing"><?php esc_html_e( 'See pricing', 'kingdom-saas' ); ?></a>
			</div>
			<p class="ks-small"><?php esc_html_e( 'No card needed · Live in 2 minutes · Cancel any time', 'kingdom-saas' ); ?></p>
		</div>
		<div class="ks-hero__art" aria-hidden="true">
			<div class="ks-device ks-device--back"><img src="<?php echo esc_url( $img . 'choir.svg' ); ?>" alt=""></div>
			<div class="ks-device"><div class="ks-device__bar"><i></i><i></i><i></i><span>gracechapel.org</span></div><img src="<?php echo esc_url( $img . 'worship.svg' ); ?>" alt=""><div class="ks-device__cap"><strong>Grace Chapel</strong><span><?php esc_html_e( 'Sunday Service · 9:00 AM', 'kingdom-saas' ); ?></span></div></div>
			<div class="ks-float ks-float--a">₦250,000 <small><?php esc_html_e( 'tithes this week', 'kingdom-saas' ); ?></small></div>
			<div class="ks-float ks-float--b">+18 <small><?php esc_html_e( 'new partners', 'kingdom-saas' ); ?></small></div>
		</div>
	</div>
</section>

<section class="ks-band" id="features">
	<div class="ks-wrap">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Everything included', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Built for the way churches really work', 'kingdom-saas' ); ?></h2></header>
		<div class="ks-grid ks-grid--3">
			<?php
			$features = array(
				array( '⛪', __( 'Beautiful church website', 'kingdom-saas' ), __( '25+ page designs, sermons, events, live streaming, prayer requests, testimonies and a Visit Us page — all editable without code.', 'kingdom-saas' ) ),
				array( '♥', __( 'Partnership portal', 'kingdom-saas' ), __( 'Partners sign up, pay and get their own dashboard by plan. Unpaid partners are locked out until they renew.', 'kingdom-saas' ) ),
				array( '₦', __( 'Tithe, offering & giving', 'kingdom-saas' ), __( 'Paystack, Flutterwave, Stripe, PayPal and bank transfer — in US dollars or your local currency.', 'kingdom-saas' ) ),
				array( '◎', __( 'Church Admin dashboard', 'kingdom-saas' ), __( 'Manage partners, payments, prayers, visits and notifications from a simple dashboard — no WordPress skills needed.', 'kingdom-saas' ) ),
				array( '✆', __( 'WhatsApp built in', 'kingdom-saas' ), __( 'Visitors chat with your team, and partners reach their pastors on WhatsApp straight from their dashboard.', 'kingdom-saas' ) ),
				array( '⌘', __( 'Your own domain', 'kingdom-saas' ), sprintf( /* translators: %s: example church address */ __( 'Start on %s and connect yourchurch.org whenever you are ready, with free https.', 'kingdom-saas' ), ks_address( 'yourchurch' ) ) ),
			);
			foreach ( $features as $f ) :
				?>
				<article class="ks-card ks-feature"><span class="ks-feature__ic" aria-hidden="true"><?php echo esc_html( $f[0] ); ?></span><h3><?php echo esc_html( $f[1] ); ?></h3><p><?php echo esc_html( $f[2] ); ?></p></article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band ks-band--alt">
	<div class="ks-wrap">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'How it works', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Live in three steps', 'kingdom-saas' ); ?></h2></header>
		<ol class="ks-steps">
			<li><b>1</b><h3><?php esc_html_e( 'Sign up', 'kingdom-saas' ); ?></h3><p><?php esc_html_e( 'Enter your church name and pick your web address.', 'kingdom-saas' ); ?></p></li>
			<li><b>2</b><h3><?php esc_html_e( 'Make it yours', 'kingdom-saas' ); ?></h3><p><?php esc_html_e( 'Add your logo, colours, photos, service times and partnership plans.', 'kingdom-saas' ); ?></p></li>
			<li><b>3</b><h3><?php esc_html_e( 'Share & receive', 'kingdom-saas' ); ?></h3><p><?php esc_html_e( 'Share your link and start receiving partners, tithes and offerings online.', 'kingdom-saas' ); ?></p></li>
		</ol>
	</div>
</section>

<section class="ks-band" id="nigeria">
	<div class="ks-wrap">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Made for Nigerian churches', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Built for how churches in Nigeria give, gather and grow', 'kingdom-saas' ); ?></h2></header>
		<div class="ks-grid ks-grid--3">
			<?php
			$ng = array(
				array( '₦', __( 'Giving in naira', 'kingdom-saas' ), __( 'Tithes, offerings, seeds and partnership in naira with Paystack or Flutterwave — card, transfer and USSD — and in dollars for members abroad.', 'kingdom-saas' ) ),
				array( '🏦', __( 'Bank transfers, confirmed', 'kingdom-saas' ), __( 'Show your church account numbers. Members say when they have paid and your treasurer confirms it in one tap.', 'kingdom-saas' ) ),
				array( '✆', __( 'WhatsApp everywhere', 'kingdom-saas' ), __( 'First-timers, partners and prayer requests reach your team on WhatsApp — the way your members already talk.', 'kingdom-saas' ) ),
				array( '📶', __( 'Light on data', 'kingdom-saas' ), __( 'Fast pages that work on any smartphone and on slow networks, with a church app that still opens when the network is poor.', 'kingdom-saas' ) ),
				array( '⛪', __( 'Every denomination', 'kingdom-saas' ), __( 'Pentecostal, Anglican, Catholic, Baptist, Methodist and independent churches, parishes and ministries.', 'kingdom-saas' ) ),
				array( '📍', __( 'Across Nigeria', 'kingdom-saas' ), __( 'Lagos, Abuja, Port Harcourt, Ibadan, Enugu, Benin City, Kano, Uyo, Warri — and Nigerian churches in the diaspora.', 'kingdom-saas' ) ),
			);
			foreach ( $ng as $f ) :
				?>
				<article class="ks-card ks-feature"><span class="ks-feature__ic" aria-hidden="true"><?php echo esc_html( $f[0] ); ?></span><h3><?php echo esc_html( $f[1] ); ?></h3><p><?php echo esc_html( $f[2] ); ?></p></article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band ks-band--alt" id="solutions">
	<div class="ks-wrap">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Explore', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Everything your church needs online', 'kingdom-saas' ); ?></h2></header>
		<div class="ks-grid ks-grid--3 ks-links">
			<?php foreach ( KS_SEO::solutions() as $slug => $s ) : ?>
				<a class="ks-card ks-link-card" href="<?php echo esc_url( ks_platform_url( $slug . '/' ) ); ?>"><strong><?php echo esc_html( $s['eyebrow'] ); ?></strong><span><?php echo esc_html( $s['desc'] ); ?></span><em aria-hidden="true">→</em></a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band" id="pricing">
	<div class="ks-wrap ks-pricing" data-ks-prices data-r="<?php echo esc_attr( $view ); ?>" data-i="month">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Pricing', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Simple plans that grow with your church', 'kingdom-saas' ); ?></h2><p class="ks-lead"><?php /* translators: %d: days */ printf( esc_html__( 'Every plan starts with a %d-day free trial.', 'kingdom-saas' ), (int) $trial ); ?></p></header>
		<div class="ks-toggles">
			<div class="ks-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Currency', 'kingdom-saas' ); ?>">
				<label><input type="radio" name="ks_region_view" value="international" <?php checked( $view, 'international' ); ?>><span>🌍 <?php esc_html_e( 'International', 'kingdom-saas' ); ?> <b>USD</b></span></label>
				<?php if ( $local ) : ?><label><input type="radio" name="ks_region_view" value="local" <?php checked( $view, 'local' ); ?>><span>📍 <?php echo esc_html( ks_get( 'local_label' ) ); ?> <b><?php echo esc_html( $local ); ?></b></span></label><?php endif; ?>
			</div>
			<div class="ks-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Billing period', 'kingdom-saas' ); ?>">
				<label><input type="radio" name="ks_interval_view" value="month" checked><span><?php esc_html_e( 'Monthly', 'kingdom-saas' ); ?></span></label>
				<label><input type="radio" name="ks_interval_view" value="year"><span><?php esc_html_e( 'Yearly', 'kingdom-saas' ); ?> <b><?php esc_html_e( '2 months free', 'kingdom-saas' ); ?></b></span></label>
			</div>
		</div>
		<div class="ks-grid ks-grid--3 ks-price-grid">
			<?php foreach ( KS_Plans::all() as $id => $p ) : ?>
				<article class="ks-card ks-tier<?php echo $p['featured'] ? ' is-featured' : ''; ?>">
					<?php if ( $p['featured'] ) : ?><span class="ks-ribbon"><?php esc_html_e( 'Most popular', 'kingdom-saas' ); ?></span><?php endif; ?>
					<h3><?php echo esc_html( $p['name'] ); ?></h3>
					<p class="ks-muted"><?php echo esc_html( $p['desc'] ); ?></p>
					<div class="ks-price">
						<?php foreach ( array( 'international', 'local' ) as $r ) : ?>
							<?php foreach ( array( 'month', 'year' ) as $iv ) : ?>
								<?php $pr = KS_Plans::price( $id, $iv, $r ); ?>
								<span class="ks-p ks-p--<?php echo esc_attr( $r . '-' . $iv ); ?>"><?php echo esc_html( ks_money( $pr['amount'], $pr['currency'] ) ); ?><small>/<?php echo 'year' === $iv ? esc_html__( 'year', 'kingdom-saas' ) : esc_html__( 'month', 'kingdom-saas' ); ?></small></span>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</div>
					<ul class="ks-ticks"><?php foreach ( array_filter( array_map( 'trim', explode( "\n", $p['highlights'] ) ) ) as $h ) : ?><li><?php echo esc_html( $h ); ?></li><?php endforeach; ?></ul>
					<a class="ks-btn ks-btn--block<?php echo $p['featured'] ? '' : ' ks-btn--ghost'; ?>" data-plan-link href="<?php echo esc_url( add_query_arg( 'plan', $id, ks_platform_url( 'signup/' ) ) ); ?>"><?php esc_html_e( 'Start free trial', 'kingdom-saas' ); ?></a>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band ks-band--alt" id="faq">
	<div class="ks-wrap ks-narrow">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Questions', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Frequently asked', 'kingdom-saas' ); ?></h2></header>
		<div class="ks-faq">
			<?php foreach ( KS_SEO::landing_faqs() as $q ) : ?>
				<details><summary><?php echo esc_html( $q[0] ); ?></summary><p><?php echo esc_html( $q[1] ); ?></p></details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band ks-final">
	<div class="ks-wrap ks-narrow">
		<h2><?php esc_html_e( 'Ready to bring your church online?', 'kingdom-saas' ); ?></h2>
		<a class="ks-btn ks-btn--lg ks-btn--light" href="<?php echo esc_url( ks_platform_url( 'signup/' ) ); ?>"><?php esc_html_e( 'Start free trial', 'kingdom-saas' ); ?></a>
	</div>
</section>
