/* Church Admin: picture pickers (WordPress media library) and password reveal. */
(function () {
	'use strict';
	var t = window.kcsChurchAdmin || {};

	function pick(onSelect, video) {
		if (!window.wp || !wp.media) { return; }
		var frame = video
			? wp.media({ title: t.titleVideo || 'Choose a video', button: { text: t.useVideo || 'Use this video' }, library: { type: 'video' }, multiple: false })
			: wp.media({ title: t.title || 'Choose a picture', button: { text: t.use || 'Use this picture' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (!video && a.sizes && a.sizes.large ? a.sizes.large.url : a.url);
			onSelect(a, url);
		});
		frame.open();
	}

	document.querySelectorAll('[data-kcs-media]').forEach(function (input) {
		if (!window.wp || !wp.media) { return; }
		var mode = input.getAttribute('data-kcs-media');
		var field = input.closest('[data-kcs-media-field]');
		var btns = document.createElement('span');
		btns.className = 'kca-image__btns';
		var choose = document.createElement('button');
		choose.type = 'button';
		choose.className = 'kcs-btn kcs-btn--ghost';
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'kcs-btn kcs-btn--ghost';
		remove.textContent = t.remove || 'Remove';
		btns.appendChild(choose);
		if (field) { btns.appendChild(remove); }
		var preview = field ? field.querySelector('.kca-image__preview') : null;

		var video = mode === 'video';
		function sync() {
			var has = !!input.value && input.value !== '0';
			choose.textContent = video ? (t.chooseVideo || 'Upload or choose video') : (has && field ? (t.change || 'Change') : (t.choose || 'Choose picture'));
			remove.hidden = !has;
			if (preview) {
				// Video preview only for uploaded files, not YouTube/Vimeo links.
				var show = has && (!video || /\.(mp4|m4v|webm|ogv|mov)(\?.*)?$/i.test(input.value));
				if (video && show && preview.getAttribute('src') !== input.value) { preview.src = input.value; }
				preview.hidden = !show;
			}
		}
		if (video) { input.addEventListener('change', sync); }
		choose.addEventListener('click', function () {
			pick(function (a, url) {
				input.value = mode === 'id' ? a.id : url;
				if (preview) { preview.src = url; }
				sync();
			}, video);
		});
		remove.addEventListener('click', function () {
			input.value = '';
			if (preview) { preview.removeAttribute('src'); }
			sync();
		});
		input.insertAdjacentElement('afterend', btns);
		sync();
	});

	document.querySelectorAll('input[data-kcs-reveal]').forEach(function (input) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'kca-reveal';
		b.textContent = '👁';
		b.setAttribute('aria-label', 'Show');
		b.addEventListener('click', function () { input.type = input.type === 'password' ? 'text' : 'password'; });
		input.insertAdjacentElement('afterend', b);
	});
})();

// Menu: always open as a sidebar on wide screens; a tap-to-open panel on phones.
(function () {
	var menu = document.querySelector('[data-kca-menu]');
	if (!menu || !window.matchMedia) { return; }
	var wide = window.matchMedia('(min-width: 960px)');
	function sync() { if (wide.matches) { menu.setAttribute('open', ''); } else { menu.removeAttribute('open'); } }
	sync();
	if (wide.addEventListener) { wide.addEventListener('change', sync); }
})();

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

// Service times editor: add and remove rows.
document.querySelectorAll('[data-sched]').forEach(function (box) {
	var rows = box.querySelector('[data-sched-rows]');
	var tpl = box.querySelector('[data-sched-template]');
	box.addEventListener('click', function (e) {
		if (e.target.closest('[data-sched-add]') && tpl) {
			rows.appendChild(tpl.content.cloneNode(true));
			var last = rows.lastElementChild && rows.lastElementChild.querySelector('input[type="time"]');
			if (last) { last.focus(); }
		}
		var del = e.target.closest('[data-sched-del]');
		if (del) {
			var row = del.closest('.kca-sched__row');
			if (rows.children.length > 1) { row.remove(); } else { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
		}
	});
});

// Order of payment methods: move rows up and down.
document.querySelectorAll('[data-order]').forEach(function (list) {
	list.addEventListener('click', function (e) {
		var up = e.target.closest('[data-order-up]'), down = e.target.closest('[data-order-down]');
		var li = (up || down) && (up || down).closest('li');
		if (!li) { return; }
		if (up && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); }
		if (down && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); }
		(up || down).focus();
	});
});
