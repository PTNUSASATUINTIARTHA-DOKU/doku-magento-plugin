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

    public function __construct(
        Order $order,
        Logger $logger,
        Session $session,
        ResourceConnection $resourceConnection,
        Data $helper,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timeZone,
        Validator $formKeyValidator,
        TransactionRepositoryInterface $transactionRepository
    ) {

        $this->order = $order;
        $this->logger = $logger;
        $this->session = $session;
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
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
            if (in_array($paymentChannel, $O2Ochannel)) {
                $expiryValue = $requestParams['online_to_offline_info']['expired_time'];
                $vaNumber = $requestParams['response']['online_to_offline_info']['payment_code'];
            } else {
                $expiryValue = $requestParams['virtual_account_info']['expired_time'];
                $vaNumber = $requestParams['response']['virtual_account_info']['virtual_account_number'];
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
                    $this->deleteCart();
                } else {
                    $path = "checkout/cart";
                    $this->messageManager->addWarningMessage('Payment Failed. Please try again or contact our customer service.');
                    $order->cancel()->save();
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
}
