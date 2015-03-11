<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Model_Status_Transition
{

    protected $order = null;

    protected $opsResponse = array();

    protected $infoArr = array();

    protected $captureHelper = null;

    protected $directLinkHelper = null;

    protected $voidHelper = null;

    protected $refundHelper = null;

    protected $paymentHelper = null;

    /**
     * @param null $paymentHelper
     */
    public function setPaymentHelper($paymentHelper)
    {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @param null $refundHelper
     */
    public function setRefundHelper($refundHelper)
    {
        $this->refundHelper = $refundHelper;
    }

    /**
     * @param null $voidHelper
     */
    public function setVoidHelper($voidHelper)
    {
        $this->voidHelper = $voidHelper;
    }

    /**
     * @return null
     */
    public function getVoidHelper()
    {
        if (null == $this->voidHelper) {
            $this->voidHelper = Mage::helper("ops/order_void");
        }

        return $this->voidHelper;
    }

    /**
     * @param array $infoArr
     */
    public function setInfoArr($infoArr)
    {
        $this->infoArr = $infoArr;
    }

    /**
     * @return array
     */
    public function getInfoArr()
    {
        return $this->infoArr;
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

    /**
     * @param null $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return null
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * processes the Ingenico Payment Services responses
     *
     * @param array                  $opsResponse
     * @param Mage_Sales_Model_Order $order
     *
     * @return $this
     */
    public function processOpsResponse(array $opsResponse, Mage_Sales_Model_Order $order)
    {
        $this->opsResponse = $opsResponse;
        $this->order       = $order;
        $this->processCaptureFeedback();
        $this->processRefundFeedback();
        $this->processVoidFeedback();

        return $this;
    }

    /**
     * process the feedback from Ingenico Payment Services for capture operations
     *
     * @return $this
     */
    public function processCaptureFeedback()
    {
        if ($this->isCaptureOperation()) {
            // use direct link helper processFeedback, but need special handling for those status which are handled after setting up the direct link request
            if ($this->isCaptureRequestResponse()) {
                $this->infoArr = $this->getCaptureOperation();
                $this->saveCaptureTxn();
                //Reload order to avoid wrong status
                $order = $this->reloadOrder();
                $order->addStatusHistoryComment(
                    $this->getDataHelper()->__(
                        'Invoice will be created automatically as soon as Ingenico Payment Services sends an acknowledgement. Ingenico Payment Services status: %s.',
                        $this->getDataHelper()->getStatusText($this->opsResponse['STATUS'])
                    )
                );
                $order->save();
                $this->setOrder($order);
            } else {
                $this->processFeedback();
            }
        }

        return $this;
    }

    /**
     * processes the feedback from Ingenico Payment Services for void operations
     *
     * @return $this
     */
    public function processVoidFeedback()
    {
        if ($this->isVoidOperation()) {
            if ($this->isOpsVoidWaitingOrUncertain()) {
                $this->infoArr = array(
                    'amount'       => $this->opsResponse['AMOUNT'],
                    'void_request' => Mage::app()->getRequest()->getParams(),
                    'response'     => $this->opsResponse,
                );
                $msg           = $this->getDataHelper()->__(
                    'Start Ingenico Payment Services void request. Ingenico Payment Services status: %s.',
                    $this->getDataHelper()->getStatusText($this->opsResponse['STATUS'])
                );
                $this->createVoidTxn($msg);

            } /*
             * If the ops response results directly in accepted state, create a void transaction
             */
            elseif ($this->isVoidAccepted()) {
                $this->infoArr = array();
                $msg           = $this->getDataHelper()->__(
                    'Void order succeed. Ingenico Payment Services status: %s.',
                    $this->opsResponse['STATUS']
                );
                $this->createVoidTxn($msg);
                $this->getVoidHelper()->acceptVoid($this->getOrder(), $this->getOpsResponse());
            } else {
                $this->processFeedback();
            }
        }

        return $this;
    }

    /**
     * processes the feedback from Ingenico Payment Services for refund operations
     *
     * @return $this
     */
    public function processRefundFeedback()
    {
        if ($this->isRefundOperation()) {
            $operation = $this->getRefundHelper()->determineOperationCode(
                $this->getOrder()->getPayment(),
                $this->opsResponse['AMOUNT']
            );


            if ($this->isRefundWaiting()) {
                $this->getPaymentHelper()->saveOpsRefundOperationCodeToPayment(
                    $this->getOrder()->getPayment(),
                    $operation
                );
                $this->getRefundHelper()->setPayment($this->getOrder()->getPayment());
                if (false == $this->getDirectlinkHelper()->hasPaymentTransactions($this->getOrder(), 'refund')) {
                    $this->getRefundHelper()->createRefundTransaction($this->opsResponse);
                }

            } elseif ($this->isRefundAccepted()
                && $this->getOrder()->getPayment()->getAdditionalInformation('status')
                != Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING
            ) {
                //do refund directly if response is ok already
                $this->getPaymentHelper()->saveOpsRefundOperationCodeToPayment(
                    $this->getOrder()->getPayment(),
                    $operation
                );
                $this->getRefundHelper()->setPayment($this->getOrder()->getPayment());
                try {
                    $this->getRefundHelper()->createRefund($this->getOrder(), $this->getOpsResponse(), true);
                } catch (Mage_Core_Exception $e) {
                    Mage::logException($e);
                }
            } else {
                $this->processFeedback();
            }
        }
        return $this;
    }

    /**
     * retrieves the Ingenico Payment Services status for the payment
     *
     * @return string | null the Ingenico Payment Services status for the payment
     */
    protected function getOpsStatus()
    {
        $status = null;
        if (array_key_exists('STATUS', $this->opsResponse)) {
            $status = $this->opsResponse['STATUS'];
        }

        return $status;
    }

    /**
     * return the Ingenico Payment Services's payment identifier
     *
     * @return string | null the Ingenico Payment Services's payment identifier
     */
    protected function getOpsPayId()
    {
        $payId = null;
        if (array_key_exists('PAYID', $this->opsResponse)) {
            $payId = $this->opsResponse['PAYID'];
        }

        return $payId;
    }

    /**
     * retrieves the sub payid from Ingenico Payment Services's response
     *
     * @return null| string the payment sub identifier from Ingenico Payment Services
     */
    protected function getOpsPayIdSub()
    {
        $payIdSub = null;
        if (array_key_exists('PAYIDSUB', $this->opsResponse)) {
            $payIdSub = $this->opsResponse['PAYIDSUB'];
        }

        return $payIdSub;
    }

    /**
     * checks if the current status is valid for a capture operation
     *
     * @return bool
     */
    public function isCaptureOperation()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_UNCERTAIN,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REFUSED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DECLINED_ACQUIRER,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSED_MERCHANT,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_IN_PROGRESS,
            )
        );
    }

    /**
     * checks if the current status is valid for a refund operation
     *
     * @return bool
     */
    public function isRefundOperation()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_UNCERTAIN_STATUS,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_REFUSED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_DECLINED_ACQUIRER,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PROCESSED_MERCHANT,
            )
        );
    }

    /**
     * checks if the current status is valid for a void operation
     *
     * @return bool
     */
    public function isVoidOperation()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_WAITING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_UNCERTAIN,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_REFUSED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED_ACCEPTED,
            )
        );
    }


    /**
     * checks if the response is matching a previous capture request
     *
     * @return bool
     */
    public function isCaptureRequestResponse()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_UNCERTAIN,
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_IN_PROGRESS
            )
        );
    }

    /**
     * retrieves the capture operation
     *
     * @return array
     */
    public function getCaptureOperation()
    {
        return $this->getCaptureHelper()->prepareOperation(
            $this->getOrder()->getPayment(),
            $this->opsResponse['AMOUNT']
        );
    }

    /**
     * @return Netresearch_OPS_Helper_Order_Capture
     */
    public function getCaptureHelper()
    {
        if (null == $this->captureHelper) {
            $this->captureHelper = Mage::helper('ops/order_capture');
        }

        return $this->captureHelper;
    }

    public function setCaptureHelper(Netresearch_OPS_Helper_Order_Capture $captureHelper)
    {
        $this->captureHelper = $captureHelper;
    }

    /**
     * creates a capture transaction
     */
    protected function saveCaptureTxn()
    {
        $this->getDirectlinkHelper()->directLinkTransact(
            $this->reloadOrder(),
            $this->getOpsPayId(),
            $this->getOpsPayIdSub(),
            $this->infoArr,
            Netresearch_OPS_Model_Payment_Abstract::OPS_CAPTURE_TRANSACTION_TYPE,
            $this->getDataHelper()->__('Start Ingenico Payment Services %s capture request', $this->infoArr['type'])
        );
    }

    /**
     * @return Netresearch_OPS_Helper_Directlink
     */
    public function getDirectlinkHelper()
    {
        if (null == $this->directLinkHelper) {
            $this->directLinkHelper = Mage::helper('ops/directlink');
        }

        return $this->directLinkHelper;
    }

    public function setDirectlinkHelper(Netresearch_OPS_Helper_Directlink $directlinkHelper)
    {
        $this->directLinkHelper = $directlinkHelper;
    }

    /**
     * @return Netresearch_OPS_Helper_Data
     */
    public function getDataHelper()
    {
        return Mage::helper('ops/data');
    }

    /**
     * checks if the response is void in waiting or uncertain
     *
     * @return bool
     */
    protected function isOpsVoidWaitingOrUncertain()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_WAITING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_UNCERTAIN
            )
        );
    }

    /**
     * checks if the refund is accepted
     *
     * @return bool
     */
    protected function isRefundAccepted()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PROCESSED_MERCHANT
            )
        );
    }

    /**
     * checks if the void is accepted
     *
     * @return bool
     */
    protected function isVoidAccepted()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED_ACCEPTED
            )
        );
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    protected function reloadOrder()
    {
        return Mage::getSingleton("sales/order")->loadByIncrementId(
            $this->getOrder()->getIncrementId()
        );
    }

    /**
     * creates a void transaction
     *
     * @param     $msg    - message
     * @param int $closed - whether it's closed or not
     */
    protected function createVoidTxn($msg, $closed = 0)
    {
        $this->getDirectlinkHelper()->directLinkTransact(
            $this->reloadOrder(),
            $this->getOpsPayId(),
            $this->getOpsPayIdSub(),
            $this->infoArr,
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_TRANSACTION_TYPE,
            $msg,
            $closed
        );
    }

    /**
     * checks if the refund is waiting or uncertain
     *
     * @return bool
     */
    protected function isRefundWaiting()
    {
        return in_array(
            $this->getOpsStatus(),
            array(
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_UNCERTAIN_STATUS
            )
        );
    }

    /**
     * @return Netresearch_OPS_Helper_Payment
     */
    public function getPaymentHelper()
    {
        if (null == $this->paymentHelper) {
            $this->paymentHelper = Mage::helper('ops/payment');
        }

        return $this->paymentHelper;
    }

    /**
     * @return Netresearch_OPS_Helper_Order_Refund
     */
    public function getRefundHelper()
    {
        if (null == $this->refundHelper) {
            $this->refundHelper = Mage::helper('ops/order_refund');
        }

        return $this->refundHelper;
    }

    /**
     * processes the feedback from Ingenico Payment Services
     */
    protected function processFeedback()
    {
        try {
            $this->getDirectlinkHelper()->processFeedback($this->getOrder(), $this->getOpsResponse());
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
        }
    }
} 