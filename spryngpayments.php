<?php

use SpryngPaymentsApiPhp\Client;

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

class SpryngPayments extends PaymentModule
{
    public $api = null;
    public $name = 'spryngpayments';
    public $tab = 'payments_gateways';
    public $version = '1.0';
    public $author = 'Complexity';
    public $need_instance = true;
    public $ps_versions_compliency = array('min' => '1.5', 'max' => '2');
    public $settings = [
        'SPRYNG_API_KEY',
        'SPRYNG_SANDBOX_ENABLED'
    ];

    public function __construct()
    {
        parent::__construct();

        $this->displayName = 'Spryng Payments for Prestashop';
        $this->description = 'Spryng Payments payment gateway for Prestashop.';

        require_once('vendor/autoload.php');

        $this->api = new Client($this->getSettingValue('SPRYNG_API_KEY'), $this->getSettingValue('SPRYNG_SANDBOX_ENABLED'));
    }

    private function getSettingValue($setting)
    {
        return Configuration::get($setting);
    }

    public function uninstall()
    {
        if (!parent::uninstall())
        {
            return false;
        }

        foreach ($this->settings as $setting)
        {
            if (!Configuration::deleteByName($setting))
            {
                return false;
            }
        }

        $this->dropDatabaseTables();

        return true;
    }

    public function install()
    {
        if (!parent::install() || !$this->_registerHooks())
        {

        }

        $this->createDatabaseTables();
    }

    private function dropDatabaseTables()
    {
        require(dirname(__FILE__).'/db/drop.php');
    }

    private function createDatabaseTables()
    {
        require(dirname(__FILE__).'/db/init.php');
    }
    
    protected function _registerHooks()
    {
        return
            $this->_registerHook('displayPayment') &&
            $this->_registerHook('displayPaymentEU', false) &&
            $this->_registerHook('displayPaymentTop') &&
            $this->_registerHook('displayAdminOrder') &&
            $this->_registerHook('displayHeader') &&
            $this->_registerHook('displayBackOfficeHeader') &&
            $this->_registerHook('displayOrderConfirmation');
    }

    protected function _unregisterHooks()
    {
        return
            $this->_unregisterHook('displayPayment') &&
            $this->_unregisterHook('displayPaymentEU', false) &&
            $this->_unregisterHook('displayPaymentTop') &&
            $this->_unregisterHook('displayAdminOrder') &&
            $this->_unregisterHook('displayHeader') &&
            $this->_unregisterHook('displayBackOfficeHeader') &&
            $this->_unregisterHook('displayOrderConfirmation');
    }
    
    protected function _registerHook($name, $standard = true)
    {
        if (!$standard && Hook::getIdByName($name))
        {
            return true;
        }
        
        return $this->registerHook($name);
    }

    protected function _unregisterHook($name, $standard = true)
    {
        if (!$standard && !Hook::getIdByName($name))
        {
            return true;
        }

        return $this->unregisterHook($name);
    }
}