<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_Auth' ) ):

    class Better_Messages_Mobile_App_Auth
    {
        public $is_mobile = false;
        public $current_device_id = false;
        public $current_app_id    = false;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Auth();
            }

            return $instance;
        }

        public function __construct(){
            add_filter( 'better_messages_rest_is_user_authorized', array( $this, 'check_app_access' ), 10, 2 );
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );

            add_action('init', array( $this, 'catch_bm_auth' ), 1 );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1/app', '/login', array(
                'methods' => 'POST',
                'callback' => array( $this, 'login' ),
                'permission_callback' => '__return_true'
            ) );
        }

        public function catch_bm_auth(){

            if( isset($_GET['bm_app_auth_token']) && ! empty( $_GET['bm_app_auth_token'] ) && is_string( $_GET['bm_app_auth_token']) ){

                $token = sanitize_text_field( $_GET['bm_app_auth_token'] );

                try {
                    $data = json_decode(base64_decode($token), true);

                    $claims = $data['claims'];
                    $token  = base64_decode($data['token']);

                    $claimed_user_id   = $claims['user_id'];
                    $claimed_device_id = $claims['device_id'];
                    $claimed_domain    = $claims['domain'];
                    $claimed_app_id    = $claims['app_id'];
                    $claimed_time      = $claims['timestamp'];

                    $current_time = time();

                    if ($claimed_time < $current_time - 300) {
                        return;
                    }

                    if( $claimed_domain !== Better_Messages()->functions->get_site_domain() ){
                        return;
                    }

                    $device = Better_Messages()->mobile_app->functions->get_device_for_auth( $claimed_user_id, $claimed_device_id, $claimed_app_id );

                    if( ! $device ){
                        return;
                    }

                    $verify = openssl_verify(json_encode($claims), $token, $device['device_public_key'], OPENSSL_ALGO_SHA256);

                    if ($verify === 1) {
                        wp_set_current_user( $device['user_id'] );
                        wp_set_auth_cookie( $device['user_id'] );
                        do_action('better_messages_mobile_app_web_auth', $device['user_id'], $device);
                    }

                } catch ( Exception $exception ){}
            }
        }

        public function is_mobile_app(){
            return $this->is_mobile;
        }

        public function generate_keys()
        {
            $config = array(
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            );

            // Create the private and public key
            $res = openssl_pkey_new($config);

            // Extract the private key from $res to $privKey
            openssl_pkey_export($res, $privKey);

            // Extract the public key from $res to $pubKey
            $pubKey = openssl_pkey_get_details($res);
            $pubKey = $pubKey["key"];

            $privKey = trim(str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n"], '', $privKey));
            $pubKey = trim(str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"], '', $pubKey));

            return [
                'private_key' => $privKey,
                'public_key'  => $pubKey
            ];
        }

        public function login( WP_REST_Request $request ){
            $login    = sanitize_user( $request->get_param('username') );
            $password = trim( $request->get_param('password') );
            $device   = $request->get_param('device');

            if( ! $device['key'] ){
                return new WP_Error(
                    'rest_forbidden',
                    _x('The device public key is required', 'Mobile App Authorization', 'bp-better-messages')
                );
            }

            if( ! $login || ! $password ){
                return new WP_Error(
                    'rest_forbidden',
                    _x('The username and password are required', 'Mobile App Authorization', 'bp-better-messages')
                );
            }

            $user = get_user_by( 'login', $login );

            if( ! $user ){
                $user = get_user_by( 'email', $login );
            }

            if( ! $user ){
                return new WP_Error(
                    'rest_forbidden',
                    _x('The username or password you entered is incorrect', 'App Authorization', 'better-messages-mobile-app'),
                    array( 'status' => 403 )
                );
            }

            $is_password_correct = wp_check_password( $password, $user->data->user_pass, $user->ID );

            if( $is_password_correct ) {
                $user_id = $user->ID;
                wp_set_current_user($user_id);

                $auth = [
                    'user_id' => $user->ID,
                    'domain'  => Better_Messages()->functions->get_site_domain()
                ];

                $device['device_public_key']  = $device['key'];
                $app_id = $device['app']['id'];

                Better_Messages_Mobile_App()->functions->update_user_device( $user_id, $device['id'], $app_id, $device, true, true, true );

                return [
                    'auth' => $auth,
                    'manifest' => Better_Messages()->mobile_app->scripts->get_last_assets()
                ];
            } else {
                return new WP_Error(
                    'rest_forbidden',
                    _x('The username or password you entered is incorrect', 'App Authorization', 'better-messages-mobile-app'),
                    array( 'status' => 403 )
                );
            }
        }

        public function check_app_access( $allowed, WP_REST_Request $request ){
            $authorization = $request->get_header('authorization');
            if(strpos($authorization, 'BMAuth ', 0 ) === 0){
                $auth_token  = substr( $authorization, 7 );

                try {
                    $data = json_decode( base64_decode( $auth_token ), true );

                    $claims = $data['claims'];
                    $token  = base64_decode($data['token']);

                    $claimed_user_id   = $claims['user_id'];
                    $claimed_device_id = $claims['device_id'];
                    $claimed_domain    = $claims['domain'];
                    $claimed_app_id    = $claims['app_id'];
                    $claimed_time      = $claims['timestamp'];

                    if( $claimed_domain !== Better_Messages()->functions->get_site_domain() ){
                        wp_send_json( array(
                            'code' => 'mobile_app_auth',
                            'message' => 'The domain in the token do not match with this site'
                        ), 403 );
                    }

                    $device = Better_Messages()->mobile_app->functions->get_device_for_auth( $claimed_user_id, $claimed_device_id, $claimed_app_id );

                    if( ! $device ){
                        wp_send_json( array(
                            'code' => 'mobile_app_auth',
                            'message' => 'The device is not found'
                        ), 403 );
                    }

                    $verify = openssl_verify(json_encode($claims), $token, $device['device_public_key'], OPENSSL_ALGO_SHA256);

                    if ($verify === 1) {
                        $current_time = time();

                        if ($claimed_time < $current_time - 900) {
                            wp_send_json( array(
                                'code' => 'mobile_app_auth',
                                'message' => 'The token is expired'
                            ), 403 );
                        }

                        wp_set_current_user( $device['user_id'] );
                        $this->current_device_id = $device['device_id'];
                        $this->current_app_id = $claimed_app_id;
                        $this->is_mobile = true;

                        $last_assets = Better_Messages()->mobile_app->scripts->get_last_assets();
                        header("X-BM-App-Scripts-Hash: {$last_assets['hash']}");
                        header("Access-Control-Expose-Headers: X-BM-App-Scripts-Hash");

                        Better_Messages()->mobile_app->functions->update_device_last_active( $this->current_device_id, $this->current_app_id );

                        do_action( 'better_messages_mobile_app_auth', $device['user_id'], $device );

                        $allowed = true;
                    } else {
                        wp_send_json( array(
                            'code' => 'mobile_app_auth',
                            'message' => 'The token is not valid'
                        ), 403 );
                    }
                } catch ( Exception $exception ){
                    wp_send_json( array(
                        'code' => 'mobile_app_auth',
                        'message' => $exception->getMessage()
                    ), 403 );
                }
            }

            return $allowed;
        }
    }

endif;

function Better_Messages_Mobile_App_Auth()
{
    return Better_Messages_Mobile_App_Auth::instance();
}
