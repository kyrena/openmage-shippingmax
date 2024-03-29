<?php
/**
 * Created L/12/07/2021
 * Updated S/19/02/2022
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

class Kyrena_Shippingmax_Model_Source_Shiptor {

	public function toOptionArray() {

		return [
			['value' => 'shiptor',          'label' => 'shiptor'],
			['value' => 'shiptor-one-day',  'label' => 'shiptor-one-day'],
			['value' => 'shiptor-oversize', 'label' => 'shiptor-oversize'],
			['value' => 'shiptor-area',     'label' => 'shiptor-area'],
			['value' => 'boxberry',         'label' => 'boxberry'],
			['value' => 'cdek',             'label' => 'cdek'],
			['value' => 'dpd',              'label' => 'dpd'],
			['value' => 'pickpoint',        'label' => 'pickpoint'],
			['value' => 'russian-post',     'label' => 'russian-post'],
			['value' => 'iml',              'label' => 'iml'],
			['value' => 'pec',              'label' => 'pec'],
			['value' => 'sberlogistics',    'label' => 'sberlogistics'],
		];
	}
}