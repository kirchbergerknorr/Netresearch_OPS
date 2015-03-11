<?php
/**
 * Netresearch_OPS_Model_Payment_InterSolve
 *
 * @package
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_Model_Payment_InterSolve
    extends Netresearch_OPS_Model_Payment_Abstract
{
    /** Check if we can capture directly from the backend */
    protected $_canBackendDirectCapture = true;

    /** info source path */
    protected $_infoBlockType = 'ops/info_redirect';

    /** payment code */
    protected $_code = 'ops_interSolve';

    protected function getPayment($payment=null)
    {
        if (is_null($payment)) {
            $checkout = Mage::getSingleton('checkout/session');
            $payment = $checkout->getQuote()->getPayment();
            if (!$payment->getId()) {
                $payment = Mage::getModel('sales/order')
                    ->loadByIncrementId($checkout->getLastRealOrderId())
                    ->getPayment();
            }
        }
        return $payment;
    }

    public function getOpsCode($payment = null)
    {
        return 'InterSolve';
    }

    public function getOpsBrand($payment=null)
    {
        return trim($this->getPayment($payment)->getAdditionalInformation('BRAND'));
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {

        $brand = '';
        if (is_object($data) && $data instanceof Varien_Object) {
            $brand = $data->getIntersolveBrand();
        } elseif (is_array($data) && isset($data['intersolve_brand'])) {
            $brand = $data['intersolve_brand'];
        }
        if (strlen(trim($brand)) === 0) {
            $brand = 'InterSolve';
        }
        $payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
        $payment->setAdditionalInformation('BRAND', $brand);

        parent::assignData($data);
        return $this;
    }
}

