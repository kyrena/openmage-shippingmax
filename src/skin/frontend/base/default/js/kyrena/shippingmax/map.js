/**
 * Created V/12/04/2019
 * Updated L/26/12/2022
 *
 * Copyright 2019-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2019-2023 | Mickaël Vang <mickael~cellublue~com>
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
	this.show    = false; // select country
	this.showAll = false; // shippingmax_storelocator

	this.init = function () {

		console.info('shippingmax.map - hello');

		// @see Kyrena_Shippingmax_Model_Source_Maps
		// pour self.defmap et pour les codes des cartes
		var mkr, nb, elem, maps = {},
		    igncopy = '<a href="https://www.ign.fr/" target="_blank">IGN France<\/a>',
		    osmcopy = '<a href="https://www.openstreetmap.org/copyright" target="_blank">Open Street Map<\/a>',
		    ggmcopy = '<a href="https://www.google.com/maps" target="_blank">Google<\/a>',
		    config = {
			ign: self.ignkey ? L.tileLayer('https://wxs.ign.fr/' + self.ignkey + '/geoportail/wmts?&request=GetTile&service=WMTS' +
				'&version=1.0.0&style=normal&tilematrixset=PM&format=image/jpeg&layer=GEOGRAPHICALGRIDSYSTEMS.MAPS' +
				'&tilematrix={z}&tilerow={y}&tilecol={x}', {
				attribution: igncopy,
				name: 'IGN France',
				minZoom: 4,
				maxZoom: 18,
				tileSize: 256,
				detectRetina: true
			}) : null,
			ignst: self.ignkey ? L.tileLayer('https://wxs.ign.fr/' + self.ignkey + '/geoportail/wmts?&request=GetTile&service=WMTS' +
				'&version=1.0.0&style=normal&tilematrixset=PM&format=image/jpeg&layer=ORTHOIMAGERY.ORTHOPHOTOS' +
				'&tilematrix={z}&tilerow={y}&tilecol={x}', {
				attribution: igncopy,
				name: 'IGN Sat France',
				minZoom: 4,
				maxZoom: 18,
				tileSize: 256,
				detectRetina: true
			}) : null,
			osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			osmfr: L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map France',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			osmde: L.tileLayer('https://{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Deutschland',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			osmbre: L.tileLayer('https://tile.openstreetmap.bzh/br/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Brezhoneg',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			osmoci: L.tileLayer('https://tile.openstreetmap.bzh/oc/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Occitan',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			osmeus: L.tileLayer('https://tile.openstreetmap.bzh/eu/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Euskara',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			osmbot: L.tileLayer('https://{s}.tile.openstreetmap.fr/openriverboatmap/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Boat',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			ocm: L.tileLayer('https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png', {
				attribution: '<a href="https://github.com/cyclosm/cyclosm-cartocss-style" target="_blank">CyclOSM<\/a>',
				name: 'Open Cyclo Map',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			otm: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Topo Map',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			chm: L.tileLayer('https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg', {
				attribution: '<a href="https://www.swisstopo.admin.ch/" target="_blank">Swisstopo<\/a>',
				name: 'Swiss Topo Map',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			ggm: L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
				subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
				attribution: ggmcopy,
				name: 'Google Map',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			}),
			ggmst: L.tileLayer('httpS://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
				subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
				attribution: ggmcopy,
				name: 'Google Map Sat',
				minZoom: 4,
				maxZoom: 19,
				detectRetina: true
			})
		};

		if (!config[self.defmap] || (self.defmap === 'ggm') && (['yes', '1', 1].indexOf(navigator.doNotTrack) > -1))
			self.defmap = 'osm';

		// charge les marqueurs
		self.grp = new L.featureGroup();
		this.createMarkers(self.data);

		// charge les fonds de carte
		for (elem in config) if (config.hasOwnProperty(elem)) {
			if (config[elem] && config[elem].options.name) {
				config[elem].options.attribution += ' ' + self.browser;
				maps['<i class="hide">' + elem + '<\/i> ' + config[elem].options.name] = config[elem];
			}
		}

		// charge la carte
		nb = Object.keys(self.data).length;
		self.map = L.map('map', { layers: [config[self.defmap], self.grp], scrollWheelZoom: true, doubleRightClickZoom: true });

		if (nb < 1) {
			this.qs('div.main').classList.add('empty');
		}
		else if (nb > self.maxpts) {
			self.map.setView([46.76, 2.42], 6); // @todo France
		}
		else {
			self.map.fitBounds(self.grp.getBounds(), { maxZoom: 15 });
		}

		// pseudo plein écran
		if (!this.qs('.alone')) {
			L.Control.Bigmap = L.Control.extend({
				options: { position: 'topleft' },
				onAdd: function (map) {
					var elem  = L.DomUtil.create('div', 'leaflet-control-fullscreen leaflet-bar leaflet-control');
					this.link = L.DomUtil.create('a', 'leaflet-control-fullscreen-button leaflet-bar-part', elem);
					this.link.href = '';
					this._map = map;
					L.DomEvent.disableClickPropagation(this.link);
					L.DomEvent.on(this.link, 'click', this.action, this);
					return elem;
				},
				action: function (ev) {
					L.DomEvent.stop(ev);
					if (ev.detail == 1) {
						var that = shippingmax, btn = this.getContainer().classList, div = that.qs('.main').classList;
						if (btn.contains('leaflet-fullscreen-on')) {
							btn.remove('leaflet-fullscreen-on');
							div.remove('full-map');
							if (div = that.qs('.item.clicked, .item.active'))
								that.scrollToDetails(that.qs('form.results'), div);
						}
						else {
							btn.add('leaflet-fullscreen-on');
							div.add('full-map');
						}
						this._map.invalidateSize();
					}
				}
			});
			self.map.addControl(new L.Control.Bigmap());
		}

		L.control.scale({ imperial: false }).addTo(self.map);
		L.control.layers(maps).addTo(self.map);
		self.grp.addTo(self.map);

		// marque l'éventuel point de livraison sélectionné
		elem = this.qs('.item.active');
		if (elem) {
			mkr = self.mkrs[elem.getAttribute('id').slice(2)];
			mkr.getElement().classList.add('active');
			this.goToDetailsFromMarker({ target: mkr, move: false });
		}

		// géoloc
		elem = this.qs('.btn-geoloc');
		if (elem && navigator.geolocation)
			elem.classList.remove('hide');

		// pour Edge 14 qui laisse le loader affiché
		elem = self.parent.document.getElementById('shippingmaxBox');
		if (elem)
			elem.classList.add('hack');

		// ferme avec échap
		if ((self != self.parent) && (typeof self.parent.shippingmax == 'object') && (typeof self.parent.shippingmax.keyClose == 'function'))
			document.addEventListener('keydown', self.parent.shippingmax.keyClose);
	};

	this.geoloc = function () {

		if (navigator.geolocation) {
			var that = shippingmax, elem = that.qs('.btn-geoloc span'), text = elem.textContent;
			elem.textContent = '...';
			navigator.geolocation.getCurrentPosition(function (position) {
				that.submitFormAjax(that.qs('form.address'), position.coords.latitude, position.coords.longitude);
				elem.textContent = text;
			}, function (err) {
				elem.textContent = text;
				console.warn(err);
			});
		}
	};

	this.createMarkers = function (mkrs) {

		var mkr, id;
		self.mkrs = {};

		for (mkr in mkrs) if (mkrs.hasOwnProperty(mkr)) {

			mkr = mkrs[mkr];
			id  = mkr.id;

			mkr = L.marker([mkr.lat, mkr.lng], {
				title: mkr.name,
				shadowUrl: null,
				shadowRetinaUrl: null,
				shadowSize: null,
				shadowAnchor: null
			}).addTo(self.grp); //.bindPopup(mkr.name)
			mkr.on('click', shippingmax.goToDetailsFromMarker);
			mkr.on('dblclick', L.DomEvent.stop);
			mkr.superId = id;

			self.mkrs[id] = mkr;
		}

		if ((self.marklat != 0) && (self.marklng != 0)) {
			L.marker([self.marklat, self.marklng], {
				icon: L.icon({
					iconUrl: L.Icon.Default.prototype._getIconUrl().replace('undefined', 'ic-user-map.svg'),
					iconSize: [26, 40],
					iconAnchor: [13, 25],
					shadowUrl: null,
					shadowRetinaUrl: null,
					shadowSize: null,
					shadowAnchor: null,
					className: 'userpos'
				})
			}).addTo(self.grp);
		}
	};

	this.submitFormAjax = function (form, lat, lng) {

		var data, xhr, that = shippingmax, wasAll = that.showAll, loader = document.getElementById('loader').classList;
		if (loader.contains('show'))
			return false;

		try {
			loader.add('show');

			if (that.showAll)
				that.showAll = false; // true onload and onclick btn-show-all
			else if ((typeof lat == 'number') && (typeof lng == 'number'))
				data = that.serializeForm(form) + '&lat=' + lat + '&lng=' + lng + '&geoloc=1';
			else
				data = that.serializeForm(form);

			// form update ou form save
			if (form.action.indexOf('update') > -1) {
				that.qs('form.results').innerHTML = '';
				document.activeElement.blur();
			}

			// post ou get
			xhr = new XMLHttpRequest();
			if (data) {
				xhr.open('POST', (data.indexOf('id=') > -1) ? form.action + '?isAjax=true&' + data : form.action + '?isAjax=true', true);
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			}
			else {
				xhr.open('GET', form.action + '?isAjax=true', true);
			}

			xhr.onreadystatechange = function () {

				if ((xhr.readyState === 4) && ((xhr.status === 200) || (xhr.status === 0))) {

					var mkr, elem, subelem, json, nb;
					try {
						json = JSON.parse(xhr.responseText);

						if (json.status && json.maplist) {

							// update
							that.qs('form.results').innerHTML = json.maplist;
							self.data    = json.items;
							self.marklat = json.lat;
							self.marklng = json.lng;

							if (json.country && (elem = that.qs('input[value="' + json.country.toUpperCase() + '"]'))) {
								if (subelem = that.qs('.country-choice input[checked]'))
									subelem.removeAttribute('checked');
								if (subelem = that.qs('.country-choice input:checked'))
									subelem.checked = false;
								elem.checked = true;
								elem.setAttribute('checked', 'checked');
							}

							if (wasAll || json.postcode)
								document.getElementById('postcode').value = json.postcode || '';
							if (wasAll || json.city)
								document.getElementById('city').value = json.city || '';

							self.grp.clearLayers();
							nb = Object.keys(self.data).length;
							if (nb > 0) {
								that.qs('div.main').classList.remove('empty');
								self.map.invalidateSize();
								that.createMarkers(self.data);
								if (nb > self.maxpts) {
									self.map.setView([46.76, 2.42], 6); // @todo France
								}
								else {
									self.map.fitBounds(self.grp.getBounds(), { maxZoom: 15 });
								}
							}
							else {
								that.qs('div.main').classList.add('empty');
							}
						}
						else if (json.status) {

							// save
							if (typeof self.parent.shippingmax.show == 'function') {
								self.parent.shippingmax.show(json);
							}
							else {
								that.resetMarkers(true);
								mkr = self.mkrs[json.id];
								mkr.getElement().classList.add('active');
								self.map.setView(mkr.getLatLng());
								document.getElementById('pt' + json.id).classList.add('active', 'clicked');
							}
						}
						else if (json.error) {
							if (json.error === 'refresh')
								self.location.reload();
							else
								alert(json.error);
						}
						else {
							alert(json);
						}

						loader.remove('show');
					}
					catch (e) {
						console.error(e, xhr.responseText); // SyntaxError: Unexpected end of JSON input
						self.location.reload();
					}
				}
			};

			xhr.send(data);
			return false;
		}
		catch (e) {
			console.error(e);
			loader.remove('show');
		}

		return true;
	};

	this.serializeForm = function (form) {

		var data = [];

		Array.prototype.forEach.call(form.elements, function (elem) {

			if (elem.nodeName === 'INPUT') {
				if (['checkbox', 'radio'].indexOf(elem.getAttribute('type')) > -1) {
					if (elem.checked)
						data.push(elem.name + '=' + encodeURIComponent(elem.value));
				}
				else {
					data.push(elem.name + '=' + encodeURIComponent(elem.value));
				}
			}
			else if (elem.nodeName === 'SELECT') {
				data.push(elem.name + '=' + encodeURIComponent(elem.value));
			}
		});

		return data.join('&');
	};

	this.goToDetailsFromMarker = function (ev) {

		// ne fait rien si submitFormAjax est en cours ou s'il va commencer
		if (document.getElementById('loader').classList.contains('show') || (document.activeElement && (document.activeElement.nodeName === 'BUTTON')))
			return;

		var that = shippingmax, mkr = ev.target, css = mkr.getElement().classList, already = css.contains('clicked');
		that.resetMarkers();

		// désélectionne ou sélectionne
		if (already) {
			css.remove('clicked');
		}
		else {
			var elem = document.getElementById('pt' + mkr.superId), form = that.qs('form.results');

			if (ev.move !== false) {
				css.add('clicked');
				elem.classList.add('clicked');
				self.map.setView(mkr.getLatLng());
			}
			else if (that.qs('.alone')) {
				// pour la carte d'une commande
				css.add('clicked');
				elem.classList.add('clicked');
			}

			that.scrollToDetails(form, elem);
		}
	};

	this.goToMarkerFromDetails = function (elem) {

		// ne fait rien si submitFormAjax est en cours ou s'il va commencer
		if (document.getElementById('loader').classList.contains('show') || (document.activeElement && (document.activeElement.nodeName === 'BUTTON')))
			return;

		var css = elem.classList, already = css.contains('clicked');
		this.resetMarkers();

		// désélectionne ou sélectionne
		if (already) {
			css.remove('clicked');
		}
		else {
			css.add('clicked');
			var mkr = self.mkrs[elem.getAttribute('id').slice(2)];
			mkr.getElement().classList.add('clicked');
			self.map.setView(mkr.getLatLng(), 15);
		}
	};

	this.scrollToDetails = function (form, elem) {

		if (form.scrollHeight > form.offsetHeight) {
			if (window.innerWidth < 992) {
				form.scrollTop = elem.offsetTop;
			}
			else {
				form.scrollTop = elem.offsetTop - form.offsetHeight / 2 + 80;
				var rect = elem.getBoundingClientRect();
				if ((rect.top < 0) && (rect.bottom <= window.innerHeight))
					form.scrollTop = elem.offsetTop;
			}
		}
	};

	this.resetMarkers = function (active) {
		document.querySelectorAll('.clicked').forEach(function (elem) { elem.classList.remove('clicked'); });
		if (active)
			document.querySelectorAll('.active').forEach(function (elem) { elem.classList.remove('active'); });
	};

	this.showSelect = function (action) {
		action = (action === false) ? 'remove' : ((this.show = !this.show) ? 'add' : 'remove');
		this.qs('.search-elements').classList[action]('country-select');
		this.show = action === 'add';
	};

	this.updatePostcode = function (elem) {
		document.getElementById('postcode').setAttribute('type', (self.tel.indexOf(elem.value) > -1) ? 'tel' : 'text');
	};

	this.qs = function (selector) {
		return document.querySelector(selector);
	};

})();

if (typeof self.addEventListener == 'function') {
	self.addEventListener('load', shippingmax.init.bind(shippingmax));
	document.addEventListener('touchmove', function (ev) {
		// no zoom
		if ((ev.scale != 1) || (ev.touches && (ev.touches.length > 1)))
			ev.preventDefault();
	}, { passive: false });
}

// instarelou
function _AutofillCallbackHandler() { }
function _pcmBridgeCallbackHandler() { }
var PaymentAutofillConfig = {};