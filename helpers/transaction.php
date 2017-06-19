<?php

/**
 * Class TransactionHelper
 */
class TransactionHelper extends SpryngHelper
{
    /**
     * Submits a transaction to the API
     *
     * @param $transaction
     * @param $method
     * @return null|\SpryngPaymentsApiPhp\Object\Transaction
     */
    public function submitTransaction($transaction, $method)
    {
        try
        {
            switch($method)
            {
                case 'creditcard':
                    $newTransaction = $this->api->transaction->create($transaction);
                    break;
                case 'ideal':
                    $newTransaction = $this->api->iDeal->initiate($transaction);
                    break;
                case 'paypal':
                    $newTransaction = $this->api->Paypal->initiate($transaction);
                    break;
                case 'sepa':
                case 'slimpay':
                    $newTransaction = $this->api->Sepa->initiate($transaction);
                    break;
                case 'klarna':
                    $newTransaction = $this->api->Klarna->initiate($transaction);
                    break;
                case 'sofort':
                    $newTransaction = $this->api->SOFORT->initiate($transaction);
                    break;
            }
        }
        catch(\SpryngPaymentsApiPhp\Exception\TransactionException $ex)
        {
            return null;
        }
        catch(\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            var_dump($ex);
            return null;
        }

        return $newTransaction;
    }

    /**
     * Stores the details of a transaction in the database.
     *
     * @param $transactionId
     * @param $method
     * @param $cartId
     * @param $status
     */
    public function storeTransaction($transactionId, $method, $cartId, $status, $webhookKey)
    {
        Db::getInstance()->insert(
            'spryng_payments',
            array(
                'transaction_id' => $transactionId,
                'payment_method' => $method,
                'cart_id' => $cartId,
                'order_id' => null,
                'status' => $status,
                'webhook_key' => $webhookKey,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            )
        );
    }

    /**
     * Saves the cart ID for a Spryng Payments transaction to the database.
     *
     * @param $cartId
     * @return bool
     */
    public function setOrderIdForCartId($cartId)
    {
        Db::getInstance()->update(
            _DB_PREFIX_ . 'spryng_payments',
            array('order_id' => $this->getOrderIdForCartId($cartId)),
            'cart_id = ' . $cartId
        );

        return true;
    }

    /**
     * Retrieves the order ID for a cart ID from the database.
     *
     * @param $cartId
     * @return int|null
     */
    public function getOrderIdForCartId($cartId)
    {
        $orderData = Db::getInstance()->executeS(sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = %d ORDER BY `%s` DESC LIMIT 1;',
            'id_order',
            _DB_PREFIX_ . 'orders',
            'id_cart',
            $cartId,
            'date_add'
        ));

        if (count($orderData) !== 1)
        {
            return null;
        }
        else
        {
            return (int) $orderData[0]['id_order'];
        }
    }

    /**
     * Finds a Spryng transaction ID for a certain cart ID
     *
     * @param $cartId
     * @return Exception|null|\SpryngPaymentsApiPhp\Exception\RequestException|\SpryngPaymentsApiPhp\Object\Transaction
     */
    public function findTransactionByCartId($cartId)
    {
        $data = Db::getInstance()->executeS(
            sprintf('SELECT * FROM `%s` WHERE `%s` = %d ORDER BY `%s` DESC LIMIT 1;',
                _DB_PREFIX_ . 'spryng_payments',
                'cart_id',
                $cartId,
                'created_at'
            )
        );

        if (count($data) !== 1)
        {
            return null;
        }
        else
        {
            $data = $data[0];
        }

        try
        {
            $transaction = $this->api->transaction->getTransactionById($data['transaction_id']);
        }
        catch (\SpryngPaymentsApiPhp\Exception\RequestException $clientException)
        {
            return $clientException;
        }

        return $transaction;
    }

    /**
     * Finds the details of an order by a Spryng Transaction ID in the database
     *
     * @param $transactionId
     * @return null
     */
    public function findOrderDetailsByTransactionId($transactionId)
    {
        $data = Db::getInstance()->executeS(
            sprintf('SELECT * FROM `%s` WHERE `%s` = "%s" ORDER BY `%s` DESC LIMIT 1;',
                _DB_PREFIX_ . 'spryng_payments',
                'transaction_id',
                $transactionId,
                'created_at'
            )
        );

        if (count($data) !== 1)
        {
            return null;
        }
        else
        {
            return $data[0];
        }
    }

    /**
     * Searches database for order details based on webhook key
     *
     * @param $key
     * @return null
     */
    public function findOrderDetailsByWebhookKey($key)
    {
        $data = Db::getInstance()->executeS(
            sprintf('SELECT * FROM `%s` WHERE `%s` = "%s" ORDER BY `%s` DESC LIMIT 1;',
                _DB_PREFIX_ . 'spryng_payments',
                'webhook_key',
                $key,
                'created_at'
            )
        );

        if (count($data) !== 1)
        {
            return null;
        }
        else
        {
            return $data[0];
        }
    }

    public function generateWebhookKey($length)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
        $key = '';
        for ($i = 0; $i < $length; $i++)
        {
            $key .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $key;
    }
}