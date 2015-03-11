<?php

/**
 * @author      Paul Siedler <paul.siedler@netresearch.de>
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Netresearch_OPS_Model_Backend_Operation_Refund_Additional_OpenInvoiceNl
    extends Netresearch_OPS_Model_Backend_Operation_Parameter_Additional_OpenInvoiceNlAbstract
{
    protected $creditmemo = array();
    protected $amount = 0;
    protected $refundHelper = null;

    /**
     * @param Mage_Sales_Model_Abstract $itemContainer
     * @return array
     */
    public function extractAdditionalParams(Mage_Sales_Model_Abstract $itemContainer = null)
    {
        $invoice = null;
        if ($itemContainer instanceof Mage_Sales_Model_Order_Invoice && $itemContainer) {
            $invoice = $itemContainer;
        } else if ($itemContainer instanceof Mage_Sales_Block_Order_Creditmemo && $itemContainer){
            $invoice = Mage::getModel('sales/order_invoice')->load($itemContainer->getInvoiceId());
        }

        if($invoice == null){
            // if invoice is not set we load id hard from the request params
            $invoice = $this->getRefundHelper()->getInvoiceFromCreditMemoRequest();
        }
        $this->creditmemo = $this->getRefundHelper()->getCreditMemoFromRequest();

        if ($invoice instanceof Mage_Sales_Model_Order_Invoice) {
            $this->extractFromCreditMemoItems($invoice);
            // We dont extract from discount data for the moment, because partial refunds are a problem
            // $this->extractFromDiscountData($invoice);
            $this->extractFromInvoicedShippingMethod($invoice);
            $this->extractFromAdjustments($invoice);
            // Overwrite amount to fix Magentos rounding problems (eg +1ct)
            $this->additionalParams['AMOUNT'] = $this->amount;
        }

        return $this->additionalParams;
    }

    /**
     * extracts all data from the invoice according to the credit memo items
     *
     * @param $itemContainer
     */
    protected function extractFromCreditMemoItems(Mage_Sales_Model_Order_Invoice $invoice)
    {
        foreach ($invoice->getItemsCollection() as $item) {
            if (array_key_exists($item->getOrderItemId(), $this->creditmemo['items'])) {
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
                $this->amount += $this->getOpsDataHelper()->getAmount($item->getBasePriceInclTax()) * $this->creditmemo['items'][$item->getOrderItemId()]['qty'];
                $this->additionalParams['ITEMQUANT' . $this->itemIdx] = $this->creditmemo['items'][$item->getOrderItemId()]['qty'];
                $this->additionalParams['ITEMVATCODE' . $this->itemIdx]
                                                                        =
                    str_replace(',', '.', (string)(float)$item->getTaxPercent()) . '%';
                $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
                ++$this->itemIdx;
            }
        }

    }


    protected function extractFromInvoicedShippingMethod(Mage_Sales_Model_Order_Invoice $invoice)
    {
        if ($this->creditmemo['shipping_amount'] > 0) {
            $this->additionalParams['ITEMID' . $this->itemIdx]    = 'SHIPPING';
            $this->additionalParams['ITEMNAME' . $this->itemIdx]  =
                substr($invoice->getOrder()->getShippingDescription(), 0, 30);
            $this->additionalParams['ITEMPRICE' . $this->itemIdx] = $this->getOpsDataHelper()->getAmount($this->creditmemo['shipping_amount']);
            $this->amount += $this->getOpsDataHelper()->getAmount($this->creditmemo['shipping_amount']);
            $this->additionalParams['ITEMQUANT' . $this->itemIdx]   = 1;
            $this->additionalParams['ITEMVATCODE' . $this->itemIdx] = $this->getShippingTaxRate($invoice) . '%';
            $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
            ++$this->itemIdx;
        }

    }

    /**
     * extracts all data from the adjustment fee/refund
     *
     * @param $invoice
     */
    protected function extractFromAdjustments(Mage_Sales_Model_Order_Invoice $invoice)
    {

        if ($this->creditmemo['adjustment_positive'] > 0) {
            $this->additionalParams['ITEMID' . $this->itemIdx]    = 'ADJUSTREFUND';
            $this->additionalParams['ITEMNAME' . $this->itemIdx]  = 'Adjustment Refund';
            $this->additionalParams['ITEMPRICE' . $this->itemIdx] = $this->getOpsDataHelper()->getAmount($this->creditmemo['adjustment_positive']);
            $this->amount += $this->getOpsDataHelper()->getAmount($this->creditmemo['adjustment_positive']);
            $this->additionalParams['ITEMQUANT' . $this->itemIdx]   = 1;
            $this->additionalParams['ITEMVATCODE' . $this->itemIdx] = $this->getShippingTaxRate($invoice) . '%';
            $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
            ++$this->itemIdx;

        }
        if ($this->creditmemo['adjustment_negative'] > 0) {
            $this->additionalParams['ITEMID' . $this->itemIdx]    = 'ADJUSTFEE';
            $this->additionalParams['ITEMNAME' . $this->itemIdx]  = 'Adjustment Fee';
            $this->additionalParams['ITEMPRICE' . $this->itemIdx] = $this->getOpsDataHelper()->getAmount(-$this->creditmemo['adjustment_negative']);
            $this->amount += $this->getOpsDataHelper()->getAmount(-$this->creditmemo['adjustment_negative']);
            $this->additionalParams['ITEMQUANT' . $this->itemIdx]   = 1;
            $this->additionalParams['ITEMVATCODE' . $this->itemIdx] = $this->getShippingTaxRate($invoice) . '%';
            $this->additionalParams['TAXINCLUDED' . $this->itemIdx] = 1;
            ++$this->itemIdx;
        }
    }

    /**
     * gets the refund helper
     *
     * @return Netresearch_OPS_Helper_Order_Refund|null
     */
    protected function getRefundHelper()
    {
        if (null === $this->refundHelper) {
            $this->refundHelper = Mage::helper('ops/order_refund');
        }

        return $this->refundHelper;
    }
} 