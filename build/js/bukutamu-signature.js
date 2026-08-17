/**
 * Signature pad ringan berbasis <canvas>, tanpa dependency eksternal.
 * Menangani mouse & touch, serta scaling devicePixelRatio agar tetap tajam di layar retina.
 */
(function () {
	'use strict';

	function BukutamuSignaturePad( canvas ) {
		this.canvas = canvas;
		this.ctx = canvas.getContext( '2d' );
		this.drawing = false;
		this.hasInk = false;

		this.resize();
		window.addEventListener( 'resize', this.resize.bind( this ) );
		this.bindEvents();
	}

	BukutamuSignaturePad.prototype.resize = function () {
		var ratio = Math.max( window.devicePixelRatio || 1, 1 );
		var rect = this.canvas.getBoundingClientRect();
		var snapshot = this.hasInk ? this.canvas.toDataURL( 'image/png' ) : null;

		this.canvas.width = rect.width * ratio;
		this.canvas.height = rect.height * ratio;
		this.ctx.setTransform( 1, 0, 0, 1, 0, 0 );
		this.ctx.scale( ratio, ratio );
		this.ctx.lineWidth = 2;
		this.ctx.lineCap = 'round';
		this.ctx.lineJoin = 'round';
		this.ctx.strokeStyle = '#0f172a';

		if ( snapshot ) {
			var img = new Image();
			var ctx = this.ctx;
			img.onload = function () {
				ctx.drawImage( img, 0, 0, rect.width, rect.height );
			};
			img.src = snapshot;
		}
	};

	BukutamuSignaturePad.prototype.getPos = function ( event ) {
		var rect = this.canvas.getBoundingClientRect();
		var point = event.touches && event.touches.length ? event.touches[ 0 ] : event;
		return {
			x: point.clientX - rect.left,
			y: point.clientY - rect.top,
		};
	};

	BukutamuSignaturePad.prototype.start = function ( event ) {
		this.drawing = true;
		this.hasInk = true;
		var pos = this.getPos( event );
		this.ctx.beginPath();
		this.ctx.moveTo( pos.x, pos.y );
		event.preventDefault();
	};

	BukutamuSignaturePad.prototype.move = function ( event ) {
		if ( ! this.drawing ) {
			return;
		}
		var pos = this.getPos( event );
		this.ctx.lineTo( pos.x, pos.y );
		this.ctx.stroke();
		event.preventDefault();
	};

	BukutamuSignaturePad.prototype.stop = function () {
		this.drawing = false;
	};

	BukutamuSignaturePad.prototype.bindEvents = function () {
		this.canvas.addEventListener( 'mousedown', this.start.bind( this ) );
		this.canvas.addEventListener( 'mousemove', this.move.bind( this ) );
		window.addEventListener( 'mouseup', this.stop.bind( this ) );

		this.canvas.addEventListener( 'touchstart', this.start.bind( this ), { passive: false } );
		this.canvas.addEventListener( 'touchmove', this.move.bind( this ), { passive: false } );
		this.canvas.addEventListener( 'touchend', this.stop.bind( this ) );
	};

	BukutamuSignaturePad.prototype.clear = function () {
		var rect = this.canvas.getBoundingClientRect();
		this.ctx.clearRect( 0, 0, rect.width, rect.height );
		this.hasInk = false;
	};

	BukutamuSignaturePad.prototype.isEmpty = function () {
		return ! this.hasInk;
	};

	BukutamuSignaturePad.prototype.toDataUrl = function () {
		return this.canvas.toDataURL( 'image/png' );
	};

	window.BukutamuSignaturePad = BukutamuSignaturePad;
})();
