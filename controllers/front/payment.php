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
        $pclass = null;

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
                $idealIssuer = $_POST['issuer'];
                if (empty($idealIssuer))
                    die('Could not initialize iDEAL payment.');

                if (!self::$ISSUERS[$idealIssuer])
                    die('Invalid issuer');
            }
            else if ($paymentMethod == 'klarna')
            {
                $pclass = $_POST['pclass'];
                if (empty($pclass))
                    die('Could not initiate Klarna transaction');
            }
        }

        $orderAmount = $cart->getOrderTotal(true, Cart::BOTH);
        $transaction = $this->getTransactionData(
            $orderAmount,
            $paymentMethod,
            $idealIssuer,
            $cart,
            $customer,
            $cardToken,
            $pclass
        );

        if (is_null($transaction))
        {
            die('Could not initialise a valid transaction.');
        }

        $submittedTransaction = $this->module->transactionHelper->submitTransaction($transaction, $paymentMethod);
        if (is_null($submittedTransaction))
        {
            die('Transaction was declined.');
        }
        else
        {
            $this->module->transactionHelper->storeTransaction(
                $submittedTransaction->_id,
                $paymentMethod,
                $cart->id,
                $submittedTransaction->status
            );
        }

        if (isset($submittedTransaction->details->approval_url))
        {
            Tools::redirect($submittedTransaction->details->approval_url);
        }
        else
        {
            if ($paymentMethod == 'creditcard' || $paymentMethod == 'klarna')
            {
                Tools::redirect($this->context->link->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]));
            }
        }
    }

    public function getTransactionData($amount, $method, $issuer, $cart, $customer, $cardToken, $pclass)
    {
        $goodsList = $this->module->goodsHelper->getGoodsList($cart->getProducts());
        $total = $goodsList->getTotalAmount();

        $payment = array();
        $payment['amount'] = (int) $total;
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
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                $payment['details']['issuer'] = $issuer;
                break;
            case 'creditcard':
                $payment['payment_product'] = 'card';
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'CC_ACCOUNT', true);
                $payment['card'] = $cardToken;
                break;
            case 'paypal':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'PAYPAL_ACCOUNT', true);
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                $payment['details']['capture_now'] = true;
                break;
            case 'sepa':
            case 'slimpay':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'SEPA_ACCOUNT', true);
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);

                $customer = $this->module->customerHelper->getCustomer($cart, $payment['account']);

                if (is_null($customer))
                {
                    return null;
                }
                $payment['customer'] = $customer;
                break;
            case 'klarna':
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'KLARNA_ACCOUNT', true);
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                $payment['details']['pclass'] = $pclass;
                $payment['details']['goods_list'] = $this->module->goodsHelper->getGoodsList($cart->getProducts());
                $customer = $this->module->customerHelper->getCustomer($cart, $payment['account']);

                if (is_null($customer))
                {
                    return null;
                }
                $payment['customer'] = $customer;
                break;
        }

        return $payment;
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

    protected function getHttpsRedirectUrl($cartId)
    {
        $url = $this->context->link->getModuleLink($this->module->name, 'return', ['cart_id' => $cartId]);

        if (substr($url, 0, 5) === 'https')
        {
            return $url;
        }

        return str_replace('http', 'https', $url);
    }
}