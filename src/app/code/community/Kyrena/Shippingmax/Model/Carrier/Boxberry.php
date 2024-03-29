<?php
/**
 * Created J/08/07/2021
 * Updated M/15/11/2022
 *
 * Copyright 2019-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2019-2022 | Jérôme Siau <jerome~cellublue~com>
 * Copyright 2021      | Florian Palamuso <florian~cellublue~com>
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

class Kyrena_Shippingmax_Model_Carrier_Boxberry extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_boxberry';
	protected $_full = true;
	protected $_api  = true;
	protected $_fullCacheLifetime = 7200; // 2 heures en secondes

	public function loadItemsFromApi(object $address) {

		$url = $this->getConfigData('api_url');
		if (str_contains($url, 'prepaid'))
			$url = preg_replace('#prepaid=\d?#', 'prepaid=1', $url);
		else
			$url = str_contains($url, '?') ? $url.'&prepaid=1': $url.'?prepaid=1';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);

		$items   = [];
		$mapping = ['051' => 'AM', '112' => 'BY', '417' => 'KG', '398' => 'KZ', '643' => 'RU'];
		$results = $this->runCurl($ch);

		//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
		if (!empty($results) && is_array($results)) {

			// https://help.boxberry.ru/pages/viewpage.action?pageId=1703985
			foreach ($results as $result) {

				if (empty($result['Code']) || empty($result['CountryCode']))
					continue;

				$country = $result['CountryCode'];
				if (!array_key_exists($country, $mapping)) {
					Mage::log('Boxberry: unknown country code: '.$country, Zend_Log::ERR);
					continue;
				}

				$gps = explode(',', $result['GPS']);
				if (count($gps) != 2)
					continue;

				$name = $result['Name'];
				$name = (($pos = mb_strpos($name, '_')) === false) ? $name : mb_substr($name, 0, $pos);

				$items[$result['Code']] = [
					'id'          => $result['Code'],
					'lat'         => (float) str_replace(',', '.', $gps[0]),
					'lng'         => (float) str_replace(',', '.', $gps[1]),
					'name'        => $name,
					'street'      => $result['AddressReduce'],
					'postcode'    => mb_substr($result['Address'], 0, mb_strpos($result['Address'], ',')),
					'city'        => $result['CityName'],
					'region'      => $result['Area'],
					'country_id'  => $mapping[$country],
					'description' => implode("\n", array_filter([
						trim($result['TripDescription']),
						$this->getDescription($result['WorkShedule'])
					])),
					//'max_weight'  => $result['LoadLimit'] ?? null, // kg
					'cod'         => ($result['OnlyPrepaidOrders'] != 'Yes'),
				];
			}
		}

		return $items;
	}

	protected function getDescription($data) {

		if (empty($data))
			return '';

		// lundi à dimanche en russe abrégé
		$abbrs = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
		$days  = [1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => '', 7 => ''];
		$positionToDay = array_keys($days);

		$pos = mb_stripos($data, 'обед');
		if ($pos !== false) {
			$lunch = '/'.trim(mb_substr($data, $pos + 5));
			$data  = explode(',', str_replace(', '.mb_substr($data, $pos), '', $data));
		}
		else {
			$lunch = '';
			$data  = explode(',', $data);
		}

		foreach ($data as $stringParts) {
			$explodedStringParts = explode(',', $stringParts);
			foreach ($explodedStringParts as $explodedStringPart) {
				$explodedSchedule = explode(':', $explodedStringPart);
				$schedule[trim($explodedSchedule[0])] = $explodedSchedule[1];
				foreach ($schedule as $period => $hours) {
					$interval = explode('-', $period);
					$from = $interval[0];
					$startPosition = array_search($from, $abbrs, true);
					if (!is_numeric($startPosition)) {
						Mage::log('Boxberry: unknown from: '.$from, Zend_Log::ERR);
						continue;
					}
					$endPosition   = null;
					if (count($interval) > 1) {
						$to = $interval[1];
						$endPosition = array_search($to, $abbrs, true);
					}
					if ($endPosition) {
						for ($i = $startPosition; $i <= $endPosition; $i++) {
							$days[$positionToDay[$i]] = trim($hours).$lunch;
						}
					}
					else {
						$days[$positionToDay[$startPosition]] = trim($hours).$lunch;
					}
				}
			}
		}

		$html = [];
		if (count($days) != 7)
			return '';

		// Array ( [1] => 09.00-19.00 )
		// Array ( [1] => 09.00-19.00/14.00-14.30 )
		$always = array_unique(array_values($days));
		if ((count($always) == 1) && in_array($always[0], ['00.00-23.59', '00.01-23.59']))
			return '24/7';

		foreach ($days as $day => $str) {

			// fermé toute la journée
			if (($str == 'NODAY') || (strlen($str) < 11)) {
				$html[$day] = $day.'#closed';
			}
			// ouvert non stop
			// 09.00-19.00
			else if (!str_contains($str, '/')) {
				$html[$day] = $day.'#'.
					substr($str, 0, 2).'#'.substr($str, 3, 2).'#'.
					substr($str, 6, 2).'#'.substr($str, 9, 2);
			}
			// fermé à midi
			// 09.00-14.00/14.30-19.00
			// 09.00-19.00/14.00-14.30
			else if ((int) substr($str, 6, 2) > (int) substr($str, 12, 2)) {
				$html[$day] = $day.'#'.
					substr($str, 0, 2).'#'.substr($str, 3, 2).'#'.
					substr($str, 12, 2).'#'.substr($str, 15, 2).'#'.
					substr($str, 18, 2).'#'.substr($str, 21, 2).'#'.
					substr($str, 6, 2).'#'.substr($str, 9, 2);
			}
			else {
				$html[$day] = $day.'#'.
					substr($str, 0, 2).'#'.substr($str, 3, 2).'#'.
					substr($str, 6, 2).'#'.substr($str, 9, 2).'#'.
					substr($str, 12, 2).'#'.substr($str, 15, 2).'#'.
					substr($str, 18, 2).'#'.substr($str, 21, 2);
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