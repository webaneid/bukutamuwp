/**
 * Lightbox ringan untuk galeri foto di halaman single buku tamu: klik thumbnail membuka popup
 * berisi foto ukuran besar. Tanpa library eksternal, satu modal dibuat sekali lalu dipakai
 * ulang untuk semua thumbnail di halaman (lihat templates/single-buku_tamu.php).
 *
 * Catatan: kartu di arsip/testimoni (templates/testimoni-card.php) TIDAK memakai lightbox ini
 * — kartu itu link biasa ke halaman single, bukan trigger popup (lihat CLAUDE.md).
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

	function buildModal() {
		var overlay = document.createElement( 'div' );
		overlay.className = 'bukutamu-lightbox bt-fixed bt-inset-0 bt-z-[9999] bt-hidden bt-items-center bt-justify-center bt-bg-black/80 bt-p-4';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );

		var img = document.createElement( 'img' );
		img.alt = '';
		img.className = 'bt-max-h-full bt-max-w-full bt-rounded-lg bt-object-contain';

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.setAttribute( 'aria-label', 'Tutup' );
		closeBtn.className = 'bt-absolute bt-right-4 bt-top-4 bt-flex bt-h-9 bt-w-9 bt-items-center bt-justify-center bt-rounded-full bt-bg-white/90 bt-text-slate-700 hover:bt-bg-white';
		closeBtn.textContent = String.fromCharCode( 215 );

		overlay.appendChild( img );
		overlay.appendChild( closeBtn );
		document.body.appendChild( overlay );

		function close() {
			overlay.classList.add( 'bt-hidden' );
			overlay.classList.remove( 'bt-flex' );
			img.src = '';
		}

		closeBtn.addEventListener( 'click', close );
		overlay.addEventListener( 'click', function ( event ) {
			if ( event.target === overlay ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close();
			}
		} );

		return {
			open: function ( url ) {
				img.src = url;
				overlay.classList.remove( 'bt-hidden' );
				overlay.classList.add( 'bt-flex' );
			},
		};
	}

	ready( function () {
		var triggers = document.querySelectorAll( '[data-bukutamu-lightbox]' );
		if ( ! triggers.length ) {
			return;
		}

		var modal = buildModal();

		triggers.forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var url = trigger.getAttribute( 'data-bukutamu-lightbox' );
				if ( url ) {
					modal.open( url );
				}
			} );
		} );
	} );
})();
