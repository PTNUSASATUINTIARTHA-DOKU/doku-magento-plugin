<?php

namespace Jokul\Magento2\Controller\Payment;

use Magento\Sales\Model\Order;
use \Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Jokul\Magento2\Helper\Data;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Request\Http;
use Jokul\Magento2\Model\GeneralConfiguration;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Request extends \Magento\Framework\App\Action\Action {

    protected $_pageFactory;
    protected $session;
    protected $order;
    protected $logger;
    protected $resourceConnection;
    protected $config;
    protected $helper;
    protected $sessionFactory;
    protected $httpRequest;
    protected $storeManagerInterface;
    protected $_timezoneInterface;

    public function __construct(
        Session $session, 
        Order $order, 
        ResourceConnection $resourceConnection,  
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
        $this->config = $_generalConfiguration;
        $this->helper = $helper;
        $this->_pageFactory = $pageFactory;
        $this->sessionFactory = $sessionFactory;
        $this->httpRequest = $httpRequest;
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

        $this->logger->info('===== Request controller (Magento2) ===== Start');

        $this->logger->info('===== Request controller (Magento2) ===== Find Order');

        $result = array();

        $order = $this->getOrder();

        if ($order->getEntityId()) {
            $order->setState(Order::STATE_NEW);
            $this->session->getLastRealOrder()->setState(Order::STATE_NEW);
            $order->save();
            
            $this->logger->info('===== Request controller (Magento2) ===== Order Found!');

            $configCode = $this->config->getRelationPaymentChannel($order->getPayment()->getMethod());

            $billingData = $order->getBillingAddress();
            $config = $this->config->getConfig();
            
            $realGrandTotal = $order->getGrandTotal();

            $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
                    $config['payment'][$order->getPayment()->getMethod()]['admin_fee'], 
                    $config['payment'][$order->getPayment()->getMethod()]['admin_fee_type'],
                    $config['payment'][$order->getPayment()->getMethod()]['disc_amount'], 
                    $config['payment'][$order->getPayment()->getMethod()]['disc_type'],
                    $realGrandTotal);

            $grandTotal = $realGrandTotal + $totalAdminFeeDisc['total_admin_fee'];
            
            $buffGrandTotal = $grandTotal - $totalAdminFeeDisc['total_discount'];
            
            $grandTotal = number_format($buffGrandTotal, 2, ".", "");
            
            $clientId = $config['payment']['core']['client_id'];
            $sharedId = $this->config->getSharedKey();

            $words = $this->helper->doCreateWords(
                    array(
                        'amount' => $grandTotal,
                        'invoice' => $order->getIncrementId(),
                        'clientid' => $clientId,
                        'sharedid' => $sharedId
                    )
            );
            
            $basket = "";
            foreach ($order->getAllVisibleItems() as $item) {
                $basket .= preg_replace("/[^a-zA-Z0-9\s]/", "", $item->getName()). ',' . number_format($item->getPrice(), 2, ".", "") . ',' . (int) $item->getQtyOrdered() . ',' .
                        number_format(($item->getPrice() * $item->getQtyOrdered()), 2, ".", "") . ';';
            }

            $url = $config['payment']['core']['mip_request_url'];
            
            $requestArr = array(
                'CLIENTID' => $clientId,
                'CHAINMERCHANT' => $config['payment']['core']['chain_id'] ? $config['payment']['core']['chain_id'] : 'NA',
                'AMOUNT' => $grandTotal,
                'PURCHASEAMOUNT' => $grandTotal,
                'INVOICENUMBER' => $order->getIncrementId(),
                'WORDS' => $words,
                'REQUESTDATETIME' => $this->_timezoneInterface->date()->format('YmdHis'),
                'CURRENCY' => '360',
                'PURCHASECURRENCY' => '360',
                'SESSIONID' => $order->getIncrementId(),
                'NAME' => trim($billingData->getFirstname() . " " . $billingData->getLastname()),
                'EMAIL' => $billingData->getEmail(),
                'BASKET' => $basket,
                'MOBILEPHONE' => $billingData->getTelephone(),
                'PAYMENTCHANNEL' => $configCode
            );

            $this->logger->info('parameter : ' . json_encode($requestArr, JSON_PRETTY_PRINT));
            
            $response = $this->helper->sendRequest($requestArr, $url);
            
            $this->logger->info('response : ' . json_encode($response, JSON_PRETTY_PRINT));
            
            $result = array();
            
            if($configCode == "18"){
                if (!isset($response['INVOICENUMBER']) || $response['RESULTMSG'] == 'FAILED') {
                    echo json_encode(array('err' => true, 'response_msg' => 'Generate request failed',
                        'result' => array()));
                    exit;
                }

                $buffRedirectParam = explode(";;",$response['REDIRECTPARAMETER']);

                foreach($buffRedirectParam as $param){
                    $buffParam = explode("||", $param);
                    $result[$buffParam[0]] = $buffParam[1];
                }

                $result['URL'] = $response['REDIRECTURL'];
            } else {
                if (!isset($response['RESPONSECODE']) || $response['RESPONSECODE'] != '0000') {
                    echo json_encode(array('err' => true, 'response_msg' => 'Generate request failed',
                        'result' => array()));
                    exit;
                }
                
                $base_url = $this->storeManagerInterface
                        ->getStore($order->getStore()->getId())
                        ->getBaseUrl();
                
                $result['URL'] = $base_url."jokulbackend/service/redirect";
                $result['RESPONSECODE'] = $response['RESPONSECODE'];
                $result['RESPONSEMSG'] = $response['RESPONSEMSG'];
                $result['INVOICENUMBER'] = $order->getIncrementId();
                
                $wordsParams = array(
                    'amount' => $grandTotal,
                    'sharedid' => $sharedId,
                    'invoice' => $order->getIncrementId(),
                    'statuscode' => $response['RESPONSECODE']
                );


                $redirectWords = $this->helper->doCreateWords($wordsParams);
                
                $result['WORDS'] = $redirectWords;
                $result['STATUSCODE'] = $response['RESPONSECODE'];
            }
            
            $requestArr['URL'] = $url;
            $requestArr['SHAREDID'] = $sharedId;
            $requestArr['RESPONSE'] = $response;
            
            $jsonRequest = json_encode($requestArr, JSON_PRETTY_PRINT);
            
            $this->resourceConnection->getConnection()->insert('jokul_transaction', [
                    'quote_id' => $order->getQuoteId(),
                    'store_id' => $order->getStoreId(),
                    'order_id' => $order->getId(),
                    'invoice_number' => $order->getIncrementId(),
                    'payment_channel_id' => $configCode,
                    'order_status' => 'REQUEST',
                    'request_params' => $jsonRequest,
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
            
        } else {
            $this->logger->info('===== Request controller (Magento2) ===== Order not found');
        }

        $this->logger->info('===== Request controller (Magento2) ===== end');
        
        echo json_encode(array('err' => false, 'response_msg' => 'Generate request Success',
                    'result' => $result));

    }

}
