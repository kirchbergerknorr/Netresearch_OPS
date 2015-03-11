<?php
/**
 * Netresearch_OPS_Block_Form_DirectDebit
 *
 * @package   OPS
 * @copyright 2012 Netresearch App Factory AG <http://www.netresearch.de>
 * @author    Thomas Birke <thomas.birke@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_Block_Form_DirectDebit extends Netresearch_OPS_Block_Form
{

    protected $previousParams = array();

    /**
     * Backend Payment Template
     */
    const BACKEND_TEMPLATE = 'ops/form/directDebit.phtml';

    protected function _construct()
    {
        parent::_construct();

        //Only in case that the form is loaded in the backend, use a special template
        if (false === Mage::getModel("ops/config")->isFrontendEnvironment()) {
            $this->setTemplate(self::BACKEND_TEMPLATE);
        }
    }

    /**
     * get ids of supported countries
     *
     * @return array
     */
    public function getDirectDebitCountryIds()
    {
        return explode(',', $this->getConfig()->getDirectDebitCountryIds());
    }

    /**
     * get previously entred params for displaying them again in the backend
     *
     * @return array - the previously entred params
     */
    public function getParams()
    {
        $params = Mage::getModel('adminhtml/session')->getData(
            'ops_direct_debit_params'
        );

        Mage::getModel('adminhtml/session')->unsetData(
            'ops_direct_debit_params'
        );
        if (!is_array($params)) {
            $params = array(
                'country' => '',
                'CN' => '',
                'iban' => '',
                'bic' => '',
                'account' => '',
                'bankcode' => ''
            );
        }
        $this->previousParams = $params;

        return $this->previousParams;
    }

    /**
     * checks if the iban field is required
     *
     * @return bool - true if so, false otherwise
     */
    public function isIbanFieldRequired()
    {
        return (count($this->previousParams) == 0
            || (array_key_exists(
                    'iban', $this->previousParams
                )
                && 0 < strlen('iban')
                || array_key_exists('account', $this->previousParams)
                && 0 == strlen($this->previousParams['account'])));
    }

    /**
     * checks if the account field is required
     *
     * @return bool - true if so, false otherwise
     */
    public function isAccountFieldRequired()
    {
        return (count($this->previousParams) == 0
            || (array_key_exists(
                    'account', $this->previousParams
                )
                && (!array_key_exists('iban', $this->previousParams)
                    || 0 == strlen($this->previousParams['iban']))));
    }



    /**
     * checks if the bankcode field is required
     *
     * @return bool - true if so, false otherwise
     */
    public function isBankCodeFieldRequired()
    {
        return $this->isAccountFieldRequired()
        && array_key_exists(
            'country', $this->previousParams
        )
        && ('DE' == strtoupper(
                $this->getCountry())
                || 'AT' == strtoupper($this->getCountry())

        );
    }

    /**
     * checks if the bankcode field is visible
     *
     * @return bool - true if so, false otherwise
     */
    public function isBankCodeFieldVisible()
    {
        return array_key_exists('country', $this->previousParams)
        && ('DE' == strtoupper($this->getCountry())
            || 'AT' == strtoupper($this->getCountry()));
    }

    /**
     * checks if the bic field is visible
     *
     * @return bool - true if so, false otherwise
     */
    public function isBicFieldVisible()
    {
        return ('NL' == strtoupper($this->getCountry()));
    }

    /**
     * gets the previously entered country (if any)
     *
     * @return string - empty string if no country is given, otherwise the country
     */
    public function getCountry()
    {
        $country = '';
        if (array_key_exists('country', $this->previousParams)) {
            $country = $this->previousParams['country'];
        }

        return $country;
    }

    /**
     * gets the previously entered iban (if any)
     *
     * @return string - empty string if no iban is given, otherwise the iban
     */
    public function getIban()
    {
        $iban = '';
        if (array_key_exists('iban', $this->previousParams)) {
            $iban = $this->previousParams['iban'];
        }

        return $iban;
    }

    /**
     * gets the previously entered bic (if any)
     *
     * @return string - empty string if no bic is given, otherwise the bic
     */
    public function getBic()
    {
        $bic = '';
        if (array_key_exists('bic', $this->previousParams)) {
            $bic = $this->previousParams['bic'];
        }

        return $bic;
    }

    /**
     * gets the previously entered account (if any)
     *
     * @return string - empty string if no account is given, otherwise the account
     */
    public function getAccount()
    {
        $account = '';
        if (array_key_exists('account', $this->previousParams)) {
            $account = $this->previousParams['account'];
        }

        return $account;
    }

    /**
     * gets the previously entered bankcode (if any)
     *
     * @return string - empty string if no bankcode is given, otherwise the bankcode
     */
    public function getBankcode()
    {
        $bankcode = '';
        if (array_key_exists('bankcode', $this->previousParams)) {
            $bankcode = $this->previousParams['bankcode'];
        }

        return $bankcode;
    }

    /**
     * gets the previously entered card holder (if any)
     *
     * @return string - empty string if no card holder is given, otherwise the card holder
     */
    public function getCardholderName()
    {
        $cardholder = '';
        if (array_key_exists('CN', $this->previousParams)) {
            $cardholder = $this->previousParams['CN'];
        }

        return $cardholder;
    }
}
