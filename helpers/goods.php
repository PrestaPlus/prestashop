<?php

class GoodsHelper extends SpryngHelper
{

    // Klarna flags
    const FLAG_SHIPMENT_FEE = 8;
    const FLAG_HANDLING_FEE = 16;
    const FLAG_INCL_VAT = 32;

    public function getGoodsList(array $products)
    {
        $goods = new \SpryngPaymentsApiPhp\Object\GoodsList();

        foreach($products as $product)
        {
            $goods->add($this->convertProductToGood($product));
        }

        return $goods;
    }

    private function convertProductToGood(array $product)
    {
        $good = new \SpryngPaymentsApiPhp\Object\Good();


        if ($product['rate'] > 0)
        {
            $good->flags = [self::FLAG_INCL_VAT];
            $good->vat = (int) $product['rate'];
            $good->price = (int) (100 * round($product['price_wt'], 2));
        }
        else
        {
            $good->flags = [];
            $good->vat = 0;
            $good->price = (int) (100 * round($product['price'], 2));
        }

        if ($product['price_with_reduction'] < $product['price_without_reduction'])
        {
            $discountRate = (100 - (($product['price_with_reduction'] / $product['price_without_reduction']) * 100));
            $discountRate = (int) round($discountRate);
        }
        else
        {
            $discountRate = 0;
        }

        $good->discount = $discountRate;
        $good->reference = $product['reference'];
        $good->title = strip_tags($product['description_short']);
        $good->quantity = $product['quantity'];

        return $good;
    }
}