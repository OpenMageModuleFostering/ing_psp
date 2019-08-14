<?php

class ING_PSP_Model_System_Config_Source_Dropdown_Values
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'kassacompleet',
                'label' => 'Kassa Compleet',
            ),
            array(
                'value' => 'ingcheckout',
                'label' => 'ING Checkout',
            ),
            array(
                'value' => 'epay',
                'label' => 'ING ePay',
            ),
        );
    }
}