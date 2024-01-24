<?php

namespace Jokul\Magento2\Block\Adminhtml\Order\View\Tab;

class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Tab\Info
{

    /**
     * Get Payment method information
     *
     * @return string
     */
    public function getJokulPaymentInfo()
    {
        $result = $this->getChildHtml('order_payment');
        $order = $this->getOrder();

        if ($order->getStatus() === 'processing') {
            $result .= ' - ' . str_replace('_', ' ', $order->getCustomerNote());
        }

        return $result;
    }
}
