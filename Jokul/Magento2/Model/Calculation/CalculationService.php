<?php

namespace Jokul\Magento2\Model\Calculation;

use Magento\Framework\Exception\ConfigurationMismatchException;
use Magento\Quote\Model\Quote;
use \Jokul\Magento2\Helper\Data as FeeHelper;
use \Jokul\Magento2\Model\Calculation\Calculator\CalculatorInterface;
use Psr\Log\LoggerInterface;

class CalculationService implements CalculatorInterface
{

    /**
     * @var FeeHelper
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CalculationService constructor.
     * @param FeeHelper $helper
     * @param LoggerInterface $logger
     */
    public function __construct(FeeHelper $helper, LoggerInterface $logger)
    {
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function calculate(Quote $quote)
    {
        return 0.0;
    }

    /**
     * Check is order has minimum order total
     *
     * @param Quote $quote
     * @return bool
     */
    private function hasMinOrderTotal(Quote $quote)
    {
        return false;
    }

    /**
     * Check is order has maximum order total
     *
     * @param Quote $quote
     * @return bool
     */
    private function hasMaxOrderTotal(Quote $quote)
    {
        return false;
    }

    /**
     * Check is customer group allowed
     *
     * @param Quote $quote
     * @return bool
     */
    public function isAllowCustomerGroup(Quote $quote)
    {
        return true;
    }
}
