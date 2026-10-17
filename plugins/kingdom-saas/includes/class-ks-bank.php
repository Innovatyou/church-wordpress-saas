<?php
/**
 * Subscription payments by bank transfer.
 *
 * The platform owner lists their bank accounts in Platform → Settings. A church that picks
 * "Bank transfer" sees the account for its currency and the invoice reference, then presses
 * "I have made this transfer" (optionally with a receipt). The invoice waits as "Awaiting
 * confirmation" until a super admin confirms it in Platform → Payments, which starts the plan.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bank transfers for subscriptions.
 */
class KS_Bank {

	/**
	 * Most accounts the platform can list.
	 */
	const MAX_ACCOUNTS = 3;

	/**
	 * Largest receipt file (bytes).
	 */
	const MAX_PROOF = 5242880;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_ks_invoice_proof', array( __CLASS__, 'download_proof' ) );
	}

	/**
	 * The platform's bank accounts.
	 *
	 * @return array[] Each: bank, name, number, currency, sort_code, iban, swift.
	 */
	public static function accounts() {
		$list = ks_get( 'bank_accounts' );
		return is_array( $list ) ? array_values( array_filter( $list, function ( $a ) {
			return is_array( $a ) && ! empty( $a['number'] );
		} ) ) : array();
	}

	/**
	 * Clean account rows from the settings form.
	 *
	 * @param array $rows Raw rows.
	 * @return array[]
	 */
	public static function sanitize_accounts( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$a = array();
			foreach ( array( 'bank', 'name', 'number', 'sort_code', 'iban', 'swift' ) as $k ) {
				$a[ $k ] = substr( sanitize_text_field( (string) ( $row[ $k ] ?? '' ) ), 0, 100 );
			}
			$cur           = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $row['currency'] ?? '' ) ) );
			$a['currency'] = 3 === strlen( $cur ) ? $cur : '';
			if ( '' === $a['number'] ) {
				$a['number'] = $a['iban'];
			}
			if ( '' !== $a['number'] ) {
				$out[] = $a;
			}
			if ( count( $out ) >= self::MAX_ACCOUNTS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Accounts that accept a currency (no currency = any).
	 *
	 * @param string $currency Currency.
	 * @return array[]
	 */
	public static function accounts_for( $currency ) {
		return array_values( array_filter( self::accounts(), function ( $a ) use ( $currency ) {
			return '' === $a['currency'] || strtoupper( $currency ) === $a['currency'];
		} ) );
	}

	/**
	 * Can churches pay by bank transfer in this currency?
	 *
	 * @param string $currency Currency ('' = any).
	 * @return bool
	 */
	public static function available( $currency = '' ) {
		if ( ! self::accounts() ) {
			return '' !== trim( (string) ks_get( 'bank_details' ) );
		}
		return '' === $currency || (bool) self::accounts_for( $currency );
	}

	/**
	 * Decoded invoice meta.
	 *
	 * @param object $inv Invoice.
	 * @return array
	 */
	public static function meta( $inv ) {
		$meta = isset( $inv->meta ) ? json_decode( (string) $inv->meta, true ) : null;
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Transfers waiting for the platform owner.
	 *
	 * @return int
	 */
	public static function count_waiting() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ks_table( 'invoices' ) . " WHERE status = 'review'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The church's newest bank invoice that is still open.
	 *
	 * @param int $blog_id Site.
	 * @return object|null
	 */
	public static function open_invoice( $blog_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'invoices' ) . " WHERE blog_id = %d AND gateway = 'bank' AND status IN ('pending','review') ORDER BY id DESC LIMIT 1", $blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * The church says it has paid.
	 *
	 * @param object $sub  Subscription.
	 * @param object $inv  Invoice.
	 * @param array  $d    Form data (unslashed).
	 * @param array  $file Receipt from $_FILES, if any.
	 * @return true|WP_Error
	 */
	public static function notice( $sub, $inv, $d, $file ) {
		if ( ! $inv || (int) $inv->blog_id !== (int) $sub->blog_id || 'bank' !== $inv->gateway ) {
			return new WP_Error( 'ks_invoice', __( 'We could not find that invoice.', 'kingdom-saas' ) );
		}
		if ( 'pending' !== $inv->status ) {
			return new WP_Error( 'ks_state', __( 'We already have your notice for this payment.', 'kingdom-saas' ) );
		}
		$payer = substr( sanitize_text_field( (string) ( $d['payer'] ?? '' ) ), 0, 120 );
		if ( '' === $payer ) {
			return new WP_Error( 'ks_payer', __( 'Please enter the name the transfer was sent from.', 'kingdom-saas' ) );
		}
		$date = sanitize_text_field( (string) ( $d['paid_on'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}
		$proof = '';
		if ( $file && ! empty( $file['name'] ) && UPLOAD_ERR_NO_FILE !== (int) $file['error'] ) {
			$proof = self::store_proof( $file );
			if ( is_wp_error( $proof ) ) {
				return new WP_Error( 'ks_proof', __( 'The receipt must be a JPG, PNG or PDF file under 5 MB.', 'kingdom-saas' ) );
			}
		}
		$meta             = self::meta( $inv );
		$meta['transfer'] = array(
			'payer'   => $payer,
			'bank'    => substr( sanitize_text_field( (string) ( $d['payer_bank'] ?? '' ) ), 0, 120 ),
			'date'    => $date,
			'note'    => substr( sanitize_textarea_field( (string) ( $d['transfer_note'] ?? '' ) ), 0, 1000 ),
			'proof'   => $proof,
			'by'      => get_current_user_id(),
			'sent_at' => current_time( 'mysql', true ),
		);
		global $wpdb;
		$ok = $wpdb->query( $wpdb->prepare( 'UPDATE ' . ks_table( 'invoices' ) . " SET status = 'review', meta = %s WHERE id = %d AND status = 'pending'", wp_json_encode( $meta ), $inv->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $ok ) {
			return new WP_Error( 'ks_state', __( 'We already have your notice for this payment.', 'kingdom-saas' ) );
		}
		$money = ks_money( $inv->amount, $inv->currency );
		$plan  = KS_Plans::get( $inv->plan );
		$rows  = array(
			__( 'Church', 'kingdom-saas' )    => $sub->church_name,
			__( 'Plan', 'kingdom-saas' )      => ( $plan ? $plan['name'] : $inv->plan ) . ' · ' . ( 'year' === $inv->billing_interval ? __( 'yearly', 'kingdom-saas' ) : __( 'monthly', 'kingdom-saas' ) ),
			__( 'Reference', 'kingdom-saas' ) => $inv->reference,
			__( 'Sent by', 'kingdom-saas' )   => $payer . ( $meta['transfer']['bank'] ? ' · ' . $meta['transfer']['bank'] : '' ),
			__( 'Date sent', 'kingdom-saas' ) => date_i18n( get_option( 'date_format' ), strtotime( $date ) ),
			__( 'Receipt', 'kingdom-saas' )   => $proof ? __( 'Attached (open it in Platform → Payments)', 'kingdom-saas' ) : __( 'None', 'kingdom-saas' ),
		);
		$table = '<table style="border-collapse:collapse;width:100%">';
		foreach ( $rows as $k => $v ) {
			$table .= '<tr><td style="padding:6px 0;color:#7a7385">' . esc_html( $k ) . '</td><td style="padding:6px 0"><strong>' . esc_html( $v ) . '</strong></td></tr>';
		}
		$table .= '</table>';
		KS_Email::send(
			ks_get( 'support_email' ),
			/* translators: 1: amount, 2: church */
			sprintf( __( 'Please confirm a bank transfer: %1$s from %2$s', 'kingdom-saas' ), $money, $sub->church_name ),
			'<p>' . esc_html__( 'A church says it has paid its subscription by bank transfer. Check your account, then confirm or reject it.', 'kingdom-saas' ) . '</p>' . $table,
			array( __( 'Review payments', 'kingdom-saas' ), add_query_arg( 'tab', 'invoices', ks_platform_url( 'platform/' ) ) )
		);
		KS_Email::owner(
			$sub,
			/* translators: %s: reference */
			sprintf( __( 'We are checking your transfer %s', 'kingdom-saas' ), $inv->reference ),
			/* translators: %s: amount */
			'<p>' . esc_html( sprintf( __( 'Thank you! You told us you sent %s. Your plan starts as soon as we confirm it — we will email you.', 'kingdom-saas' ), $money ) ) . '</p>'
		);
		do_action( 'ks_bank_notice', $inv, $sub );
		return true;
	}

	/**
	 * Reject a transfer that never arrived.
	 *
	 * @param object $inv    Invoice.
	 * @param string $reason Reason emailed to the church.
	 * @return bool
	 */
	public static function reject( $inv, $reason ) {
		global $wpdb;
		$reason           = substr( sanitize_textarea_field( (string) $reason ), 0, 500 );
		$reason           = '' !== $reason ? $reason : __( 'We could not find this transfer in our account.', 'kingdom-saas' );
		$meta             = self::meta( $inv );
		$meta['rejected'] = array(
			'reason' => $reason,
			'at'     => current_time( 'mysql', true ),
		);
		$ok = $wpdb->query( $wpdb->prepare( 'UPDATE ' . ks_table( 'invoices' ) . " SET status = 'failed', meta = %s WHERE id = %d AND status IN ('pending','review')", wp_json_encode( $meta ), $inv->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $ok ) {
			return false;
		}
		$sub = KS_Tenants::get( $inv->blog_id );
		if ( $sub ) {
			KS_Email::owner(
				$sub,
				/* translators: %s: reference */
				sprintf( __( 'We could not confirm your transfer %s', 'kingdom-saas' ), $inv->reference ),
				/* translators: %s: amount */
				'<p>' . esc_html( sprintf( __( 'We could not confirm your bank transfer of %s.', 'kingdom-saas' ), ks_money( $inv->amount, $inv->currency ) ) ) . '</p><p><strong>' . esc_html__( 'Reason:', 'kingdom-saas' ) . '</strong> ' . esc_html( $reason ) . '</p><p>' . esc_html__( 'If you have already paid, reply to this email with your transfer receipt. You can also start a new payment from your billing page.', 'kingdom-saas' ) . '</p>',
				array( __( 'Open billing', 'kingdom-saas' ), KS_Tenants::admin_url( $inv->blog_id, 'subscription' ) )
			);
		}
		do_action( 'ks_bank_rejected', $inv, $reason );
		return true;
	}

	/**
	 * Save a receipt privately under a random name.
	 *
	 * @param array $file Entry from $_FILES.
	 * @return string|WP_Error Stored file name.
	 */
	private static function store_proof( $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > self::MAX_PROOF || (int) $file['size'] <= 0 ) {
			return new WP_Error( 'ks_proof', 'upload' );
		}
		$check = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'pdf'          => 'application/pdf',
			)
		);
		$dir   = self::proof_dir();
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! $dir ) {
			return new WP_Error( 'ks_proof', 'type' );
		}
		$name = strtolower( wp_generate_password( 32, false, false ) ) . '.' . $check['ext'];
		if ( ! move_uploaded_file( $file['tmp_name'], $dir . $name ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return new WP_Error( 'ks_proof', 'move' );
		}
		@chmod( $dir . $name, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		return $name;
	}

	/**
	 * Private folder for receipts in the main site's uploads (blocked from the web).
	 *
	 * @return string|false
	 */
	public static function proof_dir() {
		$switched = is_multisite() && get_current_blog_id() !== get_main_site_id() && switch_to_blog( get_main_site_id() );
		$up       = wp_upload_dir( null, false );
		if ( $switched ) {
			restore_current_blog();
		}
		$root = trailingslashit( $up['basedir'] ) . 'ks-private/';
		$dir  = $root . 'receipts/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! file_exists( $root . '.htaccess' ) ) {
			file_put_contents( $root . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		foreach ( array( $root, $dir ) as $d ) {
			if ( ! file_exists( $d . 'index.php' ) ) {
				file_put_contents( $d . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return $dir;
	}

	/**
	 * Link for the platform owner to open a receipt.
	 *
	 * @param object $inv Invoice.
	 * @return string
	 */
	public static function proof_url( $inv ) {
		if ( empty( self::meta( $inv )['transfer']['proof'] ) ) {
			return '';
		}
		return wp_nonce_url( get_admin_url( get_main_site_id(), 'admin-post.php?action=ks_invoice_proof&id=' . (int) $inv->id ), 'ks_invoice_proof_' . (int) $inv->id );
	}

	/**
	 * Stream a receipt to a super admin.
	 */
	public static function download_proof() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_super_admin() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'ks_invoice_proof_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-saas' ), 403 );
		}
		global $wpdb;
		$inv  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ks_table( 'invoices' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$name = $inv ? basename( (string) ( self::meta( $inv )['transfer']['proof'] ?? '' ) ) : '';
		$dir  = self::proof_dir();
		if ( ! $name || ! $dir || ! preg_match( '/^[a-z0-9]{32}\.(jpe?g|jpe|png|pdf)$/', $name ) || ! is_file( $dir . $name ) ) {
			wp_die( esc_html__( 'File not found.', 'kingdom-saas' ), 404 );
		}
		$ext = pathinfo( $name, PATHINFO_EXTENSION );
		nocache_headers();
		header( 'Content-Type: ' . ( 'pdf' === $ext ? 'application/pdf' : ( 'png' === $ext ? 'image/png' : 'image/jpeg' ) ) );
		header( 'Content-Length: ' . filesize( $dir . $name ) );
		header( 'Content-Disposition: inline; filename="receipt-' . sanitize_file_name( $inv->reference ) . '.' . $ext . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $dir . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
