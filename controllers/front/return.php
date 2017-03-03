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

        $transaction = $this->module->transactionHelper->findTransactionByCartId($cart->id);

        $msgWelcome = '';
        $msgMsg = '';
        $msgContinue = '<a href="' . _PS_BASE_URL_ . __PS_BASE_URI__ . '">' . 'Continue shopping' . '</a>';
        switch($transaction->status)
        {
            case 'SETTLEMENT_COMPLETED':
                $msgMsg = 'Thank you. Your order was paid successfully.';
                break;
            case 'SETTLEMENT_REQUESTED':

        }
    }
}