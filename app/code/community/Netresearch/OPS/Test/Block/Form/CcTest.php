<?php

class Netresearch_OPS_Test_Block_Form_CcTest extends EcomDev_PHPUnit_Test_Case
{

    public function testGetAliasBrands()
    {

        $aliasBrands = array(
            'American Express',
            'Diners Club',
            'MaestroUK',
            'MasterCard',
            'VISA',
        );

        $ccAliasInterfaceEnabledTypesMock = $this->getModelMock('ops/source_cc_aliasInterfaceEnabledTypes', array('getAliasInterfaceCompatibleTypes'));
        $ccAliasInterfaceEnabledTypesMock->expects($this->any())
            ->method('getAliasInterfaceCompatibleTypes')
            ->will($this->returnValue($aliasBrands));
        $this->replaceByMock('model', 'ops/source_cc_aliasInterfaceEnabledTypes', $ccAliasInterfaceEnabledTypesMock);
        $ccForm = Mage::app()->getLayout()->getBlockSingleton('ops/form_cc');
        $ccAliases = $ccForm->getAliasBrands();
        $this->assertEquals($aliasBrands, $ccAliases);
    }


    public function testTemplate()
    {
        //Frontend case
        $modelMock = $this->getModelMock('ops/config', array('isFrontendEnvironment'));
        $modelMock->expects($this->any())
            ->method('isFrontendEnvironment')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $modelMock);
        $ccForm = new Netresearch_OPS_Block_Form_Cc();
        $this->assertEquals(Netresearch_OPS_Block_Form::FRONTEND_TEMPLATE, $ccForm->getTemplate());

        //Backend case
        $modelMock = $this->getModelMock('ops/config', array('isFrontendEnvironment'));
        $modelMock->expects($this->any())
            ->method('isFrontendEnvironment')
            ->will($this->returnValue(false));
        $this->replaceByMock('model', 'ops/config', $modelMock);
        $ccForm = new Netresearch_OPS_Block_Form_Cc();
        $this->assertEquals(Netresearch_OPS_Block_Form_Cc::BACKEND_TEMPLATE, $ccForm->getTemplate());
    }
}
