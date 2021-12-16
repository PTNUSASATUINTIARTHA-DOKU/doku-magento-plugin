<?php

namespace Jokul\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class GeneralConfiguration implements ConfigProviderInterface
{
    protected $log;
    protected $timezoneInterface;

    const CLIENT_ID_PRODUCTION_CONFIG_PATH = 'payment/doku_general_config/client_id_production';
    const CLIENT_ID_DEVELOPMENT_CONFIG_PATH = 'payment/doku_general_config/client_id_development';
    const SHARED_KEY_PRODUCTION_CONFIG_PATH = 'payment/doku_general_config/shared_key_production';
    const SHARED_KEY_DEVELOPMENT_CONFIG_PATH = 'payment/doku_general_config/shared_key_development';
    const EXPIRY_CONFIG_PATH = 'payment/doku_general_config/expiry';
    const ENVIRONMENT_CONFIG_PATH = 'payment/doku_general_config/environment';
    const SENDER_EMAIL_CONFIG_PATH = 'payment/doku_general_config/sender_mail';
    const SENDER_NAME_CONFIG_PATH = 'payment/doku_general_config/sender_name';
    const BCC_EMAIL_CONFIG_PATH = 'payment/doku_general_config/sender_mail_bcc';

    const REL_PAYMENT_CHANNEL = [
        'mandiri_va' => "01",
        'mandiri_syariah_va' => "02",
        'doku_va' => "03",
        'bca_va' => "04",
        'permata_va' => "05",
        'doku_cc' => "06",
        'alfamart' => "07",
        'bri_va' => "08",
        'doku_checkout_merchanthosted' => "09"
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
                    'expiry' => $this->getExpiry()
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

    public function getExpiry()
    {
        return $this->scopeConfig->getValue(SELF::EXPIRY_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getSenderMail()
    {
        return $this->scopeConfig->getValue(SELF::SENDER_EMAIL_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getSenderName()
    {
        return $this->scopeConfig->getValue(SELF::SENDER_NAME_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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

    public function getLabelAdminFeeAndDiscount($adminFee, $adminFeeType, $discount, $discountType)
    {

        $label = "";

        if (!empty($discount)) {
            if ($discountType == "percentage") {
                if ($discount < 100) {
                    $label = "<b>Discount: </b>" . $discount . "%<br>";
                }
            } else {
                $label = "<b>Discount: </b>Rp. " . $discount . "<br>";
            }
        }

        if (!empty($adminFee)) {
            if ($adminFeeType == "percentage") {
                if ($adminFee < 100) {
                    $label .= "<b>Admin Fee: </b>" . $adminFee . "%<br><br>";
                }
            } else {
                $label .= "<b>Admin Fee:  </b>Rp." . $adminFee . "<br><br>";
            }
        }

        return $label;
    }
}
