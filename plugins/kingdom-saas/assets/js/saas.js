/* Ministry OS Cloud — platform pages. */
(function () {
	'use strict';
	var script = document.currentScript || document.querySelector('script[data-rest]');
	var rest = script ? script.getAttribute('data-rest') : '/wp-json/ks/v1/';

	/* Pricing: International / Local and Monthly / Yearly switches. */
	function wirePrices(scope) {
		scope.addEventListener('change', function (e) {
			var el = e.target;
			if (el.type !== 'radio') { return; }
			if (/region/.test(el.name)) { scope.setAttribute('data-r', el.value); }
			if (/interval/.test(el.name)) { scope.setAttribute('data-i', el.value); }
			scope.querySelectorAll('[data-plan-link]').forEach(function (a) {
				var u = new URL(a.href);
				u.searchParams.set('region', scope.getAttribute('data-r'));
				u.searchParams.set('interval', scope.getAttribute('data-i'));
				a.href = u.toString();
			});
		});
	}
	document.querySelectorAll('[data-ks-prices]').forEach(wirePrices);

	/* Sign-up: suggest the web address from the church name and check it live. */
	var church = document.querySelector('[data-ks-church]');
	var sub = document.querySelector('[data-ks-subdomain]');
	var msg = document.querySelector('[data-ks-subdomain-msg]');
	var touched = !!(sub && sub.value);
	var timer;
	function slug(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 34); }
	function check() {
		if (!sub || !msg) { return; }
		var v = sub.value.trim().toLowerCase();
		sub.value = v;
		if (!v) { msg.textContent = ''; return; }
		clearTimeout(timer);
		timer = setTimeout(function () {
			fetch(rest + 'subdomain?name=' + encodeURIComponent(v), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					msg.textContent = d.available ? '✓ ' + d.domain + ' is available' : '✕ ' + d.message;
					msg.className = 'ks-hint ' + (d.available ? 'is-ok' : 'is-bad');
					sub.setCustomValidity(d.available ? '' : d.message);
				})
				.catch(function () {});
		}, 300);
	}
	if (church && sub) {
		church.addEventListener('input', function () { if (!touched) { sub.value = slug(church.value); check(); } });
	}
	if (sub) {
		sub.addEventListener('input', function () { touched = true; check(); });
		if (sub.value) { check(); }
	}

	/* Carry region / interval chosen on the pricing page into sign-up. */
	var params = new URLSearchParams(location.search);
	if (params.get('region')) {
		var r = document.querySelector('[data-ks-signup] input[name="region"][value="' + params.get('region') + '"]');
		if (r) { r.checked = true; }
	}

	/* Sign-up: friendly validation messages and no double submit. */
	var form = document.querySelector('[data-ks-signup]');
	if (form) {
		form.addEventListener('submit', function (e) {
			if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
			var b = form.querySelector('button');
			b.disabled = true;
			b.textContent = b.getAttribute('data-busy') || 'Creating your website…';
		});
	}
})();

/* Dark / light switch (remembered on this device). */
document.querySelectorAll('[data-ks-mode]').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
		document.documentElement.dataset.theme = next;
		try { localStorage.setItem('ks-theme', next); } catch (e) {}
	});
});

// Dashboard search: pages and settings appear as you type (↑ ↓ Enter), "/" focuses the box,
// Enter with nothing picked opens the full results page (people, payments…).
(function () {
	var form = document.querySelector('[data-dsearch]');
	if (!form || form.hasAttribute('data-dsearch-ready')) { return; }
	form.setAttribute('data-dsearch-ready', '');
	var input = form.querySelector('input[type="search"]');
	var list = form.querySelector('[role="listbox"]');
	var data = form.querySelector('[data-dsearch-index]');
	var items = [];
	try { items = JSON.parse(data.textContent) || []; } catch (e) { items = []; }
	var more = data.getAttribute('data-more') || '%s';
	var none = data.getAttribute('data-none') || '';
	var active = -1;
	function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	items.forEach(function (i) { i.h = norm(i.t + ' ' + i.d + ' ' + i.k); i.n = norm(i.t); });
	function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
	function render() {
		var q = norm(input.value.trim());
		if (q.length < 2) { close(); return; }
		var words = q.split(/\s+/);
		var hits = items.filter(function (i) { return words.every(function (w) { return i.h.indexOf(w) > -1; }); });
		hits.sort(function (a, b) { return (b.n.indexOf(q) === 0) - (a.n.indexOf(q) === 0) || (b.n.indexOf(q) > -1) - (a.n.indexOf(q) > -1) || a.t.length - b.t.length; });
		var html = hits.slice(0, 8).map(function (i, n) {
			return '<li role="option" id="dsearch-' + n + '"><a href="' + esc(i.u) + '"><strong>' + esc(i.t) + '</strong><small>' + esc(i.d) + '</small></a></li>';
		}).join('');
		if (!hits.length && none) { html = '<li class="is-note">' + esc(none) + '</li>'; }
		html += '<li role="option" class="is-more" id="dsearch-more"><a href="#" data-dsearch-submit>' + esc(more.replace('%s', input.value.trim())) + ' →</a></li>';
		list.innerHTML = html;
		list.hidden = false;
		input.setAttribute('aria-expanded', 'true');
		active = -1;
	}
	function options() { return list.querySelectorAll('[role="option"]'); }
	function mark(n) {
		var o = options();
		if (!o.length) { return; }
		active = (n + o.length) % o.length;
		o.forEach(function (el, i) { el.classList.toggle('is-active', i === active); });
		input.setAttribute('aria-activedescendant', o[active].id);
		o[active].scrollIntoView({ block: 'nearest' });
	}
	input.addEventListener('input', render);
	input.addEventListener('focus', render);
	input.addEventListener('keydown', function (e) {
		if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
		else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
		else if (e.key === 'Escape') { close(); }
		else if (e.key === 'Enter' && active > -1) {
			var a = options()[active].querySelector('a');
			if (a && !a.hasAttribute('data-dsearch-submit')) { e.preventDefault(); window.location.href = a.href; }
		}
	});
	list.addEventListener('click', function (e) {
		var a = e.target.closest('[data-dsearch-submit]');
		if (a) { e.preventDefault(); form.submit(); }
	});
	document.addEventListener('click', function (e) { if (!form.contains(e.target)) { close(); } });
	document.addEventListener('keydown', function (e) {
		var t = e.target.tagName;
		if (e.key === '/' && t !== 'INPUT' && t !== 'TEXTAREA' && t !== 'SELECT' && !e.target.isContentEditable) { e.preventDefault(); input.focus(); }
	});
	// Jumped to a setting: open its collapsed group and highlight it.
	function reveal() {
		var id = decodeURIComponent((window.location.hash || '').slice(1));
		var el = id && document.getElementById(id);
		if (!el) { return; }
		for (var p = el.parentElement; p; p = p.parentElement) { if (p.tagName === 'DETAILS') { p.open = true; } }
		el.scrollIntoView({ block: 'center' });
		var next = el.offsetHeight ? el : el.nextElementSibling; // an empty marker highlights the field after it
		if (next) { next.classList.add('is-found'); setTimeout(function () { next.classList.remove('is-found'); }, 2600); }
	}
	window.addEventListener('hashchange', reveal);
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', reveal); } else { reveal(); }
})();
