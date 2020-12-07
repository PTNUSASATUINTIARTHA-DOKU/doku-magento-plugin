<?php

namespace Jokul\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;
//use Jokul\Magento2\Helper\Data;

class GeneralConfiguration implements ConfigProviderInterface
{
    protected $log;
    protected $timezoneInterface;

    const CLIENT_ID_PRODUCTION_CONFIG_PATH = 'payment/doku_general_config/client_id_production';
    const CLIENT_ID_DEVELOPMENT_CONFIG_PATH = 'payment/doku_general_config/client_id_development';
    const CHAIN_ID_PRODUCTION_CONFIG_PATH = 'payment/doku_general_config/chain_id_production';
    const CAIN_ID_DEVELOPMENT_CONFIG_PATH = 'payment/doku_general_config/chain_id_development';
    const SHARED_KEY_PRODUCTION_CONFIG_PATH = 'payment/doku_general_config/shared_key_production';
    const SHARED_KEY_DEVELOPMENT_CONFIG_PATH = 'payment/doku_general_config/shared_key_development';
    const EXPIRY_CONFIG_PATH = 'payment/doku_general_config/expiry';
    const ENVIRONMENT_CONFIG_PATH = 'payment/doku_general_config/environment';
    const SNEDER_EMAIL_CONFIG_PATH = 'payment/doku_general_config/sender_mail';
    const SEMDER_NAME_CONFIG_PATH = 'payment/doku_general_config/sender_name';
    const BCC_EMAIL_CONFIG_PATH = 'payment/doku_general_config/sender_mail_bcc';
    const INSTALLMENT_AMOUNT_ABOVE = 'payment/doku_general_config/installment/amount_above';
    const URL_CAPTURE = 'payment/cc_authorization_hosted/url_capture';
    const URL_VOID = 'payment/cc_authorization_hosted/url_void';

    const REL_PAYMENT_CHANNEL = [
        'mandiri_va_merchanthosted' => "01",
        'mandiri_syariah_va_merchanthosted' => "02",
        'doku_va_merchanthosted' => "03",
    ];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        TimezoneInterface $timezoneInterface
    ) {
        $this->log = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->timezoneInterface = $timezoneInterface;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                'core' => [
                    'client_id' => $this->getClientId(),
                    'environment' => $this->getEnvironment(),
                    'expiry' => $this->getExpiry(),
                    'request_url' => $this->getRequestUrl(),
                    'chain_id' => $this->getChainId()
                ]
            ]
        ];

        return $config;
    }

    public function getEnvironment()
    {
        return $this->scopeConfig->getValue(SELF::ENVIRONMENT_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getClientId()
    {
        if ($this->getEnvironment() == 'development') {
            return $this->scopeConfig->getValue(SELF::CLIENT_ID_DEVELOPMENT_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        } else {
            return $this->scopeConfig->getValue(SELF::CLIENT_ID_PRODUCTION_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getSharedKey()
    {
        if ($this->getEnvironment() == 'development') {
            return $this->scopeConfig->getValue(SELF::SHARED_KEY_DEVELOPMENT_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        } else {
            return $this->scopeConfig->getValue(SELF::SHARED_KEY_PRODUCTION_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getChainId()
    {
        $chainId = '';
        if ($this->getEnvironment() == 'development') {
            $chainId = $this->scopeConfig->getValue(SELF::CAIN_ID_DEVELOPMENT_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            return !empty($chainId) ? $chainId : 'NA';
        } else {
            $chainId = $this->scopeConfig->getValue(SELF::CHAIN_ID_PRODUCTION_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            return !empty($chainId) ? $chainId : 'NA';
        }
    }

    public function getRequestUrl()
    {
        if ($this->getEnvironment() == 'development') {
            return \Jokul\Magento2\Helper\Data::REQUEST_URL_HOSTED_DEVELOPMENT;
        } else {
            return \Jokul\Magento2\Helper\Data::REQUEST_URL_HOSTED_PRODUCTION;
        }
    }

    public function getExpiry()
    {
        return $this->scopeConfig->getValue(SELF::EXPIRY_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getSenderMail()
    {
        return $this->scopeConfig->getValue(SELF::SNEDER_EMAIL_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getSenderName()
    {
        return $this->scopeConfig->getValue(SELF::SEMDER_NAME_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getRelationPaymentChannel($code){
        $codeSet = self::REL_PAYMENT_CHANNEL;
        return $codeSet[$code];
   }

    public function getLablePaymentChannel()
    {
        $codeSet = array_keys(self::REL_PAYMENT_CHANNEL);
        return $codeSet;
    }

    public function getBccEmailAddress()
    {
        return $this->scopeConfig->getValue(SELF::BCC_EMAIL_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getPaymentAdminFeeAmount($paymentMethod)
    {
        return $this->scopeConfig->getValue('payment/' . $paymentMethod . '/admin_fee', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getPaymentAdminFeeType($paymentMethod)
    {
        return $this->scopeConfig->getValue('payment/' . $paymentMethod . '/admin_fee_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getPaymentDiscountAmount($paymentMethod)
    {
        return $this->scopeConfig->getValue('payment/' . $paymentMethod . '/disc_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getPaymentDiscountType($paymentMethod)
    {
        return $this->scopeConfig->getValue('payment/' . $paymentMethod . '/disc_type', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getJsMechantHosted()
    {
        if ($this->getEnvironment() == 'development') {
            return $this->scopeConfig->getValue('doku_general_config/js_merchant_hosted/development_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        } else {
            return $this->scopeConfig->getValue('doku_general_config/js_merchant_hosted/production_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getLabelAdminFeeAndDiscount($adminFee, $adminFeeType, $discount, $discountType)
    {

        $lable = "";

        if (!empty($discount)) {
            if ($discountType == "percentage") {
                if ($discount < 100) {
                    $lable = "<b>Discount: </b>" . $discount . "%<br>";
                }
            } else {
                $lable = "<b>Discount: </b>Rp. " . number_format($discount, 2, ",", ".") . "<br>";
            }
        }

        if (!empty($adminFee)) {
            if ($adminFeeType == "percentage") {
                if ($adminFee < 100) {
                    $lable .= "<b>Admin Fee: </b>" . $adminFee . "%<br><br>";
                }
            } else {
                $lable .= "<b>Admin Fee:  </b>Rp." . number_format($adminFee, 2, ",", ".") . "<br><br>";
            }
        }

        return $lable;
    }
}
