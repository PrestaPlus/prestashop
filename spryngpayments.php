
<?php

if (!defined('_PS_VERSION_'))
{
    die('No direct script access');
}

require_once('vendor/autoload.php');
require_once('helpers/helper.php');
require_once('helpers/transaction.php');
require_once('helpers/customer.php');
require_once('helpers/address.php');
require_once('helpers/goods.php');
require_once('helpers/order.php');

/**
 * Class SpryngPayments
 */
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
     * SpryngPayments constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // Initial plugin name and description
        $this->displayName = 'Spryng Payments for Prestashop';
        $this->description = 'Spryng Payments payment gateway for Prestashop.';

        require_once('vendor/autoload.php'); // Load Composer deps

        // Initiate the PHP SDK using defined settings. Uses sandbox by default.
        $this->api = new \SpryngPaymentsApiPhp\Client(
            ((bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SANDBOX_ENABLED') ? // Use applicable API key for environment
                $this->getConfigurationValue($this->getConfigKeyPrefix() . 'API_KEY_SANDBOX') :
                $this->getConfigurationValue($this->getConfigKeyPrefix() . 'API_KEY_LIVE')),
            (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SANDBOX_ENABLED')
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
            $prefix = $this->getConfigKeyPrefix();

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
        $organisations = $this->getOrganisationListForConfigurationForm(); // Load orgs from the API
        $accounts = $this->getAccountListForConfigurationForm(); // Load accounts from the API

        // If no accounts were found, it could be because no API key is saved.
        // Disable the selector and display informative message
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

        // If no organisations were found, it could be because no API key is saved.
        // Disable the selector and display informative message
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

        // Define form as an array, which can be converted to HTML by the Presta form helper
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
                        'name' => $this->getConfigKeyPrefix().'API_KEY_LIVE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'API_KEY_LIVE')
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'API Key Sandbox',
                        'name' => $this->getConfigKeyPrefix().'API_KEY_SANDBOX',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'API_KEY_SANDBOX')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Sandbox Mode',
                        'name' => $this->getConfigKeyPrefix().'SANDBOX_ENABLED',
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
                        'label' => 'iDEAL Organisation',
                        'name' => $this->getConfigKeyPrefix().'IDEAL_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'iDEAL Account',
                        'name' => $this->getConfigKeyPrefix().'IDEAL_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Enabled',
                        'name' => $this->getConfigKeyPrefix().'CC_ENABLED',
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
                        'label' => 'CreditCard Organisation',
                        'name' => $this->getConfigKeyPrefix().'CC_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'CreditCard Account',
                        'name' => $this->getConfigKeyPrefix().'CC_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Enabled',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_ENABLED',
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
                        'label' => 'PayPal Organisation',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'PayPal Account',
                        'name' => $this->getConfigKeyPrefix().'PAYPAL_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SEPA Enabled',
                        'name' => $this->getConfigKeyPrefix().'SEPA_ENABLED',
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
                        'name' => $this->getConfigKeyPrefix().'SEPA_TITLE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'SEPA_TITLE')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'SEPA Description',
                        'name' => $this->getConfigKeyPrefix().'SEPA_DESCRIPTION',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'SEPA_DESCRIPTION')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SEPA Organisation',
                        'name' => $this->getConfigKeyPrefix().'SEPA_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SEPA Account',
                        'name' => $this->getConfigKeyPrefix().'SEPA_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Klarna Enabled',
                        'name' => $this->getConfigKeyPrefix().'KLARNA_ENABLED',
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
                        'name' => $this->getConfigKeyPrefix().'KLARNA_TITLE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'KLARNA_TITLE')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'Klarna Description',
                        'name' => $this->getConfigKeyPrefix().'KLARNA_DESCRIPTION',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'KLARNA_DESCRIPTION')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Klarna Organisation',
                        'name' => $this->getConfigKeyPrefix().'KLARNA_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'Klarna Account',
                        'name' => $this->getConfigKeyPrefix().'KLARNA_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SOFORT Enabled',
                        'name' => $this->getConfigKeyPrefix().'SOFORT_ENABLED',
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
                        'name' => $this->getConfigKeyPrefix().'SOFORT_TITLE',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'SOFORT_TITLE')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'SOFORT Description',
                        'name' => $this->getConfigKeyPrefix().'SOFORT_DESCRIPTION',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'SOFORT_DESCRIPTION')
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'SOFORT Project ID',
                        'name' => $this->getConfigKeyPrefix().'SOFORT_PROJECT_ID',
                        'required' => false,
                        'value' => $this->getConfigurationValue($this->getConfigKeyPrefix().'SOFORT_PROJECT_ID')
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SOFORT Organisation',
                        'name' => $this->getConfigKeyPrefix().'SOFORT_ORGANISATION',
                        'disabled' => $organisationSelectorDisabled,
                        'options' => $organisations
                    ),
                    array(
                        'type' => 'select',
                        'label' => 'SOFORT Account',
                        'name' => $this->getConfigKeyPrefix().'SOFORT_ACCOUNT',
                        'disabled' => $accountSelectorDisabled,
                        'options' => $accounts
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            )
        );

        // Use the presta form helper to convert the form array to HTML
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
    private function getAllConfigurationValues()
    {
        $prefix = $this->getConfigKeyPrefix();
        return array(
            $prefix . 'API_KEY_LIVE' => Tools::getValue($prefix . 'API_KEY_LIVE', Configuration::get($prefix . 'API_KEY_LIVE')),
            $prefix . 'API_KEY_SANDBOX' => Tools::getValue($prefix . 'API_KEY_SANDBOX', Configuration::get($prefix . 'API_KEY_SANDBOX')),
            $prefix . 'SANDBOX_ENABLED' => Tools::getValue($prefix . 'SANDBOX_ENABLED', Configuration::get($prefix . 'SANDBOX_ENABLED')),
            $prefix . 'MERCHANT_REFERENCE' => Tools::getValue($prefix . 'MERCHANT_REFERENCE', Configuration::get($prefix . 'MERCHANT_REFERENCE')),
            $prefix . 'CC_ORGANISATION' => Tools::getValue($prefix . 'CC_ORGANISATION', Configuration::get($prefix . 'CC_ORGANISATION')),
            $prefix . 'CC_ACCOUNT' => Tools::getValue($prefix . 'CC_ACCOUNT', Configuration::get($prefix . 'CC_ACCOUNT')),
            $prefix . 'CC_DESCRIPTION' => Tools::getValue($prefix . 'CC_DESCRIPTION', Configuration::get($prefix . 'CC_DESCRIPTION')),
            $prefix . 'CC_ENABLED' => Tools::getValue($prefix . 'CC_ENABLED', Configuration::get($prefix . 'CC_ENABLED')),
            $prefix . 'CC_TITLE' => Tools::getValue($prefix . 'CC_TITLE', Configuration::get($prefix . 'CC_TITLE')),
            $prefix . 'IDEAL_ORGANISATION' => Tools::getValue($prefix . 'IDEAL_ORGANISATION', Configuration::get($prefix . 'IDEAL_ORGANISATION')),
            $prefix . 'IDEAL_ACCOUNT' => Tools::getValue($prefix . 'IDEAL_ACCOUNT', Configuration::get($prefix . 'IDEAL_ACCOUNT')),
            $prefix . 'IDEAL_ENABLED' => Tools::getValue($prefix . 'IDEAL_ENABLED', Configuration::get($prefix . 'IDEAL_ENABLED')),
            $prefix . 'IDEAL_TITLE' => Tools::getValue($prefix . 'IDEAL_TITLE', Configuration::get($prefix . 'IDEAL_TITLE')),
            $prefix . 'IDEAL_DESCRIPTION' => Tools::getValue($prefix . 'IDEAL_DESCRIPTION', Configuration::get($prefix . 'IDEAL_DESCRIPTION')),
            $prefix . 'PAYPAL_ENABLED' => Tools::getValue($prefix . 'PAYPAL_ENABLED', Configuration::get($prefix . 'PAYPAL_ENABLED')),
            $prefix . 'PAYPAL_ORGANISATION' => Tools::getValue($prefix . 'PAYPAL_ORGANISATION', Configuration::get($prefix . 'PAYPAL_ORGANISATION')),
            $prefix . 'PAYPAL_ACCOUNT' => Tools::getValue($prefix . 'PAYPAL_ACCOUNT', Configuration::get($prefix . 'PAYPAL_ACCOUNT')),
            $prefix . 'PAYPAL_TITLE' => Tools::getValue($prefix . 'PAYPAL_TITLE', Configuration::get($prefix . 'PAYPAL_TITLE')),
            $prefix . 'PAYPAL_DESCRIPTION' => Tools::getValue($prefix . 'PAYPAL_DESCRIPTION', Configuration::get($prefix . 'PAYPAL_DESCRIPTION')),
            $prefix . 'SEPA_ENABLED' => Tools::getValue($prefix . 'SEPA_ENABLED', Configuration::get($prefix . 'SEPA_ENABLED')),
            $prefix . 'SEPA_ORGANISATION' => Tools::getValue($prefix . 'SEPA_ORGANISATION', Configuration::get($prefix . 'SEPA_ORGANISATION')),
            $prefix . 'SEPA_ACCOUNT' => Tools::getValue($prefix . 'SEPA_ACCOUNT', Configuration::get($prefix . 'SEPA_ACCOUNT')),
            $prefix . 'SEPA_TITLE' => Tools::getValue($prefix . 'SEPA_TITLE', Configuration::get($prefix . 'SEPA_TITLE')),
            $prefix . 'SEPA_DESCRIPTION' => Tools::getValue($prefix . 'SEPA_DESCRIPTION', Configuration::get($prefix . 'SEPA_DESCRIPTION')),
            $prefix . 'KLARNA_ENABLED' => Tools::getValue($prefix . 'KLARNA_ENABLED', Configuration::get($prefix . 'KLARNA_ENABLED')),
            $prefix . 'KLARNA_ORGANISATION' => Tools::getValue($prefix . 'KLARNA_ORGANISATION', Configuration::get($prefix . 'KLARNA_ORGANISATION')),
            $prefix . 'KLARNA_ACCOUNT' => Tools::getValue($prefix . 'KLARNA_ACCOUNT', Configuration::get($prefix . 'KLARNA_ACCOUNT')),
            $prefix . 'KLARNA_TITLE' => Tools::getValue($prefix . 'KLARNA_TITLE', Configuration::get($prefix . 'KLARNA_TITLE')),
            $prefix . 'KLARNA_DESCRIPTION' => Tools::getValue($prefix . 'KLARNA_DESCRIPTION', Configuration::get($prefix . 'KLARNA_DESCRIPTION')),
            $prefix . 'SOFORT_ENABLED' => Tools::getValue($prefix . 'SOFORT_ENABLED', Configuration::get($prefix . 'SOFORT_ENABLED')),
            $prefix . 'SOFORT_ORGANISATION' => Tools::getValue($prefix . 'SOFORT_ORGANISATION', Configuration::get($prefix . 'SOFORT_ORGANISATION')),
            $prefix . 'SOFORT_ACCOUNT' => Tools::getValue($prefix . 'SOFORT_ACCOUNT', Configuration::get($prefix . 'SOFORT_ACCOUNT')),
            $prefix . 'SOFORT_TITLE' => Tools::getValue($prefix . 'SOFORT_TITLE', Configuration::get($prefix . 'SOFORT_TITLE')),
            $prefix . 'SOFORT_DESCRIPTION' => Tools::getValue($prefix . 'SOFORT_DESCRIPTION', Configuration::get($prefix . 'SOFORT_DESCRIPTION')),
            $prefix . 'SOFORT_PROJECT_ID' => Tools::getValue($prefix . 'SOFORT_PROJECT_ID', Configuration::get($prefix . 'SOFORT_PROJECT_ID')),
        );
    }

    /**
     * Checks weather API requests can be executed based on settings to prevent exceptions
     *
     * @return bool
     */
    private function canDoRequests()
    {
        // Check if sandbox is enabled
        if ((bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SANDBOX_ENABLED'))
        {
            $settingKey = 'API_KEY_SANDBOX';
        }
        else
        {
            $settingKey = 'API_KEY_LIVE';
        }

        // If the active API key is empty, no requests can be performed
        $key = $this->getConfigurationValue($this->getConfigKeyPrefix() . $settingKey);
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
        $this->createOrderStatus('Settlement Completed', $states, $this->getConfigKeyPrefix() .'SETTLEMENT_COMPLETED', '#5cb85c', true);
        $this->createOrderStatus('Settlement Requested', $states, $this->getConfigKeyPrefix() .'SETTLEMENT_REQUESTED', '#f0ad4e', false);
        $this->createOrderStatus('Declined', $states, $this->getConfigKeyPrefix() .'DECLINED', '#d9534f', false);
        $this->createOrderStatus('Initiated', $states, $this->getConfigKeyPrefix() .'INITIATED', '#f0ad4e', false);
        $this->createOrderStatus('Authorized', $states, $this->getConfigKeyPrefix() .'AUTHORIZED', '#f0ad4e', false);
        $this->createOrderStatus('Failed', $states, $this->getConfigKeyPrefix() .'FAILED', '#d9534f', false);
        $this->createOrderStatus('Settlement Canceled', $states, $this->getConfigKeyPrefix() .'SETTLEMENT_CANCELED', '#d9534f', false);
        $this->createOrderStatus('Settlement Processed', $states, $this->getConfigKeyPrefix() .'SETTLEMENT_PROCESSED', '#f0ad4e', false);
        $this->createOrderStatus('Settlement Failed', $states, $this->getConfigKeyPrefix() .'SETTLEMENT_FAILED', '#d9534f', false);
        $this->createOrderStatus('Settlement Declined', $states, $this->getConfigKeyPrefix() .'SETTLEMENT_DECLINED', '#d9534f', false);
        $this->createOrderStatus('Voided', $states, $this->getConfigKeyPrefix() .'VOIDED', '#d9534f', false);
        $this->createOrderStatus('Unknown', $states, $this->getConfigKeyPrefix() .'UNKNOWN', '#f0ad4e', false);

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
        $statusId = (int) $this->getConfigurationValue($this->getConfigKeyPrefix() . $status);

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
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PLUGIN_VERSION', $this->getVersion()) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'API_KEY_LIVE', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'API_KEY_SANDBOX', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SANDBOX_ENABLED', true) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'MERCHANT_REFERENCE', 'Prestashop Plugin') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAY_BUTTON_TEXT', 'Pay with Spryng Payments') &&

            // iDEAL settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_TITLE', 'Spryng Payments - iDEAL') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_DESCRIPTION', 'Pay online via your own bank') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ORGANISATION', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ACCOUNT', '') &&

            // Credit Card settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_TITLE', 'Spryng Payments - CreditCard') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_DESCRIPTION', 'Pay using your own CreditCard') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_ORGANISATION', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'CC_ACCOUNT', '') &&

            // PayPal settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_TITLE', 'Spryng Payments - PayPal') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_DESCRIPTION', 'Pay safely using PayPal') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ORGANISATION', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ACCOUNT', '') &&

            // SEPA settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SEPA_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SEPA_TITLE', 'Spryng Payments - SEPA') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SEPA_DESCRIPTION', 'Pay with European SEPA') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SEPA_ORGANISATION', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SEPA_ACCOUNT', '') &&

            // KLARNA settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'KLARNA_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'KLARNA_TITLE', 'Spryng Payments - KLARNA') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'KLARNA_DESCRIPTION', 'Pay with European KLARNA') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'KLARNA_ORGANISATION', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'KLARNA_ACCOUNT', '') &&

            // SOFORT settings
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SOFORT_ENABLED', false) &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SOFORT_TITLE', 'Spryng Payments - SOFORT') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SOFORT_DESCRIPTION', 'Pay with European SOFORT') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SOFORT_PROJECT_ID', 'Your SOFORT Project ID') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SOFORT_ORGANISATION', '') &&
            $this->initializeConfigurationValue($this->getConfigKeyPrefix().'SOFORT_ACCOUNT', '');
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
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PLUGIN_VERSION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'API_KEY_LIVE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'API_KEY_SANDBOX') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SANDBOX_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'MERCHANT_REFERENCE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAY_BUTTON_TEXT') &&

            // iDEAL settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ORGANISATION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'IDEAL_ACCOUNT') &&

            // Credit Card settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_ORGANISATION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'CC_ACCOUNT') &&

            // PayPal settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ORGANISATION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'PAYPAL_ACCOUNT') &&

            // SEPA settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SEPA_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SEPA_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SEPA_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SEPA_ORGANISATION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SEPA_ACCOUNT') &&

            // KLARNA settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'KLARNA_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'KLARNA_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'KLARNA_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'KLARNA_ORGANISATION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'KLARNA_ACCOUNT') &&

            // SOFORT settings
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SOFORT_ENABLED') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SOFORT_TITLE') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SOFORT_DESCRIPTION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SOFORT_PROJECT_ID') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SOFORT_ORGANISATION') &&
            $this->deleteConfigurationValue($this->getConfigKeyPrefix().'SOFORT_ACCOUNT');
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
     * Delete a single configuration value
     *
     * @param $key
     * @return bool
     */
    protected function deleteConfigurationValue($key)
    {
        return Configuration::deleteByName($key);
    }

    /**
     * Get the value of a configuration parameter
     *
     * @param $key
     * @return string
     */
    public function getConfigurationValue($key)
    {
        return Configuration::get($key);
    }

    /**
     * Get the current version of the module
     *
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * Get the prefix of module configuration parameters
     *
     * @return string
     */
    public function getConfigKeyPrefix()
    {
        return self::CONFIG_KEY_PREFIX;
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
                'enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'IDEAL_ENABLED'),
                'title' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'IDEAL_TITLE'),
                'description' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'IDEAL_DESCRIPTION'),
                'organisation' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'IDEAL_ORGANISATION'),
                'account' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'IDEAL_ACCOUNT'),
                'issuers' => $this->iDealIssuers,
                'toggle' => 1
            ),
            'creditcard' => array(
                'enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'CC_ENABLED'),
                'title' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'CC_TITLE'),
                'description' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'CC_DESCRIPTION'),
                'organisation' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'CC_ORGANISATION'),
                'account' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'CC_ACCOUNT'),
                'toggle' => 1
            ),
            'paypal' => array(
                'enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'PAYPAL_ENABLED'),
                'title' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'PAYPAL_TITLE'),
                'description' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'PAYPAL_DESCRIPTION'),
                'organisation' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'PAYPAL_ORGANISATION'),
                'account' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'PAYPAL_ACCOUNT'),
                'toggle' => 0
            ),
            'sepa' => array(
                'enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SEPA_ENABLED'),
                'title' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SEPA_TITLE'),
                'description' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SEPA_DESCRIPTION'),
                'organisation' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SEPA_ORGANISATION'),
                'account' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SEPA_ACCOUNT'),
                'toggle' => 0
            ),
            'klarna' => array(
                'enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'KLARNA_ENABLED'),
                'title' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'KLARNA_TITLE'),
                'description' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'KLARNA_DESCRIPTION'),
                'organisation' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'KLARNA_ORGANISATION'),
                'account' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'KLARNA_ACCOUNT'),
                'toggle' => 1,
                'pclasses' => $this->api->Klarna->getPClasses($this->getConfigurationValue(
                    $this->getConfigKeyPrefix() . 'KLARNA_ACCOUNT'))
            ),
            'sofort' => array(
                'enabled' => (bool) $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SOFORT_ENABLED'),
                'title' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SOFORT_TITLE'),
                'description' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SOFORT_DESCRIPTION'),
                'organisation' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SOFORT_ORGANISATION'),
                'account' => $this->getConfigurationValue($this->getConfigKeyPrefix() . 'SOFORT_ACCOUNT'),
                'toggle' => 0
            )
        );

        // Assign payment method information to the template
        $this->smarty->assign(array(
            'sandboxEnabled' => (bool) $this->getConfigurationValue('SPRYNG_SANDBOX_ENABLED'),
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
