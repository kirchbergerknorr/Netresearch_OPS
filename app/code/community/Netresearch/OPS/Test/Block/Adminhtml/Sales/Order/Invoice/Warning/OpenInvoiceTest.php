<?php
/**
 * @author      Michael Lühr <michael.luehr@netresearch.de> 
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Netresearch_OPS_Test_Block_Adminhtml_Sales_Order_Invoice_Warning_OpenInvoiceTest extends EcomDev_PHPUnit_Test_Case
{

    public function testGetTemplate()
    {
        $block = Mage::app()->getLayout()->getBlockSingleton('ops/adminhtml_sales_order_invoice_warning_openInvoice');
        $this->assertEquals('ops/sales/order/invoice/warning/open-invoice.phtml', $block->getTemplate());
    }

} 