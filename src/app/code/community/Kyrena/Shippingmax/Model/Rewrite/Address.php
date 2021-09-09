<?php
/**
 * Created M/23/04/2019
 * Updated M/20/08/2019
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

class Kyrena_Shippingmax_Model_Rewrite_Address extends Owebia_Shipping2_Model_Os2_Data_Address {

	public function getData($name = null) {

		if (!is_array($this->_data))
			$this->_data = [];

		if (empty($name))
			return $this->_data;

		if (array_key_exists($name, $this->_data))
			return $this->_data[$name];

		$this->_data[$name] = $this->_load($name);
		return $this->_data[$name];
	}

	public function setData($name, $value) {

		if (!is_array($this->_data))
			$this->_data = [];

		$this->_data[$name] = $value;
		return $this;
	}
}