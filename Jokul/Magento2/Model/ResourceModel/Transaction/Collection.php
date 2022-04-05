<?php

namespace Jokul\Magento2\Model\ResourceModel\Transaction;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * Initizalize resource collection
     *
     * @return void
     */

    public function _construct()
    {
        $this->_init('Jokul\Magento2\Model\Transaction', 'Jokul\Magento2\Model\ResourceModel\Transaction');
    }


}