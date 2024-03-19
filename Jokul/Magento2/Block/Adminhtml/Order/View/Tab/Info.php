<?php

namespace Jokul\Magento2\Block\Adminhtml\Order\View\Tab;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Helper\Admin as SalesAdminHelper;


class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Tab\Info
{

    protected $_coreRegistry;
    protected $resourceConnection;
    private $dokusTransactionOrder = null;

    /**
     * View constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param SalesAdminHelper $salesAdminHelper
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        SalesAdminHelper $salesAdminHelper,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $registry, $salesAdminHelper, $data);
        $this->_coreRegistry = $registry;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get Payment method information
     *
     * @return string
     */
    public function getJokulPaymentInfo()
    {
        $result = $this->getChildHtml('order_payment');
        $order = $this->getOrder();
        $orderStatus = $order->getStatus();

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('jokul_transaction');
        $sql = "SELECT * FROM " . $tableName . " WHERE invoice_number = '" . $order->getIncrementId() . "'";
        $this->dokusTransactionOrder = $connection->fetchRow($sql);
        $paymentType = $this->dokusTransactionOrder['payment_type'];

        $paymentChannel = $order->getCustomerNote();

        if ($orderStatus === 'processing' || $orderStatus === 'complete') {
            $result .= ' - ' . str_replace('_', ' ', $paymentChannel);

            if ($paymentChannel == 'CREDIT_CARD' && $paymentType == 'AUTHORIZE') {
                $result .= " (AUTHORIZE)";
            }
        }
        return $result;
    }
}
