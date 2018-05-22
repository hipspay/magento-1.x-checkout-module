<?php
class Hips_Hips_Block_Onepage_Link extends Mage_Checkout_Block_Onepage_Link
{
    public function getCheckoutUrl()
    {
        $storeCode = null;
        if (Mage::getStoreConfig('payment/fullpay/active', $storeCode) == 1) {
            return $this->getUrl('hips/onepage', array('_secure'=>true));
        } else {
            return $this->getUrl('checkout/onepage', array('_secure'=>true));
        }
    }
}
