<?php
/**
 * Created V/12/04/2019
 * Updated S/26/06/2021
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Help extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (mb_stripos($element->getHtmlId(), 'openmage') !== false)
			return sprintf('<p class="box" style="margin-top:16px;">%s</p>', $element->getData('legend'));

		$version = $this->helper('shippingmax')->getOwebiaVersion();
		if (mb_stripos($element->getHtmlId(), 'owebia') !== false) {
			if (empty(Mage::getConfig()->getNode('modules/Owebia_Shipping2/lite')))
				return sprintf('<p class="box" style="margin-top:16px;">%s %s <span class="f-right"><a href="%s">github.com/owebia</a></span></p>',
					'Owebia/Shipping', $version, 'https://github.com/owebia/magento1-module-advanced-shipping');
			else
				return sprintf('<p class="box" style="margin-top:16px;">%s %s %s <span class="f-right"><a href="%s">github.com/kyrena</a> + <a href="%s">github.com/owebia</a></span></p>',
					'Owebia/Shipping', $version.'-lite', '(without jquery/editor/phpparser/doc)',
					'https://github.com/kyrena/openmage-shippingmax',
					'https://github.com/owebia/magento1-module-advanced-shipping');
		}

		$version = $this->helper('shippingmax')->getVersion();
		if (!empty($element->getData('legend')))
			return sprintf('<p class="box" style="margin-top:16px;">%s %s &nbsp;|&nbsp; %s <span class="f-right"><a href="%s">github.com/kyrena</a></span></p>',
				'Kyrena/Shippingmax', $version, $element->getData('legend'), 'https://github.com/kyrena/openmage-shippingmax');

		$msg = $this->checkRewrites();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="f-right"><a href="%s">github.com/kyrena</a></span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%s</strong><br />%s</p>',
				'Kyrena/Shippingmax', $version, 'https://github.com/kyrena/openmage-shippingmax',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('There is conflict (<em>%s</em>).', $msg));

		$var = ini_get('max_input_vars');
		return sprintf('<p class="box">%s %s <span class="no-display" id="inptvars"></span> <span class="f-right"><a href="%s">github.com/kyrena</a></span></p>%s',
			'Kyrena/Shippingmax', $version, 'https://github.com/kyrena/openmage-shippingmax',
			'<script type="text/javascript">self.addEventListener("load", function () {'.
			' var nb = document.querySelectorAll("input, select, textarea").length, elem = document.getElementById("inptvars");'.
			' if ('.$var.' <= nb) {'.
			'  elem.innerHTML = " | ⚠ php:max_input_vars '.$var.' <= " + nb + " inputs";'.
			'  elem.setAttribute("class", "error");'.
			' }'.
			'});</script>');
	}

	private function checkRewrites() {

		$rewrites = [
			['block' => 'adminhtml/sales_order_view_tab_info'],
			['block' => 'customer/address_renderer_default'],
			['block' => 'sales/order_info'],
			['model' => 'owebia_shipping2/os2_data_address'],
			['model' => 'owebia_shipping2/Os2_data_address'],
			['model' => 'owebia_shipping2/Os2_Data_address'],
			['model' => 'owebia_shipping2/Os2_Data_Address'],
			['model' => 'sales/order_shipment_track'],
			['model' => 'sales_resource/quote_address_rate_collection']
		];

		foreach ($rewrites as $rewrite) {
			foreach ($rewrite as $type => $class) {
				if (($type == 'model') && (mb_stripos(Mage::getConfig()->getModelClassName($class), 'kyrena') === false))
					return $class;
				else if (($type == 'block') && (mb_stripos(Mage::getConfig()->getBlockClassName($class), 'kyrena') === false))
					return $class;
				else if (($type == 'helper') && (mb_stripos(Mage::getConfig()->getHelperClassName($class), 'kyrena') === false))
					return $class;
			}
		}

		return true;
	}
}