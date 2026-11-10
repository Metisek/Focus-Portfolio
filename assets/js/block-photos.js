/* Blok „Focus Portfolio – Zdjęcia” dla edytora Gutenberg (bez kroku budowania). Podgląd renderuje serwer. */
(function (wp) {
	'use strict';
	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var C = wp.components;
	var SSR = wp.serverSideRender;
	var conf = window.FP_PHOTOS_BLOCK || { controls: [], labels: {} };

	wp.blocks.registerBlockType('focus/photos', {
		apiVersion: 3,
		title: 'Focus Portfolio – Zdjęcia',
		description: 'Zdjęcia z portfolio na dowolnej stronie: losowo, wyróżnione, po jednym z ostatnich wydarzeń, z katalogów albo wybrane.',
		icon: 'images-alt2',
		category: 'media',
		keywords: ['zdjęcia', 'losowe', 'portfolio', 'focus'],
		supports: { align: ['wide', 'full'], html: false, spacing: { margin: true, padding: true } },
		edit: function (props) {
			var a = props.attributes;
			var fields = conf.controls.map(function (c) {
				var label = conf.labels[c.key] || c.key;
				var onChange = function (val) {
					var o = {};
					o[c.key] = val === undefined || val === null ? '' : String(val);
					props.setAttributes(o);
				};
				if (c.type === 'select') {
					return el(C.SelectControl, { key: c.key, label: label, value: a[c.key], options: c.options, onChange: onChange });
				}
				return el(C.TextControl, { key: c.key, type: c.type === 'number' ? 'number' : 'text', label: label, value: a[c.key], placeholder: 'jak w ustawieniach', onChange: onChange });
			});
			return el('div', be.useBlockProps(),
				el(be.InspectorControls, null,
					el(C.PanelBody, { title: 'Zdjęcia', initialOpen: true },
						fields,
						el('p', { className: 'components-base-control__help' }, 'Puste pola biorą wartości z Focus Portfolio → Wygląd → „Zdjęcia na innych stronach”.')
					)
				),
				el(C.Disabled, null, el(SSR, { block: 'focus/photos', attributes: a }))
			);
		},
		save: function () { return null; }
	});
})(window.wp);
