<?php
namespace Jokul\Magento2\Block\Checkout\Onepage;

class Success extends \Magento\Sales\Block\Order\Totals
{
    protected $checkoutSession;
    protected $customerSession;
    protected $_orderFactory;
    private $_dokuTransaction = null;
    private $resourceConnection;
    
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function getOrder() {
        if ($this->_order === null) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId(
                    $this->checkoutSession->getLastRealOrderId());
        }
        return $this->_order;
    }

    public function getCustomerId()
    {
        return $this->customerSession->getCustomer()->getId();
    }
    
    public function getDokuTransaction() {

        if ($this->_dokuTransaction === null) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('jokul_transaction');

            $sql = "SELECT * FROM " . $tableName . " where invoice_number = '" . $this->checkoutSession->getLastRealOrderId() . "'";
            $this->_dokuTransaction = $connection->fetchRow($sql);
        }
        
        return $this->_dokuTransaction;
    }
    
    public function getDokuTransactionDetailParams() {

        $order = $this->getOrder();
        $dokusTransactionOrder = $this->getDokuTransaction();
        $requestParam = json_decode($dokusTransactionOrder['request_params'],true);
        $howToPayUrl = $requestParam['response']['virtual_account_info']['how_to_pay_page'];

        $paymentChannelLabel = $order->getPayment()->getMethodInstance()->getTitle();

        $discountValue = "0,00";
        if (!empty($dokusTransactionOrder['discount_trx_amount'])) {
            $discountValue = number_format($dokusTransactionOrder['discount_trx_amount'], 2, ",", ".");
            if ($dokusTransactionOrder['discount_type'] == 'percentage') {
                $percantegeLable = (int) $dokusTransactionOrder['discount_amount'] < 100 ? $dokusTransactionOrder['discount_amount'] : 100;
                $discountValue .= " (" . $percantegeLable . "%)";
            }
        }

        $adminFeeValue = "0,00";
        if (!empty($dokusTransactionOrder['admin_fee_trx_amount'])) {
            $adminFeeValue = number_format($dokusTransactionOrder['admin_fee_trx_amount'], 2, ",", ".");
            if ($dokusTransactionOrder['admin_fee_type'] == 'percentage') {
                $percantegeLable = (int) $dokusTransactionOrder['admin_fee_amount'] < 100 ? $dokusTransactionOrder['admin_fee_amount'] : 100;
                $adminFeeValue .= " (" . $percantegeLable . "%)";
            }
        }

        $params = [
            'customerName' => $order->getCustomerName(),
            'customerEmail' => $order->getCustomerEmail(),
            'storeName' => $order->getStoreName(),
            'orderId' => $order->getIncrementId(),
            'vaNumber' => !empty($dokusTransactionOrder['va_number']) ? $dokusTransactionOrder['va_number'] : "",
            'amount' => number_format($dokusTransactionOrder['doku_grand_total'], 2, ",", "."),
            'discountValue' => $discountValue,
            'adminFeeValue' => $adminFeeValue,
            'paymentChannel' => $paymentChannelLabel,
            'howToPayUrl' => $howToPayUrl,
            'expiry' => date('d F Y, H:i', strtotime($dokusTransactionOrder['expired_at_storetimezone']))
        ];
       
        return $params;
    }

}