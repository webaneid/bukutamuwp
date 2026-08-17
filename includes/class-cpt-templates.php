<?php
/**
 * Routing template archive/single native untuk CPT buku_tamu, plus mitigasi permukaan yang
 * otomatis ikut terbuka begitu CPT diubah jadi public (lihat CLAUDE.md > Lessons Learned #19
 * untuk konteks keputusannya).
 *
 * Mitigasi yang diterapkan di sini (di luar `show_in_rest => false` yang diatur di class-cpt.php):
 * - Feed RSS khusus archive CPT ini di-redirect ke archive biasa (tidak dimaksudkan untuk
 *   disindikasikan).
 * - Dikecualikan dari sitemap XML — baik Yoast SEO (dipakai tema `jalawarta` di situs ini,
 *   lihat filter `wpseo_sitemap_exclude_post_type` di inc/seo.php tema) MAUPUN sitemap core
 *   WordPress (`wp_sitemaps_post_types`) — supaya tetap termitigasi walau plugin ini dipasang
 *   di situs klien lain dengan SEO plugin berbeda atau tanpa SEO plugin sama sekali.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Cpt_Templates {

	const ARCHIVE_TEMPLATE = 'templates/archive-buku_tamu.php';
	const SINGLE_TEMPLATE  = 'templates/single-buku_tamu.php';
	const POSTS_PER_PAGE   = 12;

	private static ?Bukutamu_Cpt_Templates $instance = null;

	public static function instance(): Bukutamu_Cpt_Templates {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_filter( 'template_include', [ self::$instance, 'load_template' ] );
			add_action( 'pre_get_posts', [ self::$instance, 'set_archive_query_args' ] );
			add_action( 'template_redirect', [ self::$instance, 'block_feed' ] );
			add_filter( 'wpseo_sitemap_exclude_post_type', [ self::$instance, 'exclude_from_yoast_sitemap' ], 10, 2 );
			add_filter( 'wp_sitemaps_post_types', [ self::$instance, 'exclude_from_core_sitemap' ] );
		}
		return self::$instance;
	}

	/**
	 * Jumlah & urutan entri di archive native. Sengaja lewat pre_get_posts (memodifikasi
	 * main query), BUKAN WP_Query terpisah — supaya paginasi bawaan WordPress
	 * (get_previous_posts_page_link()/get_next_posts_page_link(), $wp_query->max_num_pages)
	 * otomatis bekerja tanpa kode paginasi custom (beda dari shortcode [bukutamu_testimoni]
	 * yang memang butuh WP_Query terpisah karena bisa ditaruh di halaman mana saja).
	 */
	public function set_archive_query_args( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_post_type_archive( Bukutamu_CPT::POST_TYPE ) ) {
			$query->set( 'posts_per_page', self::POSTS_PER_PAGE );
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
		}
	}

	/**
	 * Hentikan permintaan feed (mis. /buku-tamu/feed/) — redirect ke archive biasa (bukan 404),
	 * supaya tidak terlihat seperti error di mata crawler/browser.
	 */
	public function block_feed(): void {
		if ( is_feed() && is_post_type_archive( Bukutamu_CPT::POST_TYPE ) ) {
			wp_safe_redirect( get_post_type_archive_link( Bukutamu_CPT::POST_TYPE ) );
			exit;
		}
	}

	public function exclude_from_yoast_sitemap( bool $excluded, string $post_type ): bool {
		return Bukutamu_CPT::POST_TYPE === $post_type ? true : $excluded;
	}

	/**
	 * @param array<string, object> $post_types
	 * @return array<string, object>
	 */
	public function exclude_from_core_sitemap( array $post_types ): array {
		unset( $post_types[ Bukutamu_CPT::POST_TYPE ] );
		return $post_types;
	}

	public function load_template( string $template ): string {
		if ( is_post_type_archive( Bukutamu_CPT::POST_TYPE ) ) {
			return $this->resolve( $template, self::ARCHIVE_TEMPLATE, 'archive-' . Bukutamu_CPT::POST_TYPE . '.php' );
		}

		if ( is_singular( Bukutamu_CPT::POST_TYPE ) ) {
			return $this->resolve( $template, self::SINGLE_TEMPLATE, 'single-' . Bukutamu_CPT::POST_TYPE . '.php' );
		}

		return $template;
	}

	/**
	 * Hormati template tema kalau tema sudah punya file dengan nama standar WordPress
	 * (mis. archive-buku_tamu.php) — plugin ini hanya jadi fallback default, bukan memaksa.
	 */
	private function resolve( string $current_template, string $plugin_relative_path, string $theme_filename ): string {
		if ( basename( $current_template ) === $theme_filename ) {
			return $current_template;
		}

		$plugin_template = BUKUTAMU_PATH . $plugin_relative_path;

		return file_exists( $plugin_template ) ? $plugin_template : $current_template;
	}
}
