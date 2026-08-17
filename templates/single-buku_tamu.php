<?php
/**
 * Single native untuk CPT buku_tamu — detail satu entri (WordPress otomatis 404 untuk status
 * non-publish yang diakses pengunjung biasa, tanpa perlu penanganan tambahan di sini).
 *
 * TIDAK PERNAH menampilkan nomor HP/email (data pribadi) ATAU tanda tangan — sama seperti
 * archive/testimoni, lihat CLAUDE.md > Keamanan & Lessons Learned #20. SENGAJA memanggil
 * get_header()/get_footer() tema.
 *
 * Galeri ditampilkan LENGKAP di sini (beda dari kartu arsip/testimoni yang cuma 1 foto acak)
 * — setiap foto tampil sebagai thumbnail (ACF size 'thumbnail'), diklik untuk popup ukuran
 * besar (ACF size 'large') lewat build/js/bukutamu-lightbox.js.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( have_posts() ) :
	the_post();

	$post_id     = get_the_ID();
	$nama        = (string) get_field( 'field_bukutamu_nama', $post_id );
	$instansi    = (string) get_field( 'field_bukutamu_instansi', $post_id );
	$kesan_pesan = (string) get_field( 'field_bukutamu_kesan_pesan', $post_id );
	$galeri      = get_field( 'field_bukutamu_galeri_foto', $post_id );
	$tanggal     = get_field( 'field_bukutamu_tanggal', $post_id );

	$galeri = is_array( $galeri )
		? array_values(
			array_filter(
				$galeri,
				static function ( $item ) {
					return ! empty( $item['url'] );
				}
			)
		)
		: [];
	?>
	<div class="bukutamu-page bt-mx-auto bt-max-w-2xl bt-px-4 bt-py-10 sm:bt-py-16 bt-font-sans">
		<a href="<?php echo esc_url( get_post_type_archive_link( Bukutamu_CPT::POST_TYPE ) ); ?>"
			class="bt-mb-6 bt-inline-flex bt-items-center bt-gap-1.5 bt-text-sm bt-text-slate-500 hover:bt-text-slate-700">
			&larr; <?php esc_html_e( 'Kembali ke Arsip Buku Tamu', 'bukutamu' ); ?>
		</a>

		<article class="bt-overflow-hidden bt-rounded-2xl bt-border bt-border-slate-200 bt-bg-white bt-shadow-sm">
			<div class="bt-space-y-4 bt-p-6 sm:bt-p-8">
				<div>
					<h1 class="bt-text-xl bt-font-semibold bt-text-slate-900"><?php echo esc_html( $nama ); ?></h1>
					<?php if ( $instansi ) : ?>
						<div class="bt-text-sm bt-text-slate-500"><?php echo esc_html( $instansi ); ?></div>
					<?php endif; ?>
					<?php if ( $tanggal ) : ?>
						<div class="bt-mt-1 bt-text-xs bt-text-slate-400"><?php echo esc_html( mysql2date( 'j F Y', $tanggal ) ); ?></div>
					<?php endif; ?>
				</div>

				<p class="bt-text-sm bt-italic bt-leading-relaxed bt-text-slate-600">&ldquo;<?php echo esc_html( $kesan_pesan ); ?>&rdquo;</p>

				<?php if ( ! empty( $galeri ) ) : ?>
					<div>
						<div class="bt-mb-2 bt-text-xs bt-font-medium bt-text-slate-400"><?php esc_html_e( 'Galeri Foto', 'bukutamu' ); ?></div>
						<div class="bukutamu-single-gallery bt-grid bt-grid-cols-3 bt-gap-1.5 sm:bt-grid-cols-4">
							<?php
							foreach ( $galeri as $foto ) :
								$thumb_url = ! empty( $foto['sizes']['thumbnail'] ) ? $foto['sizes']['thumbnail'] : $foto['url'];
								$large_url = ! empty( $foto['sizes']['large'] ) ? $foto['sizes']['large'] : $foto['url'];
								?>
								<button type="button" data-bukutamu-lightbox="<?php echo esc_url( $large_url ); ?>"
									class="bt-block bt-aspect-square bt-cursor-zoom-in bt-overflow-hidden bt-rounded-lg bt-bg-slate-100">
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy"
										class="bt-h-full bt-w-full bt-object-cover bt-transition hover:bt-scale-105">
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</article>
	</div>
	<?php
endif;

get_footer();
