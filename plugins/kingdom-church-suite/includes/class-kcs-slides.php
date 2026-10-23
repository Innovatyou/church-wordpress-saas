<?php
/**
 * Announcement slider: slides the church writes in Church Admin, shown on the homepage and/or the
 * partner dashboard ([kcs_announcements where="home|dashboard"]).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slides.
 */
class KCS_Slides {

	const OPTION = 'kcs_slides';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'kcs_announcements', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * All slides, in order.
	 *
	 * @return array[]
	 */
	public static function all() {
		return array_values( array_filter( (array) get_option( self::OPTION, array() ), 'is_array' ) );
	}

	/**
	 * Slides to show now in a place.
	 *
	 * @param string $where home|dashboard.
	 * @return array[]
	 */
	public static function live( $where ) {
		$today = current_time( 'Y-m-d' );
		return array_values(
			array_filter(
				self::all(),
				function ( $s ) use ( $where, $today ) {
					return ! empty( $s['active'] )
						&& in_array( $s['where'] ?? 'both', array( 'both', $where ), true )
						&& ( empty( $s['start'] ) || $s['start'] <= $today )
						&& ( empty( $s['end'] ) || $s['end'] >= $today );
				}
			)
		);
	}

	/**
	 * Is the slider switched on for a place?
	 *
	 * @param string $where home|dashboard.
	 * @return bool
	 */
	public static function enabled( $where ) {
		return '1' === (string) kcs_get_option( 'slider_' . $where, '1' );
	}

	/**
	 * [kcs_announcements where="home"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts  = shortcode_atts( array( 'where' => 'home' ), $atts, 'kcs_announcements' );
		$where = 'dashboard' === $atts['where'] ? 'dashboard' : 'home';
		if ( ! self::enabled( $where ) ) {
			return '';
		}
		$slides = self::live( $where );
		if ( ! $slides ) {
			return '';
		}
		$seconds = max( 3, min( 20, (int) kcs_get_option( 'slider_seconds', 6 ) ) );
		ob_start();
		?>
		<div class="kcs-slider kcs-slider--<?php echo esc_attr( $where ); ?>" data-kcs-slider data-seconds="<?php echo (int) $seconds; ?>" role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Announcements', 'kingdom-church-suite' ); ?>">
			<div class="kcs-slider__track" data-track tabindex="0">
				<?php foreach ( $slides as $i => $s ) : ?>
					<article class="kcs-slide<?php echo ! empty( $s['image'] ) ? ' has-image' : ''; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: number, 2: total */ __( '%1$d of %2$d', 'kingdom-church-suite' ), $i + 1, count( $slides ) ) ); ?>">
						<?php if ( ! empty( $s['image'] ) ) : ?>
							<img class="kcs-slide__img" src="<?php echo esc_url( $s['image'] ); ?>" alt="" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>">
						<?php endif; ?>
						<div class="kcs-slide__body">
							<?php if ( ! empty( $s['label'] ) ) : ?><span class="kcs-slide__label"><?php echo esc_html( $s['label'] ); ?></span><?php endif; ?>
							<h3 class="kcs-slide__title"><?php echo esc_html( $s['title'] ); ?></h3>
							<?php if ( ! empty( $s['text'] ) ) : ?><p class="kcs-slide__text"><?php echo nl2br( esc_html( $s['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p><?php endif; ?>
							<?php if ( ! empty( $s['btn_text'] ) && ! empty( $s['btn_url'] ) ) : ?>
								<a class="kcs-btn kcs-slide__btn" href="<?php echo esc_url( $s['btn_url'] ); ?>"><?php echo esc_html( $s['btn_text'] ); ?></a>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $slides ) > 1 ) : ?>
				<button type="button" class="kcs-slider__nav kcs-slider__nav--prev" data-prev aria-label="<?php esc_attr_e( 'Previous announcement', 'kingdom-church-suite' ); ?>">‹</button>
				<button type="button" class="kcs-slider__nav kcs-slider__nav--next" data-next aria-label="<?php esc_attr_e( 'Next announcement', 'kingdom-church-suite' ); ?>">›</button>
				<div class="kcs-slider__dots" data-dots>
					<?php foreach ( $slides as $i => $s ) : ?>
						<button type="button" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: number */ __( 'Show announcement %d', 'kingdom-church-suite' ), $i + 1 ) ); ?>"<?php echo 0 === $i ? ' aria-current="true"' : ''; ?>></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Church Admin
	 * ------------------------------------------------------------------- */

	/**
	 * Clean a slide from the form.
	 *
	 * @param array $d  Posted data.
	 * @param array $old Existing slide.
	 * @return array
	 */
	private static function from_post( $d, $old = array() ) {
		$date = function ( $v ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? (string) $v : '';
		};
		return array(
			'id'       => $old['id'] ?? substr( md5( uniqid( '', true ) ), 0, 10 ),
			'label'    => sanitize_text_field( $d['label'] ?? '' ),
			'title'    => sanitize_text_field( $d['title'] ?? '' ),
			'text'     => sanitize_textarea_field( $d['text'] ?? '' ),
			'image'    => esc_url_raw( $d['image'] ?? '' ),
			'btn_text' => sanitize_text_field( $d['btn_text'] ?? '' ),
			'btn_url'  => esc_url_raw( $d['btn_url'] ?? '' ),
			'where'    => in_array( $d['where'] ?? '', array( 'both', 'home', 'dashboard' ), true ) ? $d['where'] : 'both',
			'start'    => $date( $d['start'] ?? '' ),
			'end'      => $date( $d['end'] ?? '' ),
			'active'   => ! empty( $d['active'] ),
		);
	}

	/**
	 * Handle an action.
	 *
	 * @param string $do Action.
	 * @param array  $d  Posted data.
	 * @return array array( message, type ).
	 */
	public static function handle( $do, $d ) {
		$slides = self::all();
		$sid    = sanitize_key( $d['slide'] ?? '' );
		$index  = null;
		foreach ( $slides as $i => $s ) {
			if ( $s['id'] === $sid ) {
				$index = $i;
			}
		}
		switch ( $do ) {
			case 'slide_save':
				$slide = self::from_post( $d, null === $index ? array() : $slides[ $index ] );
				if ( '' === $slide['title'] ) {
					return array( __( 'Please give the announcement a title.', 'kingdom-church-suite' ), 'error' );
				}
				if ( null === $index ) {
					$slides[] = $slide;
				} else {
					$slides[ $index ] = $slide;
				}
				$msg = __( 'Announcement saved.', 'kingdom-church-suite' );
				break;
			case 'slide_delete':
				if ( null !== $index ) {
					array_splice( $slides, $index, 1 );
				}
				$msg = __( 'Announcement deleted.', 'kingdom-church-suite' );
				break;
			case 'slide_move':
				$to = $index + ( 'up' === ( $d['dir'] ?? '' ) ? -1 : 1 );
				if ( null !== $index && isset( $slides[ $to ] ) ) {
					list( $slides[ $index ], $slides[ $to ] ) = array( $slides[ $to ], $slides[ $index ] );
				}
				$msg = __( 'Order saved.', 'kingdom-church-suite' );
				break;
			case 'slider_settings':
				$settings                   = get_option( 'kcs_settings', array() );
				$settings['slider_home']      = empty( $d['slider_home'] ) ? '0' : '1';
				$settings['slider_dashboard'] = empty( $d['slider_dashboard'] ) ? '0' : '1';
				$settings['slider_seconds']   = (string) max( 3, min( 20, absint( $d['slider_seconds'] ?? 6 ) ) );
				update_option( 'kcs_settings', $settings );
				return array( __( 'Slider settings saved.', 'kingdom-church-suite' ), 'success' );
			default:
				return array( '', 'success' );
		}
		update_option( self::OPTION, array_values( $slides ), false );
		return array( $msg, 'success' );
	}

	/**
	 * Church Admin → Announcement slider.
	 *
	 * @param callable $fields Hidden fields builder ( $do, $view, $id ).
	 */
	public static function view( $fields ) {
		$slides = self::all();
		$edit   = isset( $_GET['slide'] ) ? sanitize_key( $_GET['slide'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where  = array( 'both' => __( 'Homepage and partner dashboard', 'kingdom-church-suite' ), 'home' => __( 'Homepage only', 'kingdom-church-suite' ), 'dashboard' => __( 'Partner dashboard only', 'kingdom-church-suite' ) );
		$cur    = array();
		foreach ( $slides as $s ) {
			if ( $s['id'] === $edit ) {
				$cur = $s;
			}
		}
		$upload = current_user_can( 'upload_files' );
		?>
		<form method="post" class="kca-card kcs-form">
			<?php echo call_user_func( $fields, 'slider_settings', 'slides', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Where the slider shows', 'kingdom-church-suite' ); ?></h3>
			<label class="kcs-check"><input type="checkbox" name="slider_home" value="1" <?php checked( self::enabled( 'home' ) ); ?>> <?php esc_html_e( 'Show on the homepage (under the banner)', 'kingdom-church-suite' ); ?></label>
			<label class="kcs-check"><input type="checkbox" name="slider_dashboard" value="1" <?php checked( self::enabled( 'dashboard' ) ); ?>> <?php esc_html_e( 'Show on the partner dashboard', 'kingdom-church-suite' ); ?></label>
			<label><?php esc_html_e( 'Seconds per announcement', 'kingdom-church-suite' ); ?><input type="number" name="slider_seconds" min="3" max="20" value="<?php echo (int) kcs_get_option( 'slider_seconds', 6 ); ?>"></label>
			<button class="kcs-btn"><?php esc_html_e( 'Save', 'kingdom-church-suite' ); ?></button>
		</form>

		<form method="post" class="kca-card kcs-form" id="slide-form">
			<?php echo call_user_func( $fields, 'slide_save', 'slides', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="slide" value="<?php echo esc_attr( $cur['id'] ?? '' ); ?>">
			<h3><?php echo esc_html( $cur ? __( 'Edit announcement', 'kingdom-church-suite' ) : __( 'Add an announcement', 'kingdom-church-suite' ) ); ?></h3>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Small label (optional)', 'kingdom-church-suite' ); ?><input name="label" value="<?php echo esc_attr( $cur['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. This Sunday', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Title', 'kingdom-church-suite' ); ?><input name="title" required value="<?php echo esc_attr( $cur['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Crusade in Uyo, 12–14 December', 'kingdom-church-suite' ); ?>"></label>
			</div>
			<label><?php esc_html_e( 'Text (optional)', 'kingdom-church-suite' ); ?><textarea name="text" rows="3"><?php echo esc_textarea( $cur['text'] ?? '' ); ?></textarea></label>
			<div class="kca-image" data-kcs-media-field>
				<span class="kca-image__label"><?php esc_html_e( 'Picture (optional)', 'kingdom-church-suite' ); ?></span>
				<img class="kca-image__preview" src="<?php echo esc_url( $cur['image'] ?? '' ); ?>" alt=""<?php echo empty( $cur['image'] ) ? ' hidden' : ''; ?>>
				<input type="<?php echo $upload ? 'hidden' : 'url'; ?>" name="image" value="<?php echo esc_attr( $cur['image'] ?? '' ); ?>"<?php echo $upload ? ' data-kcs-media="url"' : ' placeholder="https://…/photo.jpg"'; ?>>
			</div>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Button text (optional)', 'kingdom-church-suite' ); ?><input name="btn_text" value="<?php echo esc_attr( $cur['btn_text'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Register now', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Button link', 'kingdom-church-suite' ); ?><input type="url" name="btn_url" value="<?php echo esc_attr( $cur['btn_url'] ?? '' ); ?>" placeholder="https://"></label>
				<label><?php esc_html_e( 'Show on', 'kingdom-church-suite' ); ?><select name="where"><?php foreach ( $where as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $cur['where'] ?? 'both', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'From (optional)', 'kingdom-church-suite' ); ?><input type="date" name="start" value="<?php echo esc_attr( $cur['start'] ?? '' ); ?>"></label>
					<label><?php esc_html_e( 'Until (optional)', 'kingdom-church-suite' ); ?><input type="date" name="end" value="<?php echo esc_attr( $cur['end'] ?? '' ); ?>"></label>
				</div>
			</div>
			<label class="kcs-check"><input type="checkbox" name="active" value="1" <?php checked( $cur ? ! empty( $cur['active'] ) : true ); ?>> <?php esc_html_e( 'Show this announcement', 'kingdom-church-suite' ); ?></label>
			<div class="kca-actions">
				<button class="kcs-btn"><?php esc_html_e( 'Save announcement', 'kingdom-church-suite' ); ?></button>
				<?php if ( $cur ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( KCS_Church_Admin::url( 'slides' ) ); ?>"><?php esc_html_e( 'Cancel', 'kingdom-church-suite' ); ?></a><?php endif; ?>
			</div>
		</form>

		<div class="kca-stack">
			<?php foreach ( $slides as $i => $s ) : ?>
				<?php
				$today = current_time( 'Y-m-d' );
				$state = empty( $s['active'] ) ? __( 'Hidden', 'kingdom-church-suite' ) : ( ! empty( $s['end'] ) && $s['end'] < $today ? __( 'Ended', 'kingdom-church-suite' ) : ( ! empty( $s['start'] ) && $s['start'] > $today ? __( 'Scheduled', 'kingdom-church-suite' ) : __( 'Showing', 'kingdom-church-suite' ) ) );
				?>
				<article class="kca-card kca-item">
					<div>
						<strong><?php echo esc_html( $s['title'] ); ?></strong>
						<span class="kca-pill<?php echo __( 'Showing', 'kingdom-church-suite' ) === $state ? ' is-on' : ''; ?>"><?php echo esc_html( $state ); ?></span>
						<p class="kcs-muted"><?php echo esc_html( $where[ $s['where'] ?? 'both' ] . ( $s['start'] || $s['end'] ? ' · ' . trim( $s['start'] . ' → ' . $s['end'], ' →' ) : '' ) ); ?></p>
					</div>
					<div class="kca-actions">
						<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( KCS_Church_Admin::url( 'slides', array( 'slide' => $s['id'] ) ) . '#slide-form' ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a>
						<?php foreach ( array( 'up' => '↑', 'down' => '↓' ) as $dir => $arrow ) : ?>
							<?php if ( ( 'up' === $dir && $i > 0 ) || ( 'down' === $dir && $i < count( $slides ) - 1 ) ) : ?>
								<form method="post" class="kca-inline"><?php echo call_user_func( $fields, 'slide_move', 'slides', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="slide" value="<?php echo esc_attr( $s['id'] ); ?>"><input type="hidden" name="dir" value="<?php echo esc_attr( $dir ); ?>"><button class="kcs-btn kcs-btn--ghost" aria-label="<?php echo esc_attr( 'up' === $dir ? __( 'Move up', 'kingdom-church-suite' ) : __( 'Move down', 'kingdom-church-suite' ) ); ?>"><?php echo esc_html( $arrow ); ?></button></form>
							<?php endif; ?>
						<?php endforeach; ?>
						<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this announcement?', 'kingdom-church-suite' ) ); ?>');"><?php echo call_user_func( $fields, 'slide_delete', 'slides', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="slide" value="<?php echo esc_attr( $s['id'] ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Delete', 'kingdom-church-suite' ); ?></button></form>
					</div>
				</article>
			<?php endforeach; ?>
			<?php if ( ! $slides ) : ?><p class="kcs-muted"><?php esc_html_e( 'No announcements yet. Add your first one above: it slides across the top of your homepage and partner dashboard.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
		</div>
		<?php
	}
}
