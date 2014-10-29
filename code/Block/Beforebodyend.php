<?php

/**
 * Beforebodyend block
 *
 * @author Fabrizio Branca
 */
class Aoe_Static_Block_Beforebodyend extends Mage_Core_Block_Template {

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $session;

    /**
     * Get session
     *
     * @return Mage_Customer_Model_Session
     */
    public function getSession()
    {
        if (is_null($this->session)) {
            $this->session = Mage::getSingleton('customer/session');
        }
        return $this->session;
    }

    /**
     * Check if there is a logged in customer
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->getSession()->isLoggedIn();
    }

    /**
     * Get customer name
     *
     * @return bool|string
     */
    public function getCustomerName()
    {
        if ($this->isLoggedIn()) {
            return $this->getSession()->getCustomer()->getName();
        } else {
            return false;
        }
    }

    /**
     * Get cart summary count
     *
     * @return int
     */
    public function getCartSummaryCount()
    {
        // return Mage::helper('checkout/cart')->getSummaryCount();
    }

    /**
     * Return whether cookie mode is enabled.
     *
     * @return bool
     */
    public function useSessionStorage()
    {
        return $this->_getHelper()->useSessionStorage();
    }

    /**
     * Convert an array to JSON string.
     *
     * @param array $array Array to encode.
     *
     * @return string
     */
    public function toJson($array)
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');

        $result = $helper->jsonEncode($array);
        return $result;
    }

    /**
     * Return configured blocks to store in session storage.
     * As JSON.
     *
     * @return string
     */
    public function getSessionStorageBlocks()
    {
        $blocks = $this->_getHelper()->getSessionStorageBlocks();
        return $this->toJson($blocks);
    }

    /**
     * Return configured groups for clearing session storage.
     * As JSON.
     *
     * @return string
     */
    public function getSessionStorageGroups()
    {
        $groups = $this->_getHelper()->getSessionStorageGroups();
        return $this->toJson($groups);
    }

    /**
     * Return Aoe_Static helper.
     *
     * @return Aoe_Static_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper("aoestatic");
    }
}
