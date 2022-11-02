var Dom = YAHOO.util.Dom;

class SiteDialog {
	static STATE_OPENED = 1;
	static STATE_CLOSED = 2;
	static dialogs = [];
	static has_push_state = window.pushState;
	static opened_dialog_stack = [];
	static desktop_sentinel = null;
	static is_desktop = false;
	static scroll_top = null;
	static resize_debounce_delay = 30;

	static RESIZE_NONE = 0;
	static RESIZE_FILL = 1;
	static RESIZE_CENTER = 2;

	static DEFAULT_CONFIG = {
		USE_OVERLAY: {
			key: 'use_overlay',
			value: true,
			validator: YAHOO.lang.isBoolean
		},
		/**
		 * Allow clicking outside the dialog to close the dialog.
		 */
		DISMISSABLE: {
			key: 'dismissable',
			value: true,
			validator: YAHOO.lang.isBoolean
		},
		/**
		 * Only show dialog in mobile layout. If switching back to desktop, the
		 * dialog is automatically closed.
		 */
		MOBILE_ONLY: {
			key: 'mobile_only',
			value: false,
			validator: YAHOO.lang.isBoolean
		},
		/**
		 * Use pushState API if available to control opening and closing the
		 * dialog.
		 */
		USE_PUSH_STATE: {
			key: 'use_push_state',
			value: true,
			validator: YAHOO.lang.isBoolean
		},
		CLASS_NAME: {
			key: 'class_name',
			value: ''
		},
		TOGGLE_ELEMENT: {
			key: 'toggle_element',
			value: null
		},
		RELATIVE_CONTAINER: {
			key: 'relative_container',
			value: null
		},
		RESIZE_MODE: {
			key: 'resize_mode',
			value: SiteDialog.RESIZE_FILL,
			validator: YAHOO.lang.isNumber
		}
	};

	constructor(el, user_config) {
		SiteDialog.addSentinel();

		this.initConfig(user_config);
		this.initElements(el);

		SiteDialog.dialogs.push(this);
	}

	static updateLayout() {
		if (SiteDialog.opened_dialog_stack.length === 0) {
			for (i = 0; i < document.body.childNodes.length; i++) {
				YAHOO.util.Dom.removeClass(
					document.body.childNodes[i],
					'site-dialog-hidden'
				);
			}

			// Also re-show all dialogs. Needed because relatively positioned
			// dialogs may not be children of the body element when updateLayout()
			// is called.
			for (j = 0; j < SiteDialog.dialogs.length; j++) {
				YAHOO.util.Dom.removeClass(
					SiteDialog.dialogs[j].dialog,
					'site-dialog-hidden'
				);
				if (SiteDialog.dialogs[j].overlay) {
					YAHOO.util.Dom.removeClass(
						SiteDialog.dialogs[j].overlay,
						'site-dialog-hidden'
					);
				}
			}

			if (SiteDialog.scroll_top !== null) {
				window.scrollTo(0, SiteDialog.scroll_top);
				SiteDialog.scroll_top = null;
			}
		} else {
			// save scroll position
			SiteDialog.scroll_top = YAHOO.util.Dom.getDocumentScrollTop();

			window.scrollTo(0, 0);

			var top_index = SiteDialog.opened_dialog_stack.length - 1;
			var top_dialog = SiteDialog.opened_dialog_stack[top_index];
			for (i = 0; i < document.body.childNodes.length; i++) {
				var node = document.body.childNodes[i];

				// don't hide the top-level opened dialog
				if (node === top_dialog.dialog) {
					YAHOO.util.Dom.removeClass(node, 'site-dialog-hidden');

					// don't hide sentinel
				} else if (node !== SiteDialog.desktop_sentinel) {
					YAHOO.util.Dom.addClass(node, 'site-dialog-hidden');
				}
			}
		}
	}

	static raiseDialog(dialog) {
		var index = null;
		for (var i = 0; i < SiteDialog.opened_dialog_stack.length; i++) {
			if (SiteDialog.opened_dialog_stack[i] === dialog) {
				index = i;
				break;
			}
		}

		if (index !== null) {
			SiteDialog.opened_dialog_stack.splice(index, 1);
		}

		SiteDialog.opened_dialog_stack.push(dialog);
		SiteDialog.updateLayout();
	}

	static lowerDialog(dialog) {
		var index = null;
		for (var i = 0; i < SiteDialog.opened_dialog_stack.length; i++) {
			if (SiteDialog.opened_dialog_stack[i] === dialog) {
				index = i;
				break;
			}
		}

		if (index !== null) {
			SiteDialog.opened_dialog_stack.splice(index, 1);
		}

		SiteDialog.updateLayout();
	}

	static addSentinel() {
		if (SiteDialog.desktop_sentinel === null) {
			SiteDialog.desktop_sentinel = document.createElement('div');
			SiteDialog.desktop_sentinel.className = 'site-dialog-sentinel';
			document.body.appendChild(SiteDialog.desktop_sentinel);

			var timeout = null;

			var checkSentinel = function() {
				var display = YAHOO.util.Dom.getComputedStyle(
					SiteDialog.desktop_sentinel,
					'display'
				);

				if (display === 'none' && !SiteDialog.is_desktop) {
					// changing from mobile to desktop
					SiteDialog.is_desktop = true;
					SiteDialog.handleLayoutChange();
				} else if (display === 'block' && SiteDialog.is_desktop) {
					// changing from desktop to mobile
					SiteDialog.is_desktop = false;
					SiteDialog.handleLayoutChange();
				}
			};

			var handleResize = function() {
				for (var i = 0; i < SiteDialog.dialogs.length; i++) {
					SiteDialog.dialogs[i].handleResize();
				}
			};

			// Initialize layout state
			if (YAHOO.util.Dom.hasClass(document.documentElement, 'ie8')) {
				// Give IE8 time to load responsive styles before initializing
				// mode. It needs to re-download and parse all the CSS. Respond.js
				// does not provide an event for this. To do so, we add an element
				// to the DOM that has a media query style that sets its display
				// to 'none'. We check if the media query has been loaded on a
				// short interval.
				var mq_detect_el = document.createElement('div');
				mq_detect_el.className = 'site-dialog-mq-detect';
				document.body.appendChild(mq_detect_el);
				var mq_detect_interval = setInterval(function() {
					var display = YAHOO.util.Dom.getComputedStyle(
						mq_detect_el,
						'display'
					);
					if (display === 'none') {
						mq_detect_el.parentNode.removeChild(mq_detect_el);
						checkSentinel();
						clearInterval(mq_detect_interval);
						mq_detect_interval = null;
					}
				}, 10);
				setTimeout(checkSentinel, 1000);
			} else {
				checkSentinel();
			}

			window.addEventListener('resize', () => {
				// Debounce resize updates so they only fire every at most
				// every SiteDialog.resize_debounce_delay ms.
				if (timeout) {
					clearTimeout(timeout);
				}
				timeout = setTimeout(function() {
					checkSentinel();
					handleResize();
					timeout = null;
				}, SiteDialog.resize_debounce_delay);
			});
		}
	}

	static handleLayoutChange() {
		for (var i = 0; i < SiteDialog.dialogs.length; i++) {
			SiteDialog.dialogs[i].handleLayoutChange();
		}
	}

	initDefaultConfig() {
		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.USE_OVERLAY.key, {
			value: SiteDialog.DEFAULT_CONFIG.USE_OVERLAY.value,
			validator: SiteDialog.DEFAULT_CONFIG.USE_OVERLAY.validator
		});

		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.DISMISSABLE.key, {
			value: SiteDialog.DEFAULT_CONFIG.DISMISSABLE.value,
			validator: SiteDialog.DEFAULT_CONFIG.DISMISSABLE.validator
		});

		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.MOBILE_ONLY.key, {
			value: SiteDialog.DEFAULT_CONFIG.MOBILE_ONLY.value,
			validator: SiteDialog.DEFAULT_CONFIG.MOBILE_ONLY.validator
		});

		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.USE_PUSH_STATE.key, {
			value: SiteDialog.DEFAULT_CONFIG.USE_PUSH_STATE.value,
			validator: SiteDialog.DEFAULT_CONFIG.USE_PUSH_STATE.validator
		});

		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.CLASS_NAME.key, {
			value: SiteDialog.DEFAULT_CONFIG.CLASS_NAME.value
		});

		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.TOGGLE_ELEMENT.key, {
			value: SiteDialog.DEFAULT_CONFIG.TOGGLE_ELEMENT.value
		});

		this.config.addProperty(SiteDialog.DEFAULT_CONFIG.RESIZE_MODE.key, {
			value: SiteDialog.DEFAULT_CONFIG.RESIZE_MODE.value,
			validator: SiteDialog.DEFAULT_CONFIG.RESIZE_MODE.validator
		});

		this.config.addProperty(
			SiteDialog.DEFAULT_CONFIG.RELATIVE_CONTAINER.key,
			{
				value: SiteDialog.DEFAULT_CONFIG.RELATIVE_CONTAINER.value
			}
		);
	}

	initConfig(user_config) {
		this.config = new YAHOO.util.Config(this);
		this.initDefaultConfig();

		// Merge user and default config values.
		if (user_config) {
			this.config.applyConfig(user_config, true);
		}

		// Flatten config object. We're not using events.
		this.config = this.config.getConfig();
	}

	initElements(el) {
		this.header = this.drawHeader();
		this.body = this.drawBody();
		this.scroll = this.drawScroll(this.header, this.body);
		this.footer = this.drawFooter();
		this.container = this.drawContainer(this.scroll, this.footer);

		this.dialog = this.drawDialog(this.container, el);

		this.id = this.dialog.id;

		if (this.config.use_overlay) {
			this.overlay = this.drawOverlay();
		}

		this.state = SiteDialog.STATE_OPENED;
		this.close();

		if (this.config.use_overlay) {
			document.body.appendChild(this.overlay);
		}

		if (this.config.dismissable) {
			document.body.addEventListener('click', e => {
				this.handleDocumentClick(e);
			});

			document.body.addEventListener('keydown', e => {
				this.handleDocumentKeyDown(e);
			});
		}

		if (SiteDialog.is_desktop && this.config.relative_container) {
			this.config.relative_container.appendChild(this.dialog);
		} else {
			document.body.appendChild(this.dialog);
		}

		if (SiteDialog.has_push_state && this.config.use_push_state) {
			window.addEventListener('popstate', e => {
				this.handlePopState(e);
			});
		}
	}

	getPushStateId() {
		return this.id;
	}

	drawOverlay() {
		var overlay = document.createElement('div');
		overlay.className = 'site-dialog-overlay';
		return overlay;
	}

	drawDialog(container, el) {
		var dialog;

		if (el) {
			dialog = Dom.get(el);
			if (!dialog) {
				dialog = document.createElement('div');
				dialog.id = el;
			}
		} else {
			dialog = document.createElement('div');
		}

		Dom.generateId(dialog, 'site-dialog');

		Dom.addClass(dialog, 'site-dialog-dialog');
		if (this.config.relative_container) {
			Dom.addClass(dialog, 'site-dialog-relative');
		}
		if (this.config.class_name + '' !== '') {
			Dom.addClass(dialog, this.config.class_name);
		}

		dialog.appendChild(container);

		return dialog;
	}

	drawScroll(header, body) {
		var scroll = document.createElement('div');
		scroll.className = 'site-dialog-scroll';

		scroll.appendChild(header);
		scroll.appendChild(body);

		return scroll;
	}

	drawHeader() {
		var header = document.createElement('div');
		header.className = 'site-dialog-header';
		return header;
	}

	drawContainer(scroll, footer) {
		var container = document.createElement('div');
		container.className = 'site-dialog-container';

		container.appendChild(scroll);
		container.appendChild(footer);

		return container;
	}

	drawBody() {
		var body = document.createElement('div');
		body.className = 'site-dialog-body';
		return body;
	}

	drawFooter() {
		var footer = document.createElement('div');
		footer.className = 'site-dialog-footer';
		return footer;
	}

	open() {
		if (this.isOpened()) {
			return;
		}

		this.raise();

		Dom.removeClass(this.overlay, 'site-dialog-closed');
		Dom.removeClass(this.dialog, 'site-dialog-closed');

		// need to set state before doing initial positioning
		this.state = SiteDialog.STATE_OPENED;

		this.handleResize();

		// bubble up opened stack if not desktop
		if (!SiteDialog.is_desktop) {
			SiteDialog.raiseDialog(this);
		}
	}

	close() {
		if (this.isClosed()) {
			return;
		}

		// remove from opened stack
		SiteDialog.lowerDialog(this);

		Dom.addClass(this.overlay, 'site-dialog-closed');
		Dom.addClass(this.dialog, 'site-dialog-closed');

		this.state = SiteDialog.STATE_CLOSED;
	}

	openWithAnimation() {
		// TODO: implement animations
		this.open();
	}

	closeWithAnimation() {
		// TODO: implement animations
		this.close();
	}

	isOpened() {
		return this.state === SiteDialog.STATE_OPENED;
	}

	isClosed() {
		return !this.isOpened();
	}

	raise() {
		if (this.overlay) {
			SwatZIndexManager.raiseElement(this.overlay);
		}
		SwatZIndexManager.raiseElement(this.dialog);
	}

	toggle() {
		if (this.isOpened()) {
			this.close();
		} else {
			this.open();
		}
	}

	toggleWithAnimation() {
		if (this.isOpened()) {
			this.closeWithAnimation();
		} else {
			this.openWithAnimation();
		}
	}

	appendToHeader(node) {
		this.header.appendChild(node);
	}

	appendToBody(node) {
		this.body.appendChild(node);
	}

	appendToFooter(node) {
		this.footer.appendChild(node);
	}

	clearHeader() {
		while (this.header.firstChild) {
			this.header.removeChild(this.header.firstChild);
		}
	}

	clearBody() {
		while (this.body.firstChild) {
			this.body.removeChild(this.body.firstChild);
		}
	}

	clearFooter() {
		while (this.footer.firstChild) {
			this.footer.removeChild(this.footer.firstChild);
		}
	}

	handleLayoutChange() {
		// switching from mobile to desktop
		if (SiteDialog.is_desktop) {
			// remove from stack if opened
			if (this.isOpened()) {
				SiteDialog.lowerDialog(this);
			}

			// close if mobile only
			if (this.config.mobile_only) {
				this.close();
			}

			// put dialog back in relative container
			if (this.config.relative_container) {
				this.config.relative_container.appendChild(this.dialog);
			}

			// switching from desktop to mobile
		} else {
			// put dialog in body
			if (this.config.relative_container) {
				document.body.appendChild(this.dialog);
			}

			// add to stack if opened
			if (this.isOpened()) {
				SiteDialog.raiseDialog(this);
			}
		}
	}

	handleResize() {
		if (this.isClosed()) {
			return;
		}

		if (
			this.config.resize_mode === SiteDialog.RESIZE_FILL ||
			!SiteDialog.is_desktop
		) {
			var footer_region = Dom.getRegion(this.footer);

			var margin =
				parseInt(Dom.getStyle(this.container, 'marginTop')) +
				parseInt(Dom.getStyle(this.container, 'marginBottom'));

			margin = isNaN(margin) ? 0 : margin;

			this.scroll.style.height =
				Dom.getViewportHeight() - footer_region.height - margin + 'px';

			this.dialog.style.height = Dom.getViewportHeight() + 'px';
			this.dialog.style.top = null;
		} else if (this.config.resize_mode === SiteDialog.RESIZE_CENTER) {
			this.dialog.style.height = 'auto';
			this.scroll.style.height = 'auto';

			var margin =
				parseInt(Dom.getStyle(this.container, 'marginTop')) +
				parseInt(Dom.getStyle(this.container, 'marginBottom'));

			margin = isNaN(margin) ? 0 : margin;

			var region = Dom.getRegion(this.container);
			var viewport = Dom.getViewportHeight();

			// center vertically in viewport
			this.dialog.style.top =
				(viewport - region.height - margin) / 2 + 'px';
		} else {
			this.dialog.style.height = 'auto';
			this.scroll.style.height = 'auto';
		}
	}

	handleDocumentClick(e) {
		if (this.isOpened()) {
			var prevent_close = false;
			var target = e.target;
			while (target.parentNode && !prevent_close) {
				if (
					target === this.dialog ||
					target === this.config.toggle_element
				) {
					prevent_close = true;
				}
				target = target.parentNode;
			}

			if (!prevent_close) {
				this.closeWithAnimation();
			}
		}
	}

	handleDocumentKeyDown(e) {
		// allow escape key to close dialog
		if (this.isOpened() && e.keyCode === 27) {
			this.closeWithAnimation();
		}
	}

	handlePopState(e) {
		// TODO: push/pop-state is not supported yet.
		if (e.state && e.state.id && e.state.id === this.getPushStateId()) {
			this.open();
		} else {
			this.close();
		}
	}
}
