<?php
/**
 * Platform emails to church owners.
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email.
 */
class KS_Email {

	/**
	 * Last sending error (for the platform's test email).
	 *
	 * @var string
	 */
	public static $last_error = '';

	/**
	 * Hooks: every site on the network sends through the platform mailbox (Settings → Email
	 * sending) unless the church set up its own in Church Admin.
	 */
	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ), 5 );
		add_action(
			'wp_mail_failed',
			function ( $error ) {
				self::$last_error = is_wp_error( $error ) ? $error->get_error_message() : '';
			}
		);
	}

	/**
	 * Use the platform's SMTP mailbox.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $mail Mailer.
	 */
	public static function smtp( $mail ) {
		$host = trim( (string) ks_get( 'smtp_host' ) );
		if ( '' === $host || ( class_exists( 'KCS_Mailer' ) && KCS_Mailer::church_config() ) ) {
			return;
		}
		$cfg = array(
			'host'   => $host,
			'port'   => (int) ks_get( 'smtp_port' ),
			'secure' => (string) ks_get( 'smtp_secure' ),
			'user'   => trim( (string) ks_get( 'smtp_user' ) ),
			'pass'   => (string) ks_get( 'smtp_pass' ),
			'from'   => trim( (string) ks_get( 'smtp_from' ) ),
		);
		if ( class_exists( 'KCS_Mailer' ) ) {
			KCS_Mailer::apply( $mail, $cfg );
			return;
		}
		// Main site without Church Suite: the same settings, applied here.
		$mail->isSMTP();
		$mail->Host        = $cfg['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Port        = $cfg['port'] ? $cfg['port'] : ( 'ssl' === $cfg['secure'] ? 465 : 587 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->SMTPSecure  = in_array( $cfg['secure'], array( 'ssl', 'tls' ), true ) ? $cfg['secure'] : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->SMTPAutoTLS = 'none' !== $cfg['secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->SMTPAuth    = '' !== $cfg['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Username    = $cfg['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Password    = $cfg['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mail->Timeout     = 15; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$sender = is_email( $cfg['from'] ) ? $cfg['from'] : ( is_email( $cfg['user'] ) ? $cfg['user'] : '' );
		if ( $sender && strtolower( (string) $mail->From ) !== strtolower( $sender ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( is_email( $mail->From ) && ! $mail->getReplyToAddresses() ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$mail->addReplyTo( $mail->From, $mail->FromName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
			$mail->setFrom( $sender, $mail->FromName, false ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		if ( $sender ) {
			$mail->Sender = $sender; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}

	/**
	 * Send a branded email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    HTML body (trusted, already escaped).
	 * @param array  $button  Optional array( label, url ).
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $button = array() ) {
		$name  = ks_get( 'platform_name' );
		$color = sanitize_hex_color( ks_get( 'brand_color' ) ) ?: '#5b2a86';
		$btn   = $button ? '<p style="margin:24px 0"><a href="' . esc_url( $button[1] ) . '" style="background:' . $color . ';color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:700;display:inline-block">' . esc_html( $button[0] ) . '</a></p>' : '';
		$html  = '<div style="background:#f5f3f8;padding:24px;font-family:Arial,Helvetica,sans-serif"><div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;color:#1f1a26;line-height:1.55">'
			. '<p style="font-weight:800;font-size:18px;color:' . $color . ';margin:0 0 16px">' . esc_html( $name ) . '</p>' . $body . $btn
			. '<p style="color:#7a7385;font-size:12px;margin-top:28px">' . esc_html( $name ) . ' · ' . esc_html( ks_get( 'support_email' ) ) . '</p></div></div>';
		return wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $name . ' <' . ks_get( 'support_email' ) . '>' ) );
	}

	/**
	 * Email the owner of a church site.
	 *
	 * @param object $sub     Subscription.
	 * @param string $subject Subject.
	 * @param string $body    HTML body.
	 * @param array  $button  Button.
	 * @return bool
	 */
	public static function owner( $sub, $subject, $body, $button = array() ) {
		return self::send( $sub->owner_email, $subject, '<p>' . esc_html( sprintf( /* translators: %s: church */ __( 'Hello %s team,', 'kingdom-saas' ), $sub->church_name ) ) . '</p>' . $body, $button );
	}
}
