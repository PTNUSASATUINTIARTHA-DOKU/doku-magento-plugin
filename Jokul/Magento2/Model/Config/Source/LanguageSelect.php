<?php

namespace Jokul\Magento2\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class LanguageSelect implements ArrayInterface {

    public function toOptionArray() {
        return array(
            array(
                'label' => 'English',
                'value' => 'en',
            ),
            array(
                'label' => 'Indonesia',
                'value' => 'id',
            ),
        );
    }

}