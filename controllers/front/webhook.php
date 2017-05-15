<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access.');
}

class SpryngPaymentsWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $payload = file_get_contents('php://input'); // Get the body of the request
        $parsedPayload = json_decode($payload, true); // Parse as JSON
        $providedTransactionId = $parsedPayload['_id']; // Get the ID of the provided order

        $orderDetails = $this->module->transactionHelper->findOrderDetailsByTransactionId($providedTransactionId); // Fetch details

        if (is_null($orderDetails))
        {
            // Log invalid webhook use
            Logger::addLog($this->module->name . ': received webhook with invalid transaction ID. Provided: ' . htmlentities($providedTransactionId));
            die;
        }

        $transaction = $this->module->transactionHelper->findTransactionByCartId($orderDetails['cart_id']); // Fetch transaction by cart id
        if ($transaction instanceof \SpryngPaymentsApiPhp\Exception\RequestException)
        {
            Logger::addLog(sprintf('%s: Error occurred while trying to fetch transaction for cart %d. Message: ',
                $this->module->name, $orderDetails['cart_id'], $transaction->getMessage()));
            die;
        }

        if (is_null($transaction))
        {
            // Log invalid webhook use
            Logger::addLog(sprintf(
                '%s: Transaction with ID %s no longer seems to exist.',
                $this->module->name,
                $transaction->_id
            ));
            die;
        }

        // Get the order id by it's cart id
        $orderId = $this->module->transactionHelper->getOrderIdForCartId($orderDetails['cart_id']);
        // Update the order with the new status
        $this->module->changeOrderStatus($orderId, $transaction->status);

        // And done
        die;
    }
}