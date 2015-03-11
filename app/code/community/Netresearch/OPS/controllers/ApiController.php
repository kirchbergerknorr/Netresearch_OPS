<?php
/**
 * Netresearch_OPS_ApiController
 *
 * @package
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de>
 * @author    André Herrn <andre.herrn@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_ApiController extends Netresearch_OPS_Controller_Abstract
{
    /**
     * Order instance
     */
    protected $_order;

    /*
     * Predispatch to check the validation of the request from OPS
     */
    public function preDispatch()
    {
        parent::preDispatch();
        if (!$this->_validateOPSData()) {
            throw new Exception ("Hash not valid");
        }
    }

    /**
     * Action to control postback data from ops
     *
     */
    public function postBackAction()
    {
        $params = $this->getRequest()->getParams();
        if (Mage::app()->getStore()->getId() != $this->_getOrder()->getStoreId()) {
            $redirectRoute = Mage::getUrl(
                'ops/api/postBack',
                array(
                    '_store' => $this->_getOrder()->getStoreId(),
                    '_nosid' => true,
                    '_query' => $params
                 )
            );
        $this->_redirectUrl($redirectRoute);
        return;
        }
        try {
            $status = $this->getPaymentHelper()->applyStateForOrder(
                $this->_getOrder(),
                $params
            );
            $redirectRoute = Mage::helper('ops/api')
                ->getRedirectRouteFromStatus($status);
            $this->_redirect($redirectRoute, array('_query' => $params));
        } catch (Exception $e) {
            Mage::helper('ops')->log(
                "Run into exception '{$e->getMessage()}' in postBackAction"
            );
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    /**
     * Action to control postback data from ops
     *
     */
    public function directLinkPostBackAction()
    {
        $params = $this->getRequest()->getParams();
        try {
            $this->getDirectlinkHelper()->processFeedback(
                $this->_getOrder(),
                $params
            );
        } catch (Exception $e) {
            Mage::helper('ops')->log(
                "Run into exception '{$e->getMessage()}' in directLinkPostBackAction"
            );
            throw ($e);
        }
    }
}
