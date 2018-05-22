<?php

class Hips_Hips_Model_Api extends Varien_Object
{
    /**
     * Warning codes recollected after each API call
     *
     * @var array
     */
    protected $_callWarnings = array();

    /**
     * Error codes recollected after each API call
     *
     * @var array
     */
    protected $_callErrors = array();

    /**
     * Whether to return raw response information after each call
     *
     * @var bool
     */
    protected $_rawResponseNeeded = false;

    /**
     * API call HTTP headers
     *
     * @var array
     */
    protected $_headers = array();

    /**
     * API endpoint getter
     *
     * @return string
     */
    public function getApiEndpoint()
    {
        $url = 'https://api.hips.com/v1';
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

        return Mage::getStoreConfig('payment/fullpay/private_key', $storeCode);
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

        return Mage::getStoreConfig('payment/fullpay/public_key', $storeCode);
    }

    /**
     * Do the API call
     *
     * @param string $methodName
     * @param array $request
     * @return array
     * @throws Mage_Core_Exception
     */
    public function call($methodName, array $request)
    {
        try {
            $key = $this->getSecretKey();
            $url = $this->getApiEndpoint().'/'.$methodName;
            $data = json_encode($request);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
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
            curl_close($ch);
            return json_decode($result);
        } catch (Exception $e) {
            $debugData['http_error'] = array('error' => $e->getMessage(), 'code' => $e->getCode());
            $this->_debug($debugData);
            throw $e;
        }
    }

     /**
     * Do the API call
     *
     * @param string $methodName
     * @param array $request
     * @return array
     * @throws Mage_Core_Exception
     */
    public function viewOrder()
    {
        try {
            $key = $this->getSecretKey();
            $id = Mage::getSingleton('core/session')->getHipsFullpayToken();
            $url = $this->getApiEndpoint().'/orders/'.$id;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
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
            curl_close($ch);
            return json_decode($result);
        } catch (Exception $e) {
            $debugData['http_error'] = array('error' => $e->getMessage(), 'code' => $e->getCode());
            $this->_debug($debugData);
            throw $e;
        }
    }

    /**
     * Build query string from request
     *
     * @param array $request
     * @return string
     */
    protected function _buildQuery($request)
    {
        return json_encode($request);
    }

    /**
     * Do the API call for Patch
     *
     * @param string $methodName
     * @param array $request
     * @return array
     * @throws Mage_Core_Exception
     */
    public function callPatch($methodName, array $request)
    {
        try {
            $key = $this->getSecretKey();
            $url = $this->getApiEndpoint().'/'.$methodName;
            $data = json_encode($request);


            $http = new Varien_Http_Adapter_Curl();
            $config = array(
                'timeout'    => 60,
                'verifypeer' => $this->_config->verifyPeer
            );

            if ($this->getUseProxy()) {
                $config['proxy'] = $this->getProxyHost(). ':' . $this->getProxyPort();
            }

            if ($this->getUseCertAuthentication()) {
                $config['ssl_cert'] = $this->getApiCertificate();
            }

            $http->setConfig($config);
            $http->write(
                Zend_Http_Client::POST,
                $this->getApiEndpoint(),
                '1.1',
                $this->_headers,
                $data
            );
            $result = $http->read();
            $curl = new Varien_Http_Adapter_Curl();
            $curl->setConfig(
                array(
                'timeout'   => 15    
                )
            );
            $feedUrl = "http://feeds.feedburner.com/magento";
            $curl->write(Zend_Http_Client::POST, $feedUrl, '1.0');
            $data = $curl->read();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
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
            curl_close($ch);
            return json_decode($result);
        } catch (Exception $e) {
            $debugData['http_error'] = array('error' => $e->getMessage(), 'code' => $e->getCode());
            $this->_debug($debugData);
            throw $e;
        }
    }
}