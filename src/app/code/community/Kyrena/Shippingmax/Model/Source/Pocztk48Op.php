<?php
/**
 * Created L/18/10/2021
 * Updated L/18/10/2021
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

class Kyrena_Shippingmax_Model_Source_Pocztk48Op {

	public function toOptionArray() {

		return [
			['value' => 'POCZTA',           'label' => 'POCZTA'],
			['value' => 'ORLEN',            'label' => 'ORLEN'],
			['value' => 'AUTOMAT_POCZTOWY', 'label' => 'AUTOMAT_POCZTOWY'],
			['value' => 'RUCH',             'label' => 'RUCH'],
			['value' => 'ZABKA',            'label' => 'ZABKA'],
			['value' => 'FRESHMARKET',      'label' => 'FRESHMARKET'],
		];
	}
}