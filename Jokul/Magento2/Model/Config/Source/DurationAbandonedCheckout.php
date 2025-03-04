<?php

namespace Jokul\Magento2\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

// EXPERIMENT
class DurationAbandonedCheckout implements ArrayInterface {

    public function toOptionArray() {
        return array(
            array(
                'label' => 'Tomorrow',
                'value' => '1440',
            ),
            array(
                'label' => '7 Days',
                'value' => '10080',
            ),
            array(
                'label' => '14 Days',
                'value' => '20160',
            ),
            array(
                'label' => '31 Days',
                'value' => '44640',
            ),
            array(
                'label' => 'Custom',
                'value' => 'custom',
            )
        );
    }

}
