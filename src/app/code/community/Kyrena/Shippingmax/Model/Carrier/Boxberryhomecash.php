<?php
/**
 * Created J/22/07/2021
 * Updated V/26/11/2021
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

class Kyrena_Shippingmax_Model_Carrier_Boxberryhomecash extends Kyrena_Shippingmax_Model_Carrier_Boxberryhome {

	protected $_code = 'shippingmax_boxberryhomecash';
	//otected $_full = true;
	//otected $_api  = true;

	public function getCacheFile() {
		return Mage::getBaseDir('var').'/shippingmax/boxberryhome.dat';
	}
}