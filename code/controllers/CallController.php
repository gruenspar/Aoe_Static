<?php

/**
 * CallController
 * Renders the block that are requested via an ajax call
 *
 * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
 */
class Aoe_Static_CallController extends Mage_Core_Controller_Front_Action
{
    /**
     * Index action. This action is called by an ajax request
     *
     * @return void
     * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
     */
    public function indexAction()
    {
        /** @var Aoe_Static_Helper_Data $helper */
        $helper = Mage::helper("aoestatic");

        $response = array();
        $response['sid'] = Mage::getModel('core/session')->getEncryptedSessionId();

        if ($currentProductId = $this->getRequest()->getParam('currentProductId')) {
            Mage::getSingleton('catalog/session')->setLastViewedProductId($currentProductId);
        }

        $this->loadLayout();
        $this->_initLayoutMessages('checkout/session');
        $this->_initLayoutMessages('catalog/session');
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('core/session');
        $layout = $this->getLayout();

        $requestedBlockNames = $this->getRequest()->getParam('getBlocks');
        $response['blocks']  = $helper->getDynamicResponseBlockHtml($requestedBlockNames, $layout);

        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
}
