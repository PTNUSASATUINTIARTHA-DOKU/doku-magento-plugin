<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 5/13/19
 * Time: 1:00 AM
 */

namespace Jokul\Magento2\Controller\Adminhtml\Authorization;

use Jokul\Magento2\Helper\Data;
use \Psr\Log\LoggerInterface;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Jokul\Magento2\Model\GeneralConfiguration;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;
use \Jokul\Magento2\Api\TransactionRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class Voidpayment extends \Magento\Backend\App\Action
{

    protected $order;
    protected $_helper;
    protected $logger;
    protected $messageManager;
    protected $_timezoneInterface;
    protected $_scopeConfig;
    protected $_generalConfiguration;
    protected $transactionRepository;
    protected $orderManagement;
    protected $builderInterface;


    public function __construct(
        LoggerInterface $loggerInterface,
        Context $context,
        Data $helper,
        TimezoneInterface $timezoneInterface,
        ScopeConfigInterface $scopeConfig,
        GeneralConfiguration $generalConfiguration,
        Order $order,
        TransactionRepositoryInterface $transactionRepository,
        OrderManagementInterface $orderManagement,
        BuilderInterface $_builderInterface

    )
    {
        parent::__construct($context);
        $this->_helper = $helper;
        $this->logger = $loggerInterface;
        $this->_timezoneInterface = $timezoneInterface;
        $this->_scopeConfig = $scopeConfig;
        $this->_generalConfiguration = $generalConfiguration;
        $this->order = $order;
        $this->transactionRepository = $transactionRepository;
        $this->orderManagement = $orderManagement;
        $this->builderInterface = $_builderInterface;
    }


    public function execute()
    {


        // TODO: Implement execute() method.
        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('order_id');

        $order = $this->order->load($id);
        $_dokuTrans = $this->transactionRepository->getByOrderId($id);

        $clientId = $this->_generalConfiguration->getClientId();
        $chainId = $this->_generalConfiguration->getChainId();
        $sharedKey = $this->_generalConfiguration->getSharedKey();

        $json = $_dokuTrans->getRequestParams();
        $requestData = json_decode($json, TRUE);

        $param = array(
            'CLIENTID' => $clientId,
            'CHAINMERCHANT' => $chainId,
            'INVOICENUMBER' => $_dokuTrans->getInvoiceNumber(),
            'SESSIONID' => $requestData['SESSIONID'],
            'PAYMENTCHANNEL' => '15', //only for CC
        );

        $param['WORDS'] = sha1($clientId . $sharedKey . $_dokuTrans->getInvoiceNumber() . $param['SESSIONID']);

        $void = $this->_helper->doVoid($param);

        $jsonRequest = json_encode($param);
        $jsonResponse = json_encode($void);
        $_dokuTrans->setVoidRequest($jsonRequest);
        $_dokuTrans->setVoidResponse($jsonResponse);
        $this->transactionRepository->save($_dokuTrans);

        $result = explode(";", $void);

        if ($result[0] == 'SUCCESS') {
            /** cancel order */

            try {
                if ($order->canCancel()) {
                    $this->orderManagement->cancel($order->getEntityId());
                }

                $_dokuTrans->setAuthorizationStatus('void');
                $this->transactionRepository->save($_dokuTrans);

                $payment = $order->getPayment();
                $payment->setLastTransactionId($_dokuTrans->getInvoiceNumber());
                $payment->setTransactionId($_dokuTrans->getInvoiceNumber());
                $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $void]);
                $message = __(json_encode($_POST, JSON_PRETTY_PRINT));
                $trans = $this->builderInterface;
                $transactionType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_VOID;
                $transaction = $trans->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($_dokuTrans->getInvoiceNumber() . $transactionType)
                    ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $void])
                    ->setFailSafe(true)
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_VOID);
                $payment->addTransactionCommentsToOrder($transaction, $message);
                $payment->save();
                $transaction->save();

                $this->messageManager->addSuccessMessage(__('Void success'));

            } catch (\Exception $e) {
                $this->logger->info('===== Capture error : ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('Fail to void payment: ' . $e->getMessage()));
            }

            /* end create invoice */
        } else {
            $this->messageManager->addErrorMessage(__('Fail to void: ' . $void));
        }


        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;

    }


}