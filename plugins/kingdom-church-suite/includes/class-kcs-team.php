<?php
/**
 * Church Admin team & permissions: the church owner adds staff and chooses which sections of
 * Church Admin each one may open. Church administrators always have full access; staff with no
 * saved choice keep every staff section (as before permissions existed).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Team.
 */
class KCS_Team {

	const OPTION = 'kcs_sections'; // Per-site user option (update_user_option adds the site prefix).

	/**
	 * Sections the owner can give to staff, grouped as in the Church Admin menu.
	 *
	 * @return array group title => array( view => label ).
	 */
	public static function sections() {
		$out = array(
			__( 'People', 'kingdom-church-suite' )        => array(
				'partners'    => __( 'Partners', 'kingdom-church-suite' ),
				'prayers'     => __( 'Prayer requests', 'kingdom-church-suite' ),
				'visits'      => __( 'First-time visitors', 'kingdom-church-suite' ),
				'messages'    => __( 'Contact messages', 'kingdom-church-suite' ),
				'testimonies' => __( 'Testimonies', 'kingdom-church-suite' ),
			),
			__( 'Giving', 'kingdom-church-suite' )        => array(
				'payments'  => __( 'Giving received (receipts, export, delete failed)', 'kingdom-church-suite' ),
				'transfers' => __( 'Confirm payments (bank transfers & payment links)', 'kingdom-church-suite' ),
				'plans'     => __( 'Partnership plans', 'kingdom-church-suite' ),
			),
			__( 'Messages', 'kingdom-church-suite' )      => array(
				'notify'     => __( 'Message partners', 'kingdom-church-suite' ),
				'newsletter' => __( 'Newsletter', 'kingdom-church-suite' ),
				'word'       => __( 'Today’s Word', 'kingdom-church-suite' ),
				'pastors'    => __( 'Pastors & WhatsApp', 'kingdom-church-suite' ),
			),
			__( 'Website & app', 'kingdom-church-suite' ) => array(
				'content' => __( 'Pictures & text', 'kingdom-church-suite' ),
				'website' => __( 'Design & live stream', 'kingdom-church-suite' ),
				'slides'  => __( 'Announcement slider', 'kingdom-church-suite' ),
				'popups'  => __( 'Popups', 'kingdom-church-suite' ),
				'app'     => __( 'Church app & push notifications', 'kingdom-church-suite' ),
			),
		);
		/**
		 * Sections add-ons offer for staff permissions (e.g. Subscription, Custom domain).
		 *
		 * @param array $out group => view => label.
		 */
		return (array) apply_filters( 'kcs_team_sections', $out );
	}

	/**
	 * All grantable view slugs.
	 *
	 * @return string[]
	 */
	public static function all_views() {
		$all = array();
		foreach ( self::sections() as $views ) {
			$all = array_merge( $all, array_keys( $views ) );
		}
		return $all;
	}

	/**
	 * Saved sections of a staff member (null = never set = every staff section).
	 *
	 * @param int $user_id User.
	 * @return string[]|null
	 */
	public static function user_sections( $user_id ) {
		$v = get_user_option( self::OPTION, $user_id );
		return is_array( $v ) ? $v : null;
	}

	/**
	 * May the current user open a Church Admin section?
	 *
	 * @param string $view View slug.
	 * @return bool
	 */
	public static function allowed( $view ) {
		if ( current_user_can( 'manage_options' ) || in_array( $view, array( 'overview', 'search', '' ), true ) ) {
			return true;
		}
		$mine = self::user_sections( get_current_user_id() );
		if ( null === $mine || ! in_array( $view, self::all_views(), true ) ) {
			return true; // Not set yet, or a section permissions don't cover (other checks apply).
		}
		return in_array( $view, $mine, true );
	}

	/**
	 * The section(s) a Church Admin action belongs to ('' = owners only; null = unrestricted).
	 *
	 * @param string $do Action.
	 * @return string[]|null
	 */
	public static function action_views( $do ) {
		$map = array(
			'partner_'           => array( 'partners' ),
			'payment_complete'   => array( 'payments', 'transfers' ),
			'payment_reject'     => array( 'payments', 'transfers' ),
			'payment_verify'     => array( 'payments', 'transfers' ),
			'transfer_'          => array( 'transfers' ),
			'payment_'           => array( 'payments' ),
			'payments_'          => array( 'payments' ),
			'receipt_settings'   => array( 'payments' ),
			'prayer_'            => array( 'prayers' ),
			'visit_'             => array( 'visits' ),
			'message_'           => array( 'messages' ),
			'testimony'          => array( 'testimonies' ),
			'notify'             => array( 'notify' ),
			'announcement_'      => array( 'notify' ),
			'pastor_'            => array( 'pastors' ),
			'wa_chat_'           => array( 'pastors' ),
			'currency_save'      => array( 'plans' ),
			'plan_'              => array( 'plans' ),
			'website_save'       => array( 'website', 'content' ),
			'campaign_'          => array( 'newsletter' ),
			'design_'            => array( 'newsletter' ),
			'word_'              => array( 'word' ),
			'subscriber_'        => array( 'newsletter' ),
			'app_'               => array( 'app' ),
			'push_'              => array( 'app' ),
			'slide'              => array( 'slides' ),
			'popup_'             => array( 'popups' ),
		);
		foreach ( $map as $prefix => $views ) {
			if ( 0 === strpos( $do, $prefix ) ) {
				return $views;
			}
		}
		return null;
	}

	/**
	 * May the current user run a Church Admin action?
	 *
	 * @param string $do Action.
	 * @return bool
	 */
	public static function may( $do ) {
		$views = self::action_views( $do );
		if ( null === $views ) {
			return true; // Settings, pages and team actions check their own capabilities.
		}
		foreach ( $views as $v ) {
			if ( self::allowed( $v ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Church Admin → Team & permissions
	 * ------------------------------------------------------------------- */

	/**
	 * Staff of this church (not the owners).
	 *
	 * @return WP_User[]
	 */
	public static function staff() {
		return get_users( array( 'role__in' => array( 'kcs_church_admin', 'editor' ), 'orderby' => 'display_name' ) );
	}

	/**
	 * Owners (full access).
	 *
	 * @return WP_User[]
	 */
	public static function owners() {
		return get_users( array( 'role' => 'administrator', 'orderby' => 'display_name' ) );
	}

	/**
	 * Clean the ticked sections.
	 *
	 * @param array $d Posted data.
	 * @return string[]
	 */
	private static function posted_sections( $d ) {
		return array_values( array_intersect( self::all_views(), array_map( 'sanitize_key', (array) ( $d['sections'] ?? array() ) ) ) );
	}

	/**
	 * Handle a team action (owners only).
	 *
	 * @param string $do Action.
	 * @param array  $d  Posted data.
	 * @param int    $id User ID.
	 * @return array array( message, type ).
	 */
	public static function handle( $do, $d, $id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( __( 'Only church administrators can manage the team.', 'kingdom-church-suite' ), 'error' );
		}
		if ( 'team_add' === $do ) {
			return self::add( $d );
		}
		$user = get_userdata( $id );
		if ( ! $user || ! is_user_member_of_blog( $id ) || user_can( $user, 'manage_options' ) || get_current_user_id() === $id ) {
			return array( __( 'That team member cannot be changed here.', 'kingdom-church-suite' ), 'error' );
		}
		if ( 'team_save' === $do ) {
			update_user_option( $id, self::OPTION, self::posted_sections( $d ) );
			/* translators: %s: name */
			return array( sprintf( __( 'Permissions saved for %s.', 'kingdom-church-suite' ), $user->display_name ), 'success' );
		}
		if ( 'team_remove' === $do ) {
			delete_user_option( $id, self::OPTION );
			if ( is_multisite() ) {
				remove_user_from_blog( $id, get_current_blog_id() );
			} else {
				$user->set_role( 'subscriber' );
			}
			/* translators: %s: name */
			return array( sprintf( __( '%s can no longer open Church Admin.', 'kingdom-church-suite' ), $user->display_name ), 'success' );
		}
		return array( '', 'success' );
	}

	/**
	 * Add a staff member: an existing account joins this church, or a new account is created and
	 * emailed a link to set its password.
	 *
	 * @param array $d Posted data.
	 * @return array array( message, type ).
	 */
	private static function add( $d ) {
		$email = sanitize_email( $d['email'] ?? '' );
		$name  = sanitize_text_field( $d['name'] ?? '' );
		if ( ! is_email( $email ) ) {
			return array( __( 'Please enter a valid email address.', 'kingdom-church-suite' ), 'error' );
		}
		$user = get_user_by( 'email', $email );
		$new  = false;
		if ( $user && user_can( $user, 'manage_options' ) && is_user_member_of_blog( $user->ID ) ) {
			return array( __( 'That person is already a church administrator with full access.', 'kingdom-church-suite' ), 'error' );
		}
		if ( ! $user ) {
			$base  = sanitize_user( strtolower( strstr( $email, '@', true ) ), true );
			$base  = $base ? $base : 'staff';
			$login = $base;
			for ( $i = 2; username_exists( $login ); $i++ ) {
				$login = $base . $i;
			}
			$uid = is_multisite() ? wpmu_create_user( $login, wp_generate_password( 20 ), $email ) : wp_create_user( $login, wp_generate_password( 20 ), $email );
			if ( ! $uid || is_wp_error( $uid ) ) {
				return array( __( 'The account could not be created.', 'kingdom-church-suite' ), 'error' );
			}
			if ( $name ) {
				$parts = preg_split( '/\s+/', $name, 2 );
				wp_update_user( array( 'ID' => $uid, 'display_name' => $name, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '' ) );
			}
			$user = get_userdata( $uid );
			$new  = true;
		}
		if ( is_multisite() ) {
			add_user_to_blog( get_current_blog_id(), $user->ID, 'kcs_church_admin' );
		} else {
			$user->set_role( 'kcs_church_admin' );
		}
		update_user_option( $user->ID, self::OPTION, self::posted_sections( $d ) );
		self::welcome( get_userdata( $user->ID ), $new );
		/* translators: %s: email */
		return array( sprintf( $new ? __( 'Account created for %s. They have been emailed a link to set their password.', 'kingdom-church-suite' ) : __( '%s has been added to the team and emailed a link to Church Admin.', 'kingdom-church-suite' ), $email ), 'success' );
	}

	/**
	 * Welcome email with the Church Admin link (and a set-password link for new accounts).
	 *
	 * @param WP_User $user User.
	 * @param bool    $new  New account.
	 */
	private static function welcome( $user, $new ) {
		$church = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		$admin  = kcs_page_url( 'church_admin' );
		$body   = '<p>' . esc_html( sprintf( /* translators: 1: name, 2: church */ __( 'Hi %1$s, you have been added to the Church Admin team at %2$s.', 'kingdom-church-suite' ), $user->display_name, $church ) ) . '</p>';
		if ( $new ) {
			$key = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$set   = add_query_arg( array( 'action' => 'rp', 'key' => $key, 'login' => rawurlencode( $user->user_login ) ), site_url( 'wp-login.php', 'login' ) );
				$body .= '<p>' . esc_html__( 'First, choose your password:', 'kingdom-church-suite' ) . '</p><p><a class="btn" href="' . esc_url( $set ) . '">' . esc_html__( 'Set my password', 'kingdom-church-suite' ) . '</a></p>';
			}
			/* translators: %s: email */
			$body .= '<p>' . esc_html( sprintf( __( 'Then log in with your email address (%s).', 'kingdom-church-suite' ), $user->user_email ) ) . '</p>';
		}
		$body .= '<p><a class="btn" href="' . esc_url( $admin ) . '">' . esc_html__( 'Open Church Admin', 'kingdom-church-suite' ) . '</a></p>';
		/* translators: %s: church */
		KCS_Email::send( $user->user_email, sprintf( __( 'Your Church Admin access at %s', 'kingdom-church-suite' ), $church ), $body );
	}

	/**
	 * The section checkboxes.
	 *
	 * @param string[]|null $ticked Ticked sections (null = all).
	 */
	private static function checkboxes( $ticked ) {
		foreach ( self::sections() as $group => $views ) {
			echo '<fieldset class="kca-team__group"><legend>' . esc_html( $group ) . '</legend><div class="kca-chips">';
			foreach ( $views as $view => $label ) {
				echo '<label class="kca-chip"><input type="checkbox" name="sections[]" value="' . esc_attr( $view ) . '"' . checked( null === $ticked || in_array( $view, $ticked, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			echo '</div></fieldset>';
		}
	}

	/**
	 * Church Admin → Team & permissions.
	 *
	 * @param callable $fields Hidden fields builder ( $do, $view, $id ).
	 */
	public static function view( $fields ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'Only church administrators can manage the team.', 'kingdom-church-suite' ) . '</div>';
			return;
		}
		?>
		<details class="kca-card kcs-form kca-team__add"<?php echo self::staff() ? '' : ' open'; ?>>
			<summary><strong><?php esc_html_e( '+ Add a team member', 'kingdom-church-suite' ); ?></strong></summary>
			<form method="post" class="kcs-form">
				<?php echo call_user_func( $fields, 'team_add', 'team', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Full name', 'kingdom-church-suite' ); ?><input name="name" required autocomplete="off"></label>
					<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" name="email" required autocomplete="off"></label>
				</div>
				<p class="kcs-muted"><?php esc_html_e( 'Tick what they may open. They get an email with a link to set their password and open Church Admin. Settings, Pages, Subscription and Team stay with church administrators.', 'kingdom-church-suite' ); ?></p>
				<?php self::checkboxes( null ); ?>
				<button class="kcs-btn"><?php esc_html_e( 'Add to team', 'kingdom-church-suite' ); ?></button>
			</form>
		</details>

		<h3><?php esc_html_e( 'Church administrators (full access)', 'kingdom-church-suite' ); ?></h3>
		<div class="kca-stack">
			<?php foreach ( self::owners() as $u ) : ?>
				<article class="kca-card kca-item"><div><strong><?php echo esc_html( $u->display_name ); ?></strong><?php echo get_current_user_id() === $u->ID ? ' <span class="kca-pill is-on">' . esc_html__( 'You', 'kingdom-church-suite' ) . '</span>' : ''; ?><p class="kcs-muted"><?php echo esc_html( $u->user_email ); ?></p></div></article>
			<?php endforeach; ?>
		</div>

		<h3><?php esc_html_e( 'Staff', 'kingdom-church-suite' ); ?></h3>
		<div class="kca-stack">
			<?php foreach ( self::staff() as $u ) : ?>
				<?php $mine = self::user_sections( $u->ID ); ?>
				<details class="kca-card kca-team__member">
					<summary>
						<strong><?php echo esc_html( $u->display_name ); ?></strong>
						<span class="kcs-muted"><?php echo esc_html( $u->user_email ); ?></span>
						<span class="kca-pill"><?php echo esc_html( null === $mine ? __( 'All staff sections', 'kingdom-church-suite' ) : sprintf( /* translators: %d: number */ _n( '%d section', '%d sections', count( $mine ), 'kingdom-church-suite' ), count( $mine ) ) ); ?></span>
					</summary>
					<form method="post" class="kcs-form">
						<?php echo call_user_func( $fields, 'team_save', 'team', $u->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php self::checkboxes( $mine ); ?>
						<div class="kca-actions"><button class="kcs-btn"><?php esc_html_e( 'Save permissions', 'kingdom-church-suite' ); ?></button></div>
					</form>
					<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this person from the team? Their account stays, but they can no longer open Church Admin.', 'kingdom-church-suite' ) ); ?>')">
						<?php echo call_user_func( $fields, 'team_remove', 'team', $u->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Remove from team', 'kingdom-church-suite' ); ?></button>
					</form>
				</details>
			<?php endforeach; ?>
			<?php if ( ! self::staff() ) : ?><p class="kcs-muted"><?php esc_html_e( 'No staff yet. Add pastors, ushers, the finance secretary or the media team, and choose what each one may open.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
		</div>
		<?php
	}
}
