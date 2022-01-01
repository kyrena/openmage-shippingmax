/**
 * Created V/12/04/2019
 * Updated M/07/12/2021
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
	this.show    = false; // select country
	this.showAll = false;

	this.init = function () {

		console.info('shippingmax.map - hello');

		// voir aussi Kyrena_Shippingmax_Model_Source_Maps
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
				maxZoom: 17,
				detectRetina: true
			}),
			osmfr: L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map France',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			osmde: L.tileLayer('https://{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Deutschland',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			osmbre: L.tileLayer('https://tile.openstreetmap.bzh/br/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Brezhoneg',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			osmoci: L.tileLayer('https://tile.openstreetmap.bzh/oc/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Occitan',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			osmeus: L.tileLayer('https://tile.openstreetmap.bzh/eu/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Euskara',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			osmbot: L.tileLayer('https://{s}.tile.openstreetmap.fr/openriverboatmap/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Street Map Boat',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			ocm: L.tileLayer('https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png', {
				attribution: '<a href="https://github.com/cyclosm/cyclosm-cartocss-style" target="_blank">CyclOSM<\/a>',
				name: 'Open Cyclo Map',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			otm: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
				attribution: osmcopy,
				name: 'Open Topo Map',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			chm: L.tileLayer('https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg', {
				attribution: '<a href="https://www.swisstopo.admin.ch/" target="_blank">Swisstopo<\/a>',
				name: 'Swiss Topo Map',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			ggm: L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
				subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
				attribution: ggmcopy,
				name: 'Google Map',
				minZoom: 4,
				maxZoom: 17,
				detectRetina: true
			}),
			ggmst: L.tileLayer('httpS://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
				subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
				attribution: ggmcopy,
				name: 'Google Map Sat',
				minZoom: 4,
				maxZoom: 17,
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
			document.querySelector('div.main').classList.add('empty');
		}
		else if (nb > self.maxpts) {
			self.map.setView([46.76, 2.42], 6); // @todo France
		}
		else {
			self.map.fitBounds(self.grp.getBounds());
			if (self.map.getZoom() > 15)
				self.map.setZoom(15);
		}

		if (!document.querySelector('.alone')) {
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
					if (ev.detail > 1)
						return;
					var btn = this.getContainer().classList, div = document.querySelector('.main').classList;
					if (btn.contains('leaflet-fullscreen-on')) {
						btn.remove('leaflet-fullscreen-on');
						div.remove('full-map');
						if (div = document.querySelector('.item.clicked.active'))
							div.scrollIntoView();
						else if (div = document.querySelector('.item.clicked'))
							div.scrollIntoView();
						else if (div = document.querySelector('.item.active'))
							div.scrollIntoView();
					}
					else {
						btn.add('leaflet-fullscreen-on');
						div.add('full-map');
					}
					this._map.invalidateSize();
				}
			});
			self.map.addControl(new L.Control.Bigmap());
		}

		L.control.scale({ imperial: false }).addTo(self.map);
		L.control.layers(maps).addTo(self.map);
		self.grp.addTo(self.map);

		// marque l'éventuel point de livraison sélectionné
		elem = document.querySelector('.item.active');
		if (elem) {
			mkr = self.mkrs[elem.getAttribute('id').slice(2)];
			mkr.getElement().classList.add('active');
			this.goToDetailFromMarker({ target: mkr, move: false });
		}

		// si géoloc
		elem = document.querySelector('.btn-geoloc');
		if (elem && navigator.geolocation)
			elem.classList.remove('hide');

		// pour Edge 14 qui laisse le loader affiché
		elem = self.parent.document.getElementById('shippingmaxBox');
		if (elem)
			elem.classList.add('hack');
	};

	this.geoloc = function () {

		if (navigator.geolocation) {
			var elem = document.querySelector('.btn-geoloc span'), text = elem.textContent;
			elem.textContent = '...';
			navigator.geolocation.getCurrentPosition(function (position) {
				shippingmax.formAjax(document.querySelector('form.address'), position.coords.latitude, position.coords.longitude);
				elem.textContent = text;
			}, function () {
				elem.textContent = text;
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
			mkr.on('click', shippingmax.goToDetailFromMarker);
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

	this.formAjax = function (form, lat, lng) {

		var data, xhr = new XMLHttpRequest(), loader = document.getElementById('loader').classList;

		try {
			if (!loader.contains('show')) {

				loader.add('show');

				if (shippingmax.showAll)
					shippingmax.showAll = false;
				else if ((typeof lat == 'number') && (typeof lng == 'number'))
					data = shippingmax.serialize(form) + '&lat=' + lat + '&lng=' + lng + '&geoloc=1';
				else
					data = shippingmax.serialize(form);

				// form update ou form save
				if (form.action.indexOf('update') > -1) {
					document.querySelector('form.results').innerHTML = '';
					document.activeElement.blur();
				}

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
								document.querySelector('form.results').innerHTML = json.maplist;
								self.data    = json.items;
								self.marklat = json.lat;
								self.marklng = json.lng;

								if (json.country && (elem = document.querySelector('input[value="' + json.country.toUpperCase() + '"]'))) {
									if (subelem = document.querySelector('.country-choice input[checked]'))
										subelem.removeAttribute('checked');
									if (subelem = document.querySelector('.country-choice input:checked'))
										subelem.checked = false;
									elem.checked = true;
									elem.setAttribute('checked', 'checked');
								}

								if (json.postcode)
									document.getElementById('postcode').value = json.postcode;
								if (json.city)
									document.getElementById('city').value = json.city;

								self.grp.clearLayers();
								nb = Object.keys(self.data).length;
								if (nb > 0) {
									document.querySelector('div.main').classList.remove('empty');
									self.map.invalidateSize();
									shippingmax.createMarkers(self.data);
									if (nb > self.maxpts) {
										self.map.setView([46.76, 2.42], 6); // @todo France
									}
									else {
										self.map.fitBounds(self.grp.getBounds());
										if (self.map.getZoom() > 15)
											self.map.setZoom(15);
									}
								}
								else {
									document.querySelector('div.main').classList.add('empty');
								}
							}
							else if (json.status) {

								// save
								if (typeof self.parent.shippingmax.show == 'function') {
									self.parent.shippingmax.show(json);
								}
								else {
									shippingmax.reset(true);
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
			}

			return false;
		}
		catch (e) {
			console.error(e);
			loader.remove('show');
		}

		return true;
	};

	this.serialize = function (form) {

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

	this.goToDetailFromMarker = function (ev) {

		// ne fait rien si formAjax est en cours
		if (document.activeElement && (document.activeElement.nodeName === 'BUTTON'))
			return;

		var mkr = ev.target, css = mkr.getElement().classList, already = css.contains('clicked');
		shippingmax.reset();

		// désélectionne ou sélectionne
		if (already) {
			css.remove('clicked');
		}
		else {
			var elem = document.getElementById('pt' + mkr.superId), form = document.querySelector('form.results');

			if (ev.move !== false) {
				css.add('clicked');
				elem.classList.add('clicked');
				self.map.setView(mkr.getLatLng());
			}
			else if (document.querySelector('.alone')) {
				// pour la carte d'une commande
				css.add('clicked');
				elem.classList.add('clicked');
			}

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
		}
	};

	this.goToMarkerFromDetail = function (elem) {

		// ne fait rien si formAjax est en cours
		if (document.activeElement && (document.activeElement.nodeName === 'BUTTON'))
			return;

		var css = elem.classList, already = css.contains('clicked');
		this.reset();

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

	this.reset = function (active) {
		document.querySelectorAll('.clicked').forEach(function (elem) { elem.classList.remove('clicked'); });
		if (active)
			document.querySelectorAll('.active').forEach(function (elem) { elem.classList.remove('active'); });
	};

	this.showSelect = function (action) {
		action = (action === false) ? 'remove' : ((this.show = !this.show) ? 'add' : 'remove');
		document.querySelector('.search-elements').classList[action]('country-select');
		this.show = action === 'add';
	};

	this.updatePostcode = function (elem) {
		document.getElementById('postcode').setAttribute('type', (self.tel.indexOf(elem.value) > -1) ? 'tel' : 'text');
	};

})();

if (typeof self.addEventListener == 'function')
	self.addEventListener('load', shippingmax.init.bind(shippingmax));