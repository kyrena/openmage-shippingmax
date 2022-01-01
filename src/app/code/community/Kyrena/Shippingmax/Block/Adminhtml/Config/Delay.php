<?php
/**
 * Created V/17/07/2020
 * Updated V/08/10/2021
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

		$code = (string) substr($element->getHtmlId(), -2); // (yes)
		if (empty(self::$_config))
			self::$_config = $this->getArrayConfig();

		if (!array_key_exists($code, self::$_config))
			return '';

		$this->setData('config', self::$_config[$code]);
		$this->setData('country', $code);
		$this->setElement($element);

		$html = $this->toHtml();
		$text = $code.' - '.Mage::getSingleton('core/locale')->getCountryTranslation($code).((strpos($html, 'todo"') === false) ? '' : ' *');
		if (($code != 'FR') && in_array($code, Mage::helper('shippingmax')->getFranceDromCom(true)))
			$text = 'FR - '.$text;

		$element->setLegend($text);

		return preg_replace('#<table.*#', '<div class="grid">', $this->_getHeaderHtml($element)).$html.str_replace('</tbody></table>', '</div>', $this->_getFooterHtml($element));
	}

	protected function getArrayConfig() {

		$ids     = Mage::getResourceModel('core/store_collection')->setOrder('name', 'asc')->getColumnValues('store_id');
		$dromcom = $this->helper('shippingmax')->getFranceDromCom(false);
		$items   = [];

		$postcodes = [
			['FR', '20600', '20'],   // Corse (FR 20)
			['FR', '98000', '9800'], // Monaco (FR/MC 98)
			['ES', '07199', '07'],   // Baleares (07)
			['ES', '35572', '35'],   // Las Palmas (35)
			['ES', '38296', '38'],   // Santa Cruz de Tenerife (38)
			['ES', '51005', '51'],   // Ceuta (51)
			['ES', '52006', '52'],   // Melilla (52)
		];

		foreach ($ids as $storeId) {

			if (empty($storeId)) continue;

			$carriers  = Mage::getModel('shipping/config')->getActiveCarriers($storeId);
			$countries = explode(',', Mage::getStoreConfig('general/country/allow', $storeId));

			foreach ($carriers as $code => $carrier) {

				foreach ($countries as $country) {

					$sort    = in_array($country, $dromcom) ? 'FR-'.$country : $country;
					$request = Mage::getModel('shipping/rate_request')
						->setOrigCountryId(Mage::getStoreConfig('shipping/origin/country_id', $storeId))
						->setDestCountryId($country)
						->setAllItems([]);

					$result = $carrier->checkAvailableShipCountries($request);
					if (is_object($result)) {
						if ($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) {
							$rates = $result->collectRates($request)->getAllRates();
							foreach ($rates as $rate)
								$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method')] = $this->getArrayData($rate, $storeId, $country);
						}
						else if (!($result instanceof Mage_Shipping_Model_Rate_Result_Error)) {
							$rates = $carrier->collectRates($request)->getAllRates();
							foreach ($rates as $rate)
								$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method')] = $this->getArrayData($rate, $storeId, $country);
						}
					}

					// réessaye avec des codes postaux trouvés dans la config du mode de livraison
					if ($code == 'shippingmax_colisprivdom') {
						$cps = ($country == 'FR') ? ['all' => [75001, 75002, 69001, 69002, 38000]] : [];
					}
					else if (in_array($code, ['shippingmax_boxberryhome', 'shippingmax_boxberryhomecash'])) {
						$cps = ($country == 'RU') ? ['all' => [181270, 192212, 307200, 350009, 350010, 350011, 350012, 460014, 460015]] : [];
					}
					else {
						$cps = Mage::getStoreConfig('carriers/'.$code.((strpos($code, 'owebiashipping') === false) ? '/owebia_config' : '/config'), $storeId);
						$cps = Mage::getModel('shippingmax/configparser')->init($cps, true)->extractAllPostcodes($country);
					}

					if (!empty($cps)) {
						foreach ($cps as $grp) {
							foreach ($grp as $cp) {
								if (strpos($cp, '*') !== false)
									continue;
								$result = $carrier->checkAvailableShipCountries($request->setDestPostcode($cp));
								if (is_object($result)) {
									if ($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) {
										$rates = $result->collectRates($request)->getAllRates();
										foreach ($rates as $rate)
											$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method')] = $this->getArrayData($rate, $storeId, $country);
										break;
									}
									if (!($result instanceof Mage_Shipping_Model_Rate_Result_Error)) {
										$rates = $carrier->collectRates($request)->getAllRates();
										foreach ($rates as $rate)
											$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method')] = $this->getArrayData($rate, $storeId, $country);
										break;
									}
								}
							}
						}
					}
				}

				foreach ($postcodes as [$country, $postcode, $key]) {

					if (!in_array($country, $countries))
						continue;

					$sort    = in_array($country, $dromcom) ? 'FR-'.$country : $country;
					$request = Mage::getModel('shipping/rate_request')
						->setOrigCountryId(Mage::getStoreConfig('shipping/origin/country_id', $storeId))
						->setDestCountryId($country)
						->setDestPostcode($postcode)
						->setAllItems([]);

					$result = $carrier->checkAvailableShipCountries($request);
					if (is_object($result)) {
						if ($result instanceof Owebia_Shipping2_Model_Carrier_Abstract) {
							$rates = $result->collectRates($request)->getAllRates();
							foreach ($rates as $rate)
								$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').'_'.$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
						}
						else if (get_class($result) != get_class(Mage::getModel('shipping/rate_result_error'))) {
							$rates = $carrier->collectRates($request)->getAllRates();
							foreach ($rates as $rate)
								$items[$country][$rate->getData('carrier')][$sort][$rate->getData('method').'_'.$key] = $this->getArrayData($rate, $storeId, $country, $postcode, $key);
						}
					}
				}
			}
		}

		return $items;
	}

	protected function getArrayData(object $rate, int $storeId, string $country, $postcode = null, $ckey = null) {

		$code = $rate->getData('carrier').'_'.$rate->getData('method');
		$ckey = empty($ckey) ? $code : $code.'_'.$ckey;

		return [
			'code'     => $code,
			'name'     => ($rate->getData('carrier_title') != $rate->getData('method_title')) ?
				$rate->getData('carrier_title').' / '.$rate->getData('method_title') : $rate->getData('carrier_title'),
			'country'  => $country,
			'postcode' => $postcode,
			'name1min' => 'groups['.$country.'][fields][cnf1min_'.$ckey.'][value]',
			'name1max' => 'groups['.$country.'][fields][cnf1max_'.$ckey.'][value]',
			'name2min' => 'groups['.$country.'][fields][cnf2min_'.$ckey.'][value]',
			'name2max' => 'groups['.$country.'][fields][cnf2max_'.$ckey.'][value]',
			'name3'    => 'groups['.$country.'][fields][cnf3_'.$ckey.'][value]',
			'cnf1min'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1min_'.$ckey, $storeId),
			'cnf1max'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1max_'.$ckey, $storeId),
			'cnf2min'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2min_'.$ckey, $storeId),
			'cnf2max'  => Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2max_'.$ckey, $storeId),
			'cnf3'     => Mage::getStoreConfigFlag('shippingmax_times/'.$country.'/cnf3_'.$ckey, $storeId)
		];
	}
}