<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 5/16/19
 * Time: 10:48 AM
 */

namespace Jokul\Magento2\Ui\DataProvider\Order;


class PaymentTypeFilter implements \Magento\Ui\DataProvider\AddFilterToCollectionInterface
{

    public function addFilter(
        \Magento\Framework\Data\Collection $collection,
        $field,
        $condition = null
    )
    {
        if (isset($condition['like']))
        {
            $collection->addFieldToFilter($field, $condition);
        }
    }
}