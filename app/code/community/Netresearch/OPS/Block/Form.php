<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Netresearch_OPS_Block_Form extends Mage_Payment_Block_Form_Cc
{

    private $aliasDataForCustomer = null;

    protected $pmLogo = null;

    protected $fieldMapping = array();

    protected $config = null;

    /**
     * Frontend Payment Template
     */
    const FRONTEND_TEMPLATE = 'ops/form.phtml';

    /**
     * Init OPS payment form
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate(self::FRONTEND_TEMPLATE);
    }

    /**
     * get OPS config
     *
     * @return Netresearch_Ops_Model_Config
     */
    public function getConfig()
    {
        if (null === $this->config) {
            $this->config =  Mage::getSingleton('ops/config');
        }

        return $this->config;
    }

    public function setConfig(Netresearch_OPS_Model_Config $config)
    {
        $this->config = $config;

        return $this;
    }

    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    public function getCcBrands()
    {
        return explode(',', $this->getConfig()->getAcceptedCcTypes());
    }

    public function getDirectDebitCountryIds()
    {
        return explode(',', $this->getConfig()->getDirectDebitCountryIds());
    }

    public function getBankTransferCountryIds()
    {
        return explode(',', $this->getConfig()->getBankTransferCountryIds());
    }

    public function getPSPID($storeId = null)
    {
        return Mage::getModel('ops/config')->getPSPID($storeId);
    }

    public function getAliasAcceptUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getAliasAcceptUrl($storeId, $admin);
    }

    public function getAliasExceptionUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getAliasExceptionUrl($storeId, $admin);
    }

    public function getAliasGatewayUrl($storeId = null)
    {
        return Mage::getModel('ops/config')->getAliasGatewayUrl($storeId);
    }

    public function getSaveCcBrandUrl()
    {
        return Mage::getModel('ops/config')->getSaveCcBrandUrl();
    }

    public function getGenerateHashUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getGenerateHashUrl($storeId, $admin);
    }

    public function getCcSaveAliasUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getCcSaveAliasUrl($storeId, $admin);
    }

    public function getRegisterDirectDebitPaymentUrl()
    {
        return Mage::getModel('ops/config')->getRegisterDirectDebitPaymentUrl();
    }

    public function getValidationUrl()
    {
        return Mage::getModel('ops/config')->getValidationUrl();
    }

    public function getDirectEbankingBrands()
    {

        return explode(',', $this->getConfig()->getDirectEbankingBrands());
    }

    /**
     * checks if the 'alias' payment method (!) is available
     * no check for customer has aliases here
     * just a passthrough of the isAvailable of Netresearch_OPS_Model_Payment_Abstract::isAvailable
     *
     * @return boolean
     */
    public function isAliasPMEnabled()
    {
        return Mage::getModel('ops/config')->isAliasManagerEnabled();
    }

    /**
     *
     * @return array empty or intersolve Vouchers
     */
    public function getInterSolveBrands()
    {
        $brands = array();
        if ($this->getMethodCode() == 'ops_interSolve') {
            $brands = Mage::getModel('ops/config')->getIntersolveBrands();
        }
        return $brands;
    }

    /**
     * retrieves the alias data for the logged in customer
     *
     * @return array | null - array the alias data or null if the customer
     * is not logged in
     */
    protected function getStoredAliasForCustomer()
    {
        if (Mage::helper('customer/data')->isLoggedIn()
            && Mage::getModel('ops/config')->isAliasManagerEnabled()) {
            $quote = $this->getQuote();
            $alias = Mage::helper('ops/alias')->getAliasesForAddresses(
                $quote->getCustomer()->getId(), $quote->getBillingAddress(),
                $quote->getShippingAddress(), $quote->getStoreId()
            )
            ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::ACTIVE)
            ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
            ->getFirstItem();
            $this->aliasDataForCustomer = $alias->getData();
        }
        return $this->aliasDataForCustomer;
    }


    /**
     * retrieves single values to given keys from the alias data
     *
     * @param $key - string the key for the alias data
     *
     * @return null | string - null if key is not set in the alias data, otherwise
     * the value for the given key from the alias data
     *
     */
    protected function getStoredAliasDataForCustomer($key)
    {
        $returnValue = null;
        $aliasData = null;
        if (is_null($this->aliasData)) {
            $aliasData = $this->getStoredAliasForCustomer();
        }
        if (is_array($aliasData) && array_key_exists($key, $aliasData)) {
            $returnValue = $aliasData[$key];
        }
        return $returnValue;
    }

    /**
     * retrieves the given path (month or year) from stored expiration date
     *
     * @param $key - the requested path
     *
     * @return null | string the extracted part of the date
     */
    public function getExpirationDatePart($key)
    {
        $returnValue = null;
        $expirationDate = $this->getStoredAliasDataForCustomer('expiration_date');
        // set expiration date to actual date if no stored Alias is used
        if ($expirationDate === null) {
            $expirationDate = date('my');
        }
        
        if (0 < strlen(trim($expirationDate))
        ) {
            $expirationDateValues = str_split($expirationDate, 2);

            if ($key == 'month') {
                $returnValue = $expirationDateValues[0];
            }
            if ($key == 'year') {
                $returnValue = $expirationDateValues[1];
            }
        }
        return $returnValue;

    }

    /**
     * retrieves the masked alias card number and formats it in a card specific format
     *
     * @return null|string - null if no alias data were found,
     * otherwise the formatted card number
     */
    public function getAliasCardNumber()
    {
        $aliasCardNumber = $this->getStoredAliasDataForCustomer('pseudo_account_or_cc_no');
        if (0 < strlen(trim($aliasCardNumber))) {
            $aliasCardNumber = Mage::helper('ops/alias')->formatAliasCardNo(
                $this->getStoredAliasDataForCustomer('brand'), $aliasCardNumber
            );
        }
        return $aliasCardNumber;
    }

    /**
     * @return null|string - the card holder either from alias data or
     * the name from the the user who is logged in, null otherwise
     */
    public function getCardHolderName()
    {
        $cardHolderName = $this->getStoredAliasDataForCustomer('card_holder');
        $customerHelper = Mage::helper('customer/data');
        if ((is_null($cardHolderName) || 0 === strlen(trim($cardHolderName)))
            && (!is_null($this->getStoredAlias('alias')))
            && $customerHelper->isLoggedIn()
            && Mage::getModel('ops/config')->isAliasManagerEnabled()
        ) {
            $cardHolderName = $customerHelper->getCustomerName();
        }
        return $cardHolderName;
    }

    /**
     * the brand of the stored card data
     *
     * @return null|string - string if stored card data were found, null otherwise
     */
    public function getStoredAliasBrand()
    {
        $storedBrand = $this->getStoredAliasDataForCustomer('brand');
        if (in_array($storedBrand, Mage::getModel('ops/config')->getInlinePaymentCcTypes())) {
            return $storedBrand;
        }
        return '';
    }

    /**
     * retrieves an old alias for re-usage or updating it
     *
     * @return null|string - string the stored alias for re-usage or
     * null if no alias data were stored
     */
    public function getStoredAlias()
    {
        return $this->getStoredAliasDataForCustomer('alias');
    }

    /**
     * wrapper for Netresearch_OPS_Helper_Data::checkIfUserRegistering
     *
     * @return type bool
     */
    public function isUserRegistering()
    {
        return Mage::Helper('ops/data')->checkIfUserIsRegistering();
    }

    /**
     * wrapper for Netresearch_OPS_Helper_Data::checkIfUserRegistering
     *
     * @return type bool
     */
    public function isUserNotRegistering()
    {
        return Mage::Helper('ops/data')->checkIfUserIsNotRegistering();
    }

    /**
     * determines whether the hint is shown to guests or not
     *
     * @return bool true if alias feature is enabled and display the hint to
     * guests is enabled
     */
    public function isAliasInfoBlockEnabled()
    {
        return ($this->isAliasPMEnabled()
                && Mage::getModel('ops/config')->isAliasInfoBlockEnabled());
    }

    /**
     * @return string
     */
    public function getPmLogo()
    {
        return $this->pmLogo;
    }


    protected function getFieldMapping()
    {
        return $this->getConfig()->getFrontendFieldMapping();
    }

    /**
     * returns the corresponding fields for frontend validation if needed
     *
     * @return string - the json encoded fields
     */
    public function getFrontendValidators()
    {
        $frontendFields = array();
        if ($this->getConfig()->canSubmitExtraParameter($this->getQuote()->getStoreId())) {
            $fieldsToValidate = $this->getConfig()->getParameterLengths();
            $mappedFields = $this->getFieldMapping();
            foreach ($fieldsToValidate as $key => $value) {
                if (array_key_exists($key, $mappedFields)) {
                    $frontendFields = $this->getFrontendValidationFields($mappedFields, $key, $value, $frontendFields);
                }
            }
        }

        return Mage::helper('core/data')->jsonEncode($frontendFields);
    }

    /**
     * @param $mappedFields
     * @param $key
     * @param $value
     * @param $frontendFields
     *
     * @return mixed
     */
    public function getFrontendValidationFields($mappedFields, $key, $value, $frontendFields)
    {
        if (!is_array($mappedFields[$key])) {
            $frontendFields[$mappedFields[$key]] = $value;
        } else {
            foreach ($mappedFields[$key] as $mKey) {
                $frontendFields[$mKey] = $value;
            }
        }

        return $frontendFields;
    }
}
