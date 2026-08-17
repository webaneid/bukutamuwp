/**
 * Handler form publik buku tamu: preview foto, signature pad, dan submit via REST API
 * (fetch + FormData). Tanpa jQuery, tanpa dependency eksternal.
 *
 * Validasi di sini hanya untuk UX (feedback instan) — validasi yang menentukan tetap di
 * server (lihat includes/class-rest-api.php). Jangan pernah anggap validasi client cukup.
 */
(function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function initForm( form ) {
		var config = window.bukutamuFormConfig || {};
		var i18n = config.i18n || {};

		var alertBox = form.querySelector( '.bukutamu-form__alert' );
		var submitBtn = form.querySelector( '.bukutamu-form__submit' );
		var submitLabel = form.querySelector( '.bukutamu-form__submit-label' );
		var spinner = form.querySelector( '.bukutamu-form__spinner' );
		var fileInput = form.querySelector( '.bukutamu-form__dropzone input[type="file"]' );
		var previewBox = form.querySelector( '.bukutamu-form__preview' );
		var canvas = form.querySelector( '.bukutamu-form__signature-pad' );
		var clearBtn = form.querySelector( '.bukutamu-form__signature-clear' );

		var selectedFiles = [];
		var pad = canvas && window.BukutamuSignaturePad ? new window.BukutamuSignaturePad( canvas ) : null;

		function showAlert( message, type ) {
			if ( ! alertBox ) {
				return;
			}
			alertBox.textContent = message;
			alertBox.classList.remove( 'bt-hidden', 'bt-bg-emerald-50', 'bt-text-emerald-700', 'bt-bg-rose-50', 'bt-text-rose-700' );
			if ( 'success' === type ) {
				alertBox.classList.add( 'bt-bg-emerald-50', 'bt-text-emerald-700' );
			} else {
				alertBox.classList.add( 'bt-bg-rose-50', 'bt-text-rose-700' );
			}
		}

		function hideAlert() {
			if ( alertBox ) {
				alertBox.classList.add( 'bt-hidden' );
			}
		}

		function renderPreview() {
			if ( ! previewBox ) {
				return;
			}
			previewBox.innerHTML = '';

			selectedFiles.forEach( function ( file, index ) {
				var url = URL.createObjectURL( file );

				var wrapper = document.createElement( 'div' );
				wrapper.className = 'bt-relative bt-aspect-square bt-overflow-hidden bt-rounded-lg bt-border bt-border-slate-200';

				var img = document.createElement( 'img' );
				img.src = url;
				img.alt = '';
				img.className = 'bt-h-full bt-w-full bt-object-cover';
				img.onload = function () {
					URL.revokeObjectURL( url );
				};

				var removeBtn = document.createElement( 'button' );
				removeBtn.type = 'button';
				removeBtn.setAttribute( 'aria-label', 'Hapus foto' );
				removeBtn.className = 'bt-absolute bt-right-1 bt-top-1 bt-flex bt-h-5 bt-w-5 bt-items-center bt-justify-center bt-rounded-full bt-bg-black/60 bt-text-xs bt-text-white';
				removeBtn.textContent = String.fromCharCode( 215 );
				removeBtn.addEventListener( 'click', function () {
					selectedFiles.splice( index, 1 );
					renderPreview();
				} );

				wrapper.appendChild( img );
				wrapper.appendChild( removeBtn );
				previewBox.appendChild( wrapper );
			} );
		}

		if ( fileInput ) {
			fileInput.addEventListener( 'change', function () {
				var incoming = Array.prototype.slice.call( fileInput.files );
				var maxFiles = config.maxFiles || 5;
				var maxSize = config.maxFileSizeBytes || 2 * 1024 * 1024;

				incoming.forEach( function ( file ) {
					if ( selectedFiles.length >= maxFiles ) {
						showAlert( i18n.tooManyFiles || 'Maksimum jumlah foto tercapai.', 'error' );
						return;
					}
					if ( file.size > maxSize ) {
						showAlert( i18n.fileTooLarge || 'Ukuran foto terlalu besar.', 'error' );
						return;
					}
					selectedFiles.push( file );
				} );

				fileInput.value = '';
				renderPreview();
			} );
		}

		if ( clearBtn && pad ) {
			clearBtn.addEventListener( 'click', function () {
				pad.clear();
			} );
		}

		function setSubmitting( isSubmitting ) {
			submitBtn.disabled = isSubmitting;
			if ( spinner ) {
				spinner.classList.toggle( 'bt-hidden', ! isSubmitting );
			}
			if ( submitLabel ) {
				submitLabel.textContent = isSubmitting
					? ( i18n.submitting || 'Mengirim...' )
					: ( i18n.submitLabel || 'Kirim' );
			}
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			hideAlert();

			if ( pad && pad.isEmpty() ) {
				showAlert( i18n.signatureRequired || 'Silakan bubuhkan tanda tangan.', 'error' );
				return;
			}

			if ( ! form.reportValidity() ) {
				return;
			}

			var formData = new FormData( form );

			selectedFiles.forEach( function ( file ) {
				formData.append( 'galeri_foto[]', file );
			} );

			if ( pad ) {
				formData.append( 'ttd_data', pad.toDataUrl() );
			}

			setSubmitting( true );

			fetch( config.restUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} )
				.then( function ( result ) {
					if ( result.ok && result.body && result.body.success ) {
						showAlert( result.body.message, 'success' );
						form.reset();
						selectedFiles = [];
						renderPreview();
						if ( pad ) {
							pad.clear();
						}
					} else {
						var message = ( result.body && result.body.message ) || i18n.genericError || 'Terjadi kesalahan.';
						showAlert( message, 'error' );
					}
				} )
				.catch( function () {
					showAlert( i18n.genericError || 'Terjadi kesalahan.', 'error' );
				} )
				.finally( function () {
					setSubmitting( false );
				} );
		} );
	}

	ready( function () {
		var forms = document.querySelectorAll( '.bukutamu-form' );
		forms.forEach( initForm );
	} );
})();
