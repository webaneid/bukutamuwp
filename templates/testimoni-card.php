<?php
/**
 * Satu kartu testimoni. Dipanggil di dalam loop WP_Query custom (lihat testimoni-grid.php,
 * archive-buku_tamu.php).
 *
 * PENTING:
 * - Hanya menampilkan data yang aman untuk publik — nama, instansi, cuplikan pesan, SATU foto.
 *   Nomor HP & email TIDAK PERNAH ditampilkan (lihat CLAUDE.md > Keamanan).
 * - Tanda tangan TIDAK PERNAH ditampilkan di front-end mana pun (arsip/testimoni/single) —
 *   hanya terlihat admin di wp-admin. Lihat CLAUDE.md Lessons Learned #20.
 * - Foto yang ditampilkan dipilih ACAK dari galeri setiap render (bukan selalu foto pertama)
 *   — kartu ini cuma teaser; galeri LENGKAP baru tampil di halaman single (klik kartu).
 */

defined( 'ABSPATH' ) || exit;

$post_id     = get_the_ID();
$nama        = (string) get_field( 'field_bukutamu_nama', $post_id );
$instansi    = (string) get_field( 'field_bukutamu_instansi', $post_id );
$kesan_pesan = (string) get_field( 'field_bukutamu_kesan_pesan', $post_id );
$galeri      = get_field( 'field_bukutamu_galeri_foto', $post_id );
$permalink   = get_permalink( $post_id );

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

$foto_utama = '';
if ( ! empty( $galeri ) ) {
	$foto_acak  = $galeri[ array_rand( $galeri ) ];
	$foto_utama = ! empty( $foto_acak['sizes']['medium'] ) ? $foto_acak['sizes']['medium'] : $foto_acak['url'];
}
?>
<article class="bt-flex bt-flex-col bt-overflow-hidden bt-rounded-2xl bt-border bt-border-slate-200 bt-bg-white bt-shadow-sm">
	<a href="<?php echo esc_url( $permalink ); ?>" class="bt-block bt-aspect-[4/3] bt-w-full bt-overflow-hidden bt-bg-slate-100">
		<?php if ( $foto_utama ) : ?>
			<img src="<?php echo esc_url( $foto_utama ); ?>" alt="<?php echo esc_attr( $nama ); ?>" loading="lazy"
				class="bt-h-full bt-w-full bt-object-cover bt-transition hover:bt-scale-105">
		<?php else : ?>
			<div class="bt-flex bt-h-full bt-w-full bt-items-center bt-justify-center bt-text-slate-300">
				<?php bukutamu_icon( 'image', 'bt-h-10 bt-w-10' ); ?>
			</div>
		<?php endif; ?>
	</a>

	<div class="bt-flex bt-flex-1 bt-flex-col bt-gap-3 bt-p-5">
		<p class="bt-flex-1 bt-text-sm bt-italic bt-leading-relaxed bt-text-slate-600">&ldquo;<?php echo esc_html( wp_trim_words( $kesan_pesan, 28 ) ); ?>&rdquo;</p>

		<a href="<?php echo esc_url( $permalink ); ?>" class="bt-block bt-border-t bt-border-slate-100 bt-pt-3 hover:bt-text-slate-600">
			<p class="bt-text-sm bt-font-semibold bt-text-slate-900"><?php echo esc_html( $nama ); ?></p>
			<?php if ( $instansi ) : ?>
				<p class="bt-text-xs bt-text-slate-500"><?php echo esc_html( $instansi ); ?></p>
			<?php endif; ?>
		</a>
	</div>
</article>
