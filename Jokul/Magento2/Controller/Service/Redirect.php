<?php

namespace Jokul\Magento2\Controller\Service;

use Magento\Sales\Model\Order;
use Jokul\Magento2\Helper\Logger;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Jokul\Magento2\Helper\Data;
use Magento\Framework\Data\Form\FormKey\Validator;
use Jokul\Magento2\Api\TransactionRepositoryInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use \Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class Redirect extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $order;
    protected $logger;
    protected $session;
    protected $resourceConnection;
    protected $helper;
    protected $timeZone;
    protected $formKeyValidator;
    protected $transactionRepository;
    protected $invoiceService;
    protected $builderInterface;

    public function __construct(
        Order $order,
        Logger $logger,
        Session $session,
        ResourceConnection $resourceConnection,
        Data $helper,
        BuilderInterface $_builderInterface,
        InvoiceService $_invoiceService,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timeZone,
        Validator $formKeyValidator,
        TransactionRepositoryInterface $transactionRepository
    ) {

        $this->order = $order;
        $this->logger = $logger;
        $this->session = $session;
        $this->invoiceService = $_invoiceService;
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
        $this->builderInterface = $_builderInterface;
        $this->timeZone = $timeZone;
        $this->formKeyValidator = $formKeyValidator;
        $this->transactionRepository = $transactionRepository;
        return parent::__construct($context);
    }

    public function execute()
    {
        $path = "";
        $this->logger->doku_log('Redirect','Jokul - Redirect Controller Start');
        $post = $this->getRequest()->getParams();

        $postJson = json_encode($post, JSON_PRETTY_PRINT);

        $this->logger->doku_log('Redirect','Jokul - Redirect Controller Looking for the order on the Magento Side');

        $this->logger->doku_log('Redirect','Redirect Controller  Finding order...');
        $transactionType = isset($post['TRANSACTIONTYPE']) ? $post['TRANSACTIONTYPE'] : "";
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('jokul_transaction');

        if (!isset($post['invoice_number'])) {
            $this->logger->doku_log('Redirect','Jokul - Redirect Controller Invoice Number empty');

            $path = "checkout/onepage/failure";
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath($path);
        }

        $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $post['invoice_number'] . "'";

        $dokuOrder = $connection->fetchRow($sql);
        $paymentChannel = $dokuOrder['payment_channel_id'];

        if (!isset($dokuOrder['invoice_number'])) {
            $this->logger->doku_log('Redirect','Jokul - Redirect Controller Invoice Number not found in jokul_transaction table');

            $path = "";
            $this->messageManager->addError(__('Order not found!'));

            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath($path);
        }

        $requestParams = json_decode($dokuOrder['request_params'], true);
        $sharedKey = $requestParams['shared_key'];
        $requestAmount = $requestParams['order']['amount'];

        $O2Ochannel = array(07);
        $expiryStoreDate = "";
        $additionalParams = "";
        $vaNumber = "";
        $expiryGmtDate = "";
        if ($paymentChannel != 06) {
            if ($transactionType == "checkoutsuccess") {
                $expiryValue = $requestParams['order']['invoice_number'];
                $vaNumber = $requestParams['response']['response']['payment']['expired_date'];
            } else {
                if (in_array($paymentChannel, $O2Ochannel)) {
                    $expiryValue = $requestParams['online_to_offline_info']['expired_time'];
                    $vaNumber = $requestParams['response']['online_to_offline_info']['payment_code'];
                } else {
                    $expiryValue = $requestParams['virtual_account_info']['expired_time'];
                    $vaNumber = $requestParams['response']['virtual_account_info']['virtual_account_number'];
                }
            }

            $expiryGmtDate = date('Y-m-d H:i:s', (strtotime('+' . $expiryValue . ' minutes', time())));
            $expiryStoreDate = $this->timeZone->date(new \DateTime($expiryGmtDate))->format('Y-m-d H:i:s');
            $additionalParams = " `va_number` = '" . $vaNumber . "', ";
        }

        $order = $this->order->loadByIncrementId($post['invoice_number']);

        if ($order->getEntityId()) {

            $isSuccessOrder = false;

            $this->logger->doku_log('Redirect','Jokul - Redirect Controller Order found',$order->getIncrementId());

            $this->logger->doku_log('Redirect','Jokul - Redirect Controller  Checking Redirect Signature',$order->getIncrementId());

            $redirectSignatureParams = array(
                'amount' => $requestAmount,
                'sharedkey' => $sharedKey,
                'invoice' => $order->getIncrementId(),
                'status' => $post['status']
            );

            $redirectSignature = $this->helper->generateRedirectSignature($redirectSignatureParams);

            if (strtolower($redirectSignature)  == strtolower($post['redirect_signature'])) {
                $this->logger->doku_log('Redirect','Jokul - Redirect Controller Redirect Signature match!',$order->getIncrementId());

                if (strtolower($post['status']) == strtolower('success')) {
                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller Check Order Status',$order->getIncrementId());
                    $isSuccessOrder = true;
                    $path = "checkout/onepage/success";
                    
                    if ($paymentChannel == '09') {
                        $path = $this->checkStatus($post['invoice_number'], $order, $requestParams, $sharedKey, $tableName, $connection);
                        $this->logger->doku_log('Redirect','Path From Checkstatus '.$path, $order->getIncrementId());
                    }
                } else {
                    $path = "checkout/cart";
                    $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller Order Status Failed',$order->getIncrementId());
                }

                $this->logger->doku_log('Redirect','Jokul - Redirect Controller Send Email Notification - Start',$order->getIncrementId());

                $this->helper->sendDokuEmailOrder($order, $vaNumber, $dokuOrder, $isSuccessOrder, $expiryStoreDate);

                $this->logger->doku_log('Redirect','Jokul - Redirect Controller Send Email Notification - End',$order->getIncrementId());
            } else {
                $path = "";
                $order->cancel()->save();
                $this->messageManager->addError(__('Sorry, something went wrong!'));
                $this->logger->doku_log('Redirect','Jokul - Redirect Controller Redirect Signature not match!',$order->getIncrementId());
            }
        } else {
            $path = "";
            $this->messageManager->addError(__('Order not found'));
            $this->logger->doku_log('Redirect','Jokul - Redirect Controller Order not found');
        }

        $sql = "Update " . $tableName . " SET " . $additionalParams . " `updated_at` = 'now()', `expired_at_gmt` = '" . $expiryGmtDate . "', `expired_at_storetimezone` = '" . $expiryStoreDate . "', `redirect_params` = '" . $postJson . "' where invoice_number = '" . $post['invoice_number'] . "'";
        $connection->query($sql);

        $this->logger->doku_log('Redirect','Redirect Controller  End');

        $this->session->setLastSuccessQuoteId($order->getQuoteId());
        $this->session->setLastOrderId($order->getEntityId());
        $this->session->setLastRealOrderId($order->getEntityId());

        $params = array('invoice' => $order->getIncrementId(), 'result' => $post['status'], 'transaction_type' => $transactionType);
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath($path, $params);
    }
    
    public function checkStatus ($invoiceNumber, $order, $requestParams, $sharedKey, $tableName, $connection) {
        $countHit = 0;
        $maxCountHit = 4;
        $path = "";
        try {
            while (true) {
                usleep(3000000);
                $this->logger->doku_log('Redirect','start check status!' .$countHit,$order->getIncrementId());
                $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $invoiceNumber . "'";
                $dokuOrder = $connection->fetchRow($sql);
                $orderStatus = $dokuOrder['order_status'];

                if ($orderStatus == "SUCCESS") {
                    $this->deleteCart();
                    $path = "checkout/onepage/success";
                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller DB Success!' .$countHit,$order->getIncrementId());
                    break;
                } else if ($orderStatus == "FAILED") {
                    $path = "checkout/cart";
                    $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller DB Failed!'.$countHit,$order->getIncrementId());
                    break;
                } else {
                    $this->logger->doku_log('Redirect','Jokul - RedirectPending Controller Check Status',$order->getIncrementId());
                    $clientId = $requestParams['response']['response']['headers']['client_id'];
                    $requestTarget = "/orders/v1/status/".$order->getIncrementId();
                    $requestTimestamp = date("Y-m-d H:i:s");
                    $requestTimestamp = date(DATE_ISO8601, strtotime($requestTimestamp));

                    $signatureParams = array(
                        "clientId" => $clientId,
                        "key" => $sharedKey,
                        "requestTarget" => $requestTarget,
                        "requestId" => rand(1, 100000),
                        "requestTimestamp" => substr($requestTimestamp, 0, 19) . "Z"
                    );

                    $signature = $this->helper->doCheckStatusRequestSignature($signatureParams);
                    $result = $this->helper->doCheckStatus($signatureParams, $signature);

                    if (isset($result)) {
                        if (!isset($result['error'])) {
                            if ($result['transaction']['type'] == 'SALE' && $result['channel']['id'] == 'CREDIT_CARD') {
                                $this->updateOrderStatusCCSale($order, $invoiceNumber, $result, $dokuOrder);
                            } else if ($result['transaction']['status'] == 'SUCCESS') {
                                $this->deleteCart();
                                $path = "checkout/onepage/success";
                                $this->logger->doku_log('Redirect','Jokul - Redirect Controller Check Status Success!'.$countHit, $order->getIncrementId());
                                if (strtolower($result['acquirer']['id']) == strtolower('OVO')) {
                                    $this->updateStatus($order, $invoiceNumber, 'SUCCESS');
                                }
                                break;
                            } else if ($result['transaction']['status'] == 'PENDING') {
                                if ($countHit == $maxCountHit){
                                    $this->deleteCart();
                                    if ($result['transaction']['type'] == 'AUTHORIZE' && $result['channel']['id'] == 'CREDIT_CARD') {
                                        $this->updateOrderStatusCCAuthorize($order, $invoiceNumber, $result, $dokuOrder);
                                    }
                                    $path = "checkout/onepage/success";
                                    
                                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller Check Status Success!'.$countHit, $order->getIncrementId());
                                    break;
                                }
                            } else if ($result['transaction']['status'] == 'FAILED') {
                                if ($countHit == $maxCountHit){
                                    $path = "checkout/cart";
                                    $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller Check Status Failed!'.$countHit,$order->getIncrementId());
                                    break;
                                }
                            } else if (!isset($result['transaction'])) {
                                if ($countHit == $maxCountHit){
                                    $path = "checkout/cart";
                                    $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                                    $this->logger->doku_log('Redirect','Jokul - Redirect Controller Check Status Not Found'.$countHit,$order->getIncrementId());
                                    break;
                                }
                            }
                        }
                    }
                }
                $countHit++;
            }
        } catch (\Exception $e) {
            $this->messageManager->addError(__('Server Error'));
        }
        return $path;
    }

    public function updateStatus($order, $invoiceNumber, $status) {
        if ($order->canInvoice() && !$order->hasInvoices()) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setTotalPaid($order->getGrandTotal());
            $invoice->register();

            $payment = $order->getPayment();
            $payment->setLastTransactionId($invoiceNumber);
            $payment->setTransactionId($invoiceNumber);
            $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => $_POST]);
            $message = __(json_encode($_POST, JSON_PRETTY_PRINT));
            $trans = $this->builderInterface;

            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($invoiceNumber)
                ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => $_POST])
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);;
            $payment->addTransactionCommentsToOrder($transaction, $message);
            $payment->save();
            $transaction->save();

            if (strtolower($status) == strtolower('SUCCESS')) {
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

                $order->setData('state', 'processing');
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->save();

                if ($invoice && !$invoice->getEmailSent()) {
                    $invoiceSender = $objectManager->get('Magento\Sales\Model\Order\Email\Sender\InvoiceSender');
                    $invoiceSender->send($invoice);
                    $order->addRelatedObject($invoice);
                    $order->addStatusHistoryComment(__('Your Invoice for Order ID #%1.', $invoiceNumber))
                        ->setIsCustomerNotified(true);
                }
                
                $this->logger->doku_log('Check Status','Jokul - Back To Merchant Update transaction to Processing '.$invoiceNumber);
            } else {
                $order->setData('state', 'canceled');
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $order->save();
                $this->logger->doku_log('Check Status','Jokul - Back To Merchant Update transaction to FAILED '. $invoiceNumber);
            }
        }
    }

    public function deleteCart()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cartObject = $objectManager->create('Magento\Checkout\Model\Cart');
        $cartObject->truncate()->save();

        $this->logger->doku_log('Redirect','delete Cart');
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

    public function updateOrderStatusCCAuthorize($order, $invoiceNumber, $result, $jokulTransaction) {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');
            
            $order->setData('state', 'holded');
            $order->setStatus(\Magento\Sales\Model\Order::STATE_HOLDED);
            $order->setCustomerNote("CREDIT_CARD");
            
            $sql = "Update " . $tableName . " SET `payment_type` = '" . $result['transaction']['type'] . "', `payment_channel` = '" . $result['channel']['id'] . "' where invoice_number = '" . $invoiceNumber . "'";
            $this->logger->doku_log('Redirect', 'QUERY: ' . $sql);
            $order->save();
            $connection->query($sql);
        } catch (Exception $e) {
            $this->logger->doku_log('Redirect', 'Error occurred: ' . $e->getMessage());
            $this->messageManager->addError(__('Server Error'));
        }
    }

    public function updateOrderStatusCCSale($order, $invoiceNumber, $result, $jokulTransaction) {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');
            
            $order->setData('state', 'processing]');
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
            
            $sql = "Update " . $tableName . " SET `payment_type` = '" . $result['transaction']['type'] . "', `payment_channel` = '" . $result['channel']['id'] . "' where invoice_number = '" . $invoiceNumber . "'";
            $this->logger->doku_log('Redirect', 'QUERY: ' . $sql);
            $order->save();
            $connection->query($sql);
        } catch (Exception $e) {
            $this->logger->doku_log('Redirect', 'Error occurred: ' . $e->getMessage());
            $this->messageManager->addError(__('Server Error'));
        }
    }
}
