<?php
/**
 * Fungsi bantu global. Prefix: bukutamu_.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ambil markup SVG ikon inline dari assets/icons/{$name}.svg.
 *
 * Inline (bukan <img> atau icon font) supaya bisa diwarnai lewat CSS (currentColor) tanpa
 * request HTTP tambahan — selaras prinsip "ringan" di CLAUDE.md.
 */
function bukutamu_get_icon( string $name, string $class = '' ): string {
	$name = sanitize_file_name( $name );
	$path = BUKUTAMU_PATH . 'assets/icons/' . $name . '.svg';

	if ( ! file_exists( $path ) ) {
		return '';
	}

	$svg = (string) file_get_contents( $path );

	if ( '' !== $class ) {
		$svg = preg_replace( '/<svg /', '<svg class="' . esc_attr( $class ) . '" ', $svg, 1 );
	}

	return $svg;
}

/**
 * Cetak ikon SVG inline. Aman: sumber selalu file lokal di dalam plugin, bukan input user.
 */
function bukutamu_icon( string $name, string $class = '' ): void {
	echo bukutamu_get_icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG dibaca dari file lokal tepercaya di dalam plugin, bukan dari input pengguna.
}

/**
 * Markup HTML badge identitas situs untuk header halaman publik (standalone/form), dengan
 * fallback bertingkat supaya selalu tampil wajar di situs mana pun plugin ini dipasang:
 *
 * 1. Logo custom dari Customizer (Settings > Site Identity > Logo), bila tema mendukungnya.
 * 2. Site icon / favicon (Settings > General > Site Icon), bila logo tidak diset.
 * 3. Ikon pena bawaan plugin, sebagai fallback terakhir bila situs belum mengatur keduanya.
 *
 * Sengaja pakai URL gambar langsung (bukan get_custom_logo()) supaya markup & ukurannya bisa
 * dikontrol penuh lewat class Tailwind kita sendiri, bukan wrapper default WordPress.
 */
function bukutamu_get_site_branding_html(): string {
	$site_name = get_bloginfo( 'name' );

	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo_url = wp_get_attachment_image_url( (int) $logo_id, 'medium' );
		if ( $logo_url ) {
			return sprintf(
				'<img src="%1$s" alt="%2$s" class="bt-mx-auto bt-mb-4 bt-h-16 bt-max-w-[220px] bt-object-contain">',
				esc_url( $logo_url ),
				esc_attr( $site_name )
			);
		}
	}

	$site_icon_url = get_site_icon_url( 112 );
	if ( $site_icon_url ) {
		return sprintf(
			'<span class="bt-mx-auto bt-mb-4 bt-flex bt-h-14 bt-w-14 bt-items-center bt-justify-center bt-overflow-hidden bt-rounded-2xl bt-bg-white bt-shadow-lg"><img src="%1$s" alt="%2$s" class="bt-h-full bt-w-full bt-object-cover"></span>',
			esc_url( $site_icon_url ),
			esc_attr( $site_name )
		);
	}

	return sprintf(
		'<span class="bt-mx-auto bt-mb-4 bt-flex bt-h-14 bt-w-14 bt-items-center bt-justify-center bt-rounded-2xl bt-bg-slate-900 bt-text-white bt-shadow-lg">%s</span>',
		bukutamu_get_icon( 'pen', 'bt-h-6 bt-w-6' )
	);
}

/**
 * Cetak badge identitas situs. Aman: semua nilai dinamis (URL, nama situs) sudah di-escape di
 * bukutamu_get_site_branding_html(); ID logo/site icon berasal dari pengaturan admin, bukan
 * input publik.
 */
function bukutamu_site_branding(): void {
	echo bukutamu_get_site_branding_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nilai dinamis sudah di-escape di dalam bukutamu_get_site_branding_html().
}
