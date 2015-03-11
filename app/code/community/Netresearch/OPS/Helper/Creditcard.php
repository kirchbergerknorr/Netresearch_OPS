<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch
 * @package     ${MODULENAME}
 * @copyright   Copyright (c) 2013 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Helper_Creditcard extends Netresearch_OPS_Helper_Payment_DirectLink_Request
{

    protected $aliasHelper = null;

    /**
     * @param null $aliasHelper
     */
    public function setAliasHelper($aliasHelper)
    {
        $this->aliasHelper = $aliasHelper;
    }

    /**
     * @return null
     */
    public function getAliasHelper()
    {
        if (null === $this->aliasHelper) {
            $this->aliasHelper = Mage::helper('ops/alias');
        }
        return $this->aliasHelper;
    }



    /**
     * @param $quote
     * @param $requestParams
     */
    public function handleAdminPayment(Mage_Sales_Model_Quote $quote, $requestParams)
    {
       return $this;
    }

    protected function getPaymentSpecificParams(Mage_Sales_Model_Quote $quote)
    {
        $alias = $quote->getPayment()->getAdditionalInformation('alias');
        if (is_null($alias) && $this->getDataHelper()->isAdminSession()) {
            $alias = $this->getAliasHelper()->getAlias($quote);
        }
        $params = array (
            'ALIAS' => $alias,
        );
        if (is_numeric($quote->getPayment()->getAdditionalInformation('cvc'))) {
            $params['CVC'] = $quote->getPayment()->getAdditionalInformation('cvc');
        }
        $requestParams3ds = array();
        if ($this->getConfig()->get3dSecureIsActive() && false == $this->getDataHelper()->isAdminSession()) {
            $requestParams3ds = array(
                'FLAG3D'           => 'Y',
                'WIN3DS'           => Netresearch_OPS_Model_Payment_Abstract::OPS_DIRECTLINK_WIN3DS,
                'LANGUAGE'         => Mage::app()->getLocale()->getLocaleCode(),
                'HTTP_ACCEPT'      => '*/*',
                'HTTP_USER_AGENT'  => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)',
                'ACCEPTURL'        => $this->getConfig()->getAcceptUrl(),
                'DECLINEURL'       => $this->getConfig()->getDeclineUrl(),
                'EXCEPTIONURL'     => $this->getConfig()->getExceptionUrl(),
            );
        }
        $params = array_merge($params, $requestParams3ds);
        return $params;
    }
} 