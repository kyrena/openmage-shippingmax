<?php
/**
 * Created J/25/04/2019
 * Updated V/16/07/2021
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Test extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {
		$element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
		return parent::render($element);
	}

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {

		$code  = str_replace(['carriers_', '_test'], '', $element->getHtmlId());
		$track = Mage::getStoreConfig('carriers/'.$code.'/tracking');

		if (empty($track)) {
			$element->setValue(sprintf('<a href="%s">test front</a> / <a href="%s">test back</a>',
				preg_replace('#/key/[^/]+/#', '/', $this->getUrl('shippingmax/map/index', ['code' => $code, '_store' => $this->getStoreId()])),
				preg_replace('#/key/[^/]+/#', '/', $this->getUrl('*/shippingmax_map/index', ['code' => $code]))));
		}
		else {
			$element->setValue(sprintf('<a href="%s">test front</a> / <a href="%s">test back</a> / <a href="%s">test tracking</a>',
				preg_replace('#/key/[^/]+/#', '/', $this->getUrl('shippingmax/map/index', ['code' => $code, '_store' => $this->getStoreId()])),
				preg_replace('#/key/[^/]+/#', '/', $this->getUrl('*/shippingmax_map/index', ['code' => $code])),
				str_replace(['{{num}}', '{{postcode}}'], ['123456789', '38000'], $track)
			));
		}

		return sprintf('<span id="%s">%s</span>', $element->getHtmlId(), $element->getValue());
	}

	private function getStoreId() {

		$store   = $this->getRequest()->getParam('store');
		$website = $this->getRequest()->getParam('website');

		if (!empty($store))
			$storeId = Mage::app()->getStore($store)->getId();
		else if (!empty($website))
			$storeId = Mage::getModel('core/website')->load($website)->getDefaultStore()->getId();
		else
			$storeId = Mage::app()->getDefaultStoreView()->getId();

		return $storeId;
	}
}