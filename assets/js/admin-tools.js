/* Focus Portfolio: fotografowie i autorzy ze stopki (OCR w przeglądarce, tesseract.js). */
jQuery(function ($) {
	'use strict';
	var A = window.FP_ADMIN || {};
	var $log = $('#fp-log');
	function log(msg, isErr) { $log.text(msg).toggleClass('fp-err', !!isErr); }
	function post(action, data) {
		return $.post(A.ajax, $.extend({ action: action, nonce: A.nonce }, data || {}));
	}

	/* ---------- autorzy ze stopki ---------- */
	var ocrStop = false;
	var STOP_WORDS = { FOTO: 1, PHOTO: 1, FOT: 1, PHOTOGRAPHY: 1, FOTOGRAFIA: 1 };
	// słowa ze stopki, które nie są nazwiskiem (Ustawienia → Autorzy, np. logo organizacji); OCR myli O/0 i dokleja kreskę („FOCUSI”)
	var SKIP = (A.ocrSkip || []).map(function (w) { return w.replace(/0/g, 'O'); });
	function skipWord(w) {
		var v = w.replace(/0/g, 'O');
		return STOP_WORDS[v] || SKIP.some(function (s) { return v.indexOf(s) === 0 || lev(v, s) <= 1; });
	}

	function loadScript(src) {
		return new Promise(function (ok, fail) {
			if (window.Tesseract) return ok();
			var s = document.createElement('script');
			s.src = src;
			s.onload = ok;
			s.onerror = function () { fail(new Error('Nie udało się pobrać biblioteki OCR (sprawdź połączenie lub blokery).')); };
			document.head.appendChild(s);
		});
	}
	function loadImg(src) {
		return new Promise(function (ok, fail) {
			var im = new Image();
			im.onload = function () { ok(im); };
			im.onerror = function () { fail(new Error('nie udało się wczytać zdjęcia')); };
			im.src = src;
		});
	}
	function plain(s) { return s.toLowerCase().replace(/ł/g, 'l').normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	function lev(a, b) {
		var d = [], i, j;
		for (i = 0; i <= a.length; i++) d[i] = [i];
		for (j = 0; j <= b.length; j++) d[0][j] = j;
		for (i = 1; i <= a.length; i++) for (j = 1; j <= b.length; j++) {
			d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
		}
		return d[a.length][b.length];
	}
	function titleCase(s) {
		return s.toLowerCase().replace(/(^|[\s\-])(\S)/g, function (m, p, c) { return p + c.toUpperCase(); });
	}

	/**
	 * Stopka typu „LOGO | IMIĘ NAZWISKO” (imię bywa nad nazwiskiem): bierzemy słowa pisane wersalikami
	 * i dopasowujemy do autorów, których wtyczka już zna (literówki OCR do ~2 znaków).
	 */
	function parseAuthor(text, known) {
		var words = (text.match(/[A-ZĄĆĘŁŃÓŚŹŻ0]{3,}(?:-[A-ZĄĆĘŁŃÓŚŹŻ]{2,})?/g) || []).filter(function (w) {
			return /[A-ZĄĆĘŁŃÓŚŹŻ]{3}/.test(w) && !skipWord(w);
		}).slice(0, 4);
		if (!words.length) return { name: '', known: false };

		// najpierw znani autorzy: pary słów w obu kolejnościach, a przy jednym słowie także samo imię/nazwisko
		var best = null;
		function consider(c) {
			known.forEach(function (k) {
				var dist = lev(plain(c), plain(k));
				if (dist <= Math.max(2, Math.floor(k.length / 6)) && (!best || dist < best.dist)) best = { name: k, dist: dist };
			});
		}
		for (var i = 0; i < words.length; i++) for (var j = 0; j < words.length; j++) {
			if (i !== j) consider(words[i] + ' ' + words[j]);
		}
		if (best) return { name: best.name, known: true };
		if (words.length === 1) {
			var hits = known.filter(function (k) {
				return plain(k).split(/\s+/).some(function (part) { return lev(part, plain(words[0])) <= 1; });
			});
			return hits.length === 1 ? { name: hits[0], known: true } : { name: titleCase(words[0]), known: false, partial: true };
		}
		// nowy autor: imię na początek (podpis bywa czytany jako „NAZWISKO IMIĘ”)
		var a = words[0], b = words[1];
		if (!isFirstName(a) && isFirstName(b)) { var x = a; a = b; b = x; }
		return { name: titleCase(a + ' ' + b), known: false };
	}

	var FIRST = ('ADAM ADRIAN AGATA AGNIESZKA ALEKSANDER ALEKSANDRA ALICJA ALINA AMELIA ANDRZEJ ANETA ANIA ANNA ANTONI ANTONINA ARTUR ' +
		'BARBARA BARTEK BARTOSZ BASIA BEATA BENIAMIN BLANKA BOGDAN BOGUMIŁA CEZARY DAMIAN DANIEL DANIELA DARIA DARIUSZ DAWID DOMINIK DOMINIKA DOROTA ' +
		'EDYTA EMIL EMILIA EMILIAN ERYK EWA EWELINA FILIP FRANCISZEK GABRIEL GABRIELA GRZEGORZ HANNA HUBERT IGA IGOR IGNACY IRENA IWONA IZABELA ' +
		'JACEK JAKUB JAN JANEK JANINA JERZY JOANNA JULIA JULIAN JUSTYNA KACPER KAJA KAMIL KAMILA KAROL KAROLINA KATARZYNA KINGA KLAUDIA KONRAD KORNELIA KRYSTIAN KRZYSZTOF KSAWERY KUBA ' +
		'LAURA LENA LEON ŁUKASZ MACIEJ MAGDA MAGDALENA MAJA MAKSYMILIAN MALWINA MARCEL MARCIN MAREK MARIA MARIUSZ MARTA MARTYNA MATEUSZ MICHAŁ MIKOŁAJ MILENA MIŁOSZ MONIKA ' +
		'NADIA NATALIA NIKOLA NORBERT OLA OLIWER OLIWIA OSKAR PATRYCJA PATRYK PAULINA PAWEŁ PIOTR PRZEMYSŁAW RAFAŁ ROBERT ROKSANA SANDRA SEBASTIAN SZYMON ' +
		'TOMASZ TYMEK TYMON TYMOTEUSZ URSZULA WERONIKA WIKTOR WIKTORIA WITOLD WOJCIECH ZOFIA ZOSIA ZUZANNA ŻANETA').split(' ');
	function isFirstName(w) { return FIRST.indexOf(w) >= 0; }

	/** Wycinek z podpisem: dolny pas (domyślnie 15% wysokości), lewa połowa szerokości; invert = biały napis na ciemnym tle. */
	function footer(img, invert) {
		var wp = Math.max(20, Math.min(100, +$('#fp-ocr-w').val() || 50)) / 100;
		var hp = Math.max(5, Math.min(40, +$('#fp-ocr-h').val() || 15)) / 100;
		var align = $('#fp-ocr-align').val() || 'left';
		var w = Math.round(img.naturalWidth * (align === 'full' ? 1 : wp));
		var h = Math.round(img.naturalHeight * hp);
		var x = align === 'right' ? img.naturalWidth - w : 0;
		var scale = Math.min(3, 2000 / w);
		var c = document.createElement('canvas');
		c.width = Math.round(w * scale);
		c.height = Math.round(h * scale);
		var g = c.getContext('2d');
		g.drawImage(img, x, img.naturalHeight - h, w, h, 0, 0, c.width, c.height);
		var d = g.getImageData(0, 0, c.width, c.height), px = d.data;
		for (var i = 0; i < px.length; i += 4) {
			var y = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
			y = invert ? 255 - y : y;
			px[i] = px[i + 1] = px[i + 2] = y;
		}
		g.putImageData(d, 0, 0);
		return c;
	}

	/* wybór katalogów: „Wszystkie” albo konkretne */
	$('#fp-ocr-dirs').on('change', 'input', function () {
		if ($(this).hasClass('fp-ocr-dir-all')) {
			if (this.checked) $('.fp-ocr-dir').prop('checked', false);
		} else {
			$('.fp-ocr-dir-all').prop('checked', !$('.fp-ocr-dir:checked').length);
			if (this.closest('summary')) $(this).closest('details').find('.fp-ocr-sub input').prop('checked', this.checked);
		}
	});
	function selectedDirs() {
		if ($('.fp-ocr-dir-all').prop('checked')) return [];
		return $('.fp-ocr-dir:checked').map(function () { return this.value; }).get();
	}

	/* ---------- rejestr fotografów ---------- */
	var $people = $('#fp-people'), $ptb = $people.find('tbody');
	function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ł/g, 'l'); }
	function lastName(n) { var p = String(n).replace(/@\S+/g, '').trim().split(/\s+/); return p[p.length - 1] || n; }

	// wyszukiwarka, filtr i sortowanie (bez przeładowania; zapis wysyła wszystkie wiersze, także ukryte)
	function drawPeople() {
		var q = fold($('#fp-p-q').val()), f = $('#fp-p-filter').val(), s = $('#fp-p-sort').val();
		var rows = $ptb.children('tr').get();
		rows.sort(function (a, b) {
			var na = +a.getAttribute('data-n'), nb = +b.getAttribute('data-n');
			var A = a.getAttribute('data-name') || '', B = b.getAttribute('data-name') || '';
			if (s === 'n-desc' && na !== nb) return nb - na;
			if (s === 'n-asc' && na !== nb) return na - nb;
			if (s === 'last') { var c = lastName(A).localeCompare(lastName(B), 'pl'); if (c) return c; }
			return A.localeCompare(B, 'pl');
		});
		var shown = 0;
		rows.forEach(function (tr) {
			var ok = !q || fold(tr.getAttribute('data-q') + ' ' + $(tr).find('.fp-user-pick').val()).indexOf(q) >= 0;
			if (ok && f === 'saved') ok = tr.getAttribute('data-state') === 'saved';
			if (ok && f === 'new') ok = tr.getAttribute('data-state') === 'new';
			if (ok && f === 'nouser') ok = !(+$(tr).find('.fp-user-id').val());
			if (ok && f === 'noprofile') ok = !$(tr).find('.fp-pf input').filter(function () { return this.value; }).length;
			if (ok && f === 'hidden') ok = $(tr).find('[name$="[hidden]"]').prop('checked');
			tr.hidden = !ok;
			if (ok) shown++;
			$ptb.append(tr);
		});
		$('.fp-p-shown').text(shown === rows.length ? '' : 'Pokazano ' + shown + ' z ' + rows.length);
		$('.fp-p-empty').prop('hidden', shown > 0);
	}
	$('#fp-p-q').on('input', drawPeople);
	$('#fp-p-filter, #fp-p-sort').on('change', drawPeople);
	if ($people.length) drawPeople();

	// konto na stronie: wpisany tekst → id z listy podpowiedzi
	$people.on('input change', '.fp-user-pick', function () {
		var v = this.value, id = 0;
		$('#fp-users option').each(function () { if (this.value === v) { id = +this.getAttribute('data-id'); return false; } });
		$(this).siblings('.fp-user-id').val(id);
		this.classList.toggle('is-bad', !!v && !id);
	});

	// usuń (po zapisie): wybór, czy tylko dane osoby, czy też podpis na jej zdjęciach; drugi klik = przywróć
	$people.on('click', '.fp-p-delbtn', function () {
		var $tr = $(this).closest('tr'), del = !$tr.hasClass('is-del'), $mode = $tr.find('.fp-p-delmode');
		$tr.toggleClass('is-del', del);
		$mode.prop('hidden', !del);
		$tr.find('.fp-p-del').val(del ? $mode.val() : 0);
		$(this).text(del ? 'Przywróć' : 'Usuń');
	});
	$people.on('change', '.fp-p-delmode', function () { $(this).closest('tr').find('.fp-p-del').val(this.value); });

	// nowy pusty wiersz na górze listy
	$('#fp-person-add').on('click', function () {
		var i = $ptb.children().length + 1000;
		var pf = function (k, l, type, ph) { return '<label class="fp-pf"><span>' + l + '</span><input type="' + type + '" name="people[' + i + '][' + k + ']" placeholder="' + ph + '"></label>'; };
		var $tr = $('<tr data-state="saved" data-n="0" data-q="" data-name="">').append(
			$('<td class="fp-p-name">').html('<input type="hidden" name="people[' + i + '][saved]" value="1"><input type="text" name="people[' + i + '][name]" placeholder="Imię Nazwisko"><span class="fp-p-badge">nowa osoba</span>'),
			$('<td>').html('<input type="text" class="fp-wide" name="people[' + i + '][aliases]">'),
			$('<td class="fp-p-prof">').html(pf('ig', 'IG', 'text', 'nazwa konta') + pf('fb', 'FB', 'url', 'https://facebook.com/…') + pf('www', 'WWW', 'url', 'https://…')),
			$('<td>').html('<input type="hidden" name="people[' + i + '][user_id]" value="0" class="fp-user-id"><input type="search" class="fp-user-pick" list="fp-users" placeholder="Szukaj konta…" aria-label="Konto na stronie">'),
			$('<td class="num">').text('0'),
			$('<td>').html('<input type="checkbox" name="people[' + i + '][hidden]" value="1" aria-label="Ukryj w filtrze autorów">'),
			$('<td>')
		);
		$ptb.prepend($tr);
		$('#fp-p-q').val('');
		$('#fp-p-filter').val('');
		$ptb.children('tr').prop('hidden', false);
		$tr.find('input').first().trigger('focus');
	});

	$('#fp-ocr-run').on('click', async function () {
		var $run = $(this), $stop = $('#fp-ocr-stop'), $prog = $('#fp-ocr-progress').removeClass('fp-err');
		var $table = $('#fp-ocr-table'), $tbody = $table.find('tbody');
		var worker = null;
		ocrStop = false;
		$run.prop('disabled', true);
		$stop.prop('hidden', false);
		$('#fp-ocr-save').prop('hidden', true);
		$tbody.empty();
		try {
			var r = await post('fp_ocr_list', { all: $('#fp-ocr-scope').val() === 'all' ? 1 : '', dirs: selectedDirs() });
			if (!r.success) throw new Error(r.data.message);
			var items = r.data.items, known = r.data.known;
			if (!items.length) { $prog.text('Wszystkie zdjęcia mają już autora.'); return; }
			$prog.text('Pobieram bibliotekę OCR…');
			await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js');
			worker = await window.Tesseract.createWorker('pol');
			await worker.setParameters({ tessedit_pageseg_mode: '11' });
			$table.prop('hidden', false);
			var found = 0;
			for (var i = 0; i < items.length && !ocrStop; i++) {
				var it = items[i];
				$prog.text('Zdjęcie ' + (i + 1) + ' z ' + items.length + ' · rozpoznano ' + found);
				var res = { name: '', known: false }, preview = '';
				try {
					var img = await loadImg(it.src);
					for (var pass = 0; pass < 2 && !res.name; pass++) {
						var c = footer(img, pass === 0);
						if (!preview) preview = c.toDataURL('image/jpeg', 0.6);
						res = parseAuthor((await worker.recognize(c)).data.text, known);
					}
				} catch (e) {
					res = { name: '', known: false, err: e.message };
				}
				if (res.name && !res.partial) found++;
				var $tr = $('<tr>').data('f', it.f);
				$tr.append(
					// samo jedno słowo (np. tylko imię) nie jest zaznaczane: trzeba je uzupełnić ręcznie
					$('<td>').append($('<input type="checkbox" class="fp-ocr-pick">').prop('checked', !!res.name && !res.partial).attr('aria-label', 'Zapisz autora')),
					$('<td>').append(preview ? $('<img class="fp-ocr-crop" alt="Stopka zdjęcia">').attr('src', preview) : '—'),
					$('<td>').append(
						$('<input type="text" class="regular-text fp-ocr-name">').val(res.name).attr('placeholder', res.err || 'nie rozpoznano').attr('aria-label', 'Autor'),
						res.known ? $('<span class="fp-ocr-known">').text('znany autor') : res.partial ? $('<span class="fp-ocr-partial">').text('tylko część, uzupełnij') : ''
					),
					$('<td>').append(
						$('<a target="_blank" rel="noopener">').attr('href', it.src).text(it.event + ' / ' + it.f.split('/').pop()),
						it.current ? $('<div class="description">').text('teraz: ' + it.current) : ''
					)
				);
				$tbody.append($tr);
			}
			$prog.text((ocrStop ? 'Zatrzymano. ' : 'Gotowe. ') + 'Rozpoznano ' + found + ' z ' + $tbody.children().length + '. Sprawdź nazwiska i zapisz.');
			$('#fp-ocr-save').prop('hidden', false);
		} catch (e) {
			$prog.text(e.message).addClass('fp-err');
		} finally {
			if (worker) await worker.terminate();
			$run.prop('disabled', false);
			$stop.prop('hidden', true);
		}
	});
	$('#fp-ocr-stop').on('click', function () { ocrStop = true; });
	$('#fp-ocr-all').on('change', function () { $('.fp-ocr-pick').prop('checked', this.checked); });
	$('#fp-ocr-table').on('input', '.fp-ocr-name', function () {
		$(this).closest('tr').find('.fp-ocr-pick').prop('checked', this.value.trim() !== '');
	});
	$('#fp-ocr-save').on('click', function () {
		var items = [];
		$('#fp-ocr-table tbody tr').each(function () {
			var $tr = $(this);
			if ($tr.find('.fp-ocr-pick').prop('checked')) items.push({ f: $tr.data('f'), a: $tr.find('.fp-ocr-name').val().trim() });
		});
		if (!items.length) return;
		post('fp_ocr_save', { items: items }).then(function (r) {
			$('#fp-ocr-progress').text(r.data.message).toggleClass('fp-err', !r.success);
		});
	});
});
