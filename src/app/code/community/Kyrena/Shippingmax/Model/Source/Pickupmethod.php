<?php
/**
 * Created J/29/12/2022
 * Updated J/29/12/2022
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

class Kyrena_Shippingmax_Model_Source_Pickupmethod {

	protected $_options;

	public function toOptionArray() {

		if (empty($this->_options)) {

			$this->_options = [];

			$maps = array_keys(Mage::getConfig()->getNode('global/shippingmax/maps')->asArray());
			foreach ($maps as $code) {
				if ($code != 'shippingmax_storelocator')
					$this->_options[$code] = ['value' => $code, 'label' => $code];
			}
		}

		return $this->_options;
	}
}