<?php
class Netresearch_OPS_Test_Helper_OrderTest extends EcomDev_PHPUnit_Test_Case
{

    private $devPrefix = '';

    public function setUp()
    {
        $this->devPrefix = Mage::getModel('ops/config')->getConfigData(
            'devprefix'
        );
        parent::setUp();
    }


    /**
     * @loadFixture order.yaml
     */
    public function testGetOpsOrderId()
    {
        $store = Mage::app()->getStore(0)->load(0);
        $store->resetConfig();
        $helper = Mage::helper('ops/order');
        $order = Mage::getModel('sales/order')->load(1);
        $delimiter = $helper::DELIMITER;
        $this->assertEquals(
            $this->devPrefix . $order->getQuoteId(),
            $helper->getOpsOrderId($order)
        );


        $store->setConfig(
            'payment_services/ops/redirectOrderReference',
            Netresearch_OPS_Model_Payment_Abstract::REFERENCE_ORDER_ID
        );

        $order = Mage::getModel('sales/order')->load(2);
        $this->assertEquals(
            $this->devPrefix . $delimiter . $order->getIncrementId(),
            $helper->getOpsOrderId($order)
        );

        $store->setConfig(
            'payment_services/ops/redirectOrderReference',
            Netresearch_OPS_Model_Payment_Abstract::REFERENCE_QUOTE_ID
        );
        $order = Mage::getModel('sales/order')->load(3);
        $this->assertEquals(
            $this->devPrefix . $order->getQuoteId(),
            $helper->getOpsOrderId($order)
        );

        $store->setConfig(
            'payment_services/ops/redirectOrderReference',
            Netresearch_OPS_Model_Payment_Abstract::REFERENCE_ORDER_ID
        );
        $order = Mage::getModel('sales/order')->load(3);
        $this->assertEquals(
            $this->devPrefix . $order->getQuoteId(),
            $helper->getOpsOrderId($order, false)
        );
    }

    
    /**
     * @loadFixture order.yaml
     */
    public function testGetOrder()
    {
        $helper = Mage::helper('ops/order');
        // old behaviour: load order from quote
        $opsOrderId = $this->devPrefix . '5';
        $order = $helper->getOrder($opsOrderId);
        $this->assertEquals(4, $order->getId());

        // new behaviour
        $delimiter = $helper::DELIMITER;
        $opsOrderId = $this->devPrefix . $delimiter . 2000;
        $order = $helper->getOrder($opsOrderId);
        $this->assertEquals(2, $order->getId());


    }


    /**
     * @loadFixture order.yaml
     */
    public function testGetQuote()
    {
        $order = Mage::getModel('sales/order')->load(1);
        $quote = Mage::helper('ops/order')->getQuote($order->getQuoteId());
        $this->assertTrue($quote instanceof Mage_Sales_Model_Quote);
        $this->assertEquals(1, $quote->getId());
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testCheckIfAddressAreSameWithSameAddressData()
    {
        $order = Mage::getModel('sales/order')->load(11);
        $this->assertTrue(
            (bool)Mage::helper('ops/order')->checkIfAddressesAreSame($order)
        );

        $order = Mage::getModel('sales/order')->load(27);
        $this->assertFalse(
            (bool)Mage::helper('ops/order')->checkIfAddressesAreSame($order)
        );
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testCheckIfAddressAreSameWithDifferentAddressData()
    {
        $order = Mage::getModel('sales/order')->load(12);
        $this->assertFalse(
            (bool)Mage::helper('ops/order')->checkIfAddressesAreSame($order)
        );
    }

    public function testCheckForOpsStateOnStatusUpdateWillPassStatusUpdate()
    {
        $payment = Mage::getModel('sales/order_payment');
        $payment->setAdditionalInformation('status', 9);
        $order = Mage::getModel('sales/order');
        $order->setPayment($payment);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
        Mage::helper('ops/order')->checkForOpsStateOnStatusUpdate($order);
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getStatus());
    }

    public function testCheckForOpsStateOnStatusUpdateWillNotPassStatusUpdate()
    {
        $payment = Mage::getModel('sales/order_payment');
        $payment->setAdditionalInformation('status', 9);
        $order = Mage::getModel('sales/order');
        $order->setPayment($payment);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        Mage::helper('ops/order')->checkForOpsStateOnStatusUpdate($order);
        $this->assertEquals(null, $order->getStatus());
    }

    public function testSetDataHelper()
    {
        $dataHelper = $this->getHelperMock('ops/data');
        $helper = Mage::helper('ops/order');
        $helper->setDataHelper($dataHelper);
        $this->assertEquals($dataHelper, $helper->getDataHelper());
    }

    public function testSetStatusMappingModel()
    {
        $dataHelper = $this->getModelMock('ops/status_mapping');
        $helper = Mage::helper('ops/order');
        $helper->setStatusMappingModel($dataHelper);
        $this->assertEquals($dataHelper, $helper->getStatusMappingModel());
    }
}
