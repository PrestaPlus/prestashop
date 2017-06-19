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
        $webhookKey = $_GET['key'];
        $providedTransactionId = $parsedPayload['_id']; // Get the ID of the provided order

        // Try to find order details with the provided webhook key
        $orderDetails = $this->module->transactionHelper->findOrderDetailsByWebhookKey($webhookKey); // Fetch details

        if (is_null($orderDetails))
        {
            // Log invalid webhook use
            PrestaShopLogger::addLog($this->module->name . ': received webhook with invalid transaction ID. Provided: ' . htmlentities($providedTransactionId));
            die;
        }

        // Check if the transaction ID for the found details matches the provided one. If not, something fishy is going on, so die.
        if ($orderDetails['transaction_id'] !== $providedTransactionId)
        {
            PrestaShopLogger::addLog(sprintf('%s: Found order details for webhook key %s, but provided transaction ID \'%s\'
                did not match transaction id \'%s\' from the database.', $this->module->name, $webhookKey,
                $providedTransactionId, $orderDetails['transaction_id']));
            die;
        }

        $transaction = $this->module->transactionHelper->findTransactionByCartId($orderDetails['cart_id']); // Fetch transaction by cart id
        if ($transaction instanceof \SpryngPaymentsApiPhp\Exception\RequestException)
        {
            PrestaShopLogger::addLog(sprintf('%s: Error occurred while trying to fetch transaction for cart %d. Message: ',
                $this->module->name, $orderDetails['cart_id'], $transaction->getMessage()));
            die;
        }

        if (is_null($transaction))
        {
            // Log invalid webhook use
            PrestaShopLogger::addLog(sprintf(
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