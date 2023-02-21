<?php

namespace Jokul\Magento2\Controller\Payment;

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

class RequestCheckout extends \Magento\Framework\App\Action\Action
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
    protected $productRepository;

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
        ProductRepositoryInterface $productRepository
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
        $this->logger->info('===== Request controller Checkout GATEWAY ===== Start');

        $this->logger->info('===== Request controller Checkout GATEWAY ===== Find Order');

        $result = array();
        $redirectData = array();

        $order = $this->getOrder();

        if ($order->getEntityId()) {
            $order->setState(Order::STATE_NEW);
            $this->session->getLastRealOrder()->setState(Order::STATE_NEW);
            $order->save();

            $this->logger->info('===== Request controller Checkout GATEWAY ===== Order Found!');

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

            $clientId = $config['payment']['core']['client_id'];
            $sharedKey = $this->config->getSharedKey();
            $expiryTime = isset($config['payment']['core']['expiry']) && (int) $config['payment']['core']['expiry'] != 0 ?  $config['payment']['core']['expiry'] : 60;

            $customerName = trim($billingData->getFirstname() . " " . $billingData->getLastname());

            $requestTarget = "/checkout/v1/payment";

            $requestTimestamp = date("Y-m-d H:i:s");
            $requestTimestamp = date(DATE_ISO8601, strtotime($requestTimestamp));

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($order->getId());
            $orderItems = $order->getAllItems();

            $itemQty = array();
            $discountTotal = 0;

            $pattern = "/[^A-Za-z0-9? .,_-]/";

            foreach ($orderItems as $item) {
                $totalItem = number_format($item->getQtyOrdered(), 0, "", "");
                $amountItem = number_format($item->getPrice(),0,"","");
                $discountItem = number_format($item->getDiscountAmount(),0,"","");
                $totalAmountItem = ($amountItem * $totalItem) - $discountItem;
                $AmountPerItem = $totalAmountItem / $totalItem;
                $product = $this->productRepository->get($item->getSku());
                $itemQty[] = array(
                    'price' => number_format($AmountPerItem,0,"",""),
                    'quantity' => number_format($item->getQtyOrdered(), 0, "", ""),
                    'name' => preg_replace($pattern, "", $item->getName()),
                    'sku' => $item->getSku(),
                    'category' => 'uncategorized',
                    'url' => $product->getProductUrl()
                );
            }

            if ($order->getShippingAmount() > 0) {
                $itemQty[] = array(
                    'name' => 'Shipping',
                    'price' => number_format($order->getShippingAmount(),0,"",""),
                    'quantity' => '1',
                    'sku' => '01',
                    'category' => 'uncategorized',
                    'url' => 'http://www.doku.com/'
                );
            }

            $taxTotal = 0;
            $taxTotal = $order->getTaxAmount();
            if ($taxTotal > 0) {
                $itemQty[] = array(
                    'name' => 'Tax',
                    'price' => number_format($taxTotal,0,"",""),
                    'quantity' => '1',
                    'sku' => '02',
                    'category' => 'uncategorized',
                    'url' => 'http://www.doku.com/'
                );
            }

            $signatureParams = array(
                "clientId" => $clientId,
                "key" => $sharedKey,
                "requestTarget" => $requestTarget,
                "requestId" => rand(1, 100000),
                "requestTimestamp" => substr($requestTimestamp, 0, 19) . "Z"
            );

            $redirectSignatureParams = array(
                'amount' => number_format($grandTotal,0,"",""),
                'sharedkey' => $sharedKey,
                'invoice' => $order->getIncrementId(),
                'status' => 'success'
            );

            $redirectSignature = $this->helper->generateRedirectSignature($redirectSignatureParams);
            $redirectParamsSuccess['invoice_number'] = $order->getIncrementId();
            $redirectParamsSuccess['redirect_signature'] = $redirectSignature;

            $redirectParamsSuccess['status'] = 'success';
            $redirectParamsSuccess['TRANSACTIONTYPE'] = 'checkoutsuccess';

            $base_url = $this->storeManagerInterface->getStore($order->getStore()->getId())->getBaseUrl();
            $callbackUrl = $base_url . "jokulbackend/service/redirect?" . http_build_query($redirectParamsSuccess);
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
            $autoRedirect = $this->config->getAutoRedirect();
            $shippingAddress = $order->getShippingAddress();
            $street = $shippingAddress->getStreet();
            $streetAddress = !empty($street[0]) ? $street[0] : $street[1];

            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            $customerId = "";
            $phone = preg_replace('/[^0-9]/', '', $order->getShippingAddress()->getTelephone());
            if($customerSession->isLoggedIn()) {
                $customerId = $customerSession->getCustomer()->getId();
            } else {
                $customerId = $phone;
            }

            $statusSubAccount = $this->helper->getStatusSubAccount($order->getPayment()->getMethod());
            $patternAddress = "/[^A-Za-z0-9? .-\/+,=_:@]/";
            if ($statusSubAccount == 'yes') {
                $subAccountId = $this->helper->getSubAccountId($order->getPayment()->getMethod());
                $params = array(
                    "order" => $autoRedirect === '1' ? array(
                        "invoice_number" => $order->getIncrementId(),
                        "line_items" => $itemQty,
                        "amount" => number_format($grandTotal,0,"",""),
                        "callback_url" => $callbackUrl,
                        "currency" => "IDR",
                        "auto_redirect" => true,
                        "disable_retry_payment" => true
                    ): array(
                        "invoice_number" => $order->getIncrementId(),
                        "line_items" => $itemQty,
                        "amount" => number_format($grandTotal,0,"",""),
                        "callback_url" => $callbackUrl,
                        "currency" => "IDR",
                        "auto_redirect" => false,
                        "disable_retry_payment" => false
                    ),
                    "payment" => array(
                        "payment_due_date" => $expiryTime
                    ),
                    "customer" => array(
                        "id" => $customerId,
                        "name" => trim($customerName),
                        "email" => $billingData->getEmail(),
                        "phone" => $phone,
                        "country" => $billingData->getData('country_id'),
                        "postcode" => $billingData->getPostcode(),
                        "state" => !empty($order->getShippingAddress()->getRegion()) ? $order->getShippingAddress()->getRegion() : "-",
                        "city" => $billingData->getCity(),
                        "address" => preg_replace($patternAddress, "", $billingData->getData('street'))
                    ),
                    "additional_info" => array (
                        "integration" => array (
                            "name" => "magento-plugin",
                            "version" => "1.4.5",
                            "cms_version" => $productMetadata->getVersion()
                        ),
                        "method" => "Jokul Checkout",
                        "doku_wallet_notify_url" => $base_url."jokulbackend/service/notify",
                        "account" => array (
                            "id" => $subAccountId
                        )
                    ),
                    "shipping_address" => array(
                        "first_name" => $shippingAddress->getFirstName(),
                        "address" => preg_replace($patternAddress, "", $streetAddress),
                        "city" => $shippingAddress->getCity(),
                        "postal_code" => $shippingAddress->getPostcode(),
                        "phone" => $shippingAddress->getTelephone(),
                        "country_code" => $shippingAddress->getCountryId()
                    )
                );
            } else {
                $params = array(
                    "order" => $autoRedirect === '1' ? array(
                        "invoice_number" => $order->getIncrementId(),
                        "line_items" => $itemQty,
                        "amount" => number_format($grandTotal,0,"",""),
                        "callback_url" => $callbackUrl,
                        "currency" => "IDR",
                        "auto_redirect" => true,
                        "disable_retry_payment" => false
                    ): array(
                        "invoice_number" => $order->getIncrementId(),
                        "line_items" => $itemQty,
                        "amount" => number_format($grandTotal,0,"",""),
                        "callback_url" => $callbackUrl,
                        "currency" => "IDR",
                        "auto_redirect" => false,
                        "disable_retry_payment" => true
                    ),
                    "payment" => array(
                        "payment_due_date" => $expiryTime
                    ),
                    "customer" => array(
                        "id" => $customerId,
                        "name" => trim($customerName),
                        "email" => $billingData->getEmail(),
                        "phone" => $phone,
                        "country" => $billingData->getData('country_id'),
                        "postcode" => $billingData->getPostcode(),
                        "state" => !empty($order->getShippingAddress()->getRegion()) ? $order->getShippingAddress()->getRegion() : "-",
                        "city" => $billingData->getCity(),
                        "address" => preg_replace($patternAddress, "", $billingData->getData('street'))
                    ),
                    "additional_info" => array (
                        "integration" => array (
                            "name" => "magento-plugin",
                            "version" => "1.4.5",
                            "cms_version" => $productMetadata->getVersion()
                        ),
                        "method" => "Jokul Checkout",
                        "doku_wallet_notify_url" => $base_url."jokulbackend/service/notify"
                    ),
                    "shipping_address" => array(
                        "first_name" => $shippingAddress->getFirstName(),
                        "address" => preg_replace($patternAddress, "", $streetAddress),
                        "city" => $shippingAddress->getCity(),
                        "postal_code" => $shippingAddress->getPostcode(),
                        "phone" => $shippingAddress->getTelephone(),
                        "country_code" => $shippingAddress->getCountryId()
                    )
                );
            }

            $this->logger->info('===== Request controller Checkout GATEWAY ===== request param = ' . json_encode($params, JSON_PRETTY_PRINT));
            $this->logger->info('===== Request controller Checkout GATEWAY ===== send request');

            $this->logger->info('PAYMENT CHANNEL ' . $configCode);
            $this->logger->info('AUTOREDIRECT ' . $autoRedirect);

            $orderStatus = 'FAILED';
            try {
                $signature = $this->helper->doCreateRequestSignature($signatureParams, $params);
                $result = $this->helper->doGenerateCheckout($signatureParams, $params, $signature);
            } catch (\Exception $e) {
                $this->logger->info('Eception ' . $e);
                $result['res_response_code'] = "500";
                $result['res_response_msg'] = "Can't connect to server";
            }

            $this->logger->info('===== Request controller Checkout GATEWAY ===== response payment = ' . json_encode($result, JSON_PRETTY_PRINT));

            if (isset($result['response']['order']['invoice_number'])) {
                $orderStatus = 'PENDING';
                $result['result'] = 'pending';
            } else {
                $result['result'] = 'FAILED';
                $result['errorMessage'] = $result['message'][0];
            }

            $params['shared_key'] = $sharedKey;
            $params['response'] = $result;
            $params['transactiontype'] = 'checkoutpending';

            $jsonResult = json_encode(array_merge($params), JSON_PRETTY_PRINT);

            $vaNumber = '';
            if (isset($result['response']['order']['invoice_number'])) {
                $vaNumber = $result['response']['order']['invoice_number'];
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
                'doku_grand_total' => number_format($grandTotal,0,"",""),
                'admin_fee_type' => '',
                'admin_fee_amount' => 0,
                'admin_fee_trx_amount' => 0,
                'discount_type' => '',
                'discount_amount' => 0,
                'discount_trx_amount' => 0
            ]);

            $base_url = $this->storeManagerInterface
                ->getStore($order->getStore()->getId())
                ->getBaseUrl();

            $redirectData['url'] = $base_url . "jokulbackend/service/redirectpending";
            $redirectData['invoice_number'] = $order->getIncrementId();

            $redirectData['redirect_signature'] = $redirectSignature;
            $redirectData['status'] = 'success';
        } else {
            $this->logger->info('===== Request controller Checkout GATEWAY ===== Order not found');
        }

        $this->logger->info('===== Request controller Checkout GATEWAY ===== end');

        if ($result['message'][0] == 'SUCCESS') {
            $this->logger->info('===== Print Json '.json_encode($redirectData));
            echo json_encode(array(
                'err' => false,
                'response_message' => 'Generate Checkout Success',
                'result' => $redirectData
            ));
            $this->logger->info('===== Request controller Checkout Redirecting to Success Page' . $order->getIncrementId());
        } else {
            $this->logger->info('===== Request controller Checkout GATEWAY Response ===== ' . print_r($result, true));

            $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
            $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');

            $order = $_checkoutSession->getLastRealOrder();
            $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $_checkoutSession->replaceQuote($quote);
                echo json_encode(array(
                    'err' => false,
                    'response_msg' => 'Generate Checkout failed (' . $result['errorMessage'] . ')',
                    'result' => $redirectData
                ));
            }

        }
    }
}
