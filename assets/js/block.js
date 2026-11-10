/* Blok „Focus Portfolio” dla edytora Gutenberg (bez kroku budowania). Podgląd renderuje serwer. */
(function (wp) {
	'use strict';
	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var C = wp.components;
	var SSR = wp.serverSideRender;
	var conf = window.FP_BLOCK || { controls: [] };

	wp.blocks.registerBlockType('focus/portfolio', {
		apiVersion: 3,
		title: 'Focus Portfolio',
		description: 'Portfolio zdjęć z folderu na hostingu: kategorie, wydarzenia i galerie.',
		icon: 'format-gallery',
		category: 'media',
		keywords: ['portfolio', 'galeria', 'focus'],
		supports: { align: ['wide', 'full'], html: false, spacing: { margin: true, padding: true } },
		edit: function (props) {
			var a = props.attributes;
			var fields = conf.controls.map(function (c) {
				var onChange = function (val) {
					var o = {};
					o[c.key] = val === undefined || val === null ? '' : String(val);
					props.setAttributes(o);
				};
				if (c.type === 'number') {
					return el(C.TextControl, { key: c.key, type: 'number', label: c.label, value: a[c.key], placeholder: 'jak w ustawieniach', onChange: onChange });
				}
				return el(C.SelectControl, { key: c.key, label: c.label, value: a[c.key], options: c.options, onChange: onChange });
			});
			return el('div', be.useBlockProps(),
				el(be.InspectorControls, null,
					el(C.PanelBody, { title: 'Portfolio', initialOpen: true },
						fields,
						el('p', { className: 'components-base-control__help' }, 'Puste pola biorą wartości z Wygląd → Portfolio. Odstępy ustawisz w sekcji Wymiary.')
					)
				),
				el(C.Disabled, null, el(SSR, { block: 'focus/portfolio', attributes: a }))
			);
		},
		save: function () { return null; }
	});
})(window.wp);
