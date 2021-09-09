<?php
/**
 * Created M/23/04/2019
 * Updated M/16/03/2021
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Debug extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {
		$element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
		return parent::render($element);
	}

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {

		$storeId = Mage::app()->getDefaultStoreView()->getId();
		$passwd  = Mage::getStoreConfig('carriers/shippingmax/debug_password');

		$element->setValue(sprintf('<a href="%s">debug front</a> / <a href="%s">debug back</a>',
			preg_replace('#/key/[^/]+/#', '/', $this->getUrl('shippingmax/map/debug', ['pass' => $passwd, '_store' => $storeId])),
			preg_replace('#/key/[^/]+/#', '/', $this->getUrl('*/shippingmax_map/debug', ['pass' => $passwd]))
		));

		return sprintf('<span lang="en" id="%s">%s</span>', $element->getHtmlId(), $element->getValue());
	}
}