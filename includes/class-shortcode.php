<?php
/**
 * Registrasi shortcode publik:
 * - [bukutamu_form judul="1" redirect=""]  → form submission buku tamu
 * - [bukutamu_testimoni jumlah="6"]        → showcase terbatas, tanpa paginasi (landing page)
 * - [bukutamu_terima_kasih]                → halaman tujuan redirect setelah submit berhasil
 *
 * Arsip LENGKAP (semua entri, dengan paginasi) TIDAK lagi lewat shortcode — dipindah ke
 * archive native CPT (/buku-tamu/, lihat includes/class-cpt-templates.php) atas keputusan
 * user setelah diberi tahu trade-off keamanannya (lihat CLAUDE.md Lessons Learned #19).
 * [bukutamu_testimoni] tetap ada untuk showcase terbatas yang bisa ditempel di halaman mana
 * pun (mis. landing page), beda kebutuhan dari arsip lengkap.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Shortcode {

	private static ?Bukutamu_Shortcode $instance = null;

	public static function instance(): Bukutamu_Shortcode {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_shortcode( 'bukutamu_form', [ self::$instance, 'render_form' ] );
			add_shortcode( 'bukutamu_testimoni', [ self::$instance, 'render_testimoni' ] );
			add_shortcode( 'bukutamu_terima_kasih', [ self::$instance, 'render_terima_kasih' ] );
		}
		return self::$instance;
	}

	/**
	 * Atribut `judul="0"` mematikan heading "Buku Tamu" bawaan di dalam card — dipakai saat
	 * shortcode ini ditaruh di halaman yang sudah punya judul sendiri (mis. template standalone
	 * `templates/page-standalone.php`), supaya judul tidak muncul dobel.
	 *
	 * Atribut `redirect` (opsional, URL) — kalau diisi, setelah submit BERHASIL browser
	 * langsung diarahkan (`window.location.href`, lihat bukutamu-form.js) ke URL itu, BUKAN
	 * menampilkan pesan sukses inline seperti biasa. Ditujukan untuk diarahkan ke halaman yang
	 * memakai shortcode [bukutamu_terima_kasih]. Kosong (default) = perilaku lama tetap
	 * dipertahankan (pesan sukses tampil inline di form, tanpa redirect) — backward compatible
	 * untuk pemasangan [bukutamu_form] yang sudah ada tanpa atribut ini.
	 */
	public function render_form( $atts ): string {
		$atts = shortcode_atts(
			[
				'judul'    => '1',
				'redirect' => '',
			],
			$atts,
			'bukutamu_form'
		);

		$tampilkan_judul = ! in_array( $atts['judul'], [ '0', 'false', 'tidak' ], true );
		$redirect_url    = $atts['redirect'];

		ob_start();
		include BUKUTAMU_PATH . 'templates/form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Halaman "Terima kasih" tujuan redirect setelah submit berhasil — lihat atribut `redirect`
	 * di render_form(). Cocok dipasang di Page dengan template "Buku Tamu — Tanpa Header/Footer"
	 * (lihat CLAUDE.md bagian "Halaman Standalone"), sama seperti [bukutamu_form].
	 */
	public function render_terima_kasih( $atts ): string {
		ob_start();
		include BUKUTAMU_PATH . 'templates/terima-kasih.php';
		return (string) ob_get_clean();
	}

	public function render_testimoni( $atts ): string {
		$atts = shortcode_atts(
			[
				'jumlah' => 6,
			],
			$atts,
			'bukutamu_testimoni'
		);

		$jumlah = max( 1, min( 24, (int) $atts['jumlah'] ) );

		$query = new WP_Query(
			[
				'post_type'      => Bukutamu_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $jumlah,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			]
		);

		ob_start();
		include BUKUTAMU_PATH . 'templates/testimoni-grid.php';
		$output = (string) ob_get_clean();

		wp_reset_postdata();

		return $output;
	}
}
