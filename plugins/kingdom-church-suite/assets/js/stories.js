/**
 * Share your story: choose text / pictures / YouTube link, shrink pictures on the phone before
 * sending, check the YouTube link, show upload progress — and a picture viewer on story pages.
 */
(function () {
	'use strict';

	var base = window.KCS || { restUrl: '/wp-json/kcs/v1/' };

	document.querySelectorAll('[data-kcs-story]').forEach(function (form) {
		var cfg = {};
		try { cfg = JSON.parse(form.getAttribute('data-config')) || {}; } catch (e) { cfg = {}; }
		var L = cfg.i18n || {};
		var msg = form.querySelector('.kcs-form-msg');
		var photos = [];
		var video = { ok: false, url: '' };

		function say(text, type) {
			msg.className = 'kcs-form-msg' + (text ? ' kcs-alert kcs-alert--' + (type || 'error') : '');
			msg.textContent = text || '';
		}
		function type() {
			var r = form.querySelector('[name="type"]:checked');
			return r ? r.value : 'text';
		}
		function showType() {
			var t = type();
			form.querySelectorAll('[data-story-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-story-panel') !== t; });
			var label = form.querySelector('[data-story-label]');
			if (label) { label.textContent = t === 'text' ? L.storyText : L.storyCap; }
			say('');
		}
		form.querySelectorAll('[name="type"]').forEach(function (r) { r.addEventListener('change', showType); });

		/* Pictures ------------------------------------------------------- */
		var input = form.querySelector('[data-story-input]');
		var drop = form.querySelector('[data-story-drop]');
		var thumbs = form.querySelector('[data-story-thumbs]');
		function shrink(file) {
			// Phones take huge photos: resize to 1600px and JPEG so uploads are quick and small.
			return new Promise(function (resolve) {
				if (!/^image\/(jpeg|png|webp|heic|heif)$/i.test(file.type) || !window.createImageBitmap && !window.FileReader) { resolve(file); return; }
				var img = new Image();
				var url = URL.createObjectURL(file);
				img.onload = function () {
					var max = 1600;
					var k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
					if (k === 1 && file.size < 1.5 * 1024 * 1024 && /jpeg/.test(file.type)) { URL.revokeObjectURL(url); resolve(file); return; }
					var c = document.createElement('canvas');
					c.width = Math.round(img.naturalWidth * k);
					c.height = Math.round(img.naturalHeight * k);
					c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
					URL.revokeObjectURL(url);
					c.toBlob(function (blob) {
						resolve(blob ? new File([blob], (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
					}, 'image/jpeg', 0.85);
				};
				img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
				img.src = url;
			});
		}
		function draw() {
			if (!thumbs) { return; }
			thumbs.innerHTML = '';
			photos.forEach(function (f, i) {
				var d = document.createElement('div');
				d.className = 'kcs-thumb';
				var im = document.createElement('img');
				im.src = URL.createObjectURL(f);
				im.alt = '';
				var x = document.createElement('button');
				x.type = 'button';
				x.className = 'kcs-thumb__x';
				x.setAttribute('aria-label', L.remove);
				x.textContent = '×';
				x.addEventListener('click', function () { photos.splice(i, 1); draw(); });
				d.appendChild(im);
				d.appendChild(x);
				thumbs.appendChild(d);
			});
			if (drop) { drop.classList.toggle('is-full', photos.length >= cfg.maxPhotos); }
		}
		function add(list) {
			say('');
			var files = Array.prototype.slice.call(list || []);
			files.forEach(function (f) {
				if (!/^image\//.test(f.type)) { say(L.notImage); return; }
				if (photos.length >= cfg.maxPhotos) { say(L.tooMany); return; }
				photos.push(f);
				shrink(f).then(function (small) {
					var i = photos.indexOf(f);
					if (i < 0) { return; } // Removed while it was being prepared.
					if (small.size > cfg.maxMb * 1024 * 1024) {
						photos.splice(i, 1);
						say(L.tooBig);
					} else {
						photos[i] = small;
					}
					draw();
				});
			});
			draw();
		}
		if (drop && input) {
			drop.addEventListener('click', function () { if (photos.length < cfg.maxPhotos) { input.click(); } else { say(L.tooMany); } });
			drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); drop.click(); } });
			input.addEventListener('change', function () { add(input.files); input.value = ''; });
			['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
			['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
			drop.addEventListener('drop', function (e) { add(e.dataTransfer && e.dataTransfer.files); });
		}

		/* YouTube link --------------------------------------------------- */
		var yt = form.querySelector('[data-story-yt]');
		var prev = form.querySelector('[data-story-yt-preview]');
		var timer;
		function ytId(u) {
			var m = String(u).match(/(?:youtube\.com\/(?:watch\?v=|embed\/|live\/|shorts\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
			return m ? m[1] : '';
		}
		function check() {
			var u = yt.value.trim();
			video = { ok: false, url: u };
			var id = ytId(u);
			var status = prev.querySelector('[data-story-yt-status]');
			if (!u) { prev.hidden = true; return Promise.resolve(); }
			prev.hidden = false;
			prev.className = 'kcs-yt-preview';
			if (!id) { prev.querySelector('img').hidden = true; status.textContent = L.notYt; prev.classList.add('is-bad'); return Promise.resolve(); }
			var img = prev.querySelector('img');
			img.hidden = false;
			img.src = 'https://i.ytimg.com/vi/' + id + '/mqdefault.jpg';
			status.textContent = L.checking;
			return fetch(base.restUrl + 'stories/check-video?url=' + encodeURIComponent(u), { credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
				.then(function (res) {
					if (yt.value.trim() !== u) { return; }
					if (!res.ok) { status.textContent = (res.d && res.d.message) || L.error; prev.classList.add('is-bad'); return; }
					video.ok = true;
					var s = res.d.seconds | 0;
					status.textContent = (res.d.title ? res.d.title + ' — ' : '') + (s ? L.videoLen.replace('%s', Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2)) : L.videoOk);
					prev.classList.add('is-ok');
				})
				.catch(function () { status.textContent = ''; video.ok = true; });
		}
		if (yt) {
			yt.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(check, 600); });
			yt.addEventListener('blur', function () { clearTimeout(timer); if (yt.value.trim() !== video.url) { check(); } });
		}

		/* Send ----------------------------------------------------------- */
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			say('');
			var t = type();
			if (t === 'photos' && !photos.length) { say(L.needPhoto); return; }
			if (t === 'video' && !yt.value.trim()) { say(L.needVideo); yt.focus(); return; }
			if (t === 'video' && !ytId(yt.value)) { say(L.notYt); yt.focus(); return; }
			if (!form.reportValidity()) { return; }
			var data = new FormData();
			['type', 'story', 'name', 'location', 'email', 'title', 'website'].forEach(function (k) {
				var el = form.querySelector('[name="' + k + '"]' + (k === 'type' ? ':checked' : ''));
				if (el) { data.append(k, el.value); }
			});
			data.append('consent', form.querySelector('[name="consent"]').checked ? '1' : '');
			if (t === 'video') { data.append('video_url', yt.value.trim()); }
			if (t === 'photos') { photos.forEach(function (f) { data.append('photos[]', f, f.name || 'photo.jpg'); }); }
			var btn = form.querySelector('button[type="submit"]');
			var bar = form.querySelector('[data-story-progress]');
			btn.disabled = true;
			bar.hidden = t !== 'photos';
			bar.firstElementChild.style.width = '0%';
			var xhr = new XMLHttpRequest();
			var guard = window.KCSGuard ? window.KCSGuard.headers(form) : Promise.resolve({});
			guard.then(function (extra) {
			xhr.open('POST', base.restUrl + 'stories');
			if (base.loggedIn && base.nonce) { xhr.setRequestHeader('X-WP-Nonce', base.nonce); }
			Object.keys(extra).forEach(function (k) { xhr.setRequestHeader(k, extra[k]); });
			xhr.upload.onprogress = function (ev) { if (ev.lengthComputable) { bar.firstElementChild.style.width = Math.round(ev.loaded / ev.total * 100) + '%'; } };
			xhr.onload = function () {
				btn.disabled = false;
				bar.hidden = true;
				var res = null;
				try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
				if (xhr.status >= 200 && xhr.status < 300 && res) {
					form.querySelector('[data-story-fields]').hidden = true;
					form.querySelector('[data-story-done]').hidden = false;
					form.querySelector('[data-story-done-text]').textContent = res.message || '';
					form.scrollIntoView({ behavior: 'smooth', block: 'center' });
				} else {
					say((res && res.message) || (xhr.status === 413 ? L.tooBig : L.error));
				}
			};
			xhr.onerror = function () { btn.disabled = false; bar.hidden = true; say(L.error); };
			xhr.onloadend = function () { if (window.KCSGuard) { window.KCSGuard.reset(form); } };
			xhr.send(data);
			});
		});
		var again = form.querySelector('[data-story-again]');
		if (again) {
			again.addEventListener('click', function () {
				form.reset();
				photos = [];
				draw();
				if (prev) { prev.hidden = true; }
				form.querySelector('[data-story-fields]').hidden = false;
				form.querySelector('[data-story-done]').hidden = true;
				showType();
			});
		}
		showType();
	});

	/* Picture viewer on story pages -------------------------------------- */
	var links = Array.prototype.slice.call(document.querySelectorAll('[data-kcs-lightbox]'));
	if (!links.length) { return; }
	var box = document.createElement('div');
	box.className = 'kcs-lightbox';
	box.hidden = true;
	box.innerHTML = '<button type="button" class="kcs-lightbox__x" aria-label="Close">×</button><button type="button" class="kcs-lightbox__nav is-prev" aria-label="Previous">‹</button><img alt=""><button type="button" class="kcs-lightbox__nav is-next" aria-label="Next">›</button>';
	document.body.appendChild(box);
	var at = 0;
	var img = box.querySelector('img');
	function show(i) {
		at = (i + links.length) % links.length;
		img.src = links[at].href;
		box.hidden = false;
		box.querySelectorAll('.kcs-lightbox__nav').forEach(function (b) { b.hidden = links.length < 2; });
	}
	links.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); show(i); }); });
	box.addEventListener('click', function (e) {
		if (e.target.classList.contains('is-prev')) { show(at - 1); return; }
		if (e.target.classList.contains('is-next')) { show(at + 1); return; }
		if (e.target !== img) { box.hidden = true; }
	});
	document.addEventListener('keydown', function (e) {
		if (box.hidden) { return; }
		if (e.key === 'Escape') { box.hidden = true; }
		if (e.key === 'ArrowLeft') { show(at - 1); }
		if (e.key === 'ArrowRight') { show(at + 1); }
	});
	var x0 = null;
	box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
	box.addEventListener('touchend', function (e) {
		if (x0 === null) { return; }
		var dx = e.changedTouches[0].clientX - x0;
		if (Math.abs(dx) > 50) { show(at + (dx < 0 ? 1 : -1)); }
		x0 = null;
	});
})();
