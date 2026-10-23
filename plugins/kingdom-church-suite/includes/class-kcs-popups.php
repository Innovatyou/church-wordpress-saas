<?php
/**
 * Popups with display rules: which pages, who, which devices, when it opens and how often.
 * Page, audience and dates are checked on the server; device, trigger and frequency in the
 * visitor's browser. One popup at most per page view (the first one in the list that matches).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Popups.
 */
class KCS_Popups {

	const OPTION = 'kcs_popups';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 5 ); // Before the footer scripts that open it.
	}

	/**
	 * All popups.
	 *
	 * @return array[]
	 */
	public static function all() {
		return array_values( array_filter( (array) get_option( self::OPTION, array() ), 'is_array' ) );
	}

	/**
	 * Choices for the rules.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'pages'     => array(
				'all'    => __( 'Every page', 'kingdom-church-suite' ),
				'home'   => __( 'Homepage only', 'kingdom-church-suite' ),
				'only'   => __( 'Only the pages I tick', 'kingdom-church-suite' ),
				'except' => __( 'Every page except the ones I tick', 'kingdom-church-suite' ),
			),
			'audience'  => array(
				'all'      => __( 'Everyone', 'kingdom-church-suite' ),
				'guests'   => __( 'Visitors who are not logged in', 'kingdom-church-suite' ),
				'partners' => __( 'Logged-in partners only', 'kingdom-church-suite' ),
			),
			'device'    => array(
				'all'     => __( 'Phones and computers', 'kingdom-church-suite' ),
				'mobile'  => __( 'Phones only', 'kingdom-church-suite' ),
				'desktop' => __( 'Computers only', 'kingdom-church-suite' ),
			),
			'trigger'   => array(
				'delay'  => __( 'After a few seconds', 'kingdom-church-suite' ),
				'scroll' => __( 'When the visitor scrolls down the page', 'kingdom-church-suite' ),
				'exit'   => __( 'When the visitor is about to leave (computers; phones after 30 seconds)', 'kingdom-church-suite' ),
			),
			'frequency' => array(
				'session' => __( 'Once per visit', 'kingdom-church-suite' ),
				'days'    => __( 'Once every few days', 'kingdom-church-suite' ),
				'once'    => __( 'Only once (until it is changed)', 'kingdom-church-suite' ),
				'always'  => __( 'Every time the page opens', 'kingdom-church-suite' ),
			),
		);
	}

	/**
	 * Does this popup's page, audience and date rules match the current request?
	 *
	 * @param array $p Popup.
	 * @return bool
	 */
	public static function matches( $p ) {
		if ( empty( $p['active'] ) ) {
			return false;
		}
		$today = current_time( 'Y-m-d' );
		if ( ( ! empty( $p['start'] ) && $p['start'] > $today ) || ( ! empty( $p['end'] ) && $p['end'] < $today ) ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( 'guests' === $p['audience'] && $user->exists() ) {
			return false;
		}
		if ( 'partners' === $p['audience'] && ! ( $user->exists() && ( in_array( 'kcs_partner', (array) $user->roles, true ) || KCS_Partners::get_by_user( $user->ID ) ) ) ) {
			return false;
		}
		$id    = is_singular() ? (int) get_queried_object_id() : 0;
		$pages = array_map( 'intval', (array) ( $p['page_ids'] ?? array() ) );
		switch ( $p['pages'] ) {
			case 'home':
				return is_front_page();
			case 'only':
				return ( in_array( $id, $pages, true ) || ( is_front_page() && in_array( -1, $pages, true ) ) );
			case 'except':
				return ! ( in_array( $id, $pages, true ) || ( is_front_page() && in_array( -1, $pages, true ) ) );
		}
		return true;
	}

	/**
	 * Print the first matching popup.
	 */
	public static function render() {
		if ( is_admin() || is_customize_preview() || ( function_exists( 'kcs_get_option' ) && is_page( (int) kcs_get_option( 'page_church_admin', 0 ) ) ) ) {
			return;
		}
		foreach ( self::all() as $p ) {
			if ( ! self::matches( $p ) ) {
				continue;
			}
			$data = array(
				'id'        => $p['id'] . '-' . ( $p['version'] ?? 1 ),
				'device'    => $p['device'],
				'trigger'   => $p['trigger'],
				'delay'     => (int) $p['delay'],
				'scroll'    => (int) $p['scroll'],
				'frequency' => $p['frequency'],
				'days'      => (int) $p['days'],
			);
			?>
			<div class="kcs-popup" data-kcs-popup="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" hidden>
				<div class="kcs-popup__backdrop" data-close></div>
				<div class="kcs-popup__box<?php echo ! empty( $p['image'] ) ? ' has-image' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="kcs-popup-title-<?php echo esc_attr( $p['id'] ); ?>">
					<button type="button" class="kcs-popup__close" data-close aria-label="<?php esc_attr_e( 'Close', 'kingdom-church-suite' ); ?>">×</button>
					<?php if ( ! empty( $p['image'] ) ) : ?><img class="kcs-popup__img" src="<?php echo esc_url( $p['image'] ); ?>" alt="" loading="lazy"><?php endif; ?>
					<div class="kcs-popup__body">
						<h2 class="kcs-popup__title" id="kcs-popup-title-<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['title'] ); ?></h2>
						<?php if ( ! empty( $p['text'] ) ) : ?><p class="kcs-popup__text"><?php echo nl2br( esc_html( $p['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php endif; ?>
						<?php if ( ! empty( $p['btn_text'] ) && ! empty( $p['btn_url'] ) ) : ?>
							<a class="kcs-btn kcs-popup__btn" href="<?php echo esc_url( $p['btn_url'] ); ?>" data-done><?php echo esc_html( $p['btn_text'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
			return;
		}
	}

	/* ---------------------------------------------------------------------
	 * Church Admin
	 * ------------------------------------------------------------------- */

	/**
	 * Clean a popup from the form.
	 *
	 * @param array $d   Posted data.
	 * @param array $old Existing popup.
	 * @return array
	 */
	private static function from_post( $d, $old = array() ) {
		$c    = self::choices();
		$pick = function ( $key, $default ) use ( $d, $c ) {
			return array_key_exists( (string) ( $d[ $key ] ?? '' ), $c[ $key ] ) ? (string) $d[ $key ] : $default;
		};
		$date = function ( $v ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? (string) $v : '';
		};
		$new = array(
			'id'        => $old['id'] ?? substr( md5( uniqid( '', true ) ), 0, 10 ),
			'name'      => sanitize_text_field( $d['name'] ?? '' ),
			'title'     => sanitize_text_field( $d['title'] ?? '' ),
			'text'      => sanitize_textarea_field( $d['text'] ?? '' ),
			'image'     => esc_url_raw( $d['image'] ?? '' ),
			'btn_text'  => sanitize_text_field( $d['btn_text'] ?? '' ),
			'btn_url'   => esc_url_raw( $d['btn_url'] ?? '' ),
			'pages'     => $pick( 'pages', 'all' ),
			'page_ids'  => array_values( array_filter( array_map( 'intval', (array) ( $d['page_ids'] ?? array() ) ) ) ),
			'audience'  => $pick( 'audience', 'all' ),
			'device'    => $pick( 'device', 'all' ),
			'trigger'   => $pick( 'trigger', 'delay' ),
			'delay'     => max( 0, min( 120, absint( $d['delay'] ?? 5 ) ) ),
			'scroll'    => max( 10, min( 100, absint( $d['scroll'] ?? 50 ) ) ),
			'frequency' => $pick( 'frequency', 'session' ),
			'days'      => max( 1, min( 365, absint( $d['days'] ?? 7 ) ) ),
			'start'     => $date( $d['start'] ?? '' ),
			'end'       => $date( $d['end'] ?? '' ),
			'active'    => ! empty( $d['active'] ),
		);
		// A changed popup shows again to visitors who already closed the old one.
		$before         = $old;
		unset( $before['version'] );
		$new['version'] = (int) ( $old['version'] ?? 0 ) + ( $before == $new ? 0 : 1 ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- compare arrays by value.
		return $new;
	}

	/**
	 * Handle an action.
	 *
	 * @param string $do Action.
	 * @param array  $d  Posted data.
	 * @return array array( message, type ).
	 */
	public static function handle( $do, $d ) {
		$popups = self::all();
		$pid    = sanitize_key( $d['popup'] ?? '' );
		$index  = null;
		foreach ( $popups as $i => $p ) {
			if ( $p['id'] === $pid ) {
				$index = $i;
			}
		}
		if ( 'popup_save' === $do ) {
			$p = self::from_post( $d, null === $index ? array() : $popups[ $index ] );
			if ( '' === $p['title'] ) {
				return array( __( 'Please give the popup a title.', 'kingdom-church-suite' ), 'error' );
			}
			if ( null === $index ) {
				$popups[] = $p;
			} else {
				$popups[ $index ] = $p;
			}
			$msg = __( 'Popup saved.', 'kingdom-church-suite' );
		} elseif ( 'popup_toggle' === $do && null !== $index ) {
			$popups[ $index ]['active'] = empty( $popups[ $index ]['active'] );
			$msg                        = $popups[ $index ]['active'] ? __( 'Popup switched on.', 'kingdom-church-suite' ) : __( 'Popup switched off.', 'kingdom-church-suite' );
		} elseif ( 'popup_delete' === $do && null !== $index ) {
			array_splice( $popups, $index, 1 );
			$msg = __( 'Popup deleted.', 'kingdom-church-suite' );
		} else {
			return array( '', 'success' );
		}
		update_option( self::OPTION, array_values( $popups ), false );
		return array( $msg, 'success' );
	}

	/**
	 * Church Admin → Popups.
	 *
	 * @param callable $fields Hidden fields builder ( $do, $view, $id ).
	 */
	public static function view( $fields ) {
		$popups = self::all();
		$c      = self::choices();
		$edit   = isset( $_GET['popup'] ) ? sanitize_key( $_GET['popup'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cur    = array();
		foreach ( $popups as $p ) {
			if ( $p['id'] === $edit ) {
				$cur = $p;
			}
		}
		$v      = function ( $key, $default = '' ) use ( $cur ) {
			return $cur[ $key ] ?? $default;
		};
		$pages  = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		$ticked = array_map( 'intval', (array) $v( 'page_ids', array() ) );
		$select = function ( $name, $value ) use ( $c ) {
			$h = '<select name="' . esc_attr( $name ) . '">';
			foreach ( $c[ $name ] as $k => $l ) {
				$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			return $h . '</select>';
		};
		$upload = current_user_can( 'upload_files' );
		?>
		<form method="post" class="kca-card kcs-form kca-popup-form" id="popup-form">
			<?php echo call_user_func( $fields, 'popup_save', 'popups', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="popup" value="<?php echo esc_attr( $v( 'id' ) ); ?>">
			<h3><?php echo esc_html( $cur ? __( 'Edit popup', 'kingdom-church-suite' ) : __( 'Create a popup', 'kingdom-church-suite' ) ); ?></h3>
			<fieldset><legend><?php esc_html_e( 'What it says', 'kingdom-church-suite' ); ?></legend>
				<label><?php esc_html_e( 'Name (only you see it)', 'kingdom-church-suite' ); ?><input name="name" value="<?php echo esc_attr( $v( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Crusade invitation', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Title', 'kingdom-church-suite' ); ?><input name="title" required value="<?php echo esc_attr( $v( 'title' ) ); ?>"></label>
				<label><?php esc_html_e( 'Text (optional)', 'kingdom-church-suite' ); ?><textarea name="text" rows="3"><?php echo esc_textarea( $v( 'text' ) ); ?></textarea></label>
				<div class="kca-image" data-kcs-media-field>
					<span class="kca-image__label"><?php esc_html_e( 'Picture (optional)', 'kingdom-church-suite' ); ?></span>
					<img class="kca-image__preview" src="<?php echo esc_url( $v( 'image' ) ); ?>" alt=""<?php echo $v( 'image' ) ? '' : ' hidden'; ?>>
					<input type="<?php echo $upload ? 'hidden' : 'url'; ?>" name="image" value="<?php echo esc_attr( $v( 'image' ) ); ?>"<?php echo $upload ? ' data-kcs-media="url"' : ' placeholder="https://…/flyer.jpg"'; ?>>
				</div>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Button text (optional)', 'kingdom-church-suite' ); ?><input name="btn_text" value="<?php echo esc_attr( $v( 'btn_text' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Register now', 'kingdom-church-suite' ); ?>"></label>
					<label><?php esc_html_e( 'Button link', 'kingdom-church-suite' ); ?><input type="url" name="btn_url" value="<?php echo esc_attr( $v( 'btn_url' ) ); ?>" placeholder="https://"></label>
				</div>
			</fieldset>
			<fieldset><legend><?php esc_html_e( 'Where it shows', 'kingdom-church-suite' ); ?></legend>
				<label><?php esc_html_e( 'Pages', 'kingdom-church-suite' ); ?><?php echo $select( 'pages', $v( 'pages', 'all' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<div class="kca-chips kca-popup-pages">
					<label class="kca-chip"><input type="checkbox" name="page_ids[]" value="-1" <?php checked( in_array( -1, $ticked, true ) ); ?>> <?php esc_html_e( 'Homepage', 'kingdom-church-suite' ); ?></label>
					<?php foreach ( $pages as $pg ) : ?>
						<?php if ( (int) $pg->ID === (int) kcs_get_option( 'page_church_admin', 0 ) ) { continue; } ?>
						<label class="kca-chip"><input type="checkbox" name="page_ids[]" value="<?php echo (int) $pg->ID; ?>" <?php checked( in_array( (int) $pg->ID, $ticked, true ) ); ?>> <?php echo esc_html( get_the_title( $pg ) ); ?></label>
					<?php endforeach; ?>
				</div>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Who sees it', 'kingdom-church-suite' ); ?><?php echo $select( 'audience', $v( 'audience', 'all' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
					<label><?php esc_html_e( 'Devices', 'kingdom-church-suite' ); ?><?php echo $select( 'device', $v( 'device', 'all' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				</div>
			</fieldset>
			<fieldset><legend><?php esc_html_e( 'When it opens', 'kingdom-church-suite' ); ?></legend>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Open it', 'kingdom-church-suite' ); ?><?php echo $select( 'trigger', $v( 'trigger', 'delay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
					<div class="kcs-grid-2">
						<label><?php esc_html_e( 'Seconds to wait', 'kingdom-church-suite' ); ?><input type="number" name="delay" min="0" max="120" value="<?php echo (int) $v( 'delay', 5 ); ?>"></label>
						<label><?php esc_html_e( 'Scrolled % of the page', 'kingdom-church-suite' ); ?><input type="number" name="scroll" min="10" max="100" value="<?php echo (int) $v( 'scroll', 50 ); ?>"></label>
					</div>
					<label><?php esc_html_e( 'How often', 'kingdom-church-suite' ); ?><?php echo $select( 'frequency', $v( 'frequency', 'session' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
					<label><?php esc_html_e( 'Days between showings', 'kingdom-church-suite' ); ?><input type="number" name="days" min="1" max="365" value="<?php echo (int) $v( 'days', 7 ); ?>"></label>
					<label><?php esc_html_e( 'From (optional)', 'kingdom-church-suite' ); ?><input type="date" name="start" value="<?php echo esc_attr( $v( 'start' ) ); ?>"></label>
					<label><?php esc_html_e( 'Until (optional)', 'kingdom-church-suite' ); ?><input type="date" name="end" value="<?php echo esc_attr( $v( 'end' ) ); ?>"></label>
				</div>
			</fieldset>
			<label class="kcs-check"><input type="checkbox" name="active" value="1" <?php checked( $cur ? ! empty( $cur['active'] ) : true ); ?>> <?php esc_html_e( 'Switch this popup on', 'kingdom-church-suite' ); ?></label>
			<div class="kca-actions">
				<button class="kcs-btn"><?php esc_html_e( 'Save popup', 'kingdom-church-suite' ); ?></button>
				<?php if ( $cur ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( KCS_Church_Admin::url( 'popups' ) ); ?>"><?php esc_html_e( 'Cancel', 'kingdom-church-suite' ); ?></a><?php endif; ?>
			</div>
		</form>

		<p class="kcs-muted"><?php esc_html_e( 'Only one popup opens per page: the first one in this list whose rules match. Popups never open on Church Admin.', 'kingdom-church-suite' ); ?></p>
		<div class="kca-stack">
			<?php foreach ( $popups as $p ) : ?>
				<article class="kca-card kca-item">
					<div>
						<strong><?php echo esc_html( $p['name'] ? $p['name'] : $p['title'] ); ?></strong>
						<span class="kca-pill<?php echo ! empty( $p['active'] ) ? ' is-on' : ''; ?>"><?php echo esc_html( ! empty( $p['active'] ) ? __( 'On', 'kingdom-church-suite' ) : __( 'Off', 'kingdom-church-suite' ) ); ?></span>
						<p class="kcs-muted"><?php echo esc_html( implode( ' · ', array( $c['pages'][ $p['pages'] ], $c['audience'][ $p['audience'] ], $c['device'][ $p['device'] ], $c['frequency'][ $p['frequency'] ] ) ) ); ?></p>
					</div>
					<div class="kca-actions">
						<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( KCS_Church_Admin::url( 'popups', array( 'popup' => $p['id'] ) ) . '#popup-form' ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a>
						<form method="post" class="kca-inline"><?php echo call_user_func( $fields, 'popup_toggle', 'popups', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="popup" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php echo esc_html( ! empty( $p['active'] ) ? __( 'Switch off', 'kingdom-church-suite' ) : __( 'Switch on', 'kingdom-church-suite' ) ); ?></button></form>
						<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this popup?', 'kingdom-church-suite' ) ); ?>');"><?php echo call_user_func( $fields, 'popup_delete', 'popups', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="popup" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Delete', 'kingdom-church-suite' ); ?></button></form>
					</div>
				</article>
			<?php endforeach; ?>
			<?php if ( ! $popups ) : ?><p class="kcs-muted"><?php esc_html_e( 'No popups yet. Create one above for an event, a crusade, a giving drive or a newsletter invitation.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
		</div>
		<?php
	}
}
