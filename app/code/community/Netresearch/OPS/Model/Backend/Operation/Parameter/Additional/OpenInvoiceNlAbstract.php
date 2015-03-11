<?php

/**
 * @author      Paul Siedler <paul.siedler@netresearch.de>
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Netresearch_OPS_Model_Backend_Operation_Parameter_Additional_OpenInvoiceNlAbstract
    implements Netresearch_OPS_Model_Backend_Operation_Parameter_Additional_Interface
{
    protected $additionalParams = array();
    protected $opsDataHelper = null;
    protected $itemIdx = 1;


    /**
     * @param Mage_Sales_Model_Abstract $itemContainer
     *
     * @return array
     */
    public function extractAdditionalParams(Mage_Sales_Model_Abstract $itemContainer)
    {
        if ($itemContainer instanceof Mage_Sales_Model_Order_Invoice) {
            $this->extractFromInvoiceItems($itemContainer);
            $this->extractFromDiscountData($itemContainer);
            $this->extractFromInvoicedShippingMethod($itemContainer);
        }

        return $this->additionalParams;
    }

    /**
     * extracts all necessary data from the invoice items
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     */
    protected function extractFromInvoiceItems(Mage_Sales_Model_Order_Invoice $invoice)
    {
        foreach ($invoice->getItemsCollection() as $item) {
            /** @var $item Mage_Sales_Model_Order_Invoice_Item */
            // filter out configurable products
            if ($item->getParentItemId()
                && $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                || $item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                continue;
            }
            $this->additionalParams['ITEMID' . $this->itemIdx]    = substr($item->getOrderItemId(), 0, 15);
            $this->additionalParams['ITEMNAME' . $this->itemIdx]  = substr($item->getName(), 0, 30);
            $this->additionalParams['ITEMPRICE' . $this->itemIdx] = $this->getOpsDataHelper()->getAmount(
                                                                         $item->getBasePriceInclTax()
            );
            $this->additionalParams['ITEMQUANT' . $this->itemIdx] = $item->getQty();
            $this->additionalParams['ITEMVATCODE' . $this->itemIdx]
                                                                    =
                str_replace(',', '.', (string)(float)$item->getTaxPercent()) . '%';
            $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
            ++$this->itemIdx;
        }

        return $this;
    }

    /**
     * extract the necessary data from the shipping data of the invoice
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return $this
     */
    protected function extractFromInvoicedShippingMethod(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $amount = $invoice->getBaseShippingInclTax();
        if (0 < $amount) {
            $this->additionalParams['ITEMID' . $this->itemIdx]      = 'SHIPPING';
            $this->additionalParams['ITEMNAME' . $this->itemIdx]    =
                substr($invoice->getOrder()->getShippingDescription(), 0, 30);
            $this->additionalParams['ITEMPRICE' . $this->itemIdx]   = $this->getOpsDataHelper()->getAmount($amount);
            $this->additionalParams['ITEMQUANT' . $this->itemIdx]   = 1;
            $this->additionalParams['ITEMVATCODE' . $this->itemIdx] = $this->getShippingTaxRate($invoice) . '%';
            $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
            ++$this->itemIdx;
        }


        return $this;
    }

    /**
     * retrieves used shipping tax rate
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return float
     */
    protected function getShippingTaxRate(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $taxRate       = 0.0;
        $order         = $invoice->getOrder();
        $payment       = $order->getPayment();
        $paymentMethod = null;
        if ($payment) {
            $paymentMethod = $payment->getMethodInstance();
        }
        if ($paymentMethod instanceof Netresearch_OPS_Model_Payment_Abstract) {
            $taxRate = (floatval($paymentMethod->getShippingTaxRate($order)));
        }

        return $taxRate;
    }


    /**
     * gets the ops data helper
     *
     * @return Netresearch_OPS_Helper_Data
     */
    protected function getOpsDataHelper()
    {
        if (null === $this->opsDataHelper) {
            $this->opsDataHelper = Mage::helper('ops/data');
        }

        return $this->opsDataHelper;
    }


    /**
     * @param $itemContainer
     */
    protected function extractFromDiscountData($invoice)
    {
        $amount = $invoice->getBaseDiscountAmount();
        if (0 > $amount) {
            $couponRuleName = 'DISCOUNT';
            $order          = $invoice->getOrder();
            if ($order->getCouponRuleName() && strlen(trim($order->getCouponRuleName())) > 0) {
                $couponRuleName = substr(trim($order->getCouponRuleName()), 0, 30);
            }
            $this->additionalParams['ITEMID' . $this->itemIdx]    = 'DISCOUNT';
            $this->additionalParams['ITEMNAME' . $this->itemIdx]  = $couponRuleName;
            $this->additionalParams['ITEMPRICE' . $this->itemIdx] = $this->getOpsDataHelper()->getAmount($amount);
            $this->amount += $this->getOpsDataHelper()->getAmount($amount);
            $this->additionalParams['ITEMQUANT' . $this->itemIdx]   = 1;
            $this->additionalParams['ITEMVATCODE' . $this->itemIdx] = $this->getShippingTaxRate($invoice) . '%';
            $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
            ++$this->itemIdx;
        }
    }
}