(function () {
	'use strict';

	function qs(selector, root) {
		return (root || document).querySelector(selector);
	}

	function qsa(selector, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(selector));
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text === 'string') {
			node.textContent = text;
		}
		return node;
	}

	function syncCard(card) {
		var labelInput = qs('.wem-cf-new-label', card) || qs('input[name$="[label]"]', card);
		var keyInput = qs('.wem-cf-new-key', card);
		var typeInput = qs('.wem-cf-new-type', card);
		var summaryLabel = qs('.wem-cf-summary-label', card);
		var summaryKey = qs('.wem-cf-summary-key', card);
		var typeBadge = qs('.wem-cf-type-badge', card);
		var metaPreview = qs('.wem-cf-meta-preview', card);

		if (labelInput && summaryLabel) {
			summaryLabel.textContent = labelInput.value.trim() || '新字段';
		}
		if (keyInput) {
			var key = keyInput.value.trim();
			if (summaryKey) {
				summaryKey.textContent = key || '尚未设置标识';
			}
			if (metaPreview) {
				metaPreview.textContent = '_wem_cf_' + key;
			}
		}
		if (typeInput && typeBadge && window.WEMCFAdmin && WEMCFAdmin.fieldTypes) {
			typeBadge.textContent = WEMCFAdmin.fieldTypes[typeInput.value] || typeInput.value;
		}
	}

	function appendOption(card, optionsRoot, settingKey) {
		var fieldIndex = card.getAttribute('data-index');
		var optionIndex = parseInt(optionsRoot.getAttribute('data-next-option-index') || '0', 10);
		optionsRoot.setAttribute('data-next-option-index', String(optionIndex + 1));

		var row = el('div', 'wem-cf-option-row');
		var valueLabel = el('label');
		valueLabel.appendChild(document.createTextNode('选项值'));
		var valueInput = document.createElement('input');
		valueInput.type = 'text';
		valueInput.className = 'code';
		valueInput.name = 'group[fields][' + fieldIndex + '][settings][' + settingKey + '][' + optionIndex + '][value]';
		valueInput.placeholder = '例如：terra';
		valueInput.required = true;
		valueLabel.appendChild(valueInput);

		var textLabel = el('label');
		textLabel.appendChild(document.createTextNode('显示名称'));
		var textInput = document.createElement('input');
		textInput.type = 'text';
		textInput.name = 'group[fields][' + fieldIndex + '][settings][' + settingKey + '][' + optionIndex + '][label]';
		textInput.placeholder = '例如：Terra';
		textInput.required = true;
		textLabel.appendChild(textInput);

		var remove = el('button', 'button-link-delete wem-cf-remove-option', '删除');
		remove.type = 'button';

		row.appendChild(valueLabel);
		row.appendChild(textLabel);
		row.appendChild(remove);
		optionsRoot.appendChild(row);
		bindOptionRow(row);
		valueInput.focus();
	}

	function bindOptionRow(row) {
		var remove = qs('.wem-cf-remove-option', row);
		if (!remove || remove.getAttribute('data-bound') === '1') {
			return;
		}
		remove.setAttribute('data-bound', '1');
		remove.addEventListener('click', function () {
			var message = window.WEMCFAdmin ? WEMCFAdmin.confirmDeleteOption : '确定删除这个选项吗？';
			if (window.confirm(message)) {
				row.remove();
			}
		});
	}

	function bindOptions(card) {
		qsa('.wem-cf-option-row', card).forEach(bindOptionRow);
		qsa('.wem-cf-add-option', card).forEach(function (button) {
			if (button.getAttribute('data-bound') === '1') {
				return;
			}
			button.setAttribute('data-bound', '1');
			button.addEventListener('click', function () {
				var setting = button.closest('.wem-cf-options-setting');
				var root = setting ? qs('.wem-cf-options', setting) : null;
				if (!root) {
					return;
				}
				appendOption(card, root, button.getAttribute('data-setting-key') || 'options');
			});
		});
	}

	function renderDynamicSettings(card) {
		var root = qs('.wem-cf-dynamic-settings', card);
		var typeInput = qs('.wem-cf-new-type', card);
		if (!root || !typeInput) {
			return;
		}

		root.innerHTML = '';
		var schemas = window.WEMCFAdmin && WEMCFAdmin.fieldSettings ? WEMCFAdmin.fieldSettings : {};
		var schema = schemas[typeInput.value] || {};
		var keys = Object.keys(schema);
		var fieldIndex = card.getAttribute('data-index');

		if (!keys.length) {
			var none = el('p', 'description', '该字段类型没有额外设置。');
			root.appendChild(none);
			return;
		}

		keys.forEach(function (settingKey) {
			var config = schema[settingKey] || {};
			var inputType = config.type || 'text';
			var labelText = config.label || settingKey;
			var helpText = config.help || '';
			var name = 'group[fields][' + fieldIndex + '][settings][' + settingKey + ']';

			if (inputType === 'options') {
				var setting = el('div', 'wem-cf-options-setting');
				var title = el('div', 'wem-cf-setting-title');
				var strong = el('strong', '', labelText);
				title.appendChild(strong);
				setting.appendChild(title);
				if (helpText) {
					setting.appendChild(el('p', 'description', helpText));
				}
				var optionsRoot = el('div', 'wem-cf-options');
				optionsRoot.setAttribute('data-next-option-index', '0');
				setting.appendChild(optionsRoot);
				var add = el('button', 'button wem-cf-add-option', '+ 添加选项');
				add.type = 'button';
				add.setAttribute('data-setting-key', settingKey);
				setting.appendChild(add);
				root.appendChild(setting);
				bindOptions(card);
				appendOption(card, optionsRoot, settingKey);
				return;
			}

			var label = el('label', 'wem-cf-dynamic-setting');
			label.appendChild(document.createTextNode(labelText));
			var input;
			if (inputType === 'textarea') {
				input = document.createElement('textarea');
				input.rows = 2;
			} else {
				input = document.createElement('input');
				input.type = 'text';
			}
			input.name = name;
			if (helpText) {
				input.placeholder = helpText;
			}
			label.appendChild(input);
			if (helpText) {
				label.appendChild(el('small', '', helpText));
			}
			root.appendChild(label);
		});
	}

	function bindCard(card) {
		var toggle = qs('.wem-cf-toggle-field', card);
		var editor = qs('.wem-cf-field-editor', card);
		var remove = qs('.wem-cf-remove-field', card);
		var up = qs('.wem-cf-move-up', card);
		var down = qs('.wem-cf-move-down', card);
		var typeInput = qs('.wem-cf-new-type', card);

		if (toggle && editor) {
			toggle.addEventListener('click', function () {
				var isHidden = editor.hasAttribute('hidden');
				if (isHidden) {
					editor.removeAttribute('hidden');
					card.classList.add('is-open');
					toggle.textContent = '收起';
				} else {
					editor.setAttribute('hidden', 'hidden');
					card.classList.remove('is-open');
					toggle.textContent = '编辑';
				}
			});
		}

		if (remove) {
			remove.addEventListener('click', function () {
				var message = window.WEMCFAdmin ? WEMCFAdmin.confirmDeleteField : '确定删除这个字段吗？';
				if (window.confirm(message)) {
					card.remove();
				}
			});
		}

		if (up) {
			up.addEventListener('click', function () {
				var previous = card.previousElementSibling;
				if (previous) {
					card.parentNode.insertBefore(card, previous);
				}
			});
		}

		if (down) {
			down.addEventListener('click', function () {
				var next = card.nextElementSibling;
				if (next) {
					card.parentNode.insertBefore(next, card);
				}
			});
		}

		if (typeInput) {
			typeInput.addEventListener('change', function () {
				renderDynamicSettings(card);
				syncCard(card);
			});
		}

		qsa('input, textarea, select', card).forEach(function (input) {
			input.addEventListener('input', function () { syncCard(card); });
			input.addEventListener('change', function () { syncCard(card); });
		});

		bindOptions(card);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var fields = qs('#wem-cf-fields');
		var addButton = qs('#wem-cf-add-field');
		var template = qs('#wem-cf-field-template');

		qsa('.wem-cf-field-card').forEach(bindCard);

		if (fields && addButton && template) {
			addButton.addEventListener('click', function () {
				var index = parseInt(fields.getAttribute('data-next-index') || '0', 10);
				var html = template.innerHTML.replace(/__INDEX__/g, String(index));
				fields.setAttribute('data-next-index', String(index + 1));
				var holder = document.createElement('div');
				holder.innerHTML = html.trim();
				var card = holder.firstElementChild;
				fields.appendChild(card);
				bindCard(card);
				renderDynamicSettings(card);
				syncCard(card);
				var first = qs('input[type="text"]', card);
				if (first) { first.focus(); }
			});
		}

		var locationRadios = qsa('input[name="group[location][mode]"]');
		var locationPages = qs('.wem-cf-location-pages');
		var postTypeInputs = qsa('input[name="group[post_types][]"]');
		var previousPostTypeState = null;

		function syncLocation() {
			var selected = qs('input[name="group[location][mode]"]:checked');
			var restricted = selected && selected.value === 'specific_pages';

			if (locationPages) {
				locationPages.hidden = !restricted;
				var pageSelect = qs('#wem-cf-location-page-ids', locationPages);
				if (pageSelect) { pageSelect.required = !!restricted; }
			}

			if (restricted) {
				if (previousPostTypeState === null) {
					previousPostTypeState = postTypeInputs.map(function (input) {
						return { input: input, checked: input.checked };
					});
				}
				postTypeInputs.forEach(function (input) {
					var isPage = input.value === 'page';
					input.checked = isPage;
					input.disabled = !isPage;
					var label = input.closest('label');
					if (label) { label.classList.toggle('is-disabled', !isPage); }
				});
			} else {
				postTypeInputs.forEach(function (input) {
					input.disabled = false;
					var label = input.closest('label');
					if (label) { label.classList.remove('is-disabled'); }
				});
				if (previousPostTypeState !== null) {
					previousPostTypeState.forEach(function (item) {
						item.input.checked = item.checked;
					});
					previousPostTypeState = null;
				}
			}
		}

		locationRadios.forEach(function (radio) {
			radio.addEventListener('change', syncLocation);
		});
		syncLocation();

		var groupForm = qs('#wem-cf-group-form');
		if (groupForm && fields) {
			groupForm.addEventListener('submit', function (event) {
				var selectedLocation = qs('input[name="group[location][mode]"]:checked');
				var pageSelect = qs('#wem-cf-location-page-ids');
				if (selectedLocation && selectedLocation.value === 'specific_pages' && pageSelect && !Array.from(pageSelect.selectedOptions).length) {
					event.preventDefault();
					window.alert('请选择至少一个指定页面。');
					pageSelect.focus();
					return;
				}
				if (!qs('.wem-cf-field-card', fields)) {
					event.preventDefault();
					window.alert('请至少添加一个字段后再保存字段组。');
				}
			});
		}

		qsa('.wem-cf-delete-group').forEach(function (link) {
			link.addEventListener('click', function (event) {
				var name = link.getAttribute('data-name') || '';
				var message = '确定删除字段组“' + name + '”吗？\n\n只会删除字段定义，不会删除文章中已经保存的字段数据。';
				if (!window.confirm(message)) {
					event.preventDefault();
				}
			});
		});
	});
})();
