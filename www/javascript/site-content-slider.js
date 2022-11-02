/**
 * Initializes sliders on this page after the document has been loaded
 *
 * To set up a slider, use:
 * <div class="site-content-slider">
 *   <div class="slider-page">Page 1</div>
 *   <div class="slider-page">Page 2</div>
 * </div>
 *
 * Optional properties on the .site-content-slider div:
 *  - data-nav: display "dot" type navigation between pages
 *  - data-next-prev: display next/prev links
 *  - data-random-start: choose the first page at random
 *  - data-text-next: next button text
 *  - data-text-prev: previous button text
 *  - data-auto-advance: enable/disable auto-advancing. Default "true".
 *
 *  The .site-content-slider div can optionally have height set in CSS, or
 *  if left without height, the pages' height will be set to the height
 *  of the tallest page.
 */
window.addEventListener('DOMContentLoaded', () => {
	var sliders = YAHOO.util.Dom.getElementsByClassName('site-content-slider');
	for (var i = 0; i < sliders.length; i++) {
		var slider = new SiteContentSlider(sliders[i]);
		SiteContentSlider.sliders.push(slider);
	}
});

var Dom = YAHOO.util.Dom;

/**
 * Page in a slider
 */
class SiteContentSliderPage {
	/**
	 * @param {Element} element
	 * @param {number} index
	 */
	constructor(element, index) {
		this.element = element;
		this.index = index;

		this.nav = document.createElement('a');
		this.nav.href = '#';
		this.nav.appendChild(document.createElement('span'));
	}
}

class SiteContentSlider {
	static sliders = [];

	_interval;

	/**
	 * Pager widget
	 *
	 * @param {Element} container
	 */
	constructor(container) {
		this.container = container;

		// stop automatic switching of pages on click
		this.container.addEventListener('click', () => {
			this.clearInterval();
		});

		this.page_container = document.createElement('div');
		Dom.addClass(this.page_container, 'page-container');
		Dom.setStyle(this.page_container, 'position', 'absolute');
		Dom.setStyle(this.page_container, 'top', 0);
		Dom.setStyle(this.page_container, 'left', 0);
		this.container.appendChild(this.page_container);

		this.auto_height = this.container.style.height === '';

		this.pages = [];
		this.current_page = null;

		this.touch_x = null;
		this.touch_start_x = null;
		this.touch_start_y = null;
		this.touch_end_x = null;

		this.pageChangeEvent = new YAHOO.util.CustomEvent('pageChange', this);

		this.initSettings();

		var pages = Dom.getElementsByClassName('slider-page', 'div', container);

		for (var i = 0; i < pages.length; i++) {
			this.pages.push(new SiteContentSliderPage(pages[i], i));
		}

		if (this.pages.length > 1 && this.getSetting('nav', false)) {
			this.drawNav();
		} else {
			this.nav = null;
		}

		if (this.pages.length > 1 && this.getSetting('next-prev', false)) {
			this.drawNextPrev();
		} else {
			this.next_prev = null;
		}

		// initialize current page
		if (this.pages.length > 0) {
			var page;

			var random_start_page = this.getSetting('random-start', false);

			if (random_start_page) {
				page = this.getPseudoRandomPage();
			} else {
				page = this.pages[0];
			}

			this.initPages();
			this.setPage(page);
		}

		this.addTouchEvents();

		if (this.auto_advance) {
			this.setInterval();
		}

		window.addEventListener('resize', () => {
			this.initPages();
			this.setPage(this.current_page);
		});

		// If images have dynamic width/height, some browsers (Safari, I'm
		// looking at you) will return the incorrect height for the page's
		// height until the image is loaded. Recalculating the page height
		// once the images finish loading fixes the issue.
		var images = container.getElementsByTagName('img');
		var loaded_count = 0;

		for (var image of images) {
			image.addEventListener('load', () => {
				loaded_count++;
				if (loaded_count === images.length) {
					this.initPages();
					this.setPage(this.current_page);
				}
			});
		}
	}

	initSettings() {
		// settings set with "data-x" properties on the container tag
		this.page_click_duration = this.getSetting('page-click-duration', 0.25);
		this.page_auto_duration = this.getSetting('page-auto-duration', 1);
		this.page_interval = this.getSetting('page-interval', 10);
		this.text_next = this.getSetting('text-next', 'Next');
		this.text_prev = this.getSetting('text-prev', 'Previous');

		// cast to boolean from string "false"
		var auto_advance = this.getSetting('auto-advance', true);
		this.auto_advance = auto_advance !== 'false';
	}

	getSetting(name, default_value) {
		var value = this.container.getAttribute('data-' + name);
		return typeof value === 'undefined' || value === null
			? default_value
			: value;
	}

	initPages() {
		var region = Dom.getRegion(this.container);
		var width = region.width;
		var max_height = 0;
		var pos = 0;

		for (var i = 0; i < this.pages.length; i++) {
			Dom.setStyle(this.pages[i].element, 'height', 'auto');
			Dom.setStyle(this.pages[i].element, 'width', width + 'px');

			max_height = Math.max(
				max_height,
				Dom.getRegion(this.pages[i].element).height
			);

			Dom.setStyle(this.pages[i].element, 'top', 0);
			Dom.setStyle(this.pages[i].element, 'left', pos + -width + 'px');
			Dom.setStyle(this.pages[i].element, 'position', 'absolute');
			pos += width;

			this.page_container.appendChild(this.pages[i].element);
		}

		if (this.auto_height) {
			Dom.setStyle(this.container, 'height', max_height + 'px');
		} else {
			max_height = Dom.getRegion(this.container).height;
		}

		for (var i = 0; i < this.pages.length; i++) {
			Dom.setStyle(this.pages[i].element, 'height', max_height + 'px');
		}
	}

	setPage(page) {
		if (this.current_page !== page) {
			Dom.addClass(page.element, 'slider-current');
			Dom.addClass(page.nav, 'selected');

			if (this.current_page !== null) {
				Dom.removeClass(this.current_page.element, 'slider-current');
				Dom.removeClass(this.current_page.nav, 'selected');
			}

			this.current_page = page;
			this.pageChangeEvent.fire(page.index);
		}

		var width = Dom.getRegion(this.container).width;
		this.setSpeed(0);
		this.positionPages(-width * page.index);
		this.updateNextPrev();
	}

	setPageWithAnimation(page, speed, start_pos) {
		var width = Dom.getRegion(this.container).width;
		this.setSpeed(speed);
		this.positionPages(-width * page.index);
		this.setPage(page);
	}

	setSpeed(speed) {
		var s = 'left ' + speed + 's';
		Dom.setStyle(this.page_container, '-moz-transition', s);
		Dom.setStyle(this.page_container, '-webkit-transition', s);
		Dom.setStyle(this.page_container, '-o-transition', s);
		Dom.setStyle(this.page_container, 'transition', s);
	}

	positionPages(pos) {
		var region = Dom.getRegion(this.container);
		var width = region.width;
		Dom.setStyle(this.page_container, 'left', pos + width + 'px');
	}

	setInterval() {
		this._interval = setInterval(() => {
			this.nextPageWithAnimation(this.page_auto_duration);
		}, this.page_interval * 1000);
	}

	clearInterval() {
		if (this._interval) {
			clearInterval(this._interval);
		}
	}

	getPseudoRandomPage() {
		var page = null;

		if (this.pages.length > 0) {
			var now = new Date();
			page = this.pages[now.getSeconds() % this.pages.length];
		}

		return page;
	}

	prevPageWithAnimation(speed) {
		var index = this.current_page.index - 1;
		if (index < 0) {
			index = this.pages.length - 1;
		}

		this.setPageWithAnimation(this.pages[index], speed);
	}

	nextPageWithAnimation(speed) {
		var index = this.current_page.index + 1;
		if (index >= this.pages.length) {
			// TODO: add "infinite" scrolling so it scrolls to the first
			// page to the left
			if (this.pages.length > 2) {
				this.setPage(this.pages[0], speed);
			} else {
				this.setPageWithAnimation(this.pages[0], speed);
			}
		} else {
			this.setPageWithAnimation(this.pages[index], speed);
		}
	}

	addTouchEvents() {
		this.container.addEventListener('touchstart', e => {
			var touch = e.touches[0];
			this.touch_start_x = touch.pageX;
			this.touch_start_y = touch.pageY;
		});

		this.container.addEventListener('touchend', () => {
			var width = Dom.getRegion(this.container).width;

			// only move the page if the drag was more that 1/5 width
			if (Math.abs(this.touch_end_x - this.touch_start_x) < width / 5) {
				var new_index = this.current_page.index;
			} else if (this.touch_end_x < this.touch_start_x) {
				var new_index = this.current_page.index + 1;
			} else {
				var new_index = this.current_page.index - 1;
			}

			new_index = Math.max(0, new_index);
			new_index = Math.min(this.pages.length - 1, new_index);

			this.setPageWithAnimation(
				this.pages[new_index],
				this.page_click_duration,
				this.touch_x
			);

			this.touch_start_x = null;
			this.touch_start_y = null;
		});

		window.addEventListener('touchmove', e => {
			if (this.touch_start_x === null) {
				return;
			}

			var touch = e.touches[0];

			// Prevent vertical scrolling if the movement is mostly horizontal.
			// This keeps the page from bouncing around.
			if (Math.abs(this.touch_start_x - touch.pageX) > 10) {
				e.preventDefault();

				// also stop the automatical animation of pages
				this.clearInterval();
			}

			var width = Dom.getRegion(this.container).width;
			this.touch_end_x = touch.pageX;

			if (Math.abs(this.touch_start_x - this.touch_end_x) > width) {
				if (this.touch_start_x > this.touch_end_x) {
					var drag_width = width;
				} else {
					var drag_width = -width;
				}
			} else {
				var drag_width = this.touch_start_x - this.touch_end_x;
			}

			this.touch_x = Math.min(
				width,
				this.current_page.index * -width - drag_width
			);

			// if at the end/beginning, slow down drag
			if (
				this.touch_x > 0 ||
				this.touch_x < (this.pages.length - 1) * -width
			) {
				this.touch_x = this.touch_x + drag_width / 2;
			}

			this.positionPages(this.touch_x);
		});
	}

	drawNav() {
		this.nav = document.createElement('div');
		Dom.addClass(this.nav, 'slider-nav');
		this.container.appendChild(this.nav);

		for (var i = 0; i < this.pages.length; i++) {
			const page = this.pages[i];
			page.nav.addEventListener('click', () => {
				e.preventDefault();
				this.clearInterval();
				this.setPageWithAnimation(page, this.page_click_duration);
			});

			this.nav.appendChild(this.pages[i].nav);
		}
	}

	drawNextPrev() {
		// create previous link
		this.prev = document.createElement('a');
		this.prev.href = '#previous-page';
		Dom.addClass(this.prev, 'slider-prev');
		this.prev.appendChild(document.createTextNode(this.text_prev));

		this.prev_insensitive = document.createElement('span');
		Dom.addClass(this.prev_insensitive, 'swat-hidden');
		Dom.addClass(this.prev_insensitive, 'slider-prev-insensitive');
		this.prev_insensitive.appendChild(
			document.createTextNode(this.text_prev)
		);

		this.prev.addEventListener('click', () => {
			e.preventDefault();
			this.clearInterval();
			this.prevPageWithAnimation(this.page_click_duration);
		});

		this.prev.addEventListener('dblclick', () => {
			e.preventDefault();
		});

		// create next link
		this.next = document.createElement('a');
		this.next.href = '#next-page';
		Dom.addClass(this.next, 'slider-next');
		this.next.appendChild(document.createTextNode(this.text_next));

		this.next_insensitive = document.createElement('span');
		Dom.addClass(this.next_insensitive, 'swat-hidden');
		Dom.addClass(this.next_insensitive, 'slider-next-insensitive');
		this.next_insensitive.appendChild(
			document.createTextNode(this.text_next)
		);

		this.next.addEventListener('click', () => {
			e.preventDefault();
			this.clearInterval();
			this.nextPageWithAnimation(this.page_click_duration);
		});

		this.next.addEventListener('dblclick', () => {
			e.preventDefault();
		});

		// create navigation element
		this.next_prev = document.createElement('div');
		Dom.addClass(this.next_prev, 'slider-next-prev');
		this.next_prev.appendChild(this.prev_insensitive);
		this.next_prev.appendChild(this.prev);
		this.next_prev.appendChild(this.next);
		this.next_prev.appendChild(this.next_insensitive);

		this.container.appendChild(this.next_prev);
	}

	setPrevSensitivity(sensitive) {
		if (this.prev) {
			if (sensitive) {
				Dom.addClass(this.prev_insensitive, 'swat-hidden');
				Dom.removeClass(this.prev, 'swat-hidden');
			} else {
				Dom.addClass(this.prev, 'swat-hidden');
				Dom.removeClass(this.prev_insensitive, 'swat-hidden');
			}
		}
	}

	setNextSensitivity(sensitive) {
		if (this.next) {
			if (sensitive) {
				Dom.addClass(this.next_insensitive, 'swat-hidden');
				Dom.removeClass(this.next, 'swat-hidden');
			} else {
				Dom.addClass(this.next, 'swat-hidden');
				Dom.removeClass(this.next_insensitive, 'swat-hidden');
			}
		}
	}
	updateNextPrev() {
		var page_number = this.current_page.index + 1;
		var page_count = this.pages.length;

		this.setPrevSensitivity(page_number != 1);
		this.setNextSensitivity(page_number != page_count);
	}
}
