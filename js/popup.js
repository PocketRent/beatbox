(function ($, window) {
	'use strict';

	var document = window.document;

	var defaultOptions = {
		close: true
	}

	var currentPopup = null;

	var animationDuration = 250;

	var Popup = function (options) {

		this.constructor = Popup;

		if (typeof options != 'object') options = {};
		this.options = {};
		for (var i in defaultOptions) {
			if (i in options) {
				this.options[i] = options[i]
			} else {
				this.options[i] = defaultOptions[i];
			}
		}

		/* Create the tree for the popup
		 *
		 * <div class="popup">
		 *	   <div>
		 *         <div class="popupTitleBar"></div>
		 *	       <div class="popupContent"></div>
		 *     </div>
		 *  </div>
		 */
		this.root = document.createElement('div');
		this.root.setAttribute('class', 'popup');
		this.root.id = generateID();

		this.holder = document.createElement('div');

		this.titleBar = document.createElement('div');
		this.titleBar.setAttribute('class', 'popupTitleBar');

		this.content = document.createElement('div');
		this.content.setAttribute('class', 'popupContent');

		this.root.appendChild(this.holder);
		this.holder.appendChild(this.titleBar);
		this.holder.appendChild(this.content);

		var t = this;

		this.background = document.createElement('div');

		this.background.classList.add('popupBackground');

		this.background.style.position = 'fixed';
		this.background.style.width = '100%';
		this.background.style.height = '100%';
		this.background.style.left = '0px';
		this.background.style.top = '0px';

		if (this.options.close) {
			// Add the background click handler
			this.background.onclick = function (e) {
				e.preventDefault();
				e.stopPropagation();
				t.close();
				return false;
			}

			// Add a close button
			var closeButton = document.createElement('a');
			closeButton.classList.add('close');
			closeButton.classList.add('text-icon');
			closeButton.innerHTML = "'";
			closeButton.onclick = function (e) {
				e.preventDefault();
				e.stopPropagation();
				t.close();
				return false;
			}
			this.titleBar.appendChild(closeButton);
		}

		this.isOpen = false;

		this.events = {};
		this.triggeredEvents = [];
	}

	Popup.fn = Popup.prototype;

	Popup.fn.setContent = function (content) {
		if (!content) content = '';

		if (content['jquery']) {
			if (content.length > 0) {
				if (content.length > 1) {
				contentWrap = document.createElement('div');
				content.each(function (i, el) {
					contentWrap.appendChild(el);
				})
				content = contentWrap;
				} else {
					content = content[0];
				}
			} else {
				content = '';
			}
		}

		if (content instanceof Element) {
			while (this.content.firstChild)
				this.content.removeChild(this.content.firstChild);
			this.content.appendChild(content);
		} else {
			this.content.innerHTML = content;
		}

		if (this.isOpen) this.resize();
	}

	Popup.fn.setTitle = function (titleText) {
		var firstChild = this.titleBar.firstChild;
		if (firstChild) {
			if (firstChild.classList.contains('popupTitle')) {
				firstChild.innerText = titleText;
			} else {
				var title = document.createElement('span');
				title.setAttribute('class', 'popupTitle');
				title.innerText = titleText;
				this.titleBar.insertBefore(title, firstChild);
			}
		} else {
			this.titleBar.appendChild(title);
		}
	}

	Popup.fn.open = function () {
		if (!this.isOpen) {
			this.isOpen = true;
			if (!this.trigger('open')) return;
			if (currentPopup && currentPopup != this && currentPopup.isOpen) {

				if (!this.trigger('replace')) return;

				this.prepare();
				var newBounding = this.root.getBoundingClientRect();

				this.root.style.width = currentPopup.root.style.width;
				this.root.style.height = currentPopup.root.style.height;
				this.holder.style.opacity = '0';

				var that = this;

				$(currentPopup.holder).animate({'opacity': '0'}, animationDuration,
					function () {
						var $this = $(this);

						var p = currentPopup.root.parentNode;
						p.replaceChild(that.root, currentPopup.root);
						currentPopup.isOpen = false;

						that.show();
						addObserver(that);

						that.background.remove();
						that.background = currentPopup.background;
						currentPopup.background = null;

						if (that.options.close) {
							that.background.onclick = function (e) {
								e.preventDefault();
								e.stopPropagation();
								that.close();
								return false;
							}

							if (!currentPopup.options.close) {
								$(that.titleBar).animate({'opacity': 1}, animationDuration);
							}
						}

						currentPopup.close();
						currentPopup = that;

						that.resize();

						$(that.holder).delay(animationDuration)
							.animate({opacity: 1}, animationDuration);
					});
			} else {
				currentPopup = this;
				this.prepare();
				this.show();
				addObserver(this);
			}

		}
	}

	Popup.fn.resize = function () {
		if (!this.resizing) {
			var root = this.root;

			var oldBounding = root.getBoundingClientRect();

			var oldWidth = root.style.width;
			var oldHeight = root.style.height;

			root.style.left = "";
			root.style.right = "";
			root.style.top = "";
			root.style.bottom = "";
			root.style.width = "auto";
			root.style.height = "auto";

			var bounding = root.getBoundingClientRect();

			root.style.width = oldWidth;
			root.style.height = oldHeight;

			root.style.left = "0";
			root.style.right = "0";
			root.style.top = "0";
			root.style.bottom = "0";

			if (Math.abs(oldBounding.width - bounding.width) > 5 || Math.abs(oldBounding.height - bounding.height) > 5) {
				this.resizing = true;
				var t = this;
				$(root).animate({
					width: bounding.width+'px',
					height: bounding.height+'px'
				}, 100, function () { t.resizing = false; });
			}
		}
	}

	var addObserver = function (popup) {
		// Mutation observers are perfect for this, so we'll use them if they
		// exist. Unfortunately, they're pretty new so we'll fallback to polling
		// on older *coughIEcough* browers to get the same effect. It's also only
		// really an issue on larger screens, so we won't even try on mobile
		// devices
		if (!categorizr.isMobile) {
			var MutationObserver = window.MutationObserver
				|| window.WebKitMutationObserver
				|| window.MozMutationObserver;
			if (MutationObserver) {
				popup.observer = new MutationObserver(function (changes) {
					popup.resize();
				});

				popup.observer.observe(popup.root, {childList:true, subtree:true});
				popup.observer.observe(popup.content, {attributes:true, subtree:true});
			} else {
				popup.sizePoll = setInterval(function () {
					popup.resize();
				}, 250); // The higher this number is, the better, but 250 seems to be the upper limit, otherwise it seems too laggy
			}
		}
	}

	Popup.fn.prepare = function () {
		this.root.style.visibility = "hidden";
		this.root.style.cssFloat = "left";
		this.root.style.position = "absolute";
		this.root.style.width = "auto";
		this.root.style.height = "auto";

		this.background.style.visibility = 'hidden';

		$(document.body).append(this.root);
		$(document.body).append(this.background);

		this.bgOpacity = $(this.background).css('opacity');

		this.background.style.opacity = '0';
		this.background.style.visibility = 'visible';

		var bounding = this.root.getBoundingClientRect();

		this.root.style.width = bounding.width+'px';
		this.root.style.height = bounding.height+'px';

		this.root.style.left = "0";
		this.root.style.right = "0";
		this.root.style.top = "0";
		this.root.style.bottom = "0";
		this.root.style.margin = "auto";
	}

	Popup.fn.show = function() {
		this.root.style.visibility = "visible";
		$(this.root).animate({opacity: '1'}, animationDuration);
		$(this.background).animate({opacity: this.bgOpacity}, 250);
	}

	Popup.fn.close = function () {
		if (this.isOpen && this.trigger('close')) {

			var root = this.root;
			var bg = this.background;
			var t = this;

			if (root && root.parentNode) {
				$(root).animate({opacity: '0'}, 250, function () {
					$(root).remove(); // Entwine patches jQuery to let it work with add/remove
									  // we don't normally need to worry, but we need to make
									  // sure that removing the popup triggers the appropriate
									  // handlers for anything in the popup itself


					root.style.visibility = "hidden";

					root.style.left = "";
					root.style.right = "";
					root.style.top = "";
					root.style.bottom = "";
					root.style.margin = "";

				});
			}

			if (bg && bg.parentNode) {
				$(bg).delay(100).animate({opacity: '0'}, 200, function () {
					bg.parentNode.removeChild(bg);
				});
			}

			if (currentPopup == this)
				currentPopup = null;
			this.isOpen = false;
		}

		if (this.observer) {
			this.observer.disconnect();
			this.observer = null;
		} else if (this.sizePoll) {
			clearInterval(this.sizePoll);
			this.sizePoll = null;
		}

	}

	Popup.fn.on = function (evt, callback) {
		if (!this.events[evt]) this.events[evt] = [];
		this.events[evt].push(callback);

		if (this.triggeredEvents.indexOf(evt) != -1) {
			callback();
		}
	}

	Popup.fn.trigger = function (evt) {
		if (this.currentEvent == evt) return;
		this.triggeredEvents.push(evt);

		var t = this;
		this.currentEvent = evt;

		if (this.events[evt]) {
			for (var i = 0; i < this.events[evt].length; i++) {
				if (i in this.events[evt]) {
					var fn = this.events[evt][i];
					if (fn.call(t) === false) {
						this.currentEvent = undefined;
						return false;
					}
				}
			}
		}

		this.currentEvent = undefined;
		return true;
	}

	Popup.extend = function(f) {
		var surrogate = function () { };
		surrogate.prototype = Popup.prototype;

		f.prototype = new surrogate();
		f.prototype._super = Popup.prototype;

		f.prototype.constructor = f;
	}

	var Dialog = function (options) {
		this._super.constructor.call(this, options);
		this.root.classList.add('dialog');
	}
	Popup.extend(Dialog);

	var Alert = function (options) {
		if (options == undefined)
			options = {};
		if (options.close == undefined)
			options.close = false;

		this._super.constructor.call(this, options);
		this.root.classList.add('alert');
	}
	Popup.extend(Alert);

	if (categorizr.isMobile) {
		Dialog.prototype.prepare = function() {
			this.root.style.opacity = '0';
			document.body.appendChild(this.root);
		}

		Dialog.prototype.show = function () {
			$(this.root).animate({opacity: '1'}, 250);
		}

		Alert.prototype.prepare = function() {
			this.root.style.visibility = "hidden";
			this.root.style.cssFloat = "left";
			this.root.style.position = "absolute";
			this.root.style.height = "auto";

			this.background.style.visibility = 'hidden';

			document.body.appendChild(this.root);
			document.body.appendChild(this.background);

			this.bgOpacity = $(this.background).css('opacity');

			this.background.style.opacity = '0';
			this.background.style.visibility = 'visible';

			var bounding = this.root.getBoundingClientRect();

			this.root.style.height = bounding.height;

			this.root.style.left = "0";
			this.root.style.right = "0";
			this.root.style.top = "0";
			this.root.style.bottom = "0";
			this.root.style.margin = "auto";
		}
	}

	window.Popup = Popup;
	window.Dialog = Dialog;
	window.Alert = Alert;

	/**
	 * Utilities
	 */

	var generateID = function (prefix) {
		if (typeof prefix == 'undefined') prefix = 'popup';

		var length = 8;
		var codes = [];

		while (codes.length < length) {
			var code = 65 + Math.floor((Math.random()*58));
			if (code > 90 && code < 97) continue;
			codes.push(code);
		}

		var s = String.fromCharCode.apply(String, codes);

		return prefix + s;
	}

	// Short-hand helper functions

	/**
	 * openAlert(message [, buttonText = 'Ok']);
	 *
	 * Opens a standard alert dialog with the given message
	 * and button text.
	 *
	 * Returns the opened popup.
	 */
	window.openAlert = function (message, buttonText) {
		var buttonText = buttonText || 'Ok';

		var a = new Alert();
		a.setContent(message);

		var button = createButton(buttonText);
		button.onclick = function (e) {
			e.preventDefault();
			e.stopPropagation()
			a.close();
			return false;
		};

		var buttonDiv = document.createElement('div');
		buttonDiv.classList.add('popupButtons');

		buttonDiv.appendChild(button);
		a.holder.appendChild(buttonDiv);

		a.open();

		return a;
	}

	/**
	 * openConfirm(message [, options]);
	 *
	 * Opens a confirmation dialog with the given message.
	 * The optional options argument is an object.
	 *
	 *     Field            Default        Description
	 *     confirmText       'Ok'          Text on the confirm button
	 *     cancelText        'Cancel'      Text on the cancel button
	 *     confirm          (none)         Callback when confirm button is clicked
	 *     cancel           (none)         Callback when cancel button is clicked
	 *
	 * Returning false from either the confirm or cancel callbacks will
	 * prevent the popup from closing.
	 *
	 * Two events are also triggered, 'confirm' and 'cancel', the same as the
	 * above callbacks. These can be attached to returned popup.
	 */
	window.openConfirm = function (message, options) {
		if (!options) options = {};

		var confirmText = options.confirmText || 'Ok';
		var cancelText = options.cancelText || 'Cancel';

		var a = new Alert();
		a.setContent(message);
		a.root.classList.add('confirm')

		var confirmButton = createButton(confirmText);
		confirmButton.onclick = function (e) {
			e.preventDefault();
			e.stopPropagation();
			var close = true;
			if (options.confirm) {
				close = options.confirm() !== false;
			}
			if (close)
				close = a.trigger('confirm');

			if (close)
				a.close();
		}
		var cancelButton = createButton(cancelText);
		cancelButton.onclick = function (e) {
			e.preventDefault();
			e.stopPropagation();
			var close = true;
			if (options.cancel) {
				close = options.cancel() !== false;
			}

			if (close)
				close = a.trigger('cancel');

			if (close)
				a.close();
		}

		var buttonDiv = document.createElement('div');
		buttonDiv.classList.add('popupButtons');
		buttonDiv.appendChild(confirmButton);
		buttonDiv.appendChild(cancelButton);

		a.holder.appendChild(buttonDiv);

		a.open();

		return a;
	}

	/**
	 * openDialog(fragment[, path[, title]])
	 *
	 * Requests the given fragment and inserts the content into the
	 * dialog. If title is given, it is set on both the loading dialog
	 * and the actual dialog.
	 */
	window.openDialog = function(fragment, path, title) {
		if (path == undefined) {

			var rootUrl = History.getBaseUrl(),
				url = History.getPageUrl(),
				relativeUrl = url.replace(rootUrl, '') || '/';

			path = relativeUrl.replace(/\?.+$/, '');
		}

		var url = new Uri(path);
		url.param('fragments', fragment);
		url = url.toString();

		var req = $.getJSON(url);

		var loadTimer = setTimeout(function () {
			var loading = new Dialog();
			loading.content.classList.add('loading');
			loading.root.classList.add('small');

			if (title) {
				loading.setTitle(title);
			}

			loading.on('close', function () {
				req.abort();
			})
			loading.open();
		}, 400);

		var dialog = new Dialog();
		if (title) {
			dialog.setTitle(title);
		}
		req.success(function(data) {
			clearTimeout(loadTimer);
			if (typeof data[fragment] == 'string') {
				dialog.setContent(data[fragment]);
			} else {
				dialog.setContent("Oops, looks like something went wrong, please try again");
			}
			dialog.open();
		}).fail(function(obj) {
			clearTimeout(loadTimer);
			if (obj.readyState > 1) {
				dialog.setContent('Error!');
				dialog.open();
			}
		});

		return dialog;
	}
})(jQuery, this);
