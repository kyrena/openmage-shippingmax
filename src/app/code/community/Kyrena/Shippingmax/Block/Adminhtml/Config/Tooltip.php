<?php
/**
 * Created L/13/09/2021
 * Updated L/13/09/2021
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Tooltip extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (!empty(Mage::getConfig()->getNode('modules/Owebia_Shipping2/lite')))
			$element->setTooltip((empty($text = $element->getTooltip()) ? '' : $text.'<br /><br />').'<u>Only with Owebia/Shipping 2.6.10-<b>lite</b>:</u><br />label is optional<br />{config.x-y-z}<br />fees (default)<br />fees_eur (for stores in EUR)<br />conditons (default)<br />conditons_pln (for stores in PLN)');

		$element->setComment('<a href="https://owebia.com/os2/en/doc">owebia.com/os2/en/doc</a>'.(empty($text = $element->getComment()) ? '' : '<br />'.$text));

		return parent::render($element);
	}
}