<?php
/**
 * Created V/12/04/2019
 * Updated V/03/03/2023
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

class Kyrena_Shippingmax_Model_Carrier_Inpospaczk extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_inpospaczk';
	protected $_full = true;
	protected $_api  = true;
	protected $_country = 'PL';

	public function loadItemsFromApi(object $address) {

		// https://stackoverflow.com/a/33035088/2980105
		$limit = (int) str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
		if ($limit < 1073741824)
			ini_set('memory_limit', '1G');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));

		$items   = [];
		$results = $this->runCurl($ch);

		//echo '<pre>';print_r(array_slice($results['_embedded']['machines'], 0, 20));exit;
		// https://api-pl.easypack24.net/v4/machines (max 10000?)
		// https://api-uk.easypack24.net/v4/machines
		// https://api-it.easypack24.net/v4/machines
		if (!empty($results['_embedded']['machines']) && is_array($results['_embedded']['machines'])) {

			foreach ($results['_embedded']['machines'] as $result) {

				if (empty($result['id']) || empty($result['location_description']) || empty($result['location']) ||
				    empty($result['status']) || ($result['status'] != 'Operating'))
					continue;

				$items[$result['id']] = [
					'id'          => $result['id'],
					'lat'         => (float) str_replace(',', '.', $result['location'][0]),
					'lng'         => (float) str_replace(',', '.', $result['location'][1]),
					'name'        => $result['location_description'],
					'street'      => $result['address']['street'].' '.$result['address']['building_no'],
					'postcode'    => $result['address']['post_code'],
					'city'        => $result['address']['city'],
					'region'      => $result['address']['province'],
					'country_id'  => $this->_country,
					'description' => $this->getDescription($result['operating_hours']),
				];
			}
		}

		//echo '<pre>';print_r(array_slice($results['items'], 0, 20));exit;
		// https://api-pl-points.easypack24.net/v1/points?per_page=50000 (https://geowidget.easypack24.net/pl/)
		// https://api-uk-points.easypack24.net/v1/points?per_page=50000 (https://geowidget.easypack24.net/uk/)
		// https://api-it-points.easypack24.net/v1/points?per_page=50000 (https://geowidget.easypack24.net/it/)
		// https://api-pl-points.easypack24.net/v1/points/POP-GLO15 (partner_id:30)
		//   0: Parcel Locker
		//  33: ParcelPoint with parcel locker and parcel pick-up function
		//  30: ParcelPoint without parcel locker and parcel pick-up function, has only parcel post function
		else if (!empty($results['items']) && is_array($results['items'])) {

			foreach ($results['items'] as $result) {

				if (empty($result['name']) || empty($result['location_description']) || empty($result['location']) ||
				    empty($result['status']) || ($result['status'] != 'Operating') ||
				    (isset($result['partner_id']) && ($result['partner_id'] == 30)))
					continue;

				$items[$result['name']] = [
					'id'          => $result['name'],
					'lat'         => (float) str_replace(',', '.', $result['location']['latitude']),
					'lng'         => (float) str_replace(',', '.', $result['location']['longitude']),
					'name'        => $result['location_description'],
					'street'      => $result['address_details']['street'].' '.$result['address_details']['building_number'],
					'postcode'    => $result['address_details']['post_code'],
					'city'        => $result['address_details']['city'],
					'region'      => $result['address_details']['province'],
					'country_id'  => $this->_country,
					'description' => $this->getDescription($result['opening_hours']),
				];
			}
		}

		return $items;
	}

	protected function getDescription($data) {

		if ($data == '24/7')
			return '24/7';
		if (empty($data))
			return '';

		$data = explode(';', $data);
		$days = [1 => 'MON', 2 => 'TUE', 3 => 'WED', 4 => 'THU', 5 => 'FRI', 6 => 'SAT', 7 => 'SUN'];
		$html = [];

		foreach ($days as $day => $str) {

			foreach ($data as $hours) {

				$hours = array_filter(explode('|', str_replace('-', '|', $hours)));
				if (empty($hours) || ($hours[0] != $str))
					continue;

				// ouvert non stop ou fermé le matin ou fermé l'après midi
				if (count($hours) == 3) {
					$html[$day] = $day.'#'.
						str_replace(':', '#', $hours[1]).'#'.
						str_replace(':', '#', $hours[2]);
				}
				// fermé à midi
				else if (count($hours) == 5) {
					$html[$day] = $day.'#'.
						str_replace(':', '#', $hours[1]).'#'.
						str_replace(':', '#', $hours[2]).'#'.
						str_replace(':', '#', $hours[3]).'#'.
						str_replace(':', '#', $hours[4]);
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