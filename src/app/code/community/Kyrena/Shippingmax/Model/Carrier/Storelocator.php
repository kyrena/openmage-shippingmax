<?php
/**
 * Created J/23/04/2020
 * Updated M/14/09/2021
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

class Kyrena_Shippingmax_Model_Carrier_Storelocator extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_storelocator';
	protected $_full = true;
	protected $_api  = true;

	public function loadItemsFromApi(object $address) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		$heads   = [];
		$items   = [];
		$results = $this->runCurl($ch, false);
		$results = array_filter(explode("\n", $results));

		if (count($results) > 2) {

			foreach ($results as $result) {

				$result = array_map('trim', explode("\t", $result));
				if (empty($heads)) {
					$heads = $result;
				}
				else if (count($result) == count($heads)) {

					$data = [];
					foreach ($heads as $idx => $head)
						$data[$head] = $result[$idx];

					if (empty($data['id']) || empty($data['LATITUDE']) || empty($data['LONGITUDE']) || empty($data['NOM DU MAGASIN']))
						continue;

					$items[$data['id']] = [
						'id'         => $data['id'],
						'lat'        => trim(str_replace(',', '.', $data['LATITUDE']), '0'),
						'lng'        => trim(str_replace(',', '.', $data['LONGITUDE']), '0'),
						'name'       => $data['NOM DU MAGASIN'],
						'street'     => implode("\n", array_filter([$data['ADRESSE']])),
						'postcode'   => $data['CODE POSTAL'],
						'city'       => $data['VILLE'],
						'country_id' => $data['PAYS']
					];
				}
			}

			krsort($items);
		}

		return $items;
	}

	protected function checkItem(object $address, &$item) {
		return (empty($address->getData('lat')) || empty($address->getData('lng'))) ? true : parent::checkItem($address, $item);
	}
}