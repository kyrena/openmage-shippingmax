<?php
/**
 * Created V/12/04/2019
 * Updated J/23/12/2021
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

class Kyrena_Shippingmax_Block_Selected extends Mage_Checkout_Block_Onepage_Shipping_Method_Available {

	public function getItem(string $code, $rate = null) {

		// Mage_Checkout_Block_Onepage_Shipping_Method_Available ($this)
		// Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form
		$object  = Mage::app()->getStore()->isAdmin() ? Mage::getBlockSingleton('adminhtml/sales_order_create_shipping_method_form') : $this;
		$address = $object->getAddress();
		$country = $address->getData('country_id');

		// récupère le point relais sélectionné
		$help = $this->helper('shippingmax');
		$data = $help->getSession()->getData($code);

		// récupère depuis les dernières commandes
		if (empty($data['item']) && is_object($rate) && (empty($data['from_orders']) || ($data['from_orders'] != $country)))
			$data = $help->getItemFromLastOrder($code, $country, $rate);

		if (empty($data['item']))
			return false;

		// n'autorise pas le changement de pays
		// sauf pour Monaco qui est considéré comment France avec Mondial Relay
		if (($data['item']['country_id'] != $country) && !(($code == 'shippingmax_mondialrelay') && ($data['item']['country_id'] == 'FR') && ($country == 'MC')))
			return false;

		$countries = $help->getCarrierCountries($code);
		return in_array($data['item']['country_id'], $countries) ? $data['item'] : false;
	}

	public function getRateByCode(string $code) {

		// Mage_Checkout_Block_Onepage_Shipping_Method_Available ($this)
		// Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form
		$object = Mage::app()->getStore()->isAdmin() ? Mage::getBlockSingleton('adminhtml/sales_order_create_shipping_method_form') : $this;
		$groups = $object->getShippingRates();

		foreach ($groups as $rates) {
			foreach ($rates as $rate) {
				if (mb_stripos($rate->getCode(), $code.'_') !== false)
					return $rate;
			}
		}

		return false;
	}

	public function getIsSelected(object $rate) {

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