<?php
/**
 * Created J/08/07/2021
 * Updated M/03/08/2021
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

class Kyrena_Shippingmax_Model_Carrier_Shiptorcash extends Kyrena_Shippingmax_Model_Carrier_Shiptor {

	protected $_code = 'shippingmax_shiptorcash';
	//otected $_full = false;
	//otected $_api  = true;

	public function getCacheFile() {

		if (Mage::getStoreConfig('carriers/shippingmax_shiptor/api_url') == Mage::getStoreConfig('carriers/shippingmax_shiptorcash/api_url'))
			return Mage::getBaseDir('var').'/shippingmax_shiptor.dat';

		return parent::getCacheFile();
	}

	protected function checkItem(object $address, &$item) {

		if (empty($item['cod']))
			return false;

		return parent::checkItem($address, $item);
	}
}