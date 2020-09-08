<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 5/16/19
 * Time: 2:23 AM
 */

namespace Jokul\Magento2\Ui\Component\Listing\Column;


use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use \Magento\Framework\Api\SearchCriteriaBuilder;

class Paymenttype extends Column
{

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $criteria,
        array $components = [], array $data = [])
    {
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteria = $criteria;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }


    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if(!empty($item['payment_type'])) {
                    $item['payment_type'] = ucfirst(strtolower($item['payment_type']));
                }

            }
        }
        return $dataSource;
    }


}