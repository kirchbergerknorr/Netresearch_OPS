<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch
 * @package     ${MODULENAME}
 * @copyright   Copyright (c) 2013 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Test_Model_Validator_Payment_DirectDebitTest
    extends EcomDev_PHPUnit_Test_Case
{

    protected function getValidator()
    {
        return $validator          = Mage::getModel('ops/validator_payment_directDebit');
    }
    public function testIsValidWithInvalidDataReturnsFalse()
    {
        /** @var $validator Netresearch_OPS_Model_Validator_Payment_DirectDebit */
        $validator          = Mage::getModel('ops/validator_payment_directDebit');
        $directDebitData = array();
        $this->assertFalse($validator->isValid($directDebitData));
        $directDebitData = array('CN' => 'foo');
        $this->assertFalse($validator->isValid($directDebitData));
        $this->assertTrue(0 < $validator->getMessages());
        $directDebitData = array('CN' => 'foo', 'country' => 'de');
        $this->assertFalse($validator->isValid($directDebitData));
    }

    public function testIsValidWithBankAccountData()
    {
        /** @var $validator Netresearch_OPS_Model_Validator_Payment_DirectDebit */
        $validator          = Mage::getModel('ops/validator_payment_directDebit');
        $directDebitData = array(
            'CN'   => 'foo', 'country' => 'de', 'account' => '12345678',
            'iban' => ''
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN'   => 'foo', 'country' => 'de', 'account' => '12345678a',
            'iban' => ''
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN'   => '', 'country' => 'de', 'account' => '12345678a',
            'iban' => ''
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN'   => 'foo', 'country' => 'at', 'account' => '12345678',
            'iban' => ''
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN'   => 'foo', 'country' => 'nl', 'account' => '12345678',
            'iban' => ''
        );
        $this->assertTrue($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN'   => 'foo', 'country' => 'de', 'account' => '12345678',
            'iban' => '', 'bankcode' => '12345678'
        );
        $this->assertTrue($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN'   => 'foo', 'country' => 'at', 'account' => '12345678',
            'iban' => '', 'bankcode' => '12345678'
        );
        $this->assertTrue($validator->isValid($directDebitData));


    }

    public function testIsValidWithIbanData()
    {
        /** @var $validator Netresearch_OPS_Model_Validator_Payment_DirectDebit */
        $validator          = Mage::getModel('ops/validator_payment_directDebit');
        $directDebitData = array(
            'CN' => 'foo', 'country' => 'de', 'account' => '',
            'iban' => 'DE12345456677891234545667789'
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'at', 'account' => '',
            'iban' => 'AT12345456677891234545667789'
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'nl', 'account' => '',
            'iban' => 'NL12345456677891234545667789'
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'de', 'account' => '',
            'iban' => 'DE65160500003502221536'
        );
        $this->assertTrue($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'de', 'account' => '',
            'iban' => 'DE65 1605 0000 3502 2215 36'
        );
        $this->assertFalse($validator->isValid($directDebitData));
        $directDebitData = array(
            'CN' => 'foo', 'country' => 'de', 'account' => '',
            'iban' => 'DE89370400440532013000'
        );
        $this->assertTrue($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'nl', 'account' => '',
            'iban' => 'NL39RABO0300065264'
        );
        $this->assertTrue($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'nl', 'account' => '',
            'iban' => 'NL39RABO0300065264', 'bic' => 'RABONL2U'
        );
        $this->assertTrue($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'nl', 'account' => '',
            'iban' => 'NL39RABO0300065264', 'bic' => '012345678912'
        );
        $this->assertFalse($validator->isValid($directDebitData));

        $directDebitData = array(
            'CN' => 'foo', 'country' => 'nl', 'account' => '',
            'iban' => 'NL39RABO0300065264', 'bic' => '01234567891'
        );
        $this->assertTrue($validator->isValid($directDebitData));
    }

    public function testSetDataHelper()
    {
        /** @var $validator Netresearch_OPS_Model_Validator_Payment_DirectDebit */
        $validator          = Mage::getModel('ops/validator_payment_directDebit');
        $validator->setDataHelper(Mage::helper('core/data'));
        $this->assertEquals(
            get_class(Mage::helper('core/data')),
            get_class($validator->getDataHelper())
        );
    }
    public function testSetdirectDebitHelper()
    {
        /** @var $validator Netresearch_OPS_Model_Validator_Payment_DirectDebit */
        $validator          = Mage::getModel('ops/validator_payment_directDebit');
        $validator->setDirectDebitHelper(Mage::helper('ops/directDebit'));
        $this->assertEquals(
            get_class(Mage::helper('ops/directDebit')),
            get_class($validator->getDirectDebitHelper())
        );
    }

} 