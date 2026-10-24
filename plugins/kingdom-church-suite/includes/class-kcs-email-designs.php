<?php
/**
 * Newsletter designs: ready-made email layouts churches start from (in their colours, with their
 * logo, pictures and page links), plus designs a church saves for itself.
 *
 * Layouts use inline styles and simple tables so they look right in Gmail, Outlook and phone mail
 * apps; they sit inside the branded email frame (KCS_Email::wrap()).
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email designs.
 */
class KCS_Email_Designs {

	const OPTION = 'kcs_email_designs';

	/**
	 * Church details the layouts use.
	 *
	 * @return array
	 */
	private static function church() {
		$pics = array();
		foreach ( array( 'kingdom_hero_image', 'kingdom_gallery_1', 'kingdom_gallery_2', 'kingdom_gallery_3', 'kingdom_church_image', 'kingdom_welcome_image' ) as $mod ) {
			$url = (string) get_theme_mod( $mod, '' );
			// Email apps do not show SVG drawings: only the church's own photos.
			if ( $url && ! preg_match( '/\.svg(\?|$)/i', $url ) ) {
				$pics[] = $url;
			}
		}
		$url = function ( $key, $fallback = '' ) {
			$u = function_exists( 'kcs_page_url' ) && kcs_get_option( 'page_' . $key ) ? kcs_page_url( $key ) : '';
			return $u ? $u : ( $fallback ? $fallback : home_url( '/' ) );
		};
		return array(
			'name'    => kcs_get_option( 'church_name', get_bloginfo( 'name' ) ),
			'color'   => kcs_get_option( 'email_color', '#5b2a86' ),
			'address' => kcs_get_option( 'church_address', '' ),
			'pics'    => array_values( array_unique( $pics ) ),
			'give'    => $url( 'give' ),
			'watch'   => $url( 'watch_live' ),
			'prayer'  => $url( 'prayer' ),
			'visit'   => $url( 'visit' ),
			'home'    => home_url( '/' ),
		);
	}

	/**
	 * A picture, or a box asking the church to add one.
	 *
	 * @param array $c Church.
	 * @param int   $n Which photo.
	 * @return string
	 */
	private static function picture( $c, $n = 0 ) {
		if ( isset( $c['pics'][ $n ] ) ) {
			return '<p style="margin:0 0 20px"><img src="' . esc_url( $c['pics'][ $n ] ) . '" alt="" width="576" style="display:block;width:100%;max-width:576px;height:auto;border-radius:12px"></p>';
		}
		return '<p style="margin:0 0 20px;padding:56px 16px;border-radius:12px;background:#f1edf6;color:#8a8196;text-align:center;font-size:14px">' . esc_html__( 'Your picture here: click this box, delete it, then use “Add Media” to insert a photo.', 'kingdom-church-suite' ) . '</p>';
	}

	/**
	 * A button.
	 *
	 * @param array  $c    Church.
	 * @param string $text Text.
	 * @param string $url  Link.
	 * @return string
	 */
	private static function button( $c, $text, $url ) {
		return '<p style="margin:24px 0;text-align:center"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 28px;border-radius:999px;background:' . esc_attr( $c['color'] ) . ';color:#ffffff;font-weight:bold;text-decoration:none">' . esc_html( $text ) . '</a></p>';
	}

	/**
	 * Ready-made designs.
	 *
	 * @return array key => array( name, description, icon, subject, html ).
	 */
	public static function starters() {
		$c     = self::church();
		$h1    = 'style="margin:0 0 12px;font-size:26px;line-height:1.25;color:#1f1b24"';
		$p     = 'style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#3d3747"';
		$small = 'style="margin:0 0 6px;font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:bold;color:' . esc_attr( $c['color'] ) . '"';
		$sign  = '<p ' . $p . '>' . esc_html__( 'God bless you,', 'kingdom-church-suite' ) . '<br><strong>' . esc_html( $c['name'] ) . '</strong></p>';
		$box   = function ( $inner ) use ( $c ) {
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-collapse:collapse"><tr><td style="padding:18px 20px;border-left:4px solid ' . esc_attr( $c['color'] ) . ';background:#f7f4fb;border-radius:8px">' . $inner . '</td></tr></table>';
		};
		$designs = array(
			'letter'        => array(
				__( 'Simple letter', 'kingdom-church-suite' ),
				__( 'A warm personal message from the pastor.', 'kingdom-church-suite' ),
				'✉',
				__( 'A word from our pastor', 'kingdom-church-suite' ),
				'<p ' . $p . '>' . esc_html__( 'Dear {first_name},', 'kingdom-church-suite' ) . '</p><p ' . $p . '>' . esc_html__( 'Write your message here. Share what God is doing in our church family, encourage the members and invite them to the next service.', 'kingdom-church-suite' ) . '</p><p ' . $p . '>' . esc_html__( 'Add as many paragraphs as you need.', 'kingdom-church-suite' ) . '</p>' . $sign,
			),
			'announcements' => array(
				__( 'Weekly announcements', 'kingdom-church-suite' ),
				__( 'Three short notices with a button to your events.', 'kingdom-church-suite' ),
				'📢',
				__( 'This week at church', 'kingdom-church-suite' ),
				'<p ' . $small . '>' . esc_html__( 'This week', 'kingdom-church-suite' ) . '</p><h1 ' . $h1 . '>' . esc_html__( 'Church announcements', 'kingdom-church-suite' ) . '</h1><p ' . $p . '>' . esc_html__( 'Hello {first_name}, here is what is happening this week.', 'kingdom-church-suite' ) . '</p>'
					. $box( '<p style="margin:0 0 4px;font-weight:bold;color:#1f1b24">' . esc_html__( 'Sunday Service — 9:00 am', 'kingdom-church-suite' ) . '</p><p style="margin:0;color:#3d3747">' . esc_html__( 'Come early for prayer at 8:30 am. Bring a friend!', 'kingdom-church-suite' ) . '</p>' )
					. $box( '<p style="margin:0 0 4px;font-weight:bold;color:#1f1b24">' . esc_html__( 'Midweek Bible Study — Wednesday 6:00 pm', 'kingdom-church-suite' ) . '</p><p style="margin:0;color:#3d3747">' . esc_html__( 'We continue our study of the book of Acts.', 'kingdom-church-suite' ) . '</p>' )
					. $box( '<p style="margin:0 0 4px;font-weight:bold;color:#1f1b24">' . esc_html__( 'Youth Fellowship — Saturday 4:00 pm', 'kingdom-church-suite' ) . '</p><p style="margin:0;color:#3d3747">' . esc_html__( 'Games, worship and the Word for teenagers and young adults.', 'kingdom-church-suite' ) . '</p>' )
					. self::button( $c, __( 'See all events', 'kingdom-church-suite' ), $c['home'] ),
			),
			'event'         => array(
				__( 'Event invitation', 'kingdom-church-suite' ),
				__( 'A big picture, the date and place, and a button.', 'kingdom-church-suite' ),
				'🎉',
				__( 'You are invited!', 'kingdom-church-suite' ),
				self::picture( $c, 0 ) . '<p ' . $small . '>' . esc_html__( 'You are invited', 'kingdom-church-suite' ) . '</p><h1 ' . $h1 . '>' . esc_html__( 'Name of the event', 'kingdom-church-suite' ) . '</h1><p ' . $p . '>' . esc_html__( 'Dear {first_name}, join us for a special time of worship, the Word and miracles. Come with your family and friends.', 'kingdom-church-suite' ) . '</p>'
					. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;border-collapse:collapse;font-size:15px;color:#3d3747"><tr><td style="padding:10px 0;border-bottom:1px solid #eee;width:30%;color:#8a8196">' . esc_html__( 'Date', 'kingdom-church-suite' ) . '</td><td style="padding:10px 0;border-bottom:1px solid #eee"><strong>' . esc_html__( 'Friday 12 – Sunday 14 December', 'kingdom-church-suite' ) . '</strong></td></tr><tr><td style="padding:10px 0;border-bottom:1px solid #eee;color:#8a8196">' . esc_html__( 'Time', 'kingdom-church-suite' ) . '</td><td style="padding:10px 0;border-bottom:1px solid #eee"><strong>' . esc_html__( '5:00 pm daily', 'kingdom-church-suite' ) . '</strong></td></tr><tr><td style="padding:10px 0;color:#8a8196">' . esc_html__( 'Venue', 'kingdom-church-suite' ) . '</td><td style="padding:10px 0"><strong>' . esc_html( $c['address'] ? strtok( $c['address'], "\n" ) : __( 'Church auditorium', 'kingdom-church-suite' ) ) . '</strong></td></tr></table>'
					. self::button( $c, __( 'Get directions', 'kingdom-church-suite' ), $c['visit'] ),
			),
			'sermon'        => array(
				__( 'Sermon recap', 'kingdom-church-suite' ),
				__( 'Last Sunday’s message with a key quote and a watch button.', 'kingdom-church-suite' ),
				'🎙',
				__( 'Did you miss Sunday’s message?', 'kingdom-church-suite' ),
				self::picture( $c, 1 ) . '<p ' . $small . '>' . esc_html__( 'Sunday’s message', 'kingdom-church-suite' ) . '</p><h1 ' . $h1 . '>' . esc_html__( 'Title of the sermon', 'kingdom-church-suite' ) . '</h1><p style="margin:0 0 16px;font-size:14px;color:#8a8196">' . esc_html__( 'Preacher’s name · Bible reading', 'kingdom-church-suite' ) . '</p>'
					. $box( '<p style="margin:0;font-size:18px;line-height:1.5;font-style:italic;color:#1f1b24">' . esc_html__( '“A key sentence from the message that people will remember.”', 'kingdom-church-suite' ) . '</p>' )
					. '<p ' . $p . '>' . esc_html__( 'Summarise the message in two or three sentences, and what we can do this week.', 'kingdom-church-suite' ) . '</p>' . self::button( $c, __( 'Watch the message', 'kingdom-church-suite' ), $c['watch'] ),
			),
			'giving'        => array(
				__( 'Giving appeal', 'kingdom-church-suite' ),
				__( 'Invite members to give towards a project.', 'kingdom-church-suite' ),
				'💝',
				__( 'Partner with us', 'kingdom-church-suite' ),
				self::picture( $c, 2 ) . '<h1 ' . $h1 . '>' . esc_html__( 'Help us build God’s house', 'kingdom-church-suite' ) . '</h1><p ' . $p . '>' . esc_html__( 'Dear {first_name}, explain the project, why it matters and how every gift makes a difference.', 'kingdom-church-suite' ) . '</p>'
					. $box( '<p style="margin:0;font-style:italic;color:#1f1b24">' . esc_html__( '“Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver.” — 2 Corinthians 9:7', 'kingdom-church-suite' ) . '</p>' )
					. self::button( $c, __( 'Give now', 'kingdom-church-suite' ), $c['give'] ) . '<p style="margin:0 0 16px;font-size:14px;color:#8a8196;text-align:center">' . esc_html__( 'You can also give by bank transfer: add your account details here.', 'kingdom-church-suite' ) . '</p>' . $sign,
			),
			'devotional'    => array(
				__( 'Devotional & prayer', 'kingdom-church-suite' ),
				__( 'A Bible verse, a short reflection and a prayer.', 'kingdom-church-suite' ),
				'📖',
				__( 'Today’s word', 'kingdom-church-suite' ),
				'<p ' . $small . '>' . esc_html__( 'Today’s word', 'kingdom-church-suite' ) . '</p>'
					. $box( '<p style="margin:0 0 8px;font-size:19px;line-height:1.5;color:#1f1b24">' . esc_html__( '“The Lord is my shepherd; I shall not want.”', 'kingdom-church-suite' ) . '</p><p style="margin:0;font-size:14px;color:#8a8196">' . esc_html__( 'Psalm 23:1', 'kingdom-church-suite' ) . '</p>' )
					. '<p ' . $p . '><strong>' . esc_html__( 'Reflection', 'kingdom-church-suite' ) . '</strong><br>' . esc_html__( 'Write a short reflection on the verse.', 'kingdom-church-suite' ) . '</p><p ' . $p . '><strong>' . esc_html__( 'Prayer', 'kingdom-church-suite' ) . '</strong><br>' . esc_html__( 'Lord, thank you for your faithfulness. Guide us today. In Jesus’ name, Amen.', 'kingdom-church-suite' ) . '</p>'
					. self::button( $c, __( 'Send us a prayer request', 'kingdom-church-suite' ), $c['prayer'] ),
			),
			'thanks'        => array(
				__( 'Thank you', 'kingdom-church-suite' ),
				__( 'Thank partners and volunteers after an event.', 'kingdom-church-suite' ),
				'🙏',
				__( 'Thank you, {first_name}!', 'kingdom-church-suite' ),
				self::picture( $c, 3 ) . '<h1 ' . $h1 . ' align="center">' . esc_html__( 'Thank you!', 'kingdom-church-suite' ) . '</h1><p ' . $p . '>' . esc_html__( 'Dear {first_name}, because of your prayers, giving and service, God did great things. Share a short testimony or the results here.', 'kingdom-church-suite' ) . '</p>' . $sign,
			),
		);
		/**
		 * Ready-made newsletter designs.
		 *
		 * @param array $designs key => array( name, description, icon, subject, html ).
		 */
		return (array) apply_filters( 'kcs_email_designs', $designs );
	}

	/**
	 * Designs the church saved.
	 *
	 * @return array id => array( name, subject, html ).
	 */
	public static function saved() {
		return array_filter( (array) get_option( self::OPTION, array() ), 'is_array' );
	}

	/**
	 * Save the current email as a design.
	 *
	 * @param string $name    Name.
	 * @param string $subject Subject.
	 * @param string $html    HTML.
	 * @return string ID.
	 */
	public static function save( $name, $subject, $html ) {
		$all        = self::saved();
		$id         = substr( md5( uniqid( '', true ) ), 0, 10 );
		$all[ $id ] = array(
			'name'    => sanitize_text_field( $name ),
			'subject' => sanitize_text_field( $subject ),
			'html'    => wp_kses_post( $html ),
		);
		update_option( self::OPTION, $all, false );
		return $id;
	}

	/**
	 * Delete a saved design.
	 *
	 * @param string $id ID.
	 */
	public static function delete( $id ) {
		$all = self::saved();
		unset( $all[ sanitize_key( $id ) ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * The design picker shown above the newsletter editor.
	 *
	 * @param callable $fields Hidden fields builder ( $do, $view, $id ).
	 */
	public static function picker( $fields ) {
		$data = array();
		foreach ( self::starters() as $key => $d ) {
			$data[ 's-' . $key ] = array( 'subject' => $d[3], 'html' => $d[4] );
		}
		foreach ( self::saved() as $id => $d ) {
			$data[ 'm-' . $id ] = array( 'subject' => $d['subject'], 'html' => $d['html'] );
		}
		?>
		<details class="kca-card kca-designs" open>
			<summary><strong><?php esc_html_e( 'Start from a design', 'kingdom-church-suite' ); ?></strong> <span class="kcs-muted"><?php esc_html_e( 'in your church colours, with your logo and photos', 'kingdom-church-suite' ); ?></span></summary>
			<div class="kca-designs__grid">
				<?php foreach ( self::starters() as $key => $d ) : ?>
					<button type="button" class="kca-design" data-kcs-design="s-<?php echo esc_attr( $key ); ?>">
						<span class="kca-design__icon" aria-hidden="true"><?php echo esc_html( $d[2] ); ?></span>
						<strong><?php echo esc_html( $d[0] ); ?></strong>
						<small><?php echo esc_html( $d[1] ); ?></small>
					</button>
				<?php endforeach; ?>
				<?php foreach ( self::saved() as $id => $d ) : ?>
					<div class="kca-design kca-design--mine">
						<button type="button" class="kca-design__use" data-kcs-design="m-<?php echo esc_attr( $id ); ?>">
							<span class="kca-design__icon" aria-hidden="true">★</span>
							<strong><?php echo esc_html( $d['name'] ); ?></strong>
							<small><?php esc_html_e( 'Your saved design', 'kingdom-church-suite' ); ?></small>
						</button>
						<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this saved design?', 'kingdom-church-suite' ) ); ?>')"><?php echo call_user_func( $fields, 'design_delete', 'newsletter', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="design" value="<?php echo esc_attr( $id ); ?>"><button class="kca-design__del" aria-label="<?php esc_attr_e( 'Delete design', 'kingdom-church-suite' ); ?>">×</button></form>
					</div>
				<?php endforeach; ?>
			</div>
			<script type="application/json" data-kcs-designs><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
		</details>
		<?php
	}
}
