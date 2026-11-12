<?php

$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$cmsPageTable = $installer->getTable('cms/page');

if (!$connection->tableColumnExists($cmsPageTable, 'ainative_css')) {
    $connection->addColumn(
        $cmsPageTable,
        'ainative_css',
        [
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length' => '2M',
            'nullable' => true,
            'comment' => 'AI Native CMS page CSS',
        ],
    );
}

$installer->endSetup();