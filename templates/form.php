<?php
/**
 * Template form publik buku tamu. Di-include lewat output buffering dari
 * Bukutamu_Shortcode::render_form(). Semua styling pakai utility class Tailwind ber-prefix
 * "bt-" (lihat src/tailwind.config.js) — sengaja tidak memuat CSS bawaan WordPress/tema.
 *
 * Elemen anti-abuse (honeypot, timestamp, nonce) WAJIB ada di setiap render form — lihat
 * Bukutamu_Security dan Bukutamu_Rest_Api::handle_submit().
 *
 * Variabel $tampilkan_judul disiapkan oleh Bukutamu_Shortcode::render_form() dari atribut
 * shortcode `judul` (default tampil) — dimatikan di halaman yang sudah punya judul sendiri.
 * Variabel $redirect_url (opsional) disiapkan dari atribut shortcode `redirect` — dirender
 * sebagai atribut `data-redirect-url` di elemen <form>, dibaca oleh bukutamu-form.js untuk
 * redirect browser setelah submit berhasil (lihat Bukutamu_Shortcode::render_form()).
 */

defined( 'ABSPATH' ) || exit;

$form_id   = 'bukutamu-form-' . wp_unique_id();
$loaded_at = time();
?>
<div class="bt-mx-auto bt-w-full bt-max-w-2xl bt-font-sans bt-text-slate-800">
	<form id="<?php echo esc_attr( $form_id ); ?>" class="bukutamu-form bt-space-y-6 bt-rounded-2xl bt-border bt-border-slate-200 bt-bg-white bt-p-6 bt-shadow-sm sm:bt-p-8" novalidate
		<?php echo ! empty( $redirect_url ) ? 'data-redirect-url="' . esc_url( $redirect_url ) . '"' : ''; ?>>

		<?php if ( $tampilkan_judul ) : ?>
			<div class="bt-space-y-1">
				<h2 class="bt-text-xl bt-font-semibold bt-text-slate-900"><?php esc_html_e( 'Buku Tamu', 'bukutamu' ); ?></h2>
				<p class="bt-text-sm bt-text-slate-500"><?php esc_html_e( 'Silakan isi buku tamu kunjungan Anda.', 'bukutamu' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="bukutamu-form__alert bt-hidden bt-rounded-lg bt-p-3 bt-text-sm" role="alert" aria-live="polite"></div>

		<input type="hidden" name="<?php echo esc_attr( Bukutamu_Security::TIMESTAMP_FIELD ); ?>" value="<?php echo esc_attr( $loaded_at ); ?>">
		<input type="hidden" name="bukutamu_nonce" value="<?php echo esc_attr( Bukutamu_Security::create_nonce() ); ?>">

		<div class="bt-absolute bt-left-[-9999px] bt-top-auto bt-h-px bt-w-px bt-overflow-hidden" aria-hidden="true">
			<label for="<?php echo esc_attr( $form_id . '-hp' ); ?>"><?php esc_html_e( 'Jangan isi kolom ini', 'bukutamu' ); ?></label>
			<input type="text" tabindex="-1" autocomplete="off" id="<?php echo esc_attr( $form_id . '-hp' ); ?>" name="<?php echo esc_attr( Bukutamu_Security::HONEYPOT_FIELD ); ?>" value="">
		</div>

		<div class="bt-grid bt-grid-cols-1 bt-gap-5 sm:bt-grid-cols-2">
			<div class="bt-space-y-1.5">
				<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700" for="<?php echo esc_attr( $form_id . '-nama' ); ?>">
					<?php bukutamu_icon( 'user', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
					<?php esc_html_e( 'Nama', 'bukutamu' ); ?> <span class="bt-text-rose-500">*</span>
				</label>
				<input type="text" required maxlength="150" name="nama" id="<?php echo esc_attr( $form_id . '-nama' ); ?>"
					class="bt-w-full bt-rounded-lg bt-border bt-border-slate-300 bt-px-3 bt-py-2 bt-text-sm bt-outline-none focus:bt-border-slate-500 focus:bt-ring-2 focus:bt-ring-slate-200"
					placeholder="<?php esc_attr_e( 'Nama lengkap', 'bukutamu' ); ?>">
			</div>

			<div class="bt-space-y-1.5">
				<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700" for="<?php echo esc_attr( $form_id . '-hp-number' ); ?>">
					<?php bukutamu_icon( 'phone', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
					<?php esc_html_e( 'Nomor HP', 'bukutamu' ); ?> <span class="bt-text-rose-500">*</span>
				</label>
				<input type="tel" required maxlength="20" name="nomor_hp" id="<?php echo esc_attr( $form_id . '-hp-number' ); ?>"
					class="bt-w-full bt-rounded-lg bt-border bt-border-slate-300 bt-px-3 bt-py-2 bt-text-sm bt-outline-none focus:bt-border-slate-500 focus:bt-ring-2 focus:bt-ring-slate-200"
					placeholder="08xxxxxxxxxx">
			</div>

			<div class="bt-space-y-1.5">
				<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700" for="<?php echo esc_attr( $form_id . '-email' ); ?>">
					<?php bukutamu_icon( 'mail', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
					<?php esc_html_e( 'Email', 'bukutamu' ); ?> <span class="bt-text-rose-500">*</span>
				</label>
				<input type="email" required name="email" id="<?php echo esc_attr( $form_id . '-email' ); ?>"
					class="bt-w-full bt-rounded-lg bt-border bt-border-slate-300 bt-px-3 bt-py-2 bt-text-sm bt-outline-none focus:bt-border-slate-500 focus:bt-ring-2 focus:bt-ring-slate-200"
					placeholder="nama@email.com">
			</div>

			<div class="bt-space-y-1.5">
				<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700" for="<?php echo esc_attr( $form_id . '-instansi' ); ?>">
					<?php bukutamu_icon( 'building', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
					<?php esc_html_e( 'Instansi / Lembaga', 'bukutamu' ); ?>
				</label>
				<input type="text" maxlength="150" name="instansi" id="<?php echo esc_attr( $form_id . '-instansi' ); ?>"
					class="bt-w-full bt-rounded-lg bt-border bt-border-slate-300 bt-px-3 bt-py-2 bt-text-sm bt-outline-none focus:bt-border-slate-500 focus:bt-ring-2 focus:bt-ring-slate-200"
					placeholder="<?php esc_attr_e( 'Opsional', 'bukutamu' ); ?>">
			</div>
		</div>

		<div class="bt-space-y-1.5">
			<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700" for="<?php echo esc_attr( $form_id . '-kesan' ); ?>">
				<?php bukutamu_icon( 'message', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
				<?php esc_html_e( 'Kesan & Pesan', 'bukutamu' ); ?> <span class="bt-text-rose-500">*</span>
			</label>
			<textarea required maxlength="1000" rows="4" name="kesan_pesan" id="<?php echo esc_attr( $form_id . '-kesan' ); ?>"
				class="bt-w-full bt-resize-none bt-rounded-lg bt-border bt-border-slate-300 bt-px-3 bt-py-2 bt-text-sm bt-outline-none focus:bt-border-slate-500 focus:bt-ring-2 focus:bt-ring-slate-200"
				placeholder="<?php esc_attr_e( 'Tuliskan kesan dan pesan Anda...', 'bukutamu' ); ?>"></textarea>
		</div>

		<div class="bt-space-y-1.5">
			<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700">
				<?php bukutamu_icon( 'camera', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
				<?php esc_html_e( 'Lampiran Foto', 'bukutamu' ); ?>
			</label>
			<label class="bukutamu-form__dropzone bt-flex bt-cursor-pointer bt-flex-col bt-items-center bt-justify-center bt-gap-2 bt-rounded-lg bt-border-2 bt-border-dashed bt-border-slate-300 bt-bg-slate-50 bt-px-4 bt-py-6 bt-text-center bt-text-sm bt-text-slate-500 hover:bt-border-slate-400">
				<?php bukutamu_icon( 'upload', 'bt-h-6 bt-w-6 bt-text-slate-400' ); ?>
				<span><?php esc_html_e( 'Klik atau seret foto ke sini (maks. 5 foto, @5MB)', 'bukutamu' ); ?></span>
				<input type="file" accept="image/jpeg,image/png,image/webp" multiple class="bt-hidden">
			</label>
			<p class="bukutamu-form__file-error bt-hidden bt-text-xs bt-text-rose-600" role="alert" aria-live="polite"></p>
			<div class="bukutamu-form__preview bt-grid bt-grid-cols-3 bt-gap-2 sm:bt-grid-cols-5"></div>
		</div>

		<div class="bt-space-y-1.5">
			<label class="bt-flex bt-items-center bt-gap-1.5 bt-text-sm bt-font-medium bt-text-slate-700">
				<?php bukutamu_icon( 'pen', 'bt-h-4 bt-w-4 bt-text-slate-400' ); ?>
				<?php esc_html_e( 'Tanda Tangan', 'bukutamu' ); ?> <span class="bt-text-rose-500">*</span>
			</label>
			<div class="bt-overflow-hidden bt-rounded-lg bt-border bt-border-slate-300 bt-bg-slate-50">
				<canvas class="bukutamu-form__signature-pad bt-block bt-h-40 bt-w-full bt-touch-none bt-bg-white"></canvas>
			</div>
			<button type="button" class="bukutamu-form__signature-clear bt-inline-flex bt-items-center bt-gap-1.5 bt-text-xs bt-font-medium bt-text-slate-500 hover:bt-text-slate-700">
				<?php bukutamu_icon( 'eraser', 'bt-h-3.5 bt-w-3.5' ); ?>
				<?php esc_html_e( 'Hapus Tanda Tangan', 'bukutamu' ); ?>
			</button>
		</div>

		<label class="bt-flex bt-items-start bt-gap-2.5 bt-text-sm bt-text-slate-600">
			<input type="checkbox" required name="persetujuan_privasi" value="1" class="bt-mt-0.5 bt-h-4 bt-w-4 bt-rounded bt-border-slate-300">
			<span><?php esc_html_e( 'Saya menyetujui data ini digunakan sesuai kebijakan privasi.', 'bukutamu' ); ?> <span class="bt-text-rose-500">*</span></span>
		</label>

		<button type="submit" class="bukutamu-form__submit bt-inline-flex bt-w-full bt-items-center bt-justify-center bt-gap-2 bt-rounded-lg bt-bg-slate-900 bt-px-4 bt-py-2.5 bt-text-sm bt-font-semibold bt-text-white bt-transition hover:bt-bg-slate-700 disabled:bt-cursor-not-allowed disabled:bt-opacity-60">
			<?php bukutamu_icon( 'spinner', 'bukutamu-form__spinner bt-hidden bt-h-4 bt-w-4 bt-animate-spin' ); ?>
			<span class="bukutamu-form__submit-label"><?php esc_html_e( 'Kirim', 'bukutamu' ); ?></span>
		</button>

		<p class="bt-text-center bt-text-xs bt-text-slate-400">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL webane.com */
					__( 'Buku Tamu oleh <a href="%s" target="_blank" rel="noopener noreferrer" class="bt-underline hover:bt-text-slate-600">Webane Indonesia</a>', 'bukutamu' ),
					esc_url( 'https://webane.com' )
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
						'class'  => [],
					],
				]
			);
			?>
		</p>
	</form>
</div>
