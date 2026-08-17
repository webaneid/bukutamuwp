<?php
/**
 * Halaman terima kasih setelah submit form berhasil. Di-include lewat output buffering dari
 * Bukutamu_Shortcode::render_terima_kasih(). Dipakai sebagai tujuan redirect atribut
 * `redirect` di [bukutamu_form] — lihat templates/form.php & build/js/bukutamu-form.js.
 */

defined( 'ABSPATH' ) || exit;

$archive_url = get_post_type_archive_link( Bukutamu_CPT::POST_TYPE );
?>
<div class="bukutamu-terima-kasih bt-mx-auto bt-w-full bt-max-w-lg bt-text-center bt-font-sans">
	<div class="bt-mx-auto bt-mb-5 bt-flex bt-h-20 bt-w-20 bt-items-center bt-justify-center bt-rounded-full bt-bg-emerald-50 bt-text-emerald-700">
		<?php bukutamu_icon( 'check-circle', 'bt-h-10 bt-w-10' ); ?>
	</div>

	<h2 class="bt-text-2xl bt-font-bold bt-text-slate-900 sm:bt-text-3xl">
		<?php esc_html_e( 'Jazakumullahu Khairon Katsiron', 'bukutamu' ); ?>
	</h2>
	<p class="bt-mt-2 bt-text-base bt-text-slate-600">
		<?php esc_html_e( 'Buku tamu berhasil diisi.', 'bukutamu' ); ?>
	</p>

	<?php if ( $archive_url ) : ?>
		<a href="<?php echo esc_url( $archive_url ); ?>"
			class="bt-mt-8 bt-inline-flex bt-items-center bt-justify-center bt-rounded-lg bt-bg-slate-900 bt-px-6 bt-py-3 bt-text-sm bt-font-semibold bt-text-white bt-transition hover:bt-bg-slate-700">
			<?php esc_html_e( 'Lihat Buku Tamu', 'bukutamu' ); ?>
		</a>
	<?php endif; ?>
</div>
