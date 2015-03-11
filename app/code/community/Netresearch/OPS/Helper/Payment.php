<?php

/**
 * Netresearch_OPS_Helper_Payment
 *
 * @package
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de>
 * @author    André Herrn <andre.herrn@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_Helper_Payment extends Mage_Core_Helper_Abstract
{
    const HASH_ALGO = 'sha1';

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function getConfig()
    {
        return Mage::getSingleton('ops/config');
    }

    /**
     * Crypt Data by SHA1 ctypting algorithm by secret key
     *
     * @param array $data
     * @param string $key
     *
     * @return hash
     */
    public function shaCrypt($data, $key = '')
    {
        if (is_array($data)) {
            return hash(self::HASH_ALGO, implode("", $data));
        }
        if (is_string($data)) {
            return hash(self::HASH_ALGO, $data);
        } else {
            return "";
        }
    }

    /**
     * Check hash crypted by SHA1 with existing data
     *
     * @param array $data
     * @param string $hash
     * @param string $key
     *
     * @return bool
     */
    public function shaCryptValidation($data, $hashFromOPS, $key = '')
    {
        if (is_array($data)) {
            $data = implode("", $data);
        }

        $hashUtf8    = strtoupper(hash(self::HASH_ALGO, $data));
        $hashNonUtf8 = strtoupper(hash(self::HASH_ALGO, utf8_encode($data)));

        $helper = Mage::helper('ops');
        $helper->log($helper->__("Module Secureset: %s", $data));

        if ($this->compareHashes($hashFromOPS, $hashUtf8)) {
            return true;
        } else {
            $helper->log($helper->__("Trying again with non-utf8 secureset"));

            return $this->compareHashes($hashFromOPS, $hashNonUtf8);
        }
    }

    private function compareHashes($hashFromOPS, $actual)
    {
        $helper = Mage::helper('ops');
        $helper->log(
            $helper->__(
                "Checking hashes\nHashed String by Magento: %s\nHashed String by Ingenico Payment Services: %s",
                $actual,
                $hashFromOPS
            )
        );

        if ($hashFromOPS == $actual) {
            Mage::helper('ops')->log("Successful validation");

            return true;
        }

        return false;
    }

    /**
     * Return set of data which is ready for SHA crypt
     *
     * @param array $data
     * @param string $key
     *
     * @return string
     */
    public function getSHAInSet($params, $SHAkey)
    {
        $params          = $this->prepareParamsAndSort($params);
        $plainHashString = "";
        foreach ($params as $paramSet):
            if ($paramSet['value'] == '' || $paramSet['key'] == 'SHASIGN') {
                continue;
            }
            $plainHashString .= strtoupper($paramSet['key']) . "=" . $paramSet['value'] . $SHAkey;
        endforeach;

        return $plainHashString;
    }

    /**
     * Return prepared and sorted array for SHA Signature Validation
     *
     * @param array $params
     *
     * @return string
     */
    public function prepareParamsAndSort($params)
    {
        unset($params['CardNo']);
        unset($params['Brand']);
        unset($params['SHASign']);

        $params = array_change_key_case($params, CASE_UPPER);

        //PHP ksort takes care about "_", OPS not
        $sortedParams = array();
        foreach ($params as $key => $value):
            $sortedParams[str_replace("_", "", $key)] = array('key' => $key, 'value' => $value);
        endforeach;
        ksort($sortedParams);

        return $sortedParams;
    }

    /*
     * Get SHA-1-IN hash for ops-authentification
     *
     * All Parameters have to be alphabetically, UPPERCASE
     * Empty Parameters shouldn't appear in the secure string
     *
     * @param array  $formFields
     * @param string $shaCode
     *
     * @return string
     */
    public function getSHASign($formFields, $shaCode = null, $storeId = null)
    {
        if (is_null($shaCode)) {
            $shaCode = Mage::getModel('ops/config')->getShaOutCode($storeId);
        }
        $formFields = array_change_key_case($formFields, CASE_UPPER);
        uksort($formFields, 'strnatcasecmp');
        $plainHashString = '';
        foreach ($formFields as $formKey => $formVal) {
            if (is_null($formVal) || '' === $formVal || $formKey == 'SHASIGN') {
                continue;
            }
            $plainHashString .= strtoupper($formKey) . '=' . $formVal . $shaCode;
        }

        return $plainHashString;
    }

    /**
     * We get some CC info from ops, so we must save it
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $ccInfo
     *
     * @return Netresearch_OPS_ApiController
     */
    public function _prepareCCInfo($order, $ccInfo)
    {
        if (isset($ccInfo['CN'])) {
            $order->getPayment()->setCcOwner($ccInfo['CN']);
        }

        if (isset($ccInfo['CARDNO'])) {
            $order->getPayment()->setCcNumberEnc($ccInfo['CARDNO']);
            $order->getPayment()->setCcLast4(substr($ccInfo['CARDNO'], -4));
        }

        if (isset($ccInfo['ED'])) {
            $order->getPayment()->setCcExpMonth(substr($ccInfo['ED'], 0, 2));
            $order->getPayment()->setCcExpYear(substr($ccInfo['ED'], 2, 2));
        }

        return $this;
    }

    public function isPaymentAccepted($status)
    {
        return in_array(
            $status, array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_UNKNOWN,
                Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_UNCERTAIN,
                Netresearch_OPS_Model_Payment_Abstract::OPS_WAITING_FOR_IDENTIFICATION
            )
        );
    }

    public function isPaymentAuthorizeType($status)
    {
        return in_array(
            $status, array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_UNKNOWN,
                Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT
            )
        );
    }

    public function isPaymentCaptureType($status)
    {
        return in_array(
            $status, array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_UNCERTAIN
            )
        );
    }

    public function isPaymentFailed($status)
    {
        return false == $this->isPaymentAccepted($status);
    }

    /**
     * apply ops state for order
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     *
     * @return void
     */
    public function applyStateForOrder($order, $params)
    {
        /**
         * OpenInvoiceDe should always have status code 41, which is a final state in this case
         */
        if ($order->getPayment()->getMethodInstance()->getCode() == 'ops_openInvoiceDe'
            && $params['STATUS'] == Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT
        ) {
            $params['STATUS'] = Netresearch_OPS_Model_Payment_Abstract::OPS_OPEN_INVOICE_DE_PROCESSED;
        }

        $feedbackStatus = '';

        switch ($params['STATUS']) {
            case Netresearch_OPS_Model_Payment_Abstract::OPS_WAITING_FOR_IDENTIFICATION : //3D-Secure
                $this->waitOrder($order, $params);
                $feedbackStatus = Netresearch_OPS_Model_Status_Feedback::OPS_ORDER_FEEDBACK_STATUS_ACCEPT;
                break;

            case Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_KWIXO:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_UNKNOWN:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT:
                $this->acceptOrder($order, $params);
                $feedbackStatus = Netresearch_OPS_Model_Status_Feedback::OPS_ORDER_FEEDBACK_STATUS_ACCEPT;
                break;
            case Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_OPEN_INVOICE_DE_PROCESSED:
                $this->acceptOrder($order, $params, 1);
                $feedbackStatus = Netresearch_OPS_Model_Status_Feedback::OPS_ORDER_FEEDBACK_STATUS_ACCEPT;
                break;
            case Netresearch_OPS_Model_Payment_Abstract::OPS_AUTH_REFUSED:
            case Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REFUSED:
                $this->declineOrder($order, $params);
                $feedbackStatus = Netresearch_OPS_Model_Status_Feedback::OPS_ORDER_FEEDBACK_STATUS_DECLINE;
                break;
            case Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_CANCELED_BY_CUSTOMER:
                $this->cancelOrder($order, $params, Mage_Sales_Model_Order::STATE_CANCELED,
                                   Mage::helper('ops')->__(
                                       'Order canceled on Ingenico Payment Services side. Status: %s, Payment ID: %s.',
                                       Mage::helper('ops')->getStatusText($params['STATUS']),
                                       $params['PAYID']));
                $feedbackStatus = Netresearch_OPS_Model_Status_Feedback::OPS_ORDER_FEEDBACK_STATUS_CANCEL;
                break;
            default:
                //all unknown transaction will accept as exceptional
                $this->handleException($order, $params);
                $feedbackStatus = Netresearch_OPS_Model_Status_Feedback::OPS_ORDER_FEEDBACK_STATUS_EXCEPTION;
        }

        return $feedbackStatus;
    }

    /**
     * Process success action by accept url
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     */
    public function acceptOrder($order, $params, $instantCapture = 0)
    {
        $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
        $this->_prepareCCInfo($order, $params);
        $this->setPaymentTransactionInformation($order->getPayment(), $params, 'accept');
        $this->setFraudDetectionParameters($order->getPayment(), $params);

        if ($transaction = Mage::helper('ops/payment')->getTransactionByTransactionId($order->getQuoteId())) {
            $transaction->setTxnId($params['PAYID'])->save();
        }

        try {
            if (false === $this->forceAuthorize($order) &&
                ($this->getConfig()->getConfigData('payment_action')
                    == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE
                    || $instantCapture)
                && $params['STATUS'] != Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT
            ) {
                $this->_processDirectSale($order, $params, $instantCapture);
            } else {
                $this->_processAuthorize($order, $params);
            }
        } catch (Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('ops')->__('Order can not be saved.'));
            throw $e;
        }
    }

    /**
     * Set Payment Transaction Information
     *
     * @param Mage_Sales_Model_Order_Payment $payment Sales Payment Model
     * @param array $params Request params
     * @param string $action Action (accept|cancel|decline|wait|exception)
     */
    protected function setPaymentTransactionInformation(Mage_Sales_Model_Order_Payment $payment, $params, $action)
    {
        $payment->setTransactionId($params['PAYID']);
        $code = $payment->getMethodInstance()->getCode();

        $isInlineWithOrderId = false;

        /* In authorize mode we still have no authorization transaction for CC and DirectDebit payments,
         * so capture or cancel won't work. So we need to create a new authorization transaction for them
         * when a payment was accepted by Ingenico Payment Services
         *
         * In exception-case we create the authorization-transaction too because some exception-cases can turn into accepted
         */
        if (('accept' === $action || 'exception' === $action)
            && in_array($code, array('ops_cc', 'ops_directDebit'))
        ) {
            $payment->setIsTransactionClosed(false);
            $isInlineWithOrderId = $this->isInlinePaymentWithOrderId($payment);
            /* create authorization transaction for non-inline pms */
            if (false === $isInlineWithOrderId || (array_key_exists('HTML_ANSWER', $params) || 0 < strlen($payment->getAdditionalInformation('HTML_ANSWER')))) {
                $payment->addTransaction("authorization", null, true, $this->__("Process outgoing transaction"));
            }
            $payment->setLastTransId($params['PAYID']);
        }

        /* Ingenico Payment Services sends parameter HTML_ANSWER to trigger 3D secure redirection */
        if (isset($params['HTML_ANSWER']) && ('ops_cc' == $code)) {
            $payment->setAdditionalInformation('HTML_ANSWER', $params['HTML_ANSWER']);
            $payment->setIsTransactionPending(true);
        }

        $payment->setAdditionalInformation('paymentId', $params['PAYID']);
        $payment->setAdditionalInformation('status', $params['STATUS']);
        if (array_key_exists('ACCEPTANCE', $params) && 0 < strlen(trim($params['ACCEPTANCE']))) {
            $payment->setAdditionalInformation('acceptance', $params['ACCEPTANCE']);
        }
        if (array_key_exists('BRAND', $params) && ('ops_cc' == $code) && 0 < strlen(trim($params['BRAND']))) {
            $payment->setAdditionalInformation('CC_BRAND', $params['BRAND']);
        }
        if (false === $isInlineWithOrderId || array_key_exists('HTML_ANSWER', $params)) {
            $payment->setIsTransactionClosed(true);
        }
        $payment->setDataChanges(true);
        $payment->save();
    }

    /**
     * add fraud detection of Ingenico Payment Services to additional payment data
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param array $params
     */
    protected function setFraudDetectionParameters($payment, $params)
    {
        $params = array_change_key_case($params, CASE_UPPER);
        if (array_key_exists('SCORING', $params)) {
            $payment->setAdditionalInformation('scoring', $params['SCORING']);
        }
        if (array_key_exists('SCO_CATEGORY', $params)) {
            $payment->setAdditionalInformation('scoringCategory', $params['SCO_CATEGORY']);
        }
        $additionalScoringData = array();
        foreach ($this->getConfig()->getAdditionalScoringKeys() as $key) {
            if (array_key_exists($key, $params)) {
                if (false === mb_detect_encoding($params[$key], 'UTF-8', true)) {
                    $additionalScoringData[$key] = utf8_encode($params[$key]);
                } else {
                    $additionalScoringData[$key] = $params[$key];
                }
            }
        }
        $payment->setAdditionalInformation('additionalScoringData', $additionalScoringData);
    }

    /**
     * Process cancel action by cancel url
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     * @param string $status Order status
     * @param string $comment Order comment
     */
    public function cancelOrder($order, $params, $status, $comment)
    {
        try {
            Mage::register('ops_auto_void', true); //Set this session value to true to allow cancel

            $order->cancel();
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, $status, $comment);
            $order->save();
            $this->cancelInvoices($order);
            try {
                $this->setPaymentTransactionInformation($order->getPayment(), $params, 'cancel');
            } catch (Exception $e) {
                // just ignore that
                Mage::helper('ops')->log(
                    'Catched exception while saving payment transaction information of a canceled order: '
                    . $e->getMessage()
                );
            }
        } catch (Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('ops')->__('Order can not be canceled for system reason.'));
            throw $e;
        }
    }

    /**
     * Process decline action by ops decline url
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     */
    public function declineOrder($order, $params)
    {
        try {
            Mage::register('ops_auto_void', true); //Set this session value to true to allow cancel
            $order->cancel();
            $order->setState(
                Mage_Sales_Model_Order::STATE_CANCELED,
                Mage_Sales_Model_Order::STATE_CANCELED,
                Mage::helper('ops')->__(
                    'Order declined on ops side. Ingenico Payment Services status: %s, Payment ID: %s.',
                    Mage::helper('ops')->getStatusText($params['STATUS']),
                    $params['PAYID']
                )
            );
            $order->save();
            $this->cancelInvoices($order);
            $this->setPaymentTransactionInformation($order->getPayment(), $params, 'decline');
        } catch (Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('ops')->__('Order can not be canceled for system reason.'));
            throw $e;
        }
    }

    /**
     * Wait for 3D secure confirmation
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     */
    public function waitOrder($order, $params)
    {
        try {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                Mage::helper('ops')->__(
                    'Order is waiting for Ingenico Payment Services confirmation of 3D-Secure. Ingenico Payment Services status: %s, Payment ID: %s.',
                    Mage::helper('ops')->getStatusText($params['STATUS']),
                    $params['PAYID']
                )
            );
            $order->save();
            $this->setPaymentTransactionInformation($order->getPayment(), $params, 'wait');
        } catch (Exception $e) {
            $this->_getCheckout()->addError(
                Mage::helper('ops')->__('Error during 3D-Secure processing of Ingenico Payment Services. Error: %s', $e->getMessage())
            );
            throw $e;
        }
    }

    /**
     * Process exception action by ops exception url
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     */
    public function handleException($order, $params)
    {
        $exceptionMessage = $this->getPaymentExceptionMessage($params['STATUS']);

        if (!empty($exceptionMessage)) {
            try {
                $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
                $this->_prepareCCInfo($order, $params);
                $order->getPayment()->setLastTransId($params['PAYID']);
                //to send new order email only when state is pending payment
                if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    $order->sendNewOrderEmail();

                }
                $order->addStatusHistoryComment($exceptionMessage);
                $order->save();
                $this->setPaymentTransactionInformation($order->getPayment(), $params, 'exception');
            } catch (Exception $e) {
                $this->_getCheckout()->addError(Mage::helper('ops')->__('Order can not be saved for system reason.'));
            }
        } else {
            $this->_getCheckout()->addError(Mage::helper('ops')->__('An unknown exception occured.'));
        }
    }

    /**
     * Get Payment Exception Message
     *
     * @param int $ops_status Request OPS Status
     */
    protected function getPaymentExceptionMessage($ops_status)
    {
        $exceptionMessage = '';
        switch ($ops_status) {
            case Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_UNCERTAIN :
                $exceptionMessage = Mage::helper('ops')->__(
                    'A technical problem arose during payment process, giving unpredictable result. Ingenico Payment Services status: %s.',
                    Mage::helper('ops')->getStatusText($ops_status)
                );
                break;
            default:
                $exceptionMessage = Mage::helper('ops')->__(
                    'An unknown exception was thrown in the payment process. Ingenico Payment Services status: %s.',
                    Mage::helper('ops')->getStatusText($ops_status)
                );
        }

        return $exceptionMessage;
    }

    /**
     * Process Configured Payment Action: Direct Sale, create invoice if state is Pending
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     */
    protected function _processDirectSale($order, $params, $instantCapture = 0)
    {
        Mage::register('ops_auto_capture', true);
        $status = $params['STATUS'];
        if ($status == Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('ops')->__('Waiting for the payment of the customer')
            );
            $order->save();
        } elseif ($status == Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('ops')->__('Authorization waiting from Ingenico Payment Services')
            );
            $order->save();
        } elseif ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
            || $instantCapture
        ) {
            if ($status == Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED) {
                if ($order->getStatus() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        Mage::helper('ops')->__('Processed by Ingenico Payment Services')
                    );

                }
            } else {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PROCESSING,
                    Mage_Sales_Model_Order::STATE_PROCESSING,
                    Mage::helper('ops')->__('Processed by Ingenico Payment Services')
                );
                $order->save();
            }
            if (!$order->getInvoiceCollection()->getSize()
                && $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
                && $order->canInvoice()
            ) {
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
                $invoice->getOrder()->setIsInProcess(true);
                $invoice->save();
                $this->sendInvoiceToCustomer($invoice);

                $transactionSave = Mage::getModel('core/resource_transaction')
                                       ->addObject($invoice)
                                       ->addObject($invoice->getOrder())
                                       ->save();

                /*
                 * If the payment method is a redirect-payment-method send the email
                 * In any other case Magento sends an email automatically in Mage_Checkout_Model_Type_Onepage::saveOrder
                 */
                if ($this->isRedirectPaymentMethod($order) === true
                    && $order->getEmailSent() != 1
                ) {
                    $order->sendNewOrderEmail();
                }
                $eventData = array('data_object' => $order, 'order' => $order);
                Mage::dispatchEvent('ops_sales_order_save_commit_after', $eventData);
            }

            if ($this->isInlinePaymentWithOrderId($order->getPayment())
                && 0 < strlen(trim($order->getPayment()->getAdditionalInformation('HTML_ANSWER')))
                && $order->getPayment()->getAdditionalInformation('status') == Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED
            ) {
                $order->getPayment()->setIsTransactionApproved(true)->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, true)->save();
                $this->setInvoicesToPaid($order);
                $order->getPayment()->getAuthorizationTransaction()->setIsClosed(true)->save();
                $order->getPayment()->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, $order->getPayment())->setIsClosed(true)->save();
                $order->sendNewOrderEmail();
                $order->save();
            }
            if ($this->isInlinePaymentWithOrderId($order->getPayment()) && Mage::getModel('ops/config')->getSendInvoice() && Mage::getModel('ops/config')->getPaymentAction($order->getStoreId()) === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $this->sendInvoiceToCustomer($invoice);
                }
            }
        } else {
            $order->save();
        }
    }

    /**
     * send invoice to customer if that was configured by the merchant
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice Invoice to be sent
     *
     * @return void
     */
    public function sendInvoiceToCustomer(Mage_Sales_Model_Order_Invoice $invoice)
    {
        if (false == $invoice->getEmailSent()
            && $this->getConfig()->getSendInvoice()
        ) {
            $invoice->sendEmail($notifyCustomer = true);
        }
    }

    /**
     * Process Configured Payment Actions: Authorized, Default operation
     * just place order
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param array $params Request params
     */
    protected function _processAuthorize($order, $params)
    {
        $status = $params['STATUS'];
        if ($status == Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('ops')->__(
                    'Waiting for payment. Ingenico Payment Services status: %s.', Mage::helper('ops')->getStatusText($status)
                )
            );
        } elseif ($status == Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('ops')->__(
                    'Authorization uncertain. Ingenico Payment Services status: %s.', Mage::helper('ops')->getStatusText($status)
                )
            );
        } else {
            if ($this->isRedirectPaymentMethod($order) === true
                && $order->getEmailSent() != 1
            ) {
                $order->sendNewOrderEmail();
            }

            $payId = $params['PAYID'];
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage::helper('ops')->__(
                    'Processed by Ingenico Payment Services. Payment ID: %s. Ingenico Payment Services status: %s.', $payId,
                    Mage::helper('ops')->getStatusText($status)
                )
            );
        }
        $order->save();
    }

    /**
     * Fetches transaction with given transaction id
     *
     * @param string $txnId
     *
     * @return mixed Mage_Sales_Model_Order_Payment_Transaction | boolean
     */
    public function getTransactionByTransactionId($transactionId)
    {
        if (!$transactionId) {
            return;
        }
        $transaction = Mage::getModel('sales/order_payment_transaction')
                           ->getCollection()
                           ->addAttributeToFilter('txn_id', $transactionId)
                           ->getLastItem();
        if (is_null($transaction->getId())) {
            return false;
        }
        $transaction->getOrderPaymentObject();

        return $transaction;
    }

    /**
     * refill cart
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    public function refillCart($order)
    {
        // add items
        $cart = Mage::getSingleton('checkout/cart');

        if (0 < $cart->getQuote()->getItemsCollection()->count()) {
            //cart is not empty, so refilling it is not required
            return;
        }
        foreach ($order->getItemsCollection() as $item) {
            try {
                $cart->addOrderItem($item);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        $cart->save();

        // add coupon code
        $coupon  = $order->getCouponCode();
        $session = Mage::getSingleton('checkout/session');
        if (false == is_null($coupon)) {
            $session->getQuote()->setCouponCode($coupon)->save();
        }
    }

    /**
     * Save OPS Status to Payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param array $params OPS-Response
     *
     * @return void
     */
    public function saveOpsStatusToPayment(Mage_Sales_Model_Order_Payment $payment, $params)
    {
        $payment
            ->setAdditionalInformation('status', $params['STATUS'])
            ->save();
    }

    /**
     * Check is payment method is a redirect method
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function isRedirectPaymentMethod($order)
    {
        $method = $order->getPayment()->getMethodInstance();
        if ($method
            && $method->getOrderPlaceRedirectUrl() != '' //Magento returns ''
            && $method->getOrderPlaceRedirectUrl() !== false
        ) { //Ops return false
            return true;
        } else {
            return false;
        }
    }

    public function getQuote()
    {
        return $this->_getCheckout()->getQuote();
    }

    /**
     * sets the state to pending payment if neccessary (order is in state new)
     * and adds a comment to status history
     *
     * @param $order - the order
     */
    public function handleUnknownStatus($order)
    {
        if ($order instanceof Mage_Sales_Model_Order) {
            $message = Mage::helper('ops')->__(
                'Unknown Ingenico Payment Services state for this order. Please check Ingenico Payment Services backend for this order.'
            );
            if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $message
                );
            } else {
                $order->addStatusHistoryComment($message);
            }
            $order->save();
        }
    }

    /**
     * returns the base grand total from either a quote or an order
     *
     * @param $salesObject
     *
     * @return double the base amount of the order
     * @throws Exception if $salesObject is not a quote or an order
     */
    public function getBaseGrandTotalFromSalesObject($salesObject)
    {
        if ($salesObject instanceof Mage_Sales_Model_Order or $salesObject instanceof Mage_Sales_Model_Quote) {
            return $salesObject->getBaseGrandTotal();
        } else {
            Mage::throwException('$salesObject is not a quote or an order instance');
        }
    }


    /**
     * Save the last used refund operation code to payment
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $operationCode
     *
     * @return void
     */
    public function saveOpsRefundOperationCodeToPayment(Mage_Sales_Model_Order_Payment $payment, $operationCode)
    {
        if (in_array(
            strtoupper(trim($operationCode)),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_FULL,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PARTIAL
            ))
        ) {
            Mage::helper('ops/data')->log(
                sprintf("set last refund operation '%s' code to payment for order '%s'",
                        $operationCode,
                        $payment->getOrder()->getIncrementId()
                )
            );
            $payment
                ->setAdditionalInformation('lastRefundOperationCode', $operationCode)
                ->save();
        }
    }

    /**
     * sets the canRefund information depending on the last refund operation code
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    public function setCanRefundToPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $refundOperationCode = $payment->getAdditionalInformation('lastRefundOperationCode');
        if (in_array(
            strtoupper(trim($refundOperationCode)),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_FULL,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PARTIAL
            ))
        ) {
            /*
             * a further refund is possible if the transaction remains open, that means either the merchant
             * did not close the transaction or the refunded amount is less than the orders amount
             */
            $canRefund = ($refundOperationCode == Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PARTIAL);
            Mage::helper('ops/data')->log(
                sprintf("set canRefund to '%s' for payment of order '%s'",
                        var_export($canRefund, true),
                        $payment->getOrder()->getIncrementId()
                )
            );
            $payment
                ->setAdditionalInformation('canRefund', $canRefund)
                ->save();
        }
    }

    /**
     * determine whether the payment supports only authorize or not
     * @param Mage_Sales_Model_Order $order
     * @return true . if so, false otherwise
     */
    protected function forceAuthorize(Mage_Sales_Model_Order $order)
    {
        return ($order->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Kwixo_Abstract);
    }


    /**
     * add ops_cc payment to checkout methods if quote total is zero and zero amount checkout is activated
     *
     * @param Mage_Payment_Block_Form_Container $block
     *
     * @return $this
     */
    public function addCCForZeroAmountCheckout(Mage_Payment_Block_Form_Container $block)
    {
        $methods = $block->getMethods();
        if (false === $this->checkIfCCisInCheckoutMethods($methods)) {
            $ccPayment = Mage::getModel('ops/payment_cc');
            if ($ccPayment->getFeatureModel()->isCCAndZeroAmountAuthAllowed($ccPayment, $block->getQuote())) {
                $ccPayment->setInfoInstance($block->getQuote()->getPayment());
                $methods[] = $ccPayment;
                $block->setData('methods', $methods);
            }
        }

        return $this;
    }


    /**
     * check if ops_cc is in payment methods array
     *
     * @param $methods
     *
     * @return array
     */
    protected function checkIfCCisInCheckoutMethods($methods)
    {
        $result = false;
        foreach ($methods as $method) {
            if ($method->getCode() == 'ops_cc') {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * checks if the payment method can use order's increment id as merchant's reference
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @return bool
     */
    public function isInlinePaymentWithOrderId(Mage_Payment_Model_Info $payment)
    {
        return $this->isInlinePayment($payment) && (0 < strlen(trim($payment->getMethodInstance()->getConfigPaymentAction())));
    }

    /**
     * checks if the payment method can pbe processed via direct link
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @return bool
     */
    public function isInlinePayment(Mage_Payment_Model_Info $payment)
    {
        $result         = false;
        $methodInstance = $payment->getMethodInstance();
        if ($methodInstance instanceof Netresearch_OPS_Model_Payment_DirectDebit
            || ($methodInstance instanceof Netresearch_OPS_Model_Payment_Cc
                && ($methodInstance->hasBrandAliasInterfaceSupport($payment) || Mage::helper('ops/data')->isAdminSession()))
        ) {
            $result = true;
        }

        return $result;
    }

    /**
     * checks if the inline payment can use quote id as merchant's reference
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @return bool
     */
    public function isInlinePaymentWithQuoteId(Mage_Payment_Model_Info $payment)
    {
        return $this->isInlinePayment($payment) && (0 === strlen(trim($payment->getMethodInstance()->getConfigPaymentAction())));
    }

    /**
     * sets the invoices of an order to paid
     *
     * @param Mage_Sales_Model_Order $order
     * @return Netresearch_OPS_Helper_Payment
     */
    public function setInvoicesToPaid($order)
    {
        /** @var $invoice Mage_Sales_Model_Order_Invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
            $invoice->save();
        }

        return $this;
    }

    /**
     * cancel all invoices for a given order
     *
     * @param $order
     *
     * @return Netresearch_OPS_Helper_Payment
     * @throws Exception
     */
    public function cancelInvoices($order)
    {
        /** @var $invoice Mage_Sales_Model_Order_Invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_CANCELED);
            $invoice->save();
        }

        return $this;
    }

}
