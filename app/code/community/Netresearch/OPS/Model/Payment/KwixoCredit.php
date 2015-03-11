<?php

/**
 * Netresearch_OPS_Model_Payment_KwixoCredit
 * 
 * @package   
 * @copyright 2013 Netresearch
 * @author    Sebastian Ertner <sebastian.ertner@netresearch.de> 
 * @license   OSL 3.0
 */
class Netresearch_OPS_Model_Payment_KwixoCredit extends Netresearch_OPS_Model_Payment_Kwixo_Abstract
{
    const OPS_CODE = "KWIXO_CREDIT";
    
    /** Check if we can capture directly from the backend */
    protected $_canBackendDirectCapture = true;

    /** info source path */
    protected $_infoBlockType = 'ops/info_redirect';


    protected $_formBlockType = 'ops/form_kwixo_credit';

    /** payment code */
    protected $_code = 'ops_kwixoCredit';

    public function getOpsCode($payment = null)
    {
        return self::OPS_CODE;
    }
    
    /**
     * getter for _code
     * 
     * @return string
     */
    public function getCode()
    {
        return $this->_code;
    }
}