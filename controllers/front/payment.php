<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

class SpryngPaymentsPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    static $ISSUERS = array(
        'ABNANL2A' => 'ABN Ambro',
        'ASNBNL21'=> 'ASN Bank',
        'BUNQNL2A' => 'Bunq',
        'FVLBNL22' => 'Van Lanschot Bankiers',
        'INGBNL2A' => 'ING',
        'KNABNL2H' => 'Knab',
        'RABONL2U' => 'Rabobank',
        'RBRBNL21' => 'Regiobank',
        'SNSNML2A' => 'SNS Bank',
        'TRIONL2U' => 'Triodos Bank'
    );

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $idealIssuer = null;

        if (
            isset($_POST['method']) &&
            $_POST['method'] == 'creditcard' &&
            isset($_POST['cardToken']) &&
            (bool) $_POST['cardToken']
        )
        {
            $paymentMethod = 'creditcard';
            $cardToken = $_POST['cardToken'];
        }
        else
        {
            $cardToken = null;
            $paymentMethod = isset($_POST['method']) ? $_POST['method'] : null;
            if ($paymentMethod == 'ideal')
            {
                $idealIssuer = $_POST['ideal_issuer'];
                if (empty($idealIssuer))
                    die('Could not initialize iDEAL payment.');

                if (!self::$ISSUERS[$idealIssuer])
                    die('Invalid issuer');
            }
        }

        $orderAmount = $cart->getOrderTotal(true, Cart::BOTH);
        $transaction = $this->preparePaymentObject($orderAmount, $paymentMethod, $idealIssuer, $cart, $customer, $cardToken);

        $submittedTransaction = $this->submitTransaction($transaction, $paymentMethod);

        Db::getInstance()->insert(
            'spryng_payments',
            array(
                'transaction_id' => $submittedTransaction->_id,
                'payment_method' => $paymentMethod,
                'cart_id' => (int) $cart->id,
                'order_id' => null,
                'status' => $submittedTransaction->status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            )
        );

        if (isset($submittedTransaction->details->approval_url))
        {
            Tools::redirect($submittedTransaction->details->approval_url);
        }
        else
        {
            if ($paymentMethod == 'creditcard')
            {
                Tools::redirect($this->context->link->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]));
            }
        }
    }

    protected function submitTransaction($transaction, $method)
    {
        try
        {
            switch($method)
            {
                case 'creditcard':
                    $newTransaction = $this->module->api->transaction->create($transaction);
                    break;
                case 'ideal':
                    $newTransaction = $this->module->api->iDeal->create($transaction);
                    break;
                case 'paypal':
                    $newTransaction = $this->module->api->Paypal->create($transaction);
            }
        }
        catch(\SpryngPaymentsApiPhp\Exception\TransactionException $ex)
        {
            die('<p>Submitted transaction is invalid.</p>');
        }
        catch(\GuzzleHttp\Exception\ClientException $ex)
        {
            die('<p>Your payment was refused.</p>');
        }

        return $newTransaction;
    }

    protected function preparePaymentObject($amount, $method, $issuer, $cart, $customer, $cardToken)
    {
        $payment = array();
        $payment['amount'] = (int) $amount * 100;
        $payment['customer_ip'] = $_SERVER['REMOTE_ADDR'];
        $payment['dynamic_descriptor'] = $cart->id.'_'.$customer->secure_key;
        $payment['dynamic_descriptor'] = 'TEST_234';
        $payment['merchant_reference'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'MERCHANT_REFERENCE');
        $payment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $payment['capture'] = true;

        switch($method)
        {
            case 'ideal':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'IDEAL_ACCOUNT');
                $payment['details']['redirect_url'] = $this->context->link->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]);
                $payment['details']['issuer'] = $issuer;
                break;
            case 'creditcard':
                $payment['payment_product'] = 'card';
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'CC_ACCOUNT');
                $payment['card'] = $cardToken;
                break;
            case 'paypal':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'PAYPAL_ACCOUNT');
                $payment['details']['redirect_url'] = $this->context->link->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]);
                break;
        }

        return $payment;
    }
}