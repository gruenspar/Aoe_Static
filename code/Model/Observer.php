<?php

/**
 * Observer model
 *
 * @category Aoe
 * @package  Aoe_Static
 * @author   Fabrizio Branca <mail@fabrizio-branca.de>
 * @author   Toni Grigoriu <toni@tonigrigoriu.com>
 * @author   Stephan Hoyer <ste.hoyer@gmail.com>
 * @author   Jan Papenbrock <j.papenbrock@gruenspar.de>
 */
class Aoe_Static_Model_Observer
{
    protected $isCacheableAction = null;
    protected $customerBlocks = null;

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
     *
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

        //webforms WZFK-184
        $cmsWebform = false;
        if ($fullActionName == 'cms_page_view') {
            $cache = Mage::getSingleton('core/cache');
            $request = $event->getControllerAction()->getRequest();
            $pageId = $request->getParam('page_id');
            $storeId = Mage::app()->getStore()->getId();
            $key = 'webforms'. $pageId . $storeId;

            $data = $cache->load($key);
            if (!Mage::app()->useCache('collections') || !$data) {
                $pageContent = Mage::getModel('cms/page')->load($pageId)->getContent();
                if (strpos($pageContent, 'webforms/form') !== false) {
                    $cmsWebform = true;
                    $cache->save($cmsWebform, $key, array("collections"), 60 * 60 * 24);
                }
            } else {
                $cmsWebform = $data;
            }

        }

        /* @var $response Mage_Core_Controller_Response_Http */
        $response = $controllerAction->getResponse();
        if ($lifetime && !$this->hasSpecialOptionsSet($fullActionName) && !$cmsWebform) {
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
     *
     * @return void
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
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function cleanVarnishCache($observer)
    {
        /** @var Aoe_Static_Helper_Data $varnishHelper */
        $varnishHelper = Mage::helper('aoestatic');
        $types = Mage::app()->getRequest()->getParam('types');
        if (Mage::app()->useCache('aoestatic') ) {
            if ((is_array($types) && in_array('aoestatic', $types)) || $types == "aoestatic") {
                $errors = $varnishHelper->purgeAll();
                if (count($errors) > 0) {
                    $this->_getSession()->addError(
                        Mage::helper('adminhtml')
                            ->__("Error while purging Varnish cache:<br />" . implode('<br />', $errors))
                    );
                } else {
                    $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__("Varnish cache cleared!"));
                }
            }
            $varnishHelper->purgeByTags($types);
        }
    }

    /**
     * Unsets template for every dynamically loaded block to avoid it being rendered
     * if current action is cachable. Useful if one-time session toggles are used in
     * dynamic blocks.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return Aoe_Static_Model_Observer
     */
    public function htmlBefore($observer)
    {
        if ($this->isReplaceableBlock($observer->getBlock())
            && $this->isCacheableAction()
        ) {
            $observer->getBlock()->setTemplate(null);
        }
        return $this;
    }

    /**
     * Fires collect tags and replacePlaceholder functions for every block
     * if current action is cacheable.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return Aoe_Static_Model_Observer
     */
    public function htmlAfter($observer)
    {
        if ($this->canReplaceBlock($observer->getBlock())) {
            $this->getCache()->collectTags($observer);
            $this->replacePlaceholder($observer);
        }
        return $this;
    }

    /**
     * Can given block block be replaced?
     *
     * @param Mage_Core_Block_Abstract $block
     *
     * @return bool
     */
    protected function canReplaceBlock($block)
    {
        if ($this->getHelper()->isAjaxCallback()) {
            return false;
        }

        $result = $this->isReplaceableBlock($block);

        return $result;
    }

    /**
     * Is block replaceable?
     *
     * @param Mage_Core_Block_Abstract $block
     *
     * @return bool
     */
    protected function isReplaceableBlock($block)
    {
        if (is_null($this->customerBlocks)) {
            $this->customerBlocks = $this->getHelper()->getCustomerBlocks();
        }

        $result = array_key_exists($block->getNameInLayout(), $this->customerBlocks);
        return $result;
    }

    /**
     * Is current action cacheable in Varnish?
     *
     * @return bool
     */
    protected function isCacheableAction()
    {
        if (is_null($this->isCacheableAction)) {
            $this->isCacheableAction = $this->getHelper()->isCacheableAction();
        }

        return $this->isCacheableAction;
    }

    /**
     * Replace content block with placeholder content
     * if block is customer related.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    protected function replacePlaceholder($observer)
    {
        if ($this->isReplaceableBlock($observer->getBlock())) {
            $name = $observer->getBlock()->getNameInLayout();

            $placeholder = '<div class="placeholder" rel="%s">%s</div>%s';

            if ($this->getHelper()->useSessionStorage()
                && in_array($name, $this->getHelper()->getSessionStorageBlocks())
            ) {
                $instantLoad = sprintf(
                    '<script type="text/javascript">ajaxHomeInstantLoad("%s");</script>',
                    $name
                );
            } else {
                $instantLoad = "";
            }

            $cmsHtml = '';
            if ($this->customerBlocks[$name]) {
                $block = Mage::getBlockSingleton('cms/block')
                    ->setBlockId($this->customerBlocks[$name]);
                $cmsHtml = $block->toHtml();
            }

            $observer->getTransport()->setHtml(sprintf($placeholder, $name, $cmsHtml, $instantLoad));
        }
    }

    /**
     * Get module helper.
     *
     * @return Aoe_Static_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('aoestatic');
    }

    /**
     * Get cache instance.
     *
     * @return Aoe_Static_Model_Cache
     */
    public function getCache()
    {
        return Mage::getSingleton('aoestatic/cache');
    }
}
