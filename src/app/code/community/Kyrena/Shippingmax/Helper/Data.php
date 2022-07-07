<?php
/**
 * Created V/12/04/2019
 * Updated V/24/06/2022
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
		return empty($data) ? $data : htmlspecialchars($data, $quotes ? ENT_SUBSTITUTE | ENT_COMPAT : ENT_SUBSTITUTE | ENT_NOQUOTES);
	}

	public function formatDate($date = null, $format = Zend_Date::DATETIME_LONG, $showTime = false) {
		$object = Mage::getSingleton('core/locale');
		return str_replace($object->date($date)->toString(Zend_Date::TIMEZONE), '', $object->date($date)->toString($format));
	}

	public function getHumanEmailAddress($email) {
		return empty($email) ? '' : $this->escapeEntities(str_replace(['<', '>', ',', '"'], ['(', ')', ', ', ''], $email));
	}

	public function getHumanDuration($start, $end = null) {

		if (is_numeric($start) || (!in_array($start, ['', '0000-00-00 00:00:00', null]) && !in_array($end, ['', '0000-00-00 00:00:00', null]))) {

			$data    = is_numeric($start) ? $start : strtotime($end) - strtotime($start);
			$minutes = (int) ($data / 60);
			$seconds = $data % 60;

			if ($data > 599)
				$data = '<strong>'.(($seconds > 9) ? $minutes.':'.$seconds : $minutes.':0'.$seconds).'</strong>';
			else if ($data > 59)
				$data = '<strong>'.(($seconds > 9) ? '0'.$minutes.':'.$seconds : '0'.$minutes.':0'.$seconds).'</strong>';
			else if ($data > 1)
				$data = ($seconds > 9) ? '00:'.$data : '00:0'.$data;
			else
				$data = '⩽&nbsp;1';
		}

		return empty($data) ? '' : $data;
	}

	public function getNumber($value, array $options = []) {
		$options['locale'] = Mage::getSingleton('core/translate')->getLocale();
		return Zend_Locale_Format::toNumber($value, $options);
	}

	public function getNumberToHumanSize(int $number) {

		if ($number < 1) {
			$data = '';
		}
		else if (($number / 1024) < 1024) {
			$data = $number / 1024;
			$data = $this->getNumber($data, ['precision' => 2]);
			$data = $this->__('%s kB', preg_replace('#[.,]00[[:>:]]#', '', $data));
		}
		else if (($number / 1024 / 1024) < 1024) {
			$data = $number / 1024 / 1024;
			$data = $this->getNumber($data, ['precision' => 2]);
			$data = $this->__('%s MB', preg_replace('#[.,]00[[:>:]]#', '', $data));
		}
		else {
			$data = $number / 1024 / 1024 / 1024;
			$data = $this->getNumber($data, ['precision' => 2]);
			$data = $this->__('%s GB', preg_replace('#[.,]00[[:>:]]#', '', $data));
		}

		return $data;
	}

	public function getUsername() {

		$file = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$file = array_pop($file);
		$file = array_key_exists('file', $file) ? basename($file['file']) : '';

		// backend
		if ((PHP_SAPI != 'cli') && Mage::app()->getStore()->isAdmin() && Mage::getSingleton('admin/session')->isLoggedIn())
			$user = sprintf('admin %s', Mage::getSingleton('admin/session')->getData('user')->getData('username'));
		// cron
		else if (is_object($cron = Mage::registry('current_cron')))
			$user = sprintf('cron %d - %s', $cron->getId(), $cron->getData('job_code'));
		// xyz.php
		else if ($file != 'index.php')
			$user = $file;
		// full action name
		else if (is_object($action = Mage::app()->getFrontController()->getAction()))
			$user = $action->getFullActionName();
		// frontend
		else
			$user = sprintf('frontend %d', Mage::app()->getStore()->getData('code'));

		return $user;
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
			$address  = $this->getSession()->getQuote()->getShippingAddress();
			$country  = $address->getData('country_id');
			$postcode = $address->getData('postcode');
			if (empty($country))
				return null;
		}

		// recherche du vrai pays surtout pour la France et ses DROM/COM
		// Mage::getBlockSingleton('shippingmax/rewrite_renderer')
		// Mage::app()->getLayout()->getBlockSingleton('shippingmax/rewrite_renderer')
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

		// tous les pays
		if ($code == 'shippingmax_storelocator')
			$countries = Mage::getResourceModel('directory/country_collection')->getColumnValues('country_id');
		else
			$countries = array_filter(explode(',', Mage::getStoreConfig('general/country/allow', $storeId)));

		// filtre sur la config des pays possibles sur le mode de livrais
		$selCountries = array_filter(explode(',', Mage::getStoreConfig('carriers/'.$code.'/allowedcountry'))); // config.xml
		if (!empty($selCountries))
			$countries = array_intersect($countries, $selCountries);

		// filtre sur la config des pays autorisés sur le mode de livraison
		if (Mage::getStoreConfigFlag('carriers/'.$code.'/sallowspecific', $storeId)) {
			$selCountries = array_filter(explode(',', Mage::getStoreConfig('carriers/'.$code.'/specificcountry', $storeId)));
			$countries    = array_intersect($countries, $selCountries);
		}

		// filtre sur la config avancée d'owebia
		$config = Mage::getStoreConfig('carriers/'.$code.(str_contains($code, 'owebiashipping') ? '/config' : '/owebia_config'), $storeId);
		if (mb_stripos($config, '"shipto"') !== false) {
			$config = Mage::getSingleton('shippingmax/addressfilter')->substitute($config);
			return Mage::getModel('shippingmax/configparser')->init($config, true)->filterCountries($countries);
		}

		return $countries;
	}

	public function getItemFromLastOrder(string $code, string $country, object $rate) {

		if (!empty($customer = $this->getSession()->getQuote()->getCustomer()) && !empty($cid = $customer->getId())) {

			$result = ['from_orders' => $country];

			try {
				$details = Mage::getResourceModel('shippingmax/details_collection')
					->addFieldToFilter('customer_id', $cid)
					->addFieldToFilter('details', ['like' => '%"country_id":"'.$country.'"%'])
					->addFieldToFilter('details', ['like' => '%"carrier":"'.$code.'"%'])
					->setOrder('order_id', 'desc')
					->setPageSize(10);

				// cherche d'abord par rapport à l'adresse de livraison
				$address = $this->getSession()->getQuote()->getShippingAddress();
				Mage::getModel('shippingmax/coords')->setAddressCoords($address);
				$items = $rate->getCarrierInstance()->loadItemsFromCache($address);
				foreach ($details as $detail) {
					$detail = json_decode($detail->getData('details'), true);
					if (array_key_exists($detail['id'], $items)) {
						$result = ['item' => $items[$detail['id']]];
						$result['lat'] = $result['item']['lat'];
						$result['lng'] = $result['item']['lng'];
						$result['country_id']  = $result['item']['country_id'];
						$result['selected']    = $detail['id'];
						$result['from_orders'] = $country;
						$result['item']['from_orders'] = $country;
						break;
					}
				}

				// cherche ensuite par rapport à l'adresse des points relais précédemment sélectionnés
				if (empty($result['selected'])) {
					foreach ($details as $detail) {
						$detail = json_decode($detail->getData('details'), true);
						$items  = $rate->getCarrierInstance()->loadItemsFromCache(new Varien_Object($detail));
						if (array_key_exists($detail['id'], $items)) {
							$result = ['item' => $items[$detail['id']]];
							$result['lat'] = $result['item']['lat'];
							$result['lng'] = $result['item']['lng'];
							$result['country_id']  = $result['item']['country_id'];
							$result['selected']    = $detail['id'];
							$result['from_orders'] = $country;
							$result['item']['from_orders'] = $country;
							break;
						}
					}
				}
			}
			catch (Throwable $t) {
				Mage::logException($t);
				$result = ['from_orders' => $country];
			}

			$this->getSession()->setData($code, $result);
			return $result;
		}

		return [];
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

	public function getCarrierCode(string $code) {

		// shippingmax_pocztk48Op_std devient shippingmax_pocztk48Op
		// shippingmax_pocztk48Op reste shippingmax_pocztk48Op
		if (substr_count($code, '_') >= 2)
			return mb_substr($code, 0, mb_strpos($code, '_', mb_strpos($code, '_') + 1));

		return $code;
	}

	public function getEnabledCarrierCode(string $code, int $storeId) {

		$code = $this->getCarrierCode($code);

		// en cas de mix (par exemple si colisprivpts est désactivé et que mondialrelay est activé)
		$mixmaps = Mage::getConfig()->getNode('global/shippingmax/mixmaps')->asArray();
		foreach ($mixmaps as $key => $candidates) {
			if (array_key_exists($code, $candidates) && Mage::getStoreConfigFlag('carriers/'.$key.'/active', $storeId) &&
			    Mage::getStoreConfigFlag('carriers/'.$key.'/mix_'.str_replace('shippingmax_', '', $code), $storeId))
				return $key;
		}

		return $code;
	}

	public function formatDesc(string $data) {

		if (substr_count($data, '#') > 5) {

			$lines = explode("\n", $data);
			$since = '';
			$desc  = [];

			foreach ($lines as $idx => $line) {

				if (str_contains($line, '#') && is_numeric($line[0])) {

					$line = explode('#', $line);
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