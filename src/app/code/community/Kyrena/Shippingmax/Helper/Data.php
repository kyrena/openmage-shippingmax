<?php
/**
 * Created V/12/04/2019
 * Updated V/03/03/2023
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

class Kyrena_Shippingmax_Helper_Data extends Mage_Core_Helper_Abstract {

	protected $_yasumiProviders;
	protected $_yasumiTranslations;
	protected $_dayNames = [];


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
		$options['locale'] = Mage::getSingleton('core/locale')->getLocaleCode();
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
			$user = sprintf('frontend %s', Mage::app()->getStore()->getData('code'));

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

		// vérifie que c'est bien activé et que le pays est bien présent
		if (!Mage::getStoreConfigFlag('shippingmax_times/general/enabled'))
			return null;

		if (empty($country)) {
			$address  = $this->getSession()->getQuote()->getShippingAddress();
			$country  = $address->getData('country_id');
			$postcode = $address->getData('postcode');
			if (empty($country))
				return null;
		}

		// recherche du vrai pays surtout pour la France et ses DROM/COM
		$fake    = Mage::getModel('customer/address')->setData('country_id', $country)->setData('postcode', $postcode);
		$country = Mage::getBlockSingleton('shippingmax/rewrite_renderer')->render($fake, 'country');
		$storeId = is_null($storeId) ? Mage::app()->getStore()->getId() : $storeId;
		$config  = @unserialize(Mage::getStoreConfig('shippingmax_times/'.$country.'/config', $storeId), ['allowed_classes' => false]);

		// recherche des délais
		// pour shippingmax_supercode_abc_xyz puis pour shippingmax_supercode_abc
		foreach ([$code, mb_substr($code, 0, mb_strrpos($code, '_'))] as $rateCode) {

			if (empty($postcode)) {
				// délai sans postcode
				$key     = $rateCode;
				$cnf1min = (int) ($config['cnf1min_'.$key] ?? 0);
				$cnf1max = (int) ($config['cnf1max_'.$key] ?? 0);
				$cnf2min = (int) ($config['cnf2min_'.$key] ?? 0);
				$cnf2max = (int) ($config['cnf2max_'.$key] ?? 0);
				$cnf3    = $config['cnf3_'.$key] ?? false;
				if (!empty($cnf2min))
					break;
			}
			else {
				$nb = 6;
				while (--$nb >= 2) {
					// délai avec postcode
					$key     = $rateCode.'_'.mb_substr(trim($postcode), 0, $nb);
					$cnf1min = (int) ($config['cnf1min_'.$key] ?? 0);
					$cnf1max = (int) ($config['cnf1max_'.$key] ?? 0);
					$cnf2min = (int) ($config['cnf2min_'.$key] ?? 0);
					$cnf2max = (int) ($config['cnf2max_'.$key] ?? 0);
					$cnf3    = $config['cnf3_'.$key] ?? false;
					if (!empty($cnf2min))
						break 2;
				}
				if (empty($cnf2min)) {
					// délai sans postcode
					$key     = $rateCode;
					$cnf1min = (int) ($config['cnf1min_'.$key] ?? 0);
					$cnf1max = (int) ($config['cnf1max_'.$key] ?? 0);
					$cnf2min = (int) ($config['cnf2min_'.$key] ?? 0);
					$cnf2max = (int) ($config['cnf2max_'.$key] ?? 0);
					$cnf3    = $config['cnf3_'.$key] ?? false;
					if (!empty($cnf2min))
						break;
				}
			}
		}

		// pas de délai
		if (empty($cnf2min))
			return null;

		// ajoute des jours
		if (!empty($add = (int) Mage::getStoreConfig('shippingmax_times/general/add_for_all'))) {
			$cnf2min += $add;
			$cnf2max += $add;
		}

		// nombre de jours de livraison uniquement
		if ($days) {
			if ($cnf2min == $cnf2max)
				return ($cnf2min > 1) ? $this->__('%d days', $cnf2min) : $this->__('%d day', 1);
			return $this->__('%d/%d days', $cnf2min, $cnf2max);
		}

		// calcul les dates par rapport aux délais trouvés
		// de 1 (pour Lundi) à 7 (pour Dimanche)
		$dateFrom = Mage::getSingleton('core/locale')->date();
		$dateTo   = Mage::getSingleton('core/locale')->date();
		$today    = $dateFrom->toString(Zend_Date::WEEKDAY_8601);
		$cutoff   = (int) Mage::getStoreConfig('shippingmax_times/general/cutoff', $storeId);

		if (($dtz = Mage::getStoreConfig('general/locale/timezone', 0)) != ($stz = Mage::getStoreConfig('general/locale/timezone', $storeId))) {
			$cutoff = (new DateTime())
				->setTimezone(new DateTimeZone($dtz))
				->setTime($cutoff, 0)
				->setTimezone(new DateTimeZone($stz))
				->format('G');
		}

		if ($today == 6) { // pas d'expédition le samedi
			$addMin = $cnf1min + $cnf2min + 1;
			$addMax = $cnf1min + $cnf2max + 1;
		}
		else if ($today == 7) { // pas d'expédition le dimanche
			$addMin = $cnf1min + $cnf2min + 1;
			$addMax = $cnf1min + $cnf2max + 1;
		}
		else if ($dateFrom->toString(Zend_Date::HOUR) < $cutoff) {
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

		// estimation de livraison
		if ($dateFrom == $dateTo)
			return $this->__('Estimated delivery: %s', $dateFrom->toString(Zend_Date::WEEKDAY).' '.$dateFrom->toString(Zend_Date::DATE_SHORT));

		return $this->__('Estimated delivery: from %s to %s',
			$dateFrom->toString(Zend_Date::WEEKDAY).' '.$dateFrom->toString(Zend_Date::DATE_SHORT),
			$dateTo->toString(Zend_Date::WEEKDAY).' '.$dateTo->toString(Zend_Date::DATE_SHORT));
	}

	public function isClosedDay(object $date, string $country, $postcode = null) {

		if (stripos(__FILE__, 'vendor/kyrena') === false)
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

		if ($code == 'shippingmax_storelocator')
			return array_filter(explode(',', Mage::getStoreConfig('carriers/'.$code.'/specificcountry', $storeId)));

		// liste des pays autorisés
		$countries = array_filter(explode(',', Mage::getStoreConfig('general/country/allow', $storeId)));

		// filtre sur la config des pays possibles sur le mode de livraison
		$selCountries = Mage::getStoreConfig('carriers/'.$code.'/allowedcountry');
		$selCountries = empty($selCountries) ? [] : array_filter(explode(',', $selCountries));
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
			$countries = Mage::getModel('shippingmax/configparser')->init($config, false)->filterCountries($countries); // no auto_correction
		}

		return array_values($countries);
	}

	public function getItemFromLastOrder(object $address, object $rate, string $code) {

		$cid = $address->getData('customer_id');
		if (!empty($cid)) {

			try {
				// remplace Monaco par France (MC = FR)
				$country = ($address->getData('country_id') == 'MC') ? 'FR' : $address->getData('country_id');
				$candidates = Mage::getResourceModel('shippingmax/details_collection')
					->addFieldToFilter('customer_id', $cid)
					->addFieldToFilter('details', ['like' => '%"country_id":"'.$country.'"%'])
					->addFieldToFilter('details', ['like' => '%"carrier":"'.$code.'"%'])
					->setOrder('order_id', 'desc')
					->setPageSize(10);

				Mage::getModel('shippingmax/coords')->setAddressCoords($address);
				$items = $rate->getCarrierInstance()->loadItemsFromCache($address);
				$cache = [];

				foreach ($candidates as $candidate) {

					$candidate = @json_decode($candidate->getData('details'), true);

					// cherche le point de livraison d'abord par rapport à l'adresse de livraison
					if (array_key_exists($candidate['id'], $items)) {
						$item = $items[$candidate['id']];
						return [
							'country_id' => $item['country_id'],
							'item'       => $item,
							'lat'        => $address->getData('lat'),
							'lng'        => $address->getData('lng'),
							'selected'   => $item['id'],
						];
					}

					// cherche ensuite le point de livraison par rapport à l'adresse du point de livraison de la commande
					if (!in_array($candidate['id'], $cache)) {
						$subItems = $rate->getCarrierInstance()->loadItemsFromCache(new Varien_Object($candidate));
						if (array_key_exists($candidate['id'], $subItems)) {
							$item = $subItems[$candidate['id']];
							return [
								'country_id' => $item['country_id'],
								'item'       => $item,
								'selected'   => $item['id'],
							];
						}
						$cache[] = $candidate['id'];
					}
				}
			}
			catch (Throwable $t) {
				Mage::logException($t);
			}
		}

		return [];
	}

	public function getSession(bool $string = false) {

		// pour le checkout/onepage du front-office ou pour la création de commande du back-office
		$name = Mage::app()->getStore()->isAdmin() ? 'adminhtml/session_quote' : 'checkout/session';
		return $string ? $name : Mage::getSingleton($name);
	}

	public function getMapUrl(string $code) {

		return Mage::app()->getStore()->isAdmin() ?
			Mage::getUrl('*/shippingmax_map/index', ['code' => str_replace('shippingmax_', '', $code)]) :
			Mage::getUrl('shippingmax/map/index', ['code' => str_replace('shippingmax_', '', $code)]);
	}

	public function getCarrierCode(string $code) {

		// shippingmax_supercode_xyz devient shippingmax_supercode
		// shippingmax_supercode reste shippingmax_supercode
		if (substr_count($code, '_') >= 2)
			return mb_substr($code, 0, mb_strpos($code, '_', mb_strpos($code, '_') + 1));

		return $code;
	}

	public function getEnabledCarrierCode(string $code, int $storeId) {

		$code = $this->getCarrierCode($code);

		// en cas de mix (par exemple si $code=colisprivpts est désactivé, et que mondialrelay est activé avec colisprivpts)
		$maps = array_keys(Mage::getConfig()->getNode('global/shippingmax/maps')->asArray());
		foreach ($maps as $candidate) {
			$config = Mage::getStoreConfig('carriers/'.$candidate.'/mix', $storeId);
			if (!empty($config) && str_contains($config, $code) && Mage::getStoreConfigFlag('carriers/'.$candidate.'/active', $storeId))
				return $candidate;
		}

		return $code;
	}

	public function formatDesc(string $data) {

		if (empty($this->_dayNames)) {
			$date = Mage::getSingleton('core/locale')->date();
			foreach (range(1, 7) as $day)
				$this->_dayNames[$day] = ucfirst($date->setWeekday($day)->toString(Zend_Date::WEEKDAY));
		}

		if (substr_count($data, '#') > 5) {

			$lines = explode('~', str_replace("\n", '~', $data)); // 'saut de ligne' ou '~'
			$since = '';
			$desc  = [];

			foreach ($lines as $idx => $line) {

				if (str_contains($line, '#') && is_numeric($line[0])) {

					$line = explode('#', $line);
					$day  = isset($line[0][0]) ? (int) $line[0][0] : 0; // '1 Monday' ou '1'

					if ($day > 0) {

						$day  = $this->_dayNames[$day];
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

						// 1#09#30#12#00#12#00#19#00
						// 1#09#30#12#00#14#30#19#00
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
						// 1#15#00#19#00
						else if (count($line) >= 5) {
							$desc[] = $this->__('%s: %s:%s - %s:%s', $day,
								str_pad($line[1], 2, '0', STR_PAD_LEFT), str_pad($line[2], 2, '0', STR_PAD_LEFT),
								str_pad($line[3], 2, '0', STR_PAD_LEFT), str_pad($line[4], 2, '0', STR_PAD_LEFT));
						}
						// 1#closed
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