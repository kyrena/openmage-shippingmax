<?php
/**
 * Created L/26/07/2021
 * Updated S/19/02/2022
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

class Kyrena_Shippingmax_Model_Carrier_Dpdfrrelais extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_dpdfrrelais';
	protected $_full = false;
	protected $_api  = true;

	public function loadItemsFromApi(object $address) {

		$items = [];
		ini_set('default_socket_timeout', 20);

		try {
			$client = new SoapClient($this->getConfigData('api_url'), ['trace' => 1]);
			$params = [
				'carrier'             => $this->getConfigData('api_username', false, 'EXA'),
				'key'                 => $this->getConfigData('api_password', true, 'deecd7bc81b71fcc0e292b53e826c48f'),
				'countrycode'         => $address->getData('country_id'),
				'zipCode'             => $address->getData('postcode'),
				'city'                => $address->getData('city'),
				'latitude'            => round($address->getData('lat'), 11),
				'longitude'           => round($address->getData('lng'), 11),
				'max_distance_search' => $this->getConfigData('dst_search'),
				'max_pudo_number'     => $this->getConfigData('max_points'),
                    'date_from'           => date('d/m/Y'),
				'requestID'           => '1234', // toujours à 1234, cf module dpdfrance 5.2.0 pour magento 1.9 (ligne 79)
				'request_id'          => '1234', // toujours à 1234, cf module dpdfrance 5.2.0 pour magento 1.9 (ligne 80)
				//'weight'            => '',
				//'holiday_tolerant'  => '',
			];

			// https://mypudo.pickup-services.com/mypudo/mypudo.asmx?op=GetPudoList
			// https://mypudo.pickup-services.com/mypudo/mypudo.asmx?op=GetPudoListByLongLat
			// SoapFault exception: [soap:Server] Le serveur n'a pas pu traiter la demande. ---> La référence d'objet n'est pas définie à une instance d'un objet.
			//if (empty($params['latitude']) || empty($params['longitude']))
				$results = $client->getPudoList($params);
			//else
			//	$results = $client->getPudoListByLongLat($params);
		}
		catch (Throwable $t) {
			Mage::logException($t);
		}

		ini_restore('default_socket_timeout');
		if (empty($results) || !is_object($results))
			return $items;

		//header('Content-Type: application/xml');print_r($results->GetPudoListResult->any);exit;
		if (!empty($results->GetPudoListResult->any)) {

			$results = new SimpleXMLElement($results->GetPudoListResult->any);
			$results = $results->xpath('*/PUDO_ITEM');

			foreach ($results as $result) {
				$items[(string) $result->PUDO_ID] = [
					'id'          => (string) $result->PUDO_ID,
					'lat'         => trim(str_replace(',', '.', (string) $result->LATITUDE), '0'),
					'lng'         => trim(str_replace(',', '.', (string) $result->LONGITUDE), '0'),
					'name'        => (string) $result->NAME,
					'street'      => implode("\n", array_filter([(string) $result->ADDRESS1, (string) $result->ADDRESS2, (string) $result->ADDRESS3])),
					'postcode'    => (string) $result->ZIPCODE,
					'city'        => (string) $result->CITY,
					'country_id'  => 'FR',
					'description' => implode("\n", array_filter([
						(string) $result->LOCAL_HINT,
						$this->createDesc($result)
					])),
				];
			}
		}

		return $items;
	}

	protected function createDesc($data) {

		if (empty($data->OPENING_HOURS_ITEMS))
			return '';

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

		foreach ($data->OPENING_HOURS_ITEMS->OPENING_HOURS_ITEM as $info) {
			$i = 0;
			foreach ($days as $key => $day) {
				if ((++$i == (int) $info->DAY_ID)) {
					// [START_TM] => 07:30 [END_TM] => 12:00
					// [START_TM] => 14:00 [END_TM] => 19:00
					$start = str_replace(':', '', (string) $info->START_TM);
					$end   = str_replace(':', '', (string) $info->END_TM);
					if ($day[0] == '0000') {
						$days[$key] = [$start, $end, '0000', '0000'];
					}
					else if ($day[1] == $start) {
						$days[$key][1] = $end;
					}
					else {
						$days[$key][2] = $start;
						$days[$key][3] = $end;
					}
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

			$str = array_filter($str);

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