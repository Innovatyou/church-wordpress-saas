<?php
/**
 * Template helpers.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the companion plugin active?
 *
 * @return bool
 */
function kingdom_plugin_active() {
	return class_exists( 'KCS_Plugin' );
}

/**
 * Render a homepage/page section.
 *
 * @param string $name Section slug (template-parts/sections/{name}.php).
 * @param array  $args Arguments.
 */
function kingdom_section( $name, $args = array() ) {
	if ( ! kingdom_section_enabled( $name ) ) {
		return;
	}
	get_template_part( 'template-parts/sections/' . sanitize_key( $name ), null, $args );
}

/**
 * Is a website section switched on? (Customize → Show / Hide Sections, or Church Admin → Website.)
 *
 * The page's own editor content can't be switched off here.
 *
 * @param string $name Section slug.
 * @return bool
 */
function kingdom_section_enabled( $name ) {
	if ( 'page-content' === $name ) {
		return true;
	}
	return (bool) get_theme_mod( 'kingdom_show_' . sanitize_key( $name ), true );
}

/**
 * Parse a banner video URL.
 *
 * @param string $url YouTube, Vimeo or direct video file URL.
 * @return array|null type (youtube|vimeo|file) and id/src.
 */
function kingdom_video_source( $url ) {
	$url = trim( (string) $url );
	if ( ! $url ) {
		return null;
	}
	$yt = kingdom_youtube_id( $url );
	if ( $yt ) {
		return array( 'type' => 'youtube', 'id' => $yt );
	}
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
		return array( 'type' => 'vimeo', 'id' => $m[1] );
	}
	if ( preg_match( '~\.(mp4|webm|ogv|mov)(\?.*)?$~i', $url ) ) {
		return array( 'type' => 'file', 'src' => $url );
	}
	return null;
}

/**
 * Background video markup for the homepage banner (muted, looping, no controls).
 *
 * @param array  $src    From kingdom_video_source().
 * @param string $poster Poster image shown while loading or if autoplay is blocked.
 * @return string
 */
function kingdom_background_video( $src, $poster = '' ) {
	if ( ! $src ) {
		return '';
	}
	$title = esc_attr__( 'Banner video', 'kingdom' );
	if ( 'youtube' === $src['type'] ) {
		$q = 'autoplay=1&mute=1&loop=1&playlist=' . rawurlencode( $src['id'] ) . '&controls=0&rel=0&playsinline=1&modestbranding=1&disablekb=1&iv_load_policy=3&fs=0';
		return '<iframe class="k-hero__bgvideo" src="' . esc_url( 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $src['id'] ) . '?' . $q ) . '" title="' . $title . '" allow="autoplay; encrypted-media; picture-in-picture" tabindex="-1" aria-hidden="true"></iframe>';
	}
	if ( 'vimeo' === $src['type'] ) {
		return '<iframe class="k-hero__bgvideo" src="' . esc_url( 'https://player.vimeo.com/video/' . rawurlencode( $src['id'] ) . '?background=1&autoplay=1&loop=1&muted=1&dnt=1' ) . '" title="' . $title . '" allow="autoplay; fullscreen" tabindex="-1" aria-hidden="true"></iframe>';
	}
	// A phone video (optional) comes first with a media query so phones never download the big one.
	$sources = '';
	if ( ! empty( $src['small'] ) ) {
		$sources .= '<source src="' . esc_url( $src['small'] ) . '" media="(max-width: 767px)"' . kingdom_video_type( $src['small'] ) . '>';
	}
	$sources .= '<source src="' . esc_url( $src['src'] ) . '"' . kingdom_video_type( $src['src'] ) . '>';
	return '<video class="k-hero__video" autoplay muted loop playsinline preload="metadata" disablepictureinpicture disableremoteplayback' . ( $poster ? ' poster="' . esc_url( $poster ) . '"' : '' ) . ' aria-hidden="true" data-k-bgvideo>' . $sources . '</video>';
}

/**
 * type="" attribute for a video file the browser can check before downloading it.
 *
 * @param string $url Video file URL.
 * @return string
 */
function kingdom_video_type( $url ) {
	$ext   = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$types = array( 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg' );
	return isset( $types[ $ext ] ) ? ' type="' . $types[ $ext ] . '"' : '';
}

/**
 * Website settings the Church Admin dashboard can change (sections + banner).
 *
 * Exposed to the Ministry OS Church Suite plugin through the kcs_website_settings filter.
 *
 * @return array key => [label, type, group, default, choices?]
 */
function kingdom_website_settings() {
	$out = array(
		'kingdom_hero_bg_video'     => array( __( 'Banner video (upload one, or paste a YouTube, Vimeo or MP4 link)', 'kingdom' ), 'video', 'banner', '' ),
		'kingdom_hero_bg_video_small' => array( __( 'Phone version of the uploaded video (optional: a smaller or upright video for phones)', 'kingdom' ), 'video', 'banner', '' ),
		'kingdom_hero_hide_text'    => array( __( 'Hide all banner text and buttons (show only the video/image)', 'kingdom' ), 'checkbox', 'banner', false ),
		'kingdom_hero_sound_button' => array( __( 'Show a "Watch with sound" button on YouTube banners', 'kingdom' ), 'checkbox', 'banner', true ),
		'kingdom_hero_height'       => array( __( 'Banner height', 'kingdom' ), 'select', 'banner', 'full', array( 'full' => __( 'Full screen', 'kingdom' ), 'tall' => __( 'Tall (80%)', 'kingdom' ), 'medium' => __( 'Medium (60%)', 'kingdom' ), 'video' => __( 'Fit the video (16:9)', 'kingdom' ) ) ),
		'kingdom_hero_text_anim'    => array( __( 'Banner text animation', 'kingdom' ), 'select', 'banner', 'fade', kingdom_hero_animations() ),
		'kingdom_hero_anim_speed'   => array( __( 'Animation speed', 'kingdom' ), 'select', 'banner', 'normal', array( 'slow' => __( 'Slow', 'kingdom' ), 'normal' => __( 'Normal', 'kingdom' ), 'fast' => __( 'Fast', 'kingdom' ) ) ),
		'kingdom_hero_overlay'      => array( __( 'Banner darkness over the video/image (0–90)', 'kingdom' ), 'number', 'banner', 55 ),
	);
	$palettes = array();
	foreach ( kingdom_palettes() as $slug => $p ) {
		$palettes[ $slug ] = $p[0];
	}
	$palettes['custom']           = __( 'Custom (use the colours below)', 'kingdom' );
	$out['kingdom_palette']       = array( __( 'Colour palette', 'kingdom' ), 'select', 'brand', 'royal', $palettes );
	$out['kingdom_color_primary'] = array( __( 'Custom main colour', 'kingdom' ), 'color', 'brand', '#5b2a86' );
	$out['kingdom_color_accent']  = array( __( 'Custom highlight colour', 'kingdom' ), 'color', 'brand', '#f4a62a' );
	$out['kingdom_color_dark']    = array( __( 'Custom dark colour (footer & dark sections)', 'kingdom' ), 'color', 'brand', '#16121c' );
	$out['kingdom_font_pair']     = array( __( 'Font pairing', 'kingdom' ), 'select', 'fonts', 'custom', wp_list_pluck( kingdom_font_pairs(), 0 ) );
	$out['kingdom_font_heading']  = array( __( 'Heading font (when Custom)', 'kingdom' ), 'select', 'fonts', 'Playfair Display', kingdom_font_choices( true ) );
	$out['kingdom_font_body']     = array( __( 'Body text font (when Custom)', 'kingdom' ), 'select', 'fonts', 'Inter', kingdom_font_choices( false ) );
	$out['kingdom_font_source']   = array( __( 'Load fonts from', 'kingdom' ), 'select', 'fonts', 'google', array( 'google' => __( 'Google Fonts', 'kingdom' ), 'system' => __( 'Device fonts only (fastest, nothing loaded from Google)', 'kingdom' ) ) );
	foreach ( kingdom_media_settings() as $key => $f ) {
		$out[ $key ] = array( $f[0], $f[1], 'media', $f[2] );
	}
	foreach ( kingdom_available_sections() as $slug => $label ) {
		$out[ 'kingdom_show_' . $slug ] = array( $label, 'checkbox', 'sections', true );
	}
	foreach ( kingdom_site_elements() as $key => $label ) {
		$out[ $key ] = array( $label, 'checkbox', 'sections', true );
	}
	foreach ( kingdom_footer_settings() as $key => $f ) {
		$out[ $key ] = array( $f[0], $f[1], 'footer', $f[2], $f[3] ?? array() );
	}
	// Pictures & logo.
	$out['custom_logo']        = array( __( 'Church logo', 'kingdom' ), 'image_id', 'pictures', 0 );
	$out['kingdom_logo_light'] = array( __( 'White logo (for dark headers and dark mode, optional)', 'kingdom' ), 'image', 'pictures', '' );
	$out['kingdom_logo_size']  = array( __( 'Logo size in the header', 'kingdom' ), 'select', 'pictures', 'medium', kingdom_logo_sizes() );
	foreach ( kingdom_image_slots() as $slot => $info ) {
		$out[ $slot ] = array( $info[0] . ( $info[2] ? ' — ' . $info[2] : '' ), 'image', 'pictures', '' );
		if ( 0 === strpos( $slot, 'kingdom_gallery_' ) ) {
			$out[ $slot . '_caption' ] = array( $info[0] . ' — ' . __( 'caption', 'kingdom' ), 'text', 'pictures', '' );
		}
	}
	$out['kingdom_gallery_title']     = array( __( 'Gallery title', 'kingdom' ), 'text', 'pictures', __( 'Life at church', 'kingdom' ) );
	$out['kingdom_page_header_image'] = array( __( 'Header picture on inner pages (optional)', 'kingdom' ), 'image', 'pictures', '' );
	// Homepage text.
	$texts = array(
		'kingdom_hero_eyebrow'     => array( __( 'Banner: small label', 'kingdom' ), 'text', __( 'Welcome home', 'kingdom' ) ),
		'kingdom_hero_title'       => array( __( 'Banner: title', 'kingdom' ), 'text', __( 'A place to belong, believe and become', 'kingdom' ) ),
		'kingdom_hero_text'        => array( __( 'Banner: text', 'kingdom' ), 'textarea', __( 'Join us this Sunday — in person or online — and experience the love of Jesus with a family that cares.', 'kingdom' ) ),
		'kingdom_hero_btn1_text'   => array( __( 'Banner: main button text', 'kingdom' ), 'text', __( 'Visit us', 'kingdom' ) ),
		'kingdom_hero_btn1_url'    => array( __( 'Banner: main button link (optional)', 'kingdom' ), 'url', '' ),
		'kingdom_hero_btn2_text'   => array( __( 'Banner: second button text', 'kingdom' ), 'text', __( 'Watch online', 'kingdom' ) ),
		'kingdom_hero_btn2_url'    => array( __( 'Banner: second button link (optional)', 'kingdom' ), 'url', '' ),
		'kingdom_welcome_eyebrow'  => array( __( 'Welcome: small label', 'kingdom' ), 'text', __( 'Welcome', 'kingdom' ) ),
		'kingdom_welcome_title'    => array( __( 'Welcome: title', 'kingdom' ), 'text', __( 'You are not here by accident', 'kingdom' ) ),
		'kingdom_welcome_text'     => array( __( 'Welcome: message', 'kingdom' ), 'textarea', __( 'We are a family of believers passionate about Jesus, prayer and the Word. Whether you are exploring faith for the first time or looking for a church home, there is a place for you here.', 'kingdom' ) ),
		'kingdom_pastor_name'      => array( __( 'Pastor name', 'kingdom' ), 'text', __( 'Pastor John & Mary Doe', 'kingdom' ) ),
		'kingdom_pastor_role'      => array( __( 'Pastor role', 'kingdom' ), 'text', __( 'Lead Pastors', 'kingdom' ) ),
		'kingdom_welcome_btn_text' => array( __( 'Welcome: button text', 'kingdom' ), 'text', __( 'Our story', 'kingdom' ) ),
		'kingdom_welcome_btn_url'  => array( __( 'Welcome: button link (optional)', 'kingdom' ), 'url', '' ),
	);
	foreach ( $texts as $key => $f ) {
		$out[ $key ] = array( $f[0], $f[1], 'texts', $f[2] );
	}
	foreach ( kingdom_visit_page_settings() as $key => $f ) {
		$out[ $key ] = array( $f[0], $f[1], 'visitpage', $f[2] );
	}
	foreach ( kingdom_charity_settings() as $key => $f ) {
		$out[ $key ] = array( $f[0], $f[1], 'charity', $f[2] );
	}
	// Contact & social.
	$contact = array(
		'kingdom_address'           => array( __( 'Address', 'kingdom' ), 'textarea' ),
		'kingdom_phone'             => array( __( 'Phone', 'kingdom' ), 'text' ),
		'kingdom_email'             => array( __( 'Email', 'kingdom' ), 'email' ),
		'kingdom_map'               => array( __( 'Google map: paste the embed code (Google Maps → Share → Embed a map → Copy HTML). Empty shows a map of your address.', 'kingdom' ), 'map' ),
		'kingdom_social_facebook'   => array( 'Facebook', 'url' ),
		'kingdom_social_instagram'  => array( 'Instagram', 'url' ),
		'kingdom_social_youtube'    => array( 'YouTube', 'url' ),
		'kingdom_social_x'          => array( 'X (Twitter)', 'url' ),
		'kingdom_social_tiktok'     => array( 'TikTok', 'url' ),
		'kingdom_social_whatsapp'   => array( 'WhatsApp (https://wa.me/number)', 'url' ),
		'kingdom_social_telegram'   => array( 'Telegram', 'url' ),
	);
	foreach ( $contact as $key => $f ) {
		$out[ $key ] = array( $f[0], $f[1], 'contact', '' );
	}
	// Contact Us page.
	$out['kingdom_contact_title']     = array( __( 'Contact page: title', 'kingdom' ), 'text', 'contact', __( 'We would love to hear from you', 'kingdom' ) );
	$out['kingdom_contact_intro']     = array( __( 'Contact page: text (optional)', 'kingdom' ), 'textarea', 'contact', '' );
	$out['kingdom_contact_form_show'] = array( __( 'Show the contact form', 'kingdom' ), 'checkbox', 'contact', true );
	$out['kingdom_contact_to']        = array( __( 'Send contact form messages to (one or more emails, separated by commas; empty uses the church notification email)', 'kingdom' ), 'emails', 'contact', '' );
	$out['kingdom_contact_map_auto']  = array( __( 'No map code? Show a map of the church address', 'kingdom' ), 'checkbox', 'contact', true );
	return $out;
}

/**
 * Banner text animations (slug => label).
 *
 * @return array
 */
function kingdom_hero_animations() {
	return array(
		'fade'       => __( 'Gentle fade (default)', 'kingdom' ),
		'rise'       => __( 'Rise up, one line after another', 'kingdom' ),
		'words'      => __( 'Title word by word', 'kingdom' ),
		'letters'    => __( 'Title letter by letter', 'kingdom' ),
		'typewriter' => __( 'Typewriter', 'kingdom' ),
		'zoom'       => __( 'Zoom in', 'kingdom' ),
		'slide'      => __( 'Slide in from the side', 'kingdom' ),
		'blur'       => __( 'Blur to sharp', 'kingdom' ),
		'flip'       => __( '3D flip', 'kingdom' ),
		'bounce'     => __( 'Bounce in', 'kingdom' ),
		'shine'      => __( 'Shine across the title', 'kingdom' ),
		'none'       => __( 'No animation', 'kingdom' ),
	);
}

/**
 * Visit page texts (key => array( label, type, default )). Sample wording for every church to edit.
 *
 * @return array
 */
function kingdom_visit_page_settings() {
	return array(
		'kingdom_vp_show'        => array( __( 'Show this visitor information on the Visit page', 'kingdom' ), 'checkbox', true ),
		'kingdom_vp_title'       => array( __( 'Title', 'kingdom' ), 'text', __( 'Visit us', 'kingdom' ) ),
		'kingdom_vp_intro'       => array( __( 'Introduction', 'kingdom' ), 'longtext', __( "Every year we welcome visitors from near and far who come to worship with us, receive prayer and see what God is doing in our church family.\n\nWhether you live nearby or are travelling from another country, you are welcome. Read the information below, then get in touch so we can prepare for your visit.", 'kingdom' ) ),
		'kingdom_vp_local_title' => array( __( 'Visitors from within the country: heading', 'kingdom' ), 'text', __( 'Visiting from within the country', 'kingdom' ) ),
		'kingdom_vp_local_text'  => array( __( 'Visitors from within the country: text', 'kingdom' ), 'longtext', __( 'If you would like to visit, please call us on one of these lines and wait for confirmation before coming.', 'kingdom' ) ),
		'kingdom_vp_hours'       => array( __( 'Call hours', 'kingdom' ), 'text', __( 'Call us: Mon–Fri 9am–5pm', 'kingdom' ) ),
		'kingdom_vp_phones'      => array( __( 'Phone numbers for visits (one per line; empty uses the church phone)', 'kingdom' ), 'textarea', '' ),
		'kingdom_vp_email'       => array( __( 'Email for visits (empty uses the church email)', 'kingdom' ), 'email', '' ),
		'kingdom_vp_location'    => array( __( 'Where you are (empty uses the church address)', 'kingdom' ), 'textarea', '' ),
		'kingdom_vp_person'      => array( __( 'Contact person (optional)', 'kingdom' ), 'text', '' ),
		'kingdom_vp_ask_service' => array( __( 'Ask which service on the Visit Us booking form', 'kingdom' ), 'checkbox', true ),
		'kingdom_vp_faq_title'   => array( __( 'Questions: heading', 'kingdom' ), 'text', __( 'Visiting from abroad', 'kingdom' ) ),
		'kingdom_vp_faq'         => array( __( 'Questions and answers (write each question on its own line ending with ?, and its answer on the lines below)', 'kingdom' ), 'longtext', __( "How do I arrange a visit?\nFill out the questionnaire below with your full details and submit it. Please wait for us to confirm your visit before you book flights or make travel plans.\n\nWill I need a visa?\nIt depends on your country. If you need one, we can send you an invitation letter to present at the embassy with your application.\n\nHow will I get to the church?\nOnce you have confirmed your arrival time with us, a church representative will meet you at the airport.\n\nWhere will I stay?\nTell us in the questionnaire if you need accommodation and we will share the options with you.\n\nHow long do visitors stay?\nMost visitors stay for about a week, attending services and fellowship during their stay.\n\nWhat is the weather like?\nDescribe the climate here and what clothes visitors should bring.", 'kingdom' ) ),
		'kingdom_vp_form_show'   => array( __( 'Show the visitor questionnaire', 'kingdom' ), 'checkbox', true ),
		'kingdom_vp_form_to'     => array( __( 'Send Visit Us bookings and questionnaires to (one or more emails, separated by commas; empty uses the church notification email)', 'kingdom' ), 'emails', '' ),
		'kingdom_vp_form_title'  => array( __( 'Questionnaire: heading', 'kingdom' ), 'text', __( 'Visitor questionnaire', 'kingdom' ) ),
		'kingdom_vp_form_text'   => array( __( 'Questionnaire: text', 'kingdom' ), 'textarea', __( 'Fill out your questionnaire here. We will reply to confirm your visit.', 'kingdom' ) ),
	);
}

/**
 * Charity section texts (key => array( label, type, default )).
 *
 * @return array
 */
function kingdom_charity_settings() {
	return array(
		'kingdom_charity_eyebrow'  => array( __( 'Small label', 'kingdom' ), 'text', __( 'Charity', 'kingdom' ) ),
		'kingdom_charity_title'    => array( __( 'Title', 'kingdom' ), 'text', __( 'Our charity outreach', 'kingdom' ) ),
		'kingdom_charity_text'     => array( __( 'Text (leave an empty line between paragraphs)', 'kingdom' ), 'longtext', __( "Our charity is the humanitarian arm of our church. We care for widows, orphans, the elderly, people living with disabilities and families in need in our community.\n\nThrough food drives, medical outreaches and scholarships for children from underprivileged homes, we share the love of Jesus in practical ways. Join us to feed the hungry, bring relief to the poor and bless the needy.", 'kingdom' ) ),
		'kingdom_charity_causes'   => array( __( 'Who you help (one per line)', 'kingdom' ), 'textarea', __( "Widows\nOrphans\nThe elderly\nPeople living with disabilities\nScholarships for children", 'kingdom' ) ),
		'kingdom_charity_image'    => array( __( 'Picture (optional)', 'kingdom' ), 'image', '' ),
		'kingdom_charity_btn_text' => array( __( 'Button text', 'kingdom' ), 'text', __( 'Support our charity', 'kingdom' ) ),
		'kingdom_charity_btn_url'  => array( __( 'Button link (optional; empty opens Give Online)', 'kingdom' ), 'url', '' ),
	);
}

/**
 * Theme setting text with its sample default from the settings lists above.
 *
 * @param string $key Setting.
 * @return string
 */
function kingdom_text_setting( $key ) {
	static $defaults = null;
	if ( null === $defaults ) {
		$defaults = array_map(
			function ( $f ) {
				return $f[2];
			},
			kingdom_visit_page_settings() + kingdom_charity_settings()
		);
	}
	return (string) get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

/**
 * Paragraphs from plain text: an empty line starts a new paragraph, single line breaks are kept.
 *
 * @param string $text Text.
 * @return string HTML.
 */
function kingdom_paragraphs( $text ) {
	$out = '';
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $block ) {
		if ( '' !== trim( $block ) ) {
			$out .= '<p>' . nl2br( esc_html( trim( $block ) ), false ) . '</p>';
		}
	}
	return $out;
}

/**
 * Questions and answers from plain text: a line ending with "?" starts a question, the lines
 * below it are its answer.
 *
 * @param string $text Text.
 * @return array[] Each array( question, answer ).
 */
function kingdom_faq_items( $text ) {
	$items = array();
	$i     = -1;
	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line && '?' === substr( $line, -1 ) && strlen( $line ) <= 200 ) {
			$items[ ++$i ] = array( rtrim( $line, '?' ) . '?', '' );
		} elseif ( $i >= 0 ) {
			$items[ $i ][1] .= $line . "\n";
		}
	}
	return array_map(
		function ( $item ) {
			return array( $item[0], trim( preg_replace( "/\n{3,}/", "\n\n", $item[1] ) ) );
		},
		$items
	);
}

/**
 * Footer settings (key => array( label, type, default, choices? )), shared by the Customizer
 * and Church Admin → Website.
 *
 * @return array
 */
function kingdom_footer_settings() {
	return array(
		'kingdom_footer_show'         => array( __( 'Show the footer', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_layout'       => array(
			__( 'Footer layout', 'kingdom' ),
			'select',
			'columns',
			array(
				'columns'  => __( 'Four columns', 'kingdom' ),
				'centered' => __( 'Centred', 'kingdom' ),
				'minimal'  => __( 'Minimal (one slim line)', 'kingdom' ),
			),
		),
		'kingdom_footer_colour'       => array(
			__( 'Footer colours', 'kingdom' ),
			'select',
			'dark',
			array(
				'dark'  => __( 'Dark', 'kingdom' ),
				'light' => __( 'Light', 'kingdom' ),
				'brand' => __( 'Church main colour', 'kingdom' ),
			),
		),
		'kingdom_footer_newsletter'   => array( __( 'Newsletter sign-up band', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_show_about'   => array( __( 'Church name, about text and social icons', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_show_times'   => array( __( 'Service times', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_show_links'   => array( __( 'Quick links (footer menu)', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_show_contact' => array( __( 'Address, phone and email', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_show_bottom'  => array( __( 'Copyright line', 'kingdom' ), 'checkbox', true ),
		'kingdom_footer_about'        => array( __( 'About text', 'kingdom' ), 'textarea', __( 'Raising a generation of believers who love God, love people and transform their world.', 'kingdom' ) ),
		'kingdom_copyright'           => array( __( 'Copyright text (empty = © year and church name)', 'kingdom' ), 'text', '' ),
	);
}
add_filter(
	'kcs_website_settings',
	function () {
		return kingdom_website_settings();
	}
);

/**
 * Available homepage sections (slug => label).
 *
 * @return array
 */
function kingdom_available_sections() {
	return array(
		'hero'          => __( 'Hero', 'kingdom' ),
		'service-times' => __( 'Service times bar', 'kingdom' ),
		'welcome'       => __( 'Welcome / pastor message', 'kingdom' ),
		'youtube-live'  => __( 'YouTube live stream', 'kingdom' ),
		'live'          => __( 'Live stream (Church Suite schedule & chat)', 'kingdom' ),
		'videos'        => __( 'Videos', 'kingdom' ),
		'audio'         => __( 'Audio messages & podcast', 'kingdom' ),
		'countdown'     => __( 'Next service countdown', 'kingdom' ),
		'sermons'       => __( 'Latest sermons', 'kingdom' ),
		'events'        => __( 'Upcoming events', 'kingdom' ),
		'ministries'    => __( 'Ministries', 'kingdom' ),
		'stats'         => __( 'Stats counter', 'kingdom' ),
		'partnership'   => __( 'Partnership plans', 'kingdom' ),
		'give'          => __( 'Online giving', 'kingdom' ),
		'leadership'    => __( 'Leadership', 'kingdom' ),
		'testimonies'   => __( 'Testimonies', 'kingdom' ),
		'verse'         => __( 'Verse of the day', 'kingdom' ),
		'prayer'        => __( 'Prayer request', 'kingdom' ),
		'blog'          => __( 'Latest news', 'kingdom' ),
		'newsletter'    => __( 'Newsletter sign-up', 'kingdom' ),
		'visit'         => __( 'Visit us: what to expect', 'kingdom' ),
		'charity'       => __( 'Charity', 'kingdom' ),
		'social'        => __( 'Social media connect', 'kingdom' ),
		'gallery'       => __( 'Church photo gallery', 'kingdom' ),
		'contact'       => __( 'Contact & map', 'kingdom' ),
		'cta'           => __( 'Call to action', 'kingdom' ),
	);
}

/**
 * "Custom colours" fields for the visitor colour picker.
 *
 * @param string $id Unique id prefix.
 */
function kingdom_custom_colour_fields( $id ) {
	list( $primary, $accent ) = kingdom_brand_colors();
	?>
	<div class="k-palette__custom">
		<span><?php esc_html_e( 'Custom colours', 'kingdom' ); ?></span>
		<label for="<?php echo esc_attr( $id ); ?>-primary"><input type="color" id="<?php echo esc_attr( $id ); ?>-primary" data-custom-colour="primary" value="<?php echo esc_attr( $primary ); ?>"> <?php esc_html_e( 'Main', 'kingdom' ); ?></label>
		<label for="<?php echo esc_attr( $id ); ?>-accent"><input type="color" id="<?php echo esc_attr( $id ); ?>-accent" data-custom-colour="accent" value="<?php echo esc_attr( $accent ); ?>"> <?php esc_html_e( 'Highlight', 'kingdom' ); ?></label>
	</div>
	<?php
}

/**
 * Readable text colour (white or near-black) on a background colour.
 *
 * @param string $hex Background colour.
 * @return string
 */
function kingdom_on_colour( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#ffffff';
	}
	$lin = array();
	foreach ( str_split( $hex, 2 ) as $c ) {
		$v     = hexdec( $c ) / 255;
		$lin[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	$l = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	// Pick whichever gives more contrast.
	return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.05 ) * 0.6 ? '#ffffff' : '#16121c';
}

/**
 * Site-wide elements that can be switched on and off (theme mod => label).
 *
 * @return array
 */
function kingdom_site_elements() {
	return array(
		'kingdom_topbar'            => __( 'Top bar (service time, phone, partner login)', 'kingdom' ),
		'kingdom_header_cta'        => __( 'Header “Give” button', 'kingdom' ),
		'kingdom_header_search'     => __( 'Header search button', 'kingdom' ),
		'kingdom_dark_toggle'       => __( 'Dark-mode button', 'kingdom' ),
		'kingdom_live_badge'        => __( '“Live now” badge in the header', 'kingdom' ),
		'kingdom_palette_switcher'  => __( 'Colour palette picker for visitors', 'kingdom' ),
		'kingdom_to_top'            => __( 'Back-to-top button', 'kingdom' ),
		'kingdom_mobile_nav'        => __( 'Bottom tab bar on phones', 'kingdom' ),
	);
}

/**
 * Settings for the YouTube live, Videos and Audio sections (key => array( label, type, default )).
 *
 * @return array
 */
function kingdom_media_settings() {
	$out = array(
		'kingdom_ytlive_channel'  => array( __( 'YouTube live: your channel (link, @handle or channel ID) — switches to your stream automatically', 'kingdom' ), 'text', '' ),
		'kingdom_ytlive_last_replay' => array( __( 'YouTube live: when not live, play our last livestream', 'kingdom' ), 'checkbox', true ),
		'kingdom_ytlive_url'      => array( __( 'YouTube live: a fixed video to show when you are not live instead (optional, link or ID)', 'kingdom' ), 'text', '' ),
		'kingdom_ytlive_api_key'  => array( __( 'YouTube live: YouTube API key (optional, most reliable detection)', 'kingdom' ), 'text', '' ),
		'kingdom_ytlive_title'    => array( __( 'YouTube live: heading', 'kingdom' ), 'text', __( 'Worship with us live', 'kingdom' ) ),
		'kingdom_ytlive_autoplay' => array( __( 'YouTube live: start playing (muted) when the page opens — YouTube may not count these as views until the viewer taps play', 'kingdom' ), 'checkbox', false ),
		'kingdom_ytlive_chat'     => array( __( 'YouTube live: show the live chat next to the video', 'kingdom' ), 'checkbox', false ),
		'kingdom_ytlive_viewers'  => array( __( 'YouTube live: show how many people are watching', 'kingdom' ), 'checkbox', true ),
		'kingdom_videos_title'    => array( __( 'Videos: heading', 'kingdom' ), 'text', __( 'Watch & be blessed', 'kingdom' ) ),
		'kingdom_audio_title'     => array( __( 'Audio: heading', 'kingdom' ), 'text', __( 'Listen to the Word', 'kingdom' ) ),
		'kingdom_audio_embed'     => array( __( 'Audio: podcast link (Spotify, SoundCloud or Apple Podcasts)', 'kingdom' ), 'url', '' ),
	);
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: number */
		$out[ 'kingdom_video_' . $i . '_url' ] = array( sprintf( __( 'Video %d: YouTube or Vimeo link', 'kingdom' ), $i ), 'url', '' );
		/* translators: %d: number */
		$out[ 'kingdom_video_' . $i . '_title' ] = array( sprintf( __( 'Video %d: title', 'kingdom' ), $i ), 'text', '' );
	}
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: number */
		$out[ 'kingdom_audio_' . $i . '_url' ] = array( sprintf( __( 'Audio %d: MP3 link', 'kingdom' ), $i ), 'url', '' );
		/* translators: %d: number */
		$out[ 'kingdom_audio_' . $i . '_title' ] = array( sprintf( __( 'Audio %d: title', 'kingdom' ), $i ), 'text', '' );
	}
	return $out;
}

/**
 * Videos for the Videos section: the church's own list, else the latest sermon videos.
 *
 * @param int $limit Max items.
 * @return array Items of array( title, type, id, thumb, url ).
 */
function kingdom_videos( $limit = 4 ) {
	$items = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$src = kingdom_video_source( get_theme_mod( 'kingdom_video_' . $i . '_url', '' ) );
		if ( $src && 'file' !== $src['type'] ) {
			$items[] = array(
				'title' => get_theme_mod( 'kingdom_video_' . $i . '_title', '' ),
				'type'  => $src['type'],
				'id'    => $src['id'],
				'thumb' => 'youtube' === $src['type'] ? 'https://i.ytimg.com/vi/' . $src['id'] . '/hqdefault.jpg' : kingdom_image( 'kingdom_gallery_1' ),
				'url'   => 'youtube' === $src['type'] ? 'https://www.youtube.com/watch?v=' . $src['id'] : 'https://vimeo.com/' . $src['id'],
			);
		}
	}
	if ( ! $items && post_type_exists( 'kcs_sermon' ) ) {
		$q = get_posts( array( 'post_type' => 'kcs_sermon', 'posts_per_page' => $limit * 3, 'meta_key' => '_kcs_video_url', 'meta_compare' => '!=', 'meta_value' => '' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $q as $p ) {
			$src = kingdom_video_source( get_post_meta( $p->ID, '_kcs_video_url', true ) );
			if ( $src && 'youtube' === $src['type'] ) {
				$items[] = array( 'title' => get_the_title( $p ), 'type' => 'youtube', 'id' => $src['id'], 'thumb' => 'https://i.ytimg.com/vi/' . $src['id'] . '/hqdefault.jpg', 'url' => get_permalink( $p ) );
			}
		}
	}
	return array_slice( $items, 0, $limit );
}

/**
 * Audio tracks for the Audio section: the church's own list, else the latest sermon recordings.
 *
 * @param int $limit Max items.
 * @return array Items of array( title, src, meta, url ).
 */
function kingdom_audio_tracks( $limit = 4 ) {
	$items = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$src = esc_url_raw( (string) get_theme_mod( 'kingdom_audio_' . $i . '_url', '' ) );
		if ( $src ) {
			$title   = get_theme_mod( 'kingdom_audio_' . $i . '_title', '' );
			$items[] = array( 'title' => $title ? $title : wp_basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ), 'src' => $src, 'meta' => '', 'url' => '' );
		}
	}
	if ( ! $items && post_type_exists( 'kcs_sermon' ) ) {
		$q = get_posts( array( 'post_type' => 'kcs_sermon', 'posts_per_page' => $limit, 'meta_key' => '_kcs_audio_url', 'meta_compare' => '!=', 'meta_value' => '' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $q as $p ) {
			$speakers = wp_get_post_terms( $p->ID, 'kcs_speaker', array( 'fields' => 'names' ) );
			$items[]  = array(
				'title' => get_the_title( $p ),
				'src'   => get_post_meta( $p->ID, '_kcs_audio_url', true ),
				'meta'  => trim( ( is_array( $speakers ) && $speakers ? $speakers[0] . ' · ' : '' ) . get_the_date( '', $p ) ),
				'url'   => get_permalink( $p ),
			);
		}
	}
	return array_slice( $items, 0, $limit );
}

/**
 * Embed URL for a podcast link (Spotify, SoundCloud or Apple Podcasts).
 *
 * @param string $url Link.
 * @return array{src: string, height: int}|null
 */
function kingdom_podcast_embed( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '~open\.spotify\.com/(episode|show|playlist)/([A-Za-z0-9]+)~', $url, $m ) ) {
		return array( 'src' => 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2], 'height' => 'episode' === $m[1] ? 152 : 352 );
	}
	if ( false !== strpos( $url, 'soundcloud.com/' ) ) {
		return array( 'src' => 'https://w.soundcloud.com/player/?url=' . rawurlencode( $url ) . '&color=%23ff5500&visual=false', 'height' => 166 );
	}
	if ( false !== strpos( $url, 'podcasts.apple.com/' ) ) {
		return array( 'src' => str_replace( 'podcasts.apple.com', 'embed.podcasts.apple.com', $url ), 'height' => 175 );
	}
	return null;
}

/**
 * Run a plugin shortcode, or show a helpful notice to admins when the plugin is missing.
 *
 * @param string $shortcode Shortcode string.
 * @return string
 */
function kingdom_shortcode( $shortcode ) {
	if ( kingdom_plugin_active() ) {
		return do_shortcode( $shortcode );
	}
	if ( current_user_can( 'activate_plugins' ) ) {
		return '<div class="k-notice">' . esc_html__( 'Activate the Ministry OS Church Suite plugin to show this feature.', 'kingdom' ) . '</div>';
	}
	return '';
}

/**
 * Weekly services (from the plugin schedule, or the Customizer fallback).
 *
 * @return array[] day, time (display), name.
 */
function kingdom_service_times() {
	$out = array();
	if ( kingdom_plugin_active() ) {
		foreach ( KCS_Livestream::schedule() as $s ) {
			$out[] = array(
				'day'  => date_i18n( 'l', strtotime( $s['day'] ) ),
				'time' => date_i18n( get_option( 'time_format' ), strtotime( $s['time'] ) ),
				'name' => $s['name'],
			);
		}
	}
	if ( ! $out ) {
		$raw = (string) get_theme_mod( 'kingdom_service_times', '' );
		if ( 0 === strpos( str_replace( "\r", '', trim( $raw ) ), "Sunday|8:00 AM|First Service\nSunday|10:30 AM|Celebration Service" ) ) {
			$raw = ''; // The old sample, not the church's times.
		}
		foreach ( explode( "\n", $raw ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) );
			if ( count( $p ) >= 2 ) {
				$out[] = array( 'day' => $p[0], 'time' => $p[1], 'name' => $p[2] ?? '' );
			}
		}
	}
	return $out;
}

/**
 * Social profile URLs.
 *
 * @return array network => url.
 */
function kingdom_social_links() {
	$out = array();
	foreach ( array( 'facebook', 'instagram', 'youtube', 'x', 'tiktok', 'whatsapp', 'telegram' ) as $net ) {
		$url = get_theme_mod( 'kingdom_social_' . $net );
		if ( ! $url && kingdom_plugin_active() && ! in_array( $net, array( 'whatsapp', 'telegram' ), true ) ) {
			$url = kcs_get_option( 'social_' . $net );
		}
		if ( $url ) {
			$out[ $net ] = $url;
		}
	}
	return $out;
}

/**
 * Inline SVG icon.
 *
 * @param string $name Icon.
 * @return string
 */
function kingdom_icon( $name ) {
	$paths = array(
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v8h4v-8h3l1-4h-4V8a0 0 0 0 1 0 0z"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
		'youtube'   => '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .4-3.8 31 31 0 0 0-.4-3.8z"/><path d="m10 15 5-3-5-3z" fill="currentColor"/>',
		'x'         => '<path d="M4 4l16 16M20 4 4 20"/>',
		'tiktok'    => '<path d="M15 3v10.5a3.5 3.5 0 1 1-3.5-3.5M15 3c.5 2.5 2.5 4 5 4"/>',
		'whatsapp'  => '<path d="M3 21l1.6-4.6A8.5 8.5 0 1 1 8 19.6z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1.2-1.2-1.7-1-1 .7a4 4 0 0 1-2-2l.7-1-1-1.7z"/>',
		'telegram'  => '<path d="M21 4 3 11l6 2 2 6 3-4 5 4z"/><path d="m9 13 8-6"/>',
		'home'      => '<path d="M3 11 12 4l9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		'palette'   => '<path d="M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.5 0-1-.8-1.3-.8-2.2 0-.8.6-1.3 1.4-1.3H16a5 5 0 0 0 5-5C21 6.6 17 3 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
		'play'      => '<path d="M8 5v14l11-7z" fill="currentColor"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'pin'       => '<path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
		'phone'     => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
		'heart'     => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11z"/>',
		'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'      => '<path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/>',
		'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
		'chevron-left'  => '<path d="m15 6-6 6 6 6"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		'headphones' => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><rect x="3" y="14" width="4" height="7" rx="1"/><rect x="17" y="14" width="4" height="7" rx="1"/>',
		'book'      => '<path d="M4 4h6a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H4zM20 4h-6a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h7z"/>',
		'download'  => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
		'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
		'users'     => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 9M22 21a7 7 0 0 0-4-6.3"/>',
		'hands'     => '<path d="M12 21V11l-3-6a1.5 1.5 0 0 0-2.7 1.3L8 11l-3 4v6M12 21V11l3-6a1.5 1.5 0 0 1 2.7 1.3L16 11l3 4v6"/>',
		'car'       => '<path d="M5 17H3v-5l2-5h14l2 5v5h-2M5 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0M15 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0M9 17h6M3 12h18"/>',
		'child'     => '<circle cx="12" cy="5" r="3"/><path d="M12 8v7M8 11h8M10 21l2-6 2 6"/>',
		'coffee'    => '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5zM17 9h1a3 3 0 0 1 0 6h-1M8 2v3M12 2v3"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="k-icon k-icon--' . esc_attr( $name ) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Section heading.
 *
 * @param string $eyebrow Small label.
 * @param string $title   Title.
 * @param string $text    Intro.
 * @param string $align   center|left.
 */
function kingdom_section_heading( $eyebrow, $title, $text = '', $align = 'center' ) {
	echo '<header class="k-section__head k-section__head--' . esc_attr( $align ) . '" data-reveal>';
	if ( $eyebrow ) {
		echo '<span class="k-eyebrow">' . esc_html( $eyebrow ) . '</span>';
	}
	if ( $title ) {
		echo '<h2 class="k-section__title">' . esc_html( $title ) . '</h2>';
	}
	if ( $text ) {
		echo '<p class="k-section__text">' . esc_html( $text ) . '</p>';
	}
	echo '</header>';
}

/**
 * Inner page header with title & breadcrumbs.
 *
 * @param string $title    Title.
 * @param string $subtitle Subtitle.
 * @param string $image    Background image URL.
 */
function kingdom_page_header( $title = '', $subtitle = '', $image = '' ) {
	if ( ! $title ) {
		if ( is_singular() ) {
			$title = get_the_title();
		} elseif ( is_search() ) {
			/* translators: %s: query */
			$title = sprintf( __( 'Search results for “%s”', 'kingdom' ), get_search_query() );
		} elseif ( is_404() ) {
			$title = __( 'Page not found', 'kingdom' );
		} elseif ( is_home() ) {
			$title = get_the_title( get_option( 'page_for_posts' ) ) ? get_the_title( get_option( 'page_for_posts' ) ) : __( 'News', 'kingdom' );
		} else {
			$title = wp_strip_all_tags( get_the_archive_title() );
		}
	}
	if ( ! $subtitle && is_singular() && has_excerpt() && is_page() ) {
		$subtitle = get_the_excerpt();
	}
	if ( ! $subtitle && ( is_archive() ) ) {
		$subtitle = wp_strip_all_tags( get_the_archive_description() );
	}
	if ( ! $image && is_singular() && has_post_thumbnail() && is_page() ) {
		$image = get_the_post_thumbnail_url( null, 'kingdom-hero' );
	}
	if ( ! $image ) {
		$image = get_theme_mod( 'kingdom_page_header_image', '' );
	}
	?>
	<section class="k-page-header<?php echo $image ? ' has-image' : ''; ?>"<?php echo $image ? ' style="--k-img:url(' . esc_url( $image ) . ')"' : ''; ?>>
		<div class="k-container">
			<?php kingdom_breadcrumbs(); ?>
			<h1 class="k-page-header__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $subtitle ) : ?><p class="k-page-header__subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Breadcrumbs (plugin SEO breadcrumbs when available).
 */
function kingdom_breadcrumbs() {
	if ( is_front_page() || ! get_theme_mod( 'kingdom_breadcrumbs', true ) ) {
		return;
	}
	if ( function_exists( 'kcs_breadcrumbs' ) ) {
		kcs_breadcrumbs();
		return;
	}
	echo '<nav class="kcs-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'kingdom' ) . '"><ol><li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'kingdom' ) . '</a></li><li><span aria-current="page">' . esc_html( wp_strip_all_tags( is_singular() ? get_the_title() : get_the_archive_title() ) ) . '</span></li></ol></nav>';
}

/**
 * Post meta line.
 */
function kingdom_posted_on() {
	echo '<div class="k-meta"><time datetime="' . esc_attr( get_the_date( DATE_ATOM ) ) . '">' . esc_html( get_the_date() ) . '</time>';
	echo '<span>' . esc_html( get_the_author() ) . '</span>';
	$minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) );
	/* translators: %d: minutes */
	echo '<span>' . esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'kingdom' ), $minutes ) ) . '</span></div>';
}

/**
 * YouTube ID from a URL.
 *
 * @param string $url URL.
 * @return string
 */
function kingdom_youtube_id( $url ) {
	if ( class_exists( 'KCS_Post_Types' ) ) {
		return KCS_Post_Types::youtube_id( $url );
	}
	return preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|live/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ? $m[1] : '';
}

/**
 * Sermon thumbnail URL (featured image or YouTube still).
 *
 * @param int $post_id Sermon.
 * @return string
 */
function kingdom_sermon_thumb( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, 'kingdom-card' );
	}
	$yt = kingdom_youtube_id( get_post_meta( $post_id, '_kcs_video_url', true ) );
	return $yt ? 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' : '';
}

/**
 * Event date badge.
 *
 * @param int $post_id Event.
 */
function kingdom_event_badge( $post_id = 0 ) {
	$start = get_post_meta( $post_id ? $post_id : get_the_ID(), '_kcs_start', true );
	if ( ! $start ) {
		return;
	}
	$ts = strtotime( $start );
	echo '<span class="k-date-badge"><b>' . esc_html( date_i18n( 'd', $ts ) ) . '</b><small>' . esc_html( date_i18n( 'M', $ts ) ) . '</small></span>';
}

/**
 * Event date string.
 *
 * @param int $post_id Event.
 * @return string
 */
function kingdom_event_date( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( class_exists( 'KCS_Post_Types' ) ) {
		return KCS_Post_Types::format_event_date( $post_id );
	}
	$start = get_post_meta( $post_id, '_kcs_start', true );
	return $start ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) ) : '';
}

/**
 * Pagination.
 */
function kingdom_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => '←',
			'next_text' => '→',
			'class'     => 'k-pagination',
		)
	);
}

/**
 * Share links for the current post.
 */
function kingdom_share_links() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	$links = array(
		'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
		'x'        => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
		'whatsapp' => 'https://wa.me/?text=' . $title . '%20' . $url,
	);
	echo '<div class="k-share"><span>' . esc_html__( 'Share', 'kingdom' ) . '</span>';
	foreach ( $links as $net => $href ) {
		echo '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( ucfirst( $net ) ) . '">' . kingdom_icon( $net ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '<button type="button" class="k-share__copy" data-kcs-share aria-label="' . esc_attr__( 'Copy link', 'kingdom' ) . '">🔗</button></div>';
}

/**
 * A menu location as a tree, for the mobile menu's sliding panels.
 *
 * @param string $location Theme menu location.
 * @return array[] id, title, url, target, current, ancestor, children.
 */
function kingdom_menu_tree( $location = 'primary' ) {
	$items     = array();
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations[ $location ] ) ) {
		$menu_items = wp_get_nav_menu_items( $locations[ $location ], array( 'update_post_term_cache' => false ) );
		if ( $menu_items ) {
			// Adds current-menu-item / current-menu-ancestor, as wp_nav_menu() does.
			_wp_menu_item_classes_by_context( $menu_items );
			foreach ( $menu_items as $m ) {
				$classes      = (array) $m->classes;
				$items[ (int) $m->ID ] = array(
					'id'       => (int) $m->ID,
					'parent'   => (int) $m->menu_item_parent,
					'title'    => wp_strip_all_tags( apply_filters( 'nav_menu_item_title', $m->title, $m, array(), 0 ) ),
					'url'      => $m->url,
					'target'   => $m->target,
					'current'  => (bool) array_intersect( $classes, array( 'current-menu-item', 'current_page_item' ) ),
					'ancestor' => (bool) array_intersect( $classes, array( 'current-menu-ancestor', 'current-menu-parent', 'current_page_ancestor' ) ),
					'children' => array(),
				);
			}
		}
	}
	if ( ! $items ) {
		// No menu yet: Home, then the site's pages (minus system pages), like the desktop fallback.
		$current    = is_page() ? get_queried_object_id() : 0;
		$items[ -1 ] = array(
			'id'       => -1,
			'parent'   => 0,
			'title'    => __( 'Home', 'kingdom' ),
			'url'      => home_url( '/' ),
			'target'   => '',
			'current'  => is_front_page(),
			'ancestor' => false,
			'children' => array(),
		);
		foreach ( get_pages( array( 'sort_column' => 'menu_order,post_title', 'exclude' => kingdom_hidden_menu_pages() ) ) as $pg ) {
			if ( (int) get_option( 'page_on_front' ) === (int) $pg->ID ) {
				continue;
			}
			$items[ (int) $pg->ID ] = array(
				'id'       => (int) $pg->ID,
				'parent'   => (int) $pg->post_parent,
				'title'    => get_the_title( $pg ),
				'url'      => get_permalink( $pg ),
				'target'   => '',
				'current'  => $current === (int) $pg->ID,
				'ancestor' => $current && in_array( (int) $pg->ID, get_post_ancestors( $current ), true ),
				'children' => array(),
			);
		}
	}
	// Build the tree (children keep menu order).
	$tree = array();
	foreach ( array_keys( $items ) as $id ) {
		$parent = $items[ $id ]['parent'];
		if ( $parent && isset( $items[ $parent ] ) ) {
			$items[ $parent ]['children'][] = &$items[ $id ];
		} else {
			$tree[] = &$items[ $id ];
		}
	}
	return $tree;
}

/**
 * One row: the link, plus a boxed arrow that opens its sub-menu.
 *
 * @param array  $item  Menu item.
 * @param string $panel Panel the row is in.
 */
function kingdom_drawer_item( $item, $panel ) {
	$classes = 'k-drawer__item' . ( $item['current'] ? ' is-current' : '' ) . ( $item['ancestor'] ? ' is-ancestor' : '' );
	$sub     = 'k-drawer-p-' . (int) $item['id'];
	echo '<li class="' . esc_attr( $classes ) . '">';
	if ( $item['children'] && ( ! $item['url'] || '#' === $item['url'] ) ) {
		// A heading-only item: the whole row opens the sub-menu.
		echo '<button type="button" class="k-drawer__link" data-drawer-open="' . esc_attr( $sub ) . '" aria-controls="' . esc_attr( $sub ) . '" aria-expanded="false">' . esc_html( $item['title'] ) . '</button>';
	} else {
		echo '<a class="k-drawer__link" href="' . esc_url( $item['url'] ) . '"' . ( $item['target'] ? ' target="' . esc_attr( $item['target'] ) . '" rel="noopener"' : '' ) . ( $item['current'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['title'] ) . '</a>';
	}
	if ( $item['children'] ) {
		/* translators: %s: menu item, e.g. "Ministries" */
		echo '<button type="button" class="k-drawer__more" data-drawer-open="' . esc_attr( $sub ) . '" aria-controls="' . esc_attr( $sub ) . '" aria-expanded="false" aria-label="' . esc_attr( sprintf( __( 'Open %s menu', 'kingdom' ), $item['title'] ) ) . '">' . kingdom_icon( 'chevron-right' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</li>';
}

/**
 * Mobile menu: one sub-menu panel (and, recursively, its own sub-menus).
 *
 * @param array  $item   Menu item with children.
 * @param string $parent Panel to go back to.
 */
function kingdom_drawer_panel( $item, $parent ) {
	$id = 'k-drawer-p-' . (int) $item['id'];
	echo '<section class="k-drawer__panel" id="' . esc_attr( $id ) . '" data-drawer-panel data-parent="' . esc_attr( $parent ) . '" aria-label="' . esc_attr( $item['title'] ) . '">';
	echo '<button type="button" class="k-drawer__back" data-drawer-back>' . kingdom_icon( 'chevron-left' ) . '<span>' . esc_html__( 'Back', 'kingdom' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<p class="k-drawer__panel-title">' . esc_html( $item['title'] ) . '</p><ul class="k-drawer__menu">';
	if ( $item['url'] && '#' !== $item['url'] ) {
		$overview = $item;
		/* translators: %s: menu item, e.g. "Ministries" */
		$overview['title']    = sprintf( __( 'All %s', 'kingdom' ), $item['title'] );
		$overview['children'] = array();
		kingdom_drawer_item( $overview, $id );
	}
	foreach ( $item['children'] as $child ) {
		kingdom_drawer_item( $child, $id );
	}
	echo '</ul></section>';
	foreach ( $item['children'] as $child ) {
		if ( $child['children'] ) {
			kingdom_drawer_panel( $child, $id );
		}
	}
}

/**
 * Fallback for the primary menu: list published pages.
 */
function kingdom_fallback_menu() {
	$exclude = kingdom_hidden_menu_pages();
	if ( get_option( 'page_on_front' ) ) {
		$exclude[] = (int) get_option( 'page_on_front' );
	}
	echo '<ul class="k-menu"><li class="page_item' . ( is_front_page() ? ' current_page_item' : '' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'kingdom' ) . '</a></li>';
	wp_list_pages( array( 'title_li' => '', 'depth' => 2, 'exclude' => implode( ',', $exclude ) ) );
	echo '</ul>';
}

/**
 * Pages left out of the automatic menu (used until the church builds its own menu): system pages
 * such as Thank You, Church Admin, Partner Login and Partner Dashboard, plus WordPress's sample page.
 * Staff and partners reach their dashboards from the buttons in the menu instead.
 *
 * @return int[]
 */
function kingdom_hidden_menu_pages() {
	$ids = array();
	if ( kingdom_plugin_active() ) {
		foreach ( array( 'thank_you', 'church_admin', 'partner_dashboard', 'partner_login' ) as $key ) {
			$ids[] = (int) kcs_get_option( 'page_' . $key, 0 );
		}
	}
	global $wpdb;
	// WordPress's "Sample Page" (and copies such as sample-page-2).
	$ids = array_merge( $ids, array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name REGEXP '^sample-page(-[0-9]+)?$'" ) ) );
	$ids[] = (int) get_option( 'wp_page_for_privacy_policy' );
	return array_values( array_filter( array_unique( (array) apply_filters( 'kingdom_hidden_menu_pages', $ids ) ) ) );
}

/**
 * Does the current page's content already contain a shortcode?
 *
 * Plugin-created pages (Give, Partnership, Watch Live…) already hold the
 * shortcode, so templates use this to avoid rendering a feature twice.
 *
 * @param string $tag Shortcode tag.
 * @return bool
 */
function kingdom_content_has( $tag ) {
	$post = get_post();
	return $post && has_shortcode( $post->post_content, $tag );
}

/**
 * Render a feature shortcode unless the page content already includes it.
 *
 * @param string $tag       Shortcode tag.
 * @param string $shortcode Full shortcode.
 */
function kingdom_feature( $tag, $shortcode ) {
	if ( ! kingdom_content_has( $tag ) ) {
		echo kingdom_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Built-in colour palettes: slug => [label, primary, accent, dark].
 *
 * @return array
 */
function kingdom_palettes() {
	return apply_filters(
		'kingdom_palettes',
		array(
			'royal'    => array( __( 'Royal Purple', 'kingdom' ), '#5b2a86', '#f4a62a', '#16121c' ),
			'ocean'    => array( __( 'Ocean Blue', 'kingdom' ), '#1d4ed8', '#38bdf8', '#0b1220' ),
			'emerald'  => array( __( 'Emerald Grace', 'kingdom' ), '#047857', '#fbbf24', '#0a1a14' ),
			'crimson'  => array( __( 'Crimson Fire', 'kingdom' ), '#b91c1c', '#f59e0b', '#1a0b0b' ),
			'sunrise'  => array( __( 'Sunrise', 'kingdom' ), '#c2410c', '#facc15', '#1c0f08' ),
			'midnight' => array( __( 'Midnight', 'kingdom' ), '#334155', '#22d3ee', '#020617' ),
			'rose'     => array( __( 'Rose of Sharon', 'kingdom' ), '#be185d', '#f9a8d4', '#1f0a14' ),
			'jordan'   => array( __( 'River Jordan', 'kingdom' ), '#0f766e', '#f97316', '#071a18' ),
			'heritage' => array( __( 'Heritage Gold', 'kingdom' ), '#7c2d12', '#d4a017', '#1a120b' ),
		)
	);
}

/**
 * Active brand colours (palette or custom pickers).
 *
 * @return array [primary, accent, dark]
 */
function kingdom_brand_colors() {
	$palettes = kingdom_palettes();
	$choice   = get_theme_mod( 'kingdom_palette', 'royal' );
	if ( 'custom' !== $choice && isset( $palettes[ $choice ] ) ) {
		return array( $palettes[ $choice ][1], $palettes[ $choice ][2], $palettes[ $choice ][3] );
	}
	return array(
		// An emptied colour falls back to the default instead of breaking the colour scheme.
		sanitize_hex_color( get_theme_mod( 'kingdom_color_primary', '#5b2a86' ) ) ? sanitize_hex_color( get_theme_mod( 'kingdom_color_primary', '#5b2a86' ) ) : '#5b2a86',
		sanitize_hex_color( get_theme_mod( 'kingdom_color_accent', '#f4a62a' ) ) ? sanitize_hex_color( get_theme_mod( 'kingdom_color_accent', '#f4a62a' ) ) : '#f4a62a',
		sanitize_hex_color( get_theme_mod( 'kingdom_color_dark', '#16121c' ) ) ? sanitize_hex_color( get_theme_mod( 'kingdom_color_dark', '#16121c' ) ) : '#16121c',
	);
}

/**
 * Default church images (bundled illustrations) keyed by Customizer setting.
 *
 * @return array setting => [label, bundled file, description]
 */
function kingdom_image_slots() {
	return array(
		'kingdom_hero_image'    => array( __( 'Homepage hero', 'kingdom' ), 'worship.svg', __( 'Worship / congregation photo. 1920×1080 or larger.', 'kingdom' ) ),
		'kingdom_welcome_image' => array( __( 'Pastor / welcome photo', 'kingdom' ), 'pastor.svg', __( 'Portrait of your lead pastor(s). 900×1100.', 'kingdom' ) ),
		'kingdom_church_image'  => array( __( 'Church building', 'kingdom' ), 'church-building.svg', __( 'Used on Visit us and About. 1600×1000.', 'kingdom' ) ),
		'kingdom_stats_image'   => array( __( 'Stats background', 'kingdom' ), 'choir.svg', __( 'A wide, busy photo works best (it is darkened).', 'kingdom' ) ),
		'kingdom_gallery_1'     => array( __( 'Gallery photo 1', 'kingdom' ), 'worship.svg', '' ),
		'kingdom_gallery_2'     => array( __( 'Gallery photo 2', 'kingdom' ), 'choir.svg', '' ),
		'kingdom_gallery_3'     => array( __( 'Gallery photo 3', 'kingdom' ), 'community.svg', '' ),
		'kingdom_gallery_4'     => array( __( 'Gallery photo 4', 'kingdom' ), 'kids.svg', '' ),
		'kingdom_gallery_5'     => array( __( 'Gallery photo 5', 'kingdom' ), 'pastor.svg', '' ),
		'kingdom_gallery_6'     => array( __( 'Gallery photo 6', 'kingdom' ), 'church-building.svg', '' ),
	);
}

/**
 * Image URL for a slot: the church's uploaded photo, else the bundled illustration.
 *
 * @param string $slot Setting name from kingdom_image_slots().
 * @return string
 */
function kingdom_image( $slot ) {
	$custom = get_theme_mod( $slot );
	if ( $custom ) {
		return $custom;
	}
	$slots = kingdom_image_slots();
	return isset( $slots[ $slot ] ) ? KINGDOM_URI . '/assets/img/' . $slots[ $slot ][1] : '';
}

/**
 * Is this page an app-style dashboard (partner or church admin)?
 *
 * @return string ''|partner|admin
 */
function kingdom_dashboard_type() {
	if ( ! is_singular() ) {
		return '';
	}
	$post = get_post();
	if ( ! $post ) {
		return '';
	}
	if ( has_shortcode( $post->post_content, 'kcs_church_admin' ) ) {
		return 'admin';
	}
	if ( has_shortcode( $post->post_content, 'kcs_partner_dashboard' ) || 'page-templates/template-partner-portal.php' === get_page_template_slug( $post ) ) {
		return 'partner';
	}
	return '';
}

/**
 * Website WhatsApp chat widget settings, or null when it should not show.
 *
 * Managed in Church Admin → Pastors & WhatsApp (plugin). Without the plugin, the
 * WhatsApp link from Customize → Social Profiles is used as a single contact.
 *
 * @return array|null title, greeting, hours, contacts[] (label, url).
 */
function kingdom_whatsapp_chat() {
	if ( kingdom_dashboard_type() ) {
		return null;
	}
	$page   = wp_strip_all_tags( wp_get_document_title() );
	$theme  = get_theme_mod( 'kingdom_social_whatsapp' );
	$digits = $theme ? preg_replace( '/\D+/', '', $theme ) : '';
	if ( class_exists( 'KCS_WhatsApp_Chat' ) ) {
		$cfg = KCS_WhatsApp_Chat::get();
		if ( empty( $cfg['enabled'] ) ) {
			return null;
		}
		$contacts = $cfg['contacts'];
		if ( ! $contacts && $digits ) {
			$contacts = array( array( 'label' => __( 'Church office', 'kingdom' ), 'phone' => $digits, 'message' => '' ) );
		}
		if ( ! $contacts ) {
			return null;
		}
		$cfg['contacts'] = array_map(
			function ( $c ) use ( $page ) {
				return array( 'label' => $c['label'], 'url' => KCS_WhatsApp_Chat::link( $c, $page ) );
			},
			$contacts
		);
		return $cfg;
	}
	if ( ! $digits || ! get_theme_mod( 'kingdom_whatsapp_float', true ) ) {
		return null;
	}
	return array(
		'title'    => get_bloginfo( 'name' ),
		'greeting' => __( 'Hello! 👋 How can we help you today?', 'kingdom' ),
		'hours'    => '',
		/* translators: %s: site name */
		'contacts' => array( array( 'label' => __( 'Church office', 'kingdom' ), 'url' => 'https://wa.me/' . $digits . '?text=' . rawurlencode( sprintf( __( 'Hello %s, I found you on your website.', 'kingdom' ), get_bloginfo( 'name' ) ) ) ) ),
	);
}

/**
 * Header logo sizes (height on computers; phones use 80%).
 *
 * @param bool $px Return pixel heights instead of labels.
 * @return array
 */
function kingdom_logo_sizes( $px = false ) {
	$h = array( 'small' => 44, 'medium' => 60, 'large' => 76, 'xlarge' => 92 );
	if ( $px ) {
		return $h;
	}
	return array(
		'small'  => __( 'Small', 'kingdom' ),
		'medium' => __( 'Medium', 'kingdom' ),
		'large'  => __( 'Large', 'kingdom' ),
		'xlarge' => __( 'Extra large (logos with text under the picture)', 'kingdom' ),
	);
}

/**
 * Church Admin → Visit page → "Ask which service" switches the service question on the visit form.
 */
add_filter(
	'kcs_visit_form_service',
	function ( $ask ) {
		return $ask && (bool) get_theme_mod( 'kingdom_vp_ask_service', true );
	}
);

/**
 * Emails typed in Church Admin (comma separated) as a list.
 *
 * @param string $mod Theme setting.
 * @return array
 */
function kingdom_setting_emails( $mod ) {
	return array_values( array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) get_theme_mod( $mod, '' ) ) ), 'is_email' ) );
}

/**
 * Church Admin → Visit page / Contact & social: where the forms are sent.
 */
add_filter(
	'kcs_visit_recipients',
	function ( $emails ) {
		$set = kingdom_setting_emails( 'kingdom_vp_form_to' );
		return $set ? $set : $emails;
	}
);
add_filter(
	'kcs_contact_recipients',
	function ( $emails ) {
		$set = kingdom_setting_emails( 'kingdom_contact_to' );
		return $set ? $set : $emails;
	}
);

/**
 * The Google map to show: the embed the church pasted, else (when allowed) a map of its address.
 *
 * @return string Embed URL or ''.
 */
function kingdom_map_src() {
	$map = trim( (string) get_theme_mod( 'kingdom_map', '' ) );
	if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $map, $m ) ) {
		$map = html_entity_decode( $m[1] );
	}
	if ( 0 === strpos( $map, 'https://' ) ) {
		return $map;
	}
	$address = trim( (string) get_theme_mod( 'kingdom_address', '' ) );
	if ( $address && get_theme_mod( 'kingdom_contact_map_auto', true ) ) {
		return 'https://www.google.com/maps?q=' . rawurlencode( preg_replace( '/\s+/', ' ', $address ) ) . '&output=embed';
	}
	return '';
}
