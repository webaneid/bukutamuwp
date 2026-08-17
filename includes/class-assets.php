<?php
/**
 * Enqueue CSS/JS plugin secara kondisional — hanya di halaman yang benar-benar membutuhkannya
 * (shortcode [bukutamu_form]/[bukutamu_testimoni], atau archive/single native CPT buku_tamu),
 * supaya tidak membebani halaman lain (prinsip "ringan", lihat CLAUDE.md).
 *
 * Keterbatasan yang disadari: deteksi has_shortcode() hanya memeriksa post_content dari
 * postingan utama yang sedang di-query. Bila shortcode dipasang lewat widget, template PHP
 * custom, atau page builder yang tidak menyimpan shortcode di post_content, aset tidak akan
 * ter-enqueue otomatis — gunakan filter `bukutamu_force_enqueue_assets` (return true) untuk
 * memaksa aset dimuat di kasus tersebut.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Assets {

	private static ?Bukutamu_Assets $instance = null;

	public static function instance(): Bukutamu_Assets {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'wp_enqueue_scripts', [ self::$instance, 'register' ] );
			add_action( 'wp_enqueue_scripts', [ self::$instance, 'maybe_enqueue' ], 20 );
		}
		return self::$instance;
	}

	public function register(): void {
		wp_register_style( 'bukutamu', BUKUTAMU_URL . 'build/css/bukutamu.css', [], BUKUTAMU_VERSION );

		wp_register_script(
			'bukutamu-signature',
			BUKUTAMU_URL . 'build/js/bukutamu-signature.js',
			[],
			BUKUTAMU_VERSION,
			true
		);

		wp_register_script(
			'bukutamu-form',
			BUKUTAMU_URL . 'build/js/bukutamu-form.js',
			[ 'bukutamu-signature' ],
			BUKUTAMU_VERSION,
			true
		);

		wp_register_script(
			'bukutamu-lightbox',
			BUKUTAMU_URL . 'build/js/bukutamu-lightbox.js',
			[],
			BUKUTAMU_VERSION,
			true
		);

		wp_localize_script(
			'bukutamu-form',
			'bukutamuFormConfig',
			[
				'restUrl'          => esc_url_raw( rest_url( Bukutamu_Rest_Api::API_NAMESPACE . Bukutamu_Rest_Api::ROUTE ) ),
				'maxFiles'         => Bukutamu_Uploads::MAX_FILES,
				'maxFileSizeBytes' => Bukutamu_Uploads::MAX_FILE_SIZE,
				'i18n'             => [
					'submitting'        => __( 'Mengirim...', 'bukutamu' ),
					'submitLabel'       => __( 'Kirim', 'bukutamu' ),
					'genericError'      => __( 'Terjadi kesalahan. Silakan coba lagi.', 'bukutamu' ),
					'tooManyFiles'      => __( 'Maksimum jumlah foto tercapai.', 'bukutamu' ),
					'fileTooLarge'      => __( 'Ukuran foto terlalu besar.', 'bukutamu' ),
					'signatureRequired' => __( 'Silakan bubuhkan tanda tangan.', 'bukutamu' ),
				],
			]
		);
	}

	public function maybe_enqueue(): void {
		if ( apply_filters( 'bukutamu_force_enqueue_assets', false ) ) {
			$this->enqueue_form();
			$this->enqueue_grid();
			$this->enqueue_single();
			return;
		}

		// Archive & single native CPT buku_tamu (/buku-tamu/...) tidak punya post_content
		// untuk di-scan has_shortcode() — deteksinya langsung lewat query flag WP.
		if ( is_post_type_archive( Bukutamu_CPT::POST_TYPE ) ) {
			$this->enqueue_grid();
			return;
		}

		if ( is_singular( Bukutamu_CPT::POST_TYPE ) ) {
			$this->enqueue_single();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'bukutamu_form' ) ) {
			$this->enqueue_form();
		}

		if ( has_shortcode( $post->post_content, 'bukutamu_testimoni' ) ) {
			$this->enqueue_grid();
		}
	}

	private function enqueue_form(): void {
		wp_enqueue_style( 'bukutamu' );
		wp_enqueue_script( 'bukutamu-signature' );
		wp_enqueue_script( 'bukutamu-form' );
	}

	/**
	 * Grid kartu (testimoni/arsip) — CSS saja, TIDAK butuh JS. Kartu di sini cuma link biasa
	 * ke halaman single (lihat templates/testimoni-card.php), lightbox galeri ada di halaman
	 * single itu sendiri (enqueue_single()).
	 */
	private function enqueue_grid(): void {
		wp_enqueue_style( 'bukutamu' );
	}

	private function enqueue_single(): void {
		wp_enqueue_style( 'bukutamu' );
		wp_enqueue_script( 'bukutamu-lightbox' );
	}
}
