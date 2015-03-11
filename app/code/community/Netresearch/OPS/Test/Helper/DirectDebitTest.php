<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2013 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Test_Helper_DirectDebitTest
    extends EcomDev_PHPUnit_Test_Case
{

    protected function getDirectDebitHelper()
    {
        return Mage::helper('ops/directDebit');
    }

    public function testGetDataHelper()
    {
        $this->assertTrue(
            $this->getDirectDebitHelper()->getDataHelper() instanceof
            Netresearch_OPS_Helper_Data
        );
    }

    public function testGetQuoteHelper()
    {
        $this->assertTrue(
            $this->getDirectDebitHelper()->getQuoteHelper() instanceof
            Netresearch_OPS_Helper_Quote
        );
    }

    public function testGetOrderHelper()
    {
        $this->assertTrue(
            $this->getDirectDebitHelper()->getOrderHelper() instanceof
            Netresearch_OPS_Helper_Order
        );
    }

    public function testGetCustomerHelper()
    {
        $this->assertTrue(
            $this->getDirectDebitHelper()->getCustomerHelper() instanceof
            Mage_Customer_Helper_Data
        );
    }

    public function testGetValidator()
    {
        $this->assertTrue(
            $this->getDirectDebitHelper()->getValidator() instanceof
            Netresearch_OPS_Model_Validator_Payment_DirectDebit
        );
    }

    public function testGetCountry()
    {
        $helper = $this->getDirectDebitHelper();
        $params = array();
        $this->assertEquals('', $helper->getCountry($params));
        $params['country'] = 'de';
        $this->assertEquals('DE', $helper->getCountry($params));
    }

    public function testHasIban()
    {
        $helper = $this->getDirectDebitHelper();
        $params = array();
        $this->assertFalse($helper->hasIban($params));
        $params['iban'] = '';
        $this->assertFalse($helper->hasIban($params));
        $params['iban'] = ' ';
        $this->assertFalse($helper->hasIban($params));
        $params['iban'] = '123456789';
        $this->assertTrue($helper->hasIban($params));
    }

    public function testSetDirectDebitDataToPayment()
    {
        $payment = Mage::getModel('sales/quote_payment');
        $helper  = $this->getDirectDebitHelper();
        $params  = array('country' => 'de', 'account' => '', 'bankcode' => '');
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            'Direct Debits DE', $payment->getAdditionalInformation('PM')
        );

        $params = array(
            'country'  => 'de', 'CN' => 'Account Holder', 'account' => '',
            'bankcode' => ''
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            'Account Holder', $payment->getAdditionalInformation('CN')
        );

        $params = array(
            'country' => 'nl', 'CN' => 'Account Holder',
            'account' => '1234567', 'bankcode' => ''
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            '1234567', $payment->getAdditionalInformation('CARDNO')
        );

        $params = array(
            'country' => 'at', 'CN' => 'Account Holder',
            'account' => '1234567', 'bankcode' => '1234567'
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            '1234567BLZ1234567', $payment->getAdditionalInformation('CARDNO')
        );

        $params = array(
            'country' => 'de', 'CN' => 'Account Holder',
            'account' => '1234567', 'bankcode' => '1234567'
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            '1234567BLZ1234567', $payment->getAdditionalInformation('CARDNO')
        );

        $params = array(
            'country'  => 'de', 'CN' => 'Account Holder',
            'iban'     => 'DE1234567890', 'account' => '1234567',
            'bankcode' => '1234567'
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            'DE1234567890', $payment->getAdditionalInformation('CARDNO')
        );

        $params = array(
            'country'  => 'at', 'CN' => 'Account Holder',
            'iban'     => 'DE1234567890', 'account' => '1234567',
            'bankcode' => '1234567'
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            '1234567BLZ1234567', $payment->getAdditionalInformation('CARDNO')
        );

        $params = array(
            'country' => 'nl', 'CN' => 'Account Holder',
            'iban'    => 'NL1234567890', 'bic' => '12345678',
            'account' => '1234567', 'bankcode' => '1234567'
        );
        $helper->setDirectDebitDataToPayment($payment, $params);
        $this->assertEquals(
            'NL1234567890', $payment->getAdditionalInformation('CARDNO')
        );
        $this->assertEquals(
            '12345678', $payment->getAdditionalInformation('BIC')
        );
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGetDirectLinkRequestParams()
    {
        $quote      = Mage::getModel('sales/quote')->load(10);
        $order      = Mage::getModel('sales/order')->load(11);
        $dataHelper = $this->getHelperMock('ops/data', array('isAdminSession'));
        $dataHelper->expects($this->any())
            ->method('isAdminSession')
            ->will($this->returnValue(false));

        $customerHelper = $this->getHelperMock(
            'customer/data', array('isLoggedIn')
        );
        $customerHelper->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(false));

        $quoteHelper = $this->getHelperMock(
            'ops/quote', array('getPaymentAction', 'getQuoteCurrency')
        );
        $quoteHelper->expects($this->any())
            ->method('getPaymentAction')
            ->will($this->returnValue('RES'));
        $quoteHelper->expects($this->any())
            ->method('getQuoteCurrency')
            ->will($this->returnValue('EUR'));

        $orderHelper = $this->getHelperMock(
            'ops/order', array('checkIfAddressesAreSame')
        );
        $orderHelper->expects($this->any())
            ->method('checkIfAddressesAreSame')
            ->will($this->returnValue(1));

        $helper = $this->getDirectDebitHelper();
        $helper->setDataHelper($dataHelper);
        $helper->setCustomerHelper($customerHelper);
        $helper->setQuoteHelper($quoteHelper);
        $helper->setOrderHelper($orderHelper);
        $this->assertEquals(
            $this->getExpectedResultOnFrontendRequest($order),
            $helper->getDirectLinkRequestParams($quote, $order)
        );
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGetDirectLinkRequestParamsWithNoShippingAddress()
    {
        $quote      = Mage::getModel('sales/quote')->load(10);
        $order      = Mage::getModel('sales/order')->load(28);
        $dataHelper = $this->getHelperMock('ops/data', array('isAdminSession'));
        $dataHelper->expects($this->any())
            ->method('isAdminSession')
            ->will($this->returnValue(false));

        $customerHelper = $this->getHelperMock(
            'customer/data', array('isLoggedIn')
        );
        $customerHelper->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(false));

        $quoteHelper = $this->getHelperMock(
            'ops/quote', array('getPaymentAction', 'getQuoteCurrency')
        );
        $quoteHelper->expects($this->any())
            ->method('getPaymentAction')
            ->will($this->returnValue('RES'));
        $quoteHelper->expects($this->any())
            ->method('getQuoteCurrency')
            ->will($this->returnValue('EUR'));

        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));

        $helper = $this->getDirectDebitHelper();
        $helper->setConfig($configMock);
        $helper->getRequestHelper()->setConfig($configMock);
        $helper->setDataHelper($dataHelper);
        $helper->setCustomerHelper($customerHelper);
        $helper->setQuoteHelper($quoteHelper);

        $result = $helper->getDirectLinkRequestParams($quote, $order);
        $this->assertEquals(
            '04227',
            $result['ECOM_SHIPTO_POSTAL_POSTALCODE']
        );
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGetDirectLinkRequestParamsWithLoggedInCustomer()
    {
        $quote      = Mage::getModel('sales/quote')->load(10);
        $order      = Mage::getModel('sales/order')->load(28);
        $dataHelper = $this->getHelperMock('ops/data', array('isAdminSession'));
        $dataHelper->expects($this->any())
            ->method('isAdminSession')
            ->will($this->returnValue(false));

        $fakeCustomer = new Varien_Object();
        $fakeCustomer->setId(666);
        $customerHelper = $this->getHelperMock(
            'customer/data', array('isLoggedIn', 'getCustomer')
        );
        $customerHelper->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(true));
        $customerHelper->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($fakeCustomer));

        $quoteHelper = $this->getHelperMock(
            'ops/quote', array('getPaymentAction', 'getQuoteCurrency')
        );
        $quoteHelper->expects($this->any())
            ->method('getPaymentAction')
            ->will($this->returnValue('RES'));
        $quoteHelper->expects($this->any())
            ->method('getQuoteCurrency')
            ->will($this->returnValue('EUR'));
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $helper = $this->getDirectDebitHelper();
        $helper->setConfig($configMock);
        $helper->getRequestHelper()->setConfig($configMock);
        $helper->setDataHelper($dataHelper);
        $helper->setCustomerHelper($customerHelper);
        $helper->setQuoteHelper($quoteHelper);

        $result = $helper->getDirectLinkRequestParams($quote, $order);
        $this->assertEquals(666, $result['CUID']);
    }


    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGetDirectLinkRequestParamsForBackendOrder()
    {
        $quote = Mage::getModel('sales/quote')->load(10);
        $order = Mage::getModel('sales/order')->load(11);

        $validator = $this->getModelMock(
            'ops/validator_payment_directDebit', array('isValid')
        );
        $validator->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(true));

        $dataHelper = $this->getHelperMock('ops/data', array('isAdminSession'));
        $dataHelper->expects($this->any())
            ->method('isAdminSession')
            ->will($this->returnValue(true));

        $customerHelper = $this->getHelperMock(
            'customer/data', array('isLoggedIn')
        );
        $customerHelper->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(false));

        $quoteHelper = $this->getHelperMock(
            'ops/quote', array('getPaymentAction', 'getQuoteCurrency')
        );
        $quoteHelper->expects($this->any())
            ->method('getPaymentAction')
            ->will($this->returnValue('RES'));
        $quoteHelper->expects($this->any())
            ->method('getQuoteCurrency')
            ->will($this->returnValue('EUR'));

        $orderHelper = $this->getHelperMock(
            'ops/order', array('checkIfAddressesAreSame')
        );
        $orderHelper->expects($this->any())
            ->method('checkIfAddressesAreSame')
            ->will($this->returnValue(1));

        $helper = $this->getDirectDebitHelper();
        $helper->setDataHelper($dataHelper);
        $helper->setCustomerHelper($customerHelper);
        $helper->setQuoteHelper($quoteHelper);
        $helper->setOrderHelper($orderHelper);
        $helper->setValidator($validator);

        $params = array(
            'country'  => 'DE', 'CN' => 'Hans Wurst', 'account' => '4711',
            'bankcode' => '0815'
        );
        $helper->handleAdminPayment($quote, $params);
        $result = $helper->getDirectLinkRequestParams($quote, $order, $params);
        $this->assertEquals('Direct Debits DE', $result['PM']);
        $this->assertEquals('Hans Wurst', $result['CN']);
        $this->assertEquals($result['PM'], $result['BRAND']);
        $this->assertEquals('4711BLZ0815', $result['CARDNO']);
        $this->assertEquals(1, $result['ECI']);

        $params = array(
            'country'  => 'DE', 'CN' => 'Hans Wurst', 'account' => '4711',
            'bankcode' => '0815', 'bic' => '4712', 'iban' => '0816'
        );
        $helper->handleAdminPayment($quote, $params);
        $result = $helper->getDirectLinkRequestParams($quote, $order, $params);
        $this->assertEquals('0816', $result['CARDNO']);
        $this->assertArrayNotHasKey('BIC', $result);

        $params = array(
            'country'  => 'NL', 'CN' => 'Hans Wurst', 'account' => '4711',
            'bankcode' => '0815', 'bic' => '4712', 'iban' => '0816'
        );
        $helper->handleAdminPayment($quote, $params);
        $result = $helper->getDirectLinkRequestParams($quote, $order, $params);
        $this->assertEquals('0816', $result['CARDNO']);
        $this->assertArrayHasKey('BIC', $result);
        $this->assertEquals('4712', $result['BIC']);

        $params = array(
            'country'  => 'NL', 'CN' => 'Hans Wurst', 'account' => '4711',
            'bankcode' => '0815', 'bic' => '', 'iban' => '0816'
        );
        $quote->getPayment()->setAdditionalInformation('PM', 'Direct Debits NL');
        $helper->handleAdminPayment($quote, $params);
        $result = $helper->getDirectLinkRequestParams($quote, $order, $params);
        $this->assertArrayNotHasKey('BIC', $result);

        $params = array(
            'country'  => 'DE', 'CN' => 'Hans Wurst', 'account' => '4711',
            'bankcode' => '0815', 'bic' => '', 'iban' => '0816'
        );
        $quote->getPayment()->setAdditionalInformation('PM', 'Direct Debits DE');
        $helper->handleAdminPayment($quote, $params);
        $result = $helper->getDirectLinkRequestParams($quote, $order, $params);
        $this->assertArrayNotHasKey('BIC', $result);
    }


    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGetDirectLinkRequestParamsForBackendOrderThrowsException(
    )
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);

        $quote = Mage::getModel('sales/quote')->load(10);
        $order = Mage::getModel('sales/order')->load(11);

        $validator = $this->getModelMock(
            'ops/validator_payment_directDebit', array('isValid', 'getMessages')
        );
        $validator->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(false));
        $validator->expects($this->any())
            ->method('getMessages')
            ->will($this->returnValue(array('foo', 'bar')));

        $dataHelper = $this->getHelperMock('ops/data', array('isAdminSession'));
        $dataHelper->expects($this->any())
            ->method('isAdminSession')
            ->will($this->returnValue(true));

        $helper = $this->getDirectDebitHelper();
        $helper->setDataHelper($dataHelper);
        $helper->setValidator($validator);

        $params = array(
            'country'  => 'DE', 'CN' => 'Hans Wurst', 'account' => '4711',
            'bankcode' => '0815'
        );
        try {
            $helper->getDirectLinkRequestParams(
                $quote, $order, $params
            );
        } catch (Exception $e) {

        }
    }

    protected function getExpectedResultOnFrontendRequest($order)
    {
        return array(
            'AMOUNT'                        => 0.0,
            'CARDNO'                        => NULL,
            'CN'                            => utf8_decode(""),
            'CURRENCY'                      => "EUR",
            'ED'                            => "9999",
            'OPERATION'                     => "RES",
            'ORDERID'                       => Mage::getModel('ops/config')->getConfigData('devprefix') . $order->getQuoteId(),
            'PM'                            => NULL,
            'OWNERADDRESS'                  => utf8_decode(
                "An der Tabaksmühle 3a"
            ),
            'OWNERTOWN'                     => utf8_decode("Leipzig"),
            'OWNERZIP'                      => "04229",
            'OWNERTELNO'                    => NULL,
            'OWNERCTY'                      => "DE",
            'ADDMATCH'                      => 1,
            'ECOM_SHIPTO_POSTAL_POSTALCODE' => "04229",
            'ECOM_BILLTO_POSTAL_POSTALCODE' => "04229",
            'ORIG'                          => Mage::helper("ops")->getModuleVersionString(),
            'BRAND'                         => NULL,
            'ECOM_SHIPTO_POSTAL_NAME_FIRST' => 'Hubertus',
            'ECOM_SHIPTO_POSTAL_NAME_LAST' => utf8_decode('Fürstenberg'),
            'ECOM_SHIPTO_POSTAL_STREET_LINE1' => utf8_decode('An der Tabaksmühle 3a'),
            'ECOM_SHIPTO_POSTAL_STREET_LINE2' => '',
            'ECOM_SHIPTO_POSTAL_COUNTRYCODE' => 'DE',
            'ECOM_SHIPTO_POSTAL_CITY' => 'Leipzig',
            'EMAIL'                         => 'hubertus.von.fuerstenberg@trash-mail.com',
            'REMOTE_ADDR'                   => null,
            'ECOM_SHIPTO_POSTAL_STATE'      => ''
        );

    }


} 