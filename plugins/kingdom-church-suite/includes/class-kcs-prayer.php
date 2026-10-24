<?php
/**
 * Prayer requests & the community prayer wall: people share requests (by name or anonymously),
 * others tap "I prayed" and leave encouragements, and answered prayers become praise reports.
 *
 * The person who asked gets a private link to see who is praying, read encouragements and mark
 * the prayer as answered.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prayer.
 */
class KCS_Prayer {

	/**
	 * Hooks.
	 */
	public static function init() {}

	/**
	 * Statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'new'      => __( 'New', 'kingdom-church-suite' ),
			'praying'  => __( 'Praying', 'kingdom-church-suite' ),
			'approved' => __( 'On prayer wall', 'kingdom-church-suite' ),
			'answered' => __( 'Answered 🙌', 'kingdom-church-suite' ),
			'archived' => __( 'Archived', 'kingdom-church-suite' ),
		);
	}

	/**
	 * Prayer topics, key => label.
	 *
	 * @return array
	 */
	public static function categories() {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) kcs_get_option( 'prayer_categories', "Healing\nFamily\nFinances\nSalvation\nGuidance\nWork & career\nRelationships\nThanksgiving\nOther" ) ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[ sanitize_title( $line ) ] = $line;
			}
		}
		return $out;
	}

	/**
	 * Create a request.
	 *
	 * @param array $data name,email,phone,request,is_private,category,city,anonymous,notify.
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		global $wpdb;
		$request = sanitize_textarea_field( $data['request'] ?? '' );
		if ( strlen( $request ) < 5 ) {
			return new WP_Error( 'kcs_prayer', __( 'Please share your prayer request.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$request  = mb_substr( $request, 0, 2000 );
		$email    = sanitize_email( $data['email'] ?? '' );
		$private  = ! empty( $data['is_private'] );
		$category = sanitize_title( $data['category'] ?? '' );
		$token    = wp_generate_password( 32, false );
		$wpdb->insert(
			kcs_table( 'prayer_requests' ),
			array(
				'name'       => mb_substr( sanitize_text_field( $data['name'] ?? '' ), 0, 100 ),
				'email'      => is_email( $email ) ? $email : '',
				'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
				'request'    => $request,
				'is_private' => $private ? 1 : 0,
				'status'     => ! $private && '1' === kcs_get_option( 'prayer_auto_approve', '0' ) ? 'approved' : 'new',
				'category'   => isset( self::categories()[ $category ] ) ? $category : '',
				'city'       => mb_substr( sanitize_text_field( $data['city'] ?? '' ), 0, 100 ),
				'anonymous'  => empty( $data['anonymous'] ) ? 0 : 1,
				'notify'     => ! empty( $data['notify'] ) && is_email( $email ) ? 1 : 0,
				'token'      => $token,
				'created_at' => current_time( 'mysql' ),
			)
		);
		$id   = (int) $wpdb->insert_id;
		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( is_email( $email ) ) {
			$t = KCS_Email::render_template( 'prayer_received', array( 'name' => $name ? $name : __( 'friend', 'kingdom-church-suite' ) ) );
			KCS_Email::send( $email, $t['subject'], $t['body'] . ( $private ? '' : self::manage_block( $token ) ) );
		}
		if ( '1' === kcs_get_option( 'notify_admin_prayer', '1' ) ) {
			KCS_Email::send(
				KCS_Email::admin_recipients(),
				/* translators: %s: name */
				sprintf( __( 'New prayer request from %s', 'kingdom-church-suite' ), $name ? $name : __( 'Anonymous', 'kingdom-church-suite' ) ),
				'<p>' . nl2br( esc_html( $request ) ) . '</p><p>' . esc_html( $email ) . ' ' . esc_html( $data['phone'] ?? '' ) . '</p>'
					. ( ! $private && '1' !== kcs_get_option( 'prayer_auto_approve', '0' ) ? '<p>' . esc_html__( 'The person asked for this to be on the public prayer wall. Choose “On prayer wall” to show it.', 'kingdom-church-suite' ) . '</p>' : '' )
					. '<p><a class="btn" href="' . esc_url( KCS_Church_Admin::url( 'prayers' ) ) . '">' . esc_html__( 'View requests', 'kingdom-church-suite' ) . '</a></p>'
			);
		}
		do_action( 'kcs_prayer_created', $id );
		KCS_Email::webhook( 'prayer.created', array( 'id' => $id, 'name' => $name, 'is_private' => $private ) );
		return $id;
	}

	/**
	 * Private link for the person who asked.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function manage_url( $token ) {
		$base = kcs_get_option( 'page_prayer' ) ? kcs_page_url( 'prayer' ) : home_url( '/' );
		return add_query_arg( 'prayer_token', rawurlencode( $token ), $base );
	}

	/**
	 * Email paragraph with the private link.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function manage_block( $token ) {
		return '<p>' . esc_html__( 'Use your private link to see how many people are praying, read their encouragements and tell us when God answers:', 'kingdom-church-suite' ) . '</p><p><a class="btn" href="' . esc_url( self::manage_url( $token ) ) . '">' . esc_html__( 'Follow my prayer request', 'kingdom-church-suite' ) . '</a></p>';
	}

	/**
	 * A request by its private token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function by_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		if ( strlen( $token ) < 20 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'prayer_requests' ) . ' WHERE token = %s LIMIT 1', $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * A request.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'prayer_requests' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Name shown on the wall: first name only, or "Anonymous".
	 *
	 * @param object $r Row.
	 * @return string
	 */
	public static function display_name( $r ) {
		if ( ! empty( $r->anonymous ) || '' === trim( (string) $r->name ) ) {
			return __( 'Anonymous', 'kingdom-church-suite' );
		}
		return (string) strtok( trim( $r->name ), ' ' );
	}

	/**
	 * A row as the wall shows it (no email or phone).
	 *
	 * @param object $r Row.
	 * @return array
	 */
	public static function public_item( $r ) {
		$cats = self::categories();
		$name = self::display_name( $r );
		return array(
			'id'             => (int) $r->id,
			'name'           => $name,
			'initial'        => __( 'Anonymous', 'kingdom-church-suite' ) === $name ? '🙏' : mb_strtoupper( mb_substr( $name, 0, 1 ) ),
			'city'           => (string) $r->city,
			'category'       => (string) $r->category,
			'category_label' => $cats[ $r->category ] ?? '',
			'request'        => (string) $r->request,
			'praise'         => 'answered' === $r->status ? (string) $r->praise : '',
			'answered'       => 'answered' === $r->status,
			'prayers'        => (int) $r->prayer_count,
			'encouragements' => (int) $r->encourage_count,
			/* translators: %s: time */
			'ago'            => sprintf( __( '%s ago', 'kingdom-church-suite' ), human_time_diff( strtotime( $r->created_at ), current_time( 'timestamp' ) ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			'hue'            => (int) ( crc32( $name . $r->id ) % 360 ),
		);
	}

	/**
	 * Public prayer wall items.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function wall( $limit = 20 ) {
		return array_map( array( __CLASS__, 'public_item' ), self::query( array( 'per_page' => $limit ) )['rows'] );
	}

	/**
	 * Search the public wall.
	 *
	 * @param array $args filter (all|praise|most|needs), category, search, page, per_page.
	 * @return array rows, total, pages.
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'filter' => 'all', 'category' => '', 'search' => '', 'page' => 1, 'per_page' => 12 ) );
		$t     = kcs_table( 'prayer_requests' );
		$where = array( "is_private = 0 AND status IN ('approved','answered')" );
		$order = 'id DESC';
		switch ( $args['filter'] ) {
			case 'praise':
				$where[] = "status = 'answered'";
				$order   = 'COALESCE(answered_at, created_at) DESC, id DESC';
				break;
			case 'most':
				$order = 'prayer_count DESC, id DESC';
				break;
			case 'needs':
				$where[] = "status = 'approved'";
				$order   = 'prayer_count ASC, id DESC';
				break;
		}
		if ( $args['category'] && isset( self::categories()[ $args['category'] ] ) ) {
			$where[] = $wpdb->prepare( 'category = %s', $args['category'] );
		}
		if ( '' !== trim( (string) $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( trim( $args['search'] ) ) . '%';
			$where[] = $wpdb->prepare( '(request LIKE %s OR praise LIKE %s OR city LIKE %s)', $like, $like, $like );
		}
		$per   = max( 1, min( 50, (int) $args['per_page'] ) );
		$page  = max( 1, (int) $args['page'] );
		$where = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY $order LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'rows' => $rows, 'total' => $total, 'pages' => (int) ceil( $total / $per ) );
	}

	/**
	 * Wall totals.
	 *
	 * @return array requests, prayers, answered.
	 */
	public static function stats() {
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT COUNT(*) requests, COALESCE(SUM(prayer_count),0) prayers, SUM(status = \'answered\') answered FROM ' . kcs_table( 'prayer_requests' ) . " WHERE is_private = 0 AND status IN ('approved','answered')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'requests' => (int) ( $row->requests ?? 0 ),
			'prayers'  => (int) ( $row->prayers ?? 0 ),
			'answered' => (int) ( $row->answered ?? 0 ),
		);
	}

	/**
	 * Latest counts for requests already on screen, and how many new requests arrived.
	 *
	 * @param int[] $ids   IDs on screen.
	 * @param int   $since Highest ID on screen.
	 * @return array
	 */
	public static function live( $ids, $since ) {
		global $wpdb;
		$t      = kcs_table( 'prayer_requests' );
		$ids    = array_slice( array_filter( array_map( 'absint', (array) $ids ) ), 0, 100 );
		$counts = array();
		if ( $ids ) {
			$in = implode( ',', $ids );
			foreach ( $wpdb->get_results( "SELECT id, prayer_count, encourage_count, status FROM $t WHERE id IN ($in) AND is_private = 0 AND status IN ('approved','answered')" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$counts[ $r->id ] = array( (int) $r->prayer_count, (int) $r->encourage_count, 'answered' === $r->status );
			}
		}
		$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE id > %d AND is_private = 0 AND status IN ('approved','answered')", (int) $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'counts' => (object) $counts, 'new' => $new, 'stats' => self::stats() );
	}

	/**
	 * Increment "I prayed" counter.
	 *
	 * @param int $id ID.
	 * @return int|WP_Error New count.
	 */
	public static function pray( $id ) {
		global $wpdb;
		$table = kcs_table( 'prayer_requests' );
		$done  = $wpdb->query( $wpdb->prepare( "UPDATE $table SET prayer_count = prayer_count + 1, last_prayed_at = %s WHERE id = %d AND is_private = 0 AND status IN ('approved','answered')", current_time( 'mysql' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $done ? self::get( $id ) : null;
		if ( ! $row ) {
			return new WP_Error( 'kcs_not_found', __( 'This prayer request is no longer on the wall.', 'kingdom-church-suite' ), array( 'status' => 404 ) );
		}
		$count = (int) $row->prayer_count;
		// Tell the person at milestones, at most every few hours.
		if ( $row->notify && in_array( $count, array( 1, 3, 5, 10, 25, 50, 100, 250, 500, 1000 ), true ) && ! get_transient( 'kcs_prayed_note_' . $id ) ) {
			set_transient( 'kcs_prayed_note_' . $id, 1, 3 * HOUR_IN_SECONDS );
			KCS_Email::send(
				$row->email,
				1 === $count ? __( 'Someone just prayed for you 🙏', 'kingdom-church-suite' ) : sprintf( /* translators: %d: count */ __( '%d people have prayed for you 🙏', 'kingdom-church-suite' ), $count ),
				'<p>' . esc_html( sprintf( /* translators: %s: name */ __( 'Dear %s,', 'kingdom-church-suite' ), self::display_name( $row ) === __( 'Anonymous', 'kingdom-church-suite' ) ? __( 'friend', 'kingdom-church-suite' ) : self::display_name( $row ) ) ) . '</p><p>'
					. esc_html( 1 === $count ? __( 'Someone from our church family just prayed for your request on the prayer wall. You are not alone.', 'kingdom-church-suite' ) : sprintf( /* translators: %d: count */ __( '%d people from our church family have now prayed for your request. You are not alone.', 'kingdom-church-suite' ), $count ) )
					. '</p><p style="font-style:italic">' . esc_html__( '“Where two or three gather in my name, there am I with them.” — Matthew 18:20', 'kingdom-church-suite' ) . '</p>' . self::manage_block( $row->token )
			);
		}
		return $count;
	}

	/**
	 * Visible encouragements for a request.
	 *
	 * @param int  $id  Request.
	 * @param bool $all Include hidden/pending (for admins).
	 * @return array
	 */
	public static function encouragements( $id, $all = false ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . kcs_table( 'prayer_encouragements' ) . ' WHERE prayer_id = %d' . ( $all ? '' : " AND status = 'visible'" ) . ' ORDER BY id DESC LIMIT 100', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $all ) {
			return $rows;
		}
		return array_map(
			function ( $e ) {
				return array(
					'id'      => (int) $e->id,
					'name'    => $e->name ? (string) strtok( $e->name, ' ' ) : __( 'A friend', 'kingdom-church-suite' ),
					'message' => (string) $e->message,
					/* translators: %s: time */
					'ago'     => sprintf( __( '%s ago', 'kingdom-church-suite' ), human_time_diff( strtotime( $e->created_at ), current_time( 'timestamp' ) ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				);
			},
			$rows
		);
	}

	/**
	 * Add an encouragement.
	 *
	 * @param int   $id   Request.
	 * @param array $data name, message.
	 * @return array|WP_Error
	 */
	public static function encourage( $id, $data ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row || $row->is_private || ! in_array( $row->status, array( 'approved', 'answered' ), true ) ) {
			return new WP_Error( 'kcs_not_found', __( 'This prayer request is no longer on the wall.', 'kingdom-church-suite' ), array( 'status' => 404 ) );
		}
		$message = trim( sanitize_textarea_field( $data['message'] ?? '' ) );
		if ( mb_strlen( $message ) < 2 ) {
			return new WP_Error( 'kcs_prayer', __( 'Please write a short encouragement.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		$message = mb_substr( $message, 0, 500 );
		$review  = '1' === kcs_get_option( 'prayer_review_encouragements', '0' ) || preg_match( '#https?://|www\.|\.(com|net|org|ng)\b#i', $message );
		$wpdb->insert(
			kcs_table( 'prayer_encouragements' ),
			array(
				'prayer_id'  => $id,
				'name'       => mb_substr( sanitize_text_field( $data['name'] ?? '' ), 0, 100 ),
				'message'    => $message,
				'status'     => $review ? 'pending' : 'visible',
				'created_at' => current_time( 'mysql' ),
			)
		);
		if ( $review ) {
			return array( 'pending' => true, 'message' => __( 'Thank you! Your encouragement will show after a quick check.', 'kingdom-church-suite' ) );
		}
		self::recount( $id );
		if ( $row->notify && ! get_transient( 'kcs_enc_note_' . $id ) ) {
			set_transient( 'kcs_enc_note_' . $id, 1, 3 * HOUR_IN_SECONDS );
			KCS_Email::send(
				$row->email,
				__( 'You have a new encouragement 💛', 'kingdom-church-suite' ),
				'<p>' . esc_html__( 'Someone left you a word of encouragement on the prayer wall:', 'kingdom-church-suite' ) . '</p><blockquote style="margin:16px 0;padding:12px 16px;border-left:4px solid #d9ae4f;background:#faf6ee">' . nl2br( esc_html( $message ) ) . '</blockquote>' . self::manage_block( $row->token )
			);
		}
		return array( 'pending' => false, 'item' => self::encouragements( $id )[0] ?? null, 'count' => (int) self::get( $id )->encourage_count );
	}

	/**
	 * Refresh the visible encouragement count.
	 *
	 * @param int $id Request.
	 */
	public static function recount( $id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . kcs_table( 'prayer_requests' ) . ' SET encourage_count = (SELECT COUNT(*) FROM ' . kcs_table( 'prayer_encouragements' ) . " WHERE prayer_id = %d AND status = 'visible') WHERE id = %d", $id, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Show, hide or delete an encouragement (Church Admin).
	 *
	 * @param int    $enc_id Encouragement.
	 * @param string $action visible|hidden|delete.
	 */
	public static function moderate( $enc_id, $action ) {
		global $wpdb;
		$t   = kcs_table( 'prayer_encouragements' );
		$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT prayer_id FROM $t WHERE id = %d", $enc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $pid ) {
			return;
		}
		if ( 'delete' === $action ) {
			$wpdb->delete( $t, array( 'id' => $enc_id ) );
		} elseif ( in_array( $action, array( 'visible', 'hidden' ), true ) ) {
			$wpdb->update( $t, array( 'status' => $action ), array( 'id' => $enc_id ) );
		}
		self::recount( $pid );
	}

	/**
	 * The person who asked updates their request.
	 *
	 * @param object $row    Request.
	 * @param string $action answered|remove|praise.
	 * @param string $praise Praise report.
	 * @return string Message.
	 */
	public static function owner_update( $row, $action, $praise = '' ) {
		global $wpdb;
		$t = kcs_table( 'prayer_requests' );
		if ( 'remove' === $action ) {
			$wpdb->update( $t, array( 'status' => 'archived' ), array( 'id' => $row->id ) );
			return __( 'Your request has been taken off the prayer wall. Our prayer team will keep praying.', 'kingdom-church-suite' );
		}
		$data = array( 'praise' => mb_substr( sanitize_textarea_field( $praise ), 0, 2000 ) );
		if ( 'answered' === $action ) {
			$data['status']      = in_array( $row->status, array( 'approved', 'answered' ), true ) ? 'answered' : $row->status;
			$data['answered_at'] = current_time( 'mysql' );
		}
		$wpdb->update( $t, $data, array( 'id' => $row->id ) );
		if ( 'answered' === $action && '1' === kcs_get_option( 'notify_admin_prayer', '1' ) ) {
			KCS_Email::send( KCS_Email::admin_recipients(), __( 'A prayer was answered 🙌', 'kingdom-church-suite' ), '<p><strong>' . esc_html__( 'Request', 'kingdom-church-suite' ) . ':</strong><br>' . nl2br( esc_html( $row->request ) ) . '</p>' . ( $data['praise'] ? '<p><strong>' . esc_html__( 'Praise report', 'kingdom-church-suite' ) . ':</strong><br>' . nl2br( esc_html( $data['praise'] ) ) . '</p>' : '' ) );
		}
		return 'answered' === $action ? __( 'Praise God! Your answered prayer now encourages everyone on the wall. 🙌', 'kingdom-church-suite' ) : __( 'Saved.', 'kingdom-church-suite' );
	}
}
