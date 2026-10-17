<?php
/**
 * Stats counter.
 *
 * @package Kingdom
 */

$kingdom_lines = array_filter( array_map( 'trim', explode( "\n", get_theme_mod( 'kingdom_stats', "25|Years of ministry\n3000|Members\n40|Ministries\n12|Nations reached" ) ) ) );
if ( ! $kingdom_lines ) {
	return;
}
$kingdom_bg = kingdom_image( 'kingdom_stats_image' );
?>
<section class="k-stats<?php echo $kingdom_bg ? ' has-image' : ''; ?>"<?php echo $kingdom_bg ? ' style="--k-img:url(' . esc_url( $kingdom_bg ) . ')"' : ''; ?>>
	<div class="k-container k-stats__grid">
		<?php foreach ( $kingdom_lines as $kingdom_line ) : ?>
			<?php $kingdom_p = array_map( 'trim', explode( '|', $kingdom_line ) ); ?>
			<div class="k-stat" data-reveal>
				<?php $kingdom_num = str_replace( array( ',', ' ' ), '', $kingdom_p[0] ); ?>
				<strong<?php echo ctype_digit( $kingdom_num ) ? ' data-count="' . esc_attr( $kingdom_num ) . '"' : ''; // Only plain numbers count up ("24/7" or "3.5k" stay as written). ?>><?php echo esc_html( $kingdom_p[0] ); ?></strong><span class="k-stat__plus">+</span>
				<small><?php echo esc_html( $kingdom_p[1] ?? '' ); ?></small>
			</div>
		<?php endforeach; ?>
	</div>
</section>
