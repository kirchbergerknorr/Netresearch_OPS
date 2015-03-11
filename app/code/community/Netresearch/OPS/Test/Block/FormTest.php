<?php
class Netresearch_OPS_Test_Block_FormTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{
    private $_block;

    public function setUp()
    {
        parent::setup();
        $this->_block = Mage::app()->getLayout()->getBlockSingleton('ops/form');
    }

    public function testGetCcBrands()
    {
        $this->assertInternalType('array', $this->_block->getCcBrands());
    }

    public function testGetDirectDebitCountryIds()
    {
        $this->assertInternalType(
            'array', $this->_block->getDirectDebitCountryIds()
        );
    }

    public function testIsAliasPMEnabled()
    {
        $model = Mage::getModel('ops/config');
        $this->assertEquals(
            $model->isAliasManagerEnabled(), $this->_block->isAliasPMEnabled()
        );
    }

    public function testGetStoredAliasDataForCustomer()
    {

        $reflectionClass = new ReflectionClass(get_class($this->_block));
        $method = $reflectionClass->getMethod("getStoredAliasDataForCustomer");
        $method->setAccessible(true);
        $this->assertNull($method->invoke($this->_block, 'bla'));

        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasForCustomer')
            ->will($this->returnValue(array()));
        $reflectionClass = new ReflectionClass(get_class($blockMock));
        $method = $reflectionClass->getMethod("getStoredAliasDataForCustomer");
        $method->setAccessible(true);
        $this->assertNull($method->invoke($blockMock, 'bla'));


        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasForCustomer')
            ->will($this->returnValue(array('bla' => 'foo')));
        $reflectionClass = new ReflectionClass(get_class($blockMock));
        $method = $reflectionClass->getMethod("getStoredAliasDataForCustomer");
        $method->setAccessible(true);
        $this->assertEquals(
            'foo', $method->invoke($blockMock, 'bla')
        );

        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasForCustomer')
            ->will($this->returnValue(array('bla' => 'foo')));
        $reflectionClass = new ReflectionClass(get_class($blockMock));
        $method = $reflectionClass->getMethod("getStoredAliasDataForCustomer");
        $method->setAccessible(true);
        $this->assertNull($method->invoke($blockMock, 'foo'));

    }


    public function testGetAliasCardNumber()
    {
        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->any())
            ->method('getStoredAliasForCustomer')
            ->will(
                $this->returnValue(
                    array(
                         'pseudo_account_or_cc_no' => 'xxxxxxxxxxxx1111',
                         'brand' => 'visa'
                    )
                )
        );
        $this->assertEquals('XXXX XXXX XXXX 1111', $blockMock->getAliasCardNumber());
    }

    /**
     * @loadFixture ../../../var/fixtures/aliases.yaml
     */
    public function testGetStoredAliasForCustomer()
    {

        $reflectionClass = new ReflectionClass(get_class($this->_block));
        $method = $reflectionClass->getMethod("getStoredAliasForCustomer");
        $method->setAccessible(true);
        $this->assertNull($method->invoke($this->_block));


        $configMock = $this->getModelMock('ops/config', array('isAliasManagerEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasManagerEnabled')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $aliases = Mage::getModel('ops/alias')
            ->getCollection()
            ->addFieldToFilter('customer_id', 1)
            ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::ACTIVE)
            ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
        ;
        $alias = $aliases->getFirstItem();
        $aliasHelperMock = $this->getHelperMock('ops/alias', array('getAliasesForAddresses'));
        $aliasHelperMock->expects($this->once())
            ->method('getAliasesForAddresses')
            ->will($this->returnValue($aliases));
        $this->replaceByMock('helper', 'ops/alias', $aliasHelperMock);

        $customerMock = $this->getHelperMock('customer/data', array('isLoggedIn'));
        $customerMock->expects($this->once())
            ->method('isLoggedIn')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'customer/data', $customerMock);

        $fakeCustomer = new Varien_Object();
        $fakeCustomer->setId(1);

        $fakeQuote = new Varien_Object();
        $fakeQuote->setCustomer($fakeCustomer);



        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->once())
            ->method('getQuote')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('block', 'ops/form', $blockMock);

        $reflectionClass = new ReflectionClass(get_class($blockMock));
        $method = $reflectionClass->getMethod("getStoredAliasForCustomer");
        $method->setAccessible(true);

        $this->assertEquals($alias->getData(), $method->invoke($blockMock));

    }


    public function testGetExpirationDatePart()
    {
        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->any())
            ->method('getStoredAliasForCustomer')
            ->will($this->returnValue(array('expiration_date' => '0416')));
        $this->assertEquals('04', $blockMock->getExpirationDatePart('month'));
        $this->assertEquals('16', $blockMock->getExpirationDatePart('year'));
    }

    public function testGetCardHolderName()
    {
        $configMock = $this->getModelMock('ops/config', array('isAliasManagerEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasManagerEnabled')
            ->will($this->returnValue(false));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $this->assertNull($block = $this->_block->getCardHolderName());

        $configMock = $this->getModelMock('ops/config', array('isAliasManagerEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasManagerEnabled')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $configMock);


        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasDataForCustomer', 'getStoredAlias')
        );
        $blockMock->expects($this->any())
            ->method('getStoredAliasDataForCustomer')
            ->will($this->returnValue('Hubertus von Fürstenberg'));

        $blockMock->expects($this->any())
            ->method('getStoredAlias')
            ->will($this->returnValue('4711'));
        $this->assertEquals('Hubertus von Fürstenberg', $blockMock->getCardHolderName());

        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasDataForCustomer', 'getStoredAlias')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasDataForCustomer')
            ->will($this->returnValue(null));

        $blockMock->expects($this->any())
            ->method('getStoredAlias')
            ->will($this->returnValue('4711'));

        $customerHelperMock = $this->getHelperMock('customer/data', array('isLoggedIn', 'getCustomerName'));
        $customerHelperMock->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(true));
        $customerHelperMock->expects($this->any())
            ->method('getCustomerName')
            ->will($this->returnValue('Hubertus zu Fürstenberg'));
        $this->replaceByMock('helper', 'customer/data', $customerHelperMock);

        $this->assertEquals('Hubertus zu Fürstenberg', $blockMock->getCardHolderName());

        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasDataForCustomer', 'getStoredAlias')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasDataForCustomer')
            ->will($this->returnValue(''));

        $blockMock->expects($this->any())
            ->method('getStoredAlias')
            ->will($this->returnValue('4711'));

        $this->assertEquals('Hubertus zu Fürstenberg', $blockMock->getCardHolderName());

        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasDataForCustomer', 'getStoredAlias')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasDataForCustomer')
            ->will($this->returnValue(null));

        $blockMock->expects($this->any())
            ->method('getStoredAlias')
            ->will($this->returnValue('4711'));

        $customerHelperMock = $this->getHelperMock('customer/data', array('isLoggedIn'));
        $customerHelperMock->expects($this->any())
            ->method('isLoggedIn')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'customer/data', $customerHelperMock);

        $this->assertNull($blockMock->getCardHolderName());


    }

    public function testGetStoredAliasBrandWithInlineBrand()
    {
        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->any())
            ->method('getStoredAliasForCustomer')
            ->will(
                $this->returnValue(
                    array(
                         'pseudo_account_or_cc_no' => 'xxxxxxxxxxxx1111',
                         'brand' => 'VISA'
                    )
                )
        );
        
        $modelMock = $this->getModelMock(
            'ops/config', array('getInlinePaymentCcTypes')
        );
        $modelMock->expects($this->any())
            ->method('getInlinePaymentCcTypes')
            ->will(
                $this->returnValue(
                    array(
                         'VISA'
                    )
                )
        );

        $this->assertEquals('VISA', $blockMock->getStoredAliasBrand());
    }
    
    
    public function testGetStoredAliasBrandWithNonInlineBrand()
    {
        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->any())
            ->method('getStoredAliasForCustomer')
            ->will(
                $this->returnValue(
                    array(
                         'pseudo_account_or_cc_no' => 'xxxxxxxxxxxx1111',
                         'brand' => 'VISA'
                    )
                )
        );
        
        $modelMock = $this->getModelMock(
            'ops/config', array('getInlinePaymentCcTypes')
        );
        $modelMock->expects($this->any())
            ->method('getInlinePaymentCcTypes')
            ->will(
                $this->returnValue(
                    array(
                         'FOO'
                    )
                )
        );
        
        $this->replaceByMock('model', 'ops/config', $modelMock);

        $this->assertEquals('', $blockMock->getStoredAliasBrand());
    }

    public function testGetStoredAlias()
    {
        $blockMock = $this->getBlockMock(
            'ops/form', array('getStoredAliasForCustomer')
        );
        $blockMock->expects($this->once())
            ->method('getStoredAliasForCustomer')
            ->will(
                $this->returnValue(
                    array(
                         'alias' => '4711',
                         'brand' => 'VISA'
                    )
                )
        );


        $this->assertEquals('4711', $blockMock->getStoredAlias());
    }
    
    public function testIsUserRegistering()
    {
        $dataHelperMock = $this->getHelperMock('ops/data', array('checkIfUserIsRegistering'));
        $dataHelperMock->expects($this->any())
            ->method('checkIfUserIsRegistering')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);
        
        $block = new Netresearch_OPS_Block_Form();
        $this->assertFalse($block->isUserRegistering());
    }
    
    
    public function testIsUserNotRegistering()
    {
        $dataHelperMock = $this->getHelperMock('ops/data', array('checkIfUserIsNotRegistering'));
        $dataHelperMock->expects($this->any())
            ->method('checkIfUserIsNotRegistering')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);
        
        $block = new Netresearch_OPS_Block_Form();
        $this->assertFalse($block->isUserNotRegistering());
    }

    public function testIsAliasInfoBlockEnabled()
    {
        $configMock = $this->getModelMock('ops/config', array('isAliasPMEnabled', 'isAliasInfoBlockEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasPMEnabled')
            ->will($this->returnValue(false));
        $configMock->expects($this->any())
            ->method('isAliasInfoBlockEnabled')
            ->will($this->returnValue(false));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $this->assertFalse(Mage::app()->getLayout()->getBlockSingleton('ops/form')->isAliasInfoBlockEnabled());

        $configMock = $this->getModelMock('ops/config', array('isAliasPMEnabled', 'isAliasInfoBlockEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasPMEnabled')
            ->will($this->returnValue(false));
        $configMock->expects($this->any())
            ->method('isAliasInfoBlockEnabled')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $this->assertFalse(Mage::app()->getLayout()->getBlockSingleton('ops/form')->isAliasInfoBlockEnabled());

        $configMock = $this->getModelMock('ops/config', array('isAliasPMEnabled', 'isAliasInfoBlockEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasPMEnabled')
            ->will($this->returnValue(true));
        $configMock->expects($this->any())
            ->method('isAliasInfoBlockEnabled')
            ->will($this->returnValue(false));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $this->assertFalse(Mage::app()->getLayout()->getBlockSingleton('ops/form')->isAliasInfoBlockEnabled());

        $configMock = $this->getModelMock('ops/config', array('isAliasPMEnabled', 'isAliasInfoBlockEnabled'));
        $configMock->expects($this->any())
            ->method('isAliasPMEnabled')
            ->will($this->returnValue(true));
        $configMock->expects($this->any())
            ->method('isAliasInfoBlockEnabled')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $configMock);

        $this->assertFalse(Mage::app()->getLayout()->getBlockSingleton('ops/form')->isAliasInfoBlockEnabled());
    }

    public function testGetPmLogo()
    {
        $this->assertEquals(null, $this->_block->getPmLogo());
    }

    public function testGetFrontendValidatorsAreEmtpyWhenNoExtraParamtersAreSubmitted()
    {
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(false));
        $this->_block->setConfig($configMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array()), $this->_block->getFrontendValidators());
    }

    public function testGetFrontendValidatorsAreEmptyDueToEmptyValidators()
    {
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter', 'getParameterLengths'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $configMock->expects($this->once())
            ->method('getParameterLengths')
            ->will($this->returnValue(array()));

        $quote = Mage::getModel('sales/quote');
        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $blockMock->setConfig($configMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array()), $blockMock->getFrontendValidators());
    }

    public function testGetFrontendValidatorsAreEmptyDueToUnmappedValidators()
    {
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter', 'getParameterLengths'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $configMock->expects($this->once())
            ->method('getParameterLengths')
            ->will($this->returnValue(array('Foo' => 50)));

        $quote = Mage::getModel('sales/quote');
        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $blockMock->setConfig($configMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array()), $blockMock->getFrontendValidators());
    }


    public function testGetFrontendValidatorsAreNotEmpty()
    {

        $configValues = array('CN' => 50, 'ECOM_BILLTO_POSTAL_POSTALCODE' => 10, 'ECOM_SHIPTO_POSTAL_POSTALCODE' => 10);

        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter', 'getParameterLengths'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $configMock->expects($this->once())
            ->method('getParameterLengths')
            ->will($this->returnValue($configValues));

        $quote = Mage::getModel('sales/quote');
        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $blockMock->setConfig($configMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array(
                    'billing:firstname' => 50, 'billing:lastname' => 50, 'billing:postcode' => 10, 'shipping:postcode' => 10
                )), $blockMock->getFrontendValidators());
    }
}
