<?php

namespace Jokul\Magento2\Model\Invoice\Total;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Jokul\Magento2\Helper\Data as FeeHelper;

class Fee extends AbstractTotal
{
    /**
     * @var FeeHelper
     */
    protected $helper;

    /**
     * Fee constructor.
     *
     * @param FeeHelper $helper
     */
    public function __construct(
        FeeHelper $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Collect invoice totals
     *
     * @param Invoice $invoice
     * @return $this
     * @param FeeHelper $helper
     */
    public function collect(Invoice $invoice)
    {
        $paymentChannel = $invoice->getOrder()->getPayment()->getMethod();

        $adminFee = !empty($this->helper->getAdminFee($paymentChannel)) ? $this->helper->getAdminFee($paymentChannel) : 0;
        $discount = !empty($this->helper->getDiscountValue($paymentChannel)) ? $this->helper->getDiscountValue($paymentChannel) : 0;
        $adminFeeType = !empty($this->helper->getAdminFeeType($paymentChannel)) ? $this->helper->getAdminFeeType($paymentChannel) : "";
        $discountType = !empty($this->helper->getDiscountValueType($paymentChannel)) ? $this->helper->getDiscountValueType($paymentChannel) : "";

        $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
            $adminFee,
            $adminFeeType,
            $discount,
            $discountType,
            $invoice->getSubtotal()
        );

        $adminFeeTotal = !empty($totalAdminFeeDisc['total_admin_fee']) ? $totalAdminFeeDisc['total_admin_fee'] : 0;
        $discountTotal = !empty($totalAdminFeeDisc['total_discount']) ? $totalAdminFeeDisc['total_discount'] : 0;

        $TotalAmountJokul = $adminFeeTotal - $discountTotal;
        $invoice->setGrandTotal($invoice->getGrandTotal() + $TotalAmountJokul);

        return $this;
    }
}
