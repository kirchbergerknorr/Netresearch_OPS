<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Helper_Payment_Request
{
    protected $config = null;

    /**
     * @param null $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @return null
     */
    public function getConfig()
    {
        if ($this->config === null) {
            $this->config = Mage::getModel('ops/config');
        }

        return $this->config;
    }


    /**
     * extracts the ship to information from a given address
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array - the parameters containing the ship to data
     */
    public function extractShipToParameters(Mage_Customer_Model_Address_Abstract $address, Mage_Sales_Model_Quote $quote)
    {
        $paramValues = array();
        if ($this->getConfig()->canSubmitExtraParameter($quote->getStoreId())) {
            $paramValues['ECOM_SHIPTO_POSTAL_NAME_FIRST']   = $address->getFirstname();
            $paramValues['ECOM_SHIPTO_POSTAL_NAME_LAST']    = $address->getLastname();
            $paramValues['ECOM_SHIPTO_POSTAL_STREET_LINE1'] = $address->getStreet(1);
            $paramValues['ECOM_SHIPTO_POSTAL_STREET_LINE2'] = $address->getStreet(2);
            $paramValues['ECOM_SHIPTO_POSTAL_COUNTRYCODE']  = $address->getCountry();
            $paramValues['ECOM_SHIPTO_POSTAL_CITY']         = $address->getCity();
            $paramValues['ECOM_SHIPTO_POSTAL_POSTALCODE']   = $address->getPostcode();
            $paramValues['ECOM_SHIPTO_POSTAL_STATE']        = $this->getIsoRegionCode($address);
        }

        return $paramValues;
    }

    /**
     * extraxcts the according Ingenico Payment Services owner* parameter
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Customer_Model_Address_Abstract $billingAddress
     *
     * @return array
     */
    public function getOwnerParams(Mage_Sales_Model_Quote $quote, Mage_Customer_Model_Address_Abstract $billingAddress)
    {
        $ownerParams = array();
        if ($this->getConfig()->canSubmitExtraParameter($quote->getStoreId())) {
            $ownerParams = array(
                'OWNERADDRESS'                  => str_replace("\n", ' ', $billingAddress->getStreet(-1)),
                'OWNERTOWN'                     => $billingAddress->getCity(),
                'OWNERZIP'                      => $billingAddress->getPostcode(),
                'OWNERTELNO'                    => $billingAddress->getTelephone(),
                'OWNERCTY'                      => $billingAddress->getCountry(),

                'ECOM_BILLTO_POSTAL_POSTALCODE' => $billingAddress->getPostcode(),
            );
        }

        return $ownerParams;
    }

    /**
     * extracts the region code in iso format (if possible)
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     *
     * @return string - the regin code in iso format
     */
    public function getIsoRegionCode(Mage_Customer_Model_Address_Abstract $address)
    {
        $regionCode  = trim($address->getRegionCode());
        $countryCode = $address->getCountry();
        if ($this->isAlreadyIsoCode($regionCode, $countryCode)) {
            return $regionCode;
        }
        if (0 === strpos($regionCode, $countryCode . '-')) {
            return str_replace($countryCode . '-', '', $regionCode);
        }

        return $this->getRegionCodeFromMapping($countryCode, $regionCode);
    }

    /**
     * checks if the given region code is already in iso format
     *
     * @param $regionCode
     * @param $countryCode
     *
     * @return bool
     */
    protected function isAlreadyIsoCode($regionCode, $countryCode)
    {
        return ((strlen($regionCode) < 3 && !in_array($countryCode, array('AT')))
            || (strlen($regionCode) === 3 && !in_array($countryCode, array('DE'))));
    }

    protected function getRegionCodeFromMapping($countryCode, $regionCode)
    {
        $countryRegionMapping = $this->getCountryRegionMapping($countryCode);
        if (array_key_exists($regionCode, $countryRegionMapping)) {
            return $countryRegionMapping[$regionCode];
        }

        return $countryCode;
    }

    /**
     * retrieves country specific region mapping
     *
     * @param $countryCode
     *
     * @return array - the country specific region mapping or empty array if mapping could not be found
     */
    protected function getCountryRegionMapping($countryCode)
    {
        if (strtoupper($countryCode) === 'DE') return $this->getRegionMappingForGermany();
        if (strtoupper($countryCode) === 'AT') return $this->getRegionMappingForAustria();
        if (strtoupper($countryCode) === 'ES') return $this->getRegionMappingForSpain();
        if (strtoupper($countryCode) === 'FI') return $this->getRegionsMappingForFinland();
        if (strtoupper($countryCode) === 'LV') return $this->getRegionsMappingForLatvia();

        return array();;
    }

    /**
     * translates the Magento's region code for germany into ISO format
     *
     * @return array
     */
    protected function getRegionMappingForGermany()
    {
        return array(
            'NDS' => 'NI',
            'BAW' => 'BW',
            'BAY' => 'BY',
            'BER' => 'BE',
            'BRG' => 'BB',
            'BRE' => 'HB',
            'HAM' => 'HH',
            'HES' => 'HE',
            'MEC' => 'MV',
            'NRW' => 'NW',
            'RHE' => 'RP',
            'SAR' => 'SL',
            'SAS' => 'SN',
            'SAC' => 'ST',
            'SCN' => 'SH',
            'THE' => 'TH'
        );
    }

    /**
     * translates the Magento's region code for austria into ISO format
     *
     * @return array
     */
    protected function getRegionMappingForAustria()
    {
        return array(
            'WI' => '9',
            'NO' => '3',
            'OO' => '4',
            'SB' => '5',
            'KN' => '2',
            'ST' => '6',
            'TI' => '7',
            'BL' => '1',
            'VB' => '8'
        );
    }

    /**
     * translates the Magento's region code for spain into ISO format
     *
     * @return array
     */
    protected function getRegionMappingForSpain()
    {
        return array(
            'A Coruсa'               => 'C',
            'Alava'                  => 'VI',
            'Albacete'               => 'AB',
            'Alicante'               => 'A',
            'Almeria'                => 'AL',
            'Asturias'               => 'O',
            'Avila'                  => 'AV',
            'Badajoz'                => 'BA',
            'Baleares'               => 'PM',
            'Barcelona'              => 'B',
            'Caceres'                => 'CC',
            'Cadiz'                  => 'CA',
            'Cantabria'              => 'S',
            'Castellon'              => 'CS',
            'Ceuta'                  => 'CE',
            'Ciudad Real'            => 'CR',
            'Cordoba'                => 'CO',
            'Cuenca'                 => 'CU',
            'Girona'                 => 'GI',
            'Granada'                => 'GR',
            'Guadalajara'            => 'GU',
            'Guipuzcoa'              => 'SS',
            'Huelva'                 => 'H',
            'Huesca'                 => 'HU',
            'Jaen'                   => 'J',
            'La Rioja'               => 'LO',
            'Las Palmas'             => 'GC',
            'Leon'                   => 'LE',
            'Lleida'                 => 'L',
            'Lugo'                   => 'LU',
            'Madrid'                 => 'M',
            'Malaga'                 => 'MA',
            'Melilla'                => 'ML',
            'Murcia'                 => 'MU',
            'Navarra'                => 'NA',
            'Ourense'                => 'OR',
            'Palencia'               => 'P',
            'Pontevedra'             => 'PO',
            'Salamanca'              => 'SA',
            'Santa Cruz de Tenerife' => 'TF',
            'Segovia'                => 'Z',
            'Sevilla'                => 'SG',
            'Soria'                  => 'SE',
            'Tarragona'              => 'SO',
            'Teruel'                 => 'T',
            'Toledo'                 => 'TE',
            'Valencia'               => 'TO',
            'Valladolid'             => 'V',
            'Vizcaya'                => 'VA',
            'Zamora'                 => 'BI',
            'Zaragoza'               => 'ZA',
        );
    }

    /**
     * translates the Magento's region code for finland into ISO format
     *
     * @return array
     */
    protected function getRegionsMappingForFinland()
    {
        return array(
            'Lappi'             => '10',
            'Pohjois-Pohjanmaa' => '14',
            'Kainuu'            => '05',
            'Pohjois-Karjala'   => '13',
            'Pohjois-Savo'      => '15',
            'Etelä-Savo'        => '04',
            'Etelä-Pohjanmaa'   => '03',
            'Pohjanmaa'         => '12',
            'Pirkanmaa'         => '11',
            'Satakunta'         => '17',
            'Keski-Pohjanmaa'   => '07',
            'Keski-Suomi'       => '08',
            'Varsinais-Suomi'   => '19',
            'Etelä-Karjala'     => '02',
            'Päijät-Häme'       => '16',
            'Kanta-Häme'        => '06',
            'Uusimaa'           => '18',
            'Itä-Uusimaa'       => '19',
            'Kymenlaakso'       => '09',
            'Ahvenanmaa'        => '01'
        );
    }

    /**
     * translates the Magento's region code for latvia into ISO format
     *
     * @return array
     */
    protected function getRegionsMappingForLatvia()
    {
        return array(
            'Ādažu novads'         => 'LV',
            'Aglonas novads'       => '001',
            'Aizputes novads'      => '003',
            'Aknīstes novads'      => '004',
            'Alojas novads'        => '005',
            'Alsungas novads'      => '006',
            'Amatas novads'        => '008',
            'Apes novads'          => '009',
            'Auces novads'         => '010',
            'Babītes novads'       => '012',
            'Baldones novads'      => '013',
            'Baltinavas novads'    => '014',
            'Beverīnas novads'     => '017',
            'Brocēnu novads'       => '018',
            'Burtnieku novads'     => '019',
            'Carnikavas novads'    => '020',
            'Cesvaines novads'     => '021',
            'Ciblas novads'        => '023',
            'Dagdas novads'        => '024',
            'Dundagas novads'      => '027',
            'Durbes novads'        => '028',
            'Engures novads'       => '029',
            'Ērgļu novads'         => 'LV',
            'Garkalnes novads'     => '031',
            'Grobiņas novads'      => '032',
            'Iecavas novads'       => '034',
            'Ikšķiles novads'      => '035',
            'Ilūkstes novads'      => '036',
            'Inčukalna novads'     => '037',
            'Jaunjelgavas novads'  => '038',
            'Jaunpiebalgas novads' => '039',
            'Jaunpils novads'      => '040',
            'Jēkabpils'            => '042',
            'Kandavas novads'      => '043',
            'Kārsavas novads'      => 'LV',
            'Ķeguma novads'        => 'LV',
            'Ķekavas novads'       => 'LV',
            'Kokneses novads'      => '046',
            'Krimuldas novads'     => '048',
            'Krustpils novads'     => '049',
            'Lielvārdes novads'    => '053',
            'Līgatnes novads'      => 'LV',
            'Līvānu novads'        => '056',
            'Lubānas novads'       => '057',
            'Mālpils novads'       => '061',
            'Mārupes novads'       => '062',
            'Mazsalacas novads'    => '060',
            'Naukšēnu novads'      => '064',
            'Neretas novads'       => '065',
            'Nīcas novads'         => '066',
            'Olaines novads'       => '068',
            'Ozolnieku novads'     => '069',
            'Pārgaujas novads'     => 'LV',
            'Pāvilostas novads'    => '070',
            'Pļaviņu novads'       => '072',
            'Priekules novads'     => '074',
            'Priekuļu novads'      => '075',
            'Raunas novads'        => '076',
            'Riebiņu novads'       => '078',
            'Rojas novads'         => '079',
            'Ropažu novads'        => '080',
            'Rucavas novads'       => '081',
            'Rugāju novads'        => '082',
            'Rūjienas novads'      => '084',
            'Rundāles novads'      => '083',
            'Salacgrīvas novads'   => '085',
            'Salas novads'         => '086',
            'Salaspils novads'     => '087',
            'Saulkrastu novads'    => '089',
            'Sējas novads'         => 'LV',
            'Siguldas novads'      => '091',
            'Skrīveru novads'      => '092',
            'Skrundas novads'      => '093',
            'Smiltenes novads'     => '094',
            'Stopiņu novads'       => '095',
            'Strenču novads'       => '096',
            'Tērvetes novads'      => '098',
            'Vaiņodes novads'      => '100',
            'Valmiera'             => 'LV',
            'Varakļānu novads'     => '102',
            'Vārkavas novads'      => 'LV',
            'Vecpiebalgas novads'  => '104',
            'Vecumnieku novads'    => '105',
            'Viesītes novads'      => '107',
            'Viļakas novads'       => '108',
            'Viļānu novads'        => '109',
            'Zilupes novads'       => '110'
        );
    }
} 