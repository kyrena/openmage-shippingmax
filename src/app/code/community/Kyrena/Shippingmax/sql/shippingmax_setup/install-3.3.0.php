<?php
/**
 * Created V/12/04/2019
 * Updated V/10/02/2023
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
	Mage::throwException('Please wait, install is already in progress...');

$lock->lockAndBlock();
$this->startSetup();

// ignore user abort and time limit
ignore_user_abort(true);
set_time_limit(0);

try {
	// https://stackoverflow.com/a/1196429
	$this->run('
		DROP TABLE IF EXISTS '.$this->getTable('shippingmax_coords').';
		CREATE TABLE '.$this->getTable('shippingmax_coords').' (
			coords_id  int(11) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime         NULL DEFAULT NULL,
			updated_at datetime         NULL DEFAULT NULL,
			addrkey    varchar(32)      NULL DEFAULT NULL,
			kladr      varchar(50)      NULL DEFAULT NULL,
			postcode   varchar(20)      NULL DEFAULT NULL,
			city       varchar(255)     NULL DEFAULT NULL,
			country_id varchar(2)       NOT NULL,
			lat        decimal(8,6)     NOT NULL DEFAULT 0,
			lng        decimal(9,6)     NOT NULL DEFAULT 0,
			PRIMARY KEY (coords_id),
			KEY addrkey (addrkey)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

		DROP TABLE IF EXISTS '.$this->getTable('shippingmax_order_details').';
		CREATE TABLE '.$this->getTable('shippingmax_order_details').' (
			order_id     int(10) unsigned NOT NULL,
			customer_id  int(10) unsigned NOT NULL,
			details      text             NOT NULL,
			PRIMARY KEY (order_id),
			INDEX `customer_id` (`customer_id`),
			FOREIGN KEY (order_id) REFERENCES '.$this->getTable('sales_flat_order').' (entity_id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	');
	// DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "customer/address_templates/%";
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "carriers/%/auto_escaping"');
	$this->run('DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "carriers/%/auto_correction"');

	$table = $this->getTable('sales_flat_order');
	if (!$this->getConnection()->tableColumnExists($table, 'estimated_shipping_date'))
		$this->run('ALTER TABLE '.$table.' ADD estimated_shipping_date varchar(75) DEFAULT NULL');
}
catch (Throwable $t) {
	$lock->unlock();
	Mage::throwException($t);
}

$this->endSetup();
$lock->unlock();