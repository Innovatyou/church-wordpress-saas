<?php
/**
 * Website pages from Church Admin: hide or show any page, create pages (page builder, or HTML &
 * CSS), delete them, and keep old addresses working after a page is renamed.
 *
 * HTML & CSS pages keep their code in post meta and print it on the page, because WordPress strips
 * most HTML from post content for site administrators on a network (only network admins have
 * unfiltered_html). The HTML is still filtered (no scripts), the CSS only loads on that page.
 *
 * @package KingdomChurchSuite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages.
 */
class KCS_Pages {

	const MODE     = '_kcs_page_mode';
	const HTML     = '_kcs_page_html';
	const CSS      = '_kcs_page_css';
	const OLD_SLUG = '_kcs_old_slug';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'html_content' ), 999 );
		add_action( 'wp_head', array( __CLASS__, 'page_css' ), 99 );
		add_filter( 'wp_get_nav_menu_items', array( __CLASS__, 'hide_menu_items' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'old_address' ), 1 );
		add_action( 'init', array( __CLASS__, 'rename_visit_page' ), 30 );
	}

	/* ---------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------- */

	/**
	 * Is this page written in HTML & CSS?
	 *
	 * @param int $id Page ID.
	 * @return bool
	 */
	public static function is_html( $id ) {
		return 'html' === get_post_meta( $id, self::MODE, true );
	}

	/**
	 * Tags allowed in HTML pages: everything allowed in posts, plus embeds (iframes) and inline styles.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$tags           = wp_kses_allowed_html( 'post' );
		$tags['iframe'] = array( 'src' => true, 'width' => true, 'height' => true, 'title' => true, 'allow' => true, 'allowfullscreen' => true, 'frameborder' => true, 'loading' => true, 'referrerpolicy' => true, 'style' => true, 'class' => true, 'id' => true );
		foreach ( array( 'section', 'div', 'span', 'a', 'img', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'ol', 'li', 'figure', 'header', 'footer', 'article', 'aside', 'nav', 'main', 'button' ) as $tag ) {
			$tags[ $tag ]          = isset( $tags[ $tag ] ) ? $tags[ $tag ] : array();
			$tags[ $tag ]['style'] = true;
			$tags[ $tag ]['class'] = true;
			$tags[ $tag ]['id']    = true;
		}
		return $tags;
	}

	/**
	 * Clean HTML for an HTML & CSS page.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function clean_html( $html ) {
		$html = preg_replace( '#<script\b[^>]*>.*?</script\s*>#is', '', (string) $html );
		return wp_kses( (string) $html, self::allowed_html() );
	}

	/**
	 * Take <style> blocks out of pasted HTML (their CSS goes in the CSS box).
	 *
	 * @param string $html HTML, changed in place.
	 * @return string The CSS found.
	 */
	public static function take_styles( &$html ) {
		$css  = '';
		$html = preg_replace_callback(
			'#<style\b[^>]*>(.*?)</style\s*>#is',
			function ( $m ) use ( &$css ) {
				$css .= trim( $m[1] ) . "\n";
				return '';
			},
			(string) $html
		);
		return trim( $css );
	}

	/**
	 * Clean CSS: plain text, and it can't close the style tag.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function clean_css( $css ) {
		$css = wp_strip_all_tags( (string) $css );
		return trim( str_ireplace( array( '</style', '<script', 'expression(', 'javascript:' ), '', $css ) );
	}

	/**
	 * Print an HTML & CSS page's HTML in place of its content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function html_content( $content ) {
		$id = get_the_ID();
		if ( ! $id || 'page' !== get_post_type( $id ) || ! is_singular() || get_queried_object_id() !== $id || ! self::is_html( $id ) ) {
			return $content;
		}
		return '<div class="kcs-html-page">' . do_shortcode( self::clean_html( get_post_meta( $id, self::HTML, true ) ) ) . '</div>';
	}

	/**
	 * Print an HTML & CSS page's CSS.
	 */
	public static function page_css() {
		if ( ! is_page() ) {
			return;
		}
		$id = get_queried_object_id();
		if ( self::is_html( $id ) ) {
			$css = self::clean_css( get_post_meta( $id, self::CSS, true ) );
			if ( '' !== $css ) {
				echo "<style id=\"kcs-page-css\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cleaned in clean_css().
			}
		}
	}

	/**
	 * Hidden pages (drafts) leave the menus on the website.
	 *
	 * @param array  $items Menu items.
	 * @param object $menu  Menu.
	 * @param array  $args  Args.
	 * @return array
	 */
	public static function hide_menu_items( $items, $menu, $args ) {
		if ( is_admin() ) {
			return $items;
		}
		$gone = array();
		foreach ( $items as $i => $item ) {
			$parent_gone = in_array( (int) $item->menu_item_parent, $gone, true );
			if ( $parent_gone || ( 'post_type' === $item->type && 'page' === $item->object && 'publish' !== get_post_status( (int) $item->object_id ) ) ) {
				$gone[] = (int) $item->ID;
				unset( $items[ $i ] );
			}
		}
		return array_values( $items );
	}

	/**
	 * An old page address (before a rename) opens the page's new address.
	 */
	public static function old_address() {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$slug = sanitize_title( basename( $path ) );
		if ( '' === $slug ) {
			return;
		}
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::OLD_SLUG, 'meta_value' => $slug ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $pages ) {
			wp_safe_redirect( get_permalink( $pages[0] ), 301 );
			exit;
		}
	}

	/**
	 * Change a page's title and address, remembering the old address.
	 *
	 * @param int    $id    Page ID.
	 * @param string $title New title.
	 * @param string $slug  New slug ('' keeps the address).
	 */
	public static function rename( $id, $title, $slug = '' ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return;
		}
		$data = array( 'ID' => $id, 'post_title' => $title );
		if ( '' !== $slug && $slug !== $post->post_name ) {
			add_post_meta( $id, self::OLD_SLUG, $post->post_name );
			$data['post_name'] = $slug;
		}
		wp_update_post( $data );
	}

	/**
	 * "Plan Your Visit" is now "Visit Us": rename the page once on each church site, unless the
	 * church gave it its own title.
	 */
	public static function rename_visit_page() {
		if ( get_option( 'kcs_visit_us_renamed' ) || ! add_option( 'kcs_visit_us_renamed', 1, '', false ) ) {
			return;
		}
		$id = (int) kcs_get_option( 'page_visit', 0 );
		if ( $id && 'Plan Your Visit' === get_the_title( $id ) ) {
			self::rename( $id, __( 'Visit Us', 'kingdom-church-suite' ), 'visit-us' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Church Admin helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Pages that must stay on (the site needs them to work).
	 *
	 * @return int[]
	 */
	public static function protected_ids() {
		$ids = array( (int) get_option( 'page_on_front' ) );
		foreach ( array( 'church_admin', 'partner_login', 'partner_dashboard', 'thank_you' ) as $key ) {
			$ids[] = (int) kcs_get_option( 'page_' . $key, 0 );
		}
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Layouts a new or plain page can use (template file => label), from the active theme.
	 *
	 * @return array
	 */
	public static function layouts() {
		$all     = wp_get_theme()->get_page_templates( null, 'page' );
		$layouts = array( '' => __( 'Standard (page title at the top)', 'kingdom-church-suite' ) );
		$wanted  = array(
			'page-templates/template-full-width.php' => __( 'Full width (no page title)', 'kingdom-church-suite' ),
			'page-templates/template-landing.php'    => __( 'Blank canvas (no menu or footer)', 'kingdom-church-suite' ),
		);
		foreach ( $wanted as $file => $label ) {
			if ( isset( $all[ $file ] ) ) {
				$layouts[ $file ] = $label;
			}
		}
		return $layouts;
	}

	/**
	 * Is Elementor active on this site?
	 *
	 * @return bool
	 */
	public static function elementor() {
		return defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * Page builder link: Elementor when it is active, else the WordPress block editor.
	 *
	 * @param int    $id    Page ID.
	 * @param string $which 'elementor', 'blocks' or '' (best available).
	 * @return string
	 */
	public static function builder_url( $id, $which = '' ) {
		if ( ( 'elementor' === $which || '' === $which ) && self::elementor() ) {
			return add_query_arg( array( 'post' => $id, 'action' => 'elementor' ), admin_url( 'post.php' ) );
		}
		return add_query_arg( array( 'post' => $id, 'action' => 'edit' ), admin_url( 'post.php' ) );
	}

	/**
	 * The main menu's ID.
	 *
	 * @return int
	 */
	private static function main_menu() {
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		return (int) ( $locations['primary'] ?? 0 );
	}

	/**
	 * Is the page in the main menu?
	 *
	 * @param int $id Page ID.
	 * @return bool
	 */
	public static function in_menu( $id ) {
		return (bool) self::menu_items( $id );
	}

	/**
	 * The page's items in the main menu.
	 *
	 * @param int $id Page ID.
	 * @return int[]
	 */
	private static function menu_items( $id ) {
		$menu = self::main_menu();
		if ( ! $menu ) {
			return array();
		}
		return array_values(
			array_filter(
				wp_get_associated_nav_menu_items( $id, 'post_type', 'page' ),
				function ( $item ) use ( $menu ) {
					return has_term( $menu, 'nav_menu', $item );
				}
			)
		);
	}

	/**
	 * Add the page to the main menu, or take it out.
	 *
	 * @param int  $id   Page ID.
	 * @param bool $show In the menu.
	 */
	public static function set_menu( $id, $show ) {
		$menu  = self::main_menu();
		$items = self::menu_items( $id );
		if ( $show && ! $items && $menu ) {
			wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		} elseif ( ! $show ) {
			foreach ( $items as $item ) {
				wp_delete_post( $item, true );
			}
		}
	}

	/**
	 * Handle a Church Admin action.
	 *
	 * @param string $do Action.
	 * @param array  $d  Posted data (unslashed).
	 * @param int    $id Page ID.
	 * @return array array( message, type, redirect URL or '' ).
	 */
	public static function handle( $do, $d, $id ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return array( __( 'You are not allowed to change pages.', 'kingdom-church-suite' ), 'error', '' );
		}
		$page = $id ? get_post( $id ) : null;
		if ( $id && ( ! $page || 'page' !== $page->post_type || ! current_user_can( 'edit_post', $id ) ) ) {
			return array( __( 'That page no longer exists.', 'kingdom-church-suite' ), 'error', '' );
		}
		$list = KCS_Church_Admin::url( 'pages' );
		switch ( $do ) {
			case 'page_create':
				$title = sanitize_text_field( $d['title'] ?? '' );
				if ( '' === $title ) {
					return array( __( 'Please give the page a title.', 'kingdom-church-suite' ), 'error', '' );
				}
				$layout = (string) ( $d['layout'] ?? '' );
				$mode   = 'html' === ( $d['mode'] ?? '' ) ? 'html' : 'builder';
				$new    = wp_insert_post(
					array(
						'post_type'     => 'page',
						'post_status'   => 'publish',
						'post_title'    => $title,
						'page_template' => array_key_exists( $layout, self::layouts() ) && '' !== $layout ? $layout : 'default',
					),
					true
				);
				if ( is_wp_error( $new ) ) {
					return array( $new->get_error_message(), 'error', '' );
				}
				if ( 'html' === $mode ) {
					update_post_meta( $new, self::MODE, 'html' );
					/* translators: %s: page title */
					update_post_meta( $new, self::HTML, '<section class="my-page">' . "\n\t<h2>" . esc_html( $title ) . "</h2>\n\t<p>" . esc_html__( 'Write your page here.', 'kingdom-church-suite' ) . "</p>\n</section>" );
					update_post_meta( $new, self::CSS, ".my-page {\n\tmax-width: 900px;\n\tmargin: 0 auto;\n\tpadding: 3rem 1rem;\n}" );
				}
				if ( ! empty( $d['in_menu'] ) ) {
					self::set_menu( $new, true );
				}
				if ( 'html' === $mode ) {
					return array( __( 'Page created. Write its HTML and CSS below.', 'kingdom-church-suite' ), 'success', KCS_Church_Admin::url( 'pages', array( 'edit_page' => $new ) ) );
				}
				return array( __( 'Page created.', 'kingdom-church-suite' ), 'success', self::builder_url( $new, sanitize_key( $d['builder'] ?? '' ) ) );

			case 'page_save':
				$title = sanitize_text_field( $d['title'] ?? '' );
				if ( '' !== $title && $title !== $page->post_title ) {
					wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
				}
				$layout = (string) ( $d['layout'] ?? '__keep' );
				if ( '__keep' !== $layout && array_key_exists( $layout, self::layouts() ) ) {
					update_post_meta( $id, '_wp_page_template', '' === $layout ? 'default' : $layout );
				}
				if ( self::is_html( $id ) ) {
					$html  = (string) ( $d['html'] ?? '' );
					$moved = self::take_styles( $html );
					$css   = trim( (string) ( $d['css'] ?? '' ) . ( '' !== $moved ? "\n" . $moved : '' ) );
					update_post_meta( $id, self::HTML, wp_slash( self::clean_html( $html ) ) );
					update_post_meta( $id, self::CSS, wp_slash( self::clean_css( $css ) ) );
				}
				self::set_menu( $id, ! empty( $d['in_menu'] ) );
				return array( __( 'Page saved.', 'kingdom-church-suite' ), 'success', KCS_Church_Admin::url( 'pages', array( 'edit_page' => $id ) ) );

			case 'page_mode':
				if ( 'html' === ( $d['mode'] ?? '' ) ) {
					if ( '' === (string) get_post_meta( $id, self::HTML, true ) ) {
						update_post_meta( $id, self::HTML, wp_slash( self::clean_html( $page->post_content ) ) );
					}
					update_post_meta( $id, self::MODE, 'html' );
					return array( __( 'This page now uses HTML & CSS.', 'kingdom-church-suite' ), 'success', KCS_Church_Admin::url( 'pages', array( 'edit_page' => $id ) ) );
				}
				delete_post_meta( $id, self::MODE );
				return array( __( 'This page now uses the page builder. Your HTML is kept in case you switch back.', 'kingdom-church-suite' ), 'success', KCS_Church_Admin::url( 'pages', array( 'edit_page' => $id ) ) );

			case 'page_toggle':
				if ( in_array( $id, self::protected_ids(), true ) ) {
					return array( __( 'This page is needed for your website to work, so it can’t be hidden.', 'kingdom-church-suite' ), 'error', '' );
				}
				$show = 'publish' !== $page->post_status;
				wp_update_post( array( 'ID' => $id, 'post_status' => $show ? 'publish' : 'draft' ) );
				/* translators: %s: page title */
				return array( sprintf( $show ? __( '“%s” is on your website again.', 'kingdom-church-suite' ) : __( '“%s” is hidden. Visitors can’t open it and it is left out of your menus.', 'kingdom-church-suite' ), $page->post_title ), 'success', $list );

			case 'page_delete':
				if ( in_array( $id, self::protected_ids(), true ) ) {
					return array( __( 'This page is needed for your website to work, so it can’t be deleted.', 'kingdom-church-suite' ), 'error', '' );
				}
				wp_trash_post( $id );
				/* translators: %s: page title */
				return array( sprintf( __( '“%s” was moved to the bin.', 'kingdom-church-suite' ), $page->post_title ), 'success', $list );
		}
		return array( '', 'success', '' );
	}

	/**
	 * Church Admin → Pages.
	 *
	 * @param callable $fields Builds the hidden fields of an action form: ( $do, $view, $id ).
	 */
	public static function view( $fields ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			echo '<div class="kcs-alert kcs-alert--info">' . esc_html__( 'Only church administrators can change pages.', 'kingdom-church-suite' ) . '</div>';
			return;
		}
		$edit = isset( $_GET['edit_page'] ) ? get_post( absint( $_GET['edit_page'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $edit && 'page' === $edit->post_type && 'trash' !== $edit->post_status ) {
			self::edit_form( $edit, $fields );
			return;
		}
		$layouts   = self::layouts();
		$protected = self::protected_ids();
		$pages     = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 200, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
		$templates = wp_get_theme()->get_page_templates( null, 'page' );
		?>
		<details class="kca-card kcs-form kca-newpage"<?php echo $pages ? '' : ' open'; ?>>
			<summary><strong><?php esc_html_e( '+ Add a new page', 'kingdom-church-suite' ); ?></strong></summary>
			<form method="post" class="kcs-form">
				<?php echo call_user_func( $fields, 'page_create', 'pages', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label><?php esc_html_e( 'Page title', 'kingdom-church-suite' ); ?><input name="title" required placeholder="<?php esc_attr_e( 'e.g. Youth Ministry', 'kingdom-church-suite' ); ?>"></label>
				<label><?php esc_html_e( 'Layout', 'kingdom-church-suite' ); ?><select name="layout"><?php foreach ( $layouts as $file => $label ) : ?><option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<fieldset><legend><?php esc_html_e( 'How do you want to build it?', 'kingdom-church-suite' ); ?></legend>
					<?php if ( self::elementor() ) : ?>
						<label class="kcs-check"><input type="radio" name="mode" value="builder" checked> <input type="hidden" name="builder" value="elementor"> <?php esc_html_e( 'Elementor page builder (drag and drop)', 'kingdom-church-suite' ); ?></label>
					<?php else : ?>
						<label class="kcs-check"><input type="radio" name="mode" value="builder" checked> <?php esc_html_e( 'Page builder: drag and drop blocks (headings, pictures, columns, buttons, videos…)', 'kingdom-church-suite' ); ?></label>
					<?php endif; ?>
					<label class="kcs-check"><input type="radio" name="mode" value="html"> <?php esc_html_e( 'HTML & CSS: write or paste your own code', 'kingdom-church-suite' ); ?></label>
				</fieldset>
				<label class="kcs-check"><input type="checkbox" name="in_menu" value="1" checked> <?php esc_html_e( 'Add it to the main menu', 'kingdom-church-suite' ); ?></label>
				<button class="kcs-btn"><?php esc_html_e( 'Create page', 'kingdom-church-suite' ); ?></button>
			</form>
		</details>

		<p class="kcs-muted"><?php esc_html_e( 'Hide a page you don’t use: visitors can’t open it and it leaves your menus. Show it again any time. Pages marked “Needed” keep your website working and stay on.', 'kingdom-church-suite' ); ?></p>
		<div class="kca-stack">
			<?php foreach ( $pages as $p ) : ?>
				<?php
				$live  = 'publish' === $p->post_status;
				$tpl   = get_page_template_slug( $p );
				$tname = $tpl && isset( $templates[ $tpl ] ) ? $templates[ $tpl ] : __( 'Standard page', 'kingdom-church-suite' );
				$need  = in_array( (int) $p->ID, $protected, true );
				?>
				<article class="kca-card kca-item kca-page<?php echo $live ? '' : ' is-hidden'; ?>">
					<div>
						<strong><?php echo esc_html( get_the_title( $p ) ? get_the_title( $p ) : __( '(no title)', 'kingdom-church-suite' ) ); ?></strong>
						<span class="kca-pill<?php echo $live ? ' is-on' : ''; ?>"><?php echo esc_html( $live ? __( 'On the website', 'kingdom-church-suite' ) : __( 'Hidden', 'kingdom-church-suite' ) ); ?></span>
						<?php if ( $need ) : ?><span class="kca-pill"><?php esc_html_e( 'Needed', 'kingdom-church-suite' ); ?></span><?php endif; ?>
						<p class="kcs-muted"><?php echo esc_html( $tname . ( self::is_html( $p->ID ) ? ' · ' . __( 'HTML & CSS', 'kingdom-church-suite' ) : '' ) ); ?> · <a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $live ? __( 'View', 'kingdom-church-suite' ) : __( 'Preview', 'kingdom-church-suite' ) ); ?></a></p>
					</div>
					<div class="kca-actions">
						<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( KCS_Church_Admin::url( 'pages', array( 'edit_page' => $p->ID ) ) ); ?>"><?php esc_html_e( 'Edit', 'kingdom-church-suite' ); ?></a>
						<?php if ( ! $need ) : ?>
							<form method="post" class="kca-inline"><?php echo call_user_func( $fields, 'page_toggle', 'pages', $p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost"><?php echo esc_html( $live ? __( 'Hide', 'kingdom-church-suite' ) : __( 'Show', 'kingdom-church-suite' ) ); ?></button></form>
							<form method="post" class="kca-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Move this page to the bin?', 'kingdom-church-suite' ) ); ?>');"><?php echo call_user_func( $fields, 'page_delete', 'pages', $p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="kcs-btn kcs-btn--ghost kcs-btn--danger"><?php esc_html_e( 'Delete', 'kingdom-church-suite' ); ?></button></form>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Edit one page: title, layout, menu, and its HTML & CSS or a link to the page builder.
	 *
	 * @param WP_Post  $p      Page.
	 * @param callable $fields Hidden fields builder.
	 */
	private static function edit_form( $p, $fields ) {
		$layouts = self::layouts();
		$tpl     = get_page_template_slug( $p );
		$html    = self::is_html( $p->ID );
		$builder = (bool) self::elementor() && 'builder' === get_post_meta( $p->ID, '_elementor_edit_mode', true );
		?>
		<p><a href="<?php echo esc_url( KCS_Church_Admin::url( 'pages' ) ); ?>">← <?php esc_html_e( 'All pages', 'kingdom-church-suite' ); ?></a></p>
		<form method="post" class="kca-card kcs-form">
			<?php echo call_user_func( $fields, 'page_save', 'pages', $p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<label><?php esc_html_e( 'Page title', 'kingdom-church-suite' ); ?><input name="title" value="<?php echo esc_attr( $p->post_title ); ?>" required></label>
			<?php if ( array_key_exists( $tpl, $layouts ) || '' === $tpl ) : ?>
				<label><?php esc_html_e( 'Layout', 'kingdom-church-suite' ); ?><select name="layout"><?php foreach ( $layouts as $file => $label ) : ?><option value="<?php echo esc_attr( $file ); ?>" <?php selected( $tpl, $file ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<?php else : ?>
				<input type="hidden" name="layout" value="__keep">
			<?php endif; ?>
			<label class="kcs-check"><input type="checkbox" name="in_menu" value="1" <?php checked( self::in_menu( $p->ID ) ); ?>> <?php esc_html_e( 'Show in the main menu', 'kingdom-church-suite' ); ?></label>
			<?php if ( $html ) : ?>
				<label><?php esc_html_e( 'HTML', 'kingdom-church-suite' ); ?><textarea name="html" rows="18" class="kca-code" spellcheck="false"><?php echo esc_textarea( (string) get_post_meta( $p->ID, self::HTML, true ) ); ?></textarea></label>
				<small class="kcs-muted"><?php esc_html_e( 'Scripts are removed for safety. Shortcodes such as [kcs_give] and YouTube embeds (iframe) work.', 'kingdom-church-suite' ); ?></small>
				<label><?php esc_html_e( 'CSS (only used on this page)', 'kingdom-church-suite' ); ?><textarea name="css" rows="12" class="kca-code" spellcheck="false"><?php echo esc_textarea( (string) get_post_meta( $p->ID, self::CSS, true ) ); ?></textarea></label>
			<?php endif; ?>
			<div class="kca-actions">
				<button class="kcs-btn"><?php esc_html_e( 'Save page', 'kingdom-church-suite' ); ?></button>
				<a class="kcs-btn kcs-btn--ghost" href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View page', 'kingdom-church-suite' ); ?></a>
			</div>
		</form>
		<section class="kca-card">
			<h3><?php esc_html_e( 'Page content', 'kingdom-church-suite' ); ?></h3>
			<?php if ( $html ) : ?>
				<p class="kcs-muted"><?php esc_html_e( 'This page is written in HTML & CSS (above). You can switch it to the page builder; your code is kept.', 'kingdom-church-suite' ); ?></p>
			<?php else : ?>
				<p class="kcs-muted"><?php esc_html_e( 'Build this page by dragging in blocks: headings, text, pictures, columns, buttons, videos and more.', 'kingdom-church-suite' ); ?></p>
				<div class="kca-actions">
					<?php if ( self::elementor() ) : ?>
						<a class="kcs-btn" href="<?php echo esc_url( self::builder_url( $p->ID, 'elementor' ) ); ?>"><?php esc_html_e( 'Edit with Elementor', 'kingdom-church-suite' ); ?></a>
					<?php endif; ?>
					<?php if ( ! $builder ) : ?>
						<a class="kcs-btn<?php echo self::elementor() ? ' kcs-btn--ghost' : ''; ?>" href="<?php echo esc_url( self::builder_url( $p->ID, 'blocks' ) ); ?>"><?php esc_html_e( 'Open the page builder', 'kingdom-church-suite' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<form method="post" class="kca-inline">
				<?php echo call_user_func( $fields, 'page_mode', 'pages', $p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mode" value="<?php echo $html ? 'builder' : 'html'; ?>">
				<button class="kcs-btn kcs-btn--ghost"><?php echo esc_html( $html ? __( 'Switch to the page builder', 'kingdom-church-suite' ) : __( 'Switch to HTML & CSS', 'kingdom-church-suite' ) ); ?></button>
			</form>
		</section>
		<?php
	}
}
