<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Better_Messages_OneSignal' ) ) {

    class Better_Messages_OneSignal
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_OneSignal();
            }

            return $instance;
        }

        public function __construct(){
            add_filter( 'better_messages_bulk_pushs', array( $this, 'send_bulk_pushs' ), 10, 4 );
            add_filter( 'better_messages_send_onesignal_push', array( $this, 'send_pushs' ), 10, 7 );

            add_filter( 'better_messages_3rd_party_push_active', '__return_true' );
            add_filter( 'better_messages_push_active', '__return_false' );
            add_filter( 'better_messages_push_message_in_settings', array( $this, 'push_message_in_settings' ) );

            add_filter( 'bp_better_messages_script_variable', array( $this, 'add_onesignal_script_variable' ) );
            add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
            add_action( 'wp_footer', array( $this, 'frontend_script' ), 99999 );
        }

        public function frontend_script(){
            if( ! wp_script_is( 'better-messages' ) || ! is_user_logged_in() ) return;

            $is_dev = defined( 'BM_DEV' );
            $suffix = ( $is_dev ? '' : '.min' );

            $url = Better_Messages()->url . "addons/onesignal/sub-update{$suffix}.js";

            echo '<script src="' . $url . '?ver=0.2"></script>';
        }

        public function rest_api_init()
        {
            register_rest_route('better-messages/v1', '/oneSignal/updateSubscription', array(
                'methods' => 'POST',
                'callback' => array($this, 'update_subscription'),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ));
        }

        public function update_subscription( WP_REST_Request $request )
        {
            $user_id = Better_Messages()->functions->get_current_user_id();

            if ($user_id <= 0) {
                return new WP_Error('onesignal_error', 'User ID is required', array('status' => 400));
            }

            if (class_exists('OneSignal')) {
                $onesignal_wp_settings = OneSignal::get_onesignal_settings();
                $onesignal_app_id = $onesignal_wp_settings['app_id'];
                $onesignal_auth_key = $onesignal_wp_settings['app_rest_api_key'];

                $subscription_id = (string) $request->get_param('subscription_id');

                if ( ! $subscription_id ) {
                    return new WP_Error('onesignal_error', 'Subscription ID is required', array('status' => 400));
                }

                $onesignal_post_url = "https://api.onesignal.com/apps/{$onesignal_app_id}/subscriptions/{$subscription_id}/user/identity";

                $fields = [
                    'identity' => [
                        'external_id' => (string)$user_id
                    ]
                ];

                $request = array(
                    'method' => 'PATCH',
                    'headers' => array(
                        'content-type' => 'application/json;charset=utf-8',
                        'Authorization' => 'Basic ' . $onesignal_auth_key,
                    ),
                    'body' => wp_json_encode($fields),
                    'timeout' => 3,
                );

                $response = wp_remote_request($onesignal_post_url, $request);

                if (is_wp_error($response)) {
                    return new WP_Error('onesignal_error', $response->get_error_message(), array('status' => 500));
                }

                return [
                    'user_id' => $user_id,
                    'subscription_id' => $subscription_id,
                ];
            }

            if( defined('ONESIGNAL_VERSION_V3' ) ) {
                $apiKey = get_option('OneSignalWPSetting')['app_rest_api_key'] ?? '';
                if( $apiKey === '' ) return false;
                $onesignal_app_id = get_option('OneSignalWPSetting')['app_id'];
                $subscription_id = (string) $request->get_param('subscription_id');

                if ( ! $subscription_id ) {
                    return new WP_Error('onesignal_error', 'Subscription ID is required', array('status' => 400));
                }

                $onesignal_post_url = "https://api.onesignal.com/apps/{$onesignal_app_id}/subscriptions/{$subscription_id}/user/identity";

                $fields = [
                    'identity' => [
                        'external_id' => (string)$user_id
                    ]
                ];

                $request = array(
                    'method' => 'PATCH',
                    'headers' => array(
                        'content-type' => 'application/json;charset=utf-8',
                        'Authorization' => 'Key ' . $apiKey,
                    ),
                    'body' => wp_json_encode($fields),
                    'timeout' => 3,
                );

                $response = wp_remote_request($onesignal_post_url, $request);

                if ( is_wp_error( $response ) ) {
                    return new WP_Error('onesignal_error', $response->get_error_message(), array('status' => 500));
                }

                return [
                    'user_id' => $user_id,
                    'subscription_id' => $subscription_id,
                ];
            }

            return false;
        }

        public function add_onesignal_script_variable( $script_variable ){
            if( is_user_logged_in() ){
                $script_variable['oneSignal'] = '1';
            }

            return $script_variable;
        }

        public function send_bulk_pushs( $pushs, $all_recipients, $notification, $message )
        {
            if( class_exists('OneSignal_Admin') && class_exists('OneSignal') ) {

                $onesignal_wp_settings = OneSignal::get_onesignal_settings();

                if ($onesignal_wp_settings['app_id'] === '' || $onesignal_wp_settings['app_rest_api_key'] === '') return $pushs;

                $image = $notification['icon'];

                $fields = array(
                    'app_id' => $onesignal_wp_settings['app_id'],
                    'chrome_web_icon' => $image,
                    'chrome_web_badge' => $image,
                    'firefox_icon' => $image,
                    'headings' => ['en' => stripslashes_deep(wp_specialchars_decode($notification['title']))],
                    'url' => $notification['data']['url'],
                    'contents' => ['en' => stripslashes_deep(wp_specialchars_decode($notification['body']))],
                );

                $pushs = [
                    'onesignal_api_key' => $onesignal_wp_settings['app_rest_api_key'],
                    'user_ids' => array_map('strval', $all_recipients),
                    'fields' => $fields
                ];
            } else if( defined('ONESIGNAL_VERSION_V3' ) ){
                $apiKey = get_option('OneSignalWPSetting')['app_rest_api_key'] ?? '';
                if( $apiKey === '' ) return $pushs;

                $image = $notification['icon'];

                $fields = array(
                    'app_id' => get_option('OneSignalWPSetting')['app_id'],
                    'chrome_web_icon' => $image,
                    'chrome_web_badge' => $image,
                    'firefox_icon' => $image,
                    'headings' => ['en' => stripslashes_deep(wp_specialchars_decode($notification['title']))],
                    'url' => $notification['data']['url'],
                    'contents' => ['en' => stripslashes_deep(wp_specialchars_decode($notification['body']))],
                );

                $pushs = [
                    'onesignal_api_key' => $apiKey,
                    'user_ids' => array_map('strval', $all_recipients),
                    'fields' => $fields
                ];
            }

            return $pushs;
        }

        public function send_pushs( $result, $user_id, $notification, $type, $thread_id, $message_id, $sender_id ){
            if( class_exists('OneSignal_Admin') && class_exists('OneSignal') ) {

                $onesignal_wp_settings = OneSignal::get_onesignal_settings();

                if ($onesignal_wp_settings['app_id'] === '' || $onesignal_wp_settings['app_rest_api_key'] === '') return $result;

                $onesignal_auth_key = $onesignal_wp_settings['app_rest_api_key'];

                $image = $notification['icon'];

                $fields = array(
                    'app_id' => $onesignal_wp_settings['app_id'],
                    'chrome_web_icon' => $image,
                    'chrome_web_badge' => $image,
                    'firefox_icon' => $image,
                    'headings' => ['en' => stripslashes_deep(wp_specialchars_decode($notification['title']))],
                    'url' => $notification['data']['url'],
                    'contents' => ['en' => stripslashes_deep(wp_specialchars_decode($notification['body']))],
                );

                return [
                    'onesignal_api_key' => $onesignal_auth_key,
                    'user_ids' => array_map('strval', [$user_id]),
                    'fields' => $fields
                ];
            } else if( defined('ONESIGNAL_VERSION_V3' ) ){
                $apiKey = get_option('OneSignalWPSetting')['app_rest_api_key'] ?? '';

                if( $apiKey === '' ) return $result;

                $image = $notification['icon'];

                $fields = array(
                    'app_id'           => get_option('OneSignalWPSetting')['app_id'],
                    'chrome_web_icon' => $image,
                    'chrome_web_badge' => $image,
                    'firefox_icon'     => $image,
                    'headings'         => ['en' => stripslashes_deep(wp_specialchars_decode($notification['title']))],
                    'url'              => $notification['data']['url'],
                    'contents'         => ['en' => stripslashes_deep(wp_specialchars_decode($notification['body']))],
                );

                return [
                    'onesignal_api_key' => $apiKey,
                    'user_ids' => array_map('strval', [$user_id]),
                    'fields' => $fields
                ];
            }

            return $result;
        }

        public function push_message_in_settings( $message ){
            $message = '<p style="color: #0c5460;background-color: #d1ecf1;border: 1px solid #d1ecf1;padding: 15px;line-height: 24px;max-width: 550px;">';
            $message .= sprintf(_x('The OneSignal WordPress plugin integration is active and will be used, this option do not need to be enabled.', 'Settings page', 'bp-better-messages'), 'https://www.better-messages.com/docs/integrations/onesignal/');
            $message .= '</p>';

            return $message;
        }

    }
}
