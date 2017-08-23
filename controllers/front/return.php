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
        if ($transaction instanceof \SpryngPaymentsApiPhp\Exception\RequestException)
        {
            PrestaShopLogger::addLog(sprintf('%s: RequestException occurred while trying to fetch transaction for cart %d. Message: %s',
                $this->module->name, $cartId, $transaction->getMessage()));
            die;
        }
        $stateId = (int) $this->module->getConfigurationValue($this->module->getConfigKeyPrefix() . $transaction->status);

        $this->module->validateOrder(
            $cartId,
            $stateId,
            $this->getEuroAmount($transaction->amount, $cartId),
            $transaction->payment_product,
            null,
            array(),
            null,
            false,
            $cart->secure_key
        );

        $this->module->transactionHelper->setOrderIdForCartId($cartId);

        $msgWelcome = 'Welcome Back';
        $msgMsg = '';
        $msgContinue = '<a href="' . _PS_BASE_URL_ . __PS_BASE_URI__ . '">' . 'Continue shopping' . '</a>';
        switch($transaction->status)
        {
            case 'SETTLEMENT_REQUESTED':
            case 'SETTLEMENT_COMPLETED':
                $msgMsg = 'Thank you. Your order was paid successfully.';
                break;
            case 'INITIATED':
            case 'AUTHORIZED':
            case 'SETTLEMENT_PROCESSED':
            case 'UNKNOWN':
            default:
                $msgMsg = 'We have not yet received a definite payment status. Your order will be updated as soon as your payment is confirmed';
                break;
            case 'DECLINED':
            case 'FAILED':
            case 'SETTLEMENT_FAILED':
            case 'VOIDED':
                $msgMsg = 'We have received a negative payment status from the processor. Your order was not paid successfully.';
                break;
        }

        $this->context->smarty->assign([
            'welcome' => $msgWelcome,
            'message' => $msgMsg,
            'continue' => $msgContinue
        ]);

        $this->setTemplate('return.tpl');
    }

    private function getEuroAmount($amount, $cartId)
    {
        $cart = new Cart($cartId);
        $euroCurrencyId = Currency::getIdByIsoCode('EUR');
        $amount = (float) $amount / 100;

        if (!$euroCurrencyId)
        {
            return null;
        }

        if ($cart->id_currency !== $euroCurrencyId)
        {
            $amount = Tools::convertPriceFull($amount, Currency::getCurrencyInstance($euroCurrencyId), Currency::getCurrencyInstance($cart->id_currency));
        }

        return round($amount, 2);
    }
}