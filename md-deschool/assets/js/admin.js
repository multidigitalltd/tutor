/**
 * DeSchool admin scripts: native repeaters and media pickers.
 * Vanilla JS, no jQuery (Multi Digital performance standard).
 *
 * @package MultiDigital\DeSchool
 */
( function () {
	'use strict';

	/**
	 * Re-index repeater rows so submitted arrays stay sequential.
	 *
	 * @param {HTMLElement} repeater Repeater container.
	 */
	function reindex( repeater ) {
		var items = repeater.querySelectorAll( '[data-mdds-repeater-item]' );
		items.forEach( function ( item, index ) {
			item.querySelectorAll( 'input, textarea, select' ).forEach( function ( field ) {
				if ( ! field.name ) {
					return;
				}
				field.name = field.name.replace( /\[(?:\d+|__index__)\]/, '[' + index + ']' );
			} );
		} );
	}

	/**
	 * Initialise a single repeater.
	 *
	 * @param {HTMLElement} repeater Repeater container.
	 */
	function initRepeater( repeater ) {
		var key      = repeater.getAttribute( 'data-mdds-repeater' );
		var list     = repeater.querySelector( '[data-mdds-repeater-items]' );
		var addBtn   = repeater.querySelector( '[data-mdds-repeater-add]' );
		var template = document.querySelector( '[data-mdds-repeater-template="' + key + '"]' );

		if ( ! list || ! addBtn || ! template ) {
			return;
		}

		addBtn.addEventListener( 'click', function () {
			var html  = template.innerHTML.replace( /__index__/g, String( Date.now() ) );
			var temp  = document.createElement( 'div' );
			temp.innerHTML = html.trim();
			var row = temp.querySelector( '[data-mdds-repeater-item]' );
			if ( row ) {
				list.appendChild( row );
				reindex( repeater );
				var firstField = row.querySelector( 'input, textarea' );
				if ( firstField ) {
					firstField.focus();
				}
			}
		} );

		list.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '[data-mdds-repeater-remove]' );
			if ( ! remove ) {
				return;
			}
			event.preventDefault();
			var item = remove.closest( '[data-mdds-repeater-item]' );
			if ( item ) {
				item.parentNode.removeChild( item );
				reindex( repeater );
			}
		} );
	}

	/**
	 * Initialise a media picker field.
	 *
	 * @param {HTMLElement} wrap Media field wrapper.
	 */
	function initMedia( wrap ) {
		var selectBtn = wrap.querySelector( '[data-mdds-media-select]' );
		var removeBtn = wrap.querySelector( '[data-mdds-media-remove]' );
		var idField   = wrap.querySelector( '[data-mdds-media-id]' );
		var nameField = wrap.querySelector( '[data-mdds-media-name]' );

		if ( ! selectBtn || ! idField || ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame;
		selectBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = window.wp.media( {
				multiple: false
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				idField.value = attachment.id;
				if ( nameField ) {
					nameField.textContent = attachment.filename || attachment.title;
				}
				if ( removeBtn ) {
					removeBtn.hidden = false;
				}
			} );
			frame.open();
		} );

		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				idField.value = '';
				if ( nameField ) {
					nameField.textContent = '';
				}
				removeBtn.hidden = true;
			} );
		}
	}

	/**
	 * Initialise the multi-step course wizard.
	 *
	 * @param {HTMLElement} wizard Wizard form.
	 */
	function initWizard( wizard ) {
		var panels = Array.prototype.slice.call( wizard.querySelectorAll( '[data-mdds-wizard-step]' ) );
		var dots   = Array.prototype.slice.call( wizard.querySelectorAll( '[data-mdds-wizard-dots] li' ) );
		var back   = wizard.querySelector( '[data-mdds-wizard-back]' );
		var next   = wizard.querySelector( '[data-mdds-wizard-next]' );
		if ( ! panels.length || ! next ) {
			return;
		}

		var current = 0;

		function show( index ) {
			current = Math.max( 0, Math.min( index, panels.length - 1 ) );
			panels.forEach( function ( panel, i ) {
				panel.classList.toggle( 'is-active', i === current );
			} );
			dots.forEach( function ( dot, i ) {
				dot.classList.toggle( 'is-active', i <= current );
			} );
			if ( back ) {
				back.hidden = 0 === current;
			}
			next.hidden = current === panels.length - 1;
		}

		/**
		 * Validate required fields in the current panel before advancing.
		 *
		 * @return {boolean}
		 */
		function valid() {
			var fields = panels[ current ].querySelectorAll( 'input, textarea, select' );
			for ( var i = 0; i < fields.length; i++ ) {
				if ( ! fields[ i ].checkValidity() ) {
					fields[ i ].reportValidity();
					return false;
				}
			}
			return true;
		}

		next.addEventListener( 'click', function () {
			if ( valid() ) {
				show( current + 1 );
			}
		} );

		if ( back ) {
			back.addEventListener( 'click', function () {
				show( current - 1 );
			} );
		}

		show( 0 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-mdds-repeater]' ).forEach( initRepeater );
		document.querySelectorAll( '[data-mdds-media]' ).forEach( initMedia );
		document.querySelectorAll( '[data-mdds-wizard]' ).forEach( initWizard );
	} );
}() );
