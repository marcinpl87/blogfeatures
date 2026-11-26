(function (blocks, element, blockEditor, components) {
	'use strict';

	var el = element.createElement;
	var useState = element.useState;
	var useBlockProps = blockEditor.useBlockProps || function (extra) {
		return extra || {};
	};
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var Button = components.Button;

	function blankItem() {
		return { title: '', desc: '', url: '' };
	}

	function itemName(item, index) {
		var title = (item.title || '').trim();
		var url = (item.url || '').trim();
		var name = title || url || '(bez tytułu)';
		return (index + 1) + '. ' + name;
	}

	function Edit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var items = Array.isArray(attributes.items) ? attributes.items : [];
		var openState = useState(null);
		var openIndex = openState[0];
		var setOpenIndex = openState[1];
		var blockProps = useBlockProps({ className: 'bf-playlist-editor' });

		function commit(next, open) {
			setAttributes({ items: next });
			if (open !== undefined) {
				setOpenIndex(open);
			}
		}

		function update(index, key, value) {
			var next = items.map(function (item, i) {
				if (i !== index) {
					return item;
				}
				var copy = Object.assign({}, item);
				copy[key] = value;
				return copy;
			});
			setAttributes({ items: next });
		}

		function move(index, delta) {
			var target = index + delta;
			if (target < 0 || target >= items.length) {
				return;
			}
			var next = items.slice();
			var swapped = next[target];
			next[target] = next[index];
			next[index] = swapped;
			var open = openIndex;
			if (openIndex === index) {
				open = target;
			} else if (openIndex === target) {
				open = index;
			}
			commit(next, open);
		}

		function remove(index) {
			var next = items.filter(function (_, i) {
				return i !== index;
			});
			var open = openIndex;
			if (openIndex === index) {
				open = null;
			} else if (openIndex !== null && openIndex > index) {
				open = openIndex - 1;
			}
			commit(next, open);
		}

		function add() {
			commit(items.concat([blankItem()]), items.length);
		}

		var children = [
			el('p', { className: 'bf-playlist-editor-hint' }, 'Ta lista należy tylko do tej strony.'),
			el(TextControl, {
				label: 'Nagłówek (opcjonalnie)',
				value: attributes.heading || '',
				onChange: function (value) {
					setAttributes({ heading: value });
				},
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true
			})
		];

		items.forEach(function (item, index) {
			var expanded = openIndex === index;
			var fields = null;
			if (expanded) {
				fields = el('div', { className: 'bf-playlist-editor-fields' },
					el(TextControl, {
						label: 'Tytuł',
						value: item.title || '',
						onChange: function (value) { update(index, 'title', value); },
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true
					}),
					el(TextareaControl, {
						label: 'Opis',
						value: item.desc || '',
						rows: 3,
						onChange: function (value) { update(index, 'desc', value); },
						__nextHasNoMarginBottom: true
					}),
					el(TextControl, {
						label: 'Adres URL',
						value: item.url || '',
						placeholder: 'https://… lub /mp3/…',
						onChange: function (value) { update(index, 'url', value); },
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true
					})
				);
			}

			children.push(el('div', { className: 'bf-playlist-editor-item', key: 'item-' + index },
				el('div', { className: 'bf-playlist-editor-bar' },
					el('button', {
						type: 'button',
						className: 'bf-playlist-editor-toggle',
						'aria-expanded': expanded ? 'true' : 'false',
						onClick: function () {
							setOpenIndex(expanded ? null : index);
						}
					}, (expanded ? '▾ ' : '▸ ') + itemName(item, index)),
					el('div', { className: 'bf-playlist-editor-actions' },
						el(Button, {
							icon: 'arrow-up-alt2',
							label: 'W górę',
							showTooltip: true,
							disabled: index === 0,
							onClick: function () { move(index, -1); }
						}),
						el(Button, {
							icon: 'arrow-down-alt2',
							label: 'W dół',
							showTooltip: true,
							disabled: index === items.length - 1,
							onClick: function () { move(index, 1); }
						}),
						el(Button, {
							icon: 'no-alt',
							label: 'Usuń',
							showTooltip: true,
							isDestructive: true,
							onClick: function () { remove(index); }
						})
					)
				),
				fields
			));
		});

		children.push(el(Button, {
			key: 'add',
			variant: 'primary',
			onClick: add
		}, 'Dodaj pozycję'));

		return el('div', blockProps, children);
	}

	blocks.registerBlockType('blogfeatures/playlist', {
		title: 'Playlista',
		icon: 'playlist-audio',
		category: 'widgets',
		description: 'Lista pozycji zapisana na tej stronie. Każda strona ma własną listę.',
		keywords: ['playlist', 'playlista', 'lista', 'nagrania'],
		attributes: {
			heading: { type: 'string', default: '' },
			items: { type: 'array', default: [] }
		},
		edit: Edit,
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor || window.wp.editor, window.wp.components);
