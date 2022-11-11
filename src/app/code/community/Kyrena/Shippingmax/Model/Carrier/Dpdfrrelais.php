<?php
/**
 * Created L/26/07/2021
 * Updated M/11/10/2022
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

		// pour la configuration des prix (cf module dpdfrance 5.2.0 pour magento 1.9)
		// zones de montage : FR(04120, 04130, 04140, 04160, 04170, 04200, 04240, 04260, 04300, 04310, 04330, 04360, 04370, 04400, 04510, 04530, 04600, 04700, 04850, 05100, 05110, 05120, 05130, 05150, 05160, 05170, 05200, 05220, 05240, 05250, 05260, 05290, 05300, 05310, 05320, 05330, 05340, 05350, 05400, 05460, 05470, 05500, 05560, 05600, 05700, 05800, 06140, 06380, 06390, 06410, 06420, 06430, 06450, 06470, 06530, 06540, 06620, 06710, 06750, 06910, 09110, 09140, 09300, 09460, 25120, 25140, 25240, 25370, 25450, 25500, 25650, 30570, 31110, 38112, 38114, 38142, 38190, 38250, 38350, 38380, 38410, 38580, 38660, 38700, 38750, 38860, 38880, 39220, 39310, 39400, 63113, 63210, 63240, 63610, 63660, 63690, 63840, 63850, 64440, 64490, 64560, 64570, 65110, 65120, 65170, 65200, 65240, 65400, 65510, 65710, 66210, 66760, 66800, 68140, 68610, 68650, 73110, 73120, 73130, 73140, 73150, 73160, 73170, 73190, 73210, 73220, 73230, 73250, 73260, 73270, 73300, 73320, 73340, 73350, 73390, 73400, 73440, 73450, 73460, 73470, 73500, 73530, 73550, 73590, 73600, 73620, 73630, 73640, 73710, 73720, 73870, 74110, 74120, 74170, 74220, 74230, 74260, 74310, 74340, 74350, 74360, 74390, 74400, 74420, 74430, 74440, 74450, 74470, 74480, 74660, 74740, 74920, 83111, 83440, 83530, 83560, 83630, 83690, 83830, 83840, 84390, 88310, 88340, 88370, 88400, 90200)
		// iles et corse : FR(20*, 17111, 17123, 17190, 17310, 17370, 17410, 17480, 17550, 17580, 17590, 17630, 17650, 17670, 17740, 17840, 17880, 17940, 22870, 29242, 29253, 29259, 29980, 29990, 56360, 56590, 56780, 56840, 85350)

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
				'max_pudo_number'     => min(25, $this->getConfigData('max_points')),
                    'date_from'           => date('d/m/Y'),
				'requestID'           => '1234', // toujours à 1234, cf module dpdfrance 5.2.0 pour magento 1.9 (ligne 79)
				'request_id'          => '1234', // toujours à 1234, cf module dpdfrance 5.2.0 pour magento 1.9 (ligne 80)
				//'weight'            => '',
				//'holiday_tolerant'  => '',
			];

			// https://mypudo.pickup-services.com/mypudo/mypudo.asmx?op=GetPudoList
			// https://mypudo.pickup-services.com/mypudo/mypudo.asmx?op=GetPudoListByLongLat
			// SoapFault exception: [soap:Server] Le serveur n'a pas pu traiter la demande.
			// ---> La référence d'objet n'est pas définie à une instance d'un objet.
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
			1 => ['0000', '0000', '0000', '0000'],
			2 => ['0000', '0000', '0000', '0000'],
			3 => ['0000', '0000', '0000', '0000'],
			4 => ['0000', '0000', '0000', '0000'],
			5 => ['0000', '0000', '0000', '0000'],
			6 => ['0000', '0000', '0000', '0000'],
			7 => ['0000', '0000', '0000', '0000'],
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

		// Array ( [0] => 0000 [1] => 2359 [2] => 0000 [3] => 0000 )
		// Array ( [0] => 0001 [1] => 2359 [2] => 0000 [3] => 0000 )
		$always = array_unique(array_values(array_map('implode', $days)));
		if ((count($always) == 1) && in_array($always[0], ['0000235900000000', '0001235900000000']))
			return '24/7';

		foreach ($days as $day => $str) {

			$str = array_filter($str);

			// fermé toute la journée
			if ($str[0] == '0000') {
				$html[$day] = $day.'#closed';
			}
			// ouvert non stop
			else if ($str[2] == '0000') {
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