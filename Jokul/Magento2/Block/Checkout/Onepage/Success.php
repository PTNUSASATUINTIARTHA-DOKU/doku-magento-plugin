<?php

namespace Jokul\Magento2\Block\Checkout\Onepage;

class Success extends \Magento\Sales\Block\Order\Totals
{
    protected $checkoutSession;
    protected $customerSession;
    protected $_orderFactory;
    private  $dokusTransactionOrder = null;
    private $resourceConnection;
    private $transactionType;
    public $params;
    private $order;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->params = $this->getRequest()->getParams();
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->resourceConnection = $resourceConnection;
        $this->order = $this->_order;
        $this->_orderConfig = $orderConfig;
    }

    public function getOrder()
    {
        if ($this->_order === null) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId(
                $this->params['invoice']
            );
        }
        return $this->_order;
    }

    public function getCustomerId()
    {
        return $this->customerSession->getCustomer()->getId();
    }

    public function getDokuTransaction()
    {
        if ($this->dokusTransactionOrder === null) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');

            $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $this->params['invoice'] . "'";
            $this->dokusTransactionOrder = $connection->fetchRow($sql);
        }

        return $this->dokusTransactionOrder;
    }

    public function getDokuTransactionOther($invoice)
    {
        if ($this->dokusTransactionOrder === null) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');

            $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $invoice . "'";
            $this->dokusTransactionOrder = $connection->fetchRow($sql);
        }

        return $this->dokusTransactionOrder;
    }

    public function getPrintUrl()
    {
        $this->order = $this->getOrder();
        return $this->getUrl('sales/order/print', array('order_id' => $this->order->getId()));
    }

    public function getDokuTransactionDetailParams()
    {
        $howToPayUrl = '';
        $checkoutUrl = '';

        if (isset($this->params['invoice']) && isset($this->params['transaction_type'])) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId(
                $this->params['invoice']
            );

            $this->order =  $this->_order;
            $this->dokusTransactionOrder = $this->getDokuTransaction();
            $requestParam = json_decode($this->dokusTransactionOrder['request_params'], true);

            if ($this->params['transaction_type'] == 'checkoutpending') {
                $checkoutUrl =  $requestParam['response']['response']['payment']['url'];
            }

            $this->dokusTransactionOrder = $this->getDokuTransactionOther($this->params['invoice']);
        } else {
            $this->order = $this->getOrder();
            $this->dokusTransactionOrder = $this->getDokuTransaction();

            $requestParam = json_decode($this->dokusTransactionOrder['request_params'], true);
            $O2Ochannel = array(07);
            if (in_array($this->dokusTransactionOrder['payment_channel_id'], $O2Ochannel)) {
                $howToPayUrl = $requestParam['response']['online_to_offline_info']['how_to_pay_page'];
            } else {
                $howToPayUrl = $requestParam['response']['virtual_account_info']['how_to_pay_page'];
            }
        }

        $paymentChannelLabel = $this->order->getPayment()->getMethodInstance()->getTitle();

        $discountValue = "0,00";
        if (!empty($this->dokusTransactionOrder['discount_trx_amount'])) {
            $discountValue = number_format($this->dokusTransactionOrder['discount_trx_amount'], 2, ",", ".");
            if ($this->dokusTransactionOrder['discount_type'] == 'percentage') {
                $percantegeLable = (int) $this->dokusTransactionOrder['discount_amount'] < 100 ? $this->dokusTransactionOrder['discount_amount'] : 100;
                $discountValue .= " (" . $percantegeLable . "%)";
            }
        }

        $adminFeeValue = "0,00";
        if (!empty($this->dokusTransactionOrder['admin_fee_trx_amount'])) {
            $adminFeeValue = number_format($this->dokusTransactionOrder['admin_fee_trx_amount'], 2, ",", ".");
            if ($this->dokusTransactionOrder['admin_fee_type'] == 'percentage') {
                $percantegeLable = (int) $this->dokusTransactionOrder['admin_fee_amount'] < 100 ? $this->dokusTransactionOrder['admin_fee_amount'] : 100;
                $adminFeeValue .= " (" . $percantegeLable . "%)";
            }
        }

        $params = [
            'transactionType' => $this->transactionType,
            'customerName' => $this->order->getCustomerName(),
            'customerEmail' => $this->order->getCustomerEmail(),
            'storeName' => $this->order->getStoreName(),
            'orderId' => $this->order->getIncrementId(),
            'vaNumber' => !empty($this->dokusTransactionOrder['va_number']) ? $this->dokusTransactionOrder['va_number'] : "",
            'amount' => number_format($this->dokusTransactionOrder['doku_grand_total'], 2, ",", "."),
            'discountValue' => $discountValue,
            'adminFeeValue' => $adminFeeValue,
            'paymentChannel' => $paymentChannelLabel,
            'checkoutUrl' => $checkoutUrl,
            'howToPayUrl' => $howToPayUrl,
            'expiry' => date('d F Y, H:i', strtotime($this->dokusTransactionOrder['expired_at_storetimezone']))
        ];

        return $params;
    }
}
