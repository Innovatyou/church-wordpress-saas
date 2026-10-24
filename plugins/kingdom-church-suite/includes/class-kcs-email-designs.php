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
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_kcs_play_picture', array( __CLASS__, 'ajax_play_picture' ) );
	}

	/**
	 * Make the "play button" copy of a picture the church just chose for a message email.
	 */
	public static function ajax_play_picture() {
		check_ajax_referer( 'kcs_play_picture' );
		if ( ! KCS_Church_Admin::can() || ! KCS_Team::allowed( 'newsletter' ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$url = $id && wp_attachment_is_image( $id ) ? self::play_picture( $id ) : '';
		if ( ! $url ) {
			wp_send_json_error();
		}
		wp_send_json_success( array( 'url' => $url ) );
	}

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
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
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
		$cache = (array) apply_filters( 'kcs_email_designs', self::modern( $c ) + $designs );
		return $cache;
	}

	/**
	 * A bundled picture the church swaps for its own.
	 *
	 * @param string $name File in assets/img/email.
	 * @return string
	 */
	private static function placeholder( $name ) {
		return KCS_URL . 'assets/img/email/' . $name;
	}

	/**
	 * The church logo for the dark email header, or '' (SVG logos do not show in email apps).
	 *
	 * @return string
	 */
	private static function logo() {
		$logo = (string) kcs_get_option( 'church_logo' );
		if ( ! $logo && get_theme_mod( 'custom_logo' ) ) {
			$logo = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' );
		}
		return $logo && ! preg_match( '/\.svg(\?|$)/i', $logo ) ? $logo : '';
	}

	/**
	 * A copy of a picture with a round play button on it, for "watch the message" emails
	 * (email apps cannot lay a button over a picture, so it is drawn into the picture).
	 *
	 * @param int|string $src Attachment ID or picture URL on this site.
	 * @return string URL of the copy, or '' when it cannot be made.
	 */
	public static function play_picture( $src ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}
		$path = '';
		if ( is_numeric( $src ) ) {
			$path = (string) get_attached_file( (int) $src );
		} else {
			$src     = strtok( (string) $src, '?' );
			$uploads = wp_get_upload_dir();
			foreach ( array( $uploads['baseurl'] => $uploads['basedir'], content_url() => WP_CONTENT_DIR ) as $base => $dir ) {
				$base = set_url_scheme( $base, 'http' );
				if ( 0 === strpos( set_url_scheme( $src, 'http' ), $base . '/' ) ) {
					$path = $dir . substr( set_url_scheme( $src, 'http' ), strlen( $base ) );
					break;
				}
			}
		}
		$path = $path ? wp_normalize_path( $path ) : '';
		if ( ! $path || false !== strpos( $path, '..' ) || ! is_file( $path ) || ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $path ) ) {
			return '';
		}
		$uploads = wp_get_upload_dir();
		$name    = 'play-' . substr( md5( $path . filemtime( $path ) ), 0, 16 ) . '.jpg';
		$dir     = trailingslashit( $uploads['basedir'] ) . 'kcs-email';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'kcs-email/' . $name;
		if ( is_file( $dir . '/' . $name ) ) {
			return $url;
		}
		$img = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $img ) {
			return '';
		}
		$w = imagesx( $img );
		$h = imagesy( $img );
		// Work at up to 1200px wide, drawn twice the size and shrunk for smooth edges.
		$tw  = min( 1200, $w );
		$th  = (int) round( $h * $tw / $w );
		$out = imagecreatetruecolor( $tw, $th );
		imagecopyresampled( $out, $img, 0, 0, 0, 0, $tw, $th, $w, $h );
		imagedestroy( $img );
		$r    = (int) round( min( $tw, $th ) * 0.12 );
		$size = $r * 4 + 8;
		$btn  = imagecreatetruecolor( $size, $size );
		imagealphablending( $btn, false );
		imagefill( $btn, 0, 0, imagecolorallocatealpha( $btn, 0, 0, 0, 127 ) );
		imagealphablending( $btn, true );
		$c = $size / 2;
		imagefilledellipse( $btn, (int) $c, (int) $c, $r * 4 + 24, $r * 4 + 24, imagecolorallocatealpha( $btn, 0, 0, 0, 96 ) );
		imagefilledellipse( $btn, (int) $c, (int) $c, $r * 4, $r * 4, imagecolorallocatealpha( $btn, 255, 255, 255, 12 ) );
		$tri = array( $c - $r * 0.6, $c - $r * 0.9, $c - $r * 0.6, $c + $r * 0.9, $c + $r, $c );
		imagefilledpolygon( $btn, array_map( 'intval', $tri ), imagecolorallocate( $btn, 34, 30, 48 ) );
		imagecopyresampled( $out, $btn, (int) ( $tw / 2 - $r ), (int) ( $th / 2 - $r ), 0, 0, $r * 2, $r * 2, $size, $size );
		imagedestroy( $btn );
		wp_mkdir_p( $dir );
		$ok = imagejpeg( $out, $dir . '/' . $name, 82 );
		imagedestroy( $out );
		return $ok ? $url : '';
	}

	/**
	 * Modern designs: a dark header with the logo, a coloured strip, a cream headline section,
	 * big rounded pictures and colour blocks, full width in the email.
	 *
	 * Every picture is marked so the church can swap it under the editor (“Change pictures”).
	 *
	 * @param array $c Church.
	 * @return array
	 */
	private static function modern( $c ) {
		$accent = $c['color'];
		$dark   = '#1b1824';
		$cream  = '#f7f0e6';
		$olive  = '#4d5a33';
		$gold   = '#d9ae4f';
		$serif  = 'font-family:Georgia,Times,serif';
		$sans   = 'font-family:Arial,Helvetica,sans-serif';
		$pic    = function ( $n, $fallback ) use ( $c ) {
			return isset( $c['pics'][ $n ] ) ? $c['pics'][ $n ] : self::placeholder( $fallback );
		};
		$video  = '';
		foreach ( array( 1, 0 ) as $n ) {
			if ( ! $video && isset( $c['pics'][ $n ] ) ) {
				$video = self::play_picture( $c['pics'][ $n ] );
			}
		}
		$video = $video ? $video : self::placeholder( 'video.jpg' );

		// One full-width band.
		$band = function ( $bg, $inner, $pad = '44px 40px', $align = 'left' ) use ( $sans ) {
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="' . esc_attr( $bg ) . '" style="border-collapse:collapse;background:' . esc_attr( $bg ) . '"><tr><td class="kcs-pad" align="' . esc_attr( $align ) . '" style="padding:' . esc_attr( $pad ) . ';text-align:' . esc_attr( $align ) . ';' . $sans . '">' . $inner . '</td></tr></table>';
		};
		$header = $band(
			$dark,
			self::logo()
				? '<a href="' . esc_url( $c['home'] ) . '"><img src="' . esc_url( self::logo() ) . '" alt="' . esc_attr( $c['name'] ) . '" height="56" style="display:inline-block;height:56px;width:auto;max-width:240px;border:0"></a>'
				: '<a href="' . esc_url( $c['home'] ) . '" style="' . $serif . ';font-size:24px;letter-spacing:1px;color:#ffffff;text-decoration:none">' . esc_html( $c['name'] ) . '</a>',
			'26px 24px',
			'center'
		);
		$strip  = function ( $text, $link, $url ) use ( $band, $accent ) {
			return $band( $accent, '<span style="font-size:14px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#ffffff">' . esc_html( $text ) . ( $link ? ' &nbsp;·&nbsp; <a href="' . esc_url( $url ) . '" style="color:#ffffff;text-decoration:underline">' . esc_html( $link ) . '</a>' : '' ) . '</span>', '13px 24px', 'center' );
		};
		$eyebrow = function ( $text, $color ) {
			return '<p style="margin:0 0 14px;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:' . esc_attr( $color ) . '">' . esc_html( $text ) . '</p>';
		};
		// A big serif headline in two colours.
		$headline = function ( $one, $two, $color, $color2, $size = 44, $tag = 'h1' ) use ( $serif ) {
			return '<' . $tag . ' class="kcs-h1" style="margin:0 0 18px;' . $serif . ';font-size:' . (int) $size . 'px;line-height:1.1;font-weight:normal;color:' . esc_attr( $color ) . '">' . esc_html( $one ) . '<br><em style="color:' . esc_attr( $color2 ) . '">' . esc_html( $two ) . '</em></' . $tag . '>';
		};
		$text = function ( $t, $color = '#3d3747', $extra = '' ) {
			return '<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:' . esc_attr( $color ) . ';' . $extra . '">' . $t . '</p>';
		};
		$image = function ( $src, $url, $class = 'kcs-pic', $radius = 18 ) {
			$img = '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $src ) . '" alt="" width="520" style="display:block;width:100%;max-width:520px;height:auto;border:0;border-radius:' . (int) $radius . 'px">';
			return '<p style="margin:0 0 26px">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . $img . '</a>' : $img ) . '</p>';
		};
		$btn = function ( $t, $url, $bg, $color = '#ffffff', $align = 'center' ) {
			return '<p style="margin:8px 0 6px;text-align:' . esc_attr( $align ) . '"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:16px 30px;border-radius:999px;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';font-size:14px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;text-decoration:none">' . esc_html( $t ) . '</a></p>';
		};
		// Picture and words side by side (one under the other on phones).
		$cols = function ( $bg, $left, $right ) use ( $sans ) {
			return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="' . esc_attr( $bg ) . '" style="border-collapse:collapse;background:' . esc_attr( $bg ) . '"><tr>'
				. '<td class="kcs-col kcs-col--pic kcs-pad" width="44%" valign="middle" style="width:44%;padding:40px 12px 40px 40px;vertical-align:middle;' . $sans . '">' . $left . '</td>'
				. '<td class="kcs-col kcs-col--text kcs-pad" width="56%" valign="middle" style="width:56%;padding:40px 40px 40px 16px;vertical-align:middle;' . $sans . '">' . $right . '</td>'
				. '</tr></table>';
		};
		$sign  = function ( $color, $muted ) use ( $c, $serif, $text ) {
			return $text( esc_html__( 'With love and prayers,', 'kingdom-church-suite' ), $color )
				. '<p style="margin:0;' . $serif . ';font-size:26px;font-style:italic;color:' . esc_attr( $color ) . '">' . esc_html__( 'Pastor’s name', 'kingdom-church-suite' ) . '</p>'
				. '<p style="margin:4px 0 0;font-size:14px;color:' . esc_attr( $muted ) . '">' . esc_html( $c['name'] ) . '</p>';
		};
		$where = $c['address'] ? strtok( $c['address'], "\n" ) : __( 'Church auditorium', 'kingdom-church-suite' );
		$full  = function ( $html ) {
			return '<div class="kcs-full">' . $html . '</div>';
		};

		$designs = array();

		$designs['modern_message'] = array(
			__( 'Modern message (video)', 'kingdom-church-suite' ),
			__( 'Dark header, big headline, message picture with a play button, letter and an offer.', 'kingdom-church-suite' ),
			'✨',
			__( 'A new message for you this week', 'kingdom-church-suite' ),
			$full(
				$header
				. $strip( __( 'New message available now', 'kingdom-church-suite' ), __( 'Watch online', 'kingdom-church-suite' ), $c['watch'] )
				. $band(
					$cream,
					$eyebrow( __( 'This week’s message', 'kingdom-church-suite' ), $accent )
						. $headline( __( 'Your best days', 'kingdom-church-suite' ), __( 'are still ahead', 'kingdom-church-suite' ), $dark, $accent )
						. $image( $video, $c['watch'], 'kcs-pic kcs-video-pic' )
						. $btn( __( 'Watch the message', 'kingdom-church-suite' ), $c['watch'], $accent ),
					'48px 40px 44px',
					'center'
				)
				. $band(
					$dark,
					$text( esc_html__( 'Dear {first_name},', 'kingdom-church-suite' ), '#f3eef8' )
						. $text( esc_html__( 'Write your letter here. Share what God laid on your heart this week, the hope in this message and how it can help your family.', 'kingdom-church-suite' ), '#d9d3e2' )
						. $text( esc_html__( 'Add as many paragraphs as you need. End with a word of blessing and an invitation to church on Sunday.', 'kingdom-church-suite' ), '#d9d3e2' )
						. $sign( '#ffffff', '#a59fb0' ),
					'44px 48px'
				)
				. $band(
					$cream,
					$headline( __( 'Live with intention,', 'kingdom-church-suite' ), __( 'walk in purpose', 'kingdom-church-suite' ), $dark, $accent, 34, 'h2' )
						. $text( esc_html__( 'A short line about the next step: a Bible plan, a class, a small group or a new series starting soon.', 'kingdom-church-suite' ), '#5b5466', 'text-align:center' ),
					'48px 40px 40px',
					'center'
				)
				. $cols(
					$olive,
					'<img class="kcs-pic" src="' . esc_url( $pic( 2, 'offer.jpg' ) ) . '" alt="" width="220" style="display:block;width:100%;max-width:220px;height:auto;border:0;border-radius:14px">',
					'<p style="margin:0 0 6px;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:' . $gold . '">' . esc_html__( 'Coming up', 'kingdom-church-suite' ) . '</p>'
						. '<h3 style="margin:0 0 10px;' . $serif . ';font-size:26px;line-height:1.2;font-weight:normal;color:#ffffff">' . esc_html__( 'Name of the event or resource', 'kingdom-church-suite' ) . '</h3>'
						. $text( esc_html__( 'Two lines about it: what it is, who it is for and why they should come.', 'kingdom-church-suite' ), '#e6ead9', 'font-size:15px' )
						. $btn( __( 'Register now', 'kingdom-church-suite' ), $c['home'], $gold, $dark, 'left' )
						. '<p style="margin:10px 0 0;font-size:13px;font-style:italic;color:#c9cfb6">' . esc_html__( 'Free for everyone · bring a friend', 'kingdom-church-suite' ) . '</p>'
				)
				. $cols(
					$dark,
					'<img class="kcs-pic" src="' . esc_url( $pic( 3, 'people.jpg' ) ) . '" alt="" width="220" style="display:block;width:100%;max-width:220px;height:auto;border:0;border-radius:14px">',
					'<h3 style="margin:0 0 10px;' . $serif . ';font-size:26px;line-height:1.2;font-weight:normal;color:#ffffff">' . esc_html__( 'Need prayer?', 'kingdom-church-suite' ) . '</h3>'
						. $text( esc_html__( 'Our prayer team would be honoured to stand with you in faith this week.', 'kingdom-church-suite' ), '#d9d3e2', 'font-size:15px' )
						. $btn( __( 'Send a prayer request', 'kingdom-church-suite' ), $c['prayer'], $accent, '#ffffff', 'left' )
				)
			),
		);

		$expect = '';
		foreach (
			array(
				array( '1', __( 'Uplifting worship', 'kingdom-church-suite' ), __( 'Songs of praise that lift your heart.', 'kingdom-church-suite' ) ),
				array( '2', __( 'A message of hope', 'kingdom-church-suite' ), __( 'Practical teaching from the Bible for everyday life.', 'kingdom-church-suite' ) ),
				array( '3', __( 'Children’s church', 'kingdom-church-suite' ), __( 'A safe, fun class for your little ones.', 'kingdom-church-suite' ) ),
				array( '4', __( 'A warm welcome', 'kingdom-church-suite' ), __( 'Friendly faces and refreshments after the service.', 'kingdom-church-suite' ) ),
			) as $row
		) {
			$expect .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px"><tr>'
				. '<td width="56" valign="top" style="width:56px;vertical-align:top"><p style="margin:0;width:44px;height:44px;line-height:44px;border-radius:999px;background:' . esc_attr( $accent ) . ';text-align:center;font-family:Georgia,Times,serif;font-size:20px;color:#ffffff">' . esc_html( $row[0] ) . '</p></td>'
				. '<td valign="top" style="vertical-align:top"><p style="margin:0 0 2px;font-size:17px;font-weight:bold;color:#ffffff">' . esc_html( $row[1] ) . '</p><p style="margin:0;font-size:15px;line-height:1.5;color:#c9c2d4">' . esc_html( $row[2] ) . '</p></td>'
				. '</tr></table>';
		}
		$fact = function ( $label, $value ) use ( $accent, $dark ) {
			return '<td class="kcs-col" width="33%" valign="top" style="width:33%;padding:16px 8px;vertical-align:top;text-align:center"><p style="margin:0 0 4px;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:' . esc_attr( $accent ) . '">' . esc_html( $label ) . '</p><p style="margin:0;font-size:16px;font-weight:bold;color:' . esc_attr( $dark ) . '">' . esc_html( $value ) . '</p></td>';
		};
		$invite = 'mailto:?subject=' . rawurlencode( __( 'Come to church with me this Sunday', 'kingdom-church-suite' ) ) . '&body=' . rawurlencode( sprintf( /* translators: 1: church, 2: website */ __( 'I would love you to join me at %1$s this Sunday. Details: %2$s', 'kingdom-church-suite' ), $c['name'], $c['visit'] ) );

		$designs['modern_sunday'] = array(
			__( 'Join us this Sunday', 'kingdom-church-suite' ),
			__( 'A modern invitation: time and place, directions, what to expect and “invite a friend”.', 'kingdom-church-suite' ),
			'⛪',
			__( 'Join us this Sunday, {first_name}!', 'kingdom-church-suite' ),
			$full(
				$header
				. $strip( __( 'This Sunday · 9:00 am · Everyone is welcome', 'kingdom-church-suite' ), '', '' )
				. $band(
					$cream,
					$eyebrow( __( 'You’re invited', 'kingdom-church-suite' ), $accent )
						. $headline( __( 'Join us', 'kingdom-church-suite' ), __( 'this Sunday', 'kingdom-church-suite' ), $dark, $accent, 52 )
						. $text( esc_html__( 'Hi {first_name}, there is a seat saved for you. Come as you are and bring your family — we cannot wait to worship with you.', 'kingdom-church-suite' ), '#5b5466', 'text-align:center' )
						. $image( $pic( 0, 'people.jpg' ), $c['visit'] )
						. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="border-collapse:separate;background:#ffffff;border-radius:16px;margin:0 0 26px"><tr>'
						. $fact( __( 'Time', 'kingdom-church-suite' ), __( 'Sunday · 9:00 am', 'kingdom-church-suite' ) )
						. $fact( __( 'Place', 'kingdom-church-suite' ), $where )
						. $fact( __( 'Online', 'kingdom-church-suite' ), __( 'Watch live', 'kingdom-church-suite' ) )
						. '</tr></table>'
						. $btn( __( 'Get directions', 'kingdom-church-suite' ), $c['visit'], $accent )
						. '<p style="margin:14px 0 0;text-align:center"><a href="' . esc_url( $c['watch'] ) . '" style="font-size:14px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:' . esc_attr( $dark ) . ';text-decoration:underline">' . esc_html__( 'Or watch online', 'kingdom-church-suite' ) . '</a></p>',
					'48px 40px 44px',
					'center'
				)
				. $band(
					$dark,
					'<h2 style="margin:0 0 24px;' . $serif . ';font-size:32px;line-height:1.15;font-weight:normal;color:#ffffff">' . esc_html__( 'What to expect', 'kingdom-church-suite' ) . '</h2>' . $expect,
					'44px 48px 30px'
				)
				. $cols(
					$olive,
					'<img class="kcs-pic" src="' . esc_url( $pic( 1, 'photo.jpg' ) ) . '" alt="" width="220" style="display:block;width:100%;max-width:220px;height:auto;border:0;border-radius:14px">',
					'<h3 style="margin:0 0 10px;' . $serif . ';font-size:28px;line-height:1.2;font-weight:normal;color:#ffffff">' . esc_html__( 'Bring a friend', 'kingdom-church-suite' ) . '</h3>'
						. $text( esc_html__( 'Someone you know needs hope this week. Send them this invitation and come together.', 'kingdom-church-suite' ), '#e6ead9', 'font-size:15px' )
						. $btn( __( 'Invite a friend', 'kingdom-church-suite' ), $invite, $gold, $dark, 'left' )
				)
				. $band(
					$cream,
					'<h2 style="margin:0 0 10px;' . $serif . ';font-size:34px;line-height:1.15;font-weight:normal;color:' . esc_attr( $dark ) . '">' . esc_html__( 'See you on Sunday!', 'kingdom-church-suite' ) . '</h2>' . $text( esc_html__( 'Questions? Just reply to this email.', 'kingdom-church-suite' ), '#5b5466', 'text-align:center' ),
					'44px 40px',
					'center'
				)
			),
		);

		$designs['modern_giving'] = array(
			__( 'Partner with us (modern)', 'kingdom-church-suite' ),
			__( 'A big picture of the project, a scripture block and ways to give.', 'kingdom-church-suite' ),
			'💛',
			__( 'Be part of what God is doing', 'kingdom-church-suite' ),
			$full(
				$header
				. $strip( __( 'Every seed counts', 'kingdom-church-suite' ), __( 'Give online', 'kingdom-church-suite' ), $c['give'] )
				. $band(
					$cream,
					$eyebrow( __( 'Partner with us', 'kingdom-church-suite' ), $accent )
						. $headline( __( 'Together we', 'kingdom-church-suite' ), __( 'build His house', 'kingdom-church-suite' ), $dark, $accent )
						. $image( $pic( 2, 'photo.jpg' ), $c['give'] )
						. $text( esc_html__( 'Dear {first_name}, explain the project, why it matters and what every gift will make possible.', 'kingdom-church-suite' ), '#5b5466', 'text-align:center' )
						. $btn( __( 'Give now', 'kingdom-church-suite' ), $c['give'], $accent ),
					'48px 40px 44px',
					'center'
				)
				. $band(
					$dark,
					'<p style="margin:0 0 14px;' . $serif . ';font-size:24px;line-height:1.45;font-style:italic;color:#ffffff">' . esc_html__( '“Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver.”', 'kingdom-church-suite' ) . '</p><p style="margin:0;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:' . $gold . '">' . esc_html__( '2 Corinthians 9:7', 'kingdom-church-suite' ) . '</p>',
					'48px 48px',
					'center'
				)
				. $cols(
					$olive,
					'<img class="kcs-pic" src="' . esc_url( $pic( 3, 'offer.jpg' ) ) . '" alt="" width="220" style="display:block;width:100%;max-width:220px;height:auto;border:0;border-radius:14px">',
					'<h3 style="margin:0 0 10px;' . $serif . ';font-size:26px;line-height:1.2;font-weight:normal;color:#ffffff">' . esc_html__( 'Other ways to give', 'kingdom-church-suite' ) . '</h3>'
						. $text( esc_html__( 'Bank transfer: add your bank name, account name and account number here.', 'kingdom-church-suite' ), '#e6ead9', 'font-size:15px' )
						. $btn( __( 'Give online', 'kingdom-church-suite' ), $c['give'], $gold, $dark, 'left' )
				)
				. $band( $cream, $sign( $dark, '#7a7385' ), '40px 48px' )
			),
		);

		return $designs;
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
