/**
 * Adds common development things to browsers that lack them,
 * like a console object and other simple development features
 */
(function (window) {
	"use strict";
	// If we're in live disable the console, if there is no console, add a stub anyway
	if (!window.environment.in_dev || typeof window.console == "undefined") {
		window.console = {
			debug: function () { },
			dir: function () { },
			error: function () { },
			group: function () { },
			groupCollapsed: function () { },
			groupEnd: function () { },
			info: function () { },
			log: function () { },
			time: function () { },
			timeEnd: function () { },
			trace: function () { },
			warn: function () { }
		};
	}

	if (window.environment.in_dev) {
		window.assert = function(condition/*, message*/) {
			"use strict";
			var message = arguments[1] || ''+condition;
			if (typeof console.assert == 'function') {
				console.assert(condition, message);
			} else {
				if (!condition) {
					console.trace();
					throw "Assertion Failed: "+message; // The only way to just stop right here as far as I can tell
				}
			}
		};
	} else {
		window.assert = function () {};
	}
})(this)
