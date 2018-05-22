<?php
/**
 * Hips Data helper
 */
class Hips_Hips_Helper_Url extends Mage_Checkout_Helper_Url
{
    /**
     * Retrieve checkout url
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        if (Mage::getStoreConfig('payment/fullpay/active') == 1) {
            return $this->_getUrl('hips/onepage');
        } else {
            return $this->_getUrl('checkout/onepage');
        }
    }
}
