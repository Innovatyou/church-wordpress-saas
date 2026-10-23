/**
 * Ministry OS theme interactions (no dependencies).
 */
(function () {
	'use strict';

	var root = document.documentElement;
	root.classList.add('js');

	/* Sticky header shadow / transparent header switch */
	var header = document.querySelector('[data-header]');
	var toTop = document.querySelector('[data-to-top]');
	function onScroll() {
		var y = window.scrollY;
		if (header) { header.classList.toggle('is-scrolled', y > 40); }
		if (toTop) { toTop.classList.toggle('is-visible', y > 600); }
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
	if (toTop) { toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); }

	/* Mobile menu: full screen, sliding sub-menus (header button and bottom tab bar) */
	var drawer = document.querySelector('[data-drawer]');
	var navToggles = document.querySelectorAll('[data-nav-toggle]');
	if (drawer && navToggles.length) {
		var panels = drawer.querySelectorAll('[data-drawer-panel]');
		var drawerRoot = document.getElementById('k-drawer-root');
		var lastFocus = null;
		var closeTimer = null;
		var isOpen = function () { return document.body.classList.contains('is-nav-open'); };
		var focusables = function () {
			var active = drawer.querySelector('[data-drawer-panel].is-active');
			return Array.prototype.filter.call(drawer.querySelectorAll('a[href], button:not([disabled]), input, summary, [tabindex]:not([tabindex="-1"])'), function (el) {
				var panel = el.closest('[data-drawer-panel]');
				return (!panel || panel === active) && !el.hidden && el.offsetParent !== null;
			});
		};
		var showPanel = function (panel, back) {
			var current = drawer.querySelector('[data-drawer-panel].is-active');
			if (!panel || panel === current) { return; }
			panels.forEach(function (p) { p.classList.remove('is-behind'); });
			if (current) {
				current.classList.remove('is-active');
				if (!back) { current.classList.add('is-behind'); }
			}
			panel.classList.add('is-active');
			panel.scrollTop = 0;
			drawer.querySelectorAll('[data-drawer-open]').forEach(function (b) { b.setAttribute('aria-expanded', b.getAttribute('data-drawer-open') === panel.id ? 'true' : 'false'); });
			var first = back ? drawer.querySelector('[data-drawer-open="' + (current ? current.id : '') + '"]') : panel.querySelector('[data-drawer-back]');
			if (first) { setTimeout(function () { first.focus({ preventScroll: true }); }, 50); }
		};
		var setNav = function (open) {
			if (open === isOpen()) { return; }
			clearTimeout(closeTimer);
			document.body.classList.toggle('is-nav-open', open);
			document.documentElement.classList.toggle('k-drawer-open', open);
			navToggles.forEach(function (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); });
			if (open) {
				lastFocus = document.activeElement;
				drawer.hidden = false;
				// Always start on the main menu.
				panels.forEach(function (p) { p.classList.remove('is-active', 'is-behind'); });
				if (drawerRoot) { drawerRoot.classList.add('is-active'); drawerRoot.scrollTop = 0; }
				requestAnimationFrame(function () { requestAnimationFrame(function () { drawer.classList.add('is-open'); }); });
				var close = drawer.querySelector('[data-drawer-close]');
				if (close) { setTimeout(function () { close.focus({ preventScroll: true }); }, 60); }
			} else {
				drawer.classList.remove('is-open');
				closeTimer = setTimeout(function () { drawer.hidden = true; }, 300);
				if (lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
			}
		};
		navToggles.forEach(function (t) {
			t.addEventListener('click', function () { setNav(!isOpen()); });
		});
		drawer.addEventListener('click', function (e) {
			var openBtn = e.target.closest('[data-drawer-open]');
			if (openBtn) { showPanel(document.getElementById(openBtn.getAttribute('data-drawer-open')), false); return; }
			var back = e.target.closest('[data-drawer-back]');
			if (back) {
				var panel = back.closest('[data-drawer-panel]');
				showPanel(document.getElementById(panel.getAttribute('data-parent')) || drawerRoot, true);
				return;
			}
			if (e.target.closest('[data-drawer-close]')) { setNav(false); return; }
			// Links to a section of the current page: close so the section is visible.
			var a = e.target.closest('a[href*="#"]');
			if (a && a.pathname === location.pathname && a.hostname === location.hostname) { setNav(false); }
		});
		document.addEventListener('keydown', function (e) {
			if (!isOpen()) { return; }
			if (e.key === 'Escape') {
				var active = drawer.querySelector('[data-drawer-panel].is-active');
				if (active && active !== drawerRoot) { showPanel(document.getElementById(active.getAttribute('data-parent')) || drawerRoot, true); } else { setNav(false); }
				return;
			}
			if (e.key === 'Tab') {
				// Keep keyboard focus inside the open menu.
				var list = focusables();
				if (!list.length) { return; }
				var first = list[0];
				var last = list[list.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } else if (!drawer.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
			}
		});
		// Back/forward cache: never come back to a page with the menu still open.
		window.addEventListener('pageshow', function (e) { if (e.persisted && isOpen()) { setNav(false); } });
	}

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

	/* Banner text animations that need the title split into words or letters, or typed. */
	document.querySelectorAll('[data-hero-anim]').forEach(function (box) {
		var kind = box.getAttribute('data-hero-anim');
		var title = box.querySelector('.k-hero__title');
		if (!title || reduceMotion || ['words', 'letters', 'typewriter'].indexOf(kind) < 0) { return; }
		var text = title.textContent.trim();
		var speed = parseFloat(getComputedStyle(box).getPropertyValue('--k-as')) || 1;
		title.setAttribute('aria-label', text);
		title.textContent = '';
		var words = text.split(/\s+/);
		var holder = document.createElement('span');
		holder.setAttribute('aria-hidden', 'true');
		title.appendChild(holder);
		if (kind === 'words') {
			words.forEach(function (w, i) {
				var s = document.createElement('span');
				s.className = 'k-anim-w';
				s.style.setProperty('--i', i);
				s.textContent = w;
				holder.appendChild(s);
				if (i < words.length - 1) { holder.appendChild(document.createTextNode(' ')); }
			});
			box.style.setProperty('--k-after', ((words.length * 0.09 + 0.5) * speed) + 's');
		} else if (kind === 'letters') {
			var n = 0;
			words.forEach(function (w, wi) {
				var word = document.createElement('span');
				word.className = 'k-anim-word';
				Array.prototype.forEach.call(w, function (ch) {
					var s = document.createElement('span');
					s.className = 'k-anim-l';
					s.style.setProperty('--i', n++);
					s.textContent = ch;
					word.appendChild(s);
				});
				holder.appendChild(word);
				if (wi < words.length - 1) { holder.appendChild(document.createTextNode(' ')); }
			});
			box.style.setProperty('--k-after', ((n * 0.035 + 0.5) * speed) + 's');
		} else {
			var typed = document.createTextNode('');
			var caret = document.createElement('span');
			caret.className = 'k-anim-caret';
			// The untyped rest stays in place, invisible, so the banner doesn't jump while typing.
			var rest = document.createElement('span');
			rest.style.visibility = 'hidden';
			rest.textContent = text;
			holder.appendChild(typed);
			holder.appendChild(caret);
			holder.appendChild(rest);
			var step = 70 * speed;
			box.style.setProperty('--k-after', ((text.length * step + 400) / 1000) + 's');
			var i = 0;
			setTimeout(function type() {
				typed.nodeValue = text.slice(0, ++i);
				rest.textContent = text.slice(i);
				if (i < text.length) { setTimeout(type, step); } else { caret.classList.add('is-done'); }
			}, 350 * speed);
		}
	});

	/* 3D tilt cards */
	if (document.body.classList.contains('k-3d') && !reduceMotion && finePointer) {
		document.querySelectorAll('[data-tilt]').forEach(function (card) {
			card.addEventListener('pointermove', function (e) {
				var r = card.getBoundingClientRect();
				var x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
				card.classList.add('is-tilting');
				card.style.transform = 'rotateX(' + ((0.5 - y) * 10).toFixed(2) + 'deg) rotateY(' + ((x - 0.5) * 12).toFixed(2) + 'deg) translateZ(10px)';
				card.style.setProperty('--mx', (x * 100) + '%');
				card.style.setProperty('--my', (y * 100) + '%');
			});
			card.addEventListener('pointerleave', function () { card.classList.remove('is-tilting'); card.style.transform = ''; });
		});
	}

	/* Hero parallax depth */
	var scene = document.querySelector('[data-parallax]');
	if (scene && document.body.classList.contains('k-3d') && !reduceMotion) {
		var layers = scene.children;
		var move = function (px, py) {
			for (var i = 0; i < layers.length; i++) {
				var depth = (i + 1) * 6;
				layers[i].style.translate = (px * depth).toFixed(1) + 'px ' + (py * depth).toFixed(1) + 'px';
			}
		};
		if (finePointer) {
			scene.parentElement.addEventListener('pointermove', function (e) {
				move(e.clientX / window.innerWidth - 0.5, e.clientY / window.innerHeight - 0.5);
			});
		}
		window.addEventListener('scroll', function () { scene.style.transform = 'translateY(' + (window.scrollY * 0.25).toFixed(1) + 'px)'; }, { passive: true });
	}

	/* WhatsApp chat widget */
	var wa = document.querySelector('[data-wa]');
	if (wa) {
		var waPanel = wa.querySelector('.k-wa__panel'), waBtn = wa.querySelector('.k-wa__btn');
		var setWa = function (open) { waPanel.hidden = !open; waBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); wa.classList.toggle('is-open', open); };
		wa.addEventListener('click', function (e) { if (e.target.closest('[data-wa-toggle]')) { setWa(waPanel.hidden); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !waPanel.hidden) { setWa(false); waBtn.focus(); } });
		document.addEventListener('click', function (e) { if (!waPanel.hidden && !e.target.closest('[data-wa]')) { setWa(false); } });
		// Shrink the label to an icon after the visitor starts scrolling.
		window.addEventListener('scroll', function () { wa.classList.toggle('is-compact', window.scrollY > 400); }, { passive: true });
	}

	/* Visitor colour palette */
	var picker = document.querySelector('[data-palette-picker]');
	if (picker) {
		var toggle = picker.querySelector('.k-palette__toggle');
		var panel = picker.querySelector('.k-palette__panel');
		var mark = function () {
			var cur = root.getAttribute('data-palette') || '';
			document.querySelectorAll('.k-palette [data-palette], .k-nav__palette [data-palette]').forEach(function (b) { b.classList.toggle('is-active', b.dataset.palette === cur && cur !== ''); });
		};
		toggle.addEventListener('click', function () {
			panel.hidden = !panel.hidden;
			toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
		});
		var clearCustom = function () { ['--k-primary', '--k-accent', '--k-on-primary'].forEach(function (v) { root.style.removeProperty(v); }); };
		document.addEventListener('click', function (e) {
			var b = e.target.closest('.k-palette [data-palette], .k-nav__palette [data-palette]');
			if (!b) { return; }
			clearCustom();
			if (b.dataset.palette) { root.setAttribute('data-palette', b.dataset.palette); } else { root.removeAttribute('data-palette'); }
			try { b.dataset.palette ? localStorage.setItem('kingdom-palette', b.dataset.palette) : localStorage.removeItem('kingdom-palette'); } catch (err) { /* private mode */ }
			mark();
		});
		/* Custom colours: any colour the visitor likes, with readable button text. */
		var onColour = function (hex) {
			var n = parseInt(hex.slice(1), 16), c = [n >> 16 & 255, n >> 8 & 255, n & 255].map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
			var l = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
			return 1.05 / (l + 0.05) >= (l + 0.05) / 0.05 * 0.6 ? '#ffffff' : '#16121c';
		};
		var applyCustom = function () {
			var p = document.querySelector('[data-custom-colour="primary"]').value, a = document.querySelector('[data-custom-colour="accent"]').value;
			root.setAttribute('data-palette', 'custom');
			root.style.setProperty('--k-primary', p); root.style.setProperty('--k-on-primary', onColour(p)); root.style.setProperty('--k-accent', a);
			try { localStorage.setItem('kingdom-palette', 'custom'); localStorage.setItem('kingdom-palette-custom', JSON.stringify({ primary: p, accent: a, on: onColour(p) })); } catch (err) { /* private mode */ }
			mark();
		};
		document.querySelectorAll('[data-custom-colour]').forEach(function (input) {
			input.addEventListener('input', function () {
				document.querySelectorAll('[data-custom-colour="' + input.dataset.customColour + '"]').forEach(function (o) { if (o !== input) { o.value = input.value; } });
				applyCustom();
			});
		});
		try {
			var saved = JSON.parse(localStorage.getItem('kingdom-palette-custom') || 'null');
			if (saved && root.getAttribute('data-palette') === 'custom') {
				document.querySelectorAll('[data-custom-colour="primary"]').forEach(function (i) { i.value = saved.primary; });
				document.querySelectorAll('[data-custom-colour="accent"]').forEach(function (i) { i.value = saved.accent; });
			}
		} catch (err) { /* private mode */ }
		document.addEventListener('click', function (e) { if (!panel.hidden && !e.target.closest('[data-palette-picker]')) { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); } });
		mark();
	}

	/* Search panel */
	var searchToggle = document.querySelector('[data-search-toggle]');
	var searchPanel = document.querySelector('[data-search-panel]');
	if (searchToggle && searchPanel) {
		searchToggle.addEventListener('click', function () {
			searchPanel.hidden = !searchPanel.hidden;
			searchToggle.setAttribute('aria-expanded', searchPanel.hidden ? 'false' : 'true');
			if (!searchPanel.hidden) { var i = searchPanel.querySelector('input'); if (i) { i.focus(); } }
		});
	}

	/* Dark mode */
	var themeToggle = document.querySelector('[data-theme-toggle]');
	if (themeToggle) {
		themeToggle.addEventListener('click', function () {
			var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			root.setAttribute('data-theme', next);
			try { localStorage.setItem('kingdom-scheme', next); } catch (e) { /* private mode */ }
		});
	}

	/* Reveal on scroll */
	var reveal = document.querySelectorAll('[data-reveal]');
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { entry.target.classList.add('is-revealed'); io.unobserve(entry.target); }
			});
		}, { rootMargin: '0px 0px -60px 0px' });
		reveal.forEach(function (el) { io.observe(el); });
	} else {
		reveal.forEach(function (el) { el.classList.add('is-revealed'); });
	}

	/* Animated counters */
	var counters = document.querySelectorAll('[data-count]');
	if ('IntersectionObserver' in window && counters.length) {
		var co = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				var el = entry.target, target = parseInt(el.dataset.count, 10) || 0, start = null;
				co.unobserve(el);
				var step = function (ts) {
					if (!start) { start = ts; }
					var p = Math.min(1, (ts - start) / 1600);
					el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
					if (p < 1) { requestAnimationFrame(step); }
				};
				requestAnimationFrame(step);
			});
		}, { threshold: .4 });
		counters.forEach(function (c) { co.observe(c); });
	}

	/* YouTube video modal */
	var modal = document.querySelector('[data-video-modal]');
	if (modal) {
		var body = modal.querySelector('.k-modal__body');
		var modalOpener = null;
		var closeModal = function () {
			modal.hidden = true;
			body.innerHTML = '';
			if (modalOpener && modalOpener.focus) { modalOpener.focus(); }
		};
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-video], [data-video-src]');
			// Only real video buttons (with a video) open the player.
			if (trigger && !(trigger.dataset.videoSrc || /^[A-Za-z0-9_-]{11}$/.test(trigger.dataset.video || ''))) { trigger = null; }
			if (trigger) {
				e.preventDefault();
				var iframe = document.createElement('iframe');
				// data-video = YouTube ID; data-video-src = any embed URL (e.g. Vimeo).
				iframe.src = trigger.dataset.videoSrc || 'https://www.youtube.com/embed/' + encodeURIComponent(trigger.dataset.video) + '?autoplay=1&rel=0';
				iframe.allow = 'autoplay; encrypted-media; picture-in-picture; web-share';
				iframe.allowFullscreen = true;
				iframe.referrerPolicy = 'strict-origin-when-cross-origin'; // YouTube refuses embeds that do not say which site they are on
				body.innerHTML = '';
				body.appendChild(iframe);
				modalOpener = trigger;
				modal.hidden = false;
				var closeBtn = modal.querySelector('[data-modal-close]');
				if (closeBtn) { closeBtn.focus(); }
			}
			if (e.target === modal || e.target.closest('[data-modal-close]')) { closeModal(); }
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { closeModal(); } });
	}

	/* YouTube live: switch to the stream the moment it starts (no page refresh) */
	document.querySelectorAll('[data-ytlive]').forEach(function (sec) {
		var label = sec.querySelector('[data-ytlive-label]');
		var count = sec.querySelector('[data-ytlive-countdown]');
		var player = sec.querySelector('[data-ytlive-player]');
		var chat = sec.querySelector('[data-ytlive-chat]');
		var watch = sec.querySelector('[data-ytlive-watch]');
		var grid = sec.querySelector('.k-ytlive__grid');
		function frame(src, title) {
			var f = document.createElement('iframe');
			f.src = src; f.title = title || 'YouTube'; f.allowFullscreen = true;
			f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
			f.referrerPolicy = 'strict-origin-when-cross-origin';
			return f;
		}
		function tick() {
			var starts = parseInt(sec.dataset.starts, 10) * 1000;
			if (!count) { return; }
			if (sec.dataset.state !== 'upcoming' || !starts || starts < Date.now()) { count.hidden = true; return; }
			var s = Math.floor((starts - Date.now()) / 1000), d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
			count.textContent = (d ? d + 'd ' : '') + (d || h ? h + 'h ' : '') + m + 'm ' + (d ? '' : (s % 60) + 's');
			count.hidden = false;
		}
		function apply(s) {
			if (!s || !s.state || s.state === 'none') { return; }
			var changed = s.state !== sec.dataset.state || (s.video_id || '') !== sec.dataset.ytliveVideo;
			sec.dataset.state = s.state; sec.dataset.starts = s.starts_at || 0;
			sec.className = sec.className.replace(/\bis-(live|upcoming|offline)\b/g, '') + ' is-' + s.state;
			if (label) { label.textContent = s.label; }
			if (watch && s.watch) { watch.href = s.watch; }
			if (changed) {
				player.innerHTML = '';
				if (s.embed) { player.appendChild(frame(s.embed, s.title)); }
				if (chat) {
					chat.innerHTML = '';
					chat.hidden = !s.chat;
					if (s.chat) { chat.appendChild(frame(s.chat, 'Live chat')); }
					grid.classList.toggle('has-chat', !!s.chat);
				}
				sec.dataset.ytliveVideo = s.video_id || '';
			}
			tick();
		}
		function poll() {
			if (document.hidden) { return; }
			fetch(sec.dataset.ytlive, { headers: { Accept: 'application/json' } })
				.then(function (r) { return r.ok ? r.json() : null; })
				.then(apply)
				.catch(function () {});
		}
		tick();
		setInterval(tick, 1000);
		setInterval(poll, 60000);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
	});

	/* Uploaded banner video: always playing, always muted (picks the phone version on small screens). */
	document.querySelectorAll('[data-k-bgvideo]').forEach(function (video) {
		video.muted = true;
		// Phone version: not every browser honours media="" on <source>, so pick it here too.
		var small = video.querySelector('source[media]');
		if (small && window.matchMedia) {
			var want = window.matchMedia(small.getAttribute('media')).matches ? small.src : video.querySelector('source:not([media])').src;
			if (video.currentSrc !== want) { video.src = want; }
		}
		function play() {
			if (video.paused) { var p = video.play(); if (p && p.catch) { p.catch(function () {}); } }
		}
		play();
		// Browsers may pause it in a background tab or on low power; start it again.
		video.addEventListener('pause', function () { setTimeout(play, 300); });
		document.addEventListener('visibilitychange', function () { if (!document.hidden) { play(); } });
		// Phones that block autoplay start it on the first touch.
		document.addEventListener('touchstart', play, { once: true, passive: true });
	});

	/* Filter selects navigate to the chosen archive */
	document.querySelectorAll('[data-filter-select]').forEach(function (sel) {
		sel.addEventListener('change', function () { if (sel.value) { window.location.href = sel.value; } });
	});

	/* Audio playback speed */
	document.querySelectorAll('[data-audio-speed]').forEach(function (sel) {
		var audio = sel.closest('.k-audio').querySelector('audio');
		sel.addEventListener('change', function () { audio.playbackRate = parseFloat(sel.value); });
	});

	/* Share fallback when the plugin script is not present */
	if (!window.KCS) {
		document.addEventListener('click', function (e) {
			var b = e.target.closest('[data-kcs-share]');
			if (!b) { return; }
			if (navigator.share) { navigator.share({ title: document.title, url: location.href }).catch(function () {}); }
			else if (navigator.clipboard) { navigator.clipboard.writeText(location.href); b.textContent = '✓'; }
		});
	}
})();
