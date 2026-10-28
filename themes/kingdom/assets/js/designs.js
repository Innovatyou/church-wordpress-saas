/**
 * Ministry OS website designs: interactive effects (no dependencies).
 *
 * The active design is <html data-design="…">. Each design turns on a few effects;
 * the look itself is in assets/css/designs/{design}.css. Everything here is an
 * enhancement: without JavaScript, or for visitors who prefer reduced motion,
 * the page is fully visible and usable.
 */
(function () {
	'use strict';

	var root = document.documentElement;
	var design = root.getAttribute('data-design') || 'classic';
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

	var effects = {
		aurora: { reveal: true, spotlight: true, parallax: '.k-hero__scene, .k-split__media img' },
		sanctuary: { reveal: true, parallax: '.k-split__media img, .k-hero__image, .k-page-header.has-image' },
		pulse: { reveal: true, spotlight: true, magnetic: true, split: true, progress: true },
		horizon: { reveal: true, split: true, progress: true, parallax: '.k-split__media img, .k-card__media img' },
		ember: { reveal: true, parallax: '.k-split__media img' },
		cosmos: { reveal: true, spotlight: true, split: true, stars: true, parallax: '.k-section__head' }
	}[design];
	if (!effects) { return; }

	var cards = '.k-card, .kcs-plan, .k-feature, .k-testimony, .k-social-card, .k-leader, .k-event-row, .kcs-card, .k-floating-card';

	/* Words rise into view (headlines) ------------------------------------ */
	if (effects.split && !reduce) {
		document.querySelectorAll('.k-hero__title, .k-section__title, .k-page-header__title, .k-cta h2').forEach(function (el) {
			if (el.children.length || !el.textContent.trim() || el.closest('.is-dashboard')) { return; }
			var words = el.textContent.trim().split(/\s+/);
			el.setAttribute('aria-label', el.textContent.trim());
			el.textContent = '';
			words.forEach(function (w, i) {
				var outer = document.createElement('span');
				var inner = document.createElement('span');
				outer.className = 'k-w';
				outer.setAttribute('aria-hidden', 'true');
				inner.textContent = w;
				inner.style.setProperty('--k-i', i);
				outer.appendChild(inner);
				el.appendChild(outer);
				if (i < words.length - 1) { el.appendChild(document.createTextNode(' ')); }
			});
			el.classList.add('k-split-words');
		});
	}

	/* Reveal on scroll, staggered within each group ------------------------- */
	if (effects.reveal && !reduce && 'IntersectionObserver' in window) {
		root.classList.add('k-fx');
		var targets = document.querySelectorAll(cards + ', .k-section__head, .k-split__media, .k-split__body, .k-split > div, .k-stat, .k-split-words, .k-cta__inner, .k-featured-sermon, .kcs-countdown, .k-verse-band__text');
		targets.forEach(function (el) {
			var parent = el.parentElement;
			var index = parent ? Array.prototype.indexOf.call(parent.children, el) : 0;
			el.style.setProperty('--k-d', Math.min(index, 6) * 90 + 'ms');
			el.classList.add('k-rv');
		});
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { entry.target.classList.add('k-in'); io.unobserve(entry.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
		targets.forEach(function (el) { io.observe(el); });
		// Anything already on screen when the page opens shows at once.
		requestAnimationFrame(function () {
			targets.forEach(function (el) {
				var r = el.getBoundingClientRect();
				if (r.top < window.innerHeight && r.bottom > 0) { el.classList.add('k-in'); }
			});
		});
	}

	/* Light that follows the cursor over cards ------------------------------ */
	if (effects.spotlight && fine) {
		document.addEventListener('pointermove', function (e) {
			var card = e.target.closest && e.target.closest(cards);
			if (!card) { return; }
			var r = card.getBoundingClientRect();
			card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
			card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
		}, { passive: true });
	}

	/* Buttons that pull toward the cursor ----------------------------------- */
	if (effects.magnetic && fine && !reduce) {
		document.querySelectorAll('.k-btn, .kcs-btn').forEach(function (btn) {
			btn.addEventListener('pointermove', function (e) {
				var r = btn.getBoundingClientRect();
				var x = (e.clientX - r.left - r.width / 2) * 0.25;
				var y = (e.clientY - r.top - r.height / 2) * 0.35;
				btn.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
			});
			btn.addEventListener('pointerleave', function () { btn.style.transform = ''; });
		});
	}

	/* Scroll: reading progress and parallax depth --------------------------- */
	var bar = null;
	if (effects.progress) {
		bar = document.createElement('div');
		bar.className = 'k-progress';
		bar.setAttribute('aria-hidden', 'true');
		document.body.appendChild(bar);
	}
	var layers = effects.parallax && !reduce ? Array.prototype.slice.call(document.querySelectorAll(effects.parallax)) : [];
	var visible = new Set();
	if (layers.length && 'IntersectionObserver' in window) {
		var po = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) { if (entry.isIntersecting) { visible.add(entry.target); } else { visible.delete(entry.target); } });
		});
		layers.forEach(function (el) { el.classList.add('k-par'); po.observe(el); });
	}
	var ticking = false;
	function onScroll() {
		ticking = false;
		var h = window.innerHeight;
		if (bar) {
			var max = document.documentElement.scrollHeight - h;
			bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, window.scrollY / max) : 0) + ')';
		}
		visible.forEach(function (el) {
			var r = el.getBoundingClientRect();
			// -1 when the element enters at the bottom, 0 in the middle, 1 as it leaves at the top.
			var p = ((h / 2) - (r.top + r.height / 2)) / (h / 2 + r.height / 2);
			el.style.setProperty('--k-par', Math.max(-1, Math.min(1, p)).toFixed(3));
		});
	}
	if (bar || layers.length) {
		window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		onScroll();
	}

	/* Starfield behind the banner (Cosmos) ---------------------------------- */
	if (effects.stars) {
		document.querySelectorAll('.k-hero, .k-page-header').forEach(function (host) {
			var canvas = document.createElement('canvas');
			canvas.className = 'k-stars';
			canvas.setAttribute('aria-hidden', 'true');
			host.insertBefore(canvas, host.firstChild);
			var ctx = canvas.getContext('2d');
			if (!ctx) { return; }
			var stars = [];
			var w = 0, hgt = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
			var mx = 0, my = 0, tx = 0, ty = 0, running = false, frame = 0;
			function size() {
				w = host.clientWidth; hgt = host.clientHeight;
				canvas.width = w * dpr; canvas.height = hgt * dpr;
				ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				var count = Math.round(Math.min(260, w * hgt / 5200));
				stars = [];
				for (var i = 0; i < count; i++) {
					stars.push({ x: Math.random() * w, y: Math.random() * hgt, z: Math.random() * 0.9 + 0.1, t: Math.random() * Math.PI * 2 });
				}
			}
			function draw() {
				frame++;
				tx += (mx - tx) * 0.05; ty += (my - ty) * 0.05;
				ctx.clearRect(0, 0, w, hgt);
				var drift = reduce ? 0 : frame * 0.08;
				for (var i = 0; i < stars.length; i++) {
					var s = stars[i];
					var x = (s.x + tx * s.z * 40 + drift * s.z) % w;
					var y = s.y + ty * s.z * 30 - window.scrollY * s.z * 0.15;
					y = ((y % hgt) + hgt) % hgt;
					var a = reduce ? 0.8 : 0.45 + 0.55 * Math.abs(Math.sin(s.t + frame * 0.02 * s.z));
					ctx.globalAlpha = a;
					ctx.fillStyle = s.z > 0.75 ? '#fff' : '#cdd6ff';
					ctx.beginPath();
					ctx.arc(x < 0 ? x + w : x, y, s.z * 1.6, 0, Math.PI * 2);
					ctx.fill();
				}
				if (running && !reduce) { requestAnimationFrame(draw); }
			}
			size();
			draw();
			window.addEventListener('resize', function () { size(); draw(); }, { passive: true });
			if (!reduce) {
				host.addEventListener('pointermove', function (e) {
					var r = host.getBoundingClientRect();
					mx = (e.clientX - r.left) / r.width - 0.5;
					my = (e.clientY - r.top) / r.height - 0.5;
				}, { passive: true });
				// Only animate while the banner is on screen.
				new IntersectionObserver(function (entries) {
					var on = entries[0].isIntersecting;
					if (on && !running) { running = true; requestAnimationFrame(draw); }
					running = on;
				}).observe(host);
			}
		});
	}
})();
