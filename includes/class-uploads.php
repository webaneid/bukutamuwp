<?php
/**
 * Validasi & penyimpanan file upload dari form publik (galeri foto).
 *
 * Semua file WAJIB lolos validasi mime ASLI (bukan hanya ekstensi/nama file, lihat
 * wp_check_filetype_and_ext()) sebelum dipindahkan ke direktori upload standar WordPress.
 * Plugin ini TIDAK membuat direktori upload custom — semua lewat jalur upload bawaan WP
 * agar proteksi warisan (mis. blokir eksekusi PHP di wp-content/uploads) tetap berlaku.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Uploads {

	const MAX_FILES     = 5;
	const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB — selaras dengan max_size di acf-json gallery field.

	const ALLOWED_MIMES = [
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	];

	private static function ensure_media_functions_loaded(): void {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Ubah struktur $_FILES multi-file (name[]) menjadi daftar array per-file yang mudah diolah.
	 */
	private static function normalize_files_array( array $files ): array {
		if ( ! isset( $files['name'] ) ) {
			return [];
		}

		if ( ! is_array( $files['name'] ) ) {
			return UPLOAD_ERR_NO_FILE === $files['error'] ? [] : [ $files ];
		}

		$normalized = [];
		$count      = count( $files['name'] );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( UPLOAD_ERR_NO_FILE === $files['error'][ $i ] ) {
				continue;
			}
			$normalized[] = [
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			];
		}

		return $normalized;
	}

	/**
	 * Proses semua file di field REST tertentu: validasi lalu upload yang valid.
	 *
	 * @return int[]|WP_Error Daftar attachment ID, atau WP_Error bila ada file tidak valid.
	 */
	public static function handle_gallery_uploads( WP_REST_Request $request, string $field, int $post_id ) {
		$files_param = $request->get_file_params();

		if ( empty( $files_param[ $field ] ) ) {
			return [];
		}

		$files = self::normalize_files_array( $files_param[ $field ] );

		if ( empty( $files ) ) {
			return [];
		}

		if ( count( $files ) > self::MAX_FILES ) {
			return new WP_Error(
				'bukutamu_too_many_files',
				sprintf(
					/* translators: %d: jumlah maksimum file */
					__( 'Maksimum %d foto per pengiriman.', 'bukutamu' ),
					self::MAX_FILES
				),
				[ 'status' => 400 ]
			);
		}

		self::ensure_media_functions_loaded();

		$attachment_ids = [];

		foreach ( $files as $file ) {
			$validated = self::validate_file( $file );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$overrides = [
				'test_form' => false,
				'mimes'     => self::ALLOWED_MIMES,
			];

			$moved = wp_handle_upload( $file, $overrides );

			if ( isset( $moved['error'] ) ) {
				return new WP_Error( 'bukutamu_upload_failed', $moved['error'], [ 'status' => 400 ] );
			}

			$attachment_id = wp_insert_attachment(
				[
					'post_mime_type' => $moved['type'],
					'post_title'     => sanitize_file_name( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_parent'    => $post_id,
				],
				$moved['file'],
				$post_id
			);

			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}

			wp_update_attachment_metadata(
				$attachment_id,
				wp_generate_attachment_metadata( $attachment_id, $moved['file'] )
			);

			$attachment_ids[] = $attachment_id;
		}

		return $attachment_ids;
	}

	/**
	 * Validasi satu file: status upload, ukuran, dan mime type ASLI.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_file( array $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error(
				'bukutamu_upload_error',
				__( 'Terjadi kesalahan saat mengunggah salah satu foto.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'bukutamu_file_too_large',
				sprintf(
					/* translators: %s: ukuran maksimum file dalam MB */
					__( 'Ukuran setiap foto maksimum %sMB.', 'bukutamu' ),
					number_format_i18n( self::MAX_FILE_SIZE / 1024 / 1024 )
				),
				[ 'status' => 400 ]
			);
		}

		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::ALLOWED_MIMES );

		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error(
				'bukutamu_invalid_file_type',
				__( 'Format foto harus JPG, PNG, atau WEBP.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}
}
