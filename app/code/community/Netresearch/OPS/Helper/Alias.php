<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Helper_Alias extends Mage_Core_Helper_Abstract
{

    public function getAdminSession()
    {
        return Mage::getSingleton('admin/session');
    }

    public function isAdminSession()
    {
        if ($this->getAdminSession()->getUser()) {
            return 0 < $this->getAdminSession()->getUser()->getUserId();
        }
        return false;
    }
    
    /**
     * PM value is not used for payments with Alias Manager
     *
     * @param Mage_Sales_Model_Quote_Payment|null Payment
     *
     * @return null
     */
    public function getOpsCode($payment = null)
    {
        return $payment;
    }

    /**
     * BRAND value is not used for payments with Alias Manager
     *
     * @param Mage_Sales_Model_Quote_Payment|null Payment
     *
     * @return null
     */
    public function getOpsBrand($payment = null)
    {
        return $payment;
    }

    /**
     * get alias or generate a new one
     *
     * alias has length 16 and consists of quote creation date, a separator,
     * and the quote id to make sure we have the full quote id we shorten
     * the creation date accordingly
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return string
     */
    public function getAlias($quote)
    {
        
        $alias = $quote->getPayment()->getAdditionalInformation('alias');
        if (0 == strlen($alias)) {
            /* turn createdAt into format MMDDHHii */
            $createdAt = substr(
                str_replace(array(':', '-', ' '), '', $quote->getCreatedAt()),
                4,
                -2
            );
            $quoteId = $quote->getId();
            /* shorten createdAt, if we would exceed maximum length */
            $maxAliasLength = 16;
            $separator = '99';
            $maxCreatedAtLength
                = $maxAliasLength - strlen($quoteId) - strlen($separator);
            $alias = substr($createdAt, 0, $maxCreatedAtLength) . $separator
                . $quoteId;
        }
        
        if ($this->isAdminSession() && !strpos($alias,'BE')) {
            $alias = $alias.'BE';
        }
        return $alias;
    }

    /**
     * saves the alias if customer is logged in (and want to create an alias)
     *
     * @param                        $aliasData
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return Netresearch_OPS_Model_Alias | null
     */
    public function saveAlias($aliasData)
    {
        $quote = null;
        $aliasModel = null;
        Mage::helper('ops')->log('aliasData ' . Zend_Json::encode(Mage::helper('ops/data')->clearMsg($aliasData)));
        if (array_key_exists('OrderID', $aliasData) && is_numeric($aliasData['OrderID'])) {
            $quote = Mage::getModel('sales/quote')->load($aliasData['OrderID']);
        }

        $aliasModel = null;
        if ($quote instanceof Mage_Sales_Model_Quote
            && $quote->getPayment()
            && (1 == $quote->getPayment()->getAdditionalInformation('saveOpsAlias'))
        ) {
            $customerId = $quote->getCustomer()->getId();
            $billingAddressHash = $this->generateAddressHash(
                $quote->getBillingAddress()
            );
            $shippingAddressHash = $this->generateAddressHash(
                $quote->getShippingAddress()
            );

            // first: check if alias exists
            $oldAlias = Mage::getModel('ops/alias')->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('billing_address_hash', $billingAddressHash)
                ->addFieldToFilter('shipping_address_hash', $shippingAddressHash)
                ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::ACTIVE)
                ->addFieldToFilter('store_id', array(
                    array('attribute' => 'store_id', 'eq' => $quote->getStoreId()),
                    array('attribute' => 'store_id', 'null' => true)
                ))
                ->getFirstItem();
            // and if so update this alias with alias data from alias gateway
            if (is_numeric($oldAlias->getAlias())) {
                $oldAlias->setCardHolder($aliasData['CN']);
                $oldAlias->setBrand($aliasData['Brand']);
                $oldAlias->setExpirationDate($aliasData['ED']);
                $oldAlias->setPseudoAccountOrCCNo($aliasData['CardNo']);
                $oldAlias->setStoreId($quote->getStoreId());
                $oldAlias->save();
                $aliasModel = $oldAlias;
            } else {
                // alias does not exist -> create a new one if requested
                if (!is_null($quote) && $quote->getPayment()
                    && $quote->getPayment()->getAdditionalInformation('saveOpsAlias')
                ) {
                    if (!is_null($quote->getCustomer()->getId())) {
                        $this->deleteAlias($quote, $aliasData);
                    }
                    // create new alias
                    $aliasModel = $this->saveNewAlias($quote, $aliasData);
                    $quote->getPayment()->setAdditionalInformation(
                        'opsAliasId', $aliasModel->getId()
                    );
                    $quote->getPayment()->save();
                }
            }
        }
        return $aliasModel;
    }

    /**
     *
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function deleteAlias(Mage_Sales_Model_Quote $quote)
    {
        $customerId = $quote->getCustomer()->getId();
        $billingAddressHash = $this->generateAddressHash(
            $quote->getBillingAddress()
        );
        $shippingAddressHash = $this->generateAddressHash(
            $quote->getShippingAddress()
        );
        $aliasModel = Mage::getModel('ops/alias');
        $aliasCollection = $aliasModel->getCollection()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('billing_address_hash', $billingAddressHash)
            ->addFieldToFilter('shipping_address_hash', $shippingAddressHash)
            ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::PENDING)
            ->addFieldToFilter('store_id', array(array('eq' => $quote->getStoreId()), array('null' => true)))
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);
        $aliasCollection->load();
        foreach ($aliasCollection as $alias) {
            $alias->delete();
        }
    }

    protected function saveNewAlias(Mage_Sales_Model_Quote $quote, $aliasData)
    {
        $customerId = $quote->getCustomer()->getId();
        $billingAddressHash = $this->generateAddressHash(
            $quote->getBillingAddress()
        );
        $shippingAddressHash = $this->generateAddressHash(
            $quote->getShippingAddress()
        );

        $aliasModel = Mage::getModel('ops/alias');
        $aliasModel->setCustomerId($customerId);
        $aliasModel->setAlias($aliasData['Alias']);
        $aliasModel->setExpirationDate($aliasData['ED']);
        $aliasModel->setBillingAddressHash($billingAddressHash);
        $aliasModel->setShippingAddressHash($shippingAddressHash);
        $aliasModel->setBrand($aliasData['Brand']);
        $aliasModel->setPaymentMethod($quote->getPayment()->getMethod());
        $aliasModel->setPseudoAccountOrCCNo($aliasData['CardNo']);
        $aliasModel->setState(Netresearch_OPS_Model_Alias_State::PENDING);
        $aliasModel->setStoreId($quote->getStoreId());
        if (array_key_exists('CN', $aliasData)) {
            $aliasModel->setCardHolder($aliasData['CN']);
        }
        Mage::helper('ops')->log(
            'saving alias' . Zend_Json::encode($aliasModel->getData())
        );
        $aliasModel->save();

        return $aliasModel;
    }

    /**
     * generates hash from address data
     *
     * @param Mage_Sales_Model_Quote_Address $address the address data to hash
     *
     * @returns sha1 hash of address
     */
    public function generateAddressHash(
    Mage_Customer_Model_Address_Abstract $address
    )
    {
        $addressString = $address->getFirstname();
        $addressString .= $address->getMiddlename();
        $addressString .= $address->getLastname();
        $addressString .= $address->getCompany();
        $street = $address->getStreetFull();
        if (is_array($street)) {
            $street = implode('', $street);
        }
        $addressString .= $street;
        $addressString .= $address->getPostcode();
        $addressString .= $address->getCity();
        $addressString .= $address->getCountryId();

        return sha1($addressString);
    }

    /**
     * retrieves the aliases for a given customer
     *
     * @param int $customerId
     * @param     Mage_Sales_Model_Quote
     *
     * @return Netresearch_OPS_Model_Mysql4_Alias_Collection - collection
     *  of aliases for the given customer
     */
    public function getAliasesForCustomer(
    $customerId, Mage_Sales_Model_Quote $quote = null
    )
    {
        $billingAddressHash = null;
        $shippingAddressHash = null;
        $storeId = null;
        if (!is_null($quote)) {
            $billingAddressHash = $this->generateAddressHash(
                $quote->getBillingAddress()
            );
            $shippingAddressHash = $this->generateAddressHash(
                $quote->getShippingAddress()
            );
            $storeId = $quote->getStoreId();
        }
        return Mage::getModel('ops/alias')
                ->getAliasesForCustomer(
                    $customerId, $billingAddressHash, $shippingAddressHash, $storeId
        );
    }

    /**
     * if alias is valid for address
     *
     * @param int                            $customerId
     * @param string                         $alias
     * @param Mage_Sales_Model_Quote_Address $billingAddress
     * @param Mage_Sales_Model_Quote_Address $shippingAddress
     *
     * @return boolean
     */
    public function isAliasValidForAddresses(
    $customerId, $alias, $billingAddress, $shippingAddress, $storeId = null
    )
    {
        $aliasCollection = $this->getAliasesForAddresses(
                $customerId, $billingAddress, $shippingAddress, $storeId
            )
            ->addFieldToFilter('alias', $alias)
            ->setPageSize(1);
        return (1 == $aliasCollection->count());
    }

    /**
     * get aliases that are allowed for customer with given addresses
     *
     * @param int                            $customerId      Id of customer
     * @param Mage_Sales_Model_Quote_Address $billingAddress  billing address
     * @param Mage_Sales_Model_Quote_Address $shippingAddress shipping address
     *
     * @return Netresearch_OPS_Model_Mysql4_Alias_Collection
     */
    public function getAliasesForAddresses(
    $customerId, $billingAddress, $shippingAddress, $storeId = null
    )
    {
        $billingAddressHash = $this->generateAddressHash($billingAddress);
        $shippingAddressHash = $this->generateAddressHash($shippingAddress);
        return Mage::getModel('ops/alias')->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('billing_address_hash', $billingAddressHash)
                ->addFieldToFilter('shipping_address_hash', $shippingAddressHash)
                ->addFieldToFilter('store_id', array(array('eq' => $storeId), array('null' => true)));
    }

    /**
     * formats the pseudo cc number in a brand specific format
     * supported brand (so far):
     *      - MasterCard
     *      - Visa
     *      - American Express
     *      - Diners Club
     *
     * @param $brand - the cc brand we need to format the pseudo cc number
     * @param $aliasCcNo - the pseudo cc number itself
     *
     * @return string - the formatted pseudo cc number
     */
    public function formatAliasCardNo($brand, $aliasCcNo)
    {

        if (in_array(strtolower($brand), array('visa', 'mastercard'))) {
            $aliasCcNo = implode(' ', str_split($aliasCcNo, 4));
        }
        if (in_array(strtolower($brand), array('american express', 'diners club', 'maestrouk'))) {
            $aliasCcNo = str_replace('-', ' ', $aliasCcNo);
        }

        return strtoupper($aliasCcNo);
    }

    /**
     * saves the alias and if given the cvc to the payment information
     *
     * @param Mage_Payment_Model_Info $payment - the payment which should be updated
     * @param array                   $aliasData - the data we will update
     * @param boolean                 $userIsRegistering - is registering method in checkout
     * @param boolean                 $paymentSave - is it necessary to save the payment afterwards
     */
    public function setAliasToPayment(Mage_Payment_Model_Info $payment, array $aliasData, $userIsRegistering = false, $paymentSave = false)
    {
        if (array_key_exists('alias', $aliasData) && 0 < strlen(trim($aliasData['alias']))) {
            $payment->setAdditionalInformation('alias', trim($aliasData['alias']));
            $payment->setAdditionalInformation('userIsRegistering', $userIsRegistering);
            if (array_key_exists('CVC', $aliasData)) {
                $payment->setAdditionalInformation('cvc', $aliasData['CVC']);
                $this->setCardHolderToAlias($payment->getQuote(), $aliasData);
            }
            $payment->setDataChanges(true);
            if($paymentSave === true){
                $payment->save();
            }
        } else {
            Mage::helper('ops/data')->log('did not save alias due to empty alias');
            Mage::helper('ops/data')->log($aliasData);
        }
    }

    protected function setCardHolderToAlias($quote, $aliasData)
    {
        $customerId = $quote->getCustomerId();
        $billingAddressHash = $this->generateAddressHash($quote->getBillingAddress());
        $shippingAddressHash = $this->generateAddressHash($quote->getShippingAddress());
        $oldAlias = Mage::getModel('ops/alias')->getCollection()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('billing_address_hash', $billingAddressHash)
            ->addFieldToFilter('shipping_address_hash', $shippingAddressHash)
            ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::ACTIVE)
            ->addFieldToFilter('store_id', array(array('eq' => $quote->getStoreId()), array('null' => true)))
            ->getFirstItem();
        // and if so update this alias with alias data from alias gateway
        if (is_numeric($oldAlias->getId()) && is_null($oldAlias->getCardHolder()) && array_key_exists('CN', $aliasData)
        ) {
            $oldAlias->setCardHolder($aliasData['CN']);
            $oldAlias->save();
        }
    }

    /**
     * set the last pending alias to active and remove other aliases for customer based on address
     * 
     * @param Mage_Sales_Model_Quote $quote
     */
    public function setAliasActive(
        Mage_Sales_Model_Quote $quote,
        Mage_Sales_Model_Order $order = null,
        $saveSalesObjects = false
    ) {
        if (is_null($quote->getPayment()->getAdditionalInformation('userIsRegistering'))
            || false === $quote->getPayment()->getAdditionalInformation('userIsRegistering')
        ) {
            $aliasesToDelete = Mage::helper('ops/alias')->getAliasesForAddresses(
                    $quote->getCustomer()->getId(), $quote->getBillingAddress(), $quote->getShippingAddress()
                )
                ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::ACTIVE);
            $lastPendingAlias = Mage::helper('ops/alias')->getAliasesForAddresses(
                    $quote->getCustomer()->getId(),
                    $quote->getBillingAddress(),
                    $quote->getShippingAddress(),
                    $quote->getStoreId()
                )
                ->addFieldToFilter('alias', $quote->getPayment()->getAdditionalInformation('alias'))
                ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::PENDING)
                ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
                ->getFirstItem();
            if (0 < $lastPendingAlias->getId()) {
                foreach ($aliasesToDelete as $alias) {
                    $alias->delete();
                }
                $lastPendingAlias->setState(Netresearch_OPS_Model_Alias_State::ACTIVE);
                $lastPendingAlias->save();
            }
        } else {
            $this->setAliasToActiveAfterUserRegisters($order, $quote);
        }
        $this->cleanUpAdditionalInformation($order->getPayment(), false, $saveSalesObjects);
        $this->cleanUpAdditionalInformation($quote->getPayment(), false, $saveSalesObjects);
    }

    public function setAliasToActiveAfterUserRegisters(
    Mage_Sales_Model_Order $order, Mage_Sales_Model_Quote $quote
    )
    {
        if (true === $quote->getPayment()->getAdditionalInformation('userIsRegistering')
        ) {
            $customerId = $order->getCustomerId();
            $billingAddressHash = $this->generateAddressHash(
                $quote->getBillingAddress()
            );
            $shippingAddressHash = $this->generateAddressHash(
                $quote->getShippingAddress()
            );
            $aliasId = $quote->getPayment()->getAdditionalInformation(
                'opsAliasId'
            );
            if (is_numeric($aliasId) && 0 < $aliasId) {
                $alias = Mage::getModel('ops/alias')->getCollection()
                    ->addFieldToFilter(
                        'alias', $quote->getPayment()->getAdditionalInformation('alias')
                    )
                    ->addFieldToFilter(
                        'billing_address_hash', $billingAddressHash
                    )
                    ->addFieldToFilter(
                        'shipping_address_hash', $shippingAddressHash
                    )
                    ->addFieldToFilter('store_id', array('eq' => $quote->getStoreId()))
                    ->getFirstItem();
                if ($alias->getState() === Netresearch_OPS_Model_Alias_State::PENDING
                ) {
                    $alias->setState(Netresearch_OPS_Model_Alias_State::ACTIVE);
                    $alias->setCustomerId($customerId);
                    $alias->save();
                }
            }
        }
    }

    /**
     * cleans up the stored cvc and storedOPSId
     *
     * @param Mage_Sales_Model_Quote_Payment || Mage_Sales_Model_Order_Payment $payment
     * @param bool $cvcOnly
     * @param bool $savePayment
     *
     */
    public function cleanUpAdditionalInformation($payment, $cvcOnly = false, $savePayment = false)
    {
        if (is_array($payment->getAdditionalInformation())
            && array_key_exists('cvc', $payment->getAdditionalInformation())
        ) {
            $payment->unsAdditionalInformation('cvc');
        }

        if ($cvcOnly === false && is_array($payment->getAdditionalInformation())
            && array_key_exists('storedOPSId', $payment->getAdditionalInformation())
        ) {
            $payment->unsAdditionalInformation('storedOPSId');
        }

        /* OGNH-7: seems not to needed anymore since payment and quote is saved after this call,
         otherwise admin payments will fail */
        if ($savePayment) {
            $payment->save();
        }
    }
}
