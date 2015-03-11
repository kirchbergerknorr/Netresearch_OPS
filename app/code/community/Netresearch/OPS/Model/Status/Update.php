<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Model_Status_Update
{

    /** @var Netresearch_OPS_Model_Api_DirectLink $directLinkApi */
    protected $directLinkApi = null;

    protected $order = null;

    protected $requestParams = array();

    /** @var Netresearch_OPS_Model_Config $opsConfig */
    protected $opsConfig = null;

    protected $opsResponse = array();

    protected $transitionModel = null;

    protected $paymentHelper = null;

    protected $directLinkHelper = null;

    protected $messageContainer = null;

    protected $dataHelper = null;

    /**
     * @param null $dataHelper
     */
    public function setDataHelper($dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * @return null
     */
    public function getDataHelper()
    {
        if (null == $this->dataHelper) {
            $this->dataHelper = Mage::helper('ops/data');
        }

        return $this->dataHelper;
    }
    /**
     * @param null $messageContainer
     */
    public function setMessageContainer(Mage_Core_Model_Session_Abstract $messageContainer)
    {
        $this->messageContainer = $messageContainer;
    }

    /**
     * @return Mage_Core_Model_Session_Abstract
     */
    public function getMessageContainer()
    {
        if (null == $this->messageContainer) {
            $this->messageContainer = Mage::getSingleton('adminhtml/session');
        }
        return $this->messageContainer;
    }

    /**
     * @param Netresearch_OPS_Model_Config $opsConfig
     */
    public function setOpsConfig(Netresearch_OPS_Model_Config $opsConfig)
    {
        $this->opsConfig = $opsConfig;
    }

    /**
     * @return Netresearch_OPS_Model_Config
     */
    public function getOpsConfig()
    {
        if (null === $this->opsConfig) {
            $this->opsConfig = Mage::getModel('ops/config');
        }
        return $this->opsConfig;
    }

    /**
     * @param array $requestParams
     */
    public function setRequestParams($requestParams)
    {
        $this->requestParams = $requestParams;
    }

    /**
     * @return array
     */
    public function getRequestParams()
    {
        return $this->requestParams;
    }

    /**
     * @param Netresearch_OPS_Helper_Order $orderHelper
     */
    public function setOrderHelper(Netresearch_OPS_Helper_Order $orderHelper)
    {
        $this->orderHelper = $orderHelper;
    }

    /**
     * @return Netresearch_OPS_Helper_Order
     */
    public function getOrderHelper()
    {
        if (null == $this->orderHelper) {
            $this->orderHelper = Mage::helper('ops/order');
        }

        return $this->orderHelper;
    }

    /**
     * @param array $opsResponse
     */
    public function setOpsResponse($opsResponse)
    {
        $this->opsResponse = $opsResponse;
    }

    /**
     * @return array
     */
    public function getOpsResponse()
    {
        return $this->opsResponse;
    }

    /** @var Netresearch_OPS_Helper_Order $orderHelper */
    protected $orderHelper = null;

    /**
     * @param Netresearch_OPS_Model_Api_DirectLink $directLinkApi
     */
    public function setDirectLinkApi(Netresearch_OPS_Model_Api_DirectLink $directLinkApi)
    {
        $this->directLinkApi = $directLinkApi;
    }

    /**
     * @return Netresearch_OPS_Model_Api_DirectLink
     */
    public function getDirectLinkApi()
    {
        if (null === $this->directLinkApi) {
            $this->directLinkApi = Mage::getModel('ops/api_directlink');
        }
        return $this->directLinkApi;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->order = $order;
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    public function updateStatusFor(Mage_Sales_Model_Order $order)
    {
        if (false === ($order->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract)) {
            return $this;
        }
        $this->setOrder($order);
        $this->buildParams($order->getPayment());
        $this->performRequest();
        $this->updatePaymentStatus();

        return $this;
    }

    protected function buildParams(Mage_Sales_Model_Order_Payment $payment)
    {
        // use PAYID if possible
        if (0 < strlen(trim($payment->getAdditionalInformation('paymentId')))) {
            $this->requestParams['PAYID'] = $payment->getAdditionalInformation('paymentId');

        } else {
            $useOrderId = true;
            if ($this->canNotUseOrderId($payment)
            ) {
                $useOrderId = false;
            }
            $this->requestParams['ORDERID'] = $this->getOrderHelper()->getOpsOrderId($this->getOrder(), $useOrderId);
        }
        $this->addPayIdSub($payment);

        return $this;
    }

    protected function performRequest()
    {
        $storeId = $this->getOrder()->getStoreId();
        $url = $this->getOpsConfig()->getDirectLinkMaintenanceApiPath($storeId);
        try {
        $this->opsResponse = $this->getDirectLinkApi()->performRequest($this->getRequestParams(), $url, $storeId);
        } catch (Mage_Core_Exception $e) {
            $this->getMessageContainer()->addError($this->getDataHelper()->__($e->getMessage()));
            return $this;
        }
        $this->opsResponse = array_change_key_case($this->opsResponse, CASE_UPPER);
        // in further processing the amount is sometimes in upper and sometimes in lower case :(
        if (array_key_exists('AMOUNT', $this->opsResponse)) {
            $this->opsResponse['amount'] = $this->opsResponse['AMOUNT'];
        }
        return $this;
    }

    protected function updatePaymentStatus()
    {
        if (!array_key_exists('STATUS', $this->getOpsResponse())
            || $this->opsResponse['STATUS'] == $this->getOrder()->getPayment()->getAdditionalInformation('status')
        ) {
            return $this;
        }

        $this->getPaymentHelper()->saveOpsStatusToPayment($this->getOrder()->getPayment(), $this->getOpsResponse());

        if (0 < strlen(trim($this->getOrder()->getPayment()->getAdditionalInformation('paymentId')))) {
            $this->getTransitionModel()->processOpsResponse($this->getOpsResponse(), $this->getOrder());

        } else {
            // simulate initial request
            $this->getPaymentHelper()->applyStateForOrder($this->getOrder(), $this->getOpsResponse());
        }
        $this->getMessageContainer()->addSuccess($this->getDataHelper()->__('Ingenico Payment Services status successfully updated'));

        return $this;
    }

    public function getTransitionModel()
    {
        if (null == $this->transitionModel) {
            $this->transitionModel = Mage::getModel('ops/status_transition');
        }

        return $this->transitionModel;
    }

    public function setTransitionModel(Netresearch_OPS_Model_Status_Transition $transitionModel)
    {
        $this->transitionModel = $transitionModel;
    }

    public function getPaymentHelper()
    {
        if (null == $this->paymentHelper) {
            $this->paymentHelper = Mage::helper('ops/payment');
        }

        return $this->paymentHelper;
    }

    public function setPaymentHelper(Netresearch_OPS_Helper_Payment $paymentHelper)
    {
        $this->paymentHelper = $paymentHelper;
    }

    public function getDirectLinkHelper()
    {
        if (null == $this->directLinkHelper) {
            $this->directLinkHelper = Mage::helper('ops/directlink');
        }

        return $this->directLinkHelper;
    }

    public function setDirectLinkHelper(Netresearch_OPS_Helper_Directlink $directLinkHelper)
    {
        $this->directLinkHelper = $directLinkHelper;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    protected function addPayIdSub(Mage_Sales_Model_Order_Payment $payment)
    {
        $txType = null;
        if ($payment->getAdditionalInformation('status')
            == Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING
        ) {
            $txType = Netresearch_OPS_Model_Payment_Abstract::OPS_CAPTURE_TRANSACTION_TYPE;
        }
        if ($payment->getAdditionalInformation('status') == Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING
        ) {
            $txType = Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_TRANSACTION_TYPE;
        }
        if (null !== $txType) {
            $transaction = $this->getDirectLinkHelper()->getPaymentTransaction(
                $this->getOrder(),
                $this->requestParams['PAYID'],
                $txType
            );
            if (0 < $transaction->getTxnId()) {
                $historyLevel = str_replace(
                    $transaction->getParentTxnId() . '/',
                    '',
                    $transaction->getTxnId()
                );
                $this->requestParams['PAYIDSUB'] = $historyLevel;
            }
        }
    }

    protected function canNotUseOrderId(Mage_Sales_Model_Order_Payment $payment)
    {
        $methodInstance = $payment->getMethodInstance();

        return ($methodInstance instanceof Netresearch_OPS_Model_Payment_Kwixo_Abstract)
        || ($methodInstance instanceof Netresearch_OPS_Model_Payment_DirectDebit)
        || ($methodInstance instanceof Netresearch_OPS_Model_Payment_Cc
            && $methodInstance->hasBrandAliasInterfaceSupport($payment));
    }

} 