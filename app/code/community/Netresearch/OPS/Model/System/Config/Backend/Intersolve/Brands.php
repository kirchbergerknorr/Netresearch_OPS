<?php
/**
 * Netresearch OPS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2012 Netresearch GmbH & Co. KG (http://www.netresearch.de/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 *
 *
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 */

class Netresearch_Ops_Model_System_Config_Backend_Intersolve_Brands
    extends Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array
{
    protected $_eventPrefix = 'ops_config_backend_intersolve_brands';

    /**
     * additional validation for unique brands
     *
     * @override
     * @throws Mage_Core_Exception if the brands are not unique -> validation failed
     * @return Netresearch_Ops_Model_System_Config_Backend_Intersolve_Brands
     */
    public function save()
    {
        $brands = $this->getValue();
        if (is_array($brands) && sizeof($brands) > 1) {
            $alreadyProcessedBrands = array();
            foreach ($brands as $brand) {
                if (is_array($brand) && array_key_exists('brand', $brand)) {
                    if (in_array($brand['brand'], $alreadyProcessedBrands)) {
                        Mage::throwException("Brands must be unique");
                    }
                    $alreadyProcessedBrands[] = $brand['brand'];
                }
            }
        }
        return parent::save();
    }
}
