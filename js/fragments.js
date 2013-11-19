(function (window) {
	"use strict";

	var $ = window.jQuery,
		document = window.document,
		History = window.History;


	window.loadFragments = function () {

		if (arguments.length == 0) { return null; }

		var rootUrl = History.getBaseUrl(),
			url = History.getPageUrl(),
			relativeUrl = url.replace(rootUrl, '') || '/';

		var path = relativeUrl.replace(/\?.+$/, '');

		var url = path + '?fragments='+arguments[0];

		var i;
		for(i=1;i<arguments.length;i++) {
			url += ','+arguments[i];
		}

		var p = $.getJSON(url, function (data) {
			for(name in data) {
				var evtData = { fragmentHTML: null, fragmentData: null };

				if (typeof data[name] == 'object') {
					evtData.fragmentData = data[name];
				} else if (typeof data[name] == 'string') {
					evtData.fragmentHTML = data[name];
				}

				var evt = $.Event("fragmentload", evtData);
				var frag = $('.fragment[data-fragment-name="'+name+'"]');

				frag.trigger(evt);
			}
		});

		return p;
	}

	// Document level handler for fragmentload events
	$(document).on("fragmentload", function (e) {
		if (e.isDefaultPrevented()) return;
		if (e.fragmentHTML !== null) {
			$(e.target).html(e.fragmentHTML);
		}
	});
})(this);
