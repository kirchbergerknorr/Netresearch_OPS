<?php

/**
 * Netresearch_OPS_Block_Form_OpsId
 * 
 * @package   OPS
 * @copyright 2012 Netresearch App Factory AG <http://www.netresearch.de>
 * @author    Thomas Birke <thomas.birke@netresearch.de> 
 * @license   OSL 3.0
 */
class Netresearch_OPS_Block_Form_Cc extends Netresearch_OPS_Block_Form
{
    /**
     * Backend Payment Template
     */
    const BACKEND_TEMPLATE = 'ops/form/cc.phtml';

    protected function _construct()
    {
        parent::_construct();
        
        //Only in case that the form is loaded in the backend, use a special template
        if (false === Mage::getModel("ops/config")->isFrontendEnvironment()) {
            $this->setTemplate(self::BACKEND_TEMPLATE);
        }
    }

    /**
     * gets all Alias CC brands
     * 
     * @return array
     */
    public function getAliasBrands()
    {
        return Mage::getModel('ops/source_cc_aliasInterfaceEnabledTypes')
                ->getAliasInterfaceCompatibleTypes();
    }
}
