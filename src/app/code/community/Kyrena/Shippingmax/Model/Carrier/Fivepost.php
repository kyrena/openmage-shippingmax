<?php
/**
 * Created J/08/07/2021
 * Updated L/04/10/2021
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

class Kyrena_Shippingmax_Model_Carrier_Fivepost extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_fivepost';
	protected $_full = true;
	protected $_api  = true;
	protected $_fullCacheLifetime = 86400; // 24 heures en secondes

	public function loadItemsFromApi(object $address) {

		$page  = 0;
		$pages = 0;
		$items = [];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_URL, trim($this->getConfigData('api_url'), '/').'/jwt-generate-claims/rs256/1?apikey='.$this->getConfigData('api_password', true));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/x-www-form-urlencoded'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'subject=OpenAPI&audience=A122019!');
		$results = $this->runCurl($ch, true);

		if (empty($results['jwt']))
			return $items;

		$jwt = $results['jwt'];
		do {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_URL, trim($this->getConfigData('api_url'), '/').'/api/v1/pickuppoints/query').
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
				'Content-Type: application/json; charset="utf-8"',
				'Authorization: "Bearer '.$jwt.'"'
			]);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				'pageSize'   => 1000,
				'pageNumber' => $page
			]));

			$results = $this->runCurl($ch, true);
			$mapping = ['Россия' => 'RU'];

			//echo '<pre>';print_r(array_slice($results['content'], 0, 20));exit;
			if (!empty($results['content']) && is_array($results['content'])) {

				$pages = $results['totalPages'];

				foreach ($results['content'] as $result) {

					if (empty($result['id']))
						continue;

					$country = $result['address']['country'];
					if (!array_key_exists($country, $mapping)) {
						Mage::log('Pickpoint: unknown country code: '.$country, Zend_Log::ERR);
						continue;
					}

					$result['id'] = (string) substr(md5($result['id']), 0, 10); // (yes)
					$items[$result['id']] = [
						'id'          => $result['id'],
						'lat'         => trim(str_replace(',', '.', $result['address']['lat']), '0'),
						'lng'         => trim(str_replace(',', '.', $result['address']['lng']), '0'),
						'name'        => $result['name'],
						'street'      => '@todo',
						'postcode'    => $result['address']['zipCode'],
						'city'        => $result['address']['city'],
						'country_id'  => $mapping[$country],
						'description' => implode("\n", array_filter([
							$result['additional'],
							//$this->createDesc($result['workHours'])
						])),
						//'max_weight'  => $result['cellLimits']['maxWeight'] / 1000, // g
						'cod'         => !empty($result['cashAllowed']) || !empty($result['cardAllowed']),
					];
				}
			}
		}
		while (++$page < $pages);

		return $items;
	}
}