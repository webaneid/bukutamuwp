<?php
/**
 * Dijalankan hanya saat plugin di-uninstall (hapus permanen) lewat wp-admin, bukan saat deactivate.
 *
 * Sengaja TIDAK menghapus entri buku_tamu / attachment secara otomatis: data kunjungan (termasuk
 * tanda tangan & foto) adalah data yang mungkin masih dibutuhkan pemilik situs. Penghapusan data
 * adalah keputusan sadar admin (mis. lewat Tools > Export, lalu hapus manual), bukan efek samping
 * uninstall plugin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Opsi internal kecil untuk mekanisme auto-flush rewrite rules (lihat Bukutamu_CPT) — aman
// dihapus, tidak menyimpan data pengunjung apa pun.
delete_option( 'bukutamu_rewrite_version' );
