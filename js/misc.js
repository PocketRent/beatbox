(function (window) {
	"use strict";

	var Uri = function (uri) {

		this.protocol = window.location.protocol.replace(':', '');
		this.host = window.location.hostname;
		this.port = "";
		this.path = "";
		this.params = {};
		this.hash = null;

		if (typeof uri == "string") {
			// Use the browser to parse most of the uri for us
			var a = document.createElement('a');
			a.href = uri;

			this.protocol = a.protocol.replace(':', '');
			this.host = a.hostname;
			this.port = a.port ? parseInt(a.port) : null;
			this.path = a.pathname.replace(/^([^\/])/, '/$1');
			this.params = (function () {
				var ret = {},
					seg = a.search.replace(/^\?/, '').split('&'),
					len = seg.length, i = 0, s;
				for (;i<len;i++) {
					if (!seg[i]) { continue; }
					s = seg[i].split('=', 2);
					ret[s[0]] = s[1];
				}

				return ret;
			})();

			this.hash = a.hash.replace(/^#/, '');
		}
	};

	Uri.prototype.param = function(key, value) {
		if (value == undefined) return this.params[key];

		if (!value) {
			delete this.params[key];
		} else {
			this.params[key] = value;
			return value;
		}
	};

	Uri.prototype.toString = function () {
		var uri = "";
		if (!(window.location.hostname == this.host&&
			window.location.protocol == this.protocol + ':')) {
			uri = this.protocol + '://' + this.host;
		}

		uri += this.path;

		var query = "";
		for (var key in this.params) {
			query += key + '=' + this.params[key] + '&';
		}

		if (query) {
			query = '?' + query;
			query = query.replace(/&+$/, '')
		}

		uri += query;

		if (this.hash) {
			uri += '#' + this.hash
		}

		return uri;
	}

	window.Uri = Uri;
})(this);
