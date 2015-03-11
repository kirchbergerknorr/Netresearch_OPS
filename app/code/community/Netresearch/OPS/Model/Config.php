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

/**
 * Config model
 */
class Netresearch_OPS_Model_Config extends Mage_Payment_Model_Config
{
    const OPS_PAYMENT_PATH = 'payment_services/ops/';
    const OPS_CONTROLLER_ROUTE_API     = 'ops/api/';
    const OPS_CONTROLLER_ROUTE_PAYMENT = 'ops/payment/';

    /**
     * Return ops payment config information
     *
     * @param string $path
     * @param int $storeId
     * @return Simple_Xml
     */
    public function getConfigData($path, $storeId=null)
    {
        if (!empty($path)) {
            return Mage::getStoreConfig(self::OPS_PAYMENT_PATH . $path, $storeId);
        }
        return false;
    }

    /**
     * Return SHA1-in crypt key from config. Setup on admin place.
     *
     * @param int $storeId
     * @return string
     */
    public function getShaInCode($storeId=null)
    {
        return Mage::helper('core')->decrypt($this->getConfigData('secret_key_in', $storeId));
    }

    /**
     * Return SHA1-out crypt key from config. Setup on admin place.
     * @param int $storeId
     * @return string
     */
    public function getShaOutCode($storeId=null)
    {
        return Mage::helper('core')->decrypt($this->getConfigData('secret_key_out', $storeId));
    }

    /**
     * Return frontend gateway path, get from config. Setup on admin place.
     *
     * @param int $storeId
     * @return string
     */
    public function getFrontendGatewayPath($storeId=null)
    {
        return $this->getConfigData('frontend_gateway', $storeId);
    }

    /**
     * Return Direct Link Gateway path, get from config. Setup on admin place.
     *
     * @param int $storeId
     * @return string
     */
    public function getDirectLinkGatewayPath($storeId=null)
    {
        return $this->getConfigData('directlink_gateway', $storeId);
    }

    public function getDirectLinkGatewayOrderPath($storeId=null)
    {
        return $this->getConfigData('directlink_gateway_order', $storeId);
    }

    /**
     * Return API User, get from config. Setup on admin place.
     *
     * @param int $storeId
     * @return string
     */
    public function getApiUserId($storeId=null)
    {
        return $this->getConfigData('api_userid', $storeId);
    }

    /**
     * Return API Passwd, get from config. Setup on admin place.
     *
     * @param int $storeId
     * @return string
     */
    public function getApiPswd($storeId=null)
    {
        return Mage::helper('core')->decrypt($this->getConfigData('api_pswd', $storeId));
    }

    /**
     * Get PSPID, affiliation name in ops system
     *
     * @param int $storeId
     * @return string
     */
    public function getPSPID($storeId=null)
    {
        return $this->getConfigData('pspid', $storeId);
    }

    public function getPaymentAction($storeId=null)
    {
        return $this->getConfigData('payment_action', $storeId);
    }

    /**
     * Get paypage template for magento style templates using
     *
     * @return string
     */
    public function getPayPageTemplate()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'paypage',
            array('_nosid' => true, '_secure' => $this->isCurrentlySecure()));
    }

    /**
     * Return url which ops system will use as accept
     *
     * @return string
     */
    public function getAcceptUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'accept',
            array('_nosid' => true, '_secure' => $this->isCurrentlySecure()));
    }

    /**
     * Return url which ops system will use as accept for alias generation
     *
     * @return string
     */
    public function getAliasAcceptUrl($storeId=null, $admin=false)
    {
        $params = array(
            '_secure' => $this->isCurrentlySecure(),
            '_nosid' => true
        );
        if (false === is_null($storeId)) {
            $params['_store'] = $storeId;
        }
        $urlModelKey = $admin ? 'adminhtml/url' : 'core/url';
        return Mage::getModel($urlModelKey)->getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'acceptAlias', $params);
    }

    /**
     * Return url which ops system will use as decline url
     *
     * @return string
     */
    public function getDeclineUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'decline',
            array('_nosid' => true, '_secure' => $this->isCurrentlySecure()));
    }

    /**
     * Return url which ops system will use as exception url
     *
     * @return string
     */
    public function getExceptionUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'exception',
            array('_nosid' => true, '_secure' => $this->isCurrentlySecure()));
    }

    /**
     * Return url which ops system will use as exception url for alias generation
     *
     * @return string
     */
    public function getAliasExceptionUrl($storeId=null, $admin=false)
    {
        $params = array(
            '_secure' => $this->isCurrentlySecure(),
            '_nosid' => true
        );
        if (false === is_null($storeId)) {
            $params['_store'] = $storeId;
        }
        $urlModelKey = $admin ? 'adminhtml/url' : 'core/url';
        return Mage::getModel($urlModelKey)->getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'exceptionAlias', $params);
    }

    /**
     * Return url which ops system will use as cancel url
     *
     * @return string
     */
    public function getCancelUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'cancel',
            array('_nosid' => true, '_secure' => $this->isCurrentlySecure()));
    }

    /**
     * Return url which ops system will use as continue shopping url
     *
     * @param array $redirect
     *
     * @return string
     */
    public function getContinueUrl($redirect = array())
    {
        $urlParams = array('_nosid' => true, '_secure' => $this->isCurrentlySecure());
        if (!empty($redirect)) $urlParams = array_merge($redirect, $urlParams);
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'continue', $urlParams);
    }

    /**
     * Return url to redirect after confirming the order
     *
     * @return string
     */
    public function getPaymentRedirectUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'placeform',
            array('_secure' => true, '_nosid' => true));
    }

    /**
     * Return 3D Secure url to redirect after confirming the order
     *
     * @return string
     */
    public function get3dSecureRedirectUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'placeform3dsecure',
            array('_secure' => true, '_nosid' => true));
    }

    public function getSaveCcBrandUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'saveCcBrand',
            array('_secure' => $this->isCurrentlySecure(), '_nosid' => true));
    }

    public function getGenerateHashUrl($storeId=null, $admin=false)
    {
        $params = array(
            '_secure' => $this->isCurrentlySecure(),
            '_nosid' => true
        );
        if (false === is_null($storeId)) {
            $params['_store'] = $storeId;
        }
        $urlModelKey = $admin ? 'adminhtml/url' : 'core/url';
        return Mage::getModel($urlModelKey)->getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'generatehash', $params);
    }

    public function getRegisterDirectDebitPaymentUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'registerDirectDebitPayment',
            array('_secure' => $this->isCurrentlySecure(), '_nosid' => true));
    }

    /**
     * Checks if requests should be logged or not regarding configuration
     *
     * @return boolean
     */
    public function shouldLogRequests($storeId=null)
    {
        return $this->getConfigData('debug_flag', $storeId);
    }

    public function hasCatalogUrl()
    {
        return Mage::getStoreConfig('payment_services/ops/showcatalogbutton');
    }

    public function hasHomeUrl()
    {
        return Mage::getStoreConfig('payment_services/ops/showhomebutton');
    }

    public function getAcceptedCcTypes()
    {
        return Mage::getStoreConfig('payment/ops_cc/types');
    }

    public function getInlinePaymentCcTypes()
    {
        $redirectAll = (bool) (int) Mage::getStoreConfig('payment/ops_cc/redirect_all');
        if ($redirectAll) {
            return array();
        }

        $inlineTypes = Mage::getStoreConfig('payment/ops_cc/inline_types');
        if (false == is_array($inlineTypes)) {
            $inlineTypes = explode(',', $inlineTypes);
        }
        return $inlineTypes;
    }

    public function get3dSecureIsActive()
    {
        return Mage::getStoreConfig('payment/ops_cc/enabled_3dsecure');
    }

    public function getDirectDebitCountryIds()
    {
        return Mage::getStoreConfig('payment/ops_directDebit/countryIds');
    }

    public function getBankTransferCountryIds()
    {
        return Mage::getStoreConfig('payment/ops_bankTransfer/countryIds');
    }

    public function getDirectEbankingBrands()
    {
       return Mage::getStoreConfig('payment/ops_directEbanking/brands');
    }

    public function getAliasGatewayUrl($storeId = null)
    {
        return $this->getConfigData('ops_alias_gateway', $storeId);
    }

    public function getCcSaveAliasUrl($storeId = null, $admin=false)
    {
        $params = array(
            '_secure' => $this->isCurrentlySecure()
        );
        if (false === is_null($storeId)) {
            $params['_store'] = $storeId;
        }
        if ($admin) {
            return Mage::getModel('adminhtml/url')->getUrl('ops/admin/saveAlias', $params);
        } else {
            return Mage::getUrl('ops/payment/saveAlias', $params);
        }
    }

    /**
     * get deeplink to transaction view at Ingenico Payment Services
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return string
     */
    public function getOpsAdminPaymentUrl($payment)
    {
        return '';
    }

    public function isCurrentlySecure()
    {
        return Mage::app()->getStore()->isCurrentlySecure();
    }

    public function getIntersolveBrands()
    {
        $result = array();
        $brands = Mage::getStoreConfig('payment/ops_interSolve/brands');
        if (!is_null($brands)) {
            $result = unserialize($brands);
        }
        return $result;
    }

    public function getAllCcTypes()
    {
        return explode(',', Mage::getStoreConfig('payment/ops_cc/availableTypes'));
    }

    /**
     * get keys of parameters to be shown in scoring information block
     *
     * @return array
     */
    public function getAdditionalScoringKeys()
    {
        return array(
            'AAVCHECK',
            'CVCCHECK',
            'CCCTY',
            'IPCTY',
            'NBREMAILUSAGE',
            'NBRIPUSAGE',
            'NBRIPUSAGE_ALLTX',
            'NBRUSAGE',
            'VC',
            'CARDNO',
            'ED',
            'CN'
        );
    }

    public function getSendInvoice()
    {
        return (bool) (int) Mage::getStoreConfig('payment_services/ops/send_invoice');
    }

    /**
     * if payment method with given code is enabled for backend payments
     *
     * @param string $code Payment method code
     * @return bool
     */
    public function isEnabledForBackend($code, $storeId = 0)
    {
        return (bool) (int) Mage::getStoreConfig('payment/' . $code . '/backend_enabled', $storeId);
    }

    public function isAliasInfoBlockEnabled()
    {
        return (bool) (int) Mage::getStoreConfig('payment/ops_cc/show_alias_manager_info_for_guests');
    }
    
    /**
     * return config value for Alias Manager enabled
     * 
     * @return bool
     */
    public function isAliasManagerEnabled()
    {
        return (bool) Mage::getStoreConfig('payment/ops_cc/active_alias');
    }

    /**
     * getter for usage of order reference
     */
    public function getOrderReference($storeId = null)
    {
        return $this->getConfigData('redirectOrderReference', $storeId);
    }

    /**
     * @param int $storeId - the store id to use
     *
     * @return int whether the QuoteId should be shown in
     * the order grid (1) or not (0)
     */
    public function getShowQuoteIdInOrderGrid($storeId = null)
    {
        return $this->getConfigData('showQuoteIdInOrderGrid', $storeId);
    }

    /**
     * @param int $storeId - the store id to use
     *
     * @return bool whether the tracking code fro fraud detection
     * is activated or not
     */
    public function isTrackingCodeActivated($storeId = null)
    {
        return (bool) $this->getConfigData('enableTrackingCode', $storeId);
    }


    /**

     * Check if the current environment is frontend or backend
     * 
     * @return boolean
     */
    public function isFrontendEnvironment()
    {
        return (false === Mage::app()->getStore()->isAdmin());
    }

     /*
     * getter for the accept route for payments
     *
     * @return string
     */
    public function getAcceptRedirectRoute()
    {
        return self::OPS_CONTROLLER_ROUTE_PAYMENT . 'accept';
    }

    /**
     * getter for the cancel route for payments
     *
     * @return string
     */
    public function getCancelRedirectRoute()
    {
        return self::OPS_CONTROLLER_ROUTE_PAYMENT . 'cancel';
    }

    /**
     * getter for the decline route for payments
     *
     * @return string
     */
    public function getDeclineRedirectRoute()
    {
        return self::OPS_CONTROLLER_ROUTE_PAYMENT . 'decline';
    }

    /**
     * getter for the decline route for payments
     *
     * @return string
     */
    public function getExceptionRedirectRoute()
    {
        return self::OPS_CONTROLLER_ROUTE_PAYMENT . 'exception';
    }


    public function getMethodsRequiringAdditionalParametersFor($operation)
    {
        return Mage::getStoreConfig('payment/additional_params_required/' . $operation);
    }


    /**
     * returns the url for the maintenance api calls
     *
     * @param null $storeId
     *
     * @return string - the url for the maintenance api calls
     */
    public function getDirectLinkMaintenanceApiPath($storeId=null)
    {
        return $this->getConfigData('directlink_maintenance_api', $storeId);
    }

    /**
     * getter for the iDeal issuers
     *
     * @return array
     */
    public function getIDealIssuers()
    {
        return Mage::getStoreConfig('payment/ops_iDeal/issuer');
    }

    /**
     * whether extra parameters needs to be passed to Ingenico Payment Services or not
     *
     * @param null $storeId
     *
     * @return bool - true if it's enabled, false otherwise
     */
    public function canSubmitExtraParameter($storeId = null)
    {
        return (bool)$this->getConfigData('submitExtraParameters', $storeId);
    }

    public function getParameterLengths()
    {
        return $this->getConfigData('paramLength');
    }

    public function getFrontendFieldMapping()
    {
        return $this->getConfigData('frontendFieldMapping');

    }

    public function getValidationUrl()
    {
        return Mage::getUrl(self::OPS_CONTROLLER_ROUTE_PAYMENT . 'validate',
            array('_secure' => $this->isCurrentlySecure(), '_nosid' => true));
    }

    public function getInlineOrderReference($storeId = null)
    {
        return $this->getConfigData('inlineOrderReference', $storeId);
    }
}
