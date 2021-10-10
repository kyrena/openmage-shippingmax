<?php
/**
 * Created L/19/07/2021
 * Updated M/14/09/2021
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

class Kyrena_Shippingmax_Model_Carrier_Boxberryhome extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_boxberryhome';
	protected $_full = true;
	protected $_api  = true;
	protected $_postcodesOnly = true;
	protected $_fullCacheLifetime = 86400; // 24 heure en secondes

	public function loadItemsFromApi(object $address) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));

		$items   = [];
		$results = $this->runCurl($ch, true);

		//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
		if (!empty($results) && is_array($results)) {
			foreach ($results as $result)
				$items[$result['Zip']] = $result['Zip'];
		}

		return $items;
	}

	protected function checkIfAvailable(object $request) {

		// on s'assure que le code postal est autorisé
		$address = new Varien_Object();
		foreach ($request->getData() as $key => $value) {
			if (strncasecmp($key, 'dest_', 5) === 0)
				$address->setData(str_replace('dest_', '', $key), trim($value));
		}

		$items = $this->loadItemsFromCache($address);
		if (!in_array(trim($request->getData('dest_postcode')), $items))
			return false;

		return parent::checkIfAvailable($request);
	}
}