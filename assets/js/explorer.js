/* Focus Portfolio: Eksplorator zdjęć (drzewo, widoki, operacje na plikach, właściwości, problemy, kosz). */
jQuery(function ($) {
	'use strict';
	var $root = $('#fpx');
	if (!$root.length) return;
	var A = window.FP_ADMIN || {};
	var C = $root.data('conf') || {};
	var LS = 'fpx-ui';
	var ui = (function () { try { return JSON.parse(localStorage.getItem(LS) || '{}'); } catch (e) { return {}; } })();
	function saveUi() { try { localStorage.setItem(LS, JSON.stringify(ui)); } catch (e) {} }

	var S = {
		mode: 'dir',          // dir | inbox | trash
		path: '',
		flat: false,
		year: '',
		data: null,
		items: [],
		sel: [],              // ścieżki zaznaczonych
		anchor: null,
		hist: [],
		hpos: -1,
		view: ui.view || 'tiles',
		size: ui.size || 170,
		filter: '',
		copies: !!ui.copies,  // pokaż kopie rozmiarów z Biblioteki mediów (na stronie pomijane)
		ibType: '',           // filtr rodzaju w „Do uporządkowania”
		dirs: null,           // wszystkie katalogi (okno „Przenieś do”)
		people: []
	};

	/* ---------- narzędzia ---------- */
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function api(op, data) {
		return $.post(A.ajax, $.extend({ action: 'fp_fs', nonce: A.nonce, op: op }, data || {})).then(function (r) {
			if (!r || !r.success) return $.Deferred().reject((r && r.data && r.data.message) || 'Błąd serwera.');
			return r.data;
		}, function (x) { return $.Deferred().reject('Błąd połączenia (' + x.status + ').'); });
	}
	function size(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' kB'; }
	function date(ts) { if (!ts) return ''; var d = new Date(ts * 1000); return d.toLocaleDateString('pl-PL') + ' ' + d.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' }); }
	function base(p) { return p.split('/').pop(); }
	function parent(p) { var i = p.lastIndexOf('/'); return i < 0 ? '' : p.slice(0, i); }
	function item(p) { return S.items.find(function (x) { return x.path === p; }); }
	function selItems() { return S.sel.map(item).filter(Boolean); }
	var ROLE = { root: 'Źródło', category: 'Kategoria', month: 'Miesiąc', year: 'Rok', edition: 'Edycja', event: 'Wydarzenie', sub: 'Podkatalog', hidden: 'Pomijany' };
	var ICON = { root: 'admin-home', category: 'category', month: 'calendar-alt', year: 'calendar', edition: 'awards', event: 'format-gallery', sub: 'portfolio', hidden: 'hidden' };

	/* ---------- szkielet ---------- */
	$root.html(
		'<div class="fpx-toolbar">' +
			'<div class="fpx-nav">' +
				'<button type="button" class="fpx-ib" data-a="back" title="Wstecz (Alt+←)"><span class="dashicons dashicons-arrow-left-alt2"></span></button>' +
				'<button type="button" class="fpx-ib" data-a="fwd" title="Dalej (Alt+→)"><span class="dashicons dashicons-arrow-right-alt2"></span></button>' +
				'<button type="button" class="fpx-ib" data-a="up" title="Katalog wyżej (Backspace)"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
				'<button type="button" class="fpx-ib" data-a="refresh" title="Odśwież (F5)"><span class="dashicons dashicons-update"></span></button>' +
				'<div class="fpx-crumbs" aria-label="Ścieżka"></div>' +
				'<input type="search" class="fpx-search" placeholder="Filtruj w tym katalogu…" aria-label="Filtruj">' +
			'</div>' +
			'<div class="fpx-actions">' +
				'<span class="fpx-grp fpx-edit">' +
					'<button type="button" class="button" data-a="mkdir"><span class="dashicons dashicons-plus-alt2"></span> <span class="fpx-mk-label">Nowy katalog</span></button>' +
					'<button type="button" class="button" data-a="upload"><span class="dashicons dashicons-upload"></span> Wgraj</button>' +
					'<button type="button" class="button" data-a="rename" data-sel="1"><span class="dashicons dashicons-edit"></span> Zmień nazwę</button>' +
					'<button type="button" class="button" data-a="move" data-sel="n"><span class="dashicons dashicons-move"></span> Przenieś</button>' +
					'<button type="button" class="button" data-a="trash" data-sel="n"><span class="dashicons dashicons-trash"></span> Usuń</button>' +
				'</span>' +
				'<span class="fpx-grp fpx-edit">' +
					'<button type="button" class="button" data-a="star" data-sel="p" title="Najlepsze: wyżej w sortowaniu „Najlepsze”"><span class="dashicons dashicons-star-filled"></span> Gwiazdka</button>' +
					'<button type="button" class="button" data-a="cover" data-sel="p" title="Okładka wydarzenia (do 5)"><span class="dashicons dashicons-format-image"></span> Okładka</button>' +
				'</span>' +
				'<span class="fpx-grp fpx-viewbtns" role="group" aria-label="Widok">' +
					'<button type="button" class="fpx-ib" data-view="tiles" title="Kafelki"><span class="dashicons dashicons-grid-view"></span></button>' +
					'<button type="button" class="fpx-ib" data-view="list" title="Lista"><span class="dashicons dashicons-list-view"></span></button>' +
					'<button type="button" class="fpx-ib" data-view="details" title="Szczegóły"><span class="dashicons dashicons-editor-table"></span></button>' +
					'<input type="range" class="fpx-size" min="110" max="320" step="10" aria-label="Wielkość kafelków">' +
				'</span>' +
				'<label class="fpx-flat"><input type="checkbox" class="fpx-flat-cb"> Wszystkie zdjęcia z podkatalogów</label>' +
				'<label class="fpx-copies" title="Kopie rozmiarów (-150x150, -scaled), które tworzy Biblioteka mediów WordPressa. Na stronie są pomijane."><input type="checkbox" class="fpx-copies-cb"> Pokaż kopie WordPressa</label>' +
			'</div>' +
		'</div>' +
		'<div class="fpx-body">' +
			'<aside class="fpx-tree" aria-label="Katalogi"><div class="fpx-special"></div><ul class="fpx-treeroot" role="tree"></ul></aside>' +
			'<main class="fpx-main" tabindex="0" aria-label="Zawartość katalogu"></main>' +
			'<aside class="fpx-props" aria-label="Właściwości"></aside>' +
		'</div>' +
		'<div class="fpx-status"><span class="fpx-st-l"></span><span class="fpx-st-r"></span></div>' +
		'<input type="file" class="fpx-file" multiple accept=".jpg,.jpeg,.png,.webp,.zip,image/jpeg,image/png,image/webp,application/zip" hidden>' +
		'<div class="fpx-ctx" hidden></div>' +
		'<div class="fpx-modal" hidden><div class="fpx-dlg" role="dialog" aria-modal="true"></div></div>' +
		'<div class="fpx-viewer" hidden></div>'
	);
	if (!C.canEdit) $root.addClass('is-ro');
	var $main = $root.find('.fpx-main'), $tree = $root.find('.fpx-treeroot'), $props = $root.find('.fpx-props');
	var $status = $root.find('.fpx-st-l'), $status2 = $root.find('.fpx-st-r');
	$root.find('.fpx-size').val(S.size);
	$root[0].style.setProperty('--fpx-tile', S.size + 'px');

	function setStatus(t, err) { $status.text(t).toggleClass('is-err', !!err); }

	/* ---------- okna dialogowe ---------- */
	var $modal = $root.find('.fpx-modal'), $dlg = $modal.find('.fpx-dlg');
	function dialog(o) {
		// o: {title, html, ok, cancel, danger, init($dlg), value()}
		var d = $.Deferred();
		$dlg.html('<h2>' + esc(o.title) + '</h2><div class="fpx-dlg-body">' + (o.html || '') + '</div>' +
			'<div class="fpx-dlg-btns">' + (o.cancel === false ? '' : '<button type="button" class="button fpx-no">' + esc(o.cancel || 'Anuluj') + '</button>') +
			'<button type="button" class="button ' + (o.danger ? 'button-primary fpx-danger-btn' : 'button-primary') + ' fpx-yes">' + esc(o.ok || 'OK') + '</button></div>');
		$modal.prop('hidden', false);
		if (o.init) o.init($dlg);
		setTimeout(function () { ($dlg.find('input,select,textarea').first()[0] || $dlg.find('.fpx-yes')[0]).focus(); }, 20);
		function close(v) { $modal.prop('hidden', true); $dlg.off(); $(document).off('keydown.fpxdlg'); v === undefined ? d.reject() : d.resolve(v); }
		$dlg.on('click', '.fpx-no', function () { close(); });
		$dlg.on('click', '.fpx-yes', function () { close(o.value ? o.value($dlg) : true); });
		$(document).on('keydown.fpxdlg', function (e) {
			if (e.key === 'Escape') close();
			if (e.key === 'Enter' && !$(e.target).is('textarea') && !$(e.target).is('.fpx-mv-search')) { e.preventDefault(); $dlg.find('.fpx-yes').trigger('click'); }
		});
		return d.promise();
	}
	function prompt(title, label, val, hint) {
		return dialog({ title: title, ok: 'Zapisz', html: '<label class="fpx-lbl">' + esc(label) + '<input type="text" class="regular-text fpx-in" value="' + esc(val || '') + '"></label>' + (hint ? '<p class="description">' + hint + '</p>' : '') + '<p class="fpx-check-out"></p>',
			value: function ($d) { return $.trim($d.find('.fpx-in').val()); } });
	}
	function alertBox(title, text) { return dialog({ title: title, html: '<p>' + esc(text) + '</p>', cancel: false }); }

	/** Sprawdza, jak skaner zrozumie ścieżkę katalogu, i pyta o potwierdzenie przy problemach. */
	function confirmPath(path, action) {
		return api('check', { path: path }).then(function (r) {
			if (r.ok) return true;
			return dialog({
				title: 'Ta nazwa może popsuć wyświetlanie', ok: action + ' mimo to', danger: true, cancel: 'Popraw nazwę',
				html: '<p><b>' + esc(r.label) + '</b></p><p class="fpx-warn">' + esc(r.warn) + '</p><p class="description">Przy skanowaniu taki katalog może trafić do „Do uporządkowania” albo do Archiwum (zależnie od ustawień Struktury). Zawartość nie zostanie usunięta.</p>'
			});
		});
	}

	/* ---------- nawigacja ---------- */
	function go(path, opts, noHist) {
		opts = opts || {};
		S.mode = opts.mode || 'dir';
		S.path = path || '';
		S.flat = !!opts.flat;
		S.year = opts.year || '';
		S.sel = [];
		if (!noHist) {
			S.hist = S.hist.slice(0, S.hpos + 1);
			S.hist.push({ path: S.path, mode: S.mode, flat: S.flat, year: S.year });
			S.hpos = S.hist.length - 1;
		}
		$root.find('.fpx-flat-cb').prop('checked', S.flat);
		return reload();
	}
	function reload() {
		$main.addClass('is-busy');
		var p;
		if (S.mode === 'inbox') p = api('inbox').then(function (d) { S.data = d; S.items = []; renderInbox(); });
		else if (S.mode === 'trash') p = api('trash_list').then(function (d) { S.data = d; S.items = []; renderTrash(); });
		else p = api('list', { path: S.path, flat: S.flat ? 1 : '', year: S.year, copies: S.copies ? 1 : '' }).then(function (d) { S.data = d; S.items = d.items; renderDir(); });
		return p.always(function () { $main.removeClass('is-busy'); renderCrumbs(); markTree(); updateButtons(); })
			.fail(function (e) { setStatus(e, true); $main.html('<p class="fpx-empty">' + esc(e) + '</p>'); });
	}

	function renderCrumbs() {
		var $c = $root.find('.fpx-crumbs').empty();
		var add = function (label, path, opts) {
			$('<button type="button" class="fpx-crumb">').text(label).on('click', function () { go(path, opts); }).appendTo($c);
		};
		add(C.root.split('/').pop(), '');
		if (S.mode === 'inbox') { $c.append('<span class="fpx-sep">›</span>'); add('Do uporządkowania', '', { mode: 'inbox' }); return; }
		if (S.mode === 'trash') { $c.append('<span class="fpx-sep">›</span>'); add('Kosz', '', { mode: 'trash' }); return; }
		var acc = '';
		(S.path ? S.path.split('/') : []).forEach(function (seg) {
			acc = acc ? acc + '/' + seg : seg;
			var p = acc;
			$c.append('<span class="fpx-sep">›</span>');
			add(seg, p);
		});
		if (S.year) { $c.append('<span class="fpx-sep">›</span>'); add(S.year, S.path, { flat: true, year: S.year }); }
	}

	/* ---------- drzewo ---------- */
	function treeNode(d) {
		var $li = $('<li role="treeitem" aria-expanded="false">').attr('data-path', d.path);
		var $row = $('<div class="fpx-tn">').attr('data-path', d.path).attr('data-drop', d.path);
		$row.append('<button type="button" class="fpx-tog" aria-label="Rozwiń">' + (d.subs ? '▸' : '') + '</button>');
		$row.append('<span class="dashicons dashicons-' + (ICON[d.role] || 'portfolio') + '"></span>');
		$row.append($('<span class="fpx-tn-name">').text(d.name));
		if (!d.ok) $row.append('<span class="fpx-badge fpx-badge-warn" title="' + esc(d.warn) + '">!</span>');
		if (d.archive) $row.append('<span class="fpx-badge" title="Archiwum">A</span>');
		if (d.hidden) $row.append('<span class="fpx-badge" title="Ukryte na stronie">U</span>');
		if (d.total) $row.append($('<span class="fpx-tn-n">').attr('title', 'Zdjęć razem z podkatalogami').text(d.total));
		$li.append($row).append('<ul role="group" hidden></ul>');
		$li.data('d', d);
		return $li;
	}
	function loadTree($ul, path) {
		return api('list', { path: path }).then(function (r) {
			$ul.empty();
			var dirs = r.items.filter(function (x) { return x.type === 'dir' && !x.skip; });
			// kategoria z miesiącami: grupuj miesiące w wirtualne lata
			if (r.info.role === 'category' && r.info.mode === 'months') {
				var years = {};
				var other = [];
				dirs.forEach(function (d) {
					var m = /(\d{4})$/.exec(d.period || '');
					if (d.role === 'month' && m) (years[m[1]] = years[m[1]] || []).push(d); else other.push(d);
				});
				Object.keys(years).sort().reverse().forEach(function (y) {
					var $li = $('<li role="treeitem" aria-expanded="false" class="fpx-vyear">');
					var $row = $('<div class="fpx-tn">').attr('data-year', y).attr('data-path', path);
					$row.append('<button type="button" class="fpx-tog">▸</button><span class="dashicons dashicons-calendar"></span>');
					$row.append($('<span class="fpx-tn-name">').text(y));
					$row.append($('<span class="fpx-tn-n">').text(years[y].length + ' mies.'));
					var $sub = $('<ul role="group" hidden>');
					years[y].sort(function (a, b) { return b.name.localeCompare(a.name, 'pl', { numeric: true }); }).forEach(function (d) { $sub.append(treeNode(d)); });
					$ul.append($li.append($row).append($sub));
				});
				other.forEach(function (d) { $ul.append(treeNode(d)); });
			} else {
				dirs.forEach(function (d) { $ul.append(treeNode(d)); });
			}
			if (!dirs.length) $ul.append('<li class="fpx-tn-empty">(brak podkatalogów)</li>');
			markTree();
		});
	}
	function toggleNode($li, open) {
		var $ul = $li.children('ul');
		open = open === undefined ? $ul.prop('hidden') : open;
		$ul.prop('hidden', !open);
		$li.attr('aria-expanded', open ? 'true' : 'false');
		$li.find('> .fpx-tn > .fpx-tog').text(open ? '▾' : '▸');
		if (open && !$ul.children().length && !$li.hasClass('fpx-vyear')) loadTree($ul, $li.data('path') || $li.attr('data-path'));
	}
	function markTree() {
		$tree.find('.fpx-tn').removeClass('is-current');
		$root.find('.fpx-special .fpx-tn').removeClass('is-current');
		if (S.mode !== 'dir') { $root.find('.fpx-special [data-mode="' + S.mode + '"]').addClass('is-current'); return; }
		var sel = S.year ? '.fpx-tn[data-year="' + S.year + '"][data-path="' + cssEsc(S.path) + '"]' : '.fpx-tn[data-path="' + cssEsc(S.path) + '"]:not([data-year])';
		$tree.find(sel).addClass('is-current');
	}
	function cssEsc(s) { return String(s).replace(/(["\\])/g, '\\$1'); }
	function expandTo(path) {
		// rozwiń drzewo po kolei do bieżącego katalogu
		var segs = path ? path.split('/') : [];
		var acc = '';
		var chain = $.Deferred().resolve();
		segs.forEach(function (seg, i) {
			acc = acc ? acc + '/' + seg : seg;
			var p = acc;
			chain = chain.then(function () {
				var $li = $tree.find('li[data-path="' + cssEsc(p) + '"]').first();
				if (!$li.length) {
					// miesiąc może być w wirtualnym roku: rozwiń lata rodzica
					var $vy = $tree.find('.fpx-vyear').filter(function () { return $(this).find('li[data-path="' + cssEsc(p) + '"]').length; }).first();
					if ($vy.length) { toggleNode($vy, true); $li = $vy.find('li[data-path="' + cssEsc(p) + '"]').first(); }
				}
				if ($li.length && i < segs.length - 1) {
					var $ul = $li.children('ul');
					$ul.prop('hidden', false);
					$li.attr('aria-expanded', 'true').find('> .fpx-tn > .fpx-tog').text('▾');
					if (!$ul.children().length) return loadTree($ul, p);
				}
			});
		});
		return chain.then(markTree);
	}
	$tree.on('click', '.fpx-tog', function (e) { e.stopPropagation(); toggleNode($(this).closest('li')); });
	$tree.on('click', '.fpx-tn', function () {
		var $t = $(this);
		if ($t.attr('data-year')) go($t.attr('data-path'), { flat: true, year: $t.attr('data-year') });
		else go($t.attr('data-path'));
	});
	$tree.on('dblclick', '.fpx-tn', function () { toggleNode($(this).closest('li')); });

	function renderSpecial() {
		var $s = $root.find('.fpx-special').empty();
		var mk = function (mode, icon, label) {
			return $('<div class="fpx-tn fpx-sp">').attr('data-mode', mode).append('<span class="dashicons dashicons-' + icon + '"></span>', $('<span class="fpx-tn-name">').text(label));
		};
		var $root2 = $('<div class="fpx-tn fpx-sp" data-path="">').append('<span class="dashicons dashicons-admin-home"></span>', $('<span class="fpx-tn-name">').text(C.root.split('/').pop()));
		$root2.attr('data-drop', '');
		$s.append($root2, mk('inbox', 'flag', 'Do uporządkowania').append('<span class="fpx-tn-n fpx-inbox-n"></span>'), mk('trash', 'trash', 'Kosz'));
		$s.on('click', '[data-mode]', function () { go('', { mode: $(this).attr('data-mode') }); });
		$s.on('click', '[data-path]', function () { go(''); });
		api('inbox').then(function (d) { $s.find('.fpx-inbox-n').text(d.problems.length || ''); });
	}

	/* ---------- widok katalogu ---------- */
	function visibleItems() {
		var f = S.filter.toLowerCase();
		return S.items.filter(function (x) { return !f || x.name.toLowerCase().indexOf(f) >= 0 || (x.author || '').toLowerCase().indexOf(f) >= 0 || (x.title || '').toLowerCase().indexOf(f) >= 0; });
	}
	function renderDir() {
		var items = visibleItems();
		var info = S.data.info;
		$main.attr('data-view', S.view).attr('data-drop', S.path);
		var html = '';
		if (S.data.problems && S.data.problems.length) {
			html += '<div class="fpx-banner">' + S.data.problems.map(function (p) { return '<p><span class="dashicons dashicons-warning"></span> ' + esc(p.msg) + '</p>'; }).join('') + fixButtons(S.data.problems[0], true) + '</div>';
		}
		if (!items.length) {
			html += '<div class="fpx-empty">' + (S.filter ? 'Nic nie pasuje do filtra.' : 'Pusty katalog.' + (C.canEdit && !S.flat ? ' Przeciągnij tu zdjęcia z komputera albo użyj „Wgraj”.' : '')) + '</div>';
		} else if (S.view === 'details') {
			html += '<table class="fpx-table widefat striped"><thead><tr><th>Nazwa</th><th>Rodzaj / wydarzenie</th><th>Autor</th><th>Data</th><th>Rozmiar</th><th>Zmieniono</th></tr></thead><tbody>' +
				items.map(function (x) { return rowHtml(x); }).join('') + '</tbody></table>';
		} else {
			html += '<div class="fpx-grid">' + items.map(function (x) { return S.view === 'list' ? listHtml(x) : tileHtml(x); }).join('') + '</div>';
		}
		$main.html(html);
		renderSel();
		var dirs = S.items.filter(function (x) { return x.type === 'dir'; }).length;
		var photos = S.items.length - dirs;
		var copies = S.data.copies ? ' · ukryto ' + S.data.copies + ' kopii WordPressa' : '';
		setStatus((S.flat ? 'Wszystkie zdjęcia' + (S.year ? ' z ' + S.year : '') + ': ' : '') + (dirs ? dirs + ' katalogów · ' : '') + photos + ' plików' + copies + (info.label ? ' · ' + info.label : ''));
		$root.find('.fpx-mk-label').text(mkLabel(info));
	}
	function mkLabel(info) {
		var r = info.role, m = info.mode;
		if (r === 'root') return 'Nowa kategoria';
		if (r === 'category' && m === 'months') return 'Nowy miesiąc';
		if (r === 'category' && m === 'numbered') return 'Nowa edycja';
		if (r === 'category' && m === 'years') return 'Nowy rok';
		if (r === 'category' || r === 'month' || r === 'year') return 'Nowe wydarzenie';
		return 'Nowy katalog';
	}
	function badges(x) {
		var b = '';
		if (x.star) b += '<span class="fpx-star" title="Gwiazdka">★</span>';
		if (x.cover !== null && x.cover !== undefined) b += '<span class="fpx-cov" title="Okładka nr ' + (x.cover + 1) + '">' + (x.cover + 1) + '</span>';
		if (x.skipped) b += '<span class="fpx-skip" title="' + esc(x.skipped) + '">pominięte</span>';
		return b;
	}
	function dirBadges(x) {
		return (x.ok ? '' : '<span class="fpx-badge fpx-badge-warn" title="' + esc(x.warn) + '">!</span>') + (x.archive ? '<span class="fpx-badge">Archiwum</span>' : '') + (x.hidden ? '<span class="fpx-badge">Ukryte</span>' : '');
	}
	function tileHtml(x) {
		if (x.type === 'dir') {
			return '<div class="fpx-it fpx-dir" draggable="true" data-p="' + esc(x.path) + '" data-drop="' + esc(x.path) + '" tabindex="-1">' +
				'<div class="fpx-thumb"><span class="dashicons dashicons-' + (ICON[x.role] || 'portfolio') + '"></span></div>' +
				'<div class="fpx-name">' + esc(x.title || x.name) + '</div><div class="fpx-sub">' + esc(ROLE[x.role] || '') + (x.period ? ' · ' + esc(x.period) : '') + ' · ' + dirCount(x) + '</div>' + dirBadges(x) + '</div>';
		}
		return '<div class="fpx-it fpx-file' + (x.skipped ? ' is-skipped' : '') + '" draggable="true" data-p="' + esc(x.path) + '" tabindex="-1">' +
			'<div class="fpx-thumb">' + (x.thumb ? '<img loading="lazy" alt="" src="' + esc(x.thumb) + '">' : '<span class="dashicons dashicons-media-default"></span>') + badges(x) + '</div>' +
			'<div class="fpx-name">' + esc(x.name) + '</div><div class="fpx-sub">' + esc(S.flat ? (x.event || '') : (x.author || '')) + '</div></div>';
	}
	function listHtml(x) {
		var icon = x.type === 'dir' ? '<span class="dashicons dashicons-' + (ICON[x.role] || 'portfolio') + '"></span>' : (x.thumb ? '<img loading="lazy" alt="" src="' + esc(x.thumb) + '">' : '<span class="dashicons dashicons-media-default"></span>');
		return '<div class="fpx-it fpx-' + (x.type === 'dir' ? 'dir' : 'file') + '" draggable="true" data-p="' + esc(x.path) + '"' + (x.type === 'dir' ? ' data-drop="' + esc(x.path) + '"' : '') + ' tabindex="-1">' +
			'<span class="fpx-li-icon">' + icon + '</span><span class="fpx-name">' + esc(x.type === 'dir' ? (x.title || x.name) : x.name) + '</span>' + (x.type === 'dir' ? dirBadges(x) : badges(x)) + '</div>';
	}
	function rowHtml(x) {
		var kind = x.type === 'dir' ? (ROLE[x.role] || 'Katalog') + (x.period ? ' · ' + x.period : '') : (x.event || (x.skipped ? x.skipped : ''));
		var when = x.type === 'dir' ? '' : (x.taken ? new Date(x.taken * 1000).toLocaleDateString('pl-PL') : '');
		var icon = x.type === 'dir' ? '<span class="dashicons dashicons-' + (ICON[x.role] || 'portfolio') + '"></span>' : (x.thumb ? '<img loading="lazy" alt="" src="' + esc(x.thumb) + '">' : '');
		return '<tr class="fpx-it fpx-' + (x.type === 'dir' ? 'dir' : 'file') + '" draggable="true" data-p="' + esc(x.path) + '"' + (x.type === 'dir' ? ' data-drop="' + esc(x.path) + '"' : '') + ' tabindex="-1">' +
			'<td><span class="fpx-li-icon">' + icon + '</span> ' + esc(x.type === 'dir' ? (x.title || x.name) : x.name) + ' ' + (x.type === 'dir' ? dirBadges(x) : badges(x)) + '</td>' +
			'<td>' + esc(kind) + '</td><td>' + esc(x.author || '') + '</td><td>' + esc(when) + '</td>' +
			'<td>' + (x.type === 'dir' ? dirCount(x) : size(x.size)) + '</td><td>' + esc(date(x.mtime)) + '</td></tr>';
	}
	/** Liczba zdjęć katalogu razem z podkatalogami (i ile leży bezpośrednio w nim, jeśli to nie wszystkie). */
	function dirCount(x) {
		var t = x.total !== undefined ? x.total : x.images;
		return t + ' zdj.' + (x.subs && x.images && x.images !== t ? ' (' + x.images + ' tutaj)' : '');
	}

	/* ---------- zaznaczanie ---------- */
	function renderSel() {
		$main.find('.fpx-it').each(function () { $(this).toggleClass('is-sel', S.sel.indexOf(this.getAttribute('data-p')) >= 0); });
		renderProps();
		updateButtons();
	}
	function updateButtons() {
		var sel = selItems();
		var photos = sel.filter(function (x) { return x.type === 'file' && x.id; });
		$root.find('[data-sel="1"]').prop('disabled', sel.length !== 1 || S.mode !== 'dir');
		$root.find('[data-sel="n"]').prop('disabled', !sel.length || S.mode !== 'dir');
		$root.find('[data-sel="p"]').prop('disabled', !photos.length);
		$root.find('[data-a="mkdir"], [data-a="upload"]').prop('disabled', S.mode !== 'dir' || S.flat);
		$root.find('[data-a="back"]').prop('disabled', S.hpos <= 0);
		$root.find('[data-a="fwd"]').prop('disabled', S.hpos >= S.hist.length - 1);
		$root.find('[data-a="up"]').prop('disabled', S.mode === 'dir' && !S.path && !S.year);
		$root.find('[data-view]').removeClass('is-on').filter('[data-view="' + S.view + '"]').addClass('is-on');
		$root.find('.fpx-size').prop('hidden', S.view !== 'tiles');
		$status2.text(sel.length ? 'Zaznaczono: ' + sel.length : '');
	}
	$main.on('click', '.fpx-it', function (e) {
		var p = this.getAttribute('data-p');
		var vis = visibleItems().map(function (x) { return x.path; });
		if (e.shiftKey && S.anchor) {
			var a = vis.indexOf(S.anchor), b = vis.indexOf(p);
			S.sel = vis.slice(Math.min(a, b), Math.max(a, b) + 1);
		} else if (e.ctrlKey || e.metaKey) {
			var i = S.sel.indexOf(p);
			if (i >= 0) S.sel.splice(i, 1); else S.sel.push(p);
			S.anchor = p;
		} else {
			S.sel = [p];
			S.anchor = p;
		}
		renderSel();
	});
	$main.on('click', function (e) { if (e.target === this || $(e.target).is('.fpx-grid, .fpx-empty')) { S.sel = []; renderSel(); } });
	$main.on('dblclick', '.fpx-it', function () {
		var x = item(this.getAttribute('data-p'));
		if (!x) return;
		if (x.type === 'dir') go(x.path); else view(x);
	});

	/* ---------- podgląd zdjęcia ---------- */
	var $viewer = $root.find('.fpx-viewer');
	function view(x, list) {
		var photos = list || visibleItems().filter(function (i) { return i.type === 'file' && i.img; });
		var idx = Math.max(0, photos.indexOf(x));
		function show() {
			var p = photos[idx];
			$viewer.html('<button type="button" class="fpx-v-x" aria-label="Zamknij">×</button><button type="button" class="fpx-v-p" aria-label="Poprzednie">‹</button>' +
				'<img alt="" src="' + esc(p.large || p.thumb) + '"><button type="button" class="fpx-v-n" aria-label="Następne">›</button>' +
				'<div class="fpx-v-cap">' + esc(p.name) + (p.author ? ' · fot. ' + esc(p.author) : '') + ' · ' + (idx + 1) + ' / ' + photos.length + '</div>').prop('hidden', false);
		}
		show();
		$viewer.off().on('click', '.fpx-v-x', close).on('click', '.fpx-v-p', function () { idx = (idx - 1 + photos.length) % photos.length; show(); })
			.on('click', '.fpx-v-n', function () { idx = (idx + 1) % photos.length; show(); })
			.on('click', function (e) { if (e.target === this) close(); });
		$(document).on('keydown.fpxv', function (e) {
			if (e.key === 'Escape') close();
			if (e.key === 'ArrowLeft') { idx = (idx - 1 + photos.length) % photos.length; show(); }
			if (e.key === 'ArrowRight') { idx = (idx + 1) % photos.length; show(); }
		});
		function close() { $viewer.prop('hidden', true).empty(); $(document).off('keydown.fpxv'); }
	}

	/* ---------- panel właściwości ---------- */
	function renderProps() {
		if (S.mode !== 'dir') { $props.html('<p class="description">' + (S.mode === 'inbox' ? 'Przy każdym problemie wybierz rozwiązanie. Nic nie dzieje się automatycznie.' : 'Usunięte elementy można przywrócić na pierwotne miejsce.') + '</p>'); return; }
		var sel = selItems();
		if (!sel.length) return dirProps();
		if (sel.length === 1 && sel[0].type === 'file') return photoProps(sel[0]);
		if (sel.length === 1 && sel[0].type === 'dir') {
			$props.html('<h3>' + esc(sel[0].title || sel[0].name) + '</h3><p>' + esc(sel[0].label) + '</p>' + (sel[0].warn ? '<p class="fpx-warn">' + esc(sel[0].warn) + '</p>' : '') +
				'<p>' + dirCount(sel[0]) + ' · ' + sel[0].subs + ' podkatalogów</p><p><button type="button" class="button" data-a="open">Otwórz</button></p>' +
				(C.canEdit ? '<p class="fpx-dir-acts"><button type="button" class="button" data-a="rename">Zmień nazwę</button> <button type="button" class="button" data-a="move">Przenieś…</button> <button type="button" class="button fpx-danger-link" data-a="trash">Do kosza</button></p>' : ''));
			return;
		}
		var photos = sel.filter(function (x) { return x.id; });
		$props.html('<h3>Zaznaczono: ' + sel.length + '</h3>' +
			(photos.length && C.canEdit ? '<div class="fpx-f"><label>Autor zaznaczonych zdjęć<input type="text" class="fpx-multi-author" list="fpx-people"></label><button type="button" class="button" data-a="setauthor">Ustaw autora</button></div>' +
				'<p><button type="button" class="button" data-a="star">★ Gwiazdka</button> <button type="button" class="button" data-a="unstar">Usuń gwiazdki</button></p>' : '') +
			(C.canEdit ? '<p><button type="button" class="button" data-a="move">Przenieś do…</button> <button type="button" class="button" data-a="trash">Do kosza</button></p>' : ''));
	}
	function dirProps() {
		var d = S.data, m = d.meta || {}, info = d.info;
		var ro = C.canEdit ? '' : ' disabled';
		if (S.flat) {
			$props.html('<h3>Podgląd: wszystkie zdjęcia' + (S.year ? ' z ' + esc(S.year) : '') + '</h3><p class="description">Widok zbiorczy z podkatalogów. Zaznacz zdjęcia, żeby dać gwiazdkę, okładkę albo autora.</p>');
			return;
		}
		var covers = (m.covers || []).map(function (f) {
			var full = (S.path ? S.path + '/' : '') + f, it = item(full);
			return '<li data-f="' + esc(f) + '">' + (it && it.thumb ? '<img alt="" src="' + esc(it.thumb) + '">' : '<span>' + esc(f) + '</span>') +
				(C.canEdit ? '<span class="fpx-cov-btns"><button type="button" data-cv="up" title="Wcześniej">‹</button><button type="button" data-cv="down" title="Później">›</button><button type="button" data-cv="rm" title="Usuń z okładek">×</button></span>' : '') + '</li>';
		}).join('');
		var isCat = info.role === 'category';
		var datable = ['event', 'month', 'edition', 'sub'].indexOf(info.role) >= 0;
		var h = '<h3>' + esc(m.title || base(S.path) || C.root) + '</h3>' +
			'<p class="fpx-role"><span class="dashicons dashicons-' + (ICON[info.role] || 'portfolio') + '"></span> ' + esc(info.label) + '</p>' +
			(info.warn ? '<p class="fpx-warn">' + esc(info.warn) + '</p>' : '') +
			(d.url ? '<p><a href="' + esc(d.url) + '" target="_blank">Zobacz na stronie ↗</a></p>' : '') +
			// ten katalog: zmiana nazwy, przeniesienie, kosz (bez zaznaczania go w katalogu wyżej)
			(C.canEdit && S.path && info.role !== 'root' ? '<p class="fpx-dir-acts"><button type="button" class="button" data-here="rename">Zmień nazwę</button> <button type="button" class="button" data-here="move">Przenieś…</button> <button type="button" class="button fpx-danger-link" data-here="trash">Do kosza</button></p>' : '');
		if (info.role !== 'root' && info.role !== 'hidden') {
			h += '<form class="fpx-meta">' +
				'<div class="fpx-f"><label>Tytuł na stronie<input type="text" name="title" value="' + esc(m.title || '') + '" placeholder="' + esc(base(S.path)) + '"' + ro + '></label></div>' +
				'<div class="fpx-f"><label>Podtytuł<input type="text" name="subtitle" value="' + esc(m.subtitle || '') + '"' + ro + '></label></div>' +
				'<div class="fpx-f"><label>Opis<textarea name="description" rows="3"' + ro + '>' + esc(m.description || '') + '</textarea></label></div>' +
				(datable ? '<div class="fpx-f"><label>' + (info.role === 'month' ? 'Rok i miesiąc (poprawka)' : 'Data wydarzenia') + '<input type="' + (info.role === 'month' ? 'month' : 'date') + '" name="date" value="' + esc(normDate(m.date, info.role)) + '"' + ro + '></label>' +
					(C.canEdit ? '<button type="button" class="button-link" data-a="today">dzisiejsza</button>' : '') + '</div>' +
					'<label class="fpx-chk"><input type="checkbox" name="archive"' + (m.archive ? ' checked' : '') + ro + '> Archiwum</label>' : '') +
				'<label class="fpx-chk"><input type="checkbox" name="hidden"' + (m.hidden ? ' checked' : '') + ro + '> Ukryj na stronie</label>' +
				(!isCat ? '<div class="fpx-f"><label>Domyślny autor zdjęć<input type="text" name="author" list="fpx-people" value="' + esc(m.author || '') + '"' + ro + '></label></div>' +
					'<div class="fpx-f"><label>Kolejność zdjęć<select name="order"' + ro + '>' +
					[['', 'Automatyczna (nazwa pliku)'], ['date', 'Wg daty zrobienia'], ['manual', 'Ręczna (przeciągnij zdjęcia)']].map(function (o) { return '<option value="' + o[0] + '"' + ((m.order || '') === o[0] || (!o[0] && m.order === 'balanced') ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') +
					'</select></label></div>' : '') +
				'<div class="fpx-f"><label>' + (isCat ? 'Mozaika kategorii' : 'Okładki') + ' <span class="description">(do 5, w tej kolejności)</span></label><ul class="fpx-covers">' + (covers || '<li class="fpx-cov-empty">Automatycznie. Zaznacz zdjęcia i kliknij „Okładka”.</li>') + '</ul></div>' +
				(C.canEdit ? '<p><button type="submit" class="button button-primary">Zapisz</button></p>' : '') +
				'</form>';
		}
		if (d.groups && d.groups.length) {
			h += '<h4>Grupy zdjęć luzem w tym katalogu</h4><ul class="fpx-groups">' + d.groups.map(function (g) {
				return '<li><b>' + esc(g.title) + '</b> · ' + g.count + ' zdj. ' + (g.url ? '<a href="' + esc(g.url) + '" target="_blank">↗</a>' : '') +
					(C.canEdit && g.key !== '*' ? ' <button type="button" class="button-link" data-mkgroup="' + esc(g.key) + '">Zrób z tego katalog</button>' : '') + '</li>';
			}).join('') + '</ul>';
		}
		h += '<datalist id="fpx-people">' + S.people.map(function (p) { return '<option value="' + esc(p) + '">'; }).join('') + '</datalist>';
		$props.html(h);
	}
	function normDate(d, role) {
		if (!d) return '';
		if (role === 'month') return d.slice(0, 7);
		return d.length === 7 ? d + '-01' : d;
	}
	function photoProps(x) {
		var ro = C.canEdit ? '' : ' disabled';
		$props.html(
			(x.thumb ? '<img class="fpx-prev" alt="" src="' + esc(x.thumb) + '">' : '') +
			'<h3>' + esc(x.name) + '</h3>' +
			(x.skipped ? '<p class="fpx-warn">' + esc(x.skipped) + '</p>' : '') +
			'<p>' + (x.w ? x.w + ' × ' + x.h + ' px · ' : '') + size(x.size) + (x.taken ? '<br>Zrobione: ' + esc(date(x.taken)) : '') + (x.event ? '<br>Wydarzenie: ' + esc(x.event) : '') + '</p>' +
			(x.id ? '<div class="fpx-f"><label>Autor<input type="text" class="fpx-author" list="fpx-people" value="' + esc(x.author || '') + '" placeholder="' + esc(x.auto_a || 'nieznany') + '"' + ro + '></label>' +
				(x.auto_a ? '<span class="description">Z pliku: ' + esc(x.auto_a) + '</span>' : '') + (C.canEdit ? '<button type="button" class="button" data-a="setauthor1">Zapisz autora</button>' : '') + '</div>' +
				(C.canEdit ? '<p><button type="button" class="button" data-a="star">' + (x.star ? '☆ Usuń gwiazdkę' : '★ Gwiazdka') + '</button> <button type="button" class="button" data-a="cover">' + (x.cover !== null && x.cover !== undefined ? 'Usuń z okładek' : 'Ustaw jako okładkę') + '</button></p>' : '') : '') +
			'<p><a href="#" data-a="view">Podgląd</a></p>' +
			'<datalist id="fpx-people">' + S.people.map(function (p) { return '<option value="' + esc(p) + '">'; }).join('') + '</datalist>'
		);
	}

	$props.on('submit', '.fpx-meta', function (e) {
		e.preventDefault();
		var f = this;
		var el = function (n) { return f.elements.namedItem(n); };
		var fields = {
			title: el('title').value, subtitle: el('subtitle').value, description: el('description').value,
			hidden: el('hidden').checked ? 1 : 0
		};
		if (el('date')) { fields.date = el('date').value || ''; fields.archive = el('archive').checked ? 1 : 0; }
		if (el('author')) fields.author = el('author').value;
		if (el('order')) fields.order = el('order').value;
		fields.covers = $(f).find('.fpx-covers li[data-f]').map(function () { return this.getAttribute('data-f'); }).get();
		if (!fields.covers.length) fields.covers = [''];
		saveMeta(fields).then(function () { setStatus('Zapisano właściwości.'); });
	});
	function saveMeta(fields, path) {
		return api('meta', { path: path === undefined ? S.path : path, fields: fields }).then(function () { return reload(); }, function (e) { setStatus(e, true); });
	}
	$props.on('click', '[data-a="today"]', function () {
		var d = new Date(), v = d.toISOString().slice(0, 10), $i = $props.find('[name="date"]');
		$i.val($i.attr('type') === 'month' ? v.slice(0, 7) : v);
	});
	$props.on('click', '[data-cv]', function () {
		var $li = $(this).closest('li'), a = this.getAttribute('data-cv');
		if (a === 'up') $li.prev().before($li);
		if (a === 'down') $li.next().after($li);
		if (a === 'rm') $li.remove();
		$props.find('.fpx-meta').trigger('submit');
	});
	$props.on('click', '[data-mkgroup]', function () {
		var key = this.getAttribute('data-mkgroup');
		var files = S.items.filter(function (x) { return x.type === 'file' && x.name.indexOf(key) === 0; }).map(function (x) { return x.path; });
		dialog({ title: 'Katalog z grupy „' + key + '”', ok: 'Utwórz i przenieś', html: '<p>Utworzę katalog „' + esc(key) + '” w tym miejscu i przeniosę do niego ' + files.length + ' zdjęć. Adres wydarzenia na stronie się nie zmieni.</p>' })
			.then(function () { return api('mkdir', { path: S.path, name: key }); })
			.then(function (r) { return api('move', { items: files, dest: r.path }); })
			.then(function () { setStatus('Utworzono katalog „' + key + '”.'); refreshTree(); reload(); }, function (e) { if (e) setStatus(e, true); });
	});

	/* ---------- akcje ---------- */
	function act(a) {
		var sel = selItems();
		switch (a) {
			case 'back': if (S.hpos > 0) { S.hpos--; restoreHist(); } break;
			case 'fwd': if (S.hpos < S.hist.length - 1) { S.hpos++; restoreHist(); } break;
			case 'up':
				if (S.mode !== 'dir') go('');
				else if (S.year) go(S.path);
				else if (S.path) go(parent(S.path));
				break;
			case 'refresh': reload(); refreshTree(); break;
			case 'selall': if (S.mode === 'dir') { S.sel = visibleItems().map(function (x) { return x.path; }); renderSel(); } break;
			case 'open': if (sel[0] && sel[0].type === 'dir') go(sel[0].path); break;
			case 'view': if (sel[0]) view(sel[0]); break;
			case 'mkdir': doMkdir(); break;
			case 'upload': $root.find('.fpx-file').trigger('click'); break;
			case 'rename': if (sel.length === 1) doRename(sel[0]); break;
			case 'move': if (sel.length) doMove(sel.map(function (x) { return x.path; })); break;
			case 'trash': if (sel.length) doTrash(sel); break;
			case 'star': photoOp(sel, sel.every(function (x) { return x.star; }) ? 'unstar' : 'star'); break;
			case 'unstar': photoOp(sel, 'unstar'); break;
			case 'cover': photoOp(sel, sel.every(function (x) { return x.cover !== null && x.cover !== undefined; }) ? 'uncover' : 'cover'); break;
			case 'setauthor': photoOp(sel, 'author', $props.find('.fpx-multi-author').val()); break;
			case 'setauthor1': photoOp(sel, 'author', $props.find('.fpx-author').val()); break;
		}
	}
	function restoreHist() { var h = S.hist[S.hpos]; go(h.path, { mode: h.mode, flat: h.flat, year: h.year }, true); }
	$root.on('click', '[data-a]', function (e) {
		if ($(this).is('a')) e.preventDefault();
		if (this.disabled) return;
		act(this.getAttribute('data-a'));
	});
	$root.find('.fpx-viewbtns').on('click', '[data-view]', function () {
		S.view = ui.view = this.getAttribute('data-view'); saveUi();
		if (S.mode === 'dir') renderDir(); updateButtons();
	});
	$root.find('.fpx-size').on('input', function () { S.size = ui.size = +this.value; $root[0].style.setProperty('--fpx-tile', S.size + 'px'); saveUi(); });
	$root.find('.fpx-flat-cb').on('change', function () { go(S.path, { flat: this.checked }); });
	$root.find('.fpx-copies-cb').prop('checked', S.copies).on('change', function () { S.copies = ui.copies = this.checked; saveUi(); if (S.mode === 'dir') reload(); });
	$root.find('.fpx-search').on('input', function () { S.filter = this.value; if (S.mode === 'dir') renderDir(); });

	function photoOp(sel, action, value) {
		var items = sel.filter(function (x) { return x.id; }).map(function (x) { return x.path; });
		if (!items.length) return;
		if (action === 'cover') {
			var cur = (S.data.meta && S.data.meta.covers) ? S.data.meta.covers.length : 0;
			if (items.length > 5) { alertBox('Za dużo okładek', 'Wydarzenie może mieć najwyżej 5 okładek. Zaznacz do 5 zdjęć.'); return; }
		}
		api('photo', { items: items, action2: action, value: value || '' }).then(function () {
			setStatus({ star: 'Dodano gwiazdki.', unstar: 'Usunięto gwiazdki.', cover: 'Dodano do okładek (najwyżej 5; najstarsze wypadają).', uncover: 'Usunięto z okładek.', author: 'Zapisano autora.' }[action] || 'Zapisano.');
			var keep = S.sel.slice();
			return reload().then(function () { S.sel = keep; renderSel(); });
		}, function (e) { setStatus(e, true); });
	}

	function doMkdir() {
		var label = mkLabel(S.data.info);
		var hint = S.data.info.role === 'category' && S.data.info.mode === 'months' ? 'Np. <code>2026-11 Listopad</code>. Wydarzenia twórz potem wewnątrz miesiąca.' :
			S.data.info.role === 'category' && S.data.info.mode === 'numbered' ? 'Np. <code>05 Niebieski</code> (wewnątrz katalogu roku) albo <code>05 niebieski 26_</code>.' :
			S.data.info.role === 'category' && S.data.info.mode === 'years' ? 'Rok, np. <code>2026</code>. Wydarzenia twórz potem wewnątrz roku.' :
			S.data.info.role === 'category' && S.data.info.mode === 'dated' ? 'Data i nazwa, np. <code>2026-05-12 Juwenalia</code>.' :
			S.data.info.role === 'root' ? 'Kategoria, np. <code>SPORT</code>. Schemat ustawisz w Strukturze.' : 'Nazwa wydarzenia, np. <code>Bal Tysiąca Świateł</code>.';
		prompt(label, 'Nazwa', '', hint).then(function (name) {
			if (!name) return;
			var path = (S.path ? S.path + '/' : '') + name;
			return confirmPath(path, 'Utwórz').then(function () { return api('mkdir', { path: S.path, name: name }); })
				.then(function (r) { setStatus('Utworzono „' + name + '”.'); refreshTree(); return go(r.path); });
		}).fail(function (e) { if (e) setStatus(e, true); });
	}
	/** Czy bieżący katalog leży w (albo jest) ścieżce p: po zmianie nazwy, przeniesieniu albo koszu trzeba z niego wyjść. */
	function inside(p) { return S.mode === 'dir' && (S.path === p || S.path.indexOf(p + '/') === 0); }
	function doRename(x) {
		var isDir = x.type === 'dir';
		prompt('Zmień nazwę', 'Nowa nazwa', x.name, isDir ? 'Zmiana nazwy katalogu zmienia adres wydarzenia na stronie. Dane z <code>_portfolio.json</code> zostają.' : 'Rozszerzenie pliku zostaje bez zmian.').then(function (name) {
			if (!name || name === x.name) return;
			var p = isDir ? confirmPath(parent(x.path) ? parent(x.path) + '/' + name : name, 'Zmień nazwę') : $.Deferred().resolve();
			return p.then(function () { return api('rename', { path: x.path, name: name }); })
				.then(function (r) {
					setStatus('Zmieniono nazwę na „' + name + '”.');
					S.dirs = null;
					if (isDir) refreshTree();
					return isDir && inside(x.path) ? go(r.path + S.path.slice(x.path.length)) : reload();
				});
		}).fail(function (e) { if (e) setStatus(e, true); });
	}
	function loadDirs() {
		if (S.dirs) return $.Deferred().resolve(S.dirs);
		return api('dirs').then(function (r) { S.dirs = r.dirs; S.people = r.people; return S.dirs; });
	}
	function doMove(paths, dest) {
		var go2 = function (d) {
			var dirs = paths.filter(function (p) { var x = item(p); return (x && x.type === 'dir') || $tree.find('li[data-path="' + cssEsc(p) + '"]').length; });
			var chk = dirs.length ? confirmPath((d ? d + '/' : '') + base(dirs[0]), 'Przenieś') : $.Deferred().resolve();
			return chk.then(function () { return api('move', { items: paths, dest: d }); }).then(function (r) {
				setStatus('Przeniesiono: ' + r.moved.length + (r.errors.length ? ' · Błędy: ' + r.errors.join('; ') : ''), !!r.errors.length);
				S.dirs = null;
				refreshTree();
				// przeniesiono katalog, w którym jesteśmy: idź za nim
				var cur = paths.filter(inside)[0];
				return cur !== undefined && r.moved.length ? go((d ? d + '/' : '') + base(cur) + S.path.slice(cur.length)) : reload();
			});
		};
		if (dest !== undefined) {
			return dialog({ title: 'Przenieść?', ok: 'Przenieś', html: '<p>Przenieść ' + paths.length + ' elem. do <b>' + esc(dest || C.root) + '</b>?</p><p class="description">Autor i gwiazdka zdjęć przechodzą razem z nimi. Okładki w starym miejscu zostaną odznaczone.</p>' })
				.then(function () { return go2(dest); }).fail(function (e) { if (e) setStatus(e, true); });
		}
		loadDirs().then(function (dirs) {
			return dialog({
				title: 'Przenieś ' + paths.length + ' elem. do…', ok: 'Przenieś',
				html: '<input type="search" class="regular-text fpx-mv-search" placeholder="Szukaj katalogu (np. listopad, bal)…" aria-label="Szukaj katalogu"><ul class="fpx-mv-list"></ul><p class="fpx-mv-info description"></p>',
				init: function ($d) {
					var chosen = null;
					function draw(q) {
						q = (q || '').toLowerCase();
						var list = [{ path: '', label: 'Katalog źródłowy' }].concat(dirs).filter(function (x) { return !q || (x.path + ' ' + x.label).toLowerCase().indexOf(q) >= 0; }).slice(0, 200);
						$d.find('.fpx-mv-list').html(list.map(function (x) { return '<li><button type="button" data-d="' + esc(x.path) + '"><b>' + esc(x.path || C.root) + '</b><span>' + esc(x.label) + '</span></button></li>'; }).join(''));
					}
					draw('');
					$d.on('input', '.fpx-mv-search', function () { draw(this.value); });
					$d.on('click', '[data-d]', function () { $d.find('.is-sel').removeClass('is-sel'); $(this).addClass('is-sel'); chosen = this.getAttribute('data-d'); $d.find('.fpx-mv-info').text('Cel: ' + (chosen || C.root)); });
					$d.on('dblclick', '[data-d]', function () { $d.find('.fpx-yes').trigger('click'); });
					$d.data('chosen', function () { return chosen; });
				},
				value: function ($d) { return $d.data('chosen')(); }
			});
		}).then(function (d) { if (d === null) { setStatus('Nie wybrano katalogu docelowego.', true); return; } return go2(d); })
			.fail(function (e) { if (e) setStatus(e, true); });
	}
	function doTrash(sel) {
		var dirs = sel.filter(function (x) { return x.type === 'dir'; });
		var photos = sel.filter(function (x) { return x.type === 'file'; });
		dialog({
			title: 'Przenieść do kosza?', ok: 'Do kosza', danger: true,
			html: '<p>' + (dirs.length ? dirs.length + ' katalogów (z całą zawartością)' : '') + (dirs.length && photos.length ? ' i ' : '') + (photos.length ? photos.length + ' plików' : '') + ' zniknie z portfolio.</p>' +
				'<ul class="fpx-trash-list">' + sel.slice(0, 12).map(function (x) { return '<li>' + esc(x.path) + '</li>'; }).join('') + (sel.length > 12 ? '<li>…</li>' : '') + '</ul>' +
				'<p class="description">Nic nie jest kasowane: elementy trafiają do Kosza i możesz je przywrócić. Trwale usunąć może tylko administrator.</p>'
		}).then(function () {
			return api('trash', { items: sel.map(function (x) { return x.path; }) }).then(function (r) {
				setStatus('W koszu: ' + r.trashed.length + (r.errors.length ? ' · Błędy: ' + r.errors.join('; ') : ''), !!r.errors.length);
				S.dirs = null;
				if (dirs.length) refreshTree();
				var cur = dirs.map(function (x) { return x.path; }).filter(inside)[0];
				return cur !== undefined ? go(parent(cur)) : reload();
			});
		}).fail(function (e) { if (e) setStatus(e, true); });
	}

	/* ---------- menu kontekstowe (prawy przycisk; drugi prawy klik albo Shift = zwykłe menu przeglądarki) ---------- */
	var $ctx = $root.find('.fpx-ctx'), ctxTarget = null;
	function hideCtx() { $ctx.prop('hidden', true); }
	function wantsNative(e) {
		if (e.shiftKey) return true;
		if (!$ctx.prop('hidden')) { hideCtx(); return true; }
		return false;
	}
	/** rows: [akcja, etykieta, 'a' = akcja paska (zaznaczenie) | 'x' = akcja na ctxTarget] albo '-' (separator) */
	function showCtx(e, rows) {
		e.preventDefault();
		if (!rows.length) return;
		$ctx.html(rows.map(function (r) {
			return r === '-' ? '<hr>' : '<button type="button" ' + (r[2] === 'x' ? 'data-cx' : 'data-a') + '="' + r[0] + '"' + (r[3] ? ' class="fpx-ctx-danger"' : '') + '>' + esc(r[1]) + '</button>';
		}).join('')).prop('hidden', false);
		var w = $ctx.outerWidth(), h = $ctx.outerHeight();
		$ctx.css({ left: Math.max(4, Math.min(e.clientX, window.innerWidth - w - 6)), top: Math.max(4, Math.min(e.clientY, window.innerHeight - h - 6)) });
	}
	// zdjęcia i katalogi w środkowym panelu
	$main.on('contextmenu', '.fpx-it', function (e) {
		e.stopPropagation(); // bez menu „pustego miejsca” pod spodem
		if (wantsNative(e)) return;
		var p = this.getAttribute('data-p');
		if (S.sel.indexOf(p) < 0) { S.sel = [p]; renderSel(); }
		var sel = selItems(), one = sel.length === 1 ? sel[0] : null;
		var photos = sel.filter(function (x) { return x.id; });
		var rows = [];
		if (one && one.type === 'dir') rows.push(['open', 'Otwórz']);
		if (one && one.type === 'file') rows.push(['view', 'Podgląd']);
		if (C.canEdit) {
			rows.push('-');
			if (one) rows.push(['rename', 'Zmień nazwę… (F2)']);
			rows.push(['move', 'Przenieś do…']);
			if (photos.length) { rows.push(['star', '★ Gwiazdka (przełącz)']); rows.push(['cover', 'Okładka (przełącz)']); }
			rows.push('-');
			rows.push(['trash', 'Do kosza (Del)', 'a', 1]);
		}
		showCtx(e, rows);
	});
	// puste miejsce w środkowym panelu: ten katalog
	$main.on('contextmenu', function (e) {
		if (S.mode !== 'dir' || wantsNative(e)) return;
		ctxTarget = { type: 'dir', path: S.path, name: base(S.path) };
		var rows = [];
		if (C.canEdit && !S.flat) rows.push(['mkdir', mkLabel(S.data.info) + '…'], ['upload', 'Wgraj zdjęcia…'], '-');
		rows.push(['selall', 'Zaznacz wszystko (Ctrl+A)'], ['refresh', 'Odśwież (F5)']);
		if (C.canEdit && S.path) rows.push('-', ['rename', 'Zmień nazwę tego katalogu…', 'x'], ['move', 'Przenieś ten katalog…', 'x'], ['trash', 'Ten katalog do kosza', 'x', 1]);
		showCtx(e, rows);
	});
	// katalogi w drzewie
	$tree.on('contextmenu', '.fpx-tn[data-path]:not([data-year])', function (e) {
		if (wantsNative(e)) return;
		var path = this.getAttribute('data-path');
		ctxTarget = { type: 'dir', path: path, name: base(path) };
		var rows = [['open', 'Otwórz', 'x']];
		if (C.canEdit) rows.push(['mkdir-in', 'Nowy katalog w środku…', 'x'], ['upload-in', 'Wgraj zdjęcia tutaj…', 'x'], '-', ['rename', 'Zmień nazwę…', 'x'], ['move', 'Przenieś do…', 'x'], '-', ['trash', 'Do kosza', 'x', 1]);
		showCtx(e, rows);
	});
	$ctx.on('click', '[data-cx]', function () {
		var a = this.getAttribute('data-cx'), t = ctxTarget;
		hideCtx();
		if (!t) return;
		if (a === 'open') go(t.path);
		else if (a === 'mkdir-in') go(t.path).then(doMkdir);
		else if (a === 'upload-in') go(t.path).then(function () { $root.find('.fpx-file').trigger('click'); });
		else if (a === 'rename') doRename(t);
		else if (a === 'move') doMove([t.path]);
		else if (a === 'trash') doTrash([t]);
	});
	$props.on('click', '[data-here]', function () {
		var t = { type: 'dir', path: S.path, name: base(S.path) }, a = this.getAttribute('data-here');
		if (a === 'rename') doRename(t);
		else if (a === 'move') doMove([t.path]);
		else if (a === 'trash') doTrash([t]);
	});
	$(document).on('click', hideCtx);
	$(window).on('blur resize', hideCtx);
	$main.on('scroll', hideCtx);
	$(document).on('keydown', function (e) { if (e.key === 'Escape') hideCtx(); });

	/* ---------- klawiatura ---------- */
	$(document).on('keydown', function (e) {
		if (!$modal.prop('hidden') || !$viewer.prop('hidden') || $(e.target).is('input, textarea, select')) return;
		if (!$.contains($root[0], document.activeElement) && document.activeElement !== document.body) return;
		var k = e.key;
		if (k === 'Delete' && S.sel.length && C.canEdit) { e.preventDefault(); act('trash'); }
		else if (k === 'F2' && S.sel.length === 1 && C.canEdit) { e.preventDefault(); act('rename'); }
		else if (k === 'Enter' && S.sel.length === 1) { e.preventDefault(); var x = selItems()[0]; if (x.type === 'dir') go(x.path); else view(x); }
		else if (k === 'Backspace') { e.preventDefault(); act('up'); }
		else if (k === 'F5') { e.preventDefault(); act('refresh'); }
		else if ((e.ctrlKey || e.metaKey) && k.toLowerCase() === 'a' && S.mode === 'dir') { e.preventDefault(); S.sel = visibleItems().map(function (x) { return x.path; }); renderSel(); }
		else if (e.altKey && k === 'ArrowLeft') act('back');
		else if (e.altKey && k === 'ArrowRight') act('fwd');
	});

	/* ---------- przeciąganie ---------- */
	var dragPaths = null;
	$root.on('dragstart', '.fpx-it', function (e) {
		if (!C.canEdit) return;
		var p = this.getAttribute('data-p');
		if (S.sel.indexOf(p) < 0) { S.sel = [p]; renderSel(); }
		dragPaths = S.sel.slice();
		e.originalEvent.dataTransfer.setData('text/plain', dragPaths.join('\n'));
		e.originalEvent.dataTransfer.effectAllowed = 'move';
	});
	$root.on('dragend', '.fpx-it', function () { dragPaths = null; $root.find('.is-drop').removeClass('is-drop'); });
	$root.on('dragover', '[data-drop], .fpx-file, .fpx-main', function (e) {
		if (!C.canEdit || S.mode !== 'dir') return;
		e.preventDefault();
		e.stopPropagation();
		$root.find('.is-drop').removeClass('is-drop');
		$(this).addClass('is-drop');
	});
	$root.on('dragleave', '[data-drop], .fpx-main', function () { $(this).removeClass('is-drop'); });
	$root.on('drop', '[data-drop], .fpx-file, .fpx-main', function (e) {
		if (!C.canEdit || S.mode !== 'dir') return;
		e.preventDefault();
		e.stopPropagation();
		$root.find('.is-drop').removeClass('is-drop');
		var dt = e.originalEvent.dataTransfer;
		var $t = $(this);
		// pliki z komputera → wgraj do wskazanego katalogu
		if (dt.files && dt.files.length && !dragPaths) {
			upload(dt.files, $t.is('[data-drop]') ? $t.attr('data-drop') : S.path);
			return;
		}
		if (!dragPaths) return;
		var paths = dragPaths;
		dragPaths = null;
		// upuszczenie na zdjęcie w trybie ręcznej kolejności = zmiana kolejności
		if ($t.hasClass('fpx-file') && (S.data.meta || {}).order === 'manual' && !S.flat) {
			var target = $t.attr('data-p');
			var names = S.items.filter(function (x) { return x.type === 'file'; }).map(function (x) { return x.path; });
			names = names.filter(function (p) { return paths.indexOf(p) < 0; });
			var at = names.indexOf(target);
			names.splice(at < 0 ? names.length : at, 0, ...paths.filter(function (p) { var x = item(p); return x && x.type === 'file'; }));
			saveMeta({ order: 'manual', manual: names.map(base) }).then(function () { setStatus('Zapisano kolejność zdjęć.'); });
			return;
		}
		var dest = $t.attr('data-drop');
		if (dest === undefined || paths.indexOf(dest) >= 0 || paths.every(function (p) { return parent(p) === dest; })) return;
		doMove(paths, dest);
	});

	/* ---------- wgrywanie ---------- */
	$root.find('.fpx-file').on('change', function () { upload(this.files, S.path); this.value = ''; });
	function upload(files, dest) {
		files = Array.prototype.slice.call(files).filter(function (f) { return /\.(jpe?g|png|webp|zip)$/i.test(f.name); });
		if (!files.length) { setStatus('Wgrywać można JPG, PNG, WEBP i ZIP.', true); return; }
		var big = files.filter(function (f) { return C.maxSize && f.size > C.maxSize; });
		if (big.length) { setStatus(big.length + ' plików jest większych niż limit serwera (' + size(C.maxSize) + ') i zostanie pominiętych.', true); files = files.filter(function (f) { return big.indexOf(f) < 0; }); }
		var check = dest === S.path ? $.Deferred().resolve() : confirmPath(dest, 'Wgraj');
		check.then(function () {
			var i = 0, ok = 0, bad = [];
			function next() {
				if (i >= files.length) {
					setStatus('Wgrano ' + ok + ' z ' + files.length + (bad.length ? ' · Pominięto: ' + bad.join('; ') : '') + '. Miniatury zrobią się przy pierwszym wyświetleniu.', !!bad.length);
					return reload();
				}
				var f = files[i++];
				setStatus('Wgrywam ' + i + ' z ' + files.length + ': ' + f.name + '…');
				var fd = new FormData();
				fd.append('action', 'fp_fs'); fd.append('nonce', A.nonce); fd.append('op', 'upload'); fd.append('path', dest); fd.append('file', f);
				if (i === files.length) fd.append('last', '1');
				$.ajax({ url: A.ajax, type: 'POST', data: fd, processData: false, contentType: false }).then(function (r) {
					if (r.success) { ok += r.data.saved.length; bad = bad.concat(r.data.skipped); } else bad.push(f.name + ': ' + r.data.message);
				}, function (x) { bad.push(f.name + ': błąd ' + x.status); }).always(next);
			}
			next();
		});
	}

	/* ---------- Do uporządkowania ---------- */
	var TYPES = { no_year: 'Bez roku', no_date: 'Bez daty', loose: 'Zdjęcia luzem', empty: 'Puste katalogi' };
	function fixButtons(p, inline) {
		if (!C.canEdit) return '';
		var b = '<span class="fpx-fix" data-path="' + esc(p.path) + '" data-type="' + esc(p.type) + '">';
		if (p.type === 'no_year' || p.type === 'no_date') {
			b += '<button type="button" class="button" data-fix="date">Wpisz datę</button><button type="button" class="button" data-fix="today">Dzisiejsza data</button><button type="button" class="button" data-fix="archive">Archiwum</button>';
			if (p.type === 'no_date') b += '<button type="button" class="button" data-fix="move">Przenieś do miesiąca…</button>';
		}
		if (p.type === 'loose') b += '<button type="button" class="button" data-fix="loose">Utwórz wydarzenie i przenieś</button>';
		if (p.type === 'empty') b += '<button type="button" class="button" data-fix="trash">Do kosza</button>';
		if (!inline) b += '<button type="button" class="button-link" data-fix="open">Otwórz</button>';
		return b + '</span>';
	}
	function renderInbox() {
		var list = S.data.problems || [];
		$main.attr('data-view', 'inbox');
		if (!list.length) { $main.html('<div class="fpx-empty fpx-ok">Wszystko uporządkowane.</div>'); setStatus('Brak problemów.'); renderProps(); return; }
		var counts = {};
		list.forEach(function (p) { counts[p.type] = (counts[p.type] || 0) + 1; });
		if (S.ibType && !counts[S.ibType]) S.ibType = '';
		var closed = ui.ibClosed || {};
		var chip = function (t, label, n) { return '<button type="button" class="fpx-chip' + (S.ibType === t ? ' is-on' : '') + '" data-ibt="' + t + '">' + esc(label) + ' <span>' + n + '</span></button>'; };
		// filtr rodzaju i zwijanie grup
		var html = '<div class="fpx-ib-bar">' + chip('', 'Wszystkie', list.length) +
			Object.keys(TYPES).filter(function (t) { return counts[t]; }).map(function (t) { return chip(t, TYPES[t], counts[t]); }).join('') +
			'<span class="fpx-ib-tog"><button type="button" class="button-link" data-ibo="1">Rozwiń wszystkie</button> · <button type="button" class="button-link" data-ibo="0">Zwiń wszystkie</button></span></div>';
		Object.keys(TYPES).forEach(function (t) {
			var rows = list.filter(function (p) { return p.type === t; });
			if (!rows.length || (S.ibType && S.ibType !== t)) return;
			html += '<details class="fpx-ib-sec" data-t="' + t + '"' + (closed[t] ? '' : ' open') + '><summary><span class="fpx-ib-h">' + esc(TYPES[t]) + '</span> <span class="fpx-ib-n">' + rows.length + '</span></summary><ul class="fpx-inbox">' + rows.map(function (p, i) {
				return '<li><div class="fpx-ib-main"><code>' + esc(p.path) + '</code><p>' + esc(p.msg) + '</p>' + inboxThumbs(p, t, i) + '</div>' + fixButtons(p) + '</li>';
			}).join('') + '</ul></details>';
		});
		$main.html(html);
		setStatus(list.length + ' spraw do uporządkowania.');
		renderProps();
	}
	/** Miniatury zdjęć, których dotyczy problem (klik = podgląd). */
	function inboxThumbs(p, t, i) {
		if (!p.thumbs || !p.thumbs.length) return p.type === 'empty' ? '' : '<p class="fpx-ib-none">Brak podglądu (zdjęcia nie są jeszcze w portfolio).</p>';
		return '<div class="fpx-ib-thumbs">' + p.thumbs.map(function (th, k) {
			return '<button type="button" class="fpx-ib-th" data-ibv="' + t + '|' + i + '|' + k + '" title="' + esc(th.name) + '"><img loading="lazy" alt="" src="' + esc(th.thumb) + '"></button>';
		}).join('') + (p.count > p.thumbs.length ? '<span class="fpx-ib-more">+' + (p.count - p.thumbs.length) + '</span>' : '') + '</div>';
	}
	$main.on('click', '[data-ibt]', function () { S.ibType = this.getAttribute('data-ibt'); renderInbox(); });
	$main.on('click', '[data-ibo]', function () {
		var open = this.getAttribute('data-ibo') === '1';
		ui.ibClosed = {};
		if (!open) Object.keys(TYPES).forEach(function (t) { ui.ibClosed[t] = 1; });
		saveUi();
		$main.find('.fpx-ib-sec').prop('open', open);
	});
	// zapamiętaj zwinięte grupy („toggle” nie bąbelkuje, więc w fazie przechwytywania)
	$main[0].addEventListener('toggle', function (e) {
		var d = e.target;
		if (!d.classList || !d.classList.contains('fpx-ib-sec')) return;
		ui.ibClosed = ui.ibClosed || {};
		if (d.open) delete ui.ibClosed[d.getAttribute('data-t')]; else ui.ibClosed[d.getAttribute('data-t')] = 1;
		saveUi();
	}, true);
	$main.on('click', '[data-ibv]', function () {
		var k = this.getAttribute('data-ibv').split('|');
		var p = (S.data.problems || []).filter(function (x) { return x.type === k[0]; })[+k[1]];
		if (!p) return;
		var list = p.thumbs.map(function (th) { return { name: th.name, thumb: th.thumb, large: th.large }; });
		view(list[+k[2]], list);
	});
	$main.on('click', '[data-fix]', function () {
		var $f = $(this).closest('.fpx-fix'), path = $f.attr('data-path'), type = $f.attr('data-type'), fix = this.getAttribute('data-fix');
		var problem = (S.data.problems || []).find(function (p) { return p.path === path && p.type === type; }) || { files: [] };
		var month = type === 'no_year';
		var done = function (msg) { setStatus(msg); renderSpecial(); return reload(); };
		if (fix === 'open') return go(path);
		if (fix === 'today') {
			var t = new Date().toISOString().slice(0, 10);
			return saveMeta({ date: month ? t.slice(0, 7) : t, archive: 0 }, path).then(function () { done('Ustawiono dzisiejszą datę.'); });
		}
		if (fix === 'archive') return saveMeta({ archive: 1 }, path).then(function () { done('Przeniesiono do Archiwum (pliki zostały na miejscu).'); });
		if (fix === 'date') {
			return dialog({
				title: month ? 'Rok i miesiąc dla „' + base(path) + '”' : 'Data wydarzenia „' + base(path) + '”', ok: 'Zapisz',
				html: '<label class="fpx-lbl">' + (month ? 'Rok i miesiąc' : 'Data') + '<input type="' + (month ? 'month' : 'date') + '" class="fpx-in"></label><p class="description">Zapis w <code>_portfolio.json</code> tego katalogu; nazwa katalogu się nie zmienia.</p>',
				value: function ($d) { return $d.find('.fpx-in').val(); }
			}).then(function (v) { if (v) return saveMeta({ date: v, archive: 0 }, path).then(function () { done('Zapisano datę.'); }); });
		}
		if (fix === 'move') return doMove([path]);
		if (fix === 'trash') return api('trash', { items: [path] }).then(function () { done('Pusty katalog jest w koszu.'); refreshTree(); });
		if (fix === 'loose') {
			return prompt('Nowe wydarzenie z ' + problem.files.length + ' zdjęć', 'Nazwa wydarzenia', '', 'Katalog powstanie w <code>' + esc(path) + '</code> i trafią do niego zdjęcia luzem bez nazwy.').then(function (name) {
				if (!name) return;
				return api('mkdir', { path: path, name: name }).then(function (r) { return api('move', { items: problem.files, dest: r.path }); })
					.then(function (r) { refreshTree(); done('Utworzono wydarzenie „' + name + '” (' + r.moved.length + ' zdjęć).'); });
			}).fail(function (e) { if (e) setStatus(e, true); });
		}
	});

	/* ---------- Kosz ---------- */
	function renderTrash() {
		var list = S.data.items || [];
		$main.attr('data-view', 'trash');
		var html = '<div class="fpx-trash-bar">' + (C.canAdmin && list.length ? '<button type="button" class="button fpx-danger-btn" data-tr="empty">Opróżnij kosz</button>' : '') + '</div>';
		if (!list.length) html += '<div class="fpx-empty">Kosz jest pusty.</div>';
		else html += '<table class="widefat striped fpx-table"><thead><tr><th>Element</th><th>Usunięto</th><th>Kto</th><th>Rozmiar</th><th></th></tr></thead><tbody>' + list.map(function (x) {
			return '<tr><td><span class="dashicons dashicons-' + (x.type === 'dir' ? 'category' : 'format-image') + '"></span> ' + esc(x.orig) + '</td><td>' + esc(date(x.when)) + '</td><td>' + esc(x.who) + '</td><td>' + (x.size ? size(x.size) : '') + '</td><td>' +
				(C.canEdit ? '<button type="button" class="button" data-tr="restore" data-t="' + esc(x.trash) + '">Przywróć</button> ' : '') +
				(C.canAdmin ? '<button type="button" class="button-link fpx-danger-link" data-tr="purge" data-t="' + esc(x.trash) + '">Usuń na zawsze</button>' : '') + '</td></tr>';
		}).join('') + '</tbody></table>';
		$main.html(html);
		setStatus(list.length + ' elementów w koszu.');
		renderProps();
	}
	$main.on('click', '[data-tr]', function () {
		var a = this.getAttribute('data-tr'), t = this.getAttribute('data-t');
		if (a === 'restore') return api('restore', { items: [t] }).then(function (r) { setStatus('Przywrócono: ' + r.restored.join(', ')); refreshTree(); reload(); }, function (e) { setStatus(e, true); });
		var what = a === 'empty' ? 'wszystko w koszu' : '„' + t.replace(/^__kosz\/[^/]+\//, '') + '”';
		dialog({ title: 'Usunąć na zawsze?', ok: 'Usuń na zawsze', danger: true, html: '<p>Trwale usunę ' + esc(what) + ' z serwera. <b>Tego nie da się cofnąć.</b></p><label class="fpx-chk"><input type="checkbox" class="fpx-sure"> Rozumiem</label>',
			value: function ($d) { return $d.find('.fpx-sure').prop('checked') ? true : null; } })
			.then(function (ok) {
				if (!ok) { setStatus('Zaznacz „Rozumiem”, żeby usunąć.', true); return; }
				return (a === 'empty' ? api('empty_trash') : api('purge', { items: [t] })).then(function () { setStatus('Usunięto trwale.'); reload(); });
			}).fail(function (e) { if (e) setStatus(e, true); });
	});

	/* ---------- start ---------- */
	function refreshTree() {
		var open = $tree.find('li[aria-expanded="true"]').map(function () { return $(this).attr('data-path'); }).get();
		return loadTree($tree, '').then(function () {
			var chain = $.Deferred().resolve();
			open.forEach(function (p) { chain = chain.then(function () { var $li = $tree.find('li[data-path="' + cssEsc(p) + '"]').first(); if ($li.length) { toggleNode($li, true); } }); });
			return chain;
		}).then(function () { return expandTo(S.path); });
	}
	renderSpecial();
	loadDirs();
	loadTree($tree, '').then(function () {
		if (C.start === 'inbox') go('', { mode: 'inbox' });
		else if (C.start === 'trash') go('', { mode: 'trash' });
		else go(C.path || '').then(function () { expandTo(S.path); });
	});
});
