<?php

class Helper
{
    protected $api;

    public function __construct(\SpryngPaymentsApiPhp\Client $api)
    {
        $this->api = $api;
    }
}