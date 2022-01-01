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

class Kyrena_Shippingmax_Model_Carrier_Pickpoint extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_pickpoint';
	protected $_full = true;
	protected $_api  = true;

	public function loadItemsFromApi(object $address) {

		$items = [];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, trim($this->getConfigData('api_url'), '/').'/login');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json; charset="utf-8"'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
			'Login'    => $this->getConfigData('api_username'),
			'Password' => $this->getConfigData('api_password', true)
		]));
		$results = $this->runCurl($ch, true);

		if (empty($results['SessionId']))
			return $items;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, trim($this->getConfigData('api_url'), '/').'/clientpostamatlist').
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json; charset="utf-8"'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
			'SessionId' => $results['SessionId'],
			'IKN'       => $this->getConfigData('api_ikn', true)
		]));

		$mapping = ['BY' => 'BY', 'RUS' => 'RU'];
		$results = $this->runCurl($ch, true, 99);

		//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
		if (!empty($results) && is_array($results)) {

			/* $maxAmount = []; $minAmount = [];
			foreach ($results as $result) {
				if (is_numeric($result['AmountTo']) && ($result['AmountTo'] > 0)) {
					if (empty($maxAmount[$result['Cash']][$result['CountryIso']]) || ($result['AmountTo'] > $maxAmount[$result['Cash']][$result['CountryIso']]))
						$maxAmount[$result['Cash']][$result['CountryIso']] = $result['AmountTo'];
					if (empty($minAmount[$result['CountryIso']]) || ($result['AmountTo'] < $minAmount[$result['Cash']][$result['CountryIso']]))
						$minAmount[$result['Cash']][$result['CountryIso']] = $result['AmountTo'];
				}
			}
			echo '<pre>min ',print_r($minAmount, true),' max ',print_r($maxAmount, true);exit; */

			/* $names = [];
			foreach ($results as $result) {
				if (!empty($result['OwnerName']))
					$names[$result['OwnerName']] = $result['OwnerName'];
			}
			echo '<pre>',print_r($names, true);exit; */

			$methods = explode(',', $this->getConfigData('allowed_methods'));
			foreach ($results as $result) {

				// Status: 2 operating, 5 overloaded
				// TemporarilyClosed: 0 work, 1 temporarily closed
				if (empty($result['Id']) || empty($result['CountryIso']) || empty($result['Status']) || ($result['Status'] != 2) || !empty($result['TemporarilyClosed']))
					continue;

				if (!empty($methods) && !in_array($result['OwnerName'], $methods))
					continue;

				$country = $result['CountryIso'];
				if (!array_key_exists($country, $mapping)) {
					Mage::log('Pickpoint: unknown country code: '.$country, Zend_Log::ERR);
					continue;
				}

				$items[$result['Id']] = [
					'id'          => $result['Id'],
					'lat'         => trim(str_replace(',', '.', $result['Latitude']), '0'),
					'lng'         => trim(str_replace(',', '.', $result['Longitude']), '0'),
					'name'        => $result['Name'],
					'street'      => $result['Address'],
					'postcode'    => $result['PostCode'],
					'city'        => $result['CitiName'],
					'region'      => $result['Region'],
					'country_id'  => $mapping[$country],
					'description' => implode("\n", array_filter([
						$result['OutDescription'],
						$this->createDesc($result['WorkTime']),
					])),
					//'max_weight'  => $result['MaxWeight'] ?? null, // kg
					'cod'         => !empty($result['Cash']),
				];
			}
		}

		return $items;
	}

	protected function createDesc($data) {

		$data = (array) explode(',', $data); // (yes)
		if (count($data) != 7)
			return '';

		$html = [];
		$days = [
			'1 Monday'    => $data[0],
			'2 Tuesday'   => $data[1],
			'3 Wednesday' => $data[2],
			'4 Thursday'  => $data[3],
			'5 Friday'    => $data[4],
			'6 Saturday'  => $data[5],
			'7 Sunday'    => $data[6]
		];

		// Array ( [1 Monday] => 09:00-19:00 )
		// Array ( [1 Monday] => 09:00-19:00/14:00-14:30 )
		$always = array_unique(array_values($days));
		if ((count($always) == 1) && in_array($always[0], ['00:00-23:59', '00:01-23:59']))
			return '24/7';

		foreach ($days as $day => $str) {

			// fermé toute la journée
			if (($str == 'NODAY') || (strlen($str) < 11)) {
				$html[] = $day.'#closed';
			}
			// ouvert non stop
			// 09:00-19:00
			else if (strpos($str, '/') === false) {
				$html[] = $day.'#'.
					substr($str, 0, 2).'#'.substr($str, 3, 2).'#'.
					substr($str, 6, 2).'#'.substr($str, 9, 2);
			}
			// fermé à midi
			// 09:00-14:00/14:30-19:00
			// 09:00-19:00/14:00-14:30
			else if ((int) substr($str, 6, 2) > (int) substr($str, 12, 2)) {
				$html[] = $day.'#'.
					substr($str, 0, 2).'#'.substr($str, 3, 2).'#'.
					substr($str, 12, 2).'#'.substr($str, 15, 2).'#'.
					substr($str, 18, 2).'#'.substr($str, 21, 2).'#'.
					substr($str, 6, 2).'#'.substr($str, 9, 2);
			}
			else {
				$html[] = $day.'#'.
					substr($str, 0, 2).'#'.substr($str, 3, 2).'#'.
					substr($str, 6, 2).'#'.substr($str, 9, 2).'#'.
					substr($str, 12, 2).'#'.substr($str, 15, 2).'#'.
					substr($str, 18, 2).'#'.substr($str, 21, 2);
			}
		}

		return implode("\n", $html);
	}
}