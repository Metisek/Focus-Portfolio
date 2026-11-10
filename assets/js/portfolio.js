/* Focus Portfolio: kolory z motywu, filtry i stronicowanie, układ masonry, lightbox. Bez zależności. */
(function () {
	'use strict';
	var conf = window.FP_CONF || { lightbox: 1, chunk: 60, i18n: {} };
	var t = conf.i18n || {};
	var filterHash = '';

	function setHash(h) {
		try { history.replaceState(null, '', location.pathname + location.search + h); } catch (e) {}
	}

	/* ---------- kolory z motywu: tło strony (pod portfolio) i kolor tekstu, dla list i zaznaczonych przycisków ---------- */
	document.querySelectorAll('.fp').forEach(function (fp) {
		var bg = '', el = fp;
		while (el && el.nodeType === 1) {
			var c = getComputedStyle(el).backgroundColor;
			if (c && c !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(c)) { bg = c; break; }
			el = el.parentElement;
		}
		fp.style.setProperty('--fp-page-bg', bg || '#fff');
		fp.style.setProperty('--fp-ink', getComputedStyle(fp).color);
	});

	/* ---------- filtry (formularz w adresie strony): okresy, autorzy, sortowanie, liczba na stronę ---------- */
	document.querySelectorAll('[data-fp-filters]').forEach(function (form) {
		var dirty = false;
		function submit() {
			// bez pustych pól i bez numeru strony: po zmianie filtrów wracamy na stronę 1
			form.querySelectorAll('input[type="search"]').forEach(function (i) { if (!i.value && i.name) i.disabled = true; });
			form.submit();
		}
		form.addEventListener('change', function (e) {
			var t = e.target;
			if (t.hasAttribute('data-fp-auto')) { submit(); return; }
			var y = t.getAttribute('data-fp-year');
			if (y) form.querySelectorAll('[data-fp-in="' + y + '"]').forEach(function (m) { m.checked = t.checked; });
			if (t.name === 'okres[]' || t.name === 'autor[]' || y) dirty = true;
			syncYears(form);
		});
		form.querySelectorAll('[data-fp-dd]').forEach(function (dd) {
			// zamknięcie listy z wprowadzonymi zmianami = zastosuj; przy otwarciu lista nie może wyjść poza ekran
			var panel = dd.querySelector('.fp-filter-panel');
			dd.addEventListener('toggle', function () {
				if (!dd.open) { if (dirty) submit(); return; }
				form.querySelectorAll('[data-fp-dd][open]').forEach(function (o) { if (o !== dd) o.open = false; });
				panel.classList.remove('is-right');
				var r = panel.getBoundingClientRect();
				if (r.right > window.innerWidth - 8) panel.classList.add('is-right');
				if (panel.getBoundingClientRect().left < 8) panel.classList.remove('is-right');
				var s = dd.querySelector('.fp-filter-search');
				if (s && window.innerWidth > 767) s.focus();
			});
			var clear = dd.querySelector('.fp-filter-clear');
			if (clear) clear.addEventListener('click', function () {
				dd.querySelectorAll('input[type="checkbox"]').forEach(function (b) { b.checked = false; b.indeterminate = false; });
				dirty = true;
				submit();
			});
			var search = dd.querySelector('.fp-filter-search');
			if (search) search.addEventListener('input', function () {
				var q = search.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
				dd.querySelectorAll('[data-fp-name]').forEach(function (l) {
					var n = l.getAttribute('data-fp-name').normalize('NFD').replace(/[̀-ͯ]/g, '');
					l.hidden = q && n.indexOf(q) < 0;
				});
			});
			if (search) search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
		});
		var q = form.querySelector('.fp-q input');
		if (q) {
			var timer;
			q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(submit, 700); });
		}
		// klik poza listą albo w przyciemnione tło (na telefonie) zamyka listę; Esc też
		document.addEventListener('click', function (e) {
			form.querySelectorAll('[data-fp-dd][open]').forEach(function (dd) { if (!dd.contains(e.target) || e.target === dd) dd.open = false; });
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') form.querySelectorAll('[data-fp-dd][open]').forEach(function (dd) { dd.open = false; dd.querySelector('summary').focus(); });
		});
		syncYears(form);
	});
	function syncYears(form) {
		form.querySelectorAll('[data-fp-year]').forEach(function (y) {
			var months = form.querySelectorAll('[data-fp-in="' + y.getAttribute('data-fp-year') + '"]');
			var on = Array.prototype.filter.call(months, function (m) { return m.checked; }).length;
			y.checked = on > 0 && on === months.length;
			y.indeterminate = on > 0 && on < months.length;
		});
	}

	/* ---------- galerie: kolejność zdjęć z sortowania (dla lightboxa) ---------- */
	document.querySelectorAll('.fp-gallery').forEach(function (gal) {
		gal._fpItems = Array.prototype.slice.call(gal.querySelectorAll('.fp-ph'));
	});

	/* ---------- masonry: zdjęcia po kolei do najniższej kolumny, więc czytane wierszami idą w kolejności sortowania ---------- */
	function ratio(it) { return parseFloat(it.style.getPropertyValue('--r')) || 1.5; }
	function colsFor(gal, fp) {
		var cs = getComputedStyle(fp);
		if (fp.classList.contains('fp-gc-auto')) {
			// automatycznie: tyle kolumn, ile zmieści się zdjęć o min. szerokości, w granicach min–max
			var gap = parseFloat(getComputedStyle(gal).columnGap) || 0;
			var min = parseInt(cs.getPropertyValue('--fp-gmin'), 10) || 1;
			var max = parseInt(cs.getPropertyValue('--fp-gmax'), 10) || 6;
			var tile = parseFloat(cs.getPropertyValue('--fp-gtile')) || 260;
			return Math.max(min, Math.min(max, Math.floor((gal.clientWidth + gap) / (tile + gap)) || 1));
		}
		return parseInt(cs.getPropertyValue('--fp-cols-now'), 10) || 3;
	}
	document.querySelectorAll('.fp-g-masonry .fp-gallery').forEach(function (gal) {
		var fp = gal.closest('.fp');
		var items = gal._fpItems;
		var even = fp.classList.contains('fp-even');
		var last = 0, wraps = [];
		if (!items.length) return;
		function layout() {
			var cols = colsFor(gal, fp);
			if (cols !== last) {
				last = cols;
				var heights = [];
				wraps = [];
				gal.innerHTML = '';
				gal.classList.add('fp-balanced');
				gal.setAttribute('data-cols', cols);
				for (var i = 0; i < cols; i++) { heights.push(0); var w = document.createElement('div'); w.className = 'fp-bcol'; wraps.push(w); gal.appendChild(w); }
				items.forEach(function (it) {
					var k = heights.indexOf(Math.min.apply(null, heights));
					wraps[k].appendChild(it);
					heights[k] += 1 / ratio(it);
				});
			}
			if (even) equalize();
		}
		// wyrównany dół: każda kolumna dostaje tę samą wysokość, pola zdjęć rozciągają się lub skracają (najwyżej o 20%)
		function equalize() {
			if (wraps.length < 2) return;
			var w = wraps[0].clientWidth;
			var gap = parseFloat(getComputedStyle(wraps[0]).rowGap) || 0;
			var cols = wraps.filter(function (c) { return c.children.length; }).map(function (c) {
				var its = Array.prototype.slice.call(c.children);
				return { its: its, img: its.reduce(function (s, it) { return s + w / ratio(it); }, 0), gaps: Math.max(0, its.length - 1) * gap };
			});
			if (cols.length < 2) return;
			var target = cols.reduce(function (s, c) { return s + c.img + c.gaps; }, 0) / cols.length;
			cols.forEach(function (c) {
				var s = c.img ? Math.max(0.8, Math.min(1.25, (target - c.gaps) / c.img)) : 1;
				c.its.forEach(function (it) {
					it.classList.toggle('fp-fill', Math.abs(s - 1) > 0.001);
					it.style.aspectRatio = Math.abs(s - 1) > 0.001 ? String(ratio(it) / s) : '';
				});
			});
		}
		layout();
		var tm;
		window.addEventListener('resize', function () { clearTimeout(tm); tm = setTimeout(layout, 150); });
	});

	if (!conf.lightbox) return;

	/* ---------- lightbox ---------- */
	var lb, img, pos, cap, go, orig, coverBtn, set = [], idx = 0, lastFocus = null;

	function build() {
		lb = document.createElement('div');
		lb.className = 'fp-lb';
		lb.hidden = true;
		lb.setAttribute('role', 'dialog');
		lb.setAttribute('aria-modal', 'true');
		lb.innerHTML =
			'<div class="fp-lb-top"><span class="fp-lb-pos"></span><button type="button" class="fp-lb-x" aria-label="' + (t.close || 'Zamknij') + '">×</button></div>' +
			'<div class="fp-lb-stage"><button type="button" class="fp-lb-nav fp-lb-prev" aria-label="' + (t.prev || '‹') + '">‹</button><img alt=""><button type="button" class="fp-lb-nav fp-lb-next" aria-label="' + (t.next || '›') + '">›</button></div>' +
			'<div class="fp-lb-bot"><span class="fp-lb-cap"></span><span class="fp-lb-actions">' +
			'<button type="button" class="fp-lb-cover" hidden>★ ' + (t.cover || 'Ustaw jako okładkę') + '</button> ' +
			'<a class="fp-lb-orig" target="_blank" rel="noopener" hidden>' + (t.orig || 'Oryginał') + '</a> ' +
			'<a class="fp-lb-go" hidden>' + (t.goto || '→') + '</a></span></div>';
		document.body.appendChild(lb);
		img = lb.querySelector('img');
		pos = lb.querySelector('.fp-lb-pos');
		cap = lb.querySelector('.fp-lb-cap');
		go = lb.querySelector('.fp-lb-go');
		orig = lb.querySelector('.fp-lb-orig');
		coverBtn = lb.querySelector('.fp-lb-cover');
		lb.querySelector('.fp-lb-x').addEventListener('click', close);
		lb.querySelector('.fp-lb-prev').addEventListener('click', function () { show(idx - 1); });
		lb.querySelector('.fp-lb-next').addEventListener('click', function () { show(idx + 1); });
		lb.addEventListener('click', function (e) {
			if (e.target === lb || e.target.classList.contains('fp-lb-stage')) close();
		});
		coverBtn.addEventListener('click', setCover);
		img.addEventListener('load', function () { lb.classList.remove('fp-lb-loading'); });
		var x0 = null;
		lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		lb.addEventListener('touchend', function (e) {
			if (x0 === null) return;
			var dx = e.changedTouches[0].clientX - x0;
			if (Math.abs(dx) > 40) show(idx + (dx < 0 ? 1 : -1));
			x0 = null;
		});
	}

	/** Tylko dla zalogowanego administratora: zapisuje to zdjęcie jako okładkę jego wydarzenia. */
	function setCover() {
		var a = set[idx];
		var key = a.getAttribute('data-fp-key') || '';
		var slash = key.indexOf('/');
		if (!conf.admin || slash < 0) return;
		var body = new URLSearchParams({
			action: 'fp_set_cover',
			nonce: conf.admin.nonce,
			cat: key.slice(0, slash),
			key: key.slice(slash + 1),
			f: a.getAttribute('data-fp-f') || ''
		});
		coverBtn.disabled = true;
		fetch(conf.admin.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (r) { coverBtn.textContent = (r.success ? '✓ ' : '✗ ') + (r.data && r.data.message ? r.data.message : ''); })
			.catch(function () { coverBtn.textContent = '✗ Nie udało się zapisać'; })
			.then(function () { coverBtn.disabled = false; });
	}

	function show(i) {
		idx = (i + set.length) % set.length;
		var a = set[idx];
		lb.classList.add('fp-lb-loading');
		img.src = a.getAttribute('data-fp-src') || a.href;
		img.alt = a.querySelector('img') ? a.querySelector('img').alt : '';
		pos.textContent = (idx + 1) + ' / ' + set.length;
		var parts = (a.getAttribute('data-fp-cap') || '').split(' · ');
		cap.innerHTML = '';
		var b = document.createElement('b');
		b.textContent = parts.shift() || '';
		cap.appendChild(b);
		if (parts.length) cap.appendChild(document.createTextNode(' · ' + parts.join(' · ')));
		var ev = a.getAttribute('data-fp-ev');
		go.hidden = !ev;
		if (ev) go.href = ev;
		orig.hidden = !conf.download;
		orig.href = a.href;
		orig.setAttribute('download', '');
		coverBtn.hidden = !(conf.admin && a.getAttribute('data-fp-key'));
		coverBtn.textContent = '★ ' + (t.cover || 'Ustaw jako okładkę');
		[idx + 1, idx - 1].forEach(function (n) {
			var p = set[(n + set.length) % set.length];
			if (p) (new Image()).src = p.getAttribute('data-fp-src') || p.href;
		});
		// każde zdjęcie ma własny adres z nazwą pliku: …/wydarzenie/#foto-DSC_0012.jpg
		setHash('#' + photoId(a));
	}

	/** Zdjęcia galerii w kolejności sortowania (nie w kolejności kolumn masonry). */
	function visibleItems(gallery) {
		return (gallery._fpItems || Array.prototype.slice.call(gallery.querySelectorAll('.fp-ph'))).filter(function (el) { return !el.classList.contains('fp-off'); });
	}

	/**
	 * Kotwica zdjęcia: „foto-” + nazwa pliku (bez znaków, które psują adres); powtórki w galerii dostają „-2”, „-3”.
	 * Liczy ją serwer (data-fp-id, FP_Links), więc linki z innych stron, np. z losowych zdjęć, trafiają w to samo zdjęcie.
	 */
	function photoId(a) {
		if (a.getAttribute('data-fp-id')) return a.getAttribute('data-fp-id');
		if (a._fpId) return a._fpId;
		var gal = a.closest('.fp-gallery'), seen = {};
		visibleItems(gal).forEach(function (el) {
			var n = (el.getAttribute('data-fp-n') || 'zdjecie').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[łŁ]/g, 'l').replace(/[^A-Za-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '');
			var id = 'foto-' + n, k = 2;
			while (seen[id]) id = 'foto-' + n + '-' + k++;
			seen[id] = 1;
			el._fpId = id;
		});
		return a._fpId;
	}

	function open(gallery, a) {
		if (!lb) build();
		set = visibleItems(gallery);
		lastFocus = document.activeElement;
		lb.hidden = false;
		document.body.classList.add('fp-lb-open');
		show(Math.max(0, set.indexOf(a)));
		lb.querySelector('.fp-lb-x').focus();
	}

	function close() {
		lb.hidden = true;
		img.removeAttribute('src');
		document.body.classList.remove('fp-lb-open');
		setHash(filterHash);
		if (lastFocus) lastFocus.focus();
	}

	function openFromHash() {
		var h = decodeURIComponent(location.hash.slice(1));
		var gal = document.querySelector('.fp-gallery');
		if (!h || !gal || (lb && !lb.hidden)) return;
		var items = visibleItems(gal), a = null;
		var m = /^zdjecie-(\d+)$/.exec(h); // stare linki z numerem zdjęcia
		if (m) a = items[Math.min(items.length, +m[1]) - 1];
		else if (h.indexOf('foto-') === 0) {
			document.querySelectorAll('.fp-gallery').forEach(function (g) {
				if (!a) a = visibleItems(g).filter(function (el) { return photoId(el) === h; })[0] || null;
			});
		}
		if (a) open(a.closest('.fp-gallery'), a);
	}
	openFromHash();
	window.addEventListener('hashchange', openFromHash);

	// faza przechwytywania: lightbox musi dostać kliknięcie przed animacją przejścia stron Phlox
	// i lightboxem Elementora, które inaczej przeniosłyby od razu pod adres pliku
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('.fp-gallery .fp-ph');
		if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 || a.closest('[data-fp-nolb]')) return;
		e.preventDefault();
		e.stopImmediatePropagation();
		open(a.closest('.fp-gallery'), a);
	}, true);

	document.addEventListener('keydown', function (e) {
		if (!lb || lb.hidden) return;
		if (e.key === 'Escape') close();
		else if (e.key === 'ArrowLeft') show(idx - 1);
		else if (e.key === 'ArrowRight') show(idx + 1);
	});
})();
