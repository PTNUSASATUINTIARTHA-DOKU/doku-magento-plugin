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
use Jokul\Magento2\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;


class Capture extends \Magento\Backend\App\Action
{

    protected $order;
    protected $_helper;
    protected $logger;
    protected $messageManager;
    protected $_timezoneInterface;
    protected $_scopeConfig;
    protected $_generalConfiguration;
    protected $transactionRepository;
    protected $invoiceService;
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
        InvoiceService $_invoiceService,
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
        $this->invoiceService = $_invoiceService;
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
            'INVOICE_NUMBER' => $_dokuTrans->getInvoiceNumber(),
            'APPROVALCODE' => $_dokuTrans->getApprovalCode(),
            'AMOUNT' => $_dokuTrans->getDokuGrandTotal(),
            'PURCHASEAMOUNT' => $_dokuTrans->getDokuGrandTotal(),
            'CURRENCY' => '360',
            'PURCHASECURRENCY' => '360',
            'SESSIONID' => $requestData['SESSIONID'],
            'PAYMENTCHANNEL' => '15', //only for CC
        );

        $param['WORDS'] = sha1($clientId . $sharedKey . $_dokuTrans->getInvoiceNumber() . $param['SESSIONID']);

        $capture = $this->_helper->doCapture($param);

        $jsonRequest = json_encode($param);
        $jsonResponse = json_encode($capture);
        $_dokuTrans->setCaptureRequest($jsonRequest);
        $_dokuTrans->setCaptureResponse($jsonResponse);
        $this->transactionRepository->save($_dokuTrans);


        if($capture) {
            if($capture['RESULTMSG'] == 'SUCCESS') {

                /** create invoice */

                try {
                    if ($order->canInvoice() && !$order->hasInvoices()) {
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->register();
                        $invoice->pay();
                        $invoice->save();
                        $transactionSave = $objectManager->create(
                            'Magento\Framework\DB\Transaction'
                        )->addObject(
                            $invoice
                        )->addObject(
                            $invoice->getOrder()
                        );
                        $transactionSave->save();

                        $payment = $order->getPayment();
                        $payment->setLastTransactionId($_dokuTrans->getInvoiceNumber());
                        $payment->setTransactionId($_dokuTrans->getInvoiceNumber());
                        $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $capture]);
                        $message = __(json_encode($_POST, JSON_PRETTY_PRINT));
                        $trans = $this->builderInterface;
                        $transactionType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
                        $transaction = $trans->setPayment($payment)
                            ->setOrder($order)
                            ->setTransactionId($_dokuTrans->getInvoiceNumber(). $transactionType)
                            ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $capture])
                            ->setFailSafe(true)
                            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                        $payment->addTransactionCommentsToOrder($transaction, $message);
                        $payment->save();
                        $transaction->save();

                        if ($invoice && !$invoice->getEmailSent()) {
                            $invoiceSender = $objectManager->get('Magento\Sales\Model\Order\Email\Sender\InvoiceSender');
                            $invoiceSender->send($invoice);
                            $order->addRelatedObject($invoice);
                            $order->addStatusHistoryComment(__('Your Invoice for Order ID #%1.', $_dokuTrans->getInvoiceNumber()))
                                ->setIsCustomerNotified(true);
                        }
                    }

                    $order->setData('state', 'processing');
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

                    $order->save();
                    $_dokuTrans->setAuthorizationStatus('capture');
                    $this->transactionRepository->save($_dokuTrans);
                    $this->messageManager->addSuccessMessage(__('Capture success'));
                } catch (\Exception $e) {
                    $this->logger->info('===== Capture error : '. $e->getMessage());
                    $this->messageManager->addErrorMessage(__('Fail to create invoice: ' . $e->getMessage()));
                }

                /* end create invoice */
            } else {
                $this->messageManager->addErrorMessage(__('Fail to capture: ' . json_encode($capture)));
            }
        } else {
            $this->messageManager->addErrorMessage(__('Fail to capture'));
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        // Your code
        $clientId = $this->_generalConfiguration->getClientId();
        $chainId = $this->_generalConfiguration->getChainId();
        $sharedKey = $this->_generalConfiguration->getSharedKey();

        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;

    }




}