<?php
/**
 * Endpoint REST publik untuk submission form buku tamu.
 *
 * POST /wp-json/bukutamu/v1/submit
 *
 * Semua request dianggap TIDAK tepercaya. `permission_callback` sengaja `__return_true`
 * karena endpoint ini memang untuk publik (belum tentu login) — gerbang keamanannya bukan
 * capability WordPress, melainkan nonce + honeypot + timing check + rate limit di dalam
 * callback (lihat Bukutamu_Security).
 *
 * Urutan validasi sengaja disusun dari yang paling murah ke yang paling mahal, dan
 * wp_insert_post() baru terjadi setelah semua validasi field lolos — supaya endpoint tidak
 * bisa dipakai membanjiri DB/media library sebelum validasi dasar selesai. Bila penyimpanan
 * tanda tangan/galeri gagal setelah post dibuat, post tersebut di-rollback (dihapus) supaya
 * tidak ada entri "setengah jadi" yang nyangkut di status pending.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Rest_Api {

	const API_NAMESPACE = 'bukutamu/v1';
	const ROUTE          = '/submit';

	private static ?Bukutamu_Rest_Api $instance = null;

	public static function instance(): Bukutamu_Rest_Api {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'rest_api_init', [ self::$instance, 'register_routes' ] );
		}
		return self::$instance;
	}

	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_submit' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_submit( WP_REST_Request $request ) {
		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error(
				'bukutamu_acf_missing',
				__( 'Konfigurasi situs belum lengkap. Hubungi admin situs.', 'bukutamu' ),
				[ 'status' => 500 ]
			);
		}

		// 1. Anti-abuse murah dulu, sebelum apa pun disentuh.
		if ( ! Bukutamu_Security::verify_nonce( $request->get_param( 'bukutamu_nonce' ) ) ) {
			return new WP_Error(
				'bukutamu_invalid_nonce',
				__( 'Sesi form kedaluwarsa, silakan muat ulang halaman.', 'bukutamu' ),
				[ 'status' => 403 ]
			);
		}

		if (
			Bukutamu_Security::honeypot_triggered( $request->get_param( Bukutamu_Security::HONEYPOT_FIELD ) )
			|| Bukutamu_Security::submitted_too_fast( $request->get_param( Bukutamu_Security::TIMESTAMP_FIELD ) )
		) {
			// Jangan beri tahu bot bahwa ia terdeteksi — balas seolah sukses, tapi jangan simpan apa pun.
			return $this->success_response();
		}

		// 2. Validasi & sanitasi field teks. Sengaja dilakukan SEBELUM rate limit check,
		//    supaya pengunjung yang salah ketik (mis. format email) tidak ikut menghabiskan
		//    jatah rate limit hanya untuk mendapati error validasi biasa.
		$data = $this->sanitize_and_validate_fields( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$signature_data_url = (string) $request->get_param( 'ttd_data' );
		if ( '' === trim( $signature_data_url ) ) {
			return new WP_Error(
				'bukutamu_missing_signature',
				__( 'Tanda tangan wajib diisi.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		// 3. Rate limit baru dicek setelah data terbukti valid — supaya jatah "1 submission /
		//    60 detik" per IP hanya berlaku untuk percobaan submit yang sungguhan, bukan typo.
		if ( Bukutamu_Security::is_rate_limited() ) {
			return new WP_Error(
				'bukutamu_rate_limited',
				__( 'Terlalu banyak percobaan. Coba lagi sebentar lagi.', 'bukutamu' ),
				[ 'status' => 429 ]
			);
		}

		// 4. Buat entri — status SELALU 'pending', menunggu moderasi admin.
		$post_id = wp_insert_post(
			[
				'post_type'   => Bukutamu_CPT::POST_TYPE,
				'post_status' => 'pending',
				'post_title'  => $data['nama'],
				'post_author' => $this->get_entry_author_id(),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'bukutamu_insert_failed',
				__( 'Gagal menyimpan entri, silakan coba lagi.', 'bukutamu' ),
				[ 'status' => 500 ]
			);
		}

		// 5. Simpan tanda tangan & galeri foto. Rollback post bila salah satu gagal.
		$signature_id = Bukutamu_Signature::save_from_data_url( $signature_data_url, $post_id );
		if ( is_wp_error( $signature_id ) ) {
			wp_delete_post( $post_id, true );
			return $signature_id;
		}

		$gallery_ids = Bukutamu_Uploads::handle_gallery_uploads( $request, 'galeri_foto', $post_id );
		if ( is_wp_error( $gallery_ids ) ) {
			wp_delete_post( $post_id, true );
			return $gallery_ids;
		}

		// 6. Tulis semua field ACF (pakai field KEY, bukan nama, agar tidak ambigu — lihat CLAUDE.md).
		update_field( 'field_bukutamu_nama', $data['nama'], $post_id );
		update_field( 'field_bukutamu_nomor_hp', $data['nomor_hp'], $post_id );
		update_field( 'field_bukutamu_email', $data['email'], $post_id );
		update_field( 'field_bukutamu_instansi', $data['instansi'], $post_id );
		update_field( 'field_bukutamu_kesan_pesan', $data['kesan_pesan'], $post_id );
		update_field( 'field_bukutamu_ttd', $signature_id, $post_id );
		update_field( 'field_bukutamu_galeri_foto', $gallery_ids, $post_id );
		update_field( 'field_bukutamu_tanggal', current_time( 'Y-m-d H:i:s' ), $post_id );
		update_field( 'field_bukutamu_persetujuan', true, $post_id );

		return $this->success_response();
	}

	/**
	 * @return array<string, string>|WP_Error
	 */
	private function sanitize_and_validate_fields( WP_REST_Request $request ) {
		$nama        = sanitize_text_field( (string) $request->get_param( 'nama' ) );
		$hp          = sanitize_text_field( (string) $request->get_param( 'nomor_hp' ) );
		$email       = sanitize_email( (string) $request->get_param( 'email' ) );
		$instansi    = sanitize_text_field( (string) $request->get_param( 'instansi' ) );
		$kesan_pesan = sanitize_textarea_field( (string) $request->get_param( 'kesan_pesan' ) );
		$persetujuan = (string) $request->get_param( 'persetujuan_privasi' );

		if ( '' === $nama ) {
			return new WP_Error( 'bukutamu_missing_nama', __( 'Nama wajib diisi.', 'bukutamu' ), [ 'status' => 400 ] );
		}

		if ( '' === $hp || ! Bukutamu_Security::is_valid_phone( $hp ) ) {
			return new WP_Error( 'bukutamu_invalid_phone', __( 'Nomor HP tidak valid.', 'bukutamu' ), [ 'status' => 400 ] );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'bukutamu_invalid_email', __( 'Email tidak valid.', 'bukutamu' ), [ 'status' => 400 ] );
		}

		if ( '' === $kesan_pesan ) {
			return new WP_Error( 'bukutamu_missing_message', __( 'Kesan dan pesan wajib diisi.', 'bukutamu' ), [ 'status' => 400 ] );
		}

		if ( ! in_array( $persetujuan, [ '1', 'true', 'on' ], true ) ) {
			return new WP_Error( 'bukutamu_consent_required', __( 'Anda harus menyetujui kebijakan privasi.', 'bukutamu' ), [ 'status' => 400 ] );
		}

		return [
			'nama'        => mb_substr( $nama, 0, 150 ),
			'nomor_hp'    => mb_substr( $hp, 0, 20 ),
			'email'       => $email,
			'instansi'    => mb_substr( $instansi, 0, 150 ),
			'kesan_pesan' => mb_substr( $kesan_pesan, 0, 1000 ),
		];
	}

	/**
	 * Entri publik butuh post_author yang valid (bukan 0) supaya kompatibel dengan query
	 * WordPress standar. Default ke user administrator pertama; bisa dioverride lewat filter
	 * `bukutamu_entry_author_id` (mis. untuk multisite atau struktur role kustom).
	 */
	private function get_entry_author_id(): int {
		$author_id = (int) apply_filters( 'bukutamu_entry_author_id', 0 );
		if ( $author_id > 0 ) {
			return $author_id;
		}

		$admins = get_users(
			[
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'fields'  => 'ID',
			]
		);

		return ! empty( $admins ) ? (int) $admins[0] : 1;
	}

	private function success_response(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Terima kasih! Entri Anda telah terkirim dan menunggu persetujuan admin.', 'bukutamu' ),
			],
			200
		);
	}
}
