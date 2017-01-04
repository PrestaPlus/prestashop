<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

class SpryngPaymentsPrestashopPayController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);

        if (!$this->validateCustomerAndCart($customer, $cart))
        {
            die('Could not validate you for this payment method. <a href="'._PS_BASE_URL_ . __PS_BASE_URI__.'">Click here to continue</a>');
        }

        $paymentMethod = $_GET['method'];
        if ($paymentMethod == 'ideal')
        {
            $idealIssuer = $_GET['ideal_issuer'];
            if (empty($idealIssuer))
            {
                die('Could not initialize iDEAL payment.');
            }
        }
        else
        {
            $idealIssuer = null;
        }

        $orderAmount = $cart->getOrderTotal(true, Cart::BOTH);

        // iDEAL only deals with euro's
        if ($paymentMethod == 'ideal')
        {
            $orderAmount = $this->convertAmountToEuro($orderAmount);
        }


    }

    protected function preparePaymentObject($amount, $method, $issuer, $cartId, $customer, $secureKey)
    {
        $payment = array();
        $payment['amount'] = $amount;
        $payment['customer_ip'] = $_SERVER['REMOTE_ADDR'];
        $payment['dynamic_descriptor'] = $cart->id_customer.'_'.$date('YmdHis');
        $payment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        switch($method)
        {
            case 'ideal':
                $payment['account'] = $this->module->getConfigValue($this->module->getConfigKeyPrefix().'IDEAL_ACCOUNT');
                $payment['details']['issuer'] = $issuer;
                break;
            case 'creditcard':
                $payment['account'] = $this->module->getConfigValue($this->module->getConfigKeyPrefix().'CC_ACCOUNT');
                break;
            case 'paypal':
                $payment['account'] = $this->module->getConfigValue($this->module->getConfigKeyPrefix().'PAYPAL_ACCOUNT');
                break;
        }


        return $payment;
    }

    protected function convertAmountToEuro($amount)
    {
        $cart = $this->context->cart;
        $currency_euro = Currency::getIdByIsoCode('EUR');
        if (!$currency_euro)
        {
            die($this->module->lang['This payment method is only available for Euros.']);
        }

        if ($cart->id_currency !== $currency_euro)
        {
            $amount = Tools::convertPrice($amount, $cart->id_currency, FALSE);

            if (Currency::getDefaultCurrency() !== $currency_euro)
            {
                $amount = Tools::convertPrice($amount, $currency_euro, TRUE);
            }
        }

        return round($amount, 2);
    }

    public function validateCustomerAndCart($customer, $cart)
    {
        if (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice
        || !$this->module->active)
        {
            Tools::redirect(_PS_BASE_URL_ . __PS_BASE_URI__);
            return false;
        }

        if (!Validate::isLoadedObject($customer))
        {
            return false;
        }

        $spryngIsAuthorized = false;
        foreach (Module::getPaymentModules() as $module)
        {
            if ($module['name'] == 'spryngpayments')
            {
                $spryngIsAuthorized = true;
            }
        }

        if (!$spryngIsAuthorized)
        {
            return false;
        }

        return true;
    }
}