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
			'package'      => $release->zipball_url,
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
			'download_link' => $release->zipball_url,
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
}
