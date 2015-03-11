<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Test_Model_Status_MappingTest extends EcomDev_PHPUnit_Test_Case
{

    protected $mappingModel = null;

    protected $opsStates = array();

    protected $expectedOrderStates = array();

    public function setUp()
    {
        parent::setUp();
        $this->mappingModel = Mage::getModel('ops/status_mapping');
    }

    protected function assertStatusMapping()
    {
        foreach ($this->opsStates as $opsState) {
            $this->assertEquals($this->expectedOrderStates, $this->mappingModel->getStatusForOpsStatus($opsState));
        }
    }

    public function testVoidStatus()
    {
        $this->opsStates = array(
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_WAITING,
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_UNCERTAIN,
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_REFUSED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED_ACCEPTED,
        );

        $this->expectedOrderStates = array(
            Mage_Sales_Model_Order::STATE_PROCESSING,
            Mage_Sales_Model_Order::STATE_COMPLETE,
            Mage_Sales_Model_Order::STATE_CANCELED
        );

        $this->assertStatusMapping();

    }

    public function testCaptureStatus()
    {
        $this->opsStates = array(
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REQUESTED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSING,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_UNCERTAIN,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_DECLINED_ACQUIRER,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_PROCESSED_MERCHANT,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_IN_PROGRESS,
        );

        $this->expectedOrderStates = array(
            Mage_Sales_Model_Order::STATE_COMPLETE,
            Mage_Sales_Model_Order::STATE_PROCESSING
        );

        $this->assertStatusMapping();
    }

    public function testRefundStatus()
    {
        $this->opsStates = array(
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUNDED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_WAITING,
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_UNCERTAIN_STATUS,
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_REFUSED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_DECLINED_ACQUIRER,
            Netresearch_OPS_Model_Payment_Abstract::OPS_REFUND_PROCESSED_MERCHANT,
        );

        $this->expectedOrderStates = array(
            Mage_Sales_Model_Order::STATE_CLOSED,
            Mage_Sales_Model_Order::STATE_PROCESSING
        );

        $this->assertStatusMapping();
    }

    public function testAuthStatusProcessingStateInMagento()
    {
        $this->opsStates           = array(
            Netresearch_OPS_Model_Payment_Abstract::OPS_ORDER_SAVED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_OPEN_INVOICE_DE_PROCESSED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_WAITING_FOR_IDENTIFICATION,
            Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_KWIXO,
            Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_UNKNOWN,
            Netresearch_OPS_Model_Payment_Abstract::OPS_STAND_BY,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENTS_SCHEDULED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_TO_GET_MANUALLY,
            ''
        );
        $this->expectedOrderStates = array(Mage_Sales_Model_Order::STATE_PROCESSING);
        $this->assertStatusMapping();
    }

    public function testAuthStatusPendingPaymentStateInMagento()
    {
        $this->opsStates           = array(
            Netresearch_OPS_Model_Payment_Abstract::OPS_AWAIT_CUSTOMER_PAYMENT,
            Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING

        );
        $this->expectedOrderStates = array(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $this->assertStatusMapping();
    }

    public function testRefusedStatus()
    {
        $this->opsStates           = array(
            Netresearch_OPS_Model_Payment_Abstract::OPS_AUTH_REFUSED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_REFUSED,
            Netresearch_OPS_Model_Payment_Abstract::OPS_PAYMENT_CANCELED_BY_CUSTOMER
        );
        $this->expectedOrderStates = array(Mage_Sales_Model_Order::STATE_CANCELED);
        $this->assertStatusMapping();
    }

    public function testSetTransitionModel()
    {
        $transitionModel = $this->getModelMock('ops/status_transition');
        $this->mappingModel->setTransitionModel($transitionModel);
        $this->assertEquals($this->mappingModel->getTransitionModel(), $transitionModel);
    }
} 