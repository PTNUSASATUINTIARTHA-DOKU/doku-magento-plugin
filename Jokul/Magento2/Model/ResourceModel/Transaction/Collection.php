<?php
/**
 * Created by PhpStorm.
 * User: leogent <leogent@gmail.com>
 * Date: 2/3/19
 * Time: 11:53 PM
 */

namespace Jokul\Magento2\Model\ResourceModel\Transaction;

/**
 * Recurring Collection
 * @package Jokul\Magento2\Model\ResourceModel\Transaction
 * @author Leogent <leogent@gmail.com>
 */

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