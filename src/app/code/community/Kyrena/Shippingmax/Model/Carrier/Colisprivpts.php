<?php
/**
 * Created V/06/11/2020
 * Updated V/24/06/2022
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

class Kyrena_Shippingmax_Model_Carrier_Colisprivpts extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_colisprivpts';
	protected $_full = false;
	protected $_api  = true;
	protected $_fullCacheLifetime = 21600; // 6 heures en secondes

	public function __construct() {
		$this->_full = mb_stripos($this->getConfigData('api_url'), 'http') === false;
		return parent::__construct();
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
			$results = explode("\n", utf8_encode($results)); // le fichier est en iso-8859-1

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
							'lat'         => trim(str_replace(',', '.', $data['LATITUDE']), '0'),
							'lng'         => trim(str_replace(',', '.', $data['LONGITUDE']), '0'),
							'name'        => $data['NOM'],
							'street'      => $data['ADRESSE'],
							'postcode'    => $data['CODE_POSTAL'],
							'city'        => $data['VILLE'],
							'country_id'  => $data['PAYS'],
							'description' => $this->createDescCurl($data)
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
						'lat'         => trim(str_replace(',', '.', $result['coordinate']['latitude']), '0'),
						'lng'         => trim(str_replace(',', '.', $result['coordinate']['longitude']), '0'),
						'name'        => $result['name'],
						'street'      => $result['address']['street'],
						'postcode'    => $result['address']['zip'],
						'city'        => $result['address']['city'],
						'country_id'  => $result['address']['country'],
						'description' => $this->createDescApi($result['openingHours']),
						'dst'         => round($result['distance'], 1)
					];
				}
			}
		}

		return $items;
	}

	protected function checkIfAvailable(object $request) {

		// France (sans la Corse, sans Monaco, sans les DROM/COM)
		if (in_array($request->getData('dest_country_id'), Mage::helper('shippingmax')->getFranceDromCom())) {

			$postcode = trim($request->getData('dest_postcode'));
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

	protected function createDescCurl($values) {

		$html = [];
		$days = [
			'1 Monday'    => 'LUNDI;DEBUT_LU;FIN_LU',
			'2 Tuesday'   => 'MARDI;DEBUT_MA;FIN_MA',
			'3 Wednesday' => 'MERCREDI;DEBUT_ME;FIN_ME',
			'4 Thursday'  => 'JEUDI;DEBUT_JE;FIN_JE',
			'5 Friday'    => 'VENDREDI;DEBUT_VE;FIN_VE',
			'6 Saturday'  => 'SAMEDI;DEBUT_SA;FIN_SA',
			'7 Sunday'    => 'DIMANCHE;DEBUT_DI;FIN_DI'
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

		return implode("\n", $html);
	}

	protected function createDescApi($values) {

		$html = [];
		$days = [
			'1 Monday'    => 'MON',
			'2 Tuesday'   => 'TUE',
			'3 Wednesday' => 'WED',
			'4 Thursday'  => 'THU',
			'5 Friday'    => 'FRI',
			'6 Saturday'  => 'SAT',
			'7 Sunday'    => 'SUN'
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
		return implode("\n", $html);
	}
}