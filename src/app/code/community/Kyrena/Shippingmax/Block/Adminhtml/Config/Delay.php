<?php
/**
 * Created V/17/07/2020
 * Updated J/30/06/2022
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Delay extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	protected $_template = 'kyrena/shippingmax/delay.phtml';
	protected static $_config;

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (!Mage::getStoreConfigFlag('shippingmax_times/general/enabled'))
			return '';

		$code = substr($element->getHtmlId(), -2);
		if (empty(self::$_config))
			self::$_config = $this->getArrayConfig();

		if (!array_key_exists($code, self::$_config))
			return '';

		$this->setData('config', self::$_config[$code]);
		$this->setData('country', $code);
		$this->setElement($element);

		$html = $this->toHtml();
		$text = $code.' - '.Mage::getSingleton('core/locale')->getCountryTranslation($code).(str_contains($html, 'todo"') ? ' *' : '');
		if (($code != 'FR') && in_array($code, Mage::helper('shippingmax')->getFranceDromCom(true)))
			$text = 'FR - '.$text;

		if (!empty(self::$_config[$code]['franco']))
			$text .= ' <span style="float:right; padding-right:40px;">franco</span>';

		$element->setLegend(mb_convert_encoding('&#'.(127397 + ord($code[0])).';', 'UTF-8', 'HTML-ENTITIES').
			mb_convert_encoding('&#'.(127397 + ord($code[1])).';', 'UTF-8', 'HTML-ENTITIES').
			' &nbsp; '.$text);

		return preg_replace('#<table.*#', '<div class="grid">', $this->_getHeaderHtml($element)).$html.str_replace('</tbody></table>', '</div>', $this->_getFooterHtml($element));
	}

	protected function getArrayConfig() {

		$ids     = Mage::getResourceModel('core/store_collection')->setOrder('name', 'asc')->getColumnValues('store_id');
		$dromcom = $this->helper('shippingmax')->getFranceDromCom(false);
		$items   = [];

		$postcodes = [
			[null, null, null],
			['FR', '20600', '_20'],   // Corse (FR 20)
			['FR', '98000', '_9800'], // Monaco (FR/MC 98)
			['ES', '07199', '_07'],   // Baleares (07)
			['ES', '35572', '_35'],   // Las Palmas (35)
			['ES', '38296', '_38'],   // Santa Cruz de Tenerife (38)
			['ES', '51005', '_51'],   // Ceuta (51)
			['ES', '52006', '_52'],   // Melilla (52)
		];

		foreach ($ids as $storeId) {

			if (empty($storeId)) continue;

			$carriers  = Mage::getModel('shipping/config')->getActiveCarriers($storeId);
			$countries = explode(',', Mage::getStoreConfig('general/country/allow', $storeId));
			$franco    = 50000;

			foreach ($carriers as $code => $carrier) {

				foreach ($postcodes as [$postCountry, $postcode, $key]) {

					foreach ($countries as $country) {

						if (!empty($postCountry) && ($postCountry != $country))
							continue;

						// 1/ STANDARD
						$sort    = in_array($country, $dromcom) ? 'FR-'.$country : $country;
						$request = Mage::getModel('shipping/rate_request')
							->setOrigCountryId(Mage::getStoreConfig('shipping/origin/country_id', $storeId))
							->setDestCountryId($country)
							->setDestPostcode($postcode)
							->setBaseCurrency(Mage::getStoreConfig('currency/options/base', $storeId))
							->setPackageCurrency(Mage::getStoreConfig('currency/options/default', $storeId))
							->setAllItems([]);

						$result = $carrier->checkAvailableShipCountries($request);
						if (is_object($result)) {
							if ($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) {
								$rates = $result->collectRates($request)->getAllRates();
								foreach ($rates as $rate) {
									if (empty($items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key])) {
										$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
									}
									else {
										$price = $this->getCarrierPrice($rate, $storeId);
										$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key]['price'][$price] = $price;
									}
								}
							}
							else if (!($result instanceof Mage_Shipping_Model_Rate_Result_Error)) {
								$rates = $carrier->collectRates($request)->getAllRates();
								foreach ($rates as $rate) {
									if (empty($items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key])) {
										$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
									}
									else {
										$price = $this->getCarrierPrice($rate, $storeId);
										$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key]['price'][$price] = $price;
									}
								}
							}
						}

						// 2/ réessaye avec des codes postaux trouvés dans la config du mode de livraison
						// car certains modes de livraisons sont limités à certains codes postaux
						$cps = Mage::getStoreConfig('carriers/'.$code.(str_contains($code, 'owebiashipping') ? '/config' : '/owebia_config'), $storeId);
						$cps = Mage::getModel('shippingmax/configparser')->init($cps, true)->extractAllPostcodes($country);

						foreach ($cps as $grp) {
							foreach ($grp as $cp) {
								if (str_contains($cp, '*'))
									continue;
								$result = $carrier->checkAvailableShipCountries($request->setDestPostcode($cp));
								if (is_object($result)) {
									if ($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) {
										$rates = $result->collectRates($request)->getAllRates();
										foreach ($rates as $rate) {
											if (empty($items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key])) {
												$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
											}
											else {
												$price = $this->getCarrierPrice($rate, $storeId);
												$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key]['price'][$price] = $price;
											}
										}
										break; // un seul code postal suffira
									}
									if (!($result instanceof Mage_Shipping_Model_Rate_Result_Error)) {
										$rates = $carrier->collectRates($request)->getAllRates();
										foreach ($rates as $rate) {
											if (empty($items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key])) {
												$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
											}
											else {
												$price = $this->getCarrierPrice($rate, $storeId);
												$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key]['price'][$price] = $price;
											}
										}
										break; // un seul code postal suffira
									}
								}
							}
						}

						// 3/ FRANCO
						// réessaye pour le franco à partir de
						$request = Mage::getModel('shipping/rate_request')
							->setOrigCountryId(Mage::getStoreConfig('shipping/origin/country_id', $storeId))
							->setDestCountryId($country)
							->setDestPostcode($postcode)
							->setAllItems([
								Mage::getModel('sales/quote_item')->addData([
									'product' => Mage::getModel('catalog/product')->setData('price', $franco),
									'free_shipping' => true,
									'base_row_total' => $franco,
									'base_row_total_incl_tax' => $franco,
									'base_original_price' => $franco,
									'price_incl_tax' => $franco,
								])
							])
							->setPackageValue($franco)
							->setPackageValueWithDiscount($franco)
							->setPackagePhysicalValue($franco)
							->setBaseSubtotalInclTax($franco)
							->setBaseCurrency(Mage::getStoreConfig('currency/options/base', $storeId))
							->setPackageCurrency(Mage::getStoreConfig('currency/options/default', $storeId))
							->setFreeShipping(true);

						$result = $carrier->checkAvailableShipCountries($request);
						if (is_object($result)) {
							if (($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) && (count($rates = $result->resetParser()->collectRates($request)->getAllRates()) > 0)) {
								foreach ($rates as $rate) {
									if ($rate->getPrice() == 0) {
										$items[$country]['franco'][$code] = $code;
										$short = substr($rate->getData('method'), 0, strrpos($rate->getData('method'), '_')).$key;
										if (empty($items[$country][$rate->getData('carrier')][$sort][$short])) {
											$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
										}
										else {
											$price = $this->getCarrierPrice($rate, $storeId);
											$items[$country][$rate->getData('carrier')][$sort][$short]['price'][$price] = $price;
										}
									}
								}
							}
							else if (!($result instanceof Mage_Shipping_Model_Rate_Result_Error) && (count($rates = $carrier->collectRates($request)->getAllRates()) > 0)) {
								foreach ($rates as $rate) {
									if ($rate->getPrice() == 0) {
										$items[$country]['franco'][$code] = $code;
										$short = substr($rate->getData('method'), 0, strrpos($rate->getData('method'), '_')).$key;
										if (empty($items[$country][$rate->getData('carrier')][$sort][$short])) {
											$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
										}
										else {
											$price = $this->getCarrierPrice($rate, $storeId);
											$items[$country][$rate->getData('carrier')][$sort][$short]['price'][$price] = $price;
										}
									}
								}
							}
						}

						// 4/ réessaye avec des codes postaux trouvés dans la config du mode de livraison
						// car certains modes de livraisons sont limités à certains codes postaux
						foreach ($cps as $grp) {
							foreach ($grp as $cp) {
								if (str_contains($cp, '*'))
									continue;
								$result = $carrier->checkAvailableShipCountries($request->setDestPostcode($cp));
								if (is_object($result)) {
									if ($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) {
										$rates = $result->collectRates($request)->getAllRates();
										foreach ($rates as $rate) {
											$items[$country]['franco'][$code] = $code;
											$short = substr($rate->getData('method'), 0, strrpos($rate->getData('method'), '_')).$key;
											if (empty($items[$country][$rate->getData('carrier')][$sort][$short])) {
												$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
											}
											else {
												$price = $this->getCarrierPrice($rate, $storeId);
												$items[$country][$rate->getData('carrier')][$sort][$short]['price'][$price] = $price;
											}
										}
										break; // un seul code postal suffira
									}
									if (!($result instanceof Mage_Shipping_Model_Rate_Result_Error)) {
										$rates = $carrier->collectRates($request)->getAllRates();
										foreach ($rates as $rate) {
											$items[$country]['franco'][$code] = $code;
											$short = substr($rate->getData('method'), 0, strrpos($rate->getData('method'), '_')).$key;
											if (empty($items[$country][$rate->getData('carrier')][$sort][$short])) {
												$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
											}
											else {
												$price = $this->getCarrierPrice($rate, $storeId);
												$items[$country][$rate->getData('carrier')][$sort][$short]['price'][$price] = $price;
											}
										}
										break; // un seul code postal suffira
									}
								}
							}
						}
					}
				}
			}
		}

		return $items;
	}

	protected function getArrayData(object $rate, int $storeId, string $country, $postcode = null, $key = null) {

		$code  = $rate->getData('carrier').'_'.$rate->getData('method');
		$key   = empty($key) ? $code : $code.$key;
		$price = $this->getCarrierPrice($rate, $storeId);

		return [
			'code'     => $code,
			'name'     => ($rate->getData('carrier_title') != $rate->getData('method_title')) ?
				$rate->getData('carrier_title').' / '.$rate->getData('method_title') : $rate->getData('carrier_title'),
			'country'  => $country,
			'postcode' => $postcode,
			'name1min' => 'groups['.$country.'][fields][cnf1min_'.$key.'][value]',
			'name1max' => 'groups['.$country.'][fields][cnf1max_'.$key.'][value]',
			'name2min' => 'groups['.$country.'][fields][cnf2min_'.$key.'][value]',
			'name2max' => 'groups['.$country.'][fields][cnf2max_'.$key.'][value]',
			'name3'    => 'groups['.$country.'][fields][cnf3_'.$key.'][value]',
			'cnf1min'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1min_'.$key, $storeId),
			'cnf1max'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1max_'.$key, $storeId),
			'cnf2min'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2min_'.$key, $storeId),
			'cnf2max'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2max_'.$key, $storeId),
			'cnf3'     => Mage::getStoreConfigFlag('shippingmax_times/'.$country.'/cnf3_'.$key, $storeId),
			'price'    => [$price => $price],
		];
	}

	protected function getCarrierPrice(object $rate, int $storeId) {
		return Mage::getSingleton('core/locale')->currency(Mage::getStoreConfig('currency/options/default', $storeId))->toCurrency($rate->getPrice());
	}
}