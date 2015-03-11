<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Helper_Validation_Checkout_Step
{

    const BILLING_STEP = 'billing';

    const SHIPPING_STEP = 'shipping';

    /**
     * retrieves the params for pushing back to the billing step
     *
     * @return array
     */
    protected function getBillingParams()
    {
        return array(
            'CN',
            'OWNERZIP',
            'OWNERTOWN',
            'OWNERTELNO',
            'OWNERADDRESS',
            'ECOM_BILLTO_POSTAL_POSTALCODE'
        );
    }

    /**
     * retrieves the params for pushing back to the billing step
     *
     * @return array
     */
    protected function getShippingParams()
    {
        return array(
            'ECOM_SHIPTO_POSTAL_NAME_FIRST',
            'ECOM_SHIPTO_POSTAL_NAME_LAST',
            'ECOM_SHIPTO_POSTAL_STREET1',
            'ECOM_SHIPTO_POSTAL_STREET2',
            'ECOM_SHIPTO_POSTAL_STREET3',
            'ECOM_SHIPTO_POSTAL_COUNTRYCODE',
            'ECOM_SHIPTO_POSTAL_COUNTY',
            'ECOM_SHIPTO_POSTAL_POSTALCODE',
            'ECOM_SHIPTO_POSTAL_CITY',
            'ECOM_SHIPTO_POSTAL_STREET_NUMBER',
            'ECOM_SHIPTO_POSTAL_STATE'
        );
    }

    /**
     * gets the corresponding checkout step for the erroneous fields
     *
     * @param array $erroneousFields
     *
     * @return string - the checkout step
     */
    public function getStep(array $erroneousFields)
    {
        $checkoutStep = '';
        foreach ($erroneousFields as $erroneousField) {
            if (in_array($erroneousField, $this->getBillingParams())) {
                $checkoutStep = self::BILLING_STEP;
                break;
            }
            if (in_array($erroneousField, $this->getShippingParams())) {
                $checkoutStep = self::SHIPPING_STEP;
            }
        }

        return $checkoutStep;
    }
} 