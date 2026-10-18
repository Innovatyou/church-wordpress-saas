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
		return apply_filters( 'kcs_church_admin_views', array(
			'overview'    => array( __( 'Overview', 'kingdom-church-suite' ), '◎' ),
			'partners'    => array( __( 'Partners', 'kingdom-church-suite' ), '♥' ),
			'plans'       => array( __( 'Plans & currency', 'kingdom-church-suite' ), '◈' ),
			'payments'    => array( __( 'Payments', 'kingdom-church-suite' ), '₦' ),
			'transfers'   => array( __( 'Bank transfers', 'kingdom-church-suite' ), '⇄' ),
			'prayers'     => array( __( 'Prayer requests', 'kingdom-church-suite' ), '✦' ),
			'visits'      => array( __( 'Visits', 'kingdom-church-suite' ), '⌂' ),
			'testimonies' => array( __( 'Testimonies', 'kingdom-church-suite' ), '❝' ),
			'notify'      => array( __( 'Notify partners', 'kingdom-church-suite' ), '✉' ),
			'pastors'     => array( __( 'Pastors & WhatsApp', 'kingdom-church-suite' ), '☎' ),
			'website'     => array( __( 'Website', 'kingdom-church-suite' ), '▣' ),
			'content'     => array( __( 'Pictures & text', 'kingdom-church-suite' ), '▧' ),
			'newsletter'  => array( __( 'Newsletter', 'kingdom-church-suite' ), '✎' ),
			'app'         => array( __( 'Church app', 'kingdom-church-suite' ), '▤' ),
		) + ( current_user_can( 'manage_options' ) ? array( 'settings' => array( __( 'Settings', 'kingdom-church-suite' ), '⚙' ) ) : array() ) );
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

			case 'payment_verify':
				$p = KCS_Payments::get( $id );
				if ( $p ) {
					/* translators: %s: status */
					$msg = sprintf( __( 'Checked with the gateway: %s.', 'kingdom-church-suite' ), KCS_Payments::verify_and_update( $p ) );
				}
				break;

			case 'prayer_status':
				$status = sanitize_key( $d['status'] ?? '' );
				if ( array_key_exists( $status, KCS_Prayer::statuses() ) ) {
					$wpdb->update( kcs_table( 'prayer_requests' ), array( 'status' => $status ), array( 'id' => $id ) );
					$msg = __( 'Prayer request updated.', 'kingdom-church-suite' );
				}
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
						wp_trash_post( $id );
						$msg = __( 'Testimony removed.', 'kingdom-church-suite' );
					}
				}
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
				$settings['tithe_gateway']         = KCS_Payments::gateway( $gw ) ? $gw : 'none';
				update_option( 'kcs_settings', $settings );
				$msg  = __( 'Currency settings saved.', 'kingdom-church-suite' );
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
						default:
							$value = sanitize_text_field( $raw );
					}
					set_theme_mod( $key, $value );
				}
				$msg  = __( 'Website updated.', 'kingdom-church-suite' );
				$view = in_array( $view, array( 'website', 'content' ), true ) ? $view : 'website';
				break;

			case 'settings_save':
				if ( ! current_user_can( 'manage_options' ) ) {
					$msg  = __( 'Only church administrators can change settings.', 'kingdom-church-suite' );
					$type = 'error';
					break;
				}
				$tab = sanitize_key( $d['tab'] ?? 'general' );
				if ( isset( KCS_Settings::tabs()[ $tab ] ) ) {
					KCS_Settings::save_tab( $tab, isset( $_POST['kcs'] ) && is_array( $_POST['kcs'] ) ? $_POST['kcs'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field in save_tab.
					if ( 'general' === $tab ) {
						flush_rewrite_rules();
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
				$msg  = $sent ? __( 'Test email sent. Check the inbox (and the spam folder).', 'kingdom-church-suite' ) : __( 'The test email could not be sent. Check the email settings.', 'kingdom-church-suite' );
				$type = $sent ? 'success' : 'error';
				$view = 'settings';
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
				$d['content'] = wpautop( make_clickable( esc_html( (string) $d['content'] ) ) );
				$id           = KCS_Newsletter::save_campaign( $d, $id );
				$doing        = sanitize_key( $d['do'] ?? 'save' );
				$view         = 'newsletter';
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
			return kcs_get_template( 'partner-login', array( 'notice' => __( 'Church staff: please log in to open the admin dashboard.', 'kingdom-church-suite' ), 'redirect_to' => self::url() ) );
		}
		if ( ! self::can() ) {
			return '<div class="kcs-alert kcs-alert--error">' . esc_html__( 'This dashboard is for church staff only.', 'kingdom-church-suite' ) . '</div>';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$views = self::views();
		$view  = isset( $_GET['view'] ) && isset( $views[ sanitize_key( $_GET['view'] ) ] ) ? sanitize_key( $_GET['view'] ) : 'overview';
		// phpcs:enable
		$flash = get_transient( 'kcs_admin_flash_' . get_current_user_id() );
		delete_transient( 'kcs_admin_flash_' . get_current_user_id() );
		$counts = self::badges();

		ob_start();
		?>
		<div class="kca" data-kca>
			<aside class="kca__side">
				<div class="kca__brand"><span class="kca__logo" aria-hidden="true"></span><span><strong><?php echo esc_html( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ); ?></strong><small><?php esc_html_e( 'Church admin', 'kingdom-church-suite' ); ?></small></span></div>
				<nav class="kca__nav" aria-label="<?php esc_attr_e( 'Dashboard sections', 'kingdom-church-suite' ); ?>">
					<?php foreach ( $views as $slug => $v ) : ?>
						<a href="<?php echo esc_url( self::url( $slug ) ); ?>"<?php echo $slug === $view ? ' aria-current="page"' : ''; ?>><span class="kca__ic" aria-hidden="true"><?php echo esc_html( $v[1] ); ?></span><?php echo esc_html( $v[0] ); ?><?php if ( ! empty( $counts[ $slug ] ) ) : ?><em><?php echo (int) $counts[ $slug ]; ?></em><?php endif; ?></a>
					<?php endforeach; ?>
				</nav>
				<a class="kca__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'kingdom-church-suite' ); ?></a>
			</aside>
			<main class="kca__main">
				<header class="kca__head">
					<h2><?php echo esc_html( $views[ $view ][0] ); ?></h2>
					<span class="kcs-muted"><?php echo esc_html( wp_get_current_user()->display_name ); ?> · <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></span>
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
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'prayers'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'prayer_requests' ) . " WHERE status = 'new'" ),
			'visits'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'visits' ) . " WHERE status = 'new'" ),
			'payments'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'payments' ) . " WHERE status = 'review' AND gateway <> 'bank_transfer'" ),
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
		$waiting = KCS_Transfers::count_waiting();
		?>
		<?php if ( $waiting ) : ?>
			<div class="kcs-alert kcs-alert--info"><a href="<?php echo esc_url( self::url( 'transfers' ) ); ?>"><?php /* translators: %d: number of transfers */ echo esc_html( sprintf( _n( '%d bank transfer is waiting for you to confirm.', '%d bank transfers are waiting for you to confirm.', $waiting, 'kingdom-church-suite' ), $waiting ) ); ?> <?php esc_html_e( 'Review now →', 'kingdom-church-suite' ); ?></a></div>
		<?php endif; ?>
		<div class="kca-kpis">
			<a class="kca-kpi" href="<?php echo esc_url( self::url( 'partners', array( 'status' => 'active' ) ) ); ?>"><span><?php esc_html_e( 'Active partners', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_by_status']['active']; ?></strong><small><?php echo (int) $stats['partners_total']; ?> <?php esc_html_e( 'total', 'kingdom-church-suite' ); ?></small></a>
			<a class="kca-kpi kca-kpi--warn" href="<?php echo esc_url( self::url( 'partners', array( 'status' => 'overdue' ) ) ); ?>"><span><?php esc_html_e( 'Overdue (locked)', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) $stats['partners_by_status']['overdue']; ?></strong><small><?php echo (int) $stats['partners_by_status']['pending']; ?> <?php esc_html_e( 'awaiting first payment', 'kingdom-church-suite' ); ?></small></a>
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
				<p><a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( self::url( 'notify' ) ); ?>"><?php esc_html_e( 'Send a notification', 'kingdom-church-suite' ); ?></a></p>
			</section>
		</div>
		<?php
	}

	/**
	 * Partners list / detail.
	 */
	private static function view_partners() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		$plans = KCS_Plans::all();
		if ( $id && ( $p = KCS_Partners::get( $id ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found,Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
			$pays = KCS_Payments::query( array( 'partner_id' => $p->id, 'per_page' => 20 ) )['items'];
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
							<li><span><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( mysql2date( get_option( 'date_format' ), $pay->created_at ) . ' · ' . $pay->gateway ); ?></small></span><?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
						<?php endforeach; ?>
						<?php if ( ! $pays ) : ?><li class="kcs-muted"><?php esc_html_e( 'No payments yet.', 'kingdom-church-suite' ); ?></li><?php endif; ?>
					</ul>
					<?php if ( $p->phone ) : ?><p><a class="kcs-btn kcs-btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $p->phone ) ); ?>"><?php esc_html_e( 'WhatsApp this partner', 'kingdom-church-suite' ); ?></a></p><?php endif; ?>
				</section>
			</div>
			<?php
			return;
		}

		$result = KCS_Partners::query( array( 'search' => $search, 'status' => $status, 'per_page' => 25, 'page' => $page ) );
		$names  = array();
		foreach ( $plans as $pl ) {
			$names[ $pl->id ] = $pl->name;
		}
		?>
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="partners">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone, ID…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search partners', 'kingdom-church-suite' ); ?>">
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
		self::pager( $result['total'], 25, $page, array( 'view' => 'partners', 's' => $search, 'status' => $status ) );
	}

	/**
	 * Payments.
	 */
	private static function view_payments() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page   = max( 1, absint( $_GET['pg'] ?? 1 ) );
		// phpcs:enable
		$purpose  = isset( $_GET['purpose'] ) ? sanitize_key( $_GET['purpose'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$purposes = kcs_all_purposes();
		$result   = KCS_Payments::query( array( 'status' => $status, 'search' => $search, 'purpose' => $purpose, 'per_page' => 25, 'page' => $page ) );
		global $wpdb;
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
		<form class="kca-filters" method="get">
			<input type="hidden" name="view" value="payments">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Reference, name or email…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search payments', 'kingdom-church-suite' ); ?>">
			<select name="purpose" aria-label="<?php esc_attr_e( 'Purpose', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All purposes', 'kingdom-church-suite' ); ?></option><?php foreach ( $purposes as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $purpose, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><option value=""><?php esc_html_e( 'All statuses', 'kingdom-church-suite' ); ?></option><?php foreach ( KCS_Payments::statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
			<button class="kcs-btn"><?php esc_html_e( 'Filter', 'kingdom-church-suite' ); ?></button>
		</form>
		<div class="kca-table">
			<table>
				<thead><tr><th><?php esc_html_e( 'Giver', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Amount', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Method', 'kingdom-church-suite' ); ?></th><th><?php esc_html_e( 'Status', 'kingdom-church-suite' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $result['items'] as $pay ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Giver', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $pay->name ? $pay->name : $pay->email ); ?></strong><small><?php echo esc_html( $pay->reference . ' · ' . mysql2date( get_option( 'date_format' ), $pay->created_at ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Amount', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( kcs_format_money( $pay->amount, $pay->currency ) ); ?></strong><small><?php echo esc_html( $purposes[ $pay->purpose ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $pay->purpose ) ) ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Method', 'kingdom-church-suite' ); ?>"><?php echo esc_html( $pay->gateway ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'kingdom-church-suite' ); ?>"><?php echo kcs_status_badge( $pay->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td class="kca-actions">
							<?php if ( in_array( $pay->status, array( 'pending', 'review', 'failed' ), true ) ) : ?>
								<?php if ( KCS_Transfers::can_confirm() ) : ?><form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Mark this payment as received? The giver gets a receipt.', 'kingdom-church-suite' ) ); ?>')"><?php echo self::fields( 'payment_complete', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn"><?php esc_html_e( 'Mark received', 'kingdom-church-suite' ); ?></button></form><?php endif; ?>
								<?php if ( 'bank_transfer' !== $pay->gateway ) : ?><form method="post" class="kca-inline"><?php echo self::fields( 'payment_verify', 'payments', $pay->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php esc_html_e( 'Check gateway', 'kingdom-church-suite' ); ?></button></form><?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $result['items'] ) : ?><tr><td colspan="5" class="kcs-muted"><?php esc_html_e( 'No payments match.', 'kingdom-church-suite' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		self::pager( $result['total'], 25, $page, array( 'view' => 'payments', 's' => $search, 'status' => $status, 'purpose' => $purpose ) );
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
		$waiting     = KCS_Payments::query( array( 'gateway' => 'bank_transfer', 'status' => 'review', 'per_page' => 50 ) )['items'];
		$started     = KCS_Payments::query( array( 'gateway' => 'bank_transfer', 'status' => 'pending', 'date_from' => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -30 days' ) ), 'per_page' => 20 ) )['items'];
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
			<h3><?php esc_html_e( 'Waiting for you to confirm', 'kingdom-church-suite' ); ?> <?php if ( $waiting ) : ?><span class="kcs-badge kcs-badge--review"><?php echo count( $waiting ); ?></span><?php endif; ?></h3>
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
								<td data-label="<?php esc_attr_e( 'Giver', 'kingdom-church-suite' ); ?>"><strong><?php echo esc_html( $pay->name ? $pay->name : $pay->email ); ?></strong><small><?php echo esc_html( $pay->reference . ' · ' . mysql2date( get_option( 'date_format' ), $pay->created_at ) ); ?></small></td>
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
				<label><?php esc_html_e( 'Featured payment method on Tithe & Offering', 'kingdom-church-suite' ); ?>
					<select name="tithe_gateway">
						<option value="none"><?php esc_html_e( '— None —', 'kingdom-church-suite' ); ?></option>
						<?php foreach ( KCS_Payments::gateways() as $g ) : ?>
							<option value="<?php echo esc_attr( $g->id ); ?>" <?php selected( kcs_get_option( 'tithe_gateway', 'paystack' ), $g->id ); ?>><?php echo esc_html( $g->title . ( $g->is_enabled() ? '' : ' ' . __( '(not switched on)', 'kingdom-church-suite' ) ) ); ?></option>
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
		$rows = $wpdb->get_results( 'SELECT * FROM ' . kcs_table( 'prayer_requests' ) . " ORDER BY FIELD(status,'new','praying','approved','answered','archived'), id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<p class="kcs-muted">' . esc_html__( 'Requests marked “On prayer wall” or “Answered” (and not private) appear on the public prayer wall.', 'kingdom-church-suite' ) . '</p><div class="kca-stack">';
		foreach ( $rows as $r ) {
			echo '<article class="kca-card kca-item"><div><p>' . nl2br( esc_html( $r->request ) ) . '</p><small class="kcs-muted">' . esc_html( ( $r->name ? $r->name : __( 'Anonymous', 'kingdom-church-suite' ) ) . ' · ' . trim( $r->email . ' ' . $r->phone ) . ' · ' . mysql2date( get_option( 'date_format' ), $r->created_at ) ) . ( $r->is_private ? ' · ' . esc_html__( 'Private', 'kingdom-church-suite' ) : '' ) . ( $r->prayer_count ? ' · 🙏 ' . (int) $r->prayer_count : '' ) . '</small></div>' . self::status_form( 'prayer_status', 'prayers', $r->id, KCS_Prayer::statuses(), $r->status ) . '</article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $rows ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No prayer requests yet.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Visit requests.
	 */
	private static function view_visits() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . kcs_table( 'visits' ) . " ORDER BY FIELD(status,'new','confirmed','visited','no_show'), visit_date ASC, id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<div class="kca-stack">';
		foreach ( $rows as $v ) {
			$wa = $v->phone ? '<a class="kcs-btn kcs-btn--ghost" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D+/', '', $v->phone ) ) . '">WhatsApp</a>' : '';
			echo '<article class="kca-card kca-item"><div><strong>' . esc_html( $v->name ) . '</strong> <span class="kcs-muted">' . esc_html( ( $v->visit_date ? mysql2date( 'D, ' . get_option( 'date_format' ), $v->visit_date ) : __( 'Date not set', 'kingdom-church-suite' ) ) . ( $v->service ? ' · ' . $v->service : '' ) ) . '</span><p class="kcs-muted">' . esc_html( sprintf( /* translators: 1: adults, 2: kids */ __( '%1$d adult(s), %2$d child(ren)', 'kingdom-church-suite' ), $v->adults, $v->kids ) . ' · ' . trim( $v->email . ' ' . $v->phone ) ) . '</p>' . ( $v->notes ? '<p>' . esc_html( $v->notes ) . '</p>' : '' ) . '</div><div class="kca-actions">' . $wa . self::status_form( 'visit_status', 'visits', $v->id, KCS_Visits::statuses(), $v->status ) . '</div></article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $rows ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No visit requests yet. They come from the “Plan your visit” form.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Testimonies waiting for review.
	 */
	private static function view_testimonies() {
		$pending = get_posts( array( 'post_type' => 'kcs_testimony', 'post_status' => 'pending', 'posts_per_page' => 50 ) );
		echo '<div class="kca-stack">';
		foreach ( $pending as $t ) {
			echo '<article class="kca-card kca-item"><div><strong>' . esc_html( get_the_title( $t ) ) . '</strong><div>' . wp_kses_post( $t->post_content ) . '</div><small class="kcs-muted">— ' . esc_html( get_post_meta( $t->ID, '_kcs_person', true ) . ( get_post_meta( $t->ID, '_kcs_location', true ) ? ', ' . get_post_meta( $t->ID, '_kcs_location', true ) : '' ) ) . '</small></div><div class="kca-actions">';
			foreach ( array( 'publish' => __( 'Publish', 'kingdom-church-suite' ), 'trash' => __( 'Remove', 'kingdom-church-suite' ) ) as $k => $l ) {
				echo '<form method="post" class="kca-inline">' . self::fields( 'testimony', 'testimonies', $t->ID ) . '<input type="hidden" name="decision" value="' . esc_attr( $k ) . '"><button class="kcs-btn' . ( 'trash' === $k ? ' kcs-btn--ghost' : '' ) . '">' . esc_html( $l ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></article>';
		}
		if ( ! $pending ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No testimonies are waiting for review.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
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
				<section class="kca-card kcs-form">
					<h3><?php echo esc_html( $title ); ?></h3>
					<?php if ( 'banner' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Paste a YouTube, Vimeo or MP4 link to play it, muted and looping, behind the homepage banner. Leave it empty to use the banner image.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'brand' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Pick a ready-made palette, or choose Custom and set your own church colours. Button text switches between white and dark automatically so it stays readable.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'media' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Paste your YouTube channel link or @handle once: the website shows your live stream the moment you go live, a countdown when a stream is scheduled, and your replay video the rest of the time. Videos and audio left empty show your latest sermons.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'fonts' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Choose a ready-made pairing (a heading font and a body text font that look good together), or Custom to pick your own. The preview updates as you choose.', 'kingdom-church-suite' ); ?></p>
						<?php self::font_preview(); ?>
					<?php elseif ( 'pictures' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'Upload your own photos from your phone or computer. Remove a photo to go back to the built-in illustration. Wide photos work best for the banner.', 'kingdom-church-suite' ); ?></p>
					<?php elseif ( 'texts' === $group ) : ?>
						<p class="kcs-muted"><?php esc_html_e( 'The words on your homepage banner and welcome section. Button links are optional: leave them empty to use the usual pages.', 'kingdom-church-suite' ); ?></p>
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
						if ( 'checkbox' === $f[1] ) :
							?>
							<label class="kcs-check kca-switch"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( (bool) $value ); ?>> <?php echo esc_html( $f[0] ); ?></label>
						<?php elseif ( 'color' === $f[1] ) : ?>
							<label class="kca-colour"><input type="color" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"> <span><?php echo esc_html( $f[0] ); ?> <code><?php echo esc_html( $value ); ?></code></span></label>
						<?php elseif ( 'textarea' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><textarea name="<?php echo esc_attr( $key ); ?>" rows="3"><?php echo esc_textarea( $value ); ?></textarea></label>
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
						<?php elseif ( 'email' === $f[1] ) : ?>
							<label><?php echo esc_html( $f[0] ); ?><input type="email" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"></label>
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
			'payments'   => __( 'Turn on the payment methods you use and paste their keys. Every payment is checked with the provider before it counts as paid. Bank transfer accounts are managed under Bank transfers.', 'kingdom-church-suite' ),
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
					<section class="kca-card kcs-form kca-setting-group">
						<h3><?php echo esc_html( $section ); ?></h3>
				<?php endif; ?>
				<?php
				foreach ( $fields as $key => $field ) {
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
	 * One settings field.
	 *
	 * @param string $key   Key.
	 * @param array  $field Field definition from KCS_Settings::schema().
	 * @param array  $opts  Saved settings.
	 */
	private static function setting_field( $key, $field, $opts ) {
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
				echo '<label>' . esc_html( $field['label'] ) . '<input type="password" autocomplete="new-password" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-kcs-reveal>' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			default:
				$type  = in_array( $field['type'], array( 'email', 'url', 'number' ), true ) ? $field['type'] : 'text';
				$media = 'url' === $type && preg_match( '/logo|icon|image|photo/', $key ) && current_user_can( 'upload_files' ) ? ' data-kcs-media="url"' : '';
				echo '<label>' . esc_html( $field['label'] ) . '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( 'number' === $type ? ' step="any"' : '' ) . $media . '>' . $desc . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Newsletter: write to subscribers and partners, and manage subscribers.
	 */
	private static function view_newsletter() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$edit   = isset( $_GET['edit'] ) ? KCS_Newsletter::get_campaign( absint( $_GET['edit'] ) ) : null;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
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
		$subs  = $wpdb->get_results( "SELECT * FROM $t $where ORDER BY id DESC LIMIT 25 OFFSET " . ( ( $page - 1 ) * 25 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$sent  = $wpdb->get_results( 'SELECT * FROM ' . kcs_table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 10' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="kca-kpis">
			<div class="kca-kpi"><span><?php esc_html_e( 'Subscribers', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) ( $counts['subscribed'] ?? 0 ); ?></strong><small><?php echo (int) ( $counts['pending'] ?? 0 ); ?> <?php esc_html_e( 'waiting to confirm', 'kingdom-church-suite' ); ?></small></div>
			<div class="kca-kpi"><span><?php esc_html_e( 'Unsubscribed', 'kingdom-church-suite' ); ?></span><strong><?php echo (int) ( $counts['unsubscribed'] ?? 0 ); ?></strong></div>
		</div>
		<div class="kca-cols">
			<form method="post" class="kca-card kcs-form">
				<?php echo self::fields( 'campaign_save', 'newsletter', $edit ? $edit->id : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h3><?php echo $edit ? esc_html__( 'Edit draft', 'kingdom-church-suite' ) : esc_html__( 'Write an email', 'kingdom-church-suite' ); ?></h3>
				<label><?php esc_html_e( 'Send to', 'kingdom-church-suite' ); ?><select name="audience"><?php foreach ( KCS_Newsletter::audiences() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $edit ? $edit->audience : 'subscribers', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Subject', 'kingdom-church-suite' ); ?><input name="subject" required maxlength="200" value="<?php echo esc_attr( $edit ? $edit->subject : '' ); ?>"></label>
				<label><?php esc_html_e( 'Preview line (optional)', 'kingdom-church-suite' ); ?><input name="preheader" maxlength="200" value="<?php echo esc_attr( $edit ? $edit->preheader : '' ); ?>" placeholder="<?php esc_attr_e( 'Shown after the subject in the inbox', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?><textarea name="content" rows="9" required><?php echo esc_textarea( $edit ? trim( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br />' ), array( "\n\n", "\n", "\n" ), $edit->content ) ) ) : '' ); ?></textarea><small class="kcs-muted"><?php esc_html_e( 'Write {first_name} to greet each person by name. Links are made clickable.', 'kingdom-church-suite' ); ?></small></label>
				<div class="kca-actions">
					<button class="kcs-btn" name="do" value="send" onclick="return confirm('<?php echo esc_js( __( 'Send this email now?', 'kingdom-church-suite' ) ); ?>')"><?php esc_html_e( 'Send now', 'kingdom-church-suite' ); ?></button>
					<button class="kcs-btn kcs-btn--ghost" name="do" value="test"><?php esc_html_e( 'Send me a test', 'kingdom-church-suite' ); ?></button>
					<button class="kcs-btn kcs-btn--ghost" name="do" value="save"><?php esc_html_e( 'Save draft', 'kingdom-church-suite' ); ?></button>
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
			</section>
		</div>
		<section class="kca-card kca-gap">
			<h3><?php esc_html_e( 'Subscribers', 'kingdom-church-suite' ); ?></h3>
			<form class="kca-filters" method="get">
				<input type="hidden" name="view" value="newsletter">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name or email…', 'kingdom-church-suite' ); ?>" aria-label="<?php esc_attr_e( 'Search subscribers', 'kingdom-church-suite' ); ?>">
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
			<?php self::pager( $total, 25, $page, array( 'view' => 'newsletter', 's' => $search ) ); ?>
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
				<label class="kcs-check"><input type="checkbox" name="pwa_live_push" value="1" <?php checked( '1', (string) kcs_get_option( 'pwa_live_push', '1' ) ); ?>> <?php esc_html_e( 'Notify every phone automatically when the YouTube live stream starts', 'kingdom-church-suite' ); ?></label>
				<label><?php esc_html_e( 'App name', 'kingdom-church-suite' ); ?><input name="pwa_name" value="<?php echo esc_attr( kcs_get_option( 'pwa_name', '' ) ); ?>" placeholder="<?php echo esc_attr( kcs_get_option( 'church_name', get_bloginfo( 'name' ) ) ); ?>"></label>
				<label><?php esc_html_e( 'Name under the icon (max 14 letters)', 'kingdom-church-suite' ); ?><input name="pwa_short_name" maxlength="14" value="<?php echo esc_attr( kcs_get_option( 'pwa_short_name', '' ) ); ?>"></label>
				<label><?php esc_html_e( 'App icon image URL (optional — square, at least 512×512)', 'kingdom-church-suite' ); ?><input type="url" name="pwa_icon"<?php echo current_user_can( 'upload_files' ) ? ' data-kcs-media="url"' : ''; ?> value="<?php echo esc_attr( kcs_get_option( 'pwa_icon', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to use your church logo', 'kingdom-church-suite' ); ?>"></label>
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
	private static function pager( $total, $per, $page, $args ) {
		$pages = (int) ceil( $total / $per );
		if ( $pages < 2 ) {
			return;
		}
		echo '<nav class="kca-pager">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			echo '<a href="' . esc_url( add_query_arg( array_merge( array_filter( $args ), array( 'pg' => $i ) ), kcs_page_url( 'church_admin' ) ) ) . '"' . ( $i === $page ? ' aria-current="page"' : '' ) . '>' . (int) $i . '</a>';
		}
		echo '</nav>';
	}
}
