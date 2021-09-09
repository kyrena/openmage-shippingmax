<?php
/**
 * Created V/16/07/2021
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

class Kyrena_Shippingmax_Model_Source_Pickpoint {

	public function toOptionArray() {

		return [
			['value' => 'PickPoint',           'label' => 'PickPoint'],
			['value' => 'Московский постамат', 'label' => 'Московский постамат'],
			['value' => 'QIWI',                'label' => 'QIWI'],
			['value' => 'PulseExpress',        'label' => 'PulseExpress'],
			['value' => 'X5 ОМНИ',             'label' => 'X5 ОМНИ'],
			['value' => 'Халва',               'label' => 'Халва'],
			['value' => 'ЭНЖИ',                'label' => 'ЭНЖИ'],
			['value' => '5POST',               'label' => '5POST'],
		];
	}
}