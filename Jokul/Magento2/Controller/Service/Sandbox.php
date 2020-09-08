<?php
/**
 * Created by PhpStorm.
 * User: leogent <leogent@gmail.com>
 * Date: 2/3/19
 * Time: 9:58 PM
 */

namespace Jokul\Magento2\Controller\Service;


use Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Sandbox extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product $product,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone

    )
    {
        parent::__construct($context);
        $this->_logger = $logger;
        $this->objectManager = $objectManager;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->_storeManager = $storeManager;
        $this->_product = $product;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->order = $order;
        $this->timezone = $timezone;
    }

    public function execute()
    {


    }



    public function getStoreConfig($path) {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

}