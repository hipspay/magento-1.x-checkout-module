<?php
   
class Hips_Hips_Model_Checkout extends Varien_Object
{

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * Payment method type
     *
     * @var unknown_type
     */
    protected $_methodType = 'fullpay';

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $_customerSession;

    /**
     * @var Mage_Checkout_Model_Session
     */
    protected $_checkoutSession;

    /**
     * Order
     *
     * @var Mage_Sales_Model_QuoteMage_Sales_Model_Quote
     */
    protected $_order = null;

    const PAYMENT_INFO_TRANSPORT_TOKEN    = 'fullpay_token';

    /**
     * Set quote and config instances
     * @param array $params
     */
    public function __construct($params = array())
    {
        if (empty($params)) {
            $params = array();
        }

        $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        $this->_checkoutSession = Mage::getSingleton('checkout/session');
        $this->_customerSession = Mage::getSingleton('customer/session');
    }

    /**
     * Setter for customer Id
     *
     * @param int $id
     * @return Mage_Paypal_Model_Express_Checkout
     * @deprecated please use self::setCustomer
     */
    public function setCustomerId($id)
    {
        $this->_customerId = $id;
        return $this;
    }

    /**
     * Setter for customer
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return Mage_Paypal_Model_Express_Checkout
     */
    public function setCustomer($customer)
    {
        $this->_quote->assignCustomer($customer);
        $this->_customerId = $customer->getId();
        return $this;
    }

    public function createOrder()
    {
        $this->_quote->collectTotals();

        if (!$this->_quote->getGrandTotal() && !$this->_quote->hasNominalItems()) {
            Mage::throwException(
                Mage::helper('hips')->__(
                    'Hips does not support processing orders with zero amount. 
                    To complete your purchase, proceed to the standard checkout process.'
                )
            );
        }

        $this->_quote->reserveOrderId()->save();
        $request = array();
        $request['order_id'] = $this->_quote->getReservedOrderId();
        $request['purchase_currency'] = $this->_quote->getBaseCurrencyCode(); 
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $request['user_session_id'] = Mage::getSingleton('customer/session')->getEncryptedSessionId();
            $request['user_identifier'] = $customer->getName();
        } else {
            $session = Mage::getSingleton('core/session');
            $SID=$session->getEncryptedSessionId();
            $request['user_session_id'] = rand(10000, 999999999999999);
            $request['user_identifier'] = 'GUEST';
        }

        $request['meta_data_1'] ='' ;
        if ($this->getPaymentAction() == 'authorize') {
            $request['fulfill'] = 'false';
        } else {
            $request['fulfill'] = 'true';
        }

        $request['cart'] = $this->getCart();
        if (!$this->_quote->isVirtual()) {
            $request['require_shipping'] = 'true';
            $request['express_shipping'] = 'true';
        } else {
            $request['require_shipping'] = 'false';
            $request['express_shipping'] = 'false';
        }

        $request['ecommerce_platform'] = "Magento 1.9.3.1";
        $request['ecommerce_module'] = "Hips Magento Module 0.1.0";
        $request['checkout_settings'] = array("extended_cart" => 'true');
        $request['hooks'] = array(
            "user_return_url_on_success" => Mage::getUrl('hips/onepage/success'),
            "user_return_url_on_fail"=>Mage::getUrl('hips/onepage/failure'),
            "terms_url"=> Mage::getUrl('terms'),
            "webhook_url"=> Mage::getUrl('hips/confirmations/index')
        );
        $token = Mage::getModel('hips/api')->call('orders', $request);
        return $token;
    }

    public function getCart()
    {
        $cart = array();
        foreach ($this->_quote->getAllVisibleItems() as $item) {
            $cartItem = array();
            if ($item->getProduct()->getIsVirtual()) {
                $cartItem['type'] = 'digital';
            } else {
                $cartItem['type'] = 'physical';
            }

            $cartItem['sku'] = $item->getProduct()->getSku();
            $cartItem['name'] = $item->getProduct()->getName();
            $cartItem['quantity'] = $item->getQty();
            $cartItem['unit_price'] = $item->getPriceInclTax()*100;
            $cartItem['discount_rate'] = ($item->getDiscountAmount()/$item->getPrice())*100;
            $cartItem['vat_amount'] = ($item->getPriceInclTax() - $item->getPrice())*100;
            $cartItem['weight_unit'] = "lb";
            $cartItem['weight'] = $item->getProduct()->getWeight();
            $cart['items'][] = $cartItem;          
        }

        return $cart;
    }

    /**
     * Gets the HipsPayments secret key from the admin config
     * @return string Secret Key or empty string if not set
     */
    public function getPaymentAction()
    {
        $storeCode = null;
        if (Mage::app()->getStore()->isAdmin()) {
            $storeCode = Mage::getSingleton('adminhtml/session_quote')->getStore()->getCode();
        }

        return Mage::getStoreConfig('payment/fullpay/payment_action', $storeCode);
    }

    /**
     * @return mixed
     */
    public function placeOrder()
    {
        $request = (array)Mage::getModel('hips/api')->viewOrder();        
        $billingAdd = (array)$request['billing_address'];
        $this->_quote->setCustomerEmail($billingAdd['email']);
        $addressData = array(
                'firstname' => $billingAdd['given_name'],
                'lastname' => $billingAdd['family_name'],
                'street' => $billingAdd['street_address'],
                'city' => $billingAdd['city'],
                'postcode' => $billingAdd['postal_code'],
                'telephone' => $billingAdd['phone_mobile'],
                'country_id' => $billingAdd['country'],
        );
        $billingAddress = $this->_quote->getBillingAddress()->addData($addressData);
        if ($request['shipping_address']->id) {
            $shippingAdd = (array)$request['shipping_address'];
            if ($shippingAdd['phone_mobile']) {
                $tel = $shippingAdd['phone_mobile'];
            } else {
                $tel = $billingAdd['phone_mobile'];
            }

            $addressData = array(
                    'firstname' => $shippingAdd['given_name'],
                    'lastname' => $shippingAdd['family_name'],
                    'street' => $shippingAdd['street_address'],
                    'city' => $shippingAdd['city'],
                    'postcode' => $shippingAdd['postal_code'],
                    'telephone' => $tel,
                    'country_id' => $shippingAdd['country'],
            );
        }

        $shippingAddress = $this->_quote->getShippingAddress()->addData($addressData);
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()
                        ->setShippingMethod('hips_shipping_hips_shipping')
                        ->setPaymentMethod('fullpay');
        $this->_quote->getPayment()->importData(array('method' => 'fullpay'));
        $this->_quote->getPayment()->setAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_TOKEN, $request->id);
        $this->_quote->collectTotals()->save();
        $service = Mage::getModel('sales/service_quote', $this->_quote);
        $service->submitAll();
        $this->_checkoutSession->setLastQuoteId($this->_quote->getId())
            ->setLastSuccessQuoteId($this->_quote->getId())
            ->clearHelperData();
        $order = $service->getOrder();
        if ($order) {
            Mage::dispatchEvent(
                "checkout_type_onepage_save_order_after", 
                array("order" => $order, "quote" => $this->_quote)
            );
            $redirectUrl = '';            
            if (!$redirectUrl && $order->getCanSendNewEmailFlag()) {
                try {
                    $order->queueNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $this->_checkoutSession->setLastOrderId($order->getId())
                ->setRedirectUrl($redirectUrl)
                ->setLastRealOrderId($order->getIncrementId());
        }

        Mage::dispatchEvent("checkout_submit_all_after", array("order" => $order, "quote" => $this->_quote));
        $this->_quote->delete();
        return $order;
    }

    /**
     *
     */
    public function cancelOrder()
    {
        $id = Mage::getSingleton('core/session')->getHipsFullpayToken();
        $order = Mage::getModel('fullpay/api')->viewOrder();
    }

     /**
     * Return checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

    public function updateOrder()
    {
        $this->_quote->collectTotals();

        if (!$this->_quote->getGrandTotal() && !$this->_quote->hasNominalItems()) {
            Mage::throwException(
                Mage::helper('hips')->__(
                    'Hips does not support processing orders with zero amount. 
                    To complete your purchase, proceed to the standard checkout process.'
                )
            );
        }

        $this->_quote->reserveOrderId()->save();
        $request = array();
        $request['order_id'] = $this->_quote->getReservedOrderId();
        $request['purchase_currency'] = $this->_quote->getBaseCurrencyCode(); 
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $request['user_session_id'] = Mage::getSingleton('customer/session')->getEncryptedSessionId();
            $request['user_identifier'] = $customer->getName();
        } else {
            $session = Mage::getSingleton('core/session');
            $SID=$session->getEncryptedSessionId();
            $request['user_session_id'] = rand(10000, 999999999999999);
            $request['user_identifier'] = 'GUEST';
        }

        $request['cart'] = $this->getCart();
        $request['require_shipping'] = 'true';
        $request['express_shipping'] = 'true';
        $request['hooks'] = array(
            "user_return_url_on_success" => Mage::getUrl('hips/onepage/success'),
            "user_return_url_on_fail"=>Mage::getUrl('hips/onepage/failure'),
            "terms_url"=> Mage::getUrl('terms'),
            "webhook_url"=> Mage::getUrl('hips/confirmations/index')
        );
        $method =  'orders/'.Mage::getSingleton('core/session')->getHipsFullpayToken();
        $token = Mage::getModel('hips/api')->callPatch($method, $request);
        return $token;
    }
}