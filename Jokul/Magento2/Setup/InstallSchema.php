<?php

namespace Jokul\Magento2\Setup;

use \Magento\Framework\Setup\SchemaSetupInterface;
use \Magento\Framework\Db\Ddl\Table;
use \Magento\Framework\Setup\ModuleContextInterface;
use \Magento\Framework\Setup\InstallSchemaInterface;

class InstallSchema implements InstallSchemaInterface
{

	public function install(
		SchemaSetupInterface $setup,
		ModuleContextInterface $context
	) {

		$installer = $setup;
		$installer->startSetup();

		$table = $installer->getConnection()
			->newTable($installer->getTable('jokul_transaction'))
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
				'Magento Store Id'
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
				'invoice_number',
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
			)->addColumn(
				'created_at',
				Table::TYPE_DATETIME,
				null,
				[['nullable' => true]],
				'Created At'
			)->addColumn(
				'updated_at',
				Table::TYPE_DATETIME,
				null,
				['nullable' => true],
				'Update At'
			)->addColumn(
				'admin_fee_type',
				Table::TYPE_TEXT,
				null,
				['nullable' => true],
				'Admin Fee Type'
			)->addColumn(
				'admin_fee_amount',
				Table::TYPE_DECIMAL,
				'12,2',
				['nullable' => false, 'default' => 0.00],
				'Admin Fee Amount'
			)->addColumn(
				'admin_fee_trx_amount',
				Table::TYPE_DECIMAL,
				'12,2',
				['nullable' => false, 'default' => 0.00],
				'Admin Fee Trx Amount'
			)->addColumn(
				'discount_type',
				Table::TYPE_TEXT,
				null,
				['nullable' => true],
				'Discount Fee Type'
			)->addColumn(
				'discount_amount',
				Table::TYPE_DECIMAL,
				'12,2',
				['nullable' => false, 'default' => 0.00],
				'Discount Fee Amount'
			)->addColumn(
				'discount_trx_amount',
				Table::TYPE_DECIMAL,
				'12,2',
				['nullable' => false, 'default' => 0.00],
				'Discount Fee Trx Amount'
			)->addColumn(
				'expired_at_gmt',
				Table::TYPE_DATETIME,
				null,
				['nullable' => true],
				'Exipred at in GMT'
			)->addColumn(
				'expired_at_storetimezone',
				Table::TYPE_DATETIME,
				null,
				['nullable' => true],
				'Expired at in store time zone'
			)->addColumn(
				'doku_grand_total',
				Table::TYPE_DECIMAL,
				'12,2',
				['nullable' => false, 'default' => 0.00],
				'Doku Grand Total'
			)->addColumn(
				'payment_type',
				Table::TYPE_TEXT,
				20,
				['nullable' => true],
				'Payment Type'
			)->setComment('Doku Transaction Table');

		$installer->getConnection()->createTable($table);
		$installer->endSetup();
	}
}
