<?php

/**
 * Class AddressHelper
 */
class AddressHelper extends Helper
{
    /**
     * @param $country
     * @return string|null
     */
    public function getIsoCountryCodeForCustomerCountry($country)
    {
        $result = Db::getInstance()->executeS(sprintf(
            'SELECT `%s` FROM `%s` %s INNER JOIN `%s` %s ON %s.`%s` = %s.`%s` WHERE %s.`%s` = "%s" LIMIT %d;',
            'iso_code',
            _DB_PREFIX_ . 'country',
            'psc',
            _DB_PREFIX_ . 'country_lang',
            'pscl',
            'pscl',
            'id_country',
            'psc',
            'id_country',
            'pscl',
            'name',
            $country,
            1
        ));

        if (count($result) !== 1)
        {
            return null;
        }

        return $result[0]['iso_code'];
    }

    /**
     * Get's an address instance based on a Cart.
     *
     * @param Cart $cart
     * @return Address|null
     */
    public function getInvoiceAddressForCart(Cart $cart)
    {
        $address = new Address($cart->id_address_invoice);

        if (!Validate::isLoadedObject($address))
        {
            return null;
        }

        return $address;
    }

    /**
     * Parses international postal codes for Spryng Customers.
     *
     * @param $countryCode
     * @param $postalCode
     * @return string
     */
    public function parsePostalCode($countryCode, $postalCode)
    {
        switch($countryCode)
        {
            case 'NL':
                if (strlen($postalCode) === 6)
                {
                    return wordwrap($postalCode, 4, ' ', true);
                }
                else
                {
                    return $postalCode;
                }
                break;
            default:
                return $postalCode;
        }
    }
}