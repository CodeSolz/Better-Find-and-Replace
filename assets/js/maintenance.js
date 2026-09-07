/**
 * Content Health screen behaviour.
 *
 * Every request goes through the free plugin's hardened AJAX dispatcher, which
 * checks the nonce and the per-method capability server-side. Nothing here is a
 * security control - the confirmations exist so a person does not change their
 * own content by accident, not to stop anybody doing anything.
 *
 * Two rules the dialogs in this file follow, because every action on this
 * screen edits something somebody published:
 *
 * - Say what will change, where, and whether the site changes at all. "Ignore"
 *   and "Re-check" touch nothing; "Remove link" and "Replace URL" edit a post.
 * - Never write on the strength of a click alone. A change is previewed by the
 *   same code that applies it, and the preview is what the person confirms.
 *
 * @since 1.10.0
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.bfrMaintenance === 'undefined' ) {
		return;
	}

	var cfg = window.bfrMaintenance;
	var pollTimer = null;

	// Consecutive failed status requests, and how many are forgiven before
	// polling gives up. A dropped request is usually the network rather than
	// the scan, and one blip should not end the poll.
	var pollFailures = 0;
	var POLL_RETRIES = 3;

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
	 * Text, safe to drop into a dialog.
	 *
	 * @param {string} value Raw text.
	 * @return {string} Escaped.
	 */
	function esc( value ) {
		return $( '<div/>' ).text( null === value || undefined === value ? '' : value ).html();
	}

	/**
	 * Fill in a translated string's placeholders.
	 *
	 * @param {string} template A string with %1$s style placeholders.
	 * @param {Array}  values   Values, in order.
	 * @return {string} The finished string.
	 */
	function format( template, values ) {
		return String( template ).replace( /%(\d+)\$s/g, function ( match, position ) {
			var value = values[ parseInt( position, 10 ) - 1 ];

			return undefined === value ? match : value;
		} );
	}

	/* ------------------------------------------------------------------
	 * The banner, between the heading and the list
	 * ---------------------------------------------------------------- */

	/**
	 * Say something about the screen as a whole.
	 *
	 * @param {string} text Message. An empty string hides the banner.
	 * @param {string} tone 'busy', 'info', 'success' or 'error'.
	 */
	function notify( text, tone ) {
		var $banner = $( '#bfrmaint-banner' );

		if ( ! $banner.length ) {
			return;
		}

		if ( ! text ) {
			$banner.attr( 'hidden', 'hidden' ).empty();

			return;
		}

		$banner
			.removeClass( 'is-busy is-info is-success is-error' )
			.addClass( 'is-' + ( tone || 'info' ) )
			.text( text )
			.removeAttr( 'hidden' );
	}

	/* ------------------------------------------------------------------
	 * Dialogs
	 * ---------------------------------------------------------------- */

	/**
	 * Is the dialog library there?
	 *
	 * It is enqueued with this file, so it always should be - but a page that
	 * loses it should still be usable rather than silently dead, so every
	 * caller falls back to the browser's own confirm().
	 *
	 * @return {boolean} Whether Swal can be used.
	 */
	function hasDialogs() {
		return typeof window.Swal !== 'undefined';
	}

	/**
	 * The house look, shared by every dialog on this screen.
	 *
	 * @param {Object} options Swal options to merge in.
	 * @return {Object} The finished options.
	 */
	function dialog( options ) {
		return $.extend(
			{
				buttonsStyling: false,
				reverseButtons: true,
				focusConfirm: false,
				showCancelButton: true,
				cancelButtonText: cfg.i18n.cancel,
				customClass: {
					popup: 'bfrmaint-swal',
					title: 'bfrmaint-swal-title',
					htmlContainer: 'bfrmaint-swal-body',
					actions: 'bfrmaint-swal-actions',
					confirmButton: 'btn btn-custom-submit',
					cancelButton: 'bfrmaint-btn-ghost'
				}
			},
			options
		);
	}

	/**
	 * Ask before doing something.
	 *
	 * @param {Object} options Title, html, and the confirm button's label.
	 * @return {jQuery.Deferred} Resolved when confirmed, rejected when not.
	 */
	function confirmThen( options ) {
		var done = $.Deferred();

		if ( ! hasDialogs() ) {
			if ( window.confirm( options.plain || options.title ) ) {
				done.resolve();
			} else {
				done.reject();
			}

			return done.promise();
		}

		window.Swal.fire(
			dialog( {
				title: options.title,
				html: options.html || '',
				icon: options.icon || 'question',
				confirmButtonText: options.confirm
			} )
		).then( function ( answer ) {
			if ( answer.isConfirmed ) {
				done.resolve();
			} else {
				done.reject();
			}
		} );

		return done.promise();
	}

	/* ------------------------------------------------------------------
	 * Scanning
	 * ---------------------------------------------------------------- */

	/**
	 * Put the scan button back the way it was.
	 *
	 * @param {jQuery} $button The button, or every scan button on the page.
	 */
	function releaseScan( $button ) {
		( $button && $button.length ? $button : $( '#bfrmaint-scan' ) ).prop( 'disabled', false );
	}

	$( document ).on( 'click', '#bfrmaint-scan', function ( e ) {
		e.preventDefault();

		var $button = $( this );

		// Disabled first, before anything is asked of the server: a second
		// click while the first is in flight would start a second scan.
		$button.prop( 'disabled', true );
		notify( cfg.i18n.working, 'busy' );

		pollFailures = 0;

		call( 'bfrmaint@scanstart' ).done( function ( response ) {
			var result = payload( response );

			if ( ! result.status ) {
				notify( result.message || cfg.i18n.failed, 'error' );
				releaseScan( $button );

				return;
			}

			notify( cfg.i18n.scanStarted, 'busy' );
			poll();
		} ).fail( function () {
			notify( cfg.i18n.failed, 'error' );
			releaseScan( $button );
		} );
	} );

	/**
	 * Ask for progress until the run stops.
	 *
	 * WP-Cron only advances the queue when something hits the site, so the
	 * poll doubles as the thing that keeps a scan moving while the tab is
	 * open on a quiet site.
	 */
	function poll() {
		window.clearTimeout( pollTimer );

		pollTimer = window.setTimeout( function () {
			call( 'bfrmaint@scanstatus' ).done( function ( response ) {
				var result = payload( response );

				pollFailures = 0;

				// The server answered, and the answer was no - an expired
				// nonce, a capability that went away, a run that is not there
				// any more. Polling cannot recover from any of those, so this
				// says why and hands the button back. Returning quietly, as
				// this once did, left the button disabled with nothing on
				// screen to explain it and no way out but a page reload.
				if ( ! result.status ) {
					notify( result.message || cfg.i18n.failed, 'error' );
					releaseScan();

					return;
				}

				if ( result.running ) {
					$( '#bfrmaint-scan-status' ).text(
						format( cfg.i18n.scanProgress, [ result.processed, result.total ] )
					);
					poll();

					return;
				}

				// The run is over, one way or another, so the button works
				// again whatever the outcome turns out to be.
				releaseScan();

				// A scan that was killed or stopped is not a scan that
				// finished, and the banner must not say it was. The server
				// words this one: it is the only side that knows how the run
				// ended.
				if ( result.message ) {
					notify( result.message, 'error' );

					return;
				}

				// Finished properly. The list is reloaded so what it shows is
				// what the scan just found.
				notify( cfg.i18n.scanFinished, 'success' );

				window.setTimeout( function () {
					window.location.reload();
				}, 1200 );
			} ).fail( function () {
				// A dropped request is usually the network rather than the
				// scan, so this retries before giving up - and when it does
				// give up it still releases the button, because a scan nobody
				// can watch is no reason to leave a screen nobody can use.
				pollFailures += 1;

				if ( pollFailures <= POLL_RETRIES ) {
					poll();

					return;
				}

				notify( cfg.i18n.failed, 'error' );
				releaseScan();
			} );
		}, 2500 );
	}

	// A scan already running when the page loaded should show progress too.
	if ( $( '#bfrmaint-scan' ).prop( 'disabled' ) ) {
		poll();
	}

	/* ------------------------------------------------------------------
	 * Per-issue actions
	 * ---------------------------------------------------------------- */

	/**
	 * What each row action says before it does anything.
	 *
	 * @param {string} action The data-do value.
	 * @return {Object|null} Dialog copy, or null for an action with no dialog.
	 */
	function actionCopy( action ) {
		if ( 'ignore' === action ) {
			return {
				title: cfg.i18n.ignoreTitle,
				html: cfg.i18n.ignoreText,
				confirm: cfg.i18n.ignoreConfirm
			};
		}

		if ( 'unignore' === action ) {
			return {
				title: cfg.i18n.unignoreTitle,
				html: cfg.i18n.unignoreText,
				confirm: cfg.i18n.unignoreConfirm
			};
		}

		if ( 'recheck' === action ) {
			return {
				title: cfg.i18n.recheckTitle,
				html: cfg.i18n.recheckText,
				confirm: cfg.i18n.recheckConfirm
			};
		}

		return null;
	}

	/**
	 * Ignore, put back, or re-check one issue.
	 *
	 * @param {jQuery} $link The row action clicked.
	 */
	function runIssueAction( $link ) {
		var $row = $link.closest( 'tr' );

		$row.addClass( 'bfrmaint-row-busy' );
		notify( cfg.i18n.working, 'busy' );

		call( 'bfrmaint@issueaction', {
			issue_id: $link.data( 'issue' ),
			do: $link.data( 'do' )
		} ).done( function ( response ) {
			var result = payload( response );

			notify( result.message || '', result.status ? 'success' : 'error' );

			if ( result.status ) {
				window.location.reload();
			} else {
				$row.removeClass( 'bfrmaint-row-busy' );
			}
		} ).fail( function () {
			notify( cfg.i18n.failed, 'error' );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	}

	$( document ).on( 'click', '.bfrmaint-issue-action', function ( e ) {
		e.preventDefault();

		var $link = $( this );
		var copy = actionCopy( String( $link.data( 'do' ) ) );

		if ( ! copy ) {
			runIssueAction( $link );

			return;
		}

		confirmThen( {
			title: copy.title,
			html: '<p>' + esc( copy.html ) + '</p>',
			plain: copy.title,
			confirm: copy.confirm
		} ).done( function () {
			runIssueAction( $link );
		} );
	} );

	/**
	 * Preview a change, then apply it only if the person confirms.
	 *
	 * The preview is the same code path the apply uses, so what it reports is
	 * what will happen.
	 *
	 * @param {number} issueId     Issue id.
	 * @param {string} operation   'replace' or 'unlink'.
	 * @param {string} replacement New URL, for a replace.
	 * @param {jQuery} $row        The table row.
	 */
	function previewThenApply( issueId, operation, replacement, $row ) {
		$row.addClass( 'bfrmaint-row-busy' );
		notify( cfg.i18n.working, 'busy' );

		call( 'bfrmaint@fixpreview', {
			issue_id: issueId,
			operation: operation,
			replacement: replacement
		} ).done( function ( response ) {
			var result = payload( response );

			if ( ! result.status ) {
				notify( result.message || cfg.i18n.failed, 'error' );
				$row.removeClass( 'bfrmaint-row-busy' );

				return;
			}

			notify( '', '' );

			confirmThen( {
				title: cfg.i18n.previewTitle,
				html: previewHtml( result ),
				plain: result.message + '\n\n' + cfg.i18n.previewTitle,
				confirm: cfg.i18n.previewApply,
				icon: 'warning'
			} ).done( function () {
				applyFix( issueId, operation, replacement, $row );
			} ).fail( function () {
				$row.removeClass( 'bfrmaint-row-busy' );
			} );
		} ).fail( function () {
			notify( cfg.i18n.failed, 'error' );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	}

	/**
	 * What the preview looks like inside the dialog.
	 *
	 * The samples arrive escaped by the server, so they are placed as markup
	 * rather than escaped a second time - doing that would show the entities.
	 *
	 * @param {Object} result The fixpreview payload.
	 * @return {string} Dialog markup.
	 */
	function previewHtml( result ) {
		var html = '<p class="bfrmaint-swal-lead">' + esc( result.message || '' ) + '</p>';
		var samples = result.samples || [];

		$.each( samples, function ( index, sample ) {
			html += '<div class="bfrmaint-hunk">'
				+ '<div class="bfrmaint-swal-label">' + esc( cfg.i18n.previewFrom ) + '</div>'
				+ '<del class="bfrmaint-was">' + sample.from + '</del>'
				+ '<div class="bfrmaint-swal-label">' + esc( cfg.i18n.previewTo ) + '</div>'
				+ '<ins class="bfrmaint-now">' + sample.to + '</ins>'
				+ '</div>';
		} );

		return html + '<p class="description">' + esc( cfg.i18n.previewNote ) + '</p>';
	}

	/**
	 * Write the change.
	 *
	 * @param {number} issueId     Issue id.
	 * @param {string} operation   'replace' or 'unlink'.
	 * @param {string} replacement New URL, for a replace.
	 * @param {jQuery} $row        The table row.
	 */
	function applyFix( issueId, operation, replacement, $row ) {
		notify( cfg.i18n.working, 'busy' );

		call( 'bfrmaint@fixapply', {
			issue_id: issueId,
			operation: operation,
			replacement: replacement
		} ).done( function ( applied ) {
			var outcome = payload( applied );

			notify( outcome.message || '', outcome.status ? 'success' : 'error' );

			if ( outcome.status ) {
				window.location.reload();
			} else {
				$row.removeClass( 'bfrmaint-row-busy' );
			}
		} ).fail( function () {
			notify( cfg.i18n.failed, 'error' );
			$row.removeClass( 'bfrmaint-row-busy' );
		} );
	}

	/**
	 * Does this look like somewhere a link can point?
	 *
	 * Deliberately generous: the server validates properly, and a person
	 * typing a path on their own site should not be argued with.
	 *
	 * @param {string} value The typed URL.
	 * @return {boolean} Whether it is worth sending.
	 */
	function looksLikeUrl( value ) {
		return /^https?:\/\/[^\s]+\.[^\s]+$/i.test( value ) || /^\/[^\s]*$/.test( value );
	}

	$( document ).on( 'click', '.bfrmaint-fix', function ( e ) {
		e.preventDefault();

		var $link = $( this );
		var current = String( $link.data( 'url' ) || '' );
		var $row = $link.closest( 'tr' );

		if ( ! hasDialogs() ) {
			var typed = window.prompt( cfg.i18n.replaceLabel, current );

			if ( null === typed || '' === $.trim( typed ) ) {
				return;
			}

			previewThenApply( $link.data( 'issue' ), 'replace', $.trim( typed ), $row );

			return;
		}

		window.Swal.fire(
			dialog( {
				title: cfg.i18n.replaceTitle,
				html: '<div class="bfrmaint-swal-field">'
					+ '<div class="bfrmaint-swal-label">' + esc( cfg.i18n.replaceCurrent ) + '</div>'
					+ '<code class="bfrmaint-swal-url">' + esc( current ) + '</code>'
					+ '</div>'
					+ '<div class="bfrmaint-swal-field">'
					+ '<label class="bfrmaint-swal-label" for="bfrmaint-swal-url">' + esc( cfg.i18n.replaceLabel ) + '</label>'
					+ '<input type="url" id="bfrmaint-swal-url" class="bfrmaint-swal-input" placeholder="https://" />'
					+ '<p class="description">' + esc( cfg.i18n.replaceHint ) + '</p>'
					+ '</div>',
				confirmButtonText: cfg.i18n.replaceConfirm,
				didOpen: function () {
					var field = document.getElementById( 'bfrmaint-swal-url' );

					if ( field ) {
						field.focus();
					}
				},
				preConfirm: function () {
					var field = document.getElementById( 'bfrmaint-swal-url' );
					var value = field ? $.trim( field.value ) : '';

					if ( '' === value ) {
						window.Swal.showValidationMessage( cfg.i18n.replaceEmpty );

						return false;
					}

					if ( value === current ) {
						window.Swal.showValidationMessage( cfg.i18n.replaceSame );

						return false;
					}

					if ( ! looksLikeUrl( value ) ) {
						window.Swal.showValidationMessage( cfg.i18n.replaceInvalid );

						return false;
					}

					return value;
				}
			} )
		).then( function ( answer ) {
			if ( ! answer.isConfirmed || ! answer.value ) {
				return;
			}

			previewThenApply( $link.data( 'issue' ), 'replace', answer.value, $row );
		} );
	} );

	$( document ).on( 'click', '.bfrmaint-unlink', function ( e ) {
		e.preventDefault();

		var $link = $( this );

		confirmThen( {
			title: cfg.i18n.unlinkTitle,
			html: '<p>' + esc( cfg.i18n.unlinkText ) + '</p>',
			plain: cfg.i18n.unlinkTitle,
			confirm: cfg.i18n.unlinkConfirm,
			icon: 'warning'
		} ).done( function () {
			previewThenApply( $link.data( 'issue' ), 'unlink', '', $link.closest( 'tr' ) );
		} );
	} );
}( jQuery ) );
