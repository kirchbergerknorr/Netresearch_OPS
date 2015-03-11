<?php
/**
 * Netresearch_OPS_AdminController
 * 
 * @package   OPS
 * @copyright 2012 Netresearch
 * @author    Thomas Birke <thomas.birke@netresearch.de> 
 * @license   OSL 3.0
 */
class Netresearch_OPS_Adminhtml_AdminController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return true;
        return Mage::getSingleton('admin/session')
            ->isAllowed('sales/shipment');
    }

    public function saveAliasAction()
    {
        $alias = $this->_request->getParam('alias');
        $quote = Mage::getSingleton('admin/session_quote');
        if (0 < strlen($alias)) {
            $payment = $quote->getPayment();
            $payment->setAdditionalInformation('alias', $alias);
            Mage::helper('ops')->log('saved alias ' . $alias . ' for quote #' . $quote->getId());
            $payment->setDataChanges(true);
            $payment->save();
        }
    }

    public function downloadLogAction()
    {
        $helper = Mage::helper('ops/data');
        $logPath = $helper->getLogPath();
        $downloader = Mage::getModel('ops/file_download');
        $fileToDownload = '';
        try{
            $fileToDownload = $downloader->getFile($logPath);
        } catch (Exception $e){
            $this->_getSession()->addError($e->getMessage());
        }
        if($fileToDownload === ''){
            $message = Mage::helper('ops')->__('Log file could not be retrieved.');
            $this->_getSession()->addError($message);
        } else {
            $this->_prepareDownloadResponse('ops.log', array(
                'value' => $fileToDownload,
                'type'  => 'filename'
            ));
        }
        $this->_redirectReferer();
    }
}
