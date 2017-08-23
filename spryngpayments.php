
<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}
$sppModDir =  _PS_MODULE_DIR_.'spryngpayments/'; To be sure it reads from the right folder
require_once('vendor/autoload.php');
require_once($sppModDir.'helpers/helper.php');
require_once($sppModDir.'helpers/transaction.php');
require_once($sppModDir.'helpers/customer.php');
require_once($sppModDir.'helpers/address.php');
require_once($sppModDir.'helpers/goods.php');
require_once($sppModDir.'helpers/order.php');

/**
 * Class SpryngPayments
 */
class SpryngPayments extends PaymentModule
{
    const CONFIG_KEY_PREFIX = 'SPRYNG_';
    
    public $api = null;
    
    public $gateways = [
        'CREDIT_CARD',
        'IDEAL',
        'PAYPAL',
        'SEPA',
        'KLARNA',
        'SOFORT'
    ];
    public $iDealIssuers = [
        'ABNANL2A' => 'ABN Ambro',
        'ASNBNL21'=> 'ASN Bank',
        'BUNQNL2A' => 'Bunq',
        'FVLBNL22' => 'Van Lanschot Bankiers',
        'INGBNL2A' => 'ING',
        'KNABNL2H' => 'Knab',
        'RABONL2U' => 'Rabobank',
        'RBRBNL21' => 'Regiobank',
        'SNSNML2A' => 'SNS Bank',
        'TRIONL2U' => 'Triodos Bank'
    ];

    /**
     * Instance of the transaction helper
     *
     * @var TransactionHelper
     */
    public $transactionHelper;

    /**
     * Instance of the customer helper
     *
     * @var CustomerHelper
     */
    public $customerHelper;

    /**
     * Instance of the address helper
     *
     * @var AddressHelper
     */
    public $addressHelper;

    /**
     * Instance of the goods helper
     *
     * @var GoodsHelper
     */
    public $goodsHelper;

    /**
     * Instance of the order helper
     *
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var array
     */
    public $configKeys = array();
    
    /**
     * SpryngPayments constructor.
     */
    public function __construct()
    {
        $this->name = 'spryngpayments';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.3';
        $this->author = 'Spryng Payments';
        $this->need_instance = true;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Spryng Payments for Prestashop');
        $this->description = $this->l('Spryng Payments payment gateway for Prestashop.');

        //$this->limited_countries = array('FR'); TODO
        //$this->limited_currencies = array('EUR'); TODO

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        require_once('vendor/autoload.php'); // Load Composer deps

        // Initiate the PHP SDK using defined settings. Uses sandbox by default.
        $this->api = new \SpryngPaymentsApiPhp\Client(
            ((bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'SANDBOX_ENABLED') ? // Use applicable API key for environment
                Configuration::get(self::CONFIG_KEY_PREFIX . 'API_KEY_SANDBOX') :
                Configuration::get(self::CONFIG_KEY_PREFIX . 'API_KEY_LIVE')),
            (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'SANDBOX_ENABLED')
        );

        // Initiate helpers
        $this->transactionHelper = new TransactionHelper($this->api);
        $this->customerHelper = new CustomerHelper($this->api);
        $this->addressHelper = new AddressHelper($this->api);
        $this->goodsHelper = new GoodsHelper($this->api);
        $this->orderHelper = new OrderHelper($this->api);
    }

    /**
     * In Prestashop, the getContent() function returns the HTML for the settings page.
     *
     * @return string
     */
    public function getContent()
    {
        $configHtml = '';

        // Check if the form is submitted
        if (Tools::isSubmit('btnSubmit'))
        {
            $this->processConfigSubmit(); // Save new settings
            $configHtml .= $this->displayConfirmation('Settings updated.'); // Display save confirmation message
        }

        $configHtml .= $this->getConfigurationPanelInfoHtml(); // Load setting page header
        $configHtml .= $this->getConfigForm(); // Load form HTML

        return $configHtml;
    }

    /**
     * Persists user defined preferences to the database
     */
    protected function processConfigSubmit()
    {
        if (Tools::isSubmit('btnSubmit'))
        {
            $postData = Tools::getAllValues(); // Load all the values from the request
            // Load the setting key prefix, meaning that only params formatted as {prefix}_{key} will be processed
            $prefix = self::CONFIG_KEY_PREFIX;

            foreach($postData as $key => $post)
            {
                if (substr($key, 0, strlen($prefix)) == $prefix) // Check for the prefix
                {
                    Configuration::updateValue($key, $post);
                }
            }
        }
    }

    /**
     * Loads the settings page header template
     *
     * @return string
     */
    protected function getConfigurationPanelInfoHtml()
    {
        return $this->display(__FILE__,'config_info.tpl');
    }

    /**
     * Defines the structure of the settings page and returns it as HTML using using the Presta form helper
     *
     * @return string
     */
    protected function getConfigForm()
    {
        $organisations = $this->getOrganisationListForConfigurationForm();
        $accounts = $this->getAccountListForConfigurationForm();
        if (count($accounts) > 0)
        {
            $accountSelectorDisabled = false;
        }
        else
        {
            $accounts[0] = [
                'id_option' => 1,
                'name' => 'Please enter a valid API key first.'
            ];
            $accountSelectorDisabled = true;
        }

        if (count($organisations) > 0)
        {
            $organisationSelectorDisabled = false;
        }
        else
        {
            $organisations[0] = [
                'id_option' => 1,
                'name' => 'Please enter a valid API key first.'
            ];
            $organisationSelectorDisabled = true;
        }

        $fields = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Spryng Payments Configuration',
                    'icon' => 'icon-config'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'API Key Live',
                        'name' => self::CONFIG_KEY_PREFIX.'API_KEY_LIVE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'API Key Sandbox',
                        'name' => self::CONFIG_KEY_PREFIX.'API_KEY_SANDBOX',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Sandbox Mode',
                        'name' => self::CONFIG_KEY_PREFIX.'SANDBOX_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
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
                        'name' => self::CONFIG_KEY_PREFIX.'MERCHANT_REFERENCE',
                        'required' => false,
                    ),
                    
                    array(
                        'type' => 'select',
                        'label' => 'iDEAL Enabled',
                        'name' => self::CONFIG_KEY_PREFIX.'IDEAL_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
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
                        'name' => self::CONFIG_KEY_PREFIX.'IDEAL_TITLE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'iDEAL Description',
                        'name' => self::CONFIG_KEY_PREFIX.'IDEAL_DESCRIPTION',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'iDEAL Organisation',
                        'name' => self::CONFIG_KEY_PREFIX.'IDEAL_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'iDEAL Account',
                        'name' => self::CONFIG_KEY_PREFIX.'IDEAL_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Enabled',
                        'name' => self::CONFIG_KEY_PREFIX.'CC_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
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
                        'name' => self::CONFIG_KEY_PREFIX.'CC_TITLE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'CreditCard Description',
                        'name' => self::CONFIG_KEY_PREFIX.'CC_DESCRIPTION',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Organisation',
                        'name' => self::CONFIG_KEY_PREFIX.'CC_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Account',
                        'name' => self::CONFIG_KEY_PREFIX.'CC_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Enabled',
                        'name' => self::CONFIG_KEY_PREFIX.'PAYPAL_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
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
                        'name' => self::CONFIG_KEY_PREFIX.'PAYPAL_TITLE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'PayPal Description',
                        'name' => self::CONFIG_KEY_PREFIX.'PAYPAL_DESCRIPTION',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Organisation',
                        'name' => self::CONFIG_KEY_PREFIX.'PAYPAL_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Account',
                        'name' => self::CONFIG_KEY_PREFIX.'PAYPAL_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SEPA Enabled',
                        'name' => self::CONFIG_KEY_PREFIX.'SEPA_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'SEPA Title',
                        'name' => self::CONFIG_KEY_PREFIX.'SEPA_TITLE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'SEPA Description',
                        'name' => self::CONFIG_KEY_PREFIX.'SEPA_DESCRIPTION',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SEPA Organisation',
                        'name' => self::CONFIG_KEY_PREFIX.'SEPA_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SEPA Account',
                        'name' => self::CONFIG_KEY_PREFIX.'SEPA_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Klarna Enabled',
                        'name' => self::CONFIG_KEY_PREFIX.'KLARNA_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Klarna Title',
                        'name' => self::CONFIG_KEY_PREFIX.'KLARNA_TITLE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'Klarna Description',
                        'name' => self::CONFIG_KEY_PREFIX.'KLARNA_DESCRIPTION',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Klarna Organisation',
                        'name' => self::CONFIG_KEY_PREFIX.'KLARNA_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Klarna Account',
                        'name' => self::CONFIG_KEY_PREFIX.'KLARNA_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SOFORT Enabled',
                        'name' => self::CONFIG_KEY_PREFIX.'SOFORT_ENABLED',
                        'options' => array(
                            'query' => array(
                                array(
                                    'value' => '1',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'value' => '0',
                                    'name' => 'Disabled'
                                )
                            ),
                            'id' => 'value',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'SOFORT Title',
                        'name' => self::CONFIG_KEY_PREFIX.'SOFORT_TITLE',
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'SOFORT Description',
                        'name' => self::CONFIG_KEY_PREFIX.'SOFORT_DESCRIPTION',
                        'required' => false,
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'SOFORT Project ID',
                        'name' => self::CONFIG_KEY_PREFIX.'SOFORT_PROJECT_ID',
                        'required' => false,
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SOFORT Organisation',
                        'name' => self::CONFIG_KEY_PREFIX.'SOFORT_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SOFORT Account',
                        'name' => self::CONFIG_KEY_PREFIX.'SOFORT_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name'.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getAllConfigurationValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields));
    }
    
    /**
     * Get all config parameters to load into templates
     *
     * @return array
     */
    private function getAllConfigurationValues(){
        $this->getConfigKeys();
        return Configuration::getMultiple($this->configKeys);
    }
    
    /**
     * Get all configkeys at once!
     */
    private function getConfigKeys(){
        $paymentOptions = array('IDEAL','CC','PAYPAL','SEPA','KLARNA','SOFORT');
        $this->configKeys = array(
            self::CONFIG_KEY_PREFIX.'API_KEY_LIVE',
            self::CONFIG_KEY_PREFIX.'API_KEY_SANDBOX',
            self::CONFIG_KEY_PREFIX.'SANDBOX_ENABLED',
            self::CONFIG_KEY_PREFIX.'MERCHANT_REFERENCE',

        );

        foreach($paymentOptions as $paymentOption){
            $this->configKeys[]=self::CONFIG_KEY_PREFIX.$paymentOption.'_ENABLED';
            $this->configKeys[]=self::CONFIG_KEY_PREFIX.$paymentOption.'_TITLE';
            $this->configKeys[]=self::CONFIG_KEY_PREFIX.$paymentOption.'_DESCRIPTION';
            $this->configKeys[]=self::CONFIG_KEY_PREFIX.$paymentOption.'_ORGANISATION';
            if($paymentOption=='SOFORT'){
                $this->configKeys[]= self::CONFIG_KEY_PREFIX.'SOFORT_PROJECT_ID';
            }
            $this->configKeys[]=self::CONFIG_KEY_PREFIX.$paymentOption.'_ACCOUNT';
        }
    }
    
    /**
     * Checks weather API requests can be executed based on settings to prevent exceptions
     *
     * @return bool
     */
    private function canDoRequests()
    {
        // Check if sandbox is enabled
        if ((bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'SANDBOX_ENABLED'))
        {
            $settingKey = 'API_KEY_SANDBOX';
        }
        else
        {
            $settingKey = 'API_KEY_LIVE';
        }

        // If the active API key is empty, no requests can be performed
        $key = Configuration::get(self::CONFIG_KEY_PREFIX . $settingKey);
        if (is_null($key) || $key === '' || empty($key))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * Fetches organisations from the API
     *
     * @return array
     */
    private function getOrganisationListForConfigurationForm()
    {
        // If there is no valid active API key, just return an empty array as fetching is not going to work
        if (!$this->canDoRequests())
        {
            return array();
        }
        try {
            $organisations = $this->api->organisation->getAll(); // Load all orgs
        }
        catch(\SpryngPaymentsApiPhp\Exception\RequestException $requestException)
        {
            return array();
        }

        // Parse the organisations to a format that can be used as selector options by presta
        $options = array();
        foreach($organisations as $organisation)
        {
            $option = array(
                'value' => $organisation->_id,
                'name' => $organisation->name
            );
            array_push($options, $option);
        }

        $organisationOptions = array(
            'query' => $options,
            'id' => 'value',
            'name' => 'name'
        );

        return $organisationOptions;
    }

    /**
     * Fetches accounts from the API
     *
     * @return array
     */
    private function getAccountListForConfigurationForm()
    {
        // If there is no valid active API key, just return an empty array as fetching is not going to work
        if (!$this->canDoRequests())
        {
            return array();
        }
        try {
            $accounts = $this->api->account->getAll();
        }
        catch(\SpryngPaymentsApiPhp\Exception\RequestException $clientException)
        {
            return array();
        }

        // Parse the organisations to a format that can be used as selector options by presta
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

    /**
     * Installs the plugin by creating database table for payments, registering hooks and initializing default configuration.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) // Fail if basic plugin installation procedures fail
        {
            PrestaShopLogger::addLog('Parent installation failed');
            $this->_errors[] = 'An error occurred during the initial installation of Spryng Payments for Prestashop.';
            return false;
        }

        if (!$this->_registerHooks()) // Fail if the controller hooks can't be registered
        {
            PrestaShopLogger::addLog('Registering hooks failed');
            $this->_errors[] = 'An error has occurred during the installation process of Spryng Payments for Prestashop.';
            return false;
        }

        if (!$this->initializeConfiguration()) // Fail if the configuration can't be initialised
        {
            PrestaShopLogger::addLog('Initializing configuration failed.');
            $this->_errors[] = 'Spryng Payments failed to load initial configuration.';
            return false;
        }

        if (!$this->createDatabaseTables()) // Fail if the database can't be initialised
        {
            PrestaShopLogger::addLog('Initializing database failed.');
            $this->_errors[] = 'A database error occurred while trying to initialize Spryng Payments.';
            return false;
        }

        if (!$this->initializeOrderStates()) // Fail if the custom order states can't be created
        {
            PrestaShopLogger::addLog('Initialising Order States failed.');
            $this->_errors[] = 'An error occured while trying to initialise Spryng Order States.';
            return false;
        }

        // All went well : )
        return true;
    }

    /**
     * Saves custom order states to the database. These order states conform to Spryng Payments statuses.
     *
     * @return bool
     */
    private function initializeOrderStates()
    {
        $states = OrderState::getOrderStates(Configuration::get('PS_LANG_DEFAULT'));
        $orderStatus = array(
            array('status'=>'SETTLEMENT_COMPLETED','color'=>'5cb85c','paid'=>'true'),
            array('status'=>'Settlement Requested','color'=>'f0ad4e','paid'=>'false'),
            array('status'=>'Declined','color'=>'#d9534f','paid'=>'false'),
            array('status'=>'Initiated','color'=>'#f0ad4e','paid'=>'false'),
            array('status'=>'Authorized','color'=>'#f0ad4e','paid'=>'false'),
            array('status'=>'Failed','color'=>'#d9534f','paid'=>'false'),
            array('status'=>'Settlement Canceled','color'=>'#d9534f','paid'=>'false'),
            array('status'=>'Settlement Processed','color'=>'#f0ad4e','paid'=>'false'),
            array('status'=>'Settlement Failed','color'=>'#d9534f','paid'=>'false'),
            array('status'=>'Settlement Declined','color'=>'#d9534f','paid'=>'false'),
            array('status'=>'Voided','color'=>'#d9534f','paid'=>'false'),
            array('status'=>'Unknown','color'=>'#d9534f','paid'=>'false'),

        );
        foreach($orderStatus as $orderState){
            $key = string_replace(' ','_',strtoupper($orderState['status']));
            $this->createOrderStatus($orderState['status'], $states, self::CONFIG_KEY_PREFIX . $key, $orderState['color'], $orderState['paid']);
        }

        return true;
    }

    /**
     * Saves a single customer order state to the database
     *
     * @param $name
     * @param $states
     * @param $configName
     * @param $color
     * @param $paid
     */
    private function createOrderStatus($name, $states, $configName, $color, $paid)
    {
        // Check if there is already an existing order state with this name
        foreach($states as $state)
        {
            if ($state['name'] == $name)
            {
                Configuration::updateValue($configName, $state['id_order_state']);
                return;
            }
        }

        $names = array();

        // Set names for different languages
        $state = new OrderState();
        foreach (Language::getLanguages(false) as $language)
        {
            $names[$language['id_lang']] = $name;
        }
        $state->module_name = $this->name; // Save it for this module
        $state->name = $names; // Add names
        $state->send_email = false; // Will an email be sent if the order gets this status?
        $state->invoice = true; // Will an invoice be created for this status?
        $state->color = $color; // Hex of the color the status will have in the backend
        $state->unremovable = true; // Status can't be deleted by store managers as it's system-critical
        $state->hidden = true; // Hide the status for manual assignment
        $state->logable = true; // Will the change be logged
        $state->paid = $paid; // Does this status mean that the order is paid?
        $state->save(); // Persist
        $this->initializeConfigurationValue($configName, $state->id);
    }

    /**
     * Change the status of an order
     *
     * @param $orderId
     * @param $status
     * @return bool|OrderHistory
     */
    public function changeOrderStatus($orderId, $status)
    {
        $statusId = (int) Configuration::get(self::CONFIG_KEY_PREFIX . $status);

        if (is_null($statusId) || empty($statusId) || $statusId === 0)
            return false;

        // Load the order history and add the new status
        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->id_order_state = $statusId;
        $history->changeIdOrderState($statusId, $orderId);
        $history->add();

        // Return the new order history
        return $history;
    }

    /**
     * Uninstalls the plugin by dropping database table, unregistering hooks and deleting default configuration.
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!$this->_unregisterHooks()) // Fail when existing hooks can't be deleted
        {
            $this->_errors[] = 'The uninstallation process of Spryng Payments for Prestashop failed. A hook could not be
            unregistered.';
            return false;
        }

        if (!$this->deleteConfiguration()) // Fail when the configuration can't be deleted from the database
        {
            $this->_errors[] = 'Failed to delete all configuration values.';
            return false;
        }

        $this->dropDatabaseTables(); // Drop the custom database tables
        return true;
    }

    /**
     * Executes queries to drop the custom database tables
     *
     * @return bool
     */
    private function dropDatabaseTables()
    {
        $queries = [
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'spryng_payments_transactions`',
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'spryng_payments_logs`'
        ];

        foreach ($queries as $query)
        {
            if (!Db::getInstance()->execute($query))
                return false;
        }

        // All queries executed fine : )
        return true;
    }

    /**
     * Execute queries to create the custom database tables
     *
     * @return bool
     */
    private function createDatabaseTables()
    {
        // These queries define the custom database tables
        $queries = [
            /**
             * The 'spryng_payments' table holds all metadata related to transactions created using the module.
             *
             * transaction_id => the id of the transaction as it's generated by the Spryng Payments platform
             * cart_id => ID of the prestashop shopping cart for this transaction
             * order_id => When available, the ID of the order this transaction relates to
             * status => the last known status of the payment
             * webhook_key => a key that's added to the query of the webhook to make sure the webhook isn't abused
             */
            sprintf("
                CREATE TABLE IF NOT EXISTS `%s` (
                    `transaction_id` VARCHAR(255) NOT NULL PRIMARY KEY,
                    `payment_method` VARCHAR(255) NOT NULL,
                    `cart_id` INT(64),
                    `order_id` INT(64),
                    `status` VARCHAR(255) NOT NULL,
                    `webhook_key` VARCHAR(100),
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
                _DB_PREFIX_.'spryng_payments'
            ),
            /**
             * The 'spryng_customers' table holds data relating to Spryng Payments 'Customer' objects
             *
             * presta_customer_id => ID of the Prestashop user
             * spryng_customer_id => ID of the Spryng Payments Customer object
             */
            sprintf("
                CREATE TABLE IF NOT EXISTS `%s` (
                    `presta_customer_id` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
                    `spryng_customer_id` VARCHAR(30) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
                _DB_PREFIX_.'spryng_customers'
            )
        ];

        foreach($queries as $query)
        {
            if (!Db::getInstance()->execute($query))
            {
                PrestaShopLogger::addLog(sprintf('Error occured while executing %s', $query)); // Log when a query fails
                $this->_errors[] = sprintf('Could not initialize database. Query: %s.', $query);
                return false;
            }
            else
            {
                PrestaShopLogger::addLog('Executed: %s', $query);
            }
        }

        return true;
    }

    /**
     * Registers controller hooks
     *
     * @return bool
     */
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

    /**
     * De-registers controller hooks
     *
     * @return bool
     */
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

    /**
     * Register a single hook
     *
     * @param $name
     * @param bool $standard
     * @return bool
     */
    protected function _registerHook($name, $standard = true)
    {
        if (!$standard && Hook::getIdByName($name))
        {
            return true;
        }

        return $this->registerHook($name);
    }

    /**
     * Un-register a single hook
     *
     * @param $name
     * @param bool $standard
     * @return bool
     */
    protected function _unregisterHook($name, $standard = true)
    {
        if (!$standard && !Hook::getIdByName($name))
        {
            return true;
        }

        return $this->unregisterHook($name);
    }

    /**
     * Initialise the module configuration with empty values
     *
     * @return bool
     */
    protected function initializeConfiguration()
    {
        return
            // General settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PLUGIN_VERSION', self::VERSION) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'API_KEY_LIVE', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'API_KEY_SANDBOX', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SANDBOX_ENABLED', true) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'MERCHANT_REFERENCE', 'Prestashop Plugin') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PAY_BUTTON_TEXT', 'Pay with Spryng Payments') &&

            // iDEAL settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'IDEAL_ENABLED', false) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'IDEAL_TITLE', 'Spryng Payments - iDEAL') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'IDEAL_DESCRIPTION', 'Pay online via your own bank') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'IDEAL_ORGANISATION', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'IDEAL_ACCOUNT', '') &&

            // Credit Card settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'CC_ENABLED', false) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'CC_TITLE', 'Spryng Payments - CreditCard') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'CC_DESCRIPTION', 'Pay using your own CreditCard') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'CC_ORGANISATION', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'CC_ACCOUNT', '') &&

            // PayPal settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PAYPAL_ENABLED', false) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PAYPAL_TITLE', 'Spryng Payments - PayPal') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PAYPAL_DESCRIPTION', 'Pay safely using PayPal') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PAYPAL_ORGANISATION', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'PAYPAL_ACCOUNT', '') &&

            // SEPA settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SEPA_ENABLED', false) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SEPA_TITLE', 'Spryng Payments - SEPA') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SEPA_DESCRIPTION', 'Pay with European SEPA') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SEPA_ORGANISATION', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SEPA_ACCOUNT', '') &&

            // KLARNA settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'KLARNA_ENABLED', false) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'KLARNA_TITLE', 'Spryng Payments - KLARNA') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'KLARNA_DESCRIPTION', 'Pay with European KLARNA') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'KLARNA_ORGANISATION', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'KLARNA_ACCOUNT', '') &&

            // SOFORT settings
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SOFORT_ENABLED', false) &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SOFORT_TITLE', 'Spryng Payments - SOFORT') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SOFORT_DESCRIPTION', 'Pay with European SOFORT') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SOFORT_PROJECT_ID', 'Your SOFORT Project ID') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SOFORT_ORGANISATION', '') &&
            $this->initializeConfigurationValue(self::CONFIG_KEY_PREFIX.'SOFORT_ACCOUNT', '');
    }


    /**
     * Delete existing module configuration when the module is being un-installed
     *
     * @return bool
     */
    protected function deleteConfiguration()
    {
        
        return
            // General settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PLUGIN_VERSION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'API_KEY_LIVE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'API_KEY_SANDBOX') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SANDBOX_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'MERCHANT_REFERENCE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PAY_BUTTON_TEXT') &&

            // iDEAL settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'IDEAL_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'IDEAL_TITLE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'IDEAL_DESCRIPTION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'IDEAL_ORGANISATION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'IDEAL_ACCOUNT') &&

            // Credit Card settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'CC_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'CC_TITLE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'CC_DESCRIPTION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'CC_ORGANISATION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'CC_ACCOUNT') &&

            // PayPal settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PAYPAL_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PAYPAL_TITLE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PAYPAL_DESCRIPTION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PAYPAL_ORGANISATION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'PAYPAL_ACCOUNT') &&

            // SEPA settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SEPA_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SEPA_TITLE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SEPA_DESCRIPTION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SEPA_ORGANISATION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SEPA_ACCOUNT') &&

            // KLARNA settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'KLARNA_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'KLARNA_TITLE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'KLARNA_DESCRIPTION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'KLARNA_ORGANISATION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'KLARNA_ACCOUNT') &&

            // SOFORT settings
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SOFORT_ENABLED') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SOFORT_TITLE') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SOFORT_DESCRIPTION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SOFORT_PROJECT_ID') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SOFORT_ORGANISATION') &&
            Configuration::deleteByName(self::CONFIG_KEY_PREFIX.'SOFORT_ACCOUNT');
    }

    /**
     * Initialise a single configuration value
     *
     * @param $key
     * @param $value
     * @return bool
     */
    protected function initializeConfigurationValue($key, $value)
    {
        return Configuration::updateValue($key, (Configuration::get($key) !== false) ? Configuration::get($key) :
            $value);
    }


    /**
     * In Prestashop, the hookDisplayPayment() function is called when the payment page is rendered.
     * It returns a template file which will be rendered.
     *
     * @return string|void
     */
    public function hookDisplayPayment()
    {
        if (!$this->active)
            return;

        $configuration = array(
            'ideal' => array(
                'enabled' => (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'IDEAL_ENABLED'),
                'title' => Configuration::get(self::CONFIG_KEY_PREFIX . 'IDEAL_TITLE'),
                'description' => Configuration::get(self::CONFIG_KEY_PREFIX . 'IDEAL_DESCRIPTION'),
                'organisation' => Configuration::get(self::CONFIG_KEY_PREFIX . 'IDEAL_ORGANISATION'),
                'account' => Configuration::get(self::CONFIG_KEY_PREFIX . 'IDEAL_ACCOUNT'),
                'issuers' => $this->iDealIssuers,
                'toggle' => true
            ),
            'creditcard' => array(
                'enabled' => (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'CC_ENABLED'),
                'title' => Configuration::get(self::CONFIG_KEY_PREFIX . 'CC_TITLE'),
                'description' => Configuration::get(self::CONFIG_KEY_PREFIX . 'CC_DESCRIPTION'),
                'organisation' => Configuration::get(self::CONFIG_KEY_PREFIX . 'CC_ORGANISATION'),
                'account' => Configuration::get(self::CONFIG_KEY_PREFIX . 'CC_ACCOUNT'),
                'toggle' => true
            ),
            'paypal' => array(
                'enabled' => (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'PAYPAL_ENABLED'),
                'title' => Configuration::get(self::CONFIG_KEY_PREFIX . 'PAYPAL_TITLE'),
                'description' => Configuration::get(self::CONFIG_KEY_PREFIX . 'PAYPAL_DESCRIPTION'),
                'organisation' => Configuration::get(self::CONFIG_KEY_PREFIX . 'PAYPAL_ORGANISATION'),
                'account' => Configuration::get(self::CONFIG_KEY_PREFIX . 'PAYPAL_ACCOUNT'),
                'toggle' => false
            ),
            'sepa' => array(
                'enabled' => (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'SEPA_ENABLED'),
                'title' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SEPA_TITLE'),
                'description' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SEPA_DESCRIPTION'),
                'organisation' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SEPA_ORGANISATION'),
                'account' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SEPA_ACCOUNT'),
                'toggle' => false
            ),
            'klarna' => array(
                'enabled' => (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'KLARNA_ENABLED'),
                'title' => Configuration::get(self::CONFIG_KEY_PREFIX . 'KLARNA_TITLE'),
                'description' => Configuration::get(self::CONFIG_KEY_PREFIX . 'KLARNA_DESCRIPTION'),
                'organisation' => Configuration::get(self::CONFIG_KEY_PREFIX . 'KLARNA_ORGANISATION'),
                'account' => Configuration::get(self::CONFIG_KEY_PREFIX . 'KLARNA_ACCOUNT'),
                'toggle' => true,
                'pclasses' => $this->api->Klarna->getPClasses(Configuration::get(
                    self::CONFIG_KEY_PREFIX . 'KLARNA_ACCOUNT'))
            ),
            'sofort' => array(
                'enabled' => (bool) Configuration::get(self::CONFIG_KEY_PREFIX . 'SOFORT_ENABLED'),
                'title' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SOFORT_TITLE'),
                'description' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SOFORT_DESCRIPTION'),
                'organisation' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SOFORT_ORGANISATION'),
                'account' => Configuration::get(self::CONFIG_KEY_PREFIX . 'SOFORT_ACCOUNT')
            )
        );

        $this->smarty->assign(array(
            'sandboxEnabled' => (bool) Configuration::get('SPRYNG_SANDBOX_ENABLED'),
            'configuration' => $configuration
        ));

        return $this->display(__FILE__, 'payment.tpl');
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
