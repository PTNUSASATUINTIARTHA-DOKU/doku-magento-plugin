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

    const PREFIX_ENV_DEVELOPMENT = 'https://api-sandbox.doku.com';
    const PREFIX_ENV_PRODUCTION = 'https://api-jokul.doku.com';

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

    public function generateRedirectSignature($data)
    {

        return base64_encode(hash('sha256', implode("|", $data), true));
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
        $this->logger(get_class($this) . " ===== Jokul - Email Sender ===== Preparing Data", 'DOKU_send_email');
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

            $requestParams = json_decode($dokusTransactionOrder['request_params'], true);
            $howToPayUrl = $requestParams['response']['virtual_account_info']['how_to_pay_api'];
            $howToPayUrl = str_replace("\\", "", $howToPayUrl);

            $emailParams = [
                'subject' => "Complete Your Payment for Order: " . $order->getIncrementId() . " (" . $paymentChannelLabel . ")",
                'customerName' => $order->getCustomerName(),
                'customerEmail' => $order->getCustomerEmail(),
                'storeName' => $order->getStoreName(),
                'orderId' => $order->getIncrementId(),
                'vaNumber' => !empty($vaNumber) ? $vaNumber : $dokusTransactionOrder['va_number'],
                'amount' => number_format($dokusTransactionOrder['doku_grand_total'], 2, ",", "."),
                'discountValue' => $discountValue,
                'adminFeeValue' => $adminFeeValue,
                'paymentChannel' => $paymentChannelLabel,
                'expiry' => date('d F Y, H:i', strtotime($expiryStoreDate)),
                'paymentInstructions' => $this->getHowToPay($howToPayUrl)
            ];

            $this->dataObject->setData($emailParams);

            $this->logger(get_class($this) . " ===== Jokul - Email Sender ===== Email params: " . print_r($emailParams, true), 'DOKU_send_email');

            $sender = [
                'name' => $this->config->getSenderName(),
                'email' => $this->config->getSenderMail(),
            ];

            $template = "success_template";
            $vaChannels = array("01", "02", "03", "04", "05");
            if ($isSuccessOrder) {
                if (in_array($dokusTransactionOrder['payment_channel_id'], $vaChannels)) {
                    $template = 'default_va_template';
                }
            } else {
                $template = "failed_template";
            }

            $this->logger(get_class($this) . " ===== Jokul - Email Sender ===== Template used: " . $template, 'DOKU_send_email');

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
                $this->logger(get_class($this) . " ===== Jokul - Email Sender ===== Bcc Listing: ", 'DOKU_send_email');
                $this->logger(get_class($this) . print_r($bccEmailAddress, TRUE), 'DOKU_send_email');
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
            $this->logger(get_class($this) . " ===== Jokul - Email Sender ===== Failure reason: " . $e->getMessage(), 'DOKU_send_email');
            return false;
        }
    }

    public function doGeneratePaycode($params, $data, $signature)
    {
        $prefixdev      = SELF::PREFIX_ENV_DEVELOPMENT;
        $prefixprod     = SELF::PREFIX_ENV_PRODUCTION;
        $path           = $params['requestTarget'];

        if ($this->config->getEnvironment() == 'development') {
            $url = $prefixdev . $path;
        } else {
            $url = $prefixprod . $path;
        }
        $this->logger->info('===== Jokul - Generate VA Number ===== Jokul URL to hit: ' . $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Signature:' . $signature,
            'Request-Id:' . $params['requestId'],
            'Client-Id:' . $params['clientId'],
            'Request-Timestamp:' . $params['requestTimestamp'],
            'Request-Target:' . $params['requestTarget']
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseJson = curl_exec($ch);

        $this->logger->info('===== Jokul - Generate VA Number ===== Response from Jokul: ' . print_r($responseJson, true));

        curl_close($ch);

        if (is_string($responseJson)) {
            return json_decode($responseJson, true);
        } else {
            return $responseJson;
        }
    }

    public function doCreateRequestSignature($params, $body)
    {
        $body = str_replace(array("\r", "\n"), array("\\r", "\\n"), json_encode($body));
        return $this->doEncrypt($params, $body);
    }

    public function doCreateNotifySignature($params, $body)
    {
        return $this->doEncrypt($params, $body);
    }

    private function doEncrypt($params, $body)
    {
        $digest = base64_encode(hash("sha256", $body, True));
        $signatureComponent = "Client-Id:" . $params['clientId'] . "\n" .
            "Request-Id:" . $params['requestId'] . "\n" .
            "Request-Timestamp:" . $params['requestTimestamp'] . "\n" .
            "Request-Target:" . $params['requestTarget'] . "\n" .
            "Digest:" . htmlspecialchars_decode($digest);

        $signature = base64_encode(hash_hmac('SHA256', htmlspecialchars_decode($signatureComponent), htmlspecialchars_decode($params['key']), True));
        return "HMACSHA256=" . $signature;
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

    public function getHowToPay($url)
    {
        $ch = curl_init();
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',

        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $responseJson = json_decode($response, true);

        $this->logger(get_class($this) . "===== Jokul - Get How to Pay ===== Response from Jokul: " . $url . " => " . $response, 'DOKU_send_email');

        return $responseJson['payment_instruction'];
    }

    function guidv4($data = null)
    {
        $data = $data ?? random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function logger($var, $file)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . "/var/log/$file.log");
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($var);
    }
        public function doRequestCcPaymentForm($params, $data, $signature)
    {
        $prefixdev      = SELF::PREFIX_ENV_DEVELOPMENT;
        $prefixprod     = SELF::PREFIX_ENV_PRODUCTION;
        $path           = $params['requestTarget'];

        if ($this->config->getEnvironment() == 'development') {
            $url = $prefixdev . $path;
        } else {
            $url = $prefixprod . $path;
        }
        $this->logger->info('===== Request controller Credit Card GATEWAY ===== URL : ' . $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Signature:' . $signature,
            'Request-Id:' . $params['requestId'],
            'Client-Id:' . $params['clientId'],
            'Request-Timestamp:' . $params['requestTimestamp'],
            'Request-Target:' . $params['requestTarget']
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseJson = curl_exec($ch);

        $this->logger->info('===== Request controller Credit Card GATEWAY ===== Response he: ' . json_encode($responseJson, JSON_PRETTY_PRINT));

        curl_close($ch);

        if (is_string($responseJson)) {
            $this->logger->info('===== Request controller Credit Card GATEWAY ===== Response he 1: ' . json_encode(json_decode($responseJson, true), JSON_PRETTY_PRINT));
            return json_decode($responseJson, true);
        } else {
            $this->logger->info('===== Request controller Credit Card GATEWAY ===== Response he: 2 ' . json_encode($responseJson, JSON_PRETTY_PRINT));
            return $responseJson;
        }
    }
}
