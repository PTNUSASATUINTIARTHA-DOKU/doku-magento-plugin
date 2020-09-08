<?php

namespace Jokul\Magento2\Block\System\Config\Form\Field;

class OnusSelect extends \Magento\Framework\View\Element\Html\Select {

    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Customer\Model\GroupFactory $groupfactory, 
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->groupfactory = $groupfactory;
    }

    public function _toHtml() {
        if (!$this->getOptions()) {
            $this->addOption(0, "NO");
            $this->addOption(1, "YES");
        }
        return parent::_toHtml();
    }

    public function setInputName($value) {
        return $this->setName($value);
    }

}
