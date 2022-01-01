<?php
/**
 * Created J/25/02/2021
 * Updated V/26/11/2021
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

class Kyrena_Shippingmax_Model_Carrier_Przesodbpkcash extends Kyrena_Shippingmax_Model_Carrier_Przesodbpk {

	protected $_code = 'shippingmax_przesodbpkcash';
	//otected $_full = true;
	//otected $_api  = true;

	public function getCacheFile() {

		if (Mage::getStoreConfig('carriers/shippingmax_przesodbpk/api_url') == Mage::getStoreConfig('carriers/shippingmax_przesodbpkcash/api_url'))
			return Mage::getBaseDir('var').'/shippingmax/przesodbpk.dat';

		return parent::getCacheFile();
	}

	protected function checkItem(object $address, &$item) {

		if (empty($item['cod']))
			return false;

		return parent::checkItem($address, $item);
	}
}