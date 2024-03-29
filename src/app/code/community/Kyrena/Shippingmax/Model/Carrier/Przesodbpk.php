<?php
/**
 * Created M/02/02/2021
 * Updated V/02/06/2023
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

class Kyrena_Shippingmax_Model_Carrier_Przesodbpk extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_przesodbpk';
	protected $_full = true;
	protected $_api  = true;
	protected $_fullCacheLifetime = 86400;  // 24 heures en secondes
	protected $_fullCacheHourForUpdate = 3; // UTC hour

	public function loadItemsFromApi(object $address) {

		// https://stackoverflow.com/a/33035088/2980105
		$limit = (int) str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
		if ($limit < 1073741824)
			ini_set('memory_limit', '1G');

		$ch  = curl_init();
		$url = $this->getConfigData('api_url');
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // v5

		$items   = [];
		$results = $this->runCurl($ch);

		//echo '<pre>';print_r(array_slice($results['data'], 0, 20));exit;
		// https://docs.packetery.com/01-pickup-point-selection/04-branch-export-v4.html
		if (!empty($results['data']) && is_array($results['data'])) {

			foreach ($results['data'] as $result) {

				if (empty($result['id']) || empty($result['status']['statusId']) || ($result['status']['statusId'] != 1))
					continue;

				$items[$result['id']] = [
					'id'          => $result['id'],
					'lat'         => (float) str_replace(',', '.', $result['latitude']),
					'lng'         => (float) str_replace(',', '.', $result['longitude']),
					'name'        => $result['name'],
					'street'      => implode("\n", array_filter([$result['street'], $result['place']])),
					'postcode'    => $result['zip'],
					'city'        => $result['city'],
					'region'      => $result['region'] ?? null,
					'country_id'  => strtoupper($result['country']),
					'description' => $this->getDescription($result['openingHours']['regular']),
					//'max_weight'  => $result['maxWeight'] ?? null, // kg
					'cod'         => ($result['creditCardPayment'] == 'yes') && (stripos($result['name'], 'ZBOX') === false) && (stripos($result['name'], 'Z BOX') === false) && (stripos($result['name'], 'Z-BOX') === false) && (stripos($result['name'], 'ALZABOX') === false) && (stripos($result['name'], 'ALZA-BOX') === false) && (stripos($result['name'], 'ALZA BOX') === false),
				];
			}
		}
		//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
		// https://docs.packetery.com/01-pickup-point-selection/05-branch-export-v5.html
		// The feed branch.json contains only pickup points / The boxes is available in the separated box.json feed.
		else if (!empty($results) && is_array($results)) {

			usleep(10000); // 0.1s
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, str_replace('branch', 'box', $url));
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // v5
			$boxes = $this->runCurl($ch);
			if (!empty($boxes) && is_array($boxes) && (count($boxes) != count($results)))
				$results = array_merge($results, $boxes);

			foreach ($results as $result) {

				if (empty($result['id']) || empty($result['status']['statusId']) || ($result['status']['statusId'] != 1))
					continue;

				$items[$result['id']] = [
					'id'          => $result['id'],
					'lat'         => (float) str_replace(',', '.', $result['latitude']),
					'lng'         => (float) str_replace(',', '.', $result['longitude']),
					'name'        => $result['name'],
					'street'      => implode("\n", array_filter([$result['street'], $result['place']])),
					'postcode'    => $result['zip'],
					'city'        => $result['city'],
					'region'      => $result['region'] ?? null,
					'country_id'  => strtoupper($result['country']),
					'description' => $this->getDescription($result['openingHours']['regular']),
					//'max_weight'  => $result['maxWeight'] ?? null, // kg
					'cod'         => ($result['creditCardPayment'] == 'yes') && (stripos($result['name'], 'ZBOX') === false) && (stripos($result['name'], 'Z BOX') === false) && (stripos($result['name'], 'Z-BOX') === false) && (stripos($result['name'], 'ALZABOX') === false) && (stripos($result['name'], 'ALZA-BOX') === false) && (stripos($result['name'], 'ALZA BOX') === false),
				];
			}
		}

		return $items;
	}

	protected function getDescription($data) {

		$html = [];
		$days = [
			1 => is_array($data['monday'])    ? $data['monday']    : explode('–', str_replace([':', ', '], ['', '–'], $data['monday'])),
			2 => is_array($data['tuesday'])   ? $data['tuesday']   : explode('–', str_replace([':', ', '], ['', '–'], $data['tuesday'])),
			3 => is_array($data['wednesday']) ? $data['wednesday'] : explode('–', str_replace([':', ', '], ['', '–'], $data['wednesday'])),
			4 => is_array($data['thursday'])  ? $data['thursday']  : explode('–', str_replace([':', ', '], ['', '–'], $data['thursday'])),
			5 => is_array($data['friday'])    ? $data['friday']    : explode('–', str_replace([':', ', '], ['', '–'], $data['friday'])),
			6 => is_array($data['saturday'])  ? $data['saturday']  : explode('–', str_replace([':', ', '], ['', '–'], $data['saturday'])),
			7 => is_array($data['sunday'])    ? $data['sunday']    : explode('–', str_replace([':', ', '], ['', '–'], $data['sunday']))
		];

		// Array ( [0] => 0000 [1] => 2359 )
		// Array ( [0] => 0001 [1] => 2359 [2] => 0000 [3] => 0000 )
		$always = array_unique(array_values(array_map('implode', $days)));
		if ((count($always) == 1) && in_array($always[0], ['00002359', '00012359', '0000235900000000', '0001235900000000']))
			return '24/7';

		foreach ($days as $day => $str) {

			$str = array_filter($str);

			// fermé toute la journée
			if (empty($str)) {
				$html[$day] = $day.'#closed';
			}
			// ouvert non stop
			else if (count($str) == 2) {
				$html[$day] = $day.'#'.
					substr($str[0], 0, 2).'#'.substr($str[0], 2, 2).'#'.
					substr($str[1], 0, 2).'#'.substr($str[1], 2, 2);
			}
			// fermé à midi
			else {
				$html[$day] = $day.'#'.
					substr($str[0], 0, 2).'#'.substr($str[0], 2, 2).'#'.
					substr($str[1], 0, 2).'#'.substr($str[1], 2, 2).'#'.
					substr($str[2], 0, 2).'#'.substr($str[2], 2, 2).'#'.
					substr($str[3], 0, 2).'#'.substr($str[3], 2, 2);
			}
		}

		foreach ($days as $day => $str) {
			if (!array_key_exists($day, $html))
				$html[$day] = $day.'#closed';
		}

		ksort($html);
		return implode('~', $html);
	}
}