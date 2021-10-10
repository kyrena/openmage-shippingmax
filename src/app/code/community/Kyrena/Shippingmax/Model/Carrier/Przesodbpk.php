<?php
/**
 * Created M/02/02/2021
 * Updated L/04/10/2021
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

class Kyrena_Shippingmax_Model_Carrier_Przesodbpk extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_przesodbpk';
	protected $_full = true;
	protected $_api  = true;
	protected $_fullCacheLifetime = 86400; // 24 heures en secondes

	public function loadItemsFromApi(object $address) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));

		$items   = [];
		$results = $this->runCurl($ch, true, 99);

		//echo '<pre>';print_r(array_slice($results['data'], 0, 20));exit;
		if (!empty($results['data']) && is_array($results['data'])) {

			foreach ($results['data'] as $result) {

				if (empty($result['id']) || empty($result['status']['statusId']) || ($result['status']['statusId'] != 1))
					continue;

				$items[$result['id']] = [
					'id'          => $result['id'],
					'lat'         => trim(str_replace(',', '.', $result['latitude']), '0'),
					'lng'         => trim(str_replace(',', '.', $result['longitude']), '0'),
					'name'        => $result['name'],
					'street'      => implode("\n", array_filter([$result['street'], $result['place']])),
					'postcode'    => $result['zip'],
					'city'        => $result['city'],
					'region'      => $result['region'] ?? null,
					'country_id'  => strtoupper($result['country']),
					'description' => $this->createDesc($result['openingHours']['regular']),
					//'max_weight'  => $result['maxWeight'] ?? null, // kg
					'cod'         => ($result['creditCardPayment'] == 'yes') && (stripos($result['name'], 'ZBOX') === false) && (stripos($result['name'], 'Z BOX') === false) && (stripos($result['name'], 'Z-BOX') === false) && (stripos($result['name'], 'ALZABOX') === false) && (stripos($result['name'], 'ALZA-BOX') === false) && (stripos($result['name'], 'ALZA BOX') === false),
				];
			}
		}

		return $items;
	}

	protected function createDesc($data) {

		$html = [];
		$days = [
			'1 Monday'    => is_array($data['monday'])    ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['monday'])),
			'2 Tuesday'   => is_array($data['tuesday'])   ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['tuesday'])),
			'3 Wednesday' => is_array($data['wednesday']) ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['wednesday'])),
			'4 Thursday'  => is_array($data['thursday'])  ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['thursday'])),
			'5 Friday'    => is_array($data['friday'])    ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['friday'])),
			'6 Saturday'  => is_array($data['saturday'])  ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['saturday'])),
			'7 Sunday'    => is_array($data['sunday'])    ? [] : explode('–', str_replace([':', ', '], ['', '–'], $data['sunday']))
		];

		// Array ( [1 Monday] => Array ( [0] => 0830 [1] => 1830 ) )
		// Array ( [1 Monday] => Array ( [0] => 0830 [1] => 1230, [2] => 1530 [3] => 1830 ) )
		$always = array_unique(array_values(array_map('implode', $days)));
		if ((count($always) == 1) && in_array($always[0], ['00002359', '00012359']))
			return '24/7';

		foreach ($days as $day => $str) {

			// fermé toute la journée
			if (empty($str)) {
				$html[] = $day.'#closed';
			}
			// ouvert non stop
			else if (count($str) == 2) {
				$html[] = $day.'#'.
					substr($str[0], 0, 2).'#'.substr($str[0], 2, 2).'#'.
					substr($str[1], 0, 2).'#'.substr($str[1], 2, 2);
			}
			// fermé à midi
			else {
				$html[] = $day.'#'.
					substr($str[0], 0, 2).'#'.substr($str[0], 2, 2).'#'.
					substr($str[1], 0, 2).'#'.substr($str[1], 2, 2).'#'.
					substr($str[2], 0, 2).'#'.substr($str[2], 2, 2).'#'.
					substr($str[3], 0, 2).'#'.substr($str[3], 2, 2);
			}
		}

		return implode("\n", $html);
	}
}