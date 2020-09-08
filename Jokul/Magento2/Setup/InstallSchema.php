<?php
namespace Jokul\Magento2\Setup;

use \Magento\Framework\Setup\SchemaSetupInterface;
use \Magento\Framework\Db\Ddl\Table;
use \Magento\Framework\Setup\ModuleContextInterface;
use \Magento\Framework\Setup\InstallSchemaInterface;

class InstallSchema implements InstallSchemaInterface{

	public function install(
		SchemaSetupInterface $setup,
		ModuleContextInterface $context
	){

		$installer = $setup;
		$installer->startSetup();

		$table = $installer->getConnection()
			->newTable($installer->getTable('doku_transaction'))
			->addColumn(
				'id',
				Table::TYPE_INTEGER,
				null,
				['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
				'Id'
			)
			->addColumn(
				'store_id',
				Table::TYPE_INTEGER,
				null,
				['nullable' => false],
				'Store Id'
			)
			->addColumn(
				'quote_id',
				Table::TYPE_INTEGER,
				null,
				['nullable' => false],
				'Quote Id'
			)
			->addColumn(
				'order_id',
				Table::TYPE_TEXT,
				10,
				['nullable' => false],
				'Order Id'
			)
			->addColumn(
				'trans_id_merchant',
				Table::TYPE_TEXT,
				50,
				['nullable' => false],
				'Invoice Number'
			)
			->addColumn(
				'payment_channel_id',
				Table::TYPE_TEXT,
				2,
				['nullable' => false],
				'Payment Channel Id'
			)->addColumn(
				'va_number',
				Table::TYPE_TEXT,
				50,
				['nullable' => false],
				'va Number'
			)->addColumn(
				'order_status',
				Table::TYPE_TEXT,
				15,
				['nullable' => false],
				'Doku Order Status'
			)->addColumn(
				'request_params',
				Table::TYPE_TEXT,
				65538,
				['nullable' => false],
				'Doku Request'
			)->addColumn(
				'identify_params',
				Table::TYPE_TEXT,
				65538,
				['nullable' => false],
				'Doku identify'
			)->addColumn(
				'redirect_params',
				Table::TYPE_TEXT,
				65538,
				['nullable' => false],
				'Doku redirect'
			)->addColumn(
				'notify_params',
				Table::TYPE_TEXT,
				65538,
				['nullable' => false],
				'Doku notify'
			)->addColumn(
				'review_params',
				Table::TYPE_TEXT,
				65538,
				['nullable' => false],
				'Doku review'
			)
			->setComment('Doku Transaction Table');

		$installer->getConnection()->createTable($table);
		$installer->endSetup();

	}

}