<?php
/**
 * Created J/04/06/2020
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

class Kyrena_Shippingmax_Block_Rewrite_Renderer extends Mage_Customer_Block_Address_Renderer_Default {

	protected $_translate;
	protected $_formatter = [];
	protected $_countryNames = [];

	protected function _construct() {
		$this->setModuleName('Mage_Customer');
	}

	protected function getCountryName($code) {

		if (empty($this->_countryNames[$code]))
			$this->_countryNames[$code] = $this->_translate->getCountryTranslation($code);

		return $this->_countryNames[$code];
	}

	public function render(Mage_Customer_Model_Address_Abstract $address, $output = null) {

		// traitement spécial pour France + DROM/COM + Monaco
		$code     = strtoupper($address->getData('country_id'));
		$postcode = $address->getData('postcode');
		$edit     = false;

		if (!empty($postcode) && in_array($code, $this->helper('shippingmax')->getFranceDromCom())) {

			$code  = 'FR';
			$post4 = mb_substr($postcode, 0, 4);
			$post3 = mb_substr($post4, 0, 3);
			$post2 = mb_substr($post4, 0, 2);

			if ($post2 == '97') {
				// BL 97133 Saint-Barthélemy (977 Antilles)
				if ($postcode == '97133') {
					$code = 'BL';
					$edit = true;
				}
				// MF 97150 Saint-Martin (978 Antilles)
				else if ($post4 == '9715') {
					$code = 'MF';
					$edit = true;
				}
				// GP 971XX Guadeloupe
				else if ($post3 == '971') {
					$code = 'GP';
					$edit = true;
				}
				// MQ 972XX Martinique
				else if ($post3 == '972') {
					$code = 'MQ';
					$edit = true;
				}
				// GF 973XX Guyane
				else if ($post3 == '973') {
					$code = 'GF';
					$edit = true;
				}
				// RE 974XX La Réunion
				else if ($post3 == '974') {
					$code = 'RE';
					$edit = true;
				}
				// PM 975XX Saint-Pierre-et-Miquelon
				else if ($post3 == '975') {
					$code = 'PM';
					$edit = true;
				}
				// YT 976XX Mayotte
				else if ($post3 == '976') {
					$code = 'YT';
					$edit = true;
				}
			}
			else if ($post2 == '98') {
				// WF 986XX Wallis-et-Futuna
				if ($post3 == '986') {
					$code = 'WF';
					$edit = true;
				}
				// PF 987XX Polynésie Française
				else if ($post3 == '987') {
					$code = 'PF';
					$edit = true;
				}
				// NC 988XX Nouvelle-Calédonie
				else if ($post3 == '988') {
					$code = 'NC';
					$edit = true;
				}
				// TF 984XX Terres australes françaises
				else if ($post3 == '984') {
					$code = 'TF';
					$edit = true;
				}
				// MC 980XX Monaco
				else if ($post3 == '980') {
					$code = 'MC';
					$edit = true;
				}
			}
		}

		// final simple
		if ($output == 'country')
			return $code;

		if (stripos(__FILE__, 'vendor/kyrena') === false) {

			if ($output == 'telephone')
				return $address->getData('telephone');

			if (!$this->getType())
				$this->setType(new Varien_Object());

			return parent::render($address, $output);
		}

		// pour la suite s'assure du code pays
		$code = empty($code) ? Mage::getStoreConfig('general/country/default') : $code;

		// formatage du numéro de téléphone
		// https://github.com/giggsey/libphonenumber-for-php
		$telephone = $address->getData('telephone');
		if (!empty($telephone)) {

			try {
				$phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
				$phoneNumb = $phoneUtil->parse($telephone, $code);

				if ($output == 'telephone') {
					$telephone = $phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::E164);
				}
				else if ($phoneUtil->getRegionCodeForNumber($phoneNumb) == $code) {
					$telephone = $phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::NATIONAL);
					if ((($this->getType() ? $this->getType()->getCode() : $output) == 'html') && Mage::app()->getStore()->isAdmin())
						$telephone = '<a href="tel://'.$phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::E164).'">'.$telephone.'</a> ['.$phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::INTERNATIONAL).']';
				}
				else {
					$telephone = $phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
					if ((($this->getType() ? $this->getType()->getCode() : $output) == 'html') && Mage::app()->getStore()->isAdmin())
						$telephone = '<a href="tel://'.$phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::E164).'">'.$phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::NATIONAL).'</a> ['.$telephone.']';
				}
			}
			catch (Throwable $t) {
				$telephone = $address->getData('telephone');
			}
		}

		if ($output == 'telephone')
			return $telephone;

		// génération du template de l'adresse
		// https://github.com/adamlc/address-format
		if (array_key_exists($code, $this->_formatter)) {
			$this->_formatter[$code]->clearAttributes();
		}
		else {
			$this->_formatter[$code] = new \Adamlc\AddressFormat\Format();
			try {
				$this->_formatter[$code]->setLocale($code);
			}
			catch (Throwable $t) {
				$this->_formatter[$code]->setLocale('US');
			}
		}

		$formatter = $this->_formatter[$code];
		$formatter->setAttribute('ORGANIZATION', '#{company}');
		$formatter->setAttribute('RECIPIENT', '#{name}');
		$formatter->setAttribute('STREET_ADDRESS', '#{street}');
		$formatter->setAttribute('POSTAL_CODE', '#{postcode}');
		$formatter->setAttribute('LOCALITY', '#{city}');
		if (!empty($address->getData('region')))
			$formatter->setAttribute('ADMIN_AREA', '#{region}');

		$template = $formatter->formatAddress()."\n#{country}\n#{telephone}\n#{fax}\n#{vat_id}";
		$output   = $this->getType() ? $this->getType()->getCode() : $output;

		if ($output == 'js_template')
			return str_replace(['#{name}', '#{street}'], ['#{prefix} #{firstname} #{middlename} #{lastname} #{suffix}', "#{street0}\n#{street1}\n#{street2}\n#{street3}"], $template);

		// formatage
		if (empty($this->_translate))
			$this->_translate = Mage::getSingleton('core/locale');

		$country = $edit ? $this->getCountryName('FR').' ('.$this->getCountryName($code).')' : $this->getCountryName($code);
		if ($code == 'MF')
			$country = str_replace(' (partie française)', '', $country);

		$result = trim(str_replace([
			'#{company}',
			'#{name}',
			'#{street}',
			'#{postcode}',
			'#{city}',
			'#{region}',
			'#{country}',
			'#{telephone}',
			'#{fax}',
			'#{vat_id}'
		], [
			$address->getData('company'),
			empty(Mage::registry('address_ignore_name')) ? $address->getName() : '',
			method_exists($address, 'getStreet') ? implode("\n", $address->getStreet()) : $address->getData('street'),
			$address->getData('postcode'),
			$address->getData('city'),
			$address->getData('region'),
			$country,
			$telephone,
			$address->getData('fax'),
			$address->getData('vat_id')
		], $template));

		// final complexe
		if ($output == 'html')
			return preg_replace("#\s*\n\s*#", "\n<br />", $result);
		if ($output == 'pdf')
			return preg_replace("#\s*\n\s*#", "|\n", $result);
		if ($output == 'oneline')
			return preg_replace("#\s*\n\s*#", ', ', $result);

		return $result;
	}
}