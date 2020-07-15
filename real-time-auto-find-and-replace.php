<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Real Time Auto Find and Replace
 * Plugin URI:        https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/
 * Description:       The plugin automatically find the specific words and replace by your own. you can setup your own rules for find and replace. It will execute before rendering page in browser's as well as background calls by any other social plugins.
 * Version:           1.0.2
 * Author:            CodeSolz.net
 * Author URI:        https://www.codesolz.net
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl.txt
 * Domain Path:       /languages
 * Text Domain:       real-time-auto-find-and-replace
 * Requires PHP: 7.0
 * Requires At Least: 4.0
 * Tested Up To: 5.4
 */


if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if (!class_exists('Real_Time_Auto_Find_And_Replace')) {

    class Real_Time_Auto_Find_And_Replace {

        /**
         * Hold actions hooks
         *
         * @var array
         */
        private static $rtaafr_hooks = [];

        /**
         * Hold version
         *
         * @var String
         */
        private static $version = '1.0.0';

        /**
         * Hold version
         *
         * @var String
         */
        private static $db_version = '1.0.0';

        /**
         * Hold nameSpace
         *
         * @var string
         */
        private static $namespace = 'RealTimeAutoFindReplace';


        public function __construct() {


            //load plugins constant
            self::set_constants();

            //load core files
            self::load_core_framework();

            //load init
            self::load_action_files();

            /** Called during the plugin activation */
            self::on_activate();

            /**load textdomain */
            add_action('plugins_loaded', array(__CLASS__, 'init_textdomain'), 15);
        }

        /**
         * Set constant data
         */
        private static function set_constants()
        {
            $constants = array(
                'CS_RTAAFR_VERSION' => self::$version, //Define current version
                'CS_RTAAFR_DB_VERSION' => self::$db_version, //Define current db version
                'CS_RTAAFR_BASE_DIR_PATH' => untrailingslashit(plugin_dir_path(__FILE__)) . '/', //Hold plugins base dir path
                'CS_RTAAFR_PLUGIN_ASSET_URI' => plugin_dir_url(__FILE__) . 'assets/', //Define asset uri
                'CS_RTAAFR_PLUGIN_LIB_URI' => plugin_dir_url(__FILE__) . 'lib/', //Library uri
                'CS_RTAAFR_PLUGIN_IDENTIFIER' => plugin_basename(__FILE__), //plugins identifier - base dir
                'CS_RTAAFR_PLUGIN_NAME' => 'Real Time Auto Find And Replace', //Plugin name
            );

            foreach ($constants as $name => $value) {
                self::set_constant($name, $value);
            }

            return true;
        }

        /**
         * Set constant
         *
         * @param type $name
         * @param type $value
         * @return boolean
         */
        private static function set_constant($name, $value)
        {
            if (!defined($name)) {
                define($name, $value);
            }
            return true;
        }


        /**
         * load core framework
         */
        private static function load_core_framework()
        {
            require_once CS_RTAAFR_BASE_DIR_PATH . 'vendor/autoload.php';
        }

        /**
         * Load Action Files
         *
         * @return classes
         */
        private static function load_action_files()
        {
            
            foreach (glob(CS_RTAAFR_BASE_DIR_PATH . "core/actions/*.php") as $cs_action_file) {
                $class_name = basename($cs_action_file, '.php');
                $class = self::$namespace . '\\actions\\' . $class_name;
                if (class_exists($class) && !array_key_exists($class, self::$rtaafr_hooks)) { //check class doesn't load multiple time
                    self::$rtaafr_hooks[$class] = new $class();
                }
            }

            // pre_print( self::$rtaafr_hooks );

        }

        /**
         * init activation hook
         */
        private static function on_activate() {

            //load config
            // require_once CS_RTAAFR_BASE_DIR_PATH . 'core/install/rtaafr_config.php';

            //register hook
            // register_activation_hook(__FILE__, array(self::$namespace . '\\install\\Activate', 'on_activate'));
            return true;
        }

        /**
         * init textdomain
         */
        public static function init_textdomain()
        {
            load_plugin_textdomain('woo-altcoin-payment-gateway', false, CS_RTAAFR_BASE_DIR_PATH . '/languages/');
        }


    
    }

    global $RTAFAF;
    $RTAFAF = new Real_Time_Auto_Find_And_Replace();
}
