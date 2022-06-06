<?php
/**
 * Copyright © 2008-2020 Owebia. All rights reserved.
 * See COPYING.txt for license details.
 */

class Owebia_Shipping2_Model_System_Config_Source_LoadOnParent
    extends Mage_Adminhtml_Model_System_Config_Source_Category
{
    public function toOptionArray($addEmpty = true)
    {
        $options = [
            [
                'label' => Mage::helper('owebia_shipping2')->__('Self'),
                'value' => '0'
            ],
            [
                'label' => Mage::helper('owebia_shipping2')->__('Parent'),
                'value' => '1'
            ],
        ];
        return $options;
    }
}