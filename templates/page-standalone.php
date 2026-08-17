<?php
/**
 * Template Name: Buku Tamu — Tanpa Header/Footer
 *
 * Template halaman mandiri (standalone) — SENGAJA tidak memanggil get_header()/get_footer()
 * tema, supaya pengunjung fokus mengisi/melihat buku tamu tanpa navigasi situs lain. Dimuat
 * lewat Bukutamu_Page_Template (filter theme_page_templates + template_include) — tidak ada
 * satu pun file tema yang diubah.
 *
 * Halaman ini tetap Page WordPress biasa: query, wp_head(), wp_footer(), dan seluruh plugin
 * lain (SEO, analytics, dsb.) tetap berjalan normal — yang dilewati murni header.php/footer.php
 * tema. Konten (the_content()) berasal dari isi Page yang dipilih admin, biasanya berupa
 * shortcode [bukutamu_form judul="0"], [bukutamu_testimoni], atau [bukutamu_arsip].
 *
 * Kontainer luar sengaja lebar (max-w-6xl) supaya grid 3-kolom testimoni/arsip tidak mepet;
 * blok judul dibungkus max-w-2xl tersendiri di dalamnya supaya heading tetap ramping &
 * center walau kontainer luarnya lebar. form.php/testimoni-grid.php/arsip-grid.php masing-
 * masing sudah membatasi lebarnya sendiri (mx-auto + max-w-*), jadi aman ditaruh di kontainer
 * lebar ini tanpa terlihat "mengambang" saat kontennya sempit (mis. form).
 */

defined( 'ABSPATH' ) || exit;

if ( have_posts() ) {
	the_post();
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bukutamu-standalone-page bukutamu-page bt-m-0 bt-bg-slate-50' ); ?>>
<?php wp_body_open(); ?>

<div class="bt-relative bt-flex bt-min-h-screen bt-items-center bt-justify-center bt-overflow-hidden bt-px-4 bt-py-10 sm:bt-py-16">

	<div class="bt-pointer-events-none bt-absolute bt-inset-0 bt-overflow-hidden" aria-hidden="true">
		<div class="bt-absolute -bt-left-24 -bt-top-24 bt-h-72 bt-w-72 bt-rounded-full bt-bg-amber-100 bt-blur-3xl"></div>
		<div class="bt-absolute -bt-bottom-24 -bt-right-24 bt-h-72 bt-w-72 bt-rounded-full bt-bg-slate-200 bt-blur-3xl"></div>
	</div>

	<div class="bt-relative bt-w-full bt-max-w-6xl">
		<div class="bt-mx-auto bt-mb-2 bt-max-w-2xl bt-text-center">
			<?php bukutamu_site_branding(); ?>
			<h1 class="bt-text-2xl bt-font-bold bt-text-slate-900 sm:bt-text-3xl"><?php the_title(); ?></h1>
			<p class="bt-mt-1 bt-text-sm bt-font-semibold bt-text-slate-600"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<?php if ( get_the_excerpt() ) : ?>
				<p class="bt-mx-auto bt-mt-2 bt-max-w-md bt-text-sm bt-text-slate-500"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</div>

		<?php the_content(); ?>

		<p class="bt-mt-6 bt-text-center bt-text-xs bt-text-slate-400">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hover:bt-text-slate-600">
				&larr; <?php esc_html_e( 'Kembali ke beranda', 'bukutamu' ); ?>
			</a>
		</p>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
