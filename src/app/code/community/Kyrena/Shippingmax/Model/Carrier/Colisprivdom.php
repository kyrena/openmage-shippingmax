<?php
/**
 * Created V/06/11/2020
 * Updated L/02/01/2023
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

class Kyrena_Shippingmax_Model_Carrier_Colisprivdom extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_colisprivdom';
	protected $_full = true;
	protected $_api  = true;
	protected $_zipOnly = true;
	protected $_fullCacheLifetime = 3600; // 1 heure en secondes

	public function loadItemsFromApi(object $address) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));
		curl_setopt($ch, CURLOPT_USERPWD, $this->getConfigData('api_username').':'.$this->getConfigData('api_password', true));

		$items   = [];
		$results = explode("\n", $this->runCurl($ch, false));

		//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
		if (!empty($results) && is_array($results)) {

			// 95870DH159514OOF
			// Code postal sur 5 = 95870
			// Société de distribution sur 2 = DH (Distrihome) valeur fixe
			// N° de centre (agence) sur 2 = 15
			// N° de tournée sur 4 = 9514
			// Code postal desservi oui/non = O (O pour oui donc étiquette Colis Privé et F pour non/fermé donc étiquette Colissimo)
			// Indicateur CRT ou contre remboursement (ouvert/fermé aux CRT) = O (O pour oui et F pour non/fermé aux CRT)
			// Zone non utilisée = toujours à F
			foreach ($results as $result) {
				$result = trim($result);
				if ((mb_strlen($result) > 13) && ($result[13] == 'O')) {
					$result = mb_substr($result, 0, 5);
					$items[$result] = $result;
				}
			}
		}

		return $items;
	}

	protected function checkIfAvailable(object $request) {

		if (empty($request->getData('dest_postcode')))
			return true;

		// on s'assure que le code postal est autorisé
		$address = new Varien_Object();
		foreach ($request->getData() as $key => $value) {
			if (strncasecmp($key, 'dest_', 5) === 0)
				$address->setData(str_replace('dest_', '', $key), empty($value) ? '' : trim($value));
		}

		$items = $this->loadItemsFromCache($address);
		if (!in_array(trim($request->getData('dest_postcode')), $items))
			return false;

		return parent::checkIfAvailable($request);
	}
}