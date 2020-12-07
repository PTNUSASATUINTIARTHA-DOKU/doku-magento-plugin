<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 5/16/19
 * Time: 2:32 PM
 */

namespace Jokul\Magento2\Plugin;

use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as SalesOrderGridCollection;


class PaymentTypeColumn
{
    private $messageManager;
    private $collection;

    public function __construct(MessageManager $messageManager,
                                SalesOrderGridCollection $collection
    )
    {

        $this->messageManager = $messageManager;
        $this->collection = $collection;
    }

    public function aroundGetReport(
        \Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory $subject,
        \Closure $proceed,
        $requestName
    )
    {
        $result = $proceed($requestName);

        if ($requestName == 'sales_order_grid_data_source') {
            if ($result instanceof $this->collection
            ) {
                $select = $this->collection->getSelect();
                $select->joinLeft(
                    ["dokutrans" => $this->collection->getTable("jokul_transaction")],
                    'main_table.entity_id = dokutrans.order_id',
                    array('payment_type')
                );
                return $this->collection;
            }
        }
        return $result;
    }
}