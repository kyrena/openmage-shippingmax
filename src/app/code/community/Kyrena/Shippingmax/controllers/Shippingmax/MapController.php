<?php
/**
 * Created V/12/04/2019
 * Updated L/22/02/2021
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

require_once(str_replace('/controllers/Shippingmax/', '/controllers/', __FILE__));

class Kyrena_Shippingmax_Shippingmax_MapController extends Kyrena_Shippingmax_MapController {

	protected $_sessionNamespace = Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE;

	public function preDispatch() {

		$this->getLayout()->setArea('adminhtml');
		Mage::dispatchEvent('adminhtml_controller_action_predispatch_start', []);
		Mage_Core_Controller_Varien_Action::preDispatch();

		if (Mage::getSingleton('admin/session')->isLoggedIn()) {
			$this->getLayout()->getUpdate()->addHandle(str_replace('adminhtml_', '', $this->getFullActionName()));
			$this->loadLayoutUpdates(); // lol
		}
	}
}