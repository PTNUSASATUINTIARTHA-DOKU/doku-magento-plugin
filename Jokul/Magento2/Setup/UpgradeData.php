<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 5/26/19
 * Time: 11:53 AM
 */

namespace Jokul\Magento2\Setup;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;


class UpgradeData implements UpgradeDataInterface
{

    const ORDER_STATUS_CHALLENGE_CODE = 'challenge';

    const ORDER_STATUS_CHALLENGE_LABEL = 'Challenge';


    /**
     * Status Factory
     *
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * Status Resource Factory
     *
     * @var StatusResourceFactory
     */
    protected $statusResourceFactory;

    /**
     * InstallData constructor
     *
     * @param StatusFactory $statusFactory
     * @param StatusResourceFactory $statusResourceFactory
     */
    public function __construct(
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    )
    {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * Installs data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     *
     * @throws Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.1.4','<')) {
            /** @var StatusResource $statusResource */
            $statusResource = $this->statusResourceFactory->create();
            /** @var Status $status */
            $status = $this->statusFactory->create();
            $status->setData([
                'status' => self::ORDER_STATUS_CHALLENGE_CODE,
                'label' => self::ORDER_STATUS_CHALLENGE_LABEL
            ]);

            try {
                $statusResource->save($status);
            } catch (AlreadyExistsException $exception) {
                return;
            } catch (\Exception $exception) {
                return;
            }

            $status->assignState(Order::STATE_NEW, false, true);
        }

        $setup->endSetup();
    }

}