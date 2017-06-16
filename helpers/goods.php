<?php

/**
 * Class GoodsHelper
 */
class GoodsHelper extends SpryngHelper
{

    // Klarna flags
    const FLAG_SHIPMENT_FEE = 8;
    const FLAG_HANDLING_FEE = 16;
    const FLAG_INCL_VAT = 32;

    /**
     * Generates a goods list for Klarna payments
     *
     * @param Cart $cart
     * @return \SpryngPaymentsApiPhp\Object\GoodsList
     * @internal param Cart $products
     */
    public function getGoodsList(Cart $cart, $withShipping = true)
    {
        $goods = new \SpryngPaymentsApiPhp\Object\GoodsList();

        foreach($cart->getProducts() as $product)
        {
            $goods->add($this->convertProductToGood($product));
        }

        if ($withShipping)
        {
            // Add shipping
            $shipping = new \SpryngPaymentsApiPhp\Object\Good();
            $shippingFlags = array(self::FLAG_SHIPMENT_FEE);
            $shipping->price = $cart->getTotalShippingCost(null, true) * 100;
            $shipping->price = (int) $shipping->price;
            $shipping->discount = 0;
            $shipping->quantity = 1;
            $shipping->reference = "SHIPPING";
            $shipping->title = 'Shipping fee';

            // Check if there is vat on shipping and set is appropriately
            if ($cart->getTotalShippingCost(null, true) > $cart->getTotalShippingCost(null, false))
            {
                $shippingVat = (100 - (($cart->getTotalShippingCost(null, false) / $cart->getTotalShippingCost(null, true)) * 100));
                $shippingVat = (int) round($shippingVat);
                $shipping->vat = $shippingVat;
                array_push($shippingFlags, self::FLAG_INCL_VAT);
            }
            else
            {
                $shipping->vat = 0;
            }
            $shipping->flags = $shippingFlags;

            $goods->add($shipping);
        }

        return $goods;
    }

    /**
     * Converts a Prestashop Product instance to a Good to be used in the goods list
     *
     * @param array $product
     * @return \SpryngPaymentsApiPhp\Object\Good
     */
    private function convertProductToGood(array $product)
    {
        $good = new \SpryngPaymentsApiPhp\Object\Good();


        if ($product['rate'] > 0)
        {
            $good->flags = array(self::FLAG_INCL_VAT);
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
        $good->title = strip_tags(str_replace('!','',$product['description_short']));
        $good->quantity = $product['quantity'];

        return $good;
    }
}