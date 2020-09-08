<?php
/**
 * Created by PhpStorm.
 * User: leogent <leogent@gmail.com>
 * Date: 2/3/19
 * Time: 11:48 PM
 */

namespace Jokul\Magento2\Model;

use Jokul\Magento2\Api\Data\TransactionInterface;


class Transaction extends \Magento\Framework\Model\AbstractModel implements TransactionInterface
{

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function _construct()
    {
        $this->_init('Jokul\Magento2\Model\ResourceModel\Transaction');
    }

    /**#@-*/


}