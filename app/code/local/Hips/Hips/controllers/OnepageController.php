<?php
/**
 * Hips Fullpay Checkout Controller
 */
class Hips_Hips_OnepageController extends Mage_Core_Controller_Front_Action
{
    /**
     * Retrieve shopping cart model object
     *
     * @return Mage_Checkout_Model_Cart
     */
    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }
  
   /**
     * Get checkout session model instance
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current active quote instance
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return $this->_getCart()->getQuote();
    }

    /**
     *  Fullpay Checkout landing page
     */
    public function indexAction()
    {
        if (Mage::helper('checkout/cart')->getItemsCount()) {
            $result = Mage::getModel('hips/checkout')->createOrder();
            $this->loadLayout();
            Mage::getSingleton('core/session')->setHipsFullpayToken($result->id);
            Mage::register('token', $result->id);
            $this->renderLayout();
        } else {
            $this->_redirect('checkout/cart');
        }
    }

    /**
     *  Fullpay Checkout success page
     */
    public function successAction()
    {
        $result = Mage::getModel('hips/checkout')->placeOrder();
        $this->_redirect('checkout/onepage/success');
    }

    /**
     *  Fullpay Checkout failure page
     */
    public function failureAction()
    {
        $result = Mage::getModel('hips/checkout')->cancelOrder();
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     *  Update Qty action
     */
    public function updateQtyAction()
    {   
        $id = $this->getRequest()->getParam('itemId');
        $qty = $this->getRequest()->getParam('qty');
        $response = array();
        $quote = $this->_getQuote();
        $cartItems = $quote->getAllVisibleItems();
        foreach ($cartItems as $item) {
            if ($id==$item->getId()) {
                $item->setQty($qty);
                $item->save();   
                $this->_getQuote()->collectTotals()->save();      
                $response['subtotal'] = Mage::helper('checkout')->formatPrice($item->getRowTotal());
                break;
            }
        }

        $result = Mage::getModel('hips/checkout')->updateOrder();
        $sidebar= $this->getLayout()->createBlock('checkout/cart_totals');
        $sidebar->setTemplate('checkout/cart/totals.phtml')->toHtml();
        $response['refreshtotalBLK'] = $sidebar;
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

     /**
     * Initialize coupon
     */
    public function couponPostAction()
    {
        /**
         * No reason continue with empty shopping cart
         */
        if (!$this->_getCart()->getQuote()->getItemsCount()) {
            return;
        }

        $couponCode = (string) $this->getRequest()->getParam('coupon_code');
        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }

        $oldCouponCode = $this->_getQuote()->getCouponCode();

        if (!isset($couponCode) && !isset($oldCouponCode)) {
            return;
        }

        try {
            $codeLength = strlen($couponCode);
            $isCodeLengthValid = $codeLength && $codeLength <= Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH;
            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode($isCodeLengthValid ? $couponCode : '')
                ->collectTotals()
                ->save();
            if ($codeLength) {
                if ($isCodeLengthValid && $couponCode == $this->_getQuote()->getCouponCode()) {
                    $couponCodeValue = Mage::helper('core')->escapeHtml($couponCode);
                    $responseMsg = $this->__('Coupon code "%s" was applied.', $couponCodeValue);
                    $response = array('error'=>0,'cancel'=>0, 'msg'=>$responseMsg);
                    $this->_getSession()->setCartCouponCode($couponCode);
                } else {
                    $couponCodeValue = Mage::helper('core')->escapeHtml($couponCode);
                    $responseMsg = $this->__('Coupon code "%s" is not valid.', $couponCodeValue);
                    $response = array('error'=>1,'msg'=>$responseMsg);
                }
            } else {
                $response = array('error'=>0,'cancel'=>1,'msg'=>$this->__('Coupon code was canceled.'));
            }

            $result = Mage::getModel('hips/checkout')->updateOrder();
            $sidebar = $this->getLayout()->createBlock('checkout/cart_totals');
            $sidebar->setTemplate('checkout/cart/totals.phtml')->toHtml();
            $response['refreshtotalBLK'] = $sidebar;
        } catch (Mage_Core_Exception $e) {
            $response = array('error'=>1,'msg'=>$e->getMessage());
        } catch (Exception $e) {
             $response = array('error'=>1,'msg'=>$this->__('Cannot apply the coupon code.'));
        }
        
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}