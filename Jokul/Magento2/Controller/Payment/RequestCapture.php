<?php

namespace Jokul\Magento2\Controller\Payment;

use Magento\Framework\Mview\View\StateInterface;
use Magento\Sales\Model\Order;
use \Jokul\Magento2\Helper\Logger;
use \Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Jokul\Magento2\Model\JokulConfigProvider;
use Jokul\Magento2\Helper\Data;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Request\Http;
use Jokul\Magento2\Model\GeneralConfiguration;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use \Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;



class RequestCapture extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $_pageFactory;
    protected $session;
    protected $order;
    protected $logger;
    protected $resourceConnection;
    private $dokusTransactionOrder = null;
    protected $config;
    protected $helper;
    protected $sessionFactory;
    protected $httpRequest;
    protected $generalConfiguration;
    protected $storeManagerInterface;
    protected $_timezoneInterface;
    protected $productRepository;
    protected $invoiceService;
    protected $orderRepository;


    public function __construct(
        Session $session,
        Order $order,
        ResourceConnection $resourceConnection,
        JokulConfigProvider $config,
        Data $helper,
        Context $context,
        PageFactory $pageFactory,
        LoggerInterface $loggerInterface,
        SessionFactory $sessionFactory,
        Http $httpRequest,
        GeneralConfiguration $_generalConfiguration,
        StoreManagerInterface $_storeManagerInterface,
        TimezoneInterface $timezoneInterface,
        ProductRepositoryInterface $productRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        InvoiceService $_invoiceService,
        OrderRepositoryInterface $orderRepository


    ) {
        $this->session = $session;
        $this->logger = $loggerInterface;
        $this->order = $order;
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->helper = $helper;
        $this->_pageFactory = $pageFactory;
        $this->sessionFactory = $sessionFactory;
        $this->httpRequest = $httpRequest;
        $this->generalConfiguration = $_generalConfiguration;
        $this->storeManagerInterface = $_storeManagerInterface;
        $this->_timezoneInterface = $timezoneInterface;
        $this->productRepository = $productRepository;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $_invoiceService;
        $this->orderRepository = $orderRepository;

        return parent::__construct($context);
    }

    protected function getOrder()
    {
        $incrementId = $this->getRequest()->getParam('increment_id', 0);
        $this->logger->info('===== Request Capture ===== GET ORDER' . $this->getRequest()->getParam('increment_id'));
        $this->logger->info('===== Request Capture ===== GET ORDER' . $this->getRequest()->getParam('increment_id', 0));

        if ($incrementId) {
            return $this->orderFactory->create()->loadByIncrementId($incrementId);
        }

        return false;
    }

    public function execute()
    {
        $this->logger->info('===== Request Capture ===== Start');

        // $result = array();

        $this->logger->info('===== Request Capture ===== GET ORDER');
        $this->logger->info('===== Request Capture ===== GET ORDER' . $captureAmount = $this->getRequest()->getParam('capture_amount', 0));
        $order = $this->getOrder();
        $logMessage = '===== Request Capture ===== ORDER' . json_encode($order);
        $this->logger->info($logMessage);
        $this->logger->info('===== Request Capture ===== LEWAT ORDER');

        if ($order->getEntityId()) {

            $this->logger->info('===== Request Capture ===== Order Found');

            $captureAmount = $this->getRequest()->getParam('capture_amount', 0);

            $tableName = $this->resourceConnection->getTableName('jokul_transaction');
            $connection = $this->resourceConnection->getConnection();

            $sql = "SELECT * FROM " . $tableName . " WHERE invoice_number = '" . $order->getIncrementId() . "'";
            $this->dokusTransactionOrder = $connection->fetchRow($sql);

            $logMessage = '===== Request Capture ===== DOKU TRANSACTION' . json_encode($this->dokusTransactionOrder);
            $this->logger->info($logMessage);

            if ($this->dokusTransactionOrder) {
                $authorizeId = $this->dokusTransactionOrder['authorize_id'];

                $config = $this->config->getAllConfig();
                $clientId = $config['payment']['core']['client_id'];
                $sharedKey = $this->config->getSharedKey();

                $requestTarget = "/credit-card/capture";

                $requestTimestamp = date("Y-m-d H:i:s");
                $requestTimestamp = date(DATE_ISO8601, strtotime($requestTimestamp));

                $signatureParams = array(
                    "clientId" => $clientId,
                    "key" => $sharedKey,
                    "requestTarget" => $requestTarget,
                    "requestId" => rand(1, 100000),
                    "requestTimestamp" => substr($requestTimestamp, 0, 19) . "Z"
                );

                $params = array(
                    "payment" => array(
                        "authorize_id" => $authorizeId,
                        "capture_amount" => $captureAmount
                    )
                );

                try {
                    $signature = $this->helper->doCreateRequestSignature($signatureParams, $params);
                    $this->logger->info('===== Request Capture ===== Request Capture = ' . json_encode($params, JSON_PRETTY_PRINT));
                    $this->logger->info('===== Request Capture ===== Hit Capture');
                    $result = $this->helper->doCapturePayment($signatureParams, $params, $signature);
                    $this->logger->info('===== Request Capture ===== Response Capture = ' . json_encode($result, JSON_PRETTY_PRINT));
                } catch (\Exception $e) {
                    $this->logger->info('Exception ' . $e);
                    $result['res_response_code'] = "500";
                    $result['res_response_msg'] = "Can't connect to server";
                    $this->logger->info('===== Request Capture ===== Error' . $e->getMessage());
                }

                if (isset($result['payment']['status']) && $result['payment']['status'] == 'SUCCESS') {

                    $updateSql = "UPDATE " . $tableName . " SET order_status = 'SUCCESS' WHERE invoice_number = '" . $order->getIncrementId() . "'";
                    $connection->query($updateSql);
                    
                    $order->setData('state', 'processing');
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->setTotalPaid($captureAmount);
                    $order->setTotalDue(0);
                    $this->orderRepository->save($order);

                    if ($order->canInvoice() && !$order->hasInvoices()) {
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->setTotalPaid($captureAmount);
                        $invoice->setGrandTotal($captureAmount);
                        $invoice->register();
                        $invoice->pay();
                        $invoice->save();

                        $transactionSave = $objectManager->create(
                            'Magento\Framework\DB\Transaction'
                        )->addObject(
                            $invoice
                        )->addObject(
                            $order
                        );
                        $transactionSave->save();
                    }

                    echo json_encode(
                        array(
                            'err' => false,
                            'response_message' => 'Capture Payment Success',
                            'data' => $result
                        )
                    );
                    $this->logger->info('===== Request Capture ===== Status Success');
                } else {
                    $errorMessage = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown Error';
                    echo json_encode(
                        array(
                            'err' => true,
                            'response_message' => 'Capture Payment Failed: ' . $errorMessage,
                            'data' => $result
                        )
                    );
                    $order->setData('state', 'new');
                    $order->setData('status', 'pending');
                    $order->save();
                    $this->logger->info('===== Request Capture ===== Status Failed');
                }
                $this->logger->info('===== Request Capture ===== End');
            } else {
                $this->logger->info('===== Request Capture ===== Transaction Not Found');
            }
        } else {
            echo json_encode(
                array(
                    'err' => true,
                    'response_message' => 'Capture Payment Failed: Order not found in Jokul Transaction'
                )
            );
        }
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