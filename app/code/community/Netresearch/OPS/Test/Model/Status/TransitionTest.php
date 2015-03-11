<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Test_Model_Status_TransitionTest extends EcomDev_PHPUnit_Test_Case
{

    public function testProcessOpsResponse()
    {
        $transitionModelMock = $this->getModelMock('ops/status_transition', array('processCaptureFeedback', 'processRefundFeedback', 'processVoidFeedback'));
        $order = Mage::getModel('sales/order');
        $opsResponse = array('STATUS' => 5);
        $transitionModelMock->processOpsResponse($opsResponse, $order);
        $this->assertEquals($opsResponse, $transitionModelMock->getOpsResponse());
        $this->assertEquals($order, $transitionModelMock->getOrder());
    }


    public function testCaptureFeedbackWithDirectFeedback()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 91, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);
        $txData = array('operation' => Netresearch_OPS_Model_Payment_Abstract::OPS_CAPTURE_FULL,
                        'type' => "full");
        $captureHelperMock = $this->getHelperMock('ops/order_capture', array('prepareOperation'));
        $captureHelperMock->expects($this->once())
            ->method('prepareOperation')
            ->will($this->returnValue($txData));

        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('directLinkTransact'));
        $directLinkHelperMock->expects($this->once())
            ->method('directLinkTransact')
        ;

        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->setCaptureHelper($captureHelperMock);
        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $cntBefore = $order->getStatusHistoryCollection()->count();
        $transitionModel->processCaptureFeedback();
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);
    }

    public function testCaptureFeedbackWithPostbackFeedback()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 9, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);
        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('processFeedback'));
        $directLinkHelperMock->expects($this->once())
            ->method('processFeedback')
            ->with($order, $opsResponse);
        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->processCaptureFeedback();
    }

    public function testVoidFeedBackWithUncertainStatus()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 61, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);

        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('directLinkTransact'));
        $directLinkHelperMock->expects($this->once())
            ->method('directLinkTransact')
        ;

        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));

        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->processVoidFeedback();
    }

    public function testVoidFeedBackWithSuccessStatus()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 6, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);

        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('directLinkTransact'));
        $directLinkHelperMock->expects($this->once())
            ->method('directLinkTransact')
        ;

        $voidHelper = $this->getHelperMock('ops/order_void', array('acceptVoid'));
        $voidHelper->expects($this->once())
            ->method('acceptVoid')
            ->with($order, $opsResponse)
        ;

        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));

        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->setVoidHelper($voidHelper);
        $transitionModel->processVoidFeedback();
    }

    public function testVoidFeedBackWithDeniedStatus()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 63, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);

        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('processFeedback'));
        $directLinkHelperMock->expects($this->once())
            ->method('processFeedback')
            ->with($order, $opsResponse);



        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));

        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->processVoidFeedback();
    }

    public function testRefundFeedBackWithWaitingStatus()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 81, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);

        $refundHelper = $this->getHelperMock('ops/order_refund', array('determineOperationCode', 'createRefundTransaction'));
        $refundHelper->expects($this->once())
            ->method('determineOperationCode')
            ->will($this->returnValue('RFS'));
        $refundHelper->expects($this->once())
            ->method('createRefundTransaction')
            ->with($opsResponse);

        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('hasPaymentTransactions'));
        $directLinkHelperMock->expects($this->once())
            ->method('hasPaymentTransactions')
            ->will($this->returnValue(false));

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('saveOpsRefundOperationCodeToPayment'));
        $paymentHelperMock->expects($this->once())
            ->method('saveOpsRefundOperationCodeToPayment')
            ->with($payment, 'RFS');


        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));
        $transitionModel->setRefundHelper($refundHelper);
        $transitionModel->setPaymentHelper($paymentHelperMock);
        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->processRefundFeedback();
    }

    public function testRefundFeedBackWithAcceptedStatus()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 8, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);

        $refundHelper = $this->getHelperMock('ops/order_refund', array('determineOperationCode', 'createRefund'));
        $refundHelper->expects($this->once())
            ->method('determineOperationCode')
            ->will($this->returnValue('RFS'));
        $refundHelper->expects($this->once())
            ->method('createRefund')
            ->with($order, $opsResponse);

        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('hasPaymentTransactions'));


        $paymentHelperMock = $this->getHelperMock('ops/payment', array('saveOpsRefundOperationCodeToPayment'));
        $paymentHelperMock->expects($this->once())
            ->method('saveOpsRefundOperationCodeToPayment')
            ->with($payment, 'RFS');


        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));
        $transitionModel->setRefundHelper($refundHelper);
        $transitionModel->setPaymentHelper($paymentHelperMock);
        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->processRefundFeedback();
    }

    public function testRefundFeedBackWithDeniedStatus()
    {
        $order = $this->getModelMock('sales/order', array('save', '_beforeSave', '_afterSave', 'load'));
        $payment = Mage::getModel('sales/order_payment');
        $methodInstance = Mage::getModel('ops/payment_cc');
        $payment->setMethod($methodInstance);
        $order->setPayment($payment);
        $opsResponse = array('STATUS' => 83, 'PAYID' => 1, 'PAYIDSUB' => 1, 'AMOUNT' => 1);

        $refundHelper = $this->getHelperMock('ops/order_refund', array('determineOperationCode'));
        $refundHelper->expects($this->once())
            ->method('determineOperationCode')
            ->will($this->returnValue('RFS'));


        $directLinkHelperMock = $this->getHelperMock('ops/directlink', array('processFeedback'));
        $directLinkHelperMock->expects($this->once())
            ->method('processFeedback')
            ->with($order, $opsResponse);

        $transitionModel = $this->getModelMock('ops/status_transition', array('reloadOrder'));
        $transitionModel->expects($this->any())
            ->method('reloadOrder')
            ->will($this->returnValue($order));
        $transitionModel->setRefundHelper($refundHelper);
        $transitionModel->setDirectlinkHelper($directLinkHelperMock);
        $transitionModel->setOrder($order);
        $transitionModel->setOpsResponse($opsResponse);
        $transitionModel->processRefundFeedback();
    }

} 