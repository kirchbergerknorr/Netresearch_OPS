<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2013 Netresearch GmbH & Co. KG
 *              (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php
 *              Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Adminhtml_KwixoshippingController
    extends Mage_Adminhtml_Controller_Action
{

    /**
     * displays the form
     */
    public function indexAction()
    {
        $this->loadLayout();

        $storeId = $this->getRequest()->getParam('store', 0);
        $this->getLayout()->getBLock('kwixoshipping')->setData(
            'store', $storeId
        );
        $this->getLayout()->getBLock('kwixoshipping')->setData(
            'postData',
            Mage::getModel('adminhtml/session')->getData('errorneousData')
        );
        Mage::getModel('adminhtml/session')->unsetData('errorneousData');
        $this->renderLayout();
    }

    /**
     * save submitted form data
     */
    public function saveAction()
    {
        if ($this->getRequest()->isPost()) {
            $postData = $this->getRequest()->getPost();
            $methodCodes = array_keys(
                Mage::getSingleton('shipping/config')->getAllCarriers()
            );
            $validator = Mage::getModel('ops/validator_kwixo_shipping_setting');
            if (true === $validator->isValid($postData)) {

                foreach ($postData as $shippingCode => $kwixoData) {

                    if (!in_array($shippingCode, $methodCodes)) continue;
                    $kwixoShippingModel = Mage::getModel(
                        'ops/kwixo_shipping_setting'
                    )
                        ->load($shippingCode, 'shipping_code');
                    $kwixoShippingModel
                        ->setShippingCode($shippingCode)
                        ->setKwixoShippingType(
                            $kwixoData['kwixo_shipping_type']
                        )
                        ->setKwixoShippingSpeed(
                            $kwixoData['kwixo_shipping_speed']
                        )
                        ->setKwixoShippingDetails(
                            $kwixoData['kwixo_shipping_details']
                        )
                        ->save();
                }
            } else {
                $postData = array_merge_recursive(
                    $postData, $validator->getMessages()
                );
                Mage::getModel('adminhtml/session')->setData(
                    'errorneousData', $postData
                );
            }

        }
        $this->_redirect('adminhtml/kwixoshipping/index');
    }

} 