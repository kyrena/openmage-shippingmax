<?php
/**
 * Created J/04/02/2021
 * Updated L/24/04/2023
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

class Kyrena_Shippingmax_Block_Adminhtml_Config_Files extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {
		$element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue()->unsPath();
		return parent::render($element);
	}

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {

		// cron fichiers
		$lastjob = Mage::getResourceModel('cron/schedule_collection')
			->addFieldToFilter('job_code', 'shippingmax_update_files')
			->addFieldToFilter('status', 'success')
			->setOrder('finished_at', 'desc')
			->setPageSize(1)
			->getFirstItem();

		if (!empty($lastjob->getId())) {
			$txt = $this->__('Last pick up stations file update on the <em>%s</em> (cron job #<a %s>%d</a>).',
				Mage::getSingleton('core/locale')->date($lastjob->getData('finished_at'))->toString(Zend_Date::DATETIME_LONG),
				'href="'.$this->helper('adminhtml')->getUrl('*/cronlog_history/view', ['id' => $lastjob->getId()]).'"',
				$lastjob->getId()
			);
			$summary = [$this->helper('core')->isModuleEnabled('Luigifab_Cronlog') ? $txt : strip_tags($txt)];
		}
		else {
			$summary = [$this->__('No cron jobs successfully finished for the pick up stations file update.')];
		}

		// fichiers
		$files = glob(Mage::getBaseDir('var').'/shippingmax/*.dat');
		foreach ($files as $file) {
			try {
				$name = basename($file);
				$pos  = str_contains($name, '_') ? strrpos($file, '_') : false;
				$mod  = empty($pos) ? 'shippingmax' : substr($file, 0, $pos);
				$carrier = Mage::getSingleton('shipping/config')->getCarrierInstance($mod.'_'.substr($name, 0, -4));
				if ($carrier) {
					$summary[] = sprintf('%s<br />&nbsp; <em>%s = %s k<br />&nbsp; file lifetime: %d hours, %d day(s)</em>',
						$name,
						$this->formatDate(date('Y-m-d H:i:s', filemtime($file)), Mage_Core_Model_Locale::FORMAT_TYPE_SHORT, true),
						$this->helper('shippingmax')->getNumber(filesize($file) / 1024, ['precision' => 2]),
						$carrier->getFullCacheLifetime() / 60 / 60,
						$carrier->getFullCacheLifetime() / 60 / 60 / 24
					);
				}
			}
			catch (Throwable $t) {
				Mage::logException($t);
			}
		}

		$element->setValue(implode('</li><li>', $summary));
		return sprintf('<ul lang="mul" id="%s"><li>%s</li></ul>', $element->getHtmlId(), $element->getValue());
	}
}