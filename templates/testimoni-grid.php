<?php
/**
 * Grid testimoni publik. Variabel $query (WP_Query) disiapkan oleh
 * Bukutamu_Shortcode::render_testimoni() sebelum file ini di-include.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $query ) || ! $query instanceof WP_Query || ! $query->have_posts() ) {
	return;
}
?>
<div class="bukutamu-testimoni bt-mx-auto bt-grid bt-w-full bt-max-w-6xl bt-grid-cols-1 bt-gap-5 bt-font-sans sm:bt-grid-cols-2 lg:bt-grid-cols-3">
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();
		include BUKUTAMU_PATH . 'templates/testimoni-card.php';
	endwhile;
	?>
</div>
