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

/**OPS_PAYMENT_PROCESSING
 * OPS payment method model
 */
class Netresearch_OPS_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'ops';
    protected $_formBlockType = 'ops/form';
    protected $_infoBlockType = 'ops/info';
    protected $_config = null;
    protected $requestHelper = null;
    protected $backendOperationParameterModel = null;

    /**
     * @param null $backendOperationParameterModel
     */
    public function setBackendOperationParameterModel($backendOperationParameterModel)
    {
        $this->backendOperationParameterModel = $backendOperationParameterModel;
    }

    /**
     * @return Netresearch_OPS_Model_Backend_Operation_Parameter
     */
    public function getBackendOperationParameterModel()
    {
        if (null === $this->backendOperationParameterModel) {
            $this->backendOperationParameterModel = Mage::getModel('ops/backend_operation_parameter');
        }
        return $this->backendOperationParameterModel;
    }

     /**
     * Magento Payment Behaviour Settings
     */
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_isInitializeNeeded      = true;

    /**
     * OPS template modes
     */
    const TEMPLATE_OPS              = 'ops';
    const TEMPLATE_MAGENTO          = 'magento';

    /**
     * redirect references
     */

    const REFERENCE_QUOTE_ID        = 'quoteId';
    const REFERENCE_ORDER_ID        = 'orderId';

    /**
     * OPS response status
     */
    const OPS_INVALID                             = 0;
    const OPS_PAYMENT_CANCELED_BY_CUSTOMER        = 1;
    const OPS_AUTH_REFUSED                        = 2;

    const OPS_ORDER_SAVED                         = 4;
    const OPS_AWAIT_CUSTOMER_PAYMENT              = 41;
    const OPS_OPEN_INVOICE_DE_PROCESSED           = 41000001;
    const OPS_WAITING_FOR_IDENTIFICATION          = 46;

    const OPS_AUTHORIZED                          = 5;
    const OPS_AUTHORIZED_KWIXO                    = 50;
    const OPS_AUTHORIZED_WAITING                  = 51;
    const OPS_AUTHORIZED_UNKNOWN                  = 52;
    const OPS_STAND_BY                            = 55;
    const OPS_PAYMENTS_SCHEDULED                  = 56;
    const OPS_AUTHORIZED_TO_GET_MANUALLY          = 59;

    const OPS_VOIDED                              = 6;
    const OPS_VOID_WAITING                        = 61;
    const OPS_VOID_UNCERTAIN                      = 62;
    const OPS_VOID_REFUSED                        = 63;
    const OPS_VOIDED_ACCEPTED                     = 64;

    const OPS_PAYMENT_DELETED                     = 7;
    const OPS_PAYMENT_DELETED_WAITING             = 71;
    const OPS_PAYMENT_DELETED_UNCERTAIN           = 72;
    const OPS_PAYMENT_DELETED_REFUSED             = 73;
    const OPS_PAYMENT_DELETED_OK                  = 74;
    const OPS_PAYMENT_DELETED_PROCESSED_MERCHANT  = 75;

    const OPS_REFUNDED                            = 8;
    const OPS_REFUND_WAITING                      = 81;
    const OPS_REFUND_UNCERTAIN_STATUS             = 82;
    const OPS_REFUND_REFUSED                      = 83;
    const OPS_REFUND_DECLINED_ACQUIRER            = 84;
    const OPS_REFUND_PROCESSED_MERCHANT           = 85;

    const OPS_PAYMENT_REQUESTED                   = 9;
    const OPS_PAYMENT_PROCESSING                  = 91;
    const OPS_PAYMENT_UNCERTAIN                   = 92;
    const OPS_PAYMENT_REFUSED                     = 93;
    const OPS_PAYMENT_DECLINED_ACQUIRER           = 94;
    const OPS_PAYMENT_PROCESSED_MERCHANT          = 95;
    const OPS_PAYMENT_IN_PROGRESS                 = 99;

    /**
     * Layout of the payment method
     */
    const PMLIST_HORIZONTAL_LEFT            = 0;
    const PMLIST_HORIZONTAL                 = 1;
    const PMLIST_VERTICAL                   = 2;

    /**
     * OPS payment action constant
     */
    const OPS_AUTHORIZE_ACTION = 'RES';
    const OPS_AUTHORIZE_CAPTURE_ACTION = 'SAL';
    const OPS_CAPTURE_FULL = 'SAS';
    const OPS_CAPTURE_PARTIAL = 'SAL';
    const OPS_CAPTURE_DIRECTDEBIT_NL = 'VEN';
    const OPS_DELETE_AUTHORIZE = 'DEL';
    const OPS_DELETE_AUTHORIZE_AND_CLOSE = 'DES';
    const OPS_REFUND_FULL = 'RFS';
    const OPS_REFUND_PARTIAL = 'RFD';

    /**
     * 3D-Secure
     */
    const OPS_DIRECTLINK_WIN3DS = 'MAINW';

    /**
     * Module Transaction Type Codes
     */
    const OPS_CAPTURE_TRANSACTION_TYPE = 'capture';
    const OPS_VOID_TRANSACTION_TYPE = 'void';
    const OPS_REFUND_TRANSACTION_TYPE = 'refund';
    const OPS_DELETE_TRANSACTION_TYPE = 'delete';

    /**
     * Return OPS Config
     *
     * @return Netresearch_OPS_Model_Config
     */
    public function getConfig()
    {
        if (is_null($this->_config)) {
           $this->_config = Mage::getSingleton('ops/config');
        }

        return $this->_config;
    }

    /**
     * returns the request helper
     *
     * @return Netresearch_OPS_Helper_Payment_Request
     */
    public function getRequestHelper()
    {
        if (null === $this->requestHelper) {
            $this->requestHelper = Mage::helper('ops/payment_request');
        }

        return $this->requestHelper;
    }

    /**
     * if payment method is available
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return boolean
     */
    public function isAvailable($quote = null)
    {

        $storeId = 0;
        // allow multi store/site for backend orders with disabled backend payment methods in default store
        if (!is_null($quote) && !is_null($quote->getId())) {
            $storeId = $quote->getStoreId();
        }
        if (Mage_Core_Model_App::ADMIN_STORE_ID == Mage::app()->getStore()->getId()
            && false == $this->isEnabledForBackend($storeId)
        ) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * if method is enabled for backend payments
     *
     * @return bool
     */
    public function isEnabledForBackend($storeId = 0)
    {
        return $this->getConfig()->isEnabledForBackend($this->_code, $storeId);
    }

    /**
     * Redirect url to ops submit form
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
          return $this->getConfig()->getPaymentRedirectUrl();
    }

    protected function getPayment()
    {
        $checkout = Mage::getSingleton('checkout/session');
        $payment = $checkout->getQuote()->getPayment();
        if (!$payment->getId()) {
            $payment = Mage::getModel('sales/order')->loadByIncrementId($checkout->getLastRealOrderId())->getPayment();
        }
        return $payment;
    }

    public function getOpsBrand($payment=null)
    {
        if(is_null($payment)){
            $payment = $this->getPayment();
        }
        return trim($payment->getAdditionalInformation('PM'));
    }

    public function getOpsCode($payment=null)
    {
        if(is_null($payment)){
            $payment = $this->getPayment();
        }
        return trim($payment->getAdditionalInformation('BRAND'));
//        return str_replace('ops_', '', $this->_code);
    }

    /**
     * Return payment_action value from config area
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->getConfig()->getConfigData('payment_action');
    }

    public function getMethodDependendFormFields($order, $requestParams=null)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        if (null === $shippingAddress || false === $shippingAddress) {
            $shippingAddress = $billingAddress;
        }
        $payment = $order->getPayment()->getMethodInstance();
        $quote = Mage::helper('ops/order')->getQuote($order->getQuoteId());

        $formFields = array();
        $formFields['ORIG']         = Mage::helper("ops")->getModuleVersionString();
        $formFields['BRAND']        = $payment->getOpsBrand($order->getPayment());
        if ($this->getConfig()->canSubmitExtraParameter($order->getStoreId())) {
            $formFields['CN']           = $billingAddress->getFirstname().' '.$billingAddress->getLastname();
            $formFields['COM']          = $this->_getOrderDescription($order);
            $formFields['ADDMATCH']     = Mage::helper('ops/order')->checkIfAddressesAreSame($order);
            $ownerParams = $this->getRequestHelper()->getOwnerParams($quote, $billingAddress);
            $shipToParams = $this->getRequestHelper()->extractShipToParameters($shippingAddress, $quote);
            $formFields['ECOM_BILLTO_POSTAL_POSTALCODE'] = $billingAddress->getPostcode();
            $formFields = array_merge($formFields, $ownerParams);
            $formFields = array_merge($formFields, $shipToParams);
        }

        if (Mage::helper('customer/data')->isLoggedIn()) {
            $formFields['CUID'] = Mage::helper('customer')->getCustomer()->getId();
        }

        return $formFields;
    }

    /**
     * Prepare params array to send it to gateway page via POST
     *
     * @param Mage_Sales_Model_Order
     * @param array
     * @return array
     */
    public function getFormFields($order, $requestParams)
    {
        if (empty($order)) {
            if (!($order = $this->getOrder())) {
                return array();
            }
        }
        $payment = $order->getPayment()->getMethodInstance();
        $formFields = array();
        $formFields['PSPID']    = $this->getConfig()->getPSPID($order->getStoreId());
        $formFields['AMOUNT']   = $this->getHelper()->getAmount($order->getBaseGrandTotal());
        $formFields['CURRENCY'] = Mage::app()->getStore()->getBaseCurrencyCode();
        $formFields['ORDERID']  =  Mage::helper('ops/order')->getOpsOrderId($order);
        $formFields['LANGUAGE'] = Mage::app()->getLocale()->getLocaleCode();
        $formFields['PM']       = $payment->getOpsCode($order->getPayment());
        $formFields['EMAIL']    = $order->getCustomerEmail();

        $methodDependendFields = $this->getMethodDependendFormFields($order, $requestParams);
        if (is_array($methodDependendFields)) {
            $formFields = array_merge($formFields, $methodDependendFields);
        }

        $paymentAction = $this->_getOPSPaymentOperation();
        if ($paymentAction ) {
            $formFields['OPERATION'] = $paymentAction;
        }


        if ($this->getConfig()->getConfigData('template')=='ops') {
            $formFields['TP']= '';
            $formFields['PMLISTTYPE'] = $this->getConfig()->getConfigData('pmlist');
        } else {
            $formFields['TP']= $this->getConfig()->getPayPageTemplate();
        }
        $formFields['TITLE']            = $this->getConfig()->getConfigData('html_title');
        $formFields['BGCOLOR']          = $this->getConfig()->getConfigData('bgcolor');
        $formFields['TXTCOLOR']         = $this->getConfig()->getConfigData('txtcolor');
        $formFields['TBLBGCOLOR']       = $this->getConfig()->getConfigData('tblbgcolor');
        $formFields['TBLTXTCOLOR']      = $this->getConfig()->getConfigData('tbltxtcolor');
        $formFields['BUTTONBGCOLOR']    = $this->getConfig()->getConfigData('buttonbgcolor');
        $formFields['BUTTONTXTCOLOR']   = $this->getConfig()->getConfigData('buttontxtcolor');
        $formFields['FONTTYPE']         = $this->getConfig()->getConfigData('fonttype');
        $formFields['LOGO']             = $this->getConfig()->getConfigData('logo');
        $formFields['HOMEURL']          = $this->getConfig()->hasHomeUrl() ? $this->getConfig()->getContinueUrl(array('redirect' => 'home')) : 'NONE';
        $formFields['CATALOGURL']       = $this->getConfig()->hasCatalogUrl() ? $this->getConfig()->getContinueUrl(array('redirect' => 'catalog')) : '';
        $formFields['ACCEPTURL']        = $this->getConfig()->getAcceptUrl();
        $formFields['DECLINEURL']       = $this->getConfig()->getDeclineUrl();
        $formFields['EXCEPTIONURL']     = $this->getConfig()->getExceptionUrl();
        $formFields['CANCELURL']        = $this->getConfig()->getCancelUrl();
        $formFields['BACKURL']          = $this->getConfig()->getCancelUrl();

        $shaSign = Mage::helper('ops/payment')->shaCrypt(Mage::helper('ops/payment')->getSHASign($formFields, null, $order->getStoreId()));

        $helper = Mage::helper('ops');
        $helper->log($helper->__("Register Order %s in Ingenico Payment Services \n\nAll form fields: %s\nIngenico Payment Services String to hash: %s\nHash: %s",
            $order->getIncrementId(),
            serialize($formFields),
            Mage::helper('ops/payment')->getSHASign($formFields, null, $order->getStoreId()),
            $shaSign
        ));

        $formFields['SHASIGN']  = $shaSign;
        return $formFields;
    }

    /**
     * Get OPS Payment Action value
     *
     * @param string
     * @return string
     */
    protected function _getOPSPaymentOperation()
    {
        $value = $this->getPaymentAction();
        if ($value==Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
            $value = self::OPS_AUTHORIZE_ACTION;
        } elseif ($value==Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            $value = self::OPS_AUTHORIZE_CAPTURE_ACTION;
        }
        return $value;
    }


    protected function  convertToLatin1($StringToConvert)
    {
        $returnString = '';
        $chars = str_split($StringToConvert);
        foreach ($chars as $char) {
            if (31 < ord($char) && ord($char) < 127) {
                $returnString .= $char;
            }
        }
        return $returnString;
    }

    /**
     * get formated order description
     *
     * @param Mage_Sales_Model_Order
     * @return string
     */
    public function _getOrderDescription($order)
    {
        $descriptionItems = array();
        $description = '';
        $lengs = 0;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) continue;
            // we know that Ingenico Payment Services is not able to handle characters that are not available in iso-8859-1
//            $descriptionItems[] = mb_ereg_replace("[^a-zA-Z0-9äáàéèíóöõúüûÄÁÀÉÍÓÖÕÚÜÛ_ ]" , "" , $item->getName());
            $descriptionItems[] = $this->convertToLatin1($item->getName());
            $description = Mage::helper('core/string')->substr(implode(', ', $descriptionItems), 0, 100);
            //COM field is limited to 100 chars max
            if (100 <= Mage::helper('core/string')->strlen($description)) {
                break;
            }
        }
        return $description;
    }

    /**
     * Get Main OPS Helper
     *
     * @return Netresearch_OPS_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('ops/data');
    }

    /**
     * Determines if a capture will be processed
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @throws Mage_Core_Exception
     * @return \Mage_Payment_Model_Abstract|void
     */
    public function capture(Varien_Object $payment, $amount)
    {
        // disallow Ingenico Payment Services online capture if amount is zero
        if ($amount < 0.01) {
            return parent::capture($payment, $amount);
        }

        if (true === Mage::registry('ops_auto_capture')) {
           Mage::unregister('ops_auto_capture');
           return parent::capture($payment, $amount);
        }

        $orderId = $payment->getOrder()->getId();
        $arrInfo = Mage::helper('ops/order_capture')->prepareOperation($payment, $amount);
        $storeId = $payment->getOrder()->getStoreId();

        if ($this->isRedirectNoticed($orderId)) {
            return $this;
        }
        try {
            $requestParams = $this->getBackendOperationParameterModel()->getParameterFor(
                self::OPS_CAPTURE_TRANSACTION_TYPE,
                $this,
                $payment,
                $amount,
                $arrInfo
            );
            $response = Mage::getSingleton('ops/api_directlink')->performRequest(
                    $requestParams,
                    Mage::getModel('ops/config')->getDirectLinkGatewayPath($storeId),
                    $storeId
                );
            Mage::helper('ops/payment')->saveOpsStatusToPayment($payment, $response);

            if ($response['STATUS'] == self::OPS_PAYMENT_PROCESSING ||
                $response['STATUS'] == self::OPS_PAYMENT_UNCERTAIN ||
                $response['STATUS'] == self::OPS_PAYMENT_IN_PROGRESS
                ) {
                Mage::helper('ops/directlink')->directLinkTransact(
                    Mage::getSingleton("sales/order")->loadByIncrementId($payment->getOrder()->getIncrementId()),
                    $response['PAYID'],
                    $response['PAYIDSUB'],
                    $arrInfo,
                    self::OPS_CAPTURE_TRANSACTION_TYPE,
                    $this->getHelper()->__('Start Ingenico Payment Services %s capture request',$arrInfo['type']));
                $order = Mage::getModel('sales/order')->load($orderId); //Reload order to avoid wrong status
                $order->addStatusHistoryComment(
                    Mage::helper('ops')->__(
                        'Invoice will be created automatically as soon as Ingenico Payment Services sends an acknowledgement. Ingenico Payment Services status: %s.',
                        Mage::helper('ops')->getStatusText($response['STATUS'])
                    )
                );
                $order->save();
                $this->getHelper()->redirectNoticed(
                    $orderId,
                    $this->getHelper()->__(
                        'Invoice will be created automatically as soon as Ingenico Payment Services sends an acknowledgement. Ingenico Payment Services status: %s.',
                        Mage::helper('ops')->getStatusText($response['STATUS'])
                    )
                );
                return $this;
            }
            elseif ($response['STATUS'] == self::OPS_PAYMENT_PROCESSED_MERCHANT || $response['STATUS'] == self::OPS_PAYMENT_REQUESTED) {
                 return parent::capture($payment, $amount);
            } else{
                 Mage::throwException(
                     $this->getHelper()->__(
                         'The Invoice was not created. Ingenico Payment Services status: %s.',
                         Mage::helper('ops')->getStatusText($response['STATUS'])
                     )
                 );
            }
        }
        catch (Exception $e){
            Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
            Mage::helper('ops')->log("Exception in capture request:".$e->getMessage());
            throw new Mage_Core_Exception($e->getMessage());
        }
    }


    /**
     * Refund
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return \Mage_Payment_Model_Abstract|void
     */
    public function refund(Varien_Object $payment, $amount)
    {
        //If the refund will be created by OPS, Refund Create Method to nothing
        if (true === Mage::registry('ops_auto_creditmemo')) {
           Mage::unregister('ops_auto_creditmemo');
           return parent::refund($payment, $amount);
        }

        $refundHelper = Mage::helper('ops/order_refund')->setAmount($amount)->setPayment($payment);
        $arrInfo = $refundHelper->prepareOperation($payment, $amount);
        $storeId = $payment->getOrder()->getStoreId();
        $operation = $arrInfo['operation'];

        try {
            $requestParams  = $this->getBackendOperationParameterModel()->getParameterFor(
                self::OPS_REFUND_TRANSACTION_TYPE,
                $this,
                    $payment,
                    $amount,
                    $arrInfo
            );
            $response = Mage::getSingleton('ops/api_directlink')->performRequest(
                    $requestParams,
                    Mage::getModel('ops/config')->getDirectLinkGatewayPath($storeId),
                    $storeId
                );
            Mage::helper('ops/payment')->saveOpsStatusToPayment($payment, $response);
            if (($response['STATUS'] == self::OPS_REFUND_WAITING)
                || ($response['STATUS'] == self::OPS_REFUND_UNCERTAIN_STATUS)) {
                Mage::helper('ops/payment')->saveOpsRefundOperationCodeToPayment($payment, $operation);
                $refundHelper->createRefundTransaction($response);

            } elseif (($response['STATUS'] == self::OPS_REFUNDED)
                    || ($response['STATUS'] == self::OPS_REFUND_PROCESSED_MERCHANT)) {
                //do refund directly if response is ok already
                Mage::helper('ops/payment')->saveOpsRefundOperationCodeToPayment($payment, $operation);
                $refundHelper->createRefundTransaction($response, 1);
                return parent::refund($payment, $amount);
            } else {
                Mage::throwException($this->getHelper()->__('The CreditMemo was not created. Ingenico Payment Services status: %s.',$response['STATUS']));
            }

            Mage::getSingleton('core/session')->addNotice($this->getHelper()->__('The Creditmemo will be created automatically as soon as Ingenico Payment Services sends an acknowledgement.'));
            $this->getHelper()->redirect(
                Mage::getUrl('*/sales_order/view', array('order_id' => $payment->getOrder()->getId()))
            );
        }
        catch (Exception $e) {
            Mage::logException($e);
            Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * determines if the close transaction parameter is set in the credit memo data
     *
     * @param array $creditMemoData
     *
     * @return bool
     */
    protected function getCloseTransactionFromCreditMemoData($creditMemoData)
    {
        $closeTransaction = false;
        if (array_key_exists('ops_close_transaction', $creditMemoData)
            && strtolower(trim($creditMemoData['ops_close_transaction'])) == 'on') {
            $closeTransaction = true;
        }
        return $closeTransaction;
    }

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        try {
            $order = Mage::getModel('sales/order')->load(Mage::app()->getRequest()->getParam('order_id'));

            // if Ingenico Payment Services transaction is closed then no online refund is possible
            if ($order->getPayment()
                && $order->getPayment()->getAdditionalInformation()
                && array_key_exists('canRefund', $order->getPayment()->getAdditionalInformation())
                && false === $order->getPayment()->getAdditionalInformation('canRefund')
            ) {
                return false;
            }

            if (false === Mage::helper('ops/directlink')->hasPaymentTransactions($order,self::OPS_REFUND_TRANSACTION_TYPE)) {
                return $this->_canRefund;
            } else {
                //Add the notice if no exception was thrown, because in this case there is one creditmemo in the transaction queue
                Mage::getSingleton('core/session')->addNotice(
                    $this->getHelper()->__('There is already one creditmemo in the queue. The Creditmemo will be created automatically as soon as Ingenico Payment Services sends an acknowledgement.')
                );
                $this->getHelper()->redirect(
                    Mage::getUrl('*/sales_order/view', array('order_id' => $order->getId()))
                );
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            return $this->_canRefund;
        }
    }

    /**
     * Custom cancel behavior, deny cancel and force custom to use void instead
     *
     * @param Varien_Object $payment
     * @return void
     * @throws Mage_Core_Exception
     */
    public function cancel(Varien_Object $payment)
    {
        /*
         * Important: If an order was voided successfully and the user clicks on cancel in order-view
         * this method is not triggered anymore
         */

        //Proceed parent cancel method in case that regirstry value ops_auto_void is set
        if (true === Mage::registry('ops_auto_void')):
           Mage::unregister('ops_auto_void');
           return parent::cancel($payment);
        endif;

        //If order has state 'pending_payment' and the payment has Ingenico Payment Services-status 0 or null (unknown) then cancel the order
        if (true === $this->canCancelManually($payment->getOrder())) {
            $payment->getOrder()->addStatusHistoryComment(
                $this->getHelper()->__("The order was cancelled manually. The Ingenico Payment Services-state is 0 or null.")
            );
            return parent::cancel($payment);
        }

        //Abort cancel method by throwing a Mage_Core_Exception
        throw new Mage_Core_Exception($this->getHelper()->__('Please use void to cancel the operation.'));
    }

    /**
     * Custom void behavior, trigger Ingenico Payment Services cancel request
     *
     * @param Varien_Object $payment
     * @return void
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment)
    {
        //Proceed parent void method in case that registry value ops_auto_void is set
        if (true === Mage::registry('ops_auto_void')) {
            Mage::unregister('ops_auto_void');
            return parent::void($payment);
        }

        //Set initital params
        $params = Mage::app()->getRequest()->getParams();
        $orderID = $payment->getOrder()->getId();
        $order = Mage::getModel("sales/order")->load($orderID);

        //Calculate amount which has to be captured
        $alreadyCaptured = Mage::helper('ops/order_void')->getCapturedAmount(
            $order
        );
        $grandTotal = Mage::helper('ops/payment')
            ->getBaseGrandTotalFromSalesObject($order);
        $voidAmount = $grandTotal - $alreadyCaptured;
        $storeId = $order->getStoreId();
        //Build void directLink-Request-Params
        $requestParams = array(
            'AMOUNT'    => $this->getHelper()->getAmount($voidAmount),
            'PAYID'     => $payment->getAdditionalInformation('paymentId'),
            'OPERATION' => self::OPS_DELETE_AUTHORIZE,
            'CURRENCY'  => Mage::app()->getStore($storeId)->getBaseCurrencyCode()
        );

        //Check if there is already a waiting void transaction, if yes: redirect to order view
        if (Mage::helper('ops/directlink')->checkExistingTransact(
            self::OPS_VOID_TRANSACTION_TYPE, $orderID
        )
        ) {
            $this->getHelper()->redirectNoticed(
                $orderID, $this->getHelper()->__(
                    'You already sent a void request. Please wait until the void request will be acknowledged.'
                )
            );
            return;
        }

        //Check if there is already a waiting capture transaction, if yes: redirect to order view
        if (Mage::helper('ops/directlink')->checkExistingTransact(
            self::OPS_CAPTURE_TRANSACTION_TYPE, $orderID
        )
        ) {
            $this->getHelper()->redirectNoticed(
                $orderID, $this->getHelper()->__(
                    'There is one capture request waiting. Please wait until this request is acknowledged.'
                )
            );
            return;
        }

        try {
            //perform ops cancel request
            $response = Mage::getSingleton('ops/api_directlink')
                ->performRequest(
                $requestParams,
                Mage::getModel('ops/config')->getDirectLinkGatewayPath($storeId),
                $order->getStoreId()
            );

            //Save ops response to payment transaction
            Mage::helper('ops/payment')->saveOpsStatusToPayment(
                $payment, $response
            );

            /*
             * If the ops response results in a waiting or uncertain state, create a void transaction which is waiting
             * for an asynchron directLink-postback
             */
            if ($response['STATUS'] == self::OPS_VOID_WAITING
                || $response['STATUS'] == self::OPS_VOID_UNCERTAIN
            ) {
                Mage::helper('ops/directlink')->directLinkTransact(
                    Mage::getSingleton("sales/order")->loadByIncrementId(
                        $payment->getOrder()->getIncrementId()
                    ),
                    // reload order to avoid canceling order before confirmation from ops
                    $response['PAYID'],
                    $response['PAYIDSUB'],
                    array(
                         'amount'       => $voidAmount,
                         'void_request' => Mage::app()->getRequest()->getParams(
                         ),
                         'response'     => $response,
                    ),
                    self::OPS_VOID_TRANSACTION_TYPE,
                    Mage::helper('ops')->__(
                        'Start Ingenico Payment Services void request. Ingenico Payment Services status: %s.',
                        $this->getHelper()->getStatusText($response['STATUS'])
                    )
                );
                $this->getHelper()->redirectNoticed(
                    $orderID, $this->getHelper()->__(
                        'The void request is sent. Please wait until the void request will be accepted.'
                    )
                );
                /*
                 * If the ops response results directly in accepted state, create a void transaction and execute parent void method
                 */
            } elseif ($response['STATUS'] == self::OPS_VOIDED
                || $response['STATUS'] == self::OPS_VOIDED_ACCEPTED
            ) {
                Mage::helper('ops/directlink')->directLinkTransact(
                    Mage::getSingleton("sales/order")->loadByIncrementId(
                        $payment->getOrder()->getIncrementId()
                    ),
                    // reload order to avoid canceling order before confirmation from ops
                    $response['PAYID'],
                    $response['PAYIDSUB'],
                    array(),
                    self::OPS_VOID_TRANSACTION_TYPE,
                    $this->getHelper()->__(
                        'Void order succeed. Ingenico Payment Services status: %s.',
                        $response['STATUS']
                    ),
                    1
                );
                return parent::void($payment);
            } else {
                Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
                Mage::throwException(
                    $this->getHelper()->__(
                        'Void order failed. Ingenico Payment Services status: %s.',
                        $response['STATUS']
                    )
                );
            }
            ;
        } catch (Exception $e) {
            Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
            Mage::helper('ops')->log(
                "Exception in void request:" . $e->getMessage()
            );
            throw new Mage_Core_Exception($e->getMessage());
        }
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
    }

    /**
     * get an array of fields with disputable value
     * users are asked to correct the values before redirect to Ingenico Payment Services
     *
     * @param Mage_Sales_Model_Order $order         Current order
     * @param array                  $requestParams Request parameters
     * @return array
     */
    public function getQuestionedFormFields($quote, $requestParams)
    {
        return array();
    }

    /**
     * if we need some missing form params
     * users are asked to correct the values before redirect to Ingenico Payment Services
     *
     * @param Mage_Sales_Model_Order $order
     * @param array                  $requestParams Parameters sent in current request
     * @param array                  $formFields    Parameters to be sent to Ingenico Payment Services
     * @return bool
     */
    public function hasFormMissingParams($order, $requestParams, $formFields=null)
    {
        if (false == is_array($requestParams)) {
            $requestParams = array();
        }
        if (is_null($formFields)) {
            $formFields = $this->getFormFields($order, $requestParams);
        }
        $availableParams = array_merge($requestParams, $formFields);
        $requiredParams = $this->getQuestionedFormFields($order, $requestParams);
        foreach ($requiredParams as $requiredParam) {
            if (false == array_key_exists($requiredParam, $availableParams)
                || 0 == strlen($availableParams[$requiredParam])
            ) {
                return true;
            }
        }
        return false;
    }


    /**
     * Check if order can be cancelled manually
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function canCancelManually($order)
    {
        $payment = $order->getPayment();

        //If order has state 'pending_payment' and the payment has Ingenico Payment Services-status 0 or null (unknown) then cancel the order
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
            && (true === is_null($payment->getAdditionalInformation('status')) || ($payment->getAdditionalInformation('status') == '0'))) {
            return true;
        } else {
            return false;
        }
    }

    public function getOpsHtmlAnswer($payment=null)
    {
        $returnValue = '';
        if (is_null($payment)) {
            $quoteId = Mage::getSingleton('checkout/session')->getQuote()->getId();
            if (is_null($quoteId)) {
                $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
                $order = Mage::getModel('sales/order')->loadByAttribute('increment_id', $orderIncrementId);
            } else {
                $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);
            }
            if ($order instanceof Mage_Sales_Model_Order && 0 < $order->getId()) {
                $payment = $order->getPayment();
                $returnValue = $payment->getAdditionalInformation('HTML_ANSWER');
            }
        } elseif ($payment instanceof Mage_Payment_Model_Info) {
            $returnValue = $payment->getAdditionalInformation('HTML_ANSWER');
        }
        return $returnValue;
    }

    public function getShippingTaxRate($order)
    {
        $shippingProduct = new Varien_Object();
        $priceIncludesTax = Mage::helper('tax')->priceIncludesTax(
            $order->getStore()
        );
        $taxPercent = $shippingProduct->getTaxPercent();
        $taxClassId = Mage::helper('tax')->getShippingTaxClass(
            $order->getStore()
        );
        $shippingProduct->setTaxClassId($taxClassId);
        if (is_null($taxPercent)) {
            if ($taxClassId) {
                $request = Mage::getSingleton('tax/calculation')
                    ->getRateRequest(
                    $order->getShippingAddress(), $order->getBillingAddress(),
                    null, $order->getStore()
                );
                $taxPercent = Mage::getSingleton('tax/calculation')->getRate(
                    $request->setProductClassId($taxClassId)
                );
            }
        }
        if ($taxClassId && $priceIncludesTax) {
            $request = Mage::getSingleton('tax/calculation')->getRateRequest(
                false, false, false, $order->getStore()
            );
            $taxPercent = Mage::getSingleton('tax/calculation')->getRate(
                $request->setProductClassId($shippingProduct->getTaxClassId())
            );
        }

        return $taxPercent;
    }


    protected function isRedirectNoticed($orderId)
    {
        if (Mage::helper('ops/directlink')->checkExistingTransact(self::OPS_CAPTURE_TRANSACTION_TYPE, $orderId)) {
            $this->getHelper()->redirectNoticed(
                $orderId,
                $this->getHelper()->__(
                    'You already sent a capture request. Please wait until the capture request is acknowledged.'
                )
            );

            return true;
        }
        if (Mage::helper('ops/directlink')->checkExistingTransact(self::OPS_VOID_TRANSACTION_TYPE, $orderId)) {
            $this->getHelper()->redirectNoticed(
                $orderId,
                $this->getHelper()->__(
                    'There is one void request waiting. Please wait until this request is acknowledged.'
                )
            );

            return true;
        }
        return false;
    }

    public function setConfig(Netresearch_OPS_Model_Config $config)
    {
        $this->_config = $config;
    }
}
