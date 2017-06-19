<?php

/**
 * Class OrderHelper
 */
class OrderHelper extends SpryngHelper
{
    /**
     * Changes the status of an order
     *
     * @param $orderId
     * @param $newStatus
     */
    public function setStatus($orderId, $newStatus)
    {
        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->id_order_state = $newStatus;
        $history->changeIdOrderState($orderId, $newStatus);
    }

    /**
     * Generates a random string to be used as key in the webhook function
     *
     * @param $length
     * @return string
     */
    private function getRandomStr($length)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
        $str = '';
        for ($i = 0; $i < $length; $i++)
        {
            $str .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $str;
    }
}