<?php
/**
 * Today's Word: a short daily devotional (title, Bible verse, message and prayer) the church
 * writes ahead and schedules by date. People read it on the website or in the app, listen to it
 * read aloud, share it, and can get it by email every morning.
 *
 * When there is no devotional for a day, the verse of the day shows instead.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Daily devotionals.
 */
class KCS_Devotionals {

	const TYPE = 'kcs_devotional';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'kcs_todays_word_tick', array( __CLASS__, 'maybe_email' ) );
		if ( ! wp_next_scheduled( 'kcs_todays_word_tick' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'kcs_todays_word_tick' );
		}
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Post type.
	 */
	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'        => array(
					'name'          => __( 'Today’s Word', 'kingdom-church-suite' ),
					'singular_name' => __( 'Devotional', 'kingdom-church-suite' ),
					'add_new_item'  => __( 'Add devotional', 'kingdom-church-suite' ),
					'edit_item'     => __( 'Edit devotional', 'kingdom-church-suite' ),
					'all_items'     => __( 'All devotionals', 'kingdom-church-suite' ),
				),
				'public'        => true,
				'has_archive'   => false,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-book-alt',
				'menu_position' => 25,
				'supports'      => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'       => array( 'slug' => 'todays-word', 'with_front' => false ),
			)
		);
	}

	/**
	 * REST: GET /kcs/v1/todays-word?date=YYYY-MM-DD (for the app).
	 */
	public static function routes() {
		register_rest_route(
			'kcs/v1',
			'/todays-word',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( $req ) {
					return rest_ensure_response( self::data( self::clean_date( (string) $req['date'] ) ) );
				},
			)
		);
	}

	/**
	 * A valid date not after today, or today.
	 *
	 * @param string $date Date.
	 * @return string Y-m-d.
	 */
	public static function clean_date( $date ) {
		$today = current_time( 'Y-m-d' );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $date <= $today && strtotime( $date ) ? $date : $today;
	}

	/**
	 * The devotional for a day (published or scheduled for that day).
	 *
	 * @param string $date Y-m-d.
	 * @return WP_Post|null
	 */
	public static function for_date( $date ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
		$posts             = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => 1,
				'date_query'     => array( array( 'year' => $y, 'month' => $m, 'day' => $d ) ),
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		return $posts ? $posts[0] : null;
	}

	/**
	 * Everything a page or the app shows for a day.
	 *
	 * @param string $date Y-m-d.
	 * @return array
	 */
	public static function data( $date ) {
		$post  = self::for_date( $date );
		$prev  = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
		$next  = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		$today = current_time( 'Y-m-d' );
		$out   = array(
			'date'      => $date,
			'label'     => date_i18n( 'l, j F Y', strtotime( $date ) ),
			'is_today'  => $date === $today,
			'prev'      => $prev,
			'next'      => $next <= $today ? $next : '',
			'title'     => '',
			'verse'     => '',
			'ref'       => '',
			'message'   => '',
			'prayer'    => '',
			'image'     => '',
			'url'       => '',
			'fallback'  => false,
		);
		if ( $post ) {
			$out['title']   = get_the_title( $post );
			$out['verse']   = (string) get_post_meta( $post->ID, '_kcs_verse', true );
			$out['ref']     = (string) get_post_meta( $post->ID, '_kcs_verse_ref', true );
			$out['message'] = wpautop( wp_kses_post( $post->post_content ) );
			$out['prayer']  = (string) get_post_meta( $post->ID, '_kcs_prayer', true );
			$out['image']   = (string) get_the_post_thumbnail_url( $post, 'large' );
			$out['url']     = self::page_url( $date );
			return $out;
		}
		$v                = KCS_Features::verse_of_the_day();
		$out['title']     = __( 'Verse of the day', 'kingdom-church-suite' );
		$out['verse']     = $v['text'];
		$out['ref']       = $v['ref'];
		$out['fallback']  = true;
		$out['url']       = self::page_url( $date );
		return $out;
	}

	/**
	 * Link to the Today's Word page for a day.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public static function page_url( $date = '' ) {
		$base = kcs_get_option( 'page_todays_word' ) ? (string) get_permalink( (int) kcs_get_option( 'page_todays_word' ) ) : home_url( '/' );
		return $date && current_time( 'Y-m-d' ) !== $date ? add_query_arg( 'day', $date, $base ) : $base;
	}

	/**
	 * Save a devotional from Church Admin.
	 *
	 * @param array $d  Posted data.
	 * @param int   $id Post (0 = new).
	 * @return int|WP_Error
	 */
	public static function save( $d, $id = 0 ) {
		$date  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['date'] ?? '' ) ) ? $d['date'] : current_time( 'Y-m-d' );
		$title = sanitize_text_field( $d['title'] ?? '' );
		if ( '' === $title ) {
			return new WP_Error( 'kcs_word', __( 'Please give the devotional a title.', 'kingdom-church-suite' ) );
		}
		$other = self::for_date( $date );
		if ( $other && (int) $other->ID !== (int) $id ) {
			return new WP_Error( 'kcs_word', __( 'There is already a devotional for that day. Open it to change it, or pick another date.', 'kingdom-church-suite' ) );
		}
		$local = $date . ' 00:01:00';
		$post  = array(
			'post_type'     => self::TYPE,
			'post_title'    => $title,
			'post_content'  => wp_kses_post( $d['message'] ?? '' ),
			'post_status'   => $date > current_time( 'Y-m-d' ) ? 'future' : 'publish',
			'post_date'     => $local,
			'post_date_gmt' => get_gmt_from_date( $local ),
			'edit_date'     => true,
		);
		if ( $id ) {
			$post['ID'] = $id;
			$id         = wp_update_post( $post, true );
		} else {
			$id = wp_insert_post( $post, true );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_kcs_verse', sanitize_textarea_field( $d['verse'] ?? '' ) );
		update_post_meta( $id, '_kcs_verse_ref', sanitize_text_field( $d['ref'] ?? '' ) );
		update_post_meta( $id, '_kcs_prayer', sanitize_textarea_field( $d['prayer'] ?? '' ) );
		if ( isset( $d['image_id'] ) ) {
			$img = absint( $d['image_id'] );
			if ( $img ) {
				set_post_thumbnail( $id, $img );
			} else {
				delete_post_thumbnail( $id );
			}
		}
		return (int) $id;
	}

	/**
	 * Create the Today's Word page.
	 *
	 * @return int Page ID.
	 */
	public static function create_page() {
		$existing = (int) kcs_get_option( 'page_todays_word' );
		if ( $existing && 'page' === get_post_type( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			return $existing;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Today’s Word', 'kingdom-church-suite' ),
				'post_name'    => 'todays-word-daily',
				'post_content' => '[kcs_todays_word]',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			kcs_update_option( 'page_todays_word', (int) $id );
		}
		return (int) $id;
	}

	/**
	 * Each hour: email today's devotional to subscribers once, after the chosen hour.
	 */
	public static function maybe_email() {
		if ( '1' !== kcs_get_option( 'todays_word_email', '0' ) ) {
			return;
		}
		$today = current_time( 'Y-m-d' );
		if ( get_option( 'kcs_todays_word_sent' ) === $today || (int) current_time( 'G' ) < (int) kcs_get_option( 'todays_word_hour', 6 ) ) {
			return;
		}
		$post = self::for_date( $today );
		if ( ! $post ) {
			return;
		}
		update_option( 'kcs_todays_word_sent', $today, false );
		KCS_Newsletter::queue_message( 'subscribers', sprintf( /* translators: %s: title */ __( 'Today’s Word: %s', 'kingdom-church-suite' ), get_the_title( $post ) ), self::email_body( self::data( $today ) ), wp_trim_words( wp_strip_all_tags( $post->post_content ), 18 ) );
	}

	/**
	 * Email version.
	 *
	 * @param array $w Data.
	 * @return string
	 */
	public static function email_body( $w ) {
		$color = kcs_get_option( 'email_color', '#5b2a86' );
		return '<p style="margin:0 0 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;font-weight:bold;color:' . esc_attr( $color ) . '">' . esc_html__( 'Today’s Word', 'kingdom-church-suite' ) . ' · ' . esc_html( $w['label'] ) . '</p>'
			. '<h1 style="margin:0 0 16px;font-family:Georgia,Times,serif;font-size:28px;line-height:1.2;font-weight:normal;color:#1f1b24">' . esc_html( $w['title'] ) . '</h1>'
			. ( $w['verse'] ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px"><tr><td style="padding:18px 20px;border-left:4px solid ' . esc_attr( $color ) . ';background:#f7f4fb"><p style="margin:0 0 6px;font-family:Georgia,Times,serif;font-size:19px;line-height:1.5;font-style:italic;color:#1f1b24">“' . esc_html( $w['verse'] ) . '”</p><p style="margin:0;font-size:13px;font-weight:bold;color:#8a8196">' . esc_html( $w['ref'] ) . '</p></td></tr></table>' : '' )
			. wp_kses_post( $w['message'] )
			. ( $w['prayer'] ? '<p style="margin:20px 0 6px;font-weight:bold">' . esc_html__( 'Today’s prayer', 'kingdom-church-suite' ) . '</p><p style="margin:0 0 16px;font-style:italic">' . nl2br( esc_html( $w['prayer'] ) ) . '</p>' : '' )
			. '<p style="margin:24px 0;text-align:center"><a class="btn" href="' . esc_url( $w['url'] ) . '">' . esc_html__( 'Read and listen online', 'kingdom-church-suite' ) . '</a></p>';
	}

	/**
	 * [kcs_todays_word].
	 *
	 * @return string
	 */
	public static function shortcode() {
		$date = self::clean_date( isset( $_GET['day'] ) ? sanitize_text_field( wp_unslash( $_GET['day'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$w    = self::data( $date );
		$here = get_permalink();
		$link = function ( $d ) use ( $here ) {
			return current_time( 'Y-m-d' ) === $d ? $here : add_query_arg( 'day', $d, $here );
		};
		$speak = trim( $w['title'] . '. ' . ( $w['verse'] ? $w['verse'] . ' ' . $w['ref'] . '. ' : '' ) . wp_strip_all_tags( $w['message'] ) . ( $w['prayer'] ? ' ' . __( 'Today’s prayer.', 'kingdom-church-suite' ) . ' ' . $w['prayer'] : '' ) );
		ob_start();
		?>
		<article class="ktw" data-kcs-word>
			<header class="ktw-head">
				<p class="ktw-eyebrow"><?php echo esc_html( $w['is_today'] ? __( 'Today’s Word', 'kingdom-church-suite' ) : __( 'Daily Word', 'kingdom-church-suite' ) ); ?></p>
				<nav class="ktw-days" aria-label="<?php esc_attr_e( 'Other days', 'kingdom-church-suite' ); ?>">
					<a href="<?php echo esc_url( $link( $w['prev'] ) ); ?>" rel="prev" aria-label="<?php esc_attr_e( 'Previous day', 'kingdom-church-suite' ); ?>">‹</a>
					<span><?php echo esc_html( $w['label'] ); ?></span>
					<?php if ( $w['next'] ) : ?><a href="<?php echo esc_url( $link( $w['next'] ) ); ?>" rel="next" aria-label="<?php esc_attr_e( 'Next day', 'kingdom-church-suite' ); ?>">›</a><?php else : ?><span class="is-off" aria-hidden="true">›</span><?php endif; ?>
				</nav>
			</header>
			<?php if ( $w['image'] ) : ?><img class="ktw-img" src="<?php echo esc_url( $w['image'] ); ?>" alt="" loading="lazy"><?php endif; ?>
			<h2 class="ktw-title"><?php echo esc_html( $w['title'] ); ?></h2>
			<?php if ( $w['verse'] ) : ?>
				<blockquote class="ktw-verse"><p>“<?php echo esc_html( $w['verse'] ); ?>”</p><cite><?php echo esc_html( $w['ref'] ); ?></cite></blockquote>
			<?php endif; ?>
			<?php if ( $w['message'] ) : ?><div class="ktw-body"><?php echo wp_kses_post( $w['message'] ); ?></div><?php endif; ?>
			<?php if ( $w['prayer'] ) : ?>
				<div class="ktw-prayer"><strong><?php esc_html_e( 'Today’s prayer', 'kingdom-church-suite' ); ?></strong><p><?php echo nl2br( esc_html( $w['prayer'] ) ); ?></p></div>
			<?php endif; ?>
			<div class="ktw-actions">
				<button type="button" class="kcs-btn" data-kcs-listen data-text="<?php echo esc_attr( $speak ); ?>" data-play="<?php esc_attr_e( 'Listen', 'kingdom-church-suite' ); ?>" data-stop="<?php esc_attr_e( 'Stop', 'kingdom-church-suite' ); ?>" hidden>🔊 <span><?php esc_html_e( 'Listen', 'kingdom-church-suite' ); ?></span></button>
				<button type="button" class="kcs-btn kcs-btn--ghost" data-kcs-share>↗ <?php esc_html_e( 'Share', 'kingdom-church-suite' ); ?></button>
			</div>
			<form class="ktw-sub kcs-form" data-kcs-newsletter>
				<strong><?php esc_html_e( 'Get Today’s Word in your inbox every morning', 'kingdom-church-suite' ); ?></strong>
				<div class="ktw-sub__row">
					<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Your email address', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Email', 'kingdom-church-suite' ); ?>">
					<input type="hidden" name="source" value="todays_word">
					<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
					<button class="kcs-btn"><?php esc_html_e( 'Subscribe', 'kingdom-church-suite' ); ?></button>
				</div>
				<div class="kcs-form-msg" aria-live="polite"></div>
			</form>
		</article>
		<?php
		return ob_get_clean();
	}
}
