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
use \Jokul\Magento2\Model\GeneralConfiguration;

class RedirectCc extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
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
        GeneralConfiguration $generalConfiguration,
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
        $this->config = $generalConfiguration;
        return parent::__construct($context);
    }

    public function execute()
    {
        $path = "";
        $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Start');
        $post = $this->getRequest()->getParams();

        $postJson = json_encode($post, JSON_PRETTY_PRINT);

        $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Looking for the order on the Magento Side');

        $this->logger->doku_log('RedirectCC','Redirect Controller  Finding order...');
        $transactionType = isset($post['TRANSACTIONTYPE']) ? $post['TRANSACTIONTYPE'] : "";
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('jokul_transaction');

        if (!isset($post['invoice_number'])) {
            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Invoice Number empty');

            $path = "checkout/onepage/failure";
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath($path);
        }

        $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $post['invoice_number'] . "'";

        $dokuOrder = $connection->fetchRow($sql);
        $paymentChannel = $dokuOrder['payment_channel_id'];

        if (!isset($dokuOrder['invoice_number'])) {
            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Invoice Number not found in jokul_transaction table');

            $path = "";
            $this->messageManager->addError(__('Order not found!'));

            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath($path);
        }

        $requestParams = json_decode($dokuOrder['request_params'], true);
        $sharedKey = $this->config->getSharedKey();
        $requestAmount = $requestParams['order']['amount'];

        $order = $this->order->loadByIncrementId($post['invoice_number']);

        if ($order->getEntityId()) {

            $isSuccessOrder = false;

            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Order found',$order->getIncrementId());

            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller  Checking Redirect Signature',$order->getIncrementId());

            $redirectSignatureParams = array(
                'amount' => $requestAmount,
                'sharedkey' => $sharedKey,
                'invoice' => $order->getIncrementId(),
                'status' => $post['status']
            );

            $redirectSignature = $this->helper->generateRedirectSignature($redirectSignatureParams);

            if (strtolower($redirectSignature)  == strtolower($post['redirect_signature'])) {
                $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Redirect Signature match!',$order->getIncrementId());

                $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Check Order Status',$order->getIncrementId());
                $isSuccessOrder = true;
                $path = "checkout/onepage/success";
                    
                try {
                    $this->logger->doku_log('RedirectCC','Jokul - RedirectPending Controller Check Status',$order->getIncrementId());
                    
                    $countHit = 0;
                    $maxCountHit = 4;
                    while (true) {
                        usleep(3000000);
                        $clientId = $this->config->getClientId();;
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

                        $this->logger->doku_log('RedirectCC','start check status!' .$countHit,$order->getIncrementId());
                        $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $post['invoice_number'] . "'";
                        $dokuOrder = $connection->fetchRow($sql);
                        $orderStatus = $dokuOrder['order_status'];

                        if ($orderStatus == "SUCCESS") {
                            $this->deleteCart();
                            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller DB Success!' .$countHit,$order->getIncrementId());
                            break;
                        } else if ($orderStatus == "FAILED") {
                            $path = "checkout/cart";
                            $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller DB Failed!'.$countHit,$order->getIncrementId());
                            break;
                        } else {
                            $signature = $this->helper->doCheckStatusRequestSignature($signatureParams);
                            $result = $this->helper->doCheckStatus($signatureParams, $signature);

                            if (isset($result)) {
                                if (!isset($result['error'])) {
                                    if ($result['transaction']['status'] == 'SUCCESS') {
                                        $this->deleteCart();
                                        $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Check Status Success!'.$countHit, $order->getIncrementId());
                                        break;
                                    } else if ($result['transaction']['status'] == 'FAILED') {
                                        if ($countHit == $maxCountHit){
                                            $path = "checkout/cart";
                                            $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                                            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Check Status Failed!'.$countHit,$order->getIncrementId());
                                            break;
                                        }
                                    } else if (!isset($result['transaction'])) {
                                        if ($countHit == $maxCountHit){
                                            $path = "checkout/cart";
                                            $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                                            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Check Status Not Found'.$countHit,$order->getIncrementId());
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
                    
                $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Send Email Notification - Start',$order->getIncrementId());
                $this->helper->sendDokuEmailOrder($order, "", $dokuOrder, $isSuccessOrder, "");
                $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Send Email Notification - End',$order->getIncrementId());
            } else {
                $path = "";
                $order->cancel()->save();
                $this->messageManager->addError(__('Sorry, something went wrong!'));
                $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Redirect Signature not match!',$order->getIncrementId());
            }
        } else {
            $path = "";
            $this->messageManager->addError(__('Order not found'));
            $this->logger->doku_log('RedirectCC','Jokul - Redirect Controller Order not found');
        }

        $sql = "Update " . $tableName . " SET `va_number` = '', `updated_at` = 'now()', `redirect_params` = '" . $postJson . "' where invoice_number = '" . $post['invoice_number'] . "'";
        $connection->query($sql);

        $this->logger->doku_log('RedirectCC','Redirect Controller  End');

        $this->session->setLastSuccessQuoteId($order->getQuoteId());
        $this->session->setLastOrderId($order->getEntityId());
        $this->session->setLastRealOrderId($order->getEntityId());

        $params = array('invoice' => $order->getIncrementId(), 'result' => $post['status'], 'transaction_type' => $transactionType);
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath($path, $params);
    }

    public function deleteCart()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cartObject = $objectManager->create('Magento\Checkout\Model\Cart');
        $cartObject->truncate()->save();

        $this->logger->doku_log('RedirectCC','delete Cart');
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
