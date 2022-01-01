<?php
/**
 * Created V/12/04/2019
 * Updated S/31/07/2021
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

class Kyrena_Shippingmax_Block_Map extends Mage_Core_Block_Template {

	public function _construct() {

		if ($this->helper('core')->isModuleEnabled('Luigifab_Apijs')) {
			$browser = Mage::getSingleton('apijs/useragentparser')->parse();
			if (!empty($browser['browser']))
				$this->setData('browser', sprintf(' | %s %d (%s)', $browser['browser'], $browser['version'], $browser['platform']));
		}

		parent::_construct();
	}

	public function getCacheKeyInfo() {
		return null;
	}

	public function getCacheKey() {
		return null;
	}

	public function getCacheTags() {
		return null;
	}

	public function getCacheLifetime() {
		return null;
	}
}