<?php
/**
 * Observer model
 *
 * @category    Aoe
 * @package     Aoe_Static
 * @author		Fabrizio Branca <mail@fabrizio-branca.de>
 * @author      Toni Grigoriu <toni@tonigrigoriu.com>
 * @author      Stephan Hoyer <ste.hoyer@gmail.com>
 */
class Aoe_Static_Model_Observer
{
    var $isCacheableAction = true;
    var $customerBlocks=null;

    /**
     * Return whether there are special options set in the current session
     * so we better not cache this page.
     *
     * @param string $fullActionName Full action name.
     *
     * @return bool
     */
    protected function hasSpecialOptionsSet($fullActionName)
    {
        $isCategoryPage = (strpos($fullActionName, "catalog_category") !== false);
        $isFilterPage   = (strpos($fullActionName, "amshopby_index_index") !== false);
        if ($isCategoryPage || $isFilterPage) {
            /** @var Mage_Catalog_Model_Session $catalogSession */
            $catalogSession = Mage::getSingleton('catalog/session');

            if ($catalogSession->hasLimitPage() || $catalogSession->hasSortOrder()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check when varnish caching should be enabled.
     *
     * @param Varien_Event_Observer $observer
     * @return Aoe_Static_Model_Observer
     */
    public function processPreDispatch(Varien_Event_Observer $observer)
    {

        /* @var $helper Aoe_Static_Helper_Data */
        $helper = Mage::helper('aoestatic');
        /* @var $event Varien_Event */
        $event = $observer->getEvent();
        /* @var $controllerAction Mage_Core_Controller_Varien_Action */
        $controllerAction = $event->getControllerAction();
        $fullActionName = $controllerAction->getFullActionName();

        $lifetime = $helper->isCacheableAction($fullActionName);

        /* @var $response Mage_Core_Controller_Response_Http */
        $response = $controllerAction->getResponse();
        if ($lifetime && !$this->hasSpecialOptionsSet($fullActionName)) {
            // allow caching
            // Only for debugging and information
            $response->setHeader('X-Magento-Lifetime', $lifetime, true);
            $response->setHeader('Cache-Control', 'max-age='. $lifetime, true);
            $response->setHeader('aoestatic', 'cache', true);
        } else {
            // do not allow caching
            /* @var $cookie Mage_Core_Model_Cookie */
            $cookie = Mage::getModel('core/cookie');

            $name = '';
            $loggedIn = false;
            /* @var $session Mage_Customer_Model_Session  */
            $session = Mage::getSingleton('customer/session');
            if ($session->isLoggedIn()) {
                $loggedIn = true;
                $name = $session->getCustomer()->getName();
            }
            // Only for debugging and information
            $response->setHeader('X-Magento-LoggedIn', $loggedIn ? '1' : '0', true);
            $cookie->set('aoestatic_customername', $name, '3600', '/');
        }
        // Only for debugging and information
        $response->setHeader('X-Magento-Action', $fullActionName, true);

        return $this;
    }

    /**
     * Add layout handle 'aoestatic_cacheable' or 'aoestatic_notcacheable'
     *
     * @param Varien_Event_Observer $observer
     */
    public function beforeLoadLayout(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('aoestatic'); /* @var $helper Aoe_Static_Helper_Data */
        $event = $observer->getEvent(); /* @var $event Varien_Event */
        $controllerAction = $event->getAction(); /* @var $controllerAction Mage_Core_Controller_Varien_Action */
        $fullActionName = $controllerAction->getFullActionName();

        $lifetime = $helper->isCacheableAction($fullActionName);

        $handle = $lifetime ? 'aoestatic_cacheable' : 'aoestatic_notcacheable';

        $observer->getEvent()->getLayout()->getUpdate()->addHandle($handle);
    }

    /**
     * Returns current admin session.
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Purges complete Varnish cache if flag is set.
     *
     * @param $observer
     */
    public function cleanVarnishCache($observer)
    {
        /** @var Aoe_Static_Helper_Data $varnishHelper */
        $varnishHelper = Mage::helper('aoestatic');
        $types = Mage::app()->getRequest()->getParam('types');
        if (Mage::app()->useCache('aoestatic') ) {
            if( (is_array($types) && in_array('aoestatic', $types)) || $types == "aoestatic") {
                $errors = $varnishHelper->purgeAll();
                if (count($errors) > 0) {
                    $this->_getSession()->addError(Mage::helper('adminhtml')->__("Error while purging Varnish cache:<br />" . implode('<br />', $errors)));
                } else {
                    $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__("Varnish cache cleared!"));
                }
            }
            $varnishHelper->purgeByTags($types);
        }
    }

    /**
     * Fires collect tags and replacePlaceholder functions for every block
     * if current action is cachable.
     *
     * @param type $observer
     * @return Aoe_Static_Model_Observer
     */
    public function htmlBefore($observer)
    {
        /** @var Mage_Core_Block_Abstract $block */
        $block = $observer->getBlock();
        $name = $block->getNameInLayout();
        if (is_null($this->customerBlocks)) {
            $this->customerBlocks = $this->getHelper()->getCustomerBlocks();
        }
        if (array_key_exists($name, $this->customerBlocks)) {
            $this->isCacheableAction = $this->isCacheableAction
                && $this->getHelper()->isCacheableAction();
            if ($this->isCacheableAction) {
                $block->setTemplate(null);
            }
        }
        return $this;
    }

    /**
     * Fires collect tags and replacePlaceholder functions for every block
     * if current action is cachable.
     *
     * @param type $observer
     * @return Aoe_Static_Model_Observer
     */
    public function htmlAfter($observer)
    {
        //cache check if cachable to improve performance
        $this->isCacheableAction = $this->isCacheableAction
            && $this->getHelper()->isCacheableAction();
        if ($this->isCacheableAction) {
            Mage::getSingleton('aoestatic/cache')->collectTags($observer);
            $this->replacePlacholder($observer);
        }
        return $this;
    }

    /**
     * Replace content block wiht placeholder content
     * if block is customer related.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    protected function replacePlacholder($observer)
    {
        $name = $observer->getBlock()->getNameInLayout();
        if (is_null($this->customerBlocks)) {
            $this->customerBlocks = $this->getHelper()->getCustomerBlocks();
        }
        if (array_key_exists($name, $this->customerBlocks)) {
            $placholder = '<div class="placeholder" rel="%s">%s</div>';
            $cmsHtml = '';
            if ($this->customerBlocks[$name]) {
                $block = Mage::getBlockSingleton('cms/block')
                    ->setBlockId($this->customerBlocks[$name]);
                $cmsHtml = $block->toHtml();
            }
            $observer->getTransport()->setHtml(sprintf($placholder, $name, $cmsHtml));
        }
    }

    /**
     * @return Aoe_Static_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('aoestatic');
    }

    public function getCache()
    {
        return Mage::getSingleton('aoestatic/cache');
    }
}
