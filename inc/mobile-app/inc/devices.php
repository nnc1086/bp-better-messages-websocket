<?php
defined('ABSPATH') || exit;

if ( ! class_exists('Better_Messages_Mobile_App_Devices') ) {
    class Better_Messages_Mobile_App_Devices
    {
        public $settings;

        public $defaults;

        public static function instance(): ?Better_Messages_Mobile_App_Devices
        {
            // Store the instance locally to avoid private static replication
            static $instance = null;
            // Only run these methods if they haven't been run previously

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Devices();
            }

            // Always return the instance
            return $instance;
            // The last metroid is in captivity. The galaxy is at peace.
        }

        public function __construct()
        {
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
        }

        public function rest_api_init()
        {
            register_rest_route('better-messages/v1/admin/app', '/getDevices', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_devices'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));
        }

        public function user_is_admin(): bool
        {
            return current_user_can('manage_options');
        }

        public function get_devices( WP_Rest_Request $request )
        {
            global $wpdb;

            $table = Better_Messages()->mobile_app->devices_table;

            $devices = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );

            if( $devices ){
                foreach( $devices as $key => $device ){
                    $devices[$key]['user'] = Better_Messages()->functions->rest_user_item($device['user_id']);
                    $devices[$key]['push_token'] = ! empty($device['push_token']);
                    $devices[$key]['push_token_voip'] = ! empty($device['push_token_voip']);
                }
            }

            return $devices;
        }
    }
}
