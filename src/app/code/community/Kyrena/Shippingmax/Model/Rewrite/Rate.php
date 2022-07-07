<?php
/**
 * Created V/06/11/2020
 * Updated M/05/07/2022
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

class Kyrena_Shippingmax_Model_Rewrite_Rate extends Mage_Sales_Model_Resource_Quote_Address_Rate_Collection {

	public function getIterator() {

		$this->load();

		$codes = [];
		foreach ($this->_items as $item) {
			if (!$item->isDeleted())
				$codes[] = $item->getData('carrier');
		}

		if (PHP_SAPI != 'cli')
			$group = Mage::getSingleton('checkout/session')->getQuote()->getData('customer_group_id');

		foreach ($this->_items as $item) {

			if (!empty($group)) {
				$keys = array_filter(explode(',', Mage::getStoreConfig('carriers/'.$item->getData('carrier').'/show_for_customer_group')));
				if (!empty($keys) && !in_array($group, $keys))
					$item->isDeleted(true);
			}

			$keys = array_filter(explode(',', Mage::getStoreConfig('carriers/'.$item->getData('carrier').'/hide_when')));
			if (!empty($keys)) {
				foreach ($keys as $key) {
					if (in_array($key, $codes))
						$item->isDeleted(true);
				}
			}
		}

		return new ArrayIterator($this->_items);
	}
}