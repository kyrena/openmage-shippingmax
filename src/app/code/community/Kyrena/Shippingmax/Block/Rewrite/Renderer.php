<?php
/**
 * Created J/04/06/2020
 * Updated M/28/09/2021
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

class Kyrena_Shippingmax_Block_Rewrite_Renderer extends Mage_Customer_Block_Address_Renderer_Default {

	public function render(Mage_Customer_Model_Address_Abstract $address, $output = null) {

		$translate = Mage::getSingleton('core/locale');

		// traitement spécial pour France + DROM/COM + Monaco
		$code    = strtoupper($address->getData('country_id'));
		$country = $translate->getCountryTranslation($code);

		if (in_array($code, $this->helper('shippingmax')->getFranceDromCom())) {

			$post4 = mb_substr($address->getData('postcode'), 0, 4);
			$post3 = mb_substr($post4, 0, 3);
			$post2 = mb_substr($post4, 0, 2);

			if ($post2 == '97') {
				$france = $translate->getCountryTranslation($code = 'FR');
				// BL 97133 Saint-Barthélemy (977 Antilles)
				if ($address->getData('postcode') == '97133')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'BL').')';
				// MF 97150 Saint-Martin (978 Antilles)
				else if ($post4 == '9715')
					$country = $france.' ('.str_replace(' (partie française)', '', $translate->getCountryTranslation($code = 'MF')).')';
				// GP 971XX Guadeloupe
				else if ($post3 == '971')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'GP').')';
				// MQ 972XX Martinique
				else if ($post3 == '972')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'MQ').')';
				// GF 973XX Guyane
				else if ($post3 == '973')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'GF').')';
				// RE 974XX La Réunion
				else if ($post3 == '974')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'RE').')';
				// PM 975XX Saint-Pierre-et-Miquelon
				else if ($post3 == '975')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'PM').')';
				// YT 976XX Mayotte
				else if ($post3 == '976')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'YT').')';
			}
			else if ($post2 == '98') {
				$france = $translate->getCountryTranslation($code = 'FR');
				// WF 986XX Wallis-et-Futuna
				if ($post3 == '986')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'WF').')';
				// PF 987XX Polynésie Française
				else if ($post3 == '987')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'PF').')';
				// NC 988XX Nouvelle-Calédonie
				else if ($post3 == '988')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'NC').')';
				// TF 984XX Terres australes françaises
				else if ($post3 == '984')
					$country = $france.' ('.$translate->getCountryTranslation($code = 'TF').')';
				// MC 980XX Monaco
				else if ($post3 == '980')
					$country = $translate->getCountryTranslation($code = 'MC');
			}
		}

		if ($output == 'country')
			return $code;

		if (mb_stripos(__FILE__, 'vendor/kyrena') === false)
			return ($output == 'telephone') ? $address->getData('telephone') : parent::render($address, $output);

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
					if (in_array($output, [null, 'html']) && Mage::app()->getStore()->isAdmin())
						$telephone .= ' ['.$phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::INTERNATIONAL).']';
				}
				else {
					$telephone = $phoneUtil->format($phoneNumb, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
				}
			}
			catch (Throwable $t) {
				Mage::logException($t);
			}
		}

		if ($output == 'telephone')
			return $telephone;

		// génération du template de l'adresse
		// https://github.com/adamlc/address-format
		$formatter = new \Adamlc\AddressFormat\Format();
		try {
			$formatter->setLocale($code);
		}
		catch (Throwable $t) {
			$formatter->setLocale('US');
		}

		$formatter->setAttribute('ORGANIZATION', '#{company}');
		$formatter->setAttribute('RECIPIENT', '#{name}');
		$formatter->setAttribute('STREET_ADDRESS', '#{street}');
		$formatter->setAttribute('POSTAL_CODE', '#{postcode}');
		$formatter->setAttribute('LOCALITY', '#{city}');
		if (!empty($address->getData('region')))
			$formatter->setAttribute('ADMIN_AREA', '#{region}');

		$template = $formatter->formatAddress()."\n#{country}\n#{telephone}\n#{fax}\n#{vat_id}";
		$output   = $this->getType()->getCode();

		if ($output == 'js_template')
			return str_replace(['#{name}', '#{street}'], ['#{prefix} #{firstname} #{middlename} #{lastname} #{suffix}', "#{street0}\n#{street1}\n#{street2}\n#{street3}"], $template);

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
			$address->getName(),
			implode("\n", $address->getStreet()),
			$address->getData('postcode'),
			$address->getData('city'),
			$address->getData('region'),
			$country,
			$telephone,
			$address->getData('fax'),
			$address->getData('vat_id')
		], $template));

		// final
		if ($output == 'html')
			return preg_replace("#\s*\n\s*#", "\n<br />", $result);
		if ($output == 'pdf')
			return preg_replace("#\s*\n\s*#", "|\n", $result);
		if ($output == 'oneline')
			return preg_replace("#\s*\n\s*#", ', ', $result);

		return $result;
	}
}