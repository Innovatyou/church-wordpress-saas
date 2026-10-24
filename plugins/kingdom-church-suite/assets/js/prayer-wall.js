/**
 * Community prayer wall: "I prayed" with live counts, encouragements, topics and search,
 * endless scrolling, a swipe-to-pray focus mode and the request form (no dependencies).
 */
(function () {
	'use strict';

	var wall = document.querySelector('[data-kcs-wall]');
	if (!wall) { return; }

	var base = window.KCS || { restUrl: '/wp-json/kcs/v1/', nonce: '' };
	var cfg = {};
	try { cfg = JSON.parse(wall.getAttribute('data-config')) || {}; } catch (e) { cfg = {}; }
	var L = cfg.i18n || {};
	var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var grid = wall.querySelector('[data-kpw-grid]');
	var empty = wall.querySelector('[data-kpw-empty]');
	var more = wall.querySelector('[data-kpw-more]');
	var newBtn = wall.querySelector('[data-kpw-new]');
	var state = { filter: cfg.filter || 'all', category: cfg.category || '', search: cfg.search || '', page: cfg.page || 1, pages: cfg.pages || 1, loading: false };
	var items = {};

	function api(path, method, body) {
		var headers = { 'Content-Type': 'application/json' };
		if (base.loggedIn && base.nonce) { headers['X-WP-Nonce'] = base.nonce; }
		return fetch(base.restUrl + path, { method: method || 'GET', headers: headers, credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined })
			.then(function (res) {
				return res.json().catch(function () { return null; }).then(function (data) {
					if (!res.ok || data === null) {
						var err = new Error((data && data.message) || L.error);
						err.status = res.status;
						throw err;
					}
					return data;
				});
			});
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function nl2br(s) { return esc(s).replace(/\n/g, '<br>'); }
	function fmt(s, a, b) { return String(s).replace('%1$d', a).replace('%2$d', b).replace('%d', a); }
	function num(n) { try { return Number(n).toLocaleString(); } catch (e) { return String(n); } }

	/* Prayed list (this browser) ----------------------------------------- */
	var prayed = {};
	try { (JSON.parse(localStorage.getItem('kpw_prayed') || '[]') || []).forEach(function (id) { prayed[id] = 1; }); } catch (e) {}
	function savePrayed() {
		try { localStorage.setItem('kpw_prayed', JSON.stringify(Object.keys(prayed).slice(-500))); } catch (e) {}
	}

	/* Cards -------------------------------------------------------------- */
	var shareSvg = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M16 6l-4-4-4 4M12 2v14"/></svg>';
	function cardHtml(i) {
		return '<article class="kpw-card' + (i.answered ? ' is-answered' : '') + ' is-new" id="prayer-' + i.id + '" data-id="' + i.id + '" style="--h:' + (i.hue || 0) + '">'
			+ '<header class="kpw-card__head"><span class="kpw-avatar" aria-hidden="true">' + esc(i.initial) + '</span><span class="kpw-who"><strong>' + esc(i.name) + '</strong><small>' + esc((i.city ? i.city + ' · ' : '') + i.ago) + '</small></span>'
			+ (i.category_label ? '<span class="kpw-chip">' + esc(i.category_label) + '</span>' : '') + '</header>'
			+ (i.answered ? '<p class="kpw-ribbon">🙌 ' + esc(L.answered) + '</p>' : '')
			+ '<p class="kpw-text">' + nl2br(i.request) + '</p>'
			+ (i.praise ? '<div class="kpw-praise"><strong>' + esc(L.praise) + '</strong><p>' + nl2br(i.praise) + '</p></div>' : '')
			+ '<footer class="kpw-card__foot"><button type="button" class="kpw-pray" data-kpw-pray aria-pressed="false"><span class="kpw-pray__icon" aria-hidden="true">🙏</span><span class="kpw-pray__label">' + esc(L.iPrayed) + '</span><b data-kpw-count>' + (i.prayers | 0) + '</b></button>'
			+ '<button type="button" class="kpw-icon-btn" data-kpw-enc aria-label="💬"><span aria-hidden="true">💬</span><b data-kpw-enc-count>' + (i.encouragements | 0) + '</b></button>'
			+ '<button type="button" class="kpw-icon-btn" data-kpw-share aria-label="↗">' + shareSvg + '</button></footer></article>';
	}
	function readItem(card) {
		// Rebuild what we need from a server-drawn card.
		var id = card.getAttribute('data-id');
		var who = card.querySelector('.kpw-who');
		var chip = card.querySelector('.kpw-chip');
		var praise = card.querySelector('.kpw-praise p');
		return {
			id: +id,
			name: who ? who.querySelector('strong').textContent : '',
			initial: (card.querySelector('.kpw-avatar') || {}).textContent || '',
			meta: who ? who.querySelector('small').textContent : '',
			category_label: chip ? chip.textContent : '',
			request: card.querySelector('.kpw-text').innerText,
			praise: praise ? praise.innerText : '',
			answered: card.classList.contains('is-answered'),
			hue: parseInt(card.style.getPropertyValue('--h'), 10) || 0
		};
	}
	function enhance(card) {
		var id = card.getAttribute('data-id');
		if (!items[id]) { items[id] = readItem(card); }
		if (prayed[id]) { setPrayed(card, true); }
		var text = card.querySelector('.kpw-text');
		if (text && !card.querySelector('.kpw-more-btn')) {
			requestAnimationFrame(function () {
				if (text.scrollHeight > text.clientHeight + 4) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'kpw-more-btn';
					b.textContent = L.readMore;
					b.addEventListener('click', function () {
						var open = card.classList.toggle('is-open');
						b.textContent = open ? L.readLess : L.readMore;
					});
					text.insertAdjacentElement('afterend', b);
				}
			});
		}
		if (!calm && window.matchMedia('(hover: hover)').matches) {
			card.addEventListener('pointermove', function (e) {
				if (e.pointerType !== 'mouse') { return; }
				var r = card.getBoundingClientRect();
				var x = (e.clientX - r.left) / r.width - 0.5;
				var y = (e.clientY - r.top) / r.height - 0.5;
				card.style.setProperty('--ry', (x * 7).toFixed(2) + 'deg');
				card.style.setProperty('--rx', (-y * 7).toFixed(2) + 'deg');
				card.style.setProperty('--gx', ((x + 0.5) * 100).toFixed(1) + '%');
				card.style.setProperty('--gy', ((y + 0.5) * 100).toFixed(1) + '%');
			});
			card.addEventListener('pointerleave', function () {
				card.style.setProperty('--rx', '0deg');
				card.style.setProperty('--ry', '0deg');
			});
		}
	}
	function setPrayed(card, on) {
		var btn = card.querySelector('[data-kpw-pray]');
		if (!btn) { return; }
		btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		btn.querySelector('.kpw-pray__label').textContent = on ? L.prayed : L.iPrayed;
		card.classList.toggle('is-prayed', on);
	}
	function bump(el) {
		if (!el) { return; }
		el.classList.remove('is-bump');
		void el.offsetWidth;
		el.classList.add('is-bump');
	}
	function burst(btn) {
		if (calm) { return; }
		var r = btn.getBoundingClientRect();
		var layer = document.createElement('span');
		layer.className = 'kpw-burst';
		layer.style.left = (r.left + 26) + 'px';
		layer.style.top = (r.top + r.height / 2) + 'px';
		var glyphs = ['🙏', '✨', '💛', '🕊️', '✨', '💜'];
		for (var n = 0; n < 10; n++) {
			var s = document.createElement('span');
			s.textContent = glyphs[n % glyphs.length];
			var a = (Math.PI * 2 * n) / 10 - Math.PI / 2;
			var d = 40 + Math.random() * 40;
			s.style.setProperty('--x', (Math.cos(a) * d).toFixed(0) + 'px');
			s.style.setProperty('--y', (Math.sin(a) * d - 30).toFixed(0) + 'px');
			s.style.animationDelay = (n * 18) + 'ms';
			layer.appendChild(s);
		}
		document.body.appendChild(layer);
		setTimeout(function () { layer.remove(); }, 1100);
	}
	function pray(id, card, btn) {
		if (prayed[id]) {
			if (btn) { bump(btn); }
			return Promise.resolve();
		}
		prayed[id] = 1;
		savePrayed();
		var count = card && card.querySelector('[data-kpw-count]');
		if (count) { count.textContent = (parseInt(count.textContent, 10) || 0) + 1; bump(count); }
		if (card) { setPrayed(card, true); }
		if (btn) { burst(btn); }
		if (navigator.vibrate) { try { navigator.vibrate(12); } catch (e) {} }
		var stat = wall.querySelector('[data-kpw-stat="prayers"]');
		if (stat) { stat.textContent = num((parseInt(stat.textContent.replace(/\D/g, ''), 10) || 0) + 1); }
		return api('prayer-requests/' + id + '/pray', 'POST')
			.then(function (res) { if (count && res && res.count) { count.textContent = res.count; } })
			.catch(function (err) {
				if (err.status === 404 && card) {
					delete prayed[id];
					savePrayed();
					card.classList.add('is-gone');
				}
			});
	}

	/* Loading ------------------------------------------------------------ */
	var sentinel = document.createElement('div');
	sentinel.className = 'kpw-sentinel';
	var moreBtn = document.createElement('button');
	moreBtn.type = 'button';
	moreBtn.className = 'kpw-btn kpw-btn--ghost';
	moreBtn.textContent = '…';
	if (more) {
		more.appendChild(sentinel);
		more.appendChild(moreBtn);
	}
	function skeletons(n) {
		var h = '';
		for (var k = 0; k < n; k++) { h += '<div class="kpw-card kpw-skel" aria-hidden="true"><span></span><span></span><span></span></div>'; }
		return h;
	}
	function updateMore() {
		if (!more) { return; }
		var has = state.page < state.pages;
		moreBtn.hidden = !has;
		moreBtn.textContent = (L.loadMore || 'More') + ' ↓';
	}
	function load(reset) {
		if (state.loading) { return; }
		state.loading = true;
		var page = reset ? 1 : state.page + 1;
		if (reset) {
			grid.innerHTML = skeletons(6);
			empty.hidden = true;
		} else {
			grid.insertAdjacentHTML('beforeend', skeletons(3));
		}
		var q = 'prayer-wall?per_page=' + (cfg.perPage || 12) + '&page=' + page + '&filter=' + encodeURIComponent(state.filter) + '&category=' + encodeURIComponent(state.category) + '&search=' + encodeURIComponent(state.search);
		api(q).then(function (res) {
			grid.querySelectorAll('.kpw-skel').forEach(function (s) { s.remove(); });
			if (reset) { grid.innerHTML = ''; }
			res.items.forEach(function (i, n) {
				if (document.getElementById('prayer-' + i.id)) { return; }
				items[i.id] = i;
				grid.insertAdjacentHTML('beforeend', cardHtml(i));
				var card = grid.lastElementChild;
				card.style.animationDelay = (n * 45) + 'ms';
				enhance(card);
			});
			state.page = page;
			state.pages = res.pages;
			empty.hidden = !!grid.children.length;
			empty.textContent = state.search || state.category ? L.noMatch : L.empty;
			if (res.stats) { stats(res.stats); }
			updateMore();
		}).catch(function () {
			grid.querySelectorAll('.kpw-skel').forEach(function (s) { s.remove(); });
		}).then(function () { state.loading = false; });
	}
	function syncUrl() {
		if (!window.history || !history.replaceState) { return; }
		var u = new URL(location.href);
		['wall', 'topic', 'pray_for', 'wpg'].forEach(function (k) { u.searchParams.delete(k); });
		if (state.filter !== 'all') { u.searchParams.set('wall', state.filter); }
		if (state.category) { u.searchParams.set('topic', state.category); }
		if (state.search) { u.searchParams.set('pray_for', state.search); }
		history.replaceState(null, '', u.toString());
	}
	moreBtn.addEventListener('click', function () { load(false); });
	if ('IntersectionObserver' in window) {
		new IntersectionObserver(function (entries) {
			if (entries[0].isIntersecting && state.page < state.pages && !state.loading) { load(false); }
		}, { rootMargin: '400px' }).observe(sentinel);
	}

	/* Toolbar ------------------------------------------------------------ */
	var toolbar = wall.querySelector('[data-kpw-toolbar]');
	if (toolbar) {
		toolbar.addEventListener('submit', function (e) { e.preventDefault(); });
		toolbar.addEventListener('click', function (e) {
			var tab = e.target.closest('[data-kpw-filter]');
			var topic = e.target.closest('[data-kpw-topic]');
			if (!tab && !topic) { return; }
			e.preventDefault();
			if (tab) {
				state.filter = tab.getAttribute('data-kpw-filter');
				toolbar.querySelectorAll('[data-kpw-filter]').forEach(function (b) { b.setAttribute('aria-selected', b === tab ? 'true' : 'false'); });
			} else {
				state.category = topic.getAttribute('data-kpw-topic');
				toolbar.querySelectorAll('[data-kpw-topic]').forEach(function (b) { b.setAttribute('aria-pressed', b === topic ? 'true' : 'false'); });
				topic.scrollIntoView({ block: 'nearest', inline: 'center', behavior: calm ? 'auto' : 'smooth' });
			}
			syncUrl();
			load(true);
		});
		var search = toolbar.querySelector('[data-kpw-search]');
		var timer;
		if (search) {
			search.addEventListener('input', function () {
				clearTimeout(timer);
				timer = setTimeout(function () {
					if (search.value.trim() === state.search) { return; }
					state.search = search.value.trim();
					syncUrl();
					load(true);
				}, 350);
			});
		}
	}

	/* Card buttons ------------------------------------------------------- */
	grid.addEventListener('click', function (e) {
		var card = e.target.closest('.kpw-card');
		if (!card || card.classList.contains('kpw-skel')) { return; }
		var id = card.getAttribute('data-id');
		var p = e.target.closest('[data-kpw-pray]');
		if (p) { pray(id, card, p); return; }
		if (e.target.closest('[data-kpw-enc]')) { openEnc(id, card); return; }
		var sh = e.target.closest('[data-kpw-share]');
		if (sh) { share(id, sh); }
	});
	function share(id, btn) {
		var url = (cfg.url || location.href.split('#')[0]) + '#prayer-' + id;
		var it = items[id] || {};
		var data = { title: document.title, text: (it.request || '').slice(0, 120), url: url };
		if (navigator.share) { navigator.share(data).catch(function () {}); return; }
		if (navigator.clipboard) {
			navigator.clipboard.writeText(url).then(function () { toast(L.copied); });
		}
	}
	var toastEl;
	function toast(text) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'kpw-toast';
			toastEl.setAttribute('role', 'status');
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = text;
		toastEl.classList.add('is-on');
		clearTimeout(toastEl._t);
		toastEl._t = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2200);
	}

	/* Stats -------------------------------------------------------------- */
	function stats(s) {
		Object.keys(s).forEach(function (k) {
			var el = wall.querySelector('[data-kpw-stat="' + k + '"]');
			if (el && el.textContent !== num(s[k])) { el.textContent = num(s[k]); bump(el); }
		});
	}
	function countUp() {
		if (calm) { return; }
		wall.querySelectorAll('[data-kpw-stat]').forEach(function (el) {
			var end = parseInt(el.textContent.replace(/\D/g, ''), 10) || 0;
			if (!end) { return; }
			var t0 = null;
			function step(t) {
				if (!t0) { t0 = t; }
				var k = Math.min(1, (t - t0) / 1200);
				el.textContent = num(Math.round(end * (1 - Math.pow(1 - k, 3))));
				if (k < 1) { requestAnimationFrame(step); }
			}
			requestAnimationFrame(step);
		});
	}

	/* Live updates ------------------------------------------------------- */
	function poll() {
		if (document.hidden) { return; }
		var ids = [];
		var max = 0;
		grid.querySelectorAll('.kpw-card[data-id]').forEach(function (c) {
			var id = +c.getAttribute('data-id');
			ids.push(id);
			if (id > max) { max = id; }
		});
		api('prayer-wall/live?ids=' + ids.slice(0, 100).join(',') + '&since=' + max).then(function (res) {
			Object.keys(res.counts || {}).forEach(function (id) {
				var card = document.getElementById('prayer-' + id);
				if (!card) { return; }
				var c = card.querySelector('[data-kpw-count]');
				var e = card.querySelector('[data-kpw-enc-count]');
				if (c && +c.textContent < res.counts[id][0]) { c.textContent = res.counts[id][0]; bump(c); card.classList.add('is-glow'); setTimeout(function () { card.classList.remove('is-glow'); }, 1600); }
				if (e && +e.textContent !== res.counts[id][1]) { e.textContent = res.counts[id][1]; bump(e); }
			});
			if (res.stats) { stats(res.stats); }
			if (res['new'] > 0 && state.filter !== 'praise' && max) {
				newBtn.textContent = fmt(res['new'] === 1 ? L.newOne : L.newMany, res['new']);
				newBtn.hidden = false;
			}
		}).catch(function () {});
	}
	setInterval(poll, 30000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });
	newBtn.addEventListener('click', function () {
		newBtn.hidden = true;
		state.filter = 'all';
		wall.querySelectorAll('[data-kpw-filter]').forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-kpw-filter') === 'all' ? 'true' : 'false'); });
		syncUrl();
		load(true);
		grid.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
	});

	/* Dialogs ------------------------------------------------------------ */
	function open(d) {
		if (!d) { return; }
		if (d.showModal) { d.showModal(); } else { d.setAttribute('open', ''); }
		document.documentElement.classList.add('kpw-lock');
	}
	function close(d) {
		if (!d) { return; }
		if (d.close) { d.close(); } else { d.removeAttribute('open'); }
	}
	wall.querySelectorAll('dialog').forEach(function (d) {
		d.addEventListener('close', function () { document.documentElement.classList.remove('kpw-lock'); });
		d.addEventListener('click', function (e) {
			if (e.target === d && !d.classList.contains('kpw-focus')) { close(d); }
			if (e.target.closest('[data-kpw-close]')) { close(d); }
		});
	});

	/* Request form ------------------------------------------------------- */
	var formDialog = wall.querySelector('[data-kpw-form-dialog]');
	var form = wall.querySelector('[data-kpw-form]');
	wall.querySelectorAll('[data-kpw-open]').forEach(function (b) {
		b.addEventListener('click', function () {
			form.querySelector('[data-kpw-form-fields]').hidden = false;
			form.querySelector('[data-kpw-form-done]').hidden = true;
			open(formDialog);
			setTimeout(function () { form.querySelector('textarea').focus(); }, 60);
		});
	});
	var src = form && form.querySelector('[data-kpw-count-src]');
	var counter = form && form.querySelector('[data-kpw-counter]');
	if (src && counter) { src.addEventListener('input', function () { counter.textContent = src.value.length + ' / 2000'; }); }
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = form.querySelector('button[type="submit"]');
			var msg = form.querySelector('.kcs-form-msg');
			var data = {};
			new FormData(form).forEach(function (v, k) { data[k] = v; });
			data.is_private = data.is_private === '1' ? 1 : 0;
			btn.disabled = true;
			msg.className = 'kcs-form-msg';
			msg.textContent = '';
			api('prayer-requests', 'POST', data).then(function (res) {
				form.querySelector('[data-kpw-form-fields]').hidden = true;
				form.querySelector('[data-kpw-form-done]').hidden = false;
				form.querySelector('[data-kpw-done-text]').textContent = data.is_private ? L.privateDone : (res.status === 'approved' ? L.liveDone : L.pending);
				form.reset();
				if (counter) { counter.textContent = '0 / 2000'; }
				if (!data.is_private && res.status === 'approved') { load(true); }
			}).catch(function (err) {
				msg.className = 'kcs-form-msg kcs-alert kcs-alert--error';
				msg.textContent = err.message;
			}).then(function () { btn.disabled = false; });
		});
	}

	/* Encouragements ----------------------------------------------------- */
	var encDialog = wall.querySelector('[data-kpw-enc-dialog]');
	var encForm = wall.querySelector('[data-kpw-enc-form]');
	var encList = wall.querySelector('[data-kpw-enc-list]');
	var encFor = null;
	function encLi(e) { return '<li><p>' + nl2br(e.message) + '</p><small>— ' + esc(e.name + ' · ' + e.ago) + '</small></li>'; }
	function openEnc(id, card) {
		encFor = { id: id, card: card };
		var it = items[id] || {};
		wall.querySelector('[data-kpw-enc-name]').textContent = it.name || '';
		wall.querySelector('[data-kpw-enc-quote]').textContent = (it.request || '').length > 220 ? it.request.slice(0, 220) + '…' : (it.request || '');
		encList.innerHTML = '<li class="kpw-skel-line"></li><li class="kpw-skel-line"></li>';
		encForm.querySelector('.kcs-form-msg').textContent = '';
		encForm.querySelector('.kcs-form-msg').className = 'kcs-form-msg';
		open(encDialog);
		api('prayer-requests/' + id + '/encouragements').then(function (list) {
			encList.innerHTML = list.length ? list.map(encLi).join('') : '<li class="kpw-encs__none">' + esc(L.noEnc) + '</li>';
		}).catch(function () { encList.innerHTML = ''; });
	}
	if (encForm) {
		encForm.addEventListener('click', function (e) {
			var q = e.target.closest('[data-kpw-quick]');
			if (q) {
				var ta = encForm.querySelector('textarea');
				ta.value = q.textContent;
				ta.focus();
			}
		});
		encForm.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!encFor) { return; }
			var btn = encForm.querySelector('button:not([type])') || encForm.querySelector('.kpw-btn');
			var msg = encForm.querySelector('.kcs-form-msg');
			var data = {};
			new FormData(encForm).forEach(function (v, k) { data[k] = v; });
			btn.disabled = true;
			api('prayer-requests/' + encFor.id + '/encouragements', 'POST', data).then(function (res) {
				encForm.querySelector('textarea').value = '';
				if (res.pending) {
					msg.className = 'kcs-form-msg kcs-alert kcs-alert--success';
					msg.textContent = res.message;
					return;
				}
				var none = encList.querySelector('.kpw-encs__none');
				if (none) { none.remove(); }
				if (res.item) { encList.insertAdjacentHTML('afterbegin', encLi(res.item)); encList.firstElementChild.classList.add('is-new'); }
				var c = encFor.card && encFor.card.querySelector('[data-kpw-enc-count]');
				if (c) { c.textContent = res.count; bump(c); }
			}).catch(function (err) {
				msg.className = 'kcs-form-msg kcs-alert kcs-alert--error';
				msg.textContent = err.message;
			}).then(function () { btn.disabled = false; });
		});
	}

	/* Focus mode: pray for people one by one ----------------------------- */
	var focus = wall.querySelector('[data-kpw-focus-dialog]');
	var deck = wall.querySelector('[data-kpw-deck]');
	var queue = [];
	var at = 0;
	var prayedNow = 0;
	function focusCard(it, depth) {
		return '<article class="kpw-fcard" style="--h:' + (it.hue || 0) + ';--d:' + depth + '" data-id="' + it.id + '">'
			+ '<header><span class="kpw-avatar">' + esc(it.initial) + '</span><span class="kpw-who"><strong>' + esc(it.name) + '</strong><small>' + esc(it.category_label || '') + '</small></span></header>'
			+ '<p>' + nl2br((it.request || '').length > 520 ? it.request.slice(0, 520) + '…' : it.request) + '</p>'
			+ '<footer><span class="kpw-breath" aria-hidden="true"></span><small>' + esc(L.breathe) + '</small></footer></article>';
	}
	function drawDeck() {
		var left = queue.slice(at, at + 3);
		if (!left.length) {
			deck.innerHTML = '<div class="kpw-fdone"><span>🕊️</span><h3>' + esc(L.doneTitle) + '</h3><p>' + esc(fmt(L.doneText, prayedNow)) + '</p></div>';
			wall.querySelector('[data-kpw-focus-btns]').hidden = true;
			wall.querySelector('[data-kpw-focus-hint]').hidden = true;
			wall.querySelector('[data-kpw-focus-progress]').textContent = '';
			return;
		}
		deck.innerHTML = left.map(function (it, n) { return focusCard(it, n); }).reverse().join('');
		wall.querySelector('[data-kpw-focus-progress]').textContent = fmt(L.of, at + 1, queue.length);
		drag(deck.lastElementChild);
	}
	function next(dir) {
		var top = deck.lastElementChild;
		if (!top || !top.classList.contains('kpw-fcard')) { return; }
		var id = top.getAttribute('data-id');
		if (dir > 0) {
			prayedNow++;
			pray(id, document.getElementById('prayer-' + id), null);
		}
		top.classList.add(dir > 0 ? 'is-out-right' : 'is-out-left');
		at++;
		setTimeout(drawDeck, calm ? 0 : 320);
	}
	function drag(card) {
		if (!card) { return; }
		var x0 = 0;
		var dx = 0;
		var on = false;
		card.addEventListener('pointerdown', function (e) {
			on = true;
			x0 = e.clientX;
			dx = 0;
			card.setPointerCapture(e.pointerId);
			card.classList.add('is-drag');
		});
		card.addEventListener('pointermove', function (e) {
			if (!on) { return; }
			dx = e.clientX - x0;
			card.style.transform = 'translateX(' + dx + 'px) rotate(' + (dx / 18) + 'deg)';
			card.style.setProperty('--yes', Math.max(0, Math.min(1, dx / 120)));
			card.style.setProperty('--no', Math.max(0, Math.min(1, -dx / 120)));
		});
		function end() {
			if (!on) { return; }
			on = false;
			card.classList.remove('is-drag');
			if (Math.abs(dx) > 90) {
				card.style.transform = '';
				next(dx > 0 ? 1 : -1);
			} else {
				card.style.transform = '';
				card.style.setProperty('--yes', 0);
				card.style.setProperty('--no', 0);
			}
		}
		card.addEventListener('pointerup', end);
		card.addEventListener('pointercancel', end);
	}
	wall.querySelectorAll('[data-kpw-focus]').forEach(function (b) {
		b.addEventListener('click', function () {
			var all = [];
			grid.querySelectorAll('.kpw-card[data-id]').forEach(function (c) {
				var it = items[c.getAttribute('data-id')];
				if (it && !it.answered) { all.push(it); }
			});
			var fresh = all.filter(function (it) { return !prayed[it.id]; });
			queue = (fresh.length ? fresh : all).slice(0, 20);
			at = 0;
			prayedNow = 0;
			wall.querySelector('[data-kpw-focus-btns]').hidden = false;
			wall.querySelector('[data-kpw-focus-hint]').hidden = false;
			drawDeck();
			open(focus);
		});
	});
	if (focus) {
		focus.querySelector('[data-kpw-focus-pray]').addEventListener('click', function () { next(1); });
		focus.querySelector('[data-kpw-skip]').addEventListener('click', function () { next(-1); });
		focus.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { next(1); }
			if (e.key === 'ArrowLeft') { next(-1); }
		});
	}

	/* The person who asked ----------------------------------------------- */
	var mine = wall.querySelector('[data-kpw-mine]');
	if (mine) {
		var mf = mine.querySelector('[data-kpw-mine-form]');
		if (mf) {
			mf.addEventListener('submit', function (e) {
				e.preventDefault();
				var action = e.submitter ? e.submitter.value : 'praise';
				if (action === 'remove' && !window.confirm(L.confirmOff)) { return; }
				var msg = mf.querySelector('.kcs-form-msg');
				api('prayer-requests/mine', 'POST', { token: mine.getAttribute('data-token'), 'do': action, praise: mf.querySelector('[name="praise"]').value })
					.then(function (res) {
						msg.className = 'kcs-form-msg kcs-alert kcs-alert--success';
						msg.textContent = res.message;
						if (action === 'answered' && !calm) { burst(e.submitter || mf); }
					})
					.catch(function (err) {
						msg.className = 'kcs-form-msg kcs-alert kcs-alert--error';
						msg.textContent = err.message;
					});
			});
		}
		setTimeout(function () { mine.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' }); }, 200);
	}

	/* Start -------------------------------------------------------------- */
	grid.querySelectorAll('.kpw-card').forEach(enhance);
	updateMore();
	if ('IntersectionObserver' in window) {
		var heroStats = wall.querySelector('.kpw-stats');
		if (heroStats) {
			var once = new IntersectionObserver(function (en) {
				if (en[0].isIntersecting) { countUp(); once.disconnect(); }
			});
			once.observe(heroStats);
		}
	}
	var hash = (location.hash.match(/^#prayer-(\d+)$/) || [])[1];
	if (hash) {
		var target = document.getElementById('prayer-' + hash);
		if (target) {
			setTimeout(function () {
				target.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'center' });
				target.classList.add('is-glow');
			}, 300);
		}
	}
})();
