<?php
/**
 * Netresearch_OPS_Model_Payment_OpenInvoiceNl
 * 
 * @package   
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de> 
 * @license   OSL 3.0
 */
class Netresearch_OPS_Model_Payment_OpenInvoiceNl
    extends Netresearch_OPS_Model_Payment_OpenInvoice_Abstract
{
    const CODE = 'Open Invoice NL';

    /** if we can capture directly from the backend */
    protected $_canBackendDirectCapture = false;


    /** info source path */
    protected $_infoBlockType = 'ops/info_redirect';

    /** payment code */
    protected $_code = 'ops_openInvoiceNl';


    /**
     * Open Invoice NL is not available if quote has a coupon
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

        /* not available if there is no gender or no birthday */
        if (is_null($quote->getCustomerGender()) || is_null($quote->getCustomerDob())) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * get some method dependend form fields 
     *
     * @param Mage_Sales_Model_Quote $order 
     * @return array
     */
    public function getMethodDependendFormFields($order, $requestParams=null)
    {
        $billingAddress  = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $street = str_replace("\n", ' ',$billingAddress->getStreet(-1));
        $regexp = '/^([^0-9]*)([0-9].*)$/';
        if (!preg_match($regexp, $street, $splittedStreet)) {
            $splittedStreet[1] = $street;
            $splittedStreet[2] = '';
        }
        $formFields = parent::getMethodDependendFormFields($order, $requestParams);

        $gender = Mage::getSingleton('eav/config')
            ->getAttribute('customer', 'gender')
            ->getSource()
            ->getOptionText($order->getCustomerGender());

        $formFields['CIVILITY']                         = $gender == 'Male' ? 'M' : 'V';
        $formFields['OWNERADDRESS']                     = trim($splittedStreet[1]);
        $formFields['ECOM_BILLTO_POSTAL_STREET_NUMBER'] = trim($splittedStreet[2]);
        $formFields['OWNERZIP']                         = $billingAddress->getPostcode();
        $formFields['OWNERTOWN']                        = $billingAddress->getCity();
        $formFields['OWNERCTY']                         = $billingAddress->getCountry();
        $formFields['OWNERTELNO']                       = $billingAddress->getTelephone();

        $street = str_replace("\n", ' ',$shippingAddress->getStreet(-1));
        if (!preg_match($regexp, $street, $splittedStreet)) {
            $splittedStreet[1] = $street;
            $splittedStreet[2] = '';
        }
        $formFields['ECOM_SHIPTO_POSTAL_NAME_PREFIX']   = $shippingAddress->getPrefix();
        $formFields['ECOM_SHIPTO_POSTAL_NAME_FIRST']    = $shippingAddress->getFirstname();
        $formFields['ECOM_SHIPTO_POSTAL_NAME_LAST']     = $shippingAddress->getLastname();
        $formFields['ECOM_SHIPTO_POSTAL_STREET_LINE1']  = trim($splittedStreet[1]);
        $formFields['ECOM_SHIPTO_POSTAL_STREET_NUMBER'] = trim($splittedStreet[2]);
        $formFields['ECOM_SHIPTO_POSTAL_POSTALCODE']    = $shippingAddress->getPostcode();
        $formFields['ECOM_SHIPTO_POSTAL_CITY']          = $shippingAddress->getCity();
        $formFields['ECOM_SHIPTO_POSTAL_COUNTRYCODE']   = $shippingAddress->getCountry();

        // copy some already known values
        $formFields['ECOM_SHIPTO_ONLINE_EMAIL']         = $order->getCustomerEmail();

        if (is_array($requestParams)) {
            if (array_key_exists('OWNERADDRESS', $requestParams)) {
                $formFields['OWNERADDRESS'] = $requestParams['OWNERADDRESS'];
            }
            if (array_key_exists('ECOM_BILLTO_POSTAL_STREET_NUMBER', $requestParams)) {
                $formFields['ECOM_BILLTO_POSTAL_STREET_NUMBER'] = $requestParams['ECOM_BILLTO_POSTAL_STREET_NUMBER'];
            }
            if (array_key_exists('ECOM_SHIPTO_POSTAL_STREET_LINE1', $requestParams)) {
                $formFields['ECOM_SHIPTO_POSTAL_STREET_LINE1'] = $requestParams['ECOM_SHIPTO_POSTAL_STREET_LINE1'];
            }
            if (array_key_exists('ECOM_SHIPTO_POSTAL_STREET_NUMBER', $requestParams)) {
                $formFields['ECOM_SHIPTO_POSTAL_STREET_NUMBER'] = $requestParams['ECOM_SHIPTO_POSTAL_STREET_NUMBER'];
            }
        }

        // Order Details
        $count = 1;
        $subtotal = 0;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItemId()
                && $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                || $item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                continue;
            }
            $subtotal += $item->getBasePriceInclTax() * $item->getQtyOrdered();
            $count++;
        }

        /* add coupon item */
        if ($order->getBaseDiscountAmount()) {
            $couponAmount = $order->getBaseDiscountAmount();
            $formFields['ITEMID' . $count]      = 'DISCOUNT';
            $couponRuleName = 'DISCOUNT';
            if ($order->getCouponRuleName() && strlen(trim($order->getCouponRuleName())) > 0) {
                $couponRuleName = substr(trim($order->getCouponRuleName()), 0, 30);
            }
            $formFields['ITEMNAME' . $count]    = $couponRuleName;
            $formFields['ITEMPRICE' . $count]   = number_format($couponAmount, 2, '.', '');
            $formFields['ITEMQUANT' . $count]   = 1;
            $formFields['ITEMVATCODE' . $count] = str_replace(',', '.',(string)(float)$this->getShippingTaxRate($order)) . '%';
            $formFields['TAXINCLUDED' . $count] = 1;

            $subtotal += $couponAmount;
            $count++;
        }

        $shippingPrice = $order->getBaseShippingInclTax();
        $subtotal += $shippingPrice;

        /* modify shipping costs if there is a rounding error */
        $roundingError = $order->getBaseGrandTotal() - $subtotal;
        $shippingPrice += $roundingError;

        /* add shipping item */
        $formFields['ITEMID' . $count]      = 'SHIPPING';
        $formFields['ITEMNAME' . $count]    = substr($order->getShippingDescription(), 0, 30);
        $formFields['ITEMPRICE' . $count]   = number_format($shippingPrice, 2, '.', '');
        $formFields['ITEMQUANT' . $count]   = 1;
        $formFields['ITEMVATCODE' . $count] = str_replace(',', '.',(string)(float)$this->getShippingTaxRate($order)) . '%';
        $formFields['TAXINCLUDED' . $count] = 1;
        $count++;

        return $formFields;
    }

    public function getItemFormFields($count, $item)
    {
        $formFields = parent::getItemFormFields($count, $item);

        /* use price including tax */
        $formFields['ITEMPRICE' . $count]   = number_format($item->getBasePriceInclTax(), 2, '.', '');
        /* indicate that price already includes tax */
        $formFields['TAXINCLUDED' . $count] = 1;

        return $formFields;
    }

    /**
     * get question for fields with disputable value
     * users are asked to correct the values before redirect to Ingenico Payment Services
     *
     * @param Mage_Sales_Model_Order $order         Current order
     * @param array                  $requestParams Request parameters
     * @return string
     */
    public function getQuestion($order, $requestParams)
    {
        return Mage::helper('ops')->__('Please make sure that your street and house number are correct.');
    }

    /**
     * get an array of fields with disputable value
     * users are asked to correct the values before redirect to Ingenico Payment Services
     *
     * @param Mage_Sales_Model_Order $order         Current order
     * @param array                  $requestParams Request parameters
     * @return array
     */
    public function getQuestionedFormFields($order, $requestParams)
    {
        return array(
            'OWNERADDRESS',
            'ECOM_BILLTO_POSTAL_STREET_NUMBER',
            'ECOM_SHIPTO_POSTAL_STREET_LINE1',
            'ECOM_SHIPTO_POSTAL_STREET_NUMBER',
        );
    }
}
