<?php

class Netresearch_OPS_Test_Model_Backend_Operation_Refund_ParameterTest extends EcomDev_PHPUnit_Test_Case
{

    public function testGetRequestParams()
    {
        $fakePayment = new Varien_Object();
        $fakePayment->setOrder(new Varien_Object());
        $fakePayment->setAdditionalInformation(array('paymentId' => '4711'));
        $arrInfo          = array(
            'operation'  => 'refund',
            'invoice_id' => 2
        );
        $amount           = 10;
        $opsPaymentMethod = Mage::getModel('ops/payment_abstract');

        $captureParameterModel = Mage::getModel('ops/backend_operation_refund_parameter');
        $requestParams         = $captureParameterModel->getRequestParams($opsPaymentMethod, $fakePayment, $amount, $arrInfo);
        $this->assertArrayHasKey('AMOUNT', $requestParams);
        $this->assertArrayHasKey('PAYID', $requestParams);
        $this->assertArrayHasKey('OPERATION', $requestParams);
        $this->assertArrayHasKey('CURRENCY', $requestParams);

        $this->assertEquals(1000, $requestParams['AMOUNT']);
        $this->assertEquals(4711, $requestParams['PAYID']);
        $this->assertEquals('refund', $requestParams['OPERATION']);
        $this->assertEquals(Mage::app()->getStore($fakePayment->getOrder()->getStoreId())->getBaseCurrencyCode(), $requestParams['CURRENCY']);
    }

    public function testGetRequestParamsWithAdditionalParameters()
    {
        $fakePayment = Mage::getModel('sales/order_payment');
        $fakePayment->setOrder(Mage::getModel('sales/order'));
        $fakePayment->setAdditionalInformation(array('paymentId' => '4711'));
        $fakeInvoice = Mage::getModel('sales/order_invoice');
        $fakePayment->setInvoice($fakeInvoice);
        $arrInfo               = array(
            'operation'  => 'refund',
            'invoice_id' => 2
        );
        $amount                = 10;
        $opsPaymentMethod      = Mage::getModel('ops/payment_openInvoiceNl');
        $captureParameterModel = Mage::getModel('ops/backend_operation_refund_parameter');
        $this->mockRefundHelper();
        $requestParams         = $captureParameterModel->getRequestParams($opsPaymentMethod, $fakePayment, $amount, $arrInfo);
        $this->assertArrayHasKey('AMOUNT', $requestParams);
        $this->assertArrayHasKey('PAYID', $requestParams);
        $this->assertArrayHasKey('OPERATION', $requestParams);
        $this->assertArrayHasKey('CURRENCY', $requestParams);

        $this->assertEquals(1000, $requestParams['AMOUNT']);
        $this->assertEquals(4711, $requestParams['PAYID']);
        $this->assertEquals('refund', $requestParams['OPERATION']);
        $this->assertEquals(Mage::app()->getStore($fakePayment->getOrder()->getStoreId())->getBaseCurrencyCode(), $requestParams['CURRENCY']);
    }

    protected function mockRefundHelper()
    {
        $helperMock = $this->getHelperMock('ops/order_refund', array('getCreditMemoRequestParams', 'createRefundTransaction'));
        $params     = array(
            'creditmemo' => array(
                'items'               => array(
                    1 => array(
                        'qty' => 0
                    ),
                    2 => array(
                        'qty' => 0
                    )
                ),
                'shipping_amount'     => 0,
                'adjustment_positive' => 10,
                'adjustment_negative' => 0

            )
        );
        $helperMock->expects($this->any())
                   ->method('getCreditMemoRequestParams')
                   ->will($this->returnValue($params));
        $this->replaceByMock('helper', 'ops/order_refund', $helperMock);
    }

}
 