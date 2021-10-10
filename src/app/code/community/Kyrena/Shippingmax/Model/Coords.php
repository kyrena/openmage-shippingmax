<?php
/**
 * Created V/12/04/2019
 * Updated J/30/09/2021
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

class Kyrena_Shippingmax_Model_Coords extends Mage_Core_Model_Abstract {

	public function _construct() {
		$this->_init('shippingmax/coords');
	}

	public function getApiUrl($city, $postcode, $country) {

		if (($country == 'FR') && (stripos($city, 'cedex') !== false))
			$city = preg_replace('#\s+cedex\s*[\d]*\s*#i', '', $city);

		return 'https://nominatim.openstreetmap.org/search'.
			'?q='.(empty($city) ? urlencode($postcode) : urlencode($postcode).','.urlencode($city)).
			'&countrycodes='.urlencode(strtoupper($country)).
			'&accept-language='.Mage::getStoreConfig('general/locale/code').
			'&format=json';
	}

	public function setAddressCoords(object $address, bool $withCity = true) {

		$city     = trim($address->getData('city'));
		$postcode = trim($address->getData('postcode'));
		$country  = trim($address->getData('country_id'));

		if (empty($city) || empty($postcode) || empty($country))
			return $this;

		if (in_array($country, ['KZ', 'RU'])) {
			$str = mb_strtolower(trim($postcode.' '.$city).', '.$country);
			$url = 'https://cleaner.dadata.ru/api/v1/clean/address';
		}
		else if ($withCity) {
			$str = mb_strtolower(trim($postcode.' '.$city).', '.$country);
			$url = $this->getApiUrl($city, $postcode, $country);
		}
		else if (is_numeric($city)) {
			$str = mb_strtolower($city.', '.$country);
			$url = $this->getApiUrl(null, $city, $country);
		}
		else {
			$str = mb_strtolower($postcode.', '.$country);
			$url = $this->getApiUrl(null, $postcode, $country);
		}

		if (($country == 'FR') && ($postcode == '98000'))
			$country = 'MC';

		$this->load($key = md5($str), 'addrkey');

		if (empty($this->getId())) {

			if (in_array($country, ['KZ', 'RU']) && !empty($subkey = Mage::getStoreConfig('carriers/shippingmax/dadataru_api_key'))) {

				// https://dadata.ru/api/clean/address/
				try {
					$results = $this->sendRequest($url, [
						'Accept: application/json',
						'Content-Type: application/json; charset="utf-8"',
						'Authorization: Token '.$subkey,
						'X-Secret: '.Mage::getStoreConfig('carriers/shippingmax/dadataru_api_token')
					], json_encode([$str]));

					if (empty($results) || !is_array($results))
						Mage::throwException(sprintf('No results from dadata (%s - %s - %s)', $city, $postcode, $country));

					if (!empty($results['error']))
						Mage::throwException(sprintf('No results from dadata (%s - %s - %s)', $results['status'], $results['error'], $results['message']));

					//echo '<pre>';print_r($results);exit;
					if (!empty($results[0]['geo_lat']) && !empty($results[0]['geo_lon']) &&
					    !empty($results[0]['kladr_id']) && !empty($results[0]['postal_code'])) {

						$this->setData('addrkey', $key);
						$this->setData('country_id', $country);
						$this->setData('kladr', $results[0]['kladr_id']);
						$this->setData('city', $city);
						$this->setData('lat', round($results[0]['geo_lat'], 6));
						$this->setData('lng', round($results[0]['geo_lon'], 6));

						if (is_numeric($postcode)) {
							$this->setData('postcode', $postcode);
							$this->save();
						}
						else {
							$postcode = $results[0]['postal_code'];

							// le code postal peut être une rue
							// enregistre deux lignes :
							//  la première avec la rue dans la clef
							//  la seconde avec le code postal dans la clef
							$this->setData('postcode', $postcode);
							$this->save();

							$str = mb_strtolower(trim($postcode.' '.$city).', '.$country);
							$this->setId(null);
							$this->load($key = md5($str), 'addrkey');
							if (empty($this->getId())) {
								$this->setData('addrkey', $key);
								$this->save();
							}

							$address->setData('postcode', $postcode);
						}

						$address->setData('lat', $this->getData('lat'));
						$address->setData('lng', $this->getData('lng'));
						$address->setData('kladr', $this->getData('kladr'));
					}
					else {
						$address->setData('lat', null);
						$address->setData('lng', null);
						$address->setData('kladr', null);
					}
				}
				catch (Throwable $t) {
					Mage::logException($t);
				}
			}
			else {
				// https://wiki.openstreetmap.org/wiki/FR:Nominatim
				// https://nominatim.org/release-docs/develop/api/Search/
				try {
					$priority = 0;
					$found    = false;
					$results  = $this->sendRequest($url);

					if (empty($results) || !is_array($results)) {
						if ($withCity) return $this->setAddressCoords($address, false); // réessaye sans la ville
						Mage::throwException(sprintf('No results from nominatim (%s - %s - %s)', $city, $postcode, $country));
					}

					//echo '<pre>';print_r($results);exit;
					foreach ($results as $result) {

						if (!empty($result['lat']) && !empty($result['lon']) && !empty($result['type'])) {

							$result['importance'] = empty($result['importance']) ? 0.05 : (float) $result['importance'];
							$isZip = in_array($result['type'], ['postcode', 'postal_code']);

							if ($isZip || ($result['importance'] > $priority)) {

								$this->setData('addrkey', $key);
								$this->setData('country_id', $country);
								$this->setData('postcode', $postcode);
								$this->setData('city', $withCity ? $city : null);
								$this->setData('lat', round($result['lat'], 6));
								$this->setData('lng', round($result['lon'], 6));

								$found    = true;
								$priority = $result['importance'];
								if ($isZip)
									break;
							}
						}
					}

					if ($found) {
						$this->save();
						$address->setData('lat', $this->getData('lat'));
						$address->setData('lng', $this->getData('lng'));
					}
					else {
						$address->setData('lat', null);
						$address->setData('lng', null);
					}
				}
				catch (Throwable $t) {
					Mage::logException($t);
				}
			}
		}
		else {
			$address->setData('lat', $this->getData('lat'));
			$address->setData('lng', $this->getData('lng'));
			if (!empty($kladr = $this->getData('kladr')))
				$address->setData('kladr', $kladr);
		}

		return $this;
	}

	public function getReverseApiUrl($lat, $lng) {

		return 'https://nominatim.openstreetmap.org/reverse'.
			'?lat='.urlencode(trim($lat)).
			'&lon='.urlencode(trim($lng)).
			'&accept-language='.Mage::getStoreConfig('general/locale/code').
			'&format=json';
	}

	public function setReverseAddressCoords(object $address, bool $dadata = false) {

		$lat = $address->getData('lat');
		$lng = $address->getData('lng');

		if (empty($lat) || empty($lng))
			return $this;

		// 4 décimales, ~11 m de précision (https://gis.stackexchange.com/a/8674)
		$str = round($lat, 4).'-'.round($lng, 4);
		$this->load($key = md5($str), 'addrkey');

		if (empty($this->getId())) {

			if ($dadata && !empty($subkey = Mage::getStoreConfig('carriers/shippingmax/dadataru_api_key'))) {

				// https://dadata.ru/api/geolocate/
				try {
					$results = $this->sendRequest('https://suggestions.dadata.ru/suggestions/api/4_1/rs/geolocate/address', [
						'Accept: application/json',
						'Content-Type: application/json; charset="utf-8"',
						'Authorization: Token '.$subkey,
						'X-Secret: '.Mage::getStoreConfig('carriers/shippingmax/dadataru_api_token')
					], json_encode(['lat' => $lat, 'lon' => $lng]));

					if (empty($results) || !is_array($results))
						Mage::throwException(sprintf('No reverse results from dadata (%s - %s)', $lat, $lng));

					if (!empty($results['error']))
						Mage::throwException(sprintf('No reverse results from dadata (%s - %s - %s)', $results['status'], $results['error'], $results['message']));

					//echo '<pre>';print_r($results);exit;
					if (!empty($results['suggestions'][0]['data']['country_iso_code']) &&
					    !empty($results['suggestions'][0]['data']['postal_code']) &&
					    !empty($results['suggestions'][0]['data']['kladr_id']) &&
					    !empty($results['suggestions'][0]['data']['city'])) {

						$address->setData('country_id', strtoupper($results['suggestions'][0]['data']['country_iso_code']));
						$address->setData('postcode', $results['suggestions'][0]['data']['postal_code']);
						$address->setData('kladr', $results['suggestions'][0]['data']['kladr_id']);
						$address->setData('city', $results['suggestions'][0]['data']['city']);

						$this->setData('addrkey', $key);
						$this->setData('country_id', $address->getData('country_id'));
						$this->setData('postcode', $address->getData('postcode'));
						$this->setData('kladr', $address->getData('kladr'));
						$this->setData('city', $address->getData('city'));
						$this->setData('lat', round($lat, 4));
						$this->setData('lng', round($lng, 4));
						$this->save();
					}
				}
				catch (Throwable $t) {
					Mage::logException($t);
				}
			}
			else {
				// https://wiki.openstreetmap.org/wiki/FR:Nominatim
				// https://nominatim.org/release-docs/develop/api/Reverse/
				try {
					$result = $this->sendRequest($this->getReverseApiUrl($lat, $lng));

					if (empty($result) || !is_array($result))
						Mage::throwException(sprintf('No reverse results from nominatim (%s - %s)', $lat, $lng));

					//echo '<pre>';print_r($result);exit;
					if (!empty($result['address']) && !empty($result['address']['country_code']) && !empty($result['address']['postcode'])) {

						$address->setData('country_id', strtoupper($result['address']['country_code']));
						$address->setData('postcode', $result['address']['postcode']);
						if (!empty($result['address']['city']))
							$address->setData('city', $result['address']['city']);
						else if (!empty($result['address']['town']))
							$address->setData('city', $result['address']['town']);
						else if (!empty($result['address']['village']))
							$address->setData('city', $result['address']['village']);
						else if (!empty($result['address']['municipality']))
							$address->setData('city', $result['address']['municipality']);

						$this->setData('addrkey', $key);
						$this->setData('country_id', $address->getData('country_id'));
						$this->setData('postcode', $address->getData('postcode'));
						$this->setData('city', $address->getData('city'));
						$this->setData('lat', round($lat, 4));
						$this->setData('lng', round($lng, 4));
						$this->save();
					}
				}
				catch (Throwable $t) {
					Mage::logException($t);
				}
			}
		}
		else {
			$address->setData('country_id', $this->getData('country_id'));
			$address->setData('postcode', $this->getData('postcode'));
			$address->setData('city', $this->getData('city'));
			if (!empty($kladr = $this->getData('kladr')))
				$address->setData('kladr', $kladr);
		}

		return $this;
	}

	protected function sendRequest(string $url, $headers = null, $post = null) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_REFERER, Mage::getBaseUrl());

		if (empty($post)) {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset="utf-8"', 'Accept: application/json']);
		}
		else {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$result = curl_exec($ch);
		$result = (($result === false) || (curl_errno($ch) !== 0)) ? trim('CURL_ERROR '.curl_errno($ch).' '.curl_error($ch)) :
			@json_decode($result, true);
		curl_close($ch);

		return $result;
	}
}