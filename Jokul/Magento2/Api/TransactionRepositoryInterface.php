<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 2/10/19
 * Time: 2:36 AM
 */

namespace Jokul\Magento2\Api;


interface TransactionRepositoryInterface
{

    public function save(Data\TransactionInterface $transaction);

    public function getByInvoiceNumber($invoiceNumber);

    public function getByOrderId($orderId);

    public function delete(Data\TransactionInterface $transaction);

    public function deleteById($id);

}