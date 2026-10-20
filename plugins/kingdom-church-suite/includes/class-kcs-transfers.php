<?php
/**
 * Bank transfers: the giver's "I have made this transfer" notice, private proof files,
 * and the church's confirm / reject actions.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bank transfers.
 */
class KCS_Transfers {

	/**
	 * Largest proof file (bytes).
	 */
	const MAX_PROOF = 5242880;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_kcs_transfer_notice', array( __CLASS__, 'handle_notice' ) );
		add_action( 'admin_post_kcs_transfer_notice', array( __CLASS__, 'handle_notice' ) );
		add_action( 'admin_post_kcs_transfer_proof', array( __CLASS__, 'download_proof' ) );
	}

	/**
	 * Methods the church confirms by hand (the website cannot see these payments).
	 */
	const MANUAL = array( 'bank_transfer', 'payment_link', 'payment_link_2', 'payment_link_3' );

	/**
	 * Is this a payment the church confirms by hand?
	 *
	 * @param object|string $payment Payment or gateway ID.
	 * @return bool
	 */
	public static function is_manual( $payment ) {
		return in_array( is_object( $payment ) ? $payment->gateway : (string) $payment, self::MANUAL, true );
	}

	/**
	 * The bank transfer gateway.
	 *
	 * @return KCS_Gateway_Bank_Transfer|null
	 */
	public static function gateway() {
		$g = KCS_Payments::gateway( 'bank_transfer' );
		return $g instanceof KCS_Gateway_Bank_Transfer ? $g : null;
	}

	/**
	 * Can the current user confirm or reject manual payments?
	 *
	 * @return bool
	 */
	public static function can_confirm() {
		$g = self::gateway();
		return $g ? $g->can_confirm() : current_user_can( 'manage_options' );
	}

	/**
	 * Decoded payment meta.
	 *
	 * @param object $payment Payment.
	 * @return array
	 */
	public static function meta( $payment ) {
		$meta = isset( $payment->meta ) ? json_decode( (string) $payment->meta, true ) : null;
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Transfers waiting for the church (the giver said they paid).
	 *
	 * @return int
	 */
	public static function count_waiting() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . kcs_table( 'payments' ) . " WHERE gateway IN ('" . implode( "','", self::MANUAL ) . "') AND status = 'review'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Nonce action for a payment's notice form.
	 *
	 * @param string $reference Reference.
	 * @return string
	 */
	public static function nonce_action( $reference ) {
		return 'kcs_transfer_' . $reference;
	}

	/**
	 * Where the giver goes back to.
	 *
	 * @param string $reference Reference.
	 * @param string $result    sent|missing|file|state.
	 * @return string
	 */
	private static function back_url( $reference, $result ) {
		return add_query_arg(
			array(
				'reference'   => rawurlencode( $reference ),
				'kcs_offline' => 1,
				'kcs_tn'      => $result,
			),
			kcs_page_url( 'thank_you' )
		);
	}

	/**
	 * POST from the payment page: the giver says the transfer is done.
	 */
	public static function handle_notice() {
		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$payment   = $reference ? KCS_Payments::get_by_reference( $reference ) : null;
		if ( ! $payment || ! self::is_manual( $payment ) ) {
			wp_die( esc_html__( 'We could not find that payment reference.', 'kingdom-church-suite' ), 404 );
		}
		if ( ! isset( $_POST['_kcs_tn'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_kcs_tn'] ), self::nonce_action( $payment->reference ) ) ) {
			wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'kingdom-church-suite' ), 403 );
		}
		if ( ! kcs_rate_limit( 'transfer_notice', 10, 600 ) ) {
			wp_die( esc_html__( 'Too many attempts. Please wait a few minutes.', 'kingdom-church-suite' ), 429 );
		}
		if ( 'pending' !== $payment->status ) {
			wp_safe_redirect( self::back_url( $payment->reference, 'state' ) );
			exit;
		}
		$d     = wp_unslash( $_POST );
		$payer = substr( sanitize_text_field( (string) ( $d['payer'] ?? '' ) ), 0, 120 );
		if ( '' === $payer ) {
			wp_safe_redirect( self::back_url( $payment->reference, 'missing' ) );
			exit;
		}
		$date = sanitize_text_field( (string) ( $d['paid_on'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = current_time( 'Y-m-d' );
		}

		$proof = '';
		if ( ! empty( $_FILES['proof']['name'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['proof']['error'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$proof = self::store_proof( $_FILES['proof'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_wp_error( $proof ) ) {
				wp_safe_redirect( self::back_url( $payment->reference, 'file' ) );
				exit;
			}
		}

		$meta             = self::meta( $payment );
		$meta['transfer'] = array(
			'payer'   => $payer,
			'bank'    => substr( sanitize_text_field( (string) ( $d['payer_bank'] ?? '' ) ), 0, 120 ),
			'date'    => $date,
			'note'    => substr( sanitize_textarea_field( (string) ( $d['transfer_note'] ?? '' ) ), 0, 1000 ),
			'proof'   => $proof,
			'sent_at' => current_time( 'mysql' ),
		);
		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . kcs_table( 'payments' ) . " SET status = 'review', meta = %s WHERE id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				wp_json_encode( $meta ),
				$payment->id
			)
		);
		if ( ! $updated ) {
			wp_safe_redirect( self::back_url( $payment->reference, 'state' ) );
			exit;
		}
		$payment = KCS_Payments::get( $payment->id );
		$vars    = self::email_vars( $payment );
		KCS_Email::send_template( KCS_Email::admin_recipients(), 'admin_transfer_notice', $vars );
		if ( $payment->email ) {
			KCS_Email::send_template( $payment->email, 'transfer_received', $vars );
		}
		do_action( 'kcs_transfer_notice', $payment );
		wp_safe_redirect( self::back_url( $payment->reference, 'sent' ) );
		exit;
	}

	/**
	 * Save a proof file privately under a random name.
	 *
	 * @param array $file Entry from $_FILES.
	 * @return string|WP_Error Stored file name.
	 */
	private static function store_proof( $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > self::MAX_PROOF || (int) $file['size'] <= 0 ) {
			return new WP_Error( 'kcs_proof', 'upload' );
		}
		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'pdf'          => 'application/pdf',
		);
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'kcs_proof', 'type' );
		}
		$dir = self::proof_dir();
		if ( ! $dir ) {
			return new WP_Error( 'kcs_proof', 'dir' );
		}
		$name = strtolower( wp_generate_password( 32, false, false ) ) . '.' . $check['ext'];
		if ( ! move_uploaded_file( $file['tmp_name'], $dir . $name ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return new WP_Error( 'kcs_proof', 'move' );
		}
		@chmod( $dir . $name, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		return $name;
	}

	/**
	 * Private folder for proof files (blocked from the web).
	 *
	 * @return string|false Path with trailing slash.
	 */
	public static function proof_dir() {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'kcs-private/transfers/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$root = dirname( $dir ) . '/';
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
	 * Link for church staff to view a proof file.
	 *
	 * @param object $payment Payment.
	 * @return string Empty when there is no proof.
	 */
	public static function proof_url( $payment ) {
		$meta = self::meta( $payment );
		if ( empty( $meta['transfer']['proof'] ) ) {
			return '';
		}
		return wp_nonce_url( admin_url( 'admin-post.php?action=kcs_transfer_proof&id=' . (int) $payment->id ), 'kcs_transfer_proof_' . (int) $payment->id );
	}

	/**
	 * Show a proof file to someone allowed to confirm transfers.
	 */
	public static function download_proof() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! self::can_confirm() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'kcs_transfer_proof_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'kingdom-church-suite' ), 403 );
		}
		$payment = KCS_Payments::get( $id );
		$meta    = $payment ? self::meta( $payment ) : array();
		$name    = isset( $meta['transfer']['proof'] ) ? basename( (string) $meta['transfer']['proof'] ) : '';
		$dir     = self::proof_dir();
		if ( ! $name || ! $dir || ! preg_match( '/^[a-z0-9]{32}\.(jpe?g|jpe|png|pdf)$/', $name ) || ! is_file( $dir . $name ) ) {
			wp_die( esc_html__( 'File not found.', 'kingdom-church-suite' ), 404 );
		}
		$ext  = pathinfo( $name, PATHINFO_EXTENSION );
		$type = 'pdf' === $ext ? 'application/pdf' : ( 'png' === $ext ? 'image/png' : 'image/jpeg' );
		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Length: ' . filesize( $dir . $name ) );
		header( 'Content-Disposition: inline; filename="proof-' . sanitize_file_name( $payment->reference ) . '.' . $ext . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $dir . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Confirm a transfer: the money is in the account.
	 *
	 * @param object $payment Payment.
	 * @return string|WP_Error Resulting status.
	 */
	public static function confirm( $payment ) {
		if ( ! self::can_confirm() ) {
			return new WP_Error( 'kcs_forbidden', __( 'Only church administrators can confirm payments.', 'kingdom-church-suite' ) );
		}
		global $wpdb;
		$meta              = self::meta( $payment );
		$meta['confirmed'] = array(
			'by' => get_current_user_id(),
			'at' => current_time( 'mysql' ),
		);
		$wpdb->update( kcs_table( 'payments' ), array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $payment->id ) );
		return KCS_Payments::complete( KCS_Payments::get( $payment->id ), $payment->gateway_txn_id, (float) $payment->amount, $payment->currency, true );
	}

	/**
	 * Reject a transfer that never arrived.
	 *
	 * @param object $payment Payment.
	 * @param string $reason  Reason shown to the giver.
	 * @return true|WP_Error
	 */
	public static function reject( $payment, $reason ) {
		if ( ! self::can_confirm() ) {
			return new WP_Error( 'kcs_forbidden', __( 'Only church administrators can reject payments.', 'kingdom-church-suite' ) );
		}
		global $wpdb;
		$reason           = substr( sanitize_textarea_field( (string) $reason ), 0, 500 );
		$reason           = '' !== $reason ? $reason : __( 'We could not find this transfer in our account.', 'kingdom-church-suite' );
		$meta             = self::meta( $payment );
		$meta['rejected'] = array(
			'reason' => $reason,
			'by'     => get_current_user_id(),
			'at'     => current_time( 'mysql' ),
		);
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . kcs_table( 'payments' ) . " SET status = 'failed', meta = %s WHERE id = %d AND status IN ('pending','review')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				wp_json_encode( $meta ),
				$payment->id
			)
		);
		if ( ! $updated ) {
			return new WP_Error( 'kcs_state', __( 'This payment was already handled.', 'kingdom-church-suite' ) );
		}
		$payment = KCS_Payments::get( $payment->id );
		if ( $payment->email ) {
			KCS_Email::send_template( $payment->email, 'transfer_rejected', array_merge( self::email_vars( $payment ), array( 'reason' => $reason ) ) );
		}
		do_action( 'kcs_transfer_rejected', $payment, $reason );
		return true;
	}

	/**
	 * Email variables.
	 *
	 * @param object $payment Payment.
	 * @return array
	 */
	public static function email_vars( $payment ) {
		$t = self::meta( $payment )['transfer'] ?? array();
		return array_merge(
			KCS_Payments::email_vars( $payment ),
			array(
				'payer'         => $t['payer'] ?? '',
				'payer_bank'    => $t['bank'] ?? '',
				'transfer_date' => ! empty( $t['date'] ) ? mysql2date( get_option( 'date_format' ), $t['date'] ) : '',
				'review_url'    => add_query_arg( 'view', 'transfers', kcs_page_url( 'church_admin' ) ),
				'give_url'      => 'partnership' === $payment->purpose && $payment->partner_id ? kcs_page_url( 'partner_dashboard' ) : kcs_page_url( 'give' ),
			)
		);
	}
}
