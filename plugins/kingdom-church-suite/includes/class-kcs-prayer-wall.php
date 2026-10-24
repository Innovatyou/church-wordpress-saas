<?php
/**
 * The community prayer wall page: live stats, topics, search, "I prayed", encouragements,
 * praise reports, a "Start praying" focus mode and the request form, all on one page.
 *
 * The first page of requests is drawn on the server (works without JavaScript and for search
 * engines); prayer-wall.js then loads more as people scroll and keeps the counts live.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prayer wall.
 */
class KCS_Prayer_Wall {

	const PER_PAGE = 12;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_assets' ), 20 );
	}

	/**
	 * Load the wall's files on pages that show it.
	 */
	public static function maybe_assets() {
		$id = get_queried_object_id();
		if ( is_singular() && ( has_shortcode( (string) get_post_field( 'post_content', $id ), 'kcs_prayer_wall' ) || ( kcs_get_option( 'page_prayer' ) && (int) kcs_get_option( 'page_prayer' ) === (int) $id ) ) ) {
			self::assets();
		}
	}

	/**
	 * Enqueue the wall's CSS and JS.
	 */
	public static function assets() {
		wp_enqueue_style( 'kcs-prayer-wall', KCS_URL . 'assets/css/prayer-wall.css', array( 'kcs-public' ), KCS_VERSION );
		wp_enqueue_script( 'kcs-prayer-wall', KCS_URL . 'assets/js/prayer-wall.js', array( 'kcs-public' ), KCS_VERSION, true );
	}

	/**
	 * Words the script needs.
	 *
	 * @return array
	 */
	private static function strings() {
		return array(
			'prayed'      => __( 'Prayed', 'kingdom-church-suite' ),
			'iPrayed'     => __( 'I prayed', 'kingdom-church-suite' ),
			'readMore'    => __( 'Read more', 'kingdom-church-suite' ),
			'loadMore'    => __( 'Load more prayer requests', 'kingdom-church-suite' ),
			'readLess'    => __( 'Show less', 'kingdom-church-suite' ),
			'praise'      => __( 'Praise report', 'kingdom-church-suite' ),
			'answered'    => __( 'Answered', 'kingdom-church-suite' ),
			'empty'       => __( 'No prayer requests here yet. Be the first to share one.', 'kingdom-church-suite' ),
			'noMatch'     => __( 'Nothing matches. Try another topic or word.', 'kingdom-church-suite' ),
			/* translators: %d: count */
			'newOne'      => __( '%d new prayer request — show', 'kingdom-church-suite' ),
			/* translators: %d: count */
			'newMany'     => __( '%d new prayer requests — show', 'kingdom-church-suite' ),
			'copied'      => __( 'Link copied', 'kingdom-church-suite' ),
			'noEnc'       => __( 'No encouragements yet. Be the first to lift them up.', 'kingdom-church-suite' ),
			'sending'     => __( 'Sending…', 'kingdom-church-suite' ),
			'error'       => __( 'Something went wrong. Please try again.', 'kingdom-church-suite' ),
			/* translators: 1: current, 2: total */
			'of'          => __( '%1$d of %2$d', 'kingdom-church-suite' ),
			'doneTitle'   => __( 'Thank you for praying', 'kingdom-church-suite' ),
			/* translators: %d: count */
			'doneText'    => __( 'You prayed for %d people today. Heaven heard every word.', 'kingdom-church-suite' ),
			'breathe'     => __( 'Take a moment and pray for this person…', 'kingdom-church-suite' ),
			'confirmOff'  => __( 'Take your request off the prayer wall?', 'kingdom-church-suite' ),
			'pending'     => __( 'Thank you! Your request was received. It will appear on the wall once our prayer team has seen it.', 'kingdom-church-suite' ),
			'privateDone' => __( 'Thank you! Your request went privately to our prayer team. We are standing with you.', 'kingdom-church-suite' ),
			'liveDone'    => __( 'Thank you! Your request is now on the prayer wall. Our church family is praying with you.', 'kingdom-church-suite' ),
		);
	}

	/**
	 * One request card.
	 *
	 * @param array $i Public item.
	 * @return string
	 */
	public static function card( $i ) {
		ob_start();
		?>
		<article class="kpw-card<?php echo $i['answered'] ? ' is-answered' : ''; ?>" id="prayer-<?php echo (int) $i['id']; ?>" data-id="<?php echo (int) $i['id']; ?>" style="--h:<?php echo (int) $i['hue']; ?>">
			<header class="kpw-card__head">
				<span class="kpw-avatar" aria-hidden="true"><?php echo esc_html( $i['initial'] ); ?></span>
				<span class="kpw-who"><strong><?php echo esc_html( $i['name'] ); ?></strong><small><?php echo esc_html( trim( ( $i['city'] ? $i['city'] . ' · ' : '' ) . $i['ago'] ) ); ?></small></span>
				<?php if ( $i['category_label'] ) : ?><span class="kpw-chip"><?php echo esc_html( $i['category_label'] ); ?></span><?php endif; ?>
			</header>
			<?php if ( $i['answered'] ) : ?><p class="kpw-ribbon">🙌 <?php esc_html_e( 'Prayer answered', 'kingdom-church-suite' ); ?></p><?php endif; ?>
			<p class="kpw-text"><?php echo nl2br( esc_html( $i['request'] ) ); ?></p>
			<?php if ( $i['praise'] ) : ?>
				<div class="kpw-praise"><strong><?php esc_html_e( 'Praise report', 'kingdom-church-suite' ); ?></strong><p><?php echo nl2br( esc_html( $i['praise'] ) ); ?></p></div>
			<?php endif; ?>
			<footer class="kpw-card__foot">
				<button type="button" class="kpw-pray" data-kpw-pray aria-pressed="false"><span class="kpw-pray__icon" aria-hidden="true">🙏</span><span class="kpw-pray__label"><?php esc_html_e( 'I prayed', 'kingdom-church-suite' ); ?></span><b data-kpw-count><?php echo (int) $i['prayers']; ?></b></button>
				<button type="button" class="kpw-icon-btn" data-kpw-enc aria-label="<?php esc_attr_e( 'Encourage', 'kingdom-church-suite' ); ?>"><span aria-hidden="true">💬</span><b data-kpw-enc-count><?php echo (int) $i['encouragements']; ?></b></button>
				<button type="button" class="kpw-icon-btn" data-kpw-share aria-label="<?php esc_attr_e( 'Share', 'kingdom-church-suite' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M16 6l-4-4-4 4M12 2v14"/></svg></button>
			</footer>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * The private panel for the person who asked (?prayer_token=).
	 *
	 * @param object $row Request.
	 * @return string
	 */
	private static function owner_panel( $row ) {
		$encs = KCS_Prayer::encouragements( (int) $row->id );
		ob_start();
		?>
		<section class="kpw-mine" data-kpw-mine data-token="<?php echo esc_attr( $row->token ); ?>">
			<p class="kpw-eyebrow"><?php esc_html_e( 'Your prayer request', 'kingdom-church-suite' ); ?></p>
			<blockquote><?php echo nl2br( esc_html( $row->request ) ); ?></blockquote>
			<div class="kpw-mine__stats">
				<span><strong><?php echo (int) $row->prayer_count; ?></strong> <?php echo esc_html( _n( 'person prayed', 'people prayed', (int) $row->prayer_count, 'kingdom-church-suite' ) ); ?></span>
				<span><strong><?php echo (int) $row->encourage_count; ?></strong> <?php echo esc_html( _n( 'encouragement', 'encouragements', (int) $row->encourage_count, 'kingdom-church-suite' ) ); ?></span>
				<span class="kcs-badge kcs-badge--<?php echo esc_attr( $row->is_private ? 'private' : $row->status ); ?>"><?php echo esc_html( $row->is_private ? __( 'Private', 'kingdom-church-suite' ) : ( KCS_Prayer::statuses()[ $row->status ] ?? $row->status ) ); ?></span>
			</div>
			<?php if ( $row->is_private ) : ?>
				<p class="kcs-muted"><?php esc_html_e( 'This request is private: only our prayer team sees it.', 'kingdom-church-suite' ); ?></p>
			<?php elseif ( 'new' === $row->status ) : ?>
				<p class="kcs-muted"><?php esc_html_e( 'Our prayer team will add it to the wall shortly.', 'kingdom-church-suite' ); ?></p>
			<?php endif; ?>
			<?php if ( $encs ) : ?>
				<ul class="kpw-encs">
					<?php foreach ( $encs as $e ) : ?><li><p><?php echo nl2br( esc_html( $e['message'] ) ); ?></p><small>— <?php echo esc_html( $e['name'] . ' · ' . $e['ago'] ); ?></small></li><?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( 'archived' !== $row->status ) : ?>
				<form class="kcs-form" data-kpw-mine-form>
					<label><?php esc_html_e( 'Has God answered? Share your praise report (optional)', 'kingdom-church-suite' ); ?><textarea name="praise" rows="3" maxlength="2000"><?php echo esc_textarea( (string) $row->praise ); ?></textarea></label>
					<div class="kpw-mine__btns">
						<button class="kcs-btn" name="do" value="answered">🙌 <?php esc_html_e( 'My prayer was answered', 'kingdom-church-suite' ); ?></button>
						<?php if ( 'answered' === $row->status ) : ?><button class="kcs-btn kcs-btn--ghost" name="do" value="praise"><?php esc_html_e( 'Save praise report', 'kingdom-church-suite' ); ?></button><?php endif; ?>
						<?php if ( ! $row->is_private ) : ?><button class="kcs-btn kcs-btn--ghost" name="do" value="remove"><?php esc_html_e( 'Take it off the wall', 'kingdom-church-suite' ); ?></button><?php endif; ?>
					</div>
					<div class="kcs-form-msg" aria-live="polite"></div>
				</form>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * [kcs_prayer_wall].
	 *
	 * @param array $atts title, intro.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts  = shortcode_atts(
			array(
				'title' => __( 'Prayer Wall', 'kingdom-church-suite' ),
				'intro' => __( 'You are not alone. Share what is on your heart and let our church family stand with you in prayer — or pray for someone else right now.', 'kingdom-church-suite' ),
			),
			$atts,
			'kcs_prayer_wall'
		);
		self::assets();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filter   = isset( $_GET['wall'] ) ? sanitize_key( $_GET['wall'] ) : 'all';
		$category = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
		$search   = isset( $_GET['pray_for'] ) ? sanitize_text_field( wp_unslash( $_GET['pray_for'] ) ) : '';
		$page     = kcs_current_page( 'wpg' );
		$token    = isset( $_GET['prayer_token'] ) ? sanitize_text_field( wp_unslash( $_GET['prayer_token'] ) ) : '';
		// phpcs:enable
		$filters = array(
			'all'    => __( 'All requests', 'kingdom-church-suite' ),
			'needs'  => __( 'Needs prayer', 'kingdom-church-suite' ),
			'most'   => __( 'Most prayed', 'kingdom-church-suite' ),
			'praise' => __( 'Praise reports', 'kingdom-church-suite' ),
		);
		$filter  = isset( $filters[ $filter ] ) ? $filter : 'all';
		$q       = KCS_Prayer::query( array( 'filter' => $filter, 'category' => $category, 'search' => $search, 'page' => $page, 'per_page' => self::PER_PAGE ) );
		$items   = array_map( array( 'KCS_Prayer', 'public_item' ), $q['rows'] );
		$stats   = KCS_Prayer::stats();
		$cats    = KCS_Prayer::categories();
		$phone   = trim( (string) kcs_get_option( 'prayer_phone', '' ) );
		$mine    = $token ? KCS_Prayer::by_token( $token ) : null;
		$config  = array(
			'perPage'  => self::PER_PAGE,
			'pages'    => $q['pages'],
			'page'     => $page,
			'filter'   => $filter,
			'category' => $category,
			'search'   => $search,
			'url'      => get_permalink(),
			'i18n'     => self::strings(),
		);
		ob_start();
		?>
		<section class="kpw" data-kcs-wall data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<header class="kpw-hero">
				<span class="kpw-orb kpw-orb--1" aria-hidden="true"></span><span class="kpw-orb kpw-orb--2" aria-hidden="true"></span><span class="kpw-orb kpw-orb--3" aria-hidden="true"></span>
				<div class="kpw-hero__inner">
					<p class="kpw-eyebrow"><?php esc_html_e( 'Community', 'kingdom-church-suite' ); ?></p>
					<h2 class="kpw-title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<p class="kpw-intro"><?php echo esc_html( $atts['intro'] ); ?></p>
					<div class="kpw-hero__actions">
						<button type="button" class="kpw-btn kpw-btn--light" data-kpw-open><span aria-hidden="true">✍️</span> <?php esc_html_e( 'Share a prayer request', 'kingdom-church-suite' ); ?></button>
						<button type="button" class="kpw-btn kpw-btn--glass" data-kpw-focus><span aria-hidden="true">🕊️</span> <?php esc_html_e( 'Start praying', 'kingdom-church-suite' ); ?></button>
					</div>
					<dl class="kpw-stats">
						<div><dt><?php esc_html_e( 'Requests', 'kingdom-church-suite' ); ?></dt><dd data-kpw-stat="requests"><?php echo esc_html( number_format_i18n( $stats['requests'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Prayers prayed', 'kingdom-church-suite' ); ?></dt><dd data-kpw-stat="prayers"><?php echo esc_html( number_format_i18n( $stats['prayers'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Answered', 'kingdom-church-suite' ); ?></dt><dd data-kpw-stat="answered"><?php echo esc_html( number_format_i18n( $stats['answered'] ) ); ?></dd></div>
					</dl>
					<?php if ( $phone ) : ?>
						<p class="kpw-phone"><?php esc_html_e( 'Need someone to pray with you right now?', 'kingdom-church-suite' ); ?> <a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ); ?>">📞 <?php echo esc_html( $phone ); ?></a></p>
					<?php endif; ?>
				</div>
			</header>

			<?php
			if ( $mine ) {
				echo self::owner_panel( $mine ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $token ) {
				echo '<div class="kcs-alert kcs-alert--error">' . esc_html__( 'This prayer link is no longer valid.', 'kingdom-church-suite' ) . '</div>';
			}
			?>

			<form class="kpw-toolbar" method="get" data-kpw-toolbar>
				<div class="kpw-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Show', 'kingdom-church-suite' ); ?>">
					<?php foreach ( $filters as $k => $l ) : ?>
						<button type="submit" name="wall" value="<?php echo esc_attr( $k ); ?>" role="tab" class="kpw-tab" aria-selected="<?php echo $k === $filter ? 'true' : 'false'; ?>" data-kpw-filter="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></button>
					<?php endforeach; ?>
				</div>
				<label class="kpw-search"><span class="screen-reader-text"><?php esc_html_e( 'Search prayer requests', 'kingdom-church-suite' ); ?></span><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><input type="search" name="pray_for" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search requests or a city…', 'kingdom-church-suite' ); ?>" data-kpw-search></label>
				<?php if ( $cats ) : ?>
					<div class="kpw-topics" data-kpw-topics>
						<button type="submit" name="topic" value="" class="kpw-topic" aria-pressed="<?php echo '' === $category ? 'true' : 'false'; ?>" data-kpw-topic=""><?php esc_html_e( 'All topics', 'kingdom-church-suite' ); ?></button>
						<?php foreach ( $cats as $k => $l ) : ?>
							<button type="submit" name="topic" value="<?php echo esc_attr( $k ); ?>" class="kpw-topic" aria-pressed="<?php echo $k === $category ? 'true' : 'false'; ?>" data-kpw-topic="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</form>

			<button type="button" class="kpw-new" data-kpw-new hidden></button>

			<div class="kpw-grid" data-kpw-grid aria-live="polite">
				<?php
				foreach ( $items as $i ) {
					echo self::card( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<p class="kpw-empty" data-kpw-empty<?php echo $items ? ' hidden' : ''; ?>><?php echo esc_html( $search || $category ? self::strings()['noMatch'] : self::strings()['empty'] ); ?></p>
			<div class="kpw-more" data-kpw-more>
				<noscript><?php echo kcs_pager( $q['total'], self::PER_PAGE, $page, get_permalink(), array( 'wall' => 'all' === $filter ? '' : $filter, 'topic' => $category, 'pray_for' => $search ), array( 'param' => 'wpg', 'per_param' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></noscript>
			</div>

			<button type="button" class="kpw-fab" data-kpw-open aria-label="<?php esc_attr_e( 'Share a prayer request', 'kingdom-church-suite' ); ?>"><span aria-hidden="true">＋</span><span class="kpw-fab__label"><?php esc_html_e( 'Prayer request', 'kingdom-church-suite' ); ?></span></button>

			<dialog class="kpw-sheet" data-kpw-form-dialog aria-labelledby="kpw-form-title">
				<form class="kpw-sheet__body kcs-form" data-kpw-form>
					<button type="button" class="kpw-close" data-kpw-close aria-label="<?php esc_attr_e( 'Close', 'kingdom-church-suite' ); ?>">×</button>
					<div data-kpw-form-fields>
						<h3 id="kpw-form-title"><?php esc_html_e( 'Share a prayer request', 'kingdom-church-suite' ); ?></h3>
						<p class="kcs-muted"><?php esc_html_e( '“Cast all your anxiety on Him because He cares for you.” — 1 Peter 5:7', 'kingdom-church-suite' ); ?></p>
						<label><?php esc_html_e( 'What can we pray with you about?', 'kingdom-church-suite' ); ?><textarea name="request" rows="5" maxlength="2000" required data-kpw-count-src></textarea><small class="kpw-counter" data-kpw-counter>0 / 2000</small></label>
						<?php if ( $cats ) : ?>
							<fieldset class="kpw-pick"><legend><?php esc_html_e( 'Topic', 'kingdom-church-suite' ); ?></legend>
								<?php foreach ( $cats as $k => $l ) : ?><label><input type="radio" name="category" value="<?php echo esc_attr( $k ); ?>"><span><?php echo esc_html( $l ); ?></span></label><?php endforeach; ?>
							</fieldset>
						<?php endif; ?>
						<fieldset class="kpw-seg"><legend><?php esc_html_e( 'Who should see it?', 'kingdom-church-suite' ); ?></legend>
							<label><input type="radio" name="is_private" value="0" checked><span><strong><?php esc_html_e( 'The prayer wall', 'kingdom-church-suite' ); ?></strong><small><?php esc_html_e( 'Our church family prays with you', 'kingdom-church-suite' ); ?></small></span></label>
							<label><input type="radio" name="is_private" value="1"><span><strong><?php esc_html_e( 'Only the prayer team', 'kingdom-church-suite' ); ?></strong><small><?php esc_html_e( 'Kept private', 'kingdom-church-suite' ); ?></small></span></label>
						</fieldset>
						<div class="kcs-grid-2">
							<label><?php esc_html_e( 'First name', 'kingdom-church-suite' ); ?><input name="name" autocomplete="given-name" maxlength="100"></label>
							<label><?php esc_html_e( 'City (optional)', 'kingdom-church-suite' ); ?><input name="city" autocomplete="address-level2" maxlength="100"></label>
						</div>
						<label class="kpw-switch"><input type="checkbox" name="anonymous" value="1"><span></span><?php esc_html_e( 'Show my request as “Anonymous”', 'kingdom-church-suite' ); ?></label>
						<div class="kcs-grid-2">
							<label><?php esc_html_e( 'Email (optional, never shown)', 'kingdom-church-suite' ); ?><input type="email" name="email" autocomplete="email"></label>
							<label><?php esc_html_e( 'Phone (optional, never shown)', 'kingdom-church-suite' ); ?><input type="tel" name="phone" autocomplete="tel"></label>
						</div>
						<label class="kpw-switch"><input type="checkbox" name="notify" value="1" checked><span></span><?php esc_html_e( 'Email me when people pray or encourage me', 'kingdom-church-suite' ); ?></label>
						<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
						<button type="submit" class="kpw-btn kpw-btn--primary kpw-btn--block"><?php esc_html_e( 'Send my prayer request', 'kingdom-church-suite' ); ?></button>
						<div class="kcs-form-msg" aria-live="polite"></div>
					</div>
					<div class="kpw-done" data-kpw-form-done hidden>
						<span class="kpw-done__check" aria-hidden="true"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24" fill="none"/><path fill="none" d="M15 27l7 7 15-16"/></svg></span>
						<h3><?php esc_html_e( 'We are praying with you', 'kingdom-church-suite' ); ?></h3>
						<p data-kpw-done-text></p>
						<button type="button" class="kpw-btn kpw-btn--primary" data-kpw-close><?php esc_html_e( 'Back to the wall', 'kingdom-church-suite' ); ?></button>
					</div>
				</form>
			</dialog>

			<dialog class="kpw-sheet" data-kpw-enc-dialog aria-labelledby="kpw-enc-title">
				<div class="kpw-sheet__body">
					<button type="button" class="kpw-close" data-kpw-close aria-label="<?php esc_attr_e( 'Close', 'kingdom-church-suite' ); ?>">×</button>
					<h3 id="kpw-enc-title"><?php esc_html_e( 'Encourage', 'kingdom-church-suite' ); ?> <span data-kpw-enc-name></span></h3>
					<blockquote class="kpw-enc-quote" data-kpw-enc-quote></blockquote>
					<ul class="kpw-encs" data-kpw-enc-list></ul>
					<form class="kcs-form" data-kpw-enc-form>
						<div class="kpw-quick" aria-label="<?php esc_attr_e( 'Quick words', 'kingdom-church-suite' ); ?>">
							<?php foreach ( array( __( 'Praying for you 🙏', 'kingdom-church-suite' ), __( 'God is with you. Stay strong 💪', 'kingdom-church-suite' ), __( 'He is faithful — your breakthrough is coming ✨', 'kingdom-church-suite' ), __( 'Standing in agreement with you ❤️', 'kingdom-church-suite' ) ) as $w ) : ?>
								<button type="button" class="kpw-topic" data-kpw-quick><?php echo esc_html( $w ); ?></button>
							<?php endforeach; ?>
						</div>
						<label><?php esc_html_e( 'Your words of encouragement', 'kingdom-church-suite' ); ?><textarea name="message" rows="3" maxlength="500" required></textarea></label>
						<label><?php esc_html_e( 'Your first name (optional)', 'kingdom-church-suite' ); ?><input name="name" maxlength="100" autocomplete="given-name"></label>
						<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
						<button class="kpw-btn kpw-btn--primary kpw-btn--block"><?php esc_html_e( 'Send encouragement', 'kingdom-church-suite' ); ?></button>
						<div class="kcs-form-msg" aria-live="polite"></div>
					</form>
				</div>
			</dialog>

			<dialog class="kpw-focus" data-kpw-focus-dialog aria-label="<?php esc_attr_e( 'Pray for people one by one', 'kingdom-church-suite' ); ?>">
				<div class="kpw-focus__bg" aria-hidden="true"><span></span><span></span><span></span></div>
				<button type="button" class="kpw-close kpw-close--light" data-kpw-close aria-label="<?php esc_attr_e( 'Close', 'kingdom-church-suite' ); ?>">×</button>
				<p class="kpw-focus__progress" data-kpw-focus-progress></p>
				<div class="kpw-deck" data-kpw-deck></div>
				<p class="kpw-focus__hint" data-kpw-focus-hint><?php esc_html_e( 'Swipe right when you have prayed, left to skip.', 'kingdom-church-suite' ); ?></p>
				<div class="kpw-focus__btns" data-kpw-focus-btns>
					<button type="button" class="kpw-round" data-kpw-skip aria-label="<?php esc_attr_e( 'Skip', 'kingdom-church-suite' ); ?>">↷</button>
					<button type="button" class="kpw-btn kpw-btn--light kpw-btn--big" data-kpw-focus-pray><span aria-hidden="true">🙏</span> <?php esc_html_e( 'I prayed for this', 'kingdom-church-suite' ); ?></button>
				</div>
			</dialog>
		</section>
		<?php
		return ob_get_clean();
	}
}
