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

class Review extends \Magento\Framework\App\Action\Action {

    protected $resourceConnection;
    protected $order;
    protected $generalConfiguration;
    protected $logger;
    protected $invoiceService;
    protected $builderInterface;
    protected $Magento2Helper;

    public function __construct(
        LoggerInterface $loggerInterface,
        Context $context,
        ResourceConnection $resourceConnection,
        Order $order,
        BuilderInterface $_builderInterface,
        InvoiceService $_invoiceService,
        GeneralConfiguration $_generalConfiguration,
        Data $_Magento2Helper
    )
    {
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
    }


    public function execute() {
        try {
            $this->logger->info('===== Review Controller ===== Start');

            $this->logger->info('===== Review Controller ===== Checking whitlist IP');

            if (!empty($this->generalConfiguration->getIpWhitelist())) {
                $ipWhitelist = explode(",", $this->generalConfiguration->getIpWhitelist());

                $clientIp = $this->Magento2Helper->getClientIp();

                if (!in_array($clientIp, $ipWhitelist)) {
                    $this->logger->info('===== Review Controller ===== IP not found');
                    echo 'STOP';
                    return;
                }
            }

            $postjson = json_encode($_POST, JSON_PRETTY_PRINT);

            $this->logger->info('post : ' . $postjson);

            $postData = $_POST;

            $this->logger->info('===== Review Controller ===== Checking done');
            $this->logger->info('===== Review Controller ===== Finding order...');

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');

            $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $postData['INVOICENUMBER'] . "'";

            $dokuOrder = $connection->fetchRow($sql);

            if (!isset($dokuOrder['invoice_number'])) {
                $this->logger->info('===== Review Controller ===== Trans ID Merchant not found! in jokul_transaction table');
                echo 'STOP';
                return;
            }

            $this->logger->info('===== Review Controller ===== Order found');
            $this->logger->info('===== Review Controller ===== Updating order...');

            $order = $this->order->loadByIncrementId($postData['INVOICENUMBER']);

            if (!$order->getId()) {
                $this->logger->info('===== Review Controller ===== Order not found!');
                echo 'STOP';
                return;
            }

            $requestParams = json_decode($dokuOrder['request_params'], true);
            $clientId = isset($requestParams['CLIENTID']) ? $requestParams['CLIENTID'] : $requestParams['req_client_id'];
            $sharedKey = $requestParams['SHAREDID'];

            $words = sha1($postData['AMOUNT'] . $clientId . $sharedKey
                    . $postData['INVOICENUMBER'] . $postData['RESULTMSG'] . $postData['VERIFYSTATUS']);

            $this->logger->info('===== Review Controller ===== Checking words...');

            if ($postData['WORDS'] != $words) {
                $this->logger->info('===== Review Controller ===== Words not match!');
                echo 'STOP';
                return;
            }

            if ($postData['RESPONSECODE'] != '0000' || $postData['EDUSTATUS'] != 'APPROVE') {
                $this->logger->info('===== Review Controller ===== RESULTMSG is not success!');
                
                $sql = "Update " . $tableName . " SET `updated_at` = 'now()', `order_status` = '".$postData['RESULTMSG']."', `review_params` = '" . $postjson . "' where invoice_number = '" . $postData['INVOICENUMBER'] . "'";
                $connection->query($sql);
                
                echo 'CONTINUE';
                return;
            }

            if ($order->canInvoice() && !$order->hasInvoices()) {
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
                $payment->setLastTransactionId($postData['INVOICENUMBER']);
                $payment->setTransactionId($postData['INVOICENUMBER']);
                $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $_POST]);
                $message = __(json_encode($_POST, JSON_PRETTY_PRINT));
                $trans = $this->builderInterface;
                $transaction = $trans->setPayment($payment)
                        ->setOrder($order)
                        ->setTransactionId($postData['INVOICENUMBER'])
                        ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $_POST])
                        ->setFailSafe(true)
                        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                $payment->addTransactionCommentsToOrder($transaction, $message);
                $payment->save();
                $transaction->save();

                if ($invoice && !$invoice->getEmailSent()) {
                    $invoiceSender = $objectManager->get('Magento\Sales\Model\Order\Email\Sender\InvoiceSender');
                    $invoiceSender->send($invoice);
                    $order->addRelatedObject($invoice);
                    $order->addStatusHistoryComment(__('Your Invoice for Order ID #%1.', $postData['INVOICENUMBER']))
                            ->setIsCustomerNotified(true);
                }
            }

            $order->setData('state', 'processing');
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

            $order->save();

            $sql = "Update " . $tableName . " SET `updated_at` = 'now()', `order_status` = 'SUCCESS', `review_params` = '" . $postjson . "' where invoice_number = '" . $postData['INVOICENUMBER'] . "'";
            $connection->query($sql);

            $this->logger->info('=====  Review Controller ===== Updating success...');

            $this->logger->info('===== Review Controller ===== End');

            echo "CONTINUE";
            
        } catch (\Exception $e) {
            $this->logger->info('===== Review Controller ===== Generate code error : ' . $e->getMessage());
            $this->logger->info('===== Review Controller ===== End');

            echo 'STOP';
        }
    }

}
