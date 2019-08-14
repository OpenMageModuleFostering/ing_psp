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
 * @version     v1.1.8
 * @copyright   COPYRIGHT (C) 2017 GINGER PAYMENTS B.V. (https://www.gingerpayments.com)
 * @license     The MIT License (MIT)
 *
 **/
class ING_PSP_Model_Observer
{
    private $ing_modules = [
        'ingpsp_ideal',
        'ingpsp_banktransfer',
        'ingpsp_creditcard',
        'ingpsp_bancontact',
        'ingpsp_cashondelivery',
        'ingpsp_klarna',
        'ingpsp_paypal',
        'ingpsp_homepay',
        'ingpsp_sofort',
    ];

    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function convertPayment(Varien_Event_Observer $observer)
    {
        $orderPayment = $observer->getEvent()->getOrderPayment();
        $quotePayment = $observer->getEvent()->getQuotePayment();

        $orderPayment->setIngOrderId($quotePayment->getIngOrderId());
        $orderPayment->setIngBanktransferReference($quotePayment->getIngBanktransferReference());
        $orderPayment->setIngIdealIssuerId($quotePayment->getIngIdealIssuerId());

        return $this;
    }

    /**
     * Hide payment methods that are not allowed by ING.
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function checkPaymentMethodStatus(Varien_Event_Observer $observer)
    {
        $config = $observer->getConfig();
        $allowedProducts = $this->getActiveINGProducts();
        foreach ($this->ing_modules AS $product) {
            $ingModule = $config->getNode('sections/payment/groups/'.$product);
            if (in_array(str_replace('ingpsp_', '', $product), $allowedProducts)) {
                $ingModule->show_in_default = 1;
                $ingModule->show_in_website = 1;
                $ingModule->show_in_store = 1;
                $ingModule->active = 1;
                Mage::getConfig()->saveConfig('payment/'.$product.'/active', 1);
            } else {
                $ingModule->show_in_default = 0;
                $ingModule->show_in_website = 0;
                $ingModule->show_in_store = 0;
                $ingModule->active = 0;
                Mage::getConfig()->saveConfig('payment/'.$product.'/active', 0);
            }
            $ingModule->saveXML();
        }

        return $this;
    }

    /**
     * Hide payment methods that are not allowed by ING.
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function paymentMethodIsActive(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $method = $event->getMethodInstance();
        $result = $event->getResult();

        if (in_array($method->getCode(), $this->ing_modules)
            && $method->getCode() == 'ingpsp_klarna'
        ) {
            $result->isAvailable = $this->ipAllowed();
        }

        return $this;
    }

    /**
     * Request ING for available payment methods.
     *
     * @return array
     */
    protected function getActiveINGProducts()
    {
        require_once(Mage::getBaseDir('lib').DS.'Ing'.DS.'Services'.DS.'ing-php'.DS.'vendor'.DS.'autoload.php');

        try {
            if (Mage::getStoreConfig("payment/ingpsp/apikey")) {
                $ingAPI = \GingerPayments\Payment\Ginger::createClient(
                    Mage::getStoreConfig("payment/ingpsp/apikey"),
                    Mage::getStoreConfig("payment/ingpsp/product")
                );

                if ($ingAPI->isInTestMode()) {
                    return [
                        'klarna',
                        'banktransfer',
                        'ideal',
                        'cashondelivery',
                        'sofort'
                    ];
                }
                return $ingAPI->getAllowedProducts();
            }
        } catch (\Exception $exception) {
            Mage::log($exception->getMessage());
            Mage::getSingleton('core/session')->addError($exception->getMessage());
        }
    }

    /**
     * Function checks if payment method is allowed for current IP.
     *
     * @return bool
     */
    protected function ipAllowed()
    {
        $ipFilterList = Mage::getStoreConfig("payment/ingpsp_klarna/ip_filter");

        if (strlen($ipFilterList) > 0) {
            $ipWhitelist = array_map('trim', explode(",", $ipFilterList));

            if (!in_array(Mage::helper('core/http')->getRemoteAddr(), $ipWhitelist)) {
                return false;
            }
        }

        return true;
    }
}
