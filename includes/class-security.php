<?php
/**
 * Lapisan keamanan untuk endpoint submission publik: nonce, honeypot, deteksi waktu submit,
 * dan rate limiting per-IP. Semua pengecekan di sini WAJIB lolos sebelum data disimpan ke DB.
 *
 * Catatan penting: nonce di sini BUKAN proteksi CSRF klasik — pengunjung anonim tidak punya
 * sesi ter-autentikasi yang bisa di-CSRF-kan. Fungsinya murni menaikkan biaya bagi bot yang
 * menembak endpoint langsung tanpa pernah memuat halaman form (lihat CLAUDE.md).
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu_Security {

	const NONCE_ACTION        = 'bukutamu_submit';
	const HONEYPOT_FIELD      = 'bukutamu_hp_check';
	const TIMESTAMP_FIELD     = 'bukutamu_ts';
	const MIN_SUBMIT_SECONDS  = 3;
	const RATE_LIMIT_WINDOW   = 60; // detik
	const RATE_LIMIT_MAX      = 1;  // submission per window per IP

	public static function create_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	public static function verify_nonce( ?string $nonce ): bool {
		if ( empty( $nonce ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * True jika honeypot terisi — indikasi kuat bot mengisi semua field secara otomatis.
	 */
	public static function honeypot_triggered( ?string $value ): bool {
		return ! empty( $value );
	}

	/**
	 * True jika submit terjadi terlalu cepat setelah form dimuat — indikasi bot.
	 * $loaded_at adalah timestamp Unix yang dirender server saat form ditampilkan.
	 */
	public static function submitted_too_fast( $loaded_at ): bool {
		$loaded_at = (int) $loaded_at;
		if ( $loaded_at <= 0 ) {
			return true;
		}
		return ( time() - $loaded_at ) < self::MIN_SUBMIT_SECONDS;
	}

	/**
	 * Alamat IP pengirim. Sengaja hanya mempercayai REMOTE_ADDR (bukan header
	 * X-Forwarded-For / X-Real-IP) karena header tersebut mudah dipalsukan klien kecuali
	 * situs berada di belakang reverse proxy tepercaya yang mengaturnya secara eksplisit.
	 */
	public static function get_client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';
	}

	/**
	 * True jika IP ini sudah melewati batas rate limit dan permintaan harus ditolak.
	 * Efek samping: bila belum melewati batas, hitungan untuk window saat ini ditambah.
	 */
	public static function is_rate_limited(): bool {
		$key   = 'bukutamu_rl_' . md5( self::get_client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return true;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
		return false;
	}

	/**
	 * Validasi format nomor HP secara longgar: digit, spasi, plus, strip, panjang wajar.
	 * Tujuannya mencegah input sampah, bukan memverifikasi operator seluler yang valid.
	 */
	public static function is_valid_phone( string $phone ): bool {
		return (bool) preg_match( '/^[0-9+\-\s]{8,20}$/', $phone );
	}
}
