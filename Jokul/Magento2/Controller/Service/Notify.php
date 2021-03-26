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
        $this->logger->info('===== Jokul - Notification Controller ===== Start');
        try {
            // Start - Build Data Process
            $parsedRaw = array();
            $rawbody = urldecode(file_get_contents('php://input'));
            parse_str($rawbody, $parsedRaw);

            $this->logger->info('===== Jokul - Notification Controller ===== Notification Raw Request: ' . $rawbody);

            $postjson = json_encode($rawbody, JSON_PRETTY_PRINT);
            $postData = json_decode($rawbody, true);

            $this->logger->info('===== Jokul - Notification Controller ===== Looking for the order on Magento Side');

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');
            // End - Build Data Process End

            $invoiceNumber = $postData['order']['invoice_number'];

            $this->logger->info('===== Jokul - Notification Controller ===== Invoice Number: ' . $invoiceNumber);
            $order = $this->order->loadByIncrementId($invoiceNumber);
            if (!$order->getId()) {
                $this->logger->info('===== Jokul - Notification Controller ===== Order not found!');
                $this->sendResponse(404);
                die;
            }

            $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $invoiceNumber . "'";

            $dokuOrder = $connection->fetchRow($sql);

            if (!isset($dokuOrder['invoice_number'])) {
                $this->logger->info('===== Jokul - Notification Controller ===== Invoice Number not found in jokul_transaction table');
                $this->sendResponse(404);
                die;
            }

            $this->logger->info('===== Jokul - Notification Controller ===== Order found');
            $this->logger->info('===== Jokul - Notification Controller ===== Updating order based on notification received');

            if($postData['transaction']['status'] == $dokuOrder['order_status']){
                $this->logger->info('===== Jokul - Notification Controller ===== Transaction already updated to SUCCESS (Idempotent)');
                $this->sendResponse(200);
                die;
            }

            $requestParams = json_decode($dokuOrder['request_params'], true);
            $sharedKey = $requestParams['shared_key'];
            $this->sharedKey = $sharedKey;

            $headers = getallheaders();
            $signatureParams = array(
                "clientId" => $headers["Client-Id"],
                "key" => $sharedKey,
                "requestTarget" => $_SERVER['REQUEST_URI'],
                "requestId" => $headers['Request-Id'],
                "requestTimestamp" => $headers['Request-Timestamp']
            );

            $signature = $this->Magento2Helper->doCreateNotifySignature($signatureParams, $rawbody);

            $this->logger->info('===== Jokul - Notification Controller ===== Checking Signature');

            if ($headers['Signature'] != $signature) {
                $this->logger->info('===== Jokul - Notification Controller ===== Signature not match!');
                $this->sendResponse(401);
                die;
            }

            $this->logger->info('===== Jokul - Notification Controller ===== Signature match!');

            $paymentMethod = $order->getPayment()->getMethod();
            if ($order->canInvoice() && !$order->hasInvoices() && $paymentMethod != \Jokul\Magento2\Model\Payment\CreditCardAuthorizationHosted::CODE) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->pay();
                $invoice->save();
                $transactionSave = $objectManager->create(
                    'Magento\Framework\DB\Transaction'
                )->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();

                $payment = $order->getPayment();
                $payment->setLastTransactionId($postData["order"]["invoice_number"]);
                $payment->setTransactionId($postData["order"]["invoice_number"]);
                $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $_POST]);
                $message = __(json_encode($_POST, JSON_PRETTY_PRINT));
                $trans = $this->builderInterface;


                $transactionType = $paymentMethod == \Jokul\Magento2\Model\Payment\CreditCardHosted::CODE ? \Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER : \Magento\Sales\Model\Order\Payment\Transaction::TYPE_PAYMENT;
                $transaction = $trans->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($postData["order"]["invoice_number"])
                    ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $_POST])
                    ->setFailSafe(true)
                    ->build($transactionType);
                $payment->addTransactionCommentsToOrder($transaction, $message);
                $payment->save();
                $transaction->save();

                if ($invoice && !$invoice->getEmailSent()) {
                    $invoiceSender = $objectManager->get('Magento\Sales\Model\Order\Email\Sender\InvoiceSender');
                    $invoiceSender->send($invoice);
                    $order->addRelatedObject($invoice);
                    $order->addStatusHistoryComment(__('Your Invoice for Order ID #%1.', $postData["order"]["invoice_number"]))
                        ->setIsCustomerNotified(true);
                }
                $order->setData('state', 'processing');
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
            }

            $order->save();

            $sql = "Update " . $tableName . " SET `updated_at` = 'now()', `order_status` = 'SUCCESS' , `notify_params` = '" . $postjson . "' where invoice_number = '" . $invoiceNumber . "'";
            $connection->query($sql);

            $this->logger->info('===== Jokul - Notification Controller ===== Update transaction to SUCCESS');
            $this->sendResponse(200);

            $this->logger->info('===== Jokul - Notification Controller ===== End');
        } catch (\Exception $e) {
            $this->logger->info('===== Jokul - Notification Controller ===== Error reason: ' . $e->getMessage());
            $this->logger->info('===== Jokul - Notification Controller ===== End');

            $this->sendResponse(400);
        }
    }

    private function sendResponse($httpStatusCode)
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($httpStatusCode);
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
