/* Church Admin: picture pickers (WordPress media library) and password reveal. */
(function () {
	'use strict';
	var t = window.kcsChurchAdmin || {};

	function pick(onSelect) {
		if (!window.wp || !wp.media) { return; }
		var frame = wp.media({ title: t.title || 'Choose a picture', button: { text: t.use || 'Use this picture' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.large ? a.sizes.large.url : a.url);
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

		function sync() {
			var has = !!input.value && input.value !== '0';
			choose.textContent = has && field ? (t.change || 'Change') : (t.choose || 'Choose picture');
			remove.hidden = !has;
			if (preview) { preview.hidden = !has; }
		}
		choose.addEventListener('click', function () {
			pick(function (a, url) {
				input.value = mode === 'id' ? a.id : url;
				if (preview) { preview.src = url; }
				sync();
			});
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
