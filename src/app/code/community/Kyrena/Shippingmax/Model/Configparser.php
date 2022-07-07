<?php
/**
 * Created M/06/10/2020
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

class Kyrena_Shippingmax_Model_Configparser extends Owebia_Shipping2_Model_ConfigParser {

	public function extractAllPostcodes(string $country) {

		$rows  = $this->getConfig();
		$codes = [];

		foreach ($rows as $code => $row) {
			$shipto = $this->getRowProperty($row, 'shipto');
			if (str_contains($shipto, $country.'(')) {
				$search = [];
				preg_match('#'.$country.'\(([^)]+)\)#', $shipto, $search);
				if (!empty($search[1]))
					$codes = array_merge_recursive($codes, [str_replace(['_free', '_discount'], '', $code) => preg_split('#,\s*#', trim($search[1]))]);
			}
		}

		return $codes;
	}

	public function filterCountries($countries) {

		$rows = $this->getConfig();
		$allowed = [];

		foreach ($rows as $row) {
			$process = ['data' => []];
			foreach ($countries as $country) {
				$address = new Varien_Object(['country_id' => $country]);
				if ($this->_addressMatch($process, $row, 'shipto', $this->getRowProperty($row, 'shipto'), $address))
					$allowed[] = $country;
			}
		}

		return empty($allowed) ? [] : array_unique($allowed);
	}
}