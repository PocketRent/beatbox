/**
 * Array polyfills, pretty much just for IE 8
 */

if (typeof Array.prototype.indexOf != 'function') {
	Array.prototype.indexOf = function (searchElement/*, fromIndex*/) {
		'use strict';

		if (this == null) {
			throw new TypeError();
		}

		var n, k, t = Object(this),
			len = t.length >>> 0;

		if (len === 0) {
			return -1;
		}

		n = 0;

		if (arguments.length > 1) {
			n = Number(arguments[1]);
			if (n != n) {
				n = 0;
			} else if (n != 0 && n != Infinity && n!= -Infinity) {
				n = (n > 0 || -1) * Math.floor(Math.abs(n));
			}
		}

		if (n >= len) {
			return -1;
		}
		for (k = n >= 0 ? n : Math.max(len - Math.abs(n), 0); k < len; k++) {
			if (k in t && t[k] == searchElement) {
				return k;
			}
		}
		return -1;
	};

}
if (!Array.prototype.forEach) {
	Array.prototype.forEach = function (fn, scope) {
		'use strict';
		var i, len;
		for (i = 0, len = this.length; i < len; ++i) {
			if (i in this) {
				fn.call(scope, this[i], i, this);
			}
		}
	};
}
