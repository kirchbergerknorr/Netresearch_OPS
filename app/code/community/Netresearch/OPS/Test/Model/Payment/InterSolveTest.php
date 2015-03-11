<?php
/**
 * @category   OPS
 * @package    Netresearch_OPS
 * @author     Thomas Birke <thomas.birke@netresearch.de>
 * @copyright  Copyright (c) 2012 Netresearch GmbH & Co. KG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Netresearch_OPS_Test_Model_Payment_InterSolveTest
 * @author     Thomas Birke <thomas.birke@netresearch.de>
 * @copyright  Copyright (c) 2012 Netresearch GmbH & Co. KG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Test_Model_Payment_InterSolveTest extends EcomDev_PHPUnit_Test_Case_Config
{
    public function testClassExists()
    {
        $this->assertModelAlias('ops/payment_interSolve', 'Netresearch_OPS_Model_Payment_InterSolve');
        $this->assertTrue(Mage::getModel('ops/payment_interSolve') instanceof Netresearch_OPS_Model_Payment_InterSolve);
        $this->assertTrue(Mage::getModel('ops/payment_interSolve') instanceof Netresearch_OPS_Model_Payment_Abstract);
    }

    public function testMethodConfig()
    {
        $this->assertConfigNodeValue('default/payment/ops_interSolve/model', 'ops/payment_interSolve');
    }

    public function testPm()
    {
        $this->assertEquals('InterSolve', Mage::getModel('ops/payment_interSolve')->getOpsCode());
    }

    public function testBrand()
    {
        $payment = Mage::getModel('sales/quote_payment');
        $payment->setAdditionalInformation('BRAND', 'InterSolve');
        $this->assertEquals('InterSolve', Mage::getModel('ops/payment_interSolve')->getOpsBrand($payment));
    }

    public function testAssignDataWithBrand()
    {
        $payment = $this->getModelMock('sales/quote_payment', array('setAdditionalInformation'));
        $payment->setMethod('ops_interSolve');
        $payment->expects($this->any())
            ->method('setAdditionalInformation')
            ->with(
                $this->equalTo('BRAND'),
                $this->equalTo('InterSolve')
            );

        $quote = Mage::getModel('sales/quote');
        $quote->setPayment($payment);

        $checkout = $this->getModelMock('checkout/session');
        $checkout->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $this->replaceByMock('singleton', 'checkout/session', $checkout);

        $data = array('intersolve_brand' => 'FooBar');
        $this->assertEquals('InterSolve', $payment->getMethodInstance()->getOpsCode());

        $method = $payment->getMethodInstance()->assignData($data);
        $this->assertInstanceOf('Netresearch_OPS_Model_Payment_InterSolve', $method);

        $payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
        $this->assertEquals('FooBar', $payment->getAdditionalInformation('BRAND'));
    }

    public function testAssignDataWithoutBrand()
    {
        $payment = $this->getModelMock('sales/quote_payment', array('setAdditionalInformation'));
        $payment->setMethod('ops_interSolve');
        $payment->expects($this->any())
            ->method('setAdditionalInformation')
            ->with(
                $this->equalTo('BRAND'),
                $this->equalTo('InterSolve')
            );

        $quote = Mage::getModel('sales/quote');
        $quote->setPayment($payment);

        $checkout = $this->getModelMock('checkout/session');
        $checkout->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $this->replaceByMock('singleton', 'checkout/session', $checkout);

        $this->assertEquals('InterSolve', $payment->getMethodInstance()->getOpsCode());

        $method = $payment->getMethodInstance();
        $this->assertInstanceOf('Netresearch_OPS_Model_Payment_InterSolve', $method);
        $method->assignData(array());

        $payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
        $this->assertEquals('InterSolve', $payment->getAdditionalInformation('BRAND'));
    }
}

