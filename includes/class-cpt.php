<?php
/**
 * Registrasi Custom Post Type 'buku_tamu'.
 *
 * CPT ini PUBLIK dengan archive native (URL: /buku-tamu/) — keputusan sadar user setelah
 * diberi tahu trade-off-nya dibanding shortcode+Page (lihat CLAUDE.md Lessons Learned #19):
 * setiap entri dapat single URL sendiri, dan permukaan yang otomatis ikut terbuka (feed RSS,
 * sitemap XML) dimitigasi eksplisit di `includes/class-cpt-templates.php`. REST API TETAP
 * tertutup (`show_in_rest => false`) — itu independen dari 'public' dan sengaja tidak diaktifkan.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_CPT {

	const POST_TYPE    = 'buku_tamu';
	const ARCHIVE_SLUG = 'buku-tamu';

	private static ?Bukutamu_CPT $instance = null;

	public static function instance(): Bukutamu_CPT {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'init', [ self::class, 'register' ] );
			add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
			add_action( 'acf/save_post', [ self::class, 'sync_title_from_name' ], 20 );
		}
		return self::$instance;
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'               => __( 'Buku Tamu', 'bukutamu' ),
					'singular_name'      => __( 'Entri Buku Tamu', 'bukutamu' ),
					'menu_name'          => __( 'Buku Tamu', 'bukutamu' ),
					'all_items'          => __( 'Semua Entri', 'bukutamu' ),
					'edit_item'          => __( 'Edit Entri', 'bukutamu' ),
					'view_item'          => __( 'Lihat Entri', 'bukutamu' ),
					'search_items'       => __( 'Cari Entri', 'bukutamu' ),
					'not_found'          => __( 'Belum ada entri buku tamu.', 'bukutamu' ),
					'not_found_in_trash' => __( 'Tidak ada entri di sampah.', 'bukutamu' ),
					'archives'           => __( 'Arsip Buku Tamu', 'bukutamu' ),
				],
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				// Sengaja TIDAK otomatis muncul di menu builder tema — admin yang memilih
				// manual kalau memang mau ditambahkan ke navigasi situs.
				'show_in_nav_menus'   => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				// TETAP false meski 'public' => true — ini pengaturan independen. Mengaktifkan
				// REST API untuk CPT ini akan membuka endpoint /wp-json/wp/v2/buku_tamu yang
				// bisa membocorkan seluruh field (termasuk yang seharusnya tidak publik) lewat
				// respons JSON mentah kalau tidak dikontrol ketat. Tidak dibutuhkan — front-end
				// publik pakai template PHP (archive-buku_tamu.php/single-buku_tamu.php) via
				// query WP biasa, bukan REST.
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-groups',
				'capability_type'     => 'post',
				// Sengaja TIDAK 'title'/'editor' — lihat sync_title_from_name(). Field ACF 'nama'
				// adalah satu-satunya sumber kebenaran; kotak "Add title" & editor konten bawaan
				// WP dihilangkan supaya admin tidak punya tempat lain untuk mengisi data.
				//
				// PENTING: harus literal `false`, BUKAN `[]`. Di WP_Post_Type::add_supports(),
				// array kosong (`[]`) tetap dianggap "supports tidak diisi" dan WordPress
				// fallback ke default `['title', 'editor']` — hanya `false` yang benar-benar
				// mematikan semua default support. Ini ketahuan lewat inspeksi runtime
				// (get_all_post_type_supports()), bukan cuma baca dokumentasi — lihat Lessons
				// Learned #16 di CLAUDE.md.
				'supports'            => false,
				'has_archive'         => self::ARCHIVE_SLUG,
				'rewrite'             => [
					'slug'       => self::ARCHIVE_SLUG,
					'with_front' => false,
				],
				'hierarchical'        => false,
			]
		);
	}

	/**
	 * Flush rewrite rules OTOMATIS sekali setiap kali struktur CPT ini berubah (mis. slug
	 * archive), tanpa perlu admin deactivate+reactivate plugin manual — dicek lewat option
	 * ringan (bukan flush di setiap request; flush_rewrite_rules() mahal). Naikkan
	 * BUKUTAMU_VERSION setiap kali argumen register_post_type() yang mempengaruhi rewrite
	 * berubah, supaya mekanisme ini otomatis jalan lagi di semua instalasi yang meng-update
	 * plugin ini.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'bukutamu_rewrite_version' ) === BUKUTAMU_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( 'bukutamu_rewrite_version', BUKUTAMU_VERSION );
	}

	/**
	 * Sinkronkan post_title dari field ACF 'nama' setiap kali entri disimpan lewat wp-admin.
	 * Karena CPT ini tidak 'supports' => ['title'] (tidak ada kotak "Add title" di layar
	 * edit), ini adalah SATU-SATUNYA cara post_title terisi untuk entri yang disimpan lewat
	 * wp-admin — dipakai list table, pencarian, dan sorting. Untuk entri yang dibuat lewat
	 * REST endpoint publik (Fase 3), post_title sudah di-set langsung saat wp_insert_post().
	 */
	public static function sync_title_from_name( $post_id ): void {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$nama = get_field( 'nama', $post_id );
		if ( ! $nama ) {
			return;
		}

		$nama = sanitize_text_field( $nama );
		if ( $nama === get_the_title( $post_id ) ) {
			return;
		}

		remove_action( 'acf/save_post', [ self::class, 'sync_title_from_name' ], 20 );
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $nama,
			]
		);
		add_action( 'acf/save_post', [ self::class, 'sync_title_from_name' ], 20 );
	}
}
