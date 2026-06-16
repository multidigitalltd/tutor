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

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-mdds-repeater]' ).forEach( initRepeater );
		document.querySelectorAll( '[data-mdds-media]' ).forEach( initMedia );
	} );
}() );
