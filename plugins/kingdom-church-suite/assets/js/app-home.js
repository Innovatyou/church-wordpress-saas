/**
 * App home: greeting for the time of day, live / countdown, depth that follows the phone's tilt
 * (or the mouse), the Today's Word flip card and "I prayed" right from home (no dependencies).
 */
(function () {
	'use strict';

	var app = document.querySelector('[data-kcs-app]');
	if (!app) { return; }
	var cfg = {};
	try { cfg = JSON.parse(app.getAttribute('data-config')) || {}; } catch (e) { cfg = {}; }
	var L = cfg.i18n || {};
	var base = window.KCS || { restUrl: '/wp-json/kcs/v1/' };
	var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function api(path, method) {
		return fetch(base.restUrl + path, { method: method || 'GET', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } })
			.then(function (r) { return r.json().then(function (d) { if (!r.ok) { var e = new Error(d && d.message); e.status = r.status; throw e; } return d; }); });
	}

	/* Greeting and sky by time of day ------------------------------------ */
	var hour = new Date().getHours();
	var part = hour < 5 ? 'night' : hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : hour < 21 ? 'evening' : 'night';
	app.setAttribute('data-time', part);
	var greet = app.querySelector('[data-kah-greet]');
	if (greet && L[part]) { greet.textContent = L[part]; }

	/* Live / countdown --------------------------------------------------- */
	var live = app.querySelector('[data-kah-live]');
	var count = app.querySelector('[data-kah-count]');
	var label = app.querySelector('[data-kah-live-label]');
	var isLive = !!cfg.live;
	var label0 = label ? label.textContent : '';
	function tick() {
		if (isLive || !cfg.next || !count) { return; }
		var s = Math.max(0, Math.floor(cfg.next - Date.now() / 1000));
		var d = Math.floor(s / 86400);
		var h = Math.floor((s % 86400) / 3600);
		var m = Math.floor((s % 3600) / 60);
		var sec = s % 60;
		count.textContent = (d ? d + L.d + ' ' : '') + (d || h ? h + L.h + ' ' : '') + m + L.m + ' ' + (d ? '' : (sec < 10 ? '0' : '') + sec + L.s);
	}
	function setLive(on) {
		isLive = on;
		if (!live) { return; }
		live.classList.toggle('is-live', on);
		if (on) {
			label.textContent = L.liveNow;
		} else {
			label.textContent = label0;
			tick();
		}
	}
	tick();
	setInterval(tick, 1000);
	function checkLive() {
		if (document.hidden) { return; }
		api('live').then(function (s) { setLive(!!(s && s.is_live)); }).catch(function () {});
	}
	checkLive();
	setInterval(checkLive, 60000);

	/* Depth: layers move with the phone's tilt, or the mouse on a computer --- */
	var scene = app.querySelector('[data-kah-scene]');
	var layers = scene ? Array.prototype.slice.call(scene.querySelectorAll('[data-depth]')) : [];
	var target = { x: 0, y: 0 };
	var cur = { x: 0, y: 0 };
	var running = false;
	function frame() {
		cur.x += (target.x - cur.x) * 0.08;
		cur.y += (target.y - cur.y) * 0.08;
		layers.forEach(function (el) {
			var d = parseFloat(el.getAttribute('data-depth')) || 0;
			el.style.transform = 'translate3d(' + (cur.x * d * 40).toFixed(2) + 'px,' + (cur.y * d * 30).toFixed(2) + 'px,0)';
		});
		scene.style.setProperty('--tx', cur.x.toFixed(3));
		scene.style.setProperty('--ty', cur.y.toFixed(3));
		if (Math.abs(target.x - cur.x) > 0.001 || Math.abs(target.y - cur.y) > 0.001) {
			requestAnimationFrame(frame);
		} else {
			running = false;
		}
	}
	function move(x, y) {
		target.x = Math.max(-1, Math.min(1, x));
		target.y = Math.max(-1, Math.min(1, y));
		if (!running) { running = true; requestAnimationFrame(frame); }
	}
	if (scene && !calm) {
		scene.addEventListener('pointermove', function (e) {
			if (e.pointerType !== 'mouse') { return; }
			var r = scene.getBoundingClientRect();
			move(((e.clientX - r.left) / r.width - 0.5) * 2, ((e.clientY - r.top) / r.height - 0.5) * 2);
		});
		scene.addEventListener('pointerleave', function () { move(0, 0); });
		function orient(e) {
			if (e.gamma == null) { return; }
			move(e.gamma / 30, (e.beta - 45) / 30);
		}
		// iPhone asks first: start listening after the first tap anywhere on the home screen.
		if (window.DeviceOrientationEvent && typeof DeviceOrientationEvent.requestPermission === 'function') {
			app.addEventListener('click', function ask() {
				app.removeEventListener('click', ask);
				DeviceOrientationEvent.requestPermission().then(function (p) { if (p === 'granted') { window.addEventListener('deviceorientation', orient); } }).catch(function () {});
			});
		} else if (window.DeviceOrientationEvent) {
			window.addEventListener('deviceorientation', orient);
		}
		// Gentle drift while still.
		var t0 = performance.now();
		(function drift(t) {
			if (!running && Math.abs(target.x) < 0.02 && Math.abs(target.y) < 0.02) {
				var k = (t - t0) / 4000;
				layers.forEach(function (el) {
					var d = parseFloat(el.getAttribute('data-depth')) || 0;
					el.style.transform = 'translate3d(' + (Math.sin(k) * d * 10).toFixed(2) + 'px,' + (Math.cos(k * 0.8) * d * 8).toFixed(2) + 'px,0)';
				});
			}
			requestAnimationFrame(drift);
		})(t0);
	}

	/* Tiles: 3D press ---------------------------------------------------- */
	if (!calm) {
		app.querySelectorAll('.kah-tile, .kah-media, .kah-event').forEach(function (el) {
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				el.style.setProperty('--rx', (((e.clientY - r.top) / r.height - 0.5) * -14).toFixed(1) + 'deg');
				el.style.setProperty('--ry', (((e.clientX - r.left) / r.width - 0.5) * 14).toFixed(1) + 'deg');
			});
			el.addEventListener('pointerleave', function () { el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg'); });
		});
	}

	/* Today's Word flip card ----------------------------------------------- */
	var flip = app.querySelector('[data-kah-flip]');
	if (flip) {
		function turn(e) {
			if (e.target.closest('a, button')) { return; }
			var on = flip.classList.toggle('is-flipped');
			flip.setAttribute('aria-pressed', on ? 'true' : 'false');
		}
		flip.addEventListener('click', turn);
		flip.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); turn(e); } });
		if (!calm) {
			flip.addEventListener('pointermove', function (e) {
				var r = flip.getBoundingClientRect();
				flip.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
				flip.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
			});
		}
	}

	/* Pray from home ----------------------------------------------------- */
	var prayed = {};
	try { (JSON.parse(localStorage.getItem('kpw_prayed') || '[]') || []).forEach(function (id) { prayed[id] = 1; }); } catch (e) {}
	app.querySelectorAll('.kah-ask').forEach(function (card) {
		var id = card.getAttribute('data-id');
		var btn = card.querySelector('[data-kah-pray]');
		if (prayed[id]) { btn.classList.add('is-done'); btn.querySelector('span').textContent = L.prayed; }
		btn.addEventListener('click', function () {
			if (prayed[id]) { return; }
			prayed[id] = 1;
			try { localStorage.setItem('kpw_prayed', JSON.stringify(Object.keys(prayed).slice(-500))); } catch (e) {}
			var b = btn.querySelector('b');
			b.textContent = (parseInt(b.textContent, 10) || 0) + 1;
			btn.classList.add('is-done');
			btn.querySelector('span').textContent = L.prayed;
			var total = app.querySelector('[data-kah-prayers]');
			if (total) { total.textContent = ((parseInt(total.textContent.replace(/\D/g, ''), 10) || 0) + 1).toLocaleString(); }
			if (navigator.vibrate) { try { navigator.vibrate(12); } catch (e) {} }
			api('prayer-requests/' + id + '/pray', 'POST').then(function (r) { if (r && r.count) { b.textContent = r.count; } }).catch(function () {});
		});
	});

	/* Sections rise in as they scroll into view ----------------------------- */
	if ('IntersectionObserver' in window && !calm) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -10% 0px' });
		app.querySelectorAll('.kah-sec, .kah-tiles, .kah-install').forEach(function (s) { s.classList.add('kah-rise'); io.observe(s); });
	}
})();
