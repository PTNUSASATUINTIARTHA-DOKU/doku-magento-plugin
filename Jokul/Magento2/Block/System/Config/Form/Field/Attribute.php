<?php

namespace Jokul\Magento2\Block\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Attribute extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->create('Magento\Framework\App\Config\ScopeConfigInterface');
        $baseUrl = $scopeConfig->getValue("web/secure/base_url", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $element->setReadonly('readonly');

        $id = $element->getId();
        if (stripos($id, 'notify_url')) {
            $element->setValue($baseUrl . "jokulbackend/service/notify");
        } else if (stripos($id, 'redirect_url')) {
            $element->setValue($baseUrl . "jokulbackend/service/redirect");
        } else if (stripos($id, 'qris_url')) {
            $element->setValue($baseUrl . "jokulbackend/service/qrisnotify");
        }

        return $element->getElementHtml();
    }
}
