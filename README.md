# Buku Tamu

Plugin WordPress buku tamu digital — dikembangkan oleh [Webane Indonesia](https://webane.com).

Pengunjung mengisi form (nama, nomor HP, email, instansi/lembaga, kesan & pesan, tanda tangan
digital, lampiran foto) lewat halaman publik. Entri masuk sebagai `pending` menunggu persetujuan
admin, lalu bisa ditampilkan sebagai testimoni/arsip publik.

## Fitur

- Form publik dengan tanda tangan digital (`<canvas>`) dan upload multi-foto, tanpa dependency
  eksternal (vanilla JS, tanpa jQuery).
- Moderasi wajib — entri baru selalu `pending`, admin approve manual di wp-admin.
- Skema data lewat ACF PRO (Local JSON, auto-sync saat plugin aktif).
- Tampilan testimoni (`[bukutamu_testimoni]`) dan arsip lengkap dengan paginasi (archive native
  `/buku-tamu/`).
- Halaman standalone opsional (tanpa header/footer tema) untuk form.
- Styling Tailwind CSS ber-prefix (`bt-`), di-build & di-commit — tidak butuh Node.js di server
  produksi, tidak bentrok dengan CSS tema.
- Data pribadi (nomor HP, email) dan tanda tangan tidak pernah ditampilkan di front-end publik.

## Requirement

- WordPress 6.0+
- PHP 7.4+
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/pro/) aktif

## Instalasi

1. Salin folder ini ke `wp-content/plugins/`.
2. Aktifkan ACF PRO.
3. Aktifkan plugin **Buku Tamu** dari halaman Plugins.
4. Field group ACF otomatis ter-sync lewat Local JSON.

## Development

Build tooling (Tailwind CSS) butuh Node.js hanya saat development, tidak saat runtime produksi.

```bash
npm install
npm run build   # build sekali, hasil ke build/css/bukutamu.css
npm run watch   # rebuild otomatis saat file berubah
```

Baca `CLAUDE.md` untuk arsitektur lengkap, keputusan desain, dan riwayat perbaikan (lessons
learned) sebelum melakukan perubahan.

## Lisensi

GPL v2 or later.
