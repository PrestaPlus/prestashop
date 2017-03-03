<?php

class OrderHelper extends SpryngHelper
{
    public function setStatus($orderId, $newStatus)
    {
        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->id_order_state = $newStatus;
        $history->changeIdOrderState($orderId, $newStatus);
    }
}