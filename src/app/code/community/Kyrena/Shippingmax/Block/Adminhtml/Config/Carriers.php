<?php
/**
 * Created M/31/03/2020
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Carriers extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_template = 'kyrena/shippingmax/carriers.phtml';

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$hid   = $element->getHtmlId();
		$scope = $element->getScopeLabel();
		$items = Mage::getSingleton('shipping/config')->getAllCarriers();
		$data  = [];

		foreach ($items as $code => $carrier) {

			//if (strncmp($code, 'paypal', 6) === 0)
			//	continue;

			$data[$code] = [
				'id'          => str_replace('dynamic_fields', 'remove_'.$code, $hid),
				'code'        => $code,
				'value'       => Mage::getStoreConfig('carriers/shippingmax/remove_'.$code),
				'scope_label' => $scope,
			];
		}

		ksort($data);
		$this->setData('config', $data);
		$this->setGroup('shippingmax');

		return $this->toHtml();
	}
}