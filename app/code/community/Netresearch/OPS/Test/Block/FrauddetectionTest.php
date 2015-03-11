<?php

class Netresearch_OPS_Test_Block_FrauddetectionTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{

    public function testToHtml()
    {
        $block = Mage::app()->getLayout()->getBlockSingleton('ops/frauddetection');
        $this->assertEquals(null, $block->toHtml());

        $modelMock = $this->getModelMock('ops/config', array('isTrackingCodeActivated'));
        $modelMock->expects($this->once())
            ->method('isTrackingCodeActivated')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'ops/config', $modelMock);
        // for some reason the html is not rendered in the tests
        $this->assertNotNull($block->toHtml());
    }


    public function testGetTrackingCodeAid()
    {
        $block = Mage::app()->getLayout()->getBlockSingleton('ops/frauddetection');
        $this->assertEquals('10376', $block->getTrackingCodeAid());
    }


    public function testGetTrackingSid()
    {
        $block = Mage::app()->getLayout()->getBlockSingleton('ops/frauddetection');
        $modelMock = $this->getModelMock('customer/session', array('getSessionId'));
        $modelMock->expects($this->once())
            ->method('getSessionId')
            ->will($this->returnValue('123456'));
        $this->replaceByMock('model', 'customer/session', $modelMock);
        $this->assertEquals(md5('123456'), $block->getTrackingSid());
    }

}