<?php
/**
 * Created V/12/04/2019
 * Updated V/16/07/2021
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

$this->startSetup();

// https://stackoverflow.com/a/1196429
$this->run('
	DROP TABLE IF EXISTS '.$this->getTable('shippingmax_coords').';
	CREATE TABLE '.$this->getTable('shippingmax_coords').' (
		coords_id  int(11) unsigned NOT NULL AUTO_INCREMENT,
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
		FOREIGN KEY (order_id) REFERENCES '.$this->getTable('sales_flat_order').' (entity_id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

	-- DELETE FROM '.$this->getTable('core_config_data').' WHERE path LIKE "customer/address_templates/%";
');

$table = $this->getTable('sales_flat_order');
if (!$this->getConnection()->tableColumnExists($table, 'estimated_shipping_date'))
	$this->run('ALTER TABLE '.$table.' ADD estimated_shipping_date varchar(75) DEFAULT NULL');

$this->endSetup();