/**
 * Created V/12/04/2019
 * Updated M/25/01/2022
 *
 * Copyright 2019-2022 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2019-2022 | Jérôme Siau <jerome~cellublue~com>
 * https://github.com/kyrena/openmage-shippingmax
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = function (callback, that, i) {
		that = that || window;
		for (i = 0; i < this.length; i++)
			callback.call(that, this[i], i, this);
	};
}

var shippingmax = new (function () {

	"use strict";
	this.scroll = 0;

	this.init = function () {

		if (document.getElementById('shipping_method-progress-opcheckout')) {
			var observer = new MutationObserver(function () { checkout.reloadStep('shipping'); });
			observer.observe(document.getElementById('shipping_method-progress-opcheckout'), {
				attributes: false,
				childList: true,
				characterData: false
			});
		}
	};

	this.open = function (src) {

		if (document.getElementById('shippingmaxDialog'))
			return;

		this.scroll = window.pageYOffset;
		var data = document.createElement('div');
		data.innerHTML =
			'<div id="shippingmaxDialog" onclick="shippingmax.close(event);">' +
				'<div id="shippingmaxBox">' +
					'<iframe type="text/html" src="' + src + '" class="loader" onload="this.removeAttribute(\'class\');"></iframe>' +
					'<div class="loader"></div>' +
				'</div>' +
			'</div>';

		document.querySelector('body').appendChild(data.firstChild);
		document.querySelector('body').classList.add('no-scroll');
		document.addEventListener('keydown', shippingmax.keyClose);
	};

	this.keyClose = function (ev) {

		if (ev.keyCode === 27) {
			console.log('shippingmax.map - esc/keyClose');
			ev.preventDefault();
			shippingmax.close(true);
		}
	};

	this.close = function (ev) {

		document.querySelectorAll('.shipment-methods .dummy, .sp-methods .dummy').forEach(function (elem) { elem.checked = false; });

		if (ev !== true) {
			if (ev.target.getAttribute('id') === 'shippingmaxDialog')
				ev = true;
		}

		if (ev === true) {
			document.getElementById('shippingmaxDialog').remove();
			document.querySelector('body').classList.remove('no-scroll');
			document.removeEventListener('keydown', shippingmax.keyClose);
			window.scrollTo(0, shippingmax.scroll);
		}
	};

	this.click = function (id) {
		document.getElementById(id).parentNode.querySelector('label').click();
	};

	this.show = function (data) {

		this.close(true);

		var elem = document.getElementById(data.code);
		if (elem) {
			elem.innerHTML = data.html.slice(data.html.indexOf('>') + 1, -6); // supprime la <div id></div>
			elem.parentNode.querySelector('input').checked = true;
			document.dispatchEvent(new CustomEvent('shippingmax_update'));
			elem.parentNode.querySelector('label').click();
		}
	};

})();

if (typeof self.addEventListener == 'function')
	self.addEventListener('load', shippingmax.init);