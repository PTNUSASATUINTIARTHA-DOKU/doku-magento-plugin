<?php

namespace Jokul\Magento2\Model\Payment;


class CreditCardHosted extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'cc_hosted';

    protected $_code = 'cc_hosted';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

}