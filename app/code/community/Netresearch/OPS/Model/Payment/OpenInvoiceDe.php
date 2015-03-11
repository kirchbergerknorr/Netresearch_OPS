<?php
/**
 * Netresearch_OPS_Model_Payment_OpenInvoiceDe
 * 
 * @package   
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de> 
 * @license   OSL 3.0
 */
class Netresearch_OPS_Model_Payment_OpenInvoiceDe
    extends Netresearch_OPS_Model_Payment_OpenInvoice_Abstract
{
    const CODE = 'Open Invoice DE';

    /** if we can capture directly from the backend */
    protected $_canBackendDirectCapture = false;

    protected $_canCapturePartial = false;
    protected $_canRefundInvoicePartial = false;

    /** info source path */
    protected $_infoBlockType = 'ops/info_redirect';

    /** payment code */
    protected $_code = 'ops_openInvoiceDe';

    /**
     * Open Invoice DE is not available if quote has a coupon
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return boolean
     */
    public function isAvailable($quote=null)
    {
        /* availability depends on quote */
        if (false == $quote instanceof Mage_Sales_Model_Quote) {
            return false;
        }

        /* not available if quote contains a coupon */
        if ($quote->getSubtotal() != $quote->getSubtotalWithDiscount()) {
            return false;
        }

        /* not available if there is no gender or no birthday */
        if (is_null($quote->getCustomerGender()) || is_null($quote->getCustomerDob())) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    public function getPaymentAction()
    {
        return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
    }

    public function getMethodDependendFormFields($order, $requestParams=null)
    {
        $formFields = parent::getMethodDependendFormFields($order, $requestParams);

        $shippingAddress = $order->getShippingAddress();
        $birthday = new DateTime($order->getCustomerDob());

        $gender = Mage::getSingleton('eav/config')
            ->getAttribute('customer', 'gender')
            ->getSource()
            ->getOptionText($order->getCustomerGender());

        $formFields['CIVILITY']      = $gender == 'Male' ? 'Herr' : 'Frau';
        $formFields['ORDERSHIPCOST'] = round(100 * $order->getBaseShippingInclTax());
        $formFields['OWNERADDRESS']  = str_replace("\n", ' ',$shippingAddress->getStreet(-1));
        $formFields['OWNERCTY']      = $shippingAddress->getCountry();
        $formFields['OWNERTELNO']    = $shippingAddress->getTelephone();
        $formFields['OWNERTOWN']     = $shippingAddress->getCity();

        return $formFields;
    }
}

