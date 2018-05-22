<?php
class Hips_Hips_Block_Cardpay_Form_Cardpay extends Mage_Payment_Block_Form_Ccsave
{
    protected function _construct()
    {
        parent::_construct();
        $template = $this->setTemplate('hips/cardpay/form/cardpay.phtml');

        if (!Mage::app()->getStore()->isAdmin()) {
            $mark = Mage::getConfig()->getBlockClassName('core/template');
            $mark = new $mark;
            $mark->setTemplate('hips/cardpay/form/mark.phtml');
            $template->setMethodLabelAfterHtml($mark->toHtml());
        }
    }
}