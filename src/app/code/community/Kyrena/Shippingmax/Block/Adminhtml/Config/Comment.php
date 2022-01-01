<?php
/**
 * Created V/21/05/2021
 * Updated J/09/12/2021
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Comment extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	protected static $svg = [
		'shippingmax_boxberry'       => 'ic-logo-boxberry.svg',
		'shippingmax_boxberrycash'   => 'ic-logo-boxberry.svg',
		'shippingmax_boxberryhome'   => 'ic-logo-boxberry.svg',
		'shippingmax_chronorelais'   => 'ic-logo-chronopost.svg',
		'shippingmax_colisprivdom'   => 'ic-logo-colisprive.svg',
		'shippingmax_colisprivpts'   => 'ic-logo-colisprive.svg',
		'shippingmax_dhldestand'     => 'ic-logo-dhl.svg',
		'shippingmax_dpdfrrelais'    => 'ic-logo-dpd.svg',
		'shippingmax_fivepost'       => 'ic-logo-fivepost.svg',
		'shippingmax_fivepostcash'   => 'ic-logo-fivepost.svg',
		'shippingmax_glsdeeurob'     => 'ic-logo-gls.svg',
		'shippingmax_glsplstand'     => 'ic-logo-gls.svg',
		'shippingmax_glsplstandcash' => 'ic-logo-gls.svg',
		'shippingmax_inpospacit'     => 'ic-logo-inpost.svg',
		'shippingmax_inpospacuk'     => 'ic-logo-inpost.svg',
		'shippingmax_inpospaczk'     => 'ic-logo-inpost.svg',
		'shippingmax_mondialrelay'   => 'ic-logo-mondialrelay.svg',
		'shippingmax_pickpoint'      => 'ic-logo-pickpoint.svg',
		'shippingmax_pickpointcash'  => 'ic-logo-pickpoint.svg',
		'shippingmax_pocztk48Op'     => 'ic-logo-pocztex.svg',
		'shippingmax_pocztk48st'     => 'ic-logo-pocztex.svg',
		'shippingmax_pocztpecom'     => 'ic-logo-pocztex.svg',
		'shippingmax_przesodbpk'     => 'ic-logo-packeta.svg',
		'shippingmax_przesodbpkcash' => 'ic-logo-packeta.svg',
		'shippingmax_przesstand'     => 'ic-logo-packeta.svg',
		'shippingmax_przesstandcash' => 'ic-logo-packeta.svg',
		'shippingmax_shiptor'        => 'ic-logo-shiptor.svg',
		'shippingmax_shiptorcash'    => 'ic-logo-shiptor.svg',
		'shippingmax_shiptorhome'    => 'ic-logo-shiptor.svg',
		//'shippingmax_' => 'ic-logo-colissimo.svg',
	];

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$html = [];
		$help = $this->helper('shippingmax');
		$code = (string) str_replace('carriers_', '', $element->getId()); // (yes)

		$storeId    = $this->getStoreId();
		$maxAmounts = Mage::getStoreConfig('carriers/'.$code.'/max_amounts');
		$maxWeight  = Mage::getStoreConfig('carriers/'.$code.'/max_weight');

		$defaultCountry = Mage::getStoreConfig('general/country/default', $storeId);
		$allCountries   = Mage::getStoreConfig('carriers/'.$code.'/allowedcountry', $storeId);
		$allCountries   = empty($allCountries) ? [] : array_filter(explode(',', $allCountries));
		$selCountries   = $help->getCarrierCountries($code, $storeId);

		// pays du mode de livraison
		// fait la liste, si elle est vide, c'est que tous les pays sont autorisés
		$html['all'][] = $this->__('Allowed countries for this method:').' ';
		foreach ($allCountries as $country) {

			$name = Mage::getModel('directory/country')->loadByCode($country)->getName();
			$key  = strtolower($country);

			if (!empty($maxAmounts[$key]['amount'])) {
				$max = $help->getNumber($maxAmounts[$key]['amount'], ['precision' => 2]);
				if ($country == $defaultCountry)
					$html['all'][] = '<u><span title="'.addslashes($this->__('Default Country')).' - '.$name.', max '.$max.' '.$maxAmounts[$key]['currency'].'">'.$country.'</span></u>';
				else
					$html['all'][] = '<span title="'.$name.', max '.$max.' '.$maxAmounts[$key]['currency'].'">'.$country.'</span>';
			}
			else if ($country == $defaultCountry) {
				$html['all'][] = '<u><span title="'.addslashes($this->__('Default Country')).' - '.$name.'">'.$country.'</span></u>';
			}
			else {
				$html['all'][] = '<span title="'.$name.'">'.$country.'</span>';
			}
		}

		if (empty($allCountries)) {
			$html['all'][] = '<a href="'.$this->getUrl('*/*/*', ['section' => 'general', 'store' => $this->getRequest()->getParam('store'), 'website' => $this->getRequest()->getParam('website')]).'">'.$this->__('All Allowed Countries').'</a>';
		}

		// pays possibles pour les clients
		// fait la liste, si elle est vide, c'est qu'aucun pays n'est autorisé
		$html['sel'][] = $this->__('Allowed countries for customers:').' ';
		foreach ($selCountries as $country) {

			$name = Mage::getModel('directory/country')->loadByCode($country)->getName();
			$key  = strtolower($country);

			if (!empty($maxAmounts[$key]['amount'])) {
				$max = $help->getNumber($maxAmounts[$key]['amount'], ['precision' => 2]);
				if ($country == $defaultCountry)
					$html['sel'][] = '<u title="'.addslashes($this->__('Default Country')).'"><strong>'.$country.'</strong>&nbsp;<em>('.$name.', max '.$max.'&nbsp;'.$maxAmounts[$key]['currency'].')</em></u>';
				else
					$html['sel'][] = '<strong>'.$country.'</strong>&nbsp;<em>('.$name.', max '.$max.'&nbsp;'.$maxAmounts[$key]['currency'].')</em>';
			}
			else if ($country == $defaultCountry) {
				$html['sel'][] = '<u title="'.addslashes($this->__('Default Country')).'"><strong>'.$country.'</strong>&nbsp;<em>('.$name.')</em></u>';
			}
			else {
				$html['sel'][] = '<strong>'.$country.'</strong>&nbsp;<em>('.$name.')</em>';
			}
		}

		if (empty($selCountries)) {
			$html['sel'][] = '<a href="'.$this->getUrl('*/*/*', ['section' => 'general', 'store' => $this->getRequest()->getParam('store'), 'website' => $this->getRequest()->getParam('website')]).'">'.$this->__('None').'</a>';
		}

		// final
		$this->_html = str_replace(': ,', ': ',
			'<div class="comment shippingmax">'.
				(array_key_exists($code, self::$svg) ? '<img src="'.$this->getSkinUrl('images/kyrena/shippingmax/'.self::$svg[$code]).'" alt="" class="shippingmax logo" />' : '').
				((($code == 'shippingmax_storelocator') || (stripos($code, 'shippingmax_') === false)) ? '' : '<p>'.$this->__('Maximum weight: %d kg.', empty($maxWeight) ? 30 : $maxWeight).'</p>').
				'<p>'.preg_replace('#[.,]00[[:>:]]#', '', implode(', ', $html['all'])).'.</p>'.
				'<p>'.preg_replace('#[.,]00[[:>:]]#', '', implode(', ', $html['sel'])).'.</p>'.
			'</div>');

		// marquage
		$element->setLegend($element->getData('legend').((in_array($defaultCountry, $selCountries) && Mage::getStoreConfigFlag('carriers/'.$code.'/active', $storeId)) ? ' *' : ''));

		return parent::render($element);
	}

	protected function _getHeaderCommentHtml($element) {
		return $this->_html;
	}

	protected function getStoreId() {

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