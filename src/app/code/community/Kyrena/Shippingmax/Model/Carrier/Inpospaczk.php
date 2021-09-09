<?php
/**
 * Created V/12/04/2019
 * Updated J/05/08/2021
 *
 * Copyright 2019-2021 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2019-2021 | Jérôme Siau <jerome~cellublue~com>
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

	public function loadItemsFromApi(object $address) {

		$items   = [];
		$results = @json_decode(file_get_contents(trim($this->getConfigData('api_url'), '/').'/machines'), true);

		//echo '<pre>';print_r(array_slice($results['_embedded']['machines'], 0, 20));exit;
		if (!empty($results['_embedded']['machines']) && is_array($results['_embedded']['machines'])) {

			foreach ($results['_embedded']['machines'] as $result) {

				if (empty($result['id']) || empty($result['location']) || empty($result['status']) || ($result['status'] != 'Operating'))
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
					'country_id'  => ($this->_code == 'shippingmax_inpospaczk') ? 'PL' : 'GB',
					'description' => $result['operating_hours']
				];
			}
		}

		return $items;
	}
}