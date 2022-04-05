<?php

namespace Jokul\Magento2\Controller\Payment;

use Magento\Sales\Model\Order;
use \Jokul\Magento2\Helper\Logger;
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

class RequestO2O extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $session;
    protected $order;
    protected $logger;
    protected $resourceConnection;
    protected $config;
    protected $helper;
    protected $sessionFactory;
    protected $httpRequest;
    protected $generalConfiguration;
    protected $storeManagerInterface;
    protected $_timezoneInterface;

    public function __construct(
        Session $session,
        Order $order,
        ResourceConnection $resourceConnection,
        JokulConfigProvider $config,
        Data $helper,
        Context $context,
        PageFactory $pageFactory,
        Logger $loggerInterface,
        SessionFactory $sessionFactory,
        Http $httpRequest,
        GeneralConfiguration $_generalConfiguration,
        StoreManagerInterface $_storeManagerInterface,
        TimezoneInterface $timezoneInterface
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
        return parent::__construct($context);
    }

    protected function getOrder()
    {

        if (!$this->session->getLastRealOrder()->getIncrementId()) {

            $order = $this->order->getCollection()
                ->addFieldToFilter('quote_id', $this->session->getQuote()->getId())
                ->getFirstItem();

            if (!$order->getEntityId()) {
                $customerSession = $this->sessionFactory->create();
                $customerData = $customerSession->getCustomer();
                $order = $this->order->getCollection()
                    ->addFieldToFilter('customer_id', $customerData->getEntityId())
                    ->setOrder('created_at', 'DESC')
                    ->getFirstItem();
            }

            return $order;
        } else {
            return $this->order->loadByIncrementId($this->session->getLastRealOrder()->getIncrementId());
        }
    }

    public function execute()
    {

        $this->logger->doku_log('RequestO2O','Jokul - O2O Request Start');

        $this->logger->doku_log('RequestO2O','Jokul - O2O Request Find Order to Execute');

        $result = array();
        $redirectData = array();

        $order = $this->getOrder();

        if ($order->getEntityId()) {
            $order->setState(Order::STATE_NEW);
            $this->session->getLastRealOrder()->setState(Order::STATE_NEW);

            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Order Found!');

            $configCode = $this->config->getRelationPaymentChannel($order->getPayment()->getMethod());

            $billingData = $order->getBillingAddress();
            $config = $this->config->getAllConfig();

            $grandTotal = number_format($order->getGrandTotal(), 0, "", "");

            $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
                $config['payment'][$order->getPayment()->getMethod()]['admin_fee'],
                $config['payment'][$order->getPayment()->getMethod()]['admin_fee_type'],
                $config['payment'][$order->getPayment()->getMethod()]['disc_amount'],
                $config['payment'][$order->getPayment()->getMethod()]['disc_type'],
                $grandTotal
            );

            $order->setGrandTotal($grandTotal);
            $order->save();
            $clientId = $config['payment']['core']['client_id'];
            $sharedKey = $this->config->getSharedKey();
            $expiryTime = isset($config['payment']['core']['expiry']) && (int) $config['payment']['core']['expiry'] != 0 ? $config['payment']['core']['expiry'] : 60;
            $customerName = trim($billingData->getFirstname() . " " . $billingData->getLastname());

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface'); 

            $statusSubAccount = $this->helper->getStatusSubAccount($order->getPayment()->getMethod());
            if ($statusSubAccount == 'yes') {
                $subAccountId = $this->helper->getSubAccountId($order->getPayment()->getMethod());
                $params = array(
                    "order" => array(
                        "invoice_number" => $order->getIncrementId(),
                        "amount" => $grandTotal
                    ),
                    "online_to_offline_info" => array(
                        "expired_time" => $expiryTime,
                        "reusable_status" => false,
                        "info1" => ''
                    ),
                    "alfa_info" => array(
                        "receipt" => array(
                            "footer_message" => $this->config->getFooterMessage()
                        )
                    ),
                    "customer" => array(
                        "name" => $customerName,
                        "email" => $billingData->getEmail()
                    ),
                    "additional_info" => array (
                        "integration" => array (
                            "name" => "magento-plugin",
                            "version" => "1.4.2",
                            "cms_version" => $productMetadata->getVersion()
                        ),
                        "method" => "Jokul Direct",
                        "account" => array (
                            "id" => $subAccountId
                        )
                    )
                );
            } else {
                $params = array(
                    "order" => array(
                        "invoice_number" => $order->getIncrementId(),
                        "amount" => $grandTotal
                    ),
                    "online_to_offline_info" => array(
                        "expired_time" => $expiryTime,
                        "reusable_status" => false,
                        "info1" => ''
                    ),
                    "alfa_info" => array(
                        "receipt" => array(
                            "footer_message" => $this->config->getFooterMessage()
                        )
                    ),
                    "customer" => array(
                        "name" => $customerName,
                        "email" => $billingData->getEmail()
                    ),
                    "additional_info" => array (
                        "integration" => array (
                            "name" => "magento-plugin",
                            "version" => "1.4.2",
                            "cms_version" => $productMetadata->getVersion()
                        ),
                        "method" => "Jokul Direct"
                    )
                );
            }

            $requestTarget = "";
            if ($configCode == 07) {
                $requestTarget = "/alfa-online-to-offline/v2/payment-code";
            }

            $requestTimestamp = date("Y-m-d H:i:s");
            $requestTimestamp = date(DATE_ISO8601, strtotime($requestTimestamp));

            $signatureParams = array(
                "clientId" => $clientId,
                "key" => $sharedKey,
                "requestTarget" => $requestTarget,
                "requestId" => $this->helper->guidv4(),
                "requestTimestamp" => substr($requestTimestamp, 0, 19) . "Z"
            );

            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Request data : ' . json_encode($params, JSON_PRETTY_PRINT));
            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Send request to Jokul');

            $orderStatus = 'FAILED';
            try {
                $signature = $this->helper->doCreateRequestSignature($signatureParams, $params);
                $result = $this->helper->doGeneratePaycode($signatureParams, $params, $signature);
            } catch (\Exception $e) {
                $this->logger->doku_log('RequestO2O','Jokul - O2O Request Exception: ' . $e);
            }


            if (isset($result['order']['invoice_number'])) {
                $o2oInfo = isset($result['online_to_offline_info']) ? $result['online_to_offline_info'] : '';

                if ($o2oInfo !== '') {
                    $this->logger->doku_log('RequestO2O','Jokul - O2O Request Received Success Response from Jokul');
                    $orderStatus = 'PENDING';
                    $result['result'] = 'SUCCESS';
                    $vaNumber = $result['online_to_offline_info']['payment_code'];
                } elseif (isset($result['error'])) {
                    $this->logger->doku_log('RequestO2O','Jokul - O2O Request Received Error Response from Jokul');
                    $result['result'] = 'FAILED';
                    $result['error_message'] = $result['error']['message'];
                } else {
                    $this->logger->doku_log('RequestO2O','Jokul - O2O Request Received Undefined Error from Jokul');
                    $result['result'] = 'FAILED';
                    $result['error_message'] = 'Undefined error';
                }
            } else {
                $this->logger->doku_log('RequestO2O','Jokul - O2O Request Received Unexpected Error from Jokul');
                $result['result'] = 'FAILED';
                $result['error_message'] = 'Unexpected error';
            }

            $params['shared_key'] = $sharedKey;
            $params['response'] = $result;

            $jsonResult = json_encode(array_merge($params), JSON_PRETTY_PRINT);

            $tableName = $this->resourceConnection->getTableName('jokul_transaction');
            $this->resourceConnection->getConnection()->insert($tableName, [
                'quote_id' => $order->getQuoteId(),
                'store_id' => $order->getStoreId(),
                'order_id' => $order->getId(),
                'invoice_number' => $order->getIncrementId(),
                'payment_channel_id' => $configCode,
                'order_status' => $orderStatus,
                'request_params' => $jsonResult,
                'va_number' => $vaNumber,
                'created_at' => 'now()',
                'updated_at' => 'now()',
                'doku_grand_total' => $grandTotal,
                'admin_fee_type' => $config['payment'][$order->getPayment()->getMethod()]['admin_fee_type'],
                'admin_fee_amount' => !empty($config['payment'][$order->getPayment()->getMethod()]['admin_fee']) ? $config['payment'][$order->getPayment()->getMethod()]['admin_fee'] : 0,
                'admin_fee_trx_amount' => $totalAdminFeeDisc['total_admin_fee'],
                'discount_type' => $config['payment'][$order->getPayment()->getMethod()]['disc_type'],
                'discount_amount' => !empty($config['payment'][$order->getPayment()->getMethod()]['disc_amount']) ? $config['payment'][$order->getPayment()->getMethod()]['disc_amount'] : 0,
                'discount_trx_amount' => $totalAdminFeeDisc['total_discount']
            ]);

            $base_url = $this->storeManagerInterface
                ->getStore($order->getStore()->getId())
                ->getBaseUrl();

            $redirectData['url'] = $base_url . "jokulbackend/service/redirect";
            $redirectData['invoice_number'] = $order->getIncrementId();

            $redirectSignatureParams = array(
                'amount' => $grandTotal,
                'sharedkey' => $sharedKey,
                'invoice' => $order->getIncrementId(),
                'status' => $result['result']
            );

            $redirectSignature = $this->helper->generateRedirectSignature($redirectSignatureParams);
            $redirectData['redirect_signature'] = $redirectSignature;
            $redirectData['status'] = $result['result'];
        } else {
            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Order not found!');
        }

        $this->logger->doku_log('RequestO2O','Jokul - O2O Request End');

        if ($result['result'] == 'SUCCESS') {
            echo json_encode(array(
                'err' => false,
                'response_message' => 'O2O Number Generated',
                'result' => $redirectData
            ));
            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Redirecting to Success Page' . print_r($result, true));
        } else {
            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Prepare Order Failed Procedure');
            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Initiate Restore Cart');

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
            $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');

            $order = $_checkoutSession->getLastRealOrder();
            $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Get Cart');
            if ($quote->getId()) {
                $this->logger->doku_log('RequestO2O','Jokul - O2O Request Checking Cart');
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $_checkoutSession->replaceQuote($quote);
                $this->logger->doku_log('RequestO2O','Jokul - O2O Request Restoring Cart');
                $order->cancel()->save();
                $this->logger->doku_log('RequestO2O','Jokul - O2O Request Cart Restored');
                echo json_encode(array(
                    'err' => true,
                    'response_message' => $result['error_message'],
                    'result' => $redirectData
                ));
            }

            $this->logger->doku_log('RequestO2O','Jokul - O2O Request Show Error Popup: ' . print_r($result, true));
        }
    }
}
