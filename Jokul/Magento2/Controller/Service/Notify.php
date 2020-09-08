<?php

namespace Jokul\Magento2\Controller\Service;

use \Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use \Psr\Log\LoggerInterface;
use Jokul\Magento2\Model\GeneralConfiguration;
use Jokul\Magento2\Helper\Data;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Jokul\Magento2\Api\TransactionRepositoryInterface;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Notify extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    const ORDER_STATUS_CHALLENGE = 'challenge';

    protected $resourceConnection;
    protected $order;
    protected $generalConfiguration;
    protected $logger;
    protected $invoiceService;
    protected $builderInterface;
    protected $Magento2Helper;
    protected $timezoneInterface;
    protected $transactionRepository;

    public function __construct(
        LoggerInterface $loggerInterface,
        Context $context,
        ResourceConnection $resourceConnection,
        Order $order,
        BuilderInterface $_builderInterface,
        InvoiceService $_invoiceService,
        GeneralConfiguration $_generalConfiguration,
        Data $_Magento2Helper,
        TimezoneInterface $timezoneInterface,
        TransactionRepositoryInterface $transactionRepository
    ) {
        parent::__construct(
            $context
        );

        $this->resourceConnection = $resourceConnection;
        $this->order = $order;
        $this->builderInterface = $_builderInterface;
        $this->invoiceService = $_invoiceService;
        $this->logger = $loggerInterface;
        $this->generalConfiguration = $_generalConfiguration;
        $this->Magento2Helper = $_Magento2Helper;
        $this->timezoneInterface = $timezoneInterface;
        $this->transactionRepository = $transactionRepository;
    }

    public function execute()
    {
        $this->logger->info('===== Notify Controller ===== Start');
        try {
            // Start - Build Data Process
            $parsedRaw = array();
            $rawbody = urldecode(file_get_contents('php://input'));
            parse_str($rawbody, $parsedRaw);

            $this->logger->info('NOTIFY RAW PARAMS : ' . $rawbody);

            $postjson = json_encode($rawbody, JSON_PRETTY_PRINT);
            $postData = json_decode($rawbody, true);

            $this->logger->info('===== Notify Controller ===== Checking whitelist IP');

            if (!empty($this->generalConfiguration->getIpWhitelist())) {
                $ipWhitelist = explode(",", $this->generalConfiguration->getIpWhitelist());

                $clientIp = $this->Magento2Helper->getClientIp();
                $this->logger->info('===== Notify Controller ===== Client IP is :' . $clientIp);

                if (!in_array($clientIp, $ipWhitelist)) {
                    $this->logger->info('===== Notify Controller ===== IP not found');
                    $this->sendResponse($postData, true);
                    die;
                }
            }
            $this->logger->info('===== Notify Controller ===== Checking done');

            $this->logger->info('===== Notify Controller ===== Finding order...');

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('doku_transaction');
            // End - Build Data Process End

            $invoiceNumber = $postData['order']['invoice_number'];

            $this->logger->info('invoice : ' . $invoiceNumber);
            $order = $this->order->loadByIncrementId($invoiceNumber);
            if (!$order->getId()) {
                $this->logger->info('===== Notify Controller ===== Order not found!');
                $this->sendResponse($postData, true);
                die;
            }

            $sql = "SELECT * FROM " . $tableName . " where trans_id_merchant = '" . $invoiceNumber . "'";

            $dokuOrder = $connection->fetchRow($sql);

            if (!isset($dokuOrder['trans_id_merchant'])) {
                $this->logger->info('===== Notify Controller ===== Trans ID Merchant not found! in doku_transaction table');
                $this->sendResponse($postData, true);
                die;
            }

            $this->logger->info('===== Notify Controller ===== Order found');
            $this->logger->info('===== Notify Controller ===== Updating order...');

            $paymentMethod = $order->getPayment()->getMethod();

            $requestParams = json_decode($dokuOrder['request_params'], true);
            $sharedKey = $requestParams['SHAREDID'];
            $reference_number = isset($postData["virtual_account_payment"]["reference_number"]) ? $postData["virtual_account_payment"]["reference_number"] : "";
            $systrace_number = isset($postData["virtual_account_payment"]["systrace_number"]) ? $postData["virtual_account_payment"]["systrace_number"] : "";

            $rawCheckSum = $postData["acquirer"]["id"] .
                $postData["channel"]["id"] .
                $postData["client"]["id"] .
                $postData["order"]["amount"] .
                $postData["order"]["invoice_number"] .
                $postData["service"]["id"] .
                $postData["virtual_account_info"]["virtual_account_number"] .
                $postData["virtual_account_payment"]["channel_code"] .
                $postData["virtual_account_payment"]["date"] .
                $reference_number .
                $systrace_number.
                $sharedKey;
            $checkSum = hash('sha256', $rawCheckSum);

            $this->logger->info('===== Notify Controller ===== Checking checkSum...');

            if ($postData['security']['check_sum'] != $checkSum) {
                $this->logger->info('===== Notify Controller ===== checkSum not match!');
                $this->sendResponse($postData, true);
                die;
            }

            $order->save();

            $sql = "Update " . $tableName . " SET `updated_at` = 'now()', `order_status` = 'SUCCESS' , `notify_params` = '" . $postjson . "' where trans_id_merchant = '" . $invoiceNumber . "'";
            $connection->query($sql);

            $this->logger->info('===== Notify Controller ===== Updating success...');
            $this->sendResponse($postData, false);

            $this->logger->info('===== Notify Controller ===== End');
        } catch (\Exception $e) {
            $this->logger->info('===== Notify Controller ===== Generate code error : ' . $e->getMessage());
            $this->logger->info('===== Notify Controller ===== End');

            $this->sendResponse($postData, true);
        }
    }

    private function sendResponse($postData, $isFailed)
    {
        if ($isFailed) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(400);
        }

        $json_data_output = array(
            "client" => array(
                "id" => $postData['client']['id']
            ),
            "order" => array(
                "invoice_number" => $postData['order']['invoice_number'],
                "amount" => $postData['order']['amount']
            ),
            "virtual_account_info" => array(
                "virtual_account_number" => $postData['virtual_account_info']['virtual_account_number']
            ),
            "security" => array(
                "check_sum" => $postData['security']['check_sum']
            )
        );

        echo json_encode($json_data_output);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     * Bypass form key validator since params from DOKU does not contain form key --leogent
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
