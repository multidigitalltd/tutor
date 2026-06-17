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
	/* Task answers — one submit for the whole chapter questionnaire        */
	/* ------------------------------------------------------------------ */
	function initTasks() {
		document.querySelectorAll( '[data-mdds-tasks-form]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var fb     = form.querySelector( '[data-mdds-tasks-feedback]' );
				var submit = form.querySelector( 'button[type="submit"]' );
				var body   = new FormData( form );
				body.append( 'chapter_id', form.getAttribute( 'data-chapter' ) );

				if ( submit ) {
					submit.disabled = true;
				}
				feedback( fb, i18n.saving || 'Saving…', 'pending' );

				post( 'mdds_save_answers', body ).then( function ( res ) {
					if ( submit ) {
						submit.disabled = false;
					}
					if ( res && res.success ) {
						feedback( fb, ( res.data && res.data.message ) || i18n.saved, 'success' );
						renderAllFiles( form, res.data && res.data.files );
						form.querySelectorAll( 'input[type="file"]' ).forEach( function ( input ) {
							input.value = '';
						} );
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
	 * Re-render every task's uploaded-files list from the bulk response.
	 *
	 * @param {HTMLElement} form         Chapter tasks form.
	 * @param {Object}      filesByIndex Map of task index -> file descriptors.
	 */
	function renderAllFiles( form, filesByIndex ) {
		if ( ! filesByIndex ) {
			return;
		}
		Object.keys( filesByIndex ).forEach( function ( index ) {
			var task = form.querySelector( '[data-mdds-task][data-index="' + index + '"]' );
			if ( task ) {
				renderFiles( task, filesByIndex[ index ] );
			}
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
	/* Chapter stepper (LMS-style one-chapter-at-a-time navigation)       */
	/* ------------------------------------------------------------------ */
	var stepper = null;

	/**
	 * Whether the user prefers reduced motion.
	 *
	 * @return {boolean}
	 */
	function reducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function initStepper() {
		var container = document.querySelector( '[data-mdds-stepper]' );
		if ( ! container ) {
			return;
		}

		var steps = Array.prototype.slice.call( container.querySelectorAll( '[data-mdds-panel]' ) );
		var links = Array.prototype.slice.call( document.querySelectorAll( '[data-mdds-step]' ) );
		var nav   = document.querySelector( '[data-mdds-step-nav]' );
		if ( ! steps.length ) {
			return;
		}

		var current = parseInt( container.getAttribute( 'data-current' ), 10 ) || 0;

		function unlocked( index ) {
			return !! steps[ index ] && steps[ index ].getAttribute( 'data-locked' ) !== '1';
		}

		function show( index, focusStep ) {
			if ( index < 0 || index >= steps.length || ! unlocked( index ) ) {
				return;
			}
			current = index;

			steps.forEach( function ( step, i ) {
				var active = i === index;
				step.classList.toggle( 'is-active', active );
				if ( active ) {
					step.removeAttribute( 'hidden' );
				} else {
					step.setAttribute( 'hidden', 'hidden' );
				}
			} );

			links.forEach( function ( link ) {
				var li        = link.closest( '.mdds-step' );
				var isCurrent = parseInt( link.getAttribute( 'data-mdds-step' ), 10 ) === index;
				link.setAttribute( 'aria-current', isCurrent ? 'step' : 'false' );
				if ( li ) {
					li.classList.toggle( 'is-current', isCurrent );
				}
			} );

			if ( false !== focusStep && steps[ index ].focus ) {
				steps[ index ].focus( { preventScroll: true } );
			}
			var anchor = nav || steps[ index ];
			if ( anchor && anchor.scrollIntoView ) {
				anchor.scrollIntoView( { behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' } );
			}
		}

		function next() {
			for ( var i = current + 1; i < steps.length; i++ ) {
				if ( unlocked( i ) ) {
					show( i );
					return;
				}
			}
		}

		function prev() {
			for ( var i = current - 1; i >= 0; i-- ) {
				if ( unlocked( i ) ) {
					show( i );
					return;
				}
			}
		}

		links.forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				show( parseInt( link.getAttribute( 'data-mdds-step' ), 10 ) );
			} );
		} );

		container.querySelectorAll( '[data-mdds-next]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', next );
		} );

		container.querySelectorAll( '[data-mdds-prev]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', prev );
		} );

		container.classList.add( 'is-enhanced' );
		stepper = {
			show: show,
			next: next,
			unlocked: unlocked,
			steps: steps,
			links: links
		};

		// Render the initial step without stealing focus on page load.
		show( current, false );
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
					if ( ! res || ! res.success ) {
						return;
					}

					button.setAttribute( 'aria-pressed', nextState ? 'true' : 'false' );
					button.textContent = nextState ? ( i18n.completed || 'Completed' ) : ( i18n.markDone || 'Mark complete' );

					var section = button.closest( '[data-mdds-chapter]' );
					if ( section ) {
						section.classList.toggle( 'is-completed', nextState );
						section.setAttribute( 'data-completed', nextState ? '1' : '0' );
						var status = section.querySelector( '[data-mdds-chapter-status]' );
						if ( status ) {
							status.hidden = ! nextState;
						}
						updateStepStatus( section.getAttribute( 'data-index' ), nextState );
					}

					updateProgress( res.data && res.data.progress );

					if ( ! nextState ) {
						return; // Un-marking: stay put.
					}

					var sequential = button.getAttribute( 'data-sequential' ) === '1';
					var isLast     = button.getAttribute( 'data-last' ) === '1';

					// Sequential mode may need to reveal freshly-dripped content
					// (next chapter or the summary quiz) that was withheld server-side.
					if ( sequential ) {
						var index    = section ? parseInt( section.getAttribute( 'data-index' ), 10 ) : -1;
						var nextStep = stepper && stepper.steps[ index + 1 ];
						var revealNext = nextStep && nextStep.getAttribute( 'data-locked' ) === '1';
						var revealQuiz = !! ( res.data && res.data.all_complete ) && document.querySelector( '.mdds-quiz-locked' );
						if ( revealNext || revealQuiz ) {
							window.location.reload();
							return;
						}
					}

					if ( ! isLast && stepper ) {
						stepper.next();
					}
				} ).catch( function () {
					button.disabled = false;
				} );
			} );
		} );
	}

	/**
	 * Reflect a chapter's completion state in the step navigation.
	 *
	 * @param {string|number} index     Chapter index.
	 * @param {boolean}       completed Completion state.
	 */
	function updateStepStatus( index, completed ) {
		if ( null === index || 'undefined' === typeof index ) {
			return;
		}
		var link = document.querySelector( '[data-mdds-step="' + index + '"]' );
		var li   = link && link.closest( '.mdds-step' );
		if ( li ) {
			li.classList.toggle( 'is-completed', !! completed );
		}
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

	/* ------------------------------------------------------------------ */
	/* Q&A with the instructor                                            */
	/* ------------------------------------------------------------------ */
	function initQA() {
		document.querySelectorAll( '[data-mdds-qa-form]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var fb     = form.querySelector( '[data-mdds-qa-feedback]' );
				var submit = form.querySelector( 'button[type="submit"]' );
				var field  = form.querySelector( 'textarea[name="message"]' );
				var message = field ? field.value.trim() : '';
				if ( '' === message ) {
					return;
				}

				if ( submit ) {
					submit.disabled = true;
				}
				feedback( fb, i18n.saving || 'Saving…', 'pending' );

				post( 'mdds_qa_post', {
					message: message,
					parent: form.getAttribute( 'data-parent' ) || '0'
				} ).then( function ( res ) {
					if ( res && res.success ) {
						feedback( fb, ( res.data && res.data.message ) || i18n.saved, 'success' );
						window.location.reload();
					} else {
						if ( submit ) {
							submit.disabled = false;
						}
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

	/* ------------------------------------------------------------------ */
	/* Click-to-play video facade                                         */
	/* ------------------------------------------------------------------ */
	function initVideo() {
		document.querySelectorAll( '[data-mdds-video]' ).forEach( function ( wrap ) {
			var btn = wrap.querySelector( '.mdds-video-play' );
			if ( ! btn ) {
				return;
			}
			btn.addEventListener( 'click', function () {
				var url = wrap.getAttribute( 'data-mdds-video' );
				if ( ! url ) {
					return;
				}
				var iframe = document.createElement( 'iframe' );
				iframe.src = url;
				iframe.title = btn.getAttribute( 'aria-label' ) || '';
				iframe.setAttribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' );
				iframe.setAttribute( 'allowfullscreen', '' );
				iframe.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
				wrap.innerHTML = '';
				wrap.classList.remove( 'mdds-video-facade' );
				wrap.appendChild( iframe );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initStepper();
		initVideo();
		initTasks();
		initComplete();
		initQuiz();
		initQA();
	} );
}() );
