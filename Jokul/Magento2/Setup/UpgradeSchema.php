<?php

namespace Jokul\Magento2\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface {

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context) {

        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {

            $tableName = 'doku_transaction';

            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableName) == true) {

                // Declare data
                $columns = [
                    'created_at' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        'nullable' => true,
                        'comment' => 'Craeted At',
                    ],
                    'updated_at' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        'nullable' => true,
                        'comment' => 'Update At',
                    ],
                    'admin_fee_type' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Admin Fee Type',
                    ],
                    'admin_fee_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'nullable' => true,
                        'comment' => 'Admin Fee Amount',
                    ],
                    'admin_fee_trx_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'nullable' => true,
                        'comment' => 'Admin Fee Trx Amount',
                    ],
                    'discount_type' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Discount Fee Type',
                    ],
                    'discount_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'nullable' => true,
                        'comment' => 'Discount Fee Amount',
                    ],
                    'discount_trx_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'nullable' => true,
                        'comment' => 'Discount Fee Trx Amount',
                    ],
                ];

                $connection = $setup->getConnection();

                foreach ($columns as $name => $definition) {

                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        } 
        
        if (version_compare($context->getVersion(), '1.0.2', '<')) {

            $tableName = 'doku_transaction';

            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableName) == true) {

                // Declare data
                $columns = [
                    'expired_at_gmt' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        'nullable' => true,
                        'comment' => 'Exipred at in GMT',
                    ],
                    'expired_at_storetimezone' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        'nullable' => true,
                        'comment' => 'Expired at in store time zone',
                    ],
                    'doku_grand_total' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '12,4',
                        'nullable' => false,
                        'default' => 0.00,
                        'comment' => 'Doku Grand Total'
                    ]
                ];

                $connection = $setup->getConnection();

                foreach ($columns as $name => $definition) {

                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        
        if (version_compare($context->getVersion(), '1.0.3', '<')) {

            $tableName = 'doku_transaction';

            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableName) == true) {

                // Declare data
                $columns = [
                    'admin_fee_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '12,4',
                        'nullable' => false,
                        'default' => 0.00,
                        'comment' => 'Admin Fee Amount'
                    ],
                    'admin_fee_trx_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '12,4',
                        'nullable' => false,
                        'default' => 0.00,
                        'comment' => 'Admin Fee Trx Amount'
                    ],
                    'discount_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '12,4',
                        'nullable' => false,
                        'default' => 0.00,
                        'comment' => 'Discount Amount'
                    ],
                    'discount_trx_amount' => [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '12,4',
                        'nullable' => false,
                        'default' => 0.00,
                        'comment' => 'Discount Trx Amount'
                    ]
                ];       
           
                $connection = $setup->getConnection();

                foreach ($columns as $name => $definition) {

                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        
        if (version_compare($context->getVersion(), '1.0.4', '<')) {

            $status = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Sales\Model\Order\Status');

            $status->setData('status', 'waiting_for_verification')->setData('label', 'WAITING FOR VERIFICATION')->save();
            $status->assignState(\Magento\Sales\Model\Order::STATE_NEW, false, true);
        }

        if (version_compare($context->getVersion(), '1.1.1', '<')) {
            if ($setup->getConnection()->isTableExists('doku_transaction')) {
                $connection = $setup->getConnection();

                $connection->addColumn('doku_transaction', 'payment_type', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 20,
                    'nullable' => true,
                    'comment' => 'Payment Type',
                ]);

            }
        }

        if (version_compare($context->getVersion(), '1.1.2', '<')) {
            if ($setup->getConnection()->isTableExists('doku_transaction')) {
                $connection = $setup->getConnection();

                $connection->addColumn('doku_transaction', 'approval_code', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 20,
                    'nullable' => true,
                    'comment' => 'Approval Code',
                ]);

            }
        }

        if (version_compare($context->getVersion(), '1.1.3', '<')) {
            if ($setup->getConnection()->isTableExists('doku_transaction')) {
                $connection = $setup->getConnection();

                $connection->addColumn('doku_transaction', 'capture_request', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 65538,
                    'nullable' => true,
                    'comment' => 'Authorization Capture Request',
                    'after' => 'review_params'
                ]);

                $connection->addColumn('doku_transaction', 'capture_response', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 65538,
                    'nullable' => true,
                    'comment' => 'Authorization Capture Response',
                    'after' => 'capture_request'
                ]);

                $connection->addColumn('doku_transaction', 'void_request', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 65538,
                    'nullable' => true,
                    'comment' => 'Authorization Cancel Request',
                    'after' => 'capture_response'
                ]);

                $connection->addColumn('doku_transaction', 'void_response', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 65538,
                    'nullable' => true,
                    'comment' => 'Authorization Cancel Response',
                    'after' => 'void_request'
                ]);

                $connection->addColumn('doku_transaction', 'authorization_status', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 20,
                    'nullable' => true,
                    'comment' => 'Authorization Status',
                ]);

            }
        }

        if (version_compare($context->getVersion(), '1.1.4', '<')) {
            if ($setup->getConnection()->isTableExists('doku_transaction')) {
                $connection = $setup->getConnection();

                $connection->addColumn('doku_transaction', 'auth_expired', [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                    'nullable' => true,
                    'comment' => 'Auth Expired At',
                ]);

            }
        }




        $setup->endSetup();
    }

}
