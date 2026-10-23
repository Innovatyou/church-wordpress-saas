<?php
/**
 * Charity: the church's humanitarian outreach. Edited in Church Admin → Pictures & text → Charity.
 *
 * @package Kingdom
 */

$kingdom_causes = array_filter( array_map( 'trim', preg_split( '/\R/', kingdom_text_setting( 'kingdom_charity_causes' ) ) ) );
$kingdom_image  = kingdom_text_setting( 'kingdom_charity_image' ) ? kingdom_text_setting( 'kingdom_charity_image' ) : kingdom_image( 'kingdom_gallery_3' );
$kingdom_btn    = kingdom_text_setting( 'kingdom_charity_btn_text' );
$kingdom_url    = kingdom_url( 'kingdom_charity_btn_url', 'template-give.php', kingdom_plugin_active() ? kcs_page_url( 'give' ) : '' );
?>
<section class="k-section k-charity" id="charity">
	<div class="k-container k-split">
		<figure class="k-charity__media" data-reveal>
			<img src="<?php echo esc_url( $kingdom_image ); ?>" alt="<?php echo esc_attr( kingdom_text_setting( 'kingdom_charity_title' ) ); ?>" loading="lazy">
		</figure>
		<div class="k-charity__body" data-reveal>
			<?php if ( kingdom_text_setting( 'kingdom_charity_eyebrow' ) ) : ?><span class="k-eyebrow"><?php echo esc_html( kingdom_text_setting( 'kingdom_charity_eyebrow' ) ); ?></span><?php endif; ?>
			<h2 class="k-section__title"><?php echo esc_html( kingdom_text_setting( 'kingdom_charity_title' ) ); ?></h2>
			<div class="k-prose"><?php echo kingdom_paragraphs( kingdom_text_setting( 'kingdom_charity_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
			<?php if ( $kingdom_causes ) : ?>
				<ul class="k-charity__causes">
					<?php foreach ( $kingdom_causes as $kingdom_cause ) : ?>
						<li><?php echo kingdom_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $kingdom_cause ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $kingdom_btn ) : ?>
				<a class="k-btn k-btn--accent k-btn--lg" href="<?php echo esc_url( $kingdom_url ); ?>"><?php echo esc_html( $kingdom_btn ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
