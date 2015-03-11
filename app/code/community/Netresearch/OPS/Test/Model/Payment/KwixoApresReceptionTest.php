<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of KwixoCreditTest
 *
 * @author Sebastian Ertner
 */
class Netresearch_OPS_Test_Model_Payment_KwixoApresReceptionTest extends EcomDev_PHPUnit_Test_Case_Config
{
    private $kwixoApresReceptionModel;
    
    private $store;


    public function setUp()
    {
        parent::setup();
        $this->kwixoApresReceptionModel = Mage::getModel('ops/payment_kwixoApresReception');
        $this->store = Mage::app()->getStore(0)->load(0);
    }

    public function testGetOpsCode()
    {
        $this->assertEquals('KWIXO_RNP',$this->kwixoApresReceptionModel->getOpsCode());
    }
    
    public function testGetCode()
    {
        $this->assertEquals('ops_kwixoApresReception',$this->kwixoApresReceptionModel->getCode());
    }

    public function testGetDeliveryDate()
    {
       $this->setUp();
       $dateNow = date("Y-m-d");
       $path = 'payment/ops_kwixoApresReception/delivery_date';
       $this->store->setConfig($path, "0");
       $this->assertEquals($dateNow,$this->kwixoApresReceptionModel->getEstimatedDeliveryDate('ops_kwixoApresReception'));
       $dateNowPlusFiveDays = strtotime($dateNow ."+ 5 days");
       $this->store->setConfig($path, "5");
       $this->assertEquals(date("Y-m-d",$dateNowPlusFiveDays),$this->kwixoApresReceptionModel->getEstimatedDeliveryDate('ops_kwixoApresReception'));
    }

    public function testGetFormBlockType()
    {
        $this->assertEquals('ops/form_kwixo_apresReception', $this->kwixoApresReceptionModel->getFormBlockType());
    }
}