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
            isset($_POST['payment_product']) &&
            $_POST['payment_product'] == 'cart' &&
            isset($_POST['card']) &&
            $_GET['method'] === 'card'
        )
        {
            $paymentMethod = 'creditcard';
            $cardToken = $_POST['card'];
        }
        else
        {
            $cardToken = null;
            $paymentMethod = isset($_GET['method']) ? $_GET['method'] : null;
            if ($paymentMethod == 'ideal')
            {
                $idealIssuer = $_GET['ideal_issuer'];
                if (empty($idealIssuer))
                    die('Could not initialize iDEAL payment.');

                if (!in_array($idealIssuer, self::$ISSUERS))
                    die('Invalid issuer');
            }
        }

        $orderAmount = $cart->getOrderTotal(true, Cart::BOTH);
        $transaction = $this->preparePaymentObject($orderAmount, $paymentMethod, $idealIssuer, $cart, $customer, $cardToken);

        var_dump($transaction);
        die;

        $this->submitTransaction($transaction, $paymentMethod);

        Db::getInstance()->insert(
            _DB_PREFIX_ . 'spryng_payments',
            array(
                'transaction_id' => $transaction->_id,
                'payment_method' => $paymentMethod,
                'cart_id' => (int) $cart->id,
                'order_id' => null,
                'status' => $transaction->status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            )
        );

        if (isset($transaction->details->approval_url))
        {
            Tools::redirect($transaction->details->approval_url);
        }
        else
        {
            if ($paymentMethod == 'creditcard')
            {
                $this->context->smarty->assign(array(
                    'products' => $cart->nbProducts(),
                    'total' => $orderAmount,
                ));

                $this->setTemplate('creditcard.tpl');
            }
        }
    }

    protected function submitTransaction($transaction, $method)
    {
        try
        {
            switch(strtoupper($method))
            {
                case 'CC':
                    $newTransaction = $this->module->api->transaction->create($transaction);
                    break;
                case 'IDEAL':
                    $newTransaction = $this->module->api->ideal->create($transaction);
                    break;
                case 'PAYPAL':
                    $newTransaction = $this->module->api->paypal->create($transaction);
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
        $payment['amount'] = $amount;
        $payment['customer_ip'] = $_SERVER['REMOTE_ADDR'];
        $payment['dynamic_descriptor'] = $cart->id.'_'.$customer->secure_key.'_'.date('YmdHis');
        $payment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        switch($method)
        {
            case 'ideal':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'IDEAL_ACCOUNT');
                $payment['details']['redirect_url'] = $this->context->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]);
                $payment['details']['issuer'] = $issuer;
                break;
            case 'creditcard':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'CC_ACCOUNT');
                $payment['card'] = $cardToken;
                break;
            case 'paypal':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'PAYPAL_ACCOUNT');
                $payment['details']['redirect_url'] = $this->context->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]);
                break;
        }

        return $payment;
    }
}