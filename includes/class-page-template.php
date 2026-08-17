<?php
/**
 * Registrasi template halaman "tanpa header/footer" (standalone) untuk buku tamu, TANPA perlu
 * mengubah file tema apa pun. Pakai mekanisme page-template klasik WordPress (didukung hampir
 * semua tema, classic maupun block editor): filter `theme_page_templates` menambah opsi ke
 * dropdown "Page Attributes > Template", filter `template_include` mengarahkan WP untuk
 * memuat file template dari dalam plugin ini alih-alih dari tema.
 *
 * Cara pakai (admin): buat Page baru → isi konten dengan shortcode [bukutamu_form] atau
 * [bukutamu_testimoni] → pilih "Buku Tamu — Tanpa Header/Footer" di panel Page Attributes.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Page_Template {

	const SLUG = 'templates/page-standalone.php';

	private static ?Bukutamu_Page_Template $instance = null;

	public static function instance(): Bukutamu_Page_Template {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_filter( 'theme_page_templates', [ self::$instance, 'register_template' ] );
			add_filter( 'template_include', [ self::$instance, 'load_template' ] );
		}
		return self::$instance;
	}

	public function register_template( array $templates ): array {
		$templates[ self::SLUG ] = __( 'Buku Tamu — Tanpa Header/Footer', 'bukutamu' );
		return $templates;
	}

	public function load_template( string $template ): string {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}

		if ( self::SLUG !== get_page_template_slug( get_queried_object_id() ) ) {
			return $template;
		}

		$custom = BUKUTAMU_PATH . self::SLUG;

		return file_exists( $custom ) ? $custom : $template;
	}
}
