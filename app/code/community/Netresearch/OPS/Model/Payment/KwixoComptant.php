<?php
/**
 * Netresearch_OPS_Model_Payment_ApresReception
 * 
 * @package   
 * @copyright 2013 Netresearch
 * @author    Sebastian Ertner <sebastian.ertner@netresearch.de> 
 * @license   OSL 3.0
 */
class Netresearch_OPS_Model_Payment_KwixoComptant extends Netresearch_OPS_Model_Payment_Kwixo_Abstract
{
    
    const OPS_CODE = "KWIXO_STANDARD";
    
    /** Check if we can capture directly from the backend */
    protected $_canBackendDirectCapture = true;

    protected $_formBlockType = 'ops/form_kwixo_comptant';

    /** info source path */
    protected $_infoBlockType = 'ops/info_redirect';


    /** payment code */
    protected $_code = 'ops_kwixoComptant';

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