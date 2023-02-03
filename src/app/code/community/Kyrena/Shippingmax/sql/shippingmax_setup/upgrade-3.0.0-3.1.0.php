<?php
/**
 * Created V/03/02/2023
 * Updated V/03/02/2023
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

// prevent multiple execution
$lock = Mage::getModel('index/process')->setId('shippingmax_setup');
if ($lock->isLocked())
	Mage::throwException('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// ignore user abort and time limit
ignore_user_abort(true);
set_time_limit(0);

try {
	$values = $this->_conn->fetchAll('SELECT path, value FROM '.$this->getTable('core_config_data').' WHERE path LIKE "shippingmax_times/%/cnf%"');
	$config = [];

	foreach ($values as $value) {

		$code = substr($value['path'], strlen('shippingmax_times/'), 2);
		$key  = substr($value['path'], strlen('shippingmax_times/') + 3);

		if (strtolower($code) == $code)
			continue;

		$config[$code][$key] = (string) $value['value'];
	}

	foreach ($config as $code => $values) {
		$this->setConfigData('shippingmax_times/'.$code.'/config', serialize($values));
	}

	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "shippingmax_times/%/cnf%"');
	Mage::getConfig()->reinit();
}
catch (Throwable $t) {
	$lock->unlock();
	Mage::throwException($t);
}

$this->endSetup();
$lock->unlock();