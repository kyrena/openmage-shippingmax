<?php
/**
 * Created J/27/05/2021
 * Updated J/27/05/2021
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

class Kyrena_Shippingmax_Model_Addressfilter extends Owebia_Shipping2_Model_Os2_Data_AddressFilter {

	public function substitute($input) {

		while (preg_match('/{address_filter\.([^}]+)}/', $input, $result)) {
			$name = $result[1];
			$replacement = isset(self::$_shortcuts[$name]) ? implode(',', self::$_shortcuts[$name]['replace']) : 'unknown';
			$input = str_replace($result[0], $replacement, $input);
		}

		return $input;
    }
}