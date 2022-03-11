<?php

namespace Jokul\Magento2\Controller\Service;

use \Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use \Jokul\Magento2\Helper\Logger;
use Jokul\Magento2\Model\GeneralConfiguration;
use Jokul\Magento2\Helper\Data;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Jokul\Magento2\Api\TransactionRepositoryInterface;
use Jokul\Magento2\Model\JokulConfigProvider;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class QrisNotify extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    const ORDER_STATUS_CHALLENGE = 'challenge';

    protected $resourceConnection;
    protected $order;
    protected $generalConfiguration;
    protected $logger;
    protected $config;
    protected $invoiceService;
    protected $builderInterface;
    protected $Magento2Helper;
    protected $timezoneInterface;
    protected $transactionRepository;

    public function __construct(
        Logger $loggerInterface,
        Context $context,
        JokulConfigProvider $config,
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
        $this->config = $config;
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
        $this->logger->doku_log('Qris Notify','Jokul - Notification Controller Start');
            $rawbody = file_get_contents('php://input');
            $this->logger->doku_log('Qris Notify','Jokul - Notification Controller Notification Raw Request: ' . $rawbody);
        try {
            
            $this->logger->doku_log('Qris Notify','Jokul - Notification Controller Qris Notification Request : ' . $_POST['ACQUIRER']);
            $order = $this->order->loadByIncrementId($_POST['TRANSACTIONID']);

            $config = $this->config->getAllConfig();
            $sharedKey = $this->config->getCheckoutPaymentSharedkey('doku_checkout_merchanthosted');

            $this->logger->doku_log('Qris Notify','Jokul - Notification Controller Invoice Number : ' . $_POST['TRANSACTIONID']);
            
            if (!$order->getId()) {
                $this->logger->doku_log('Notify','Jokul - Notification Controller Order not found!');
                $this->sendResponse(404);
                die;
            }

            $words = $_POST['ISSUERID'] . $_POST['TXNDATE'] . $_POST['MERCHANTPAN'] . $_POST['INVOICE'] . $sharedKey;
            $this->logger->doku_log('Qris Notify','Component Words Qris Current : ' . $words);

            $validateWord = sha1($words);
            $this->logger->doku_log('Qris Notify','Validated Words Qris Current : ' . $validateWord);
            $this->logger->doku_log('Qris Notify','Words Qris Expected : ' . $_POST['WORDS']);

            if ($validateWord == $_POST['WORDS']) {
                if (strtolower($_POST['TXNSTATUS']) == strtolower('S')) {
                    $order->setData('state', 'processing');
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->save();
                    $this->logger->doku_log('Qris Notify','Jokul - Update transaction to Processing '.$_POST['TRANSACTIONID']);
                } else {
                    $order->setData('state', 'canceled');
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                    $order->save();
                    $this->logger->doku_log('Qris Notify','Jokul - Update transaction to FAILED '. $_POST['TRANSACTIONID']);
                }
            } else {
                $this->logger->doku_log('Qris Notify','Words Not Match '. $_POST['TRANSACTIONID']);
            }

        } catch (\Exception $e) {
            $this->logger->doku_log('Notify','Jokul - Notification Controller Error reason: ' . $e->getMessage());
            $this->logger->doku_log('Notify','Jokul - Notification Controller End');

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
