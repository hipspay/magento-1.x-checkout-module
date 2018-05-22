<?php
   
class Hips_Hips_Model_Fullpay extends Mage_Payment_Model_Method_Abstract
{
    
    protected $_code = 'fullpay';
    /**
     * Availability options
     */
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canRefund                   = true;
    /**
     * Send authorize request to gateway
     *
     * @param  Varien_Object $payment
     * @param  decimal $amount
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $amount = 0;
        $payment->setTransactionId($payment->getAdditionalInformation('fullpay_token'));
        $payment->setIsTransactionClosed(0);
        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $amount = 0;
        $errorMsg = '';
        if ($payment->getAdditionalInformation('payment_type') == 'authorize') {
            $url = 'orders/'.$payment->getAdditionalInformation('fullpay_token').'/fulfill';
            $result = Mage::getModel('hips/api')->call($url);
            if ($result->success != true) {
                $errorMsg = $this->_getHelper()->__('Error Processing the request');
            }
        }
        
        if ($errorMsg) {
            Mage::throwException($errorMsg);
        } else {
            $payment->setTransactionId($payment->getAdditionalInformation('fullpay_token'));
            $payment->setIsTransactionClosed(0);
        }

         return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('fullpay')->__('Invalid amount for refund.'));
        }

        if (!$payment->getParentTransactionId()) {
            Mage::throwException(Mage::helper('fullpay')->__('Invalid transaction ID.'));
        }

        $data = array();
        $data['amount'] = $amount*100;
        $items = array();
        $items['name'] = 'Return Item';
        $items['quantity'] = 1;
        $items['vat_amount'] = 0;
        $items['unit_price'] = -($amount*100);
        $data['items'] = $items;
        $url = 'orders/'.$payment->getAdditionalInformation('fullpay_token').'/revoke';
        $result = Mage::getModel('hips/api')->call($url, $data);
        if ($result->success != true) {
            $errorMsg = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        } else {
            $payment->setAmount($amount);
            $payment->setTransactionId($result->id);
            $payment->setParentTransactionId($payment->getAdditionalInformation('fullpay_token'));
            $payment->setIsTransactionClosed(1);
        }

        return $this;
    }
}