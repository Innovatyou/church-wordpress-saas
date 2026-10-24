<?php
/**
 * Template Name: App Home
 * Template Post Type: page
 *
 * The church app's home screen: keeps the menu and bottom tab bar, without the page title banner.
 *
 * @package Kingdom
 */

get_header();
?>
<div class="k-container k-app-home">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>
<?php
get_footer();
