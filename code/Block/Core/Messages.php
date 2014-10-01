<?php
class Aoe_Static_Block_Core_Messages extends Mage_Core_Block_Messages
{
    public function getGroupedHtml()
    {
        /** @var Aoe_Static_Helper_Data $helper */
        $helper = Mage::helper('aoestatic');
        if (!$helper->cacheContent()) {
            return parent::getGroupedHtml();
        }

        $name = $this->getNameInLayout();

        return sprintf('<div class="placeholder" rel="%s"></div>', $name);
    }
}
