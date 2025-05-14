<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_Scripts' ) ):

    class Better_Messages_Mobile_App_Scripts
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Scripts();
            }

            return $instance;
        }

        public function __construct(){
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1/app', '/syncScripts', array(
                'methods' => 'GET',
                'callback' => array( $this, 'sync_scripts' ),
                'permission_callback' => function ( WP_REST_Request $request ) {
                    return true;
                }
            ) );

            register_rest_route( 'better-messages/v1/app', '/getSettings', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_settings' ),
                'permission_callback' => '__return_true'
            ) );
        }

        public function get_settings( WP_REST_Request $request ){
            Better_Messages_Rest_Api()->is_user_authorized($request);

            Better_Messages()->load_options();

            return Better_Messages()->get_script_variables();
        }

        private $last_assets = false;

        public function get_last_assets()
        {
            if( $this->last_assets ){
                return $this->last_assets;
            }

            $is_dev = defined( 'BM_DEV' );

            do_action('better_messages_register_script_dependencies', 'mobile-app');

            $dependencies = apply_filters('better_messages_script_dependencies', array(
                'wp-i18n'
            ));

            $version = Better_Messages()->version;

            $file_name = 'bp-messages-app.min.js';

            if( $is_dev ){
                $file_name = 'bp-messages-app.js';
                $version .= filemtime( Better_Messages()->path . 'assets/js/' . $file_name );
            }

            wp_register_script(
                'better-messages-app',
                Better_Messages()->url . 'assets/js/' . $file_name,
                $dependencies,
                $version
            );

            $script_variables = Better_Messages()->get_script_variables();

            unset(
                $script_variables['nonce'], $script_variables['loginUrl']
            );

            if( get_option( 'users_can_register' ) ){
                $script_variables['registerUrl'] = apply_filters( 'better_messages_registration_url', wp_registration_url() );
            }

            $mobile_app_settings = Better_Messages()->mobile_app->settings->get_settings();

            $script_variables['mobile'] = apply_filters( 'better_messages_mobile_settings', [
                'loginLogo'          => $mobile_app_settings['loginLogo'],
                'loginLogoExtension' => $mobile_app_settings['loginLogoExtension'],
                'termsAndConditions' => $mobile_app_settings['termsAndConditions']
            ] );

            $script_variables = apply_filters( 'better_messages_mobile_app_script_variables', $script_variables );

            wp_set_script_translations('better-messages-app', 'bp-better-messages', plugin_dir_path(__FILE__) . 'languages/' );
            wp_localize_script( 'better-messages-app', 'Better_Messages', apply_filters( 'bp_better_messages_script_variables', $script_variables ) );

            wp_scripts()->all_deps([ 'better-messages-app']);

            if( $is_dev ){
                $version .= filemtime( Better_Messages()->path . 'assets/css/mobile-app.css' );
            }

            wp_register_style('better-messages-app', Better_Messages()->url . 'assets/css/mobile-app.css',
               false,
                $version
            );

            Better_Messages()->enqueue_css();

            wp_styles()->all_deps([ 'better-messages', 'better-messages-app' ]);

            $base_url = site_url( '' );

            $scripts = [];
            $styles  = [];

            $hash_array = [];

            foreach( wp_scripts()->to_do as $handle ){
                $_script = wp_scripts()->registered[$handle];

                $src = $_script->src;

                if (empty($src)) continue;

                if (strpos($src, 'http', 0) === false) {
                    $src = $base_url . $src;
                }

                $script = [
                    'handle' => $handle,
                    'src' => $src,
                    'ver' => $_script->ver,
                    'extra' => []
                ];

                $hash_string = $handle . $_script->ver;

                if (isset($_script->extra['after'])) {
                    if (!is_array($_script->extra['after'])) {
                        $_script->extra['after'] = [$_script->extra['after']];
                    }

                    $script['extra']['after'] = implode('', $_script->extra['after']);
                    $hash_string .= implode('', $_script->extra['after']);
                }

                if (isset($_script->extra['data'])) {
                    $script['extra']['data'] = $_script->extra['data'];
                    $hash_string .= $_script->extra['data'];
                }

                if ($handle === 'better-messages-app') {
                    $translations = wp_scripts()->print_translations('better-messages-app', false);
                    $hash_string .= $translations;
                    $script['extra']['data'] = $translations . $script['extra']['data'];
                }

                $hash_array[] = $hash_string;

                $scripts[] = $script;
            }


            foreach( wp_styles()->to_do as $handle ) {
                $_style = wp_styles()->registered[$handle];
                $src = $_style->src;

                if( strpos($src, 'http', 0) === false ){
                    $src = $base_url . $src;
                }

                $style = [
                    'handle' => $handle,
                    'src'    => $src,
                    'ver'    => $_style->ver,
                    'extra'  => []
                ];

                $hash_string = $handle . $_style->ver;

                if( isset($_style->extra['after']) ){
                    if( ! is_array($_style->extra['after'] ) ){
                        $_style->extra['after'] = [ $_style->extra['after'] ];
                    }
                    $style['extra']['after'] = implode('', $_style->extra['after']);
                    $hash_string .= implode('', $_style->extra['after']);
                }

                if( isset($_style->extra['data']) ){
                    $style['extra']['data'] = $_style->extra['data'];
                    $hash_string .= $_style->extra['data'];
                }

                $hash_array[] = $hash_string;

                $styles[] = $style;
            }

            $this->last_assets = [
                'scripts'   => $scripts,
                'styles'    => $styles,
                'hash'      => md5( implode('', $hash_array) )
            ];

            return $this->last_assets;
        }

        public function sync_scripts( WP_REST_Request $request ): array
        {
            Better_Messages_Rest_Api()->is_user_authorized($request);

            return $this->get_last_assets();
        }
    }

endif;

function Better_Messages_Mobile_App_Scripts()
{
    return Better_Messages_Mobile_App_Scripts::instance();
}
