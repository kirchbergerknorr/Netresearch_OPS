<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch_OPS
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Netresearch_OPS_Test_Model_Backend_Operation_ParameterTest extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @expectedException Mage_Core_Exception
     */
    public function testGetParameterForWillThrowException()
    {
        $fakePayment = new Varien_Object();
        $arrInfo = array();
        $amount = 0;
        $opsPaymentMethod = Mage::getModel('ops/payment_abstract');
        Mage::getModel('ops/backend_operation_parameter')->getParameterFor('NOT SUPPORTED OPERATION TYPE', $opsPaymentMethod, $fakePayment, $amount, $arrInfo);
    }

    public function testGetParameterForCaptureWillReturnArray()
    {
        $fakePayment = new Varien_Object();
        $fakePayment->setOrder(new Varien_Object());
        $fakePayment->setAdditionalInformation(array('paymentId' => '4711'));
        $arrInfo = array('operation' => 'capture');
        $amount = 10;
        $opsPaymentMethod = Mage::getModel('ops/payment_abstract');
        $requestParams = Mage::getModel('ops/backend_operation_parameter')->getParameterFor(Netresearch_OPS_Model_Payment_Abstract::OPS_CAPTURE_TRANSACTION_TYPE, $opsPaymentMethod, $fakePayment, $amount, $arrInfo);
        $this->assertArrayHasKey('AMOUNT', $requestParams);
        $this->assertArrayHasKey('PAYID', $requestParams);
        $this->assertArrayHasKey('OPERATION', $requestParams);
        $this->assertArrayHasKey('CURRENCY', $requestParams);

        $this->assertEquals(1000, $requestParams['AMOUNT']);
        $this->assertEquals(4711, $requestParams['PAYID']);
        $this->assertEquals('capture', $requestParams['OPERATION']);
        $this->assertEquals(Mage::app()->getStore($fakePayment->getOrder()->getStoreId())->getBaseCurrencyCode(), $requestParams['CURRENCY']);
    }

} 