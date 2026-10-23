<?php
/**
 * Visit page: visitor information, questions and answers, and the visitor questionnaire.
 * Every text is edited in Church Admin → Pictures & text → Visit page.
 *
 * @package Kingdom
 */

if ( ! get_theme_mod( 'kingdom_vp_show', true ) ) {
	return;
}
$kingdom_phones = array_filter( array_map( 'trim', preg_split( '/\R/', kingdom_text_setting( 'kingdom_vp_phones' ) ) ) );
if ( ! $kingdom_phones && get_theme_mod( 'kingdom_phone' ) ) {
	$kingdom_phones = array( get_theme_mod( 'kingdom_phone' ) );
}
$kingdom_email    = kingdom_text_setting( 'kingdom_vp_email' ) ? kingdom_text_setting( 'kingdom_vp_email' ) : get_theme_mod( 'kingdom_email' );
$kingdom_location = kingdom_text_setting( 'kingdom_vp_location' ) ? kingdom_text_setting( 'kingdom_vp_location' ) : get_theme_mod( 'kingdom_address' );
$kingdom_person   = kingdom_text_setting( 'kingdom_vp_person' );
$kingdom_faq      = kingdom_faq_items( kingdom_text_setting( 'kingdom_vp_faq' ) );
$kingdom_form     = get_theme_mod( 'kingdom_vp_form_show', true ) && shortcode_exists( 'kcs_visit_questionnaire' );
?>
<section class="k-section k-visit-info">
	<div class="k-container">
		<header class="k-section__head k-section__head--center" data-reveal>
			<h2 class="k-section__title"><?php echo esc_html( kingdom_text_setting( 'kingdom_vp_title' ) ); ?></h2>
			<div class="k-section__text k-prose"><?php echo kingdom_paragraphs( kingdom_text_setting( 'kingdom_vp_intro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></div>
		</header>

		<div class="k-visit-info__grid">
			<div class="k-visit-info__card" data-reveal>
				<h3><?php echo esc_html( kingdom_text_setting( 'kingdom_vp_local_title' ) ); ?></h3>
				<?php echo kingdom_paragraphs( kingdom_text_setting( 'kingdom_vp_local_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ul class="k-visit-info__list">
					<?php if ( kingdom_text_setting( 'kingdom_vp_hours' ) ) : ?>
						<li><?php echo kingdom_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( kingdom_text_setting( 'kingdom_vp_hours' ) ); ?></span></li>
					<?php endif; ?>
					<?php foreach ( $kingdom_phones as $kingdom_phone ) : ?>
						<li><?php echo kingdom_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $kingdom_phone ) ); ?>"><?php echo esc_html( $kingdom_phone ); ?></a></li>
					<?php endforeach; ?>
					<?php if ( $kingdom_email ) : ?>
						<li><?php echo kingdom_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="<?php echo esc_url( 'mailto:' . $kingdom_email ); ?>"><?php echo esc_html( $kingdom_email ); ?></a></li>
					<?php endif; ?>
					<?php if ( $kingdom_location ) : ?>
						<li><?php echo kingdom_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo nl2br( esc_html( $kingdom_location ), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></li>
					<?php endif; ?>
					<?php if ( $kingdom_person ) : ?>
						<li><?php echo kingdom_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( $kingdom_person ); ?></span></li>
					<?php endif; ?>
				</ul>
			</div>

			<?php if ( $kingdom_faq ) : ?>
				<div class="k-visit-info__faq" data-reveal>
					<h3><?php echo esc_html( kingdom_text_setting( 'kingdom_vp_faq_title' ) ); ?></h3>
					<div class="k-faq">
						<?php foreach ( $kingdom_faq as $kingdom_i => $kingdom_q ) : ?>
							<details<?php echo 0 === $kingdom_i ? ' open' : ''; ?>>
								<summary><?php echo esc_html( $kingdom_q[0] ); ?></summary>
								<?php
								$kingdom_answer = kingdom_paragraphs( $kingdom_q[1] );
								if ( $kingdom_form ) {
									// "…go to the Questionnaire…" opens the form below.
									$kingdom_answer = preg_replace( '/\b(questionnaire)\b/i', '<a href="#questionnaire">$1</a>', $kingdom_answer, 1 );
								}
								echo $kingdom_answer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kingdom_paragraphs().
								?>
							</details>
						<?php endforeach; ?>
					</div>
					<?php if ( $kingdom_form ) : ?>
						<p><a class="k-btn k-btn--primary" href="#questionnaire"><?php echo esc_html( kingdom_text_setting( 'kingdom_vp_form_title' ) ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $kingdom_form ) : ?>
			<div class="k-visit-info__form" id="questionnaire" data-reveal>
				<?php kingdom_section_heading( '', kingdom_text_setting( 'kingdom_vp_form_title' ), kingdom_text_setting( 'kingdom_vp_form_text' ) ); ?>
				<?php echo do_shortcode( '[kcs_visit_questionnaire]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
	</div>
</section>
