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
class Netresearch_OPS_Block_Placeform extends Mage_Core_Block_Template
{
    protected $hasMissingParams;
    protected $missingFormFields;
    protected $formFields;
    protected $question;

    public function __construct()
    {
        
    }

    public function getConfig()
    {
        return Mage::getModel('ops/config');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * OPS payment APi instance
     *
     * @return Netresearch_OPS_Model_Payment_Abstract
     */
    protected function _getApi()
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        if ($order && !is_null($order->getId())) {
            return $order->getPayment()->getMethodInstance();
        }
    }

    /**
     * Return order instance with loaded information by increment id
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrder()
    {
        if ($this->getOrder()) {
            $order = $this->getOrder();
        } else if ($this->getCheckout()->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        } else {
            return null;
        }
        return $order;
    }

    /**
     * check if payment method is q kwixo one
     * 
     * @return boolean
     */
    public function isKwixoPaymentMethod()
    {
        $isKwixoPayment = false;
        $methodInstance = $this->_getOrder()->getPayment()->getMethodInstance();
        if ($methodInstance instanceof Netresearch_OPS_Model_Payment_Kwixo_Abstract) {
            $isKwixoPayment= true;
        }
        return $isKwixoPayment;
    }
    /**
     * Get Form data by using ops payment api
     *
     * @return array
     */
    public function getFormData()
    {
        if (is_null($this->formFields) && $this->_getOrder() && !is_null($this->_getOrder()->getId())) {
            $this->formFields = $this->_getApi()->getFormFields($this->_getOrder(), $this->getRequest()->getParams());
        }
        return $this->formFields;
    }

    /**
     * Getting gateway url
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getRequest()->isPost() || is_null($this->getQuestion()) ? $this->getConfig()->getFrontendGatewayPath() :
            Mage::getUrl('*/*/*', array('_secure' => Mage::app()->getFrontController()->getRequest()->isSecure()));
    }

    public function hasMissingParams()
    {
        if (is_null($this->_getOrder())) {
            return null;
        }
        if (is_null($this->hasMissingParams)) {
            $this->hasMissingParams = $this->_getApi()->hasFormMissingParams($this->_getOrder(), $this->getRequest()->getParams(), $this->getFormData());
        }
        return $this->hasMissingParams;
    }

    public function getQuestion()
    {
        if (is_null($this->question) && $this->_getOrder() && !is_null($this->_getOrder()->getId())) {
            $this->question = $this->_getApi()->getQuestion($this->_getOrder(), $this->getRequest()->getParams());
        }
        return $this->question;
    }

    public function getQuestionedFormFields()
    {
        if (is_null($this->missingFormFields) && $this->_getOrder() && !is_null($this->_getOrder()->getId())) {
            $this->missingFormFields = $this->_getApi()->getQuestionedFormFields($this->_getOrder(), $this->getRequest()->getParams());
        }
        return $this->missingFormFields;
    }
    
    
}
