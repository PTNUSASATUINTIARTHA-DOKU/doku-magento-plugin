<?php

namespace Jokul\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Jokul\Magento2\Model\GeneralConfiguration;

class JokulConfigProvider implements ConfigProviderInterface
{
    protected $_scopeConfig;
    protected $_generalConfiguration;
    const CC_THEME_LANGUAGE = 'payment/doku_cc/languageSelect';
    const CC_THEME_BACKGROUND_COLOR = 'payment/doku_cc/ccFormBackgroundColor';
    const CC_THEME_FONT_COLOR = 'payment/doku_cc/ccFormLabelColor';
    const CC_THEME_BTN_BACKGROUND_COLOR = 'payment/doku_cc/ccFormButtonBackgroundColor';
    const CC_THEME_BTN_FONT_COLOR = 'payment/doku_cc/ccFormButtonFontColor';
    const AUTO_REDIRECT_ID_CONFIG_PATH = 'payment/doku_checkout_merchanthosted/autoRedirect';
    const AUTHORIZE_CONFIG_PATH = 'payment/doku_checkout_merchanthosted/authorize';

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

    public function getAutoRedirect(){
        return $this->_scopeConfig->getValue(SELF::AUTO_REDIRECT_ID_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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

    public function getAuthorizeStatus(){
        return $this->_scopeConfig->getValue(SELF::AUTHORIZE_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getCheckoutPaymentSharedkey($paymentMethod){
        return $this->_scopeConfig->getValue('payment/'.$paymentMethod.'/sharedkey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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

    public function getFooterMessage(){
        return $this->_scopeConfig->getValue('payment/alfamart/footer_message', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
   }

    public function getCCThemelanguage()
    {
        return $this->_scopeConfig->getValue(SELF::CC_THEME_LANGUAGE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getCCThemeBackground_color()
    {
        return $this->_scopeConfig->getValue(SELF::CC_THEME_BACKGROUND_COLOR, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function getCCThemeFont_color()
    {
        return $this->_scopeConfig->getValue(SELF::CC_THEME_FONT_COLOR, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function getCCThemeButton_background_color()
    {
        return $this->_scopeConfig->getValue(SELF::CC_THEME_BTN_BACKGROUND_COLOR, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    public function getCCThemeButton_font_color()
    {
        return $this->_scopeConfig->getValue(SELF::CC_THEME_BTN_FONT_COLOR, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getConfig()
    {
        $paymentList = \Jokul\Magento2\Model\GeneralConfiguration::REL_PAYMENT_CHANNEL;

        $configData = array();

        foreach($paymentList as $index => $value){
                $configData['payment'][$index]['description'] = $this->getPaymentDescription($index);
                $configData['payment'][$index]['admin_fee'] = $this->getPaymentAdminFeeAmount($index);
                $configData['payment'][$index]['admin_fee_type'] = $this->getPaymentAdminFeeType($index);
                $configData['payment'][$index]['disc_amount'] = $this->getPaymentDiscountAmount($index);
                $configData['payment'][$index]['disc_type'] = $this->getPaymentDiscountType($index);
        }

        return $configData;
    }
}
