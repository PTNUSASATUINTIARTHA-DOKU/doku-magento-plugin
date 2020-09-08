<?php

namespace Jokul\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Jokul\Magento2\Model\GeneralConfiguration;

class DokuMerchanthostedConfigProvider implements ConfigProviderInterface
{
    protected $_scopeConfig;
    protected $_generalConfiguration;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GeneralConfiguration $generalConfiguration
    ){
        $this->_scopeConfig = $scopeConfig;
        $this->_generalConfiguration = $generalConfiguration;
    }

    
    public function getRelationPaymentChannel($code){
         return $this->_generalConfiguration->getRelationPaymentChannel($code);
    }
    
    public function getSharedKey(){
         return $this->_generalConfiguration->getSharedKey();
    }
    
    public function getPaymentDescription($paymentMethod){
        $additionalDesc = $this->_generalConfiguration->getLabelAdminFeeAndDiscount(
                $this->getPaymentAdminFeeAmount($paymentMethod), 
                $this->getPaymentAdminFeeType($paymentMethod), 
                $this->getPaymentDiscountAmount($paymentMethod), 
                $this->getPaymentDiscountType($paymentMethod)
        );
        return $additionalDesc . $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/description', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    
    public function getAllConfig(){
        return array('payment' => array_merge($this->_generalConfiguration->getConfig()['payment'], $this->getConfig()['payment']));
    }
    
    public function getPaymentCodePrefix($paymentMethod){
       return $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/payment_code_prefix', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    
    public function getPaymentAdminFeeAmount($paymentMethod){
         return $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/admin_fee', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    
    public function getPaymentAdminFeeType($paymentMethod){
         return $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/admin_fee_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    
    public function getPaymentDiscountAmount($paymentMethod){
         return $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/disc_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    
    public function getPaymentDiscountType($paymentMethod){
         return $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/disc_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getConfig()
    {        
        $paymentList = \Jokul\Magento2\Model\GeneralConfiguration::REL_PAYMENT_CHANNEL;
        
        $configData = array();
        
        foreach($paymentList as $index => $value){
            $expIdx = explode("_", $index);
            if (end($expIdx) == 'merchanthosted') {
                $configData['payment'][$index]['description'] = $this->getPaymentDescription($index);
                $configData['payment'][$index]['admin_fee'] = $this->getPaymentAdminFeeAmount($index);
                $configData['payment'][$index]['admin_fee_type'] = $this->getPaymentAdminFeeType($index);
                $configData['payment'][$index]['disc_amount'] = $this->getPaymentDiscountAmount($index);
                $configData['payment'][$index]['disc_type'] = $this->getPaymentDiscountType($index);
            }
        }
        
        return $configData;
    }
}
