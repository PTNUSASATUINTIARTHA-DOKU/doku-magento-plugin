<?php

namespace Jokul\Magento2\Block\System\Config\Form\Field;

class InstallmentConfiguration extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    protected $_addAfter = true;

    protected $_addButtonLabel;
    
    protected $_isOnUs;


    protected function _construct(
    ) {
        parent::_construct();
        $this->_addButtonLabel = __('Add');
    }

    protected function _prepareToRender() {
        $this->addColumn('customer_bank', array('label' => __('Customer Bank')));
        $this->addColumn('installment_acquierer_code', array('label' => __('Installment Acquierer Code')));
        $this->addColumn('promo_id', array('label' => __('Promo Id')));
        $this->addColumn('tennor', array('label' => __('Tennor')));
        $this->addColumn(
                'is_on_us', [
            'label' => __('is on us'),
            'renderer' => $this->getIsOnUs(),
                ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    protected function getIsOnUs() {
        if (!$this->_isOnUs) {
            $this->_isOnUs = $this->getLayout()->createBlock(
                    'Jokul\Magento2\Block\System\Config\Form\Field\OnusSelect', '', ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->_isOnUs;
    }
   
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row) {
        $isOnUs = $row->getIsOnUs();
        $options = [];
        if ($isOnUs) {
            $options['option_' . $this->getIsOnUs()->calcOptionHash($isOnUs)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
        }
   
    public function renderCellTemplate($columnName)
    {
        return parent::renderCellTemplate($columnName);
    }  

}

