<?php
/**
 * Veltrix (email, SMS and WhatsApp marketing) for church sites.
 *
 * The "Veltrix for WordPress" plugin is network-activated by the platform owner and keeps its
 * settings per site, so each church connects its own Veltrix account. This class only sets the
 * plugin's platform constants and puts an "Email & SMS (Veltrix)" page in Church Admin; it never
 * touches the plugin's files or settings.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Veltrix integration.
 */
class KS_Veltrix {

	/**
	 * Platform constants, defined when this plugin loads (before Veltrix) unless wp-config.php
	 * already defines them.
	 */
	public static function define_constants() {
		if ( ! defined( 'VELTRIX_PLATFORM_NAME' ) ) {
			define( 'VELTRIX_PLATFORM_NAME', (string) ks_get( 'platform_name' ) );
		}
		$referral = trim( (string) ks_get( 'veltrix_referral' ) );
		if ( ! defined( 'VELTRIX_REFERRAL' ) && '' !== $referral ) {
			define( 'VELTRIX_REFERRAL', $referral );
		}
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		if ( is_main_site() ) {
			return;
		}
		add_filter( 'kcs_church_admin_views', array( __CLASS__, 'views' ) );
		add_filter( 'kcs_church_admin_groups', array( __CLASS__, 'groups' ) );
		add_filter( 'kcs_church_admin_description', array( __CLASS__, 'describe' ), 10, 2 );
		add_filter( 'kcs_church_admin_search_index', array( __CLASS__, 'search_index' ) );
		add_action( 'kcs_church_admin_view_veltrix', array( __CLASS__, 'view' ) );
	}

	/**
	 * Is the Veltrix plugin active on this church site (network-wide or on the site)?
	 *
	 * @return bool
	 */
	public static function active() {
		$plugins = array_merge( array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ), (array) get_option( 'active_plugins', array() ) );
		foreach ( $plugins as $file ) {
			if ( 0 === strpos( strtolower( (string) $file ), 'veltrix' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * May the current user manage the church's Veltrix connection? Uses the plugin's own capability.
	 *
	 * @return bool
	 */
	public static function can() {
		return self::active() && current_user_can( (string) apply_filters( 'veltrix_capability', 'manage_options' ) );
	}

	/**
	 * The plugin's settings page on this church site.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'options-general.php?page=veltrix' );
	}

	/**
	 * Add the view.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function views( $views ) {
		if ( self::can() ) {
			$views['veltrix'] = array( __( 'Email & SMS (Veltrix)', 'kingdom-saas' ), '✆' );
		}
		return $views;
	}

	/**
	 * Show it under Messages.
	 *
	 * @param array $groups Groups.
	 * @return array
	 */
	public static function groups( $groups ) {
		$key = __( 'Messages', 'kingdom-church-suite' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- same title as Church Suite's group.
		if ( isset( $groups[ $key ] ) ) {
			$groups[ $key ][] = 'veltrix';
		}
		return $groups;
	}

	/**
	 * Page description.
	 *
	 * @param string $text Text.
	 * @param string $view View.
	 * @return string
	 */
	public static function describe( $text, $view ) {
		return 'veltrix' === $view ? __( 'Connect your church’s own Veltrix account to send email, SMS and WhatsApp campaigns and deliver website emails.', 'kingdom-saas' ) : $text;
	}

	/**
	 * Dashboard search.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function search_index( $items ) {
		if ( self::can() ) {
			$items[] = array(
				't' => __( 'Email & SMS (Veltrix)', 'kingdom-saas' ),
				'd' => __( 'Messages', 'kingdom-church-suite' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'u' => add_query_arg( 'view', 'veltrix', kcs_page_url( 'church_admin' ) ),
				'k' => 'veltrix sms whatsapp email marketing bulk campaign contacts newsletter connect',
			);
		}
		return $items;
	}

	/**
	 * The Church Admin page: what Veltrix does, the steps, and the button to its settings.
	 */
	public static function view() {
		if ( ! self::can() ) {
			echo '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'Only church administrators can connect Veltrix.', 'kingdom-saas' ) . '</div>';
			return;
		}
		$steps = array(
			__( 'Press “Open Veltrix settings”, then “Connect with Veltrix”.', 'kingdom-saas' ),
			__( 'Sign in to Veltrix, or create a free account (the link is on the same page).', 'kingdom-saas' ),
			__( 'Press “Allow and connect”. You come back here automatically.', 'kingdom-saas' ),
			__( 'Press “Send test email to me”. When it arrives, tick “Send site emails through Veltrix” and save.', 'kingdom-saas' ),
			__( 'Optional: under “Contact sync”, add rules (for example “Users with the role: Member” → “Members” list), press Export, and tick “Automatically add new contacts”.', 'kingdom-saas' ),
			__( 'Optional: add [veltrix_subscribe] to any page for a newsletter sign-up form.', 'kingdom-saas' ),
		);
		?>
		<section class="kca-card">
			<h3><?php esc_html_e( 'Connect your Veltrix account', 'kingdom-saas' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'Veltrix sends your church’s email, SMS and WhatsApp campaigns. Your church uses its own Veltrix account, so your contacts, messages and billing stay yours.', 'kingdom-saas' ); ?></p>
			<ol class="ks-steps">
				<?php foreach ( $steps as $step ) : ?>
					<li><?php echo esc_html( $step ); ?></li>
				<?php endforeach; ?>
			</ol>
			<p><a class="kcs-btn" href="<?php echo esc_url( self::settings_url() ); ?>"><?php esc_html_e( 'Open Veltrix settings', 'kingdom-saas' ); ?></a></p>
			<p class="kcs-muted"><?php esc_html_e( 'Turning on “Send site emails through Veltrix” replaces the email settings in Settings → Email for receipts, reminders and password resets.', 'kingdom-saas' ); ?></p>
		</section>
		<?php
	}
}
