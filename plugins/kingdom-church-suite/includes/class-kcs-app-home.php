<?php
/**
 * App home: the screen the church app opens on. A living, layered home with a greeting for the
 * time of day, live / next-service countdown, Today's Word on a flip card, quick-action tiles,
 * a live prayer pulse, sermons and events — with depth that follows the phone's tilt.
 *
 * [kcs_app] on any page; Church Admin → Church app can make the installed app open there.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * App home.
 */
class KCS_App_Home {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'kcs_app', array( __CLASS__, 'render' ) );
		add_filter( 'kcs_pwa_manifest', array( __CLASS__, 'start_url' ) );
	}

	/**
	 * Open the installed app on the App home page when the church chose that.
	 *
	 * @param array $manifest Manifest.
	 * @return array
	 */
	public static function start_url( $manifest ) {
		$id = (int) kcs_get_option( 'page_app' );
		if ( '1' === (string) kcs_get_option( 'pwa_start_app', '0' ) && $id && 'publish' === get_post_status( $id ) ) {
			$manifest['start_url'] = add_query_arg( 'utm_source', 'app', wp_make_link_relative( get_permalink( $id ) ) );
		}
		return $manifest;
	}

	/**
	 * Create the App home page.
	 *
	 * @return int
	 */
	public static function create_page() {
		$id = (int) kcs_get_option( 'page_app' );
		if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'App', 'kingdom-church-suite' ),
				'post_name'    => 'app',
				'post_content' => '[kcs_app]',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			kcs_update_option( 'page_app', (int) $id );
			if ( locate_template( 'page-templates/template-app.php' ) ) {
				update_post_meta( $id, '_wp_page_template', 'page-templates/template-app.php' );
			}
		}
		return (int) $id;
	}

	/**
	 * A page link by setting key, or ''.
	 *
	 * @param string $key Page key.
	 * @return string
	 */
	private static function page( $key ) {
		$id = (int) kcs_get_option( 'page_' . $key );
		return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Small line icons.
	 *
	 * @param string $name Icon.
	 * @return string
	 */
	private static function icon( $name ) {
		$paths = array(
			'live'    => '<rect x="2" y="5" width="15" height="14" rx="3"/><path d="m17 10 5-3v10l-5-3"/>',
			'give'    => '<path d="M12 21s-7-4.4-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.6-9.5 9-9.5 9z"/>',
			'pray'    => '<path d="M12 3v5M8 21l1.5-7L12 9l2.5 5L16 21M5 13l3 1M19 13l-3 1"/>',
			'word'    => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM4 19V5M9 7h6"/>',
			'sermon'  => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/>',
			'event'   => '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/>',
			'visit'   => '<path d="M12 21s-7-6.1-7-11a7 7 0 0 1 14 0c0 4.9-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
			'partner' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
			'contact' => '<path d="M4 5h16v11H8l-4 4z"/>',
			'play'    => '<path d="M8 5v14l11-7z" fill="currentColor"/>',
			'bell'    => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"/>',
			'phone'   => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
		);
		return '<svg class="kah-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * Latest sermons.
	 *
	 * @return array
	 */
	private static function sermons() {
		if ( ! post_type_exists( 'kcs_sermon' ) ) {
			return array();
		}
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'kcs_sermon', 'posts_per_page' => 8 ) ) as $p ) {
			$img = (string) get_the_post_thumbnail_url( $p, 'medium_large' );
			if ( ! $img ) {
				$yt  = KCS_Post_Types::youtube_id( (string) get_post_meta( $p->ID, '_kcs_video_url', true ) );
				$img = $yt ? 'https://i.ytimg.com/vi/' . rawurlencode( $yt ) . '/hqdefault.jpg' : '';
			}
			$date  = (string) get_post_meta( $p->ID, '_kcs_sermon_date', true );
			$out[] = array(
				'title' => get_the_title( $p ),
				'url'   => get_permalink( $p ),
				'img'   => $img,
				'meta'  => trim( ( $date ? date_i18n( 'j M Y', strtotime( $date ) ) : get_the_date( 'j M Y', $p ) ) . ( get_post_meta( $p->ID, '_kcs_duration', true ) ? ' · ' . get_post_meta( $p->ID, '_kcs_duration', true ) : '' ) ),
			);
		}
		return $out;
	}

	/**
	 * Upcoming events.
	 *
	 * @return array
	 */
	private static function events() {
		if ( ! post_type_exists( 'kcs_event' ) ) {
			return array();
		}
		$out = array();
		$now = current_time( 'Y-m-d\TH:i' );
		foreach (
			get_posts(
				array(
					'post_type'      => 'kcs_event',
					'posts_per_page' => 4,
					'meta_key'       => '_kcs_start', // phpcs:ignore WordPress.DB.SlowDBQuery
					'orderby'        => 'meta_value',
					'order'          => 'ASC',
					'meta_query'     => array( array( 'key' => '_kcs_start', 'value' => $now, 'compare' => '>=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			) as $p
		) {
			$start = strtotime( (string) get_post_meta( $p->ID, '_kcs_start', true ) );
			$out[] = array(
				'title' => get_the_title( $p ),
				'url'   => get_permalink( $p ),
				'day'   => date_i18n( 'j', $start ),
				'month' => date_i18n( 'M', $start ),
				'meta'  => trim( date_i18n( 'D, g:i a', $start ) . ( get_post_meta( $p->ID, '_kcs_location', true ) ? ' · ' . get_post_meta( $p->ID, '_kcs_location', true ) : '' ) ),
			);
		}
		return $out;
	}

	/**
	 * [kcs_app].
	 *
	 * @return string
	 */
	public static function render() {
		wp_enqueue_style( 'kcs-app-home', KCS_URL . 'assets/css/app-home.css', array( 'kcs-public' ), KCS_VERSION );
		wp_enqueue_script( 'kcs-app-home', KCS_URL . 'assets/js/app-home.js', array( 'kcs-public' ), KCS_VERSION, true );
		$user   = wp_get_current_user();
		$first  = $user && $user->exists() ? ( $user->first_name ? $user->first_name : $user->display_name ) : '';
		$next   = KCS_Livestream::next_service();
		$live   = get_transient( 'kcs_live_status' );
		$is_on  = is_array( $live ) && ! empty( $live['is_live'] );
		$word   = KCS_Devotionals::data( current_time( 'Y-m-d' ) );
		$stats  = KCS_Prayer::stats();
		$asks   = array_map( array( 'KCS_Prayer', 'public_item' ), KCS_Prayer::query( array( 'filter' => 'needs', 'per_page' => 6 ) )['rows'] );
		$logo   = (string) kcs_get_option( 'church_logo' );
		$name   = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		$urls   = array(
			'watch'   => self::page( 'watch_live' ),
			'give'    => self::page( 'give' ),
			'pray'    => self::page( 'prayer' ),
			'word'    => self::page( 'todays_word' ),
			'visit'   => self::page( 'visit' ),
			'partner' => self::page( 'partner_dashboard' ),
			'sermons' => post_type_exists( 'kcs_sermon' ) ? (string) get_post_type_archive_link( 'kcs_sermon' ) : '',
			'events'  => post_type_exists( 'kcs_event' ) ? (string) get_post_type_archive_link( 'kcs_event' ) : '',
		);
		$tiles  = array_filter(
			array(
				array( 'live', __( 'Watch', 'kingdom-church-suite' ), $urls['watch'], 'a' ),
				array( 'give', __( 'Give', 'kingdom-church-suite' ), $urls['give'], 'b' ),
				array( 'pray', __( 'Prayer', 'kingdom-church-suite' ), $urls['pray'], 'c' ),
				array( 'word', __( 'Today’s Word', 'kingdom-church-suite' ), $urls['word'], 'd' ),
				array( 'sermon', __( 'Sermons', 'kingdom-church-suite' ), $urls['sermons'], 'e' ),
				array( 'event', __( 'Events', 'kingdom-church-suite' ), $urls['events'], 'f' ),
				array( 'visit', __( 'Visit us', 'kingdom-church-suite' ), $urls['visit'], 'g' ),
				array( 'partner', __( 'Partnership', 'kingdom-church-suite' ), $urls['partner'], 'h' ),
			),
			function ( $t ) {
				return '' !== $t[2];
			}
		);
		$sermons = self::sermons();
		$events  = self::events();
		$excerpt = wp_trim_words( wp_strip_all_tags( $word['message'] ), 34 );
		$config  = array(
			'next'  => $next ? (int) $next['timestamp'] : 0,
			'live'  => $is_on,
			'i18n'  => array(
				'morning'   => __( 'Good morning', 'kingdom-church-suite' ),
				'afternoon' => __( 'Good afternoon', 'kingdom-church-suite' ),
				'evening'   => __( 'Good evening', 'kingdom-church-suite' ),
				'night'     => __( 'Peace be with you', 'kingdom-church-suite' ),
				'liveNow'   => __( 'We are live now', 'kingdom-church-suite' ),
				'd'         => _x( 'd', 'days short', 'kingdom-church-suite' ),
				'h'         => _x( 'h', 'hours short', 'kingdom-church-suite' ),
				'm'         => _x( 'm', 'minutes short', 'kingdom-church-suite' ),
				's'         => _x( 's', 'seconds short', 'kingdom-church-suite' ),
				'prayed'    => __( 'Prayed', 'kingdom-church-suite' ),
			),
		);
		ob_start();
		?>
		<div class="kah" data-kcs-app data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<section class="kah-hero" data-kah-scene>
				<div class="kah-aurora" aria-hidden="true"><span data-depth="0.25"></span><span data-depth="0.5"></span><span data-depth="0.8"></span><i class="kah-stars" data-depth="0.15"></i></div>
				<div class="kah-hero__top" data-depth="0.1">
					<?php if ( $logo ) : ?><img class="kah-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>"><?php else : ?><strong class="kah-church"><?php echo esc_html( $name ); ?></strong><?php endif; ?>
					<button type="button" class="kah-bell" data-kcs-push aria-label="<?php esc_attr_e( 'Notifications', 'kingdom-church-suite' ); ?>"><?php echo self::icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text" data-kcs-push-label><?php esc_html_e( 'Turn on notifications', 'kingdom-church-suite' ); ?></span></button>
				</div>
				<div class="kah-hello" data-depth="0.2">
					<p class="kah-date"><?php echo esc_html( date_i18n( 'l, j F' ) ); ?></p>
					<h1 class="kah-greet"><span data-kah-greet><?php esc_html_e( 'Welcome', 'kingdom-church-suite' ); ?></span><?php echo $first ? ', <em>' . esc_html( $first ) . '</em>' : ''; ?></h1>
				</div>
				<a class="kah-live<?php echo $is_on ? ' is-live' : ''; ?>" data-kah-live href="<?php echo esc_url( $urls['watch'] ? $urls['watch'] : home_url( '/' ) ); ?>" data-depth="0.35">
					<span class="kah-live__dot" aria-hidden="true"></span>
					<span class="kah-live__text">
						<small data-kah-live-label><?php echo esc_html( $is_on ? __( 'We are live now', 'kingdom-church-suite' ) : ( $next ? $next['name'] : __( 'Watch online', 'kingdom-church-suite' ) ) ); ?></small>
						<strong data-kah-count><?php echo esc_html( $is_on ? __( 'Join the service', 'kingdom-church-suite' ) : ( $next ? wp_date( 'D g:i a', $next['timestamp'] ) : __( 'Past services', 'kingdom-church-suite' ) ) ); ?></strong>
					</span>
					<span class="kah-live__go"><?php echo self::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</section>

			<?php if ( $tiles ) : ?>
				<nav class="kah-tiles" aria-label="<?php esc_attr_e( 'Quick actions', 'kingdom-church-suite' ); ?>">
					<?php foreach ( array_values( $tiles ) as $n => $t ) : ?>
						<a class="kah-tile kah-tile--<?php echo esc_attr( $t[3] ); ?>" href="<?php echo esc_url( $t[2] ); ?>" style="--i:<?php echo (int) $n; ?>"><span class="kah-tile__ico"><?php echo self::icon( $t[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span><?php echo esc_html( $t[1] ); ?></span></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<section class="kah-sec">
				<header class="kah-sec__head"><h2><?php esc_html_e( 'Today’s Word', 'kingdom-church-suite' ); ?></h2><?php if ( $urls['word'] ) : ?><a href="<?php echo esc_url( $urls['word'] ); ?>"><?php esc_html_e( 'Open', 'kingdom-church-suite' ); ?> →</a><?php endif; ?></header>
				<div class="kah-flip" data-kah-flip tabindex="0" role="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'Turn the card', 'kingdom-church-suite' ); ?>">
					<div class="kah-flip__inner">
						<div class="kah-flip__face kah-flip__front">
							<span class="kah-flip__shine" aria-hidden="true"></span>
							<p class="kah-flip__verse">“<?php echo esc_html( $word['verse'] ); ?>”</p>
							<p class="kah-flip__ref"><?php echo esc_html( $word['ref'] ); ?></p>
							<small class="kah-flip__hint"><?php echo esc_html( $word['fallback'] ? __( 'Verse of the day', 'kingdom-church-suite' ) : __( 'Tap to read today’s message', 'kingdom-church-suite' ) ); ?> ↻</small>
						</div>
						<div class="kah-flip__face kah-flip__back">
							<h3><?php echo esc_html( $word['title'] ); ?></h3>
							<p><?php echo esc_html( $excerpt ? $excerpt : __( 'Let this word sink in today. Read it again slowly, and ask God to speak to you through it.', 'kingdom-church-suite' ) ); ?></p>
							<div class="kah-flip__btns">
								<?php if ( $urls['word'] ) : ?><a class="kah-chip-btn" href="<?php echo esc_url( $urls['word'] ); ?>"><?php esc_html_e( 'Read', 'kingdom-church-suite' ); ?></a><?php endif; ?>
								<button type="button" class="kah-chip-btn" data-kcs-listen data-text="<?php echo esc_attr( $word['title'] . '. ' . $word['verse'] . ' ' . $word['ref'] . '. ' . wp_strip_all_tags( $word['message'] ) ); ?>" data-play="<?php esc_attr_e( 'Listen', 'kingdom-church-suite' ); ?>" data-stop="<?php esc_attr_e( 'Stop', 'kingdom-church-suite' ); ?>" hidden>🔊 <span><?php esc_html_e( 'Listen', 'kingdom-church-suite' ); ?></span></button>
								<button type="button" class="kah-chip-btn" data-kcs-share>↗</button>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="kah-sec">
				<header class="kah-sec__head"><h2><?php esc_html_e( 'Pray for someone', 'kingdom-church-suite' ); ?></h2><?php if ( $urls['pray'] ) : ?><a href="<?php echo esc_url( $urls['pray'] ); ?>"><?php esc_html_e( 'Prayer wall', 'kingdom-church-suite' ); ?> →</a><?php endif; ?></header>
				<div class="kah-pulse">
					<span class="kah-pulse__ring" aria-hidden="true"></span>
					<p><strong data-kah-prayers><?php echo esc_html( number_format_i18n( $stats['prayers'] ) ); ?></strong> <?php esc_html_e( 'prayers prayed', 'kingdom-church-suite' ); ?> · <strong><?php echo esc_html( number_format_i18n( $stats['answered'] ) ); ?></strong> <?php esc_html_e( 'answered', 'kingdom-church-suite' ); ?></p>
				</div>
				<?php if ( $asks ) : ?>
					<div class="kah-rail" data-kah-rail>
						<?php foreach ( $asks as $a ) : ?>
							<article class="kah-ask" style="--h:<?php echo (int) $a['hue']; ?>" data-id="<?php echo (int) $a['id']; ?>">
								<header><span class="kah-ask__av"><?php echo esc_html( $a['initial'] ); ?></span><strong><?php echo esc_html( $a['name'] ); ?></strong><?php if ( $a['category_label'] ) : ?><small><?php echo esc_html( $a['category_label'] ); ?></small><?php endif; ?></header>
								<p><?php echo esc_html( wp_trim_words( $a['request'], 26 ) ); ?></p>
								<button type="button" class="kah-ask__btn" data-kah-pray>🙏 <span><?php esc_html_e( 'I prayed', 'kingdom-church-suite' ); ?></span> <b><?php echo (int) $a['prayers']; ?></b></button>
							</article>
						<?php endforeach; ?>
					</div>
				<?php elseif ( $urls['pray'] ) : ?>
					<a class="kah-cta" href="<?php echo esc_url( $urls['pray'] ); ?>"><?php esc_html_e( 'Share a prayer request', 'kingdom-church-suite' ); ?> →</a>
				<?php endif; ?>
			</section>

			<?php if ( $sermons ) : ?>
				<section class="kah-sec">
					<header class="kah-sec__head"><h2><?php esc_html_e( 'Latest messages', 'kingdom-church-suite' ); ?></h2><?php if ( $urls['sermons'] ) : ?><a href="<?php echo esc_url( $urls['sermons'] ); ?>"><?php esc_html_e( 'All', 'kingdom-church-suite' ); ?> →</a><?php endif; ?></header>
					<div class="kah-rail kah-rail--media" data-kah-rail>
						<?php foreach ( $sermons as $s ) : ?>
							<a class="kah-media" href="<?php echo esc_url( $s['url'] ); ?>">
								<span class="kah-media__img"<?php echo $s['img'] ? ' style="background-image:url(' . esc_url( $s['img'] ) . ')"' : ''; ?>><span class="kah-media__play"><?php echo self::icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></span>
								<strong><?php echo esc_html( $s['title'] ); ?></strong><small><?php echo esc_html( $s['meta'] ); ?></small>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $events ) : ?>
				<section class="kah-sec">
					<header class="kah-sec__head"><h2><?php esc_html_e( 'Coming up', 'kingdom-church-suite' ); ?></h2><?php if ( $urls['events'] ) : ?><a href="<?php echo esc_url( $urls['events'] ); ?>"><?php esc_html_e( 'All', 'kingdom-church-suite' ); ?> →</a><?php endif; ?></header>
					<div class="kah-events">
						<?php foreach ( $events as $e ) : ?>
							<a class="kah-event" href="<?php echo esc_url( $e['url'] ); ?>"><span class="kah-event__date"><b><?php echo esc_html( $e['day'] ); ?></b><small><?php echo esc_html( $e['month'] ); ?></small></span><span><strong><?php echo esc_html( $e['title'] ); ?></strong><small><?php echo esc_html( $e['meta'] ); ?></small></span></a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="kah-install" data-kcs-install hidden>
				<span class="kah-install__ico"><?php echo self::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<p><strong><?php esc_html_e( 'Put the church in your pocket', 'kingdom-church-suite' ); ?></strong><small><?php esc_html_e( 'Install the app for one-tap access, live alerts and offline reading.', 'kingdom-church-suite' ); ?></small></p>
				<span class="kah-chip-btn"><?php esc_html_e( 'Install', 'kingdom-church-suite' ); ?></span>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}
}
