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

        var_dump($this->module->transactionHelper->findTransactionByCartId($cart->id));

    }
}