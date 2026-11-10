/* Focus Portfolio: panel (skan, miniatury, import). */
jQuery(function ($) {
	'use strict';
	var A = window.FP_ADMIN || {};
	var tree = A.tree || {};

	$('.fp-color').wpColorPicker();

	// Wygląd → Kolumny: pola trybu „stała liczba” albo „automatycznie”
	function syncShow() {
		$('[data-fp-show]').each(function () {
			var p = this.getAttribute('data-fp-show').split('=');
			$(this).prop('hidden', $('input[name="fv[' + p[0] + ']"]:checked').val() !== p[1]);
		});
	}
	$(document).on('change', '[data-fp-mode]', syncShow);
	syncShow();

	var $log = $('#fp-log'), $spin = $('#fp-spin');
	function log(msg, isErr) { $log.text(msg).toggleClass('fp-err', !!isErr); }
	function post(action, data) {
		return $.post(A.ajax, $.extend({ action: action, nonce: A.nonce }, data || {}));
	}
	function progress(done, total) {
		var $p = $('#fp-thumb-progress');
		$p.find('.fp-progress-bar span').css('width', total ? Math.round(100 * done / total) + '%' : 0);
		$p.find('.fp-progress-label').html('Miniatury: <b>' + done + '</b> z ' + total);
	}
	function busy(on) { $spin.toggleClass('is-active', on); $('#fp-scan, #fp-thumbs, #fp-imp-go').prop('disabled', on); }

	function scan() {
		busy(true);
		log('Skanuję folder…');
		return post('fp_scan').then(function (r) {
			busy(false);
			if (!r.success) { log(r.data.message, true); return $.Deferred().reject(); }
			tree = r.data.tree || tree;
			fillCats();
			progress(r.data.done, r.data.total);
			log('Gotowe: ' + r.data.photos + ' zdjęć w ' + r.data.groups + ' galeriach.');
			return r.data;
		}, function () { busy(false); log('Skan się nie udał (błąd serwera).', true); });
	}

	function thumbs() {
		busy(true);
		var errors = [], retries = 0;
		function step() {
			return post('fp_thumbs').then(function (r) {
				if (!r.success) { busy(false); log(r.data.message, true); return; }
				retries = 0;
				var d = r.data;
				errors = errors.concat(d.errors || []);
				progress(d.done, d.total);
				log('Miniatury: ' + d.done + ' z ' + d.total + '…');
				if (d.processed > 0 && d.done < d.total) return step();
				busy(false);
				log(errors.length ? 'Gotowe, ale ' + errors.length + ' zdjęć się nie udało (pokazujemy oryginał): ' + errors.slice(0, 3).join('; ') : 'Wszystkie miniatury gotowe.', errors.length > 0);
			}, function () {
				// przekroczony czas serwera: postęp jest zapisany, więc próbujemy dalej (najwyżej 3 razy z rzędu)
				if (++retries > 3) { busy(false); log('Serwer nie odpowiada. Miniatury dokończy cron albo kolejne kliknięcie.', true); return; }
				log('Serwer potrzebował więcej czasu, kontynuuję…');
				return step();
			});
		}
		return step();
	}

	$('#fp-scan').on('click', scan);
	$('#fp-thumbs').on('click', thumbs);

	/* ---------- import ---------- */
	var $cat = $('#fp-imp-cat'), $catNew = $('#fp-imp-cat-new'), $sub = $('#fp-imp-sub'), $ev = $('#fp-imp-ev');
	var files = [];

	function fillCats() {
		if (!$cat.length) return;
		var cur = $cat.val();
		$cat.empty();
		Object.keys(tree).forEach(function (c) { $cat.append($('<option>').val(c).text(c)); });
		$cat.append($('<option>').val('__new').text('+ Nowa kategoria…'));
		if (cur && (tree[cur] || cur === '__new')) $cat.val(cur);
		onCat();
	}
	function catName() { return $cat.val() === '__new' ? $.trim($catNew.val()) : $cat.val(); }
	function onCat() {
		$catNew.prop('hidden', $cat.val() !== '__new');
		var subs = tree[$cat.val()] || {};
		$('#fp-imp-sub-list').empty().append(Object.keys(subs).map(function (s) { return $('<option>').val(s); }));
		onSub();
	}
	function onSub() {
		var subs = tree[$cat.val()] || {};
		var evs = subs[$.trim($sub.val())] || [];
		$('#fp-imp-ev-list').empty().append(evs.map(function (e) { return $('<option>').val(e); }));
		target();
	}
	function target() {
		var parts = [catName(), $.trim($sub.val()), $.trim($ev.val())].filter(Boolean);
		$('#fp-imp-target').text(parts.length ? parts.join(' / ') + ' /' : '(wybierz kategorię)');
		$('#fp-imp-go').prop('disabled', !files.length || !catName());
	}
	$cat.on('change', onCat);
	$catNew.on('input', target);
	$sub.on('input change', onSub);
	$ev.on('input', target);
	fillCats();

	function addFiles(list) {
		Array.prototype.forEach.call(list, function (f) {
			var ok = /\.(jpe?g|png|webp|zip)$/i.test(f.name);
			var tooBig = A.maxSize && f.size > A.maxSize;
			var $li = $('<li>').text(f.name + ' · ' + (f.size / 1048576).toFixed(1) + ' MB');
			if (!ok || tooBig) {
				$li.addClass('fp-err').append(' · ' + (ok ? 'za duży dla serwera' : 'nieobsługiwany typ'));
			} else {
				files.push({ file: f, $li: $li });
			}
			$('#fp-queue').append($li);
		});
		target();
	}
	$('#fp-imp-files').on('change', function () { addFiles(this.files); this.value = ''; });
	var $drop = $('#fp-drop');
	$drop.on('dragover dragenter', function (e) { e.preventDefault(); $drop.addClass('is-over'); });
	$drop.on('dragleave drop', function (e) { e.preventDefault(); $drop.removeClass('is-over'); });
	$drop.on('drop', function (e) { addFiles(e.originalEvent.dataTransfer.files); });

	$('#fp-import').on('submit', function (e) {
		e.preventDefault();
		if (!files.length || !catName()) return;
		busy(true);
		var queue = files.slice(), saved = 0, failed = 0;
		files = [];
		function next() {
			var item = queue.shift();
			if (!item) {
				busy(false);
				log('Wgrano ' + saved + ' zdjęć' + (failed ? ', ' + failed + ' z błędem' : '') + '. Skanuję i robię miniatury…', failed > 0);
				$ev.val('');
				return scan().then(thumbs);
			}
			var fd = new FormData();
			fd.append('action', 'fp_upload');
			fd.append('nonce', A.nonce);
			fd.append('cat', catName());
			fd.append('sub', $.trim($sub.val()));
			fd.append('event', $.trim($ev.val()));
			fd.append('file', item.file);
			item.$li.addClass('is-busy');
			$.ajax({ url: A.ajax, type: 'POST', data: fd, processData: false, contentType: false }).then(function (r) {
				item.$li.removeClass('is-busy');
				if (r.success) {
					saved += r.data.saved.length;
					failed += r.data.skipped.length;
					item.$li.addClass('fp-ok').append(' · zapisano ' + r.data.saved.length + (r.data.skipped.length ? ', pominięto: ' + r.data.skipped.join('; ') : ''));
				} else {
					failed++;
					item.$li.addClass('fp-err').append(' · ' + r.data.message);
				}
				next();
			}, function (x) {
				failed++;
				item.$li.removeClass('is-busy').addClass('fp-err').append(' · błąd serwera (' + x.status + ')' + (x.status === 413 ? ': plik za duży' : ''));
				next();
			});
		}
		next();
	});
});

/* Wspólne elementy panelu: zwijane karty, wybór katalogu źródłowego, sprawdzanie nazwy. */
jQuery(function ($) {
	'use strict';
	var A = window.FP_ADMIN || {};
	var store = {
		get: function (k) { try { return JSON.parse(localStorage.getItem(k) || '{}'); } catch (e) { return {}; } },
		set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
	};

	/* karty: stan zwinięcia zapamiętany per podstrona */
	var key = 'fp-cards-' + (A.page || '');
	var saved = store.get(key);
	$('details.fp-card[data-card]').each(function () {
		var id = this.getAttribute('data-card');
		if (Object.prototype.hasOwnProperty.call(saved, id)) this.open = !!saved[id];
	}).on('toggle', function () {
		saved[this.getAttribute('data-card')] = this.open;
		store.set(key, saved);
	});

	/* drzewko katalogów w wp-content (wybór źródła) */
	var $pick = $('#fp-dirpick');
	function loadDirs($ul) {
		$ul.html('<li class="fp-dp-load">…</li>');
		$.post(A.ajax, { action: 'fp_dirs', nonce: A.nonce, dir: $ul.data('dir') }).then(function (r) {
			$ul.empty();
			if (!r.success) { $ul.append($('<li>').text(r.data.message)); return; }
			if (!r.data.dirs.length) { $ul.append('<li class="fp-dp-empty">(brak podkatalogów)</li>'); return; }
			r.data.dirs.forEach(function (d) {
				var $li = $('<li>').toggleClass('is-disabled', !d.allowed);
				var $tog = $('<button type="button" class="fp-dp-tog" aria-label="Rozwiń">▸</button>');
				var $name = $('<button type="button" class="fp-dp-name">').text(d.name).prop('disabled', !d.allowed);
				if ($pick.data('current') === d.path) $name.addClass('is-current');
				$li.append($tog, $name, d.images ? $('<span class="fp-dp-n">').text(d.images) : '');
				var $sub = $('<ul>').data('dir', d.path).prop('hidden', true);
				$li.append($sub);
				$tog.on('click', function () {
					var open = $sub.prop('hidden');
					$sub.prop('hidden', !open);
					$tog.text(open ? '▾' : '▸');
					if (open && !$sub.children().length) loadDirs($sub);
				});
				$name.on('click', function () {
					$('#fp-source').val(d.path).trigger('change');
					$pick.find('.is-current').removeClass('is-current');
					$name.addClass('is-current');
				});
				$ul.append($li);
			});
		});
	}
	if ($pick.length) loadDirs($pick.find('.fp-dirpick-tree'));

	/* Struktura: jak skaner zrozumie nazwę */
	$('#fp-nc-go').on('click', function () {
		var path = $('#fp-nc-cat').val() + '/' + $.trim($('#fp-nc-path').val());
		$.post(A.ajax, { action: 'fp_fs', nonce: A.nonce, op: 'check', path: path }).then(function (r) {
			var $o = $('#fp-nc-out').removeClass('is-ok is-warn');
			if (!r.success) { $o.addClass('is-warn').text(r.data.message); return; }
			$o.addClass(r.data.ok ? 'is-ok' : 'is-warn').text(r.data.text);
		});
	});
	$('#fp-nc-path').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#fp-nc-go').trigger('click'); } });
});
