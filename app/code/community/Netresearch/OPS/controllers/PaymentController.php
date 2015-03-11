<?php

/**
 * Netresearch_OPS_PaymentController
 *
 * @package
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de>
 * @author    André Herrn <andre.herrn@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_PaymentController extends Netresearch_OPS_Controller_Abstract
{

    /**
     * Load place from layout to make POST on ops
     */
    public function placeformAction()
    {
        $lastIncrementId = $this->_getCheckout()->getLastRealOrderId();

        if ($lastIncrementId) {
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($lastIncrementId);

            if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
                // update transactions, order state and add comments
                $order->getPayment()->setTransactionId($order->getQuoteId());
                $order->getPayment()->setIsTransactionClosed(false);
                $transaction = $order->getPayment()->addTransaction("authorization", null, true, $this->__("Process outgoing transaction"));

                if ($order->getId()) {
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage::helper('ops')->__('Start Ingenico Payment Services processing')
                    );
                    $order->save();
                }
            }
        }

        $this->_getCheckout()->getQuote()->setIsActive(false)->save();
        $this->_getCheckout()->setOPSQuoteId($this->_getCheckout()->getQuoteId());
        $this->_getCheckout()->setOPSLastSuccessQuoteId($this->_getCheckout()->getLastSuccessQuoteId());
        $this->_getCheckout()->clear();

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Render 3DSecure response HTML_ANSWER
     */
    public function placeform3dsecureAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Display our pay page, need to ops payment with external pay page mode     *
     */
    public function paypageAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * when payment gateway accept the payment, it will land to here
     * need to change order status as processed ops
     * update transaction id
     *
     */
    public function acceptAction()
    {
        try {
            $order = $this->_getOrder();
            $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
        } catch (Exception $e) {
            $helper = Mage::helper('ops');
            $helper->log($helper->__("Exception in acceptAction: " . $e->getMessage()));
            $this->getPaymentHelper()->refillCart($this->_getOrder());
            $this->_redirect('checkout/cart');
            return;
        }
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * accept-action for Alias-generating iframe-response
     *
     */
    public function acceptAliasAction()
    {
        $helper = Mage::helper('ops');
        $helper->log($helper->__("Incoming accepted Ingenico Payment Services Alias Feedback\n\nRequest Path: %s\nParams: %s\n", $this->getRequest()->getPathInfo(), serialize($this->getRequest()->getParams())
        ));
        Mage::helper('ops/alias')->saveAlias($this->getRequest()->getParams());
        $result = array('result' => 'success', 'alias' => $this->_request->getParam('Alias'), 'CVC' => $this->_request->getParam('CVC'));
        $params = $this->getRequest()->getParams();

        if (array_key_exists('OrderID', $params)) {
            $quote = Mage::getModel('sales/quote')->load($params['OrderID']);
            $this->updateAdditionalInformation($quote, $params);
        }

        // OGNH-7 special handling for admin orders
        Mage::helper('ops/alias')->setAliasToPayment($this->getQuote()->getPayment(), array_change_key_case($this->getRequest()->getParams(),CASE_LOWER), false);

        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * updates the additional information from payment, thats needed for backend reOrders
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $params
     */
    public function updateAdditionalInformation(Mage_Sales_Model_Quote $quote, $params)
    {
        if (!is_null($quote->getId()) && $quote->getPayment() && !is_null($quote->getPayment()->getId())) {
            $payment = $quote->getPayment();
            if (array_key_exists('Alias', $params)) {
                $payment->setAdditionalInformation('alias', $params['Alias']);
            }
            if (array_key_exists('Brand', $params)) {
                $payment->setAdditionalInformation('CC_BRAND', $params['Brand']);
            }
            if (array_key_exists('CN', $params)) {
                $payment->setAdditionalInformation('CC_CN', $params['CN']);
            }
            $quote->setPayment($payment)->save();
        }
    }

    /**
     * the payment result is uncertain
     * exception status can be 52 or 92
     * need to change order status as processing ops
     * update transaction id
     *
     */
    public function exceptionAction()
    {
        $order = $this->_getOrder();
        $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * exception-action for Alias-generating iframe-response
     *
     */
    public function exceptionAliasAction()
    {
        $params = $this->getRequest()->getParams();
        $errors = array();

        foreach ($params as $key => $value) {
            if (stristr($key, 'error') && 0 != $value) {
                $errors[] = $value;
            }
        }

        $helper = Mage::helper('ops');
        $helper->log($helper->__("Incoming exception Ingenico Payment Services Alias Feedback\n\nRequest Path: %s\nParams: %s\n", $this->getRequest()->getPathInfo(), serialize($params)
        ));

        $result = array('result' => 'failure', 'errors' => $errors);
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * when payment got decline
     * need to change order status to cancelled
     * take the user back to shopping cart
     *
     */
    public function declineAction()
    {
        try {
            $this->_getCheckout()->setQuoteId($this->_getCheckout()->getOPSQuoteId());
        } catch (Exception $e) {

        }

        $this->getPaymentHelper()->refillCart($this->_getOrder());

        $message = Mage::helper('ops')->__('Your payment information was declined. Please select another payment method.');
        Mage::getSingleton('core/session')->addNotice($message);

        $this->_redirect('checkout/onepage');
    }

    /**
     * when user cancel the payment
     * change order status to cancelled
     * need to redirect user to shopping cart
     *
     * @return Netresearch_OPS_ApiController
     */
    public function cancelAction()
    {
        try {
            $this->_getCheckout()->setQuoteId($this->_getCheckout()->getOPSQuoteId());
        } catch (Exception $e) {

        }
        if (false == $this->_getOrder()->getId()) {
            $this->_order = null;
            $this->_getOrder($this->_getCheckout()->getLastQuoteId());
        }

        $this->getPaymentHelper()->refillCart($this->_getOrder());
        $this->_redirect('checkout/cart');
    }

    /**
     * when user cancel the payment and press on button "Back to Catalog" or "Back to Merchant Shop" in Orops
     *
     * @return Netresearch_OPS_ApiController
     */
    public function continueAction()
    {
        $order = Mage::getModel('sales/order')->load(
            $this->_getCheckout()->getLastOrderId()
        );
        $this->getPaymentHelper()->refillCart($order);
        $redirect = $this->getRequest()->getParam('redirect');
        if ($redirect == 'catalog'): //In Case of "Back to Catalog" Button in OPS
            $this->_redirect('/');
        else: //In Case of Cancel Auto-Redirect or "Back to Merchant Shop" Button
            $this->_redirect('checkout/cart');
        endif;
    }
    /*
     * Check the validation of the request from OPS
     */

    protected function checkRequestValidity()
    {
        if (!$this->_validateOPSData()) {
            throw new Exception("Hash is not valid");
        }
    }

    public function generateHashAction()
    {
        $config = Mage::getModel('ops/config');
        $storeId = null;
        $quoteId = $this->_request->getParam('orderid');

        $quote = Mage::getModel('sales/quote')->load($quoteId);

        if (is_null($quote->getId())) {
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
        }

        if (!is_null($quote->getId())) {
            $storeId = $quote->getStoreId();
        }
        if (false == is_null($this->_request->getParam('storeId'))) {
            $storeId = $this->_request->getParam('storeId');
        }

        if (!is_null($quote->getId()) && $quote->getPayment()) {
            $payment = $quote->getPayment();
            $payment->setAdditionalInformation('saveOpsAlias', 0);
            $payment->save();
            $quote->setPayment($payment)->save();
        }

        // OGNC-3 use main store id for orders from backend, since the feedback from Ingenico Payment Services could not be parsed in magento backend
        $aliasStoreId = $storeId;
        if (false == is_null($this->_request->getParam('isAdmin')) && $this->_request->getParam('isAdmin') == 1) {
            $aliasStoreId = 0;
        }

        $alias = $this->_request->getParam('alias');
        if (0 < strlen(trim($this->_request->getParam('storedAlias'))) && $this->_request->getParam('saveAlias')) {
            $isAliasValid = Mage::helper('ops/alias')->isAliasValidForAddresses(
                $quote->getCustomer()->getId(),
                trim($this->_request->getParam('storedAlias')),
                $quote->getBillingAddress(),
                $quote->getShippingAddress(),
                $quote->getStoreId()
            );

            if (true === $isAliasValid) {
                $alias = trim($this->_request->getParam('storedAlias'));
            }
        }
        $data = array(
            'ACCEPTURL' => $config->getAliasAcceptUrl($aliasStoreId),
            'ALIAS' => $alias,
            'EXCEPTIONURL' => $config->getAliasExceptionUrl($aliasStoreId),
            'ORDERID' => $quoteId,
            'PARAMPLUS' => $this->_request->getParam('paramplus'),
            'PSPID' => $config->getPSPID($storeId),
        );
        if (false == is_null($this->_request->getParam('brand'))) {
            $data['BRAND'] = $this->_request->getParam('brand');
        }

        $secret = $config->getShaOutCode($storeId);
        $paymentHelper = Mage::helper('ops/payment');
        $raw = $paymentHelper->getSHAInSet($data, $secret);

        /* set wish to save payment information (Alias Manager) */

        if (!is_null($quote->getId()) && $quote->getPayment() && $this->_request->getParam('saveAlias')) {
            $payment = $quote->getPayment();
            $payment->setAdditionalInformation(
                'saveOpsAlias', 1
            );
            $payment->save();
            $quote->setPayment($payment)->save();
        }
        $result = array('hash' => Mage::helper('ops/payment')->shaCrypt($raw), 'alias' => $alias);
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function registerDirectDebitPaymentAction()
    {
        $params = $this->getRequest()->getParams();
        $validator = Mage::getModel('ops/validator_payment_directDebit');
        if (false === $validator->isValid($params)) {
            $this->getResponse()
                ->setHttpResponseCode(406)
                ->setBody($this->__(implode(PHP_EOL, $validator->getMessages())))
                ->sendHeaders();
            return;
        }
        $payment = $this->_getCheckout()->getQuote()->getPayment();
        $helper = Mage::helper('ops/directDebit');
        $payment = $helper->setDirectDebitDataToPayment($payment, $params);



        $payment->save();

        $this->getResponse()->sendHeaders();
    }

    public function saveAliasAction()
    {

        $userIsRegistering = false;
        if ($this->getQuote()->getCheckoutMethod() === Mage_Sales_Model_Quote::CHECKOUT_METHOD_REGISTER) {
            $userIsRegistering = true;
        }
        Mage::helper('ops/alias')->setAliasToPayment($this->getQuote()->getPayment(), $this->getRequest()->getParams(), $userIsRegistering, true);
    }

    public function saveCcBrandAction()
    {
        $brand = $this->_request->getParam('brand');
        $cn = $this->_request->getParam('cn');

        $payment = $this->getQuote()->getPayment();
        $payment->setAdditionalInformation('CC_BRAND', $brand);
        $payment->setAdditionalInformation('CC_CN', $cn);
        $payment->setDataChanges(true);
        $payment->save();
        Mage::helper('ops')->log('saved cc brand ' . $brand . ' for quote #' . $this->getQuote()->getId());
        $this->getResponse()->sendHeaders();
    }

    public function validateAction()
    {
        $quote = $this->_getCheckout()->getQuote();
        if ($quote->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract) {
            $paramHelper = Mage::helper('ops/payment_request');
            $shippingParams = array();
            $billingParams = $paramHelper->getOwnerParams($quote, $quote->getBillingAddress());
            if ($quote->getShippingAddress()) {
                $shippingParams = $paramHelper->extractShipToParameters($quote->getShippingAddress(), $quote);
            }
            $params = array_merge($billingParams, $shippingParams);
            $validator = Mage::getModel('ops/validator_parameter_factory')->getValidatorFor(
                Netresearch_OPS_Model_Validator_Parameter_Factory::TYPE_REQUEST_PARAMS_VALIDATION
            );
            if (false == $validator->isValid($params)) {
                $result = Mage::helper('ops/validation_result')->getValidationFailedResult($validator->getMessages(), $quote);
                $this->getResponse()->setBody(Mage::helper('core/data')->jsonEncode($result));
            }
        }
    }
}
