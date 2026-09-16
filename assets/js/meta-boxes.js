(function ($) {
	'use strict';

	var initialized = Object.create(null);
	var observer = null;
	var retryTimer = null;
	var retryCount = 0;
	var maxRetries = 40;

	function editorAvailable() {
		return window.wp &&
			wp.editor &&
			typeof wp.editor.initialize === 'function' &&
			typeof wp.editor.getDefaultSettings === 'function';
	}

	function isVisible(element) {
		return !!(
			element &&
			element.isConnected &&
			(element.offsetWidth || element.offsetHeight || element.getClientRects().length)
		);
	}

	function syncEditor(editor) {
		if (!editor || typeof editor.save !== 'function') {
			return;
		}
		editor.save();
	}

	function initializeEditor(textarea) {
		var id = textarea && textarea.id ? textarea.id : '';

		if (!id || initialized[id] || !isVisible(textarea) || !editorAvailable()) {
			return false;
		}

		if (window.tinymce && tinymce.get(id)) {
			initialized[id] = true;
			return true;
		}

		wp.editor.initialize(id, {
			mediaButtons: false,
			quicktags: false,
			tinymce: {
				menubar: false,
				statusbar: false,
				toolbar1: 'bold italic bullist numlist link unlink undo redo',
				toolbar2: '',
				setup: function (editor) {
					editor.on('change input keyup undo redo', function () {
						syncEditor(editor);
					});
				}
			}
		});

		initialized[id] = true;
		return true;
	}

	function initializeVisibleEditors() {
		if (!editorAvailable()) {
			return false;
		}

		var pending = false;
		document.querySelectorAll('textarea.wem-cf-mini-editor').forEach(function (textarea) {
			if (!initialized[textarea.id]) {
				if (!initializeEditor(textarea)) {
					pending = true;
				}
			}
		});

		return pending;
	}

	function scheduleRetries() {
		if (retryTimer) {
			return;
		}

		retryTimer = window.setInterval(function () {
			retryCount += 1;
			var pending = initializeVisibleEditors();

			if (!pending || retryCount >= maxRetries) {
				window.clearInterval(retryTimer);
				retryTimer = null;
			}
		}, 250);
	}

	function observeMetaBoxMoves() {
		if (!window.MutationObserver || observer) {
			return;
		}

		observer = new MutationObserver(function () {
			initializeVisibleEditors();
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	function syncAllEditors() {
		if (!window.tinymce || !tinymce.editors) {
			return;
		}

		tinymce.editors.forEach(function (editor) {
			if (editor && editor.id && initialized[editor.id]) {
				syncEditor(editor);
			}
		});
	}


	function setMediaEmpty(field) {
		var input = field.querySelector('.wem-cf-media-id');
		var preview = field.querySelector('.wem-cf-media-preview');
		var selectButton = field.querySelector('.wem-cf-media-select');
		var removeButton = field.querySelector('.wem-cf-media-remove');
		var kind = field.getAttribute('data-wem-media-kind') || 'image';

		if (input) {
			input.value = '';
		}
		if (preview) {
			preview.innerHTML = '';
			var empty = document.createElement('span');
			empty.className = 'wem-cf-media-empty';
			empty.textContent = kind === 'pdf' ? '尚未选择 PDF' : '尚未选择图片';
			preview.appendChild(empty);
		}
		if (selectButton) {
			selectButton.textContent = selectButton.getAttribute('data-select-label') || '选择文件';
		}
		if (removeButton) {
			removeButton.hidden = true;
		}
	}

	function setMediaAttachment(field, attachment) {
		var input = field.querySelector('.wem-cf-media-id');
		var preview = field.querySelector('.wem-cf-media-preview');
		var selectButton = field.querySelector('.wem-cf-media-select');
		var removeButton = field.querySelector('.wem-cf-media-remove');
		var kind = field.getAttribute('data-wem-media-kind') || 'image';

		if (input) {
			input.value = attachment.id || '';
		}

		if (preview) {
			preview.innerHTML = '';

			if (kind === 'image') {
				var image = document.createElement('img');
				var thumbnail = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				image.src = thumbnail || '';
				image.alt = '';
				image.className = 'wem-cf-media-image';
				preview.appendChild(image);
			} else {
				var icon = document.createElement('span');
				icon.className = 'dashicons dashicons-media-document';
				icon.setAttribute('aria-hidden', 'true');
				preview.appendChild(icon);
			}

			var name = document.createElement('span');
			name.className = 'wem-cf-media-name';
			name.textContent = attachment.filename || attachment.title || (kind === 'pdf' ? 'PDF 文件' : '图片');
			preview.appendChild(name);
		}

		if (selectButton) {
			selectButton.textContent = selectButton.getAttribute('data-replace-label') || '更换文件';
		}
		if (removeButton) {
			removeButton.hidden = false;
		}
	}

	function openMediaFrame(field) {
		if (!window.wp || !wp.media) {
			return;
		}

		var kind = field.getAttribute('data-wem-media-kind') || 'image';
		var isPdf = kind === 'pdf';
		var frame = wp.media({
			title: isPdf ? '选择 PDF' : '选择图片',
			button: {
				text: isPdf ? '使用此 PDF' : '使用此图片'
			},
			library: {
				type: isPdf ? 'application/pdf' : 'image'
			},
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first();
			if (!attachment) {
				return;
			}
			setMediaAttachment(field, attachment.toJSON());
		});

		frame.open();
	}

	function bindMediaFields() {
		$(document).on('click', '.wem-cf-media-select', function (event) {
			event.preventDefault();
			var field = this.closest('.wem-cf-media-field');
			if (field) {
				openMediaFrame(field);
			}
		});

		$(document).on('click', '.wem-cf-media-remove', function (event) {
			event.preventDefault();
			var field = this.closest('.wem-cf-media-field');
			if (field) {
				setMediaEmpty(field);
			}
		});
	}

	$(function () {
		initializeVisibleEditors();
		bindMediaFields();
		observeMetaBoxMoves();
		scheduleRetries();

		// Classic editor / 普通表单提交。
		$('#post').on('submit', syncAllEditors);

		// Gutenberg 的 Meta Box 保存会读取 textarea；持续同步保证值始终最新。
		$(document).on('mousedown click', '.editor-post-publish-button, .editor-post-save-draft, .editor-post-publish-panel__toggle', syncAllEditors);
	});
})(jQuery);
