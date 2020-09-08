<?php

namespace Jokul\Magento2\Ui\Component\Listing\Column\Paymenttype;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Options
 */
class Options implements OptionSourceInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $opts = [
                ['value' => 'authorization', 'label' => 'Authorization'],
                ['value' => 'sale', 'label' => 'Sale'],
            ];
            $this->options = $opts;
        }
        return $this->options;
    }
}
