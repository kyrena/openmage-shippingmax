<?php
/**
 * Copyright © 2019-2023 Kyrena. All rights reserved.
 * See COPYING.txt for license details.
 */

class Owebia_Shipping2_Model_Os2_Data_Config extends Owebia_Shipping2_Model_Os2_Data_Abstract
{
    public function __sleep()
    {
        $variableArray = Mage::getResourceModel('admin/variable_collection')->getColumnValues('variable_name');
        return array_map(
            static function ($item) {
                return str_replace('/', '-', $item);
            }, $variableArray
        );
    }

    protected function _load($name)
    {
        return Mage::getStoreConfig(str_replace('-', '/', $name));
    }
}