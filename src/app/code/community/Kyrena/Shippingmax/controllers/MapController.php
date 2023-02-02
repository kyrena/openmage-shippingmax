<?php
/**
 * Created V/12/04/2019
 * Updated J/02/02/2023
 *
 * Copyright 2019-2023 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Kyrena_Shippingmax_MapController extends Mage_Core_Controller_Front_Action {

	protected $_session;
	protected $_isShowAll;
	protected $_isStoreLocator;
	protected $_carrierCountries;


	protected function isAjax() {
		return ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax')));
	}

	protected function customDispatch() {

		if (stripos($this->getFullActionName(), 'debug') === false) {

			$code = $this->getRequest()->getParam('code');
			if (empty($code)) {
				$this->setFlag('', 'no-dispatch', true);
				return $this->getResponse()
					->setHttpResponseCode(404)
					->setHeader('X-Shippingmax', 'code-is-required', true);
			}

			// autorise uniquement les codes des modes de livraison des points de livraison
			// même s'ils ne sont pas activés
			if (empty(Mage::getStoreConfig('carriers/'.$code.'/title')) || !Mage::helper('shippingmax')->isSpecial($code)) {

				// autorise aussi les ids des commandes
				if (!is_numeric($code)) {

					if (str_contains($code, 'shippingmax_')) {
						$this->setFlag('', 'no-dispatch', true);
						return $this->getResponse()
							->setHttpResponseCode(404)
							->setHeader('X-Shippingmax', 'code-unknown', true);
					}

					// autorise un code court
					$code = 'shippingmax_'.$code;
					if (empty(Mage::getStoreConfig('carriers/'.$code.'/title')) || !Mage::helper('shippingmax')->isSpecial($code)) {
						$this->setFlag('', 'no-dispatch', true);
						return $this->getResponse()
							->setHttpResponseCode(404)
							->setHeader('X-Shippingmax', 'short-code-unknown', true);
					}

					$this->getRequest()->setParam('code', $code);
				}
			}

			if ($code == 'shippingmax_storelocator') {
				$this->_isShowAll = true;
				$this->_isStoreLocator = true;
			}
			else {
				$this->_isShowAll = !empty($this->getRequest()->getParam('showall'));
			}
		}

		$this->_session = Mage::helper('shippingmax')->getSession();

		return $this;
	}

	protected function getCarrierItemsByAddress(object $address, string $code) {

		Mage::register('address_ignore_name', true, true);
		$this->_carrierCountries = Mage::helper('shippingmax')->getCarrierCountries($code);

		$model = Mage::getSingleton('shipping/config')->getCarrierInstance($code);

		// récupère les points de livraison
		// depuis la géolocalisation du navigateur ou depuis l'adresse
		if (!empty($address->getData('geoloc')) && !empty($address->getData('lat')) && !empty($address->getData('lng'))) {

			// n'autorise pas le changement de pays
			// donc cherche le pays par rapport aux coordonnées avant de mettre à jour la véritable adresse
			$check  = clone $address;
			$dadata = Mage::helper('shippingmax')->withDaData($code);
			Mage::getModel('shippingmax/coords')->setReverseAddressCoords($check, $dadata);

			$address->setData('geo_city', $check->getData('city'));
			$address->setData('geo_postcode', $check->getData('postcode'));
			$address->setData('geo_country_id', $check->getData('country_id'));

			// n'autorise pas le changement de pays
			// oui mais sauf si showAll et si le pays géolocalisé est autorisé
			if (
				($check->getData('country_id') == $address->getData('country_id')) ||
				($this->_isShowAll && in_array($check->getData('country_id'), $this->_carrierCountries))
			) {
				Mage::getModel('shippingmax/coords')->setReverseAddressCoords($address, $dadata);
				$items = $model->loadItemsFromCache($address);
			}
			else {
				$items = [];
			}
		}
		else {
			Mage::getModel('shippingmax/coords')->setAddressCoords($address);
			$items = $model->loadItemsFromCache($address);
		}

		$items = (!empty($items) && is_array($items)) ? $items : [];

		// filtrage à l'affichage
		if ($this->_isShowAll && !empty($items) && !empty($search = $this->getRequest()->getParam('q'))) {
			$newItems = [];
			foreach ($items as $key => $item) {
				if (mb_stripos($item['name'], $search) !== false)
					$newItems[$key] = $item;
			}
			if (!empty($newItems))
				$items = $newItems;
		}

		return $items;
	}

	protected function getCustomerAddressByCarrier(string $code) {

		// chaque mode de livraison
		$code = ($this->_isShowAll && !$this->_isStoreLocator) ? $code.'_isShowAll' : $code;
		$shippingAddress = $this->getQuoteShippingAddress();

		$address = Mage::getModel('customer/address');
		$address->setData('country_id', $shippingAddress->getData('country_id'));

		if ($this->_isShowAll) {
			// autorise le changement de pays
			if (!empty($data = $this->_session->getData($code)))
				$address->addData($data);
		}
		else if (!empty($data = $this->_session->getData($code))) {
			// n'autorise pas le changement de pays
			if (empty($shippingAddress->getId()) || (!empty($data['country_id']) && ($data['country_id'] == $shippingAddress->getData('country_id'))))
				$address->addData($data);
		}

		if ($this->_isStoreLocator)
			return $address;

		// si l'adresse du mode de livraison est vide ou incomplète
		// alors on utilise les données de l'adresse de livraison du panier
		if (empty($address->getData('city')) || empty($address->getData('postcode')) || empty($address->getData('country_id'))) {
			$address->setData('country_id', $shippingAddress->getData('country_id'));
			$address->setData('postcode', $shippingAddress->getData('postcode'));
			$address->setData('city', $shippingAddress->getData('city'));
		}

		return $address;
	}

	protected function updateSessionObject(object $address, string $code, string $from) {

		$code = ($this->_isShowAll && !$this->_isStoreLocator) ? $code.'_isShowAll' : $code;

		$address->setData('saved_at', date('c'));
		$address->setData('saved_from', $from);

		$this->_session->setData($code, $address->getData());
	}

	protected function getQuoteShippingAddress() {

		// récupère l'adresse de livraison du panier
		$shippingAddress = $this->_session->getQuote()->getShippingAddress();
		$customerSession = Mage::getSingleton('customer/session');

		if (empty($shippingAddress->getData('postcode')) && empty($shippingAddress->getData('city')) && $customerSession->isLoggedIn()) {
			$defaultShipping = $customerSession->getCustomer()->getDefaultShippingAddress();
			if (is_object($defaultShipping)) {
				$shippingAddress->setData('country_id', $defaultShipping->getData('country_id'));
				$shippingAddress->setData('postcode', $defaultShipping->getData('postcode'));
				$shippingAddress->setData('city', $defaultShipping->getData('city'));
			}
		}

		if (empty($shippingAddress->getData('country_id')))
			$shippingAddress->setData('country_id', Mage::getStoreConfig('general/country/default'));

		return $shippingAddress;
	}


	public function preDispatch() {
		Mage::register('turpentine_nocache_flag', true, true);
		parent::preDispatch();
		$this->customDispatch();
	}

	public function indexAction() {

		$options = [];
		$code    = $this->getRequest()->getParam('code');
		$address = $this->getCustomerAddressByCarrier($code);

		// récupère le point de livraison
		// à partir de l'id de la commande
		if (is_numeric($code)) {

			$details  = Mage::getModel('shippingmax/details')->load($code);
			$items    = [];
			$isGeoloc = false;
			$selected = false;

			if (!empty($details->getId())) {
				$customerId = Mage::getSingleton('customer/session')->getCustomerId();
				if (($details->getData('customer_id') == $customerId) || Mage::app()->getStore()->isAdmin()) {
					$details = @json_decode($details->getData('details'), true);
					if (!empty($details)) {
						$selected = $details['id'];
						$items[$selected] = $details;
					}
				}
			}
		}
		// récupère la liste des points de livraison
		// et construit la liste des pays autorisés
		else {
			$items    = $this->getCarrierItemsByAddress($address, $code);
			$isGeoloc = !empty($address->getData('geoloc'));
			$selected = empty($address->getData('selected')) ? false : $address->getData('selected');
			$this->updateSessionObject($address, $code, 'indexAction');

			// tout les pays ou uniquement le pays de l'adresse
			// passe le pays sélectionné en premier
			$default     = Mage::getStoreConfig('general/country/default');
			$addrCountry = $address->getData('country_id');

			if ($this->_isShowAll) {
				foreach ($this->_carrierCountries as $country) {
					if ($country == $addrCountry) {
						$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
						break;
					}
				}
				foreach ($this->_carrierCountries as $country) {
					if ($country == $default) {
						$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
						break;
					}
				}
				foreach ($this->_carrierCountries as $country) {
					if ($country != $addrCountry)
						$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
				}
			}
			else {
				$country = (empty($addrCountry) || !in_array($addrCountry, $this->_carrierCountries)) ? $default : $addrCountry;
				$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
			}
		}

		// réponse
		if (is_numeric($code) && empty($items)) {
			$this->getResponse()
				->setHttpResponseCode(404)
				->setHeader('X-Shippingmax', 'order-not-found', true);
		}
		else {
			$this->loadLayout();
			$this->_initLayoutMessages(Mage::helper('shippingmax')->getSession(true));
			$this->getLayout()->getBlock('maproot')
				->setData('code', $code);
			$this->getLayout()->getBlock('mapbody')
				->setData('address', $address)
				->setData('code', $code)
				->setData('items', $items)
				->setData('options', $options)
				->setData('showAll', $this->_isShowAll);
			$this->getLayout()->getBlock('maplist')
				->setData('address', $address)
				->setData('code', $code)
				->setData('items', $items)
				->setData('selected', $selected)
				->setData('geoloc', $isGeoloc)
				->setData('showAll', $this->_isShowAll);
			$this->renderLayout();
		}
	}

	public function updateAction() {

		$request  = $this->getRequest();
		$code     = $request->getParam('code');
		$city     = $request->getPost('city');
		$postcode = $request->getPost('postcode');
		$country  = $request->getPost('country');
		$lat      = $request->getPost('lat');
		$lng      = $request->getPost('lng');
		$isGeoloc = !empty($request->getPost('geoloc')) && !empty($lat) && !empty($lng);

		if (($country == 'RU') && Mage::getStoreConfigFlag('carriers/shippingmax/search_by_street')) {
			// ici le code postal contient une rue, tout va bien
			$message = $this->__('Please enter your street (or your postal code) and city.');
		}
		else {
			$message = $this->__('Please enter your postal code and city.');
			if (is_numeric($postcode) && (mb_strlen($postcode) == 4) && in_array($country, Mage::helper('shippingmax')->getFranceDromCom()))
				$postcode = '0'.$postcode;
		}

		// action
		if ($this->_isShowAll || $this->_isStoreLocator || !empty($postcode) || !empty($city) || $isGeoloc) {

			// mémorise la recherche
			$address = $this->getCustomerAddressByCarrier($code);
			$address->setData('country_id', $country);
			if ($isGeoloc) {
				//$address->unsetData('postcode');
				//$address->unsetData('city');
				$address->setData('geoloc', 1);
				$address->setData('lat', (float) $lat);
				$address->setData('lng', (float) $lng);
			}
			else {
				$address->setData('postcode', $postcode);
				$address->setData('city', $city);
				$address->unsetData('geoloc');
				$address->unsetData('lat');
				$address->unsetData('lng');
			}
			$this->updateSessionObject($address, $code, 'updateAction');

			// réponse
			if ($this->isAjax()) {

				// récupère la liste des points de livraison
				$items = $this->getCarrierItemsByAddress($address, $code);

				$this->loadLayout();
				$this->_initLayoutMessages(Mage::helper('shippingmax')->getSession(true));
				$html = $this->getLayout()->getBlock('maplist')
					->setData('address', $address)
					->setData('code', $code)
					->setData('items', $items)
					->setData('selected', empty($address->getData('selected')) ? false : $address->getData('selected'))
					->setData('geoloc', !empty($address->getData('geoloc')))
					->setData('showAll', $this->_isShowAll)
					->toHtml();

				$this->getResponse()
					->setHttpResponseCode(200)
					->setHeader('Content-Type', 'application/json', true)
					->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
					->setBody(json_encode([
						'status'   => true,
						'country'  => $address->getData('country_id'),
						'postcode' => $address->getData('postcode'),
						'city'     => $address->getData('city'),
						'lat'      => (float) $address->getData('lat'), // doit être un nombre
						'lng'      => (float) $address->getData('lng'), // doit être un nombre
						'maplist'  => trim(preg_replace("#\s{2,}#", ' ', $html)), // pour le html
						'count'    => count($items), // pour debug
						'items'    => $items,        // pour le JavaScript
					]));
			}
			else {
				$this->_redirect('*/*/index', ['code' => $code, 'showall' => empty($request->getParam('showall')) ? null : 1]);
			}
		}
		else if ($this->isAjax()) {
			$this->getResponse()
				->setHttpResponseCode(200)
				->setHeader('Content-Type', 'application/json', true)
				->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
				->setBody(json_encode(['status' => false, 'error' => $message]));
		}
		else {
			$this->_session->addError($message);
			$this->_redirect('*/*/index', ['code' => $code, 'showall' => empty($request->getParam('showall')) ? null : 1]);
		}
	}

	public function saveAction() {

		$code = $this->getRequest()->getParam('code');
		$id   = $this->getRequest()->getPost('id');

		// showAll n'est pas acceptable ici
		// récupère la liste des points de livraison
		if (!empty($id)) {
			$address = $this->getCustomerAddressByCarrier($code);
			$items   = $this->getCarrierItemsByAddress($address, $code);
		}

		// action
		// vérifie que le point de livraison sélectionné existe bien
		if (!empty($id) && !empty($items[$id])) {

			// mémorise le choix
			$address->setData('selected', $id);
			$address->setData('item', $items[$id]);
			$this->updateSessionObject($address, $code, 'saveAction');

			// réponse
			if ($this->isAjax()) {

				$this->loadLayout();
				$this->_initLayoutMessages(Mage::helper('shippingmax')->getSession(true));
				$html = $this->getLayout()->getBlock('shippingmax_selected')
					->setData('code', $code)
					->toHtml();

				$this->getResponse()
					->setHttpResponseCode(200)
					->setHeader('Content-Type', 'application/json', true)
					->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
					->setBody(json_encode([
						'status' => true,
						'code'   => $code,
						'id'     => $id,
						'html'   => trim(preg_replace("#\s{2,}#", ' ', $html)),
					]));
			}
			else {
				$this->getResponse()
					->setHttpResponseCode(200)
					->setHeader('Content-Type', 'text/html; charset=utf-8', true)
					->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
					->setBody('<html lang="en"><head><title>shippingmax</title>'.
						'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.
						'<meta name="robots" content="noindex,nofollow"></head>'.
						'<body><script type="text/javascript">self.parent.shippingmax.show('.json_encode([
							'status' => true,
							'code'   => $code,
							'id'     => $id,
						]).')</script></body></html>'
					);
			}
		}
		else if ($this->isAjax()) {
			$this->getResponse()
				->setHttpResponseCode(200)
				->setHeader('Content-Type', 'application/json', true)
				->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
				->setBody(json_encode(['status' => false, 'error' => 'refresh']));
		}
		else {
			$this->_redirect('*/*/index', ['code' => $code]);
		}
	}


	public function debugAction() {

		$pass = Mage::getStoreConfig('carriers/shippingmax/debug_password');
		if (!Mage::getStoreConfigFlag('carriers/shippingmax/debug_enabled')) {
			$link = '';
			$text = 'disabled';
		}
		else if (!empty($pass) && ($this->getRequest()->getParam('pass') != $pass)) {
			$link = '';
			$text = 'invalid pass';
		}
		else {
			$coords = Mage::getModel('shippingmax/coords');
			$shippingAddress = $this->getQuoteShippingAddress();

			// commande
			if (!empty($oid = $this->getRequest()->getParam('oid')) || !empty($oid = Mage::getSingleton('checkout/session')->getLastOrderId())) {
				$details = Mage::getModel('shippingmax/details')->load($oid);
				if (!empty($details->getData('details'))) {
					$json = json_decode($details->getData('details'), true);
					if (!empty($json['street']))
						$json['street'] = str_replace("\n", ' \n ', $json['street']);
					$details->setData('details', $json);
				}
			}

			// données de session (liste des adresses utilisées)
			$ids = $this->_session->getData();
			ksort($ids);

			$session = [];
			foreach ($ids as $id => $value) {
				if (mb_stripos($id, 'shippingmax') !== false) {
					unset($value['item']['description']);
					$value['shippingmax'] = '<a href="'.Mage::helper('shippingmax')->getMapUrl($id).'">map</a>';
					if (isset($value['city'], $value['postcode'], $value['country_id'])) {
						$value['nominatim1'] = '<a href="'.$coords->getApiUrl($value['city'], $value['postcode'], $value['country_id']).'">search</a>';
					}
					if (isset($value['lat'], $value['lng'])) {
						$value['nominatim2'] = '<a href="'.$coords->getReverseApiUrl($value['lat'], $value['lng']).'">reverse</a>';
						$value['osm'] = '<a href="https://www.openstreetmap.org/?mlat='.$value['lat'].'&mlon='.$value['lng'].'">osm</a>';
					}
					if (!empty($value['item']['street'])) {
						$value['item']['street'] = str_replace("\n", ' \n ', $value['item']['street']);
					}
					$session[$id] = $value;
				}
			}

			// données du cache (liste des points de livraison)
			$app = Mage::app();
			$ids = $app->getCache()->getIds();

			$cache = [];
			foreach ($ids as $id) {
				if (strncasecmp($id, 'shippingmax', 11) === 0) {
					$value = $app->loadCache($id);
					$value = empty($value) ? $value : @unserialize($value, ['allowed_classes' => false]);
					$cache[$id] = count($value).' items';
				}
			}
			ksort($cache);

			// html
			$link  = ' - <a href="'.Mage::getUrl('*/*/debugsetaddress', ['pass' => $pass]).'">set addresses</a>';
			$link .= ' - <a href="'.Mage::getUrl('*/*/debugclearsession', ['pass' => $pass]).'"'.(empty($session) ? ' style="color:#666;"' : '').'>clear session</a>';
			$link .= ' - <a href="'.Mage::getUrl('*/*/debugclearcache', ['pass' => $pass]).'"'.(empty($cache) ? ' style="color:#666;"' : '').'>clear cache</a>';
			$text  =
				(empty($details) ? '' : '<b>pickup details (oid or lastOrderId):</b> '.print_r($details->getData(), true)."\n\n").
				'<b>shippingAdress:</b>'."\n".'id: '.$shippingAddress->getId()."\n".trim($shippingAddress->format('text'))."\n\n".
				'<b>session:keys:</b> '.print_r(array_keys($session), true).
				'<br><b>session:data:</b> '.print_r($session, true).
				'<br><b>cache:keys/count:</b> '.print_r($cache, true);
		}

		$name = Mage::app()->getStore()->isAdmin() ? 'backend - ' : 'frontend - ';
		$this->getResponse()
			->setHttpResponseCode(200)
			->setHeader('Content-Type', 'text/html; charset=utf-8', true)
			->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
			->setBody(
				'<html lang="en"><head><title>shippingmax</title>'.
				'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.
				'<meta name="robots" content="noindex,nofollow"></head>'.
				'<body><pre style="white-space:pre-wrap;">'.$name.date('c').$link.'<br><br>'.$text.'</pre></body></html>'
			);
	}

	public function debugclearcacheAction() {

		$pass = Mage::getStoreConfig('carriers/shippingmax/debug_password');
		if (Mage::getStoreConfigFlag('carriers/shippingmax/debug_enabled') && (empty($pass) || ($this->getRequest()->getParam('pass') == $pass))) {

			$app = Mage::app();
			$ids = $app->getCache()->getIds();
			foreach ($ids as $id) {
				if (strncasecmp($id, 'shippingmax', 11) === 0)
					$app->removeCache($id);
			}

			$this->_redirect('*/*/debug', ['pass' => $pass]);
		}
		else {
			$this->_redirect('*/*/debug');
		}
	}

	public function debugclearsessionAction() {

		$pass = Mage::getStoreConfig('carriers/shippingmax/debug_password');
		if (Mage::getStoreConfigFlag('carriers/shippingmax/debug_enabled') && (empty($pass) || ($this->getRequest()->getParam('pass') == $pass))) {

			$values = $this->_session->getData();
			foreach ($values as $key => $value) {
				if (mb_stripos($key, 'shippingmax') !== false)
					$this->_session->unsetData($key);
			}

			$this->_redirect('*/*/debug', ['pass' => $pass]);
		}
		else {
			$this->_redirect('*/*/debug');
		}
	}

	public function debugsetaddressAction() {

		$pass = Mage::getStoreConfig('carriers/shippingmax/debug_password');
		if (Mage::getStoreConfigFlag('carriers/shippingmax/debug_enabled') && (empty($pass) || ($this->getRequest()->getParam('pass') == $pass))) {

			$this->_session
				->setData('shippingmax_boxberry',       ['city' => 'Иркутск', 'postcode' => '664003', 'country_id' => 'RU'])
				->setData('shippingmax_boxberrycash',   ['city' => 'Иркутск', 'postcode' => '664003', 'country_id' => 'RU'])
				->setData('shippingmax_chronorelais',   ['city' => 'Saint-Étienne', 'postcode' => '42100', 'country_id' => 'FR'])
				->setData('shippingmax_colisprivpts',   ['city' => 'Saint-Étienne', 'postcode' => '42100', 'country_id' => 'FR'])
				->setData('shippingmax_dpdfrrelais',    ['city' => 'Voiron', 'postcode' => '38500', 'country_id' => 'FR'])
				->setData('shippingmax_fivepost',       ['city' => 'Челябинск', 'postcode' => '454000', 'country_id' => 'RU'])
				->setData('shippingmax_fivepostcash',   ['city' => 'Челябинск', 'postcode' => '454000', 'country_id' => 'RU'])
				->setData('shippingmax_inpospacit',     ['city' => 'Roma', 'postcode' => '00121', 'country_id' => 'IT'])
				->setData('shippingmax_inpospacuk',     ['city' => 'Kilmarnock',    'postcode' => 'KA1 2QA', 'country_id' => 'GB'])
				->setData('shippingmax_inpospaczk',     ['city' => 'Chełm',         'postcode' => '22-100', 'country_id' => 'PL'])
				->setData('shippingmax_mondialrelay',   ['city' => 'Saint-Étienne', 'postcode' => '42100', 'country_id' => 'FR'])
				->setData('shippingmax_pickpoint',      ['city' => 'Люберцы', 'postcode' => '140000', 'country_id' => 'RU'])
				->setData('shippingmax_pickpointcash',  ['city' => 'Люберцы', 'postcode' => '140000', 'country_id' => 'RU'])
				->setData('shippingmax_pocztk48Op',     ['city' => 'Wrocław', 'postcode' => '50-307', 'country_id' => 'PL'])
				->setData('shippingmax_przesodbpk',     ['city' => 'Náchod',  'postcode' => '547 01', 'country_id' => 'CZ'])
				->setData('shippingmax_przesodbpkcash', ['city' => 'Náchod',  'postcode' => '547 01', 'country_id' => 'CZ'])
				->setData('shippingmax_shiptor',        ['city' => 'Москва',  'postcode' => '127299', 'country_id' => 'RU'])
				->setData('shippingmax_shiptorcash',    ['city' => 'Москва',  'postcode' => '127299', 'country_id' => 'RU'])
				->setData('shippingmax_storepts',       ['city' => 'Aubenas', 'postcode' => '07200', 'country_id' => 'FR']);

			$this->_redirect('*/*/debug', ['pass' => $pass]);
		}
		else {
			$this->_redirect('*/*/debug');
		}
	}
}