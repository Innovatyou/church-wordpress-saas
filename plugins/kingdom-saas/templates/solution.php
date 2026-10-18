<?php
/**
 * Solution page (one per way churches search: website builder, app, giving …).
 *
 * @package KingdomSaaS
 * @var array  $solution Solution content.
 * @var string $slug     Solution address.
 */

defined( 'ABSPATH' ) || exit;

$s     = $solution;
$trial = (int) ks_get( 'trial_days' );
$name  = ks_get( 'platform_name' );
?>
<section class="ks-hero ks-hero--solution">
	<div class="ks-wrap ks-narrow">
		<nav class="ks-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'kingdom-saas' ); ?>"><a href="<?php echo esc_url( ks_platform_url() ); ?>"><?php echo esc_html( $name ); ?></a> <span aria-hidden="true">›</span> <span aria-current="page"><?php echo esc_html( $s['nav'] ); ?></span></nav>
		<span class="ks-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
		<h1><?php echo esc_html( $s['h1'] ); ?></h1>
		<p class="ks-lead"><?php echo esc_html( $s['intro'] ); ?></p>
		<div class="ks-cta">
			<a class="ks-btn ks-btn--lg" href="<?php echo esc_url( ks_platform_url( 'signup/' ) ); ?>"><?php /* translators: %d: days */ printf( esc_html__( 'Start your %d-day free trial', 'kingdom-saas' ), (int) $trial ); ?></a>
			<a class="ks-btn ks-btn--ghost ks-btn--lg" href="<?php echo esc_url( ks_platform_url( '#pricing' ) ); ?>"><?php esc_html_e( 'See pricing', 'kingdom-saas' ); ?></a>
		</div>
		<p class="ks-small"><?php esc_html_e( 'No card needed · Live in 2 minutes · Cancel any time', 'kingdom-saas' ); ?></p>
	</div>
</section>

<section class="ks-band">
	<div class="ks-wrap">
		<header class="ks-head"><h2><?php /* translators: %s: platform name */ printf( esc_html__( 'What you get with %s', 'kingdom-saas' ), esc_html( $name ) ); ?></h2></header>
		<div class="ks-grid ks-grid--3">
			<?php foreach ( $s['points'] as $p ) : ?>
				<article class="ks-card ks-feature"><span class="ks-feature__ic" aria-hidden="true"><?php echo esc_html( $p[0] ); ?></span><h3><?php echo esc_html( $p[1] ); ?></h3><p><?php echo esc_html( $p[2] ); ?></p></article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band ks-band--alt">
	<div class="ks-wrap ks-narrow">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Made for Nigerian churches', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Naira giving, bank transfers and WhatsApp — built in', 'kingdom-saas' ); ?></h2></header>
		<ul class="ks-ticks ks-ticks--wide">
			<li><?php esc_html_e( 'Tithes, offerings and partnership in naira with Paystack or Flutterwave, and in dollars for members abroad.', 'kingdom-saas' ); ?></li>
			<li><?php esc_html_e( 'Bank transfer giving that your treasurer confirms in one tap.', 'kingdom-saas' ); ?></li>
			<li><?php esc_html_e( 'WhatsApp chat for visitors, partners and prayer requests.', 'kingdom-saas' ); ?></li>
			<li><?php esc_html_e( 'Fast on any smartphone and on slow data — from Lagos, Abuja and Port Harcourt to every town.', 'kingdom-saas' ); ?></li>
			<li><?php esc_html_e( 'Hosting, security, updates and support included. No web designer needed.', 'kingdom-saas' ); ?></li>
		</ul>
	</div>
</section>

<section class="ks-band" id="faq">
	<div class="ks-wrap ks-narrow">
		<header class="ks-head"><span class="ks-eyebrow"><?php esc_html_e( 'Questions', 'kingdom-saas' ); ?></span><h2><?php esc_html_e( 'Frequently asked', 'kingdom-saas' ); ?></h2></header>
		<div class="ks-faq">
			<?php foreach ( $s['faqs'] as $q ) : ?>
				<details><summary><?php echo esc_html( $q[0] ); ?></summary><p><?php echo esc_html( $q[1] ); ?></p></details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ks-band ks-band--alt">
	<div class="ks-wrap">
		<header class="ks-head"><h2><?php esc_html_e( 'More for your church', 'kingdom-saas' ); ?></h2></header>
		<div class="ks-grid ks-grid--3 ks-links">
			<?php foreach ( KS_SEO::solutions() as $other => $o ) : ?>
				<?php if ( $other === $slug ) { continue; } ?>
				<a class="ks-card ks-link-card" href="<?php echo esc_url( ks_platform_url( $other . '/' ) ); ?>"><strong><?php echo esc_html( $o['eyebrow'] ); ?></strong><span><?php echo esc_html( $o['desc'] ); ?></span><em aria-hidden="true">→</em></a>
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
