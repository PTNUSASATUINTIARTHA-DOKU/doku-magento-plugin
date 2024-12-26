<?php

namespace Jokul\Magento2\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

// EXPERIMENT
class AbandonedCheckout implements ArrayInterface {

    public function toOptionArray() {
        return array(
            array(
                'label' => 'No',
                'value' => 'no',
            ),
            array(
                'label' => 'Yes',
                'value' => 'yes',
            )
        );
    }

}
