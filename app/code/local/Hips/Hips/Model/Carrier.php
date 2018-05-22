<?php
/**
 * Hips
 * @package    Hips_Fullpay
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Hips_Hips_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract 
implements Mage_Shipping_Model_Carrier_Interface
{

    protected $_code = 'hips_shipping';
    /**
    * Custom shipping method
    */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!Mage::getStoreConfig('payment/fullpay/active', Mage::app()->getStore())) {
            return false;
        }

        $items = $request->getAllItems();
        $shippingPrice = 0.00;
        $title = 'Hips Shipping';
        if (Mage::getSingleton('core/session')->getHipsFullpayToken()) {
            $res = (array)Mage::getModel('hips/api')->viewOrder();

            if ($res['require_shipping'] == true) {
                $shipping = $res->shipping;
                $shippingPrice = ($shipping->fee/100);
                $title = $shipping->name;
            }
        }

        $result = Mage::getModel('shipping/rate_result');
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier('hips_shipping');
        $method->setCarrierTitle($title);
        $method->setMethod('hips_shipping');
        $method->setMethodTitle($title);
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        $result->append($method);
        return $result;
    }

    public function getAllowedMethods()
    {
       return array('hips_shipping'=> '');
    }
}
