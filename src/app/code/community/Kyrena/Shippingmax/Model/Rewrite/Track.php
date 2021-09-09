<?php
/**
 * Created M/30/04/2019
 * Updated M/20/08/2019
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

class Kyrena_Shippingmax_Model_Rewrite_Track extends Mage_Sales_Model_Order_Shipment_Track {

	public function getNumberDetail() {

		$carrierInstance = Mage::getSingleton('shipping/config')->getCarrierInstance($this->getCarrierCode());
		if (empty($carrierInstance))
			return ['title' => $this->getTitle(), 'number' => $this->getTrackNumber()];

		$carrierInstance->setStore($this->getStore());
		$trackingInfo = $carrierInstance->getTrackingInfo($this->getNumber(), $this->getShipment()->getOrder());

		return empty($trackingInfo) ? Mage::helper('sales')->__('No detail for number "%s"', $this->getNumber()) : $trackingInfo;
	}
}
