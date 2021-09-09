<?php
/**
 * Created V/12/04/2019
 * Updated S/04/09/2021
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

class Kyrena_Shippingmax_Helper_Data extends Mage_Core_Helper_Abstract {

	public const ADMIN_SESSION_NAME = 'adminhtml/session_quote';
	public const FRONT_SESSION_NAME = 'checkout/session';

	public function getVersion() {
		return (string) Mage::getConfig()->getModuleConfig('Kyrena_Shippingmax')->version;
	}

	public function getOwebiaVersion() {
		return (string) Mage::getConfig()->getModuleConfig('Owebia_Shipping2')->version;
	}

	public function _(string $data, ...$values) {
		$text = $this->__(' '.$data, ...$values);
		return ($text[0] == ' ') ? $this->__($data, ...$values) : $text;
	}

	public function escapeEntities($data, bool $quotes = false) {
		return htmlspecialchars($data, $quotes ? ENT_SUBSTITUTE | ENT_COMPAT : ENT_SUBSTITUTE | ENT_NOQUOTES);
	}

	public function getFranceDromCom(bool $fr = true) {
		return [$fr ? 'FR' : '', $fr ? 'MC' : '', 'BL','GF','GP','MF','MQ','NC','PF','PM','RE','TF','WF','YT'];
	}

	public function getNumericPostcodeCountries() {
		return array_merge(['RU','AT','BE','CH','ES','IT','LU'], $this->getFranceDromCom());
	}

	public function isSpecial(string $value) {

		$codes = array_keys(Mage::getConfig()->getNode('global/shippingmax/maps')->asArray());

		foreach ($codes as $code) {
			if ((stripos($value, $code) !== false) && (stripos($value, 'home') === false))
				return true;
		}

		return false;
	}

	public function withDaData(string $value) {

		$codes = array_keys(Mage::getConfig()->getNode('global/shippingmax/dadata')->asArray());

		foreach ($codes as $code) {
			if (stripos($value, $code) !== false)
				return true;
		}

		return false;
	}

	public function getShippingDate(string $code, bool $days = false, $country = null, $postcode = null, $storeId = null) {

		if (empty($country)) {
			$address  = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress();
			$country  = $address->getData('country_id');
			$postcode = $address->getData('postcode');
			if (empty($country))
				return null;
		}

		// recherche du vrai pays surtout pour la France et ses DROM/COM
		$fake    = Mage::getModel('customer/address')->setData('country_id', $country)->setData('postcode', $postcode);
		$country = Mage::getBlockSingleton('shippingmax/rewrite_renderer')->setType(new Varien_Object())->render($fake, 'country');
		$storeId = is_null($storeId) ? Mage::app()->getStore()->getId() : $storeId;

		// recherche des délais
		// pour shippingmax_chronorelais_cyz_xyz puis pour shippingmax_chronorelais_xyz
		foreach ([$code, mb_substr($code, 0, mb_strrpos($code, '_'))] as $rateCode) {

			if (empty($postcode)) {
				// délai sans postcode
				$key     = $rateCode;
				$cnf1min = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1min_'.$key, $storeId);
				$cnf1max = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1max_'.$key, $storeId);
				$cnf2min = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2min_'.$key, $storeId);
				$cnf2max = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2max_'.$key, $storeId);
				$cnf3    = Mage::getStoreConfigFlag('shippingmax_times/'.$country.'/cnf3_'.$key, $storeId);
				if (!empty($cnf2min))
					break;
			}
			else {
				$nb = 6;
				while (--$nb >= 2) {
					// délai avec postcode
					$key     = $rateCode.'_'.mb_substr(trim($postcode), 0, $nb);
					$cnf1min = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1min_'.$key, $storeId);
					$cnf1max = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1max_'.$key, $storeId);
					$cnf2min = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2min_'.$key, $storeId);
					$cnf2max = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2max_'.$key, $storeId);
					$cnf3    = Mage::getStoreConfigFlag('shippingmax_times/'.$country.'/cnf3_'.$key, $storeId);
					if (!empty($cnf2min))
						break 2;
				}
				if (empty($cnf2min)) {
					// délai sans postcode
					$key     = $rateCode;
					$cnf1min = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1min_'.$key, $storeId);
					$cnf1max = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf1max_'.$key, $storeId);
					$cnf2min = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2min_'.$key, $storeId);
					$cnf2max = (int) Mage::getStoreConfig('shippingmax_times/'.$country.'/cnf2max_'.$key, $storeId);
					$cnf3    = Mage::getStoreConfigFlag('shippingmax_times/'.$country.'/cnf3_'.$key, $storeId);
					if (!empty($cnf2min))
						break;
				}
			}
		}

		// pas de délai
		if (empty($cnf2min))
			return null;

		// nombre de jours de livraison
		if ($days) {
			if ($cnf2min == $cnf2max)
				return ($cnf2min > 1) ? $this->__('%d days', $cnf2min) : $this->__('%d day', 1);
			return $this->__('%d/%d days', $cnf2min, $cnf2max);
		}

		// calcul les dates de livraison par rapport aux délais trouvés
		// de 1 (pour Lundi) à 7 (pour Dimanche)
		$dateFrom = Mage::getSingleton('core/locale')->date();
		$dateTo   = Mage::getSingleton('core/locale')->date();
		$today    = $dateFrom->toString(Zend_Date::WEEKDAY_8601);

		if ($today == 6) { // pas d'expédition le samedi
			$addMin = $cnf1min + $cnf2min + 1;
			$addMax = $cnf1min + $cnf2max + 1;
		}
		else if ($today == 7) { // pas d'expédition le dimanche
			$addMin = $cnf1min + $cnf2min + 1;
			$addMax = $cnf1min + $cnf2max + 1;
		}
		else if ($dateFrom->toString(Zend_Date::HOUR) < (int) Mage::getStoreConfig('shippingmax_times/general/cutoff', $storeId)) {
			$addMin = $cnf1min + $cnf2min;
			$addMax = $cnf1min + $cnf2max;
		}
		else {
			$addMin = $cnf1max + $cnf2min;
			$addMax = $cnf1max + $cnf2max;
		}

		while ($addMin > 0) {
			$dateFrom->addDay(1);
			if ($this->isClosedDay($dateFrom, $country, $postcode)) // pas de livraison les jours fériés
				continue;
			else if ($cnf3 && ($dateFrom->toString(Zend_Date::WEEKDAY_8601) == 6)) // livraison samedi
				$addMin--;
			else if ($dateFrom->toString(Zend_Date::WEEKDAY_8601) > 5) // pas de livraison samedi ou dimanche
				continue;
			else
				$addMin--;
		}

		while ($addMax > 0) {
			$dateTo->addDay(1);
			if ($this->isClosedDay($dateTo, $country, $postcode)) // pas de livraison les jours fériés
				continue;
			else if ($cnf3 && ($dateTo->toString(Zend_Date::WEEKDAY_8601) == 6)) // livraison samedi
				$addMax--;
			else if ($dateTo->toString(Zend_Date::WEEKDAY_8601) > 5) // pas de livraison samedi ou dimanche
				continue;
			else
				$addMax--;
		}

		if ($dateFrom == $dateTo)
			return $this->__('Estimated delivery: %s', $dateFrom->toString(Zend_Date::WEEKDAY).' '.$dateFrom->toString(Zend_Date::DATE_SHORT));

		return $this->__('Estimated delivery: from %s to %s',
			$dateFrom->toString(Zend_Date::WEEKDAY).' '.$dateFrom->toString(Zend_Date::DATE_SHORT),
			$dateTo->toString(Zend_Date::WEEKDAY).' '.$dateTo->toString(Zend_Date::DATE_SHORT));
	}

	public function isClosedDay(object $date, string $country, $postcode = null) {

		if (mb_stripos(__FILE__, 'vendor/kyrena') === false)
			return false;

		// https://github.com/azuyalabs/yasumi
		try {
			//$holidays = \Yasumi\Yasumi::createByISO3166_2($country, date('Y'));
			if (empty($this->_yasumiProviders))
				$this->_yasumiProviders = \Yasumi\Yasumi::getProviders();
			if (!array_key_exists($country, $this->_yasumiProviders))
				return false;
			if (empty($this->_yasumiTranslations))
				$this->_yasumiTranslations = new \Yasumi\Translations(['en_US']);

			$holidays = '\\Yasumi\\Provider\\'.$this->_yasumiProviders[$country];
			$holidays = new $holidays(date('Y'), 'en_US', $this->_yasumiTranslations);
			//$holidays->addHoliday(new \Yasumi\Holiday('testDay', [], new DateTime('2021-08-14'), 'en_US', \Yasumi\Holiday::TYPE_OFFICIAL));
			return $holidays->isHoliday(new DateTime($date->toString('c')));
		}
		catch (Throwable $t) {
			return false;
		}
	}

	public function getCarrierCountries(string $code, $storeId = null) {

		$allCountries = array_filter(explode(',', Mage::getStoreConfig('general/country/allow', $storeId)));
		$selCountries = array_filter(explode(',', Mage::getStoreConfig('carriers/'.$code.'/specificcountry', $storeId)));
		$countries    = empty($selCountries) ? $allCountries : array_intersect($allCountries, $selCountries);

		$config = Mage::getStoreConfig('carriers/'.$code.((strpos($code, 'owebiashipping') === false) ? '/owebia_config' : '/config'), $storeId);
		if (mb_stripos($config, '"shipto"') !== false) {
			$config = Mage::getSingleton('shippingmax/addressfilter')->substitute($config);
			return Mage::getModel('shippingmax/configparser')->init($config, true)->filterCountries($countries);
		}

		return $countries;
	}

	public function getSession(bool $string = false) {

		// pour le checkout/onepage du front-office ou pour la création de commande du back-office
		$name = Mage::app()->getStore()->isAdmin() ? self::ADMIN_SESSION_NAME : self::FRONT_SESSION_NAME;
		return $string ? $name : Mage::getSingleton($name);
	}

	public function getMapUrl(string $code) {

		return Mage::app()->getStore()->isAdmin() ?
			Mage::app()->getStore()->getUrl('*/shippingmax_map/index', ['code' => $code]) :
			Mage::app()->getStore()->getUrl('shippingmax/map/index', ['code' => $code]);
	}

	public function getCarrierCode(string $fullcode) {

		// shippingmax_pocztk48Op_std devient shippingmax_pocztk48Op
		// shippingmax_pocztk48Op reste shippingmax_pocztk48Op
		if (substr_count($fullcode, '_') >= 2)
			return mb_substr($fullcode, 0, mb_strpos($fullcode, '_', mb_strpos($fullcode, '_') + 1));

		return $fullcode;
	}

	public function getEnabledCarrierCode(string $fullcode, int $storeId) {

		$fullcode = $this->getCarrierCode($fullcode);

		// en cas de mix (par exemple si colisprivpts est désactivé et que mondialrelay est activé)
		$mixmaps = Mage::getConfig()->getNode('global/shippingmax/mixmaps')->asArray();
		foreach ($mixmaps as $code => $candidates) {
			if (array_key_exists($fullcode, $candidates) && Mage::getStoreConfigFlag('carriers/'.$code.'/active', $storeId))
			    return $code;
		}

		return $fullcode;
	}

	public function formatDesc(string $data) {

		if (substr_count($data, '#') > 5) {

			$lines = (array) explode("\n", $data); // (yes)
			$since = '';
			$desc  = [];

			foreach ($lines as $idx => $line) {

				if ((strpos($line, '#') !== false) && is_numeric($line[0])) {

					$line = (array) explode('#', $line); // (yes)
					$day  = isset($line[0][0]) ? (int) $line[0][0] : 0;

					if ($day > 0) {

						$day  = ucfirst(Mage::getSingleton('core/locale')->date()->setWeekday($day)->toString(Zend_Date::WEEKDAY));
						$curr = implode(array_slice($line, 1));
						$next = empty($lines[$idx + 1]) ? '' : implode(array_slice(explode('#', $lines[$idx + 1]), 1));

						if ($curr == $next) {
							if (empty($since))
								$since = $day;
							continue;
						}

						if (!empty($since)) {
							$day = $since.' - '.$day;
							$since = '';
						}

						// 1 Monday#09#30#12#00#12#00#19#00
						// 1 Monday#09#30#12#00#14#30#19#00
						if (count($line) >= 9) {
							if (($line[3] == $line[5]) && ($line[4] == $line[6])) {
								$desc[] = $this->__('%s: %s:%s - %s:%s', $day,
									str_pad($line[1], 2, '0', STR_PAD_LEFT), str_pad($line[2], 2, '0', STR_PAD_LEFT),
									str_pad($line[7], 2, '0', STR_PAD_LEFT), str_pad($line[8], 2, '0', STR_PAD_LEFT));
							}
							else {
								$desc[] = $this->__('%s: %s:%s - %s:%s / %s:%s - %s:%s', $day,
									str_pad($line[1], 2, '0', STR_PAD_LEFT), str_pad($line[2], 2, '0', STR_PAD_LEFT),
									str_pad($line[3], 2, '0', STR_PAD_LEFT), str_pad($line[4], 2, '0', STR_PAD_LEFT),
									str_pad($line[5], 2, '0', STR_PAD_LEFT), str_pad($line[6], 2, '0', STR_PAD_LEFT),
									str_pad($line[7], 2, '0', STR_PAD_LEFT), str_pad($line[8], 2, '0', STR_PAD_LEFT));
							}
						}
						// 1 Monday#15#00#19#00
						else if (count($line) >= 5) {
							$desc[] = $this->__('%s: %s:%s - %s:%s', $day,
								str_pad($line[1], 2, '0', STR_PAD_LEFT), str_pad($line[2], 2, '0', STR_PAD_LEFT),
								str_pad($line[3], 2, '0', STR_PAD_LEFT), str_pad($line[4], 2, '0', STR_PAD_LEFT));
						}
						// 1 Monday#closed
						else if (count($line) >= 2) {
							$desc[] = $this->__('%s: closed', $day);
						}
					}
					else {
						$desc[] = implode('#', $line);
					}
				}
				else {
					$desc[] = $line;
				}
			}

			$data = implode("\n", $desc);
		}

		return nl2br($data);
	}
}