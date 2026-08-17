# CLAUDE.md — Plugin Buku Tamu

Dokumen ini adalah rujukan wajib untuk siapa pun (manusia atau Claude) yang mengembangkan plugin ini.
Baca dari atas sebelum menulis kode. Perbarui bagian **Lessons Learned** setiap kali menemukan/memperbaiki
kesalahan — tujuannya agar kesalahan yang sama tidak terulang di sesi berikutnya.

## Identitas Proyek

| Item | Nilai |
|---|---|
| Nama Plugin | Buku Tamu |
| Slug | `bukutamu` |
| Text Domain | `bukutamu` |
| Developer | Webane Indonesia — https://webane.com |
| Fungsi | Buku tamu digital untuk WordPress: form kunjungan publik + moderasi admin + tampilan testimoni |
| Requires PHP | 7.4+ |
| Requires WP | 6.0+ |
| Dependency wajib | Advanced Custom Fields **PRO** (untuk field type Gallery) |
| Lisensi | GPL v2 or later |

## Prinsip Desain (jangan dilanggar tanpa alasan kuat)

1. **Ringan & mandiri (self-contained).** Tidak bergantung pada CSS/JS bawaan WordPress (`wp-block-library`,
   dashicons, dsb). Semua styling pakai Tailwind yang di-build & di-purge sendiri, di-*prefix* (`bt-`) agar
   tidak bentrok dengan tema. Aset hanya dimuat di halaman yang benar-benar memakai shortcode plugin ini
   (conditional enqueue via `has_shortcode()`), bukan global di semua halaman.
2. **ACF sebagai lapisan skema data**, bukan sebagai UI form publik. Field group didefinisikan lewat
   **ACF Local JSON** (folder `acf-json/`) supaya field langsung ter-sync begitu plugin diaktifkan di
   instalasi mana pun — tidak perlu import manual. ACF dipakai untuk: (a) menyimpan & menampilkan data di
   wp-admin, (b) `get_field()`/`update_field()` sebagai API baca-tulis dari kode custom.
3. **Form publik dibuat custom** (bukan `acf_form()`), karena butuh: tanda tangan digital via `<canvas>`,
   preview upload foto, validasi real-time, dan desain Tailwind penuh tanpa CSS bawaan ACF. Form
   mengirim data lewat REST API custom, bukan submit form biasa, dan bukan input administrator, karena
   ini disediakan untuk pengguna publik (belum tentu login) — semua alur harus aman untuk request anonim.
4. **Data store = Custom Post Type**, bukan tabel custom. Alasan: dapat gratis moderasi status
   (`pending`/`publish`), media library, ACF native support, revisions, tanpa perlu bikin skema tabel +
   migrasi sendiri. Untuk skala buku tamu (ratusan–ribuan entri), `WP_Query` + meta index bawaan cukup.
5. **Aman secara default.** Semua input publik dianggap tidak tepercaya. Validasi & sanitasi di server,
   bukan hanya di JS. Lihat bagian Keamanan.
6. **Moderasi wajib.** Entri baru dari form publik selalu masuk sebagai `pending`. Hanya admin/editor yang
   bisa approve ke `publish` agar tampil di shortcode testimoni.

## Arsitektur Data

### Custom Post Type: `buku_tamu`

> **Riwayat keputusan:** versi awal plugin ini `public => false` (tanpa archive/single URL sama
> sekali) demi mengurangi attack surface. User secara sadar memilih mengubahnya jadi **publik
> dengan archive native** (lihat Lessons Learned #19) setelah diberi tahu trade-off-nya. Konfigurasi
> di bawah adalah kondisi SAAT INI (sejak v0.2.0) — bukan lagi `public => false`.

```php
register_post_type( 'buku_tamu', [
    'label'               => 'Buku Tamu',
    'public'              => true,
    'publicly_queryable'  => true,
    'exclude_from_search' => true,        // tidak muncul di hasil pencarian situs
    'show_in_nav_menus'   => false,       // tidak otomatis muncul di menu builder tema
    'show_ui'             => true,
    'show_in_menu'        => true,
    // TETAP false meski 'public' => true — independen, lihat class-cpt.php untuk alasan lengkap
    // (REST API terbuka = risiko field ter-expose mentah lewat JSON).
    'show_in_rest'        => false,
    'menu_icon'           => 'dashicons-groups',
    'capability_type'     => 'post',      // sengaja TIDAK custom, lihat Lessons Learned #1
    'supports'            => false,       // sengaja TANPA 'title' — lihat Lessons Learned #15/#16
    'has_archive'         => 'buku-tamu', // URL: /buku-tamu/
    'rewrite'             => [ 'slug' => 'buku-tamu', 'with_front' => false ],
] );
```

- `post_title` = nilai field `nama`, tapi **tidak ada kotak "Add title" di layar edit** (`supports`
  sengaja tidak menyertakan `'title'`). `post_title` diisi otomatis: langsung saat `wp_insert_post()` untuk
  entri dari REST publik, atau lewat hook `acf/save_post` (`Bukutamu_CPT::sync_title_from_name()`) untuk
  entri yang disimpan admin lewat wp-admin. Field `nama` di ACF adalah **satu-satunya** tempat admin
  mengisi nama pengunjung — lihat Lessons Learned #15 untuk alasannya.
- `post_status`: `pending` (baru masuk, menunggu approve) → `publish` (tampil di shortcode testimoni) →
  admin bisa `trash` untuk spam.
- `post_author`: request publik anonim, jadi di-set ke user ID admin default (opsi
  `bukutamu_default_author`, fallback ke user administrator pertama) — **bukan** 0, supaya kompatibel
  dengan query `author` WordPress standar.

### Field Group ACF: `group_bukutamu_entry` (lokasi: `acf-json/group_bukutamu_entry.json`)

| # | Label | Field key | Meta name | Tipe ACF | Wajib | Catatan |
|---|---|---|---|---|---|---|
| 1 | Nama | `field_bukutamu_nama` | `nama` | Text | Ya | disalin ke `post_title` |
| 2 | Nomor HP | `field_bukutamu_nomor_hp` | `nomor_hp` | Text | Ya | validasi pola angka di server |
| 3 | Email | `field_bukutamu_email` | `email` | Email | Ya | `sanitize_email` + `is_email` |
| 4 | Instansi / Lembaga | `field_bukutamu_instansi` | `instansi` | Text | Tidak | |
| 5 | Kesan & Pesan | `field_bukutamu_kesan_pesan` | `kesan_pesan` | Textarea | Ya | `sanitize_textarea_field` |
| 6 | Tanda Tangan | `field_bukutamu_ttd` | `ttd` | Image (return: array) | Ya | diisi via canvas → PNG → sideload; **TIDAK PERNAH ditampilkan front-end**, cuma di wp-admin |
| 7 | Galeri Foto | `field_bukutamu_galeri_foto` | `galeri_foto` | **Gallery (PRO)** | Tidak | diisi pengunjung saat submit, admin bisa tambah lewat wp-admin; di card 1 foto acak, di single semua foto |
| 8 | Tanggal Kunjungan | `field_bukutamu_tanggal` | `tanggal_kunjungan` | Date Time Picker | otomatis | di-set server saat insert, read-only di form publik |
| 9 | Persetujuan Privasi | `field_bukutamu_persetujuan` | `persetujuan_privasi` | True/False | Ya | checkbox consent, wajib dicentang sebelum submit |

Catatan desain:
- **Tidak ada field "foto utama" terpisah.** Untuk tampilan testimoni, "satu foto yang ditampilkan" cukup
  diambil dari elemen pertama `galeri_foto` (`$galeri[0]`) — menghindari duplikasi data & sinkronisasi
  manual antara "foto utama" vs "galeri".
- Field #6 dan #8 tidak pernah diinput manual oleh pengunjung lewat widget ACF bawaan; keduanya diisi oleh
  kode PHP (`update_field()`) saat memproses submission REST API, supaya UX front-end tetap custom Tailwind
  namun data tetap tersimpan lewat mekanisme ACF (searchable/editable normal di wp-admin).

## Struktur Folder (kondisi aktual — Fase 1, 3, 4 sudah diimplementasikan)

```
bukutamu/
├── bukutamu.php                  # Bootstrap plugin, header, hooks aktivasi
├── uninstall.php                 # Bersihkan opsi & (opsional) data saat uninstall
├── CLAUDE.md                     # Dokumen ini — arsitektur, keputusan desain, lessons learned
├── README.md                     # Dokumentasi ringkas untuk GitHub (fitur, instalasi, development)
├── package.json / package-lock.json  # Dev-only, untuk build Tailwind (lihat .gitignore: node_modules/)
├── .gitignore
├── bin/
│   └── release.sh                # Build & publish rilis ke GitHub Releases — lihat Sistem Update Plugin
├── acf-json/
│   └── group_bukutamu_entry.json
├── includes/
│   ├── class-bukutamu.php        # Singleton bootstrap, load semua modul
│   ├── class-cpt.php             # Register CPT buku_tamu (public, has_archive — lihat Lessons Learned #16/#19)
│   ├── class-cpt-templates.php   # Routing archive/single native + mitigasi (feed, sitemap) — lihat Lessons Learned #19
│   ├── class-acf-fields.php      # Daftarkan acf-json load point + notice bila ACF PRO tidak aktif
│   ├── class-rest-api.php        # Endpoint POST /wp-json/bukutamu/v1/submit
│   ├── class-uploads.php         # Validasi & simpan file foto (mime, size, count limit)
│   ├── class-signature.php       # Decode base64 PNG dari canvas → wp_insert_attachment
│   ├── class-security.php        # Nonce, honeypot, rate limit, sanitasi terpusat
│   ├── class-shortcode.php       # [bukutamu_form] dan [bukutamu_testimoni]
│   ├── class-assets.php          # Conditional enqueue CSS/JS via has_shortcode()
│   ├── class-page-template.php   # Page template "Tanpa Header/Footer" (theme_page_templates/template_include)
│   ├── class-updater.php         # Update checker via GitHub Releases — lihat bagian Sistem Update Plugin
│   ├── class-admin.php           # ⬜ BELUM ADA — Fase 2 (kolom list table, quick-approve), masih pending
│   └── helpers.php               # bukutamu_icon() / bukutamu_get_icon()
├── build/
│   ├── css/bukutamu.css          # Hasil build Tailwind (di-commit, tidak butuh Node saat runtime)
│   └── js/
│       ├── bukutamu-form.js
│       ├── bukutamu-signature.js
│       └── bukutamu-lightbox.js  # Hanya di-enqueue di halaman single (thumbnail → popup besar)
├── src/                           # Sumber dev-only (Tailwind config, JS belum dibundel)
│   ├── tailwind.config.js        # prefix 'bt-', preflight OFF — lihat Lessons Learned #7
│   └── input.css
├── templates/
│   ├── form.php
│   ├── terima-kasih.php          # Untuk shortcode [bukutamu_terima_kasih] — tujuan redirect setelah submit
│   ├── testimoni-grid.php        # Untuk shortcode [bukutamu_testimoni]
│   ├── testimoni-card.php        # Dipakai ulang oleh testimoni, archive native, DAN single native
│   ├── archive-buku_tamu.php     # Archive native (/buku-tamu/) — panggil get_header()/get_footer() tema
│   ├── single-buku_tamu.php      # Detail satu entri (/buku-tamu/{slug}/) — juga dengan header/footer tema
│   └── page-standalone.php       # Template "Tanpa Header/Footer" — lihat bagian Halaman Standalone
└── assets/icons/                  # 14 SVG mentah, di-inline via helper bukutamu_icon()
```

## Alur Submission (Publik)

1. Halaman menampilkan `[bukutamu_form]` → render `templates/form.php` (Tailwind, tanpa CSS bawaan WP/ACF).
2. Form berisi: nama, nomor HP, email, instansi, kesan & pesan, canvas tanda tangan, input multi-file foto,
   checkbox persetujuan privasi, **honeypot field tersembunyi**, dan nonce (`wp_create_nonce('bukutamu_submit')`).
3. JS (`bukutamu-form.js`) validasi ringan di client (UX only, bukan satu-satunya lapisan keamanan), lalu
   `fetch()` ke `wp-json/bukutamu/v1/submit` dengan `FormData` (nonce di header `X-WP-Nonce` atau field
   tersembunyi).
4. Server (`class-rest-api.php`) — semua langkah wajib sebelum menyentuh DB:
   - Verifikasi nonce.
   - Cek honeypot kosong; cek waktu submit ≥ 3 detik dari saat form dimuat (anti-bot ringan).
   - Rate limit per-IP via transient (mis. 1 submission / 60 detik).
   - Sanitasi semua field teks (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`).
   - Validasi file (`class-uploads.php`): whitelist mime (`jpg`,`png`,`webp`), maks ukuran per file,
     maks jumlah file, verifikasi via `wp_check_filetype_and_ext()` (bukan percaya ekstensi/nama file).
   - Decode tanda tangan base64 → validasi benar-benar PNG (`getimagesizefromstring`) → simpan via
     `wp_upload_bits()` + `wp_insert_attachment()`.
   - `wp_insert_post()` dengan `post_status => 'pending'`, lalu `update_field()` untuk semua field ACF.
   - Set `tanggal_kunjungan` = waktu server saat itu (bukan dari input client).
5. Response sukses → default: tampilkan pesan "Terima kasih, menunggu persetujuan admin" inline di
   form (bukan langsung tampil di testimoni/arsip, karena masih `pending`). Kalau shortcode dipasang
   dengan atribut `redirect="URL"` (lihat bagian "Halaman Standalone"), browser diarahkan
   (`window.location.href`) ke URL itu alih-alih menampilkan pesan inline — biasanya Page dengan
   `[bukutamu_terima_kasih]`.
6. Admin membuka wp-admin → daftar `buku_tamu` dengan status Pending → review → Publish atau Trash.

## Alur Tampilan Testimoni (Publik)

- Shortcode `[bukutamu_testimoni jumlah="6"]` → query `WP_Query( status: publish, post_type: buku_tamu )`.
- Setiap card menampilkan: nama, instansi, potongan kesan & pesan, **satu** foto dipilih **ACAK**
  dari `galeri_foto` (`array_rand()`, beda tiap kali halaman di-render — bukan selalu foto
  pertama, lihat Lessons Learned #20). Klik foto/nama pada card → link biasa ke halaman single
  native (`get_permalink()`), BUKAN modal/lightbox — galeri lengkap ada di halaman single itu
  sendiri (lihat Alur Tampilan Arsip). Card di sini murni CSS, tidak butuh JS sama sekali.
- Tanda tangan (`ttd`) TIDAK PERNAH ditampilkan — di card maupun di halaman single. Hanya
  terlihat admin di wp-admin. Nomor HP/email juga tidak pernah tampil publik (data pribadi).

## Alur Tampilan Arsip (Publik, Archive Native CPT — bukan shortcode)

> Versi awal fitur ini adalah shortcode `[bukutamu_arsip]` dengan paginasi lewat query string
> di atas Page biasa. User memilih diganti jadi **archive native WordPress** (URL: `/buku-tamu/`)
> setelah diberi tahu trade-off keamanannya — lihat Lessons Learned #19. Shortcode arsip SUDAH
> DIHAPUS, jangan dibuat lagi kecuali diminta eksplisit (hindari dua implementasi paralel untuk
> fungsi yang sama).

- URL arsip: `/buku-tamu/` (`Bukutamu_CPT::ARCHIVE_SLUG`), URL detail satu entri:
  `/buku-tamu/{slug-nama}/` — keduanya native WordPress (`has_archive`/`publicly_queryable`),
  BUKAN shortcode.
- Routing template: `includes/class-cpt-templates.php` (`Bukutamu_Cpt_Templates`), lewat filter
  `template_include` — memuat `templates/archive-buku_tamu.php` / `templates/single-buku_tamu.php`
  dari plugin, KECUALI tema sudah punya file dengan nama standar yang sama (tema selalu menang,
  plugin cuma fallback default — pola yang sama seperti `class-page-template.php`).
- Paginasi archive pakai mekanisme native WordPress (`pre_get_posts` mengatur
  `posts_per_page`/`orderby` di main query, `get_previous_posts_page_link()`/
  `get_next_posts_page_link()`/`$wp_query->max_num_pages` untuk navigasi) — BUKAN WP_Query
  terpisah atau query var custom seperti versi shortcode sebelumnya.
- Archive & single **memanggil `get_header()`/`get_footer()` tema** (beda dari
  `templates/page-standalone.php` yang sengaja tanpa header/footer) — arsip native ini
  dimaksudkan terintegrasi normal ke situs, bukan halaman kiosk mandiri.
- Kartu individual pakai ulang `templates/testimoni-card.php` (sama persis dengan shortcode
  testimoni: satu foto acak, link ke single, tanpa JS) di dalam wrapper `bukutamu-testimoni`
  yang sama, supaya otomatis dapat scoped margin-reset tanpa duplikasi CSS.
- **Halaman single** (`templates/single-buku_tamu.php`) menampilkan **galeri LENGKAP** — semua
  foto sebagai thumbnail (ACF size `thumbnail`), diklik untuk popup ukuran besar (ACF size
  `large`) lewat `build/js/bukutamu-lightbox.js` (hanya di-enqueue di halaman single, lihat
  `Bukutamu_Assets::enqueue_single()` — grid arsip/testimoni tidak butuh JS ini sama sekali).
- Sama seperti testimoni: hanya status `publish` yang tampil (WordPress otomatis 404-kan akses
  publik ke status lain), tidak pernah menampilkan HP/email MAUPUN tanda tangan — halaman
  single pun begitu (field-field itu memang tidak pernah di-`get_field()` di template publik
  mana pun, lihat Lessons Learned #20).

### Mitigasi keamanan tambahan (karena CPT sekarang publik)

Semua di `includes/class-cpt-templates.php` kecuali disebutkan lain, dan sudah DIVERIFIKASI
jalan lewat request HTTP sungguhan (bukan cuma baca kode):

- **REST API tetap tertutup** — `show_in_rest => false` di `class-cpt.php`, independen dari
  `public => true`. Ini yang paling penting: tanpa ini, `/wp-json/wp/v2/buku_tamu` akan
  mengekspos SEMUA field (termasuk yang tidak ditampilkan template PHP) sebagai JSON mentah.
- **Feed RSS di-redirect** — `/buku-tamu/feed/` di-`wp_safe_redirect()` 302 ke archive biasa
  (`block_feed()`), diuji langsung: `curl -I http://.../buku-tamu/feed/` → 302 ke `/buku-tamu/`.
- **Dikecualikan dari sitemap XML** — dua filter sekaligus: `wpseo_sitemap_exclude_post_type`
  (Yoast SEO, dipakai tema `jalawarta` di situs ini) DAN `wp_sitemaps_post_types` (sitemap core
  WordPress, untuk portabilitas ke situs klien lain tanpa Yoast). Diuji langsung lewat
  `apply_filters()` di runtime, keduanya mengecualikan `buku_tamu` dengan benar.
- **Rewrite rules auto-flush** — lihat `Bukutamu_CPT::maybe_flush_rewrite_rules()`, supaya
  perubahan struktur URL ini otomatis aktif di semua instalasi yang meng-update plugin, tanpa
  perlu admin deactivate+reactivate manual.

## Halaman Standalone (Tanpa Header/Footer Tema)

Untuk kebutuhan "laman khusus isi buku tamu" (mis. dipajang di resepsionis/tablet saat acara),
plugin menyediakan **page template** kustom bernama **"Buku Tamu — Tanpa Header/Footer"**,
didaftarkan oleh `includes/class-page-template.php` lewat filter `theme_page_templates` +
`template_include` — **tidak mengubah file tema apa pun**.

Cara pakai (admin):
1. Buat Page baru di wp-admin, judul Page-nya "Buku Tamu" (atau apa pun — ini yang tampil
   sebagai H1 dekoratif di halaman standalone).
2. Isi konten Page dengan salah satu shortcode:
   - `[bukutamu_form judul="0" redirect="URL"]` — form isi buku tamu. Atribut `judul="0"`
     WAJIB di sini, mematikan heading "Buku Tamu" bawaan di dalam card form (halaman
     standalone sudah punya heading-nya sendiri: H1 + nama situs; tanpa atribut ini judul
     tampil dobel). Atribut `redirect` OPSIONAL — isi dengan permalink Page "Terima Kasih"
     (lihat poin berikutnya) supaya setelah submit BERHASIL browser otomatis diarahkan ke
     sana; dikosongkan/tidak diisi = perilaku lama tetap jalan (pesan sukses tampil inline
     di form, tanpa redirect, backward compatible).
   - `[bukutamu_terima_kasih]` — halaman "Terima kasih" tujuan redirect di atas: ikon
     centang, pesan "Terima kasih, Jazakumullahu Khairon — Buku tamu berhasil diisi.", dan
     tombol ke archive native `/buku-tamu/`. Dipasang di Page TERPISAH dari Page form (mis.
     slug `terima-kasih-buku-tamu`), pakai template standalone yang sama seperti poin 3 di
     bawah. Lihat `templates/terima-kasih.php` / `Bukutamu_Shortcode::render_terima_kasih()`.
   - `[bukutamu_testimoni]` — showcase testimoni terbatas penuh layar (tidak perlu atribut
     `judul`, template ini tidak punya heading bawaan). Untuk arsip LENGKAP dengan paginasi,
     pakai archive native `/buku-tamu/` (lihat bagian "Alur Tampilan Arsip"), bukan halaman
     standalone ini — arsip native sudah terintegrasi header/footer tema, tidak cocok
     ditumpuk di dalam template tanpa header/footer.
3. Di panel **Page Attributes**, pilih template **"Buku Tamu — Tanpa Header/Footer"**.
4. Publish. Halaman tersebut akan me-render `templates/page-standalone.php` — dokumen HTML
   penuh miliknya sendiri (masih memanggil `wp_head()`/`wp_footer()` demi kompatibilitas
   plugin lain seperti analytics/SEO), TANPA memanggil `get_header()`/`get_footer()` tema.

Judul & excerpt Page (`the_title()`, `get_the_excerpt()`) ikut ditampilkan sebagai heading/
subheading dekoratif di atas form — admin bisa ganti teks sambutan cukup dari field
Judul/Excerpt Page, tanpa sentuh kode.

### Branding otomatis dari identitas situs

Badge di atas judul (`bukutamu_site_branding()` di `includes/helpers.php`) dan baris nama
instansi di bawah judul TIDAK di-hardcode — keduanya diambil otomatis dari pengaturan situs,
supaya plugin ini portable dipakai di banyak situs klien Webane tanpa perlu edit kode per situs:

1. **Badge logo**, fallback bertingkat:
   1. `get_theme_mod( 'custom_logo' )` (Customizer > Site Identity > Logo), bila tema mendukung
      `custom-logo` dan admin sudah upload logo.
   2. `get_site_icon_url()` (Settings > General > Site Icon), bila logo tidak diset.
   3. Ikon pena bawaan plugin, kalau situs belum mengatur keduanya — supaya badge tidak pernah
      kosong/rusak di instalasi baru yang belum dikonfigurasi.
2. **Nama instansi** di bawah judul Page: `get_bloginfo( 'name' )`, alias Settings > General >
   Site Title. Sengaja bukan field ACF baru — memakai pengaturan WordPress standar yang memang
   fungsinya untuk itu (hindari duplikasi tempat mengisi "nama situs").

**Penting:** karena deteksi aset (`Bukutamu_Assets::maybe_enqueue()`) berbasis `has_shortcode()`
pada `post_content`, mekanisme ini tetap bekerja normal di halaman standalone — CSS/JS plugin
tetap ter-enqueue karena shortcode-nya tetap ada di `post_content` Page, terlepas dari template
mana yang me-render-nya.
## Keamanan (checklist wajib per fitur baru)

- [ ] Semua input publik disanitasi di server, bukan hanya divalidasi di client.
- [ ] Semua output di-escape sesuai konteks (`esc_html`, `esc_attr`, `esc_url`).
- [ ] Endpoint REST publik pakai `permission_callback` eksplisit (jangan `__return_true` tanpa nonce check).
- [ ] Upload file: validasi mime asli, batasi ukuran & jumlah, gunakan direktori upload standar WP
      (jangan bikin folder custom yang berpotensi executable).
- [ ] Data pribadi (HP, email) tidak pernah dikirim ke front-end publik lewat REST response atau HTML.
- [ ] Rate limiting & honeypot aktif di form publik.
- [ ] Tidak ada query SQL manual tanpa `$wpdb->prepare()` (usahakan selalu pakai `WP_Query`/ACF API).
- [ ] Nonce di-generate per page-load dan diverifikasi di server untuk setiap endpoint yang mengubah data.

## Konvensi Kode

- Prefix fungsi/hook global: `bukutamu_`. Class pakai namespace atau prefix `Bukutamu_`.
- Ikuti WordPress Coding Standards (PHPCS `WordPress` ruleset) bila tersedia di environment.
- String user-facing dibungkus `__()`/`_e()` dengan text domain `bukutamu` (siap terjemahan meski awalnya
  cuma Bahasa Indonesia).
- Tailwind di-build lewat CLI (`npx tailwindcss ...`) saat development; hasil build **di-commit** ke
  `build/css/` supaya plugin tidak butuh Node.js di server produksi.
- Jangan tambah dependency JS berat (library carousel/lightbox besar dsb.) — buat versi minimal sendiri
  kalau kebutuhannya sederhana, sesuai prinsip "ringan".

## Lessons Learned

> Bagian ini WAJIB diperbarui setiap kali menemukan bug/kesalahan desain selama development, supaya sesi
> berikutnya (manusia atau Claude) tidak mengulanginya. Format: tanggal, apa yang terjadi, kenapa terjadi,
> aturan yang diambil.

### 2026-08-16 — Keputusan awal (antisipatif, sebelum ada kode)

1. **Jangan pakai `capability_type` custom untuk CPT tanpa alasan kuat.** Custom capability type
   (mis. `buku_tamu` sebagai capability_type) mengharuskan kita manual `add_cap()` ke role Administrator/
   Editor saat aktivasi — kalau lupa, admin bisa kehilangan akses ke menu CPT tanpa error yang jelas.
   Keputusan: pakai `capability_type => 'post'` default supaya Administrator/Editor otomatis punya akses.
2. **ACF Local JSON adalah sumber kebenaran.** Setelah `acf-json/group_bukutamu_entry.json` ada, JANGAN
   edit field group lewat UI wp-admin di lingkungan development tanpa langsung re-export JSON-nya — ACF
   akan menampilkan status "sync available" yang membingungkan dan bisa membuat DB copy dan file JSON
   tidak sinkron. Semua perubahan field harus lewat file JSON langsung (atau edit di UI lalu segera
   "Sync"/export ulang ke file yang sama).
3. **ACF PRO adalah hard dependency** karena field Gallery. Plugin harus mendeteksi saat aktivasi apakah
   ACF PRO aktif (cek `class_exists('ACF') && acf_get_setting('pro')` atau fungsi Gallery field ada);
   kalau tidak ada, tampilkan admin notice yang jelas dan JANGAN biarkan fitur lain fatal error.
4. **Jangan pakai `acf_form()` untuk form publik.** Field custom seperti tanda tangan tidak punya tipe ACF
   native, dan `acf_form()` memuat CSS/JS bawaan ACF yang bertentangan dengan prinsip "tidak terkait
   dengan CSS bawaan WP/plugin lain". Form publik selalu custom HTML + REST API, ACF hanya dipakai sebagai
   lapisan penyimpanan (`update_field()`).
5. **`post_status` default WordPress untuk insert via REST/PHP adalah `draft`, bukan `pending`, kalau tidak
   di-set eksplisit.** Karena kita butuh moderasi, `post_status` HARUS di-set eksplisit `'pending'` di
   `wp_insert_post()` — jangan mengandalkan default.
6. **`tanggal_kunjungan` tidak boleh dipercaya dari input client.** Selalu isi dari `current_time()` di
   server saat proses submission, meskipun field-nya secara teknis "Date Time Picker" yang bisa diedit
   manual oleh admin di wp-admin setelahnya.

### 2026-08-16 — Implementasi Fase 3 (form publik) & Fase 4 (tampilan testimoni)

7. **`corePlugins.preflight` Tailwind WAJIB `false`.** Preflight (CSS reset global Tailwind)
   tidak ikut ter-prefix oleh opsi `prefix: 'bt-'` — kalau diaktifkan, ia akan mereset elemen
   `h1`/`p`/`button`/dll di SELURUH halaman tema, bukan cuma di dalam markup plugin. Ini
   secara langsung melanggar prinsip #1 (tidak terkait dengan CSS tema). Konfigurasi final ada
   di `src/tailwind.config.js` dengan `corePlugins: { preflight: false }`.
8. **Path `content` di `tailwind.config.js` v3 relatif terhadap CWD saat build dijalankan,
   BUKAN relatif terhadap lokasi file config.** Karena build dijalankan dari root plugin
   (`npm run build` di `package.json` menunjuk `-c src/tailwind.config.js` tapi CWD tetap root),
   path harus ditulis `templates/**/*.php` (bukan `../templates/**/*.php`) walau config-nya
   berada di `src/`.
9. **Rate limit per-IP dicek SETELAH validasi field, bukan sebelum.** Awalnya rate limit
   dicek paling awal (setelah honeypot/timing), tapi ini berarti pengunjung yang salah ketik
   (mis. format email salah) ikut menghabiskan jatah "1 submission/60 detik" hanya untuk
   melihat pesan error validasi biasa — merepotkan pengguna asli tanpa menambah keamanan
   berarti (submission dengan data tidak valid toh tidak pernah sampai ke `wp_insert_post()`).
   Urutan final: nonce → honeypot/timing (bail diam-diam) → validasi field → rate limit → insert.
10. **Struktur `$_FILES` untuk input multi-file (`name="x[]"`) berbentuk kolom-per-kolom**
    (`$_FILES['x']['name'][0..n]`, bukan daftar per-file), harus di-reshape manual sebelum
    dipakai `wp_handle_upload()` satu per satu — lihat `Bukutamu_Uploads::normalize_files_array()`.
11. **String terjemahan yang mengandung markup HTML (mis. link "oleh Webane Indonesia") WAJIB
    dibungkus `wp_kses()` dengan whitelist tag eksplisit**, bukan sekadar `esc_html__()` +
    `printf()` biasa — supaya kalau suatu saat file bahasa (.po/.mo) diterjemahkan oleh pihak
    ketiga, HTML di dalam string terjemahan tidak bisa disusupi markup berbahaya di luar
    whitelist.
12. **Honeypot & timing-check yang terpicu SENGAJA membalas seolah sukses** (bukan error 4xx),
    supaya bot tidak mendapat sinyal bahwa submission-nya terdeteksi & ditolak — tapi data
    tetap TIDAK disimpan. Ini konsisten dengan prinsip "jangan beri tahu penyerang apa yang
    sedang diperiksa".
13. **Deteksi galeri "gambar blank"/tanda tangan kosong hanya dilakukan di client (JS),
    bukan di server.** Server hanya memvalidasi bahwa data adalah PNG valid berukuran wajar,
    BUKAN menganalisis apakah kanvasnya benar-benar berisi coretan. Ini keputusan sadar demi
    "ringan" (analisis piksel itu mahal & rumit untuk nilai keamanan yang kecil, karena
    honeypot+nonce+rate-limit sudah menutup vektor abuse yang realistis) — dicatat di sini
    supaya tidak dianggap sebagai bug yang belum ditemukan, melainkan trade-off yang disengaja.
14. **Aset (CSS/JS) di-enqueue kondisional lewat `has_shortcode()` pada `post_content`
   postingan utama saja.** Ini TIDAK mendeteksi shortcode yang dipasang lewat widget,
   template PHP custom, atau page builder yang menyimpan konten di luar `post_content`.
   Untuk kasus tersebut sudah disediakan filter `bukutamu_force_enqueue_assets` (return true)
   sebagai jalan keluar eksplisit — jangan coba "perbaiki" deteksi ini dengan enqueue late
   di dalam callback shortcode, karena style yang di-enqueue setelah `wp_head()` sudah
   ter-print oleh tema TIDAK otomatis muncul di `<head>` (lihat WordPress Core behaviour).

### 2026-08-16 — Perbaikan setelah user bertanya soal kotak "Add title"

15. **Awalnya `supports => ['title']` dipakai + sync satu arah (ACF 'nama' → post_title).**
    Ini punya celah: kalau admin mengedit kotak "Add title" bawaan WP secara langsung (bukan
    lewat field ACF "Nama") dan menyimpan, field `nama` di ACF TIDAK ikut ter-update — dua
    tempat input untuk "nama yang sama" bisa jadi tidak sinkron, admin bisa bingung mana yang
    benar. User menyadari risiko ini sebelum sempat jadi bug nyata dan menanyakannya.
    **Perbaikan:** hilangkan `'title'` dari `supports` sama sekali (`supports => []`), sehingga
    kotak "Add title" tidak pernah muncul di layar edit. Field `nama` di ACF jadi **satu-
    satunya** tempat admin mengisi nama pengunjung; `post_title` selalu diturunkan otomatis
    dari `nama` (lewat `wp_insert_post()` saat REST, atau hook `acf/save_post` saat wp-admin) —
    tidak pernah ada dua sumber kebenaran yang bisa saling menyimpang.
    **Aturan umum untuk CPT sejenis di masa depan:** kalau sebuah field custom (ACF/meta) pada
    dasarnya SAMA dengan `post_title` secara makna (bukan cuma "mirip"), jangan tampilkan kotak
    Title bawaan sama sekali — derive `post_title` secara programatik. Jangan coba sinkron dua
    arah (title↔field custom) karena selalu ada window race/inkonsistensi antara "field mana
    yang disimpan lebih dulu" saat keduanya sama-sama editable di layar yang sama.

16. **`'supports' => []` BUKAN `'supports' => false` — dan bedanya besar.** Setelah fix #15
    di-deploy, kotak "Add title" memang hilang tapi editor konten (the_content) tetap muncul
    di `post-new.php?post_type=buku_tamu` — ketahuan langsung oleh user di browser. Root cause
    (diverifikasi lewat `get_all_post_type_supports('buku_tamu')` di runtime WP sungguhan,
    bukan cuma baca dokumentasi): di `WP_Post_Type::add_supports()`, WordPress core mengecek
    `if ( ! empty( $this->supports ) ) { ... } elseif ( false !== $this->supports ) { add_post_type_support( $this->name, array( 'title', 'editor' ) ); }`.
    Array kosong `[]` lolos dari cabang pertama (karena `empty([])` bernilai true) TAPI juga
    lolos kondisi `elseif` (karena `[] !== false`) — jadi WordPress tetap menambahkan default
    `title` + `editor` seolah-olah `supports` tidak pernah diisi sama sekali. Hanya literal
    `false` yang benar-benar mematikan semua support default.
    **Aturan:** kalau tujuannya CPT tanpa fitur apa pun (murni data-entry lewat ACF), selalu
    pakai `'supports' => false`, JANGAN `'supports' => []`. Kalau butuh sebagian fitur, isi
    array dengan fitur yang diinginkan secara eksplisit (mis. `['thumbnail']`) — jangan pernah
    kirim array kosong dengan asumsi itu berarti "tidak ada fitur".
    **Cara verifikasi paling andal untuk masalah `register_post_type()` semacam ini:** jangan
    cuma baca kode & menyimpulkan — jalankan `get_post_type_object()` /
    `get_all_post_type_supports()` di runtime WP yang sesungguhnya (mis. skrip kecil yang
    `require wp-load.php`), karena perilaku core seperti ini seringkali tidak intuitif hanya
    dari membaca argumen yang dikirim.

### 2026-08-16 — Halaman standalone: judul dobel

17. **Saat menambah halaman "wrapper" baru (standalone/page-template) yang punya heading
    sendiri, cek dulu apakah komponen yang di-embed di dalamnya (di sini: `templates/form.php`)
    SUDAH punya heading sendiri juga.** User melaporkan "margin bawah judul kejauhan" di
    halaman standalone — ternyata bukan murni soal angka margin, tapi karena `form.php`
    menampilkan heading "Buku Tamu" sendiri (H2) tepat di bawah H1 halaman standalone yang
    juga bertuliskan "Buku Tamu" — dua judul sama persis bertumpuk dengan spacing masing-
    masing, terasa seperti jarak yang janggal/berlebihan padahal sebenarnya duplikasi.
    **Perbaikan:** heading di `form.php` dibuat opsional lewat atribut shortcode
    `[bukutamu_form judul="0"]` (lihat `Bukutamu_Shortcode::render_form()`), dipakai di
    `page-standalone.php`. **Aturan umum:** sebelum mengubah angka spacing/margin untuk
    memperbaiki "kelihatan jauh", cek dulu apakah sebenarnya ada elemen duplikat/berulang di
    antara dua komponen yang ditumpuk — mengubah angka margin tidak akan pernah terasa "pas"
    kalau akar masalahnya duplikasi konten, bukan besaran jaraknya.

18. **Preflight Tailwind OFF berarti `<h1>/<h2>/<p>` kita sendiri masih bawa margin default
    browser (UA stylesheet, mis. `h1 { margin: 0.67em 0 }`).** Setelah fix #17, user masih
    lapor jarak di bawah H1 kejauhan — root cause SEBENARNYA: karena `corePlugins.preflight`
    di-off-kan (keputusan sadar di #7, supaya tidak reset CSS tema), Tailwind juga tidak
    mereset margin default elemen `h1/h2/p/dll` DI DALAM markup kita sendiri. Akibatnya margin
    bawaan browser pada `<h1>` menumpuk dengan `bt-mb-*` yang sudah diatur di elemen
    pembungkusnya — sudah dikecilkan berkali-kali (`bt-mb-8` → `bt-mb-4` → `bt-mb-2`) tapi
    tetap terasa jauh karena sumber jarak sebenarnya ADA DUA (margin bawaan h1 + utility kita).
    **Perbaikan (bukan nge-reset satu-satu tiap tag):** tambah `@layer base` di `src/input.css`
    berisi reset `margin: 0` yang di-*scope* lewat selector turunan `.bukutamu-form :where(...)`,
    `.bukutamu-testimoni :where(...)`, `.bukutamu-standalone-page :where(...)` — HANYA elemen
    di dalam markup plugin kita yang kena reset, CSS tema di luar itu tidak tersentuh sama
    sekali (preflight global tetap mati). Pakai `:where()` supaya spesifisitas reset ini tetap
    rendah dan gampang di-override utility Tailwind biasa kalau suatu saat perlu.
    **Aturan umum:** kalau preflight dimatikan demi tidak mengganggu tema (pola yang benar
    untuk plugin), jangan lupa itu juga berarti heading/paragraf **plugin sendiri** kehilangan
    baseline reset — harus dikompensasi lewat scoped reset seperti ini, bukan diasumsikan
    "aman" begitu saja. Cara ngecek cepat: kalau utility spacing (`mb-*`/`mt-*`/`space-y-*`)
    sudah dikecilkan tapi jarak visual tidak berubah proporsional, curigai margin bawaan
    elemen HTML-nya sendiri, bukan cuma utility yang salah angka.

### 2026-08-17 — CPT diubah jadi publik dengan archive native (pembalikan keputusan #4)

19. **User memilih archive native WordPress (`public => true`, `has_archive`) untuk fitur
    arsip, menggantikan shortcode `[bukutamu_arsip]` yang awalnya dibangun** ("kok pake
    shortcut... bikin arsip custom post buku tamu lebih simple"). Ini secara langsung
    membalikkan keputusan desain awal plugin (prinsip #4 & Lessons Learned #1: CPT sengaja
    `public => false` untuk mengurangi attack surface).
    **Sebelum mengeksekusi, risiko konkret disampaikan dulu ke user** (bukan langsung dituruti
    atau langsung ditolak) — CPT publik otomatis membuka: (a) single URL per entri, (b) feed
    RSS, (c) auto-masuk sitemap XML. User tetap memilih native archive setelah tahu trade-off-
    nya — itu keputusan valid milik user, tugas developer di sini adalah **menutup celah yang
    sudah diketahui**, bukan diam-diam membiarkannya terbuka:
    - `show_in_rest` TETAP `false` meski `public => true` — ini pengaturan independen, paling
      penting karena REST API yang aktif akan mengekspos field mentah lewat JSON.
    - Feed di-redirect (`template_redirect` + `is_feed()`).
    - Dikecualikan dari sitemap XML lewat DUA filter sekaligus: `wpseo_sitemap_exclude_post_type`
      (Yoast, dipakai tema situs ini — ditemukan dengan cara grep kode tema, bukan asumsi) DAN
      `wp_sitemaps_post_types` (sitemap core WP, untuk portabilitas ke situs klien lain).
    - Halaman single TETAP tidak menampilkan HP/email — field itu sengaja tidak pernah
      di-`get_field()` di `templates/single-buku_tamu.php`.
    **Semua mitigasi di atas diverifikasi lewat request HTTP sungguhan** (`curl` ke situs
    development yang jalan), bukan cuma dibaca kodenya: dicek header response feed redirect
    (302), dicek `apply_filters()` sitemap exclusion di runtime, dan — paling penting — dicek
    isi HTML halaman single tidak mengandung nilai `nomor_hp`/`email` ASLI milik entri
    tersebut (sempat false-positive karena grep pola umum "@gmail"/nomor HP menangkap info
    kontak SITUS sendiri dari schema.org Yoast, bukan data tamu — baru dikonfirmasi aman
    setelah membandingkan dengan nilai field yang sesungguhnya, bukan berhenti di grep pertama).
    **Aturan umum:** kalau user meminta arsitektur yang membalikkan keputusan keamanan
    sebelumnya, jangan menolak begitu saja DAN jangan menurut begitu saja — jelaskan risiko
    konkret dengan istilah yang bisa diverifikasi (bukan "kurang aman" generik), biarkan user
    memutuskan, lalu implementasikan dengan mitigasi eksplisit untuk setiap risiko yang sudah
    disebut. Dan saat menguji hasil "tidak ada data sensitif yang bocor", jangan berhenti di
    grep pola generik yang bisa false-positive — verifikasi terhadap nilai data yang
    sesungguhnya.
    **Shortcode `[bukutamu_arsip]` dan `templates/arsip-grid.php` DIHAPUS** (bukan dibiarkan
    nganggur di samping fitur baru) — dua implementasi paralel untuk fungsi yang sama cuma
    menambah beban maintenance tanpa manfaat, dan bisa membingungkan developer berikutnya
    ("yang mana yang dipakai?"). Kalau butuh arsip di halaman tanpa header/footer tema lagi di
    masa depan, itu permintaan baru yang perlu dipertimbangkan ulang trade-off-nya, bukan
    tinggal un-delete kode lama.

### 2026-08-17 — Tanda tangan disembunyikan total, foto card jadi acak + galeri pindah ke single

20. **Tanda tangan (`ttd`) TIDAK PERNAH boleh tampil di front-end mana pun** — sebelumnya
    sempat ditampilkan kecil di pojok kartu testimoni/arsip dan di halaman single. User minta
    ini dihapus total dari semua tampilan publik (hanya boleh terlihat admin di wp-admin).
    **Aturan ke depan:** field `ttd` tidak boleh muncul di `get_field()` mana pun dalam
    template `templates/*.php` yang di-render untuk publik (form.php pengecualian karena itu
    JS canvas milik pengunjung sendiri saat mengisi, bukan menampilkan data orang lain).
21. **Foto di kartu (arsip & testimoni) sekarang dipilih ACAK per-render** (`array_rand()`),
    bukan selalu elemen pertama galeri — kartu murni jadi teaser, bukan lagi tempat melihat
    semua foto. Konsekuensinya: card TIDAK LAGI trigger modal/lightbox berisi banyak foto;
    card sekarang `<a>` biasa ke halaman single (permalink native, karena CPT sudah publik
    sejak Lessons Learned #19). Galeri LENGKAP (semua foto, thumbnail → popup besar) hanya ada
    di halaman single (`templates/single-buku_tamu.php`), pakai JS lightbox
    (`build/js/bukutamu-lightbox.js`, sebelumnya bernama `bukutamu-testimoni.js` — di-rename
    karena fungsinya berubah total: dari "modal berisi array URL galeri" jadi "popup satu
    gambar per klik thumbnail", implementasinya jadi lebih sederhana juga).
    **Konsekuensi ke `class-assets.php`:** grid arsip/testimoni sekarang HANYA butuh CSS,
    TIDAK butuh JS sama sekali (tidak ada interaksi apa pun di card selain link biasa) —
    lightbox JS cuma di-enqueue di halaman single (`enqueue_single()`), bukan di grid
    (`enqueue_grid()`) seperti sebelumnya. Ini pengurangan beban JS yang nyata, bukan cuma
    reorganisasi kode.
    **Setiap kali file JS di-rename atau isinya berubah, versi plugin (`BUKUTAMU_VERSION`)
    HARUS dinaikkan** — semua asset di-enqueue dengan `?ver=` dari konstanta itu untuk cache-
    busting; kalau tidak dinaikkan, browser/CDN yang sudah cache `bukutamu.css`/JS versi lama
    bisa terus menyajikan versi basi meski isinya sudah berubah di server.

### 2026-08-17 — Sistem update plugin (GitHub Releases)

22. **`is_admin()` BUKAN cara yang tepat untuk "cuma jalan pas dibutuhkan" untuk hook terkait
    update plugin.** Godaan pertama saat menulis `class-updater.php`: guard semua hook-nya
    dengan `if ( is_admin() )` supaya "tidak membebani front-end". Ini SALAH — pengecekan
    update otomatis WordPress (dua kali sehari) berjalan lewat WP-Cron (event
    `wp_update_plugins`), dan `is_admin()` bernilai **false** selama eksekusi cron (cron bukan
    request wp-admin). Kalau di-guard, background check tidak akan pernah jalan; update cuma
    kedeteksi kalau admin kebetulan buka halaman wp-admin setelah cache transient kadaluarsa.
    **Solusi yang benar:** load hook-nya unconditional. Tidak masalah untuk beban front-end —
    hook seperti `pre_set_site_transient_update_plugins`/`plugins_api`/`upgrader_source_selection`
    memang secara desain WordPress Core hanya PERNAH ter-fire dalam konteks admin/cron terkait
    update, jadi otomatis inert (tidak pernah dipanggil) di request front-end publik meski
    hook-nya didaftarkan — guard manual `is_admin()` di sini murni redundan sekaligus merusak
    fungsi cron. **Aturan umum:** sebelum menambah guard `is_admin()`/`is_front_end()` demi
    "optimisasi", pastikan dulu hook yang bersangkutan benar-benar TIDAK punya jalur eksekusi
    valid di luar konteks yang ingin di-guard (termasuk WP-Cron, REST API, WP-CLI) — kalau
    salah tebak, guard-nya diam-diam mematikan fitur alih-alih mengoptimalkan.
23. **GitHub `zipball_url` selalu tersedia otomatis untuk tag apa pun tanpa perlu upload asset
    ZIP manual** — tapi folder hasil ekstraknya bernama `{repo}-{hash}`, bukan nama slug
    plugin. WAJIB di-rename lewat `upgrader_source_selection` sebelum WordPress memindahkannya
    ke `wp-content/plugins/`, kalau tidak WordPress akan menganggapnya plugin BARU yang
    terpisah (folder tidak cocok dengan basename plugin yang sudah aktif) — bukan meng-update
    yang sudah ada, malah bisa menghasilkan DUA salinan plugin aktif berdampingan.
24. **GitHub API dibatasi 60 request/jam tanpa token** — WAJIB di-cache (transient), termasuk
    meng-cache KEGAGALAN request (durasi lebih pendek) supaya request yang gagal (GitHub
    down/rate-limited/belum ada release sama sekali) tidak diulang di setiap page load
    wp-admin. Tanpa ini, situs dengan trafik wp-admin tinggi bisa dengan mudah menghabiskan
    jatah rate limit hanya dari mengecek update plugin ini berulang-ulang.
25. **Update HANYA terdeteksi dari GitHub *Release* (tag resmi), bukan commit/push biasa ke
    `main`.** Ini keputusan desain sengaja (bukan keterbatasan) — supaya update yang ditawarkan
    ke pengguna plugin selalu versi yang benar-benar dimaksudkan rilis stabil, bukan commit
    percobaan/WIP yang kebetulan ter-push. Konsekuensinya: checklist rilis WAJIB tiga langkah
    (naikkan `BUKUTAMU_VERSION` → push → buat GitHub Release dengan tag `vX.Y.Z`) — push saja
    TIDAK CUKUP, plugin tidak akan pernah tahu ada versi baru kalau langkah Release dilewat.
26. **Rilis pertama (`v0.2.1`) awalnya dibuat tanpa asset ZIP** (`gh release create` polos) —
    secara TEKNIS sistem cek-update tetap berfungsi (berkat mitigasi `upgrader_source_selection`
    di #23), jadi tidak ketahuan ada masalah dari sudut pandang "apakah kode auto-update-nya
    jalan". Baru ketahuan kurang tepat setelah user bertanya konkret **"saya bisa download dan
    install?"** — pertanyaan itu memaksa mengecek dari sudut pandang PENGGUNA AKHIR (download
    manual dari halaman Release, bukan lewat mekanisme auto-update), dan di situ baru terlihat
    dua masalah nyata: file dev ikut ter-bundle, dan nama folder hasil ekstrak tidak rapi.
    **Perbaikan:** `bin/release.sh` — build script yang mem-package ulang jadi `bukutamu.zip`
    bersih (isi persis sama seperti struktur folder plugin yang benar) dan upload sebagai
    asset Release; `Bukutamu_Updater::get_package_url()` diubah supaya prioritas pakai asset
    itu, `zipball_url` jadi fallback saja.
    **Aturan umum:** "sistem update berfungsi" dan "hasil rilis siap didistribusikan ke
    pengguna akhir" adalah DUA klaim berbeda yang butuh verifikasi terpisah. Setelah
    membangun/menguji mekanisme internal (filter, transient, version_compare), tetap lakukan
    satu langkah lagi: coba dari sudut pandang paling naif — "kalau saya orang lain yang baru
    tahu plugin ini, apa yang saya dapat kalau saya klik download sekarang?" — sebelum
    menganggap fitur benar-benar selesai.

### 2026-08-17 — Foto tidak muncul di preview: batas ukuran file terlalu kecil & pesan error salah tempat

27. **User melaporkan "foto tidak masuk ke upload" setelah memilih foto dari galeri HP/Mac** —
    diagnosis dimulai dengan reproduksi END-TO-END sungguhan lewat `curl` (bukan cuma baca
    kode): load halaman form → ambil nonce/timestamp asli dari HTML → tunggu >3 detik → POST
    multipart ke `/wp-json/bukutamu/v1/submit` dengan foto asli. Hasilnya endpoint & pipeline
    upload TERBUKTI berfungsi normal (file tersimpan ke `wp-content/uploads/`, jadi attachment
    Media Library, field ACF `galeri_foto` terisi) — jadi bukan bug di REST/upload handler.
    **Root cause sebenarnya ada di JS, sebelum request terkirim sama sekali:**
    `Bukutamu_Uploads::MAX_FILE_SIZE` di-set 2MB (selaras `max_size` ACF gallery field),
    padahal foto kamera HP/Mac modern lazimnya 3–8MB. `bukutamu-form.js` MEMANG menolak file
    yang kelebihan ukuran (`showAlert` dipanggil), tapi pesannya tampil di kotak alert PALING
    ATAS form (dekat judul) — jauh dari drop-zone/area preview foto yang sedang dilihat user.
    Akibatnya file ditolak secara diam-diam dari sudut pandang user: tidak ada di
    `selectedFiles`, tidak pernah muncul di preview, dan pesan error-nya tidak disadari ada.
    **Perbaikan:**
    - `MAX_FILE_SIZE` dinaikkan 2MB → **5MB** (PHP `class-uploads.php` DAN `max_size` di
      `acf-json/group_bukutamu_entry.json` harus tetap disetel BERSAMAAN — dua tempat itu
      sengaja diselaraskan, lihat catatan di `class-uploads.php`).
    - Error validasi file (ukuran/jumlah berlebih) sekarang tampil di elemen terpisah
      `.bukutamu-form__file-error`, ditaruh TEPAT di bawah drop-zone — bukan lagi memakai
      `.bukutamu-form__alert` yang letaknya jauh di atas. `showAlert()` tetap dipakai khusus
      untuk hasil submit (sukses/gagal API), `showFileError()`/`hideFileError()` baru khusus
      untuk validasi pemilihan file di client.
    **Aturan umum:** kalau ada batas validasi client-side (ukuran file, jumlah, dll), pesan
    error-nya WAJIB tampil di dekat kontrol yang relevan (dekat drop-zone), bukan di kotak
    alert generik yang jauh dari fokus mata user saat itu — kalau tidak, dari sudut pandang
    user gejalanya akan terlihat seperti "fitur tidak berfungsi" padahal sebenarnya validasi
    bekerja dengan benar, cuma pesannya tidak pernah terlihat. Untuk debugging serupa di masa
    depan (submission publik yang "gagal tanpa alasan jelas"), reproduksi END-TO-END via
    `curl`/HTTP request sungguhan (replikasi persis apa yang dilakukan JS: load halaman → ambil
    nonce asli → tunggu jeda anti-bot → POST) jauh lebih meyakinkan daripada hanya membaca kode
    — itu langsung memisahkan "bug di server" dari "bug di client sebelum request terkirim".

### 2026-08-17 — 403 "Sesi form kedaluwarsa" yang konsisten, hanya saat admin ikut login di browser yang sama

28. **User terus melaporkan 403 `bukutamu_invalid_nonce` walau sudah hard-refresh berkali-kali** —
    reproduksi via `curl` murni anonim (tanpa cookie) SELALU berhasil, jadi kode
    `Bukutamu_Security`/`class-rest-api.php` sendiri tidak salah. Yang berbeda dari kondisi
    user: mereka menguji form publik di browser yang SAMA dengan sesi login wp-admin mereka
    (wajar, karena mereka developer/pemilik situs yang sedang menguji plugin sendiri).
    **Root cause: WordPress Core, BUKAN kode plugin.** `rest_cookie_check_errors()` di
    `wp-includes/rest-api.php` berjalan untuk **SETIAP** request REST API, independen dari
    `permission_callback` custom apa pun (termasuk `__return_true` di `class-rest-api.php`).
    Kalau browser membawa cookie login valid TAPI request tidak menyertakan `_wpnonce`/header
    `X-WP-Nonce`, fungsi ini memanggil `wp_set_current_user( 0 )` — **memaksa request dianggap
    logged-out**, dieksekusi SEBELUM callback plugin (`handle_submit`) sempat jalan. Karena
    nonce `bukutamu_submit` yang tertanam di HTML dibuat saat halaman di-render (uid = admin
    yang sedang login), tapi diverifikasi setelah uid dipaksa jadi 0 oleh WP Core, kedua nilai
    itu TIDAK PERNAH cocok — `wp_verify_nonce()` gagal 100% dari waktu, bukan sesekali/flaky.
    Diverifikasi dengan simulasi in-process (`wp_set_current_user()` bolak-balik + `wp_create_nonce`/
    `wp_verify_nonce` langsung, TANPA membuat cookie/session login sungguhan): kondisi "tanpa
    header X-WP-Nonce" terbukti selalu GAGAL, kondisi "uid tidak direset" (setara dengan header
    X-WP-Nonce valid dikirim) terbukti selalu LOLOS — pola ini reproducible, bukan tebakan.
    **Perbaikan:** kirim nonce standar WP Core (action `'wp_rest'`, BEDA dari nonce anti-bot
    custom `'bukutamu_submit'`) sebagai header `X-WP-Nonce` di setiap `fetch()` ke REST API —
    ini praktik standar WordPress untuk skenario persis ini (lihat kenapa `wp_localize_script`
    di banyak plugin resmi selalu menyertakan `nonce: wp_create_nonce('wp_rest')`). Ditambahkan
    `restNonce` ke `bukutamuFormConfig` (`class-assets.php`) dan header `X-WP-Nonce` di
    `fetch()` (`bukutamu-form.js`). Untuk pengunjung publik yang benar-benar anonim (tanpa
    cookie login), header ini tidak berpengaruh apa pun — `rest_cookie_check_errors()` hanya
    aktif kalau ada cookie login yang terdeteksi valid.
    **Aturan umum untuk endpoint REST publik (`permission_callback => '__return_true'`) di masa
    depan:** SELALU sertakan header `X-WP-Nonce` (action `wp_rest`) di setiap request `fetch()`
    dari front-end, walau endpoint-nya memang untuk pengunjung anonim dan punya lapisan
    keamanan sendiri (nonce custom/rate-limit/dll) — tanpa header itu, endpoint akan tampak
    "rusak secara konsisten" khusus untuk siapa pun yang KEBETULAN juga login di browser yang
    sama (developer/admin situs sendiri termasuk paling rentan mengalami ini saat testing),
    padahal endpoint-nya sendiri tidak salah sama sekali. **Cara diagnosis paling efektif untuk
    kasus "gagal konsisten, tapi kode terlihat benar":** kalau reproduksi anonim (curl tanpa
    cookie) berhasil tapi user tetap gagal, curigai perbedaan STATE BROWSER (cookie/login),
    bukan buru-buru menyalahkan ulang kode yang sudah terbukti benar secara anonim — baca
    source `wp-includes/rest-api.php` langsung untuk memastikan, bukan menebak dari nama fungsi.

### 2026-08-17 — Redirect ke halaman "Terima Kasih" setelah submit berhasil

29. **Redirect tujuan disimpan per-instance shortcode (atribut `redirect`), BUKAN di
    `bukutamuFormConfig` global.** `bukutamuFormConfig` (dari `wp_localize_script`) adalah
    SATU objek JS yang dipakai bersama oleh semua `<form class="bukutamu-form">` di halaman
    yang sama — kalau URL redirect ditaruh di situ, tidak bisa berbeda antar instance form (mis.
    kalau suatu saat ada dua form beda tujuan di halaman yang sama) dan lebih ribet untuk tetap
    backward-compatible (harus selalu ada nilai, tidak bisa "kosong berarti tidak redirect" per
    form). Solusi yang dipakai: URL redirect dirender sebagai atribut `data-redirect-url`
    langsung di elemen `<form>` itu sendiri (dari atribut shortcode `[bukutamu_form redirect="URL"]`,
    lihat `Bukutamu_Shortcode::render_form()`), dibaca `bukutamu-form.js` dari
    `form.getAttribute('data-redirect-url')` saat submit berhasil. Atribut kosong/tidak ada =
    perilaku lama (pesan sukses inline, tanpa redirect) — backward compatible untuk pemasangan
    `[bukutamu_form]` yang sudah ada sebelum fitur ini, tanpa perlu migrasi apa pun.
    **Halaman "Terima Kasih" adalah Page WordPress biasa** (bukan route/URL yang di-hardcode di
    kode plugin) berisi shortcode `[bukutamu_terima_kasih]`, dipasang admin dengan pola PERSIS
    sama seperti Page form (page template "Buku Tamu — Tanpa Header/Footer", lihat bagian
    "Halaman Standalone") — konsisten dengan filosofi plugin ini: konten & routing halaman
    publik selalu lewat mekanisme Page + shortcode WordPress standar, bukan endpoint custom
    baru, supaya admin bisa mengedit/memindah/menghapus halaman ini kapan saja lewat wp-admin
    tanpa sentuh kode.

### 2026-08-17 — Tag `<p>` bocor jadi teks mentah di halaman single

30. **User melaporkan halaman single menampilkan literal `"<p>ini adalah sebuah testimoni
    keren</p>"` alih-alih teks biasa.** Dicek dulu isi mentah di database (BUKAN langsung
    menebak/mengubah kode) — `postmeta.kesan_pesan` ternyata cuma `'ini adalah sebuah testimoni
    keren'`, TIDAK ada tag HTML sama sekali. Jadi tag `<p>` itu bukan data tersimpan yang rusak,
    melainkan hasil transformasi `get_field()` saat DIBACA: field `kesan_pesan` di
    `acf-json/group_bukutamu_entry.json` diset `"new_lines": "wpautop"` (bikin ACF otomatis
    membungkus teks polos dengan `<p>...</p>` setiap kali `get_field()` dipanggil), sementara
    `templates/single-buku_tamu.php` menampilkannya lewat `esc_html()` — yang mengubah tag
    `<p>` hasil wpautop itu jadi teks literal `&lt;p&gt;...&lt;/p&gt;` alih-alih strip/render.
    **Kenapa kartu testimoni/arsip TIDAK kena bug yang sama** meski pakai field & `get_field()`
    yang persis sama: `templates/testimoni-card.php` memanggil `wp_trim_words()` untuk memotong
    teks jadi cuplikan, dan `wp_trim_words()` MEMANG memanggil `wp_strip_all_tags()` di awal
    (lihat `wp-includes/formatting.php`) — jadi tag `<p>` bawaan wpautop kebetulan terhapus di
    situ sebelum sempat di-`esc_html()`. Halaman single menampilkan teks LENGKAP (tanpa
    `wp_trim_words()`), jadi tidak ada langkah yang diam-diam membuang tag itu.
    **Perbaikan:** `new_lines` diganti dari `"wpautop"` ke `"br"` (baris baru → `<br />`, TANPA
    pembungkus `<p>` — menghindari `<p>` bersarang di dalam `<p class="...">` dekoratif milik
    template) + `templates/single-buku_tamu.php` diubah dari `esc_html( $kesan_pesan )` menjadi
    `wp_kses( $kesan_pesan, [ 'br' => [] ] )` supaya `<br>` hasil ACF dirender sebagai baris
    baru sungguhan, sementara tag lain (kalau suatu saat ada) tetap dibuang — bukan sekadar
    dihilangkan `esc_html()`-nya begitu saja, karena tanpa whitelist eksplisit itu berarti
    mempercayai HTML apa pun dari `get_field()` mentah-mentah.
    **Aturan umum:** kalau ACF field textarea diset `new_lines` selain kosong (`wpautop`/`br`),
    `get_field()` TIDAK mengembalikan teks polos — dia mengembalikan HTML jadi. Template yang
    menampilkannya WAJIB pilih salah satu secara sadar: (a) `new_lines` dikosongkan + tetap
    `esc_html()` kalau memang tidak butuh baris baru sama sekali, atau (b) `new_lines: "br"` +
    `wp_kses( $value, [ 'br' => [] ] )` (bukan `esc_html()` mentah) kalau baris baru perlu
    dipertahankan — jangan campur `new_lines` aktif dengan `esc_html()` polos, itu selalu
    menghasilkan tag bocor jadi teks seperti kasus ini. Untuk debugging serupa: SELALU cek nilai
    MENTAH di database dulu (`$wpdb` langsung, bukan lewat `get_field()`) sebelum menyimpulkan
    apakah masalahnya di data tersimpan atau di transformasi saat baca/tampil — dua penyebab itu
    butuh perbaikan yang sama sekali berbeda.

### 2026-08-17 — Diagnosis "redirect tidak jalan" di situs produksi tanpa akses server

31. **User melaporkan redirect ke halaman terima kasih tidak jalan di `pmdarulfalah.com`
    (situs produksi terpisah, BUKAN situs development tempat kerja sesi ini) plus error 429 di
    console.** Tidak ada akses SSH/WP-CLI/filesystem ke situs itu — diagnosis dilakukan MURNI
    lewat `curl` ke URL publik halaman tersebut (`curl -s -A "Mozilla/5.0" URL`), grep pola
    spesifik di HTML mentah yang dikembalikan:
    - `?ver=` pada URL aset ter-enqueue (`bukutamu.css?ver=0.2.5`, dst.) → konfirmasi kode
      plugin di server itu SUDAH ter-update ke versi yang mengandung fitur redirect (bukan
      versi lama yang memang belum punya fiturnya).
    - Atribut `data-redirect-url` pada tag `<form class="bukutamu-form">` → TIDAK ADA sama
      sekali. Ini membuktikan root cause BUKAN cache atau bug kode, melainkan shortcode di
      Page itu masih `[bukutamu_form]` polos (belum diedit admin situs itu untuk menambahkan
      atribut `redirect="URL"`) — persis seperti kondisi `isi-buku-tamu` di situs development
      SEBELUM saya update kontennya (lihat Lessons Learned #29).
    - Tebak beberapa slug Page tujuan redirect yang mungkin (`terima-kasih`,
      `terima-kasih-buku-tamu`, `thank-you`) via `curl -o /dev/null -w "%{http_code}"` →
      semuanya 404, konfirmasi Page "Terima Kasih" memang belum pernah dibuat di situs itu.
    - Cek teks heading ("Silakan isi buku tamu kunjungan Anda") masih muncul di HTML → konfirmasi
      atribut `judul="0"` juga belum ditambahkan, situs itu masih pakai shortcode versi lama
      apa adanya, cocok dengan kesimpulan "belum pernah diedit sejak fitur redirect dirilis".
    Error 429-nya sendiri disimpulkan (bukan diverifikasi langsung, karena tidak ada akses log
    server) sebagai `Bukutamu_Security::RATE_LIMIT_MAX` (1 submission/60 detik per IP) yang
    kepicu wajar karena user submit form berulang kali dalam <60 detik saat menguji redirect
    yang belum terkonfigurasi — bukan bug, tapi disebut eksplisit sebagai KESIMPULAN BERBASIS
    LOGIKA (kode rate-limit + skenario testing yang cocok), bukan fakta yang diverifikasi
    langsung, supaya sesi berikutnya tahu bedanya kalau ternyata dugaan ini salah nanti.
    **Aturan umum untuk debug situs produksi TANPA akses server:** `curl`/WebFetch ke URL
    publik masih bisa mengungkap banyak hal lewat HTML yang sudah dirender — versi plugin
    (`?ver=` pada aset), apakah suatu fitur opt-in sudah dikonfigurasi (cek atribut/markup
    yang jadi penanda fitur itu aktif), dan status Page terkait (lewat HTTP status code) —
    tanpa perlu kredensial apa pun, SELAMA halamannya publik. **WebFetch (yang mengonversi HTML
    ke markdown lewat model kecil) TIDAK cocok untuk ini** — ia membuang atribut HTML mentah
    yang justru paling dibutuhkan untuk diagnosis semacam ini; pakai `curl` mentah + `grep`
    pola spesifik saat butuh memeriksa atribut/markup persis, bukan cuma "isi teks halaman".
    **Aturan umum untuk fitur opt-in per situs (redirect, dan pola serupa di masa depan):**
    selalu ingatkan user secara eksplisit bahwa meng-update KODE plugin tidak otomatis
    mengaktifkan fitur yang butuh konfigurasi KONTEN (Page/atribut shortcode) — dua hal itu
    independen, dan user bisa salah kira "sudah update plugin" berarti "fiturnya otomatis aktif
    di semua situs", padahal architecture plugin ini sengaja selalu lewat Page + shortcode
    admin-editable (lihat Lessons Learned #29), bukan auto-provisioning konten.

<!-- Tambahkan entri baru di bawah ini seiring development berjalan -->

## Sistem Update Plugin (GitHub Releases)

Plugin ini tidak di-hosting di WordPress.org, jadi WordPress tidak tahu cara mengecek versi
baru secara native. `includes/class-updater.php` (`Bukutamu_Updater`) menyuntikkan info update
ke mekanisme WP Core yang SAMA dipakai plugin resmi (`pre_set_site_transient_update_plugins`,
`plugins_api`) — jadi "Ada pembaruan tersedia" + tombol **Update Now** muncul normal di halaman
Plugins wp-admin, tanpa UI custom tambahan dan tanpa library eksternal (mis. Plugin Update
Checker) — selaras prinsip "ringan".

**Sumber data:** GitHub Releases API — `GET /repos/webaneid/bukutamuwp/releases/latest`
(`Bukutamu_Updater::GITHUB_REPO`). Repo publik, jadi tidak butuh token/autentikasi untuk cek
maupun download.

**WAJIB setiap merilis versi baru:**
1. Naikkan `BUKUTAMU_VERSION` di `bukutamu.php`.
2. Commit & push ke `main`.
3. Jalankan `bin/release.sh` — build Tailwind, kemas ulang jadi `bukutamu.zip` yang BERSIH
   (folder `bukutamu/`, cuma file runtime: `bukutamu.php`, `uninstall.php`, `acf-json/`,
   `assets/`, `build/`, `includes/`, `templates/` — TANPA `node_modules/`, `src/`,
   `package*.json`, `.git*`, `CLAUDE.md`, `README.md`), lalu otomatis
   `gh release create`/`gh release upload` dengan tag `vX.Y.Z` + asset zip itu.

**Jangan pakai `gh release create` manual tanpa asset zip** — tanpa asset, GitHub memakai zip
source otomatisnya sendiri (`zipball_url`) yang: (a) ikut membawa semua file dev (tidak rapi
untuk didownload manual orang lain), dan (b) folder di dalamnya bernama `bukutamuwp-{versi}`,
bukan `bukutamu` (berisiko salah kenali sebagai plugin terpisah saat auto-update, lihat
mitigasi `upgrader_source_selection` di bawah — tetap dipertahankan sebagai fallback, tapi
asset zip yang benar dari `bin/release.sh` menghindari masalah ini sejak awal).

Tanpa Release baru (bukan cuma commit/push biasa), plugin TIDAK AKAN PERNAH terdeteksi ada
update — `class-updater.php` khusus membaca endpoint "latest release", bukan commit/branch
terbaru. Ini konsekuensi memilih GitHub Releases (bukan "watch commit terbaru di branch") —
sengaja, supaya update hanya terjadi untuk rilis yang benar-benar dimaksudkan stabil, bukan
setiap commit percobaan.

**Download manual (bukan lewat auto-update):** siapa pun bisa download plugin siap-install
langsung dari halaman Release, mis. `https://github.com/webaneid/bukutamuwp/releases/latest`
→ unduh `bukutamu.zip` di bagian Assets → upload lewat wp-admin **Plugins > Add New > Upload
Plugin**. Sudah diverifikasi end-to-end: download asset zip sungguhan dari URL publik lalu
dicek isinya (folder `bukutamu/`, `bukutamu.php` dengan header plugin valid).

**Mekanisme teknis penting:**
- Cache 12 jam lewat transient (`bukutamu_github_release`) — GitHub API dibatasi 60
  request/jam untuk request tanpa token; tanpa cache, cek update bisa kena rate limit.
  Kegagalan request juga di-cache (15 menit) supaya tidak mengulang request gagal terus-
  menerus setiap page load wp-admin selama GitHub down/rate-limited.
- Hook-hook di sini SENGAJA di-load unconditional (bukan di-guard `is_admin()`) — pengecekan
  update otomatis WordPress berjalan lewat WP-Cron ('wp_update_plugins' event), yang BUKAN
  konteks `is_admin()`. Kalau di-guard, cek update background dua-kali-sehari tidak akan
  pernah jalan.
- `Bukutamu_Updater::get_package_url()` memilih paket download dengan prioritas: **asset ZIP**
  di Release (dari `bin/release.sh`) dulu, baru fallback ke `zipball_url` (source zip otomatis
  GitHub) kalau rilis tidak punya asset ZIP. Filter `upgrader_source_selection` tetap
  dipertahankan sebagai jaring pengaman untuk skenario fallback itu — me-rename folder hasil
  ekstrak `zipball_url` (`{repo}-{hash}`) jadi `bukutamu` sebelum WordPress memindahkannya ke
  `wp-content/plugins/`, supaya dikenali sebagai update, bukan plugin baru yang terpisah.

**Tautan "Cek Update" manual** — muncul di baris plugin ini di halaman **Plugins**, sejajar
dengan "Nonaktifkan" dkk (pola yang sama dipakai plugin lain di situs ini, mis. Webane
Database). Klik tautan ini menghapus paksa cache 12 jam (`bukutamu_github_release`) DAN
transient `update_plugins` bawaan WP, lalu memanggil ulang `wp_update_plugins()` (fungsi core
yang sama dipakai WP-Cron/tombol "Periksa Lagi") saat itu juga — tidak perlu menunggu siklus
cache/cron berikutnya untuk memverifikasi rilis baru terbaca. Hasilnya (ada update / sudah versi
terbaru) ditampilkan lewat notice di halaman Plugins setelah redirect. Implementasi:
`Bukutamu_Updater::plugin_action_links()` / `handle_force_check()` / `render_force_check_notice()`.

**PENTING kalau menguji update di server yang JUGA jadi tempat development plugin ini** (mis.
`bukutamu.php` di server itu di-edit langsung, bukan diinstal dari Release/ZIP): "Periksa Lagi"
di `update-core.php` atau tautan "Cek Update" TIDAK akan pernah menampilkan Buku Tamu selama
`BUKUTAMU_VERSION` di file lokal sudah >= versi Release terbaru di GitHub — itu perilaku BENAR
(`version_compare( $remote, BUKUTAMU_VERSION, '>' )` di `check_for_update()`), BUKAN tanda
sistem update salah baca rilis. Untuk menguji end-to-end secara realistis, gunakan instalasi
TERPISAH yang benar-benar diinstal dari ZIP Release lama (bukan folder kerja git plugin ini) —
kalau update di-klik pada folder kerja git itu sendiri, WordPress akan MENGHAPUS ISI FOLDER
plugin dulu sebelum mengekstrak versi baru, yang berarti folder `.git/` di dalamnya ikut
terhapus (riwayat commit lokal hilang, walau remote GitHub tetap aman — perlu `git clone` ulang
kalau ingin lanjut development di situ).

## Roadmap Implementasi

1. ✅ **Scaffolding** — `bukutamu.php`, struktur folder, CPT, ACF JSON field group, cek dependency ACF PRO.
2. ⬜ **Admin UX** — kolom kustom di list table (thumbnail, nama, instansi, tanggal, status), aksi
   quick-approve. *(Sengaja dilewati dulu atas keputusan eksplisit user pada 2026-08-16 — lanjut ke Fase 3
   & 4 duluan. Masih pending, wp-admin default WordPress/ACF sudah cukup untuk moderasi manual sementara.)*
3. ✅ **Form publik** — `[bukutamu_form]`. Tailwind form custom (bukan `acf_form()`), signature pad via
   `<canvas>` (`build/js/bukutamu-signature.js`), upload foto dengan preview & hapus per-file
   (`build/js/bukutamu-form.js`), REST endpoint `POST /wp-json/bukutamu/v1/submit`
   (`includes/class-rest-api.php`) + lapisan keamanan (`class-security.php`: nonce, honeypot, timing check,
   rate limit per-IP), validasi upload (`class-uploads.php`), dan penyimpanan tanda tangan
   (`class-signature.php`).
4. ✅ **Tampilan testimoni** — shortcode `[bukutamu_testimoni jumlah="6"]`
   (`templates/testimoni-grid.php` + `testimoni-card.php`), menampilkan nama/instansi/cuplikan
   pesan/satu foto ACAK dari galeri, card link ke halaman single (bukan modal). Data pribadi
   (HP/email) & tanda tangan tidak pernah dikirim ke output publik — lihat Lessons Learned #20.
5. 🟡 **Hardening & QA** — sebagian sudah dijalankan (bukan cuma lint statis): CPT
   `supports`/`show_in_rest`/rewrite rules dicek lewat `get_post_type_object()` di runtime WP
   sungguhan, halaman archive/single/feed diuji lewat `curl` HTTP request nyata ke situs
   development (termasuk verifikasi HP/email TIDAK bocor di halaman single, dibandingkan
   dengan nilai field asli — bukan cuma grep pola generik), filter sitemap diuji lewat
   `apply_filters()` runtime. **2026-08-17:** submission end-to-end (nonce → honeypot/timing →
   validasi field → tanda tangan → upload galeri → `wp_insert_post`) diuji lewat `curl` yang
   mereplikasi persis alur `bukutamu-form.js` sungguhan (load halaman → ambil nonce asli →
   tunggu jeda anti-bot → POST multipart dengan file gambar asli), bukan cuma baca kode —
   berhasil menemukan & memperbaiki 3 bug nyata (nonce `X-WP-Nonce` saat admin ikut login,
   batas ukuran file 2MB terlalu kecil, tag `<p>` bocor dari `wpautop` ACF) yang tidak akan
   ketahuan dari lint statis maupun asumsi "kodenya terlihat benar". **Masih belum dijalankan**:
   submit form dari UI browser sungguhan (klik signature pad/upload foto langsung, bukan lewat
   curl), audit aksesibilitas (kontras warna, navigasi keyboard untuk canvas tanda tangan,
   screen reader), uji di berbagai ukuran layar.
6. ✅ **Arsip native CPT** — archive (`/buku-tamu/`) & single (`/buku-tamu/{slug}/`), lihat
   bagian "Alur Tampilan Arsip". Di luar rencana awal (awalnya shortcode `[bukutamu_arsip]`,
   diganti atas permintaan user pada 2026-08-17 — lihat Lessons Learned #19).
7. ⬜ **Opsional/masa depan** — Gutenberg block sebagai wrapper shortcode, export CSV dari wp-admin,
   WP-CLI command, dan Fase 2 (Admin UX) yang tertunda.
8. ✅ **Sistem update plugin** — lihat bagian "Sistem Update Plugin (GitHub Releases)". Diuji
   lewat request langsung ke GitHub API + simulasi filter `pre_set_site_transient_update_plugins`
   & `plugins_api` di runtime WP sungguhan (bukan cuma lint statis) — termasuk skenario gagal
   (belum ada release → 404, ditangani dengan aman tanpa menandai update palsu). Tautan "Cek
   Update" manual di baris plugin ditambahkan v0.2.5. Rilis v0.2.4/v0.2.5 sudah diverifikasi
   benar-benar terpasang & terdeteksi di deployment produksi sungguhan (pmdarulfalah.com, bukan
   cuma situs development) lewat pengecekan `?ver=` pada aset yang ter-enqueue.
9. ✅ **Redirect ke halaman "Terima Kasih"** — shortcode `[bukutamu_terima_kasih]` + atribut
   `[bukutamu_form redirect="URL"]`, opsional & backward compatible. Lihat bagian "Halaman
   Standalone" dan Lessons Learned #29. **Perlu diingat:** ini fitur opt-in PER SITUS — setiap
   instalasi (termasuk situs klien yang sudah ada sebelum fitur ini rilis, mis. pmdarulfalah.com)
   harus membuat Page "Terima Kasih" sendiri dan menambahkan atribut `redirect` secara manual di
   wp-admin; update plugin ke versi baru TIDAK otomatis mengaktifkan redirect di halaman yang
   sudah ada sebelumnya.

## Kredit

Dikembangkan oleh **Webane Indonesia** — https://webane.com (developer website & aplikasi).
