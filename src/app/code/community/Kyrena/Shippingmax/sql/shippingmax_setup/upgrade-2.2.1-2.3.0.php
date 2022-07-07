<?php
/**
 * Created L/21/03/2022
 * Updated M/28/06/2022
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

// de manière à empécher de lancer cette procédure plusieurs fois
$lock = Mage::getModel('index/process')->setId('shippingmax_setup');
if ($lock->isLocked())
	Mage::throwException('Please wait, upgrade is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// de manière à continuer quoi qu'il arrive
ignore_user_abort(true);
set_time_limit(0);

try {
	$this->run('DELETE FROM '.$this->getTable('shippingmax_coords').' WHERE country_id = "FR" AND postcode IN ("75000", "75001", "75002", "75003", "75004", "75005", "75006", "75007", "75008", "75009", "75010", "75011", "75012", "75013", "75014", "75015", "75016", "75116", "75017", "75018", "75019", "75020", "13000", "13001", "13002", "13003", "13004", "13005", "13006", "13007", "13008", "13009", "13010", "13011", "13012", "13013", "13014", "13015", "13016", "69000", "69001", "69002", "69003", "69004", "69005", "69006", "69007", "69008", "69009");');

	$table = $this->getTable('shippingmax_coords');
	if (!$this->getConnection()->tableColumnExists($table, 'updated_at'))
		$this->run('ALTER TABLE '.$table.' ADD updated_at datetime NULL DEFAULT NULL AFTER coords_id');
	if (!$this->getConnection()->tableColumnExists($table, 'created_at'))
		$this->run('ALTER TABLE '.$table.' ADD created_at datetime NULL DEFAULT NULL AFTER coords_id');
}
catch (Throwable $t) {
	$lock->unlock();
	throw $t;
}

$this->endSetup();
$lock->unlock();