<?php

namespace Jokul\Magento2\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;

class Paymentfee extends \Magento\Framework\App\Action\Action
{
    /**
     * @var CheckoutSession
     */
    protected $_checkoutSession;

    /**
     * @var JsonFactory
     */
    protected $_resultJson;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Json
     */
    protected $json;

    /**
     * Paymentfee constructor.
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param JsonFactory $resultJson
     * @param CartRepositoryInterface $quoteRepository
     * @param Json $json
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        JsonFactory $resultJson,
        CartRepositoryInterface $quoteRepository,
        Json $json
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_resultJson = $resultJson;
        $this->quoteRepository = $quoteRepository;
        $this->json = $json;
    }

    /**
     * Calculate payment fee
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $response = ['errors' => false, 'message' => 'Jokul Payment Admin Fee and Discount Calculation is done'];
        try {
            $this->quoteRepository->get($this->_checkoutSession->getQuoteId());
            $quote = $this->_checkoutSession->getQuote();
            $payment = $this->json->unserialize($this->getRequest()->getContent());
            $this->_checkoutSession->getQuote()->getPayment()->setMethod($payment['payment']);
            $this->quoteRepository->save($quote->collectTotals());
        } catch (\Exception $e) {
            $response = ['errors' => true, 'message' => $e->getMessage()];
        }
        $resultJson = $this->_resultJson->create();
        return $resultJson->setData($response);
    }
}
