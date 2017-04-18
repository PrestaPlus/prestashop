<?php

/**
 * Class CustomerHelper
 */
class CustomerHelper extends SpryngHelper
{

    /**
     * Used to fetch i.e. country code
     *
     * @var AddressHelper
     */
    protected $address;

    /**
     * @param $cart
     * @param $account
     * @return null|string
     */
    public function getCustomer($cart, $account)
    {
        $prestaCustomer = $this->getCustomerFromCart($cart);
        $customer = $this->findLocalCustomer($prestaCustomer->id);

        if ($customer)
        {
            $customer = $this->fetchCustomerFromApi($customer);

            if (is_null($customer))
            {
                $customer = $this->prepareCustomer($cart, $account);
                $customer = $this->submitAndSaveCustomer($customer, $prestaCustomer->id, true);

                if (is_null($customer))
                {
                    return null;
                }
            }

            return $customer->_id;
        }
        else
        {
            $customer = $this->prepareCustomer($cart, $account);

            if (is_null($customer))
            {
                return null;
            }

            $customer = $this->submitAndSaveCustomer($customer, $prestaCustomer->id);

            return $customer->_id;
        }
    }

    /**
     * Checks database for existance of Spryng Customer ID based on Presta customer ID
     *
     * @param $prestaCustomerId
     * @return bool|string
     */
    private function findLocalCustomer($prestaCustomerId)
    {
        $result = Db::getInstance()->executeS(sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = %d LIMIT 1;',
            'spryng_customer_id',
            _DB_PREFIX_ . 'spryng_customers',
            'presta_customer_id',
            $prestaCustomerId
        ));

        if (count($result) !== 1)
        {
            return false;
        }

        return $result[0]['spryng_customer_id'];
    }

    /**
     * Fetches a customer from the Spryng system by it's ID.
     *
     * @param $id
     * @return null|\SpryngPaymentsApiPhp\Object\Customer
     */
    private function fetchCustomerFromApi($id)
    {
        try
        {
            $customer = $this->api->customer->getCustomerById($id);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            return null;
        }
        catch (\SpryngPaymentsApiPhp\Exception\CustomerException $ex)
        {
            return null;
        }

        return $customer;
    }

    /**
     * Prepares a customer submission array based on a Cart instance.
     *
     * @param $cart
     * @param $account
     * @return array|null
     */
    private function prepareCustomer($cart, $account)
    {
        $this->address = new AddressHelper($this->api);

        $customer = array();
        $prestaCustomer = $this->getCustomerFromCart($cart);
        $invoiceAddress = $this->address->getInvoiceAddressForCart($cart);
        $countryCode = $this->address->getIsoCountryCodeForCustomerCountry($invoiceAddress->country);

        if (is_null($invoiceAddress) || is_null($countryCode))
        {
            return null;
        }

        if ((int) $prestaCustomer->id_gender === 1)
        {
            $customer['gender'] = 'male';
            $customer['title'] = 'mr';
        }
        else
        {
            $customer['gender'] = 'female';
            $customer['title'] = 'ms';
        }
        $usedPhoneNumber = (is_null($invoiceAddress->phone) || empty($invoiceAddress->phone)) ?
            $invoiceAddress->phone_mobile :
            $invoiceAddress->phone;

        $customer['account'] = $account;
        $customer['first_name'] = $prestaCustomer->firstname;
        $customer['last_name'] = $prestaCustomer->lastname;
        $customer['date_of_birth'] = $prestaCustomer->birthday;
        $customer['email_address'] = $prestaCustomer->email;
        $customer['phone_number'] = $this->parsePhoneNumber($countryCode, $usedPhoneNumber);
        $customer['country_code'] = $countryCode;
        $customer['postal_code'] = $this->address->parsePostalCode($countryCode, $invoiceAddress->postcode);
        $customer['city'] = $invoiceAddress->city;
        $customer['street_address'] = $invoiceAddress->address1;

        return $customer;
    }

    /**
     * Submits a new customer to Spryng and updates customer table.
     *
     * @param array $customer
     * @param $prestaId
     * @param bool $update
     * @return array|null|\SpryngPaymentsApiPhp\Object\Customer
     */
    public function submitAndSaveCustomer(array $customer, $prestaId, $update = false)
    {
        try
        {
            $customer = $this->api->customer->create($customer);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            return null;
        }

        if ($update)
        {
            Db::getInstance()->execute(sprintf(
                'UPDATE `%s` SET `%s` = "%s" WHERE `%s` = "%s";',
                _DB_PREFIX_ . 'spryng_customers',
                'spryng_customer_id',
                $customer->_id,
                'presta_customer_id',
                $prestaId
            ));
        }
        else
        {
            Db::getInstance()->execute(sprintf(
                'INSERT INTO `%s` (`%s`, `%s`) VALUES ("%s", "%s");',
                _DB_PREFIX_ . 'spryng_customers',
                'presta_customer_id',
                'spryng_customer_id',
                $prestaId,
                $customer->_id
            ));
        }

        return $customer;
    }

    /**
     * Returns a customer instance extracted from an Order instance.
     * Returns null if no customer with the supplied ID exists.
     *
     * @param Order $order
     * @return Customer|null
     */
    public function getCustomerFromOrder(Order $order)
    {
        $customerId = $order->id_customer;

        return $this->fetchCustomer($customerId);
    }

    /**
     * Returns a customer instance extracted from a Cart instance.
     * Returns null if no customer with the supplied ID exists.
     *
     * @param Cart $cart
     * @return Customer|null
     */
    public function getCustomerFromCart(Cart $cart)
    {
        $customerId = $cart->id_customer;

        return $this->fetchCustomer($customerId);
    }

    /**
     * Fetches customer by it's ID and uses <code>Validate</code>
     * to check if it exists.
     *
     * @param $customerId
     * @return Customer|null
     */
    private function fetchCustomer($customerId)
    {
        $customer = new Customer($customerId);

        if (!Validate::isLoadedObject($customer))
        {
            return null;
        }

        return $customer;
    }

    /**
     * Parses phone number to international E164 format with libphonenumber.
     *
     * @param string $countryCode
     * @param string $phoneNumber
     * @return string
     */
    private function parsePhoneNumber($countryCode, $phoneNumber)
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneNumberUtil->parse($phoneNumber, $countryCode);

        return $phoneNumberUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::E164);
    }
}