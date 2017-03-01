<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

/**
 * Class SpryngPaymentsReturnModuleFrontController
 */
class SpryngPaymentsReturnModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if (!isset($_GET['cart_id']))
        {
            die('Invalid');
        }

        $cartId = (int) $_GET['cart_id'];
        $cart = new Cart($cartId);
        if (!$cart)
        {
            die('Invalid');
        }

        $transactionData = Db::getInstance()->executeS(
            sprintf('SELECT * FROM `%s` WHERE `%s` = %d ORDER BY `%s` DESC LIMIT 1;',
                _DB_PREFIX_ . 'spryng_payments',
                'cart_id',
                $cart->id,
                'created_at'
            )
        );

        if (count($transactionData) < 1)
        {
            die('Invalid');
        }
        else
        {
            $transactionData = $transactionData[0];
        }

        $transaction = $this->module->api->transaction->getTransactionById($transactionData['transaction_id']);

        var_dump($transaction);
    }
}