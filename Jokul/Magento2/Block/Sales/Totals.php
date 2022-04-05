<?php

namespace Jokul\Magento2\Block\Sales;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use \Jokul\Magento2\Helper\Data;

class Totals extends Template
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * Totals constructor.
     * @param Template\Context $context
     * @param Data $helper
     * @param DataObjectFactory $dataObjectFactory
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        DataObjectFactory $dataObjectFactory,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->dataObjectFactory = $dataObjectFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get source
     *
     * @return mixed
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    public function displayFullSummary()
    {
        return true;
    }

    /**
     * Init totals
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $this->_order = $parent->getOrder();
        $this->_source = $parent->getSource();

        $paymentChannel = $this->_order->getPayment()->getMethod();
        $adminFee = !empty($this->helper->getAdminFee($paymentChannel)) ? $this->helper->getAdminFee($paymentChannel) : 0;
        $discount = !empty($this->helper->getDiscountValue($paymentChannel)) ? $this->helper->getDiscountValue($paymentChannel) : 0;
        $adminFeeType = !empty($this->helper->getAdminFeeType($paymentChannel)) ? $this->helper->getAdminFeeType($paymentChannel) : "";
        $discountType = !empty($this->helper->getDiscountValueType($paymentChannel)) ? $this->helper->getDiscountValueType($paymentChannel) : "";

        $totalAdminFeeDisc = $this->helper->getTotalAdminFeeAndDisc(
            $adminFee,
            $adminFeeType,
            $discount,
            $discountType,
            $this->_order->getSubtotal()
        );

        $adminFee = new \Magento\Framework\DataObject(
            [
                'code' => 'admin_fee',
                'strong' => false,
                'value' => !empty($totalAdminFeeDisc['total_admin_fee']) ? $totalAdminFeeDisc['total_admin_fee'] : 0,
                'label' => __('Admin Fee'),
            ]
        );

        $parent->addTotal($adminFee, 'adminfee');

        $discountJokul = new \Magento\Framework\DataObject(
            [
                'code' => 'discount_fee',
                'strong' => false,
                'value' => !empty($totalAdminFeeDisc['total_discount']) ? $totalAdminFeeDisc['total_discount'] : 0,
                'label' => __('Discount'),
            ]
        );

        $parent->addTotal($discountJokul, 'discountJokul');

        return $this;
    }

    /**
     * Get order store object
     *
     * @return \Magento\Store\Model\Store
     */
    public function getStore()
    {
        return $this->_order->getStore();
    }
    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->_order;
    }
    /**
     * @return array
     */
    public function getLabelProperties()
    {
        return $this->getParentBlock()->getLabelProperties();
    }
    /**
     * @return array
     */
    public function getValueProperties()
    {
        return $this->getParentBlock()->getValueProperties();
    }
}
