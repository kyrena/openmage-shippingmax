<?php
/**
 * Created V/12/04/2019
 * Updated S/31/07/2021
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

class Kyrena_Shippingmax_Block_Selected extends Mage_Checkout_Block_Onepage_Shipping_Method_Available {

	public function getItem($code) {

		$data = $this->helper('shippingmax')->getSession()->getData($code);
		if (empty($data['item']))
			return false;

		// Mage_Checkout_Block_Onepage_Shipping_Method_Available ($this)
		// Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form
		$object = Mage::app()->getStore()->isAdmin() ? Mage::getBlockSingleton('adminhtml/sales_order_create_shipping_method_form') : $this;
		if ($data['item']['country_id'] != $object->getAddress()->getData('country_id'))
			return false; // n'autorise pas le changement de pays

		$countries = Mage::helper('shippingmax')->getCarrierCountries($code);
		return in_array($data['item']['country_id'], $countries) ? $data['item'] : false;
	}

	public function getRate($code) {

		// Mage_Checkout_Block_Onepage_Shipping_Method_Available ($this)
		// Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form
		$object = Mage::app()->getStore()->isAdmin() ? Mage::getBlockSingleton('adminhtml/sales_order_create_shipping_method_form') : $this;
		$shippingRateGroups = $object->getShippingRates();

		foreach ($shippingRateGroups as $rates) {
			foreach ($rates as $rate) {
				if (mb_stripos($rate->getCode(), $code.'_') !== false)
					return $rate;
			}
		}

		return false;
	}

	public function getIsSelected($rate) {

		// Mage_Checkout_Block_Onepage_Shipping_Method_Available ($this)
		// Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form
		$value = Mage::app()->getStore()->isAdmin() ?
			Mage::getBlockSingleton('adminhtml/sales_order_create_shipping_method_form')->getShippingMethod() :
			$this->getAddressShippingMethod();

		return $rate->getCode() == $value;
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