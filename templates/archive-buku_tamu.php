<?php
/**
 * Archive native untuk CPT buku_tamu (URL: /buku-tamu/, lihat Bukutamu_CPT::ARCHIVE_SLUG).
 * Dimuat lewat Bukutamu_Cpt_Templates::load_template() — tema BOLEH override dengan bikin
 * file archive-buku_tamu.php sendiri, plugin ini hanya jadi fallback default.
 *
 * SENGAJA memanggil get_header()/get_footer() tema (beda dari templates/page-standalone.php
 * yang tanpa header/footer) — arsip native ini dimaksudkan terintegrasi normal ke situs
 * (menu, breadcrumb navigasi browser, dst), bukan halaman kiosk mandiri.
 *
 * Jumlah entri per halaman & urutan diatur lewat pre_get_posts di
 * Bukutamu_Cpt_Templates::set_archive_query_args() — bukan di file ini.
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$paged     = max( 1, (int) get_query_var( 'paged' ) );
$max_pages = (int) $wp_query->max_num_pages;
?>
<div class="bukutamu-page bt-mx-auto bt-max-w-6xl bt-px-4 bt-py-10 sm:bt-py-16 bt-font-sans">
	<header class="bt-mb-8 bt-text-center">
		<h1 class="bt-text-2xl bt-font-bold bt-text-slate-900 sm:bt-text-3xl"><?php post_type_archive_title(); ?></h1>
		<p class="bt-mt-1 bt-text-sm bt-font-semibold bt-text-slate-600"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="bukutamu-testimoni bt-mx-auto bt-grid bt-w-full bt-grid-cols-1 bt-gap-5 sm:bt-grid-cols-2 lg:bt-grid-cols-3">
			<?php
			while ( have_posts() ) :
				the_post();
				include BUKUTAMU_PATH . 'templates/testimoni-card.php';
			endwhile;
			?>
		</div>

		<?php if ( $max_pages > 1 ) : ?>
			<nav class="bt-mx-auto bt-mt-8 bt-flex bt-w-full bt-items-center bt-justify-between bt-gap-4" aria-label="<?php esc_attr_e( 'Navigasi halaman arsip', 'bukutamu' ); ?>">
				<div>
					<?php if ( $paged > 1 ) : ?>
						<a href="<?php echo esc_url( get_previous_posts_page_link() ); ?>"
							class="bt-inline-flex bt-items-center bt-rounded-lg bt-border bt-border-slate-300 bt-bg-white bt-px-4 bt-py-2 bt-text-sm bt-font-medium bt-text-slate-700 hover:bt-bg-slate-50">
							&larr; <?php esc_html_e( 'Sebelumnya', 'bukutamu' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div class="bt-text-sm bt-text-slate-500">
					<?php
					printf(
						/* translators: 1: nomor halaman saat ini, 2: total halaman */
						esc_html__( 'Halaman %1$d dari %2$d', 'bukutamu' ),
						(int) $paged,
						(int) $max_pages
					);
					?>
				</div>

				<div>
					<?php if ( $paged < $max_pages ) : ?>
						<a href="<?php echo esc_url( get_next_posts_page_link( $max_pages ) ); ?>"
							class="bt-inline-flex bt-items-center bt-rounded-lg bt-border bt-border-slate-300 bt-bg-white bt-px-4 bt-py-2 bt-text-sm bt-font-medium bt-text-slate-700 hover:bt-bg-slate-50">
							<?php esc_html_e( 'Selanjutnya', 'bukutamu' ); ?> &rarr;
						</a>
					<?php endif; ?>
				</div>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<div class="bt-text-center bt-text-sm bt-text-slate-400"><?php esc_html_e( 'Belum ada entri buku tamu.', 'bukutamu' ); ?></div>
	<?php endif; ?>
</div>
<?php
get_footer();
