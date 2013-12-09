(function ($, window) {
	'use strict';

	$.entwine('beatbox', function ($) {
		$('.popup form,form.ajax-form').entwine({
			onsubmit: function (e) {
				e.preventDefault();
				var action = this.attr('action');
				var uri = new Uri(action);
				var fragment = uri.params['fragments'];
				var that = this;
				this.ajaxSubmit({
					success: function (data, status) {
						if (status == 'success') {
							data = data[fragment];

							if (data) {
								that.handleData(data);
								return;
							}
						}

						// Error handling
					}
				});
			},
			handleData: function(data) {
				if (typeof data == 'string') {
					this.replaceWith(data);
				} else if (typeof data == 'object') {
					var fragments = data['fragments'];
					if (fragments) {
						loadFragments(fragments.join(','));
					}

					if (Popup.currentPopup()) {
						Popup.currentPopup().close();
					}
				}

			}
		});
	});

	$(document).ajaxSuccess(function(event, xhr, options) {
		var location = xhr.getResponseHeader('x-ajax-location');
		if(location) {
			document.location = location;
			return false;
		}
	});

})(jQuery, this);
