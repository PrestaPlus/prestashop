<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

require_once('vendor/autoload.php');

class SpryngPayments extends PaymentModule
{

    const VERSION = '1.0';
    const CONFIG_KEY_PREFIX = 'SPRYNG_';

    public $api = null;
    public $name = 'spryngpayments';
    public $tab = 'payments_gateways';
    public $version = '1.0';
    public $author = 'Spryng Payments';
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

        $this->api = new \SpryngPaymentsApiPhp\Client(
            $this->getConfigurationValue('SPRYNG_API_KEY'),
            $this->getConfigurationValue('SPRYNG_SANDBOX_ENABLED')
        );
    }

    public function getContext()
    {
        if (Tools::isSubmit('btnSubmit'))
        {
            // TODO: Form submitted logic
        }

        $configHtml = '';
        $configHtml .= $this->getConfigurationPanelInfoHtml();
        $configHtml .= $this->getConfigForm();

        return $configHtml;
    }

    protected function getConfigurationPanelInfoHtml()
    {
        return $this->display(__FILE__,'config_info.tpl');
    }

    protected function getConfigForm()
    {
        $fields = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Spryng Payments Configuration',
                    'icon' => 'icon-config'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'API Key',
                        'name' => $this->getConfigKeyPrefix().'API_KEY',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'API_KEY')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Sandbox Mode',
                        'name' => $this->getConfigKeyPrefix().'SANDBOX_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '0',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '1',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Merchant Reference',
                        'name' => $this->getConfigKeyPrefix().'MERCHANT_REFERENCE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'MERCHANT_REFERENCE')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'iDEAL Enabled',
                        'name' => $this->getConfigKeyPrefix().'IDEAL_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '0',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '1',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'iDEAL Title',
                        'name' => $this->getConfigKeyPrefix().'IDEAL_TITLE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'IDEAL_TITLE')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'iDEAL Description',
                        'name' => $this->getConfigKeyPrefix().'IDEAL_DESCRIPTION',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'IDEAL_DESCRIPTION')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'iDEAL Account',
                        'name' => $this->getConfigKeyPrefix().'IDEAL_ACCOUNT',
                        'options' => array(
                            'query' => $this->getAccountListForConfigurationForm(),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Enabled',
                        'name' => $this->getConfigKeyPrefix().'CC_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '0',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '1',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'CreditCard Title',
                        'name' => $this->getConfigKeyPrefix().'CC_TITLE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'CC_TITLE')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'CreditCard Description',
                        'name' => $this->getConfigKeyPrefix().'CC_DESCRIPTION',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'CC_DESCRIPTION')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Account',
                        'name' => $this->getConfigKeyPrefix().'CC_ACCOUNT',
                        'options' => array(
                            'query' => $this->getAccountListForConfigurationForm(),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Enabled',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '0',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '1',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'PayPal Title',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_TITLE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_TITLE')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'PayPal Description',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_DESCRIPTION',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_DESCRIPTION')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Account',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_ACCOUNT',
                        'options' => array(
                            'query' => $this->getAccountListForConfigurationForm(),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                )
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang-id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name'.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields));
    }

    private function getAccountListForConfigurationForm()
    {
        $accounts = $this->api->account->getAll();
        $options = array();

        foreach($accounts as $account)
        {
            $option = array(
                'value' => $account->_id,
                'name' => $account->name
            );
            array_push($options, $option);
        }

        $accountOptions = array(
            'query' => $options,
            'id' => 'value',
            'name' => 'name'
        );

        return $accountOptions;
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
        PrestaShopLogger::addLog('STARTING SPRYNG INSTALLATION');

        if (!parent::install())
        {
            PrestaShopLogger::addLog('Parent installation failed');
            $this->_errors[] = 'An error occured during the initial installation of Spryng Payments for Prestashop.';
            return false;
        }

        if (!$this->_registerHooks())
        {
            PrestaShopLogger::addLog('Registering hooks failed');
            $this->_errors[] = 'An error has occurred during the installation process of Spryng Payments for Prestashop.';
            return false;
        }

        if (!$this->initializeConfiguration())
        {
            PrestaShopLogger::addLog('Initializing configuration failed.');
            $this->_errors[] = 'Spryng Payments failed to load initial configuration.';
            return false;
        }

        if (!$this->createDatabaseTables())
        {
            PrestaShopLogger::addLog('Initializing database failed.');
            $this->_errors[] = 'A database error occured while trying to initialize Spryng Payments.';
            return false;
        }

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
        $queries = [
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'spryng_payments_transactions`',
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'spryng_payments_logs`'
        ];

        foreach ($queries as $query)
        {
            if (!Db::getInstance()->execute($query))
            {
                return false;
            }
        }

        return true;
    }

    private function createDatabaseTables()
    {
        $queries = [
            sprintf("
        CREATE TABLE IF NOT EXISTS `%s` (
          `transaction_id` VARCHAR(255) NOT NULL PRIMARY KEY,
          `payment_method` VARCHAR(255) NOT NULL,
          `cart_id` INT(64),
          `order_id` INT(64),
          `status` VARCHAR(255) NOT NULL,
          `created_at` DATETIME NOT NULL,
          `updated_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
                _DB_PREFIX_.'spryng_payments'
            )
        ];

        foreach($queries as $query)
        {
            if (!Db::getInstance()->execute($query))
            {
                PrestaShopLogger::addLog(sprintf('Error occured while executing %s', $query));
                $this->_errors[] = sprintf('Could not initialize database. Query: %s.', $query);
                return false;
            }
        }

        return true;
    }
    
    protected function _registerHooks()
    {
        return
            $this->_registerHook('displayPayment') &&
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
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'MERCHANT_REFERENCE', 'Prestashop Plugin') &&
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

    public function changeOrderStatus($orderId, $newStatus)
    {

    }

    protected function initializeConfigurationValue($key, $value)
    {
        return Configuration::updateValue($key, (Configuration::get($key) !== false) ? Configuration::get($key) :
            $value);
    }

    protected function deleteConfigurationValue($key)
    {
        return Configuration::deleteByName($key);
    }

    protected function getConfigurationValue($key)
    {
        return Configuration::get($key);
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

    public function hookDisplayPayment($params)
    {
        if (!$this->active)
            return;

        $this->smarty->assign(array(
            'ideal_enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ENABLED')
        ));

        return $this->display(__FILE__,'payment.tpl');
    }

    public function hookDisplayPaymentTop()
    {

    }

    public function hookDisplayAdminOrder()
    {

    }

    public function hookDisplayHeader()
    {

    }

    public function displayBackOfficeHeader()
    {

    }

    public function displayOrderConfirmation()
    {

    }
}