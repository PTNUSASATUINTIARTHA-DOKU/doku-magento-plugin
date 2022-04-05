<?php

namespace Jokul\Magento2\Api;

interface TransactionRepositoryInterface
{

    public function save(Data\TransactionInterface $transaction);

    public function getByInvoiceNumber($invoiceNumber);

    public function getByOrderId($orderId);

    public function delete(Data\TransactionInterface $transaction);

    public function deleteById($id);

}