<?php

/**
 * Netresearch_OPS_Helper_Order_Refund
 *
 * @package
 * @copyright 2011 Netresearch
 * @author    André Herrn <andre.herrn@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_Helper_Order_Refund extends Netresearch_OPS_Helper_Order_Abstract
{
    protected $payment;
    protected $amount;
    protected $params;

    protected function getFullOperationCode()
    {
        return Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_FULL;
    }

    protected function getPartialOperationCode()
    {
        return Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PARTIAL;
    }

    protected function getPreviouslyProcessedAmount($payment)
    {
        return $payment->getBaseAmountRefundedOnline();
    }


    /**
     * @param Varien_Object $payment
     */
    public function setPayment(Varien_Object $payment)
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @param  array $params
     * @return $this
     */
    public function setCreditMemoRequestParams($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @return array params
     */
    public function getCreditMemoRequestParams()
    {
        if (!is_array($this->params)) {
            $this->setCreditMemoRequestParams(Mage::app()->getRequest()->getParams());
        }

        return $this->params;
    }

    public function getInvoiceFromCreditMemoRequest()
    {
        $params = $this->getCreditMemoRequestParams();
        if (array_key_exists('invoice_id', $params)) {
            return Mage::getModel('sales/order_invoice')->load($params['invoice_id']);
        }

        return null;
    }

    public function getCreditMemoFromRequest()
    {
        $params = $this->getCreditMemoRequestParams();
        if (array_key_exists('creditmemo', $params)) {
            return $params['creditmemo'];
        }

        return array();
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     */
    public function prepareOperation($payment, $amount)
    {
        $params = $this->getCreditMemoRequestParams();

        if (array_key_exists('creditmemo', $params)) {
            $arrInfo           = $params['creditmemo'];
            $arrInfo['amount'] = $amount;
        }
        $arrInfo['type']      = $this->determineType($payment, $amount);
        $arrInfo['operation'] = $this->determineOperationCode($payment, $amount);

        if($arrInfo['type'] == 'full'){
            // hard overwrite operation code for last transaction
            $arrInfo['operation'] = $this->getFullOperationCode();
        }


        return $arrInfo;
    }


    /**
     * Create a new payment transaction for the refund
     *
     * @param array $response
     * @param int $closed
     *
     * @return void
     */
    public function createRefundTransaction($response, $closed = 0)
    {
        $transactionParams = array(
            'creditmemo_request' => $this->getCreditMemoRequestParams(),
            'response'           => $response,
            'amount'             => $this->amount
        );

        Mage::helper('ops/directlink')->directLinkTransact(
            Mage::getModel('sales/order')->load($this->payment->getOrder()->getId()),
            $response['PAYID'],
            $response['PAYIDSUB'],
            $transactionParams,
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_TRANSACTION_TYPE,
            Mage::helper('ops')->__('Start Ingenico Payment Services refund request'),
            $closed
        );

        $order = Mage::getModel('sales/order')->load($this->payment->getOrder()->getId());
        $order->addStatusHistoryComment(
            Mage::helper('ops')->__(
                'Creditmemo will be created automatically as soon as Ingenico Payment Services sends an acknowledgement. Ingenico Payment Services Status: %s.',
                Mage::helper('ops')->getStatusText($response['STATUS'])
            )
        );
        $order->save();
    }

    /**
     * Create a new refund
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $params
     * @param bool $setPaymentRefundDisallowed
     *
     */
    public function createRefund(Mage_Sales_Model_Order $order, $params, $setPaymentRefundDisallowed = true)
    {
        $transactionParams = array();
        try {
            $refundTransaction = Mage::helper('ops/directlink')->getPaymentTransaction(
                $order,
                $params['PAYID'],
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_TRANSACTION_TYPE
            );
            $transactionParams = $refundTransaction->getAdditionalInformation();
        } catch (Mage_Core_Exception $e) {

        }
        try {

            if (array_key_exists('arrInfo', $transactionParams)) {
                $transactionParams = unserialize($transactionParams['arrInfo']);
                $invoice           = Mage::getModel('sales/order_invoice')
                                         ->load($transactionParams['creditmemo_request']['invoice_id'])
                                         ->setOrder($order);
            } elseif ($order->getInvoiceCollection()->count() === 1) {
                $invoice = $order->getInvoiceCollection()->getFirstItem();
            }
            //Start to create the creditmemo
            Mage::register('ops_auto_creditmemo', true);
            $service = Mage::getModel('sales/service_order', $order);

            $data       = $this->prepareCreditMemoData($transactionParams);
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);

            /**
             * Process back to stock flags
             */
            $backToStock = array();
            if (array_key_exists('backToStock', $data)) {
                $backToStock = $data['backToStock'];
            }
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                $orderItem = $creditmemoItem->getOrderItem();
                $parentId  = $orderItem->getParentItemId();
                if (isset($backToStock[$orderItem->getId()])) {
                    $creditmemoItem->setBackToStock(true);
                } elseif ($orderItem->getParentItem() && isset($backToStock[$parentId]) && $backToStock[$parentId]) {
                    $creditmemoItem->setBackToStock(true);
                } elseif (empty($savedData)) {
                    $creditmemoItem->setBackToStock(Mage::helper('cataloginventory')->isAutoReturnEnabled());
                } else {
                    $creditmemoItem->setBackToStock(false);
                }
            }

            //Send E-Mail and Comment
            $comment              = '';
            $sendEmail            = false;
            $sendEMailWithComment = false;
            if (isset($data['send_email']) && $data['send_email'] == 1) {
                $sendEmail = true;
            }
            if (isset($data['comment_customer_notify'])) {
                $sendEMailWithComment = true;
            }

            if (!empty($data['comment_text'])):
                $creditmemo->addComment($data['comment_text'], $sendEMailWithComment);
                if ($sendEMailWithComment):
                    $comment = $data['comment_text'];
                endif;
            endif;

            $creditmemo->setPaymentRefundDisallowed($setPaymentRefundDisallowed);
            $creditmemo->setRefundRequested(true);
            $creditmemo->setOfflineRequested(false);
            $creditmemo->register();
            if ($sendEmail):
                $creditmemo->setEmailSent(true);
            endif;
            $creditmemo->getOrder()->setCustomerNoteNotify($sendEMailWithComment);

            $transactionSave = Mage::getModel('core/resource_transaction')
                                   ->addObject($creditmemo)
                                   ->addObject($creditmemo->getOrder());
            if ($creditmemo->getInvoice()):
                $transactionSave->addObject($creditmemo->getInvoice());
            endif;
            $transactionSave->save();
            $creditmemo->sendEmail($sendEmail, $comment);


            //End of create creditmemo

            //close refund payment transaction
            Mage::helper('ops/directlink')->closePaymentTransaction(
                $order,
                $params,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_TRANSACTION_TYPE,
                Mage::helper('ops')->__(
                    'Creditmemo "%s" was created automatically. Ingenico Payment Services Status: %s.',
                    $creditmemo->getIncrementId(),
                    Mage::helper('ops')->getStatusText($params['STATUS'])
                ),
                $sendEmail
            );
            Mage::helper('ops/payment')->setCanRefundToPayment($order->getPayment());
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException('Error in Creditmemo creation process: ' . $e->getMessage());
        }

    }

    /**
     * Get requested items qtys
     */
    protected function prepareCreditMemoData($transactionParams)
    {
        if (!array_key_exists('creditmemo_request', $transactionParams)
            || !array_key_exists('creditmemo', $transactionParams['creditmemo_request'])
        ) {
            return array();
        }
        $data        = $transactionParams['creditmemo_request']['creditmemo'];
        $qtys        = array();
        $backToStock = array();

        if (array_key_exists('items', $data)) {
            foreach ($data['items'] as $orderItemId => $itemData):
                if (isset($itemData['qty'])):
                    $qtys[$orderItemId] = $itemData['qty'];
                else:
                    if (isset($itemData['back_to_stock'])):
                        $backToStock[$orderItemId] = true;
                    endif;
                endif;
            endforeach;
        }
        $data['qtys']        = $qtys;
        $data['backToStock'] = $backToStock;

        return $data;
    }
}
