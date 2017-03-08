<?php

/**
 * Class CustomerHelper
 */
class CustomerHelper extends Helper
{
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
}