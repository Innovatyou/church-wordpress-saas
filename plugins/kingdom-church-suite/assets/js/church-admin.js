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

// Newsletter: start from a design (fills the editor and, if empty, the subject).
(function () {
	var data = document.querySelector('[data-kcs-designs]');
	var form = document.querySelector('[data-kcs-campaign]');
	if (!data || !form) { return; }
	var designs = {};
	try { designs = JSON.parse(data.textContent) || {}; } catch (e) { return; }
	function editor() { return window.tinymce && window.tinymce.get('kcs_campaign_content'); }
	function current() {
		var ed = editor();
		if (ed && !ed.isHidden()) { return ed.getContent({ format: 'text' }).trim(); }
		var ta = document.getElementById('kcs_campaign_content');
		return ta ? ta.value.trim() : '';
	}
	document.querySelectorAll('[data-kcs-design]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var d = designs[btn.getAttribute('data-kcs-design')];
			if (!d) { return; }
			if (current() && !window.confirm(window.kcsChurchAdmin && kcsChurchAdmin.replaceDesign || 'Replace your message with this design?')) { return; }
			var ed = editor();
			var ta = document.getElementById('kcs_campaign_content');
			if (ed) { ed.setContent(d.html); ed.save(); }
			if (ta && (!ed || ed.isHidden())) { ta.value = d.html; }
			var subject = form.querySelector('[name="subject"]');
			if (subject && !subject.value.trim() && d.subject) { subject.value = d.subject; }
			document.querySelectorAll('[data-kcs-design]').forEach(function (b) { b.classList.toggle('is-picked', b === btn); });
			form.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	});
})();

// Announcement / popup templates: fill the form's fields (the church then adds its picture and edits the words).
(function () {
	document.querySelectorAll('[data-kcs-presets]').forEach(function (block) {
		var form = document.getElementById(block.getAttribute('data-kcs-presets'));
		var presets = {};
		try { presets = JSON.parse(block.textContent) || {}; } catch (e) { return; }
		if (!form) { return; }
		block.parentNode.querySelectorAll('[data-kcs-preset]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var p = presets[btn.getAttribute('data-kcs-preset')];
				if (!p) { return; }
				Object.keys(p).forEach(function (name) {
					var el = form.querySelector('[name="' + name + '"]');
					if (!el || el.type === 'hidden') { return; }
					if (el.type === 'checkbox') { el.checked = !!p[name]; } else { el.value = p[name]; }
					el.dispatchEvent(new Event('change', { bubbles: true }));
				});
				block.parentNode.querySelectorAll('[data-kcs-preset]').forEach(function (b) { b.classList.toggle('is-picked', b === btn); });
				form.scrollIntoView({ behavior: 'smooth', block: 'start' });
				var first = form.querySelector('[name="title"], [name="name"]');
				if (first) { setTimeout(function () { first.focus(); }, 400); }
			});
		});
	});
})();

// Newsletter: list the pictures in the email so the church can swap each one for its own photo.
(function () {
	var box = document.querySelector('[data-kcs-pics]');
	var L = window.kcsChurchAdmin && kcsChurchAdmin.pics;
	if (!box || !L) { return; }
	var timer = null;

	function editor() { return window.tinymce.get('kcs_campaign_content'); }
	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (text) { n.textContent = text; }
		return n;
	}
	function label(img, n) {
		if (/(^|\s)kcs-video-pic(\s|$)/.test(img.className)) { return L.video; }
		if (img.getAttribute('height') === '56' && img.closest('a')) { return L.logo; }
		return L.picture + ' ' + n;
	}
	function setSrc(ed, img, url) {
		ed.dom.setAttribs(img, { src: url, 'data-mce-src': url, srcset: null, sizes: null, height: img.getAttribute('height') === '56' ? '56' : null });
		img.style.height = img.getAttribute('height') === '56' ? '56px' : 'auto';
		ed.undoManager.add();
		ed.save();
		ed.fire('change');
		render();
	}
	function choose(ed, img) {
		if (!window.wp || !wp.media) { return; }
		var frame = wp.media({ title: kcsChurchAdmin.title, library: { type: 'image' }, multiple: false, button: { text: kcsChurchAdmin.use } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && (a.sizes.large || a.sizes.full) || a).url;
			if (!/(^|\s)kcs-video-pic(\s|$)/.test(img.className)) { setSrc(ed, img, url); return; }
			box.classList.add('is-busy');
			var body = new FormData();
			body.append('action', 'kcs_play_picture');
			body.append('_ajax_nonce', kcsChurchAdmin.playNonce);
			body.append('id', a.id);
			fetch(kcsChurchAdmin.ajax, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (r) { setSrc(ed, img, r && r.success ? r.data.url : url); })
				.catch(function () { setSrc(ed, img, url); })
				.then(function () { box.classList.remove('is-busy'); });
		});
		frame.open();
	}
	function render() {
		var ed = editor();
		box.hidden = false;
		box.innerHTML = '';
		var head = el('div', 'kca-pics__head');
		head.appendChild(el('strong', '', L.title));
		head.appendChild(el('small', 'kcs-muted', L.help));
		box.appendChild(head);
		if (!ed || ed.isHidden()) { box.appendChild(el('p', 'kcs-muted', L.visual)); return; }
		var imgs = Array.prototype.slice.call(ed.getBody().querySelectorAll('img')).filter(function (img) {
			return !img.hasAttribute('data-mce-object') && !img.hasAttribute('data-wp-more') && !/(^|\s)(emoji|wp-smiley)(\s|$)/.test(img.className);
		});
		var n = 0;
		if (!imgs.length) { box.appendChild(el('p', 'kcs-muted', L.none)); return; }
		var list = el('div', 'kca-pics__list');
		imgs.forEach(function (img, i) {
			var item = el('div', 'kca-pics__item');
			var thumb = el('button', 'kca-pics__thumb');
			thumb.type = 'button';
			thumb.title = L.show;
			var t = el('img');
			t.src = img.getAttribute('src');
			t.alt = '';
			thumb.appendChild(t);
			thumb.addEventListener('click', function () {
				ed.selection.select(img);
				img.scrollIntoView({ block: 'center' });
				ed.focus();
			});
			item.appendChild(thumb);
			item.appendChild(el('span', 'kca-pics__name', label(img, /(^|\s)kcs-video-pic(\s|$)/.test(img.className) || img.getAttribute('height') === '56' ? 0 : ++n)));
			var row = el('span', 'kca-pics__btns');
			var change = el('button', 'kcs-btn kcs-btn--small', L.change);
			change.type = 'button';
			change.addEventListener('click', function () { choose(ed, img); });
			var remove = el('button', 'kcs-btn kcs-btn--small kcs-btn--ghost', L.remove);
			remove.type = 'button';
			remove.addEventListener('click', function () {
				if (!window.confirm(L.confirm)) { return; }
				var a = img.parentNode && img.parentNode.nodeName === 'A' && img.parentNode.childNodes.length === 1 ? img.parentNode : img;
				var p = a.parentNode && a.parentNode.nodeName === 'P' && a.parentNode.childNodes.length === 1 ? a.parentNode : a;
				ed.dom.remove(p);
				ed.undoManager.add();
				ed.save();
				render();
			});
			row.appendChild(change);
			row.appendChild(remove);
			item.appendChild(row);
			list.appendChild(item);
		});
		box.appendChild(list);
	}
	function later() { clearTimeout(timer); timer = setTimeout(render, 250); }
	function hook(ed) {
		if (!ed || ed.id !== 'kcs_campaign_content') { return; }
		ed.on('init SetContent change undo redo', later);
		if (ed.initialized) { later(); }
	}
	function start() {
		// The editor scripts load after this file, so wait for them.
		if (!window.tinymce) { return; }
		hook(editor());
		window.tinymce.on('AddEditor', function (e) { hook(e.editor); });
	}
	if (document.readyState === 'complete') { start(); } else { window.addEventListener('load', start); }
	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('#kcs_campaign_content-tmce, #kcs_campaign_content-html')) { setTimeout(render, 50); }
	});
})();

// Giving received: check pending online payments with the gateways automatically while the page
// is open (the "Check gateway" buttons stay as a backup).
(function () {
	var rows = document.querySelectorAll('tr[data-auto-check], tr[data-locate]');
	var box = document.querySelector('[data-auto-status]');
	var C = window.kcsChurchAdmin || {};
	if (!rows.length || !box || !C.rest) { return; }
	var running = false;
	function ids() {
		return Array.prototype.map.call(document.querySelectorAll('tr[data-auto-check], tr[data-locate]'), function (r) { return +r.getAttribute('data-pay'); });
	}
	function check() {
		var list = ids();
		if (running || !list.length || document.hidden) { return; }
		running = true;
		box.hidden = false;
		box.classList.add('is-busy');
		box.textContent = C.checking;
		fetch(C.rest + 'admin/payments/check', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.restNonce }, body: JSON.stringify({ ids: list }) })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				Object.keys(res.rows || {}).forEach(function (id) {
					var tr = document.querySelector('tr[data-pay="' + id + '"]');
					if (!tr) { return; }
					var cell = tr.querySelector('[data-pay-status]');
					var row = res.rows[id];
					if (cell && row.html) {
						var warn = cell.querySelector('.kca-warn');
						cell.innerHTML = row.html;
						if (warn) { cell.insertBefore(warn, cell.children[1] || null); }
					}
					var where = tr.querySelector('[data-pay-where]');
					if (row.place && where) {
						var ip = where.textContent.match(/ · IP .*$/);
						where.textContent = '📍 ' + row.place + (ip ? ip[0] : '');
						tr.removeAttribute('data-locate');
					}
					if (row.status !== 'pending') {
						tr.removeAttribute('data-auto-check');
						tr.classList.add('is-updated');
					}
				});
				box.classList.remove('is-busy');
				box.textContent = (res.completed ? C.confirmed.replace('%d', res.completed) + ' ' : '') + C.checked.replace('%s', res.time || '');
				if (res.completed) { box.classList.add('is-good'); }
			})
			.catch(function () { box.hidden = true; })
			.then(function () { running = false; });
	}
	setTimeout(check, 800);
	setInterval(check, 60000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { check(); } });
})();

// Bulk actions (Giving received, Partners): tick rows, then apply an action to all ticked.
document.querySelectorAll('[data-kca-bulk]').forEach(function (bulk) {
	// getAttribute: the form has a hidden field named "id", which hides the form's own .id.
	var formId = bulk.getAttribute('id');
	var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('[data-kca-pick][form="' + formId + '"]')); };
	var count = bulk.querySelector('[data-kca-count]');
	var all = bulk.querySelector('[data-kca-all]');
	var sel = bulk.querySelector('select[name="bulk"]');
	function update() {
		var n = picks().filter(function (c) { return c.checked; }).length;
		if (count) { count.textContent = n; }
		bulk.classList.toggle('has-picks', n > 0);
		if (all) { all.checked = n > 0 && n === picks().length; }
	}
	function pickWhere(test, action) {
		picks().forEach(function (c) { c.checked = test(c.closest('tr')); });
		if (action && sel) { sel.value = action; sel.dispatchEvent(new Event('change')); }
		update();
	}
	if (all) { all.addEventListener('change', function () { picks().forEach(function (c) { c.checked = all.checked; }); update(); }); }
	// Buttons like data-kca-pick-status="failed,cancelled" (and optional data-kca-action) tick rows by status.
	bulk.querySelectorAll('[data-kca-pick-status]').forEach(function (b) {
		var list = b.getAttribute('data-kca-pick-status').split(',');
		b.addEventListener('click', function () { pickWhere(function (tr) { return list.indexOf(tr.getAttribute('data-st')) !== -1; }, b.getAttribute('data-kca-action')); });
	});
	var blocked = bulk.querySelector('[data-kca-pick-blocked]');
	if (blocked) { blocked.addEventListener('click', function () { pickWhere(function (tr) { return tr.hasAttribute('data-blocked'); }, 'unrestrict'); }); }
	// Show the extra choices that belong to the chosen action only.
	function extras() {
		bulk.querySelectorAll('[data-kca-for]').forEach(function (el) { el.hidden = !sel || el.getAttribute('data-kca-for').split(',').indexOf(sel.value) === -1; });
	}
	if (sel) { sel.addEventListener('change', extras); extras(); }
	document.addEventListener('change', function (e) { if (e.target.matches && e.target.matches('[data-kca-pick][form="' + formId + '"]')) { update(); } });
	bulk.addEventListener('submit', function (e) { if (!picks().some(function (c) { return c.checked; })) { e.preventDefault(); } });
	update();
});

/* Phones: the long "can't find a payment" help box starts folded (tap to open). */
(function () {
	if (!window.matchMedia || !window.matchMedia('(max-width: 720px)').matches) { return; }
	document.querySelectorAll('details.kca-modes[open]').forEach(function (d) { d.removeAttribute('open'); });
})();
