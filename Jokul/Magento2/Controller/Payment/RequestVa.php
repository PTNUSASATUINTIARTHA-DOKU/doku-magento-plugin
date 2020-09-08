<?php

namespace Jokul\Magento2\Controller\Payment;

use Magento\Sales\Model\Order;
use \Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Jokul\Magento2\Model\DokuMerchanthostedConfigProvider;
use Jokul\Magento2\Helper\Data;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Request\Http;
use Jokul\Magento2\Model\GeneralConfiguration;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class RequestVa extends \Magento\Framework\App\Action\Action {

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
        DokuMerchanthostedConfigProvider $config,
        Data $helper,
        Context $context,
        PageFactory $pageFactory,
        LoggerInterface $loggerInterface,
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

    protected function getOrder() {

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

    public function execute() {

        $this->logger->info('===== Request controller VA MANDIRI GATEWAY ===== Start');

        $this->logger->info('===== Request controller VA MANDIRI GATEWAY ===== Find Order');

        $result = array();
        $redrectData = array();

        $order = $this->getOrder();

        if ($order->getEntityId()) {
            $order->setState(Order::STATE_NEW);
            $this->session->getLastRealOrder()->setState(Order::STATE_NEW);
            $order->save();

            $this->logger->info('===== Request controller VA MANDIRI GATEWAY ===== Order Found!');

            $configCode = $this->config->getRelationPaymentChannel($order->getPayment()->getMethod());

            $billingData = $order->getBillingAddress();
            $config = $this->config->getAllConfig();

            $realGrandTotal = $order->getGrandTotal();

            $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
                    $config['payment'][$order->getPayment()->getMethod()]['admin_fee'],
                    $config['payment'][$order->getPayment()->getMethod()]['admin_fee_type'],
                    $config['payment'][$order->getPayment()->getMethod()]['disc_amount'],
                    $config['payment'][$order->getPayment()->getMethod()]['disc_type'],
                    $realGrandTotal);

            $grandTotal = $realGrandTotal + $totalAdminFeeDisc['total_admin_fee'];

            $buffGrandTotal = $grandTotal - $totalAdminFeeDisc['total_discount'];

            $grandTotal = $buffGrandTotal < 10000 ? 10000 : $buffGrandTotal;

            $mallId = $config['payment']['core']['mall_id'];
            $sharedId = $this->config->getSharedKey();
            $expiryTime = isset($config['payment']['core']['expiry']) && (int) $config['payment']['core']['expiry'] != 0 ?  $config['payment']['core']['expiry']:360;

//            $words =  reusableStatus customerEmail invoiceNumber  clientId amount chUnKr1nkZ  customerName
            $customerName = trim($billingData->getFirstname() . " " . $billingData->getLastname());

            $dataWords =
                $mallId . //client->id
                $billingData->getEmail() . //customer->email
                $customerName . //customer->name
                $grandTotal . //order->amount
                $order->getIncrementId() . // order->invoice_number
                $expiryTime . // virtual_account_info->expired_time
                "false" . //virtual_account_info->reusable_status
                $sharedId; //shared key

            $words = hash('sha256', $dataWords);

            $params = array(
                "client" => array(
                    "id" => $mallId
                ),
                "order" => array(
                    "invoice_number" => $order->getIncrementId(),
                    "amount" => $grandTotal
                ),
                "virtual_account_info" => array(
                    "expired_time" => isset($config['payment']['core']['expiry']) && (int) $config['payment']['core']['expiry'] != 0 ?  $config['payment']['core']['expiry']:360,
                    "reusable_status" => 'false',
                ),
                "customer" => array(
                    "name" => trim($billingData->getFirstname() . " " . $billingData->getLastname()),
                    "email" => $billingData->getEmail()
                ),
                "security" => array(
                    "check_sum" => $words
                )
            );

            $this->logger->info('===== Request controller VA GATEWAY ===== request param = '. json_encode($params, JSON_PRETTY_PRINT));
            $this->logger->info('===== Request controller VA GATEWAY ===== send request');

            $this->logger->info('NILAI PAYMENT CHANNEL '. $configCode);

            $orderStatus = 'FAILED';
            try {
                $result = $this->helper->doGeneratePaycode($params,$configCode);

            } catch(\Exception $e) {
                $this->logger->info('Eception '.$e);
                $result['res_response_code'] = "500";
                $result['res_response_msg'] = "Can't connect to server";
            }

            $this->logger->info('===== Request controller VA GATEWAY ===== response payment = '. json_encode($result, JSON_PRETTY_PRINT));

            if(isset($result['virtual_account_info'])){
                $orderStatus = 'PENDING';
                $result['result'] = 'success';
            }else{

                $result['result'] = 'FAILED';
            }

            $params['SHAREDID'] = $sharedId;
            $params['RESPONSE'] = $result;

            $jsonResult = json_encode(array_merge($params), JSON_PRETTY_PRINT);

            $vaNumber = '';
            if(isset($result['virtual_account_info'])){
                $vaNumber = $result['virtual_account_info']['virtual_account_number'];
            }

            $this->resourceConnection->getConnection()->insert('doku_transaction', [
                    'quote_id' => $order->getQuoteId(),
                    'store_id' => $order->getStoreId(),
                    'order_id' => $order->getId(),
                    'trans_id_merchant' => $order->getIncrementId(),
                    'payment_channel_id' => $configCode,
                    'order_status' => $orderStatus,
                    'request_params' => $jsonResult,
                    'va_number' => $vaNumber,
                    'created_at' => 'now()',
                    'updated_at' => 'now()',
                    'doku_grand_total' => $grandTotal,
                    'admin_fee_type' => $config['payment'][$order->getPayment()->getMethod()]['admin_fee_type'],
                    'admin_fee_amount' => $config['payment'][$order->getPayment()->getMethod()]['admin_fee'],
                    'admin_fee_trx_amount' => $totalAdminFeeDisc['total_admin_fee'],
                    'discount_type' => $config['payment'][$order->getPayment()->getMethod()]['disc_type'],
                    'discount_amount' => $config['payment'][$order->getPayment()->getMethod()]['disc_amount'],
                    'discount_trx_amount' => $totalAdminFeeDisc['total_discount']
                ]);

            $base_url = $this->storeManagerInterface
                ->getStore($order->getStore()->getId())
                ->getBaseUrl();

            $redrectData['URL'] = $base_url . "jokulbackend/service/redirect";
            $redrectData['RESPONSECODE'] = $result['result'];
            $redrectData['RESPONSEMSG'] = $result['result'];
            $redrectData['TRANSIDMERCHANT'] = $order->getIncrementId();

            $wordsParams = array(
                'amount' => $grandTotal,
                'sharedid' => $sharedId,
                'invoice' => $order->getIncrementId(),
                'statuscode' => $result['result']
            );

            $redirectWords = $this->helper->doCreateWords($wordsParams);

            $redrectData['WORDS'] = $redirectWords;
            $redrectData['STATUSCODE'] = $result['result'];

        } else {
            $this->logger->info('===== Request controller VA GATEWAY ===== Order not found');
        }

        $this->logger->info('===== Request controller VA GATEWAY ===== end');

        if ($result['result'] == 'success') {
            echo json_encode(array('err' => false, 'response_msg' => 'Generate paycode Success',
                'result' => $redrectData));
        } else {
            $this->logger->info('===== Request controller VA GATEWAY Response ===== ' . print_r($result,true));
              echo json_encode(array('err' => true, 'response_msg' => 'Generate paycode failed ('.$result['errorMessage'].')',
                'result' => $redrectData));
        }

    }

}
