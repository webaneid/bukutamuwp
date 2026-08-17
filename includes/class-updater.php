<?php
/**
 * Update checker berbasis GitHub Releases — plugin ini tidak di-hosting di WordPress.org,
 * jadi WP tidak tahu cara mengecek versi baru secara native. Class ini menyuntikkan info
 * update ke mekanisme WP Core yang sama dipakai plugin resmi (transient `update_plugins`),
 * supaya "Ada pembaruan tersedia" muncul normal di halaman Plugins — tanpa dependency
 * library eksternal (selaras prinsip "ringan", lihat CLAUDE.md).
 *
 * WAJIB: setiap merilis versi baru, buat GitHub Release dengan tag versi (mis. `v0.3.0`,
 * cocok dengan BUKUTAMU_VERSION tanpa prefix "v") DAN naikkan BUKUTAMU_VERSION di
 * bukutamu.php. Tanpa GitHub Release baru, plugin ini tidak akan pernah terdeteksi ada update
 * — class ini hanya membaca endpoint "latest release", bukan commit/branch terbaru.
 *
 * SENGAJA di-load unconditional (tidak di-guard `is_admin()`) meski hook-hook di sini hanya
 * relevan untuk update — pengecekan otomatis WordPress berjalan lewat WP-Cron
 * ('wp_update_plugins' event), yang BUKAN konteks wp-admin (`is_admin()` bernilai false saat
 * cron jalan). Kalau di-guard `is_admin()`, cek update background dua-kali-sehari tidak akan
 * pernah jalan, update cuma terdeteksi kalau admin kebetulan buka wp-admin. Tidak masalah
 * untuk front-end publik — hook-hook ini (`pre_set_site_transient_update_plugins`, dst) tidak
 * pernah ter-fire di luar konteks admin/cron, jadi tetap inert di front-end tanpa perlu guard.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Updater {

	const GITHUB_REPO    = 'webaneid/bukutamuwp';
	const CACHE_KEY       = 'bukutamu_github_release';
	const CACHE_DURATION  = 12 * HOUR_IN_SECONDS;
	const CACHE_DURATION_FAILED = 15 * MINUTE_IN_SECONDS;

	private static ?Bukutamu_Updater $instance = null;

	public static function instance(): Bukutamu_Updater {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_filter( 'pre_set_site_transient_update_plugins', [ self::$instance, 'check_for_update' ] );
			add_filter( 'plugins_api', [ self::$instance, 'plugin_info' ], 10, 3 );
			add_filter( 'upgrader_source_selection', [ self::$instance, 'fix_source_folder_name' ], 10, 4 );
			add_filter( 'upgrader_post_install', [ self::$instance, 'clear_cache_after_update' ], 10, 3 );
			add_filter( 'plugin_action_links_' . BUKUTAMU_BASENAME, [ self::$instance, 'plugin_action_links' ] );
			add_action( 'admin_init', [ self::$instance, 'handle_force_check' ] );
			add_action( 'admin_notices', [ self::$instance, 'render_force_check_notice' ] );
		}
		return self::$instance;
	}

	/**
	 * Ambil data rilis terbaru dari GitHub API, di-cache lewat transient supaya tidak
	 * membebani rate limit GitHub API (60 request/jam untuk request tanpa token — cukup untuk
	 * cek update berkala, TIDAK cukup kalau dipanggil setiap page load tanpa cache).
	 *
	 * @return object|null
	 */
	private function get_latest_release(): ?object {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			[
				'headers' => [ 'Accept' => 'application/vnd.github+json' ],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache kegagalan juga (durasi lebih pendek) — supaya request yang gagal (GitHub
			// down/rate-limited) tidak diulang terus-menerus di setiap page load wp-admin.
			set_transient( self::CACHE_KEY, false, self::CACHE_DURATION_FAILED );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $data ) || empty( $data->tag_name ) ) {
			set_transient( self::CACHE_KEY, false, self::CACHE_DURATION_FAILED );
			return null;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_DURATION );

		return $data;
	}

	/**
	 * Tag GitHub biasanya diawali "v" (mis. "v0.3.0") — BUKUTAMU_VERSION tidak. Normalisasi
	 * di satu tempat supaya version_compare() konsisten.
	 */
	private function get_remote_version( object $release ): string {
		return ltrim( (string) $release->tag_name, 'vV' );
	}

	/**
	 * URL paket ZIP yang dipakai untuk instal/update. Prioritas: asset ZIP yang di-upload
	 * manual ke Release (lewat bin/release.sh) — isinya sudah dikemas benar (folder
	 * "bukutamu/", cuma file runtime, tanpa file dev). Fallback ke `zipball_url` (source zip
	 * otomatis GitHub) HANYA kalau rilis tidak punya asset ZIP — folder di dalamnya bernama
	 * "{repo}-{hash}", makanya `fix_source_folder_name()` tetap dipertahankan sebagai jaring
	 * pengaman, bukan dihapus, untuk skenario fallback ini.
	 */
	private function get_package_url( object $release ): string {
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( ! empty( $asset->browser_download_url ) && preg_match( '/\.zip$/i', (string) ( $asset->name ?? '' ) ) ) {
					return $asset->browser_download_url;
				}
			}
		}

		return (string) $release->zipball_url;
	}

	/**
	 * Suntik info update ke transient bawaan WordPress — mekanisme yang sama dipakai plugin
	 * resmi WordPress.org, supaya "Ada pembaruan" muncul normal di halaman Plugins tanpa UI
	 * custom tambahan.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->get_remote_version( $release );

		if ( ! version_compare( $remote_version, BUKUTAMU_VERSION, '>' ) ) {
			return $transient;
		}

		$transient->response[ BUKUTAMU_BASENAME ] = (object) [
			'slug'         => 'bukutamu',
			'plugin'       => BUKUTAMU_BASENAME,
			'new_version'  => $remote_version,
			'url'          => 'https://github.com/' . self::GITHUB_REPO,
			'package'      => $this->get_package_url( $release ),
			'tested'       => get_bloginfo( 'version' ),
			'requires'     => '6.0',
			'requires_php' => '7.4',
		];

		return $transient;
	}

	/**
	 * Data untuk modal "Lihat detail versi X.X" di halaman Plugins.
	 *
	 * @param false|object|array $result
	 * @return false|object|array
	 */
	public function plugin_info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'bukutamu' !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Buku Tamu',
			'slug'          => 'bukutamu',
			'version'       => $this->get_remote_version( $release ),
			'author'        => wp_kses( '<a href="https://webane.com">Webane Indonesia</a>', [ 'a' => [ 'href' => [] ] ] ),
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'sections'      => [
				'description' => '<p>' . esc_html__( 'Buku tamu digital untuk WordPress — Webane Indonesia.', 'bukutamu' ) . '</p>',
				'changelog'   => $this->format_changelog( (string) ( $release->body ?? '' ) ),
			],
			'download_link' => $this->get_package_url( $release ),
		];
	}

	/**
	 * Catatan rilis GitHub berformat Markdown mentah — cukup di-escape & wpautop(), tidak
	 * perlu parser Markdown penuh (dependency berat) untuk sekadar tampilan changelog.
	 */
	private function format_changelog( string $markdown ): string {
		if ( '' === trim( $markdown ) ) {
			return '<p>' . esc_html__( 'Tidak ada catatan rilis.', 'bukutamu' ) . '</p>';
		}

		return wpautop( esc_html( $markdown ) );
	}

	/**
	 * GitHub membungkus zipball dalam folder bernama "{repo}-{hash-singkat}", BUKAN "bukutamu"
	 * — kalau dibiarkan, WordPress akan menganggapnya plugin baru yang terpisah (folder tidak
	 * cocok dengan BUKUTAMU_BASENAME), bukan update untuk plugin yang sudah aktif. Hook ini
	 * me-rename folder hasil ekstrak supaya cocok, sebelum WP memindahkannya ke wp-content/plugins/.
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param WP_Upgrader $upgrader
	 * @param array<string, mixed> $hook_extra
	 * @return string|WP_Error
	 */
	public function fix_source_folder_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || BUKUTAMU_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . 'bukutamu/';

		if ( untrailingslashit( $source ) === untrailingslashit( $target ) ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $target ) ) {
			return $target;
		}

		return $source;
	}

	/**
	 * @param bool|WP_Error $response
	 * @param array<string, mixed> $hook_extra
	 * @param array<string, mixed> $result
	 * @return bool|WP_Error
	 */
	public function clear_cache_after_update( $response, $hook_extra = [], $result = [] ) {
		if ( ! empty( $hook_extra['plugin'] ) && BUKUTAMU_BASENAME === $hook_extra['plugin'] ) {
			delete_transient( self::CACHE_KEY );
		}

		return $response;
	}

	/**
	 * Tautan "Cek Update" di baris plugin (halaman Plugins), sejajar dengan "Nonaktifkan" dkk
	 * bawaan WordPress — pola yang sama dipakai plugin lain di situs ini (mis. Webane Database).
	 * Cache 12 jam (Bukutamu_Updater::CACHE_KEY) membuat cek update otomatis WP-Cron terasa
	 * lambat saat diuji manual; tautan ini memaksa fetch ulang ke GitHub API saat itu juga,
	 * TANPA menunggu cache kedaluwarsa — supaya admin tidak perlu menunggu 12 jam atau meng-
	 * utak-atik transient manual lewat wp-admin biasa untuk memverifikasi rilis baru terbaca.
	 *
	 * @param string[] $links
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}

		$url = wp_nonce_url(
			add_query_arg( 'bukutamu_check_update', '1', admin_url( 'plugins.php' ) ),
			'bukutamu_check_update'
		);

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Cek Update', 'bukutamu' ) . '</a>' );

		return $links;
	}

	/**
	 * Handler tautan "Cek Update" di atas — hapus cache GitHub Release DAN transient update
	 * bawaan WP, lalu panggil `wp_update_plugins()` (fungsi core yang sama dipakai WP-Cron/
	 * tombol "Periksa Lagi" di Dashboard > Updates) supaya hasilnya langsung ter-refresh saat
	 * itu juga, bukan menunggu siklus cache/cron berikutnya.
	 */
	public function handle_force_check(): void {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) || empty( $_GET['bukutamu_check_update'] ) ) {
			return;
		}

		check_admin_referer( 'bukutamu_check_update' );

		delete_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_plugins();

		wp_safe_redirect( add_query_arg( 'bukutamu_update_checked', '1', admin_url( 'plugins.php' ) ) );
		exit;
	}

	/**
	 * Notice hasil "Cek Update" — beda pesan tergantung apakah update ditemukan atau tidak,
	 * supaya admin tahu pengecekan SUNGGUHAN terjadi (bukan cuma redirect kosong tanpa umpan
	 * balik apa pun), termasuk saat hasilnya memang "sudah versi terbaru".
	 */
	public function render_force_check_notice(): void {
		if ( ! is_admin() || empty( $_GET['bukutamu_update_checked'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$transient  = get_site_transient( 'update_plugins' );
		$has_update = isset( $transient->response[ BUKUTAMU_BASENAME ] );

		if ( $has_update ) {
			$message = sprintf(
				/* translators: %s: nomor versi baru */
				__( 'Buku Tamu: update ke versi %s tersedia — silakan klik "Perbarui Sekarang" di bawah.', 'bukutamu' ),
				$transient->response[ BUKUTAMU_BASENAME ]->new_version
			);
		} else {
			$message = sprintf(
				/* translators: %s: nomor versi yang sedang aktif */
				__( 'Buku Tamu: sudah menggunakan versi terbaru (%s).', 'bukutamu' ),
				BUKUTAMU_VERSION
			);
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $has_update ? 'info' : 'success' ),
			esc_html( $message )
		);
	}
}
