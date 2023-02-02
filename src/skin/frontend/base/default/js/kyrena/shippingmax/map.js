/**
 * Created V/12/04/2019
 * Updated J/02/02/2023
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
	this.showList = false; // select country
	this.showAll  = false; // storelocator

	// base

	this.init = function () {

		console.info('shippingmax.map - hello');

		// crée les fonds de carte
		// @see Kyrena_Shippingmax_Model_Source_Maps
		var elem, maps = {},
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

		for (elem in config) if (config.hasOwnProperty(elem)) {
			if (config[elem] && config[elem].options.name) {
				config[elem].options.attribution += ' ' + self.browser;
				maps[config[elem].options.name] = config[elem];
			}
		}

		if (!config[self.defmap] || (self.defmap === 'ggm') && (['yes', '1', 1].indexOf(navigator.doNotTrack) > -1))
			self.defmap = 'osm';

		// pour IOS pour autoriser le scroll dans l'object (ex-iframe)
		if (navigator.userAgent.match(/iPhone/i))
			this.qs('html').classList.add('iphone');

		// pour Edge 14 qui laisse le loader affiché (ajoute une classe sans css)
		// ajoute aussi une classe à html pour faire la différence entre un onglet et une popin
		elem = self.parent.document.getElementById('shippingmaxBox');
		if (elem) {
			elem.classList.add('hack');
			this.qs('html').classList.add('popin');
		}

		// crée la carte avec les marqueurs
		self.grp = new L.featureGroup();
		self.map = L.map('map', { layers: [config[self.defmap], self.grp], scrollWheelZoom: true, doubleRightClickZoom: true });
		L.control.layers(maps).addTo(self.map);
		L.control.scale({ imperial: false }).addTo(self.map);
		this.createMarkers();

		// boutons plein écran @todo
		// sauf pour la carte d'une commande (.alone)
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
						var btn = this.getContainer().classList, that = shippingmax, div = that.qs('body').classList;
						if (btn.contains('leaflet-fullscreen-on')) {
							btn.remove('leaflet-fullscreen-on');
							div.remove('full-map');
							this._map.invalidateSize();
							if (div = that.qs('.item.clicked, .item.selected')) {
								that.goToMarkerFromDetails({ target: { nodeName: 'fullscreenquit' } }, div);
								that.scrollToDetails(div);
							}
						}
						else {
							btn.add('leaflet-fullscreen-on');
							div.add('full-map');
							this._map.invalidateSize();
						}
					}
				}
			});
			self.map.addControl(new L.Control.Bigmap());

			L.Control.Hidemap = L.Control.extend({
				options: { position: 'topright' },
				onAdd: function (map) {
					var elem  = L.DomUtil.create('div', 'leaflet-control-hidemap leaflet-bar leaflet-control close hide');
					this.link = L.DomUtil.create('a', 'leaflet-control-hidemap-button leaflet-bar-part', elem);
					this.link.href = '';
					this._map = map;
					L.DomEvent.disableClickPropagation(this.link);
					L.DomEvent.on(this.link, 'click', this.action, this);
					return elem;
				},
				action: function (ev) {
					L.DomEvent.stop(ev);
					if (ev.detail == 1) {
						var btn = this.getContainer().classList, that = shippingmax, div = that.qs('body').classList;
						if (btn.contains('leaflet-hidemap-on')) {
							btn.remove('leaflet-hidemap-on');
							div.remove('hide-map');
							that.qs('.leaflet-top.leaflet-right').appendChild(this.getContainer());
						}
						else {
							btn.add('leaflet-hidemap-on');
							div.add('hide-map');
							that.qs('body').appendChild(this.getContainer());
						}
					}
				}
			});
			self.map.addControl(new L.Control.Hidemap());
		}

		// ferme la carte avec la touche échap
		if ((self != self.parent) && (typeof self.parent.shippingmax == 'object') && (typeof self.parent.shippingmax.keyClose == 'function'))
			document.addEventListener('keydown', self.parent.shippingmax.keyClose);
	};

	this.createMarkers = function () {

		var elem, mkr, key, nb = Object.keys(self.data).length;
		self.grp.clearLayers();

		if (nb > 0) {

			this.qs('body').classList.remove('empty');

			// crée les marqueurs des lieux de livraison
			// traitement spécial pour la carte d'une commande (.alone)
			for (key in self.data) if (self.data.hasOwnProperty(key)) {

				mkr = self.data[key];
				mkr = L.marker([mkr.lat, mkr.lng], {
					title: mkr.name,
					shadowUrl: null,
					shadowRetinaUrl: null,
					shadowSize: null,
					shadowAnchor: null
				}).addTo(self.grp);

				mkr.on('click', (nb > 1) ? shippingmax.goToDetailsFromMarker : L.DomEvent.stop);
				mkr.on('dblclick', L.DomEvent.stop);
				mkr.htmlId = key;

				self.data[key].leaflet = mkr;
			}

			// crée le marqueur position
			// si les coordonnées sont définies
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

			// centre la carte
			// traitement spécial pour la carte d'une commande (.alone) et le storelocator
			elem = this.qs('#postcode');
			if (!elem || (elem.value.length > 0))
				self.map.fitBounds(self.grp.getBounds(), { maxZoom: 15 });
			else
				self.map.setView([46.76, 2.42], 6); // France @todo

			// marque l'éventuel point de livraison sélectionné
			elem = this.qs('.item.selected');
			if (elem) {
				mkr = self.data[elem.getAttribute('id').slice(2)].leaflet;
				mkr.getElement().classList.add('selected');
				this.goToDetailsFromMarker({ target: mkr, move: false });
			}
		}
		else {
			this.qs('body').classList.add('empty');
		}

		return nb;
	};

	this.stopScrollFromFixed = function (ev) {

		var elem = ev.target;
		while (elem.parentNode && (elem.nodeName !== 'BODY')) {
			if (elem.classList.contains('leaflet-control-layers-scrollbar')) {
				return;
			}
			if (elem.classList.contains('map') || elem.classList.contains('top')) {
				ev.preventDefault();
				ev.stopPropagation();
				return;
			}
			elem = elem.parentNode;
		}
	};

	this.closeMap = function () {

		if (typeof self.parent.shippingmax.close == 'function')
			self.parent.shippingmax.close(true);
		else
			self.close();
	};

	this.qs = function (selector) {
		return document.querySelector(selector);
	};

	// méthodes inutiles pour la carte d'une commande (.alone)

	this.askGeoloc = function () {

		if (navigator.geolocation) {

			var that = shippingmax, loader = document.getElementById('loader').classList;
			loader.add('show');

			navigator.geolocation.getCurrentPosition(function (pos) {
				that.submitFormAjax(that.qs('form.search'), pos.coords.latitude, pos.coords.longitude, true);
			}, function (err) {
				loader.remove('show');
				console.warn(err);
			});
		}
		else {
			this.qs('.btn-geoloc').classList.add('hide');
		}
	};

	this.submitFormAjax = function (form, lat, lng, ignore) {

		var data, xhr, that = shippingmax, showAll = that.showAll, loader = document.getElementById('loader').classList;

		// n'est pas un button en mobile
		// ce qui fait qu'on est obligé d'utiliser showAll pour déterminer quel bouton submit est utilisé
		document.activeElement.blur();

		if ((ignore !== true) && loader.contains('show'))
			return false;

		try {
			loader.add('show');
			xhr = new XMLHttpRequest();

			// soit l'utilisateur a cliqué sur le bouton afficher tout (btn-show-all), donc en get
			// soit l'utilisateur a cliqué sur le bouton géolocalisation (btn-geoloc), donc transmet les coordonnées
			// soit l'utilisateur a fait une recherche ou a sélectionné un lieu, donc transmet les données du bon formulaire
			if (showAll) {
				that.showAll = false;
				xhr.open('GET', form.action + '?isAjax=true', true);
			}
			else {
				data = that.serializeForm(form);
				if ((typeof lat == 'number') && (typeof lng == 'number'))
					data += '&lat=' + lat + '&lng=' + lng + '&geoloc=1';

				xhr.open('POST', (data.indexOf('id=') > -1) ? form.action + '?isAjax=true&' + data : form.action + '?isAjax=true', true);
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			}

			xhr.onreadystatechange = function () {

				if ((xhr.readyState === 4) && ((xhr.status === 200) || (xhr.status === 0))) {

					var elem, subelem, json, mkr;
					try {
						json = JSON.parse(xhr.responseText);

						if (json.status && json.maplist) {

							self.scroll(0, 0);

							// update list
							that.qs('form.results').innerHTML = json.maplist;

							self.data    = json.items; // (array)
							self.marklat = json.lat;   // (float)
							self.marklng = json.lng;   // (float)

							// sélectionne le bon pays
							if (json.country && (elem = that.qs('.country-choice input[value="' + json.country.toUpperCase() + '"]'))) {
								if (subelem = that.qs('.country-choice input[checked]'))
									subelem.removeAttribute('checked');
								if (subelem = that.qs('.country-choice input:checked'))
									subelem.checked = false;
								elem.checked = true;
								elem.setAttribute('checked', 'checked');
							}

							if (showAll || json.postcode)
								document.getElementById('postcode').value = json.postcode || '';
							if (showAll || json.city)
								document.getElementById('city').value = json.city || '';

							that.createMarkers();
							loader.remove('show');
						}
						else if (json.status) {
							// parent save qui va fermer la carte
							// lorsque la carte est ouverte dans un object
							if (typeof self.parent.shippingmax.show == 'function') {
								self.parent.shippingmax.show(json);
							}
							// marque et centre le point de livraison sélectionné
							// lorsque la carte est ouverte dans un nouvel onglet
							else {
								that.resetMarkers(true);
								mkr = self.data[json.id].leaflet;
								mkr.getElement().classList.add('selected');
								self.map.setView(mkr.getLatLng(), 15);
								document.getElementById('pt' + json.id).classList.add('selected', 'clicked');
								loader.remove('show');
							}
						}
						else if (json.error) {
							// error
							if (json.error === 'refresh') {
								self.location.reload();
							}
							else {
								alert(json.error);
								loader.remove('show');
							}
						}
						else {
							// error
							alert(json);
							loader.remove('show');
						}
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

		// activeElement n'est pas un button en mobile
		// ne fait rien si submitFormAjax est en cours ou s'il va commencer
		if ((ev.target.nodeName === 'BUTTON') || (document.activeElement && (document.activeElement.nodeName === 'BUTTON')))
			return;

		// elem = li.item, css = mkr
		var that = shippingmax, mkr = ev.target, css = mkr.getElement().classList, already = css.contains('clicked');
		that.resetMarkers();

		// désélectionne, ou sélectionne et centre la carte et centre la liste
		if (already) {
			css.remove('clicked');
		}
		else {
			var elem = document.getElementById('pt' + mkr.htmlId);
			if (ev.move !== false) {
				css.add('clicked');
				elem.classList.add('clicked');
				self.map.setView(mkr.getLatLng());
			}
			that.scrollToDetails(elem);
		}
	};

	this.goToMarkerFromDetails = function (ev, elem) {

		// activeElement n'est pas un button en mobile
		// ne fait rien si submitFormAjax est en cours ou s'il va commencer
		if ((ev.target.nodeName === 'BUTTON') || (document.activeElement && (document.activeElement.nodeName === 'BUTTON')))
			return;

		// css et elem = li.item
		var css = elem.classList, already = css.contains('clicked');
		this.resetMarkers();

		// désélectionne, ou sélectionne et centre la carte
		if (already && !css.contains('selected') && (ev.target.nodeName !== 'fullscreenquit')) {
			css.remove('clicked');
		}
		else {
			css.add('clicked');
			var mkr = self.data[elem.getAttribute('id').slice(2)].leaflet;
			mkr.getElement().classList.add('clicked');
			self.map.setView(mkr.getLatLng(), 15);
		}
	};

	this.scrollToDetails = function (elem) {

		// pour IOS qui refuse de faire un scroll
		if (this.qs('html.iphone.popin')) {
			var val = this.qs('.map').getBoundingClientRect().bottom + 5;
			this.qs('.results-list').setAttribute('style', 'margin:-' + val + 'px 0 ' + val + 'px;');
			elem.scrollIntoView({ block: 'start' });
			this.qs('.results-list').removeAttribute('style');
		}
		else {
			self.scroll(0, (self.innerWidth < 768) ?
				elem.offsetTop - parseInt(self.getComputedStyle(this.qs('.results')).paddingTop) - 5 :
				elem.offsetTop - self.innerHeight / 2 + this.qs('.top').offsetHeight / 2 + elem.offsetHeight / 2
			);
		}
	};

	this.resetMarkers = function (selected) {
		document.querySelectorAll('.clicked, .selected').forEach(function (elem) {
			elem.classList.remove('clicked', selected ? 'selected' : 'nothing');
		});
	};

	this.updateZipFromCountry = function (elem) {
		document.getElementById('postcode').setAttribute('type', (self.tel.indexOf(elem.value) < 0) ? 'text' : 'tel');
		if (elem.value == 'MC') {
			document.getElementById('postcode').value = '98000';
			document.getElementById('city').value = 'Monaco';
		}
	};

	this.showSelect = function (action) {
		action = (action === false) ? 'remove' : ((this.showList = !this.showList) ? 'add' : 'remove');
		this.qs('.search').classList[action]('country-select');
		this.showList = action === 'add';
	};

})();

if (typeof self.addEventListener == 'function') {
	self.addEventListener('load', shippingmax.init.bind(shippingmax));
	self.addEventListener('DOMMouseScroll', shippingmax.stopScrollFromFixed, { passive: false });
	self.addEventListener('mousewheel', shippingmax.stopScrollFromFixed, { passive: false });
	self.addEventListener('touchmove', shippingmax.stopScrollFromFixed, { passive: false });
}

// instarelou
function _AutofillCallbackHandler() { }
function _pcmBridgeCallbackHandler() { }
var PaymentAutofillConfig = { };