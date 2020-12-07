<?php

namespace Jokul\Magento2\Controller\Service;

use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Jokul\Magento2\Helper\Data;
use Magento\Framework\Data\Form\FormKey\Validator;
use Jokul\Magento2\Api\TransactionRepositoryInterface;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Redirect extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface {

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
            LoggerInterface $logger,
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

    public function execute() {
        $path = "";
        $this->logger->info('===== Redirect Controller  ===== Start');
        $post = $this->getRequest()->getParams();

        $postJson = json_encode($post, JSON_PRETTY_PRINT);

        $this->logger->info('REDIRECT PARAMS : ' . $postJson);

        $this->logger->info('===== Redirect Controller  ===== Finding order...');

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('jokul_transaction');

        if(!isset($post['INVOICENUMBER'])) {

            $path = "checkout/onepage/failure";
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath($path);
        }


        $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $post['INVOICENUMBER'] . "'";

        $dokuOrder = $connection->fetchRow($sql);

        if (!isset($dokuOrder['invoice_number'])) {
            $this->logger->info('===== Notify Controller ===== Trans ID Merchant not found! in jokul_transaction table');

            $path = "";
            $this->messageManager->addError(__('Cannot found your order ID!'));

            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath($path);
        }

        $requestParams = json_decode($dokuOrder['request_params'], true);

        $this->logger->info('===== Redirect Controller  ===== REQUEST PARAM: '.json_encode($requestParams, JSON_PRETTY_PRINT));
        $sharedKey = $requestParams['SHAREDID'];

        $requestAmount = 0;
        if(isset($requestParams['order']['amount'])){
            $requestAmount = $requestParams['order']['amount'];
        }

        $expiryValue = 360;

        if (!empty($requestParams['EXPIRYTIME'])) {
            $expiryValue = $requestParams['EXPIRYTIME'];
        } else if (!empty($requestParams['expiredTime'])) {
            $expiryValue = $requestParams['expiredTime'];
        }

        $expiryGmtDate = date('Y-m-d H:i:s', (strtotime('+' . $expiryValue . ' minutes', time())));
        $expiryStoreDate = $this->timeZone->date(new \DateTime($expiryGmtDate))->format('Y-m-d H:i:s');

        $additionalParams = "";
        $vaNumber = "";
        if (isset($post['PAYMENTCODE']) && !empty($post['PAYMENTCODE'])) {
            $vaNumber = $post['PAYMENTCODE'];
            $additionalParams = " `va_number` = '" . $vaNumber . "', ";
        }

        $order = $this->order->loadByIncrementId($post['INVOICENUMBER']);

        if ($order->getEntityId()) {

            $isSuccessOrder = false;

            $this->logger->info('===== Redirect Controller  ===== Order found!');


            $this->logger->info('===== Redirect Controller  ===== Checking words');

            $wordsParams = array(
                'amount' => $requestAmount,
                'sharedid' => $sharedKey,
                'invoice' => $order->getIncrementId(),
                'statuscode' => $post['STATUSCODE']
            );

            $words = $this->helper->doCreateWords($wordsParams);

            if ($words == $post['WORDS']) {
                $this->logger->info('===== Redirect Controller  ===== Checking done');

                $this->logger->info('===== Redirect Controller  ===== Check STATUSCODE');

                if ($post['STATUSCODE'] == 'success') {
                    $isSuccessOrder = true;
                    $this->logger->info('===== Redirect Controller  ===== STATUSCODE Success');
                    $path = "checkout/onepage/success";
                } else {
                    $path = "checkout/onepage/failure";
                    $order->cancel()->save();
                    $this->logger->info('===== Redirect Controller  ===== STATUSCODE Failed!');
                }

                $this->logger->info('===== Redirect Controller ===== Send Email Order  ===== Start');

                $this->helper->sendDokuEmailOrder($order, $vaNumber, $dokuOrder, $isSuccessOrder, $expiryStoreDate);

                $this->logger->info('===== Redirect Controller ===== Send Email Order  ===== End');

            } else {
                $path = "";
                $order->cancel()->save();
                $this->messageManager->addError(__('Sorry, something went wrong!'));
                $this->logger->info('===== Redirect Controller ===== Words not match!');
            }
        } else {
            $path = "";
            $this->messageManager->addError(__('Order not found'));
            $this->logger->info('===== Redirect Controller  ===== Order not found');
        }

        $sql = "Update " . $tableName . " SET ".$additionalParams." `updated_at` = 'now()', `expired_at_gmt` = '".$expiryGmtDate."', `expired_at_storetimezone` = '".$expiryStoreDate."', `redirect_params` = '" . $postJson . "' where invoice_number = '" . $post['INVOICENUMBER'] . "'";
        $connection->query($sql);

        $this->logger->info('===== Redirect Controller  ===== End');

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath($path);
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
