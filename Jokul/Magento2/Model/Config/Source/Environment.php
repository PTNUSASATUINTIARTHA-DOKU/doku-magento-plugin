<?php

namespace Jokul\Magento2\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface {

    public function toOptionArray() {
        return array(
            array(
                'label' => 'Development',
                'value' => 'development',
            ),
            array(
                'label' => 'Production',
                'value' => 'production',
            ),
        );
    }

}
