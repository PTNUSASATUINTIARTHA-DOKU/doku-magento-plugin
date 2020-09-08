<?php

namespace Jokul\Magento2\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\Mail\Template\TransportBuilder;
use \Magento\Framework\DataObject;
use \Jokul\Magento2\Model\GeneralConfiguration;
use \Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{

    protected $transportBuilder;
    protected $dataObject;
    protected $config;
    protected $logger;


    const REQUEST_URL_HOSTED_DEVELOPMENT = 'https://staging.doku.com/Suite/Receive';
    const REQUEST_URL_HOSTED_PRODUCTION = 'https://pay.doku.com/Suite/Receive';

    const REQUEST_URL_MIP_DEVELOPMENT = 'https://staging.doku.com/Suite/ReceiveMIP';
    const REQUEST_URL_MIP_PRODUCTION = 'https://pay.doku.com/Suite/ReceiveMIP';

    const PREFIX_ENV_DEVELOPMENT ='http://api-sit.doku.com';
    const PREFIX_ENV_PRODUCTION ='http://jokul.doku.com';

    const CHECKSTATUS_URL_DEVELOPMENT = "https://staging.doku.com/Suite/CheckStatus";
    const CHECKSTATUS_URL_PRODUCTION = "https://gts.doku.com/Suite/CheckStatus";

    const PAYMENT_URL_DEVELOPMENT = 'https://staging.doku.com/api/payment/paymentMip';
    const PAYMENT_URL_PRODUCTION = 'https://pay.doku.com/api/payment/paymentMip';

    const DIRECT_PAYMENT_URL_PRODUCTION = 'https://pay.doku.com/api/payment/PaymentMIPDirect';
    const DIRECT_PAYMENT_URL_DEVELOPMENT = 'https://staging.doku.com/api/payment/PaymentMIPDirect';

    public function __construct(
        TransportBuilder $transportBuilder,
        DataObject $dataObject,
        GeneralConfiguration $generalConfiguration,
        LoggerInterface $loggerInterface
    ) {
        $this->logger = $loggerInterface;
        $this->transportBuilder = $transportBuilder;
        $this->dataObject = $dataObject;
        $this->config = $generalConfiguration;
    }

    public function doCreateWords($data)
    {
        if (!empty($data['device_id']))
            if (!empty($data['pairing_code']))
                return sha1($data['amount'] . $data['mallid'] . $data['sharedid'] . $data['invoice'] . $data['currency'] . $data['token'] . $data['pairing_code'] . $data['device_id']);
            else
                return sha1($data['amount'] . $data['mallid'] . $data['sharedid'] . $data['invoice'] . $data['currency'] . $data['device_id']);
        else if (!empty($data['pairing_code']))
            return sha1($data['amount'] . $data['mallid'] . $data['sharedid'] . $data['invoice'] . $data['currency'] . $data['token'] . $data['pairing_code']);
        else if (!empty($data['currency']))
            return sha1($data['amount'] . $data['mallid'] . $data['sharedid'] . $data['invoice'] . $data['currency']);
        else if (!empty($data['statuscode']))
            return sha1($data['amount'] . $data['sharedid'] . $data['invoice'] . $data['statuscode']);
        else if (!empty($data['resultmsg']) && !empty($data['verifystatus']))
            return sha1($data['amount'] . $data['mallid'] . $data['sharedid'] . $data['invoice'] . $data['resultmsg'] . $data['verifystatus']);
        else if (!empty($data['check_status']))
            return sha1($data['mallid'] . $data['sharedid'] . $data['invoice']);
        else
            return sha1($data['amount'] . $data['mallid'] . $data['sharedid'] . $data['invoice']);
    }

    public function sendRequest($dataParam, $url)
    {

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataParam));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($ch);
        curl_close($ch);

        preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $data);
        $xml = new \SimpleXMLElement($data);
        $array = json_decode(json_encode((array) $xml), TRUE);

        return $array;
    }

    public function getClientIp()
    {
        return $_SERVER['REMOTE_ADDR'] ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER['HTTP_CLIENT_IP']);
    }

    public function sendDokuEmailOrder($order, $vaNumber = "", $dokusTransactionOrder = array(), $isSuccessOrder = true, $expiryStoreDate = "")
    {
        $this->logger(get_class($this) . " ====== Email Sender ====== Preparing", 'DOKU_send_email');
        try {
            $paymentChannelLabel = $order->getPayment()->getMethodInstance()->getTitle();

            $discountValue = "0,00";
            if (!empty($dokusTransactionOrder['discount_trx_amount'])) {
                $discountValue = number_format($dokusTransactionOrder['discount_trx_amount'], 2, ",", ".");
                if ($dokusTransactionOrder['discount_type'] == 'percentage') {
                    $percantegeLable = (int) $dokusTransactionOrder['discount_amount'] < 100 ? $dokusTransactionOrder['discount_amount'] : 100;
                    $discountValue .= " (" . $percantegeLable . "%)";
                }
            }

            $adminFeeValue = "0,00";
            if (!empty($dokusTransactionOrder['admin_fee_trx_amount'])) {
                $adminFeeValue = number_format($dokusTransactionOrder['admin_fee_trx_amount'], 2, ",", ".");
                if ($dokusTransactionOrder['admin_fee_type'] == 'percentage') {
                    $percantegeLable = (int) $dokusTransactionOrder['admin_fee_amount'] < 100 ? $dokusTransactionOrder['admin_fee_amount'] : 100;
                    $adminFeeValue .= " (" . $percantegeLable . "%)";
                }
            }

            $emailParams = [
                'subject' => "Doku Transaction (" . $order->getIncrementId() . " - " . $paymentChannelLabel . ")",
                'customerName' => $order->getCustomerName(),
                'customerEmail' => $order->getCustomerEmail(),
                'storeName' => $order->getStoreName(),
                'orderId' => $order->getIncrementId(),
                'vaNumber' => !empty($vaNumber) ? $vaNumber : $dokusTransactionOrder['va_number'],
                'amount' => number_format($dokusTransactionOrder['doku_grand_total'], 2, ",", "."),
                'discountValue' => $discountValue,
                'adminFeeValue' => $adminFeeValue,
                'paymentChannel' => $paymentChannelLabel,
                'expiry' => date('d F Y, H:i', strtotime($expiryStoreDate))
            ];

            $this->dataObject->setData($emailParams);

            $sender = [
                'name' => $this->config->getSenderName(),
                'email' => $this->config->getSenderMail(),
            ];

            $template = "success_template";
            if ($isSuccessOrder) {
                if ($dokusTransactionOrder['payment_channel_id'] == '41') {
                    $template = 'mandiri_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '42') {
                    $template = 'mandiri_syariah_va_template';
                }  else if ($dokusTransactionOrder['payment_channel_id'] == '32') {
                    $template = 'cimb_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '33') {
                    $template = 'danamon_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '36') {
                    $template = 'permata_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '29') {
                    $template = 'default_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '34') {
                    $template = 'default_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '35') {
                    $template = 'default_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == '31') {
                    $template = 'default_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == "22") {
                    $template = 'default_va_template';
                } else if ($dokusTransactionOrder['payment_channel_id'] == "03") {
                    $template = 'klik_bca_template';
                }
            } else {
                $template = "failed_template";
            }

            $this->logger(get_class($this) . " ====== Email Sender ====== Using template: " . $template, 'DOKU_send_email');

            $this->transportBuilder->setTemplateIdentifier($template)->setFrom($sender)
                ->addTo($order->getCustomerEmail(), $order->getCustomerName())
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $order->getStoreId()
                    ]
                )
                ->setTemplateVars(['data' => $this->dataObject]);
            $bccEmailAddress = [];

            if ($this->config->getBccEmailAddress() !== null) {
                $bccEmailAddress = explode(",", str_replace(" ", "", $this->config->getBccEmailAddress()));
                $this->transportBuilder->addBcc($bccEmailAddress[0]);
                $this->logger(get_class($this) . " ====== Email Sender ====== Bcc Listing: ", 'DOKU_send_email');
                $this->logger(get_class($this) . print_r($bccEmailAddress,TRUE), 'DOKU_send_email');
            }
            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();

            if ($this->config->getBccEmailAddress() !== null) {
                foreach ($bccEmailAddress as $bccIdx => $bccVal) {
                    if ($bccIdx != 0) {
                        $transport = $this->transportBuilder->setTemplateIdentifier($template)->setFrom($sender)
                            ->addTo($bccVal, "admin")
                            ->setTemplateOptions(
                                [
                                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                                    'store' => $order->getStoreId()
                                ]
                            )
                            ->setTemplateVars(['data' => $this->dataObject])
                            ->getTransport();
                        $transport->sendMessage();
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger(get_class($this) . " ====== Email Sender ====== Failure: " . $e->getMessage(), 'DOKU_send_email');
            return false;
        }
    }

    public function doGeneratePaycode($data,$paymentchannel)
    {

        $prefixdev      = SELF::PREFIX_ENV_DEVELOPMENT;
        $prefixprod     = SELF::PREFIX_ENV_PRODUCTION;
        $path           = '';

        if($paymentchannel == 41 ){
            $path = "/mandiri-virtual-account/v1/payment-code";
        }elseif ($paymentchannel == 42 ){
            $path = "/bsm-virtual-account/v1/payment-code";
        }

        if ($this->config->getEnvironment() == 'development') {
            $url = $prefixdev.$path;
        }else{
            $url = $prefixprod.$path;
        }
        $this->logger->info('===== Request controller VA GATEWAY ===== URL : '.$url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseJson = curl_exec($ch);

        curl_close($ch);

        if (is_string($responseJson)) {
            return json_decode($responseJson, true);
        } else {
            return $responseJson;
        }
    }

    public function doPayment($data)
    {

        $url = SELF::PAYMENT_URL_PRODUCTION;

        if ($this->config->getEnvironment() == 'development') {
            $url = SELF::PAYMENT_URL_DEVELOPMENT;
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . json_encode($data));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $responseJson = curl_exec($ch);

        curl_close($ch);

        if (is_string($responseJson)) {
            return json_decode($responseJson, true);
        } else {
            return $responseJson;
        }
    }

    public function getTotalAdminFeeAndDisc($adminFee, $adminFeeType, $discountAmount, $discountType, $grandTotal)
    {

        $totalAdminFee = 0;
        if (!empty($adminFee)) {
            $multipleFee = 0;
            if ($adminFeeType == 'percentage') {
                if ($adminFee < 100 && $adminFee > 0) {
                    $multipleFee = $adminFee / 100;
                }
                $totalAdminFee = $grandTotal * $multipleFee;
            } else {
                $totalAdminFee = $adminFee;
            }
        }

        $totalDisc = 0;
        if (!empty($discountAmount)) {
            $multipleDisc = 0;
            if ($discountType == 'percentage') {
                if ($discountAmount < 100 && $discountAmount > 0) {
                    $multipleDisc = $discountAmount / 100;
                }
                $totalDisc = $grandTotal * $multipleDisc;
            } else {
                $totalDisc = $discountAmount;
            }
        }

        $total = array('total_admin_fee' => $totalAdminFee, 'total_discount' => $totalDisc);

        return $total;
    }

    public function checkStatusOrder($dataParam)
    {

        $url = SELF::CHECKSTATUS_URL_PRODUCTION;

        if ($this->config->getEnvironment() == 'development') {
            $url = SELF::CHECKSTATUS_URL_DEVELOPMENT;
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataParam));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($ch);
        curl_close($ch);

        try {
            $xml = new \SimpleXMLElement($data);
            $response = json_decode(json_encode((array) $xml), TRUE);
            $response["request_status"] = true;
            return $response;
        } catch (\Exception $e) {
            return array("request_status" => false, "response" => $data);
        }
    }

    public function doPrePayment($data)
    {

        $url = SELF::DIRECT_PAYMENT_URL_PRODUCTION;

        if ($this->config->getEnvironment() == 'development') {
            $url = SELF::DIRECT_PAYMENT_URL_DEVELOPMENT;
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . json_encode($data));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $responseJson = curl_exec($ch);

        curl_close($ch);

        if (is_string($responseJson)) {
            return json_decode($responseJson, true);
        } else {
            return $responseJson;
        }
    }

    public function doCapture($dataParam)
    {

        $url = $this->config->getURLCapture();
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataParam));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $this->logger(get_class($this) . " ====== CAPTURE PARAM: " . json_encode($dataParam), 'DOKU_capture');
        $data = curl_exec($ch);
        curl_close($ch);

        try {
            $this->logger(get_class($this) . " ====== CAPTURE RESPONSE: " . $data, 'DOKU_capture');
            $xml = new \SimpleXMLElement($data);
            $response = json_decode(json_encode((array) $xml), TRUE);
            return $response;
        } catch (\Exception $e) {
            $this->logger(get_class($this) . " ====== CAPTURE RESPONSE: " . $e->getMessage(), 'DOKU_capture');
            return false;
        }
    }

    public function doVoid($dataParam)
    {

        $url = $this->config->getURLVoid();
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataParam));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $this->logger(get_class($this) . " ====== VOID PARAM: " . json_encode($dataParam), 'DOKU_void');
        $response = curl_exec($ch);
        curl_close($ch);

        try {
            $this->logger(get_class($this) . " ====== VOID RESPONSE: " . $response, 'DOKU_void');
            return $response;
        } catch (\Exception $e) {
            $this->logger(get_class($this) . " ====== VOID RESPONSE: " . $e->getMessage(), 'DOKU_void');
            return false;
        }
    }

    public function doRefund($dataParam)
    {

        $url = $this->config->getURLVoid();
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataParam));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $this->logger(get_class($this) . " ====== VOID PARAM: " . json_encode($dataParam), 'DOKU_void');
        $response = curl_exec($ch);
        curl_close($ch);

        try {
            $this->logger(get_class($this) . " ====== VOID RESPONSE: " . $response, 'DOKU_void');
            return $response;
        } catch (\Exception $e) {
            $this->logger(get_class($this) . " ====== VOID RESPONSE: " . $e->getMessage(), 'DOKU_void');
            return false;
        }
    }

    public function logger($var, $file)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . "/var/log/$file.log");
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($var);
    }
}
