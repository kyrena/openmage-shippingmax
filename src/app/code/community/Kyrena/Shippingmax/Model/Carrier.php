<?php
/**
 * Created V/12/04/2019
 * Updated V/24/06/2022
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

abstract class Kyrena_Shippingmax_Model_Carrier extends Owebia_Shipping2_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

	protected $_dstSearch = 30;
	protected $_maxPoints = 50;             // map.phtml self.maxpts
	protected $_cacheLifetime = 3600;       // 1 heure en secondes
	protected $_fullCacheLifetime = 432000; // 5 jours en secondes
	protected $_postcodesOnly = false;

	// openmage
	public function getTrackingInfo($trackingNumber, $order = null) {

		// Kyrena_Shippingmax_Model_Rewrite_Track
		$storeId  = is_object($order) ? $order->getStoreId() : null;
		$postcode = is_object($order) ? $order->getShippingAddress()->getData('postcode') : '';

		$status = Mage::getModel('shipping/tracking_result_status');
		$status->setCarrier($this->_code);
		$status->setCarrierTitle($this->getConfigData('title'));
		$status->setTracking($trackingNumber);
		$status->setData('popup', true);
		$status->setData('url', str_replace(['{{num}}', '{{postcode}}'], [$trackingNumber, $postcode],
			Mage::getStoreConfig('carriers/'.$this->_code.'/tracking', $storeId)));

		return $status;
	}

	public function checkAvailableShipCountries(Mage_Shipping_Model_Rate_Request $request) {

		$result = parent::checkAvailableShipCountries($request);

		if (is_object($result) && (get_class($result) == get_class(Mage::getModel('shipping/rate_result_error'))))
			return $result;

		if (is_bool($result) && !$result)
			return false;

		if (!$this->checkIfAvailable($request))
			return false;

		if (!$this->checkMaxAmount($request))
			return false;

		if (!$this->checkMaxWeight($request))
			return false;

		return $this;
	}

	public function getConfigData($key, $decrypt = false, $default = null) {

		if ($decrypt) {
			$value = parent::getConfigData($key);
			return empty($value) ? $default : Mage::helper('core')->decrypt($value);
		}

		$value = parent::getConfigData($key);
		if (empty($value) && ($key == 'dst_search'))
			return $this->_dstSearch;
		if (empty($value) && ($key == 'max_points'))
			return $this->_maxPoints;

		return empty($value) ? $default : $value;
	}

	// kyrena
	public function isFull() {
		return $this->_full;
	}

	public function getCacheFile() {
		return Mage::getBaseDir('var').'/shippingmax/'.str_replace('shippingmax_', '', $this->_code).'.dat';
	}

	public function getFullCacheLifetime() {
		return $this->_fullCacheLifetime;
	}

	public function loadItemsFromCache(object $address, bool $dataOnly = false) {

		if (empty($address->getData('postcode')) && !$this->getConfigFlag('can_show_all'))
			return [];

		$app = Mage::app();
		$str = mb_strtolower(trim($address->getData('postcode').' '.$address->getData('city').', '.$address->getData('country_id')));
		if (mb_strlen($str) < 6) // géolocalisation
			$str = trim(round($address->getData('lat'), 6).'/'.round($address->getData('lng'), 6));

		$skey = $this->_code.'_'.md5($str); // clef pour le cache des résultats de la recherche actuelle
		if ($this->_postcodesOnly)
			$skey = $this->_code;

		$cache = $this->getCacheFile();
		$items = [];

		// RETOURNE LES RÉSULTATS DEPUIS LE CACHE (1h)
		// tout est déjà filtré et trié
		if ($app->useCache('shippingmax_places')) {

			$items = $app->loadCache($skey);
			$items = empty($items) ? null : @unserialize($items, ['allowed_classes' => false]);

			if (!empty($items) && is_array($items))
				return $items;
		}

		// CHARGE LES DONNÉES DEPUIS LE FICHIER
		// s'il y a un fichier avec tous les points relais
		if ($this->_full) {

			// charge les données depuis le cache openmage
			if ($app->useCache('shippingmax_places')) {

				$items = $app->loadCache($this->_code);
				$items = empty($items) ? null : @unserialize($items, ['allowed_classes' => false]);
			}

			// charge les données depuis le cache fichier s'il n'a pas expiré
			// puis sauvegarde dans le cache openmage
			if ((empty($items) || !is_array($items)) && is_file($cache) && (filemtime($cache) > (time() - $this->_fullCacheLifetime))) {

				$items = file_get_contents($cache);
				$items = empty($items) ? null : @unserialize($items, ['allowed_classes' => false]);

				if (!empty($items) && is_array($items) && $app->useCache('shippingmax_places'))
					$app->saveCache(serialize($items), $this->_code, ['SHIPPINGMAX_PLACES'], $this->_fullCacheLifetime);
			}
		}

		// CHARGE LES DONNÉES DEPUIS INTERNET
		// s'il fait charger les points relais depuis internet
		if (empty($items) || !is_array($items)) {

			try {
				$items = $this->loadItemsFromApi($address);

				if (!empty($items) && is_array($items)) {

					array_walk_recursive($items, static function (&$item) {
						$item = trim(Mage::helper('shippingmax')->escapeEntities(strip_tags($item)));
					});

					// sauvegarde dans le cache fichier et dans le cache openmage
					// voir aussi Kyrena_Shippingmax_Model_Observer::updateFullFiles
					if ($this->_full) {

						$dir = dirname($cache);
						if (!is_dir($dir))
							mkdir($dir, 0755);

						// met à jour le fichier et le cache
						file_put_contents($cache, serialize($items));
						if ($app->useCache('shippingmax_places'))
							$app->saveCache(serialize($items), $this->_code, ['SHIPPINGMAX_PLACES'], $this->_fullCacheLifetime);

						// supprime les résultats en cache
						$ids = $app->getCache()->getIds();
						foreach ($ids as $id) {
							if (mb_stripos($id, $this->_code) === 0)
								$app->removeCache($id);
						}
					}
				}
			}
			catch (Throwable $t) {
				Mage::logException($t);
				$error = $t;
			}
		}

		// en cas de mix, recommence et fusionne
		// par exemple shippingmax_mondialrelay et shippingmax_colisprivpts
		$mixmaps = Mage::getConfig()->getNode('global/shippingmax/mixmaps')->asArray();
		if (array_key_exists($this->_code, $mixmaps)) {

			foreach (array_keys($mixmaps[$this->_code]) as $mixmap) {

				$split = explode('_', $mixmap);
				if ($this->getConfigFlag('mix_'.$split[1])) {

					try {
						$subitems = Mage::getSingleton($split[0].'/carrier_'.$split[1])->loadItemsFromCache($address, true);

						if (!empty($subitems) && is_array($subitems)) {
							// marque
							foreach ($subitems as $subkey => $subitem) {
								$subitems[$subkey]['carrier'] = $mixmap;
							}
							// remplace
							foreach ($items as $key => $item) {
								foreach ($subitems as $subkey => $subitem) {
									if (
										($item['name'] == $subitem['name']) &&
										($item['postcode'] == $subitem['postcode']) &&
										($item['country_id'] == $subitem['country_id'])
									) {
										$items[$subkey] = $subitem;
										unset($items[$key], $subitems[$subkey]);
										continue 2;
									}
								}
							}
							// ajoute
							$items = empty($items) ? $subitems : $items + $subitems;
						}
					}
					catch (Throwable $t) {
						Mage::logException($t);
						$error = $t;
					}
				}
			}
		}

		// PRÉPARE LES RÉSULTATS
		// filtre les points relais
		if (!empty($items) && !$this->_postcodesOnly) {
			foreach ($items as $key => &$item) {
				if (empty($item['id']) || !$this->checkItem($address, $item))
					unset($items[$key]);
			}
			unset($item);
		}

		// en cas de mix
		if ($dataOnly)
			return $items;

		// TRIE ET MÉMORISE LES RÉSULTATS (1h)
		// mémorise uniquement s'il n'y a pas eu d'erreur
		if (!empty($items) && is_array($items)) {

			if ((!empty($items[array_key_first($items)]['dst']) || !empty($items[array_key_last($items)]['dst'])) && !empty($address->getData('lat').$address->getData('lng'))) {
				uasort($items, static function ($a, $b) {
					if (empty($a['dst']) || empty($b['dst']))
						return 1;
					return ($a['dst'] == $b['dst']) ? 0 : (($a['dst'] < $b['dst']) ? -1 : 1);
				});
			}

			$maxPoints = $this->getConfigData('max_points');
			if (!$this->_postcodesOnly && ($this->_code != 'shippingmax_storelocator') && (count($items) > $maxPoints))
				$items = array_slice($items, 0, max(10, $maxPoints), true);

			// sauvegarde dans le cache openmage
			if (!isset($error) && $app->useCache('shippingmax_places'))
				$app->saveCache(serialize($items), $skey, ['SHIPPINGMAX_PLACES'], $this->_cacheLifetime);

			return $items;
		}

		return [];
	}

	protected function runCurl($ch, bool $json, int $timeout = 20) {

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_REFERER, Mage::getBaseUrl());

		$result = curl_exec($ch);
		$result = (($result === false) || (curl_errno($ch) !== 0)) ? trim('CURL_ERROR '.curl_errno($ch).' '.curl_error($ch)) : $result;
		curl_close($ch);

		if (stripos($result, 'CURL_ERROR') !== false)
			Mage::throwException($this->_code.' '.$result);

		if ($json)
			$result = @json_decode(trim($result), true);

		return $result;
	}

	protected function checkIfAvailable(object $request) {
		return true;
	}

	protected function checkItem(object $address, &$item) {
		return $this->checkDistance($address, $item);
	}

	protected function checkDistance(object $address, &$item) {

		$lat1 = $address->getData('lat');
		$lon1 = $address->getData('lng');
		$lat2 = $item['lat'];
		$lon2 = $item['lng'];
		$unit = 'K';

		if (empty($item['dst'])) {

			if (($lat1 == $lat2) && ($lon1 == $lon2)) {
				$dst = (($lat1 == 0) || ($lon1 == 0)) ? 9999 : 0;
			}
			else if (is_numeric($lon1) && is_numeric($lon2)) {
				$theta = $lon1 - $lon2;
				$dist  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
				$dist  = acos($dist);
				$dist  = rad2deg($dist);
				$miles = $dist * 60 * 1.1515;
				$unit  = strtoupper($unit);
				if ($unit == 'K')
					$dst = $miles * 1.609344;
				else if ($unit == 'N')
					$dst = $miles * 0.8684;
				else
					$dst = $miles;
			}
			else {
				$dst = 9999;
			}

			$item['dst'] = round($dst, 1);
		}

		return $item['dst'] <= $this->getConfigData('dst_search');
	}

	protected function checkMaxAmount(object $request) {

		$maxAmounts = $this->getConfigData('max_amounts');
		$key = strtolower($request->getData('dest_country_id'));

		if (!empty($maxAmounts) && !empty($maxAmounts[$key]['amount'])) {

			$maxAmount = $maxAmounts[$key]['amount'];
			try {
				$pkgAmount = is_object($request->getPackageCurrency()) ? $request->getPackageCurrency()->convert($request->getPackageValue(), $maxAmounts[$key]['currency']) : $request->getPackageValue();
				//Mage::log(getenv('REQUEST_URI').' '.$this->_code.' ('.$request->getPackageValue().' '.(is_object($request->getPackageCurrency()) ? $request->getPackageCurrency()->getCode() : '').') '.$pkgAmount.' > '.$maxAmount.' ('.$maxAmounts[$key]['amount'].' '.$maxAmounts[$key]['currency'].')', Zend_Log::DEBUG);
			}
			catch (Throwable $t) {
				//Mage::logException($t); // RuntimeException: Undefined rate from "EUR-BGN".
				$pkgAmount = $request->getPackageValue();
			}

			if ($pkgAmount > $maxAmount)
				return false;
		}

		return true;
	}

	protected function checkMaxWeight(object $request) {

		$maxWeight = (float) $this->getConfigData('max_weight'); // kg
		$maxWeight = ($maxWeight > 0) ? $maxWeight : 30;
		$pkgWeight = (float) $request->getPackageWeight();

		if (Mage::getStoreConfig('owebia_shipping2/general/weight_unit') == 'g')
			$pkgWeight /= 1000;

		//Mage::log(getenv('REQUEST_URI').' '.$this->_code.' ('.$request->getPackageWeight().')', Zend_Log::DEBUG);
		if ($pkgWeight > $maxWeight)
			return false;

		return true;
	}

	// owebia
	protected function __appendMethod(&$process, $row, $fees) {

		if ($this->_api) {
			$help = Mage::helper('owebia_shipping2');
			$process['result']->append(Mage::getModel('shipping/rate_result_method')
				->setCarrier($this->_code)
				->setCarrierTitle($this->__getConfigData('title'))
				->setMethod($row['*id'])
				->setMethodTitle($help->getMethodText($this->getParser(), $process, $row, 'label'))
				->setMethodDescription($help->getMethodText($this->getParser(), $process, $row, 'description'))
				->setPrice($fees)
				->setCost($fees));
		}
		else {
			parent::__appendMethod($process, $row, $fees);
		}
	}

	protected function __getConfigData($key) {

		if (in_array($key, ['active', 'config', 'debug', 'stop_to_first_match', 'title'])) {

			if ($key == 'config')
				$key = 'owebia_config';
			else if ($key == 'debug')
				$key = 'owebia_debug';
			else if ($key == 'stop_to_first_match')
				$key = 'owebia_stopfirst';

			return $this->getConfigData($key);
		}

		return Mage::getStoreConfig('carriers/owebiashipping1/'.$key, $this->getStore());
	}
}

// https://www.php.net/array-key-last (PHP 7.3.0+)
if (!function_exists('array_key_last')) {
	function array_key_last($array) {
		if (!is_array($array) || empty($array))
			return null;
		return array_keys($array)[count($array)-1];
	}
}

// https://www.php.net/array-key-first (PHP 7.3.0+)
if (!function_exists('array_key_first')) {
	function array_key_first(array $arr) {
		foreach ($arr as $key => $unused)
			return $key;
		return null;
	}
}