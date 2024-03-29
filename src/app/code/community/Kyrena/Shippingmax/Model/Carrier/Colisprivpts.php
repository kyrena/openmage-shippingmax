<?php
/**
 * Created V/06/11/2020
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

class Kyrena_Shippingmax_Model_Carrier_Colisprivpts extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_colisprivpts';
	protected $_full; // isFull()
	protected $_api  = true;
	protected $_fullCacheLifetime = 86400;  // 24 heures en secondes
	protected $_fullCacheHourForUpdate = 3; // UTC hour

	public function isFull() {

		if (!is_bool($this->_full))
			$this->_full = mb_stripos($this->getConfigData('api_url'), 'http') === false;

		return parent::isFull();
	}

	public function loadItemsFromApi(object $address) {

		$heads = [];
		$items = [];

		if ($this->_full) {

			// mode CURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));
			curl_setopt($ch, CURLOPT_USERPWD, $this->getConfigData('api_username').':'.$this->getConfigData('api_password', true));

			$results = $this->runCurl($ch, false);
			$results = explode("\n", mb_convert_encoding($results, 'UTF-8', 'ISO-8859-1')); // le fichier est en iso-8859-1

			$today = new DateTime();
			$today->setTimezone(new DateTimeZone('Europe/Paris'));
			$dayEnd = $today->format('Ymd');   // considère le point relais fermé jusqu'à 1 jour avant la fin des congés
			$today->modify('+4 day');
			$dayStart = $today->format('Ymd'); // considère le point relais fermé 4 jours avant la date des congés
			$today->modify('+10 day');
			$dayLast = $today->format('Ymd');  // considère le point relais fermé 14 jours avant la date de fermeture

			//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
			if (count($results) > 2) {

				foreach ($results as $result) {

					$result = array_map('trim', explode(';', $result));
					if (empty($heads)) {
						$heads = $result;
					}
					else if (count($result) >= count($heads)) {

						$data = [];
						foreach ($heads as $idx => $head)
							$data[$head] = $result[$idx];

						if (empty($data['REF_CAB']) || empty($data['ACTIF']) || ($data['ACTIF'] != 'O'))
							continue;

						if (!empty($data['DATE_DEBUT_TEMP']) && !empty($data['DATE_FIN_TEMP']) &&
						    ($data['DATE_DEBUT_TEMP'] <= $data['DATE_FIN_TEMP']) &&
						    ($dayStart >= $data['DATE_DEBUT_TEMP']) &&
						    ($dayEnd < $data['DATE_FIN_TEMP']))
							continue;

						if (!empty($data['DATE_FIN_ACT']) && ($dayLast >= $data['DATE_FIN_ACT']))
							continue;

						$items[$data['REF_CAB']] = [
							'id'          => $data['REF_CAB'],
							'lat'         => (float) str_replace(',', '.', $data['LATITUDE']),
							'lng'         => (float) str_replace(',', '.', $data['LONGITUDE']),
							'name'        => $data['NOM'],
							'street'      => $data['ADRESSE'],
							'postcode'    => $data['CODE_POSTAL'],
							'city'        => $data['VILLE'],
							'country_id'  => $data['PAYS'],
							'description' => $this->getDescriptionCurl($data),
						];
					}
				}
			}
		}
		else {
			// mode API
			// https://www.colisprive.com/cpls/testsearch.aspx
			// https://www.colisprive.com/cpls/doc/
			$ctx = stream_context_create(['http' => ['timeout' => 10]]);
			$results = @json_decode(trim(file_get_contents($this->getConfigData('api_url').
				'?format=JSON'.
				'&accountid='.$this->getConfigData('api_username').
				'&country='.$address->getData('country_id').
				'&language=fr'.
				'&zip='.trim(is_numeric($address->getData('city')) ? $address->getData('city') : $address->getData('postcode')).
				'&distkmzone='.$this->getConfigData('dst_search').
				'&types=H',
			false, $ctx)), true);

			//echo '<pre>';print_r(array_slice($results['accessPointList'], 0, 20));exit;
			if (!empty($results['accessPointList']) && is_array($results['accessPointList'])) {

				foreach ($results['accessPointList'] as $result) {

					if (empty($result['code_dest']) || empty($result['statut']) || ($result['statut'] != 'open'))
						continue;

					$items[$result['code_dest']] = [
						'id'          => $result['code_dest'],
						'lat'         => (float) str_replace(',', '.', $result['coordinate']['latitude']),
						'lng'         => (float) str_replace(',', '.', $result['coordinate']['longitude']),
						'name'        => $result['name'],
						'street'      => $result['address']['street'],
						'postcode'    => $result['address']['zip'],
						'city'        => $result['address']['city'],
						'country_id'  => $result['address']['country'],
						'description' => $this->getDescriptionApi($result['openingHours']),
						'dst'         => round($result['distance'], 1)
					];
				}
			}
		}

		return $items;
	}

	protected function checkIfAvailable(object $request) {

		// France (sans la Corse, sans Monaco, sans les DROM/COM)
		$postcode = $request->getData('dest_postcode');
		if (!empty($postcode) && in_array($request->getData('dest_country_id'), Mage::helper('shippingmax')->getFranceDromCom())) {

			$postcode = trim($postcode);
			// FR France
			// FR 20XXX Corse
			// MC 980XX Monaco
			//  DROM
			// GP 971XX Guadeloupe
			// MQ 972XX Martinique
			// GF 973XX Guyane
			// RE 974XX La Réunion
			// YT 976XX Mayotte
			//  COM
			// BL 97133 Saint-Barthélemy (977 Antilles)
			// MF 97150 Saint-Martin     (978 Antilles)
			// PM 975XX Saint-Pierre-et-Miquelon
			// WF 986XX Wallis-et-Futuna
			// PF 987XX Polynésie Française
			// NC 988XX Nouvelle-Calédonie
			//  TOM
			// TF 984XX Terres australes françaises
			if (mb_stripos($postcode, '20') === 0)
				return false;
			if (mb_stripos($postcode, '97') === 0)
				return false;
			if (mb_stripos($postcode, '98') === 0)
				return false;
		}

		return parent::checkIfAvailable($request);
	}

	protected function getDescriptionCurl($values) {

		$html = [];
		$days = [
			1 => 'LUNDI;DEBUT_LU;FIN_LU',
			2 => 'MARDI;DEBUT_MA;FIN_MA',
			3 => 'MERCREDI;DEBUT_ME;FIN_ME',
			4 => 'JEUDI;DEBUT_JE;FIN_JE',
			5 => 'VENDREDI;DEBUT_VE;FIN_VE',
			6 => 'SAMEDI;DEBUT_SA;FIN_SA',
			7 => 'DIMANCHE;DEBUT_DI;FIN_DI',
		];

		foreach ($days as $day => $str) {

			$str = explode(';', $str);

			// fermé
			if (empty($values[$str[1]]) || empty($values[$str[2]])) {
				$html[$day] = $day.'#closed';
			}
			// ouvert non stop ou fermé le matin ou fermé l'après midi
			else {
				$html[$day] = $day.'#'.
					str_replace(':', '#', $values[$str[1]]).'#'.
					str_replace(':', '#', $values[$str[2]]);
			}
		}

		return implode('~', $html);
	}

	protected function getDescriptionApi($values) {

		$html = [];
		$days = [
			1 => 'MON',
			2 => 'TUE',
			3 => 'WED',
			4 => 'THU',
			5 => 'FRI',
			6 => 'SAT',
			7 => 'SUN',
		];

		foreach ($days as $day => $str) {

			foreach ($values as $hours) {

				if (($hours['name'] != $str) || empty($hours['timespanList']))
					continue;

				// ouvert non stop ou fermé le matin ou fermé l'après midi
				if (count($hours['timespanList']) == 1) {
					$html[$day] = $day.'#'.
						str_replace(':', '#', $hours['timespanList'][0]['start']).'#'.
						str_replace(':', '#', $hours['timespanList'][0]['end']);
				}
				// fermé à midi
				else {
					$html[$day] = $day.'#'.
						str_replace(':', '#', $hours['timespanList'][0]['start']).'#'.
						str_replace(':', '#', $hours['timespanList'][0]['end']).'#'.
						str_replace(':', '#', $hours['timespanList'][1]['start']).'#'.
						str_replace(':', '#', $hours['timespanList'][1]['end']);
				}

				break;
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