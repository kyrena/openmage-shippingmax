<?php
/**
 * Created J/05/01/2023
 * Updated M/24/01/2023
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Countries extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_options;

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (!$this->_options)
			$this->_options = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);

		$code = (string) str_replace(['carriers_', '_specificcountry'], '', $element->getHtmlId()); // (yes)
		$countries = Mage::getStoreConfig('carriers/'.$code.'/allowedcountry');
		$countries = empty($countries) ? [] : array_filter(explode(',', $countries));

		$options = [];
		foreach ($this->_options as $option) {
			if (empty($countries) || in_array($option['value'], $countries))
				$options[] = $option;
		}

		$element->setValues($options);
		return parent::render($element);
	}
}