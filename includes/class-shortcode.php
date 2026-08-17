<?php
/**
 * Registrasi shortcode publik:
 * - [bukutamu_form judul="1"]              → form submission buku tamu
 * - [bukutamu_testimoni jumlah="6"]        → showcase terbatas, tanpa paginasi (landing page)
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
		}
		return self::$instance;
	}

	/**
	 * Atribut `judul="0"` mematikan heading "Buku Tamu" bawaan di dalam card — dipakai saat
	 * shortcode ini ditaruh di halaman yang sudah punya judul sendiri (mis. template standalone
	 * `templates/page-standalone.php`), supaya judul tidak muncul dobel.
	 */
	public function render_form( $atts ): string {
		$atts = shortcode_atts(
			[
				'judul' => '1',
			],
			$atts,
			'bukutamu_form'
		);

		$tampilkan_judul = ! in_array( $atts['judul'], [ '0', 'false', 'tidak' ], true );

		ob_start();
		include BUKUTAMU_PATH . 'templates/form.php';
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
