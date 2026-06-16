/**
 * DeSchool front-end scripts.
 * Vanilla JS, no jQuery. Progressive enhancement over working markup.
 *
 * @package MultiDigital\DeSchool
 */
( function () {
	'use strict';

	if ( typeof window.mddsData === 'undefined' ) {
		return;
	}

	var data = window.mddsData;
	var i18n = data.i18n || {};

	/**
	 * Perform an AJAX POST to admin-ajax.php.
	 *
	 * @param {string}          action Action name (without mdds_ prefix is added).
	 * @param {FormData|Object} body   Payload.
	 * @return {Promise<Object>}
	 */
	function post( action, body ) {
		var form;
		if ( body instanceof FormData ) {
			form = body;
		} else {
			form = new FormData();
			Object.keys( body ).forEach( function ( key ) {
				form.append( key, body[ key ] );
			} );
		}
		form.append( 'action', action );
		form.append( 'nonce', data.nonce );
		form.append( 'unit_id', data.unitId );

		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: form
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Announce a message in a feedback element.
	 *
	 * @param {HTMLElement} el      Feedback element.
	 * @param {string}      message Text.
	 * @param {string}      state   State class suffix.
	 */
	function feedback( el, message, state ) {
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.className = 'mdds-task-feedback is-' + state;
	}

	/* ------------------------------------------------------------------ */
	/* Task answer saving                                                 */
	/* ------------------------------------------------------------------ */
	function initTasks() {
		document.querySelectorAll( '[data-mdds-task]' ).forEach( function ( task ) {
			var form = task.querySelector( '[data-mdds-task-form]' );
			if ( ! form ) {
				return;
			}

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var fb     = task.querySelector( '[data-mdds-task-feedback]' );
				var submit = form.querySelector( 'button[type="submit"]' );
				var body   = new FormData( form );
				body.append( 'chapter_id', task.getAttribute( 'data-chapter' ) );
				body.append( 'task_index', task.getAttribute( 'data-index' ) );

				if ( submit ) {
					submit.disabled = true;
				}
				feedback( fb, i18n.saving || 'Saving…', 'pending' );

				post( 'mdds_save_answer', body ).then( function ( res ) {
					if ( submit ) {
						submit.disabled = false;
					}
					if ( res && res.success ) {
						feedback( fb, ( res.data && res.data.message ) || i18n.saved, 'success' );
						renderFiles( task, res.data && res.data.files );
						var fileInput = form.querySelector( 'input[type="file"]' );
						if ( fileInput ) {
							fileInput.value = '';
						}
					} else {
						feedback( fb, ( res && res.data && res.data.message ) || i18n.error, 'error' );
					}
				} ).catch( function () {
					if ( submit ) {
						submit.disabled = false;
					}
					feedback( fb, i18n.error, 'error' );
				} );
			} );
		} );
	}

	/**
	 * Re-render the uploaded-files list for a task.
	 *
	 * @param {HTMLElement} task  Task element.
	 * @param {Array}       files File descriptors.
	 */
	function renderFiles( task, files ) {
		var list = task.querySelector( '[data-mdds-task-files]' );
		if ( ! list || ! Array.isArray( files ) ) {
			return;
		}
		list.innerHTML = '';
		files.forEach( function ( file ) {
			var li = document.createElement( 'li' );
			var a  = document.createElement( 'a' );
			a.href = file.url;
			a.target = '_blank';
			a.rel = 'noopener';
			a.textContent = file.name;
			li.appendChild( a );
			list.appendChild( li );
		} );
		list.hidden = files.length === 0;
	}

	/* ------------------------------------------------------------------ */
	/* Mark chapter complete                                              */
	/* ------------------------------------------------------------------ */
	function initComplete() {
		document.querySelectorAll( '[data-mdds-mark-complete]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var pressed   = button.getAttribute( 'aria-pressed' ) === 'true';
				var nextState = ! pressed;
				button.disabled = true;

				post( 'mdds_mark_complete', {
					chapter_id: button.getAttribute( 'data-chapter' ),
					completed: nextState ? '1' : '0'
				} ).then( function ( res ) {
					button.disabled = false;
					if ( res && res.success ) {
						button.setAttribute( 'aria-pressed', nextState ? 'true' : 'false' );
						button.textContent = nextState ? ( i18n.completed || 'Completed' ) : ( i18n.markDone || 'Mark complete' );
						var section = button.closest( '.mdds-chapter' );
						if ( section ) {
							section.classList.toggle( 'is-completed', nextState );
							var status = section.querySelector( '[data-mdds-chapter-status]' );
							if ( status ) {
								status.hidden = ! nextState;
							}
						}
						updateProgress( res.data && res.data.progress );
					} else {
						button.disabled = false;
					}
				} ).catch( function () {
					button.disabled = false;
				} );
			} );
		} );
	}

	/**
	 * Update the progress bar.
	 *
	 * @param {Object} progress Progress payload.
	 */
	function updateProgress( progress ) {
		if ( ! progress ) {
			return;
		}
		var wrap = document.querySelector( '[data-mdds-progress]' );
		if ( ! wrap ) {
			return;
		}
		var percent = progress.total > 0 ? Math.round( ( progress.completed / progress.total ) * 100 ) : 0;
		var bar     = wrap.querySelector( '.mdds-progress-bar' );
		var fill    = wrap.querySelector( '.mdds-progress-fill' );
		var text    = wrap.querySelector( '[data-mdds-progress-text]' );
		if ( fill ) {
			fill.style.width = percent + '%';
		}
		if ( bar ) {
			bar.setAttribute( 'aria-valuenow', String( percent ) );
		}
		if ( text ) {
			text.textContent = progress.completed + ' / ' + progress.total;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Quiz                                                               */
	/* ------------------------------------------------------------------ */
	function initQuiz() {
		var quiz = document.querySelector( '[data-mdds-quiz]' );
		if ( ! quiz ) {
			return;
		}

		var form    = quiz.querySelector( '[data-mdds-quiz-form]' );
		var result  = quiz.querySelector( '[data-mdds-quiz-result]' );
		var fb       = quiz.querySelector( '[data-mdds-quiz-feedback]' );
		var retryBtn = quiz.querySelector( '[data-mdds-quiz-retry]' );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var body = new FormData( form );
				var submit = form.querySelector( 'button[type="submit"]' );
				if ( submit ) {
					submit.disabled = true;
				}
				if ( fb ) {
					fb.textContent = i18n.saving || '';
				}

				post( 'mdds_submit_quiz', body ).then( function ( res ) {
					if ( submit ) {
						submit.disabled = false;
					}
					if ( res && res.success ) {
						showResult( quiz, result, res.data );
						markReview( quiz, res.data.review );
						if ( fb ) {
							fb.textContent = '';
						}
						if ( retryBtn ) {
							retryBtn.hidden = false;
						}
						form.hidden = true;
						if ( result ) {
							result.focus && result.focus();
						}
					} else if ( fb ) {
						fb.textContent = ( res && res.data && res.data.message ) || i18n.error;
					}
				} ).catch( function () {
					if ( submit ) {
						submit.disabled = false;
					}
					if ( fb ) {
						fb.textContent = i18n.error;
					}
				} );
			} );
		}

		if ( retryBtn ) {
			retryBtn.addEventListener( 'click', function () {
				if ( form ) {
					form.reset();
					form.hidden = false;
					clearReview( quiz );
				}
				if ( result ) {
					result.hidden = true;
				}
				retryBtn.hidden = true;
			} );
		}
	}

	/**
	 * Render the quiz score.
	 *
	 * @param {HTMLElement} quiz   Quiz container.
	 * @param {HTMLElement} result Result container.
	 * @param {Object}      d      Result data.
	 */
	function showResult( quiz, result, d ) {
		if ( ! result ) {
			return;
		}
		result.hidden = false;
		result.innerHTML = '';
		var p = document.createElement( 'p' );
		p.className = 'mdds-quiz-score ' + ( d.passed ? 'is-pass' : 'is-fail' );
		p.textContent = ( d.score + '% (' + d.correct + '/' + d.total + ')' );
		result.appendChild( p );
	}

	/**
	 * Highlight correct/incorrect answers when review data is returned.
	 *
	 * @param {HTMLElement} quiz   Quiz container.
	 * @param {Object}      review Review map.
	 */
	function markReview( quiz, review ) {
		if ( ! review ) {
			return;
		}
		Object.keys( review ).forEach( function ( q ) {
			var item = quiz.querySelector( '.mdds-quiz-question[data-question="' + q + '"]' );
			if ( ! item ) {
				return;
			}
			var info = review[ q ];
			var correct = item.querySelector( '.mdds-quiz-answer[data-answer="' + info.correct + '"]' );
			if ( correct ) {
				correct.classList.add( 'is-correct' );
			}
			if ( ! info.is_correct && info.selected >= 0 ) {
				var chosen = item.querySelector( '.mdds-quiz-answer[data-answer="' + info.selected + '"]' );
				if ( chosen ) {
					chosen.classList.add( 'is-wrong' );
				}
			}
		} );
	}

	/**
	 * Clear review highlighting.
	 *
	 * @param {HTMLElement} quiz Quiz container.
	 */
	function clearReview( quiz ) {
		quiz.querySelectorAll( '.mdds-quiz-answer' ).forEach( function ( el ) {
			el.classList.remove( 'is-correct', 'is-wrong' );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initTasks();
		initComplete();
		initQuiz();
	} );
}() );
