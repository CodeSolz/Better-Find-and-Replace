<?php namespace RealTimeAutoFindReplace\actions;

/**
 * Class: Register custom menu
 * 
 * @package Admin
 * @since 1.0.0
 * @author CodeSolz <customer-support@codesolz.net>
 */

if (!defined('CS_RTAAFR_VERSION')) {
    die();
}


// use RealTimeAutoFindReplace\admin\functions\WooFunctions;
use RealTimeAutoFindReplace\admin\options\Scripts_Settings;
use RealTimeAutoFindReplace\admin\builders\FarAdminPageBuilder;

class RegisterCustomMenu {

    /**
     * Hold pages
     *
     * @var type 
     */
    private $pages;

    /**
     *
     * @var type 
     */
    private $WcFunc;

    /**
     *
     * @var type 
     */
    public $current_screen;

    public function __construct()
    {
        //call wordpress admin menu hook
        add_action('admin_menu', array($this, 'cs_register_far_menu'));
    }

    /**
     * Init current screen
     * 
     * @return type
     */
    public function init_current_screen()
    {
        $this->current_screen = get_current_screen();
        return $this->current_screen;
    }

    /**
     * Create plugins menu
     */
    public function cs_register_far_menu() {
        global $rtafr_menu;
        add_menu_page(
            __('Real time auto find and replace', 'woo-altcoin-payment-gateway'),
            "Auto Find Replace",
            'manage_options',
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            'cs-woo-altcoin-gateway',
            CS_RTAAFR_PLUGIN_ASSET_URI . 'img/icon-24x24.png',
            57
        );

        $rtafr_menu['default_settings'] = add_submenu_page(
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            __('Settings', 'woo-altcoin-payment-gateway'),
            "DB Strings",
            'manage_options',
            'cs-woo-altcoin-gateway-settings',
            array($this, 'load_settings_page')
        );
        $rtafr_menu['add_new_coin'] = add_submenu_page(
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            __('Add New Coin', 'woo-altcoin-payment-gateway'),
            "Replace Before Render",
            'manage_options',
            'cs-woo-altcoin-add-new-coin',
            array($this, 'load_add_new_coin_page')
        );
        $rtafr_menu['all_coins_list'] = add_submenu_page(
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            __('All Coins', 'woo-altcoin-payment-gateway'),
            "All Coins",
            'manage_options',
            'cs-woo-altcoin-all-coins',
            array($this, 'load_all_coins_list_page')
        );
        $rtafr_menu['register_automatic_order'] = add_submenu_page(
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            __('Automatic Order Confirmation Registration', 'woo-altcoin-payment-gateway'),
            "Order Settings",
            'manage_options',
            'cs-woo-altcoin-automatic-order-confirmation-settings',
            array($this, 'load_automatic_order_confirmation_settings_page')
        );
        $rtafr_menu['product_page_options_settings'] = add_submenu_page(
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            __('Product Page Options', 'woo-altcoin-payment-gateway'),
            "Product Page Options",
            'manage_options',
            'cs-woo-altcoin-product-option-settings',
            array($this, 'load_product_page_option_settings')
        );

        $rtafr_menu['checkout_options_settings'] = add_submenu_page(
            CS_RTAAFR_PLUGIN_IDENTIFIER,
            __('Checkout Page', 'woo-altcoin-payment-gateway'),
            "Checkout Page Options",
            'manage_options',
            'cs-woo-altcoin-checkout-option-settings',
            array($this, 'load_checkout_settings_page')
        );

        //load script
        add_action("load-{$rtafr_menu['default_settings']}", array($this, 'register_admin_settings_scripts'));
        add_action("load-{$rtafr_menu['register_automatic_order']}", array($this, 'register_admin_settings_scripts'));
        add_action("load-{$rtafr_menu['add_new_coin']}", array($this, 'register_admin_settings_scripts'));
        add_action("load-{$rtafr_menu['all_coins_list']}", array($this, 'register_admin_settings_scripts'));
        add_action("load-{$rtafr_menu['checkout_options_settings']}", array($this, 'register_admin_settings_scripts'));
        add_action("load-{$rtafr_menu['product_page_options_settings']}", array($this, 'register_admin_settings_scripts'));

        remove_submenu_page(CS_RTAAFR_PLUGIN_IDENTIFIER, CS_RTAAFR_PLUGIN_IDENTIFIER);

        //init pages
        $this->pages = new FarAdminPageBuilder();

        //init gateway settings
        // $this->WcFuncInstance = new WooFunctions();
        // pre_print( $this->pages );
    }

    /**
     * Generate default settings page
     * 
     * @return type
     */
    public function load_settings_page()
    {

        $Default_Settings = $this->pages->DefaultSettings();
        if (is_object($Default_Settings)) {
            echo $Default_Settings->generate_default_settings(array_merge_recursive(array(
                'title' => __('Replacement from Database', 'woo-altcoin-payment-gateway'),
                'sub_title' => __('Instantly & permanently replace string from database tables', 'woo-altcoin-payment-gateway'),
            ), array('gateway_settings' => array() )));
        } else {
            echo $Default_Settings;
        }
    }

    /**
     * Generate checkout settings page
     * 
     * @return type
     */
    public function load_checkout_settings_page()
    {

        $Checkout_Page_Settings = $this->pages->CheckoutPageSettings();
        if (is_object($Checkout_Page_Settings)) {
            echo $Checkout_Page_Settings->generate_checkout_settings(array(
                'title' => __('Checkout Page Options', 'woo-altcoin-payment-gateway'),
                'sub_title' => __('Following options will be applied to the checkout page', 'woo-altcoin-payment-gateway'),
            ));
        } else {
            echo $Checkout_Page_Settings;
        }
    }

    /**
     * Generate product page options settings
     * 
     * @return type
     */
    public function load_product_page_option_settings()
    {

        $Product_PageOptions = $this->pages->ProductPageOptions();
        if (is_object($Product_PageOptions)) {
            echo $Product_PageOptions->generate_product_options_settings(array(
                'title' => __('Product Page Options', 'woo-altcoin-payment-gateway'),
                'sub_title' => __('Following options will be applied to the product\'s page', 'woo-altcoin-payment-gateway'),
            ));
        } else {
            echo $Product_PageOptions;
        }
    }

    /**
     * 
     * @return type
     */
    public function load_automatic_order_confirmation_settings_page()
    {

        $Auto_Order_Settings = $this->pages->AutoOrderSettings();
        if (is_object($Auto_Order_Settings)) {
            echo $Auto_Order_Settings->generate_settings(array(
                'title' => __('Automatic Order Confirmation Settings', 'woo-altcoin-payment-gateway'),
                'sub_title' => __('Please complete your registration to use automatic order confirmation.', 'woo-altcoin-payment-gateway'),
            ));
        } else {
            echo $Auto_Order_Settings;
        }
    }

    /**
     * Load add new coin page
     * 
     * @return type
     */
    public function load_add_new_coin_page()
    {
        $Add_New_Coin = $this->pages->AddNewCoin();
        if (is_object($Add_New_Coin)) {
            echo $Add_New_Coin->add_new_coin(array(
                'title' => __('Add New Coin', 'woo-altcoin-payment-gateway'),
                'sub_title' => __('Please fill up the following information correctly to add new coin to payment method.', 'woo-altcoin-payment-gateway'),
            ));
        } else {
            echo $Add_New_Coin;
        }
    }

    /**
     * load all products page
     */
    public function load_all_coins_list_page()
    {
        $Coin_List = $this->pages->AllCoins();
        if (is_object($Coin_List)) {
            echo $Coin_List->generate_coin_list(array(
                'title' => __('All Coins', 'woo-altcoin-payment-gateway'),
                'sub_title' => __('Following coins has been added to the payment gateway\'s coin list.', 'woo-altcoin-payment-gateway'),
            ));
        } else {
            echo $Coin_List;
        }
    }

    /**
     * load funnel builder scripts
     */
    public function register_admin_settings_scripts()
    {

        //register scripts
        add_action('admin_enqueue_scripts', array($this, 'far_load_settings_scripts'));

        //init current screen
        $this->init_current_screen();

        //load all admin footer script
        add_action('admin_footer', array($this, 'far_load_admin_footer_script'));
    }

    /**
     * Load admin scripts
     */
    public function far_load_settings_scripts($page_id)
    {
        Scripts_Settings::load_admin_settings_scripts($page_id);
    }

    /**
     * load custom scripts on admin footer
     */
    public function far_load_admin_footer_script() {
        Scripts_Settings::load_admin_footer_script($this->current_screen->id);
    }


}
