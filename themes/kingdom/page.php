<?php
/**
 * Default page.
 *
 * @package Kingdom
 */

get_header();
while ( have_posts() ) :
	the_post();
	if ( kingdom_dashboard_type() ) :
		// Dashboards get a full-width app layout without the big page title.
		?>
		<div class="k-container k-app">
			<?php the_content(); ?>
		</div>
		<?php
		continue;
	endif;
	if ( kingdom_is_result_page() ) :
		// Payment result: one focused card on a soft backdrop, without the big page title.
		?>
		<div class="k-result-stage">
			<div class="k-container k-container--narrow">
				<?php the_content(); ?>
			</div>
		</div>
		<?php
		continue;
	endif;
	kingdom_page_header();
	// The church's Visit page shows its visitor information, questions and questionnaire with any
	// layout (it may have been created with the standard page layout).
	if ( kingdom_plugin_active() && get_the_ID() === (int) kcs_get_option( 'page_visit', 0 ) ) {
		kingdom_section( 'visit-info' );
	}
	?>
	<div class="k-container k-container--narrow k-section">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'k-entry' ); ?>>
			<div class="k-entry__content">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
endwhile;
get_footer();
