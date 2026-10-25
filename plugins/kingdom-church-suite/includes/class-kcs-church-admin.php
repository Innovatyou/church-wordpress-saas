<?php
/**
 * Front-end church admin dashboard ([kcs_church_admin]).
 *
 * Church staff manage partners, payments, prayer requests, visits, testimonies,
 * partner notifications and pastors' WhatsApp contacts without wp-admin.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Church admin dashboard.
 */
class KCS_Church_Admin {

	/**
	 * Flash message.
	 *
	 * @var array|null
	 */
	private static $flash = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'kcs_church_admin', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'admin_init', array( __CLASS__, 'keep_out_of_wp_admin' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Views (slug => [label, icon]).
	 *
	 * @return array
	 */
	public static function views() {
		/**
		 * Filter the Church Admin sections. Add-ons (such as the SaaS billing screen) add their own
		 * views here and render them on the `kcs_church_admin_view_{slug}` action.
		 *
		 * @param array $views slug => array( label, icon ).
		 */
		$views = apply_filters( 'kcs_church_admin_views', array(
			'overview'    => array( __( 'Home', 'kingdom-church-suite' ), '⌂' ),
			'partners'    => array( __( 'Partners', 'kingdom-church-suite' ), '♥' ),
			'plans'       => array( __( 'Partnership plans', 'kingdom-church-suite' ), '◈' ),
			'payments'    => array( __( 'Giving received', 'kingdom-church-suite' ), '₦' ),
			'transfers'   => array( __( 'Confirm payments', 'kingdom-church-suite' ), '⇄' ),
			'prayers'     => array( __( 'Prayer requests', 'kingdom-church-suite' ), '✦' ),
			'visits'      => array( __( 'First-time visitors', 'kingdom-church-suite' ), '☺' ),
			'messages'    => array( __( 'Contact messages', 'kingdom-church-suite' ), '✉' ),
			'testimonies' => array( __( 'Testimonies', 'kingdom-church-suite' ), '❝' ),
			'notify'      => array( __( 'Message partners', 'kingdom-church-suite' ), '✉' ),
			'pastors'     => array( __( 'Pastors & WhatsApp', 'kingdom-church-suite' ), '☎' ),
			'website'     => array( __( 'Design & live stream', 'kingdom-church-suite' ), '▣' ),
			'content'     => array( __( 'Pictures & text', 'kingdom-church-suite' ), '▧' ),
			'newsletter'  => array( __( 'Newsletter', 'kingdom-church-suite' ), '✎' ),
			'word'        => array( __( 'Today’s Word', 'kingdom-church-suite' ), '☀' ),
			'app'         => array( __( 'Church app', 'kingdom-church-suite' ), '▤' ),
			'slides'      => array( __( 'Announcement slider', 'kingdom-church-suite' ), '⇆' ),
			'popups'      => array( __( 'Popups', 'kingdom-church-suite' ), '◰' ),
		) + ( current_user_can( 'edit_pages' ) ? array( 'pages' => array( __( 'Pages', 'kingdom-church-suite' ), '❐' ) ) : array() ) + ( current_user_can( 'manage_options' ) ? array( 'settings' => array( __( 'Settings', 'kingdom-church-suite' ), '⚙' ), 'team' => array( __( 'Team & permissions', 'kingdom-church-suite' ), '☷' ), 'security' => array( __( 'Security', 'kingdom-church-suite' ), '⛨' ) ) : array() ) );
		// Staff only see the sections the church owner gave them (Church Admin → Team & permissions).
		return array_filter( $views, array( 'KCS_Team', 'allowed' ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Menu groups (title => view slugs). Views added by add-ons that are not listed go under Account.
	 *
	 * @return array
	 */
	public static function groups() {
		return (array) apply_filters(
			'kcs_church_admin_groups',
			array(
				''                                          => array( 'overview' ),
				__( 'People', 'kingdom-church-suite' )      => array( 'partners', 'prayers', 'visits', 'messages', 'testimonies' ),
				__( 'Giving', 'kingdom-church-suite' )      => array( 'transfers', 'payments', 'plans' ),
				__( 'Messages', 'kingdom-church-suite' )    => array( 'notify', 'newsletter', 'word', 'pastors' ),
				__( 'Website & app', 'kingdom-church-suite' ) => array( 'content', 'website', 'pages', 'slides', 'popups', 'app' ),
				__( 'Account', 'kingdom-church-suite' )     => array( 'settings', 'team', 'security', 'subscription', 'domain' ),
			)
		);
	}

	/**
	 * One plain sentence under each page title: what the page is for.
	 *
	 * @param string $view View.
	 * @return string
	 */
	private static function describe( $view ) {
		$d = array(
			'partners'     => __( 'Everyone who has joined your partnership: their plan, payments and contact details.', 'kingdom-church-suite' ),
			'prayers'      => __( 'Prayer requests sent from your website. Mark them as prayed for when done.', 'kingdom-church-suite' ),
			'visits'       => __( 'People who booked a visit. Call or WhatsApp them to welcome them.', 'kingdom-church-suite' ),
			'messages'     => __( 'Messages sent from the form on your Contact Us page.', 'kingdom-church-suite' ),
			'testimonies'  => __( 'Testimonies shared by members. Approve them to show them on your website.', 'kingdom-church-suite' ),
			'transfers'    => __( 'Givers who paid by bank transfer or your payment link. Check your bank or provider dashboard, then confirm or reject.', 'kingdom-church-suite' ),
			'payments'     => __( 'Every tithe, offering and partnership payment received online.', 'kingdom-church-suite' ),
			'plans'        => __( 'The partnership plans people can choose, their prices and currencies.', 'kingdom-church-suite' ),
			'notify'       => __( 'Send an announcement to your partners by email and on their dashboard.', 'kingdom-church-suite' ),
			'newsletter'   => __( 'Write and send email newsletters to your subscribers.', 'kingdom-church-suite' ),
			'word'         => __( 'A short daily devotional: write ahead, pick the day, and it shows on your website and app — and can go to subscribers by email each morning.', 'kingdom-church-suite' ),
			'pastors'      => __( 'The WhatsApp chat button on your website and the pastors partners can reach.', 'kingdom-church-suite' ),
			'content'      => __( 'Change your logo, photos, homepage words and contact details.', 'kingdom-church-suite' ),
			'website'      => __( 'Colours, fonts, homepage banner, live stream and which sections show.', 'kingdom-church-suite' ),
			'app'          => __( 'Your church app members install on their phones, and push notifications.', 'kingdom-church-suite' ),
			'slides'       => __( 'Announcements that slide across the top of your homepage and partner dashboard.', 'kingdom-church-suite' ),
			'popups'       => __( 'A popup for an event, crusade or giving drive, with rules for the pages, people and devices that see it and how often.', 'kingdom-church-suite' ),
			'pages'        => __( 'Hide or show any page, add new pages with the page builder or your own HTML & CSS, and choose what is in the menu.', 'kingdom-church-suite' ),
			'settings'     => __( 'Church details, service times, payment methods, emails and more.', 'kingdom-church-suite' ),
			'team'         => __( 'Add pastors and staff to Church Admin and choose which sections each one may open.', 'kingdom-church-suite' ),
			'security'     => __( 'Protection against bots and people testing stolen cards: giving limits, blocked devices, the “I am human” check and login lockouts.', 'kingdom-church-suite' ),
			'subscription' => __( 'Your plan for this website and its payments.', 'kingdom-church-suite' ),
			'domain'       => __( 'Use your own web address, such as yourchurch.org.', 'kingdom-church-suite' ),
		);
		return (string) apply_filters( 'kcs_church_admin_description', $d[ $view ] ?? '', $view );
	}

	/**
	 * Is the current user church staff?
	 *
	 * @return bool
	 */
	public static function can() {
		return current_user_can( 'manage_kcs' );
	}

	/**
	 * Church Admin users never see wp-admin.
	 */
	public static function keep_out_of_wp_admin() {
		global $pagenow;
		// File downloads (exports, receipts) and media uploads go through these wp-admin files.
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || in_array( $pagenow, array( 'admin-post.php', 'async-upload.php' ), true ) ) {
			return;
		}
		$user = wp_get_current_user();
		if ( in_array( 'kcs_church_admin', (array) $user->roles, true ) && ! current_user_can( 'edit_posts' ) && ! isset( $_GET['kcs_media'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( kcs_page_url( 'church_admin' ) );
			exit;
		}
	}

	/**
	 * Send church staff to their dashboard after login.
	 *
	 * @param string           $redirect  Redirect.
	 * @param string           $requested Requested.
	 * @param WP_User|WP_Error $user      User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! (int) kcs_get_option( 'page_church_admin', 0 ) ) {
			return $redirect;
		}
		if ( in_array( 'kcs_church_admin', (array) $user->roles, true ) ) {
			return kcs_page_url( 'church_admin' );
		}
		// Church owners and staff use the new dashboard too, unless they asked for a particular wp-admin page.
		// The platform owner (super admin) keeps wp-admin.
		$default = '' === (string) $requested || in_array( untrailingslashit( (string) $requested ), array( untrailingslashit( admin_url() ), untrailingslashit( admin_url( 'index.php' ) ) ), true );
		if ( $default && user_can( $user, 'manage_kcs' ) && ! is_super_admin( $user->ID ) ) {
			return kcs_page_url( 'church_admin' );
		}
		return $redirect;
	}

	/**
	 * Hide the toolbar for church admins.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		$user = wp_get_current_user();
		if ( in_array( 'kcs_church_admin', (array) $user->roles, true ) && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		// The new dashboard is a full app: no WordPress toolbar on it (the platform owner keeps theirs).
		$page = (int) kcs_get_option( 'page_church_admin', 0 );
		if ( $page && ! is_admin() && did_action( 'wp' ) && is_page( $page ) && ! is_super_admin() ) {
			return false;
		}
		return $show;
	}

	/**
	 * Styles on the dashboard page.
	 */
	public static function assets() {
		if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'kcs_church_admin' ) ) {
			wp_enqueue_style( 'kcs-church-admin', KCS_URL . 'assets/css/church-admin.css', array( 'kcs-public' ), KCS_VERSION );
			if ( current_user_can( 'upload_files' ) ) {
				wp_enqueue_media();
			}
			wp_enqueue_script( 'kcs-church-admin', KCS_URL . 'assets/js/church-admin.js', array(), KCS_VERSION, true );
			wp_localize_script(
				'kcs-church-admin',
				'kcsChurchAdmin',
				array(
					'choose' => __( 'Choose picture', 'kingdom-church-suite' ),
					'change' => __( 'Change', 'kingdom-church-suite' ),
					'remove' => __( 'Remove', 'kingdom-church-suite' ),
					'title'  => __( 'Choose a picture', 'kingdom-church-suite' ),
					'use'    => __( 'Use this picture', 'kingdom-church-suite' ),
					'chooseVideo' => __( 'Upload or choose video', 'kingdom-church-suite' ),
					'titleVideo'  => __( 'Choose a video', 'kingdom-church-suite' ),
					'useVideo'    => __( 'Use this video', 'kingdom-church-suite' ),
					'replaceDesign' => __( 'Replace your message with this design?', 'kingdom-church-suite' ),
					'rest'          => esc_url_raw( rest_url( 'kcs/v1/' ) ),
					'restNonce'     => wp_create_nonce( 'wp_rest' ),
					'checking'      => __( 'Checking pending payments with the gateways…', 'kingdom-church-suite' ),
					/* translators: %s: time */
					'checked'       => __( 'Pending payments are checked automatically · last checked %s', 'kingdom-church-suite' ),
					/* translators: %d: count */
					'confirmed'     => __( '%d payment(s) confirmed by the gateway just now.', 'kingdom-church-suite' ),
					'ajax'          => admin_url( 'admin-ajax.php' ),
					'playNonce'     => wp_create_nonce( 'kcs_play_picture' ),
					'pics'          => array(
						'title'   => __( 'Pictures in this email', 'kingdom-church-suite' ),
						'help'    => __( 'Click “Change” to put your own photo in its place. Message pictures get a play button automatically.', 'kingdom-church-suite' ),
						'none'    => __( 'No pictures yet. Pick a design above, or use “Add Media” to add one.', 'kingdom-church-suite' ),
						'visual'  => __( 'Switch to the “Visual” tab to change pictures.', 'kingdom-church-suite' ),
						'change'  => __( 'Change', 'kingdom-church-suite' ),
						'remove'  => __( 'Remove', 'kingdom-church-suite' ),
						'show'    => __( 'Show', 'kingdom-church-suite' ),
						'video'   => __( 'Message picture (play button)', 'kingdom-church-suite' ),
						'logo'    => __( 'Logo', 'kingdom-church-suite' ),
						'picture' => __( 'Picture', 'kingdom-church-suite' ),
						'working' => __( 'Adding the play button…', 'kingdom-church-suite' ),
						'confirm' => __( 'Remove this picture from the email?', 'kingdom-church-suite' ),
					),
				)
			);
		}
	}

	/**
	 * URL of a view.
	 *
	 * @param string $view View.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $view = 'overview', $args = array() ) {
		return add_query_arg( array_merge( array( 'view' => $view ), $args ), kcs_page_url( 'church_admin' ) );
	}

	/* =====================================================================
	 * Actions
	 * =================================================================== */

	/**
	 * Handle POST actions (PRG pattern).
	 */
	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['kcs_admin_do'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		if ( ! self::can() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-church-suite' ), 403 );
		}
		if ( ! isset( $_POST['_kcs_admin'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kcs_admin'] ), 'kcs_church_admin' ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'kingdom-church-suite' ), 403 );
		}
		global $wpdb;
		$d    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$do   = sanitize_key( $d['kcs_admin_do'] );
		$view = sanitize_key( $d['view'] ?? 'overview' );
		$id   = absint( $d['id'] ?? 0 );
		$msg  = __( 'Saved.', 'kingdom-church-suite' );
		$type = 'success';
		if ( ! KCS_Team::may( $do ) ) {
			set_transient( 'kcs_admin_flash_' . get_current_user_id(), array( __( 'You do not have permission for that section. Ask a church administrator.', 'kingdom-church-suite' ), 'error' ), 60 );
			wp_safe_redirect( self::url() );
			exit;
		}

		switch ( $do ) {
			case 'partner_save':
				$data    = array_intersect_key( $d, array_flip( array( 'first_name', 'last_name', 'email', 'phone', 'country', 'city', 'status', 'next_due_date', 'notes' ) ) );
				$partner = KCS_Partners::get( $id );
				$moving  = $partner && ! empty( $d['plan_id'] ) && (int) $d['plan_id'] !== (int) $partner->plan_id;
				$region  = ! empty( $d['region'] ) ? kcs_sanitize_region( $d['region'] ) : '';
				$switch  = $partner && $region && kcs_currency_region( $partner->currency ) !== $region;
				$cur     = $partner && $partner->plan_id ? KCS_Plans::get( $partner->plan_id ) : null;
				// A fixed-price plan is re-priced in the new currency; otherwise only the currency changes.
				if ( $switch && ! $moving && ( ! $cur || KCS_Plans::is_custom( $cur ) ) ) {
					$data['currency'] = kcs_region_currency( $region );
					$switch           = false;
				}
				if ( ! $moving && ! $switch && isset( $d['amount'] ) ) {
					$data['amount'] = $d['amount'];
				}
				KCS_Partners::update( $id, $data );
				if ( $moving || $switch ) {
					$r = KCS_Partners::change_plan( KCS_Partners::get( $id ), $moving ? (int) $d['plan_id'] : (int) $partner->plan_id, (float) ( $d['amount'] ?? $partner->amount ), sanitize_key( $d['frequency'] ?? '' ), $region );
					if ( is_wp_error( $r ) ) {
						$msg  = $r->get_error_message();
						$type = 'error';
					}
				}
				$view = 'partners';
				break;

			case 'partner_delete':
				$partner = KCS_Partners::get( $id );
				if ( ! current_user_can( 'manage_options' ) || ! $partner ) {
					$msg  = __( 'Only church administrators can delete partners.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$login = ! empty( $d['delete_login'] ) ? self::partner_login_removable( $partner ) : null;
				KCS_Partners::delete( $id );
				if ( $login ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					if ( is_multisite() ) {
						remove_user_from_blog( $login->ID, get_current_blog_id() );
					} else {
						wp_delete_user( $login->ID );
					}
				}
				/* translators: %s: partner name */
				$msg  = sprintf( __( '%s was deleted. Their past payments are kept in Giving received.', 'kingdom-church-suite' ), trim( $partner->first_name . ' ' . $partner->last_name ) );
				$view = 'partners';
				$d['return'] = self::url( 'partners' );
				break;

			case 'partner_status':
				KCS_Partners::update( $id, array( 'status' => sanitize_key( $d['status'] ?? '' ) ) );
				$msg = __( 'Partner status updated.', 'kingdom-church-suite' );
				break;

			case 'payment_complete':
				$p = KCS_Payments::get( $id );
				if ( $p ) {
					$result = KCS_Transfers::confirm( $p );
					if ( is_wp_error( $result ) ) {
						$msg  = $result->get_error_message();
						$type = 'error';
					} elseif ( 'completed' === $result ) {
						$msg = __( 'Payment marked as received. Receipt sent and partner unlocked.', 'kingdom-church-suite' );
					} else {
						$msg  = __( 'This payment was already handled.', 'kingdom-church-suite' );
						$type = 'error';
					}
				}
				break;

			case 'payment_reject':
				$p = KCS_Payments::get( $id );
				if ( $p ) {
					$result = KCS_Transfers::reject( $p, $d['reason'] ?? '' );
					if ( is_wp_error( $result ) ) {
						$msg  = $result->get_error_message();
						$type = 'error';
					} else {
						$msg = __( 'Transfer rejected. The giver has been emailed.', 'kingdom-church-suite' );
					}
				}
				break;

			case 'transfer_settings_save':
				if ( ! current_user_can( 'manage_options' ) ) {
					$msg  = __( 'Only church administrators can change bank transfer settings.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$accounts = KCS_Gateway_Bank_Transfer::sanitize_accounts( $d['accounts'] ?? array() );
				$enable   = ! empty( $d['enabled'] );
				$settings = get_option( 'kcs_settings', array() );
				$settings['bank_transfer_accounts']        = $accounts;
				$settings['bank_transfer_enabled']         = $enable && $accounts ? '1' : '0';
				$settings['bank_transfer_for_partnership'] = ! empty( $d['for_partnership'] ) ? '1' : '0';
				$settings['bank_transfer_for_giving']      = ! empty( $d['for_giving'] ) ? '1' : '0';
				$settings['bank_transfer_confirm_by']      = 'staff' === ( $d['confirm_by'] ?? '' ) ? 'staff' : 'admins';
				$settings['bank_transfer_note']            = sanitize_textarea_field( (string) ( $d['note'] ?? '' ) );
				$settings['bank_transfer_display_title']   = sanitize_text_field( (string) ( $d['display_title'] ?? '' ) );
				update_option( 'kcs_settings', $settings );
				if ( $enable && ! $accounts ) {
					$msg  = __( 'Add at least one bank account to turn on bank transfers.', 'kingdom-church-suite' );
					$type = 'error';
				} elseif ( $enable && '0' === $settings['bank_transfer_for_partnership'] && '0' === $settings['bank_transfer_for_giving'] ) {
					$msg  = __( 'Saved, but bank transfer is not offered for anything yet. Tick partnership and/or giving.', 'kingdom-church-suite' );
					$type = 'error';
				} else {
					$msg = $enable ? __( 'Bank transfers are on. Givers will see your account details.', 'kingdom-church-suite' ) : __( 'Bank transfer settings saved (turned off).', 'kingdom-church-suite' );
				}
				$view = 'transfers';
				break;

			case 'payment_send_receipt':
				$p    = KCS_Payments::get( $id );
				$ok   = $p && KCS_Receipts::send( $p );
				$msg  = $ok ? sprintf( /* translators: %s: email */ __( 'Receipt sent to %s.', 'kingdom-church-suite' ), $p->email ) : __( 'The receipt could not be sent. Check the giver’s email address and your email settings.', 'kingdom-church-suite' );
				$type = $ok ? 'success' : 'error';
				$view = 'payments';
				break;

			case 'payment_send_retry':
				$p    = KCS_Payments::get( $id );
				$ok   = $p && KCS_Receipts::send_retry( $p );
				$msg  = $ok ? sprintf( /* translators: %s: email */ __( '“Try again” email sent to %s.', 'kingdom-church-suite' ), $p->email ) : __( 'The email could not be sent.', 'kingdom-church-suite' );
				$type = $ok ? 'success' : 'error';
				$view = 'payments';
				break;

			case 'payments_recheck':
				$before = KCS_Payments::query( array( 'status' => 'completed', 'per_page' => 1 ) )['total'];
				KCS_Payments::recheck_pending( 0 );
				$found = KCS_Payments::query( array( 'status' => 'completed', 'per_page' => 1 ) )['total'] - $before;
				/* translators: %d: number */
				$msg  = $found ? sprintf( _n( '%d payment was confirmed by the gateway.', '%d payments were confirmed by the gateway.', $found, 'kingdom-church-suite' ), $found ) : __( 'Checked: no new payments were confirmed. See what each gateway said under the status.', 'kingdom-church-suite' );
				$view = 'payments';
				break;

			case 'payment_delete':
			case 'payments_delete_failed':
				$view = 'payments';
				if ( ! KCS_Transfers::can_confirm() ) {
					$msg  = __( 'You are not allowed to delete payments.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				if ( 'payment_delete' === $do ) {
					// Failed/cancelled, or pending for more than 30 minutes (stuck: the giver never paid).
					$n = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . kcs_table( 'payments' ) . " WHERE id = %d AND ( status IN ('failed','cancelled') OR ( status = 'pending' AND created_at < %s ) )", $id, gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DateTime.CurrentTimeTimestamp.Requested
				} else {
					$n = (int) $wpdb->query( 'DELETE FROM ' . kcs_table( 'payments' ) . " WHERE status IN ('failed','cancelled')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
				/* translators: %d: number */
				$msg = $n ? sprintf( _n( '%d payment deleted.', '%d payments deleted.', $n, 'kingdom-church-suite' ), $n ) : __( 'Only failed, cancelled or stuck pending payments (older than 30 minutes) can be deleted.', 'kingdom-church-suite' );
				break;

			case 'payment_restrict':
			case 'payment_unrestrict':
			case 'payments_bulk':
				$view = 'payments';
				if ( ! KCS_Transfers::can_confirm() ) {
					$msg  = __( 'Only church administrators can do this.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$hours    = isset( $d['hours'] ) ? (int) $d['hours'] : 168;
				$reason   = sanitize_text_field( $d['reason'] ?? '' );
				$reason   = '' !== $reason ? $reason : __( 'Restricted by the church (suspected card testing)', 'kingdom-church-suite' );
				$what     = in_array( $d['what'] ?? '', array( 'device', 'email', 'both' ), true ) ? $d['what'] : 'both';
				$restrict = function ( $p ) use ( $hours, $reason, $what ) {
					$place = KCS_Security::payment_place( $p, true );
					if ( 'email' !== $what && $p->ip_hash ) {
						KCS_Security::block( $p->ip_hash, $reason, $hours, array( 'ip' => $place['ip'], 'where' => $place['where'], 'email' => $p->email ) );
					}
					if ( 'device' !== $what && $p->email ) {
						KCS_Security::block_email( $p->email, $reason, $hours );
					}
				};
				if ( 'payment_restrict' === $do ) {
					$p = KCS_Payments::get( $id );
					if ( $p ) {
						$restrict( $p );
						$msg = __( 'Restricted: this person can no longer pay by card from that device or email. Bank transfer is still open to them.', 'kingdom-church-suite' );
					}
				} elseif ( 'payment_unrestrict' === $do ) {
					$p = KCS_Payments::get( $id );
					if ( $p ) {
						KCS_Security::unblock( (string) $p->ip_hash );
						KCS_Security::unblock_email( (string) $p->email );
						$msg = __( 'Restriction removed.', 'kingdom-church-suite' );
					}
				} else {
					$ids    = array_slice( array_filter( array_map( 'absint', (array) ( $d['ids'] ?? array() ) ) ), 0, 200 );
					$action = sanitize_key( $d['bulk'] ?? '' );
					if ( ! $ids ) {
						$msg  = __( 'Tick the payments first.', 'kingdom-church-suite' );
						$type = 'error';
						break;
					}
					if ( 'delete' === $action ) {
						$in = implode( ',', $ids );
						$n  = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . kcs_table( 'payments' ) . " WHERE id IN ($in) AND ( status IN ('failed','cancelled') OR ( status = 'pending' AND created_at < %s ) )", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DateTime.CurrentTimeTimestamp.Requested
						/* translators: 1: deleted, 2: skipped */
						$msg = sprintf( __( '%1$d payment(s) deleted. %2$d skipped (completed, waiting for review, or pending less than 30 minutes).', 'kingdom-church-suite' ), $n, count( $ids ) - $n );
					} elseif ( 'restrict' === $action ) {
						foreach ( $ids as $pid ) {
							$p = KCS_Payments::get( $pid );
							if ( $p && 'completed' !== $p->status ) {
								$restrict( $p );
							}
						}
						$msg = __( 'The people behind the ticked payments are restricted from card giving (completed payments were skipped).', 'kingdom-church-suite' );
					}
				}
				break;

			case 'receipt_settings':
				KCS_Receipts::save_settings( $d );
				$msg  = __( 'Receipt settings saved.', 'kingdom-church-suite' );
				$view = 'payments';
				break;

			case 'payment_verify':
				$p = KCS_Payments::get( $id );
				if ( $p ) {
					/* translators: %s: status */
					$now  = KCS_Payments::verify_and_update( $p );
					$said = (string) KCS_Payments::get( $id )->note;
					$msg  = sprintf( __( 'Checked with the gateway: %s.', 'kingdom-church-suite' ), $now ) . ( 'completed' !== $now && '' !== $said ? ' ' . $said : '' );
				}
				break;

			case 'prayer_status':
				$status = sanitize_key( $d['status'] ?? '' );
				if ( array_key_exists( $status, KCS_Prayer::statuses() ) ) {
					$wpdb->update( kcs_table( 'prayer_requests' ), array( 'status' => $status ), array( 'id' => $id ) );
					$msg = __( 'Prayer request updated.', 'kingdom-church-suite' );
				}
				break;

			case 'prayer_enc':
				KCS_Prayer::moderate( $id, sanitize_key( $d['decision'] ?? '' ) );
				$msg = __( 'Encouragement updated.', 'kingdom-church-suite' );
				break;

			case 'visit_status':
				$status = sanitize_key( $d['status'] ?? '' );
				if ( array_key_exists( $status, KCS_Visits::statuses() ) ) {
					$wpdb->update( kcs_table( 'visits' ), array( 'status' => $status ), array( 'id' => $id ) );
					$msg = __( 'Visit updated.', 'kingdom-church-suite' );
				}
				break;

			case 'testimony':
				$post = get_post( $id );
				if ( $post && 'kcs_testimony' === $post->post_type ) {
					if ( 'publish' === ( $d['decision'] ?? '' ) ) {
						wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
						$msg = __( 'Testimony published.', 'kingdom-church-suite' );
					} else {
						// Deleted for good, with its pictures, so declined stories do not fill the hosting space.
						wp_delete_post( $id, true );
						$msg = __( 'Testimony removed.', 'kingdom-church-suite' );
					}
				}
				break;

			case 'testimony_settings':
				kcs_update_option( 'story_photos', empty( $d['story_photos'] ) ? '0' : '1' );
				kcs_update_option( 'story_video', empty( $d['story_video'] ) ? '0' : '1' );
				kcs_update_option( 'story_max_photos', (string) max( 1, min( 10, absint( $d['story_max_photos'] ?? 4 ) ) ) );
				kcs_update_option( 'story_photo_mb', (string) max( 1, min( 50, absint( $d['story_photo_mb'] ?? 5 ) ) ) );
				kcs_update_option( 'story_video_minutes', (string) max( 1, min( 60, absint( $d['story_video_minutes'] ?? 5 ) ) ) );
				kcs_update_option( 'story_guidelines', sanitize_textarea_field( $d['story_guidelines'] ?? '' ) );
				$msg = __( 'Story settings saved.', 'kingdom-church-suite' );
				break;

			case 'notify':
				$subject  = sanitize_text_field( $d['subject'] ?? '' );
				$message  = wp_kses_post( $d['message'] ?? '' );
				$plan_ids = array_filter( array_map( 'absint', (array) ( $d['plan_ids'] ?? array() ) ) );
				$status   = sanitize_key( $d['audience_status'] ?? '' );
				if ( ! $subject || ! $message ) {
					$msg  = __( 'Add a subject and a message.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$count = 0;
				if ( ! empty( $d['send_email'] ) ) {
					$ids = array();
					foreach ( KCS_Partners::query( array( 'per_page' => 100000, 'status' => $status ) )['items'] as $p ) {
						if ( ! $plan_ids || in_array( (int) $p->plan_id, $plan_ids, true ) ) {
							$ids[] = (int) $p->id;
						}
					}
					if ( $ids ) {
						$count = KCS_Newsletter::queue_message( 'partners', $subject, $message, '', 0, $ids );
						wp_schedule_single_event( time() + 5, 'kcs_process_email_queue' );
					}
				}
				if ( ! empty( $d['post_dashboard'] ) ) {
					KCS_Announcements::create(
						array(
							'title'    => $subject,
							'body'     => $message,
							'kind'     => sanitize_key( $d['kind'] ?? 'news' ),
							'link'     => $d['link'] ?? '',
							'plan_ids' => $plan_ids,
						)
					);
				}
				$phones = 0;
				if ( ! empty( $d['send_push'] ) ) {
					$users = array();
					foreach ( KCS_Partners::query( array( 'per_page' => 100000, 'status' => $status ) )['items'] as $p ) {
						if ( $p->user_id && ( ! $plan_ids || in_array( (int) $p->plan_id, $plan_ids, true ) ) ) {
							$users[] = (int) $p->user_id;
						}
					}
					$phones = KCS_Push::send_all( $subject, wp_strip_all_tags( str_replace( '{first_name}', '', $message ) ), ! empty( $d['link'] ) ? $d['link'] : kcs_page_url( 'partner_dashboard' ), $users );
				}
				/* translators: 1: number of emails, 2: number of phones */
				$msg = sprintf( __( 'Notification sent. %1$d email(s) queued, %2$d phone(s) notified.', 'kingdom-church-suite' ), $count, $phones );
				break;

			case 'announcement_delete':
				KCS_Announcements::delete( $id );
				$msg = __( 'Announcement removed from dashboards.', 'kingdom-church-suite' );
				break;

			case 'pastor_save':
				if ( empty( $d['name'] ) || empty( $d['phone'] ) ) {
					$msg  = __( 'A pastor needs a name and a WhatsApp number.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				KCS_Pastors::save( $d );
				$msg = __( 'Pastor saved.', 'kingdom-church-suite' );
				break;

			case 'wa_chat_save':
				KCS_WhatsApp_Chat::save( $d );
				$msg  = __( 'Website WhatsApp chat saved.', 'kingdom-church-suite' );
				$view = 'pastors';
				break;

			case 'currency_save':
				$settings = get_option( 'kcs_settings', array() );
				$intl     = strtoupper( sanitize_text_field( $d['currency'] ?? 'USD' ) );
				$local    = strtoupper( sanitize_text_field( $d['local_currency'] ?? '' ) );
				$settings['currency']              = array_key_exists( $intl, kcs_currencies() ) ? $intl : 'USD';
				$settings['local_currency']        = array_key_exists( $local, kcs_currencies() ) ? $local : 'none';
				$settings['international_label']   = sanitize_text_field( $d['international_label'] ?? '' );
				$settings['local_label']           = sanitize_text_field( $d['local_label'] ?? '' );
				$settings['amounts_international'] = preg_replace( '/[^0-9.,]/', '', (string) ( $d['amounts_international'] ?? '' ) );
				$settings['amounts_local']         = preg_replace( '/[^0-9.,]/', '', (string) ( $d['amounts_local'] ?? '' ) );
				$gw                                = sanitize_key( $d['tithe_gateway'] ?? '' );
				$settings['tithe_gateway']         = KCS_Payments::gateway( $gw ) || 'auto' === $gw ? $gw : 'none';
				$settings['show_plan_benefits']    = empty( $d['show_plan_benefits'] ) ? '0' : '1';
				$settings['show_partnership_reasons'] = empty( $d['show_partnership_reasons'] ) ? '0' : '1';
				update_option( 'kcs_settings', $settings );
				$msg  = __( 'Partnership settings saved.', 'kingdom-church-suite' );
				$view = 'plans';
				break;

			case 'plan_save':
				$plan = $id ? KCS_Plans::get( $id ) : null;
				$row  = array_merge(
					$plan ? (array) $plan : array( 'is_active' => 1 ),
					array_intersect_key( $d, array_flip( array( 'name', 'description', 'benefits', 'amount', 'local_amount', 'frequency', 'sort_order' ) ) ),
					array(
						'currency'            => kcs_get_option( 'currency', 'USD' ),
						'allow_custom_amount' => ! empty( $d['allow_custom_amount'] ),
						'is_featured'         => ! empty( $d['is_featured'] ),
						'is_active'           => ! empty( $d['is_active'] ),
					)
				);
				if ( '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
					$msg  = __( 'Please give the plan a name.', 'kingdom-church-suite' );
					$type = 'error';
				} else {
					KCS_Plans::save( $row, $plan ? (int) $plan->id : 0 );
					$msg = __( 'Plan saved.', 'kingdom-church-suite' );
				}
				$view = 'plans';
				break;

			case 'website_save':
				// Only the groups shown on the submitted page are saved (unticked boxes elsewhere stay as they are).
				$groups = array_map( 'sanitize_key', (array) ( $d['groups'] ?? array() ) );
				foreach ( (array) apply_filters( 'kcs_website_settings', array() ) as $key => $f ) {
					if ( $groups && ! in_array( $f[2], $groups, true ) ) {
						continue;
					}
					$raw = $d[ $key ] ?? '';
					switch ( $f[1] ) {
						case 'checkbox':
							$value = ! empty( $raw );
							break;
						case 'url':
						case 'video':
							$value = esc_url_raw( $raw );
							break;
						case 'number':
							$value = min( 90, absint( $raw ) );
							break;
						case 'color':
							$value = sanitize_hex_color( $raw ) ? sanitize_hex_color( $raw ) : $f[3];
							break;
						case 'select':
							$value = array_key_exists( $raw, (array) ( $f[4] ?? array() ) ) ? $raw : $f[3];
							break;
						case 'textarea':
						case 'longtext':
							$value = sanitize_textarea_field( $raw );
							break;
						case 'image':
							$value = esc_url_raw( $raw );
							break;
						case 'image_id':
							$value = absint( $raw );
							break;
						case 'email':
							$value = sanitize_email( $raw );
							break;
						case 'emails':
							$value = implode( ', ', array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $raw ) ), 'is_email' ) );
							break;
						case 'map':
							// A pasted Google Maps embed code (<iframe src="…">) or its link.
							if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', (string) $raw, $m ) ) {
								$raw = html_entity_decode( $m[1] );
							}
							$value = 0 === strpos( trim( (string) $raw ), 'https://' ) ? esc_url_raw( trim( (string) $raw ) ) : '';
							break;
						default:
							$value = sanitize_text_field( $raw );
					}
					set_theme_mod( $key, $value );
				}
				$msg  = __( 'Website updated.', 'kingdom-church-suite' );
				$view = in_array( $view, array( 'website', 'content' ), true ) ? $view : 'website';
				break;

			case 'gateway_order_save':
				if ( ! current_user_can( 'manage_options' ) ) {
					$msg  = __( 'Only church administrators can change settings.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				KCS_Payments::save_order( (array) ( $d['gateway_order'] ?? array() ) );
				$msg  = __( 'Order of payment methods saved.', 'kingdom-church-suite' );
				$view = 'settings';
				if ( empty( $d['return'] ) ) {
					$d['return'] = self::url( 'settings', array( 'tab' => 'payments' ) );
				}
				break;

			case 'schedule_save':
				if ( ! current_user_can( 'manage_options' ) ) {
					$msg  = __( 'Only church administrators can change settings.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				kcs_update_option( 'service_schedule', self::schedule_from_post( $d ) );
				self::save_timezone( (string) ( $d['site_timezone'] ?? '' ) );
				delete_transient( 'kcs_live_status' );
				$msg  = __( 'Service times saved.', 'kingdom-church-suite' );
				break;

			case 'settings_save':
				if ( ! current_user_can( 'manage_options' ) ) {
					$msg  = __( 'Only church administrators can change settings.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$tab = sanitize_key( $d['tab'] ?? 'general' );
				if ( isset( $d['kcs_sched'] ) ) {
					$_POST['kcs']['service_schedule'] = wp_slash( self::schedule_from_post( $d ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in handle().
				}
				if ( isset( KCS_Settings::tabs()[ $tab ] ) ) {
					KCS_Settings::save_tab( $tab, isset( $_POST['kcs'] ) && is_array( $_POST['kcs'] ) ? $_POST['kcs'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field in save_tab.
					if ( 'general' === $tab ) {
						flush_rewrite_rules();
						self::save_timezone( (string) ( $d['site_timezone'] ?? '' ) );
					}
				}
				$msg  = __( 'Settings saved.', 'kingdom-church-suite' );
				$view = 'settings';
				if ( empty( $d['return'] ) ) {
					$d['return'] = self::url( 'settings', array( 'tab' => $tab ) );
				}
				break;

			case 'test_email':
				$to   = sanitize_email( $d['to'] ?? '' );
				$sent = is_email( $to ) && KCS_Email::send( $to, __( 'Test email from your church website', 'kingdom-church-suite' ), '<p>' . esc_html__( 'It works! Emails from your church website are being delivered.', 'kingdom-church-suite' ) . '</p>' );
				/* translators: %s: error from the mail server */
				$msg  = $sent ? __( 'Test email sent. Check the inbox (and the spam folder).', 'kingdom-church-suite' ) : ( KCS_Mailer::$last_error ? sprintf( __( 'The test email could not be sent: %s Check the SMTP server, port, encryption, username and password.', 'kingdom-church-suite' ), KCS_Mailer::$last_error ) : __( 'The test email could not be sent. Check the email settings.', 'kingdom-church-suite' ) );
				$type = $sent ? 'success' : 'error';
				$view = 'settings';
				break;

			case 'design_delete':
				KCS_Email_Designs::delete( (string) ( $d['design'] ?? '' ) );
				$msg  = __( 'Design deleted.', 'kingdom-church-suite' );
				$view = 'newsletter';
				break;

			case 'campaign_save':
				$c = $id ? KCS_Newsletter::get_campaign( $id ) : null;
				if ( $c && in_array( $c->status, array( 'sending', 'sent' ), true ) ) {
					$msg  = __( 'Sent emails cannot be changed.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				if ( '' === trim( (string) ( $d['subject'] ?? '' ) ) || '' === trim( wp_strip_all_tags( (string) ( $d['content'] ?? '' ) ) ) ) {
					$msg  = __( 'Add a subject and a message.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				// The editor sends HTML (filtered on save); plain text from older forms becomes paragraphs.
				$raw          = (string) $d['content'];
				$d['content'] = false === strpos( $raw, '<' ) ? wpautop( make_clickable( esc_html( $raw ) ) ) : $raw;
				$id           = KCS_Newsletter::save_campaign( $d, $id );
				$doing        = sanitize_key( $d['do'] ?? 'save' );
				$view         = 'newsletter';
				if ( 'design' === $doing ) {
					$name = trim( (string) ( $d['design_name'] ?? '' ) );
					KCS_Email_Designs::save( '' !== $name ? $name : (string) $d['subject'], (string) $d['subject'], $d['content'] );
					/* translators: %s: name */
					$msg         = sprintf( __( 'Saved “%s” to your designs. Pick it from “Start from a design” next time.', 'kingdom-church-suite' ), '' !== $name ? $name : (string) $d['subject'] );
					$d['return'] = self::url( 'newsletter', array( 'edit' => $id ) );
					break;
				}
				if ( 'test' === $doing ) {
					$c    = KCS_Newsletter::get_campaign( $id );
					$user = wp_get_current_user();
					$vars = array( 'name' => $user->display_name, 'first_name' => $user->first_name ? $user->first_name : $user->display_name, 'email' => $user->user_email, 'unsubscribe_url' => home_url( '/' ) );
					KCS_Email::send( $user->user_email, '[TEST] ' . KCS_Email::replace( $c->subject, $vars, false ), KCS_Email::replace( $c->content, $vars ), array( 'preheader' => $c->preheader, 'unsubscribe_url' => home_url( '/' ) ) );
					/* translators: %s: email */
					$msg         = sprintf( __( 'Test sent to %s.', 'kingdom-church-suite' ), $user->user_email );
					$d['return'] = self::url( 'newsletter', array( 'edit' => $id ) );
				} elseif ( 'send' === $doing ) {
					$count = KCS_Newsletter::send_campaign( $id );
					if ( is_wp_error( $count ) ) {
						$msg  = $count->get_error_message();
						$type = 'error';
					} elseif ( ! $count ) {
						$msg  = __( 'There is no one in that list yet, so nothing was sent. The email is saved as a draft.', 'kingdom-church-suite' );
						$type = 'error';
						global $wpdb;
						$wpdb->update( kcs_table( 'campaigns' ), array( 'status' => 'draft' ), array( 'id' => $id ) );
					} else {
						/* translators: %d: count */
						$msg = sprintf( _n( 'Sending to %d person. It goes out in the background over the next few minutes.', 'Sending to %d people. It goes out in the background over the next few minutes.', $count, 'kingdom-church-suite' ), $count );
					}
					$d['return'] = self::url( 'newsletter' );
				} else {
					$msg         = __( 'Draft saved.', 'kingdom-church-suite' );
					$d['return'] = self::url( 'newsletter', array( 'edit' => $id ) );
				}
				break;

			case 'subscriber_status':
				global $wpdb;
				$st = sanitize_key( $d['status'] ?? '' );
				if ( in_array( $st, array( 'subscribed', 'unsubscribed' ), true ) ) {
					$wpdb->update( kcs_table( 'subscribers' ), array( 'status' => $st ), array( 'id' => $id ) );
				} elseif ( 'delete' === $st ) {
					$wpdb->delete( kcs_table( 'subscribers' ), array( 'id' => $id ) );
				}
				$msg  = __( 'Subscriber updated.', 'kingdom-church-suite' );
				$view = 'newsletter';
				break;

			case 'app_save':
				kcs_update_option( 'pwa_enabled', empty( $d['pwa_enabled'] ) ? '0' : '1' );
				kcs_update_option( 'pwa_banner', empty( $d['pwa_banner'] ) ? '0' : '1' );
				kcs_update_option( 'pwa_start_app', empty( $d['pwa_start_app'] ) ? '0' : '1' );
				if ( ! empty( $d['pwa_start_app'] ) ) {
					KCS_App_Home::create_page();
				}
				kcs_update_option( 'pwa_live_push', empty( $d['pwa_live_push'] ) ? '0' : '1' );
				kcs_update_option( 'pwa_name', sanitize_text_field( $d['pwa_name'] ?? '' ) );
				kcs_update_option( 'pwa_short_name', mb_substr( sanitize_text_field( $d['pwa_short_name'] ?? '' ), 0, 14 ) );
				kcs_update_option( 'pwa_icon', esc_url_raw( $d['pwa_icon'] ?? '' ) );
				$msg = __( 'Church app saved. Phones pick up the changes next time the app opens.', 'kingdom-church-suite' );
				break;

			case 'push_send':
				$title = sanitize_text_field( $d['push_title'] ?? '' );
				$body  = sanitize_textarea_field( $d['push_body'] ?? '' );
				$link  = esc_url_raw( $d['push_link'] ?? '' );
				if ( ! $title || ! $body ) {
					$msg  = __( 'Add a title and a message.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				if ( $link && wp_parse_url( $link, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
					$link = '';
				}
				$n = KCS_Push::send_all( $title, $body, $link );
				/* translators: %d: number of phones */
				$msg = $n ? sprintf( _n( 'Sending to %d phone…', 'Sending to %d phones…', $n, 'kingdom-church-suite' ), $n ) : __( 'No phones have turned on notifications yet.', 'kingdom-church-suite' );
				$type = $n ? 'success' : 'error';
				break;

			case 'security_save':
			case 'security_unblock':
			case 'security_clear':
				if ( ! current_user_can( 'manage_options' ) ) {
					break;
				}
				if ( 'security_unblock' === $do ) {
					if ( ! empty( $d['email'] ) ) {
						KCS_Security::unblock_email( (string) $d['email'] );
						$msg = __( 'That email can give online again.', 'kingdom-church-suite' );
					} else {
						KCS_Security::unblock( sanitize_key( $d['device'] ?? '' ) );
						$msg = __( 'That device can give online again.', 'kingdom-church-suite' );
					}
				} elseif ( 'security_clear' === $do ) {
					delete_option( KCS_Security::LOG );
					$msg = __( 'Security log cleared.', 'kingdom-church-suite' );
				} else {
					kcs_update_option( 'turnstile_mode', in_array( $d['turnstile_mode'] ?? '', array( 'off', 'payments', 'all' ), true ) ? $d['turnstile_mode'] : 'payments' );
					kcs_update_option( 'turnstile_site', sanitize_text_field( $d['turnstile_site'] ?? '' ) );
					if ( '' !== trim( (string) ( $d['turnstile_secret'] ?? '' ) ) || '' === trim( (string) ( $d['turnstile_site'] ?? '' ) ) ) {
						kcs_update_option( 'turnstile_secret', sanitize_text_field( $d['turnstile_secret'] ?? '' ) );
					}
					kcs_update_option( 'sec_block_countries', implode( ',', array_filter( array_map( 'trim', explode( ',', strtoupper( preg_replace( '/[^A-Za-z,]/', '', (string) ( $d['sec_block_countries'] ?? '' ) ) ) ) ), function ( $c ) { return 2 === strlen( $c ); } ) ) );
					foreach ( array( 'sec_pay_15min' => array( 1, 20 ), 'sec_pay_day' => array( 1, 100 ), 'sec_email_day' => array( 1, 50 ), 'sec_fail_block' => array( 2, 20 ), 'sec_block_hours' => array( 1, 168 ) ) as $k => $r ) {
						kcs_update_option( $k, (string) max( $r[0], min( $r[1], absint( $d[ $k ] ?? 0 ) ) ) );
					}
					$msg = __( 'Security settings saved.', 'kingdom-church-suite' );
				}
				$view = 'security';
				break;

			case 'team_add':
			case 'team_save':
			case 'team_remove':
				list( $msg, $type ) = KCS_Team::handle( $do, $d, $id );
				$view               = 'team';
				break;

			case 'slide_save':
			case 'slide_delete':
			case 'slide_move':
			case 'slider_settings':
				list( $msg, $type ) = KCS_Slides::handle( $do, $d );
				$view               = 'slides';
				if ( 'slide_save' === $do && 'success' === $type ) {
					$d['return'] = self::url( 'slides' );
				}
				break;

			case 'word_save':
				$saved = KCS_Devotionals::save( $d, $id );
				if ( is_wp_error( $saved ) ) {
					$msg  = $saved->get_error_message();
					$type = 'error';
				} else {
					$msg         = __( 'Devotional saved.', 'kingdom-church-suite' );
					$d['return'] = self::url( 'word' );
				}
				$view = 'word';
				break;

			case 'word_delete':
				if ( KCS_Devotionals::TYPE === get_post_type( $id ) ) {
					wp_delete_post( $id, true );
					$msg = __( 'Devotional deleted.', 'kingdom-church-suite' );
				}
				$view = 'word';
				break;

			case 'word_settings':
				kcs_update_option( 'todays_word_email', empty( $d['todays_word_email'] ) ? '0' : '1' );
				kcs_update_option( 'todays_word_hour', (string) min( 23, absint( $d['todays_word_hour'] ?? 6 ) ) );
				if ( ! empty( $d['create_page'] ) ) {
					KCS_Devotionals::create_page();
				}
				$msg  = __( 'Today’s Word settings saved.', 'kingdom-church-suite' );
				$view = 'word';
				break;

			case 'popup_save':
			case 'popup_toggle':
			case 'popup_delete':
				list( $msg, $type ) = KCS_Popups::handle( $do, $d );
				$view               = 'popups';
				if ( 'popup_save' === $do && 'success' === $type ) {
					$d['return'] = self::url( 'popups' );
				}
				break;

			case 'message_read':
			case 'message_delete':
				$msg  = KCS_Contact::handle( $do, $id );
				$view = 'messages';
				break;

			case 'page_create':
			case 'page_save':
			case 'page_mode':
			case 'page_toggle':
			case 'page_delete':
				list( $msg, $type, $to ) = KCS_Pages::handle( $do, $d, $id );
				$view = 'pages';
				if ( $to ) {
					if ( false !== strpos( $to, '/wp-admin/' ) ) {
						// The page builder lives in wp-admin.
						set_transient( 'kcs_admin_flash_' . get_current_user_id(), array( $msg, $type ), 60 );
						wp_safe_redirect( $to );
						exit;
					}
					$d['return'] = $to;
				}
				break;

			case 'pastor_delete':
				KCS_Pastors::delete( sanitize_key( $d['pastor_id'] ?? '' ) );
				$msg = __( 'Pastor removed.', 'kingdom-church-suite' );
				break;
		}

		set_transient( 'kcs_admin_flash_' . get_current_user_id(), array( $msg, $type ), 60 );
		$back = self::url( $view );
		if ( ! empty( $d['return'] ) ) {
			$back = wp_validate_redirect( esc_url_raw( $d['return'] ), $back );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/* =====================================================================
	 * Rendering
	 * =================================================================== */

	/**
	 * Hidden fields for an action form.
	 *
	 * @param string $do   Action.
	 * @param string $view View to return to.
	 * @param int    $id   Record ID.
	 * @return string
	 */
	private static function fields( $do, $view, $id = 0 ) {
		return '<input type="hidden" name="kcs_admin_do" value="' . esc_attr( $do ) . '"><input type="hidden" name="view" value="' . esc_attr( $view ) . '"><input type="hidden" name="id" value="' . (int) $id . '"><input type="hidden" name="return" value="' . esc_attr( esc_url_raw( add_query_arg( array() ) ) ) . '">' . wp_nonce_field( 'kcs_church_admin', '_kcs_admin', true, false );
	}

	/**
	 * A small inline status form (select + save).
	 *
	 * @param string $do       Action.
	 * @param string $view     View.
	 * @param int    $id       ID.
	 * @param array  $statuses Statuses.
	 * @param string $current  Current.
	 * @return string
	 */
	private static function status_form( $do, $view, $id, $statuses, $current ) {
		$out = '<form method="post" class="kca-inline">' . self::fields( $do, $view, $id ) . '<select name="status" aria-label="' . esc_attr__( 'Status', 'kingdom-church-suite' ) . '" onchange="this.form.submit()">';
		foreach ( $statuses as $k => $l ) {
			$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $current, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $out . '</select><noscript><button class="kcs-btn">' . esc_html__( 'Save', 'kingdom-church-suite' ) . '</button></noscript></form>';
	}

	/**
	 * Shortcode output.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return kcs_get_template( 'partner-login', array( 'mode' => 'staff', 'redirect_to' => self::url() ) );
		}
		if ( ! self::can() ) {
			return '<div class="kcs-alert kcs-alert--error">' . esc_html__( 'This dashboard is for church staff only.', 'kingdom-church-suite' ) . '</div>';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$views = self::views();
		$view  = isset( $_GET['view'] ) && isset( $views[ sanitize_key( $_GET['view'] ) ] ) ? sanitize_key( $_GET['view'] ) : 'overview';
		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( isset( $_GET['view'] ) && 'search' === $_GET['view'] ) {
			$view           = 'search';
			$views['search'] = array( __( 'Search', 'kingdom-church-suite' ), '⌕' );
		}
		// phpcs:enable
		$flash = get_transient( 'kcs_admin_flash_' . get_current_user_id() );
		delete_transient( 'kcs_admin_flash_' . get_current_user_id() );
		$counts = self::badges();

		ob_start();
		?>
		<div class="kca" data-kca>
			<aside class="kca__side">
				<div class="kca__brand"><span class="kca__logo" aria-hidden="true"></span><span><strong><?php echo esc_html( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ); ?></strong><small><?php esc_html_e( 'Church admin', 'kingdom-church-suite' ); ?></small></span></div>
				<?php
				$menu   = array();
				$placed = array();
				foreach ( self::groups() as $title => $slugs ) {
					$menu[ $title ] = array_values( array_filter( (array) $slugs, function ( $sl ) use ( $views ) { return isset( $views[ $sl ] ); } ) );
					$placed         = array_merge( $placed, $menu[ $title ] );
				}
				$extra = array_diff( array_keys( $views ), $placed );
				if ( $extra ) {
					$acc          = __( 'Account', 'kingdom-church-suite' );
					$menu[ $acc ] = array_merge( $menu[ $acc ] ?? array(), $extra );
				}
				$todo = array_sum( $counts );
				?>
				<?php echo self::search_form( $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<details class="kca__menu" data-kca-menu>
					<summary><span class="kca__ic" aria-hidden="true"><?php echo esc_html( $views[ $view ][1] ); ?></span><span><small><?php esc_html_e( 'Menu', 'kingdom-church-suite' ); ?></small><?php echo esc_html( $views[ $view ][0] ); ?></span><?php if ( $todo ) : ?><em><?php echo (int) $todo; ?></em><?php endif; ?><b aria-hidden="true">☰</b></summary>
					<nav class="kca__nav" aria-label="<?php esc_attr_e( 'Dashboard sections', 'kingdom-church-suite' ); ?>">
						<?php foreach ( $menu as $title => $slugs ) : ?>
							<?php if ( ! $slugs ) { continue; } ?>
							<div class="kca__group">
								<?php if ( '' !== $title ) : ?><p class="kca__gtitle"><?php echo esc_html( $title ); ?></p><?php endif; ?>
								<?php foreach ( $slugs as $slug ) : ?>
									<a href="<?php echo esc_url( self::url( $slug ) ); ?>"<?php echo $slug === $view ? ' aria-current="page"' : ''; ?>><span class="kca__ic" aria-hidden="true"><?php echo esc_html( $views[ $slug ][1] ); ?></span><?php echo esc_html( $views[ $slug ][0] ); ?><?php if ( ! empty( $counts[ $slug ] ) ) : ?><em><?php echo (int) $counts[ $slug ]; ?></em><?php endif; ?></a>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
						<div class="kca__group kca__group--end">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><span class="kca__ic" aria-hidden="true">↗</span><?php esc_html_e( 'View your website', 'kingdom-church-suite' ); ?></a>
							<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><span class="kca__ic" aria-hidden="true">⎋</span><?php esc_html_e( 'Log out', 'kingdom-church-suite' ); ?></a>
						</div>
					</nav>
				</details>
				<script>(function(m){if(m&&window.matchMedia&&matchMedia('(min-width: 960px)').matches){m.setAttribute('open','');}})(document.querySelector('[data-kca-menu]'));</script>
			</aside>
			<main class="kca__main">
				<header class="kca__head">
					<?php if ( 'overview' === $view ) : ?>
						<h2><?php /* translators: %s: first name */ echo esc_html( sprintf( __( 'Welcome, %s', 'kingdom-church-suite' ), wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name ) ); ?></h2>
						<p class="kcs-muted"><?php echo esc_html( date_i18n( 'l, ' . get_option( 'date_format' ) ) ); ?></p>
					<?php elseif ( 'search' === $view ) : ?>
						<h2><?php /* translators: %s: search words */ echo esc_html( '' === $query ? __( 'Search', 'kingdom-church-suite' ) : sprintf( __( 'Results for “%s”', 'kingdom-church-suite' ), $query ) ); ?></h2>
					<?php else : ?>
						<h2><?php echo esc_html( $views[ $view ][0] ); ?></h2>
						<?php if ( self::describe( $view ) ) : ?><p class="kcs-muted"><?php echo esc_html( self::describe( $view ) ); ?></p><?php endif; ?>
					<?php endif; ?>
				</header>
				<?php if ( $flash ) : ?><div class="kcs-alert kcs-alert--<?php echo esc_attr( $flash[1] ); ?>" role="status"><?php echo esc_html( $flash[0] ); ?></div><?php endif; ?>
				<?php
				do_action( 'kcs_church_admin_before_view', $view );
				if ( method_exists( __CLASS__, 'view_' . $view ) ) {
					call_user_func( array( __CLASS__, 'view_' . $view ) );
				} else {
					do_action( 'kcs_church_admin_view_' . $view );
				}
				?>
			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Counts shown as nav badges.
	 *
	 * @return array
	 */
	private static function badges() {
		global $wpdb;
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return $cache = array(
			'prayers'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'prayer_requests' ) . " WHERE status = 'new'" ),
			'visits'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'visits' ) . " WHERE status = 'new'" ),
			'messages'    => KCS_Contact::unread(),
			'payments'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'payments' ) .  " WHERE status = 'review' AND gateway NOT IN ('" . implode( "','", KCS_Transfers::MANUAL ) . "')" ),
			'transfers'   => KCS_Transfers::count_waiting(),
			'testimonies' => (int) wp_count_posts( 'kcs_testimony' )->pending,
			'partners'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'partners' ) . " WHERE status = 'overdue'" ),
		);
		// phpcs:enable
	}

	/**
	 * Overview.
	 */
	private static function view_overview() {
		global $wpdb;
		$stats = KCS_Partners::stats();
		$sum   = function ( $rows ) {
			return $rows ? implode( ' + ', array_map( function ( $r ) { return kcs_format_money( $r->total, $r->currency ); }, $rows ) ) : kcs_format_money( 0 );
		};
		$due   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'partners' ) . " WHERE status IN ('active','overdue') AND frequency <> 'one_time' AND next_due_date <= %s ORDER BY next_due_date ASC LIMIT 8", gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +7 days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$plans = array();
		foreach ( KCS_Plans::all() as $pl ) {
			$plans[ $pl->id ] = $pl->name;
		}
		$by_plan = $wpdb->get_results( 'SELECT plan_id, COUNT(*) c FROM ' . kcs_table( 'partners' ) . " WHERE status IN ('active','overdue') GROUP BY plan_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$max     = 1;
		foreach ( $by_plan as $r ) {
			$max = max( $max, (int) $r->c );
		}
		?>
		<?php
		$counts = self::badges();
		$attn   = array_filter(
			array(
				'transfers'   => array( $counts['transfers'], _n( '%d payment to confirm', '%d payments to confirm', $counts['transfers'], 'kingdom-church-suite' ) ),
				'payments'    => array( $counts['payments'], _n( '%d payment to review', '%d payments to review', $counts['payments'], 'kingdom-church-suite' ) ),
				'prayers'     => array( $counts['prayers'], _n( '%d new prayer request', '%d new prayer requests', $counts['prayers'], 'kingdom-church-suite' ) ),
				'visits'      => array( $counts['visits'], _n( '%d first-time visitor to welcome', '%d first-time visitors to welcome', $counts['visits'], 'kingdom-church-suite' ) ),
				'messages'    => array( $counts['messages'], _n( '%d new contact message', '%d new contact messages', $counts['messages'], 'kingdom-church-suite' ) ),
				'testimonies' => array( $counts['testimonies'], _n( '%d testimony to approve', '%d testimonies to approve', $counts['testimonies'], 'kingdom-church-suite' ) ),
				'partners'    => array( $counts['partners'], _n( '%d partner is behind on payments', '%d partners are behind on payments', $counts['partners'], 'kingdom-church-suite' ) ),
			),
			function ( $a ) { return $a[0] > 0; }
		);
		$attn  = array_filter( $attn, array( 'KCS_Team', 'allowed' ), ARRAY_FILTER_USE_KEY );
		$steps = current_user_can( 'manage_options' ) ? self::setup_steps() : array();
		$done  = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );
		?>
		<section class="kca-card kca-attn">
			<h3><?php esc_html_e( 'Needs your attention', 'kingdom-church-suite' ); ?></h3>
			<?php if ( $attn ) : ?>
				<ul class="kca-todo">
					<?php foreach ( $attn as $slug => $a ) : ?>
						<li><a href="<?php echo esc_url( self::url( $slug, 'partners' === $slug ? array( 'status' => 'overdue' ) : array() ) ); ?>"><b><?php echo (int) $a[0]; ?></b><span><?php echo esc_html( sprintf( $a[1], $a[0] ) ); ?></span><i aria-hidden="true">→</i></a></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="kca-clear"><span aria-hidden="true">✓</span> <?php esc_html_e( 'All caught up — nothing is waiting for you.', 'kingdom-church-suite' ); ?></p>
			<?php endif; ?>
		</section>
		<?php if ( $steps && $done < count( $steps ) ) : ?>
			<section class="kca-card kca-setup">
				<div class="kca-setup__head">
					<h3><?php esc_html_e( 'Get your website ready', 'kingdom-church-suite' ); ?></h3>
					<span class="kcs-muted"><?php /* translators: 1: done, 2: total */ echo esc_html( sprintf( __( '%1$d of %2$d done', 'kingdom-church-suite' ), $done, count( $steps ) ) ); ?></span>
				</div>
				<div class="kca-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) count( $steps ); ?>" aria-valuenow="<?php echo (int) $done; ?>"><i style="--w:<?php echo esc_attr( round( $done / count( $steps ) * 100 ) ); ?>%"></i></div>
				<ol class="kca-steps">
					<?php foreach ( $steps as $st ) : ?>
						<li class="<?php echo $st['done'] ? 'is-done' : ''; ?>"><a href="<?php echo esc_url( $st['url'] ); ?>"><span class="kca-steps__tick" aria-hidden="true"><?php echo $st['done'] ? '✓' : ''; ?></span><span><strong><?php echo esc_html( $st['title'] ); ?></strong><small><?php echo esc_html( $st['why'] ); ?></small></span><?php if ( ! $st['done'] ) : ?><i aria-hidden="true">→</i><?php endif; ?></a></li>
					<?php endforeach; ?>
				</ol>
			</section>
		<?php endif; ?>
		<div class="kca-quick">
			<a class="kcs-btn" href="<?php echo esc_url( self::url( 'notify' ) ); ?>">✉ <?php esc_html_e( 'Message partners', 'kingdom-church-suite' ); ?></a>
			<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'content' ) ); ?>">▧ <?php esc_html_e( 'Change pictures & text', 'kingdom-church-suite' ); ?></a>
			<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">↗ <?php esc_html_e( 'View your website', 'kingdom-church-suite' ); ?></a>
		</div>
		<h3 class="kca-sub"><?php esc_html_e( 'At a glance', 'kingdom-church-suite' ); ?></h3>
		<div class="kca-kpis">
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'partners', array( 'status' => 'active' ) ) ); ?>"><span><?php esc_html_e( 'Active partners', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_by_status']['active']; ?></strong><small><?php echo (int) $stats['partners_total']; ?> <?php esc_html_e( 'total', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi kca-kpi--warn" href="<?php echo esc_url( self::url( 'partners', array( 'status' => 'overdue' ) ) ); ?>"><span><?php esc_html_e( 'Behind on payments', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_by_status']['overdue']; ?></strong><small><?php echo (int) $stats['partners_by_status']['pending']; ?> <?php esc_html_e( 'awaiting first payment', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'payments', array( 'status' => 'completed' ) ) ); ?>"><span><?php esc_html_e( 'Received this month', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( $sum( $stats['revenue_this_month'] ) ); ?></strong><small><?php echo esc_html( $sum( $stats['revenue_all_time'] ) ); ?> <?php esc_html_e( 'all time', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'prayers' ) ); ?>"><span><?php esc_html_e( 'New prayer requests', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['new_prayers']; ?></strong><small><?php esc_html_e( 'waiting for the prayer team', 'kingdom-church-suite' ); ?></small></a>
		</div>
		<div class="kca-cols">
			<section class="kca-card">
				<h3><?php esc_html_e( 'Due in the next 7 days', 'kingdom-church-suite' ); ?></h3>
				<?php if ( ! $due ) : ?><p class="kcs-muted"><?php esc_html_e( 'No partnership payments are due this week.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
				<ul class="kca-list">
					<?php foreach ( $due as $p ) : ?>
						<li><a href="<?php echo esc_url( self::url( 'partners', array( 'id' => $p->id ) ) ); ?>"><strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong><small><?php echo esc_html( kcs_format_money( $p->amount, $p->currency ) . ' · ' . mysql2date( get_option( 'date_format' ), $p->next_due_date ) ); ?></small></a><?php echo kcs_status_badge( $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="kca-card">
				<h3><?php esc_html_e( 'Partners by plan', 'kingdom-church-suite' ); ?></h3>
				<div class="kca-bars">
					<?php foreach ( $by_plan as $r ) : ?>
						<div class="kca-bar"><span><?php echo esc_html( $plans[ $r->plan_id ] ?? __( 'Custom', 'kingdom-church-suite' ) ); ?></span><i style="--w:<?php echo esc_attr( round( $r->c / $max * 100 ) ); ?>%"></i><b><?php echo (int) $r->c; ?></b></div>
					<?php endforeach; ?>
					<?php if ( ! $by_plan ) : ?><p class="kcs-muted"><?php esc_html_e( 'No active partners yet.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * The dashboard search box: pages and settings appear as you type; Enter also searches people
	 * and payments.
	 *
	 * @param string $query Current search.
	 * @return string
	 */
	private static function search_form( $query ) {
		$index = wp_json_encode( self::search_index(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		return '<form class="kca-search" role="search" method="get" action="' . esc_url( self::url( 'search' ) ) . '" data-dsearch>'
			. '<input type="hidden" name="view" value="search">'
			. '<label class="screen-reader-text" for="kca-q">' . esc_html__( 'Search the dashboard', 'kingdom-church-suite' ) . '</label>'
			. '<span class="kca-search__ic" aria-hidden="true">⌕</span>'
			. '<input id="kca-q" type="search" name="q" value="' . esc_attr( $query ) . '" placeholder="' . esc_attr__( 'Search pages, settings, people…', 'kingdom-church-suite' ) . '" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="kca-q-list">'
			. '<ul class="kca-search__list" id="kca-q-list" role="listbox" hidden></ul>'
			/* translators: %s: search words */
			. '<script type="application/json" data-dsearch-index data-more="' . esc_attr__( 'Search people & payments for “%s”', 'kingdom-church-suite' ) . '" data-none="' . esc_attr__( 'No page or setting matches. Press Enter to search people & payments.', 'kingdom-church-suite' ) . '">' . $index . '</script>'
			. '</form>';
	}

	/**
	 * Everything the search box can jump to: pages, every setting and website option, plus common
	 * words people use for them.
	 *
	 * @return array[] t (title), d (where it is), u (link), k (other words).
	 */
	public static function search_index() {
		$views = self::views();
		$out   = array();
		foreach ( $views as $slug => $v ) {
			$out[] = array( 't' => $v[0], 'd' => self::describe( $slug ), 'u' => self::url( $slug ), 'k' => '' );
		}
		$extra = array(
			array( __( 'Service times', 'kingdom-church-suite' ), 'website', array(), 'service-times-card', __( 'schedule sunday worship midweek bible study countdown next service next live', 'kingdom-church-suite' ) ),
			array( __( 'Time zone', 'kingdom-church-suite' ), 'settings', array( 'tab' => 'general' ), 'set-timezone', __( 'timezone lagos clock time wrong hour', 'kingdom-church-suite' ) ),
			array( __( 'Bank account numbers', 'kingdom-church-suite' ), 'transfers', array(), '', __( 'bank transfer account number offline giving', 'kingdom-church-suite' ) ),
			array( __( 'Payment link (any provider)', 'kingdom-church-suite' ), 'settings', array( 'tab' => 'payments' ), 'set-payment_link_url', __( 'payment link page checkout form paystack flutterwave globalpay zenith remita opay moniepoint paypal stripe', 'kingdom-church-suite' ) ),
			array( __( 'WhatsApp chat button', 'kingdom-church-suite' ), 'pastors', array(), '', __( 'whatsapp number chat contact phone', 'kingdom-church-suite' ) ),
			array( __( 'Send a push notification', 'kingdom-church-suite' ), 'app', array(), '', __( 'push notification phones app announce', 'kingdom-church-suite' ) ),
		);
		foreach ( $extra as $e ) {
			if ( isset( $views[ $e[1] ] ) ) {
				$out[] = array( 't' => $e[0], 'd' => $views[ $e[1] ][0], 'u' => self::url( $e[1], $e[2] ) . ( $e[3] ? '#' . $e[3] : '' ), 'k' => $e[4] );
			}
		}
		// Website options (theme): Design & live stream, Pictures & text.
		$groups = array(
			'brand'    => array( 'website', __( 'Church colours', 'kingdom-church-suite' ) ),
			'fonts'    => array( 'website', __( 'Church fonts', 'kingdom-church-suite' ) ),
			'banner'   => array( 'website', __( 'Homepage banner', 'kingdom-church-suite' ) ),
			'media'    => array( 'website', __( 'Live stream, video & audio', 'kingdom-church-suite' ) ),
			'sections' => array( 'website', __( 'Show or hide sections', 'kingdom-church-suite' ) ),
			'footer'   => array( 'website', __( 'Footer', 'kingdom-church-suite' ) ),
			'pictures' => array( 'content', __( 'Pictures & logo', 'kingdom-church-suite' ) ),
			'texts'    => array( 'content', __( 'Homepage text', 'kingdom-church-suite' ) ),
			'contact'  => array( 'content', __( 'Contact & social', 'kingdom-church-suite' ) ),
			'visitpage' => array( 'content', __( 'Visit page', 'kingdom-church-suite' ) ),
			'charity'  => array( 'content', __( 'Charity', 'kingdom-church-suite' ) ),
		);
		foreach ( (array) apply_filters( 'kcs_website_settings', array() ) as $key => $f ) {
			if ( isset( $groups[ $f[2] ] ) && isset( $views[ $groups[ $f[2] ][0] ] ) ) {
				$g     = $groups[ $f[2] ];
				$out[] = array( 't' => wp_strip_all_tags( $f[0] ), 'd' => $views[ $g[0] ][0] . ' → ' . $g[1], 'u' => self::url( $g[0] ) . '#wf-' . $key, 'k' => '' );
			}
		}
		// Every Church Suite setting (administrators only).
		if ( isset( $views['settings'] ) ) {
			$tabs = KCS_Settings::tabs();
			foreach ( KCS_Settings::schema() as $tab => $sections ) {
				foreach ( (array) $sections as $section => $fields ) {
					foreach ( (array) $fields as $key => $field ) {
						if ( 'info' === ( $field['type'] ?? '' ) && empty( $field['desc'] ) ) {
							continue;
						}
						$label = wp_strip_all_tags( (string) $field['label'] );
						// "Enable", "Secret key"… say what they belong to.
						if ( str_word_count( $label ) <= 3 && false === stripos( $label, (string) $section ) ) {
							$label = $section . ' — ' . $label;
						}
						$out[] = array(
							't' => $label,
							'd' => $views['settings'][0] . ' → ' . ( $tabs[ $tab ] ?? $tab ) . ' → ' . $section,
							'u' => self::url( 'settings', array( 'tab' => $tab ) ) . '#set-' . $key,
							'k' => '',
						);
					}
				}
			}
		}
		return (array) apply_filters( 'kcs_church_admin_search_index', $out );
	}

	/**
	 * Search results: matching pages and settings, then people, payments, prayer requests and
	 * visitors.
	 */
	private static function view_search() {
		global $wpdb;
		$q = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( mb_strlen( $q ) < 2 ) {
			echo '<p class="kcs-muted">' . esc_html__( 'Type at least two letters: a page, a setting, a name, an email, a phone number or a payment reference.', 'kingdom-church-suite' ) . '</p>';
			return;
		}
		$words = preg_split( '/\s+/', mb_strtolower( $q ) );
		$pages = array_filter(
			self::search_index(),
			function ( $i ) use ( $words ) {
				$hay = mb_strtolower( $i['t'] . ' ' . $i['d'] . ' ' . $i['k'] );
				foreach ( $words as $w ) {
					if ( false === mb_strpos( $hay, $w ) ) {
						return false;
					}
				}
				return true;
			}
		);
		$like  = '%' . $wpdb->esc_like( $q ) . '%';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$sets = array(
			array(
				__( 'Partners', 'kingdom-church-suite' ),
				KCS_Team::allowed( 'partners' ) ? $wpdb->get_results( $wpdb->prepare( 'SELECT id, first_name, last_name, email, phone, status FROM ' . kcs_table( 'partners' ) . " WHERE CONCAT_WS(' ', first_name, last_name, email, phone) LIKE %s ORDER BY id DESC LIMIT 10", $like ) ) : array(),
				function ( $r ) {
					return array( trim( $r->first_name . ' ' . $r->last_name ), trim( $r->email . ' · ' . $r->phone, ' ·' ), self::url( 'partners', array( 'id' => $r->id ) ), $r->status );
				},
			),
			array(
				__( 'Giving & payments', 'kingdom-church-suite' ),
				KCS_Team::allowed( 'payments' ) ? $wpdb->get_results( $wpdb->prepare( 'SELECT reference, name, email, amount, currency, status, created_at FROM ' . kcs_table( 'payments' ) . " WHERE CONCAT_WS(' ', reference, name, email, phone) LIKE %s ORDER BY id DESC LIMIT 10", $like ) ) : array(),
				function ( $r ) {
					return array( ( $r->name ? $r->name : $r->email ) . ' — ' . kcs_format_money( $r->amount, $r->currency ), $r->reference . ' · ' . kcs_datetime( $r->created_at ), self::url( 'payments', array( 'find' => $r->reference ) ), $r->status );
				},
			),
			array(
				__( 'Prayer requests', 'kingdom-church-suite' ),
				KCS_Team::allowed( 'prayers' ) ? $wpdb->get_results( $wpdb->prepare( 'SELECT name, email, request, status FROM ' . kcs_table( 'prayer_requests' ) . " WHERE CONCAT_WS(' ', name, email, phone, request) LIKE %s ORDER BY id DESC LIMIT 10", $like ) ) : array(),
				function ( $r ) {
					return array( $r->name ? $r->name : __( 'Anonymous', 'kingdom-church-suite' ), wp_trim_words( $r->request, 14 ), self::url( 'prayers' ), $r->status );
				},
			),
			array(
				__( 'First-time visitors', 'kingdom-church-suite' ),
				KCS_Team::allowed( 'visits' ) ? $wpdb->get_results( $wpdb->prepare( 'SELECT name, email, phone, status FROM ' . kcs_table( 'visits' ) . " WHERE CONCAT_WS(' ', name, email, phone) LIKE %s ORDER BY id DESC LIMIT 10", $like ) ) : array(),
				function ( $r ) {
					return array( $r->name, trim( $r->email . ' · ' . $r->phone, ' ·' ), self::url( 'visits' ), $r->status );
				},
			),
		);
		// phpcs:enable
		$found = (bool) $pages;
		?>
		<?php if ( $pages ) : ?>
			<section class="kca-card">
				<h3><?php esc_html_e( 'Pages & settings', 'kingdom-church-suite' ); ?></h3>
				<ul class="kca-list">
					<?php foreach ( array_slice( $pages, 0, 12 ) as $i ) : ?>
						<li><a href="<?php echo esc_url( $i['u'] ); ?>"><strong><?php echo esc_html( $i['t'] ); ?></strong><small><?php echo esc_html( $i['d'] ); ?></small></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
		<?php foreach ( $sets as $set ) : ?>
			<?php if ( ! $set[1] ) { continue; } $found = true; ?>
			<section class="kca-card">
				<h3><?php echo esc_html( $set[0] ); ?></h3>
				<ul class="kca-list">
					<?php foreach ( $set[1] as $r ) : ?>
						<?php $row = $set[2]( $r ); ?>
						<li><a href="<?php echo esc_url( $row[2] ); ?>"><strong><?php echo esc_html( $row[0] ); ?></strong><small><?php echo esc_html( $row[1] ); ?></small></a><?php echo kcs_status_badge( $row[3] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
		<?php if ( ! $found ) : ?>
			<p class="kcs-muted"><?php esc_html_e( 'Nothing found. Try a shorter word, a phone number, an email address or a payment reference.', 'kingdom-church-suite' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * The partner's website login, if it may be removed with them: only a login used just for
	 * partnership (never church staff, administrators or the current user).
	 *
	 * @param object $partner Partner.
	 * @return WP_User|null
	 */
	private static function partner_login_removable( $partner ) {
		$user = $partner->user_id ? get_user_by( 'id', (int) $partner->user_id ) : null;
		if ( ! $user || get_current_user_id() === (int) $user->ID || is_super_admin( $user->ID ) || user_can( $user, 'manage_kcs' ) || user_can( $user, 'edit_posts' ) ) {
			return null;
		}
		return array_diff( (array) $user->roles, array( 'kcs_partner', 'subscriber' ) ) ? null : $user;
	}

	/**
	 * First things a new church should do, each linking to the exact place.
	 *
	 * @return array[] title, why, url, done.
	 */
	private static function setup_steps() {
				$wa       = KCS_WhatsApp_Chat::get();
		$steps    = array(
			array(
				'title' => __( 'Add your church logo', 'kingdom-church-suite' ),
				'why'   => __( 'It shows at the top of every page and in your church app.', 'kingdom-church-suite' ),
				'url'   => self::url( 'content' ) . '#group-pictures',
				'done'  => (bool) get_theme_mod( 'custom_logo' ),
			),
			array(
				'title' => __( 'Set your service times', 'kingdom-church-suite' ),
				'why'   => __( 'Visitors see when to come, and the countdown to your next service.', 'kingdom-church-suite' ),
				'url'   => self::url( 'website' ) . '#service-times-card',
				'done'  => (bool) KCS_Livestream::schedule(),
			),
			array(
				'title' => __( 'Add your address and phone number', 'kingdom-church-suite' ),
				'why'   => __( 'So people can find you, and Google can show your church on the map.', 'kingdom-church-suite' ),
				'url'   => self::url( 'settings', array( 'tab' => 'general' ) ),
				'done'  => '' !== trim( (string) kcs_get_option( 'church_address' ) ) && '' !== trim( (string) kcs_get_option( 'church_phone' ) ),
			),
			array(
				'title' => __( 'Turn on online giving', 'kingdom-church-suite' ),
				'why'   => __( 'Receive tithes, offerings and partnership by card or bank transfer.', 'kingdom-church-suite' ),
				'url'   => self::url( 'settings', array( 'tab' => 'payments' ) ),
				'done'  => (bool) KCS_Payments::enabled_gateways(),
			),
			array(
				'title' => __( 'Connect your YouTube channel', 'kingdom-church-suite' ),
				'why'   => __( 'Your website shows the service live, by itself, whenever you stream.', 'kingdom-church-suite' ),
				'url'   => self::url( 'website' ) . '#group-media',
				'done'  => '' !== trim( (string) get_theme_mod( 'kingdom_ytlive_channel', '' ) ) || '' !== trim( (string) kcs_get_option( 'yt_channel_id' ) ),
			),
			array(
				'title' => __( 'Add a WhatsApp number', 'kingdom-church-suite' ),
				'why'   => __( 'Visitors can chat with your church team in one tap.', 'kingdom-church-suite' ),
				'url'   => self::url( 'pastors' ),
				'done'  => ! empty( $wa['contacts'] ),
			),
		);
		return (array) apply_filters( 'kcs_church_admin_setup_steps', $steps );
	}

	/**
	 * Partners list / detail.
	 */
	private static function view_partners() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// "find", not "s": WordPress treats ?s= as a site search and would not show this page.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$search = isset( $_GET['find'] ) ? sanitize_text_field( wp_unslash( $_GET['find'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		$plans = KCS_Plans::all();
		if ( $id && ( $p = KCS_Partners::get( $id ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found,Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
			$ppg  = kcs_current_page( 'ppg' );
			$payq = KCS_Payments::query( array( 'partner_id' => $p->id, 'per_page' => 10, 'page' => $ppg ) );
			$pays = $payq['items'];
			$lock = KCS_Access::lock_reason( $p );
			?>
			<p><a href="<?php echo esc_url( self::url( 'partners' ) ); ?>">← <?php esc_html_e( 'All partners', 'kingdom-church-suite' ); ?></a></p>
			<div class="kca-cols">
				<form method="post" class="kca-card kcs-form">
					<?php echo self::fields( 'partner_save', 'partners', $p->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h3><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?> <small class="kcs-muted"><?php echo esc_html( $p->partner_code ); ?></small></h3>
					<?php if ( $lock ) : ?><div class="kcs-alert kcs-alert--error"><?php /* translators: %s: reason */ printf( esc_html__( 'Dashboard locked: %s. It unlocks automatically after the next successful payment.', 'kingdom-church-suite' ), esc_html( $lock ) ); ?></div><?php endif; ?>
					<div class="kcs-grid-2">
						<label><?php esc_html_e( 'First name', 'kingdom-church-suite' ); ?><input name="first_name" value="<?php echo esc_attr( $p->first_name ); ?>"></label>
						<label><?php esc_html_e( 'Last name', 'kingdom-church-suite' ); ?><input name="last_name" value="<?php echo esc_attr( $p->last_name ); ?>"></label>
						<label><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input type="email" name="email" value="<?php echo esc_attr( $p->email ); ?>"></label>
						<label><?php esc_html_e( 'Phone', 'kingdom-church-suite' ); ?><input name="phone" value="<?php echo esc_attr( $p->phone ); ?>"></label>
						<label><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?><select name="plan_id"><?php foreach ( $plans as $pl ) : ?><option value="<?php echo (int) $pl->id; ?>" <?php selected( (int) $p->plan_id, (int) $pl->id ); ?>><?php echo esc_html( $pl->name ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?> (<?php echo esc_html( $p->currency ); ?>)<input type="number" step="0.01" name="amount" value="<?php echo esc_attr( $p->amount ); ?>"></label>
						<?php if ( count( kcs_regions() ) > 1 ) : ?>
							<label><?php esc_html_e( 'Partner type', 'kingdom-church-suite' ); ?><select name="region"><?php foreach ( kcs_regions() as $rk => $r ) : ?><option value="<?php echo esc_attr( $rk ); ?>" <?php selected( kcs_currency_region( $p->currency ), $rk ); ?>><?php echo esc_html( $r['label'] . ' (' . $r['currency'] . ')' ); ?></option><?php endforeach; ?></select></label>
						<?php endif; ?>
						<label><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?><select name="status"><?php foreach ( KCS_Partners::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p->status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
						<label><?php esc_html_e( 'Next due date', 'kingdom-church-suite' ); ?><input type="date" name="next_due_date" value="<?php echo esc_attr( $p->next_due_date ); ?>"></label>
					</div>
					<label><?php esc_html_e( 'Notes', 'kingdom-church-suite' ); ?><textarea name="notes" rows="4"><?php echo esc_textarea( $p->notes ); ?></textarea></label>
					<button class="kcs-btn"><?php esc_html_e( 'Save partner', 'kingdom-church-suite' ); ?></button>
				</form>
				<section class="kca-card">
					<h3><?php esc_html_e( 'Giving', 'kingdom-church-suite' ); ?></h3>
					<p><?php esc_html_e( 'Total given', 'kingdom-church-suite' ); ?>: <strong><?php echo esc_html( KCS_Partners::total_given_label( $p ) ); ?></strong></p>
					<ul class="kca-list">
						<?php foreach ( $pays as $pay ) : ?>
							<li><span><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( kcs_datetime( $pay->created_at ) . ' · ' . $pay->gateway ); ?></small></span><?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
						<?php endforeach; ?>
						<?php if ( ! $pays ) : ?><li class="kcs-muted"><?php esc_html_e( 'No payments yet.', 'kingdom-church-suite' ); ?></li><?php endif; ?>
					</ul>
					<?php self::pager( $payq['total'], 10, $ppg, array( 'view' => 'partners', 'id' => $p->id ), 'ppg' ); ?>
					<?php if ( $p->phone ) : ?><p><a class="kcs-btn kcs-btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $p->phone ) ); ?>"><?php esc_html_e( 'WhatsApp this partner', 'kingdom-church-suite' ); ?></a></p><?php endif; ?>
				</section>
			</div>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<?php $kcs_login = self::partner_login_removable( $p ); ?>
				<details class="kca-card kca-danger">
					<summary><strong><?php esc_html_e( 'Delete this partner', 'kingdom-church-suite' ); ?></strong></summary>
					<form method="post" class="kcs-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this partner? This cannot be undone.', 'kingdom-church-suite' ) ); ?>')">
						<?php echo self::fields( 'partner_delete', 'partners', $p->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<p><?php esc_html_e( 'The partner is removed from your partners list, stops receiving reminders and loses access to the partner dashboard. Their past payments stay in Giving received for your records.', 'kingdom-church-suite' ); ?></p>
						<?php if ( $kcs_login ) : ?>
							<label class="kcs-check"><input type="checkbox" name="delete_login" value="1"> <?php /* translators: %s: email */ printf( esc_html__( 'Also delete their website login (%s)', 'kingdom-church-suite' ), esc_html( $kcs_login->user_email ) ); ?></label>
						<?php endif; ?>
						<button class="kcs-btn kcs-btn--danger"><?php esc_html_e( 'Delete partner', 'kingdom-church-suite' ); ?></button>
					</form>
				</details>
			<?php endif; ?>
			<?php
			return;
		}

		$per    = kcs_per_page();
		$result = KCS_Partners::query( array( 'search' => $search, 'status' => $status, 'per_page' => $per, 'page' => $page ) );
		$names  = array();
		foreach ( $plans as $pl ) {
			$names[ $pl->id ] = $pl->name;
		}
		?>
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="partners">
			<input type="search" name="find" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone, ID…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search partners', 'kingdom-church-suite' ); ?>">
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-church-suite' ); ?></option><?php foreach ( KCS_Partners::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<button class="kcs-btn"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
		</form>
		<div class="kca-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Partner', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Plan', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Next due', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Total given', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $result['items'] as $p ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Partner', 'kingdom-church-suite' ); ?>"><a href="<?php echo esc_url( self::url( 'partners', array( 'id' => $p->id ) ) ); ?>"><strong><?php echo esc_html( trim( $p->first_name . ' ' . $p->last_name ) ); ?></strong></a><small><?php echo esc_html( $p->email . ' · ' . $p->phone ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Plan', 'kingdom-church-suite' ); ?>"><?php echo esc_html( $names[ $p->plan_id ] ?? '—' ); ?><small><?php echo esc_html( kcs_format_money( $p->amount, $p->currency ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Next due', 'kingdom-church-suite' ); ?>"><?php echo $p->next_due_date ? esc_html( mysql2date( get_option( 'date_format' ), $p->next_due_date ) ) : '—'; ?></td>
						<td data-label="<?php esc_attr_e( 'Total given', 'kingdom-church-suite' ); ?>"><?php echo esc_html( KCS_Partners::total_given_label( $p ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><?php echo self::status_form( 'partner_status', 'partners', $p->id, KCS_Partners::statuses(), $p->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $result['items'] ) : ?><tr><td colspan="5" class="kcs-muted"><?php esc_html_e( 'No partners match.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		self::pager( $result['total'], $per, $page, array( 'view' => 'partners', 'find' => $search, 'status' => $status ) );
	}

	/**
	 * Payments.
	 */
	private static function view_payments() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search = isset( $_GET['find'] ) ? sanitize_text_field( wp_unslash( $_GET['find'] ) ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$purpose  = isset( $_GET['purpose'] ) ? sanitize_key( $_GET['purpose'] ) : '';
		$gateway  = isset( $_GET['gateway'] ) ? sanitize_key( $_GET['gateway'] ) : '';
		$from     = isset( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from'] ) ? (string) $_GET['from'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$to       = isset( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to'] ) ? (string) $_GET['to'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// phpcs:enable
		$purposes = kcs_all_purposes();
		$filters  = array( 'status' => $status, 'search' => $search, 'purpose' => $purpose, 'gateway' => $gateway, 'date_from' => $from, 'date_to' => $to );
		global $wpdb;
		$per      = kcs_per_page();
		$result   = KCS_Payments::query( array_merge( $filters, array( 'per_page' => $per, 'page' => $page ) ) );
		$export   = wp_nonce_url( add_query_arg( array_filter( array( 'action' => 'kcs_export', 'type' => 'payments', 'status' => $status, 's' => $search, 'purpose' => $purpose, 'gateway' => $gateway, 'date_from' => $from, 'date_to' => $to ) ), admin_url( 'admin-post.php' ) ), 'kcs_export' );
		$can_conf = KCS_Transfers::can_confirm();
		$failed_n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'payments' ) . " WHERE status IN ('failed','cancelled')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$month = $wpdb->get_results( $wpdb->prepare( 'SELECT purpose, currency, SUM(amount) total FROM ' . kcs_table( 'payments' ) . " WHERE status = 'completed' AND paid_at >= %s GROUP BY purpose, currency ORDER BY total DESC", current_time( 'Y-m-01 00:00:00' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$by    = array();
		foreach ( $month as $m ) {
			$by[ $m->purpose ][] = kcs_format_money( $m->total, $m->currency );
		}
		if ( $by ) :
			?>
			<div class="kca-kpis">
				<?php foreach ( array_slice( $by, 0, 6, true ) as $k => $totals ) : ?>
					<a class="kca-kpi" href="<?php echo esc_url( self::url( 'payments', array( 'purpose' => $k ) ) ); ?>"><span><?php echo esc_html( ( $purposes[ $k ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $k ) ) ) . ' · ' . __( 'this month', 'kingdom-church-suite' ) ); ?></span><strong><?php echo esc_html( implode( ' + ', $totals ) ); ?></strong></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php self::gateway_modes(); ?>
		<p class="kca-autocheck" data-auto-status hidden></p>
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="payments">
			<input type="search" name="find" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Reference, name or email…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search payments', 'kingdom-church-suite' ); ?>">
			<select name="purpose" aria-label="<?php esc_attr_e( 'Purpose', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All purposes', 'kingdom-church-suite' ); ?></option><?php foreach ( $purposes as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $purpose, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-church-suite' ); ?></option><?php foreach ( KCS_Payments::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<select name="gateway" aria-label="<?php esc_attr_e( 'Payment method', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All methods', 'kingdom-church-suite' ); ?></option><?php foreach ( KCS_Payments::gateways() as $g ) : ?><option value="<?php echo esc_attr( $g->id ); ?>" <?php selected( $gateway, $g->id ); ?>><?php echo esc_html( $g->display_title() ); ?></option><?php endforeach; ?></select>
			<label class="kca-date"><?php esc_html_e( 'From', 'kingdom-church-suite' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
			<label class="kca-date"><?php esc_html_e( 'To', 'kingdom-church-suite' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
			<button class="kcs-btn"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
			<?php if ( array_filter( $filters ) ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'payments' ) ); ?>"><?php esc_html_e( 'Clear', 'kingdom-church-suite' ); ?></a><?php endif; ?>
		</form>
		<div class="kca-actions kca-payments-tools">
			<span class="kcs-muted">
				<?php
				/* translators: %d: number of payments */
				echo esc_html( sprintf( _n( '%d payment', '%d payments', $result['total'], 'kingdom-church-suite' ), $result['total'] ) );
				?>
			</span>
			<?php $pending_n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'payments' ) . " WHERE status = 'pending' AND gateway NOT IN ('" . implode( "','", KCS_Transfers::MANUAL ) . "')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared ?>
			<?php if ( $pending_n ) : ?>
				<form method="post" class="kca-inline"><?php echo self::fields( 'payments_recheck', 'payments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost">
					<?php
					/* translators: %d: number */
					echo esc_html( sprintf( __( 'Check all pending now (%d)', 'kingdom-church-suite' ), $pending_n ) );
					?>
				</button></form>
			<?php endif; ?>
			<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export to Excel (CSV)', 'kingdom-church-suite' ); ?></a>
			<?php if ( $can_conf && $failed_n ) : ?>
				<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete all failed and cancelled payments? This cannot be undone.', 'kingdom-church-suite' ) ); ?>')"><?php echo self::fields( 'payments_delete_failed', 'payments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost">
					<?php
					/* translators: %d: number */
					echo esc_html( sprintf( __( 'Delete all failed & cancelled (%d)', 'kingdom-church-suite' ), $failed_n ) );
					?>
				</button></form>
			<?php endif; ?>
		</div>
		<?php if ( $can_conf && $result['items'] ) : ?>
			<form method="post" id="kca-bulk-pay" class="kca-bulk" data-kca-bulk onsubmit="return confirm('<?php echo esc_js( __( 'Do this for all ticked payments?', 'kingdom-church-suite' ) ); ?>')">
				<?php echo self::fields( 'payments_bulk', 'payments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label class="kcs-check"><input type="checkbox" data-kca-all> <?php esc_html_e( 'Select all', 'kingdom-church-suite' ); ?></label>
				<button type="button" class="kcs-btn kcs-btn--ghost" data-kca-pick-failed><?php esc_html_e( 'Select failed & cancelled', 'kingdom-church-suite' ); ?></button>
				<span class="kca-bulk__count" data-kca-count>0</span>
				<select name="bulk" aria-label="<?php esc_attr_e( 'Action', 'kingdom-church-suite' ); ?>">
					<option value="delete"><?php esc_html_e( 'Delete ticked (failed / cancelled / stuck)', 'kingdom-church-suite' ); ?></option>
					<option value="restrict"><?php esc_html_e( 'Restrict the people behind them', 'kingdom-church-suite' ); ?></option>
				</select>
				<select name="hours" aria-label="<?php esc_attr_e( 'For how long', 'kingdom-church-suite' ); ?>">
					<option value="24"><?php esc_html_e( 'for 24 hours', 'kingdom-church-suite' ); ?></option>
					<option value="168" selected><?php esc_html_e( 'for 7 days', 'kingdom-church-suite' ); ?></option>
					<option value="720"><?php esc_html_e( 'for 30 days', 'kingdom-church-suite' ); ?></option>
					<option value="0"><?php esc_html_e( 'permanently', 'kingdom-church-suite' ); ?></option>
				</select>
				<button class="kcs-btn"><?php esc_html_e( 'Apply', 'kingdom-church-suite' ); ?></button>
			</form>
		<?php endif; ?>
		<div class="kca-table">
			<table>
				<thead><tr><?php if ( $can_conf ) : ?><th class="kca-col-check"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'kingdom-church-suite' ); ?></span></th><?php endif; ?><th><?php esc_html_e( 'Giver', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Method', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php $kcs_tries = KCS_Security::attempts( wp_list_pluck( $result['items'], 'ip_hash' ) ); ?>
				<?php foreach ( $result['items'] as $pay ) : ?>
					<?php $kcs_mode = KCS_Transfers::meta( $pay )['mode'] ?? ''; ?>
					<?php
					$kcs_place   = KCS_Security::payment_place( $pay );
					$kcs_blocked = ( $pay->ip_hash && isset( KCS_Security::blocked()[ $pay->ip_hash ] ) ) || ( $pay->email && isset( KCS_Security::blocked_emails()[ strtolower( $pay->email ) ] ) );
					?>
					<tr data-pay="<?php echo (int) $pay->id; ?>" data-st="<?php echo esc_attr( $pay->status ); ?>"<?php echo 'pending' === $pay->status && ! KCS_Transfers::is_manual( $pay ) ? ' data-auto-check' : ''; ?><?php echo $kcs_place['ip'] && ! $kcs_place['known'] ? ' data-locate' : ''; ?>>
						<?php if ( $can_conf ) : ?><td class="kca-col-check"><?php if ( 'completed' !== $pay->status ) : ?><input type="checkbox" name="ids[]" value="<?php echo (int) $pay->id; ?>" form="kca-bulk-pay" data-kca-pick aria-label="<?php esc_attr_e( 'Select', 'kingdom-church-suite' ); ?>"><?php endif; ?></td><?php endif; ?>
						<td data-label="<?php esc_attr_e( 'Giver', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $pay->name ? $pay->name : $pay->email ); ?></strong><?php echo $kcs_blocked ? ' <span class="kca-pill kca-pill--blocked">⛔ ' . esc_html__( 'Restricted', 'kingdom-church-suite' ) . '</span>' : ''; ?><small><?php echo esc_html( $pay->reference . ' · ' . kcs_datetime( $pay->created_at ) ); ?></small>
							<?php if ( $kcs_place['ip'] || $kcs_place['cc'] ) : ?><small class="kca-where" data-pay-where>📍 <?php echo esc_html( $kcs_place['where'] ? $kcs_place['where'] : ( $kcs_place['cc'] ? $kcs_place['cc'] : __( 'Finding location…', 'kingdom-church-suite' ) ) ); ?><?php echo $kcs_place['ip'] && KCS_Transfers::can_confirm() ? ' · IP ' . esc_html( $kcs_place['ip'] ) : ''; ?></small><?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Amount', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( $purposes[ $pay->purpose ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $pay->purpose ) ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Method', 'kingdom-church-suite' ); ?>"><?php echo esc_html( $pay->gateway ); ?><?php echo 'test' === $kcs_mode ? ' <span class="kca-pill kca-pill--test">' . esc_html__( 'TEST', 'kingdom-church-suite' ) . '</span>' : ''; ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>" data-pay-status><?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php if ( 'completed' !== $pay->status && ! empty( $pay->ip_hash ) && ( $kcs_tries[ $pay->ip_hash ] ?? 0 ) >= 3 ) : ?><small class="kca-warn">⚠ <?php /* translators: %d: count */ echo esc_html( sprintf( __( '%d unfinished payments from this device in 24 hours — possible card testing', 'kingdom-church-suite' ), $kcs_tries[ $pay->ip_hash ] ) ); ?><?php echo isset( KCS_Security::blocked()[ $pay->ip_hash ] ) ? ' · ' . esc_html__( 'device paused', 'kingdom-church-suite' ) : ''; ?></small><?php endif; ?><?php if ( $pay->note && 'completed' !== $pay->status ) : ?><small><?php echo esc_html( $pay->note ); ?></small><?php endif; ?></td>
						<td class="kca-actions">
							<?php if ( 'completed' === $pay->status ) : ?>
								<?php $sent = KCS_Transfers::meta( $pay )['receipt_sent'] ?? ''; ?>
								<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( KCS_Receipts::url( $pay ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Receipt', 'kingdom-church-suite' ); ?></a>
								<?php if ( $pay->email ) : ?><form method="post" class="kca-inline"><?php echo self::fields( 'payment_send_receipt', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost" title="<?php echo esc_attr( $sent ? sprintf( /* translators: %s: date */ __( 'Last sent %s', 'kingdom-church-suite' ), mysql2date( 'j M Y H:i', $sent ) ) : '' ); ?>"><?php echo esc_html( $sent ? __( 'Resend receipt', 'kingdom-church-suite' ) : __( 'Send receipt', 'kingdom-church-suite' ) ); ?></button></form><?php endif; ?>
							<?php endif; ?>
							<?php if ( in_array( $pay->status, array( 'failed', 'pending', 'cancelled' ), true ) && $pay->email && ! KCS_Transfers::is_manual( $pay ) ) : ?>
								<?php $retried = KCS_Transfers::meta( $pay )['retry_sent'] ?? ''; ?>
								<form method="post" class="kca-inline"><?php echo self::fields( 'payment_send_retry', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost" title="<?php echo esc_attr( $retried ? sprintf( /* translators: %s: date */ __( 'Last sent %s', 'kingdom-church-suite' ), mysql2date( 'j M Y H:i', $retried ) ) : '' ); ?>"><?php echo esc_html( $retried ? __( 'Resend “try again” email', 'kingdom-church-suite' ) : __( 'Send “try again” email', 'kingdom-church-suite' ) ); ?></button></form>
							<?php endif; ?>
							<?php $stuck = 'pending' === $pay->status && strtotime( $pay->created_at ) < current_time( 'timestamp' ) - 30 * MINUTE_IN_SECONDS; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested ?>
							<?php if ( $can_conf && ( $stuck || in_array( $pay->status, array( 'failed', 'cancelled' ), true ) ) ) : ?>
								<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( $stuck ? __( 'This payment is still waiting. Delete it only if no money arrived (check your payment provider’s dashboard or bank). If the giver pays later, it will not be recorded automatically.', 'kingdom-church-suite' ) : __( 'Delete this failed payment?', 'kingdom-church-suite' ) ); ?>')"><?php echo self::fields( 'payment_delete', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Delete', 'kingdom-church-suite' ); ?></button></form>
							<?php endif; ?>
							<?php if ( in_array( $pay->status, array( 'pending', 'review', 'failed', 'cancelled' ), true ) ) : ?>
								<?php if ( KCS_Transfers::can_confirm() ) : ?><form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Mark this payment as received? The giver gets a receipt.', 'kingdom-church-suite' ) ); ?>')"><?php echo self::fields( 'payment_complete', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn"><?php esc_html_e( 'Mark received', 'kingdom-church-suite' ); ?></button></form><?php endif; ?>
								<?php if ( ! KCS_Transfers::is_manual( $pay ) ) : ?><form method="post" class="kca-inline"><?php echo self::fields( 'payment_verify', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Check gateway', 'kingdom-church-suite' ); ?></button></form><?php endif; ?>
							<?php endif; ?>
						<?php if ( $can_conf && 'completed' !== $pay->status && ( $pay->ip_hash || $pay->email ) ) : ?>
								<?php if ( $kcs_blocked ) : ?>
									<form method="post" class="kca-inline"><?php echo self::fields( 'payment_unrestrict', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Remove restriction', 'kingdom-church-suite' ); ?></button></form>
								<?php else : ?>
									<details class="kca-restrict"><summary class="kcs-btn kcs-btn--ghost">⛔ <?php esc_html_e( 'Restrict', 'kingdom-church-suite' ); ?></summary>
										<form method="post" class="kcs-form"><?php echo self::fields( 'payment_restrict', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<label><?php esc_html_e( 'Stop card giving from', 'kingdom-church-suite' ); ?><select name="what"><option value="both"><?php esc_html_e( 'This device (IP) and this email', 'kingdom-church-suite' ); ?></option><option value="device"><?php esc_html_e( 'This device (IP) only', 'kingdom-church-suite' ); ?></option><option value="email"><?php esc_html_e( 'This email only', 'kingdom-church-suite' ); ?></option></select></label>
											<label><?php esc_html_e( 'For', 'kingdom-church-suite' ); ?><select name="hours"><option value="24"><?php esc_html_e( '24 hours', 'kingdom-church-suite' ); ?></option><option value="168" selected><?php esc_html_e( '7 days', 'kingdom-church-suite' ); ?></option><option value="720"><?php esc_html_e( '30 days', 'kingdom-church-suite' ); ?></option><option value="0"><?php esc_html_e( 'Permanently', 'kingdom-church-suite' ); ?></option></select></label>
											<label><?php esc_html_e( 'Note (optional)', 'kingdom-church-suite' ); ?><input name="reason" maxlength="150" placeholder="<?php esc_attr_e( 'Many cards, all declined', 'kingdom-church-suite' ); ?>"></label>
											<button class="kcs-btn kcs-btn--danger"><?php esc_html_e( 'Restrict', 'kingdom-church-suite' ); ?></button>
										</form>
									</details>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $result['items'] ) : ?><tr><td colspan="5" class="kcs-muted"><?php esc_html_e( 'No payments match.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		self::pager( $result['total'], $per, $page, array_filter( array( 'view' => 'payments', 'find' => $search, 'status' => $status, 'purpose' => $purpose, 'gateway' => $gateway, 'from' => $from, 'to' => $to ) ) );
		$r = KCS_Receipts::settings();
		?>
		<details class="kca-card kcs-form kca-receipt-settings">
			<summary><strong><?php esc_html_e( 'Receipt design & emails', 'kingdom-church-suite' ); ?></strong></summary>
			<form method="post" class="kcs-form">
				<?php echo self::fields( 'receipt_settings', 'payments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="kcs-muted"><?php esc_html_e( 'The printable receipt givers open from their receipt email (and you open with “Receipt”). It shows your church name, address and logo, the amount, date, purpose, method and reference.', 'kingdom-church-suite' ); ?></p>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Receipt title', 'kingdom-church-suite' ); ?><input name="receipt_title" value="<?php echo esc_attr( $r['title'] ); ?>"></label>
					<label class="kcs-check"><input type="checkbox" name="receipt_logo" value="1" <?php checked( $r['logo'] ); ?>> <?php esc_html_e( 'Show the church logo', 'kingdom-church-suite' ); ?></label>
					<label><?php esc_html_e( 'Signed by (optional)', 'kingdom-church-suite' ); ?><input name="receipt_signatory" value="<?php echo esc_attr( $r['signatory'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Rev. John Doe', 'kingdom-church-suite' ); ?>"></label>
					<label><?php esc_html_e( 'Their title (optional)', 'kingdom-church-suite' ); ?><input name="receipt_signatory_role" value="<?php echo esc_attr( $r['role'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Church Treasurer', 'kingdom-church-suite' ); ?>"></label>
				</div>
				<label><?php esc_html_e( 'Thank-you message', 'kingdom-church-suite' ); ?><textarea name="receipt_message" rows="2"><?php echo esc_textarea( $r['message'] ); ?></textarea></label>
				<label><?php esc_html_e( 'Footer note (optional: registration number, bank details, tax note…)', 'kingdom-church-suite' ); ?><textarea name="receipt_footer" rows="2"><?php echo esc_textarea( $r['footer'] ); ?></textarea></label>
				<label class="kcs-check"><input type="checkbox" name="auto_retry_email" value="1" <?php checked( '1', (string) kcs_get_option( 'auto_retry_email', '0' ) ); ?>> <?php esc_html_e( 'Automatically send the “try again” email when an online payment fails or is not completed within 2 days', 'kingdom-church-suite' ); ?></label>
				<div class="kca-actions">
					<button class="kcs-btn"><?php esc_html_e( 'Save receipt settings', 'kingdom-church-suite' ); ?></button>
					<?php if ( current_user_can( 'manage_options' ) ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'settings', array( 'tab' => 'templates' ) ) ); ?>"><?php esc_html_e( 'Edit the receipt and “try again” email wording', 'kingdom-church-suite' ); ?></a><?php endif; ?>
				</div>
			</form>
		</details>
		<?php
	}

	/**
	 * Bank transfers: account details, and transfers waiting to be confirmed.
	 */
	private static function view_transfers() {
		$gw = KCS_Transfers::gateway();
		if ( ! $gw ) {
			echo '<p class="kcs-muted">' . esc_html__( 'Bank transfer is not available on this site.', 'kingdom-church-suite' ) . '</p>';
			return;
		}
		$can_confirm = KCS_Transfers::can_confirm();
		$can_setup   = current_user_can( 'manage_options' );
		$purposes    = kcs_all_purposes();
		$wpg         = kcs_current_page();
		$wper        = kcs_per_page();
		$waitq       = KCS_Payments::query( array( 'gateway' => KCS_Transfers::MANUAL, 'status' => 'review', 'per_page' => $wper, 'page' => $wpg ) );
		$waiting     = $waitq['items'];
		$started     = KCS_Payments::query( array( 'gateway' => KCS_Transfers::MANUAL, 'status' => 'pending', 'date_from' => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -30 days' ) ), 'per_page' => 20 ) )['items'];
		$label       = function ( $pay ) use ( $purposes ) {
			return $purposes[ $pay->purpose ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $pay->purpose ) );
		};
		$confirm_btn = function ( $pay ) {
			return '<form method="post" class="kca-inline" onsubmit="return confirm(\'' . esc_js( __( 'Confirm this money is in your account? The giver gets their receipt straight away.', 'kingdom-church-suite' ) ) . '\')">' . self::fields( 'payment_complete', 'transfers', $pay->id ) . '<button class="kcs-btn">' . esc_html__( 'Confirm received', 'kingdom-church-suite' ) . '</button></form>';
		};
		$reject_btn  = function ( $pay ) {
			return '<details class="kca-reject"><summary class="kcs-btn kcs-btn--ghost">' . esc_html__( 'Reject', 'kingdom-church-suite' ) . '</summary><form method="post" class="kcs-form">' . self::fields( 'payment_reject', 'transfers', $pay->id ) . '<label>' . esc_html__( 'Reason (emailed to the giver)', 'kingdom-church-suite' ) . '<input name="reason" maxlength="500" placeholder="' . esc_attr__( 'We could not find this transfer in our account.', 'kingdom-church-suite' ) . '"></label><button class="kcs-btn kcs-btn--danger">' . esc_html__( 'Reject transfer', 'kingdom-church-suite' ) . '</button></form></details>';
		};
		?>
		<?php if ( ! $gw->is_enabled() ) : ?>
			<div class="kcs-alert kcs-alert--info"><?php esc_html_e( 'Bank transfer is off. Add your account details below and tick "Accept bank transfers" so givers can pay straight into your account.', 'kingdom-church-suite' ); ?></div>
		<?php endif; ?>
		<?php if ( ! $can_confirm ) : ?>
			<div class="kcs-alert kcs-alert--info"><?php esc_html_e( 'Only church administrators can confirm or reject transfers. You can see what is waiting here.', 'kingdom-church-suite' ); ?></div>
		<?php endif; ?>

		<section class="kca-card">
			<h3><?php esc_html_e( 'Waiting for you to confirm', 'kingdom-church-suite' ); ?> <?php if ( $waiting ) : ?><span class="kcs-badge kcs-badge--review"><?php echo (int) $waitq['total']; ?></span><?php endif; ?></h3>
			<?php if ( ! $waiting ) : ?><p class="kcs-muted"><?php esc_html_e( 'Nothing to confirm. When a giver says they have paid, it appears here and you get an email.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
			<div class="kca-transfers">
				<?php foreach ( $waiting as $pay ) : ?>
					<?php
					$t     = KCS_Transfers::meta( $pay )['transfer'] ?? array();
					$proof = $can_confirm ? KCS_Transfers::proof_url( $pay ) : '';
					?>
					<article class="kca-transfer">
						<header>
							<strong class="kca-transfer__amt"><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong>
							<span><?php echo esc_html( $label( $pay ) ); ?></span>
							<?php $kcs_m = KCS_Payments::gateway( $pay->gateway ); ?>
							<span class="kcs-badge"><?php echo esc_html( 'bank_transfer' === $pay->gateway ? __( 'Bank transfer', 'kingdom-church-suite' ) : ( $kcs_m ? $kcs_m->display_title() : $pay->gateway ) ); ?></span>
						</header>
						<dl>
							<div><dt><?php esc_html_e( 'Giver', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $pay->name ? $pay->name : $pay->email ); ?><small><?php echo esc_html( $pay->email ); ?></small></dd></div>
							<div><dt><?php esc_html_e( 'Reference', 'kingdom-church-suite' ); ?></dt><dd><code><?php echo esc_html( $pay->reference ); ?></code></dd></div>
							<?php if ( ! empty( $t['payer'] ) ) : ?><div><dt><?php esc_html_e( 'Sent by', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $t['payer'] . ( $t['bank'] ? ' · ' . $t['bank'] : '' ) ); ?></dd></div><?php endif; ?>
							<?php if ( ! empty( $t['date'] ) ) : ?><div><dt><?php esc_html_e( 'Date sent', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), $t['date'] ) ); ?></dd></div><?php endif; ?>
							<?php if ( ! empty( $t['note'] ) ) : ?><div><dt><?php esc_html_e( 'Note', 'kingdom-church-suite' ); ?></dt><dd><?php echo esc_html( $t['note'] ); ?></dd></div><?php endif; ?>
						</dl>
						<div class="kca-actions">
							<?php if ( $proof ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( $proof ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View proof', 'kingdom-church-suite' ); ?></a><?php endif; ?>
							<?php
							if ( $can_confirm ) {
								echo $confirm_btn( $pay ) . $reject_btn( $pay ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php self::pager( $waitq['total'], $wper, $wpg, array( 'view' => 'transfers' ) ); ?>
		</section>

		<?php if ( $started ) : ?>
			<details class="kca-card">
				<summary><h3><?php esc_html_e( 'Started, giver has not said they paid', 'kingdom-church-suite' ); ?> <span class="kcs-badge"><?php echo count( $started ); ?></span></h3></summary>
				<p class="kcs-muted"><?php esc_html_e( 'Givers who chose bank transfer in the last 30 days but did not press "I have made this transfer". If the money is in your account, you can still confirm it.', 'kingdom-church-suite' ); ?></p>
				<div class="kca-table">
					<table>
						<thead><tr><th><?php esc_html_e( 'Giver', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php foreach ( $started as $pay ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Giver', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $pay->name ? $pay->name : $pay->email ); ?></strong><small><?php echo esc_html( $pay->reference . ' · ' . kcs_datetime( $pay->created_at ) ); ?></small></td>
								<td data-label="<?php esc_attr_e( 'Amount', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( $label( $pay ) ); ?></small></td>
								<td class="kca-actions"><?php echo $can_confirm ? $confirm_btn( $pay ) . $reject_btn( $pay ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>

		<?php
		if ( ! $can_setup ) {
			return;
		}
		$accounts = $gw->accounts();
		$currs    = KCS_Payments::allowed_currencies();
		foreach ( $accounts as $a ) {
			if ( $a['currency'] && ! isset( $currs[ $a['currency'] ] ) ) {
				$currs[ $a['currency'] ] = $a['currency'];
			}
		}
		?>
		<form method="post" class="kca-card kcs-form" id="transfer-settings">
			<?php echo self::fields( 'transfer_settings_save', 'transfers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Your bank accounts', 'kingdom-church-suite' ); ?></h3>
			<label class="kcs-check"><input type="checkbox" name="enabled" value="1" <?php checked( $gw->is_enabled() ); ?>> <strong><?php esc_html_e( 'Accept bank transfers', 'kingdom-church-suite' ); ?></strong></label>
			<p class="kcs-muted"><?php esc_html_e( 'Givers see these details after choosing bank transfer. Add up to 3 accounts, for example one for Naira and one for Dollars. An account with no currency is shown for every currency.', 'kingdom-church-suite' ); ?></p>
			<?php for ( $i = 0; $i < KCS_Gateway_Bank_Transfer::MAX_ACCOUNTS; $i++ ) : ?>
				<?php $a = $accounts[ $i ] ?? array_fill_keys( array( 'bank', 'name', 'number', 'currency', 'sort_code', 'iban', 'swift' ), '' ); ?>
				<fieldset class="kca-account">
					<legend><?php /* translators: %d: account number */ printf( esc_html__( 'Account %d', 'kingdom-church-suite' ), (int) $i + 1 ); ?><?php echo $i ? ' ' . esc_html__( '(optional)', 'kingdom-church-suite' ) : ''; ?></legend>
					<div class="kcs-grid-2">
						<label><?php esc_html_e( 'Bank name', 'kingdom-church-suite' ); ?><input name="accounts[<?php echo (int) $i; ?>][bank]" value="<?php echo esc_attr( $a['bank'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. GTBank', 'kingdom-church-suite' ); ?>"></label>
						<label><?php esc_html_e( 'Account name', 'kingdom-church-suite' ); ?><input name="accounts[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $a['name'] ); ?>"></label>
						<label><?php esc_html_e( 'Account number', 'kingdom-church-suite' ); ?><input name="accounts[<?php echo (int) $i; ?>][number]" value="<?php echo esc_attr( $a['number'] ); ?>" inputmode="numeric" autocomplete="off"></label>
						<label><?php esc_html_e( 'Currency', 'kingdom-church-suite' ); ?><select name="accounts[<?php echo (int) $i; ?>][currency]"><option value=""><?php esc_html_e( 'Any currency', 'kingdom-church-suite' ); ?></option><?php foreach ( $currs as $code => $name ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $a['currency'], $code ); ?>><?php echo esc_html( $code ); ?></option><?php endforeach; ?></select></label>
					</div>
					<details<?php echo $a['sort_code'] || $a['iban'] || $a['swift'] ? ' open' : ''; ?>>
						<summary><?php esc_html_e( 'International details (optional)', 'kingdom-church-suite' ); ?></summary>
						<div class="kcs-grid-2">
							<label><?php esc_html_e( 'Sort code / routing number', 'kingdom-church-suite' ); ?><input name="accounts[<?php echo (int) $i; ?>][sort_code]" value="<?php echo esc_attr( $a['sort_code'] ); ?>"></label>
							<label>IBAN<input name="accounts[<?php echo (int) $i; ?>][iban]" value="<?php echo esc_attr( $a['iban'] ); ?>"></label>
							<label>SWIFT / BIC<input name="accounts[<?php echo (int) $i; ?>][swift]" value="<?php echo esc_attr( $a['swift'] ); ?>"></label>
						</div>
					</details>
				</fieldset>
			<?php endfor; ?>

			<h3><?php esc_html_e( 'Use bank transfer for', 'kingdom-church-suite' ); ?></h3>
			<label class="kcs-check"><input type="checkbox" name="for_partnership" value="1" <?php checked( $gw->allows_purpose( 'partnership' ) ); ?>> <?php esc_html_e( 'Partnership', 'kingdom-church-suite' ); ?></label>
			<label class="kcs-check"><input type="checkbox" name="for_giving" value="1" <?php checked( $gw->allows_purpose( 'offering' ) ); ?>> <?php esc_html_e( 'Tithes, offerings and other giving', 'kingdom-church-suite' ); ?></label>

			<h3><?php esc_html_e( 'Who can confirm transfers', 'kingdom-church-suite' ); ?></h3>
			<label class="kcs-check"><input type="radio" name="confirm_by" value="admins" <?php checked( 'staff' !== $gw->opt( 'confirm_by', 'admins' ) ); ?>> <?php esc_html_e( 'Church administrators only (recommended)', 'kingdom-church-suite' ); ?></label>
			<label class="kcs-check"><input type="radio" name="confirm_by" value="staff" <?php checked( 'staff', $gw->opt( 'confirm_by', 'admins' ) ); ?>> <?php esc_html_e( 'All church staff who can open Church Admin', 'kingdom-church-suite' ); ?></label>

			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Name shown to givers', 'kingdom-church-suite' ); ?><input name="display_title" value="<?php echo esc_attr( $gw->display_title() ); ?>"></label>
			</div>
			<label><?php esc_html_e( 'Extra note for givers (optional)', 'kingdom-church-suite' ); ?><textarea name="note" rows="2" placeholder="<?php esc_attr_e( 'e.g. Transfers are confirmed within 24 hours on working days.', 'kingdom-church-suite' ); ?>"><?php echo esc_textarea( $gw->opt( 'note' ) ); ?></textarea></label>
			<button class="kcs-btn"><?php esc_html_e( 'Save bank transfer settings', 'kingdom-church-suite' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Partnership plans with international and local prices.
	 */
	private static function view_plans() {
		$freqs   = kcs_frequencies();
		$intl    = kcs_get_option( 'currency', 'USD' );
		$local   = kcs_local_currency();
		$edit_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plans   = KCS_Plans::all();
		?>
		<form method="post" class="kca-card kcs-form">
			<?php echo self::fields( 'currency_save', 'plans' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Local and international partners', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'The international currency is selected by default on the partnership, registration, giving and tithe pages. Visitors can switch to the local currency.', 'kingdom-church-suite' ); ?></p>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'International currency (default)', 'kingdom-church-suite' ); ?><select name="currency"><?php foreach ( kcs_currencies() as $c => $l ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $intl, $c ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Local currency', 'kingdom-church-suite' ); ?><select name="local_currency"><option value="none"><?php esc_html_e( '— No local option —', 'kingdom-church-suite' ); ?></option><?php foreach ( kcs_currencies() as $c => $l ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $local, $c ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'International label', 'kingdom-church-suite' ); ?><input name="international_label" value="<?php echo esc_attr( kcs_get_option( 'international_label', 'International' ) ); ?>"></label>
				<label><?php esc_html_e( 'Local label', 'kingdom-church-suite' ); ?><input name="local_label" value="<?php echo esc_attr( kcs_get_option( 'local_label', 'Local' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Nigeria', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Quick amounts (international)', 'kingdom-church-suite' ); ?><input name="amounts_international" value="<?php echo esc_attr( implode( ',', kcs_region_amounts( 'international' ) ) ); ?>"></label>
				<label><?php esc_html_e( 'Quick amounts (local)', 'kingdom-church-suite' ); ?><input name="amounts_local" value="<?php echo esc_attr( implode( ',', kcs_region_amounts( 'local' ) ) ); ?>"></label>
				<label class="kcs-check"><input type="checkbox" name="show_plan_benefits" value="1" <?php checked( '1', (string) kcs_get_option( 'show_plan_benefits', '1' ) ); ?>> <?php esc_html_e( 'Show plan benefits (the list under each plan)', 'kingdom-church-suite' ); ?></label>
				<label class="kcs-check"><input type="checkbox" name="show_partnership_reasons" value="1" <?php checked( '1', (string) kcs_get_option( 'show_partnership_reasons', '1' ) ); ?>> <?php esc_html_e( 'Show the three “why partner” cards on the Partnership page', 'kingdom-church-suite' ); ?></label>
				<label><?php esc_html_e( 'Featured payment method on Tithe & Offering', 'kingdom-church-suite' ); ?>
					<select name="tithe_gateway">
						<option value="auto" <?php selected( kcs_get_option( 'tithe_gateway', 'auto' ), 'auto' ); ?>><?php esc_html_e( 'The first one in my order', 'kingdom-church-suite' ); ?></option>
						<option value="none" <?php selected( kcs_get_option( 'tithe_gateway', 'auto' ), 'none' ); ?>><?php esc_html_e( '— None —', 'kingdom-church-suite' ); ?></option>
						<?php foreach ( KCS_Payments::ordered() as $g ) : ?>
							<option value="<?php echo esc_attr( $g->id ); ?>" <?php selected( kcs_get_option( 'tithe_gateway', 'auto' ), $g->id ); ?>><?php echo esc_html( $g->title . ( $g->is_enabled() ? '' : ' ' . __( '(not switched on)', 'kingdom-church-suite' ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<button class="kcs-btn"><?php esc_html_e( 'Save currency settings', 'kingdom-church-suite' ); ?></button>
		</form>

		<div class="kca-plans">
			<?php
			foreach ( array_merge( $plans, array( null ) ) as $pl ) :
				$open = $pl ? $edit_id === (int) $pl->id : 'new' === ( $_GET['id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<details class="kca-card kca-plan"<?php echo $open ? ' open' : ''; ?>>
					<summary>
						<?php if ( $pl ) : ?>
							<strong><?php echo esc_html( $pl->name ); ?></strong><?php echo $pl->is_featured ? ' ⭐' : ''; ?>
							<span class="kcs-muted"><?php echo esc_html( KCS_Plans::price_label( $pl, 'international' ) ); ?><?php echo $local && ! KCS_Plans::is_custom( $pl ) ? ' · ' . esc_html( (float) $pl->local_amount > 0 ? KCS_Plans::price_label( $pl, 'local' ) : __( 'local price not set', 'kingdom-church-suite' ) ) : ''; ?></span>
							<?php echo kcs_status_badge( $pl->is_active ? 'active' : 'inactive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							<strong>＋ <?php esc_html_e( 'Add a plan', 'kingdom-church-suite' ); ?></strong>
						<?php endif; ?>
					</summary>
					<form method="post" class="kcs-form">
						<?php echo self::fields( 'plan_save', 'plans', $pl ? $pl->id : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<label><?php esc_html_e( 'Plan name', 'kingdom-church-suite' ); ?><input name="name" required value="<?php echo esc_attr( $pl ? $pl->name : '' ); ?>"></label>
						<div class="kcs-grid-3">
							<?php /* translators: %s: currency code */ ?>
							<label><?php printf( esc_html__( 'International price (%s)', 'kingdom-church-suite' ), esc_html( $intl ) ); ?><input type="number" step="0.01" min="0" name="amount" value="<?php echo esc_attr( $pl ? $pl->amount : '' ); ?>"></label>
							<?php if ( $local ) : ?>
								<?php /* translators: %s: currency code */ ?>
								<label><?php printf( esc_html__( 'Local price (%s)', 'kingdom-church-suite' ), esc_html( $local ) ); ?><input type="number" step="0.01" min="0" name="local_amount" value="<?php echo esc_attr( $pl ? $pl->local_amount : '' ); ?>"></label>
							<?php endif; ?>
							<label><?php esc_html_e( 'Every', 'kingdom-church-suite' ); ?><select name="frequency"><?php foreach ( $freqs as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $pl ? $pl->frequency : 'monthly', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
						</div>
						<label><?php esc_html_e( 'Short description', 'kingdom-church-suite' ); ?><input name="description" value="<?php echo esc_attr( $pl ? $pl->description : '' ); ?>"></label>
						<label><?php esc_html_e( 'Benefits (one per line)', 'kingdom-church-suite' ); ?><textarea name="benefits" rows="4"><?php echo esc_textarea( $pl ? $pl->benefits : '' ); ?></textarea></label>
						<div class="kca-switches">
							<label class="kcs-check kca-switch"><input type="checkbox" name="allow_custom_amount" value="1" <?php checked( $pl && $pl->allow_custom_amount ); ?>> <?php esc_html_e( 'Partner chooses the amount', 'kingdom-church-suite' ); ?></label>
							<label class="kcs-check kca-switch"><input type="checkbox" name="is_featured" value="1" <?php checked( $pl && $pl->is_featured ); ?>> <?php esc_html_e( 'Most popular', 'kingdom-church-suite' ); ?></label>
							<label class="kcs-check kca-switch"><input type="checkbox" name="is_active" value="1" <?php checked( ! $pl || $pl->is_active ); ?>> <?php esc_html_e( 'Active', 'kingdom-church-suite' ); ?></label>
						</div>
						<label><?php esc_html_e( 'Level (higher number = higher plan)', 'kingdom-church-suite' ); ?><input type="number" name="sort_order" value="<?php echo esc_attr( $pl ? $pl->sort_order : count( $plans ) ); ?>"></label>
						<button class="kcs-btn"><?php echo $pl ? esc_html__( 'Save plan', 'kingdom-church-suite' ) : esc_html__( 'Add plan', 'kingdom-church-suite' ); ?></button>
					</form>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Prayer requests.
	 */
	private static function view_prayers() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search = isset( $_GET['find'] ) ? sanitize_text_field( wp_unslash( $_GET['find'] ) ) : '';
		// phpcs:enable
		$page  = kcs_current_page();
		$per   = kcs_per_page();
		$t     = kcs_table( 'prayer_requests' );
		$where = array( '1=1' );
		if ( $status && isset( KCS_Prayer::statuses()[ $status ] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}
		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = $wpdb->prepare( "CONCAT_WS(' ', name, email, phone, request) LIKE %s", $like );
		}
		$where = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY FIELD(status,'new','praying','approved','answered','archived'), id DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		echo '<p class="kcs-muted">' . esc_html__( 'Requests marked “On prayer wall” or “Answered” (and not private) appear on the public prayer wall.', 'kingdom-church-suite' ) . '</p>';
		self::list_filters( 'prayers', $search, $status, KCS_Prayer::statuses(), __( 'Search name, email or request…', 'kingdom-church-suite' ) );
		echo '<div class="kca-stack">';
		$cats = KCS_Prayer::categories();
		foreach ( $rows as $r ) {
			$meta = array_filter(
				array(
					( $r->name ? $r->name : __( 'Anonymous', 'kingdom-church-suite' ) ) . ( $r->anonymous ? ' (' . __( 'shown as Anonymous', 'kingdom-church-suite' ) . ')' : '' ),
					trim( $r->email . ' ' . $r->phone ),
					$r->city,
					$cats[ $r->category ] ?? '',
					mysql2date( get_option( 'date_format' ), $r->created_at ),
					$r->is_private ? __( 'Private', 'kingdom-church-suite' ) : __( 'Asked for the prayer wall', 'kingdom-church-suite' ),
					$r->prayer_count ? '🙏 ' . (int) $r->prayer_count : '',
				)
			);
			$encs = $r->is_private ? array() : KCS_Prayer::encouragements( (int) $r->id, true );
			echo '<article class="kca-card kca-item"><div><p>' . nl2br( esc_html( $r->request ) ) . '</p><small class="kcs-muted">' . esc_html( implode( ' · ', $meta ) ) . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $r->praise ) {
				echo '<p class="kca-praise"><strong>' . esc_html__( 'Praise report', 'kingdom-church-suite' ) . ':</strong> ' . nl2br( esc_html( $r->praise ) ) . '</p>';
			}
			if ( $encs ) {
				echo '<details class="kca-encs"><summary>' . esc_html( sprintf( /* translators: %d: count */ _n( '%d encouragement', '%d encouragements', count( $encs ), 'kingdom-church-suite' ), count( $encs ) ) ) . '</summary><ul>';
				foreach ( $encs as $e ) {
					echo '<li class="is-' . esc_attr( $e->status ) . '"><span>' . nl2br( esc_html( $e->message ) ) . '<small class="kcs-muted"> — ' . esc_html( ( $e->name ? $e->name : __( 'A friend', 'kingdom-church-suite' ) ) . ' · ' . mysql2date( get_option( 'date_format' ), $e->created_at ) ) . ( 'visible' !== $e->status ? ' · ' . esc_html( 'pending' === $e->status ? __( 'Waiting for review', 'kingdom-church-suite' ) : __( 'Hidden', 'kingdom-church-suite' ) ) : '' ) . '</small></span><form method="post" class="kca-inline">' . self::fields( 'prayer_enc', 'prayers', (int) $e->id ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						. ( 'visible' !== $e->status ? '<button class="kcs-btn kcs-btn--ghost" name="decision" value="visible">' . esc_html__( 'Show', 'kingdom-church-suite' ) . '</button>' : '<button class="kcs-btn kcs-btn--ghost" name="decision" value="hidden">' . esc_html__( 'Hide', 'kingdom-church-suite' ) . '</button>' )
						. '<button class="kcs-btn kcs-btn--ghost" name="decision" value="delete">' . esc_html__( 'Delete', 'kingdom-church-suite' ) . '</button></form></li>';
				}
				echo '</ul></details>';
			}
			echo '</div>' . self::status_form( 'prayer_status', 'prayers', $r->id, KCS_Prayer::statuses(), $r->status ) . '</article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! $rows ) {
			echo '<p class="kcs-muted">' . esc_html( $search || $status ? __( 'No prayer requests match.', 'kingdom-church-suite' ) : __( 'No prayer requests yet.', 'kingdom-church-suite' ) ) . '</p>';
		}
		echo '</div>';
		self::pager( $total, $per, $page, array( 'view' => 'prayers', 'find' => $search, 'status' => $status ) );
	}

	/**
	 * Search box + status filter above a list.
	 *
	 * @param string $view        View.
	 * @param string $search      Search.
	 * @param string $status      Status.
	 * @param array  $statuses    Statuses.
	 * @param string $placeholder Placeholder.
	 */
	private static function list_filters( $view, $search, $status, $statuses, $placeholder ) {
		?>
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
			<input type="search" name="find" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" aria-label="<?php esc_attr_e( 'Search', 'kingdom-church-suite' ); ?>">
			<?php if ( $statuses ) : ?>
				<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-church-suite' ); ?></option><?php foreach ( $statuses as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<?php endif; ?>
			<button class="kcs-btn"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
		</form>
		<?php
	}
	/**
	 * Visit requests.
	 */
	private static function view_visits() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search = isset( $_GET['find'] ) ? sanitize_text_field( wp_unslash( $_GET['find'] ) ) : '';
		// phpcs:enable
		$page  = kcs_current_page();
		$per   = kcs_per_page();
		$t     = kcs_table( 'visits' );
		$where = array( '1=1' );
		if ( $status && isset( KCS_Visits::statuses()[ $status ] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}
		if ( $search ) {
			$where[] = $wpdb->prepare( "CONCAT_WS(' ', name, email, phone) LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
		}
		$where = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY FIELD(status,'new','confirmed','visited','no_show'), visit_date ASC, id DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::list_filters( 'visits', $search, $status, KCS_Visits::statuses(), __( 'Search name, email or phone…', 'kingdom-church-suite' ) );
		echo '<div class="kca-stack">';
		foreach ( $rows as $v ) {
			$wa = $v->phone ? '<a class="kcs-btn kcs-btn--ghost" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $v->phone ) ) . '">WhatsApp</a>' : '';
			echo '<article class="kca-card kca-item"><div><strong>' . esc_html( $v->name ) . '</strong> <span class="kcs-muted">' . esc_html( ( $v->visit_date ? mysql2date( 'D, ' . get_option( 'date_format' ), $v->visit_date ) : __( 'Date not set', 'kingdom-church-suite' ) ) . ( $v->service ? ' · ' . $v->service : '' ) ) . '</span><p class="kcs-muted">' . esc_html( sprintf( /* translators: 1: adults, 2: kids */ __( '%1$d adult(s), %2$d child(ren)', 'kingdom-church-suite' ), $v->adults, $v->kids ) . ' · ' . trim( $v->email . ' ' . $v->phone ) ) . '</p>' . ( $v->notes ? '<p>' . nl2br( esc_html( $v->notes ), false ) . '</p>' : '' ) . '</div><div class="kca-actions">' . $wa . self::status_form( 'visit_status', 'visits', $v->id, KCS_Visits::statuses(), $v->status ) . '</div></article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $rows ) {
			echo '<p class="kcs-muted">' . esc_html( $search || $status ? __( 'No visit requests match.', 'kingdom-church-suite' ) : __( 'No visit requests yet. They come from the Visit Us page.', 'kingdom-church-suite' ) ) . '</p>';
		}
		echo '</div>';
		self::pager( $total, $per, $page, array( 'view' => 'visits', 'find' => $search, 'status' => $status ) );
	}

	/**
	 * Testimonies waiting for review.
	 */
	private static function view_testimonies() {
		$page    = kcs_current_page();
		$per     = kcs_per_page();
		$q       = new WP_Query( array( 'post_type' => 'kcs_testimony', 'post_status' => 'pending', 'posts_per_page' => $per, 'paged' => $page ) );
		$pending = $q->posts;
		$labels  = array(
			'text'   => __( '✍️ Written', 'kingdom-church-suite' ),
			'photos' => __( '📷 With pictures', 'kingdom-church-suite' ),
			'video'  => __( '▶️ YouTube video', 'kingdom-church-suite' ),
		);
		echo '<div class="kca-stack">';
		foreach ( $pending as $t ) {
			$type  = (string) get_post_meta( $t->ID, '_kcs_story_type', true );
			$yt    = KCS_Post_Types::youtube_id( (string) get_post_meta( $t->ID, '_kcs_video_url', true ) );
			$secs  = (int) get_post_meta( $t->ID, '_kcs_video_seconds', true );
			$email = (string) get_post_meta( $t->ID, '_kcs_submitter_email', true );
			echo '<article class="kca-card kca-item kca-story"><div>';
			echo '<strong>' . esc_html( get_the_title( $t ) ) . '</strong> ' . ( isset( $labels[ $type ] ) ? '<span class="kca-pill">' . esc_html( $labels[ $type ] ) . '</span>' : '' );
			echo '<div>' . wp_kses_post( $t->post_content ) . '</div>';
			if ( $yt ) {
				echo '<a class="kca-story__video" href="' . esc_url( 'https://www.youtube.com/watch?v=' . $yt ) . '" target="_blank" rel="noopener"><img src="' . esc_url( 'https://i.ytimg.com/vi/' . $yt . '/mqdefault.jpg' ) . '" alt="" loading="lazy"><span>▶ ' . esc_html__( 'Watch on YouTube before publishing', 'kingdom-church-suite' ) . ( $secs ? ' · ' . esc_html( gmdate( $secs >= 3600 ? 'G:i:s' : 'i:s', $secs ) ) : '' ) . '</span></a>';
			}
			$gallery = KCS_Stories::gallery( $t->ID );
			if ( $gallery ) {
				echo '<div class="kca-story__pics">';
				foreach ( $gallery as $att ) {
					echo '<a href="' . esc_url( (string) wp_get_attachment_image_url( $att, 'large' ) ) . '" target="_blank" rel="noopener">' . wp_get_attachment_image( $att, 'thumbnail', false, array( 'loading' => 'lazy' ) ) . '</a>';
				}
				echo '</div>';
			}
			echo '<small class="kcs-muted">— ' . esc_html( implode( ' · ', array_filter( array( get_post_meta( $t->ID, '_kcs_person', true ), get_post_meta( $t->ID, '_kcs_location', true ), $email, get_the_date( '', $t ), get_post_meta( $t->ID, '_kcs_consent', true ) ? __( 'Agreed to be shared', 'kingdom-church-suite' ) : '' ) ) ) ) . '</small></div><div class="kca-actions">';
			echo '<form method="post" class="kca-inline">' . self::fields( 'testimony', 'testimonies', $t->ID ) . '<input type="hidden" name="decision" value="publish"><button class="kcs-btn">' . esc_html__( 'Publish', 'kingdom-church-suite' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<form method="post" class="kca-inline" onsubmit="return confirm(\'' . esc_js( __( 'Remove this story and its pictures?', 'kingdom-church-suite' ) ) . '\')">' . self::fields( 'testimony', 'testimonies', $t->ID ) . '<input type="hidden" name="decision" value="trash"><button class="kcs-btn kcs-btn--ghost">' . esc_html__( 'Remove', 'kingdom-church-suite' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div></article>';
		}
		if ( ! $pending ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No stories are waiting for review.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
		self::pager( $q->found_posts, $per, $page, array( 'view' => 'testimonies' ) );
		$st = KCS_Stories::settings();
		?>
		<form method="post" class="kca-card kcs-form kca-gap">
			<?php echo self::fields( 'testimony_settings', 'testimonies', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( '“Share your story” settings', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'People can always write their story. Choose whether they may add pictures or a YouTube video, and the limits. Videos are links only — nothing is uploaded to your website.', 'kingdom-church-suite' ); ?></p>
			<label class="kcs-check"><input type="checkbox" name="story_photos" value="1" <?php checked( $st['photos'] ); ?>> <?php esc_html_e( 'Allow pictures', 'kingdom-church-suite' ); ?></label>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Most pictures per story', 'kingdom-church-suite' ); ?><input type="number" min="1" max="10" name="story_max_photos" value="<?php echo (int) $st['max_photos']; ?>"></label>
				<label><?php esc_html_e( 'Largest picture (MB)', 'kingdom-church-suite' ); ?><input type="number" min="1" max="50" name="story_photo_mb" value="<?php echo (int) kcs_get_option( 'story_photo_mb', 5 ); ?>"><small class="kcs-muted"><?php /* translators: %d: MB */ echo esc_html( sprintf( __( 'Your hosting accepts up to %d MB per file. Pictures are made smaller on the phone before sending.', 'kingdom-church-suite' ), (int) floor( wp_max_upload_size() / MB_IN_BYTES ) ) ); ?></small></label>
			</div>
			<label class="kcs-check"><input type="checkbox" name="story_video" value="1" <?php checked( $st['video'] ); ?>> <?php esc_html_e( 'Allow a YouTube video link', 'kingdom-church-suite' ); ?></label>
			<label><?php esc_html_e( 'Longest video (minutes)', 'kingdom-church-suite' ); ?><input type="number" min="1" max="60" name="story_video_minutes" value="<?php echo (int) $st['max_minutes']; ?>" style="max-width:140px">
				<small class="kcs-muted"><?php echo esc_html( kcs_get_option( 'yt_api_key' ) ? __( 'Video length is checked automatically with your YouTube API key.', 'kingdom-church-suite' ) : __( 'Add a YouTube API key in Settings → Live stream to check video length automatically. Without it, the length is in the guidelines and you check it before publishing.', 'kingdom-church-suite' ) ); ?></small></label>
			<label><?php esc_html_e( 'Video guidelines (one per line — leave empty for ours)', 'kingdom-church-suite' ); ?><textarea name="story_guidelines" rows="5" placeholder="<?php echo esc_attr( implode( "\n", KCS_Stories::guidelines( $st['max_minutes'] ) ) ); ?>"><?php echo esc_textarea( $st['guidelines'] ); ?></textarea></label>
			<div><button class="kcs-btn"><?php esc_html_e( 'Save', 'kingdom-church-suite' ); ?></button></div>
		</form>
		<?php
	}

	/**
	 * Team & permissions.
	 */
	private static function view_team() {
		KCS_Team::view(
			function ( $do, $view, $id ) {
				return self::fields( $do, $view, $id );
			}
		);
	}

	/**
	 * Announcement slider.
	 */
	private static function view_slides() {
		KCS_Slides::view(
			function ( $do, $view, $id ) {
				return self::fields( $do, $view, $id );
			}
		);
	}

	/**
	 * Popups.
	 */
	private static function view_popups() {
		KCS_Popups::view(
			function ( $do, $view, $id ) {
				return self::fields( $do, $view, $id );
			}
		);
	}

	/**
	 * Contact messages.
	 */
	private static function view_messages() {
		KCS_Contact::view(
			function ( $do, $view, $id ) {
				return self::fields( $do, $view, $id );
			}
		);
	}

	/**
	 * Pages: hide/show, new pages (page builder or HTML & CSS), menu.
	 */
	private static function view_pages() {
		KCS_Pages::view(
			function ( $do, $view, $id ) {
				return self::fields( $do, $view, $id );
			}
		);
	}

	/**
	 * Notify partners (email and/or dashboard announcement, by plan).
	 */
	private static function view_notify() {
		$plans = KCS_Plans::all();
		?>
		<div class="kca-cols">
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'notify', 'notify' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<fieldset><legend><?php esc_html_e( 'Who should receive it?', 'kingdom-church-suite' ); ?></legend>
					<div class="kca-chips">
						<?php foreach ( $plans as $pl ) : ?>
							<label class="kca-chip"><input type="checkbox" name="plan_ids[]" value="<?php echo (int) $pl->id; ?>"> <?php echo esc_html( $pl->name ); ?></label>
						<?php endforeach; ?>
					</div>
					<small class="kcs-muted"><?php esc_html_e( 'Leave every plan unticked to reach all partners.', 'kingdom-church-suite' ); ?></small>
					<label><?php esc_html_e( 'Partner status', 'kingdom-church-suite' ); ?><select name="audience_status"><option value=""><?php esc_html_e( 'Any status', 'kingdom-church-suite' ); ?></option><option value="active"><?php esc_html_e( 'Active only', 'kingdom-church-suite' ); ?></option><option value="overdue"><?php esc_html_e( 'Overdue only (renewal reminder)', 'kingdom-church-suite' ); ?></option><option value="pending"><?php esc_html_e( 'Awaiting first payment', 'kingdom-church-suite' ); ?></option></select></label>
				</fieldset>
				<label><?php esc_html_e( 'Subject / title', 'kingdom-church-suite' ); ?><input name="subject" required></label>
				<label><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?><textarea name="message" rows="6" required>Dear {first_name},&#10;&#10;</textarea></label>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Type', 'kingdom-church-suite' ); ?><select name="kind"><option value="news"><?php esc_html_e( 'Announcement', 'kingdom-church-suite' ); ?></option><option value="event"><?php esc_html_e( 'Event / meeting', 'kingdom-church-suite' ); ?></option><option value="resource"><?php esc_html_e( 'Resource (teaching, e-book, video)', 'kingdom-church-suite' ); ?></option></select></label>
					<label><?php esc_html_e( 'Link (optional)', 'kingdom-church-suite' ); ?><input type="url" name="link" placeholder="https://"></label>
				</div>
				<label class="kcs-check"><input type="checkbox" name="send_email" value="1" checked> <?php esc_html_e( 'Send by email', 'kingdom-church-suite' ); ?></label>
				<label class="kcs-check"><input type="checkbox" name="post_dashboard" value="1" checked> <?php esc_html_e( 'Show on their partner dashboard', 'kingdom-church-suite' ); ?></label>
				<label class="kcs-check"><input type="checkbox" name="send_push" value="1" checked> <?php esc_html_e( 'Send to their phones (partners who turned on app notifications)', 'kingdom-church-suite' ); ?></label>
				<button class="kcs-btn"><?php esc_html_e( 'Send notification', 'kingdom-church-suite' ); ?></button>
			</form>
			<section class="kca-card">
				<h3><?php esc_html_e( 'On partner dashboards now', 'kingdom-church-suite' ); ?></h3>
				<ul class="kca-list">
					<?php
					$names = array();
					foreach ( $plans as $pl ) {
						$names[ $pl->id ] = $pl->name;
					}
					foreach ( KCS_Announcements::recent( 30 ) as $a ) :
						$targets = array_filter( array_map( 'intval', explode( ',', $a->plan_ids ) ) );
						?>
						<li><span><strong><?php echo esc_html( $a->title ); ?></strong><small><?php echo esc_html( ( $targets ? implode( ', ', array_map( function ( $t ) use ( $names ) { return $names[ $t ] ?? '#' . $t; }, $targets ) ) : __( 'All partners', 'kingdom-church-suite' ) ) . ' · ' . mysql2date( get_option( 'date_format' ), $a->created_at ) ); ?></small></span>
							<form method="post" class="kca-inline"><?php echo self::fields( 'announcement_delete', 'notify', $a->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost" aria-label="<?php esc_attr_e( 'Remove', 'kingdom-church-suite' ); ?>">✕</button></form></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
		<?php
	}

	/**
	 * Pastors & WhatsApp numbers.
	 */
	private static function view_pastors() {
		$plans   = KCS_Access::ranked_plans();
		$top     = KCS_Access::top_plan_id();
		$pastors = KCS_Pastors::all();
		$edit    = null;
		$want    = isset( $_GET['pastor'] ) ? sanitize_key( $_GET['pastor'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $pastors as $p ) {
			if ( $p['id'] === $want ) {
				$edit = $p;
			}
		}
		?>
		<?php $chat = KCS_WhatsApp_Chat::get(); ?>
		<form method="post" class="kca-card kcs-form">
			<?php echo self::fields( 'wa_chat_save', 'pastors' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Website “Chat with us” on WhatsApp', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'A floating WhatsApp button on every page of the public website. Visitors pick a contact and WhatsApp opens with a ready-made message.', 'kingdom-church-suite' ); ?></p>
			<label class="kcs-check"><input type="checkbox" name="wa_enabled" value="1" <?php checked( ! empty( $chat['enabled'] ) ); ?>> <?php esc_html_e( 'Show the WhatsApp chat button on the website', 'kingdom-church-suite' ); ?></label>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Chat title', 'kingdom-church-suite' ); ?><input name="wa_title" value="<?php echo esc_attr( $chat['title'] ); ?>"></label>
				<label><?php esc_html_e( 'Reply time / office hours', 'kingdom-church-suite' ); ?><input name="wa_hours" value="<?php echo esc_attr( $chat['hours'] ); ?>"></label>
			</div>
			<label><?php esc_html_e( 'Greeting', 'kingdom-church-suite' ); ?><textarea name="wa_greeting" rows="2"><?php echo esc_textarea( $chat['greeting'] ); ?></textarea></label>
			<fieldset><legend><?php esc_html_e( 'Contacts (leave the number empty to remove a row)', 'kingdom-church-suite' ); ?></legend>
				<?php
				$rows = $chat['contacts'];
				if ( ! $rows ) {
					$rows = array(
						array( 'label' => __( 'General enquiries', 'kingdom-church-suite' ), 'phone' => '', 'message' => '' ),
						array( 'label' => __( 'Prayer line', 'kingdom-church-suite' ), 'phone' => '', 'message' => __( 'Hello, I would like someone to pray with me.', 'kingdom-church-suite' ) ),
						array( 'label' => __( 'First-time visitors', 'kingdom-church-suite' ), 'phone' => '', 'message' => __( 'Hello, I am planning to visit {church} this Sunday.', 'kingdom-church-suite' ) ),
					);
				}
				$rows[] = array( 'label' => '', 'phone' => '', 'message' => '' );
				foreach ( $rows as $i => $row ) :
					?>
					<div class="kca-wa-row">
						<input name="wa_label[]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label, e.g. Church office', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'kingdom-church-suite' ); ?>">
						<input name="wa_phone[]" value="<?php echo esc_attr( $row['phone'] ); ?>" inputmode="tel" placeholder="2348012345678" aria-label="<?php esc_attr_e( 'WhatsApp number with country code', 'kingdom-church-suite' ); ?>">
						<input name="wa_message[]" value="<?php echo esc_attr( $row['message'] ); ?>" placeholder="<?php esc_attr_e( 'Pre-filled message (optional)', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Pre-filled message', 'kingdom-church-suite' ); ?>">
					</div>
				<?php endforeach; ?>
				<small class="kcs-muted"><?php esc_html_e( 'Use {church} or {page} in a message to insert the church name or the page the visitor is on.', 'kingdom-church-suite' ); ?></small>
			</fieldset>
			<button class="kcs-btn"><?php esc_html_e( 'Save website chat', 'kingdom-church-suite' ); ?></button>
		</form>

		<h3><?php esc_html_e( 'Pastors for partners', 'kingdom-church-suite' ); ?></h3>
		<p class="kcs-muted"><?php esc_html_e( 'Partners see a “Chat on WhatsApp” button for each pastor assigned to their plan (and to lower plans). Partners on the highest plan can chat with every pastor.', 'kingdom-church-suite' ); ?></p>
		<div class="kca-cols">
			<div class="kca-stack">
				<?php foreach ( $pastors as $p ) : ?>
					<article class="kca-card kca-item">
						<div class="kca-person">
							<?php if ( $p['photo'] ) : ?><img src="<?php echo esc_url( $p['photo'] ); ?>" alt=""><?php else : ?><span class="kca-avatar"><?php echo esc_html( mb_substr( $p['name'], 0, 1 ) ); ?></span><?php endif; ?>
							<div><strong><?php echo esc_html( $p['name'] ); ?></strong> <?php echo empty( $p['active'] ) ? kcs_status_badge( 'paused' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><small class="kcs-muted"><?php echo esc_html( $p['role'] . ' · +' . $p['phone'] ); ?></small>
								<div class="kca-chips">
									<?php foreach ( $plans as $pl ) : ?>
										<?php if ( in_array( (int) $pl->id, (array) $p['plan_ids'], true ) || (int) $pl->id === $top ) : ?><span class="kca-chip is-on"><?php echo esc_html( $pl->name ); ?></span><?php endif; ?>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<div class="kca-actions">
							<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'pastors', array( 'pastor' => $p['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a>
							<form method="post" class="kca-inline"><?php echo self::fields( 'pastor_delete', 'pastors' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="pastor_id" value="<?php echo esc_attr( $p['id'] ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Remove', 'kingdom-church-suite' ); ?></button></form>
						</div>
					</article>
				<?php endforeach; ?>
				<?php if ( ! $pastors ) : ?><p class="kcs-muted"><?php esc_html_e( 'No pastors yet. Add the first one with the form.', 'kingdom-church-suite' ); ?></p><?php endif; ?>
			</div>
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'pastor_save', 'pastors' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $edit['id'] ?? '' ); ?>">
				<h3><?php echo $edit ? esc_html__( 'Edit pastor', 'kingdom-church-suite' ) : esc_html__( 'Add a pastor', 'kingdom-church-suite' ); ?></h3>
				<label><?php esc_html_e( 'Name', 'kingdom-church-suite' ); ?><input name="name" required value="<?php echo esc_attr( $edit['name'] ?? '' ); ?>" placeholder="Pastor Emeka Obi"></label>
				<label><?php esc_html_e( 'Role', 'kingdom-church-suite' ); ?><input name="role" value="<?php echo esc_attr( $edit['role'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Partnership pastor', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'WhatsApp number with country code', 'kingdom-church-suite' ); ?><input name="phone" required inputmode="tel" value="<?php echo esc_attr( $edit['phone'] ?? '' ); ?>" placeholder="2348012345678"></label>
				<label><?php esc_html_e( 'Photo (optional)', 'kingdom-church-suite' ); ?><input type="url" name="photo" value="<?php echo esc_attr( $edit['photo'] ?? '' ); ?>"<?php echo current_user_can( 'upload_files' ) ? ' data-kcs-media="url"' : ''; ?>></label>
				<fieldset><legend><?php esc_html_e( 'Available to plans', 'kingdom-church-suite' ); ?></legend>
					<div class="kca-chips">
						<?php foreach ( $plans as $pl ) : ?>
							<label class="kca-chip"><input type="checkbox" name="plan_ids[]" value="<?php echo (int) $pl->id; ?>" <?php checked( in_array( (int) $pl->id, (array) ( $edit['plan_ids'] ?? array() ), true ) || (int) $pl->id === $top ); ?> <?php disabled( (int) $pl->id === $top ); ?>> <?php echo esc_html( $pl->name ); ?><?php echo (int) $pl->id === $top ? ' ★' : ''; ?></label>
						<?php endforeach; ?>
					</div>
					<small class="kcs-muted"><?php esc_html_e( '★ The highest plan always reaches every pastor.', 'kingdom-church-suite' ); ?></small>
				</fieldset>
				<label class="kcs-check"><input type="checkbox" name="active" value="1" <?php checked( ! $edit || ! empty( $edit['active'] ) ); ?>> <?php esc_html_e( 'Available for chat', 'kingdom-church-suite' ); ?></label>
				<button class="kcs-btn"><?php esc_html_e( 'Save pastor', 'kingdom-church-suite' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Website: colours, fonts, banner, sections and footer (settings provided by the active theme).
	 */
	private static function view_website() {
		if ( current_user_can( 'manage_options' ) ) {
			?>
			<form method="post" class="kca-card kcs-form" id="service-times-card">
				<?php echo self::fields( 'schedule_save', 'website' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Service times', 'kingdom-church-suite' ); ?></h3>
				<p class="kcs-muted"><?php esc_html_e( 'Used for “Next live: …”, the countdown, the service times bar, the footer and Google. Add every weekly service.', 'kingdom-church-suite' ); ?></p>
				<?php self::schedule_editor(); ?>
				<?php self::timezone_field(); ?>
				<button class="kcs-btn"><?php esc_html_e( 'Save service times', 'kingdom-church-suite' ); ?></button>
			</form>
			<?php
		}
		self::website_form(
			array(
				'brand'    => __( 'Church colours', 'kingdom-church-suite' ),
				'fonts'    => __( 'Church fonts', 'kingdom-church-suite' ),
				'banner'   => __( 'Homepage banner', 'kingdom-church-suite' ),
				'media'    => __( 'Live stream, video & audio', 'kingdom-church-suite' ),
				'sections' => __( 'Show or hide sections', 'kingdom-church-suite' ),
				'footer'   => __( 'Footer', 'kingdom-church-suite' ),
			),
			'website'
		);
	}

	/**
	 * Pictures & text: logo, website pictures, homepage texts, contact details and social links.
	 */
	private static function view_content() {
		self::website_form(
			array(
				'pictures' => __( 'Pictures & logo', 'kingdom-church-suite' ),
				'texts'    => __( 'Homepage text', 'kingdom-church-suite' ),
				'contact'  => __( 'Contact & social', 'kingdom-church-suite' ),
				'visitpage' => __( 'Visit page', 'kingdom-church-suite' ),
				'charity'  => __( 'Charity', 'kingdom-church-suite' ),
			),
			'content'
		);
	}

	/**
	 * A form for some of the theme's website settings.
	 *
	 * @param array  $groups group => title.
	 * @param string $view   View to return to.
	 */
	private static function website_form( $groups, $view ) {
		$fields = (array) apply_filters( 'kcs_website_settings', array() );
		if ( ! $fields ) {
			echo '<p class="kcs-muted">' . esc_html__( 'The active theme does not offer website settings here. Use Appearance → Customize instead.', 'kingdom-church-suite' ) . '</p>';
			return;
		}
		$can_upload = current_user_can( 'upload_files' );
		?>
		<form method="post" class="kca-cols">
			<?php echo self::fields( 'website_save', $view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php foreach ( array_keys( $groups ) as $g ) : ?><input type="hidden" name="groups[]" value="<?php echo esc_attr( $g ); ?>"><?php endforeach; ?>
			<?php foreach ( $groups as $group => $title ) : ?>
				<?php
				if ( ! array_filter( $fields, function ( $f ) use ( $group ) { return $f[2] === $group; } ) ) {
					continue;
				}
				?>
				<section class="kca-card kcs-form" id="group-<?php echo esc_attr( $group ); ?>">
					<h3><?php echo esc_html( $title ); ?></h3>
					<?php if ( 'banner' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Upload a video from your phone or computer, or paste a YouTube, Vimeo or MP4 link. It plays muted and looping behind the homepage banner and fills the banner on every screen size. Leave it empty to use the banner image.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'brand' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Pick a ready-made palette, or choose Custom and set your own church colours. Button text switches between white and dark automatically so it stays readable.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'media' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Paste your YouTube channel link or @handle once: the website shows your live stream the moment you go live, a countdown when a stream is scheduled, and your replay video the rest of the time. Videos and audio left empty show your latest sermons.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'fonts' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Choose a ready-made pairing (a heading font and a body text font that look good together), or Custom to pick your own. The preview updates as you choose.', 'kingdom-church-suite' ); ?></p>
						<?php self::font_preview(); ?>
					<?php elseif ( 'pictures' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Upload your own photos from your phone or computer. Remove a photo to go back to the built-in illustration; gallery photos you leave empty are simply not shown (with none, the gallery is hidden). Wide photos work best for the banner.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'texts' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'The words on your homepage banner and welcome section. Button links are optional: leave them empty to use the usual pages.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'visitpage' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'The information visitors read on your Visit page, the questions and answers for visitors from abroad, and the visitor questionnaire. Answers to the questionnaire arrive in First-time visitors and by email. The sample text is there to be replaced with your own.', 'kingdom-church-suite' ); ?></p>
						<?php if ( kcs_get_option( 'page_visit' ) ) : ?><p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( kcs_page_url( 'visit' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the Visit page', 'kingdom-church-suite' ); ?></a></p><?php endif; ?>
					<?php elseif ( 'charity' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Your church’s humanitarian outreach. It shows on the About page and the Charity page. Untick “Charity” in Design & live stream → Show or hide sections to hide it everywhere.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'contact' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Shown in the menu, the footer and the contact section. Leave a social link empty to hide its icon.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'footer' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Turn the footer off, pick its layout and colours, and choose what it shows. Social links, address and phone come from your contact details; quick links come from the footer menu.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'sections' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Untick a section to remove it from every page of the website.', 'kingdom-church-suite' ); ?></p>
						<div class="kca-switches">
					<?php endif; ?>
					<?php do_action( 'kcs_website_group_intro', $group ); ?>
					<?php
					foreach ( $fields as $key => $f ) :
						if ( $f[2] !== $group ) {
							continue;
						}
						$value = get_theme_mod( $key, $f[3] );
						echo '<span class="kca-anchor" id="wf-' . esc_attr( $key ) . '"></span>';
						if ( 'checkbox' === $f[1] ) :
							?>
							<label class="kcs-check kca-switch"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( (bool) $value ); ?>> <?php echo esc_html( $f[0] ); ?></label>
						<?php elseif ( 'color' === $f[1] ) : ?>
							<label class="kca-colour"><input type="color" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"> <span><?php echo esc_html( $f[0] ); ?> <code><?php echo esc_html( $value ); ?></code></span></label>
						<?php elseif ( in_array( $f[1], array( 'textarea', 'longtext' ), true ) ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><textarea name="<?php echo esc_attr( $key ); ?>" rows="<?php echo 'longtext' === $f[1] ? 12 : 3; ?>"><?php echo esc_textarea( $value ); ?></textarea></label>
						<?php elseif ( in_array( $f[1], array( 'image', 'image_id' ), true ) ) : ?>
							<?php $preview = 'image_id' === $f[1] ? ( $value ? (string) wp_get_attachment_image_url( (int) $value, 'medium' ) : '' ) : (string) $value; ?>
							<div class="kca-image" data-kcs-media-field>
								<span class="kca-image__label"><?php echo esc_html( $f[0] ); ?></span>
								<img class="kca-image__preview" src="<?php echo esc_url( $preview ); ?>" alt=""<?php echo $preview ? '' : ' hidden'; ?>>
								<?php if ( $can_upload ) : ?>
									<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ? $value : '' ); ?>" data-kcs-media="<?php echo 'image_id' === $f[1] ? 'id' : 'url'; ?>">
								<?php elseif ( 'image' === $f[1] ) : ?>
									<input type="url" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="https://…/photo.jpg">
								<?php endif; ?>
							</div>
						<?php elseif ( 'video' === $f[1] ) : ?>
							<?php $is_file = (bool) preg_match( '~\.(mp4|m4v|webm|ogv|mov)(\?.*)?$~i', (string) $value ); ?>
							<div class="kca-image kca-video" data-kcs-media-field>
								<label class="kca-image__label" for="kca-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f[0] ); ?></label>
								<video class="kca-image__preview" src="<?php echo esc_url( $is_file ? $value : '' ); ?>" muted loop playsinline preload="metadata"<?php echo $is_file ? '' : ' hidden'; ?>></video>
								<input type="url" id="kca-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo false !== strpos( $key, 'small' ) ? 'https://…/banner-phone.mp4' : 'https://youtu.be/…'; ?>"<?php echo $can_upload ? ' data-kcs-media="video"' : ''; ?>>
								<?php if ( $can_upload ) : ?>
									<?php /* translators: %s: largest file size, e.g. 50 MB */ ?>
									<small class="kcs-muted"><?php printf( esc_html__( 'Best: MP4, 15–30 seconds, 720p or 1080p, no sound needed. Largest upload allowed: %s.', 'kingdom-church-suite' ), esc_html( size_format( wp_max_upload_size() ) ) ); ?></small>
								<?php endif; ?>
							</div>
						<?php elseif ( 'email' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><input type="email" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"></label>
						<?php elseif ( 'emails' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><input type="text" inputmode="email" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( implode( ', ', KCS_Email::admin_recipients() ) ); ?>"></label>
						<?php elseif ( 'map' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><textarea name="<?php echo esc_attr( $key ); ?>" rows="3" class="kca-code" placeholder="&lt;iframe src=&quot;https://www.google.com/maps/embed?pb=…&quot;&gt;&lt;/iframe&gt;"><?php echo esc_textarea( $value ); ?></textarea></label>
						<?php elseif ( 'select' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><select name="<?php echo esc_attr( $key ); ?>"><?php foreach ( (array) $f[4] as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $value, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
						<?php else : ?>
							<label><?php echo esc_html( $f[0] ); ?><input type="<?php echo 'url' === $f[1] ? 'url' : ( 'number' === $f[1] ? 'number' : 'text' ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo 'number' === $f[1] ? ' min="0" max="90"' : ''; ?><?php echo 'url' === $f[1] && preg_match( '/video|youtube|audio|live/', $key ) ? ' placeholder="' . esc_attr( false !== strpos( $key, 'audio' ) ? 'https://…/message.mp3' : 'https://youtu.be/…' ) . '"' : ''; ?>></label>
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ( 'sections' === $group ) : ?></div><?php endif; ?>
					<button class="kcs-btn"><?php esc_html_e( 'Save', 'kingdom-church-suite' ); ?></button>
				</section>
			<?php endforeach; ?>
		</form>
		<p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View website', 'kingdom-church-suite' ); ?></a></p>
		<?php
	}

	/**
	 * Settings: everything from Church Suite → Settings in wp-admin (church administrators only).
	 */
	private static function view_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'Only church administrators can change settings.', 'kingdom-church-suite' ) . '</div>';
			return;
		}
		$tabs = (array) apply_filters( 'kcs_church_admin_settings_tabs', KCS_Settings::tabs() );
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all  = KCS_Settings::schema();
		$opts = get_option( 'kcs_settings', array() );
		$intro = array(
			'general'    => __( 'Church details, service times, pages and partnership options.', 'kingdom-church-suite' ),
			'payments'   => __( 'Turn on the payment methods you use and paste their keys. Every payment is checked with the provider before it counts as paid. Bank transfer account numbers are added under Confirm bank transfers.', 'kingdom-church-suite' ),
			'email'      => __( 'Who emails come from, who is notified, and reminders.', 'kingdom-church-suite' ),
			'templates'  => __( 'The emails givers and partners receive. Words in {braces} are filled in automatically.', 'kingdom-church-suite' ),
			'livestream' => __( 'Your YouTube channel and live stream options.', 'kingdom-church-suite' ),
			'seo'        => __( 'How your website appears on Google and when shared on WhatsApp or Facebook.', 'kingdom-church-suite' ),
			'api'        => __( 'For connecting other systems (CRM, mobile app, Zapier). Most churches can skip this.', 'kingdom-church-suite' ),
		);
		$collapse = in_array( $tab, array( 'payments', 'templates' ), true );
		?>
		<nav class="kca-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'kingdom-church-suite' ); ?>">
			<?php foreach ( $tabs as $k => $l ) : ?>
				<a href="<?php echo esc_url( self::url( 'settings', array( 'tab' => $k ) ) ); ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php if ( isset( $intro[ $tab ] ) ) : ?><p class="kcs-muted"><?php echo esc_html( $intro[ $tab ] ); ?></p><?php endif; ?>
		<?php if ( 'payments' === $tab ) : ?>
			<form method="post" class="kca-card kcs-form" id="gateway-order">
				<?php echo self::fields( 'gateway_order_save', 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="return" value="<?php echo esc_url( self::url( 'settings', array( 'tab' => 'payments' ) ) ); ?>">
				<h3><?php esc_html_e( 'Order of payment methods', 'kingdom-church-suite' ); ?></h3>
				<p class="kcs-muted"><?php esc_html_e( 'Givers and partners see the payment methods in this order; the first one that is switched on is selected for them. Use ↑ and ↓, then save.', 'kingdom-church-suite' ); ?></p>
				<ol class="kca-order" data-order>
					<?php foreach ( KCS_Payments::ordered() as $g ) : ?>
						<li><input type="hidden" name="gateway_order[]" value="<?php echo esc_attr( $g->id ); ?>"><span class="kca-order__name"><?php echo esc_html( $g->title ); ?></span><span class="kcs-badge kcs-badge--<?php echo $g->is_enabled() ? 'completed' : 'cancelled'; ?>"><?php echo $g->is_enabled() ? esc_html__( 'On', 'kingdom-church-suite' ) : esc_html__( 'Off', 'kingdom-church-suite' ); ?></span><span class="kca-order__btns"><button type="button" class="kcs-btn kcs-btn--ghost" data-order-up aria-label="<?php /* translators: %s: payment method */ echo esc_attr( sprintf( __( 'Move %s up', 'kingdom-church-suite' ), $g->title ) ); ?>">↑</button><button type="button" class="kcs-btn kcs-btn--ghost" data-order-down aria-label="<?php /* translators: %s: payment method */ echo esc_attr( sprintf( __( 'Move %s down', 'kingdom-church-suite' ), $g->title ) ); ?>">↓</button></span></li>
					<?php endforeach; ?>
				</ol>
				<button class="kcs-btn"><?php esc_html_e( 'Save order', 'kingdom-church-suite' ); ?></button>
			</form>
		<?php endif; ?>
		<form method="post" class="kca-settings">
			<?php echo self::fields( 'settings_save', 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php foreach ( (array) ( $all[ $tab ] ?? array() ) as $section => $fields ) : ?>
				<?php
				$enabled = false;
				foreach ( $fields as $key => $field ) {
					if ( '_enabled' === substr( $key, -8 ) && '1' === (string) ( $opts[ $key ] ?? '' ) ) {
						$enabled = true;
					}
				}
				?>
				<?php if ( $collapse ) : ?>
					<details class="kca-card kcs-form kca-setting-group">
						<summary><strong><?php echo esc_html( $section ); ?></strong><?php if ( 'payments' === $tab ) : ?> <span class="kcs-badge kcs-badge--<?php echo $enabled ? 'completed' : 'cancelled'; ?>"><?php echo $enabled ? esc_html__( 'On', 'kingdom-church-suite' ) : esc_html__( 'Off', 'kingdom-church-suite' ); ?></span><?php endif; ?></summary>
				<?php else : ?>
					<section class="kca-card kcs-form kca-setting-group"<?php echo isset( $fields['service_schedule'] ) ? ' id="service-times"' : ''; ?>>
						<h3><?php echo esc_html( $section ); ?></h3>
				<?php endif; ?>
				<?php
				foreach ( $fields as $key => $field ) {
					if ( 'service_schedule' === $key ) {
						self::schedule_editor();
						self::timezone_field();
						continue;
					}
					self::setting_field( $key, $field, $opts );
				}
				?>
				<button class="kcs-btn"><?php esc_html_e( 'Save settings', 'kingdom-church-suite' ); ?></button>
				<?php echo $collapse ? '</details>' : '</section>'; ?>
			<?php endforeach; ?>
		</form>
		<?php if ( 'email' === $tab ) : ?>
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'test_email', 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Send a test email', 'kingdom-church-suite' ); ?></h3>
				<div class="kca-inline-form"><input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" aria-label="<?php esc_attr_e( 'Email address', 'kingdom-church-suite' ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Send test', 'kingdom-church-suite' ); ?></button></div>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Service times as easy rows (day, time, name) instead of "Day|HH:MM|Name" lines.
	 */
	private static function schedule_editor() {
		$rows = KCS_Livestream::schedule();
		$days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$row  = function ( $r ) use ( $days ) {
			$h = '<div class="kca-sched__row"><select name="kcs_sched[day][]" aria-label="' . esc_attr__( 'Day', 'kingdom-church-suite' ) . '">';
			foreach ( $days as $day ) {
				$h .= '<option value="' . esc_attr( $day ) . '"' . selected( $r['day'], $day, false ) . '>' . esc_html( date_i18n( 'l', strtotime( 'next ' . $day ) ) ) . '</option>';
			}
			$h .= '</select><input type="time" name="kcs_sched[time][]" value="' . esc_attr( $r['time'] ? gmdate( 'H:i', strtotime( '2000-01-01 ' . $r['time'] ) ) : '' ) . '" aria-label="' . esc_attr__( 'Time', 'kingdom-church-suite' ) . '">'
				. '<input type="text" name="kcs_sched[name][]" value="' . esc_attr( $r['name'] ) . '" placeholder="' . esc_attr__( 'e.g. Sunday Worship Service', 'kingdom-church-suite' ) . '" aria-label="' . esc_attr__( 'Service name', 'kingdom-church-suite' ) . '">'
				. '<button type="button" class="kcs-btn kcs-btn--ghost kca-sched__del" data-sched-del aria-label="' . esc_attr__( 'Remove this service', 'kingdom-church-suite' ) . '">✕</button></div>';
			return $h;
		};
		echo '<span class="kca-anchor" id="set-service_schedule"></span><div class="kca-sched" data-sched><strong>' . esc_html__( 'Weekly services', 'kingdom-church-suite' ) . '</strong>';
		echo '<div class="kca-sched__rows" data-sched-rows>';
		foreach ( $rows ? $rows : array( array( 'day' => 'Sunday', 'time' => '', 'name' => '' ) ) as $r ) {
			echo $row( $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</div><template data-sched-template>' . $row( array( 'day' => 'Sunday', 'time' => '', 'name' => '' ) ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<button type="button" class="kcs-btn kcs-btn--ghost" data-sched-add>＋ ' . esc_html__( 'Add a service', 'kingdom-church-suite' ) . '</button>';
		echo '<input type="hidden" name="kcs_sched[sent]" value="1"></div>';
	}

	/**
	 * The posted rows as "Day|HH:MM|Name" lines (rows without a time are dropped).
	 *
	 * @param array $d Posted data.
	 * @return string
	 */
	private static function schedule_from_post( $d ) {
		$s     = (array) ( $d['kcs_sched'] ?? array() );
		$days  = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$lines = array();
		foreach ( (array) ( $s['time'] ?? array() ) as $i => $time ) {
			$day  = (string) ( $s['day'][ $i ] ?? '' );
			$time = preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)/', (string) $time, $m ) ? sprintf( '%02d:%02d', $m[1], $m[2] ) : '';
			$name = trim( str_replace( '|', '-', sanitize_text_field( (string) ( $s['name'][ $i ] ?? '' ) ) ) );
			if ( $time && in_array( $day, $days, true ) ) {
				$lines[] = $day . '|' . $time . '|' . ( '' !== $name ? $name : __( 'Service', 'kingdom-church-suite' ) );
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Save the time zone picked in Settings → General (a city, or a UTC offset like "UTC+1").
	 *
	 * @param string $tz Value from the time zone list.
	 */
	private static function save_timezone( $tz ) {
		if ( '' === $tz ) {
			return;
		}
		if ( preg_match( '/^UTC([+-]\d{1,2}(?:\.\d+)?)?$/', $tz, $m ) ) {
			update_option( 'gmt_offset', isset( $m[1] ) ? (float) $m[1] : 0 );
			update_option( 'timezone_string', '' );
		} elseif ( in_array( $tz, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true ) ) {
			update_option( 'timezone_string', $tz );
		}
		delete_transient( 'kcs_live_status' );
	}

	/**
	 * The site's time zone (service times, countdown and live badge are worked out in it).
	 */
	private static function timezone_field() {
		$tz = (string) get_option( 'timezone_string' );
		if ( '' === $tz ) {
			$off = (float) get_option( 'gmt_offset' );
			$tz  = 0.0 === $off ? 'UTC' : 'UTC' . ( $off >= 0 ? '+' : '' ) . $off;
		}
		echo '<span class="kca-anchor" id="set-timezone"></span><label>' . esc_html__( 'Time zone', 'kingdom-church-suite' ) . '<select name="site_timezone">' . wp_timezone_choice( $tz, get_user_locale() ) . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-built options.
		/* translators: %s: current local time */
		echo '<small class="kcs-muted">' . esc_html( sprintf( __( 'Service times above are in this time zone. Local time now: %s. Nigeria: choose Lagos.', 'kingdom-church-suite' ), wp_date( get_option( 'time_format' ) ) ) ) . '</small></label>';
	}

	/**
	 * One settings field.
	 *
	 * @param string $key   Key.
	 * @param array  $field Field definition from KCS_Settings::schema().
	 * @param array  $opts  Saved settings.
	 */
	private static function setting_field( $key, $field, $opts ) {
		echo '<span class="kca-anchor" id="set-' . esc_attr( $key ) . '"></span>';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : ( $field['default'] ?? '' );
		$name  = 'kcs[' . $key . ']';
		$desc  = ! empty( $field['desc'] ) ? '<small class="kcs-muted">' . wp_kses_post( $field['desc'] ) . '</small>' : '';
		switch ( $field['type'] ) {
			case 'info':
				if ( $desc ) {
					echo '<div class="kca-setting-info"><strong>' . esc_html( $field['label'] ) . '</strong> ' . $desc . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return;
			case 'checkbox':
				echo '<label class="kcs-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (string) $value, '1', false ) . '> ' . esc_html( $field['label'] ) . '</label>' . $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			case 'textarea':
			case 'code':
			case 'editor':
				echo '<label>' . esc_html( $field['label'] ) . '<textarea name="' . esc_attr( $name ) . '" rows="' . ( 'textarea' === $field['type'] ? 3 : 7 ) . '"' . ( 'textarea' === $field['type'] ? '' : ' class="kca-code"' ) . '>' . esc_textarea( (string) $value ) . '</textarea>' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			case 'select':
				echo '<label>' . esc_html( $field['label'] ) . '<select name="' . esc_attr( $name ) . '">';
				foreach ( (array) $field['options'] as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			case 'color':
				echo '<label class="kca-colour"><input type="color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ? $value : '#5b2a86' ) . '"> <span>' . esc_html( $field['label'] ) . '</span></label>' . $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			case 'password':
				if ( ! empty( $field['keep'] ) ) { // Never printed back: empty keeps the saved one.
					echo '<label>' . esc_html( $field['label'] ) . '<input type="password" autocomplete="new-password" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( '' !== (string) $value ? __( '•••••••• saved — leave empty to keep', 'kingdom-church-suite' ) : '' ) . '">' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					return;
				}
				echo '<label>' . esc_html( $field['label'] ) . '<input type="password" autocomplete="new-password" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-kcs-reveal>' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			default:
				$type  = in_array( $field['type'], array( 'email', 'url', 'number' ), true ) ? $field['type'] : 'text';
				$media = 'url' === $type && preg_match( '/logo|icon|image|photo/', $key ) && current_user_can( 'upload_files' ) ? ' data-kcs-media="url"' : '';
				echo '<label>' . esc_html( $field['label'] ) . '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( 'number' === $type ? ' step="any"' : '' ) . $media . '>' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Security: bots and card testing.
	 */
	private static function view_security() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = KCS_Security::settings();
		$blocked = KCS_Security::blocked();
		$log     = (array) get_option( KCS_Security::LOG, array() );
		$types   = array(
			'pay_block'   => __( 'Giving paused', 'kingdom-church-suite' ),
			'pay_limit'   => __( 'Too many payments', 'kingdom-church-suite' ),
			'email_limit' => __( 'Too many with one email', 'kingdom-church-suite' ),
			'bot'         => __( 'Bot stopped', 'kingdom-church-suite' ),
			'login'       => __( 'Login locked', 'kingdom-church-suite' ),
		);
		$today   = count(
			array_filter(
				$log,
				function ( $l ) {
					return strtotime( $l['time'] ) > current_time( 'timestamp' ) - DAY_IN_SECONDS; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				}
			)
		);
		?>
		<div class="kca-kpis">
			<div class="kca-kpi"><span><?php esc_html_e( 'Blocked in the last 24 hours', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $today; ?></strong></div>
			<div class="kca-kpi"><span><?php esc_html_e( 'Devices paused from giving', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) count( $blocked ); ?></strong></div>
			<div class="kca-kpi"><span><?php esc_html_e( '“I am human” check', 'kingdom-church-suite' ); ?></span><strong><?php echo esc_html( KCS_Security::turnstile_on( 'payments' ) ? ( 'all' === $s['turnstile'] ? __( 'All forms', 'kingdom-church-suite' ) : __( 'Giving forms', 'kingdom-church-suite' ) ) : __( 'Off', 'kingdom-church-suite' ) ); ?></strong></div>
		</div>
		<div class="kcs-alert kcs-alert--info">
			<strong><?php esc_html_e( 'Always on:', 'kingdom-church-suite' ); ?></strong>
			<?php esc_html_e( 'an invisible bot check on every form, giving limits per device and per email, pausing devices whose payments keep failing, a 30-minute lock after 5 wrong passwords, and hidden user names.', 'kingdom-church-suite' ); ?>
		</div>

		<?php if ( $blocked ) : ?>
			<section class="kca-card">
				<h3><?php esc_html_e( 'Devices paused from giving online', 'kingdom-church-suite' ); ?></h3>
				<ul class="kca-list">
					<?php foreach ( $blocked as $dev => $b ) : ?>
						<li><span><strong><?php echo esc_html( $b['reason'] ); ?></strong><small><?php echo esc_html( implode( ' · ', array_filter( array( ! empty( $b['ip'] ) ? 'IP ' . $b['ip'] : sprintf( /* translators: %s: device */ __( 'Device %s', 'kingdom-church-suite' ), substr( $dev, 0, 8 ) ), $b['where'] ?? '', $b['email'] ?? '', ! empty( $b['forever'] ) ? __( 'permanently', 'kingdom-church-suite' ) : sprintf( /* translators: %s: date */ __( 'until %s', 'kingdom-church-suite' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $b['until'] ) ) ) ) ) ); ?></small></span>
							<form method="post" class="kca-inline"><?php echo self::fields( 'security_unblock', 'security', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="device" value="<?php echo esc_attr( $dev ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Let it give again', 'kingdom-church-suite' ); ?></button></form></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php $kcs_emails = KCS_Security::blocked_emails(); ?>
		<?php if ( $kcs_emails ) : ?>
			<section class="kca-card">
				<h3><?php esc_html_e( 'Emails restricted from card giving', 'kingdom-church-suite' ); ?></h3>
				<ul class="kca-list">
					<?php foreach ( $kcs_emails as $em => $b ) : ?>
						<li><span><strong><?php echo esc_html( $em ); ?></strong><small><?php echo esc_html( $b['reason'] . ' · ' . ( ! empty( $b['forever'] ) ? __( 'permanently', 'kingdom-church-suite' ) : sprintf( /* translators: %s: date */ __( 'until %s', 'kingdom-church-suite' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $b['until'] ) ) ) ); ?></small></span>
							<form method="post" class="kca-inline"><?php echo self::fields( 'security_unblock', 'security', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="email" value="<?php echo esc_attr( $em ); ?>"><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Let it give again', 'kingdom-church-suite' ); ?></button></form></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<form method="post" class="kca-card kcs-form">
			<?php echo self::fields( 'security_save', 'security', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Giving protection (stops card testing)', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'Fraudsters use giving pages to try stolen cards with small amounts. These limits stop them without bothering real givers.', 'kingdom-church-suite' ); ?></p>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Payments one device may start in 15 minutes', 'kingdom-church-suite' ); ?><input type="number" min="1" max="20" name="sec_pay_15min" value="<?php echo (int) $s['pay_per_15']; ?>"></label>
				<label><?php esc_html_e( '… and in 24 hours', 'kingdom-church-suite' ); ?><input type="number" min="1" max="100" name="sec_pay_day" value="<?php echo (int) $s['pay_per_day']; ?>"></label>
				<label><?php esc_html_e( 'Unfinished payments one email may start in 24 hours', 'kingdom-church-suite' ); ?><input type="number" min="1" max="50" name="sec_email_day" value="<?php echo (int) $s['email_per_day']; ?>"></label>
				<label><?php esc_html_e( 'Pause a device after this many failed or abandoned payments', 'kingdom-church-suite' ); ?><input type="number" min="2" max="20" name="sec_fail_block" value="<?php echo (int) $s['fails_to_block']; ?>"></label>
				<label><?php esc_html_e( 'Pause for (hours)', 'kingdom-church-suite' ); ?><input type="number" min="1" max="168" name="sec_block_hours" value="<?php echo (int) $s['block_hours']; ?>"></label>
			</div>
			<label><?php esc_html_e( 'Block card payments from these countries (optional)', 'kingdom-church-suite' ); ?><input name="sec_block_countries" value="<?php echo esc_attr( (string) kcs_get_option( 'sec_block_countries', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Two-letter codes, e.g. RU, VN', 'kingdom-church-suite' ); ?>">
				<small class="kcs-muted"><?php esc_html_e( 'Only if card testers keep coming from a country where you have no givers. Bank transfer stays open. Each payment in Giving received shows its location to help you decide.', 'kingdom-church-suite' ); ?></small></label>
			<h3><?php esc_html_e( '“I am human” check (Cloudflare Turnstile)', 'kingdom-church-suite' ); ?></h3>
			<p class="kcs-muted"><?php esc_html_e( 'A free, privacy-friendly check that most people pass without clicking anything. Get keys at dash.cloudflare.com → Turnstile → Add widget, and add your website address(es) to the widget.', 'kingdom-church-suite' ); ?></p>
			<label><?php esc_html_e( 'Ask for it on', 'kingdom-church-suite' ); ?>
				<select name="turnstile_mode">
					<option value="payments" <?php selected( $s['turnstile'], 'payments' ); ?>><?php esc_html_e( 'Giving forms (recommended)', 'kingdom-church-suite' ); ?></option>
					<option value="all" <?php selected( $s['turnstile'], 'all' ); ?>><?php esc_html_e( 'All forms (giving, prayer, contact, stories, newsletter…)', 'kingdom-church-suite' ); ?></option>
					<option value="off" <?php selected( $s['turnstile'], 'off' ); ?>><?php esc_html_e( 'Off', 'kingdom-church-suite' ); ?></option>
				</select>
			</label>
			<div class="kcs-grid-2">
				<label><?php esc_html_e( 'Site key', 'kingdom-church-suite' ); ?><input name="turnstile_site" value="<?php echo esc_attr( (string) kcs_get_option( 'turnstile_site', '' ) ); ?>" autocomplete="off" placeholder="0x4AAAAAA…"></label>
				<label><?php esc_html_e( 'Secret key', 'kingdom-church-suite' ); ?><input type="password" name="turnstile_secret" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( kcs_get_option( 'turnstile_secret', '' ) ? __( 'Saved — leave empty to keep', 'kingdom-church-suite' ) : '' ); ?>"></label>
			</div>
			<small class="kcs-muted"><?php echo esc_html( $s['own_keys'] ? __( 'Using your own keys.', 'kingdom-church-suite' ) : ( $s['site_key'] ? __( 'Leave empty to use the platform’s keys, which are already set.', 'kingdom-church-suite' ) : __( 'No keys yet: the invisible bot check and the limits still protect your forms.', 'kingdom-church-suite' ) ) ); ?></small>
			<div><button class="kcs-btn"><?php esc_html_e( 'Save', 'kingdom-church-suite' ); ?></button></div>
		</form>

		<details class="kca-card">
			<summary><strong><?php esc_html_e( 'Also do this with your payment providers', 'kingdom-church-suite' ); ?></strong></summary>
			<ul>
				<li><?php esc_html_e( 'Flutterwave: in your dashboard settings, make sure card payments always ask for 3-D Secure (the bank’s one-time code) and turn on fraud alerts. If you cannot find these, ask Flutterwave support to enable them and to review the declined attempts.', 'kingdom-church-suite' ); ?></li>
				<li><?php esc_html_e( 'GlobalPay: ask support to turn on 3-D Secure and velocity limits for your merchant account.', 'kingdom-church-suite' ); ?></li>
				<li><?php esc_html_e( 'Settings → Giving: raise the minimum amount (card testers like very small amounts).', 'kingdom-church-suite' ); ?></li>
				<li><?php esc_html_e( 'Never send goods or refunds for a payment you do not recognise; refunds to a different card are a common scam.', 'kingdom-church-suite' ); ?></li>
			</ul>
		</details>

		<section class="kca-card">
			<h3><?php esc_html_e( 'What was blocked', 'kingdom-church-suite' ); ?></h3>
			<?php if ( $log ) : ?>
				<div class="kca-table"><table>
					<thead><tr><th><?php esc_html_e( 'When', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'What', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Device', 'kingdom-church-suite' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $log, 0, 50 ) as $l ) : ?>
						<tr><td data-label="<?php esc_attr_e( 'When', 'kingdom-church-suite' ); ?>"><?php echo esc_html( kcs_datetime( $l['time'] ) ); ?></td><td data-label="<?php esc_attr_e( 'What', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $types[ $l['type'] ] ?? $l['type'] ); ?></strong><small><?php echo esc_html( $l['detail'] ); ?></small></td><td data-label="<?php esc_attr_e( 'Device', 'kingdom-church-suite' ); ?>"><code><?php echo esc_html( substr( (string) $l['device'], 0, 8 ) ); ?></code></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<form method="post" class="kca-inline"><?php echo self::fields( 'security_clear', 'security', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Clear log', 'kingdom-church-suite' ); ?></button></form>
			<?php else : ?>
				<p class="kcs-muted"><?php esc_html_e( 'Nothing blocked yet.', 'kingdom-church-suite' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Which mode each card gateway's saved keys are in, with the last characters of the key, so the
	 * church can match them with the provider's dashboard (test payments only show in Test mode).
	 */
	private static function gateway_modes() {
		$items = array();
		$test  = false;
		foreach ( KCS_Payments::gateways() as $g ) {
			if ( ! $g->is_enabled() || KCS_Transfers::is_manual( $g->id ) ) {
				continue;
			}
			$mode  = $g->mode();
			$key   = '';
			foreach ( array( 'secret_key', 'api_key', 'client_secret', 'key_secret' ) as $k ) {
				$v = (string) $g->opt( $k );
				if ( '' !== $v ) {
					$key = substr( $v, -4 );
					break;
				}
			}
			$test   = $test || 'test' === $mode;
			$items[] = '<li><strong>' . esc_html( $g->title ) . '</strong> · ' . ( 'test' === $mode ? '<span class="kca-pill kca-pill--test">' . esc_html__( 'TEST keys', 'kingdom-church-suite' ) . '</span>' : ( 'live' === $mode ? '<span class="kca-pill is-on">' . esc_html__( 'LIVE keys', 'kingdom-church-suite' ) . '</span>' : esc_html__( 'mode set by your keys', 'kingdom-church-suite' ) ) ) . ( $key ? ' · ' . esc_html( sprintf( /* translators: %s: last characters */ __( 'key ending …%s', 'kingdom-church-suite' ), $key ) ) : '' ) . '</li>';
		}
		if ( ! $items ) {
			return;
		}
		?>
		<details class="kca-card kca-modes"<?php echo $test ? ' open' : ''; ?>>
			<summary><strong><?php esc_html_e( 'Can’t find a payment in your Flutterwave or GlobalPay dashboard?', 'kingdom-church-suite' ); ?></strong></summary>
			<?php if ( $test ) : ?><div class="kcs-alert kcs-alert--error"><?php esc_html_e( 'A gateway is using TEST keys. Real cards are declined in test mode, and these payments only appear in the provider’s dashboard when it is switched to Test mode. Put your LIVE keys in Settings → Payments to receive real gifts.', 'kingdom-church-suite' ); ?></div><?php endif; ?>
			<ul class="kca-modes__list"><?php echo wp_kses_post( implode( '', $items ) ); ?></ul>
			<p class="kcs-muted"><?php esc_html_e( 'Payments are created in the provider account that owns the saved key. In the provider’s dashboard, open Settings → API keys and check the key there ends with the same characters, and that the dashboard is in the same mode (Test or Live). Search the dashboard by our reference (GIV-… or PTN-…). Checkouts the giver closed without entering a card never appear there.', 'kingdom-church-suite' ); ?></p>
		</details>
		<?php
	}

	/**
	 * Today's Word: daily devotionals.
	 */
	private static function view_word() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$new     = isset( $_GET['new'] );
		// phpcs:enable
		$post  = $edit_id && KCS_Devotionals::TYPE === get_post_type( $edit_id ) ? get_post( $edit_id ) : null;
		$page  = kcs_get_option( 'page_todays_word' ) ? (int) kcs_get_option( 'page_todays_word' ) : 0;
		$today = current_time( 'Y-m-d' );
		if ( $post || $new ) {
			$date = $post ? mysql2date( 'Y-m-d', $post->post_date ) : $today;
			if ( ! $post ) {
				// Suggest the next free day.
				while ( KCS_Devotionals::for_date( $date ) ) {
					$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
				}
			}
			$img = $post ? (int) get_post_thumbnail_id( $post ) : 0;
			?>
			<p><a href="<?php echo esc_url( self::url( 'word' ) ); ?>">← <?php esc_html_e( 'All devotionals', 'kingdom-church-suite' ); ?></a></p>
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'word_save', 'word', $post ? $post->ID : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Day', 'kingdom-church-suite' ); ?><input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" required></label>
					<label><?php esc_html_e( 'Title', 'kingdom-church-suite' ); ?><input name="title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g. You are not forgotten', 'kingdom-church-suite' ); ?>"></label>
				</div>
				<div class="kcs-grid-2">
					<label><?php esc_html_e( 'Bible verse', 'kingdom-church-suite' ); ?><textarea name="verse" rows="3" placeholder="<?php esc_attr_e( 'The LORD is my shepherd; I shall not want.', 'kingdom-church-suite' ); ?>"><?php echo esc_textarea( $post ? (string) get_post_meta( $post->ID, '_kcs_verse', true ) : '' ); ?></textarea></label>
					<label><?php esc_html_e( 'Reference', 'kingdom-church-suite' ); ?><input name="ref" value="<?php echo esc_attr( $post ? (string) get_post_meta( $post->ID, '_kcs_verse_ref', true ) : '' ); ?>" placeholder="<?php esc_attr_e( 'Psalm 23:1', 'kingdom-church-suite' ); ?>"></label>
				</div>
				<label><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?><textarea name="message" rows="10" required><?php echo esc_textarea( $post ? $post->post_content : '' ); ?></textarea><small class="kcs-muted"><?php esc_html_e( 'Leave an empty line between paragraphs.', 'kingdom-church-suite' ); ?></small></label>
				<label><?php esc_html_e( 'Today’s prayer (optional)', 'kingdom-church-suite' ); ?><textarea name="prayer" rows="3"><?php echo esc_textarea( $post ? (string) get_post_meta( $post->ID, '_kcs_prayer', true ) : '' ); ?></textarea></label>
				<?php if ( current_user_can( 'upload_files' ) ) : ?>
					<div class="kca-image" data-kcs-media-field>
						<span class="kca-image__label"><?php esc_html_e( 'Picture (optional)', 'kingdom-church-suite' ); ?></span>
						<img class="kca-image__preview" src="<?php echo esc_url( $img ? (string) wp_get_attachment_image_url( $img, 'medium' ) : '' ); ?>" alt=""<?php echo $img ? '' : ' hidden'; ?>>
						<input type="hidden" name="image_id" value="<?php echo (int) $img; ?>" data-kcs-media="id">
					</div>
				<?php endif; ?>
				<div class="kca-actions"><button class="kcs-btn"><?php esc_html_e( 'Save devotional', 'kingdom-church-suite' ); ?></button></div>
			</form>
			<?php if ( $post ) : ?>
				<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this devotional?', 'kingdom-church-suite' ) ); ?>')"><?php echo self::fields( 'word_delete', 'word', $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Delete devotional', 'kingdom-church-suite' ); ?></button></form>
			<?php endif; ?>
			<?php
			return;
		}
		$pg = kcs_current_page();
		$q  = new WP_Query(
			array(
				'post_type'      => KCS_Devotionals::TYPE,
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => 15,
				'paged'          => $pg,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$has_today = (bool) KCS_Devotionals::for_date( $today );
		?>
		<div class="kca-actions">
			<a class="kcs-btn" href="<?php echo esc_url( self::url( 'word', array( 'new' => 1 ) ) ); ?>">＋ <?php esc_html_e( 'Write a devotional', 'kingdom-church-suite' ); ?></a>
			<?php if ( $page ) : ?><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Today’s Word page', 'kingdom-church-suite' ); ?> ↗</a><?php endif; ?>
		</div>
		<?php if ( ! $has_today ) : ?>
			<div class="kcs-alert kcs-alert--info"><?php esc_html_e( 'There is no devotional for today, so the verse of the day is showing. Write one ahead for each day of the week.', 'kingdom-church-suite' ); ?></div>
		<?php endif; ?>
		<div class="kca-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Day', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Devotional', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $q->posts as $p ) : ?>
					<?php $day = mysql2date( 'Y-m-d', $p->post_date ); ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Day', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( mysql2date( 'D j M Y', $p->post_date ) ); ?></strong>
							<?php if ( $day === $today ) : ?><span class="kca-pill is-on"><?php esc_html_e( 'Today', 'kingdom-church-suite' ); ?></span><?php elseif ( $day > $today ) : ?><span class="kca-pill"><?php esc_html_e( 'Scheduled', 'kingdom-church-suite' ); ?></span><?php endif; ?></td>
						<td data-label="<?php esc_attr_e( 'Devotional', 'kingdom-church-suite' ); ?>"><a href="<?php echo esc_url( self::url( 'word', array( 'edit' => $p->ID ) ) ); ?>"><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong></a><small><?php echo esc_html( (string) get_post_meta( $p->ID, '_kcs_verse_ref', true ) ); ?></small></td>
						<td class="kca-actions"><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'word', array( 'edit' => $p->ID ) ) ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $q->posts ) : ?><tr><td colspan="3" class="kcs-muted"><?php esc_html_e( 'No devotionals yet. Write your first one — it takes two minutes.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php self::pager( $q->found_posts, 15, $pg, array( 'view' => 'word' ) ); ?>
		<form method="post" class="kca-card kcs-form">
			<?php echo self::fields( 'word_settings', 'word', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h3><?php esc_html_e( 'Sharing Today’s Word', 'kingdom-church-suite' ); ?></h3>
			<?php if ( ! $page ) : ?>
				<label class="kcs-check"><input type="checkbox" name="create_page" value="1" checked> <?php esc_html_e( 'Create a “Today’s Word” page on my website', 'kingdom-church-suite' ); ?></label>
			<?php endif; ?>
			<label class="kcs-check"><input type="checkbox" name="todays_word_email" value="1" <?php checked( '1', kcs_get_option( 'todays_word_email', '0' ) ); ?>> <?php esc_html_e( 'Email the day’s devotional to newsletter subscribers every morning', 'kingdom-church-suite' ); ?></label>
			<label><?php esc_html_e( 'Send from (hour of the day, 0–23)', 'kingdom-church-suite' ); ?><input type="number" min="0" max="23" name="todays_word_hour" value="<?php echo esc_attr( (string) kcs_get_option( 'todays_word_hour', 6 ) ); ?>" style="max-width:120px"></label>
			<small class="kcs-muted"><?php esc_html_e( 'Only days that have a devotional are emailed. You can also add [kcs_todays_word] to any page.', 'kingdom-church-suite' ); ?></small>
			<div><button class="kcs-btn"><?php esc_html_e( 'Save', 'kingdom-church-suite' ); ?></button></div>
		</form>
		<?php
	}

	/**
	 * Newsletter: write to subscribers and partners, and manage subscribers.
	 */
	private static function view_newsletter() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$edit   = isset( $_GET['edit'] ) ? KCS_Newsletter::get_campaign( absint( $_GET['edit'] ) ) : null;
		$search = isset( $_GET['find'] ) ? sanitize_text_field( wp_unslash( $_GET['find'] ) ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		if ( $edit && in_array( $edit->status, array( 'sending', 'sent' ), true ) ) {
			$edit = null;
		}
		$t      = kcs_table( 'subscribers' );
		$counts = array();
		foreach ( $wpdb->get_results( "SELECT status, COUNT(*) c FROM $t GROUP BY status" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts[ $r->status ] = (int) $r->c;
		}
		$where = $search ? $wpdb->prepare( 'WHERE email LIKE %s OR name LIKE %s', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' ) : '';
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$per   = kcs_per_page();
		$subs  = $wpdb->get_results( "SELECT * FROM $t $where ORDER BY id DESC LIMIT $per OFFSET " . ( ( $page - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$cpg   = kcs_current_page( 'cpg' );
		$ctot  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'campaigns' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sent  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 10 OFFSET %d', ( $cpg - 1 ) * 10 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="kca-kpis">
			<div class="kca-kpi"><span><?php esc_html_e( 'Subscribers', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) ( $counts['subscribed'] ?? 0 ); ?></strong><small><?php echo (int) ( $counts['pending'] ?? 0 ); ?> <?php esc_html_e( 'waiting to confirm', 'kingdom-church-suite' ); ?></small></div>
			<div class="kca-kpi"><span><?php esc_html_e( 'Unsubscribed', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) ( $counts['unsubscribed'] ?? 0 ); ?></strong></div>
		</div>
		<?php
		KCS_Email_Designs::picker(
			function ( $do, $view, $id ) {
				return self::fields( $do, $view, $id );
			}
		);
		?>
		<div class="kca-newsletter">
			<form method="post" class="kca-card kcs-form" data-kcs-campaign>
				<?php echo self::fields( 'campaign_save', 'newsletter', $edit ? $edit->id : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php echo $edit ? esc_html__( 'Edit draft', 'kingdom-church-suite' ) : esc_html__( 'Write an email', 'kingdom-church-suite' ); ?></h3>
				<label><?php esc_html_e( 'Send to', 'kingdom-church-suite' ); ?><select name="audience"><?php foreach ( KCS_Newsletter::audiences() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $edit ? $edit->audience : 'subscribers', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Subject', 'kingdom-church-suite' ); ?><input name="subject" required maxlength="200" value="<?php echo esc_attr( $edit ? $edit->subject : '' ); ?>"></label>
				<label><?php esc_html_e( 'Preview line (optional)', 'kingdom-church-suite' ); ?><input name="preheader" maxlength="200" value="<?php echo esc_attr( $edit ? $edit->preheader : '' ); ?>" placeholder="<?php esc_attr_e( 'Shown after the subject in the inbox', 'kingdom-church-suite' ); ?>"></label>
				<div class="kca-editor">
					<span class="kca-image__label"><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?></span>
					<?php
					$content = $edit ? (string) $edit->content : '';
					// Older plain messages: keep their paragraphs now the editor stores exact HTML.
					if ( $content && ! preg_match( '/<(p|div|table|h[1-6]|ul|ol|br)\b/i', $content ) ) {
						$content = wpautop( $content );
					}
					wp_editor(
						$content,
						'kcs_campaign_content',
						array(
							'textarea_name' => 'content',
							'textarea_rows' => 20,
							'wpautop'       => false,
							'media_buttons' => current_user_can( 'upload_files' ),
							'teeny'         => false,
							'quicktags'     => true,
							'tinymce'       => array(
								'toolbar1'           => 'formatselect,bold,italic,underline,forecolor,backcolor,alignleft,aligncenter,alignright,bullist,numlist,blockquote,link,unlink,hr,removeformat,undo,redo',
								'toolbar2'           => '',
								'block_formats'      => 'Paragraph=p;Heading=h2;Small heading=h3',
								'relative_urls'      => false,
								'remove_script_host' => false,
								'convert_urls'       => false,
								'content_style'      => 'body{max-width:600px;margin:12px auto;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#3d3747} img{max-width:100%;height:auto}',
							),
						)
					);
					?>
					<small class="kcs-muted"><?php esc_html_e( 'Use the toolbar like a word processor, “Add Media” for pictures, and the “Text” tab to paste your own HTML. Write {first_name} to greet each person by name.', 'kingdom-church-suite' ); ?></small>
					<?php if ( current_user_can( 'upload_files' ) ) : ?>
						<div class="kca-pics" data-kcs-pics hidden></div>
					<?php endif; ?>
				</div>
				<div class="kca-actions">
					<button class="kcs-btn" name="do" value="send" onclick="return confirm('<?php echo esc_js( __( 'Send this email now?', 'kingdom-church-suite' ) ); ?>')"><?php esc_html_e( 'Send now', 'kingdom-church-suite' ); ?></button>
					<button class="kcs-btn kcs-btn--ghost" name="do" value="test"><?php esc_html_e( 'Send me a test', 'kingdom-church-suite' ); ?></button>
					<button class="kcs-btn kcs-btn--ghost" name="do" value="save"><?php esc_html_e( 'Save draft', 'kingdom-church-suite' ); ?></button>
				</div>
				<div class="kca-inline-form kca-save-design">
					<input name="design_name" placeholder="<?php esc_attr_e( 'Name this design, e.g. Monthly newsletter', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Design name', 'kingdom-church-suite' ); ?>">
					<button class="kcs-btn kcs-btn--ghost" name="do" value="design"><?php esc_html_e( 'Save as my design', 'kingdom-church-suite' ); ?></button>
				</div>
			</form>
			<section class="kca-card">
				<h3><?php esc_html_e( 'Recent emails', 'kingdom-church-suite' ); ?></h3>
				<ul class="kca-list">
					<?php foreach ( $sent as $c ) : ?>
						<li>
							<?php if ( in_array( $c->status, array( 'draft', 'scheduled' ), true ) ) : ?>
								<a href="<?php echo esc_url( self::url( 'newsletter', array( 'edit' => $c->id ) ) ); ?>"><strong><?php echo esc_html( $c->subject ); ?></strong><small><?php echo esc_html( KCS_Newsletter::audiences()[ $c->audience ] ?? $c->audience ); ?></small></a>
							<?php else : ?>
								<span><strong><?php echo esc_html( $c->subject ); ?></strong><small><?php /* translators: 1: sent, 2: total */ echo esc_html( sprintf( __( '%1$d of %2$d sent', 'kingdom-church-suite' ), (int) $c->sent_count, (int) $c->total ) . ' · ' . mysql2date( get_option( 'date_format' ), $c->sent_at ? $c->sent_at : $c->created_at ) ); ?></small></span>
							<?php endif; ?>
							<?php echo kcs_status_badge( $c->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</li>
					<?php endforeach; ?>
					<?php if ( ! $sent ) : ?><li class="kcs-muted"><?php esc_html_e( 'Nothing sent yet.', 'kingdom-church-suite' ); ?></li><?php endif; ?>
				</ul>
				<?php self::pager( $ctot, 10, $cpg, array( 'view' => 'newsletter', 'find' => $search ), 'cpg' ); ?>
			</section>
		</div>
		<section class="kca-card kca-gap">
			<h3><?php esc_html_e( 'Subscribers', 'kingdom-church-suite' ); ?></h3>
			<form class="kca-filters" method="get">
				<input type="hidden" name="view" value="newsletter">
				<input type="search" name="find" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search subscribers', 'kingdom-church-suite' ); ?>">
				<button class="kcs-btn"><?php esc_html_e( 'Search', 'kingdom-church-suite' ); ?></button>
				<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kcs_export&type=subscribers' ), 'kcs_export' ) ); ?>"><?php esc_html_e( 'Download CSV', 'kingdom-church-suite' ); ?></a>
			</form>
			<div class="kca-table">
				<table>
					<thead><tr><th><?php esc_html_e( 'Subscriber', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $subs as $sub ) : ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Subscriber', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $sub->name ? $sub->name : $sub->email ); ?></strong><small><?php echo esc_html( $sub->email . ' · ' . mysql2date( get_option( 'date_format' ), $sub->created_at ) ); ?></small></td>
							<td data-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><?php echo kcs_status_badge( $sub->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="kca-actions">
								<form method="post" class="kca-inline"><?php echo self::fields( 'subscriber_status', 'newsletter', $sub->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php if ( 'unsubscribed' !== $sub->status ) : ?><button class="kcs-btn kcs-btn--ghost" name="status" value="unsubscribed"><?php esc_html_e( 'Unsubscribe', 'kingdom-church-suite' ); ?></button><?php else : ?><button class="kcs-btn kcs-btn--ghost" name="status" value="subscribed"><?php esc_html_e( 'Resubscribe', 'kingdom-church-suite' ); ?></button><?php endif; ?>
									<button class="kcs-btn kcs-btn--ghost" name="status" value="delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this subscriber?', 'kingdom-church-suite' ) ); ?>')"><?php esc_html_e( 'Delete', 'kingdom-church-suite' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $subs ) : ?><tr><td colspan="3" class="kcs-muted"><?php esc_html_e( 'No subscribers yet. People join from the newsletter box on your website.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php self::pager( $total, $per, $page, array( 'view' => 'newsletter', 'find' => $search ) ); ?>
		</section>
		<?php
	}

	/**
	 * Live font preview for the Church fonts card (fonts come from the theme's library).
	 */
	private static function font_preview() {
		if ( ! function_exists( 'kingdom_font_library' ) ) {
			return;
		}
		$data = array(
			'lib'   => kingdom_fonts(),
			'pairs' => array_map(
				function ( $p ) {
					return array( $p[1], $p[2] );
				},
				kingdom_font_pairs()
			),
		);
		list( $h, $b ) = kingdom_active_fonts();
		?>
		<div class="kca-font-preview" data-kca-font-preview data-fonts="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
			<strong data-font-h style="font-family:<?php echo esc_attr( kingdom_font_stack( $h ) ); ?>"><?php echo esc_html( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ); ?></strong>
			<p data-font-b style="font-family:<?php echo esc_attr( kingdom_font_stack( $b ) ); ?>"><?php esc_html_e( 'Join us this Sunday for worship, the Word and fellowship. Everyone is welcome — come as you are.', 'kingdom-church-suite' ); ?></p>
			<small data-font-names><?php echo esc_html( $h . ' + ' . $b ); ?></small>
		</div>
		<script>
		(function () {
			var box = document.querySelector('[data-kca-font-preview]');
			if (!box) { return; }
			var data = JSON.parse(box.getAttribute('data-fonts'));
			var form = box.closest('form');
			function val(n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value : ''; }
			function stack(name) { return name === 'System' || !name ? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' : '"' + name + '", sans-serif'; }
			function load(name) {
				if (!data.lib[name] || val('kingdom_font_source') === 'system' || document.getElementById('kca-gf-' + name.replace(/\W/g, ''))) { return; }
				var l = document.createElement('link');
				l.rel = 'stylesheet'; l.id = 'kca-gf-' + name.replace(/\W/g, '');
				l.href = 'https://fonts.googleapis.com/css2?family=' + data.lib[name] + '&display=swap';
				document.head.appendChild(l);
			}
			function update() {
				var pair = data.pairs[val('kingdom_font_pair')];
				var h = pair && pair[0] ? pair[0] : val('kingdom_font_heading');
				var b = pair && pair[1] ? pair[1] : val('kingdom_font_body');
				if (val('kingdom_font_source') === 'system') { h = 'System'; b = 'System'; }
				load(h); load(b);
				box.querySelector('[data-font-h]').style.fontFamily = stack(h);
				box.querySelector('[data-font-b]').style.fontFamily = stack(b);
				box.querySelector('[data-font-names]').textContent = (h === 'System' ? 'Device font' : h) + ' + ' + (b === 'System' ? 'Device font' : b);
				['kingdom_font_heading', 'kingdom_font_body'].forEach(function (n) {
					var el = form.querySelector('[name="' + n + '"]');
					if (el) { el.closest('label').style.opacity = pair && pair[0] ? '.5' : ''; }
				});
			}
			form.addEventListener('change', function (e) { if (/^kingdom_font_/.test(e.target.name || '')) { update(); } });
			update();
		}());
		</script>
		<?php
	}

	/**
	 * Church app: settings, notifications to every phone, history.
	 */
	private static function view_app() {
		$on      = '1' === (string) kcs_get_option( 'pwa_enabled', '1' );
		$devices = KCS_Push::count();
		$log     = (array) get_option( 'kcs_push_log', array() );
		$queue   = (array) get_option( 'kcs_push_queue', array() );
		$v       = class_exists( 'KCS_PWA' ) ? KCS_PWA::version() : '';
		?>
		<div class="kca-kpis" style="margin-bottom:1rem">
			<div class="kca-kpi"><span><?php esc_html_e( 'App', 'kingdom-church-suite' ); ?></span><strong><?php echo $on ? esc_html__( 'On', 'kingdom-church-suite' ) : esc_html__( 'Off', 'kingdom-church-suite' ); ?></strong></div>
			<div class="kca-kpi"><span><?php esc_html_e( 'Phones with notifications', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $devices; ?></strong></div>
			<div class="kca-kpi"><span><?php esc_html_e( 'Sending now', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) array_sum( array_map( function ( $j ) { return count( $j['ids'] ); }, $queue ) ); ?></strong></div>
		</div>
		<div class="kca-cols">
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'push_send', 'app' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'Send a notification to every phone', 'kingdom-church-suite' ); ?></h3>
				<p class="kcs-muted"><?php esc_html_e( 'Appears on the lock screen of everyone who installed the app and turned on notifications — Android and iPhone.', 'kingdom-church-suite' ); ?></p>
				<label><?php esc_html_e( 'Title', 'kingdom-church-suite' ); ?><input name="push_title" maxlength="60" required placeholder="<?php esc_attr_e( 'Sunday service at 9am', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?><textarea name="push_body" rows="3" maxlength="180" required placeholder="<?php esc_attr_e( 'Come early and bring a friend!', 'kingdom-church-suite' ); ?>"></textarea></label>
				<label><?php esc_html_e( 'Open this page when tapped (optional)', 'kingdom-church-suite' ); ?><input type="url" name="push_link" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>"></label>
				<button class="kcs-btn" <?php disabled( ! $devices ); ?>><?php esc_html_e( 'Send now', 'kingdom-church-suite' ); ?></button>
				<?php if ( ! $devices ) : ?><small class="kcs-muted"><?php esc_html_e( 'No phones yet. Members turn on notifications from the menu (“Turn on notifications”) or when the app asks them.', 'kingdom-church-suite' ); ?></small><?php endif; ?>
				<?php if ( $log ) : ?>
					<h4><?php esc_html_e( 'Recent notifications', 'kingdom-church-suite' ); ?></h4>
					<ul class="kca-list">
						<?php foreach ( $log as $l ) : ?>
							<li><span><strong><?php echo esc_html( $l['title'] ); ?></strong><small><?php echo esc_html( $l['body'] ); ?></small></span><small><?php /* translators: 1: delivered, 2: failed */ echo esc_html( sprintf( __( '%1$d delivered · %2$d failed', 'kingdom-church-suite' ), $l['sent'], $l['failed'] ) . ' · ' . mysql2date( get_option( 'date_format' ), $l['date'] ) ); ?></small></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</form>
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'app_save', 'app' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php esc_html_e( 'App settings', 'kingdom-church-suite' ); ?></h3>
				<?php if ( $v ) : ?>
					<div class="kca-app-preview" style="--app-bg:<?php echo esc_attr( KCS_PWA::colours()[0] ); ?>">
						<img src="<?php echo esc_url( KCS_PWA::url( 'icon', array( 'size' => 192, 'v' => $v ) ) ); ?>" width="72" height="72" alt="">
						<span><?php echo esc_html( KCS_PWA::name( true ) ); ?></span>
					</div>
					<p class="kcs-muted"><?php esc_html_e( 'The app uses your church logo and church colours automatically (change colours under Website → Church colours).', 'kingdom-church-suite' ); ?></p>
				<?php endif; ?>
				<label class="kcs-check"><input type="checkbox" name="pwa_enabled" value="1" <?php checked( $on ); ?>> <?php esc_html_e( 'Turn the church app on', 'kingdom-church-suite' ); ?></label>
				<label class="kcs-check"><input type="checkbox" name="pwa_banner" value="1" <?php checked( '1', (string) kcs_get_option( 'pwa_banner', '1' ) ); ?>> <?php esc_html_e( 'Invite visitors to install the app (after a couple of visits)', 'kingdom-church-suite' ); ?></label>
				<?php $kcs_app_page = (int) kcs_get_option( 'page_app' ) && 'publish' === get_post_status( (int) kcs_get_option( 'page_app' ) ) ? (int) kcs_get_option( 'page_app' ) : 0; ?>
				<label class="kcs-check"><input type="checkbox" name="pwa_start_app" value="1" <?php checked( '1', (string) kcs_get_option( 'pwa_start_app', '0' ) ); ?>> <?php esc_html_e( 'Open the installed app on the App home screen (greeting, live countdown, Today’s Word, prayer and sermons)', 'kingdom-church-suite' ); ?></label>
				<small class="kcs-muted"><?php echo $kcs_app_page ? wp_kses_post( sprintf( /* translators: %s: link */ __( 'App home page: %s', 'kingdom-church-suite' ), '<a href="' . esc_url( get_permalink( $kcs_app_page ) ) . '" target="_blank" rel="noopener">' . esc_html( get_permalink( $kcs_app_page ) ) . '</a>' ) ) : esc_html__( 'Ticking this creates an “App” page for you.', 'kingdom-church-suite' ); ?></small>
				<label class="kcs-check"><input type="checkbox" name="pwa_live_push" value="1" <?php checked( '1', (string) kcs_get_option( 'pwa_live_push', '1' ) ); ?>> <?php esc_html_e( 'Notify every phone automatically when the YouTube live stream starts', 'kingdom-church-suite' ); ?></label>
				<label><?php esc_html_e( 'App name', 'kingdom-church-suite' ); ?><input name="pwa_name" value="<?php echo esc_attr( kcs_get_option( 'pwa_name', '' ) ); ?>" placeholder="<?php echo esc_attr( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ); ?>"></label>
				<label><?php esc_html_e( 'Name under the icon (max 14 letters)', 'kingdom-church-suite' ); ?><input name="pwa_short_name" maxlength="14" value="<?php echo esc_attr( kcs_get_option( 'pwa_short_name', '' ) ); ?>"></label>
				<label><?php esc_html_e( 'App & browser tab icon (optional — square image, at least 512×512; empty uses your logo)', 'kingdom-church-suite' ); ?><input type="url" name="pwa_icon"<?php echo current_user_can( 'upload_files' ) ? ' data-kcs-media="url"' : ''; ?> value="<?php echo esc_attr( kcs_get_option( 'pwa_icon', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to use your church logo', 'kingdom-church-suite' ); ?>"></label>
				<button class="kcs-btn"><?php esc_html_e( 'Save app settings', 'kingdom-church-suite' ); ?></button>
				<details class="kca-help"><summary><?php esc_html_e( 'How members install the app', 'kingdom-church-suite' ); ?></summary>
					<p><strong>Android:</strong> <?php esc_html_e( 'open the website in Chrome and tap “Install app” (in the menu or the invitation at the bottom).', 'kingdom-church-suite' ); ?></p>
					<p><strong>iPhone:</strong> <?php esc_html_e( 'open the website in Safari, tap Share, then “Add to Home Screen”. Open the app from the Home Screen to turn on notifications (iOS 16.4 or newer).', 'kingdom-church-suite' ); ?></p>
				</details>
			</form>
		</div>
		<?php
	}

	/**
	 * Simple pager.
	 *
	 * @param int   $total Total.
	 * @param int   $per   Per page.
	 * @param int   $page  Current.
	 * @param array $args  Query args.
	 */
	private static function pager( $total, $per, $page, $args, $param = 'pg' ) {
		echo kcs_pager( $total, $per, $page, kcs_page_url( 'church_admin' ), $args, array( 'param' => $param, 'per_param' => 'pg' === $param ? 'per' : false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}}
