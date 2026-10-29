<?php
/**
 * Partners repository and lifecycle.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Partners.
 */
class KCS_Partners {

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'pending'  => __( 'Pending', 'kingdom-church-suite' ),
			'active'   => __( 'Active', 'kingdom-church-suite' ),
			'overdue'  => __( 'Overdue', 'kingdom-church-suite' ),
			'paused'   => __( 'Paused', 'kingdom-church-suite' ),
			'inactive' => __( 'Inactive', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'kcs_daily_tasks', array( __CLASS__, 'daily_tasks' ) );
		add_action( 'delete_user', array( __CLASS__, 'on_delete_user' ) );
	}

	/**
	 * Get partner by ID.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = kcs_table( 'partners' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get partner by WP user.
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;
		$table = kcs_table( 'partners' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d LIMIT 1", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get partner by email.
	 *
	 * @param string $email Email.
	 * @return object|null
	 */
	public static function get_by_email( $email ) {
		global $wpdb;
		$table = kcs_table( 'partners' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Query partners.
	 *
	 * @param array $args search, status, plan_id, per_page, page, orderby, order.
	 * @return array{items:array,total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'plan_id'  => 0,
				'per_page' => 20,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);
		$table = kcs_table( 'partners' );
		$where = array( '1=1' );
		$vals  = array();
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR partner_code LIKE %s)';
			array_push( $vals, $like, $like, $like, $like, $like );
		}
		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( $args['plan_id'] ) {
			$where[] = 'plan_id = %d';
			$vals[]  = (int) $args['plan_id'];
		}
		$allowed = array( 'created_at', 'first_name', 'email', 'amount', 'total_given', 'next_due_date', 'status' );
		$orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$sql_w   = implode( ' AND ', $where );
		$limit   = max( 1, (int) $args['per_page'] );
		$offset  = ( max( 1, (int) $args['page'] ) - 1 ) * $limit;

		// phpcs:disable WordPress.DB.PreparedSQL
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $sql_w";
		$items_sql = "SELECT * FROM $table WHERE $sql_w ORDER BY $orderby $order LIMIT $limit OFFSET $offset";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );
		$items     = $vals ? $wpdb->get_results( $wpdb->prepare( $items_sql, $vals ) ) : $wpdb->get_results( $items_sql );
		// phpcs:enable

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Generate a unique partner code.
	 *
	 * @return string
	 */
	public static function generate_code() {
		global $wpdb;
		$table = kcs_table( 'partners' );
		do {
			$code   = 'P' . gmdate( 'y' ) . strtoupper( wp_generate_password( 6, false, false ) );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE partner_code = %s", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} while ( $exists );
		return $code;
	}

	/**
	 * Create a partner (and optionally its WP user).
	 *
	 * @param array $data first_name,last_name,email,phone,country,city,address,plan_id,amount,currency,frequency,password,status,notes.
	 * @param bool  $create_user Whether to create a WP user.
	 * @return int|WP_Error Partner ID.
	 */
	public static function create( $data, $create_user = true ) {
		global $wpdb;
		$email = sanitize_email( $data['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'kcs_invalid_email', __( 'Please enter a valid email address.', 'kingdom-church-suite' ) );
		}
		if ( self::get_by_email( $email ) ) {
			return new WP_Error( 'kcs_partner_exists', __( 'A partner with this email already exists. Please log in instead.', 'kingdom-church-suite' ) );
		}
		/**
		 * Allow or block new partners (used by the SaaS plan limits).
		 *
		 * @param true|WP_Error $allowed True to allow.
		 * @param array         $data    Partner data.
		 */
		$allowed = apply_filters( 'kcs_can_create_partner', true, $data );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$plan      = ! empty( $data['plan_id'] ) ? KCS_Plans::get( (int) $data['plan_id'] ) : null;
		$amount    = isset( $data['amount'] ) && '' !== $data['amount'] ? (float) $data['amount'] : ( $plan ? (float) $plan->amount : 0 );
		$currency  = ! empty( $data['currency'] ) ? $data['currency'] : ( $plan ? $plan->currency : kcs_get_option( 'currency', 'USD' ) );
		$frequency = ! empty( $data['frequency'] ) ? $data['frequency'] : ( $plan ? $plan->frequency : 'monthly' );

		// International (default) or local partner: the region decides the currency.
		if ( ! empty( $data['region'] ) ) {
			$currency = kcs_region_currency( $data['region'] );
		}
		if ( $plan && ! KCS_Plans::is_custom( $plan ) ) {
			$price    = KCS_Plans::price( $plan, ! empty( $data['region'] ) ? kcs_sanitize_region( $data['region'] ) : kcs_currency_region( $currency ) );
			$amount   = $price['amount'];
			$currency = $price['currency'];
		}
		if ( $amount <= 0 ) {
			return new WP_Error( 'kcs_invalid_amount', __( 'Please enter a valid partnership amount.', 'kingdom-church-suite' ) );
		}
		if ( ! array_key_exists( $currency, kcs_currencies() ) ) {
			$currency = kcs_get_option( 'currency', 'USD' );
		}
		if ( ! array_key_exists( $frequency, kcs_frequencies() ) ) {
			$frequency = 'monthly';
		}

		$user_id = 0;
		if ( $create_user ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				if ( get_current_user_id() !== $existing->ID && ! current_user_can( 'manage_kcs' ) ) {
					return new WP_Error( 'kcs_user_exists', __( 'An account with this email already exists. Please log in first, then become a partner.', 'kingdom-church-suite' ) );
				}
				$user_id = $existing->ID;
				if ( ! in_array( 'administrator', (array) $existing->roles, true ) ) {
					$existing->add_role( 'kcs_partner' );
				}
			} else {
				$password = ! empty( $data['password'] ) ? $data['password'] : wp_generate_password( 14 );
				$username = sanitize_user( current( explode( '@', $email ) ), true );
				$base     = $username ? $username : 'partner';
				$i        = 1;
				while ( username_exists( $username ) || ! $username ) {
					$username = $base . $i++;
				}
				$user_id = wp_insert_user(
					array(
						'user_login'   => $username,
						'user_email'   => $email,
						'user_pass'    => $password,
						'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
						'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
						'display_name' => trim( sanitize_text_field( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ) ),
						'role'         => 'kcs_partner',
					)
				);
				if ( is_wp_error( $user_id ) ) {
					return $user_id;
				}
				if ( empty( $data['password'] ) ) {
					wp_new_user_notification( $user_id, null, 'user' );
				}
			}
		}

		$status = ! empty( $data['status'] ) && array_key_exists( $data['status'], self::statuses() )
			? $data['status']
			: ( '1' === kcs_get_option( 'partner_auto_activate', '1' ) ? 'pending' : 'active' );

		$now = current_time( 'mysql' );
		$wpdb->insert(
			kcs_table( 'partners' ),
			array(
				'user_id'       => (int) $user_id,
				'partner_code'  => self::generate_code(),
				'plan_id'       => $plan ? (int) $plan->id : 0,
				'first_name'    => sanitize_text_field( $data['first_name'] ?? '' ),
				'last_name'     => sanitize_text_field( $data['last_name'] ?? '' ),
				'email'         => $email,
				'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
				'country'       => sanitize_text_field( $data['country'] ?? '' ),
				'city'          => sanitize_text_field( $data['city'] ?? '' ),
				'address'       => sanitize_textarea_field( $data['address'] ?? '' ),
				'amount'        => $amount,
				'currency'      => $currency,
				'frequency'     => $frequency,
				'status'        => $status,
				'next_due_date' => current_time( 'Y-m-d' ),
				'email_opt_in'  => isset( $data['email_opt_in'] ) ? (int) (bool) $data['email_opt_in'] : 1,
				'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		$partner_id = (int) $wpdb->insert_id;
		if ( ! $partner_id ) {
			return new WP_Error( 'kcs_db', __( 'Could not save partner.', 'kingdom-church-suite' ) );
		}

		$partner = self::get( $partner_id );
		self::send_welcome( $partner );
		do_action( 'kcs_partner_created', $partner );
		KCS_Email::webhook( 'partner.created', self::to_array( $partner ) );

		if ( ! empty( $partner->email_opt_in ) ) {
			KCS_Newsletter::subscribe( $email, trim( $partner->first_name . ' ' . $partner->last_name ), 'partner', true );
		}
		return $partner_id;
	}

	/**
	 * Update a partner.
	 *
	 * @param int   $id   Partner ID.
	 * @param array $data Fields.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$allowed = array(
			'first_name'    => 'text',
			'last_name'     => 'text',
			'email'         => 'email',
			'phone'         => 'text',
			'country'       => 'text',
			'city'          => 'text',
			'address'       => 'textarea',
			'plan_id'       => 'int',
			'amount'        => 'float',
			'currency'      => 'currency',
			'frequency'     => 'frequency',
			'status'        => 'status',
			'next_due_date' => 'date',
			'email_opt_in'  => 'bool',
			'notes'         => 'textarea',
		);
		$row     = array();
		foreach ( $allowed as $key => $type ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$v = $data[ $key ];
			switch ( $type ) {
				case 'email':
					$v = sanitize_email( $v );
					if ( ! is_email( $v ) ) {
						continue 2;
					}
					// Keep emails unique and the partner's login account in step with them.
					$current = self::get( $id );
					if ( $current && strtolower( $v ) !== strtolower( $current->email ) ) {
						$other      = self::get_by_email( $v );
						$owner      = email_exists( $v );
						$linked     = (int) $current->user_id;
						if ( ( $other && (int) $other->id !== (int) $id ) || ( $owner && (int) $owner !== $linked ) ) {
							continue 2;
						}
						if ( $linked ) {
							wp_update_user( array( 'ID' => $linked, 'user_email' => $v ) );
						}
					}
					break;
				case 'textarea':
					$v = sanitize_textarea_field( $v );
					break;
				case 'int':
					$v = (int) $v;
					break;
				case 'float':
					$v = max( 0, (float) $v );
					break;
				case 'bool':
					$v = $v ? 1 : 0;
					break;
				case 'currency':
					if ( ! array_key_exists( $v, kcs_currencies() ) ) {
						continue 2;
					}
					break;
				case 'frequency':
					if ( ! array_key_exists( $v, kcs_frequencies() ) ) {
						continue 2;
					}
					break;
				case 'status':
					if ( ! array_key_exists( $v, self::statuses() ) ) {
						continue 2;
					}
					break;
				case 'date':
					$v = $v && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
					break;
				default:
					$v = sanitize_text_field( $v );
			}
			$row[ $key ] = $v;
		}
		if ( ! $row ) {
			return false;
		}
		$row['updated_at'] = current_time( 'mysql' );
		$result            = $wpdb->update( kcs_table( 'partners' ), $row, array( 'id' => (int) $id ) );
		do_action( 'kcs_partner_updated', (int) $id, $row );
		return false !== $result;
	}

	/**
	 * Move a partner to another plan (upgrade, downgrade or switch).
	 *
	 * @param object $partner   Partner.
	 * @param int    $plan_id   New plan.
	 * @param float  $amount    Amount for custom-amount plans.
	 * @param string $frequency Frequency for custom-amount plans.
	 * @param string $region    international|local; empty keeps the partner's current one.
	 * @return string|WP_Error  upgraded|downgraded|switched.
	 */
	public static function change_plan( $partner, $plan_id, $amount = 0, $frequency = '', $region = '' ) {
		$plan = KCS_Plans::get( (int) $plan_id );
		if ( ! $plan || ! $plan->is_active ) {
			return new WP_Error( 'kcs_plan', __( 'Please choose an available plan.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$region   = $region ? kcs_sanitize_region( $region ) : kcs_currency_region( $partner->currency );
		$custom   = KCS_Plans::is_custom( $plan );
		$price    = KCS_Plans::price( $plan, $region );
		$amount   = $custom ? (float) $amount : $price['amount'];
		$currency = $custom ? kcs_region_currency( $region ) : $price['currency'];
		$freq     = $custom && array_key_exists( $frequency, kcs_frequencies() ) ? $frequency : ( $custom ? $partner->frequency : $plan->frequency );
		if ( $amount <= 0 ) {
			return new WP_Error( 'kcs_amount', __( 'Please enter your partnership amount.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		if ( (int) $partner->plan_id === (int) $plan->id && abs( $amount - (float) $partner->amount ) < 0.01 && $freq === $partner->frequency && $currency === $partner->currency ) {
			return new WP_Error( 'kcs_same_plan', __( 'You are already on this plan.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}

		// Compare monthly-equivalent value so a yearly plan is ranked fairly against a monthly one.
		$per_month = array( 'weekly' => 52 / 12, 'monthly' => 1, 'quarterly' => 1 / 3, 'yearly' => 1 / 12, 'one_time' => 1 / 12 );
		$old_value = (float) $partner->amount * ( $per_month[ $partner->frequency ] ?? 1 );
		$new_value = $amount * ( $per_month[ $freq ] ?? 1 );
		$direction = $partner->currency !== $currency ? 'switched' : ( $new_value > $old_value ? 'upgraded' : ( $new_value < $old_value ? 'downgraded' : 'switched' ) );

		self::track_upgrade( $partner, $amount, $freq, $currency, $plan->id );
		$old_plan = $partner->plan_id ? KCS_Plans::get( $partner->plan_id ) : null;
		$note     = sprintf( '[%s] %s: %s → %s (%s)', current_time( 'Y-m-d' ), ucfirst( $direction ), $old_plan ? $old_plan->name : __( 'Custom', 'kingdom-church-suite' ), $plan->name, kcs_format_money( $amount, $currency ) );
		self::update(
			$partner->id,
			array(
				'plan_id'   => $plan->id,
				'amount'    => $amount,
				'currency'  => $currency,
				'frequency' => $freq,
				'notes'     => trim( $partner->notes . "\n" . $note ),
			)
		);

		$fresh = self::get( $partner->id );
		$vars  = array_merge( self::email_vars( $fresh, $plan ), array( 'old_plan' => $old_plan ? $old_plan->name : __( 'Custom', 'kingdom-church-suite' ), 'change' => $direction ) );
		KCS_Email::send_template( $fresh->email, 'plan_changed', $vars );
		if ( '1' === kcs_get_option( 'notify_admin_partner', '1' ) ) {
			KCS_Email::send(
				KCS_Email::admin_recipients(),
				/* translators: 1: partner name, 2: change, 3: plan */
				sprintf( __( '%1$s %2$s to %3$s', 'kingdom-church-suite' ), $vars['name'], $direction, $plan->name ),
				'<p>' . esc_html( $note ) . '</p><p><a class="btn" href="' . esc_url( $vars['admin_url'] ) . '">' . esc_html__( 'View partner', 'kingdom-church-suite' ) . '</a></p>'
			);
		}
		do_action( 'kcs_partner_plan_changed', $fresh, $plan, $direction );
		KCS_Email::webhook( 'partner.plan_changed', array_merge( self::to_array( $fresh ), array( 'change' => $direction ) ) );
		return $direction;
	}

	/**
	 * Total given, per currency, e.g. "$720.00 + ₦25,000.00".
	 *
	 * Partners can switch between international and local currency, so their completed
	 * payments are summed per currency instead of adding dollars to naira.
	 *
	 * @param object $partner Partner.
	 * @return string
	 */
	public static function total_given_label( $partner ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT currency, SUM(amount) total FROM ' . kcs_table( 'payments' ) . " WHERE partner_id = %d AND status = 'completed' GROUP BY currency ORDER BY total DESC", $partner->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $rows ) {
			return kcs_format_money( $partner->total_given, $partner->currency );
		}
		return implode(
			' + ',
			array_map(
				function ( $r ) {
					return kcs_format_money( $r->total, $r->currency );
				},
				$rows
			)
		);
	}

	/**
	 * Delete a partner (keeps the WP user and payment history).
	 *
	 * @param int $id Partner ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$partner = self::get( $id );
		if ( ! $partner ) {
			return;
		}
		if ( $partner->user_id ) {
			$user = get_user_by( 'id', $partner->user_id );
			if ( $user ) {
				$user->remove_role( 'kcs_partner' );
				if ( empty( $user->roles ) ) {
					$user->set_role( 'subscriber' );
				}
			}
		}
		$wpdb->delete( kcs_table( 'partners' ), array( 'id' => (int) $id ) );
		do_action( 'kcs_partner_deleted', (int) $id );
	}

	/**
	 * When a WP user is deleted, detach the partner record.
	 *
	 * @param int $user_id User ID.
	 */
	public static function on_delete_user( $user_id ) {
		global $wpdb;
		$wpdb->update( kcs_table( 'partners' ), array( 'user_id' => 0, 'status' => 'inactive' ), array( 'user_id' => (int) $user_id ) );
	}

	/**
	 * Remember what a partner had paid for before an upgrade, so the higher tier (pastor contacts,
	 * premium updates) opens only after a payment at the new level. Downgrades apply at once.
	 *
	 * @param object $partner  Partner before the change.
	 * @param float  $amount   New amount.
	 * @param string $freq     New frequency.
	 * @param string $currency New currency.
	 * @param int    $plan_id  New plan.
	 */
	public static function track_upgrade( $partner, $amount, $freq, $currency, $plan_id ) {
		$key  = 'kcs_upgrade_' . (int) $partner->id;
		$paid = get_option( $key );
		if ( ! is_array( $paid ) ) {
			$paid = array( 'plan_id' => (int) $partner->plan_id, 'amount' => (float) $partner->amount, 'frequency' => $partner->frequency, 'currency' => $partner->currency );
		}
		$new = (object) array( 'plan_id' => (int) $plan_id, 'amount' => (float) $amount, 'frequency' => $freq, 'currency' => $currency );
		if ( KCS_Access::tier( $new ) > KCS_Access::tier( (object) $paid ) ) {
			update_option( $key, $paid, false );
		} else {
			delete_option( $key );
		}
	}

	/**
	 * The plan values a partner has actually paid for (differs from the record only while an
	 * upgrade is unpaid).
	 *
	 * @param object $partner Partner.
	 * @return object
	 */
	public static function paid_terms( $partner ) {
		$paid = get_option( 'kcs_upgrade_' . (int) $partner->id );
		return is_array( $paid ) ? (object) array_merge( (array) $partner, $paid ) : $partner;
	}

	/**
	 * Record a completed payment against a partner.
	 *
	 * @param object $partner Partner.
	 * @param object $payment Payment.
	 */
	public static function apply_payment( $partner, $payment ) {
		global $wpdb;
		$same_currency = strtoupper( (string) $payment->currency ) === strtoupper( (string) $partner->currency );
		// Only a payment that covers the partner's commitment (in their currency) renews the
		// partnership; anything smaller is recorded as a gift but does not unlock or extend it.
		$covers = $same_currency && ( (float) $partner->amount <= 0 || (float) $payment->amount + 0.01 >= (float) $partner->amount );
		$table  = kcs_table( 'partners' );
		$now    = current_time( 'mysql' );
		if ( ! $covers ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $table SET total_given = total_given + %f, last_payment_at = %s, updated_at = %s WHERE id = %d", $same_currency ? (float) $payment->amount : 0, $now, $now, (int) $partner->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}
		delete_option( 'kcs_upgrade_' . (int) $partner->id ); // Paid at the new level: the upgrade is complete.
		$base     = ( $partner->next_due_date && $partner->next_due_date > current_time( 'Y-m-d' ) ) ? $partner->next_due_date : current_time( 'Y-m-d' );
		$next_due = kcs_next_due_date( $partner->frequency, $base );
		// One-time partners have no next due date: store NULL (not an empty date).
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET total_given = total_given + %f, last_payment_at = %s, next_due_date = NULLIF(%s, ''), status = 'active', updated_at = %s WHERE id = %d", (float) $payment->amount, $now, (string) $next_due, $now, (int) $partner->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Welcome emails.
	 *
	 * @param object $partner Partner.
	 */
	public static function send_welcome( $partner ) {
		$plan = $partner->plan_id ? KCS_Plans::get( $partner->plan_id ) : null;
		$vars = self::email_vars( $partner, $plan );
		KCS_Email::send_template( $partner->email, 'partner_welcome', $vars );
		if ( '1' === kcs_get_option( 'notify_admin_partner', '1' ) ) {
			KCS_Email::send_template( KCS_Email::admin_recipients(), 'admin_new_partner', $vars );
		}
	}

	/**
	 * Common template variables.
	 *
	 * @param object      $partner Partner.
	 * @param object|null $plan    Plan.
	 * @return array
	 */
	public static function email_vars( $partner, $plan = null ) {
		$freqs = kcs_frequencies();
		return array(
			'first_name'    => $partner->first_name,
			'name'          => trim( $partner->first_name . ' ' . $partner->last_name ),
			'email'         => $partner->email,
			'phone'         => $partner->phone,
			'partner_code'  => $partner->partner_code,
			'plan_name'     => $plan ? $plan->name : __( 'Custom', 'kingdom-church-suite' ),
			'amount'        => kcs_format_money( $partner->amount, $partner->currency ),
			'frequency'     => isset( $freqs[ $partner->frequency ] ) ? $freqs[ $partner->frequency ] : $partner->frequency,
			'due_date'      => $partner->next_due_date ? date_i18n( get_option( 'date_format' ), strtotime( $partner->next_due_date ) ) : '',
			'dashboard_url' => kcs_page_url( 'partner_dashboard' ),
			'pay_url'       => add_query_arg( 'kcs_action', 'pay', kcs_page_url( 'partner_dashboard' ) ),
			'admin_url'     => KCS_Church_Admin::url( 'partners', array( 'id' => (int) $partner->id ) ),
		);
	}

	/**
	 * Daily: reminders and overdue flags.
	 */
	public static function daily_tasks() {
		global $wpdb;
		$table = kcs_table( 'partners' );
		$today = current_time( 'Y-m-d' );

		self::cleanup_abandoned();

		// Mark overdue once the grace period has passed (this also locks the partner dashboard).
		$grace = max( 0, (int) kcs_get_option( 'grace_days', 7 ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'overdue' WHERE status = 'active' AND frequency <> 'one_time' AND next_due_date IS NOT NULL AND next_due_date < %s", gmdate( 'Y-m-d', strtotime( $today . ' -' . $grace . ' days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( '1' !== kcs_get_option( 'send_reminders', '1' ) ) {
			return;
		}
		$days     = max( 0, (int) kcs_get_option( 'reminder_days', 3 ) );
		$target   = gmdate( 'Y-m-d', strtotime( $today . ' +' . $days . ' days' ) );
		$partners = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status IN ('active','overdue') AND frequency <> 'one_time' AND (next_due_date = %s OR (status = 'overdue' AND DAYOFWEEK(%s) = 2))", $target, $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $partners as $partner ) {
			$plan = $partner->plan_id ? KCS_Plans::get( $partner->plan_id ) : null;
			KCS_Email::send_template( $partner->email, 'payment_reminder', self::email_vars( $partner, $plan ) );
		}
	}

	/**
	 * Can this partner's website login be removed along with them?
	 * Only plain partner/subscriber logins: never staff, editors, the person doing it, or a network admin.
	 *
	 * @param object $partner Partner.
	 * @return WP_User|null
	 */
	public static function login_removable( $partner ) {
		$user = $partner->user_id ? get_user_by( 'id', (int) $partner->user_id ) : null;
		if ( ! $user || get_current_user_id() === (int) $user->ID || is_super_admin( $user->ID ) || user_can( $user, 'manage_kcs' ) || user_can( $user, 'edit_posts' ) ) {
			return null;
		}
		return array_diff( (array) $user->roles, array( 'kcs_partner', 'subscriber' ) ) ? null : $user;
	}

	/**
	 * Remove a partner's website login. On a network the person is taken off this church's site, and
	 * their account is deleted completely when they no longer belong to any site (otherwise it stayed
	 * in Network Admin → Users).
	 *
	 * @param WP_User $user Login.
	 * @return string deleted|removed
	 */
	public static function remove_login( $user ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( ! is_multisite() ) {
			wp_delete_user( $user->ID );
			return 'deleted';
		}
		remove_user_from_blog( $user->ID, get_current_blog_id() );
		if ( ! is_super_admin( $user->ID ) && ! get_blogs_of_user( $user->ID ) ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			wpmu_delete_user( $user->ID );
			return 'deleted';
		}
		return 'removed';
	}

	/**
	 * Partners who signed up more than $days days ago and never paid.
	 *
	 * @param int  $days  Days.
	 * @param bool $count Return only how many.
	 * @return array|int
	 */
	public static function abandoned( $days, $count = false ) {
		global $wpdb;
		$table    = kcs_table( 'partners' );
		$payments = kcs_table( 'payments' );
		$before   = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - max( 1, (int) $days ) * DAY_IN_SECONDS );
		$where    = "p.status = 'pending' AND p.last_payment_at IS NULL AND p.total_given <= 0 AND p.created_at < %s AND NOT EXISTS (SELECT 1 FROM $payments y WHERE y.partner_id = p.id AND y.status IN ('completed','review'))";
		if ( $count ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table p WHERE $where", $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table p WHERE $where LIMIT 200", $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Sign-ups that never paid: still Pending, nothing given, older than the setting
	 * (Settings → Partnership, default 7 days, 0 = keep). They are removed with their login.
	 * Partners with a transfer waiting for the church to confirm are kept.
	 *
	 * @param int|null $days Days (null = setting).
	 * @return int Partners removed.
	 */
	public static function cleanup_abandoned( $days = null ) {
		$days = null === $days ? (int) kcs_get_option( 'partner_abandon_days', 7 ) : (int) $days;
		if ( $days <= 0 ) {
			return 0;
		}
		$n = 0;
		foreach ( self::abandoned( $days ) as $partner ) {
			$login = self::login_removable( $partner );
			self::delete( $partner->id );
			if ( $login ) {
				self::remove_login( $login );
			}
			++$n;
		}
		if ( $n ) {
			update_option( 'kcs_partners_cleaned', array( 'count' => $n, 'at' => current_time( 'mysql' ) ), false );
		}
		return $n;
	}

	/**
	 * Stats for the admin dashboard and API.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$p   = kcs_table( 'partners' );
		$pay = kcs_table( 'payments' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_status = $wpdb->get_results( "SELECT status, COUNT(*) c FROM $p GROUP BY status", OBJECT_K );
		$month     = $wpdb->get_results( $wpdb->prepare( "SELECT currency, SUM(amount) total FROM $pay WHERE status = 'completed' AND paid_at >= %s GROUP BY currency", current_time( 'Y-m-01 00:00:00' ) ) );
		$all       = $wpdb->get_results( "SELECT currency, SUM(amount) total, COUNT(*) c FROM $pay WHERE status = 'completed' GROUP BY currency" );
		$series    = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(paid_at, '%%Y-%%m') ym, currency, SUM(amount) total FROM $pay WHERE status = 'completed' AND paid_at >= %s GROUP BY ym, currency ORDER BY ym ASC", gmdate( 'Y-m-01', strtotime( '-11 months', current_time( 'timestamp' ) ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$subs      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'subscribers' ) . " WHERE status = 'subscribed'" );
		$prayers   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'prayer_requests' ) . " WHERE status = 'new'" );
		// phpcs:enable

		$status_counts = array();
		$total         = 0;
		foreach ( self::statuses() as $k => $label ) {
			$status_counts[ $k ] = isset( $by_status[ $k ] ) ? (int) $by_status[ $k ]->c : 0;
			$total              += $status_counts[ $k ];
		}
		return array(
			'partners_total'     => $total,
			'partners_by_status' => $status_counts,
			'revenue_this_month' => $month,
			'revenue_all_time'   => $all,
			'monthly_series'     => $series,
			'subscribers'        => $subs,
			'new_prayers'        => $prayers,
		);
	}

	/**
	 * API representation.
	 *
	 * @param object $p Partner.
	 * @return array
	 */
	public static function to_array( $p ) {
		$plan = $p->plan_id ? KCS_Plans::get( $p->plan_id ) : null;
		return array(
			'id'              => (int) $p->id,
			'user_id'         => (int) $p->user_id,
			'partner_code'    => $p->partner_code,
			'first_name'      => $p->first_name,
			'last_name'       => $p->last_name,
			'email'           => $p->email,
			'phone'           => $p->phone,
			'country'         => $p->country,
			'city'            => $p->city,
			'address'         => $p->address,
			'plan'            => $plan ? array( 'id' => (int) $plan->id, 'name' => $plan->name ) : null,
			'amount'          => (float) $p->amount,
			'currency'        => $p->currency,
			'frequency'       => $p->frequency,
			'status'          => $p->status,
			'next_due_date'   => $p->next_due_date,
			'last_payment_at' => $p->last_payment_at,
			'total_given'     => (float) $p->total_given,
			'email_opt_in'    => (bool) $p->email_opt_in,
			'created_at'      => $p->created_at,
		);
	}
}
