/* Focus Portfolio – Zdjęcia: wiersze dopełnione do krawędzi z lekkim chaosem, liczba zdjęć na urządzenie,
   tasowanie w przeglądarce i „Losuj ponownie”. Bez zależności; w edytorze Elementora startuje dla nowo wstawionych widżetów. */
(function () {
	'use strict';

	function shuffle(a) {
		a = a.slice();
		for (var k = a.length - 1; k > 0; k--) { var j = Math.floor(Math.random() * (k + 1)), x = a[k]; a[k] = a[j]; a[j] = x; }
		return a;
	}
	function ratio(t) { return parseFloat(t.getAttribute('data-r')) || 1.5; } // już przycięte do 0,7–1,5
	function device() { var w = window.innerWidth; return w <= 767 ? 2 : (w <= 1024 ? 1 : 0); } // jak Elementor i portfolio.css

	function init(root) {
		if (!root || root._fpp) return;
		var conf;
		try { conf = JSON.parse(root.getAttribute('data-fpp') || '{}'); } catch (e) { conf = {}; }
		var box = root.querySelector('.fpp-box');
		if (!box) return;
		root._fpp = true;
		var tiles = Array.prototype.slice.call(box.querySelectorAll('.fpp-it'));
		var chaos = Math.max(0, Math.min(100, +conf.chaos || 0)) / 100;
		var jitter = [], last = '';

		function seed() {
			// stała dla danego tasowania: kolejność w wierszu, wysokości wierszy i kadr (żeby zmiana szerokości nie mieszała)
			jitter = [];
			tiles.forEach(function (t) {
				t._o = Math.random();
				var img = t.querySelector('img');
				if (img && conf.view === 'rows' && chaos) img.style.objectPosition = (50 + (Math.random() - 0.5) * 30 * chaos) + '% ' + (50 + (Math.random() - 0.5) * 40 * chaos) + '%';
			});
		}
		function reorder() {
			tiles = shuffle(tiles);
			tiles.forEach(function (t) { box.appendChild(t); });
			seed();
		}

		function layout(force) {
			var d = device(), n = Math.min((conf.counts || [])[d] || tiles.length, tiles.length);
			var key = d + ':' + box.clientWidth;
			if (!force && key === last) return;
			last = key;
			if (conf.view !== 'rows') {
				tiles.forEach(function (t, i) { t.hidden = i >= n; });
				return;
			}
			rows(Math.max(1, Math.min((conf.rows || [])[d] || 1, n)), n);
		}

		/* wiersze: rozdaj zdjęcia tak, by sumy proporcji były zbliżone (wężykiem po posortowanych),
		   wysokość wiersza = szerokość / suma proporcji (wiersz dochodzi do krawędzi), ±6 % przy chaosie 50 % */
		function rows(nr, n) {
			var pick = tiles.slice(0, n);
			var R = [];
			for (var i = 0; i < nr; i++) R.push([]);
			pick.slice().sort(function (a, b) { return ratio(a) - ratio(b); }).forEach(function (t, i) {
				var k = Math.floor(i / nr), pos = i % nr;
				R[k % 2 ? nr - 1 - pos : pos].push(t);
			});
			var W = box.clientWidth, gap = parseFloat(getComputedStyle(box).rowGap) || 0;
			var frag = document.createDocumentFragment();
			R.forEach(function (row, ri) {
				if (!row.length) return;
				row.sort(function (a, b) { return a._o - b._o; });
				var sum = row.reduce(function (s, t) { return s + ratio(t); }, 0);
				if (jitter[ri] === undefined) jitter[ri] = 1 + (Math.random() * 2 - 1) * 0.12 * chaos;
				var div = document.createElement('div');
				div.className = 'fpp-row';
				div.style.height = Math.max(40, Math.round((W - gap * (row.length - 1)) / sum * jitter[ri])) + 'px';
				row.forEach(function (t) { t.hidden = false; t.style.flex = ratio(t).toFixed(4) + ' 1 0'; div.appendChild(t); });
				frag.appendChild(div);
			});
			tiles.slice(n).forEach(function (t) { t.hidden = true; box.appendChild(t); }); // zapas z cache
			Array.prototype.forEach.call(box.querySelectorAll('.fpp-row'), function (r) { r.parentNode.removeChild(r); });
			box.insertBefore(frag, box.firstChild);
			box.classList.add('is-ready');
		}

		seed();
		if (conf.shuffle) reorder();
		layout(true);
		var again = root.querySelector('.fpp-again');
		if (again) again.addEventListener('click', function () { reorder(); layout(true); });
		var tm;
		window.addEventListener('resize', function () { clearTimeout(tm); tm = setTimeout(function () { layout(false); }, 150); });
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('.fpp[data-fpp]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();

	// podgląd Elementora wstawia i przerysowuje widżety bez przeładowania strony
	if (document.body && document.body.classList.contains('elementor-editor-active') && window.MutationObserver) {
		new MutationObserver(function () { boot(); }).observe(document.body, { childList: true, subtree: true });
	}
})();
