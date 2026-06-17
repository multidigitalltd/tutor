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
	/* Video: clean facade + fully custom YouTube player (no YT chrome)   */
	/* ------------------------------------------------------------------ */
	var ytApiPromise = null;

	function loadYouTubeApi() {
		if ( window.YT && window.YT.Player ) {
			return Promise.resolve();
		}
		if ( ytApiPromise ) {
			return ytApiPromise;
		}
		ytApiPromise = new Promise( function ( resolve ) {
			var prev = window.onYouTubeIframeAPIReady;
			window.onYouTubeIframeAPIReady = function () {
				if ( typeof prev === 'function' ) {
					prev();
				}
				resolve();
			};
			var tag = document.createElement( 'script' );
			tag.src = 'https://www.youtube.com/iframe_api';
			document.head.appendChild( tag );
		} );
		return ytApiPromise;
	}

	/**
	 * Format seconds as m:ss.
	 *
	 * @param {number} s Seconds.
	 * @return {string}
	 */
	function clock( s ) {
		s = Math.max( 0, Math.floor( s || 0 ) );
		var m = Math.floor( s / 60 );
		var r = s % 60;
		return m + ':' + ( r < 10 ? '0' : '' ) + r;
	}

	function buildButton( cls, label ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = cls;
		b.setAttribute( 'aria-label', label );
		return b;
	}

	/**
	 * Mount a custom-controls YouTube player into a facade wrapper.
	 *
	 * @param {HTMLElement} wrap  Facade wrapper.
	 * @param {string}      id    YouTube video ID.
	 * @param {string}      label Accessible label.
	 */
	function playYouTube( wrap, id, label ) {
		loadYouTubeApi().then( function () {
			wrap.classList.remove( 'mdds-video-facade' );
			wrap.classList.add( 'mdds-yt' );
			wrap.innerHTML = '';

			var host = document.createElement( 'div' );
			host.className = 'mdds-yt-host';
			wrap.appendChild( host );

			// Click layer: toggles play/pause and blocks the YouTube context menu.
			var overlay = buildButton( 'mdds-yt-overlay', label || ( i18n.play || 'Play' ) );
			wrap.appendChild( overlay );

			var controls = document.createElement( 'div' );
			controls.className = 'mdds-yt-controls';

			var playBtn = buildButton( 'mdds-yt-btn mdds-yt-toggle', i18n.play || 'Play' );
			var seek = document.createElement( 'input' );
			seek.type = 'range';
			seek.className = 'mdds-yt-seek';
			seek.min = '0';
			seek.max = '100';
			seek.value = '0';
			seek.setAttribute( 'aria-label', i18n.seek || 'Seek' );
			var time = document.createElement( 'span' );
			time.className = 'mdds-yt-time';
			time.textContent = '0:00';
			var muteBtn = buildButton( 'mdds-yt-btn mdds-yt-mute', i18n.mute || 'Mute' );
			var fsBtn = buildButton( 'mdds-yt-btn mdds-yt-fs', i18n.fullscreen || 'Fullscreen' );

			controls.appendChild( playBtn );
			controls.appendChild( time );
			controls.appendChild( seek );
			controls.appendChild( muteBtn );
			controls.appendChild( fsBtn );
			wrap.appendChild( controls );

			// Permanent top mask: hides the YouTube title bar that appears for a
			// few seconds when playback starts (and on any hover).
			var titlemask = document.createElement( 'div' );
			titlemask.className = 'mdds-yt-titlemask';
			titlemask.setAttribute( 'aria-hidden', 'true' );
			wrap.appendChild( titlemask );

			// Opaque cover shown whenever the video is NOT actively rendering
			// (before play, on pause, on end) so YouTube's own screens — the
			// init thumbnail/logo and the pause overlay with suggestions — are
			// never visible. Clicking it resumes playback.
			var cover = document.createElement( 'button' );
			cover.type = 'button';
			cover.className = 'mdds-yt-cover';
			cover.setAttribute( 'aria-label', i18n.play || 'Play' );
			cover.innerHTML = '<span class="mdds-video-play-icon" aria-hidden="true"></span>';
			wrap.appendChild( cover );

			wrap.addEventListener( 'contextmenu', function ( e ) {
				e.preventDefault();
			} );

			var player;
			var timer;
			var seeking = false;
			var hasPlayed = false;

			cover.addEventListener( 'click', function () {
				if ( player ) {
					player.playVideo();
				}
			} );

			function setPlaying( isPlaying ) {
				wrap.classList.toggle( 'is-playing', isPlaying );
				playBtn.setAttribute( 'aria-label', isPlaying ? ( i18n.pause || 'Pause' ) : ( i18n.play || 'Play' ) );
			}

			function tick() {
				if ( ! player || ! player.getDuration || seeking ) {
					return;
				}
				var dur = player.getDuration() || 0;
				var cur = player.getCurrentTime() || 0;
				if ( dur > 0 ) {
					seek.value = String( ( cur / dur ) * 100 );
				}
				time.textContent = clock( cur ) + ' / ' + clock( dur );
			}

			function toggle() {
				if ( ! player ) {
					return;
				}
				var state = player.getPlayerState();
				if ( 1 === state ) {
					// Show our cover immediately so YouTube's pause overlay
					// (title/suggestions) never flashes before the state event.
					wrap.classList.remove( 'is-active' );
					player.pauseVideo();
				} else {
					player.playVideo();
				}
			}

			overlay.addEventListener( 'click', toggle );
			playBtn.addEventListener( 'click', toggle );

			muteBtn.addEventListener( 'click', function () {
				if ( ! player ) {
					return;
				}
				if ( player.isMuted() ) {
					player.unMute();
					wrap.classList.remove( 'is-muted' );
				} else {
					player.mute();
					wrap.classList.add( 'is-muted' );
				}
			} );

			fsBtn.addEventListener( 'click', function () {
				if ( document.fullscreenElement ) {
					document.exitFullscreen();
				} else if ( wrap.requestFullscreen ) {
					wrap.requestFullscreen();
				}
			} );

			seek.addEventListener( 'input', function () {
				seeking = true;
			} );
			seek.addEventListener( 'change', function () {
				if ( player && player.getDuration ) {
					player.seekTo( ( parseFloat( seek.value ) / 100 ) * player.getDuration(), true );
				}
				seeking = false;
			} );

			player = new YT.Player( host, {
				videoId: id,
				playerVars: {
					autoplay: 1,
					controls: 0,
					modestbranding: 1,
					rel: 0,
					disablekb: 1,
					fs: 0,
					playsinline: 1,
					iv_load_policy: 3
				},
				events: {
					onReady: function ( e ) {
						e.target.playVideo();
						timer = window.setInterval( tick, 500 );
					},
					onStateChange: function ( e ) {
						var st = e.data;
						if ( 1 === st ) {
							hasPlayed = true;
						}
						setPlaying( 1 === st );
						// Reveal the video while playing, and during buffering only
						// AFTER playback has already started (so the initial YouTube
						// loading screen stays hidden, but mid-play seeks don't flash
						// our cover). Paused/ended keep the cover up.
						wrap.classList.toggle( 'is-active', 1 === st || ( 3 === st && hasPlayed ) );
						if ( 0 === st && timer ) {
							tick();
						}
					}
				}
			} );
		} ).catch( function () {} );
	}

	function initVideo() {
		document.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '.mdds-video-play' );
			if ( ! btn ) {
				return;
			}
			var wrap = btn.closest( '.mdds-video-facade' );
			if ( ! wrap ) {
				return;
			}
			event.preventDefault();
			var label = btn.getAttribute( 'aria-label' ) || '';

			var ytId = wrap.getAttribute( 'data-mdds-yt' );
			if ( ytId ) {
				playYouTube( wrap, ytId, label );
				return;
			}

			// Other providers (e.g. Vimeo): load the iframe directly.
			var url = wrap.getAttribute( 'data-mdds-video' );
			if ( ! url ) {
				return;
			}
			var iframe = document.createElement( 'iframe' );
			iframe.src = url;
			iframe.title = label;
			iframe.setAttribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' );
			iframe.setAttribute( 'allowfullscreen', 'true' );
			iframe.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
			wrap.classList.remove( 'mdds-video-facade' );
			wrap.innerHTML = '';
			wrap.appendChild( iframe );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                               */
	/* ------------------------------------------------------------------ */
	document.addEventListener( 'DOMContentLoaded', function () {
		// Run each independently so one failure cannot disable the rest.
		[ initVideo, initStepper, initTasks, initComplete, initQuiz, initQA ].forEach( function ( fn ) {
			try {
				fn();
			} catch ( e ) {
				// Fail silently; progressive enhancement.
			}
		} );
	} );
}() );
