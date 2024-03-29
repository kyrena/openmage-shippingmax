<?php
/**
 * Created V/12/04/2019
 * Updated M/15/11/2022
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

class Kyrena_Shippingmax_Model_Carrier_Pocztk48Op extends Kyrena_Shippingmax_Model_Carrier {

	protected $_code = 'shippingmax_pocztk48Op';
	protected $_full = false;
	protected $_api  = true;

	public function loadItemsFromApi(object $address) {

		// <script src="https://mapa.ecommerce.poczta-polska.pl/widget/scripts/ppwidget.js"></script>
		// <script>PPWidgetApp.toggleMap(callback); function callback() { }</script>
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_URL, $this->getConfigData('api_url'));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Content-Type: application/json; charset="utf-8"',
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
			'latitude'  => $address->getData('lat'),
			'longitude' => $address->getData('lng'),
			'type'      => explode(',', $this->getConfigData('allowed_methods')),
		]));

		$items   = [];
		$results = $this->runCurl($ch);

		//echo '<pre>';print_r(array_slice($results, 0, 20));exit;
		if (!empty($results) && is_array($results)) {

			foreach ($results as $result) {

				if (empty($result['pni']) || empty($result['latitude']) || empty($result['longitude']))
					continue;

				$items[$result['pni']] = [
					'id'          => $result['pni'],
					'lat'         => (float) str_replace(',', '.', $result['latitude']),
					'lng'         => (float) str_replace(',', '.', $result['longitude']),
					'name'        => $result['name'],
					'street'      => $result['street'],
					'postcode'    => $result['zipCode'],
					'city'        => $result['city'],
					'region'      => $result['province'],
					'country_id'  => 'PL',
					'description' => implode("\n", array_filter([
						$result['type'],
						trim(str_replace('#', "\n", $result['description']))
					]))
				];
			}
		}

		return $items;
	}
}