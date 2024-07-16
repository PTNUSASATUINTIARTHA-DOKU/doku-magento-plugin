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
    const SUB_ACCOUNT_STATUS_CONFIG_PATH = 'payment/doku_general_config/sub_account_active';
    const SUB_ACCOUNT_ID_CONFIG_PATH = 'payment/doku_general_config/sub_account_id';

    const REL_PAYMENT_CHANNEL = [
        'doku_credit_card' => "01",
        'doku_dokuva' => "02",
        'doku_bcava' => "03",
        'doku_mandiriva' => "04",
        'doku_briva' => "05",
        'doku_bniva' => "06",
        'doku_permatava' => "07",
        'doku_cimbva' => "08",
        'doku_danamonva' => "09",
        'doku_bsiva' => "10",
        'doku_maybankva' => "11",
        'doku_ovo' => "12",
        'doku_shopeepay' => "13",
        'doku_dana' => "14",
        'doku_dokuwallet' => "15",
        'doku_linkaja' => "16",
        'doku_indomaret' => "17",
        'doku_alfa' => "18",
        'doku_jenius' => "19",
        'doku_kredivo' => "20",
        'doku_akulaku' => "21",
        'doku_indodana' => "22",
        'doku_briceria' => "23",
        'doku_octoclicks' => "24",
        'doku_epaybri' => "25",
        'doku_danamonOB' => "26",
        'doku_permatanet' => "27",
        'doku_directdebitbri' => "28",
        'doku_directdebitcimb' => "29",
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
    
    public function getPaymentCode($paymentMethod)
    {
        return $this->scopeConfig->getValue('payment/' . $paymentMethod . '/payment_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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

    public function getPaymentStatusSubAccount($paymentMethod)
    {
        return $this->scopeConfig->getValue(SELF::SUB_ACCOUNT_STATUS_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getPaymentSubAccountId($paymentMethod)
    {
        return $this->scopeConfig->getValue(SELF::SUB_ACCOUNT_ID_CONFIG_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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
