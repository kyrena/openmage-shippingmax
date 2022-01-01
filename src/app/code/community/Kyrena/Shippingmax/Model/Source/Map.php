<?php
/**
 * Created J/25/04/2019
 * Updated S/04/09/2021
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

class Kyrena_Shippingmax_Model_Source_Map {

	public function toOptionArray() {

		return [
			['value' => 'ign',    'label' => 'IGN France'],
			['value' => 'osm',    'label' => 'Open Street Map'],
			['value' => 'osmfr',  'label' => 'Open Street Map France'],
			['value' => 'osmde',  'label' => 'Open Street Map Deutschland'],
			['value' => 'osmbre', 'label' => 'Open Street Map Brezhoneg'],
			['value' => 'osmoci', 'label' => 'Open Street Map Occitan'],
			['value' => 'osmeus', 'label' => 'Open Street Map Euskara'],
			['value' => 'osmbot', 'label' => 'Open Street Map Boat'],
			['value' => 'ocm',    'label' => 'Open Cyclo Map'],
			['value' => 'otm',    'label' => 'Open Topo Map'],
			['value' => 'chm',    'label' => 'Swiss Topo Map'],
			['value' => 'ggm',    'label' => 'Google Map'],
		];
	}
}