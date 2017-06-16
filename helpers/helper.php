<?php

/**
 * Class SpryngHelper
 */
class SpryngHelper
{
    /**
     * Instance of the Spryng Payments PHP SDK
     *
     * @var \SpryngPaymentsApiPhp\Client
     */
    protected $api;

    /**
     * SpryngHelper constructor. Initiates the SDK
     *
     * @param \SpryngPaymentsApiPhp\Client $api
     */
    public function __construct(\SpryngPaymentsApiPhp\Client $api)
    {
        $this->api = $api;
    }
}