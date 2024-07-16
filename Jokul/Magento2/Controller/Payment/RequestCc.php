<?php

namespace Jokul\Magento2\Controller\Payment;

use Magento\Sales\Model\Order;
use Magento\Setup\Exception;
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

class RequestCc extends \Magento\Framework\App\Action\Action
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
    protected $_customerSession;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
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
        $this->_customerSession = $customerSession;
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

    public function keepCart(){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
        $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');

        $order = $_checkoutSession->getLastRealOrder();
        $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
        if ($quote->getId()) {
            $quote->setIsActive(1)->setReservedOrderId(null)->save();
            $_checkoutSession->replaceQuote($quote);
        }
    }
    public function execute()
    {
        $this->logger->doku_log('RequestCC','Request controller Credit Card Start');

        $this->logger->doku_log('RequestCC','Request controller Credit Card Find Order');

        $result = array();
        $redirectData = array();

        $order = $this->getOrder();
        if ($order->getEntityId()) {
            $order->setState(Order::STATE_NEW);
            $this->session->getLastRealOrder()->setState(Order::STATE_NEW);

            $this->logger->doku_log('RequestCC','Request controller Credit Card Order Found!',$order->getIncrementId());

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
            $sharedId = $this->config->getSharedKey();
            $expiryTime = isset($config['payment']['core']['expiry']) && (int) $config['payment']['core']['expiry'] != 0 ?  $config['payment']['core']['expiry'] : 0;

            $customerName = trim($billingData->getFirstname() . " " . $billingData->getLastname());

            $requestTarget = "/credit-card/v1/payment-page";

            $requestTimestamp = date("Y-m-d H:i:s");
            $requestTimestamp = date(DATE_ISO8601, strtotime($requestTimestamp));

            $regId = rand(1, 100000);
            $signatureParams = array(
                "clientId" => $clientId,
                "key" => $sharedId,
                "requestTarget" => $requestTarget,
                "requestId" => $this->guidv4(),
                "requestTimestamp" => substr($requestTimestamp, 0, 19) . "Z"
            );

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($order->getId());
            $orderItems = $order->getAllItems();
            
            $itemQty = array(); 
            $pattern = "/[^A-Za-z0-9? .,_-]/";
            
            foreach ($orderItems as $item) {
                $totalItem = number_format($item->getQtyOrdered(), 0, "", "");
                $amountItem = number_format($item->getPrice(),0,"","");
                $discountItem = number_format($item->getDiscountAmount(),0,"","");
                $totalAmountItem = ($amountItem * $totalItem) - $discountItem;
                $AmountPerItem = $totalAmountItem / $totalItem;
                $itemQty[] = array('price' => number_format($AmountPerItem,0,"",""), 'quantity' => number_format($item->getQtyOrdered(), 0, "", ""), 'name' => preg_replace($pattern, "", $item->getName()), 'sku' => $item->getSku(), 'category' => 'uncategorized');
            }
            
            if ($order->getShippingAmount() > 0) {
                $itemQty[] = array('name' => 'Shipping', 'price' => number_format($order->getShippingAmount(),0,"",""), 'quantity' => '1', 'sku' => '01', 'category' => 'uncategorized');
            }

            $taxTotal = 0;
            $taxTotal = $order->getTaxAmount();
            if ($taxTotal > 0) {
                $itemQty[] = array('name' => 'Tax', 'price' => number_format($taxTotal,0,"",""), 'quantity' => '1', 'sku' => '02', 'category' => 'uncategorized');
            }

            $wordsParams = array(
                'amount' => $grandTotal,
                'shared_key' => $sharedId,
                'invoice' => $order->getIncrementId(),
                'status' => 'success'
            );

            $wordsParamsFailed = array(
                'amount' => $grandTotal,
                'shared_key' => $sharedId,
                'invoice' => $order->getIncrementId(),
                'status' => 'failed'
            );

            $redirectWords = $this->helper->generateRedirectSignature($wordsParams);
            $redirectWordsfailed = $this->helper->generateRedirectSignature($wordsParamsFailed);

            $redirectParamsSuccess['invoice_number'] = $order->getIncrementId();
            $redirectParamsSuccess['redirect_signature'] = $redirectWords;

            $redirectParamsSuccess['status'] = 'success';
            $redirectParamsSuccess['TRANSACTIONTYPE'] = 'cc';


            $base_url = $this->storeManagerInterface->getStore($order->getStore()->getId())->getBaseUrl();
            $callbackUrl = $base_url . "jokulbackend/service/redirectcc?" . http_build_query($redirectParamsSuccess);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
            $statusSubAccount = $this->helper->getStatusSubAccount($order->getPayment()->getMethod());
            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            $customerId = "";
            $phone = preg_replace('/[^0-9]/', '', $order->getShippingAddress()->getTelephone());
            if($customerSession->isLoggedIn()) {
                $customerId = $customerSession->getCustomer()->getId();
            } else {
                $customerId = $phone;
            }
            
            $patternAddress = "/[^A-Za-z0-9? .-\/+,=_:@]/";
            if ($statusSubAccount == 'yes') {
                $subAccountId = $this->helper->getSubAccountId($order->getPayment()->getMethod());
                $params = array(
                    "customer" => array(
                        "id" => $customerId,
                        "name" => $customerName,
                        "email" => $billingData->getEmail(),
                        "phone" => $phone,
                        "country" => $billingData->getData('country_id'),
                        "address" => preg_replace($patternAddress, "", $billingData->getData('street'))
                    ),
                    "order" => array(
                        "invoice_number" => $order->getIncrementId(),
                        "line_items" => $itemQty,
                        "amount" => $grandTotal,
                        "callback_url" => $callbackUrl,
                        "auto_redirect" => true,
                        "session_id" => $regId,
                    ),
                    "override_configuration" => array(
                        "themes" => array(
                            "language" => $this->config->getCCThemelanguage() != "" ? $this->config->getCCThemelanguage() : "" ,
                            "background_color" => $this->config->getCCThemeBackground_color() != "" ? $this->config->getCCThemeBackground_color() : "",
                            "font_color" => $this->config->getCCThemeFont_color() != "" ? $this->config->getCCThemeFont_color() : "",
                            "button_background_color" => $this->config->getCCThemeButton_background_color() != "" ? $this->config->getCCThemeButton_background_color() : "",
                            "button_font_color" => $this->config->getCCThemeButton_font_color() != "" ? $this->config->getCCThemeButton_font_color() : "",
                        )
                    ),
                    "additional_info" => array (
                        "integration" => array (
                            "name" => "magento-plugin",
                            "version" => "1.4.5",
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
                    "customer" => array(
                        "id" => $customerId,
                        "name" => $customerName,
                        "email" => $billingData->getEmail(),
                        "phone" => $phone,
                        "country" => $billingData->getData('country_id'),
                        "address" => preg_replace($patternAddress, "", $billingData->getData('street'))
                    ),
                    "order" => array(
                        "invoice_number" => $order->getIncrementId(),
                        "line_items" => $itemQty,
                        "amount" => $grandTotal,
                        "callback_url" => $callbackUrl,
                        "auto_redirect" => true,
                        "session_id" => $regId,
                    ),
                    "override_configuration" => array(
                        "themes" => array(
                            "language" => $this->config->getCCThemelanguage() != "" ? $this->config->getCCThemelanguage() : "" ,
                            "background_color" => $this->config->getCCThemeBackground_color() != "" ? $this->config->getCCThemeBackground_color() : "",
                            "font_color" => $this->config->getCCThemeFont_color() != "" ? $this->config->getCCThemeFont_color() : "",
                            "button_background_color" => $this->config->getCCThemeButton_background_color() != "" ? $this->config->getCCThemeButton_background_color() : "",
                            "button_font_color" => $this->config->getCCThemeButton_font_color() != "" ? $this->config->getCCThemeButton_font_color() : "",
                        )
                    ),
                    "additional_info" => array (
                        "integration" => array (
                            "name" => "magento-plugin",
                            "version" => "1.4.5",
                            "cms_version" => $productMetadata->getVersion()
                        ),
                        "method" => "Jokul Direct"
                    )
                );
            }

            $this->logger->doku_log('RequestCC','Request controller Credit Card request data : ' . json_encode($params, JSON_PRETTY_PRINT));
            $this->logger->doku_log('RequestCC','Request controller Credit Card send request');


            $orderStatus = 'FAILED';
            try {
                $signature = $this->helper->doCreateRequestSignature($signatureParams, $params);
                $result = $this->helper->doRequestCcPaymentForm($signatureParams, $params, $signature);
            } catch (\Exception $e) {
                $this->logger->doku_log('RequestCC','Eception ' . $e);
            }

            if (isset($result['credit_card_payment_page'])) {
                $this->keepCart();
                $orderStatus = 'PENDING';
                $result['result'] = 'success';
                $redirectData['URL'] = $result['credit_card_payment_page']['url'];
                $redirectData['RESPONSEMSG'] = $result['result'];
            } else {
                $result['result'] = 'FAILED';
                $result['errorMessage'] = $result['error']['message'];
            }

            $params['shared_key'] = $sharedId;
            $params['status'] = $result;

            $jsonResult = json_encode(array_merge($params), JSON_PRETTY_PRINT);

            $vaNumber = '';
            if (isset($result['order'])) {
                $vaNumber = $result['order']['invoice_number'];
            }

            $adminFee = $config['payment'][$order->getPayment()->getMethod()]['admin_fee'];
            if ($adminFee == null) {
                $adminFee = "0";
            }

            $discountAmount = $config['payment'][$order->getPayment()->getMethod()]['disc_amount'];
            if ($discountAmount == null) {
                $discountAmount = "0";
            }

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
                'admin_fee_amount' => $adminFee,
                'admin_fee_trx_amount' => $totalAdminFeeDisc['total_admin_fee'],
                'discount_type' => $config['payment'][$order->getPayment()->getMethod()]['disc_type'],
                'discount_amount' => $discountAmount,
                'discount_trx_amount' => $totalAdminFeeDisc['total_discount']
            ]);

        } else {
            $this->logger->doku_log('RequestCC','Request controller Credit Card Order not found');
        }

        $this->logger->doku_log('RequestCC','Request controller Credit Card end',$order->getIncrementId());

        if (isset($result['credit_card_payment_page'])) {
            echo json_encode(array(
                'err' => false, 'response_msg' => 'Generate Url Credit Card Success',
                'result' => $redirectData
            ));
        } else {
            $this->logger->doku_log('RequestCC','Jokul - Credit Card Request Controller Prepare Order Failed Procedure',$order->getIncrementId());
            $this->logger->doku_log('RequestCC','Jokul - Credit Card Request Controller Initiate Restore Cart',$order->getIncrementId());

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
            $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');

            $order = $_checkoutSession->getLastRealOrder();
            $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
            $this->logger->doku_log('RequestCC','Jokul - Credit Card Request Controller Get Cart');
            if ($quote->getId()) {
                $this->logger->doku_log('RequestCC','Jokul - Credit Card Request Controller Checking Cart');
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $_checkoutSession->replaceQuote($quote);
                $this->logger->doku_log('RequestCC','Jokul - Credit Card Request Controller Restoring Cart');
                $order->cancel()->save();
                $this->logger->doku_log('RequestCC','Jokul - Credit Card Request Controller Cart Restored',$order->getIncrementId());
                echo json_encode(array(
                    'err' => true,
                    'response_message' => $result['error']['message'],
                    'result' => $redirectData
                ));
            }
        }
    }

    function guidv4($data = null)
    {
        $data = $data ?? random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
