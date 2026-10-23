/**
 * Ministry OS Church Suite — front-end behaviour (no dependencies).
 */
(function () {
	'use strict';

	var cfg = window.KCS || { restUrl: '/wp-json/kcs/v1/', nonce: '', i18n: {} };
	var t = function (k, d) { return (cfg.i18n && cfg.i18n[k]) || d; };

	function api(path, method, body) {
		var headers = { 'Content-Type': 'application/json' };
		if (cfg.loggedIn && cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
		return fetch(cfg.restUrl + path, {
			method: method || 'GET',
			headers: headers,
			credentials: 'same-origin',
			body: body ? JSON.stringify(body) : undefined
		}).then(function (res) {
			// A proxy or server error page is HTML, not JSON: show a friendly message instead.
			return res.json().catch(function () { return null; }).then(function (data) {
				if (!res.ok || data === null) { throw new Error((data && data.message) || t('error', 'Error')); }
				return data;
			});
		});
	}

	function formData(form) {
		var out = {};
		new FormData(form).forEach(function (v, k) { out[k] = v; });
		return out;
	}

	function msg(form, text, type) {
		var el = form.querySelector('.kcs-form-msg');
		if (!el) { return; }
		el.className = 'kcs-form-msg kcs-alert kcs-alert--' + (type || 'info');
		el.textContent = text;
	}

	function busy(form, on) {
		var btn = form.querySelector('[type="submit"]');
		if (!btn) { return; }
		if (on) { btn.dataset.label = btn.textContent; btn.textContent = t('processing', 'Processing…'); btn.disabled = true; }
		else { btn.textContent = btn.dataset.label || btn.textContent; btn.disabled = false; }
	}

	/* International / Local switch --------------------------------------- */
	document.querySelectorAll('[data-kcs-region-scope]').forEach(function (scope) {
		scope.addEventListener('change', function (e) {
			var radio = e.target;
			if (!radio.matches || !radio.matches('[data-kcs-region]') || radio.closest('[data-kcs-region-scope]') !== scope) { return; }
			scope.dataset.region = radio.value;
			scope.querySelectorAll('input[type="hidden"][name="region"]').forEach(function (i) { i.value = radio.value; });
			var cur = scope.querySelector('input[type="hidden"][data-kcs-currency]');
			if (cur && radio.dataset.currency && cur.value !== radio.dataset.currency) {
				cur.value = radio.dataset.currency;
				cur.dispatchEvent(new Event('change'));
				var amt = scope.querySelector('[name="amount"]');
				if (amt) { amt.value = ''; }
				scope.querySelectorAll('.kcs-preset.is-active').forEach(function (b) { b.classList.remove('is-active'); });
			}
		});
	});

	/* Tithe calculator --------------------------------------------------- */
	document.querySelectorAll('[data-kcs-tithe-calc]').forEach(function (box) {
		var form = box.closest('form'), income = box.querySelector('[data-kcs-income]'), out = box.querySelector('[data-kcs-tenth]');
		function tenth() { return Math.round((parseFloat(income.value) || 0) * 10) / 100; }
		income.addEventListener('input', function () { out.textContent = tenth().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); });
		box.querySelector('[data-kcs-use-tenth]').addEventListener('click', function () {
			var v = tenth();
			if (!v) { income.focus(); return; }
			form.querySelector('[name="amount"]').value = v;
			var tithe = form.querySelector('[name="purpose"][value="tithe"]');
			if (tithe) { tithe.checked = true; }
		});
	});

	/* Payment forms ------------------------------------------------------ */
	document.querySelectorAll('[data-kcs-payment-form]').forEach(function (form) {
		var amount = form.querySelector('[name="amount"]');
		form.querySelectorAll('.kcs-preset').forEach(function (b) {
			b.addEventListener('click', function () {
				amount.value = b.dataset.amount;
				form.querySelectorAll('.kcs-preset').forEach(function (x) { x.classList.toggle('is-active', x === b); });
			});
		});

		var currency = form.querySelector('[data-kcs-currency]');
		function filterGateways() {
			if (!currency) { return; }
			var code = currency.value, firstVisible = null, checkedVisible = false;
			form.querySelectorAll('.kcs-gateway').forEach(function (label) {
				var list = (label.dataset.currencies || '').split(',').filter(Boolean);
				var ok = !list.length || list.indexOf(code) !== -1;
				label.hidden = !ok;
				var radio = label.querySelector('input');
				radio.disabled = !ok;
				if (ok && !firstVisible) { firstVisible = radio; }
				if (ok && radio.checked) { checkedVisible = true; }
			});
			if (!checkedVisible && firstVisible) { firstVisible.checked = true; }
		}
		if (currency) { currency.addEventListener('change', filterGateways); filterGateways(); }

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			msg(form, t('processing', 'Processing…'), 'info');
			api('payments/initialize', 'POST', formData(form))
				.then(function (res) { window.location.href = res.redirect_url; })
				.catch(function (err) { busy(form, false); msg(form, err.message, 'error'); });
		});
	});

	/* Registration: show custom amount for custom plans ------------------- */
	document.querySelectorAll('[data-kcs-register]').forEach(function (form) {
		var box = form.querySelector('[data-kcs-custom-amount]');
		function sync() {
			var checked = form.querySelector('[name="plan_id"]:checked');
			var custom = !checked || checked.closest('.kcs-plan-option').dataset.custom === '1';
			if (box) {
				box.hidden = !custom;
				box.querySelectorAll('input,select').forEach(function (el) { el.disabled = !custom; });
				var amt = box.querySelector('[name="amount"]');
				if (amt) { amt.required = custom; }
			}
		}
		form.querySelectorAll('[name="plan_id"]').forEach(function (r) { r.addEventListener('change', sync); });
		sync();
	});

	/* Newsletter --------------------------------------------------------- */
	document.querySelectorAll('[data-kcs-newsletter]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			var data = formData(form);
			data.source = 'website';
			api('subscribe', 'POST', data)
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});

	/* Prayer requests ---------------------------------------------------- */
	document.querySelectorAll('[data-kcs-prayer]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			var data = formData(form);
			data.is_private = form.querySelector('[name="is_private"]').checked ? 1 : 0;
			api('prayer-requests', 'POST', data)
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});
	document.querySelectorAll('[data-kcs-visit]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			api('visits', 'POST', formData(form))
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});
	document.querySelectorAll('[data-kcs-questionnaire]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			api('visit-questionnaire', 'POST', formData(form))
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});
	document.querySelectorAll('[data-kcs-contact]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			api('contact', 'POST', formData(form))
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});
	document.querySelectorAll('[data-kcs-testimony]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			busy(form, true);
			api('testimonies', 'POST', formData(form))
				.then(function (res) { msg(form, res.message, 'success'); form.reset(); })
				.catch(function (err) { msg(form, err.message, 'error'); })
				.then(function () { busy(form, false); });
		});
	});
	var focusPlan = document.querySelector('.kcs-plan.is-focus');
	if (focusPlan) { setTimeout(function () { focusPlan.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300); }

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-kcs-pray]');
		if (!btn || btn.disabled) { return; }
		btn.disabled = true;
		api('prayer-requests/' + btn.dataset.kcsPray + '/pray', 'POST')
			.then(function (res) { btn.querySelector('span').textContent = res.count; btn.classList.add('is-done'); })
			.catch(function () { btn.classList.add('is-done'); });
	});

	/* Countdown ---------------------------------------------------------- */
	document.querySelectorAll('[data-kcs-countdown]').forEach(function (el) {
		var target = parseInt(el.dataset.kcsCountdown, 10) * 1000;
		var clock = el.querySelector('.kcs-countdown__clock');
		function pad(n) { return n < 10 ? '0' + n : String(n); }
		function tick() {
			var diff = Math.max(0, target - Date.now());
			var d = Math.floor(diff / 86400000), h = Math.floor(diff / 3600000) % 24, m = Math.floor(diff / 60000) % 60, s = Math.floor(diff / 1000) % 60;
			if (diff === 0) {
				clock.innerHTML = '<span class="kcs-countdown__live">' + t('live', 'LIVE') + '</span>';
				return;
			}
			clock.innerHTML = [[d, t('days', 'Days')], [h, t('hours', 'Hours')], [m, t('minutes', 'Min')], [s, t('seconds', 'Sec')]]
				.map(function (p) { return '<span><b>' + pad(p[0]) + '</b><small>' + p[1] + '</small></span>'; }).join('');
			setTimeout(tick, 1000);
		}
		tick();
	});

	/* Live badge polling ------------------------------------------------- */
	var badges = document.querySelectorAll('[data-kcs-live-badge]');
	if (badges.length) {
		var poll = function () {
			api('live').then(function (s) {
				badges.forEach(function (b) {
					b.classList.toggle('is-live', !!s.is_live);
					var txt = b.querySelector('.kcs-live-text');
					if (txt) { txt.textContent = s.is_live ? t('live', 'LIVE') + ' NOW' : txt.dataset.offline || txt.textContent; }
				});
				document.documentElement.classList.toggle('kcs-is-live', !!s.is_live);
			}).catch(function () {});
		};
		badges.forEach(function (b) { var txt = b.querySelector('.kcs-live-text'); if (txt && !b.classList.contains('is-live')) { txt.dataset.offline = txt.textContent; } });
		setInterval(poll, 120000);
	}

	/* Copy buttons (bank details, references) --------------------------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('[data-kcs-copy]');
		if (!btn) { return; }
		var text = btn.dataset.kcsCopy, label = btn.textContent;
		var done = function () { btn.textContent = btn.dataset.done || 'Copied'; btn.classList.add('is-done'); setTimeout(function () { btn.textContent = label; btn.classList.remove('is-done'); }, 1600); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done).catch(function () {});
		} else {
			var ta = document.createElement('textarea');
			ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); done(); } catch (err) {}
			document.body.removeChild(ta);
		}
	});

	/* Payment status auto-refresh --------------------------------------- */
	var status = document.querySelector('[data-kcs-payment-status]');
	if (status && (status.dataset.status === 'pending' || status.dataset.status === 'review') && !/kcs_offline/.test(location.search)) {
		var tries = 0;
		var check = function () {
			api('payments/status?reference=' + encodeURIComponent(status.dataset.kcsPaymentStatus)).then(function (r) {
				if (r.status !== status.dataset.status) { location.reload(); }
				else if (++tries < 10) { setTimeout(check, 4000); }
			}).catch(function () {});
		};
		setTimeout(check, 3000);
	}

	/* Dashboard tabs ----------------------------------------------------- */
	document.querySelectorAll('[data-kcs-tabs]').forEach(function (wrap) {
		function show(name) {
			wrap.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'); });
			wrap.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== name; });
		}
		wrap.addEventListener('click', function (e) {
			var b = e.target.closest('[data-tab],[data-tab-link]');
			if (b) { show(b.dataset.tab || b.dataset.tabLink); }
		});
		show(wrap.dataset.active || 'overview');
	});

	/* Share -------------------------------------------------------------- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-kcs-share]');
		if (!b) { return; }
		var data = { title: document.title, url: location.href };
		if (navigator.share) { navigator.share(data).catch(function () {}); }
		else if (navigator.clipboard) { navigator.clipboard.writeText(data.url); b.textContent = '✓'; }
	});
})();

// Some payment methods (e.g. Zenith GlobalPay) need the giver's phone number.
document.querySelectorAll('[data-kcs-payment-form], form.kcs-pay-form').forEach(function (form) {
	var phone = form.querySelector('input[name="phone"]');
	if (!phone || form.dataset.kcsPhoneWired) { return; }
	form.dataset.kcsPhoneWired = '1';
	function sync() {
		var picked = form.querySelector('input[name="gateway"]:checked');
		var needs = !!(picked && picked.closest('[data-needs-phone]'));
		phone.required = needs;
		var label = phone.closest('label');
		if (label) { label.classList.toggle('kcs-phone-needed', needs); }
	}
	form.addEventListener('change', function (e) { if (e.target.name === 'gateway') { sync(); } });
	sync();
});

/* Announcement slider: swipe or arrows, dots, auto-advance (paused on hover/touch/focus). */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	document.querySelectorAll('[data-kcs-slider]').forEach(function (box) {
		var track = box.querySelector('[data-track]');
		var slides = track.children;
		var dots = box.querySelectorAll('[data-dots] button');
		if (slides.length < 2) { return; }
		var current = 0, timer = null, paused = false;
		var wait = (parseInt(box.getAttribute('data-seconds'), 10) || 6) * 1000;
		function go(i) {
			current = (i + slides.length) % slides.length;
			track.scrollTo({ left: slides[current].offsetLeft - track.offsetLeft, behavior: reduce ? 'auto' : 'smooth' });
		}
		function mark() {
			var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
			current = Math.max(0, Math.min(slides.length - 1, i));
			dots.forEach(function (d, n) { if (n === current) { d.setAttribute('aria-current', 'true'); } else { d.removeAttribute('aria-current'); } });
		}
		function start() { stop(); if (!reduce && !paused) { timer = setInterval(function () { if (!document.hidden) { go(current + 1); } }, wait); } }
		function stop() { if (timer) { clearInterval(timer); timer = null; } }
		var t;
		track.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(mark, 60); }, { passive: true });
		var prev = box.querySelector('[data-prev]'), next = box.querySelector('[data-next]');
		if (prev) { prev.addEventListener('click', function () { go(current - 1); start(); }); }
		if (next) { next.addEventListener('click', function () { go(current + 1); start(); }); }
		dots.forEach(function (d, n) { d.addEventListener('click', function () { go(n); start(); }); });
		track.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { go(current + 1); } if (e.key === 'ArrowLeft') { go(current - 1); } });
		// Pause while a mouse is over it (phones fire fake mouse events after a tap, so check the pointer type).
		box.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse') { paused = true; stop(); } });
		box.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') { paused = false; start(); } });
		box.addEventListener('focusin', function () { if (box.matches(':focus-visible, :focus-within') && document.activeElement !== document.body) { paused = true; stop(); } });
		box.addEventListener('focusout', function () { paused = false; start(); });
		track.addEventListener('touchstart', function () { stop(); }, { passive: true });
		track.addEventListener('touchend', function () { setTimeout(start, 4000); }, { passive: true });
		window.addEventListener('resize', function () { go(current); });
		start();
	});
})();

/* Popups: device, trigger and frequency rules (pages, audience and dates are checked on the server). */
(function () {
	'use strict';
	var el = document.querySelector('[data-kcs-popup]');
	if (!el) { return; }
	var cfg = {};
	try { cfg = JSON.parse(el.getAttribute('data-kcs-popup')) || {}; } catch (e) { return; }
	var phone = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
	if ((cfg.device === 'mobile' && !phone) || (cfg.device === 'desktop' && phone)) { return; }
	var key = 'kcs_popup_' + cfg.id;
	function get(store) { try { return store.getItem(key); } catch (e) { return null; } }
	function set(store, v) { try { store.setItem(key, v); } catch (e) {} }
	var last = parseInt(get(window.localStorage), 10) || 0;
	if (cfg.frequency === 'once' && last) { return; }
	if (cfg.frequency === 'days' && last && Date.now() - last < (cfg.days || 7) * 86400000) { return; }
	if (cfg.frequency === 'session' && get(window.sessionStorage)) { return; }
	var shown = false, before = null;
	function remember() { set(window.localStorage, String(Date.now())); set(window.sessionStorage, '1'); }
	function close() {
		el.hidden = true;
		document.body.classList.remove('kcs-popup-open');
		document.removeEventListener('keydown', onKey);
		if (before && before.focus) { before.focus(); }
	}
	function onKey(e) {
		if (e.key === 'Escape') { close(); }
		if (e.key === 'Tab') {
			var f = el.querySelectorAll('a[href], button');
			if (!f.length) { return; }
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		}
	}
	function open() {
		if (shown) { return; }
		shown = true;
		before = document.activeElement;
		el.hidden = false;
		document.body.classList.add('kcs-popup-open');
		if (cfg.frequency !== 'always') { remember(); }
		var c = el.querySelector('.kcs-popup__close');
		if (c) { c.focus(); }
		document.addEventListener('keydown', onKey);
	}
	el.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
	if (cfg.trigger === 'scroll') {
		var onScroll = function () {
			var h = document.documentElement.scrollHeight - window.innerHeight;
			if (h <= 0 || (window.scrollY / h) * 100 >= (cfg.scroll || 50)) { window.removeEventListener('scroll', onScroll); open(); }
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	} else if (cfg.trigger === 'exit') {
		if (phone) { setTimeout(open, 30000); }
		else { document.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY <= 0) { open(); } }); }
	} else {
		setTimeout(open, Math.max(0, cfg.delay || 0) * 1000);
	}
})();
