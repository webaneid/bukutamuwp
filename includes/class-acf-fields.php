<?php
/**
 * Integrasi dengan Advanced Custom Fields PRO.
 *
 * Field group aslinya didefinisikan di acf-json/group_bukutamu_entry.json (ACF Local JSON).
 * Kelas ini hanya: (a) mendaftarkan folder acf-json plugin ini sebagai load point tambahan,
 * dan (b) menampilkan admin notice bila ACF PRO belum aktif, supaya tidak fatal error diam-diam.
 *
 * PENTING (lihat CLAUDE.md > Lessons Learned #2): setelah field group ter-sync dari JSON,
 * jangan edit field lewat UI wp-admin tanpa langsung export ulang ke file JSON yang sama —
 * DB copy dan file JSON bisa jadi tidak sinkron.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_ACF_Fields {

	private static ?Bukutamu_ACF_Fields $instance = null;

	public static function instance(): Bukutamu_ACF_Fields {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_filter( 'acf/settings/load_json', [ $this, 'add_json_load_point' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_dependency_notice' ] );
	}

	public function add_json_load_point( array $paths ): array {
		$paths[] = BUKUTAMU_PATH . 'acf-json';
		return $paths;
	}

	public static function is_acf_active(): bool {
		return class_exists( 'ACF' );
	}

	public static function is_acf_pro_active(): bool {
		return self::is_acf_active() && function_exists( 'acf_get_setting' ) && acf_get_setting( 'pro' );
	}

	public function maybe_show_dependency_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::is_acf_active() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Plugin Buku Tamu membutuhkan Advanced Custom Fields PRO yang aktif. Field data tidak akan muncul sampai ACF PRO diinstal & diaktifkan.', 'bukutamu' )
			);
			return;
		}

		if ( ! self::is_acf_pro_active() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Plugin Buku Tamu mendeteksi ACF versi Free. Field "Galeri Foto" membutuhkan ACF PRO — silakan upgrade ke ACF PRO agar semua fitur berfungsi.', 'bukutamu' )
			);
		}
	}
}
