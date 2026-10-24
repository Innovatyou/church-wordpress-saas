<?php
/**
 * Contact form ([kcs_contact_form]): messages are saved (Church Admin → Contact messages) and
 * emailed to the addresses the church chose, with Reply-To set to the sender.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contact messages.
 */
class KCS_Contact {

	const TYPE = 'kcs_message';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_shortcode( 'kcs_contact_form', array( __CLASS__, 'form' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Private post type for the messages.
	 */
	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'label'           => __( 'Contact messages', 'kingdom-church-suite' ),
				'public'          => false,
				'show_ui'         => false,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * POST /kcs/v1/contact.
	 */
	public static function routes() {
		register_rest_route(
			'kcs/v1',
			'/contact',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Who receives contact messages: the addresses set in Church Admin, else the church's
	 * notification email.
	 *
	 * @return array
	 */
	public static function recipients() {
		/**
		 * Contact form recipients.
		 *
		 * @param array $emails Emails.
		 */
		$list = array_filter( array_map( 'sanitize_email', (array) apply_filters( 'kcs_contact_recipients', KCS_Email::admin_recipients() ) ) );
		return $list ? array_values( $list ) : KCS_Email::admin_recipients();
	}

	/**
	 * The form.
	 *
	 * @return string
	 */
	public static function form() {
		ob_start();
		?>
		<form class="kcs-form kcs-contact-form" data-kcs-contact>
			<div class="kcs-grid-2">
				<label for="kcs-c-name"><?php esc_html_e( 'Your name', 'kingdom-church-suite' ); ?><input id="kcs-c-name" name="name" required autocomplete="name"></label>
				<label for="kcs-c-email"><?php esc_html_e( 'Email', 'kingdom-church-suite' ); ?><input id="kcs-c-email" name="email" type="email" required autocomplete="email"></label>
				<label for="kcs-c-phone"><?php esc_html_e( 'Phone / WhatsApp (optional)', 'kingdom-church-suite' ); ?><input id="kcs-c-phone" name="phone" type="tel" autocomplete="tel"></label>
				<label for="kcs-c-subject"><?php esc_html_e( 'Subject', 'kingdom-church-suite' ); ?><input id="kcs-c-subject" name="subject"></label>
			</div>
			<label for="kcs-c-message"><?php esc_html_e( 'Message', 'kingdom-church-suite' ); ?><textarea id="kcs-c-message" name="message" rows="5" required></textarea></label>
			<input type="text" name="website" class="kcs-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<button type="submit" class="kcs-btn"><?php esc_html_e( 'Send message', 'kingdom-church-suite' ); ?></button>
			<div class="kcs-form-msg" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * REST handler.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest( $req ) {
		if ( ! empty( $req['website'] ) ) {
			return rest_ensure_response( array( 'id' => 0 ) );
		}
		if ( ! kcs_rate_limit( 'contact', 5, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'kcs_rate', __( 'Too many messages. Please try again later.', 'kingdom-church-suite' ), array( 'status' => 429 ) );
		}
		$id = self::submit( $req->get_params() );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return rest_ensure_response( array( 'id' => $id, 'message' => __( 'Thank you! Your message has been sent. We will get back to you soon.', 'kingdom-church-suite' ) ) );
	}

	/**
	 * Save and email a message.
	 *
	 * @param array $data name, email, phone, subject, message.
	 * @return int|WP_Error
	 */
	public static function submit( $data ) {
		$name    = sanitize_text_field( $data['name'] ?? '' );
		$email   = sanitize_email( $data['email'] ?? '' );
		$phone   = sanitize_text_field( $data['phone'] ?? '' );
		$subject = sanitize_text_field( $data['subject'] ?? '' );
		$message = trim( sanitize_textarea_field( $data['message'] ?? '' ) );
		if ( ! $name || ! is_email( $email ) || '' === $message ) {
			return new WP_Error( 'kcs_contact', __( 'Please enter your name, email and message.', 'kingdom-church-suite' ), array( 'status' => 400 ) );
		}
		/* translators: %s: name */
		$title = $subject ? $subject : sprintf( __( 'Message from %s', 'kingdom-church-suite' ), $name );
		$id    = wp_insert_post( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'post_title' => $title, 'post_content' => $message ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_kcs_name', $name );
		update_post_meta( $id, '_kcs_email', $email );
		update_post_meta( $id, '_kcs_phone', $phone );
		update_post_meta( $id, '_kcs_unread', 1 );

		$church = kcs_get_option( 'church_name', get_bloginfo( 'name' ) );
		KCS_Email::send(
			self::recipients(),
			/* translators: 1: subject or sender, 2: church */
			sprintf( __( 'Contact form: %1$s', 'kingdom-church-suite' ), $title ),
			'<p><strong>' . esc_html( $name ) . '</strong> · <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' . ( $phone ? ' · ' . esc_html( $phone ) : '' ) . '</p><p>' . nl2br( esc_html( $message ) ) . '</p><p>' . esc_html__( 'Reply to this email to answer them.', 'kingdom-church-suite' ) . '</p><p><a class="btn" href="' . esc_url( add_query_arg( 'view', 'messages', kcs_page_url( 'church_admin' ) ) ) . '">' . esc_html__( 'Open contact messages', 'kingdom-church-suite' ) . '</a></p>',
			array( 'reply_to' => $name . ' <' . $email . '>' )
		);
		KCS_Email::send(
			$email,
			/* translators: %s: church */
			sprintf( __( 'We received your message — %s', 'kingdom-church-suite' ), $church ),
			'<p>' . esc_html( sprintf( /* translators: %s: name */ __( 'Hi %s, thank you for contacting us. We have received your message and will reply soon.', 'kingdom-church-suite' ), $name ) ) . '</p><blockquote>' . nl2br( esc_html( $message ) ) . '</blockquote>'
		);
		do_action( 'kcs_contact_message', $id );
		return (int) $id;
	}

	/**
	 * Unread messages.
	 *
	 * @return int
	 */
	public static function unread() {
		return count( get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'posts_per_page' => 100, 'fields' => 'ids', 'meta_key' => '_kcs_unread', 'meta_value' => 1 ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	/**
	 * Church Admin → Contact messages.
	 *
	 * @param callable $fields Hidden fields builder ( $do, $view, $id ).
	 */
	public static function view( $fields ) {
		$page  = kcs_current_page();
		$per   = kcs_per_page();
		$q     = new WP_Query( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'posts_per_page' => $per, 'paged' => $page ) );
		$items = $q->posts;
		/* translators: %s: emails */
		echo '<p class="kcs-muted">' . esc_html( sprintf( __( 'Messages from the contact form. New messages are also emailed to: %s.', 'kingdom-church-suite' ), implode( ', ', self::recipients() ) ) ) . '</p>';
		echo '<div class="kca-stack">';
		foreach ( $items as $m ) {
			$email  = (string) get_post_meta( $m->ID, '_kcs_email', true );
			$phone  = (string) get_post_meta( $m->ID, '_kcs_phone', true );
			$unread = (bool) get_post_meta( $m->ID, '_kcs_unread', true );
			$wa     = $phone ? '<a class="kcs-btn kcs-btn--ghost" href="' . esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $phone ) ) . '" target="_blank" rel="noopener">WhatsApp</a>' : '';
			echo '<article class="kca-card kca-item"><div><strong>' . esc_html( get_the_title( $m ) ) . '</strong>' . ( $unread ? ' <span class="kca-pill is-on">' . esc_html__( 'New', 'kingdom-church-suite' ) . '</span>' : '' ) . '<p class="kcs-muted">' . esc_html( get_post_meta( $m->ID, '_kcs_name', true ) . ' · ' . $email . ( $phone ? ' · ' . $phone : '' ) . ' · ' . get_the_date( '', $m ) ) . '</p><p>' . nl2br( esc_html( $m->post_content ) ) . '</p></div><div class="kca-actions">'
				. '<a class="kcs-btn" href="' . esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( 'Re: ' . get_the_title( $m ) ) ) . '">' . esc_html__( 'Reply', 'kingdom-church-suite' ) . '</a>' . $wa // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				. ( $unread ? '<form method="post" class="kca-inline">' . call_user_func( $fields, 'message_read', 'messages', $m->ID ) . '<button class="kcs-btn kcs-btn--ghost">' . esc_html__( 'Mark as read', 'kingdom-church-suite' ) . '</button></form>' : '' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				. '<form method="post" class="kca-inline">' . call_user_func( $fields, 'message_delete', 'messages', $m->ID ) . '<button class="kcs-btn kcs-btn--ghost">' . esc_html__( 'Delete', 'kingdom-church-suite' ) . '</button></form>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				. '</div></article>';
		}
		if ( ! $items ) {
			echo '<p class="kcs-muted">' . esc_html__( 'No messages yet. They come from the form on your Contact Us page.', 'kingdom-church-suite' ) . '</p>';
		}
		echo '</div>';
		echo kcs_pager( $q->found_posts, $per, $page, kcs_page_url( 'church_admin' ), array( 'view' => 'messages' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Church Admin actions.
	 *
	 * @param string $do Action.
	 * @param int    $id Message ID.
	 * @return string Message.
	 */
	public static function handle( $do, $id ) {
		if ( self::TYPE !== get_post_type( $id ) ) {
			return __( 'That message no longer exists.', 'kingdom-church-suite' );
		}
		if ( 'message_delete' === $do ) {
			wp_delete_post( $id, true );
			return __( 'Message deleted.', 'kingdom-church-suite' );
		}
		delete_post_meta( $id, '_kcs_unread' );
		return __( 'Marked as read.', 'kingdom-church-suite' );
	}
}
