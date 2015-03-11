<?php
class Netresearch_OPS_Test_Model_Payment_AbstractTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{
    protected $model = null;


    public function setUp()
    {
        parent::setUp();
        $this->model = Mage::getModel('ops/payment_abstract');
    }

    public function testCaptureWithZeroAmount()
    {
        $paymentObject = new Varien_Object();
        $directLinkHelperMock = $this->getModelMock('ops/api_directlink', array('performRequest'));
        $directLinkHelperMock->expects($this->never())
            ->method('performRequest');
        $this->assertTrue($this->model->capture($paymentObject, 0.00) instanceof Netresearch_OPS_Model_Payment_Abstract);
    }

    /**
     * @test
     */
    public function _getOrderDescriptionShorterThen100Chars()
    {
        $items = array(
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => 'abc'
            )),
            new Varien_Object(array(
                'parent_item' => true,
                'name'       => 'def'
            )),
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => 'ghi'
            )),
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => 'Dubbelwerkende cilinder Boring ø70 Stang ø40 3/8'
            )),
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => '0123456789012345678901234567890123456789012xxxxxx'
            )),
        );

        $order = $this->getModelMock('sales/order', array('getAllItems'));
        $order->expects($this->any())
            ->method('getAllItems')
            ->will($this->returnValue($items));

        $result = Mage::getModel('ops/payment_abstract')->_getOrderDescription($order);
        $this->assertEquals(
            'abc, ghi, Dubbelwerkende cilinder Boring 70 Stang 40 3/8, 012345678901234567890123456789012345678901',
            $result
        );
    }

    /**
     * @test
     */
    public function _getOrderDescriptionLongerThen100Chars()
    {
        $items = array(
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => '1bcdefghij abcdefghij abcdefghij abcdefghij abcdefghi1' //54 chars
            )),
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => '2bcdefghij abcdefghij abcdefghij abcdefghij abcdefghi2' //54 chars
            ))
        );

        $order = $this->getModelMock('sales/order', array('getAllItems'));
        $order->expects($this->any())
            ->method('getAllItems')
            ->will($this->returnValue($items));

        $result = Mage::getModel('ops/payment_abstract')->_getOrderDescription($order);
        $this->assertEquals(
            '1bcdefghij abcdefghij abcdefghij abcdefghij abcdefghi1, 2bcdefghij abcdefghij abcdefghij abcdefghij ',
            $result
        );
    }

    /**
     * @test
     */
    public function _getOrderDescriptionLongerThen100CharsOneItem()
    {
        $items = array(
            new Varien_Object(array(
                'parent_item' => false,
                'name'       => '1bcdefghij abcdefghij abcdefghij abcdefghij abcdefghi1 '.
                                '2bcdefghij abcdefghij abcdefghij abcdefghij abcdefghi2'
            ))
        );

        $order = $this->getModelMock('sales/order', array('getAllItems'));
        $order->expects($this->any())
            ->method('getAllItems')
            ->will($this->returnValue($items));

        $result = Mage::getModel('ops/payment_abstract')->_getOrderDescription($order);
        $this->assertEquals(
            '1bcdefghij abcdefghij abcdefghij abcdefghij abcdefghi1 2bcdefghij abcdefghij abcdefghij abcdefghij a',
            $result
        );
    }

    /**
     * check if payment method BankTransfer returns correct BRAND and PM values
     *
     * @loadExpectation paymentMethods
     * @test
     */
    public function shouldReturnCorrectBrandAndPMValuesForBankTransfer()
    {
        $method = Mage::getModel('ops/payment_bankTransfer');

        $payment = $this->getModelMock('sales/quote_payment', array('getId'));
        $payment->expects($this->any())
            ->method('getId')
            ->will($this->returnValue('1'));
        $this->replaceByMock('model', 'sales/quote_payment', $payment);
        $quote = $this->getModelMock(
            'sales/quote', array('getPayment', 'getQuoteId')
        );
        $quote->expects($this->any())
            ->method('getQuoteId')
            ->will($this->returnValue('321'));
        $quote->expects($this->any())
            ->method('getPayment')
            ->will($this->returnValue($payment));

        $session = Mage::getSingleton(
            'checkout/session',
            array(
                 'last_real_order_id' => '123',
                 'quote'              => $quote
            )
        );

        $method = Mage::getModel('ops/payment_bankTransfer');
        try {
            $method->assignData(array('country_id' => 'DE'));
        } catch (Mage_Core_Exception $e) {
            if ('Cannot retrieve the payment information object instance.'
                != $e->getMessage()
            ) {
                throw $e;
            }
        }
        $this->assertEquals(
            $this->expected('ops_bankTransferDe')->getBrand(),
            $method->getOpsBrand(null)
        );
        $reflectedMethod = new ReflectionMethod($method, 'getOpsCode');
        $reflectedMethod->setAccessible(true);
        $this->assertEquals(
            $this->expected('ops_bankTransferDe')->getPm(),
            $reflectedMethod->invoke($method)
        );
    }

    /**
     * @test
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testCanCancelManually()
    {
        $opsAbstractPayment = new Netresearch_OPS_Model_Payment_Abstract();

        //Check for successful can cancel (pending_payment and payment status 0)
        $order = Mage::getModel("sales/order")->load(11);
        $this->assertTrue($opsAbstractPayment->canCancelManually($order));

        //Check for successful cancel (pending_payment and payment status null/not existing)
        $order = Mage::getModel("sales/order")->load(14);
        $this->assertTrue($opsAbstractPayment->canCancelManually($order));

        //Check for denied can cancel (pending_payment and payment status 5)
        $order = Mage::getModel("sales/order")->load(12);
        $this->assertFalse($opsAbstractPayment->canCancelManually($order));

        //Check for denied can cancel (processing and payment status 0)
        $order = Mage::getModel("sales/order")->load(13);
        $this->assertFalse($opsAbstractPayment->canCancelManually($order));
    }


    public function testGetCloseTransactionFromCreditMemoData()
    {
        $reflection_class
            = new ReflectionClass("Netresearch_OPS_Model_Payment_Abstract");

        //Then we need to get the method we wish to test and
        //make it accessible
        $method = $reflection_class->getMethod(
            "getCloseTransactionFromCreditMemoData"
        );
        $method->setAccessible(true);

        //We need to create an empty object to pass to
        //ReflectionMethod invoke method followed by our
        //test parameters
        $paymentModel = new Netresearch_OPS_Model_Payment_Abstract(null);

        $this->assertFalse($method->invoke($paymentModel, array()));
        $this->assertFalse(
            $method->invoke(
                $paymentModel, array('ops_close_transaction' => 'OFF')
            )
        );
        $this->assertFalse(
            $method->invoke(
                $paymentModel, array('ops_close_transaction' => 'off')
            )
        );
        $this->assertFalse(
            $method->invoke($paymentModel, array('ops_close_transaction' => ''))
        );
        $this->assertFalse(
            $method->invoke($paymentModel, array('ops_close_transaction' => 1))
        );

        $this->assertTrue(
            $method->invoke(
                $paymentModel, array('ops_close_transaction' => 'on')
            )
        );
        $this->assertTrue(
            $method->invoke(
                $paymentModel, array('ops_close_transaction' => 'ON')
            )
        );
    }


    /**
     * @test
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testCanRefund()
    {
        $paymentModel = Mage::getModel('ops/payment_abstract');
        Mage::app()->getRequest()->setParam('order_id', 17);
        $this->assertFalse($paymentModel->canRefund());

        Mage::app()->getRequest()->setParam('order_id', 18);
        $this->assertTrue($paymentModel->canRefund());

        Mage::app()->getRequest()->setParam('order_id', 11);
        $helperMock = $this->getHelperMock(
            'ops/directlink', array('hasPaymentTransactions')
        );
        $helperMock->expects($this->any())
            ->method('hasPaymentTransactions')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        $this->assertTrue($paymentModel->canRefund());

        $helperMock = $this->getHelperMock(
            'ops/directlink', array('hasPaymentTransactions')
        );
        $helperMock->expects($this->any())
            ->method('hasPaymentTransactions')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        $helperMock = $this->getHelperMock('ops/data', array('redirect'));
        $this->replaceByMock('helper', 'ops/data', $helperMock);

        $paymentModel->canRefund();
        $messages = Mage::getSingleton('core/session')->getMessages();
        $messageItems = $messages->getItems();
        $message = array_pop($messageItems);
        $this->assertEquals(
            Mage::helper('ops/data')->__(
                'There is already one creditmemo in the queue. The Creditmemo will be created automatically as soon as Ingenico Payment Services sends an acknowledgement.'
            ), $message->getText()
        );


        $helperMock = $this->getHelperMock(
            'ops/directlink', array('hasPaymentTransactions')
        );
        $helperMock->expects($this->any())
            ->method('hasPaymentTransactions')
            ->will($this->throwException(new Exception('foo')));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);
        $this->assertTrue($paymentModel->canRefund());
    }


    /**
     * @test
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testGetMethodDependendFormFields()
    {
        $order = Mage::getModel('sales/order')->load(11);
        $sessionMock = $this->getModelMock('checkout/session', array('getQuote'));
        $sessionMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($order));
        $this->replaceByMock('model', 'checkout/session', $sessionMock);


        $sessionMock = $this->getModelMock('customer/session', array('isLoggedIn'));
        $sessionMock->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'customer/session', $sessionMock);


        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $paymentModel = Mage::getModel('ops/payment_abstract');
        $paymentModel->setConfig($configMock);
        $formFields = $paymentModel->getMethodDependendFormFields($order);

        $this->assertTrue(array_key_exists('CN', $formFields));
        $this->assertTrue(array_key_exists('OWNERZIP', $formFields));
        $this->assertTrue(array_key_exists('OWNERCTY', $formFields));
        $this->assertTrue(array_key_exists('OWNERTOWN', $formFields));
        $this->assertTrue(array_key_exists('COM', $formFields));
        $this->assertTrue(array_key_exists('OWNERTELNO', $formFields));
        $this->assertTrue(array_key_exists('OWNERADDRESS', $formFields));
        $this->assertTrue(array_key_exists('BRAND', $formFields));
        $this->assertTrue(array_key_exists('ADDMATCH', $formFields));
        $this->assertTrue(array_key_exists('ECOM_SHIPTO_POSTAL_POSTALCODE', $formFields));
        $this->assertTrue(array_key_exists('ECOM_BILLTO_POSTAL_POSTALCODE', $formFields));
        $this->assertTrue(array_key_exists('CUID', $formFields));

        $order = Mage::getModel('sales/order')->load(27);
        $formFields = $paymentModel->getMethodDependendFormFields($order);
        $this->assertTrue(array_key_exists('ECOM_SHIPTO_POSTAL_POSTALCODE', $formFields));

        $order = Mage::getModel('sales/order')->load(11);
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(false));
        $paymentModel = Mage::getModel('ops/payment_abstract');
        $paymentModel->setConfig($configMock);
        $params = $paymentModel->getMethodDependendFormFields($order);
        foreach ($this->getOwnerParams() as $ownerParam) {
            if ($ownerParam == 'OWNERZIP') continue;
            $this->assertArrayNotHasKey($ownerParam, $params);
        }
        foreach ($this->getShippingParams() as $shippingParam) {
            $this->assertArrayNotHasKey($shippingParam, $params);
        }

        $order = Mage::getModel('sales/order')->load(19);


        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(false));
        $paymentModel = Mage::getModel('ops/payment_openInvoiceDe');
        $paymentModel->setConfig($configMock);
        $params = $paymentModel->getMethodDependendFormFields($order);
        foreach ($this->getOwnerParams() as $ownerParam) {
            if ($ownerParam == 'OWNERZIP' || $ownerParam == 'ADDMATCH' || $ownerParam == 'ECOM_BILLTO_POSTAL_POSTALCODE') continue;
            $this->assertArrayHasKey($ownerParam, $params);
        }
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(false));
        $paymentModel = Mage::getModel('ops/payment_openInvoiceNl');
        $paymentModel->setConfig($configMock);
        $params = $paymentModel->getMethodDependendFormFields($order);
        foreach ($this->getOwnerParams() as $ownerParam) {
            if ($ownerParam == 'OWNERZIP' || $ownerParam == 'ADDMATCH' || $ownerParam == 'ECOM_BILLTO_POSTAL_POSTALCODE') continue;
            $this->assertArrayHasKey($ownerParam, $params);
        }
        foreach ($this->getShippingParams() as $shippingParam) {
            if ($shippingParam == 'ECOM_SHIPTO_POSTAL_STREET_LINE2') continue;
            $this->assertArrayHasKey($shippingParam, $params);
        }


    }

    public function testGetFormFieldsEmptyWithNonExistingOrder()
    {
        $paymentModel = Mage::getModel('ops/payment_abstract');
        $this->assertTrue(
            is_array($paymentModel->getFormFields(null, array()))
        );
        $this->assertEquals(
            0, count($paymentModel->getFormFields(null, array()))
        );
    }

    public function testGetFormFieldsWithEmptyOrderPassedButExistingOrder()
    {
        $order = new Varien_Object();
        $payment = new Varien_Object();
        $payment->setMethodInstance(Mage::getModel('ops/payment_cc'));
        $order->setPayment($payment);
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('getMethodDependendFormFields', 'getOrder')
        );
        $paymentModel->expects($this->once())
            ->method('getOrder')
            ->will($this->returnValue($order));
        $formFields = $paymentModel->getFormFields(null, array());
        $this->assertArrayHasKey('PSPID', $formFields);
        $this->assertArrayHasKey('SHASIGN', $formFields);
    }

    public function testGetFormFields()
    {
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract', array('getMethodDependendFormFields')
        );
        $configMock = $this->getModelMock('ops/config', array('getPSPID'));
        $configMock->expects($this->once())
            ->method('getPSPID')
            ->with(null)
            ->will($this->returnValue('NRMAGENTO'));
        $this->replaceByMock('model', 'ops/config', $configMock);
        $helperMock = $this->getHelperMock('ops/payment', array('getShaSign'));
        $helperMock->expects($this->any())
            ->method('getSHASign')
            ->with(
                $this->anything(),
                $this->anything(),
                null
            )
            ->will($this->returnValue('SHA123'));
        $this->replaceByMock('helper', 'ops/payment', $helperMock);
        $order = new Varien_Object();
        $payment = new Varien_Object();
        $payment->setMethodInstance(Mage::getModel('ops/payment_cc'));
        $order->setPayment($payment);
        $formFields = $paymentModel->getFormFields($order, array());
        $this->assertArrayHasKey('PSPID', $formFields);
        $this->assertArrayHasKey('SHASIGN', $formFields);
        $this->assertArrayHasKey('ACCEPTURL', $formFields);
        $this->assertArrayHasKey('DECLINEURL', $formFields);
        $this->assertArrayHasKey('EXCEPTIONURL', $formFields);
        $this->assertArrayHasKey('CANCELURL', $formFields);
        $this->assertEquals('NRMAGENTO', $formFields['PSPID']);
        $this->assertEquals(
            '2d9f92d6f3955847ab2db427be75fe7eb0cde045', $formFields['SHASIGN']
        );
    }

    public function testGetFormFieldsWithTPParam()
    {
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract', array('getMethodDependendFormFields')
        );
        $configMock = $this->getModelMock(
            'ops/config', array('getPSPID', 'getconfigData')
        );
        $configMock->expects($this->once())
            ->method('getPSPID')
            ->with(null)
            ->will($this->returnValue('NRMAGENTO'));
        $configMock->expects($this->any())
            ->method('getConfigData')
//            ->with('template')
            ->will($this->returnValue('spo'));
        $this->replaceByMock('model', 'ops/config', $configMock);
        $helperMock = $this->getHelperMock('ops/payment', array('getShaSign'));
        $helperMock->expects($this->any())
            ->method('getSHASign')
            ->with(
                $this->anything(),
                $this->anything(),
                null
            )
            ->will($this->returnValue('SHA123'));
        $this->replaceByMock('helper', 'ops/payment', $helperMock);
        $order = new Varien_Object();
        $payment = new Varien_Object();
        $payment->setMethodInstance(Mage::getModel('ops/payment_cc'));
        $order->setPayment($payment);
        $formFields = $paymentModel->getFormFields($order, array());
        $this->assertArrayHasKey('PSPID', $formFields);
        $this->assertArrayHasKey('SHASIGN', $formFields);
        $this->assertArrayHasKey('TP', $formFields);
        $this->assertEquals('NRMAGENTO', $formFields['PSPID']);
        $this->assertEquals(
            '2d9f92d6f3955847ab2db427be75fe7eb0cde045', $formFields['SHASIGN']
        );
    }

    public function testGetFormFieldsWithFormDependendFormFields()
    {
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract', array('getMethodDependendFormFields')
        );
        $paymentModel->expects($this->any())
            ->method('getMethodDependendFormFields')
            ->will($this->returnValue(array('foo' => 'bla')));
        $configMock = $this->getModelMock('ops/config', array('getPSPID'));
        $configMock->expects($this->once())
            ->method('getPSPID')
            ->with(null)
            ->will($this->returnValue('NRMAGENTO'));
        $this->replaceByMock('model', 'ops/config', $configMock);
        $helperMock = $this->getHelperMock('ops/payment', array('getShaSign'));
        $helperMock->expects($this->any())
            ->method('getSHASign')
            ->with(
                $this->anything(),
                $this->anything(),
                null
            )
            ->will($this->returnValue('SHA123'));
        $this->replaceByMock('helper', 'ops/payment', $helperMock);
        $order = new Varien_Object();
        $payment = new Varien_Object();
        $payment->setMethodInstance(Mage::getModel('ops/payment_cc'));
        $order->setPayment($payment);
        $formFields = $paymentModel->getFormFields($order, array());
        $this->assertArrayHasKey('PSPID', $formFields);
        $this->assertArrayHasKey('SHASIGN', $formFields);
        $this->assertArrayHasKey('foo', $formFields);
        $this->assertEquals('NRMAGENTO', $formFields['PSPID']);
        $this->assertEquals(
            '2d9f92d6f3955847ab2db427be75fe7eb0cde045', $formFields['SHASIGN']
        );
        $this->assertEquals('bla', $formFields['foo']);
    }

    public function testGetFormFieldsWithStoreId()
    {
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract', array('getMethodDependendFormFields')
        );
        $configMock = $this->getModelMock(
            'ops/config', array('getPSPID', 'getSHASign')
        );
        $configMock->expects($this->once())
            ->method('getPSPID')
            ->with(1)
            ->will($this->returnValue('NRMAGENTO5'));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $helperMock = $this->getHelperMock('ops/payment', array('getShaSign'));

        $helperMock->expects($this->any())
            ->method('getSHASign')
            ->with(
                $this->anything(),
                $this->anything(),
                1
            )
            ->will($this->returnValue('SHA987'));
        $this->replaceByMock('helper', 'ops/payment', $helperMock);
        $order = new Varien_Object();
        $payment = new Varien_Object();
        $order->setStoreId(1);
        $payment->setMethodInstance(Mage::getModel('ops/payment_cc'));
        $order->setPayment($payment);
        $formFields = $paymentModel->getFormFields($order, array());
        $this->assertArrayHasKey('PSPID', $formFields);
        $this->assertArrayHasKey('SHASIGN', $formFields);
        $this->assertEquals('NRMAGENTO5', $formFields['PSPID']);
        $this->assertEquals(
            '0f119cdea2f8ddc0c852bab4765f12d3913982fc', $formFields['SHASIGN']
        );
    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidWithOpsAutoVoid()
    {
        Mage::register('ops_auto_void', true);
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );
        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));
        $payment = new Varien_Object();
        $paymentModel->void($payment, 11.90);
        $this->assertNull(Mage::registry('ops_auto_void'));
    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidWithExistingVoidTransactionLeadsToRedirect()
    {
        $helperMock = $this->getHelperMock('ops/directlink', array('checkExistingTransact'));
        $helperMock
            ->expects($this->any())
            ->method('checkExistingTransact')
            ->with(Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_TRANSACTION_TYPE, 11)
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);
        $order = Mage::getModel('sales/order')->load(11);

        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );
        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));


        $dataHelperMock = $this->getHelperMock('ops/data', array('redirect'));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);

        Mage::getSingleton('core/session')->getMessages(true);
        $noticeCountBefore = sizeof(
            Mage::getSingleton('core/session')->getItemsByType('notice')
        );
        $paymentModel->void($order->getPayment());
        $notices = Mage::getSingleton('core/session')->getMessages()->getItemsByType(
            'notice'
        );
        $noticeCountAfter = sizeof($notices);
        $this->assertGreaterThan($noticeCountBefore, $noticeCountAfter);
        $this->assertEquals(
            $dataHelperMock->__('You already sent a void request. Please wait until the void request will be acknowledged.'),
            current($notices)->getText()
        );



    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidWithExistingCaptureLeadsToRedirect()
    {

        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );
        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));


        $dataHelperMock = $this->getHelperMock('ops/data', array('redirect'));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);


        $helperMock = $this->getHelperMock('ops/directlink', array('checkExistingTransact'));
        $closure = function ($transactionType, $orderId) {
            $result = false;
            if ($transactionType == Netresearch_OPS_Model_Payment_Abstract::OPS_CAPTURE_TRANSACTION_TYPE) {
                $result = true;
            }
            return $result;
        };

        $helperMock
            ->expects($this->any())
            ->method('checkExistingTransact')
            ->will($this->returnCallback($closure));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        Mage::getSingleton('core/session')->getMessages(true);
        $noticeCountBefore = sizeof(
            Mage::getSingleton('core/session')->getItemsByType('notice')
        );

        $order = Mage::getModel('sales/order')->load(11);

        $paymentModel->void($order->getPayment());
        $notices = Mage::getSingleton('core/session')->getMessages()->getItemsByType(
            'notice'
        );
        $noticeCountAfter = sizeof($notices);
        $this->assertGreaterThan($noticeCountBefore, $noticeCountAfter);
        $this->assertEquals(
            $dataHelperMock->__('There is one capture request waiting. Please wait until this request is acknowledged.'),
            current($notices)->getText()
        );
    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidFailsWhenRequestThrowsException()
    {
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );
        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));
        $helperMock = $this->getHelperMock('ops/directlink', array('checkExistingTransact'));
        $helperMock
            ->expects($this->any())
            ->method('checkExistingTransact')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        $apiClientMock = $this->getModelMock(
            'ops/api_directlink', array('performRequest')
        );
        $exception = new Exception('Fake Request failed');
        $apiClientMock->expects($this->any())
            ->method('performRequest')
            ->will(
                $this->throwException($exception)
            );
        $this->replaceByMock('model', 'ops/api_directlink', $apiClientMock);
        $order = Mage::getModel('sales/order')->load(11);
        try {
            $paymentModel->void($order->getPayment());
        } catch (Exception $e) {
            $this->assertEquals('Fake Request failed', $e->getMessage());
        }
    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidFailsWhenStatusIsUnknown()
    {
        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );
        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));
        $helperMock = $this->getHelperMock('ops/directlink', array('checkExistingTransact'));
        $helperMock
            ->expects($this->any())
            ->method('checkExistingTransact')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        $apiClientMock = $this->getModelMock(
            'ops/api_directlink', array('performRequest')
        );
        $apiClientMock->expects($this->any())
            ->method('performRequest')
            ->will(
                $this->returnValue(array(
                                        'STATUS' => 666
                                   )));
        $this->replaceByMock('model', 'ops/api_directlink', $apiClientMock);
        $order = Mage::getModel('sales/order')->load(11);
        try {
            $paymentModel->void($order->getPayment());
        } catch (Exception $e) {
            $this->assertEquals(666, $order->getPayment()->getAdditionalInformation('status'));
            $helper = Mage::helper('ops/data');
            $this->assertEquals($helper->__('Void order failed. Ingenico Payment Services status: %s.', 666), $e->getMessage());
        }
    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidWithStatusVoidWaiting()
    {
        $txMock = $this->getModelMock('sales/order_payment_transaction', array('save'));
        $this->replaceByMock('model', 'sales/order_payment_transaction', $txMock);

        $dataHelperMock = $this->getHelperMock('ops/data', array('redirect'));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);

        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );


        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));
        $helperMock = $this->getHelperMock('ops/directlink', array('checkExistingTransact'));
        $helperMock
            ->expects($this->any())
            ->method('checkExistingTransact')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        $apiClientMock = $this->getModelMock(
            'ops/api_directlink', array('performRequest')
        );
        $apiClientMock->expects($this->any())
            ->method('performRequest')
            ->will(
                $this->returnValue(array(
                                        'STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_VOID_WAITING,
                                        'PAYID'  => '4711',
                                        'PAYIDSUB' => '0815'
                                   )));
        $this->replaceByMock('model', 'ops/api_directlink', $apiClientMock);
        $order = Mage::getModel('sales/order')->load(11);

        Mage::getSingleton('core/session')->getMessages(true);
        $noticeCountBefore = sizeof(
            Mage::getSingleton('core/session')->getItemsByType('notice')
        );

        $paymentModel->void($order->getPayment());
        $notices = Mage::getSingleton('core/session')->getMessages()->getItemsByType(
            'notice'
        );
        $noticeCountAfter = sizeof($notices);
        $this->assertGreaterThan($noticeCountBefore, $noticeCountAfter);
        $this->assertEquals(
            $dataHelperMock->__('The void request is sent. Please wait until the void request will be accepted.'),
            current($notices)->getText()
        );

    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testVoidWithStatusVoidAccepted()
    {
        $dataHelperMock = $this->getHelperMock('ops/data', array('redirect'));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);

        $paymentModel = $this->getModelMock(
            'ops/payment_abstract',
            array('canVoid')
        );


        $txMock = $this->getModelMock('sales/order_payment_transaction', array('save'));
        $this->replaceByMock('model', 'sales/order_payment_transaction', $txMock);

        $paymentModel->expects($this->any())
            ->method('canVoid')
            ->will($this->returnValue(true));
        $helperMock = $this->getHelperMock('ops/directlink', array('checkExistingTransact'));
        $helperMock
            ->expects($this->any())
            ->method('checkExistingTransact')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/directlink', $helperMock);

        $apiClientMock = $this->getModelMock(
            'ops/api_directlink', array('performRequest')
        );
        $apiClientMock->expects($this->any())
            ->method('performRequest')
            ->will(
                $this->returnValue(array(
                                        'STATUS' => Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED_ACCEPTED,
                                        'PAYID'  => '4711',
                                        'PAYIDSUB' => '0815'
                                   )));
        $this->replaceByMock('model', 'ops/api_directlink', $apiClientMock);
        $order = Mage::getModel('sales/order')->load(11);

        $paymentModel->void($order->getPayment());

        $this->assertEquals(
            Netresearch_OPS_Model_Payment_Abstract::OPS_VOIDED_ACCEPTED,
            $order->getPayment()->getAdditionalInformation('status')
        );

    }

    /**
     * @loadFixture ../../../../var/fixtures/orders.yaml
     */
    public function testGetOpsHtmlAnswer()
    {
        $this->markTestSkipped();

        $fakeQuote = new Varien_Object();
        $fakeQuote->setId(42);
        $sessionMock = $this->getModelMock('checkout/session', array('getQuote'));
        $sessionMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('model', 'checkout/session', $sessionMock);
        $this->assertEquals('HTML', Mage::getModel('ops/payment_abstract')->getOpsHtmlAnswer());

        $fakeQuote = new Varien_Object();
        $fakeQuote->setId(null);
        $sessionMock = $this->getModelMock('checkout/session', array('getQuote', 'getLastRealOrderId'));
        $sessionMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($fakeQuote));

        $sessionMock->expects($this->any())
            ->method('getLastRealOrderId')
            ->will($this->returnValue('100000020'));
        $this->replaceByMock('model', 'checkout/session', $sessionMock);
        $this->assertEquals('HTML', Mage::getModel('ops/payment_abstract')->getOpsHtmlAnswer());

        $order = Mage::getModel('sales/order')->load(20);
        $this->assertEquals('HTML', Mage::getModel('ops/payment_abstract')->getOpsHtmlAnswer($order->getPayment()));
    }

    protected function getOwnerParams()
    {
        return $ownerParams = array(
            'OWNERADDRESS',
            'OWNERTOWN',
            'OWNERZIP',
            'OWNERTELNO',
            'OWNERCTY',
            'ADDMATCH',
            'ECOM_BILLTO_POSTAL_POSTALCODE',
        );
    }

    protected function getShippingParams()
    {
        $paramValues = array(
            'ECOM_SHIPTO_POSTAL_NAME_FIRST',
            'ECOM_SHIPTO_POSTAL_NAME_LAST',
            'ECOM_SHIPTO_POSTAL_STREET_LINE1',
            'ECOM_SHIPTO_POSTAL_STREET_LINE2',
            'ECOM_SHIPTO_POSTAL_COUNTRYCODE',
            'ECOM_SHIPTO_POSTAL_CITY',
            'ECOM_SHIPTO_POSTAL_POSTALCODE'
        );

        return $paramValues;
    }


}
