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

class Kyrena_Shippingmax_Block_Adminhtml_Rewrite_Info extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Info {

	protected function _construct() {
		$this->setModuleName('Mage_Adminhtml');
	}

	protected function _toHtml() {

		$order = $this->getOrder();
		$desc  = $order->getShippingDescription();

		$order->setShippingDescription('~!!!~');
		$html = preg_replace('#<strong>~!!!~</strong>.+</fieldset>#sU', $this->getInfos().'<br /><i>'.$order->getData('shipping_method').'</i></fieldset>', parent::_toHtml());
		$order->setShippingDescription($desc);

		return $html;
	}

	public function getInfos(bool $showTitle = true, bool $showPrice = true, bool $showDelay = true, bool $showRelay = true, bool $showLnk = true) {

		$order = $this->getOrder();
		$help  = $this->helper('shippingmax');
		$html  = [];

		$delay = $order->getData('estimated_shipping_date');
		$point = Mage::getModel('shippingmax/details')->load($order->getId());
		$point = empty($point->getId()) ? null : @json_decode($point->getData('details'), true);

		if ($showTitle)
			$html[] = '<strong style="font-size:115%;">'.$order->getOrigData('shipping_description').'</strong> ';
		if ($showPrice)
			$html[] = '<span style="float:right;">'.$order->formatPrice($order->getShippingInclTax()).'</span> ';

		if ($showTitle) {
			$locale = Mage::getSingleton('core/translate')->getLocale();
			if (Mage::getStoreConfig('general/locale/code', $order->getStoreId()) != $locale) {

				$label = Mage::getStoreConfig('carriers/'.$help->getCarrierCode($order->getData('shipping_method')).'/title', 0);
				if ($label == $order->getOrigData('shipping_description'))
					$label = '';

				// essaye d'afficher le libellé du mode de livraison dans la langue de l'utilisateur du back-office
				$storeIds = array_filter(Mage::getResourceModel('core/store_collection')->getAllIds());
				foreach ($storeIds as $storeId) {
					if ($locale == Mage::getStoreConfig('general/locale/code', $storeId)) {
						$label = (empty($label) ? '' : $label.' / ').Mage::getStoreConfig('carriers/'.$help->getCarrierCode($order->getData('shipping_method')).'/title', $storeId);
						break;
					}
				}

				if (!empty($label) && ($label != $order->getOrigData('shipping_description')))
					$html[] = '<br />('.$label.') ';
			}
		}

		if ($showDelay && !empty($delay))
			$html[] = '<br />'.$delay;

		if ($showRelay && !empty($point)) {
			if ($showLnk) {
				$lnk1 = $help->getMapUrl($order->getId());
				$lnk2 = 'https://www.google.eu/maps/dir//'.$point['lat'].','.$point['lng'];
				$html[] = '<div class="info" style="margin-top:10px;">'.
					(empty($point['description']) ? '' : $help->formatDesc($point['description']).'<br />').
					'<button type="button" class="slink" onclick="shippingmax.open(\''.$lnk1.'\');">'.$this->__('Show map').'</button> / <button type="button" class="slink" onclick="window.open(\''.$lnk2.'\');">'.$this->__('Go to the pick up station').'</button>'.
				'</div>';
			}
			else {
				$lnk2 = 'https://www.google.eu/maps/dir//'.$point['lat'].','.$point['lng'];
				$html[] = '<div class="info" style="margin-top:10px;">'.
					(empty($point['description']) ? '' : $help->formatDesc($point['description']).'<br />').
					'<a href="'.$lnk2.'" target="_blank">'.$this->__('Go to the pick up station').'</a>'.
				'</div>';
			}
		}

		return implode("\n", $html);
	}
}