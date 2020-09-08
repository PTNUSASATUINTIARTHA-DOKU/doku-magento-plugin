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

        switch ($element->getId()) {
            case 'doku_general_config_general_notify_url':
                $element->setValue($baseUrl . "jokulbackend/service/notify");
                break;
            case 'doku_general_config_general_identify_url':
                $element->setValue($baseUrl . "jokulbackend/service/identify");
                break;
            case 'doku_general_config_general_review_url':
                $element->setValue($baseUrl . "jokulbackend/service/review");
                break;
            case 'doku_general_config_general_redirect_url':
                $element->setValue($baseUrl . "jokulbackend/service/redirect");
                break;
        }

        return $element->getElementHtml();
    }
}
