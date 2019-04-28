<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install( SchemaSetupInterface $setup, ModuleContextInterface $context )
    {
        $installer = $setup;

        $installer->startSetup();

        $table = $installer->getConnection()->newTable( $installer->getTable( 'sid_instant_eft_payment' ) )
            ->addColumn( 'payment_id', Table::TYPE_BIGINT, null, ['identity' => true, 'nullable' => false, 'primary' => true], 'Payment ID' )
            ->addColumn( 'signature', Table::TYPE_TEXT, 255, ['nullable' => true], 'Transaction Signature' )
            ->addColumn( 'errorcode', Table::TYPE_TEXT, 20, ['nullable' => true], 'Error Code' )
            ->addColumn( 'errordescription', Table::TYPE_TEXT, 255, ['nullable' => true], 'Error Description' )
            ->addColumn( 'errorsolution', Table::TYPE_TEXT, 255, ['nullable' => true], 'Error Solution' )
            ->addColumn( 'status', Table::TYPE_TEXT, 20, ['nullable' => false], 'Status' )
            ->addColumn( 'country_code', Table::TYPE_TEXT, 5, ['nullable' => false], 'Country Code' )
            ->addColumn( 'country_name', Table::TYPE_TEXT, 255, ['nullable' => true], 'Country Name' )
            ->addColumn( 'currency_code', Table::TYPE_TEXT, 5, ['nullable' => false], 'Currency Code' )
            ->addColumn( 'currency_name', Table::TYPE_TEXT, 255, ['nullable' => true], 'Currency Name' )
            ->addColumn( 'currency_symbol', Table::TYPE_TEXT, 5, ['nullable' => true], 'Currency Symbol' )
            ->addColumn( 'bank_name', Table::TYPE_TEXT, 255, ['nullable' => false], 'Bank Name' )
            ->addColumn( 'amount', Table::TYPE_FLOAT, null, ['nullable' => false], 'Amount Paid' )
            ->addColumn( 'reference', Table::TYPE_TEXT, 255, ['nullable' => false], 'Reference' )
            ->addColumn( 'receiptno', Table::TYPE_TEXT, 255, ['nullable' => false], 'Receipt Number' )
            ->addColumn( 'tnxid', Table::TYPE_TEXT, 255, ['nullable' => false], 'SID Transaction ID' )
            ->addColumn( 'date_created', Table::TYPE_DATETIME, null, ['nullable' => true], 'Date Created' )
            ->addColumn( 'date_ready', Table::TYPE_DATETIME, null, ['nullable' => true], 'Date Ready' )
            ->addColumn( 'date_completed', Table::TYPE_DATETIME, null, ['nullable' => true], 'Date Completed' )
            ->addColumn( 'notified', Table::TYPE_DATETIME, null, ['nullable' => true], 'Time Notified' )
            ->addColumn( 'redirected', Table::TYPE_DATETIME, null, ['nullable' => true], 'Time Redirected' )
            ->addColumn( 'time_stamp', Table::TYPE_DATETIME, null, ['nullable' => false], 'Time Stamp' )
            ->addIndex( $installer->getIdxName( 'sid_payment', ['reference'] ), ['reference'] )
            ->setComment( 'SID Instant EFT Payments' );

        $installer->getConnection()->createTable( $table );

        $installer->endSetup();
    }
}
