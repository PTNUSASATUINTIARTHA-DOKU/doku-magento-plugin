<?php

namespace Jokul\Magento2\Block\Adminhtml\Order\View\Tab;
use Magento\Framework\App\ResourceConnection;

class CaptureTab extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface

{
    protected $_template = 'Jokul_Magento2::order/view/tab/capturetab.phtml';
    /**
     * @var \Magento\Framework\Registry
     */
    private $_coreRegistry;
    protected $resourceConnection;
    private $dokusTransactionOrder = null;

    /**
     * View constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        ResourceConnection $resourceConnection,
        array $data = []
    )
    {
        $this->_coreRegistry = $registry;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('current_order');
    }

    /**
     * Retrieve order model instance
     *
     * @return int
     *Get current id order
     */
    public function getOrderId()
    {
        return $this->getOrder()->getEntityId();
    }

    /**
     * Retrieve order increment id
     *
     * @return string
     */
    public function getOrderIncrementId()
    {
        return $this->getOrder()->getIncrementId();
    }

    /**
     * Retrieve order increment id
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->getOrder()->getCustomerEmail();
    }

    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return __('Capture');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return __('Capture');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('jokul_transaction');
        $sql = "SELECT * FROM " . $tableName . " WHERE invoice_number = '" . $this->getOrder()->getIncrementId() . "'";
        $this->dokusTransactionOrder = $connection->fetchRow($sql);

        if ($this->dokusTransactionOrder['payment_type'] != 'AUTHORIZE' && $this->dokusTransactionOrder['payment_channel'] != 'CREDIT_CARD') {
            return false;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }

    public function getOrderGrandTotal()
    {
        return $this->getOrder()->getGrandTotal();
    }
}