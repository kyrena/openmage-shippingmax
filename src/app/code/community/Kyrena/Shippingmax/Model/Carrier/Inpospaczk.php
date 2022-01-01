<?php
/**
 * Created V/12/04/2019
 * Updated J/09/12/2021
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
		$results = $this->runCurl($ch, true, 199);

		//echo '<pre>';print_r(array_slice($results['_embedded']['machines'], 0, 20));exit;
		// https://api-pl.easypack24.net/v4/machines
		// https://api-uk.easypack24.net/v4/machines
		// https://api-it.easypack24.net/v4/machines
		if (!empty($results['_embedded']['machines']) && is_array($results['_embedded']['machines'])) {

			foreach ($results['_embedded']['machines'] as $result) {

				if (empty($result['id']) || empty($result['location_description']) || empty($result['location']) ||
				    empty($result['status']) || ($result['status'] != 'Operating'))
					continue;

				$items[$result['id']] = [
					'id'          => $result['id'],
					'lat'         => trim(str_replace(',', '.', $result['location'][0]), '0'),
					'lng'         => trim(str_replace(',', '.', $result['location'][1]), '0'),
					'name'        => $result['location_description'],
					'street'      => $result['address']['street'].' '.$result['address']['building_no'],
					'postcode'    => $result['address']['post_code'],
					'city'        => $result['address']['city'],
					'region'      => $result['address']['province'],
					'country_id'  => $this->_country,
					'description' => $result['operating_hours']
				];
			}
		}

		//echo '<pre>';print_r(array_slice($results['items'], 0, 20));exit;
		// https://api-pl-points.easypack24.net/v1/points?per_page=50000 (https://geowidget.easypack24.net/pl/)
		// https://api-uk-points.easypack24.net/v1/points?per_page=50000 (https://geowidget.easypack24.net/uk/)
		// https://api-it-points.easypack24.net/v1/points?per_page=50000 (https://geowidget.easypack24.net/it/)
		else if (!empty($results['items']) && is_array($results['items'])) {

			foreach ($results['items'] as $result) {

				if (empty($result['name']) || empty($result['location_description']) || empty($result['location']) ||
				    empty($result['status']) || ($result['status'] != 'Operating'))
					continue;

				$items[$result['name']] = [
					'id'          => $result['name'],
					'lat'         => trim(str_replace(',', '.', $result['location']['latitude']), '0'),
					'lng'         => trim(str_replace(',', '.', $result['location']['longitude']), '0'),
					'name'        => $result['location_description'],
					'street'      => $result['address_details']['street'].' '.$result['address_details']['building_number'],
					'postcode'    => $result['address_details']['post_code'],
					'city'        => $result['address_details']['city'],
					'region'      => $result['address_details']['province'],
					'country_id'  => $this->_country,
					'description' => $result['opening_hours']
				];
			}
		}

		return $items;
	}
}