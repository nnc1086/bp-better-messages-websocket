<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App' ) ):

    class Better_Messages_Mobile_App
    {
        /* @Better_Messages_Mobile_App_Database */
        public $database;

        /* @Better_Messages_Mobile_App_Options */
        public $settings;

        /* @Better_Messages_Mobile_App_Admin */
        public $admin;

        /* @Better_Messages_Mobile_App_Hooks */
        public $hooks;

        /* @Better_Messages_Mobile_App_Functions */
        public $functions;

        /* @Better_Messages_Mobile_App_Auth */
        public $auth;

        /* @Better_Messages_Mobile_App_JWT */
        public $jwt;

        /* @Better_Messages_Mobile_App_IOS */
        public $ios;

        /* @Better_Messages_Mobile_App_Android */
        public $android;

        /* @Better_Messages_Mobile_App_Pushs */
        public $pushs;

        /* @Better_Messages_Mobile_App_Builds */
        public $builds;

        /* @Better_Messages_Mobile_App_Scripts */
        public $scripts;

        public $path;

        public $devices_table;

        public $builds_table;

        public $cache_group = 'bm_messages_mobile';

        public $is_active = false;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App();
            }

            return $instance;
        }

        public function __construct(){

            global $wpdb;

            $prefix = apply_filters( 'bp_core_get_table_prefix', $wpdb->base_prefix );

            $this->path = trailingslashit(dirname(__FILE__)) ;

            $this->devices_table = $prefix . 'bm_app_devices';

            $this->builds_table = $prefix . 'bm_app_builds';

            $this->is_active = true;

            require_once($this->path . 'inc/database.php');
            $this->database = Better_Messages_Mobile_App_Database::instance();

            require_once($this->path . 'inc/options.php');
            $this->settings = Better_Messages_Mobile_App_Options();

            require_once($this->path . 'inc/hooks.php');
            $this->hooks = Better_Messages_Mobile_App_Hooks();

            require_once($this->path . 'admin/admin.php');
            $this->admin = Better_Messages_Mobile_App_Admin();

            require_once($this->path . 'inc/jwt.php');
            $this->jwt = Better_Messages_Mobile_App_JWT();

            require_once($this->path . 'inc/functions.php');
            $this->functions = Better_Messages_Mobile_App_Functions();

            require_once($this->path . 'inc/ios.php');
            $this->ios = Better_Messages_Mobile_App_IOS();

            require_once($this->path . 'inc/android.php');
            $this->android = Better_Messages_Mobile_App_Android::instance();

            require_once($this->path . 'inc/builds.php');
            $this->builds = Better_Messages_Mobile_App_Builds::instance();

            require_once($this->path . 'inc/devices.php');
            $this->builds = Better_Messages_Mobile_App_Devices::instance();

            require_once ($this->path . 'inc/scripts.php');
            $this->scripts = Better_Messages_Mobile_App_Scripts();

            require_once($this->path . 'inc/auth.php');
            $this->auth = Better_Messages_Mobile_App_Auth();

            require_once ($this->path . 'inc/pushs.php');
            $this->pushs = Better_Messages_Mobile_App_Pushs();
        }
    }

endif;

function Better_Messages_Mobile_App()
{
    return Better_Messages_Mobile_App::instance();
}
