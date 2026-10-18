<?php
/**
 * Sending email through an SMTP mailbox (Hostinger email, Gmail, Zoho, Brevo, Mailgun…), so
 * receipts, reminders and newsletters reach inboxes instead of spam.
 *
 * Order: the church's own mailbox (Settings → Email), else the platform's mailbox (Ministry OS
 * Cloud, priority 5 on the same hook), else the server's default mail.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * SMTP mailer.
 */
class KCS_Mailer {

	/**
	 * Last sending error (for the "Send test email" message).
	 *
	 * @var string
	 */
	public static $last_error = '';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure' ), 10 );
		add_action(
			'wp_mail_failed',
			function ( $error ) {
				self::$last_error = is_wp_error( $error ) ? $error->get_error_message() : '';
			}
		);
	}

	/**
	 * The church's own mailbox, if switched on and complete.
	 *
	 * @return array|null host, port, secure, user, pass, from.
	 */
	public static function church_config() {
		if ( '1' !== (string) kcs_get_option( 'smtp_enabled', '0' ) || '' === trim( (string) kcs_get_option( 'smtp_host' ) ) ) {
			return null;
		}
		return array(
			'host'   => trim( (string) kcs_get_option( 'smtp_host' ) ),
			'port'   => (int) kcs_get_option( 'smtp_port', 465 ),
			'secure' => (string) kcs_get_option( 'smtp_secure', 'ssl' ),
			'user'   => trim( (string) kcs_get_option( 'smtp_user' ) ),
			'pass'   => (string) kcs_get_option( 'smtp_pass' ),
			'from'   => '',
		);
	}

	/**
	 * Point PHPMailer at the church's mailbox.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $mail Mailer.
	 */
	public static function configure( $mail ) {
		$cfg = self::church_config();
		if ( $cfg ) {
			self::apply( $mail, $cfg );
		}
	}

	/**
	 * Use an SMTP mailbox. When the From address is not the mailbox's own, send from the mailbox
	 * (keeping the church's name) and put the original address in Reply-To: mail providers reject
	 * or spam-folder messages "from" addresses the mailbox may not send for.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $mail Mailer.
	 * @param array                         $cfg  host, port, secure (ssl|tls|none), user, pass, from.
	 */
	public static function apply( $mail, $cfg ) {
		$mail->isSMTP();
		$mail->Host       = $cfg['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Port       = $cfg['port'] ? (int) $cfg['port'] : ( 'ssl' === $cfg['secure'] ? 465 : 587 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->SMTPSecure = in_array( $cfg['secure'], array( 'ssl', 'tls' ), true ) ? $cfg['secure'] : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->SMTPAutoTLS = 'none' !== $cfg['secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->SMTPAuth   = '' !== $cfg['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Username   = $cfg['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Password   = $cfg['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Timeout    = 15; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$sender = is_email( $cfg['from'] ) ? $cfg['from'] : ( is_email( $cfg['user'] ) ? $cfg['user'] : '' );
		$from   = (string) $mail->From; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( $sender && strtolower( $from ) !== strtolower( $sender ) ) {
			$name = (string) $mail->FromName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( is_email( $from ) && ! $mail->getReplyToAddresses() ) {
				$mail->addReplyTo( $from, $name );
			}
			$mail->setFrom( $sender, $name, false );
		}
		if ( $sender ) {
			$mail->Sender = $sender; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}
}
