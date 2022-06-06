<?php
/**
 * Created J/11/07/2019
 * Updated J/10/03/2022
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

class Kyrena_Shippingmax_Model_Carrier_Chronorelais extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_chronorelais';
	protected $_full = false;
	protected $_api  = true;

	public function loadItemsFromApi(object $address) {

		$items = [];
		$drom  = ['RE' => 'REU', 'MQ' => 'MTQ', 'GP' => 'GLP', 'YT' => 'MYT', 'GF' => 'GUF'];
		ini_set('default_socket_timeout', 20);

		try {
			// https://www.chronopost.fr/fr/plateformes-e-commerce
			$client = new SoapClient($this->getConfigData('api_url'), ['trace' => 1]);
			$params = [
				'accountNumber'      => $this->getConfigData('api_username'),
				'password'           => $this->getConfigData('api_password', true),
				'countryCode'        => array_key_exists($address->getData('country_id'), $drom) ?
					$drom[$address->getData('country_id')] : $address->getData('country_id'),
				'zipCode'            => $address->getData('postcode'),
				'city'               => $address->getData('city'),
				'coordGeoLatitude'   => round($address->getData('lat'), 11),
				'coordGeoLongitude'  => round($address->getData('lng'), 11),
				'maxDistanceSearch'  => $this->getConfigData('dst_search'),
				'type'               => 'P',  // ?
				'service'            => 'T',  // ?
				'weight'             => 2000, // toujours à 2000, cf module chronopost 1.2.8 pour magento 2.2 (ligne 1424)
				'shippingDate'       => date('d/m/Y'),
				'maxPointChronopost' => min($this->getConfigData('max_points'), 25), // max = 25
				'holidayTolerant'    => 1,
			];

			if (empty($params['coordGeoLatitude']) || empty($params['coordGeoLongitude']))
				$results = in_array($address->getData('country_id'), Mage::helper('shippingmax')->getFranceDromCom(true)) ?
					$client->recherchePointChronopost($params) : $client->recherchePointChronopostInter($params);
			else
				$results = $client->recherchePointChronopostParCoordonneesGeographiques($params);
		}
		catch (Throwable $t) {
			Mage::logException($t);
		}

		ini_restore('default_socket_timeout');
		if (empty($results) || !is_object($results))
			return $items;

		//echo '<pre>';print_r(array_slice($results->return->listePointRelais, 0, 20));exit;
		if (($results->return->errorCode == 0) && !empty($results->return->listePointRelais)) {

			$results = $results->return->listePointRelais;
			if (count($results) == 1)
				$results = [$results];

			foreach ($results as $result) {

				if (empty($result->identifiant) || ($result->actif != 1))
					continue;

				$items[$result->identifiant] = [
					'id'          => $result->identifiant,
					'lat'         => trim(str_replace(',', '.', $result->coordGeolocalisationLatitude), '0'),
					'lng'         => trim(str_replace(',', '.', $result->coordGeolocalisationLongitude), '0'),
					'name'        => $result->nom,
					'street'      => implode("\n", array_filter([$result->adresse1, $result->adresse2, $result->adresse3])),
					'postcode'    => $result->codePostal,
					'city'        => $result->localite,
					'country_id'  => $result->codePays,
					'description' => $this->createDesc($result),
					//'max_weight'  => $result->poidsMaxi ?? null, // kg
				];
			}
		}

		return $items;
	}

	protected function createDesc($data) {

		$html = [];
		$days = [
			'1 Monday'    => ['0000', '0000', '0000', '0000'],
			'2 Tuesday'   => ['0000', '0000', '0000', '0000'],
			'3 Wednesday' => ['0000', '0000', '0000', '0000'],
			'4 Thursday'  => ['0000', '0000', '0000', '0000'],
			'5 Friday'    => ['0000', '0000', '0000', '0000'],
			'6 Saturday'  => ['0000', '0000', '0000', '0000'],
			'7 Sunday'    => ['0000', '0000', '0000', '0000'],
		];

		foreach ($data->listeHoraireOuverture as $info) {
			$i = 0;
			foreach ($days as $key => $day) {
				if ((++$i == $info->jour) && !empty($info->horairesAsString)) {
					// [horairesAsString] => 07:30-12:00 15:00-19:00
					$day = preg_split('#\s|-#', str_replace(':', '', $info->horairesAsString));
					$day = array_pad($day, 4, '0000');
					if ($day[1] == $day[2]) {
						$day[1] = $day[3];
						$day[2] = '0000';
						$day[3] = '0000';
					}
					$days[$key] = $day;
					break;
				}
			}
		}

		// Array ( [1 Monday] => Array ( [0] => 0930 [1] => 1200 [2] => 1400 [3] => 1800 )
		// Array ( [1 Monday] => Array ( [0] => 0930 [1] => 2300 [2] => 0000 [3] => 0000 )
		$always = array_unique(array_values(array_map('implode', $days)));
		if ((count($always) == 1) && in_array($always[0], ['0000235900000000', '0100235900000000']))
			return '24/7';

		foreach ($days as $day => $str) {

			// fermé toute la journée
			if ($str[0] == '0000') {
				$html[] = $day.'#closed';
			}
			// ouvert non stop
			else if ($str[2] == '0000') {
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