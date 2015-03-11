<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Model_Status_Mapping
{

    protected $orderStatesForOpsState = array();

    protected $transitionModel = null;

    /**
     * @param null $transitionModel
     */
    public function setTransitionModel($transitionModel)
    {
        $this->transitionModel = $transitionModel;
    }

    /**
     * @return Netresearch_OPS_Model_Status_Transition
     */
    public function getTransitionModel()
    {
        if (null == $this->transitionModel) {
            $this->transitionModel = Mage::getModel('ops/status_transition');
        }

        return $this->transitionModel;
    }

    /**
     * retrieves the possible magento order's states for the according Ingenico Payment Services status
     *
     * @param $opsStatus - the Ingenico Payment Services status
     *
     * @return array - the order's states
     */
    public function getStatusForOpsStatus($opsStatus)
    {
        $this->getTransitionModel()->setOpsResponse(array('STATUS' => $opsStatus));
        $this->handleRefundOperations();
        $this->handleCaptureOperations();
        $this->handleVoidOperations();
        $this->handleAuthOperations($opsStatus);
        $this->handleRefusedOperations($opsStatus);

        return $this->orderStatesForOpsState;
    }

    /**
     * retrieves Magento order's states for refund operations
     *
     * @return $this
     */
    protected function handleRefundOperations()
    {
        if ($this->getTransitionModel()->isRefundOperation()) {
            $this->orderStatesForOpsState = array(
                Mage_Sales_Model_Order::STATE_CLOSED,
                Mage_Sales_Model_Order::STATE_PROCESSING
            );
        }

        return $this;
    }

    /**
     * retrieves Magento order's states for capture operations
     *
     * @return $this
     */
    protected function handleCaptureOperations()
    {
        if ($this->getTransitionModel()->isCaptureOperation()) {
            $this->orderStatesForOpsState = array(
                Mage_Sales_Model_Order::STATE_COMPLETE,
                Mage_Sales_Model_Order::STATE_PROCESSING
            );
        }

        return $this;
    }

    /**
     * retrieves Magento order's states for void operations
     *
     * @return $this
     */
    protected function handleVoidOperations()
    {
        if ($this->getTransitionModel()->isVoidOperation()) {
            $this->orderStatesForOpsState = array(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_COMPLETE,
                Mage_Sales_Model_Order::STATE_CANCELED
            );
        }

        return $this;
    }

    /**
     * retrieves Magento order's states for auth operations
     *
     * @param $opsStatus
     *
     * @return $this
     */
    protected function handleAuthOperations($opsStatus)
    {
        if ($this->isOpsAuthorizedStatus($opsStatus)
            || 0 == strlen(trim($opsStatus))
            || $opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_ORDER_SAVED
        ) {
            $this->orderStatesForOpsState = array(Mage_Sales_Model_Order::STATE_PROCESSING);
            if ($opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT
                || $opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING
            ) {
                $this->orderStatesForOpsState = array(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            }
        }

        return $this;
    }

    /**
     * retrieves Magento order's states for refused operations
     *
     * @param $opsStatus
     *
     * @return $this
     */
    protected function handleRefusedOperations($opsStatus)
    {
        if ($this->isRefusedOpsStatus($opsStatus)) {
            $this->orderStatesForOpsState = array(Mage_Sales_Model_Order::STATE_CANCELED);
        }

        return $this;
    }

    /**
     * @param $opsStatus
     *
     * @return bool
     */
    protected function isOpsAuthorizedStatus($opsStatus)
    {
        return ($opsStatus >= Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT
            && $opsStatus <= Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_TO_GET_MANUALLY)
        || $opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_OPEN_INVOICE_DE_PROCESSED;
    }

    /**
     * @param $opsStatus
     *
     * @return bool
     */
    protected function isRefusedOpsStatus($opsStatus)
    {
        return $opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_AUTH_REFUSED
        || $opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REFUSED
        || $opsStatus == Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_CANCELED_BY_CUSTOMER;
    }
} 