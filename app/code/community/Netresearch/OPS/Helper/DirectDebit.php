<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2013 Netresearch GmbH & Co. KG
 *          (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Helper_DirectDebit extends Netresearch_OPS_Helper_Payment_DirectLink_Request
{

    protected $dataHelper = null;

    protected $quoteHelper = null;

    protected $orderHelper = null;

    protected $customerHelper = null;

    protected $validator = null;




    /**
     * sets the data helper
     *
     * @param Netresearch_OPS_Helper_Data $dataHelper
     */
    public function setDataHelper(Netresearch_OPS_Helper_Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * gets the data helper
     *
     * @return Mage_Core_Helper_Abstract
     */
    public function getDataHelper()
    {
        if (null === $this->dataHelper) {
            $this->dataHelper = Mage::helper('ops/data');
        }

        return $this->dataHelper;
    }

    /**
     * sets the quote helper
     *
     * @param Netresearch_OPS_Helper_Quote $quoteHelper
     */
    public function setQuoteHelper(Netresearch_OPS_Helper_Quote $quoteHelper)
    {
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * gets the quote helper
     *
     * @return Mage_Core_Helper_Abstract
     */
    public function getQuoteHelper()
    {
        if (null === $this->quoteHelper) {
            $this->quoteHelper = Mage::helper('ops/quote');
        }

        return $this->quoteHelper;
    }

    /**
     * sets the order helper
     *
     * @param Netresearch_OPS_Helper_Order $orderHelper
     */
    public function setOrderHelper(Netresearch_OPS_Helper_Order $orderHelper)
    {
        $this->orderHelper = $orderHelper;
    }

    /**
     * gets the order helper
     *
     * @return Mage_Core_Helper_Abstract
     */
    public function getOrderHelper()
    {
        if (null === $this->orderHelper) {
            $this->orderHelper = Mage::helper('ops/order');
        }

        return $this->orderHelper;
    }

    /**
     * sets the customer helper
     *
     * @param Mage_Core_Helper_Abstract $customerHelper
     */
    public function setCustomerHelper(Mage_Core_Helper_Abstract $customerHelper)
    {
        $this->customerHelper = $customerHelper;
    }

    /**
     * gets the customer helper
     *
     * @return Mage_Core_Helper_Abstract
     */
    public function getCustomerHelper()
    {
        if (null === $this->customerHelper) {
            $this->customerHelper = Mage::helper('customer/data');
        }

        return $this->customerHelper;
    }


    /**
     * sets the validator
     *
     * @param Netresearch_OPS_Model_Validator_Payment_DirectDebit $validator
     */
    public function setValidator(
        Netresearch_OPS_Model_Validator_Payment_DirectDebit $validator
    )
    {
        $this->validator = $validator;
    }

    /**
     * gets the validator
     *
     * @return Netresearch_OPS_Model_Validator_Payment_DirectDebit
     */
    public function getValidator()
    {
        if (null === $this->validator) {
            $this->validator = Mage::getModel(
                'ops/validator_payment_directDebit'
            );
        }

        return $this->validator;
    }

    /**
     * gets the country from a given array
     *
     * @param array $params
     *
     * @return string - the country in uppercase, empty string if no country
     *              was provided
     */
    public function getCountry(array $params)
    {
        $country = '';
        if (array_key_exists('country', $params)) {
            $country = strtoupper($params['country']);
        }

        return $country;
    }

    /**
     * checks whether the given data has an iban field or not
     *
     * @param array $accountData
     *
     * @return bool - true if iban field is present and filled, false otherwise
     */
    public function hasIban(array $accountData)
    {
        return array_key_exists('iban', $accountData)
        && 0 < strlen(trim($accountData['iban']));
    }

    /**
     * sets the direct debit data to given payment
     *
     * @param Mage_Sales_Model_Quote_Payment $payment - the payment which
     *                                                should contain the
     *                                                additional data
     * @param array                          $params  -
     *
     * @return Mage_Sales_Model_Quote_Payment
     */
    public function setDirectDebitDataToPayment(
        Mage_Sales_Model_Quote_Payment $payment, array $params
    )
    {
        $payment->setAdditionalInformation(
            'PM', 'Direct Debits ' . $this->getCountry($params)
        );
        $this->setAccountHolder($payment, $params);
        $this->setAccountData($payment, $params);

        return $payment;
    }

    /**
     * sets the account holder to given payment
     *
     * @param Mage_Sales_Model_Quote_Payment $payment
     * @param array                          $params
     */
    protected function setAccountHolder(
        Mage_Sales_Model_Quote_Payment $payment, array $params
    )
    {
        if (array_key_exists('CN', $params)
            && 0 < strlen(trim($params['CN']))
        ) {
            $payment->setAdditionalInformation('CN', trim($params['CN']));
        }
    }

    /**
     * set the account data to given payment
     *
     * @param Mage_Sales_Model_Quote_Payment $payment
     * @param array                          $params
     */
    protected function setAccountData(
        Mage_Sales_Model_Quote_Payment $payment, array $params
    )
    {
        $country = $this->getCountry($params);

        if ('DE' == $country || 'AT' == $country) {
            if ($this->hasIban($params) && 'DE' == $country) {
                $payment->setAdditionalInformation(
                    'CARDNO', trim($params['iban'])
                );
            } else {
                $payment->setAdditionalInformation(
                    'CARDNO',
                    trim($params['account']) . 'BLZ' . trim($params['bankcode'])
                );
            }


        }
        if ('NL' == $country) {
            if ($this->hasIban($params)) {
                $payment->setAdditionalInformation(
                    'CARDNO', trim($params['iban'])
                );
                $payment->setAdditionalInformation('BIC', trim($params['bic']));
            } else {
                $payment->setAdditionalInformation(
                    'CARDNO', str_pad($params['account'], '0', STR_PAD_LEFT)
                );
            }
        }
    }

    /**
     * validates direct debit payment from the backend
     *
     * @param $requestParams - the params passed on order submission
     * @throws Mage_Core_Exception - if the data is not valid
     * @return boolean - true if data were valid
     */
    protected function validateAdminDirectDebit($requestParams)
    {
        $validator = $this->getValidator();
        if (false === $validator->isValid($requestParams)) {
            Mage::getModel('adminhtml/session')->setData('ops_direct_debit_params', $requestParams);
            Mage::throwException(
                implode('<br />', $validator->getMessages())
            );
        }
        return true;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param array $requestParams
     * @return Netresearch_OPS_Helper_DirectDebit
     */
    public function handleAdminPayment(Mage_Sales_Model_Quote $quote, $requestParams)
    {
        if ($this->getDataHelper()->isAdminSession() && is_array($requestParams)) {
            $this->validateAdminDirectDebit($requestParams);
            $this->setDirectDebitDataToPayment($quote->getPayment(), $requestParams);
            $quote->getPayment()->save();
        }

        return $this;
    }

    protected function getPaymentSpecificParams(Mage_Sales_Model_Quote $quote)
    {
        $cardHolderName = $quote->getPayment()->getAdditionalInformation('CN');
        $params = array(
            'CARDNO'            => $quote->getPayment()->getAdditionalInformation('CARDNO'),
            'CN'                => utf8_decode($cardHolderName),
            // Always the same on direct debit
            'ED'                => '9999',
            'PM'                => $quote->getPayment()->getAdditionalInformation('PM'),
        );
        if (0 < strlen(trim($quote->getPayment()->getAdditionalInformation('BIC')))) {
            $bic = $quote->getPayment()->getAdditionalInformation('BIC');
            $params['BIC'] = $bic;
        }
        $params['BRAND'] = $params['PM'];

        return $params;
    }
}
    