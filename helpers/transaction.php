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
            }
        }
        catch(\SpryngPaymentsApiPhp\Exception\TransactionException $ex)
        {
            return null;
        }
        catch(\GuzzleHttp\Exception\ClientException $ex)
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
        catch (\GuzzleHttp\Exception\ClientException $clientException)
        {
            return null;
        }

        return $transaction;
    }
}