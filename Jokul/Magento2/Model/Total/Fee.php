<?php

namespace Jokul\Magento2\Model\Total;

use Magento\Quote\Model\Quote\Address\Total;
use Jokul\Magento2\Model\Calculation\Calculator\CalculatorInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Jokul\Magento2\Helper\Data as FeeHelper;
use Magento\Tax\Model\Calculation;
use \Jokul\Magento2\Helper\Logger;

class Fee extends Address\Total\AbstractTotal
{
    /**
     * @var CalculatorInterface
     */
    protected $calculator;

    /**
     * @var FeeHelper
     */
    protected $helper;

    /**
     * @var Calculation
     */
    private $taxCalculator;
    protected $logger;

    /**
     * Fee constructor.
     *
     * @param CalculatorInterface $calculator
     * @param FeeHelper $helper
     * @param Calculation $taxCalculator
     */
    public function __construct(
        CalculatorInterface $calculator,
        FeeHelper $helper,
        Logger $loggerInterface,
        Calculation $taxCalculator
    ) {
        $this->calculator = $calculator;
        $this->helper = $helper;
        $this->logger = $loggerInterface;
        $this->taxCalculator = $taxCalculator;
    }

    /**
     * Collect fee
     *
     * @param Quote $quote
     * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $paymentChannel = $quote->getPayment()->getMethod();

        $adminFee = !empty($this->helper->getAdminFee($paymentChannel)) ? $this->helper->getAdminFee($paymentChannel) : 0;
        $discount = !empty($this->helper->getDiscountValue($paymentChannel)) ? $this->helper->getDiscountValue($paymentChannel) : 0;
        $adminFeeType = !empty($this->helper->getAdminFeeType($paymentChannel)) ? $this->helper->getAdminFeeType($paymentChannel) : "";
        $discountType = !empty($this->helper->getDiscountValueType($paymentChannel)) ? $this->helper->getDiscountValueType($paymentChannel) : "";

        $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
            $adminFee,
            $adminFeeType,
            $discount,
            $discountType,
            $quote->getSubtotal()
        );

        $adminFeeTotal = !empty($totalAdminFeeDisc['total_admin_fee']) ? $totalAdminFeeDisc['total_admin_fee'] : 0;
        $discountTotal = !empty($totalAdminFeeDisc['total_discount']) ? $totalAdminFeeDisc['total_discount'] : 0;

        $TotalAmountJokul = $adminFeeTotal - $discountTotal;
        $total->setGrandTotal($total->getGrandTotal() + $TotalAmountJokul);

        return $this;
    }

    /**
     * Fetch fee
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        $paymentChannel = $quote->getPayment()->getMethod();

        $adminFee = !empty($this->helper->getAdminFee($paymentChannel)) ? $this->helper->getAdminFee($paymentChannel) : 0;
        $discount = !empty($this->helper->getDiscountValue($paymentChannel)) ? $this->helper->getDiscountValue($paymentChannel) : 0;
        $adminFeeType = !empty($this->helper->getAdminFeeType($paymentChannel)) ? $this->helper->getAdminFeeType($paymentChannel) : "";
        $discountType = !empty($this->helper->getDiscountValueType($paymentChannel)) ? $this->helper->getDiscountValueType($paymentChannel) : "";

        $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
            $adminFee,
            $adminFeeType,
            $discount,
            $discountType,
            $total->getSubtotal()
        );

        $result = [
            [
                'code' => 'admin_fee',
                'value' => !empty($totalAdminFeeDisc['total_admin_fee']) ? $totalAdminFeeDisc['total_admin_fee'] : 0
            ],[
                'code' => 'discount_fee',
                'value' => !empty($totalAdminFeeDisc['total_discount']) ? $totalAdminFeeDisc['total_discount'] : 0
            ]
        ];

        return $result;
    }

    /**
     * Get address
     *
     * @param Quote $quote
     * @return Address
     */
    private function getAddressFromQuote(Quote $quote)
    {
        return $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
    }

    /**
     * Get tax request for quote address
     *
     * @param Quote $quote
     * @return \Magento\Framework\DataObject
     */
    private function _getRateTaxRequest(Quote $quote)
    {
        $rateTaxRequest = $this->taxCalculator->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $quote->getCustomerTaxClassId(),
            $quote->getStore(),
            $quote->getCustomerId()
        );
        return $rateTaxRequest;
    }

    /**
     * Get label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Custom Fee');
    }
}
