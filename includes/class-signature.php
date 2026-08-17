<?php
/**
 * Konversi tanda tangan digital (canvas → PNG base64 dari front-end) menjadi attachment
 * di media library, dengan validasi ketat bahwa data benar-benar gambar PNG yang valid.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Signature {

	const MAX_DECODED_SIZE = 512 * 1024; // 512KB — tanda tangan adalah gambar sederhana, bukan foto.
	const PNG_MAGIC_BYTES  = "\x89PNG\r\n\x1a\n";

	/**
	 * @return int|WP_Error Attachment ID, atau WP_Error bila data tidak valid.
	 */
	public static function save_from_data_url( string $data_url, int $post_id ) {
		if ( ! preg_match( '/^data:image\/png;base64,(?<data>.+)$/', trim( $data_url ), $matches ) ) {
			return new WP_Error(
				'bukutamu_invalid_signature_format',
				__( 'Format tanda tangan tidak valid.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		$decoded = base64_decode( $matches['data'], true );

		if ( false === $decoded ) {
			return new WP_Error(
				'bukutamu_invalid_signature_encoding',
				__( 'Data tanda tangan rusak.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		if ( strlen( $decoded ) > self::MAX_DECODED_SIZE ) {
			return new WP_Error(
				'bukutamu_signature_too_large',
				__( 'Data tanda tangan terlalu besar.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		// Cek magic bytes PNG dulu sebelum getimagesizefromstring(), supaya data yang jelas
		// bukan PNG ditolak tanpa memicu warning PHP pada data biner acak/rusak.
		if ( self::PNG_MAGIC_BYTES !== substr( $decoded, 0, 8 ) ) {
			return new WP_Error(
				'bukutamu_signature_not_image',
				__( 'Data tanda tangan bukan gambar PNG yang valid.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		$image_info = getimagesizefromstring( $decoded );

		if ( false === $image_info || IMAGETYPE_PNG !== $image_info[2] ) {
			return new WP_Error(
				'bukutamu_signature_not_image',
				__( 'Data tanda tangan bukan gambar PNG yang valid.', 'bukutamu' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$filename = 'ttd-' . $post_id . '-' . wp_generate_password( 8, false, false ) . '.png';
		$uploaded = wp_upload_bits( $filename, null, $decoded );

		if ( ! empty( $uploaded['error'] ) ) {
			return new WP_Error( 'bukutamu_signature_save_failed', $uploaded['error'], [ 'status' => 500 ] );
		}

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => 'image/png',
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
			],
			$uploaded['file'],
			$post_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] )
		);

		return $attachment_id;
	}
}
