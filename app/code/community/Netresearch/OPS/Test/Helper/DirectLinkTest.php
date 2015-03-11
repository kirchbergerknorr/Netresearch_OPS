<?php
class Netresearch_OPS_Test_Helper_DirectLinkTest extends EcomDev_PHPUnit_Test_Case
{
    public function setUp()
    {
        parent::setup();
        $this->_helper = Mage::helper('ops/directlink');
        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->setAdditionalInformation('arrInfo', serialize(array(
            'amount' => '184.90'
        )));
        $transaction->setIsClosed(0);
        $this->_transaction = $transaction;
        $this->_order = Mage::getModel('sales/order');
        $this->_order->setGrandTotal('184.90');
        $this->_order->setBaseGrandTotal('184.90');
    }

    public function testDeleteActions()
    {
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, array('STATUS'=>Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DELETED)));
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, array('STATUS'=>Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DELETED_WAITING)));
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, array('STATUS'=>Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DELETED_UNCERTAIN)));
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, array('STATUS'=>Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DELETED_REFUSED)));
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, array('STATUS'=>Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DELETED_OK)));
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, array('STATUS'=>Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DELETED_PROCESSED_MERCHANT)));
    }

    public function testRefundActions()
    {
        $opsRequest = array(
            'STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED,
            'amount' => '184.90'
        );
        $this->assertFalse($this->_helper->isValidOpsRequest(null, $this->_order, $opsRequest), 'Refund should not be possible without open transactions');
        $this->assertTrue($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, $opsRequest), 'Refund should be possible with open transactions');
        $opsRequest['amount'] = '14.90';
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, $opsRequest), 'Refund should NOT be possible because of differing amount');
    }

    public function testCancelActions()
    {
        $opsRequest = array(
            'STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED,
            'amount' => '184.90'
        );
        $this->assertFalse($this->_helper->isValidOpsRequest(null, $this->_order, $opsRequest), 'Cancel should not be possible without open transactions');
        $this->assertTrue($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, $opsRequest), 'Cancel should be possible with open transactions');
        $opsRequest['amount'] = '14.90';
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, $opsRequest), 'Cancel should NOT be possible because of differing amount');
    }

    public function testCaptureActions()
    {
        $opsRequest = array(
            'STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED,
            'amount' => '184.90'
        );
        $this->assertTrue($this->_helper->isValidOpsRequest(null, $this->_order, $opsRequest), 'Capture should be possible because of no open transactions and matching amount');
        $opsRequest['amount'] = '14.90';
        $this->assertFalse($this->_helper->isValidOpsRequest($this->_transaction, $this->_order, $opsRequest), 'Capture should NOT be possible because of differing amount');
    }

    public function testCleanupParameters()
    {
        $expected = 123.45;
        $result = $this->_helper->formatAmount('123.45');
        $this->assertEquals($expected, $result);

        $result = $this->_helper->formatAmount('\'123.45\'');
        $this->assertEquals($expected, $result);

        $result = $this->_helper->formatAmount('"123.45"');
        $this->assertEquals($expected, $result);

        $expected = $this->_helper->formatAmount(0.3);
        $result = $this->_helper->formatAmount(0.1 + 0.2);
        $this->assertEquals($expected . '', $result . '');
        $this->assertEquals((float) $expected, (float) $result);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackCaptureSuccess()
    {

        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', 9);
            return $order->getPayment();
        };

        $captureHelper = $this->getHelperMock('ops/order_capture', array('acceptCapture'));
        $captureHelper->expects($this->any())
            ->method('acceptCapture')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_capture', $captureHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED);
        $directlinkHelperMock->processFeedback($order, $params);
        $this->assertEquals(9, $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackRefundSuccess()
    {

        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));



        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', 8);
            return $order->getPayment();
        };

        $refundHelper = $this->getHelperMock('ops/order_refund', array('createRefund'));
        $refundHelper->expects($this->any())
            ->method('createRefund')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_refund', $refundHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $this->assertEquals($params['STATUS'], $order->getPayment()->getAdditionalInformation('status'));
    }


    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackRefundWithStatusEightyFiveSuccess()
    {
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));



        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PROCESSED_MERCHANT);
            return $order->getPayment();
        };

        $refundHelper = $this->getHelperMock('ops/order_refund', array('createRefund'));
        $refundHelper->expects($this->any())
            ->method('createRefund')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_refund', $refundHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PROCESSED_MERCHANT, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $this->assertEquals($params['STATUS'], $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackPaymentWaiting()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();


        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING);
            return $order->getPayment();
        };

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackPaymentRefused()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock(
            'ops/directlink',
            array('isValidOpsRequest', 'closePaymentTransaction')
        );
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation(
                'status',
                Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REFUSED
            );
            return $order->getPayment();
        };

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REFUSED, 'PAYID' => '4711');

        $directlinkHelperMock->expects($this->once())
            ->method('closePaymentTransaction')
            ->with(
                $order,
                $params,
                Netresearch_OPS_Model_Payment_Abstract::OPS_CAPTURE_TRANSACTION_TYPE,
                Mage::helper('ops')->__(
                    'Capture was refused. Automatic creation failed. Ingenico Payment Services status: %s.',
                    Mage::helper('ops')->getStatusText($params['STATUS'])
                )
            );

        $directlinkHelperMock->processFeedback($order, $params);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackRefundWaiting()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();


        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING);
            return $order->getPayment();
        };

        $refundHelper = $this->getHelperMock('ops/order_refund', array('createRefund'));
        $refundHelper->expects($this->any())
            ->method('createRefund')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_refund', $refundHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackRefundRefused()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock(
            'ops/directlink',
            array('isValidOpsRequest', 'closePaymentTransaction')
        );
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation(
                'status',
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING
            );
            return $order->getPayment();
        };

        $refundHelper = $this->getHelperMock('ops/order_refund', array('createRefund'));
        $refundHelper->expects($this->any())
            ->method('createRefund')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_refund', $refundHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_REFUSED, 'PAYID' => '4711');

        $directlinkHelperMock->expects($this->once())
            ->method('closePaymentTransaction')
            ->with(
                $order,
                $params,
                Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_TRANSACTION_TYPE,
                Mage::helper('ops')->__(
                    'Refund was refused. Automatic creation failed. Ingenico Payment Services status: %s.',
                    Mage::helper('ops')->getStatusText($params['STATUS'])
                )
            );

        $directlinkHelperMock->processFeedback($order, $params);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackVoidSuccess()
    {

        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));



        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', 6);
            return $order->getPayment();
        };

        $voidHelper = $this->getHelperMock('ops/order_void', array('acceptVoid'));
        $voidHelper->expects($this->any())
            ->method('acceptVoid')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_void', $voidHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $this->assertEquals($params['STATUS'], $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackVoidWaiting()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();


        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_WAITING);
            return $order->getPayment();
        };

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_WAITING, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackVoidRefused()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock(
            'ops/directlink',
            array('isValidOpsRequest', 'closePaymentTransaction')
        );
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation(
                'status',
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_REFUSED
            );
            return $order->getPayment();
        };

        $refundHelper = $this->getHelperMock('ops/order_void', array('acceptVoid'));
        $refundHelper->expects($this->any())
            ->method('acceptVoid')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/order_void', $refundHelper);
        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_REFUSED, 'PAYID' => '4711');

        $directlinkHelperMock->expects($this->once())
            ->method('closePaymentTransaction')
            ->with(
                $order,
                $params,
                Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_TRANSACTION_TYPE,
                Mage::helper('ops')->__(
                    'Void was refused. Automatic creation failed. Ingenico Payment Services status: %s.',
                    Mage::helper('ops')->getStatusText($params['STATUS'])
                )
            );

        $directlinkHelperMock->processFeedback($order, $params);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackAuthorizeChanged()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackAuthorizeKwixoAccepted()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(27);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();


        $closure = function ($order, $params = array()) {
            $order->getPayment()->setAdditionalInformation('status', Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED);
            return $order->getPayment();
        };

        $paymentHelper = $this->getHelperMock('ops/payment', array('acceptOrder'));
        $paymentHelper->expects($this->any())
            ->method('acceptOrder')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelper);

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $this->assertEquals($params['STATUS'], $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackUnknownStatus()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();

        $params = array('STATUS' => 4711, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testProcessFeedbackInvalidStatus()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(true));

        $cntBefore = $order->getStatusHistoryCollection()->count();

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_INVALID, 'PAYID' => '4711');
        $directlinkHelperMock->processFeedback($order, $params);
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore === $cntAfter);

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testIsNoValidOpsRequestWillThrowException()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load(11);
        $directlinkHelperMock = $this->getHelperMock('ops/directlink', array('isValidOpsRequest'));
        $directlinkHelperMock->expects($this->any())
            ->method('isValidOpsRequest')
            ->will($this->returnValue(false));

        $cntBefore = $order->getStatusHistoryCollection()->count();

        $params = array('STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_INVALID, 'PAYID' => '4711');
        try {
            $directlinkHelperMock->processFeedback($order, $params);
        } catch (Mage_Core_Exception $e) {

        }
        $cntAfter = $order->getStatusHistoryCollection()->count();
        $this->assertTrue($cntBefore < $cntAfter);
    }
}

