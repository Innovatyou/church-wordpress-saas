<?php
/**
 * Theme supports, menus, widgets and assets.
 *
 * @package Kingdom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme setup.
 */
function kingdom_setup() {
	load_theme_textdomain( 'kingdom', KINGDOM_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 260, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'kingdom-card', 720, 450, true );
	add_image_size( 'kingdom-hero', 1920, 1080, true );
	add_image_size( 'kingdom-portrait', 600, 720, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'kingdom' ),
			'topbar'  => __( 'Top bar menu', 'kingdom' ),
			'footer'  => __( 'Footer menu', 'kingdom' ),
		)
	);
}
add_action( 'after_setup_theme', 'kingdom_setup' );

/**
 * Content width.
 */
function kingdom_content_width() {
	$GLOBALS['content_width'] = 780; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}
add_action( 'after_setup_theme', 'kingdom_content_width', 0 );

/**
 * Widget areas.
 */
function kingdom_widgets() {
	$common = array(
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	);
	register_sidebar( array_merge( $common, array( 'id' => 'sidebar-1', 'name' => __( 'Sidebar', 'kingdom' ) ) ) );
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: column number */
		register_sidebar( array_merge( $common, array( 'id' => 'footer-' . $i, 'name' => sprintf( __( 'Footer column %d', 'kingdom' ), $i ) ) ) );
	}
}
add_action( 'widgets_init', 'kingdom_widgets' );

/**
 * Font library: name => array( Google Fonts family spec, style ).
 * Styles: serif (classic, traditional), sans (clean, modern), display (headings only), system.
 *
 * @return array
 */
function kingdom_font_library() {
	return apply_filters(
		'kingdom_fonts',
		array(
			// Classic & traditional (serif).
			'Playfair Display'   => array( 'Playfair+Display:wght@500;600;700;800', 'serif' ),
			'Cormorant Garamond' => array( 'Cormorant+Garamond:wght@500;600;700', 'serif' ),
			'Lora'               => array( 'Lora:wght@400;500;600;700', 'serif' ),
			'Merriweather'       => array( 'Merriweather:wght@400;700;900', 'serif' ),
			'Libre Baskerville'  => array( 'Libre+Baskerville:wght@400;700', 'serif' ),
			'EB Garamond'        => array( 'EB+Garamond:wght@400;500;600;700;800', 'serif' ),
			'Cinzel'             => array( 'Cinzel:wght@400;500;600;700;800', 'serif' ),
			'Crimson Pro'        => array( 'Crimson+Pro:wght@400;500;600;700', 'serif' ),
			'DM Serif Display'   => array( 'DM+Serif+Display', 'serif' ),
			'Fraunces'           => array( 'Fraunces:wght@400;500;600;700;800', 'serif' ),
			'Marcellus'          => array( 'Marcellus', 'serif' ),
			'Prata'              => array( 'Prata', 'serif' ),
			'Spectral'           => array( 'Spectral:wght@400;500;600;700', 'serif' ),
			'Noto Serif'         => array( 'Noto+Serif:wght@400;500;600;700', 'serif' ),
			// Clean & modern (sans-serif).
			'Inter'              => array( 'Inter:wght@400;500;600;700;800', 'sans' ),
			'Poppins'            => array( 'Poppins:wght@400;500;600;700;800', 'sans' ),
			'Montserrat'         => array( 'Montserrat:wght@400;500;600;700;800', 'sans' ),
			'DM Sans'            => array( 'DM+Sans:wght@400;500;700', 'sans' ),
			'Plus Jakarta Sans'  => array( 'Plus+Jakarta+Sans:wght@400;500;600;700;800', 'sans' ),
			'Open Sans'          => array( 'Open+Sans:wght@400;500;600;700;800', 'sans' ),
			'Lato'               => array( 'Lato:wght@400;700;900', 'sans' ),
			'Nunito'             => array( 'Nunito:wght@400;500;600;700;800', 'sans' ),
			'Raleway'            => array( 'Raleway:wght@400;500;600;700;800', 'sans' ),
			'Work Sans'          => array( 'Work+Sans:wght@400;500;600;700;800', 'sans' ),
			'Source Sans 3'      => array( 'Source+Sans+3:wght@400;500;600;700;800', 'sans' ),
			'Manrope'            => array( 'Manrope:wght@400;500;600;700;800', 'sans' ),
			'Outfit'             => array( 'Outfit:wght@400;500;600;700;800', 'sans' ),
			'Figtree'            => array( 'Figtree:wght@400;500;600;700;800', 'sans' ),
			'Mulish'             => array( 'Mulish:wght@400;500;600;700;800', 'sans' ),
			'Josefin Sans'       => array( 'Josefin+Sans:wght@400;500;600;700', 'sans' ),
			'Rubik'              => array( 'Rubik:wght@400;500;600;700;800', 'sans' ),
			'Urbanist'           => array( 'Urbanist:wght@400;500;600;700;800', 'sans' ),
			'Noto Sans'          => array( 'Noto+Sans:wght@400;500;600;700;800', 'sans' ),
			// Bold & expressive (headings).
			'Bebas Neue'         => array( 'Bebas+Neue', 'display' ),
			'Oswald'             => array( 'Oswald:wght@400;500;600;700', 'display' ),
			'Anton'              => array( 'Anton', 'display' ),
			'Great Vibes'        => array( 'Great+Vibes', 'display' ),
			'Dancing Script'     => array( 'Dancing+Script:wght@400;500;600;700', 'display' ),
			// No download: the phone or computer's own font (fastest, nothing sent to Google).
			'System'             => array( '', 'system' ),
		)
	);
}

/**
 * Google font choices: name => family spec (kept for compatibility).
 *
 * @return array
 */
function kingdom_fonts() {
	return array_map(
		function ( $f ) {
			return $f[0];
		},
		kingdom_font_library()
	);
}

/**
 * Font names grouped for dropdowns: label => array( name => name ).
 *
 * @param bool $headings Include heading-only (display) fonts.
 * @return array Flat name => "Name (style)" list.
 */
function kingdom_font_choices( $headings = true ) {
	$styles = array(
		'serif'   => __( 'classic', 'kingdom' ),
		'sans'    => __( 'modern', 'kingdom' ),
		'display' => __( 'bold headings', 'kingdom' ),
		'system'  => __( 'device font, fastest', 'kingdom' ),
	);
	$out    = array();
	foreach ( kingdom_font_library() as $name => $f ) {
		if ( ! $headings && 'display' === $f[1] ) {
			continue;
		}
		$out[ $name ] = sprintf( '%1$s (%2$s)', 'System' === $name ? __( 'Device font', 'kingdom' ) : $name, $styles[ $f[1] ] ?? $f[1] );
	}
	return $out;
}

/**
 * Ready-made font pairings: slug => array( label, heading font, body font ).
 *
 * @return array
 */
function kingdom_font_pairs() {
	return apply_filters(
		'kingdom_font_pairs',
		array(
			'classic'     => array( __( 'Classic — Playfair Display + Inter', 'kingdom' ), 'Playfair Display', 'Inter' ),
			'traditional' => array( __( 'Traditional — Cinzel + Lato', 'kingdom' ), 'Cinzel', 'Lato' ),
			'heritage'    => array( __( 'Heritage — EB Garamond + Source Sans 3', 'kingdom' ), 'EB Garamond', 'Source Sans 3' ),
			'elegant'     => array( __( 'Elegant — Cormorant Garamond + Raleway', 'kingdom' ), 'Cormorant Garamond', 'Raleway' ),
			'warm'        => array( __( 'Warm — Lora + Nunito', 'kingdom' ), 'Lora', 'Nunito' ),
			'editorial'   => array( __( 'Editorial — DM Serif Display + DM Sans', 'kingdom' ), 'DM Serif Display', 'DM Sans' ),
			'modern'      => array( __( 'Modern — Montserrat + Open Sans', 'kingdom' ), 'Montserrat', 'Open Sans' ),
			'friendly'    => array( __( 'Friendly — Poppins + Nunito', 'kingdom' ), 'Poppins', 'Nunito' ),
			'fresh'       => array( __( 'Fresh — Outfit + Figtree', 'kingdom' ), 'Outfit', 'Figtree' ),
			'youth'       => array( __( 'Youth — Bebas Neue + Work Sans', 'kingdom' ), 'Bebas Neue', 'Work Sans' ),
			'bold'        => array( __( 'Bold — Oswald + Rubik', 'kingdom' ), 'Oswald', 'Rubik' ),
			'grace'       => array( __( 'Grace — Fraunces + Manrope', 'kingdom' ), 'Fraunces', 'Manrope' ),
			'simple'      => array( __( 'Simple & fast — device fonts', 'kingdom' ), 'System', 'System' ),
			'custom'      => array( __( 'Custom — choose heading and body fonts below', 'kingdom' ), '', '' ),
		)
	);
}

/**
 * The fonts in use: array( heading, body ), always names from the library.
 *
 * @return array{0: string, 1: string}
 */
function kingdom_active_fonts() {
	$lib   = kingdom_font_library();
	$pairs = kingdom_font_pairs();
	$pair  = get_theme_mod( 'kingdom_font_pair', 'custom' );
	if ( 'custom' !== $pair && isset( $pairs[ $pair ] ) ) {
		$heading = $pairs[ $pair ][1];
		$body    = $pairs[ $pair ][2];
	} else {
		$heading = get_theme_mod( 'kingdom_font_heading', 'Playfair Display' );
		$body    = get_theme_mod( 'kingdom_font_body', 'Inter' );
	}
	return array(
		isset( $lib[ $heading ] ) ? $heading : 'Playfair Display',
		isset( $lib[ $body ] ) && 'display' !== $lib[ $body ][1] ? $body : 'Inter',
	);
}

/**
 * CSS font stack for a library font.
 *
 * @param string $name Font name.
 * @return string
 */
function kingdom_font_stack( $name ) {
	$lib   = kingdom_font_library();
	$sys   = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	$style = $lib[ $name ][1] ?? 'sans';
	if ( 'system' === $style ) {
		return $sys;
	}
	$fallback = array(
		'serif'   => 'Georgia, "Times New Roman", serif',
		'display' => 'Impact, "Arial Narrow", sans-serif',
	);
	return '"' . str_replace( '"', '', $name ) . '", ' . ( $fallback[ $style ] ?? $sys );
}

/**
 * Front-end assets.
 */
function kingdom_assets() {
	$fonts            = kingdom_fonts();
	list( $h, $b )    = kingdom_active_fonts();
	$families         = array_filter( array_unique( array( $fonts[ $h ] ?? '', $fonts[ $b ] ?? '' ) ) );
	if ( $families && 'system' !== get_theme_mod( 'kingdom_font_source', 'google' ) ) {
		wp_enqueue_style( 'kingdom-fonts', 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $families ) . '&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
	wp_enqueue_style( 'kingdom-main', KINGDOM_URI . '/assets/css/main.css', array(), KINGDOM_VERSION );
	wp_add_inline_style( 'kingdom-main', kingdom_dynamic_css() );
	wp_enqueue_script( 'kingdom-main', KINGDOM_URI . '/assets/js/main.js', array(), KINGDOM_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'kingdom_assets' );

/**
 * Preconnect to Google Fonts.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Relation.
 * @return array
 */
function kingdom_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && wp_style_is( 'kingdom-fonts', 'queue' ) ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'kingdom_resource_hints', 10, 2 );

/**
 * CSS variables from the Customizer.
 *
 * @return string
 */
function kingdom_dynamic_css() {
	list( $primary, $accent, $dark ) = kingdom_brand_colors();
	list( $heading, $body ) = kingdom_active_fonts();
	$radius  = array(
		'sharp'   => '4px',
		'rounded' => '14px',
		'pill'    => '24px',
	);
	$r       = $radius[ get_theme_mod( 'kingdom_radius', 'rounded' ) ] ?? '14px';
	$width   = min( 1600, max( 960, absint( get_theme_mod( 'kingdom_container', 1200 ) ) ) );
	$local   = 'system' === get_theme_mod( 'kingdom_font_source', 'google' );
	$hfont   = $local ? kingdom_font_stack( 'System' ) : kingdom_font_stack( $heading );
	$bfont   = $local ? kingdom_font_stack( 'System' ) : kingdom_font_stack( $body );

	$css = ':root{--k-primary:' . $primary . ';--k-on-primary:' . kingdom_on_colour( $primary ) . ';--k-accent:' . $accent . ';--k-dark:' . $dark . ';--k-radius:' . $r . ';--k-container:' . $width . 'px;--k-font-heading:' . $hfont . ';--k-font-body:' . $bfont . ';--k-logo-h:' . ( kingdom_logo_sizes( true )[ get_theme_mod( 'kingdom_logo_size', 'medium' ) ] ?? 60 ) . 'px;}';
	// Visitor colour switcher: each palette can be applied with <html data-palette="slug">.
	if ( get_theme_mod( 'kingdom_palette_switcher', true ) ) {
		foreach ( kingdom_palettes() as $slug => $p ) {
			$css .= ':root[data-palette="' . esc_attr( $slug ) . '"]{--k-primary:' . $p[1] . ';--k-on-primary:' . kingdom_on_colour( $p[1] ) . ';--k-accent:' . $p[2] . ';--k-dark:' . $p[3] . ';}';
		}
	}
	return $css;
}

/**
 * Body classes for layout options.
 *
 * @param array $classes Classes.
 * @return array
 */
function kingdom_body_classes( $classes ) {
	$classes[] = 'header-' . sanitize_html_class( get_theme_mod( 'kingdom_header_style', 'transparent' ) );
	if ( get_theme_mod( 'kingdom_header_sticky', true ) ) {
		$classes[] = 'has-sticky-header';
	}
	if ( kingdom_has_transparent_header() ) {
		$classes[] = 'has-transparent-header';
	}
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}
	if ( get_theme_mod( 'kingdom_3d', true ) ) {
		$classes[] = 'k-3d';
	}
	if ( get_theme_mod( 'kingdom_mobile_nav', true ) ) {
		$classes[] = 'has-mobile-nav';
	}
	$classes[] = 'btn-' . sanitize_html_class( get_theme_mod( 'kingdom_button_style', 'pill' ) );
	if ( kingdom_dashboard_type() ) {
		$classes[] = 'is-dashboard is-dashboard-' . kingdom_dashboard_type();
	}
	return $classes;
}
add_filter( 'body_class', 'kingdom_body_classes' );

/**
 * Transparent header on pages that start with a hero.
 *
 * @return bool
 */
function kingdom_has_transparent_header() {
	if ( 'transparent' !== get_theme_mod( 'kingdom_header_style', 'transparent' ) ) {
		return false;
	}
	$hero_templates = array( 'page-templates/template-home-classic.php', 'page-templates/template-home-modern.php', 'page-templates/template-home-mega.php', 'page-templates/template-home-live.php', 'page-templates/template-home-split.php' );
	return is_front_page() || ( is_page() && in_array( get_page_template_slug(), $hero_templates, true ) );
}

/**
 * Apply the saved colour scheme before paint to avoid a flash.
 */
function kingdom_scheme_script() {
	$default = get_theme_mod( 'kingdom_color_scheme', 'light' );
	?>
	<script>(function(){try{var s=localStorage.getItem('kingdom-scheme')||'<?php echo esc_js( $default ); ?>';if(s==='auto'){s=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',s);<?php if ( get_theme_mod( 'kingdom_palette_switcher', true ) ) : ?>var p=localStorage.getItem('kingdom-palette');if(p){document.documentElement.setAttribute('data-palette',p);}if(p==='custom'){var c=JSON.parse(localStorage.getItem('kingdom-palette-custom')||'{}'),r=document.documentElement.style;if(c.primary){r.setProperty('--k-primary',c.primary);r.setProperty('--k-on-primary',c.on||'#fff');}if(c.accent){r.setProperty('--k-accent',c.accent);}}<?php endif; ?>}catch(e){}})();</script>
	<?php
}
add_action( 'wp_head', 'kingdom_scheme_script', 0 );

/**
 * Drop the "Archives:" / "Category:" prefix from archive titles.
 *
 * @return string
 */
function kingdom_archive_title_prefix() {
	return '';
}
add_filter( 'get_the_archive_title_prefix', 'kingdom_archive_title_prefix' );

/**
 * Excerpt length.
 *
 * @return int
 */
function kingdom_excerpt_length() {
	return 24;
}
add_filter( 'excerpt_length', 'kingdom_excerpt_length' );

/**
 * Excerpt more.
 *
 * @return string
 */
function kingdom_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'kingdom_excerpt_more' );

/**
 * Lazy-load embeds in content and add rel to external links.
 *
 * @param string $html HTML.
 * @return string
 */
function kingdom_lazy_iframes( $html ) {
	return str_replace( '<iframe ', '<iframe loading="lazy" ', $html );
}
add_filter( 'embed_oembed_html', 'kingdom_lazy_iframes' );

/**
 * Admin notice recommending the companion plugin.
 */
function kingdom_plugin_notice() {
	if ( kingdom_plugin_active() || ! current_user_can( 'activate_plugins' ) || get_user_meta( get_current_user_id(), 'kingdom_dismiss_plugin', true ) ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( 'kingdom_dismiss_plugin', 1 ), 'kingdom_dismiss' );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Ministry OS theme:', 'kingdom' ) . '</strong> ' . esc_html__( 'activate the Ministry OS Church Suite plugin to enable partnerships, online giving, live streaming, sermons, events, newsletters and SEO.', 'kingdom' ) . ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go to plugins', 'kingdom' ) . '</a> · <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'kingdom' ) . '</a></p></div>';
}
add_action( 'admin_notices', 'kingdom_plugin_notice' );

/**
 * Handle notice dismissal.
 */
function kingdom_dismiss_notice() {
	if ( isset( $_GET['kingdom_dismiss_plugin'] ) && check_admin_referer( 'kingdom_dismiss' ) ) {
		update_user_meta( get_current_user_id(), 'kingdom_dismiss_plugin', 1 );
	}
}
add_action( 'admin_init', 'kingdom_dismiss_notice' );
