<?php
class Hips_Hips_Model_Source_RequestType
{
    public function toOptionArray()
    {
        $auth = Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
        $capt = Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
        return array(
            array('value' => $auth, 'label' => Mage::helper('hips')->__('No')),
            array('value' => $capt, 'label' => Mage::helper('hips')->__('Yes')),
        );
    }
}