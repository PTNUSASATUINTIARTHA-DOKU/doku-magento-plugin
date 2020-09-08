<?php

namespace Jokul\Magento2\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class PercentageSelect implements ArrayInterface {

    public function toOptionArray() {
        return array(
            array(
                'label' => 'Percentage',
                'value' => 'percentage',
            ),
            array(
                'label' => 'Fixed Amount',
                'value' => 'fixed',
            ),
        );
    }

}
