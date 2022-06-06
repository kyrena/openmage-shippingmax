<?php
/**
 * Created V/12/04/2019
 * Updated M/24/05/2022
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

class Kyrena_Shippingmax_Model_Observer {

	// EVENT controller_action_postdispatch_checkout_onepage_saveShippingMethod (frontend)
	public function updateShippingAddress(Varien_Event_Observer $observer) {

		$help = Mage::helper('shippingmax');
		Mage::unregister('_singleton/'.$help->getSession(true));

		// shippingmax_pocztk48Op_std = ok
		// shippingmax_pocztk48Op = truc_muche = ko
		$fullcode = $observer->getData('controller_action')->getRequest()->getPost('shipping_method');
		if (!$help->isSpecial($fullcode) || (substr_count($fullcode, '_') < 2))
			return;

		$session = $help->getSession();
		$address = $session->getQuote()->getShippingAddress();
		$source  = $session->getData('shippingmax_address_customer');
		$item    = $session->getData($help->getCarrierCode($fullcode));

		if (!empty($item) && !empty($item['item'])) {

			$item = new Varien_Object($item['item']);

			// mémorise l'adresse de livraison du client
			if (mb_stripos($address->getData('company'), "\0\0") === false) {
				$data = $address->getData();
				foreach ($data as $key => $value) {
					if (!in_array($key, ['company', 'street', 'postcode', 'city', 'region', 'region_id', 'country_id', 'customer_address_id', 'save_in_address_book']))
						unset($data[$key]);
				}
				$session->setData('shippingmax_address_customer', $data);
			}

			// modifie l'adresse de livraison du client
			// marque l'adresse du point relais avec deux caractères invisibles
			$region = $this->searchRegionId($item->getData('country_id'), $item->getData('postcode'), $item->getData('region'));

			$address->setData('company', $item->getData('name')."\0\0");
			$address->setStreet($item->getData('street'));
			$address->setData('postcode', $item->getData('postcode'));
			$address->setData('city', $item->getData('city'));
			$address->setData('region', empty($region) ? $item->getData('region') : null);
			$address->setData('region_id', empty($region) ? null : $region);
			$address->setData('country_id', $item->getData('country_id'));
			$address->setData('customer_address_id', 0);
			$address->setData('save_in_address_book', 0);

			$address->setShippingMethod($fullcode);
			$address->save(); // important
		}
		else if (!empty($source)) {

			$source = new Varien_Object($source);

			// restaure l'adresse de livraison du client
			$address->setData('company', $source->getData('company'));
			$address->setStreet($source->getData('street'));
			$address->setData('postcode', $source->getData('postcode'));
			$address->setData('city', $source->getData('city'));
			$address->setData('region', $source->getData('region'));
			$address->setData('region_id', $source->getData('region_id'));
			$address->setData('country_id', $source->getData('country_id'));
			$address->setData('customer_address_id', $source->getData('customer_address_id'));
			$address->setData('save_in_address_book', $source->getData('save_in_address_book'));

			$address->save(); // important
		}
	}

	// EVENT sales_convert_quote_address_to_order (global)
	public function convertShippingAddress(Varien_Event_Observer $observer) {

		$order    = $observer->getData('order');
		$address  = $observer->getData('address');
		$fullcode = $address->getShippingMethod();

		if (!empty($fullcode)) {
			$order->setData('orig_shipping_code', $fullcode); // pour les délais de livraison
			$help = Mage::helper('shippingmax');
			$code = $help->getCarrierCode($fullcode);
			$item = $help->getSession()->getData($code);
		}

		if (!empty($item) && !empty($item['item']) && ($address->getAddressType() == 'shipping')) {

			// modifie l'adresse de livraison du client
			$item   = new Varien_Object($item['item']);
			$region = $this->searchRegionId($item->getData('country_id'), $item->getData('postcode'), $item->getData('region'));

			$address->setData('company', $item->getData('name').' ('.$item->getData('id').')');
			$address->setStreet($item->getData('street'));
			$address->setData('postcode', $item->getData('postcode'));
			$address->setData('city', $item->getData('city'));
			$address->setData('region', empty($region) ? $item->getData('region') : null);
			$address->setData('region_id', empty($region) ? null : $region);
			$address->setData('country_id', $item->getData('country_id'));
			$address->setData('customer_address_id', 0);
			$address->setData('save_in_address_book', 0);

			if (mb_stripos($fullcode, '_'.$item->getData('id')) === false)
				$fullcode .= '_'.$item->getData('id');

			// modifie le code du mode de livraison (en cas de mix)
			$order->setOrigShippingMethod($fullcode);
			if (!empty($item->getData('carrier')))
				$fullcode = preg_replace('#^[^_]+_[^_]+#', $item->getData('carrier'), $fullcode);

			$address->setShippingMethod($fullcode);
			$order->setShippingMethod($fullcode);

			// s'assure qu'il y a une description
			$desc = $order->getData('shipping_description');
			if (empty($desc) && !empty($desc = Mage::getStoreConfig('carriers/'.$code.'/title', $order->getStoreId())))
				$order->setData('shipping_description', '!'.$desc);
		}
	}

	// EVENT sales_quote_address_save_before (global)
	public function updateShippingDescription(Varien_Event_Observer $observer) {

		$address = $observer->getData('quote_address');
		$method  = $address->getShippingMethod();

		if (!empty($method) && ($address->getAddressType() == 'shipping')) {

			$code = Mage::helper('shippingmax')->getCarrierCode($method);
			if (!empty($code)) {
				$desc = preg_replace('#\s{2,}#', ' ', $address->getData('shipping_description'));
				$desc = [trim(mb_substr($desc, 0, mb_stripos($desc, ' - '))), trim(mb_substr($desc, mb_stripos($desc, ' - ') + 3))];
				if (!empty($desc[1]) && ($desc[0] == $desc[1]))
					$address->setData('shipping_description', $desc[0]);
			}
		}
	}

	// EVENT sales_order_place_after (global)
	public function saveDetailsAndUpdateShippingDescription(Varien_Event_Observer $observer) {

		$help  = Mage::helper('shippingmax');
		$order = $observer->getData('order');

		if (!$order->getIsVirtual()) {

			$code = $help->getCarrierCode($order->getOrigShippingMethod() ?? $order->getShippingMethod());
			$item = $help->getSession()->getData($code);

			if (!empty($item) && !empty($item['item'])) {

				if (empty($item['item']['carrier']))
					$item['item']['carrier'] = $code;

				// mémorise les informations du point relais à vie
				$details = Mage::getModel('shippingmax/details');
				$details->setData('order_id', $order->getId());
				$details->setData('customer_id', $order->getData('customer_id'));
				$details->setData('details', json_encode($item['item']));
				$details->save();
			}

			if (!empty($code)) {

				// délais de livraison
				if (!empty($fullcode = $order->getData('orig_shipping_code')) && !empty($result = $help->getShippingDate($fullcode)))
					$order->setData('estimated_shipping_date', $result);

				// description
				$desc = preg_replace('#\s{2,}#', ' ', $order->getData('shipping_description'));
				$desc = [trim(mb_substr($desc, 0, mb_stripos($desc, ' - '))), trim(mb_substr($desc, mb_stripos($desc, ' - ') + 3))];
				if (!empty($desc[1]) && ($desc[0] == $desc[1]))
					$order->setData('shipping_description', $desc[0]);
			}
		}
	}

	// EVENT admin_system_config_changed_section_shippingmax_times (adminhtml)
	// EVENT admin_system_config_changed_section_carriers (adminhtml)
	public function clearConfig(Varien_Event_Observer $observer) {

		$database = Mage::getSingleton('core/resource');
		$writer   = $database->getConnection('core_write');
		$table    = $database->getTableName('core_config_data');

		$carriers = Mage::getModel('shipping/config')->getAllCarriers();
		foreach ($carriers as $code => $carrier) {

			if (Mage::getStoreConfigFlag('carriers/shippingmax/remove_'.$code)) {

				$writer->query('DELETE FROM '.$table.' WHERE path LIKE "carriers/'.$code.'/%" AND path NOT LIKE "carriers/'.$code.'/active"');
				$writer->query('DELETE FROM '.$table.' WHERE path LIKE "carriers/'.$code.'/active" AND scope_id != 0');

				Mage::getModel('core/config')->saveConfig('carriers/'.$code.'/active', '0');
			}
		}

		if (Mage::getStoreConfigFlag('shippingmax_times/general/enabled'))
			$writer->query('DELETE FROM '.$table.' WHERE path LIKE "shippingmax_times/general/remove"');
		else if (Mage::getStoreConfigFlag('shippingmax_times/general/remove'))
			$writer->query('DELETE FROM '.$table.' WHERE path LIKE "shippingmax_times/%" AND path NOT LIKE "shippingmax_times/general/%"');

		Mage::getConfig()->reinit(); // très important
	}

	// EVENT adminhtml_init_system_config (adminhtml)
	public function hideConfig(Varien_Event_Observer $observer) {

		$section = Mage::app()->getRequest()->getParam('section');
		if ($section == 'carriers') {
			$nodes    = $observer->getData('config')->getNode('sections/carriers/groups')->children();
			$carriers = Mage::getModel('shipping/config')->getAllCarriers();
			foreach ($carriers as $code => $carrier) {
				if (!empty($nodes->{$code}) && Mage::getStoreConfigFlag('carriers/shippingmax/remove_'.$code)) {
					$nodes->{$code}->show_in_default = 0;
					$nodes->{$code}->show_in_website = 0;
					$nodes->{$code}->show_in_store = 0;
				}
			}
		}
	}

	// CRON shippingmax_update_files
	public function updateFullFiles($cron = null) {

		$app = Mage::app();
		$msg = [];

		$help     = Mage::helper('shippingmax');
		$storeIds = Mage::getResourceModel('core/store_collection')->getAllIds();
		$carriers = Mage::getModel('shipping/config')->getAllCarriers();
		$address  = new Varien_Object(['lat' => -1, 'lng' => -1]);
		$hasError = false;

		foreach ($carriers as $code => $carrier) {

			if (method_exists($carrier, 'isFull') && ($carrier->isFull() === true)) {

				try {
					foreach ($storeIds as $storeId) {

						if ($code == 'shippingmax_storelocator')
							$active = Mage::getStoreConfigFlag('carriers/'.$code.'/api_url', $storeId);
						else
							$active = Mage::getStoreConfigFlag('carriers/'.$help->getEnabledCarrierCode($code, $storeId).'/active', $storeId);

						if (!$active)
							continue;

						$cache = $carrier->getCacheFile();
						$life  = $carrier->getFullCacheLifetime() - 600; // 10 minutes en secondes

						if (!is_file($cache) || (filemtime($cache) < (time() - $life))) {

							$dir = dirname($cache);
							if (!is_dir($dir))
								mkdir($dir, 0755);

							$start = microtime(true);
							$items = $carrier->loadItemsFromApi($address);
							$time  = microtime(true) - $start;

							// sauvegarde dans le cache fichier et dans le cache openmage
							// voir aussi Kyrena_Shippingmax_Model_Carrier::loadItemsFromCache
							if (!empty($items) && is_array($items)) {

								// met à jour le fichier et le cache
								file_put_contents($cache, serialize($items));
								$msg[] = '- '.$code.': '.floor($time).' seconds, '.floor(filesize($cache) / 1024).' k downloaded at '.floor(filesize($cache) / 1024 / $time).' k/s, '.count($items).' items';

								if ($app->useCache('shippingmax_places'))
									$app->saveCache(serialize($items), $code, ['SHIPPINGMAX_PLACES'], $carrier->getFullCacheLifetime());

								// supprime les résultats en cache
								$ids = $app->getCache()->getIds();
								foreach ($ids as $id) {
									if (mb_stripos($id, $code) === 0)
										$app->removeCache($id);
								}
							}
							else {
								$msg[] = '- '.$code.': '.floor($time).' seconds, ERROR, read your '.Mage::getStoreConfig('dev/log/exception_file').' or check your API credentials';
							}
						}
						else {
							$msg[] = '- '.$code.': already up to date';
						}

						if (is_object($cron))
							$cron->setData('messages', implode("\n", $msg))->save();

						// global
						continue 2;
					}

					$msg[] = '- '.$code.': disabled';
				}
				catch (Throwable $t) {
					$hasError = true;
					Mage::logException($t);
					$msg[] = '- '.$code.': '.$t->getMessage();
				}
			}
		}

		if (is_object($cron)) {
			$cron->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n".implode("\n", $msg));
			if ($hasError) // pour le statut du cron
				Mage::throwException('At least one error occurred while downloading files.'."\n\n".$cron->getData('messages')."\n\n");
		}

		return $msg;
	}


	// recherche l'id de la région
	// recherche uniquement si la configuration indique que l'état est obligatoire
	public function searchRegionId(string $country, $postcode = null, $name = null) {

		$required = Mage::helper('directory')->getCountriesWithStatesRequired();
		$required = is_array($required) ? $required : [];

		if (in_array($country, $required)) {

			if (!empty($postcode)) {

				$regions = Mage::getResourceModel('directory/region_collection')->addCountryFilter($country);
				$code    = mb_substr($postcode, 0, 2);

				if (($country == 'FR') && ($code == 20))
					$code = ($postcode >= 20200) ? '2B' : '2A';

				foreach ($regions as $region) {
					if ($region->getData('code') == $code)
						return $region->getData('region_id');
				}
			}

			if (!empty($name)) {

				$regions = Mage::getResourceModel('directory/region_collection')->addCountryFilter($country)->addRegionCodeOrNameFilter($name);
				foreach ($regions as $region)
					return $region->getData('region_id');
			}
		}

		return null;
	}
}