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
 * @version     v1.1.9
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/

/** @var $this Mage_Catalog_Model_Resource_Setup */
$this->startSetup();

$this->run(
    sprintf("DROP TABLE IF EXISTS `%s`",
        $this->getTable('ingpsp_payments')
    )
);

$this->run("DELETE FROM `{$this->getTable('core_config_data')}` where `path` = 'ingpsp/ideal/active';
    DELETE FROM `{$this->getTable('core_config_data')}` where `path` = 'ingpsp/ideal/description';
    DELETE FROM `{$this->getTable('core_config_data')}` where `path` = 'ingpsp/settings/apikey';
    DELETE FROM `{$this->getTable('core_config_data')}` where `path` = 'ingpsp/settings/product';
    DELETE FROM `{$this->getTable('core_resource')}` where `code` = 'ingpsp_setup';"
);

$this->endSetup();
