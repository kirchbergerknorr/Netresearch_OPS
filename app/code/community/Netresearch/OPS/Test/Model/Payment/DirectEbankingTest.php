<?php
class Netresearch_OPS_Test_Model_Payment_DirectEbankingTest extends EcomDev_PHPUnit_Test_Case
{
    public function testAssignData()
    {
        $data = array(
            'directEbanking_brand' => 'Sofort Uberweisung',
        );
        $payment = Mage::getModel('sales/order_payment');
        $infoInstance = new Varien_Object();

        $modelMock = $this->getModelMock('ops/payment_DirectEbanking', array('getPayment', 'getInfoInstance'));
        $modelMock->expects($this->any())
            ->method('getPayment')
            ->will($this->returnValue($payment));
        $modelMock->expects($this->any())
                  ->method('getInfoInstance')
                  ->will($this->returnValue($infoInstance));
        $modelMock = $modelMock->assignData($data);
        $this->assertEquals($modelMock->getOpsBrand(), 'DirectEbanking');
        $this->assertEquals($modelMock->getOpsCode(), 'DirectEbanking');
    }

}
