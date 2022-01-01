<?php
/**
 * Created V/26/04/2019
 * Updated L/25/10/2021
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

class Kyrena_Shippingmax_Block_Rewrite_Info extends Mage_Sales_Block_Order_Info {

	//protected function _construct() {
	//	$this->setModuleName('Mage_Sales');
	//}

	protected function _toHtml() {

		$order = $this->getOrder();
		$desc  = $order->getShippingDescription();

		$order->setShippingDescription('~!!!~');
		$html = str_replace('~!!!~', $this->getInfos(), parent::_toHtml());
		$order->setShippingDescription($desc);

		return $html;
	}

	public function getOrder() {
		return $this->getData('order') ?? Mage::registry('current_order');
	}

	public function getInfos(bool $showTitle = true, bool $showPrice = true, bool $showDelay = true, bool $showRelay = true, bool $showLnk = true) {

		$order = $this->getOrder();
		$help  = $this->helper('shippingmax');
		$html  = [];

		$delay = $order->getData('estimated_shipping_date');
		$point = Mage::getModel('shippingmax/details')->load($order->getId());
		$point = empty($point->getId()) ? null : @json_decode($point->getData('details'), true);

		if ($showTitle)
			$html[] = '<strong style="padding-right:0.5em; font-size:115%;">'.$order->getOrigData('shipping_description').'</strong> ';
		if ($showPrice)
			$html[] = '<span>'.$order->formatPrice($order->getShippingInclTax()).'</span> ';
		if ($showDelay && !empty($delay))
			$html[] = '<br />'.$delay;

		if ($showRelay && !empty($point)) {
			if ($showLnk) {
				$lnk1 = $help->getMapUrl($order->getId());
				$lnk2 = 'https://www.google.eu/maps/dir//'.$point['lat'].','.$point['lng'];
				$html[] = '<span class="info">'.
					'<br /><br />'.(empty($point['description']) ? '' : $help->formatDesc($point['description']).'<br /><br />').
					'<button type="button" class="slink" onclick="shippingmax.open(\''.$lnk1.'\');">'.$this->__('Show map').'</button> / <button type="button" class="slink" onclick="window.open(\''.$lnk2.'\');">'.$this->__('Go to the pick up station').'</button>'.
				'</span>';
			}
			else {
				$lnk2 = 'https://www.google.eu/maps/dir//'.$point['lat'].','.$point['lng'];
				$html[] = '<span class="info">'.
					'<br /><br />'.(empty($point['description']) ? '' : $help->formatDesc($point['description']).'<br /><br />').
					'<a href="'.$lnk2.'" target="_blank" style="position:relative; top:-8px; color:#6480FF;"><b>'.$this->__('Go to the pick up station').'</b></a>'.
				'</span>';
			}
		}

		return implode("\n", $html);
	}
}