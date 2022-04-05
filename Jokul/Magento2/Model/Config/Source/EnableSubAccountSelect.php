<?php

namespace Jokul\Magento2\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class EnableSubAccountSelect implements ArrayInterface {

    public function toOptionArray() {
        return array(
            array(
                'label' => 'Yes',
                'value' => 'yes',
            ),
            array(
                'label' => 'No',
                'value' => 'no',
            )
        );
    }

}
