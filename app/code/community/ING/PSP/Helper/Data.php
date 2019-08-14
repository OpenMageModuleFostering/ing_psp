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
 * @author      Ginger Payments B.V. (info@gingerpayments.com)
 * @version     v1.1.7
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/
class ING_PSP_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Parse address for split street and house number
     *
     * @param  string $streetAddress
     * @return array
     */
    public function parseAddress($streetAddress)
    {
        $address = $streetAddress;
        $houseNumber = '';

        $offset = strlen($streetAddress);

        while (($offset = $this->_rstrpos($streetAddress, ' ', $offset)) !== false) {
            if ($offset < strlen($streetAddress) - 1 && is_numeric($streetAddress[$offset + 1])) {
                $address = trim(substr($streetAddress, 0, $offset));
                $houseNumber = trim(substr($streetAddress, $offset + 1));
                break;
            }
        }

        if (empty($houseNumber) && strlen($streetAddress) > 0 && is_numeric($streetAddress[0])) {
            $pos = strpos($streetAddress, ' ');

            if ($pos !== false) {
                $houseNumber = trim(substr($streetAddress, 0, $pos), ", \t\n\r\0\x0B");
                $address = trim(substr($streetAddress, $pos + 1));
            }
        }

        return array($address, $houseNumber);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param null|int $offset
     * @return int
     */
    protected function _rstrpos($haystack, $needle, $offset = null)
    {
        $size = strlen($haystack);

        if (is_null($offset)) {
            $offset = $size;
        }

        $pos = strpos(strrev($haystack), strrev($needle), $size - $offset);

        if ($pos === false) {
            return false;
        }

        return $size - $pos - strlen($needle);
    }

    /**
     * @param string|float $amount
     * @return int
     */
    public static function getAmountInCents($amount)
    {
        return (int) (100 * round($amount, 2, PHP_ROUND_HALF_UP));
    }
}
