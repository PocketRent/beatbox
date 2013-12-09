/**
 * Helpers for creating simple ui objects, buttons, etc
 */
(function (window) {
	'use strict';

	window.createButton = function (text, colour, kind, size) {
		var colour = colour || 'blue',
			kind = kind || 'action',
			size = size || 'medium';

		var button = document.createElement('button');
		button.setAttribute('class', [colour, kind, size].join(' '));
		if (kind == 'link') {
			button.setAttribute('role', 'link');
		}

		button.innerText = text;

		return button;
	}

})(this);
