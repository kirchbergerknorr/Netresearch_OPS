<?php

class Netresearch_OPS_Test_Block_Form_DirectDebitTest
    extends EcomDev_PHPUnit_Test_Case
{
    public function testTemplate()
    {
        //Frontend case
        $modelMock = $this->getModelMock(
            'ops/config', array('isFrontendEnvironment')
        );
        $modelMock->expects($this->any())
            ->method('isFrontendEnvironment')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $modelMock);
        $ccForm = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals(
            Netresearch_OPS_Block_Form::FRONTEND_TEMPLATE,
            $ccForm->getTemplate()
        );

        //Backend case
        $modelMock = $this->getModelMock(
            'ops/config', array('isFrontendEnvironment')
        );
        $modelMock->expects($this->any())
            ->method('isFrontendEnvironment')
            ->will($this->returnValue(false));
        $this->replaceByMock('model', 'ops/config', $modelMock);
        $ccForm = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals(
            Netresearch_OPS_Block_Form_DirectDebit::BACKEND_TEMPLATE,
            $ccForm->getTemplate()
        );
    }

    public function testDirectDebitCountryIds()
    {
        $fakeConfig = new Varien_Object();
        $fakeConfig->setDirectDebitCountryIds("AT, DE, NL");
        $blockMock = $this->getBlockMock(
            'ops/form_directDebit', array('getconfig')
        );
        $blockMock->expects($this->once())
            ->method('getConfig')
            ->will($this->returnValue($fakeConfig));
        $this->assertEquals(
            explode(',', 'AT, DE, NL'), $blockMock->getDirectDebitCountryIds()
        );
    }

    public function testGetParams()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block          = new Netresearch_OPS_Block_Form_DirectDebit();
        $expectedResult = array(
            'country'  => '',
            'CN'       => '',
            'iban'     => '',
            'bic'      => '',
            'account'  => '',
            'bankcode' => ''
        );
        $this->assertEquals($expectedResult, $block->getParams());
        $newParams = array('bla' => 'foo');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $result = $block->getParams();
        $this->assertFalse($sessionMock->hasData('ops_direct_debit_params'));
        $this->assertEquals($newParams, $result);

    }

    public function testIsIbanFieldRequired()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertTrue($block->isIbanFieldRequired());
        $newParams = array('iban' => 'foo');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isIbanFieldRequired());
        $newParams = array('account' => '');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isIbanFieldRequired());

        $newParams = array('account' => '123456');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertFalse($block->isIbanFieldRequired());
    }

    public function testIsAccountFieldRequired()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertTrue($block->isAccountFieldRequired());
        $newParams = array('account' => 'foo');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isAccountFieldRequired());
        $newParams = array('account' => '');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isAccountFieldRequired());

        $newParams = array('account' => '123456', 'iban' => '');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isAccountFieldRequired());

        $newParams = array('account' => '123456', 'iban' => '123456');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertFalse($block->isAccountFieldRequired());
    }


    public function testIsBankCodeFieldRequired()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertFalse($block->isBankCodeFieldRequired());

        $newParams = array('account' => '12345');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertFalse($block->isBankCodeFieldRequired());

        $newParams = array('account' => '123456', 'country' => 'de');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isBankCodeFieldRequired());
    }

    public function testIsBankCodeFieldVisible()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertFalse($block->isBankCodeFieldVisible());
        $newParams = array('country' => 'at');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isBankCodeFieldVisible());

        $newParams = array('country' => 'de');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isBankCodeFieldVisible());

        $newParams = array('country' => 'nl');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertFalse($block->isBankCodeFieldVisible());
    }

    public function testIsBicFieldVisible()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertFalse($block->isBicFieldVisible());

        $newParams = array('country' => 'de');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertFalse($block->isBicFieldVisible());

        $newParams = array('country' => 'at');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertFalse($block->isBicFieldVisible());

        $newParams = array('country' => 'nl');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertTrue($block->isBicFieldVisible());
    }

    public function testGetCountry()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals('', $block->getCountry());
        $newParams = array('country' => 'de');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertEquals('de', $block->getCountry());

    }

    public function testGetIban()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals('', $block->getIban());
        $newParams = array('iban' => '1234567');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertEquals('1234567', $block->getIban());

    }

    public function testGetBic()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals('', $block->getBic());
        $newParams = array('bic' => '1234567');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertEquals('1234567', $block->getBic());

    }

    public function testGetAccount()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals('', $block->getAccount());
        $newParams = array('account' => '1234567');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertEquals('1234567', $block->getAccount());

    }

    public function testGetBankcode()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals('', $block->getBankcode());
        $newParams = array('bankcode' => '1234567');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertEquals('1234567', $block->getBankcode());

    }

    public function testGetCardholderName()
    {
        $sessionMock = $this->getModelMock(
            'adminhtml/session', array('init', 'save')
        );
        $this->replaceByMock('model', 'adminhtml/session', $sessionMock);
        $block = new Netresearch_OPS_Block_Form_DirectDebit();
        $this->assertEquals('', $block->getCardholderName());
        $newParams = array('CN' => 'Hans wurst');
        $sessionMock->setData(
            'ops_direct_debit_params', $newParams
        );
        $block->getParams();
        $this->assertEquals('Hans wurst', $block->getCardholderName());

    }


}
