<?php
/**
 * Created V/12/04/2019
 * Updated J/30/09/2021
 *
 * Copyright 2019-2021 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2019-2021 | Jérôme Siau <jerome~cellublue~com>
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

	protected function loadItems(object $address, string $code, $session = null) {

		$this->_countries = Mage::helper('shippingmax')->getCarrierCountries($code);

		// récupère l'adresse mémorisée et met à jour l'adresse de livraison
		// mais s'assure que le pays ne change pas
		if (is_object($session)) {

			$data = $session->getData($code);

			// n'autorise pas le changement de pays
			if ($code != 'shippingmax_storelocator') {
				// pas de pays mémorisé
				if (empty($data['country_id']))
					$data = [];
				// pays mémorisé non autorisé
				else if (!in_array($data['country_id'], $this->_countries))
					$data = [];
				// pays mémorisé différent du pays de l'adresse (uniquement le pays de l'adresse est autorisé)
				// pas de vérification des pays en mode test (lorsque le mode de livraison est désactivé)
				else if (($data['country_id'] != $address->getData('country_id')) && in_array($address->getData('country_id'), $this->_countries) && Mage::getStoreConfigFlag('carriers/'.$code.'/active'))
					$data = [];
			}

			if (!empty($data['city']))
				$address->setData('city', $data['city']);
			if (!empty($data['postcode']))
				$address->setData('postcode', $data['postcode']);
			if (!empty($data['country_id']))
				$address->setData('country_id', $data['country_id']);
		}

		// récupère les points relais
		if (!empty($data['geoloc']) && !empty($data['lat']) && !empty($data['lng'])) {

			// n'autorise pas le changement de pays
			// donc cherche le pays par rapport aux coordonnées avant de mettre à jour la véritable adresse
			$check = clone $address;
			$check->setData('lat', $data['lat']);
			$check->setData('lng', $data['lng']);

			$dadata = Mage::helper('shippingmax')->withDaData($code);
			Mage::getModel('shippingmax/coords')->setReverseAddressCoords($check, $dadata);

			$address->setData('geo_city', $check->getData('city'));
			$address->setData('geo_postcode', $check->getData('postcode'));
			$address->setData('geo_country_id', $check->getData('country_id'));

			// n'autorise pas le changement de pays
			// sauf pour le storelocator si le pays géolocalisé est autorisé
			if (
				(($code == 'shippingmax_storelocator') && in_array($check->getData('country_id'), $this->_countries)) ||
				($check->getData('country_id') == $address->getData('country_id'))
			) {
				$address->setData('lat', $data['lat']);
				$address->setData('lng', $data['lng']);
				Mage::getModel('shippingmax/coords')->setReverseAddressCoords($address, $dadata);
				$items = Mage::getModel('shippingmax/carrier_'.lcfirst(str_replace('shippingmax_', '', $code)))->loadItemsFromCache($address);
			}
			else {
				$items = [];
			}
		}
		else {
			Mage::getModel('shippingmax/coords')->setAddressCoords($address);
			$items = Mage::getModel('shippingmax/carrier_'.lcfirst(str_replace('shippingmax_', '', $code)))->loadItemsFromCache($address);

			if (is_object($session) && !empty($address->getData('lat')) && !empty($address->getData('lng'))) {
				$data['lat'] = $address->getData('lat');
				$data['lng'] = $address->getData('lng');
				$data['country_id'] = $address->getData('country_id');
				$session->setData($code, $data);
			}
		}

		// admin
		if (!Mage::getStoreConfigFlag('carriers/'.$code.'/active') && Mage::app()->getStore()->isAdmin()) {
			Mage::app()->getStore()->setConfig('carriers/'.$code.'/active', 1);
			if (method_exists($address, 'collectShippingRates'))
				$address->setCollectShippingRates(true)->collectShippingRates(); //->getGroupedAllShippingRates();
		}

		return (!empty($items) && is_array($items)) ? $items : [];
	}

	protected function isAjax() {
		return ($this->getRequest()->isXmlHttpRequest() || !empty($this->getRequest()->getParam('isAjax')));
	}

	protected function initOurLayoutMessages() {
		$this->_initLayoutMessages(Mage::helper('shippingmax')->getSession(true));
	}

	protected function getSession() {
		return Mage::helper('shippingmax')->getSession();
	}

	protected function getShippingAddress() {

		$address = Mage::helper('shippingmax')->getSession()->getQuote()->getShippingAddress();
		$session = Mage::getSingleton('customer/session');

		if (empty($address->getData('postcode')) && empty($address->getData('city')) && $session->isLoggedIn()) {
			$defaultShipping = $session->getCustomer()->getDefaultShippingAddress();
			if (is_object($defaultShipping)) {
				$address->setData('city', $defaultShipping->getData('city'));
				$address->setData('postcode', $defaultShipping->getData('postcode'));
				$address->setData('country_id', $defaultShipping->getData('country_id'));
			}
		}

		if (empty($address->getData('country_id')))
			$address->setData('country_id', Mage::getStoreConfig('general/country/default'));

		return $address;
	}


	public function preDispatch() {

		parent::preDispatch();

		if (mb_stripos($this->getFullActionName(), 'debug') === false) {
			$code = $this->getRequest()->getParam('code');
			if (empty($code) || (mb_stripos($code, 'shippingmax_') === false) || empty(Mage::getStoreConfig('carriers/'.$code.'/title'))) {
				if (!is_numeric($code))
					$this->setFlag('', 'no-dispatch', true);
			}
		}
	}

	public function indexAction() {

		Mage::register('turpentine_nocache_flag', true, true);

		$session = $this->getSession();
		$address = $this->getShippingAddress();
		$code    = $this->getRequest()->getParam('code');
		$options = [];

		// récupère le point de livraison
		if (is_numeric($code)) {

			$items   = [];
			$details = Mage::getModel('shippingmax/details')->load($code);

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

			$geoloc = false;
			if (empty($selected))
				return;
		}
		// récupère la liste des points de livraison
		else if (Mage::getStoreConfigFlag('carriers/'.$code.'/can_show_all')) {
			$address  = new Varien_Object();
			$items    = $this->loadItems($address, $code);
			$geoloc   = false;
			$selected = false;
		}
		else {
			$items    = $this->loadItems($address, $code, $session);
			$selected = $session->getData($code);
			$geoloc   = !empty($selected['geoloc']);
			$selected = empty($selected['selected']) ? false : $selected['selected'];
		}

		// construit la liste des pays autorisés
		if (!is_numeric($code)) {

			$default = Mage::getStoreConfig('general/country/default');

			// tout les pays
			// le pays sélectionné toujours en premier
			if ($code == 'shippingmax_storelocator') {

				foreach ($this->_countries as $country) {
					if ($country == $address->getData('country_id'))
						$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
				}

				foreach ($this->_countries as $country) {
					if ($country == $default)
						$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
				}

				foreach ($this->_countries as $country) {
					if ($country != $address->getData('country_id'))
						$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
				}
			}
			// uniquement le pays de l'adresse
			else {
				$country = $address->getData('country_id');
				$country = (empty($country) || !in_array($country, $this->_countries)) ? $default : $country;

				$options[$country] = Mage::getModel('directory/country')->loadByCode($country)->getName();
			}
		}

		// rendu html
		$this->loadLayout();
		$this->initOurLayoutMessages();
		$this->getLayout()->getBlock('maproot')
			->setData('code', $code);
		$this->getLayout()->getBlock('mapbody')
			->setData('address', $address)
			->setData('code', $code)
			->setData('items', $items)
			->setData('options', $options);
		$this->getLayout()->getBlock('maplist')
			->setData('address', $address)
			->setData('code', $code)
			->setData('items', $items)
			->setData('selected', $selected)
			->setData('geoloc', $geoloc);
		$this->renderLayout();
	}

	public function updateAction() {

		Mage::register('turpentine_nocache_flag', true, true);

		$session  = $this->getSession();
		$address  = $this->getShippingAddress();
		$code     = $this->getRequest()->getParam('code');
		$city     = $this->getRequest()->getPost('city');
		$postcode = $this->getRequest()->getPost('postcode');
		$country  = $this->getRequest()->getPost('country');
		$lat      = $this->getRequest()->getPost('lat');
		$lng      = $this->getRequest()->getPost('lng');

		if (($country == 'RU') && Mage::getStoreConfigFlag('carriers/shippingmax/search_by_street')) {
			$message = $this->__('Please enter your street (or your postal code) and city.');
			// ici le code postal contient une rue, tout va bien
		}
		else {
			$message = $this->__('Please enter your postal code and city.');
			if (is_numeric($postcode) && (mb_strlen($postcode) == 4) && in_array($country, Mage::helper('shippingmax')->getFranceDromCom()))
				$postcode = '0'.$postcode;
		}

		// action
		if (!empty($city) || !empty($postcode) || (!empty($lat) && !empty($lng)) || Mage::getStoreConfigFlag('carriers/'.$code.'/can_show_all')) {

			// mémorise la recherche
			$data = $session->getData($code);
			if (!is_array($data)) $data = [];
			$data['city']       = $city;
			$data['postcode']   = $postcode;
			$data['country_id'] = $country;
			$data['geoloc']     = !empty($this->getRequest()->getPost('geoloc'));
			if (!empty($lat)) $data['lat'] = $lat;
			if (!empty($lng)) $data['lng'] = $lng;
			$session->setData($code, $data);

			// récupère la liste des points de livraison
			if (empty($postcode) && empty($city) && empty($lat) && empty($lng)) {
				$address->setData('lat', null);
				$address->setData('lng', null);
				$address->setData('city', null);
				$address->setData('postcode', null);
				$items = $this->loadItems($address, $code);
			}
			else {
				$items = $this->loadItems($address, $code, $session);
			}

			// réponse
			if ($this->isAjax()) {
				$this->loadLayout();
				$this->initOurLayoutMessages();
				$html = $this->getLayout()->getBlock('maplist')
					->setData('address', $address)
					->setData('code', $code)
					->setData('items', $items)
					->setData('selected', empty($data['selected']) ? false : $data['selected'])
					->setData('geoloc', $data['geoloc'])
					->toHtml();

				$this->getResponse()->setHeader('Content-Type', 'application/json', true);
				$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
				$this->getResponse()->setBody(json_encode([
					'status'   => true,
					'city'     => $address->getData('city'),
					'postcode' => $address->getData('postcode'),
					'country'  => $address->getData('country_id'),
					'lat'      => (float) $address->getData('lat'),
					'lng'      => (float) $address->getData('lng'),
					'maplist'  => trim(preg_replace("#\s{2,}#", ' ', $html)), // pour le html
					'items'    => $items // pour le JavaScript
				]));
			}
			else {
				$this->_redirect('*/*/index', ['code' => $code]);
			}
		}
		else if ($this->isAjax()) {
			$this->getResponse()->setHeader('Content-Type', 'application/json', true);
			$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
			$this->getResponse()->setBody(json_encode(['status' => false, 'error' => $message]));
		}
		else {
			$session->addError($message);
			$this->_redirect('*/*/index', ['code' => $code]);
		}
	}

	public function saveAction() {

		Mage::register('turpentine_nocache_flag', true, true);

		$session = $this->getSession();
		$address = $this->getShippingAddress();
		$code    = $this->getRequest()->getParam('code');
		$id      = $this->getRequest()->getPost('id');

		// récupère la liste des points de livraison
		$items = $this->loadItems($address, $code, $session);

		// action
		if (!empty($id) && !empty($items[$id])) {

			// mémorise le choix
			$data = $session->getData($code);
			if (!is_array($data)) $data = [];
			$data['selected'] = $id;
			$data['item']     = $items[$id];
			$session->setData($code, $data);

			// réponse
			$this->loadLayout();
			$this->initOurLayoutMessages();
			$html = trim(preg_replace("#\s{2,}#", ' ', $this->getLayout()->getBlock('shippingmax_selected')->setData('code', $code)->toHtml()));

			if ($this->isAjax()) {
				$this->getResponse()->setHeader('Content-Type', 'application/json', true);
				$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
				$this->getResponse()->setBody(json_encode([
					'status' => true,
					'code'   => $code,
					'id'     => $id,
					'html'   => $html
				]));
			}
			else {
				$this->getResponse()->setBody('<html lang="en"><body><script type="text/javascript">self.parent.shippingmax.show('.json_encode([
					'status' => true,
					'code'   => $code,
					'id'     => $id,
					'html'   => $html
				]).')</script></body></html>');
			}
		}
		else if ($this->isAjax()) {
			$this->getResponse()->setHeader('Content-Type', 'application/json', true);
			$this->getResponse()->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
			$this->getResponse()->setBody(json_encode(['status' => false, 'error' => 'refresh']));
		}
		else {
			$this->_redirect('*/*/index', ['code' => $code]);
		}
	}


	public function debugAction() {

		Mage::register('turpentine_nocache_flag', true, true);

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
			$ids = $this->getSession()->getData();
			ksort($ids);

			$session = [];
			$coords  = Mage::getModel('shippingmax/coords');
			foreach ($ids as $id => $value) {
				if (mb_stripos($id, 'shippingmax') !== false) {
					$value['shippingmax'] = '<a href="'.Mage::getUrl('*/*/index', ['code' => $id]).'">map</a>';
					if (isset($value['city'], $value['postcode'], $value['country_id'])) {
						$value['nominatim1'] = '<a href="'.$coords->getApiUrl($value['city'], $value['postcode'], $value['country_id']).'">search</a>';
					}
					if (isset($value['lat'], $value['lng'])) {
						$value['nominatim2'] = '<a href="'.$coords->getReverseApiUrl($value['lat'], $value['lng']).'">reverse</a>';
						$value['osm'] = '<a href="https://www.openstreetmap.org/?mlat='.$value['lat'].'&mlon='.$value['lng'].'">osm</a>';
					}
					$session[$id] = $value;
				}
			}

			$app = Mage::app();
			$ids = $app->getCache()->getIds();

			$cache = [];
			foreach ($ids as $id) {
				if (strncasecmp($id, 'shippingmax', 11) === 0) {
					$value = $app->loadCache($id);
					$value = empty($value) ? $value : @unserialize($value, ['allowed_classes' => false]);
					$cache[$id] = count($value);
				}
			}
			ksort($cache);

			$address = $this->getShippingAddress();
			$link =
				' - <a href="'.Mage::getUrl('*/*/debugclearcache', ['pass' => $pass]).'">clear cache</a>'.
				' - <a href="'.Mage::getUrl('*/*/debugclearsession', ['pass' => $pass]).'">clear session</a>'.
				' - <a href="'.Mage::getUrl('*/*/debugsetaddress', ['pass' => $pass]).'">set address</a>';
			$text =
				'<b>shippingAdress:</b>'."\n".trim($address->format('text'))."\n\n".
				'<b>session:keys:</b> '.print_r(array_keys($session), true).
				'<br><b>session:data:</b> '.print_r($session, true).
				'<br><b>cache:keys/count:</b> '.print_r($cache, true);
		}

		$this->getResponse()->setBody(
			'<html lang="en"><head><title>shippingmax</title>'.
			'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.
			'<meta name="robots" content="noindex,nofollow"></head><body><pre style="white-space:pre-wrap;">'.
			date('c').$link.'<br><br>'.$text.
			'</pre></body></html>');
	}

	public function debugclearcacheAction() {

		Mage::register('turpentine_nocache_flag', true, true);

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

		Mage::register('turpentine_nocache_flag', true, true);

		$pass = Mage::getStoreConfig('carriers/shippingmax/debug_password');
		if (Mage::getStoreConfigFlag('carriers/shippingmax/debug_enabled') && (empty($pass) || ($this->getRequest()->getParam('pass') == $pass))) {

			$session = $this->getSession();
			foreach ($session->getData() as $key => $value) {
				if (mb_stripos($key, 'shippingmax') !== false)
					$session->unsetData($key);
			}

			$this->_redirect('*/*/debug', ['pass' => $pass]);
		}
		else {
			$this->_redirect('*/*/debug');
		}
	}

	public function debugsetaddressAction() {

		Mage::register('turpentine_nocache_flag', true, true);

		$pass = Mage::getStoreConfig('carriers/shippingmax/debug_password');
		if (Mage::getStoreConfigFlag('carriers/shippingmax/debug_enabled') && (empty($pass) || ($this->getRequest()->getParam('pass') == $pass))) {

			$session = $this->getSession();
			$session->setData('shippingmax_boxberry',       ['city' => 'Иркутск', 'postcode' => '664003', 'country_id' => 'RU']);
			$session->setData('shippingmax_boxberrycash',   ['city' => 'Иркутск', 'postcode' => '664003', 'country_id' => 'RU']);
			$session->setData('shippingmax_chronorelais',   ['city' => 'Saint-Étienne', 'postcode' => '42100', 'country_id' => 'FR']);
			$session->setData('shippingmax_colisprivpts',   ['city' => 'Saint-Étienne', 'postcode' => '42100', 'country_id' => 'FR']);
			$session->setData('shippingmax_dpdfrrelais',    ['city' => 'Voiron', 'postcode' => '38500', 'country_id' => 'FR']);
			$session->setData('shippingmax_fivepost',       ['city' => 'Челябинск', 'postcode' => '454000', 'country_id' => 'RU']);
			$session->setData('shippingmax_fivepostcash',   ['city' => 'Челябинск', 'postcode' => '454000', 'country_id' => 'RU']);
			$session->setData('shippingmax_inpospacuk',     ['city' => 'Kilmarnock', 'postcode' => 'KA1 2QA', 'country_id' => 'GB']);
			$session->setData('shippingmax_inpospaczk',     ['city' => 'Chełm', 'postcode' => '22-100', 'country_id' => 'PL']);
			$session->setData('shippingmax_mondialrelay',   ['city' => 'Saint-Étienne', 'postcode' => '42100', 'country_id' => 'FR']);
			$session->setData('shippingmax_pickpoint',      ['city' => 'Люберцы', 'postcode' => '140000', 'country_id' => 'RU']);
			$session->setData('shippingmax_pickpointcash',  ['city' => 'Люберцы', 'postcode' => '140000', 'country_id' => 'RU']);
			$session->setData('shippingmax_pocztk48Op',     ['city' => 'Wrocław', 'postcode' => '50-307', 'country_id' => 'PL']);
			$session->setData('shippingmax_przesodbpk',     ['city' => 'Náchod', 'postcode' => '547 01', 'country_id' => 'CZ']);
			$session->setData('shippingmax_przesodbpkcash', ['city' => 'Náchod', 'postcode' => '547 01', 'country_id' => 'CZ']);
			$session->setData('shippingmax_shiptor',        ['city' => 'Москва', 'postcode' => '127299', 'country_id' => 'RU']);
			$session->setData('shippingmax_shiptorcash',    ['city' => 'Москва', 'postcode' => '127299', 'country_id' => 'RU']);
			$session->setData('shippingmax_storepts',       ['city' => 'Aubenas', 'postcode' => '07200', 'country_id' => 'FR']);

			$this->_redirect('*/*/debug', ['pass' => $pass]);
		}
		else {
			$this->_redirect('*/*/debug');
		}
	}
}