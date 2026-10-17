<?php
/**
 * Platform owner dashboard (/platform/), super admins only.
 *
 * @package KingdomSaaS
 * @var array|null $flash Flash message.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$tabs   = array(
	'overview' => array( __( 'Overview', 'kingdom-saas' ), '◎' ),
	'churches' => array( __( 'Churches', 'kingdom-saas' ), '⛪' ),
	'new'      => array( __( 'Add a church', 'kingdom-saas' ), '＋' ),
	'invoices' => array( __( 'Payments', 'kingdom-saas' ), '₦' ),
	'domains'  => array( __( 'Domains', 'kingdom-saas' ), '⌘' ),
	'plans'    => array( __( 'Plans & pricing', 'kingdom-saas' ), '◈' ),
	'settings' => array( __( 'Settings', 'kingdom-saas' ), '⚙' ),
);
$tab    = isset( $_GET['tab'], $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
// phpcs:enable
$plans  = KS_Plans::all();
$f      = function ( $do, $extra = array() ) use ( $tab ) {
	$h = '<input type="hidden" name="ks_do" value="' . esc_attr( $do ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">' . wp_nonce_field( 'ks_platform', '_ks', true, false );
	foreach ( $extra as $k => $v ) {
		$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	return $h;
};
$date   = function ( $gmt ) {
	return $gmt ? date_i18n( 'j M Y', strtotime( $gmt . ' UTC' ) ) : '—';
};
$sums   = function ( $map ) {
	if ( ! $map ) {
		return ks_money( 0, 'USD' );
	}
	$out = array();
	foreach ( $map as $cur => $amt ) {
		$out[] = ks_money( $amt, $cur );
	}
	return implode( ' + ', $out );
};
$old    = $flash[2] ?? array();
$badges = array( 'invoices' => KS_Bank::count_waiting() );
?>
<div class="ks-app">
	<aside class="ks-side">
		<a class="ks-logo" href="<?php echo esc_url( ks_platform_url() ); ?>"><span class="ks-logo__mark" aria-hidden="true">✚</span><?php echo esc_html( ks_get( 'platform_name' ) ); ?></a>
		<nav aria-label="<?php esc_attr_e( 'Platform', 'kingdom-saas' ); ?>">
			<?php foreach ( $tabs as $k => $t ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $k, ks_platform_url( 'platform/' ) ) ); ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><span aria-hidden="true"><?php echo esc_html( $t[1] ); ?></span><?php echo esc_html( $t[0] ); ?><?php if ( ! empty( $badges[ $k ] ) ) : ?><em class="ks-count"><?php echo (int) $badges[ $k ]; ?></em><?php endif; ?></a>
			<?php endforeach; ?>
		</nav>
		<a class="ks-side__out" href="<?php echo esc_url( wp_logout_url( ks_platform_url() ) ); ?>"><?php esc_html_e( 'Log out', 'kingdom-saas' ); ?></a>
	</aside>
	<section class="ks-main">
		<header class="ks-main__head">
			<h1><?php echo esc_html( $tabs[ $tab ][0] ); ?></h1>
			<?php if ( ks_test_mode() ) : ?><span class="ks-test-tag"><?php esc_html_e( 'Test mode', 'kingdom-saas' ); ?></span><?php endif; ?>
		</header>
		<?php if ( $flash ) : ?><div class="ks-alert ks-alert--<?php echo esc_attr( $flash[1] ); ?>" role="status"><?php echo esc_html( $flash[0] ); ?></div><?php endif; ?>

<?php if ( 'overview' === $tab ) : ?>
		<?php $st = KS_Platform::stats(); ?>
		<?php if ( $badges['invoices'] ) : ?>
			<div class="ks-alert ks-alert--info"><a href="<?php echo esc_url( add_query_arg( 'tab', 'invoices', ks_platform_url( 'platform/' ) ) ); ?>"><?php /* translators: %d: count */ echo esc_html( sprintf( _n( '%d bank transfer is waiting for you to confirm.', '%d bank transfers are waiting for you to confirm.', $badges['invoices'], 'kingdom-saas' ), $badges['invoices'] ) ); ?> <?php esc_html_e( 'Review now →', 'kingdom-saas' ); ?></a></div>
		<?php endif; ?>
		<div class="ks-kpis">
			<div class="ks-kpi"><span><?php esc_html_e( 'Churches', 'kingdom-saas' ); ?></span><strong><?php echo (int) $st['total']; ?></strong><small><?php echo (int) $st['by_status']['active']; ?> <?php esc_html_e( 'paying', 'kingdom-saas' ); ?> · <?php echo (int) $st['by_status']['trialing']; ?> <?php esc_html_e( 'on trial', 'kingdom-saas' ); ?></small></div>
			<div class="ks-kpi"><span><?php esc_html_e( 'Monthly recurring revenue', 'kingdom-saas' ); ?></span><strong><?php echo esc_html( $sums( $st['mrr'] ) ); ?></strong><small><?php esc_html_e( 'from active plans', 'kingdom-saas' ); ?></small></div>
			<div class="ks-kpi"><span><?php esc_html_e( 'Received (30 days)', 'kingdom-saas' ); ?></span><strong><?php echo esc_html( $sums( $st['revenue_30'] ) ); ?></strong><small><?php esc_html_e( 'paid invoices', 'kingdom-saas' ); ?></small></div>
			<div class="ks-kpi ks-kpi--warn"><span><?php esc_html_e( 'Need attention', 'kingdom-saas' ); ?></span><strong><?php echo (int) ( $st['by_status']['past_due'] + $st['by_status']['suspended'] ); ?></strong><small><?php echo (int) $st['by_status']['past_due']; ?> <?php esc_html_e( 'payment due', 'kingdom-saas' ); ?> · <?php echo (int) $st['by_status']['suspended']; ?> <?php esc_html_e( 'suspended', 'kingdom-saas' ); ?></small></div>
		</div>
		<div class="ks-cols">
			<div class="ks-card">
				<h2><?php esc_html_e( 'Newest churches', 'kingdom-saas' ); ?></h2>
				<ul class="ks-list">
					<?php foreach ( array_slice( KS_Tenants::all(), 0, 6 ) as $s ) : ?>
						<li><span><strong><?php echo esc_html( $s->church_name ); ?></strong><small><?php echo esc_html( wp_parse_url( KS_Tenants::url( $s->blog_id ), PHP_URL_HOST ) . ' · ' . ( $plans[ $s->plan ]['name'] ?? $s->plan ) ); ?></small></span><?php echo wp_kses_post( ks_badge( $s->status ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="ks-card">
				<h2><?php esc_html_e( 'Billing checks', 'kingdom-saas' ); ?></h2>
				<p class="ks-muted"><?php esc_html_e( 'Trials, renewals, reminders, suspensions and pending domains are checked automatically twice a day. You can also run the check now.', 'kingdom-saas' ); ?></p>
				<form method="post"><?php echo $f( 'run_lifecycle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn"><?php esc_html_e( 'Run checks now', 'kingdom-saas' ); ?></button></form>
			</div>
		</div>

<?php elseif ( 'churches' === $tab ) : ?>
		<form class="ks-filters" method="get" action="<?php echo esc_url( ks_platform_url( 'platform/' ) ); ?>">
			<input type="hidden" name="tab" value="churches">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Church, email or address…', 'kingdom-saas' ); ?>" aria-label="<?php esc_attr_e( 'Search churches', 'kingdom-saas' ); ?>">
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-saas' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-saas' ); ?></option><?php foreach ( KS_Tenants::statuses() as $s ) : ?><option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filter, $s ); ?>><?php echo esc_html( wp_strip_all_tags( ks_badge( $s ) ) ); ?></option><?php endforeach; ?></select>
			<button class="ks-btn"><?php esc_html_e( 'Filter', 'kingdom-saas' ); ?></button>
		</form>
		<div class="ks-stack">
			<?php foreach ( KS_Tenants::all( array( 'search' => $search, 'status' => $filter ) ) as $s ) : ?>
				<?php $due = KS_Tenants::due_at( $s ); ?>
				<article class="ks-card ks-church">
					<div class="ks-church__main">
						<h3><?php echo esc_html( $s->church_name ); ?> <?php echo wp_kses_post( ks_badge( $s->status ) ); ?></h3>
						<p class="ks-muted"><a href="<?php echo esc_url( KS_Tenants::url( $s->blog_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( KS_Tenants::url( $s->blog_id ), PHP_URL_HOST ) ); ?></a> · <?php echo esc_html( $s->owner_email ); ?></p>
						<dl class="ks-facts">
							<div><dt><?php esc_html_e( 'Plan', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( ( $plans[ $s->plan ]['name'] ?? $s->plan ) . ' · ' . ( 'year' === $s->billing_interval ? __( 'yearly', 'kingdom-saas' ) : __( 'monthly', 'kingdom-saas' ) ) . ' · ' . ks_region_currency( $s->region ) ); ?></dd></div>
							<div><dt><?php echo 'trialing' === $s->status ? esc_html__( 'Trial ends', 'kingdom-saas' ) : esc_html__( 'Paid until', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( $date( $due ) ); ?><?php echo $s->cancel_at_period_end ? ' · ' . esc_html__( 'cancelling', 'kingdom-saas' ) : ''; ?></dd></div>
							<div><dt><?php esc_html_e( 'Partners', 'kingdom-saas' ); ?></dt><dd><?php echo (int) KS_Tenants::partner_count( $s->blog_id ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Joined', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( $date( $s->created_at ) ); ?></dd></div>
						</dl>
					</div>
					<div class="ks-church__actions">
						<a class="ks-btn ks-btn--sm" href="<?php echo esc_url( KS_Login::link( get_current_user_id(), $s->blog_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Church Admin', 'kingdom-saas' ); ?></a>
						<form method="post" class="ks-inline"><?php echo $f( 'plan', array( 'blog_id' => $s->blog_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><select name="plan" aria-label="<?php esc_attr_e( 'Plan', 'kingdom-saas' ); ?>"><?php foreach ( $plans as $id => $p ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s->plan, $id ); ?>><?php echo esc_html( $p['name'] ); ?></option><?php endforeach; ?></select><button class="ks-btn ks-btn--ghost ks-btn--sm"><?php esc_html_e( 'Set plan', 'kingdom-saas' ); ?></button></form>
						<form method="post" class="ks-inline"><?php echo $f( 'extend', array( 'blog_id' => $s->blog_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="number" name="days" value="14" min="1" max="366" aria-label="<?php esc_attr_e( 'Days', 'kingdom-saas' ); ?>"><button class="ks-btn ks-btn--ghost ks-btn--sm"><?php esc_html_e( 'Extend days', 'kingdom-saas' ); ?></button></form>
						<?php if ( KS_Tenants::is_live( $s ) ) : ?>
							<form method="post" class="ks-inline"><?php echo $f( 'suspend', array( 'blog_id' => $s->blog_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn ks-btn--danger ks-btn--sm" onclick="return confirm('<?php echo esc_js( __( 'Suspend this church? Visitors will see a paused page.', 'kingdom-saas' ) ); ?>')"><?php esc_html_e( 'Suspend', 'kingdom-saas' ); ?></button></form>
						<?php else : ?>
							<form method="post" class="ks-inline"><?php echo $f( 'reactivate', array( 'blog_id' => $s->blog_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn ks-btn--sm"><?php esc_html_e( 'Reactivate', 'kingdom-saas' ); ?></button></form>
						<?php endif; ?>
						<details class="ks-danger-zone"><summary><?php esc_html_e( 'Delete', 'kingdom-saas' ); ?></summary>
							<form method="post" class="ks-inline"><?php echo $f( 'delete', array( 'blog_id' => $s->blog_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input name="confirm" placeholder="<?php esc_attr_e( 'Type DELETE', 'kingdom-saas' ); ?>" aria-label="<?php esc_attr_e( 'Type DELETE to confirm', 'kingdom-saas' ); ?>"><button class="ks-btn ks-btn--danger ks-btn--sm"><?php esc_html_e( 'Delete site', 'kingdom-saas' ); ?></button></form>
						</details>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

<?php elseif ( 'new' === $tab ) : ?>
		<form method="post" class="ks-card ks-form ks-narrow-form">
			<?php echo $f( 'create' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="ks-muted"><?php esc_html_e( 'Create a church for a customer you signed up yourself (for example after a sales call). They get an email with a link to set their password.', 'kingdom-saas' ); ?></p>
			<div class="ks-grid ks-grid--2">
				<label><?php esc_html_e( 'Church name', 'kingdom-saas' ); ?><input name="church_name" required value="<?php echo esc_attr( $old['church_name'] ?? '' ); ?>"></label>
				<label><?php esc_html_e( 'Website address', 'kingdom-saas' ); ?><span class="ks-domain"><?php if ( ks_subfolder_mode() ) : ?><span><?php echo esc_html( ks_get( 'base_domain' ) ); ?>/</span><?php endif; ?><input name="subdomain" required value="<?php echo esc_attr( $old['subdomain'] ?? '' ); ?>" data-ks-subdomain><?php if ( ! ks_subfolder_mode() ) : ?><span>.<?php echo esc_html( ks_get( 'base_domain' ) ); ?></span><?php endif; ?></span></label>
				<label><?php esc_html_e( 'Contact name', 'kingdom-saas' ); ?><input name="name" value="<?php echo esc_attr( $old['name'] ?? '' ); ?>"></label>
				<label><?php esc_html_e( 'Contact email', 'kingdom-saas' ); ?><input type="email" name="email" required value="<?php echo esc_attr( $old['email'] ?? '' ); ?>"></label>
				<label><?php esc_html_e( 'Plan', 'kingdom-saas' ); ?><select name="plan"><?php foreach ( $plans as $id => $p ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $p['name'] ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Billing', 'kingdom-saas' ); ?><select name="interval"><option value="month"><?php esc_html_e( 'Monthly', 'kingdom-saas' ); ?></option><option value="year"><?php esc_html_e( 'Yearly', 'kingdom-saas' ); ?></option></select></label>
				<label><?php esc_html_e( 'Currency', 'kingdom-saas' ); ?><select name="region"><option value="international">USD</option><?php if ( ks_get( 'local_currency' ) ) : ?><option value="local"><?php echo esc_html( strtoupper( ks_get( 'local_currency' ) ) ); ?></option><?php endif; ?></select></label>
				<label><?php esc_html_e( 'Start as', 'kingdom-saas' ); ?><select name="status"><option value="trialing"><?php esc_html_e( 'Free trial', 'kingdom-saas' ); ?></option><option value="active"><?php esc_html_e( 'Active (paid offline)', 'kingdom-saas' ); ?></option></select></label>
				<label><?php esc_html_e( 'Trial days', 'kingdom-saas' ); ?><input type="number" name="trial_days" min="0" value="<?php echo esc_attr( ks_get( 'trial_days' ) ); ?>"></label>
			</div>
			<button class="ks-btn"><?php esc_html_e( 'Create church', 'kingdom-saas' ); ?></button>
		</form>

<?php elseif ( 'invoices' === $tab ) : ?>
		<?php
		global $wpdb;
		$waiting = $wpdb->get_results( 'SELECT * FROM ' . ks_table( 'invoices' ) . " WHERE status = 'review' ORDER BY id ASC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$reject  = function ( $inv ) use ( $f ) {
			return '<details class="ks-reject"><summary class="ks-btn ks-btn--ghost ks-btn--sm">' . esc_html__( 'Reject', 'kingdom-saas' ) . '</summary><form method="post" class="ks-form">' . $f( 'invoice_reject', array( 'reference' => $inv->reference ) ) . '<label>' . esc_html__( 'Reason (emailed to the church)', 'kingdom-saas' ) . '<input name="reason" maxlength="500" placeholder="' . esc_attr__( 'We could not find this transfer in our account.', 'kingdom-saas' ) . '"></label><button class="ks-btn ks-btn--danger ks-btn--sm">' . esc_html__( 'Reject transfer', 'kingdom-saas' ) . '</button></form></details>';
		};
		?>
		<div class="ks-card">
			<h2><?php esc_html_e( 'Bank transfers waiting for you', 'kingdom-saas' ); ?></h2>
			<?php if ( ! $waiting ) : ?><p class="ks-muted"><?php esc_html_e( 'Nothing to confirm. When a church says it has paid by bank transfer, it appears here and you get an email.', 'kingdom-saas' ); ?></p><?php endif; ?>
			<div class="ks-transfers">
				<?php foreach ( $waiting as $inv ) : ?>
					<?php
					$s     = KS_Tenants::get( $inv->blog_id );
					$t     = KS_Bank::meta( $inv )['transfer'] ?? array();
					$proof = KS_Bank::proof_url( $inv );
					?>
					<article class="ks-transfer">
						<header><strong><?php echo esc_html( ks_money( $inv->amount, $inv->currency ) ); ?></strong><span><?php echo esc_html( ( $plans[ $inv->plan ]['name'] ?? $inv->plan ) . ' · ' . ( 'year' === $inv->billing_interval ? __( 'yearly', 'kingdom-saas' ) : __( 'monthly', 'kingdom-saas' ) ) ); ?></span></header>
						<dl>
							<div><dt><?php esc_html_e( 'Church', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( $s ? $s->church_name : '#' . $inv->blog_id ); ?><?php if ( $s ) : ?><small><?php echo esc_html( $s->owner_email ); ?></small><?php endif; ?></dd></div>
							<div><dt><?php esc_html_e( 'Reference', 'kingdom-saas' ); ?></dt><dd><code><?php echo esc_html( $inv->reference ); ?></code></dd></div>
							<?php if ( ! empty( $t['payer'] ) ) : ?><div><dt><?php esc_html_e( 'Sent by', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( $t['payer'] . ( $t['bank'] ? ' · ' . $t['bank'] : '' ) ); ?></dd></div><?php endif; ?>
							<?php if ( ! empty( $t['date'] ) ) : ?><div><dt><?php esc_html_e( 'Date sent', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( date_i18n( 'j M Y', strtotime( $t['date'] ) ) ); ?></dd></div><?php endif; ?>
							<?php if ( ! empty( $t['note'] ) ) : ?><div><dt><?php esc_html_e( 'Note', 'kingdom-saas' ); ?></dt><dd><?php echo esc_html( $t['note'] ); ?></dd></div><?php endif; ?>
						</dl>
						<div class="ks-actions">
							<?php if ( $proof ) : ?><a class="ks-btn ks-btn--ghost ks-btn--sm" href="<?php echo esc_url( $proof ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View receipt', 'kingdom-saas' ); ?></a><?php endif; ?>
							<form method="post" class="ks-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Confirm this money is in your account? The church plan starts straight away.', 'kingdom-saas' ) ); ?>')"><?php echo $f( 'invoice_paid', array( 'reference' => $inv->reference ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn ks-btn--sm"><?php esc_html_e( 'Confirm received', 'kingdom-saas' ); ?></button></form>
							<?php echo $reject( $inv ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="ks-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Church', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Plan', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-saas' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( KS_Billing::invoices( 0, 200 ) as $inv ) : ?>
					<?php $s = KS_Tenants::get( $inv->blog_id ); ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Church', 'kingdom-saas' ); ?>"><strong><?php echo esc_html( $s ? $s->church_name : '#' . $inv->blog_id ); ?></strong><small><?php echo esc_html( $inv->reference . ' · ' . $date( $inv->created_at ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Plan', 'kingdom-saas' ); ?>"><?php echo esc_html( ( $plans[ $inv->plan ]['name'] ?? $inv->plan ) . ' · ' . $inv->billing_interval ); ?></td>
						<td data-label="<?php esc_attr_e( 'Amount', 'kingdom-saas' ); ?>"><strong><?php echo esc_html( ks_money( $inv->amount, $inv->currency ) ); ?></strong><small><?php echo esc_html( $inv->gateway ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-saas' ); ?>"><?php echo wp_kses_post( ks_badge( $inv->status ) ); ?></td>
						<td><?php if ( in_array( $inv->status, array( 'pending', 'review' ), true ) ) : ?><form method="post" class="ks-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Mark this invoice as paid? The church plan starts straight away.', 'kingdom-saas' ) ); ?>')"><?php echo $f( 'invoice_paid', array( 'reference' => $inv->reference ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn ks-btn--sm"><?php esc_html_e( 'Mark paid', 'kingdom-saas' ); ?></button></form><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

<?php elseif ( 'domains' === $tab ) : ?>
		<div class="ks-card">
			<p class="ks-muted"><?php /* translators: %s: endpoint */ printf( esc_html__( 'Point churches’ domains at this server. For automatic https on custom domains, use Caddy with on-demand TLS and set its "ask" URL to %s (see deploy/Caddyfile).', 'kingdom-saas' ), '<code>' . esc_html( get_rest_url( get_main_site_id(), 'ks/v1/tls' ) ) . '</code>' ); ?></p>
		</div>
		<div class="ks-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Domain', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Church', 'kingdom-saas' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-saas' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( KS_Domains::all() as $dm ) : ?>
					<?php $s = KS_Tenants::get( $dm->blog_id ); ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Domain', 'kingdom-saas' ); ?>"><strong><?php echo esc_html( $dm->domain ); ?></strong><small><?php echo $dm->is_primary ? esc_html__( 'Main address', 'kingdom-saas' ) : esc_html( $dm->last_error ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Church', 'kingdom-saas' ); ?>"><?php echo esc_html( $s ? $s->church_name : '#' . $dm->blog_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-saas' ); ?>"><?php echo wp_kses_post( ks_badge( $dm->status ) ); ?></td>
						<td class="ks-row-actions">
							<form method="post" class="ks-inline"><?php echo $f( 'domain_verify', array( 'id' => $dm->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn ks-btn--sm"><?php esc_html_e( 'Check DNS', 'kingdom-saas' ); ?></button></form>
							<form method="post" class="ks-inline"><?php echo $f( 'domain_remove', array( 'id' => $dm->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="ks-btn ks-btn--ghost ks-btn--sm"><?php esc_html_e( 'Remove', 'kingdom-saas' ); ?></button></form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

<?php elseif ( 'plans' === $tab ) : ?>
		<form method="post" class="ks-plan-edit">
			<?php echo $f( 'save_plans' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ks-grid ks-grid--3">
				<?php foreach ( $plans as $id => $p ) : ?>
					<fieldset class="ks-card ks-form">
						<legend><?php echo esc_html( $p['name'] ); ?> <code><?php echo esc_html( $id ); ?></code></legend>
						<label><?php esc_html_e( 'Name', 'kingdom-saas' ); ?><input name="plans[<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $p['name'] ); ?>"></label>
						<label><?php esc_html_e( 'Short description', 'kingdom-saas' ); ?><input name="plans[<?php echo esc_attr( $id ); ?>][desc]" value="<?php echo esc_attr( $p['desc'] ); ?>"></label>
						<div class="ks-grid ks-grid--2">
							<label><?php esc_html_e( 'USD / month', 'kingdom-saas' ); ?><input type="number" step="0.01" min="0" name="plans[<?php echo esc_attr( $id ); ?>][usd]" value="<?php echo esc_attr( $p['usd'] ); ?>"></label>
							<label><?php /* translators: %s: currency */ printf( esc_html__( '%s / month', 'kingdom-saas' ), esc_html( strtoupper( ks_get( 'local_currency' ) ? ks_get( 'local_currency' ) : 'Local' ) ) ); ?><input type="number" step="0.01" min="0" name="plans[<?php echo esc_attr( $id ); ?>][local]" value="<?php echo esc_attr( $p['local'] ); ?>"></label>
						</div>
						<label><?php esc_html_e( 'Max partners (0 = unlimited)', 'kingdom-saas' ); ?><input type="number" min="0" name="plans[<?php echo esc_attr( $id ); ?>][max_partners]" value="<?php echo esc_attr( $p['max_partners'] ); ?>"></label>
						<label class="ks-check"><input type="checkbox" name="plans[<?php echo esc_attr( $id ); ?>][custom_domain]" value="1" <?php checked( $p['custom_domain'] ); ?>> <?php esc_html_e( 'Custom domain', 'kingdom-saas' ); ?></label>
						<label class="ks-check"><input type="checkbox" name="plans[<?php echo esc_attr( $id ); ?>][white_label]" value="1" <?php checked( $p['white_label'] ); ?>> <?php esc_html_e( 'No “Powered by” branding', 'kingdom-saas' ); ?></label>
						<label class="ks-check"><input type="checkbox" name="plans[<?php echo esc_attr( $id ); ?>][featured]" value="1" <?php checked( $p['featured'] ); ?>> <?php esc_html_e( 'Highlight as “Most popular”', 'kingdom-saas' ); ?></label>
						<label><?php esc_html_e( 'Highlights (one per line)', 'kingdom-saas' ); ?><textarea rows="5" name="plans[<?php echo esc_attr( $id ); ?>][highlights]"><?php echo esc_textarea( $p['highlights'] ); ?></textarea></label>
					</fieldset>
				<?php endforeach; ?>
			</div>
			<p class="ks-muted"><?php esc_html_e( 'Yearly price is 10 × the monthly price (two months free).', 'kingdom-saas' ); ?></p>
			<button class="ks-btn"><?php esc_html_e( 'Save plans', 'kingdom-saas' ); ?></button>
		</form>

<?php elseif ( 'settings' === $tab ) : ?>
		<form method="post" class="ks-settings">
			<?php echo $f( 'save_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ks-grid ks-grid--2">
				<fieldset class="ks-card ks-form">
					<legend><?php esc_html_e( 'Brand', 'kingdom-saas' ); ?></legend>
					<label><?php esc_html_e( 'Platform name', 'kingdom-saas' ); ?><input name="platform_name" value="<?php echo esc_attr( ks_get( 'platform_name' ) ); ?>"></label>
					<label><?php esc_html_e( 'Tagline', 'kingdom-saas' ); ?><textarea name="tagline" rows="2"><?php echo esc_textarea( ks_get( 'tagline' ) ); ?></textarea></label>
					<label><?php esc_html_e( 'Brand colour', 'kingdom-saas' ); ?><input type="color" name="brand_color" value="<?php echo esc_attr( ks_get( 'brand_color' ) ); ?>"></label>
					<label><?php esc_html_e( 'Support email (also the sender)', 'kingdom-saas' ); ?><input type="email" name="support_email" value="<?php echo esc_attr( ks_get( 'support_email' ) ); ?>"></label>
				</fieldset>
				<fieldset class="ks-card ks-form">
					<legend><?php esc_html_e( 'Domains', 'kingdom-saas' ); ?></legend>
					<label><?php esc_html_e( 'Platform domain', 'kingdom-saas' ); ?><input name="base_domain" value="<?php echo esc_attr( ks_get( 'base_domain' ) ); ?>"></label>
					<label><?php esc_html_e( 'CNAME target for custom domains', 'kingdom-saas' ); ?><input name="cname_target" value="<?php echo esc_attr( ks_get( 'cname_target' ) ); ?>" placeholder="<?php echo esc_attr( ks_get( 'base_domain' ) ); ?>"></label>
					<label><?php esc_html_e( 'Server IP (for A records)', 'kingdom-saas' ); ?><input name="server_ip" value="<?php echo esc_attr( ks_get( 'server_ip' ) ); ?>" placeholder="203.0.113.10"></label>
					<label class="ks-check"><input type="checkbox" name="https" value="1" <?php checked( '1', ks_get( 'https' ) ); ?>> <?php esc_html_e( 'Church sites use https', 'kingdom-saas' ); ?></label>
					<label><?php esc_html_e( 'Reserved church addresses', 'kingdom-saas' ); ?><input name="reserved" value="<?php echo esc_attr( ks_get( 'reserved' ) ); ?>"></label>
				</fieldset>
				<fieldset class="ks-card ks-form">
					<legend><?php esc_html_e( 'Trials & renewals', 'kingdom-saas' ); ?></legend>
					<label><?php esc_html_e( 'Free trial days', 'kingdom-saas' ); ?><input type="number" min="0" name="trial_days" value="<?php echo esc_attr( ks_get( 'trial_days' ) ); ?>"></label>
					<label><?php esc_html_e( 'Grace days before suspension', 'kingdom-saas' ); ?><input type="number" min="0" name="grace_days" value="<?php echo esc_attr( ks_get( 'grace_days' ) ); ?>"></label>
					<label><?php esc_html_e( 'Reminder days before due date', 'kingdom-saas' ); ?><input type="number" min="0" name="reminder_days" value="<?php echo esc_attr( ks_get( 'reminder_days' ) ); ?>"></label>
					<label><?php esc_html_e( 'Local currency (empty = USD only)', 'kingdom-saas' ); ?><input name="local_currency" maxlength="3" value="<?php echo esc_attr( ks_get( 'local_currency' ) ); ?>"></label>
					<label><?php esc_html_e( 'Local label', 'kingdom-saas' ); ?><input name="local_label" value="<?php echo esc_attr( ks_get( 'local_label' ) ); ?>"></label>
				</fieldset>
				<fieldset class="ks-card ks-form">
					<legend><?php esc_html_e( 'Payments', 'kingdom-saas' ); ?></legend>
					<label><?php esc_html_e( 'Paystack secret key', 'kingdom-saas' ); ?><input type="password" name="paystack_secret" value="<?php echo esc_attr( ks_get( 'paystack_secret' ) ); ?>" autocomplete="off"></label>
					<label><?php esc_html_e( 'Stripe secret key', 'kingdom-saas' ); ?><input type="password" name="stripe_secret" value="<?php echo esc_attr( ks_get( 'stripe_secret' ) ); ?>" autocomplete="off"></label>
					<label><?php esc_html_e( 'Stripe webhook signing secret', 'kingdom-saas' ); ?><input type="password" name="stripe_webhook" value="<?php echo esc_attr( ks_get( 'stripe_webhook' ) ); ?>" autocomplete="off"></label>
					<label><?php esc_html_e( 'Cloudflare Turnstile site key (bot protection on sign-up, optional)', 'kingdom-saas' ); ?><input name="turnstile_site" value="<?php echo esc_attr( ks_get( 'turnstile_site' ) ); ?>" autocomplete="off"></label>
					<label><?php esc_html_e( 'Cloudflare Turnstile secret key', 'kingdom-saas' ); ?><input type="password" name="turnstile_secret" value="<?php echo esc_attr( ks_get( 'turnstile_secret' ) ); ?>" autocomplete="off"></label>
					<label class="ks-check"><input type="checkbox" name="test_mode" value="1" <?php checked( '1', ks_get( 'test_mode' ) ); ?>> <?php esc_html_e( 'Test mode (adds a simulated “Test payment” option)', 'kingdom-saas' ); ?></label>
					<p class="ks-small"><?php esc_html_e( 'Webhooks:', 'kingdom-saas' ); ?> <code><?php echo esc_html( get_rest_url( get_main_site_id(), 'ks/v1/webhooks/paystack' ) ); ?></code> · <code><?php echo esc_html( get_rest_url( get_main_site_id(), 'ks/v1/webhooks/stripe' ) ); ?></code></p>
				</fieldset>
				<fieldset class="ks-card ks-form ks-span-2">
					<legend><?php esc_html_e( 'Bank transfer', 'kingdom-saas' ); ?></legend>
					<p class="ks-small"><?php esc_html_e( 'Churches that choose bank transfer see these accounts and their invoice reference, then press “I have made this transfer”. You get an email and confirm it in Payments. Add up to 3 accounts; leave all empty to turn bank transfer off. An account with no currency is shown for every currency.', 'kingdom-saas' ); ?></p>
					<?php $ks_accounts = KS_Bank::accounts(); ?>
					<?php for ( $i = 0; $i < KS_Bank::MAX_ACCOUNTS; $i++ ) : ?>
						<?php $a = $ks_accounts[ $i ] ?? array_fill_keys( array( 'bank', 'name', 'number', 'currency', 'sort_code', 'iban', 'swift' ), '' ); ?>
						<div class="ks-account">
							<strong><?php /* translators: %d: number */ printf( esc_html__( 'Account %d', 'kingdom-saas' ), (int) $i + 1 ); ?></strong>
							<div class="ks-grid ks-grid--4">
								<label><?php esc_html_e( 'Bank name', 'kingdom-saas' ); ?><input name="bank_accounts[<?php echo (int) $i; ?>][bank]" value="<?php echo esc_attr( $a['bank'] ); ?>"></label>
								<label><?php esc_html_e( 'Account name', 'kingdom-saas' ); ?><input name="bank_accounts[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $a['name'] ); ?>"></label>
								<label><?php esc_html_e( 'Account number', 'kingdom-saas' ); ?><input name="bank_accounts[<?php echo (int) $i; ?>][number]" value="<?php echo esc_attr( $a['number'] ); ?>" autocomplete="off"></label>
								<label><?php esc_html_e( 'Currency', 'kingdom-saas' ); ?><select name="bank_accounts[<?php echo (int) $i; ?>][currency]"><option value=""><?php esc_html_e( 'Any', 'kingdom-saas' ); ?></option><?php foreach ( array_unique( array_filter( array( 'USD', strtoupper( (string) ks_get( 'local_currency' ) ), $a['currency'] ) ) ) as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $a['currency'], $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select></label>
								<label><?php esc_html_e( 'Sort code / routing (optional)', 'kingdom-saas' ); ?><input name="bank_accounts[<?php echo (int) $i; ?>][sort_code]" value="<?php echo esc_attr( $a['sort_code'] ); ?>"></label>
								<label><?php esc_html_e( 'IBAN (optional)', 'kingdom-saas' ); ?><input name="bank_accounts[<?php echo (int) $i; ?>][iban]" value="<?php echo esc_attr( $a['iban'] ); ?>"></label>
								<label><?php esc_html_e( 'SWIFT / BIC (optional)', 'kingdom-saas' ); ?><input name="bank_accounts[<?php echo (int) $i; ?>][swift]" value="<?php echo esc_attr( $a['swift'] ); ?>"></label>
							</div>
						</div>
					<?php endfor; ?>
					<label><?php esc_html_e( 'Extra note for churches (optional)', 'kingdom-saas' ); ?><textarea name="bank_details" rows="2" placeholder="<?php esc_attr_e( 'e.g. Transfers are confirmed within one working day.', 'kingdom-saas' ); ?>"><?php echo esc_textarea( ks_get( 'bank_details' ) ); ?></textarea></label>
				</fieldset>
			</div>
			<button class="ks-btn"><?php esc_html_e( 'Save settings', 'kingdom-saas' ); ?></button>
		</form>
<?php endif; ?>
	</section>
</div>
