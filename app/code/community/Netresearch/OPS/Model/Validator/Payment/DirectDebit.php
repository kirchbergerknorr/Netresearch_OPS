<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Model_Validator_Payment_DirectDebit
{

    protected $messages = array();

    protected $dataHelper = null;

    protected $directDebitHelper = null;

    /**
     * sets the data helper
     *
     * @param Mage_Core_Helper_Abstract $dataHelper
     */
    public function setDataHelper(Mage_Core_Helper_Abstract $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * gwets the data helper
     *
     * @return Mage_Core_Helper_Abstract|null
     */
    public function getDataHelper()
    {
        if (null == $this->dataHelper) {
            $this->dataHelper = Mage::helper('ops/data');
        }

        return $this->dataHelper;
    }

    /**
     * sets the direct debit helper
     *
     * @param Netresearch_OPS_Helper_DirectDebit $directDebitHelper
     */
    public function setDirectDebitHelper(Netresearch_OPS_Helper_DirectDebit $directDebitHelper)
    {
        $this->directDebitHelper = $directDebitHelper;
    }

    /**
     * gets the direct debit helper
     *
     * @return Netresearch_OPS_Helper_DirectDebit
     */
    public function getDirectDebitHelper()
    {
        if (null === $this->directDebitHelper) {
            $this->directDebitHelper = Mage::helper('ops/directDebit');
        }
        return $this->directDebitHelper;
    }

    /**
     * validates the direct debit payment data
     *
     * @param array $directDebitData
     *
     * @return bool - true if the direct data are valid, false otherwise
     */
    public function isValid(array $directDebitData)
    {
        if (false === $this->checkPreconditions($directDebitData)) {
            return false;
        }

        return $this->validateAccountData($directDebitData);
    }

    /**
     * gets the validation messages
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * checks if the neccesaary data are present
     *
     * @param array $accountData
     *
     * @return bool - true if the neccessary data are present, false otherwise
     */
    protected function checkPreconditions(array $accountData)
    {
        if (0 == count($accountData)
            || !array_key_exists(
                'CN', $accountData
            )
            || !array_key_exists('country', $accountData)
            || !array_key_exists('account', $accountData)
            || !array_key_exists('iban', $accountData)
        ) {
            $this->messages[] = $this->getDataHelper()->__(
                'invalid data provided'
            );

            return false;
        }

        return true;
    }

    /**
     * checks if the account data are valid
     *
     * @param array $accountData
     *
     * @return bool - true if the data are valid, false otherise
     */
    protected function validateAccountData(array $accountData)
    {
        $result = true;
        if ($this->hasAccountHolder($accountData)) {
            $this->messages[] = $this->getDataHelper()->__(
                'Account holder must be provided'
            );
            $result           = false;
        }

        // check iban data
        if ($this->hasIban($accountData)) {
            $result = $this->validateIban(
                $accountData['country'], $accountData['iban'],
                (array_key_exists('bic', $accountData))? $accountData['bic'] : ''
            );
        }
        // check bank account data
        else {
            $result = $this->validateBankAccount(
                $accountData['country'], $accountData['account'],
                (array_key_exists('bankcode', $accountData))
                    ? $accountData['bankcode'] : ''
            );
        }

        return $result;
    }


    /**
     * validates iban and (optional) bic data
     *
     * @param $country - the country for the iban
     * @param $iban - the iban to validate
     * @param $bic - the bic if given
     *
     * @return bool - true if the iban data are valid, false otherwise
     */
    protected function validateIban($country, $iban, $bic)
    {
        $country = strtoupper(trim($country));
        $result  = true;
        if ('DE' != $country && 'NL' != $country) {
            $result           = false;
            $this->messages[] = $this->getDataHelper()->__(
                'Country not supported for IBAN'
            );
        }
        $validator = new Zend_Validate_Iban();

        if (!$validator->isValid($iban)) {
            $result = false;

            $this->messages[] = $this->getDataHelper()->__('Invalid IBAN provided');
        }
        if ('NL' == $country
            && (11 < strlen(trim($bic)))
        ) {
            $result           = false;
            $this->messages[] = $this->getDataHelper()->__(
                'invalid BIC provided'
            );

        }

        return $result;
    }

    /**
     * validates bank account data
     *
     * @param $country - the country for the bank account data
     * @param $accountNo - the account number
     * @param $bankCode - the bank code
     *
     * @return bool - true if the data are valid, false otherwise
     */
    protected function validateBankAccount($country, $accountNo, $bankCode)
    {
        $result = true;
        if (!is_numeric($accountNo)) {
            $this->messages[] = $this->getDataHelper()->__(
                'Account number must contain numbers only.'
            );
            $result = false;
        }
        $country = strtolower($country);
        if (('de' == $country || 'at' == $country) && !is_numeric($bankCode)) {
            $this->messages[] = $this->getDataHelper()->__(
                'Bank code must contain numbers only.'
            );
            $result           = false;
        }

        return $result;
    }

    /**
     * checks if account holder is provided in the given account data
     *
     * @param array $accountData
     *
     * @return bool - true if account holder is present, false otherwise
     */
    protected function hasAccountHolder(array $accountData)
    {
        return array_key_exists('CN', $accountData)
        && 0 === strlen(trim($accountData['CN']));
    }

    /**
     * checks if the account data has the ibna field
     *
     * @param array $accountData
     *
     * @return bool - true if the account data contain the iban
     */
    protected function hasIban(array $accountData)
    {
        return $this->getDirectDebitHelper()->hasIban($accountData);
    }

} 