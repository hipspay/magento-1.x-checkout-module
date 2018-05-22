<?php
class Hips_Hips_Model_Cardpay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'cardpay';
    protected $_formBlockType = 'hips/cardpay_form_cardpay';
    protected $_infoBlockType = 'hips/cardpay_info_cardpay';
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canRefund               = true;

    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $cardToken = $data->getCardToken();
        if (empty($cardToken)) {
            Mage::throwException((Mage::helper('hips')->__('Error Processing the request ')));
        }

        $this->getInfoInstance()->setAdditionalInformation("card_token", $data->getCardToken());
        return $this;
    }

    /** For authorization **/
    public function authorize(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $data = array();
        $data['source'] = 'card_token';
        $data['card_token'] = $payment->getAdditionalInformation('card_token');
        $data['purchase_currency'] = 'SEK';
        $data['amount'] = $amount*100;
        $data['customer'] = $this->getCustomerInfo();
        $result = $this->call($data);
        $errorMsg = '';
        if ($result->status == 'authorized') {
            $payment->setTransactionId($result->id);
            $payment->setIsTransactionClosed(0);
            $payment->setAdditionalInformation('hips_token', $result->id);
        } else {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    /** For capture **/
    public function capture(Varien_Object $payment, $amount)
    {
        $amount = 0;
        $order = $payment->getOrder();
        $url = $payment->getAdditionalInformation('hips_token').'/capture';
        $data = array();
        $result = $this->call($data, $url);
        if ($result->status == 'successful') {
            $payment->setTransactionId($result->id);
            $payment->setIsTransactionClosed(0);
        } else {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $url = $payment->getAdditionalInformation('hips_token').'/refund ';
        $data = array();
        $data['amount'] = $amount*100;
        $item['name'] = 'Return Product';
        $item['quantity'] = 1;
        $item['vat_amount'] = 0;
        $item['unit_price'] = $amount*100;
        $data['items']  = $item;
        $result = $this->call($data, $url);
        if ($result->status == 'successful') {
            $payment->setTransactionId($result->id);
            $payment->setIsTransactionClosed(1);
        } else {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    /**
     * API endpoint getter
     *
     * @return string
     */
    public function getApiEndpoint()
    {
        $url = 'https://api.hips.com/v1/payments';
        return $url;
    }
    /**
     * Gets the HipsPayments secret key from the admin config
     * @return string Secret Key or empty string if not set
     */
    public function getSecretKey()
    {
        $storeCode = null;
        if (Mage::app()->getStore()->isAdmin()) {
            $storeCode = Mage::getSingleton('adminhtml/session_quote')->getStore()->getCode();
        }

        return Mage::getStoreConfig('payment/cardpay/private_key', $storeCode);
    }

    /**
     * Gets the HipsPayments publishable key from the admin config
     * @return string Publishable Key or empty string if not set
     */
    public function getPublicKey()
    {
        $storeCode = null;
        if (Mage::app()->getStore()->isAdmin()) {
            $storeCode = Mage::getSingleton('adminhtml/session_quote')->getStore()->getCode();
        }

        return Mage::getStoreConfig('payment/cardpay/public_key', $storeCode);
    }

    /**
     * Gets the customers email from either the billing info or the session
     */
    protected function getCustomerInfo()
    {
        $payment = $this->getInfoInstance();
        $info = array();
        if ($payment instanceof Mage_Sales_Model_Order_Payment) {
            $info['name'] = $payment->getOrder()->getBillingAddress()->getFirstname();
            $info['email'] = $payment->getOrder()->getCustomerEmail();
            $info['ip_address'] = Mage::helper('core/http')->getRemoteAddr();
        }
        
        return $info;
    }

    /**
     * Do the API call
     *
     * @param string $methodName
     * @param array $request
     * @return array
     * @throws Mage_Core_Exception
     */
    public function call($request, $methodName = '', $type = 'POST')
    {
        try {
            $key = $this->getSecretKey();
            $url = $this->getApiEndpoint();
            if ($methodName) {
              $url .= '/'.$methodName;
            }

            $data = json_encode($request);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization:'.$key
                )
            );
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return json_decode($result);
        } catch (Exception $e) {
            $debugData['http_error'] = array('error' => $e->getMessage(), 'code' => $e->getCode());
            $this->_debug($debugData);
            throw $e;
        }
    }
}

