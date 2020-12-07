<?php

namespace Jokul\Magento2\Model\Payment;


class CreditCardAuthorizationHosted extends \Magento\Payment\Model\Method\AbstractMethod
{

    const CODE = 'cc_authorization_hosted';
    protected $_code = 'cc_authorization_hosted';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    protected $_canRefundInvoicePartial = true;

}