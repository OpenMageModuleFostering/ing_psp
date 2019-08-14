<?php
/**
 *   ╲          ╱
 * ╭──────────────╮  COPYRIGHT (C) 2017 GINGER PAYMENTS B.V.
 * │╭──╮      ╭──╮│
 * ││//│      │//││
 * │╰──╯      ╰──╯│
 * ╰──────────────╯
 *   ╭──────────╮    The MIT License (MIT)
 *   │ () () () │
 *
 * @category    ING
 * @package     ING_PSP
 * @author      Ginger B.V. (info@gingerpayments.com)
 * @version     v1.1.7
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/

/** @var $this Mage_Catalog_Model_Resource_Setup */
$this->startSetup();


/** @var $conn Varien_Db_Adapter_Pdo_Mysql */
$conn = $this->getConnection();

$conn->addColumn($this->getTable('sales/quote_payment'), 'ing_order_id', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'nullable'  => true,
    'default'   => NULL,
    'comment'   => 'ing order id',
));

$conn->addColumn($this->getTable('sales/quote_payment'), 'ing_banktransfer_reference', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'nullable'  => true,
    'default'   => NULL,
    'comment'   => 'ing banktransfer reference',
));

$conn->addColumn($this->getTable('sales/quote_payment'), 'ing_ideal_issuer_id', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'nullable'  => true,
    'default'   => NULL,
    'comment'   => 'ing ideal issuer id',
));

$conn->addColumn($this->getTable('sales/order_payment'), 'ing_order_id', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'nullable'  => true,
    'default'   => NULL,
    'comment'   => 'ing order id',
));

$conn->addColumn($this->getTable('sales/order_payment'), 'ing_banktransfer_reference', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'nullable'  => true,
    'default'   => NULL,
    'comment'   => 'ing banktransfer reference',
));

$conn->addColumn($this->getTable('sales/order_payment'), 'ing_ideal_issuer_id', array(
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length'    => 255,
    'nullable'  => true,
    'default'   => NULL,
    'comment'   => 'ing ideal issuer id',
));

$this->endSetup();
