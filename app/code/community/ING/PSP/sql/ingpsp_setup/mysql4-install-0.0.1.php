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
 * @version     v1.1.8
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/

/** @var $this Mage_Catalog_Model_Resource_Setup */
$this->startSetup();

$this->run(
    sprintf("CREATE TABLE IF NOT EXISTS `%s` (
        `order_id` int(11) NOT NULL,
        `entity_id` int(11) NOT NULL,
        `method` varchar(3) NOT NULL,
        `transaction_id` varchar(32) NOT NULL,
        `bank_account` varchar(15) NOT NULL,
        `bank_status` varchar(20) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
        $this->getTable('ingpsp_payments')
    )
);

$this->endSetup();
