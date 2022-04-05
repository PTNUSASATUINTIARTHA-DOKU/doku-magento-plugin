<?php

namespace Jokul\Magento2\Block\Adminhtml\Sales;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\Template;
use \Jokul\Magento2\Helper\Data;

class Totals extends Template
{

    /**
     * @var \Helper\Data
     */
    protected $_dataHelper;

    /**
     * Order invoice
     *
     * @var \Magento\Sales\Model\Order\Invoice|null
     */
    protected $_invoice = null;

    /**
     * @var \Magento\Framework\DataObject
     */
    protected $_source;

    /**
     * OrderFee constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        DataObjectFactory $dataObjectFactory,
        \Magento\Directory\Model\Currency $currency,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->_currency = $currency;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve current order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->getParentBlock()->getOrder();
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    /**
     * @return string
     */
    public function getCurrencySymbol()
    {
        return $this->_currency->getCurrencySymbol();
    }

    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $this->_order = $parent->getOrder();
        $this->getInvoice();
        $this->getSource();

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

        $parent->addTotalBefore($adminFee, 'grand_total');

        $discountJokul = new \Magento\Framework\DataObject(
            [
                'code' => 'discount_fee',
                'strong' => false,
                'value' => !empty($totalAdminFeeDisc['total_discount']) ? $totalAdminFeeDisc['total_discount'] : 0,
                'label' => __('Discount'),
            ]
        );

        $parent->addTotalBefore($discountJokul, 'admin_fee');
        
        return $this;
    }
}
