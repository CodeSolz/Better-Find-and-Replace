/**
 * Redirects screen behaviour.
 *
 * Every request goes through the free plugin's hardened AJAX dispatcher, which
 * checks the nonce and the capability server-side. The validation shown here is
 * only there to save a round trip - the server validates again regardless, and
 * its answer is the one that counts.
 *
 * @since 1.10.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.bfrRedirects === 'undefined' ) {
		return;
	}

	var cfg = window.bfrRedirects;

	/**
	 * Post to the dispatcher.
	 *
	 * @param {string} method Allow-listed method key.
	 * @param {Object} data   Payload.
	 * @return {jQuery.Deferred} The request.
	 */
	function call( method, data ) {
		return $.post( cfg.ajaxUrl, {
			action: 'rtafar_ajax',
			cs_token: cfg.token,
			method: method,
			data: data || {}
		} );
	}

	/**
	 * The server's reply, whatever shape the dispatcher wrapped it in.
	 *
	 * @param {Object} response Raw response.
	 * @return {Object} The payload.
	 */
	function payload( response ) {
		if ( response && typeof response.data !== 'undefined' ) {
			return response.data;
		}

		return response || {};
	}

	/**
	 * Show a message by the save button.
	 *
	 * @param {string}  text    Message.
	 * @param {boolean} isError Whether it is a failure.
	 */
	function notify( text, isError ) {
		$( '#bfr-redirect-notice' )
			.removeClass( 'is-error is-success' )
			.addClass( isError ? 'is-error' : 'is-success' )
			.text( text );
	}

	$( document ).on( 'click', '#bfr-redirect-save', function ( e ) {
		e.preventDefault();

		var $button = $( this );
		// FormBuilder assigns its own sequential ids, so the fields are
		// addressed by name - see the class docblock on RedirectsScreen.
		var source = $.trim( $( '[name="bfr_redirect[source]"]' ).val() || '' );
		var destination = $.trim( $( '[name="bfr_redirect[destination]"]' ).val() || '' );
		var type = parseInt( $( '[name="bfr_redirect[type]"]' ).val(), 10 ) || 301;

		$button.prop( 'disabled', true );
		notify( '', false );

		call( 'bfrmaint@redirectsave', {
			source: source,
			destination: destination,
			type: type
		} ).done( function ( response ) {
			var result = payload( response );

			if ( ! result.status ) {
				notify( result.message || cfg.i18n.failed, true );
				$button.prop( 'disabled', false );

				return;
			}

			// A chain is worth telling the person about, but it is their call:
			// the redirect they asked for has already been saved either way.
			if ( result.suggested ) {
				if ( window.confirm( cfg.i18n.useSuggested + '\n\n' + result.suggested ) ) {
					call( 'bfrmaint@redirectsave', {
						id: result.id,
						source: source,
						destination: result.suggested,
						type: type
					} ).always( function () {
						window.location.reload();
					} );

					return;
				}
			}

			window.location.reload();
		} ).fail( function () {
			notify( cfg.i18n.failed, true );
			$button.prop( 'disabled', false );
		} );
	} );

	$( document ).on( 'click', '.bfr-redirect-delete', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( cfg.i18n.confirmDelete ) ) {
			return;
		}

		var $row = $( this ).closest( 'tr' );

		$row.addClass( 'bfrmaint-row-busy' );

		call( 'bfrmaint@redirectdelete', { id: $( this ).data( 'id' ) } ).done( function ( response ) {
			var result = payload( response );

			if ( result.status ) {
				window.location.reload();
			} else {
				notify( result.message || cfg.i18n.failed, true );
				$row.removeClass( 'bfrmaint-row-busy' );
			}
		} ).fail( function () {
			notify( cfg.i18n.failed, true );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	} );

	$( document ).on( 'click', '.bfr-redirect-toggle', function ( e ) {
		e.preventDefault();

		var $row = $( this ).closest( 'tr' );

		$row.addClass( 'bfrmaint-row-busy' );

		call( 'bfrmaint@redirecttoggle', { id: $( this ).data( 'id' ) } ).done( function ( response ) {
			var result = payload( response );

			if ( result.status ) {
				window.location.reload();
			} else {
				notify( result.message || cfg.i18n.failed, true );
				$row.removeClass( 'bfrmaint-row-busy' );
			}
		} ).fail( function () {
			notify( cfg.i18n.failed, true );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	} );
}( jQuery ) );

/**
 * 404 Monitor tab.
 *
 * Shares this file with the Redirect Manager because they are two tabs of one
 * screen; the handlers are inert on the tab that does not render their markup.
 *
 * @since 1.10.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.bfrRedirects === 'undefined' ) {
		return;
	}

	var cfg = window.bfrRedirects;

	/**
	 * Post to the dispatcher.
	 *
	 * @param {string} method Allow-listed method key.
	 * @param {Object} data   Payload.
	 * @return {jQuery.Deferred} The request.
	 */
	function call( method, data ) {
		return $.post( cfg.ajaxUrl, {
			action: 'rtafar_ajax',
			cs_token: cfg.token,
			method: method,
			data: data || {}
		} );
	}

	/**
	 * The server's reply, whatever shape the dispatcher wrapped it in.
	 *
	 * @param {Object} response Raw response.
	 * @return {Object} The payload.
	 */
	function payload( response ) {
		if ( response && typeof response.data !== 'undefined' ) {
			return response.data;
		}

		return response || {};
	}

	/**
	 * Show a message on the 404 tab.
	 *
	 * @param {string}  text    Message.
	 * @param {boolean} isError Whether it is a failure.
	 */
	function notify404( text, isError ) {
		$( '#bfr-404-notice' )
			.removeClass( 'is-error is-success' )
			.addClass( isError ? 'is-error' : 'is-success' )
			.text( text );
	}

	$( document ).on( 'click', '#bfr-404-toggle', function ( e ) {
		e.preventDefault();

		var $button = $( this );

		$button.prop( 'disabled', true );

		call( 'bfrmaint@404toggle' ).done( function ( response ) {
			var result = payload( response );

			if ( result.status ) {
				window.location.reload();
			} else {
				notify404( result.message || cfg.i18n.failed, true );
				$button.prop( 'disabled', false );
			}
		} ).fail( function () {
			notify404( cfg.i18n.failed, true );
			$button.prop( 'disabled', false );
		} );
	} );

	$( document ).on( 'click', '.bfr-404-status', function ( e ) {
		e.preventDefault();

		var $row = $( this ).closest( 'tr' );

		$row.addClass( 'bfrmaint-row-busy' );

		call( 'bfrmaint@404status', {
			id: $( this ).data( 'id' ),
			status: $( this ).data( 'status' )
		} ).done( function ( response ) {
			var result = payload( response );

			if ( result.status ) {
				window.location.reload();
			} else {
				notify404( result.message || cfg.i18n.failed, true );
				$row.removeClass( 'bfrmaint-row-busy' );
			}
		} ).fail( function () {
			notify404( cfg.i18n.failed, true );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	} );

	// Create redirect: hand the dead path to the Redirect Manager tab with the
	// "from" field already filled in, rather than duplicating the form here.
	$( document ).on( 'click', '.bfr-404-redirect', function ( e ) {
		e.preventDefault();

		var path = $( this ).data( 'path' ) || '';
		var base = window.location.href.split( '#' )[ 0 ];

		base = base.replace( /([?&])tab=[^&]*/, '$1tab=redirects' );

		if ( base.indexOf( 'tab=' ) === -1 ) {
			base += ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + 'tab=redirects';
		}

		window.location.href = base + '&bfr_source=' + encodeURIComponent( path );
	} );

	$( document ).on( 'click', '.bfr-404-references', function ( e ) {
		e.preventDefault();

		var $row = $( this ).closest( 'tr' );

		$row.addClass( 'bfrmaint-row-busy' );

		call( 'bfrmaint@404references', { path: $( this ).data( 'path' ) } ).done( function ( response ) {
			var result = payload( response );
			var lines = [ result.message || '' ];

			$.each( result.references || [], function ( i, ref ) {
				lines.push( '• ' + ref.title + ' (' + ref.occurrences + ')' );
			} );

			window.alert( lines.join( '\n' ) );
			$row.removeClass( 'bfrmaint-row-busy' );
		} ).fail( function () {
			notify404( cfg.i18n.failed, true );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	} );

	// Arriving from "Create redirect" on the 404 tab.
	$( function () {
		var match = window.location.search.match( /[?&]bfr_source=([^&]*)/ );

		if ( ! match ) {
			return;
		}

		$( '[name="bfr_redirect[source]"]' )
			.val( decodeURIComponent( match[ 1 ] ) )
			.trigger( 'focus' );

		$( '[name="bfr_redirect[destination]"]' ).trigger( 'focus' );
	} );
}( jQuery ) );

/**
 * Replace + Redirect tab.
 *
 * Preview first, always. This operation rewrites content across the whole site,
 * so the apply button does not exist until the person has seen what it would
 * touch - and the preview is produced by the same code path the apply uses.
 *
 * @since 1.10.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.bfrRedirects === 'undefined' ) {
		return;
	}

	var cfg = window.bfrRedirects;

	/**
	 * Post to the dispatcher.
	 *
	 * @param {string} method Allow-listed method key.
	 * @param {Object} data   Payload.
	 * @return {jQuery.Deferred} The request.
	 */
	function call( method, data ) {
		return $.post( cfg.ajaxUrl, {
			action: 'rtafar_ajax',
			cs_token: cfg.token,
			method: method,
			data: data || {}
		} );
	}

	/**
	 * The server's reply, whatever shape the dispatcher wrapped it in.
	 *
	 * @param {Object} response Raw response.
	 * @return {Object} The payload.
	 */
	function payload( response ) {
		if ( response && typeof response.data !== 'undefined' ) {
			return response.data;
		}

		return response || {};
	}

	/**
	 * Show a message by the preview button.
	 *
	 * @param {string}  text    Message.
	 * @param {boolean} isError Whether it is a failure.
	 */
	function notifyRR( text, isError ) {
		$( '#bfr-rr-notice' )
			.removeClass( 'is-error is-success' )
			.addClass( isError ? 'is-error' : 'is-success' )
			.text( text );
	}

	/**
	 * The two URLs currently entered.
	 *
	 * @return {Object} from and to.
	 */
	function urls() {
		return {
			from: $.trim( $( '[name="bfr_rr[from]"]' ).val() || '' ),
			to: $.trim( $( '[name="bfr_rr[to]"]' ).val() || '' )
		};
	}

	/**
	 * Render the preview, with the confirm button under it.
	 *
	 * @param {Object} result Preview payload.
	 */
	function renderPreview( result ) {
		var $out = $( '#bfr-rr-result' );
		var html = '<h3 class="bfrmaint-subhead">' + result.message + '</h3>';

		if ( result.locations && result.locations.length ) {
			html += '<table class="wp-list-table widefat fixed striped"><thead><tr>' +
				'<th>Where</th><th>What</th><th>Times</th></tr></thead><tbody>';

			$.each( result.locations, function ( i, loc ) {
				html += '<tr><td>' + loc.table + '</td><td>' + loc.label +
					( loc.context ? ' <span class="bfrmaint-anchor">' + loc.context + '</span>' : '' ) +
					'</td><td>' + loc.occurrences + '</td></tr>';
			} );

			html += '</tbody></table>';

			if ( result.truncated ) {
				html += '<p class="bfrmaint-anchor">Showing the most affected places; there are more.</p>';
			}
		}

		if ( result.redirect && ! result.redirect.ready && result.redirect.note ) {
			html += '<p class="bfrmaint-notice is-error">' + result.redirect.note + '</p>';
		}

		html += '<p><button type="button" class="btn btn-custom-submit" id="bfr-rr-apply">' +
			'Apply and create the redirect</button></p>';

		$out.html( html ).prop( 'hidden', false );
	}

	$( document ).on( 'click', '#bfr-rr-preview', function ( e ) {
		e.preventDefault();

		var $button = $( this );
		var pair = urls();

		$button.prop( 'disabled', true );
		notifyRR( '', false );
		$( '#bfr-rr-result' ).prop( 'hidden', true ).empty();

		call( 'bfrmaint@rrpreview', pair ).done( function ( response ) {
			var result = payload( response );

			if ( ! result.status ) {
				notifyRR( result.message || cfg.i18n.failed, true );
			} else {
				renderPreview( result );
			}

			$button.prop( 'disabled', false );
		} ).fail( function () {
			notifyRR( cfg.i18n.failed, true );
			$button.prop( 'disabled', false );
		} );
	} );

	$( document ).on( 'click', '#bfr-rr-apply', function ( e ) {
		e.preventDefault();

		var $button = $( this );
		var pair = urls();

		if ( ! window.confirm( 'Replace this URL everywhere and create the redirect?' ) ) {
			return;
		}

		$button.prop( 'disabled', true );
		notifyRR( cfg.i18n.working || 'Working...', false );

		call( 'bfrmaint@rrapply', pair ).done( function ( response ) {
			var result = payload( response );

			notifyRR( result.message || cfg.i18n.failed, ! result.status );

			if ( result.status ) {
				var extra = ( result.warnings || [] ).join( ' ' );

				$( '#bfr-rr-result' ).html(
					'<p class="bfrmaint-notice is-success">' + result.message + '</p>' +
					( extra ? '<p class="bfrmaint-notice is-error">' + extra + '</p>' : '' )
				);
			} else {
				$button.prop( 'disabled', false );
			}
		} ).fail( function () {
			notifyRR( cfg.i18n.failed, true );
			$button.prop( 'disabled', false );
		} );
	} );
}( jQuery ) );
