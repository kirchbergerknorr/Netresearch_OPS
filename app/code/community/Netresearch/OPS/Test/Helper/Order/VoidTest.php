<?php
class Netresearch_OPS_Test_Helper_Order_VoidTest
    extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidAccepted()
    {
        $paymentMock = $this->getModelMock('sales/order_payment', array('save'));
        $this->replaceByMock('model', 'sales/order_payment', $paymentMock);

        Mage::unregister('ops_auto_void');
        $helper = Mage::helper('ops/order_void');
        $order = Mage::getModel('sales/order')->load(11);
        $directLinkHelper = $this->getHelperMock("ops/directlink", array('closePaymentTransaction'));
        $this->replaceByMock('helper', 'ops/directlink', $directLinkHelper);
        $helper->acceptVoid($order, array('orderID' => 11));
        $this->assertTrue(Mage::registry('ops_auto_void'));
        Mage::unregister('ops_auto_void');
    }

}