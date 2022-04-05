<?php

namespace Jokul\Magento2\Model\Calculation\Calculator;

use Jokul\Magento2\Helper\Data as FeeHelper;

abstract class AbstractCalculator implements CalculatorInterface
{
    /**
     * @var FeeHelper
     */
    protected $helper;

    /**
     * AbstractCalculation constructor.
     *
     * @param FeeHelper $helper
     */
    public function __construct(FeeHelper $helper)
    {
        $this->helper = $helper;
    }
}
