<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

/**
 * Hook controller for Prestashop payment
 *
 * Class SpryngPaymentsPaymentModuleFrontController
 */
class SpryngPaymentsPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * iDEAL Issuers
     *
     * @var array
     */
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

    /**
     * SOFORT supported country codes
     *
     * @var array
     */
    public $SOFORT_AVAILABLE_COUNTRIES = array('AT', 'BE', 'CZ', 'DE', 'HU', 'IT', 'NL', 'PL', 'SK', 'ES', 'CH', 'GB');

    /**
     * Gets run when user initiates checkout
     */
    public function initContent()
    {
        // Run parent
        parent::initContent();

        $cart = $this->context->cart; // Fetch cart
        $customer = new Customer($cart->id_customer); // Fetch customer
        $idealIssuer = null; // Set iDEAL issuer to null as default
        $pclass = null; // Set Klarna PClass to null as default

        // Sets method specific parameters
        if ( // Essentially checks if user wants to pay with credit card and provided the correct parameters
            // isset($_POST['method']) && // Method exists
            // $_POST['method'] == 'creditcard' && // The method is credit card
            // isset($_POST['cardToken']) && // The user posted a tokenised version of a cart
            // (bool) $_POST['cardToken'] // It's not empty
            // avp
            Tools::getValue('method') == 'creditcard' &&
            Tools::getValue('cardToken')            
        )
        {
            // Correct parameters for credit card are supplied. Set the method to credit card and save token
            $paymentMethod = 'creditcard';
            //avp
            // $cardToken = $_POST['cardToken'];
            $cardToken = Tools::getValue('cardToken');
        }
        else
        {
            $cardToken = null; // No credit card token
            //avp
            // $paymentMethod = isset($_POST['method']) ? $_POST['method'] : null; // Get the supplied method
            $paymentMethod = Tools::getValue('method') ? Tools::getValue('method') : null; // Get the supplied method            
            if ($paymentMethod == 'ideal')
            {
                // If the method is ideal, check if the user supplied a valid issuer (bank)
                //avp
                // $idealIssuer = $_POST['issuer'];
                $idealIssuer = Tools::getValue('ideal_issuer');                
                if (empty($idealIssuer))
                    die('Could not initialize iDEAL payment.');

                if (!self::$ISSUERS[$idealIssuer])
                    die('Invalid issuer');
            }
            else if ($paymentMethod == 'klarna')
            {
                // If the method is Klarna, check if the user supplied a valid pclass (payment plan)
                $pclass = $_POST['pclass'];
                if (empty($pclass))
                    die('Could not initiate Klarna transaction');
            }
        }

        // Generate a secret key for the webhook
        $webhookKey = $this->module->transactionHelper->generateWebhookKey(100);

        // Prepare a transaction
        $transaction = $this->getTransactionData(
            $paymentMethod,
            $idealIssuer,
            $cart,
            $webhookKey,
            $cardToken,
            $pclass
        );

        if (is_null($transaction))
        {
            // There was still something wrong with the data we received and the getTransactionData could not
            // prepare a valid transaction. Die.
            die('Could not initialise a valid transaction.');
        }

        // Submit the transaction to the API
        $submittedTransaction = $this->module->transactionHelper->submitTransaction($transaction, $paymentMethod);
        if (is_null($submittedTransaction))
        {
            // Something went wrong and the transaction could not be initialised. Die.
            die('Transaction was declined.');
        }
        else
        {
            // We got a valid transaction. Save it's data to the database.
            $this->module->transactionHelper->storeTransaction(
                $submittedTransaction->_id,
                $paymentMethod,
                $cart->id,
                $submittedTransaction->status,
                $webhookKey
            );
        }

        if (isset($submittedTransaction->details->approval_url))
        {
            // If there's an additional process for the customer to complete the payment, like with PayPal or iDEAL,
            // redirect the customer to the 'approval_url' where they can complete the transaction.
            Tools::redirect($submittedTransaction->details->approval_url);
        }
        else
        {
            // Klarna and Credit Card don't require extra actions from the customer to redirect them to the default
            // return page to inform them about the status of their transaction.
            if ($paymentMethod == 'creditcard' || $paymentMethod == 'klarna')
            {
                Tools::redirect($this->context->link->getModuleLink('spryngpayments', 'return', ['cart_id' => $cart->id]));
            }
        }
    }

    /**
     * Prepares a transaction for submission.
     *
     * @param $method
     * @param $issuer
     * @param $cart
     * @param $webhooKKey
     * @param $cardToken
     * @param $pclass
     * @return array|null
     * @internal param $amount
     * @internal param $customer
     */
    public function getTransactionData($method, $issuer, $cart, $webhooKKey, $cardToken, $pclass)
    {
        // Generate a goodslist based on the products in the users cart. Mainly for Klarna but we can also calculate
        // the total cost of the order.
        $goodsList = $this->module->goodsHelper->getGoodsList($cart, true);
        $total = $goodsList->getTotalAmount(); // Get total order amount

        $payment = array();
        $payment['amount'] = (int) $total; // Order total
        $payment['customer_ip'] = $_SERVER['REMOTE_ADDR']; // The customers IP address
        // Formulate a random string to identify the transaction at a later stage
        $payment['dynamic_descriptor'] = time() . $cart->id;
        // Load the merchant reference setting
        $payment['merchant_reference'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'MERCHANT_REFERENCE');
        $payment['user_agent'] = $_SERVER['HTTP_USER_AGENT']; // Get the users' User Agent
        $payment['capture'] = true; // Capture the transaction right away
        $payment['webhook_transaction_update'] = $this->getProtectedWebhookUrl($webhooKKey);


        /**
         * Adds additional order information for various payment methods.
         */
        switch($method)
        {
            case 'ideal':
                // Set the iDEAL account
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'IDEAL_ACCOUNT');
                // Generate a redirect URL
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                // Set the iDEAL issuer (bank)
                $payment['details']['issuer'] = $issuer;
                break;
            case 'creditcard':
                // Tell the API that it's a card transaction
                $payment['payment_product'] = 'card';
                // Load the account setting
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'CC_ACCOUNT', true);
                // Add the card token
                $payment['card'] = $cardToken;
                break;
            case 'paypal':
                // Load the account setting
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'PAYPAL_ACCOUNT', true);
                // Generate a redirect URL
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                // Capture right away
                $payment['details']['capture_now'] = true;
                break;
            case 'sepa':
            case 'slimpay':
                // Load the account setting
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'SEPA_ACCOUNT', true);
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);

                // Generate a customer object and save it to the database. getCustomer returns the Spryng ID of the customer
                // which will be supplied to the transaction for submission.
                $customer = $this->module->customerHelper->getCustomer($cart, $payment['account']);

                if (is_null($customer))
                {
                    // Return null if no customer could be initialised properly.
                    return null;
                }
                $payment['customer'] = $customer;
                break;
            case 'klarna':
                // Load the account setting
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'KLARNA_ACCOUNT', true);
                // Generate a redirect URL
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                // Set the pclass (payment plan)
                $payment['details']['pclass'] = $pclass;
                // Add the generated goods list
                $payment['details']['goods_list'] = $this->module->goodsHelper->getGoodsList($cart);
                // Generate a customer object and save it to the database. getCustomer returns the Spryng ID of the customer
                // which will be supplied to the transaction for submission.
                $customer = $this->module->customerHelper->getCustomer($cart, $payment['account']);

                if (is_null($customer))
                {
                    // Return null if no customer could be initialised properly.
                    return null;
                }
                $payment['customer'] = $customer;
                break;
            case 'sofort':
                // Load the account setting
                $payment['account'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'SOFORT_ACCOUNT', true);
                // Generate a redirect URL
                $payment['details']['redirect_url'] = $this->getHttpsRedirectUrl($cart->id);
                // Load the project ID setting
                $payment['details']['project_id'] = $this->module->getConfigurationValue($this->module->getConfigKeyPrefix().'SOFORT_PROJECT_ID', true);

                // Fetch the invoice address from the address helper to get the country
                $invoiceAddress = $this->module->addressHelper->getInvoiceAddressForCart($cart);
                // Get the proper country code
                $countryCode = $this->module->addressHelper->getIsoCountryCodeForCustomerCountry($invoiceAddress->country);

                if (!in_array($countryCode, $this->SOFORT_AVAILABLE_COUNTRIES))
                {
                    // Return null if the customers country is not supported by SOFORT
                    return null;
                }
                $payment['country_code'] = $countryCode;

                // SOFORT does not allow for underscores in the dynamic description
                $payment['dynamic_descriptor'] = str_replace('_','',$payment['dynamic_descriptor']);
                break;
        }

        return $payment;
    }

    /**
     * Submits the transaction to the API
     *
     * @param $transaction
     * @param $method
     * @return mixed
     */
    protected function submitTransaction($transaction, $method)
    {
        try
        {
            // Use proper method to submit the transaction
            switch(strtolower($method))
            {
                case 'creditcard':
                    $newTransaction = $this->module->api->transaction->create($transaction);
                    break;
                case 'ideal':
                    $newTransaction = $this->module->api->iDeal->initiate($transaction);
                    break;
                case 'paypal':
                    $newTransaction = $this->module->api->Paypal->initiate($transaction);
                    break;
                case 'sepa':
                case 'slimpay':
                    $newTransaction = $this->module->api->Sepa->initiate($transaction);
                    break;
                case 'klarna':
                    $newTransaction = $this->module->api->Klarna->initiate($transaction);
                    break;
                case 'sofort':
                    $newTransaction = $this->module->api->SOFORT->initiate($transaction);
                    break;
            }
        }
        catch(\SpryngPaymentsApiPhp\Exception\TransactionException $ex)
        {
            die('<p>Submitted transaction is invalid.</p>');
        }
        catch(\SpryngPaymentsApiPhp\Exception\RequestException $ex)
        {
            die('<p>Your payment was refused.</p>');
        }

        return $newTransaction;
    }

    /**
     * Generate an HTTPS redirect URL for payment methods with redirect flow
     *
     * @param $cartId
     * @return mixed
     */
    protected function getHttpsRedirectUrl($cartId)
    {
        // Get link for return controller
        $url = $this->context->link->getModuleLink($this->module->name, 'return', ['cart_id' => $cartId]);

        // Return URL if it's already HTTPS
        if (substr($url, 0, 5) === 'https')
        {
            return $url;
        }

        // If not, return URL, replacing HTTP for HTTPS
        return str_replace('http', 'https', $url);
    }

    protected function getProtectedWebhookUrl($key)
    {
        $url = $this->context->link->getModuleLink($this->module->name, 'webhook', ['key' => $key]);
        $url = str_replace('presta.app', '8429d8a3.ngrok.io', $url);

        if (substr($url, 0, 5) === 'https')
        {
            return $url;
        }

        return str_replace('http', 'https', $url);
    }
}
