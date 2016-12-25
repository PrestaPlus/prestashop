<?php

use SpryngPaymentsApiPhp\Client;

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

class SpryngPayments extends PaymentModule
{

    const VERSION = '1.0';
    const CONFIG_KEY_PREFIX = 'SPRYNG_';

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

    /**
     * Installs the plugin by creating database table for payments, registering hooks and initializing default configuration.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->_registerHooks())
        {
            $this->_errors[] = 'An error has occurred during the installation process of Spryng Payments for Prestashop.';
            return false;
        }
        else if (!$this->initConfig())
        {
            $this->_errors[] = 'The installer was unable to initialize the default settings.';
            return false;
        }

        $this->createDatabaseTables();

        return true;
    }

    /**
     * Uninstalls the plugin by dropping database table, unregistering hooks and deleting default configuration.
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!$this->_unregisterHooks())
        {
            $this->_errors[] = 'The uninstallation process of Spryng Payments for Prestashop failed. A hook could not be
            unregistered.';
            return false;
        }

        if (!$this->deleteConfiguration())
        {
            $this->_errors[] = 'Failed to delete all configuration values.';
            return false;
        }

        $this->dropDatabaseTables();
        return true;
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

    protected function initializeConfiguration()
    {
        return
            // General settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PLUGIN_VERSION', $this->getVersion()) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'API_KEY', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SANDBOX_ENABLED', true) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'MERCHANT_REFERENCE', 'Prestashop plugun') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAY_BUTTON_TEXT', 'Pay with Spryng Payments') &&

            // iDEAL settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_TITLE', 'Spryng Payments - iDEAL') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_DESCRIPTION', 'Pay online via your own bank') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ACCOUNT', '') &&

            // Credit Card settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_TITLE', 'Spryng Payments - CreditCard') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_DESCRIPTION', 'Pay using your own CreditCard') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_ACCOUNT', '') &&

            // PayPal settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_TITLE', 'Spryng Payments - PayPal') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_DESCRIPTION', 'Pay safely using PayPal') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ACCOUNT', '');
    }

    protected function deleteConfiguration()
    {
        return
            // General settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PLUGIN_VERSION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'API_KEY') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SANDBOX_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'MERCHANT_REFERENCE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAY_BUTTON_TEXT') &&

            // iDEAL settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ACCOUNT') &&

            // Credit Card settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_ACCOUNT') &&

            // PayPal settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ACCOUNT');
    }

    protected function initializeConfigurationValue($key, $value)
    {
        return Configuration::updateValue($key, (Configuration::get($key) !== false) ? Configuration::get($key) :
            $value);
    }

    protected function deleteConfigurationValue($key)
    {
        return Configuration::deleteByName($field);
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * @return string
     */
    public function getConfigKeyPrefix()
    {
        return self::CONFIG_KEY_PREFIX;
    }
}