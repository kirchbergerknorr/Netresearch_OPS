<?php

class Netresearch_OPS_Test_Helper_Payment_RequestTest extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @return Netresearch_OPS_Helper_Payment_RequestTest
     */
    protected function getRequestHelper()
    {
        return Mage::helper('ops/payment_request');
    }

    protected function getShipToArrayKeys()
    {
        return array(
            'ECOM_SHIPTO_POSTAL_NAME_FIRST',
            'ECOM_SHIPTO_POSTAL_NAME_LAST',
            'ECOM_SHIPTO_POSTAL_STREET_LINE1',
            'ECOM_SHIPTO_POSTAL_STREET_LINE2',
            'ECOM_SHIPTO_POSTAL_COUNTRYCODE',
            'ECOM_SHIPTO_POSTAL_CITY',
            'ECOM_SHIPTO_POSTAL_POSTALCODE',
            'ECOM_SHIPTO_POSTAL_STATE',
        );
    }

    public function testExtractShipToParameters()
    {
        $address = Mage::getModel('sales/quote_address');
        $helper = $this->getRequestHelper();
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->any())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $helper->setConfig($configMock);
        $params = $helper->extractShipToParameters($address, Mage::getModel('sales/quote'));
        $this->assertTrue(is_array($params));
        foreach ($this->getShipToArrayKeys() as $key) {
            $this->assertArrayHasKey($key, $params);
        }

        $address->setFirstname('Hans');
        $address->setLastname('Wurst');
        $address->setStreet('Nonnenstrasse 11d');
        $address->setCountry('DE');
        $address->setCity('Leipzig');
        $address->setPostcode('04229');
        $params = $this->getRequestHelper()->extractShipToParameters($address, Mage::getModel('sales/quote'));
        $this->assertEquals('Hans', $params['ECOM_SHIPTO_POSTAL_NAME_FIRST']);
        $this->assertEquals('Wurst', $params['ECOM_SHIPTO_POSTAL_NAME_LAST']);
        $this->assertEquals('Nonnenstrasse 11d', $params['ECOM_SHIPTO_POSTAL_STREET_LINE1']);
        $this->assertEquals('', $params['ECOM_SHIPTO_POSTAL_STREET_LINE2']);
        $this->assertEquals('DE', $params['ECOM_SHIPTO_POSTAL_COUNTRYCODE']);
        $this->assertEquals('Leipzig', $params['ECOM_SHIPTO_POSTAL_CITY']);
        $this->assertEquals('04229', $params['ECOM_SHIPTO_POSTAL_POSTALCODE']);
    }

    public function testGetIsoRegionCodeWithIsoRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('SN');
        $address->setCountry('DE');
        $this->assertEquals('SN', $this->getRequestHelper()->getIsoRegionCode($address));
    }

    public function testGetIsoRegionCodeWithIsoRegionCodeContainingTheCountryCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('ES-AB');
        $address->setCountry('ES');
        $this->assertEquals('AB', $this->getRequestHelper()->getIsoRegionCode($address));
    }


    public function testGetIsoRegionCodeWithGermanMageRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('SAS');
        $address->setCountry('DE');
        $this->assertEquals('SN', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('NDS');
        $this->assertEquals('NI', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('THE');
        $this->assertEquals('TH', $this->getRequestHelper()->getIsoRegionCode($address));
    }

    public function testGetIsoRegionCodeWithAustrianMageRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('WI');
        $address->setCountry('AT');
        $this->assertEquals('9', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('NO');
        $this->assertEquals('3', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('VB');
        $this->assertEquals('8', $this->getRequestHelper()->getIsoRegionCode($address));
    }

    public function testGetIsoRegionCodeWithSpanishMageRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('A Coruсa');
        $address->setCountry('ES');
        $this->assertEquals('C', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('Barcelona');
        $this->assertEquals('B', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('Madrid');
        $this->assertEquals('M', $this->getRequestHelper()->getIsoRegionCode($address));
    }

    public function testGetIsoRegionCodeWithFinnishMageRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('Lappi');
        $address->setCountry('FI');
        $this->assertEquals('10', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('Etelä-Savo');
        $this->assertEquals('04', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('Itä-Uusimaa');
        $this->assertEquals('19', $this->getRequestHelper()->getIsoRegionCode($address));
    }

    public function testGetIsoRegionCodeWithLatvianMageRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('Ādažu novads');
        $address->setCountry('LV');
        $this->assertEquals('LV', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('Engures novads');
        $this->assertEquals('029', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('Viļakas novads');
        $this->assertEquals('108', $this->getRequestHelper()->getIsoRegionCode($address));
    }

    public function testGetIsoRegionCodeWithUnknownRegionCode()
    {
        $address = Mage::getModel('customer/address');
        $address->setRegionCode('DEFG');
        $address->setCountry('AB');
        $this->assertEquals('AB', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('DEF');
        $this->assertEquals('DEF', $this->getRequestHelper()->getIsoRegionCode($address));
        $address->setRegionCode('DF');
        $this->assertEquals('DF', $this->getRequestHelper()->getIsoRegionCode($address));
    }


}