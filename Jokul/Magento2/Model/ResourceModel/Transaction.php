<?php

namespace Jokul\Magento2\Model\ResourceModel;

class Transaction extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource
     *
     * @return void
     */

    public function _construct()
    {
        $this->_init('jokul_transaction','id');
    }


}