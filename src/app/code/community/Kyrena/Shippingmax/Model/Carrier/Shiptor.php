<?php
/**
 * Created J/08/07/2021
 * Updated J/15/12/2022
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

class Kyrena_Shippingmax_Model_Carrier_Shiptor extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_shiptor';
	protected $_full = false;
	protected $_api  = true;

	public function loadItemsFromApi(object $address) {

		$items = [];
		if (empty($address->getData('kladr')))
			return $items;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json; charset="utf-8"',
			'X-Authorization-Token: ваш_API_ключ',
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
			'id'      => 'JsonRpcClient.js',
			'jsonrpc' => '2.0',
			'method'  => 'getDeliveryPoints',
			'params'  => ['kladr_id' => $address->getData('kladr')],
		]));
		$results = $this->runCurl($ch);

		//echo '<pre>';print_r(array_slice($results['result'], 0, 20));exit;
		if (!empty($results['result']) && is_array($results['result'])) {

			/* $amounts = [];
			foreach ($results['result'] as $result) {
				if (isset($result['limits']['cod']) && ($result['limits']['cod'] > 0)) {
					if (!array_key_exists($result['limits']['cod'], $amounts))
						$amounts[$result['limits']['cod']] = 1;
					else
						$amounts[$result['limits']['cod']] += 1;
				}
			}
			echo '<pre>',print_r($amounts, true);exit; */

			$methods = explode(',', $this->getConfigData('allowed_methods'));
			foreach ($results['result'] as $result) {

				if (empty($result['id']) || empty($result['gps_location']) || empty($result['active']))
					continue;

				if (!empty($methods) && !in_array($result['courier'], $methods))
					continue;

				$items[$result['id']] = [
					'id'          => $result['id'],
					'lat'         => (float) str_replace(',', '.', $result['gps_location']['latitude']),
					'lng'         => (float) str_replace(',', '.', $result['gps_location']['longitude']),
					'name'        => $result['name'],
					'street'      => $result['prepare_address']['street'],
					'postcode'    => $result['prepare_address']['postal_code'],
					'city'        => $result['prepare_address']['settlement'],
					'region'      => $result['prepare_address']['administrative_area'],
					'country_id'  => 'RU',
					'description' => implode("\n", array_filter([
						$result['trip_description'],
						$this->getDescription($result)
					])),
					//'max_weight'  => $result['limits']['max_weight']['value'] ?? null, // kg
					'cod'         => !empty($result['cod']),
				];
			}
		}

		return $items;
	}

	protected function getDescription($data) {

		if ($data['work_schedule'] == 'Пн,Вт,Ср,Чт,Пт,Сб,Вс: круглосуточно')
			return '24/7';

		if (!empty($data['work_shedule_obj']['schedule']) && (count($data['work_shedule_obj']['schedule']) == 7)) {
			foreach ($data['work_shedule_obj']['schedule'] as $str) {
				if (!empty($str['workTime'])) {
					$days = [
						1 => $data['work_shedule_obj']['schedule'][1],
						2 => $data['work_shedule_obj']['schedule'][2],
						3 => $data['work_shedule_obj']['schedule'][3],
						4 => $data['work_shedule_obj']['schedule'][4],
						5 => $data['work_shedule_obj']['schedule'][5],
						6 => $data['work_shedule_obj']['schedule'][6],
						7 => $data['work_shedule_obj']['schedule'][0],
					];
					break;
				}
			}
		}

		if (empty($days) && !empty($data['work_schedule'])) {
			$data = explode(';', $data['work_schedule']);
			if (count($data) == 7) {
				$days = [
					1 => $data[0],
					2 => $data[1],
					3 => $data[2],
					4 => $data[3],
					5 => $data[4],
					6 => $data[5],
					7 => $data[6],
				];
			}
		}

		if (empty($days))
			return '';

		// Array ( [0] => 1#09#00#22#00 )
		$always = array_unique(array_values(array_map('implode', $days)));
		if ((count($always) == 1) && in_array($always[0], ['#00#00#23#59', '#00#01#23#59']))
			return '24/7';

		$html = [];

		foreach ($days as $day => $str) {

			if (is_string($str)) {
				$str = explode(' ', $str);
				// fermé toute la journée
				if (($str[1] == '-') || (strlen($str[1]) < 11)) {
					$html[$day] = $day.'#closed';
				}
				// ouvert non stop
				// 09:00-19:00
				else if (str_contains($str[1], '-')) {
					$html[$day] = $day.'#'.
						substr($str[1], 0, 2).'#'.substr($str[1], 3, 2).'#'.
						substr($str[1], 6, 2).'#'.substr($str[1], 9, 2);
				}
			}
			// fermé toute la journée
			else if (empty($str['workTime'])) {
				$html[$day] = $day.'#closed';
			}
			// ouvert non stop
			// 09:00-19:00
			else if (empty($str['breaks'])) {
				$html[$day] = $day.'#'.
					substr($str['workTime'], 0, 2).'#'.substr($str['workTime'], 3, 2).'#'.
					substr($str['workTime'], 6, 2).'#'.substr($str['workTime'], 9, 2);
			}
			// fermé à midi
			// 09:00-19:00
			else {
				if (is_array($str['breaks'])) {
					$str['breaks'] = array_unique($str['breaks']);
					$str['breaks'] = $str['breaks'][0];
				}

				$html[$day] = $day.'#'.
					substr($str['workTime'], 0, 2).'#'.substr($str['workTime'], 3, 2).'#'.
					substr($str['breaks'], 0, 2).'#'.substr($str['breaks'], 3, 2).'#'.
					substr($str['breaks'], 6, 2).'#'.substr($str['breaks'], 9, 2).'#'.
					substr($str['workTime'], 6, 2).'#'.substr($str['workTime'], 9, 2);
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