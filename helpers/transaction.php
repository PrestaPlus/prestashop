<?php

class TransactionHelper extends SpryngHelper
{
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
            return null;
        }

        return $newTransaction;
    }

    public function storeTransaction($transactionId, $method, $cartId, $status)
    {
        Db::getInstance()->insert(
            'spryng_payments',
            array(
                'transaction_id' => $transactionId,
                'payment_method' => $method,
                'cart_id' => $cartId,
                'order_id' => null,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            )
        );
    }

    public function setOrderIdForCartId($cartId)
    {
        Db::getInstance()->update(
            _DB_PREFIX_ . 'spryng_payments',
            array('order_id' => $this->getOrderIdForCartId($cartId)),
            'cart_id = ' . $cartId
        );

        return true;
    }

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
            return null;
        }

        return $transaction;
    }

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
}