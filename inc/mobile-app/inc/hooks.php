<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_Hooks' ) ):

    class Better_Messages_Mobile_App_Hooks
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Hooks();
            }

            return $instance;
        }

        public function __construct(){
            add_filter( 'better_messages_user_config', array( $this, 'logout_button'), 12, 1 );
            add_filter( 'kses_allowed_protocols', array( $this, 'add_custom_protocol' ), 10, 1 );
            add_filter( 'rest_allowed_cors_headers', array( $this, 'add_custom_headers' ), 10, 2 );
        }

        public function add_custom_protocol($protocols)
        {
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = $_SERVER['HTTP_USER_AGENT'];

                if( str_ends_with($user_agent, 'better-messages-app') ){
                    $protocols[] = 'capacitor';
                }
            }

            return $protocols;
        }
        public function add_custom_headers($allow_headers, $request = null )
        {
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = $_SERVER['HTTP_USER_AGENT'];

                if( str_ends_with($user_agent, 'better-messages-app') ){
                    return ['*'];
                }
            }

            return $allow_headers;
        }

        public function logout_button( $user_settings ){

            /*if( Better_Messages_Mobile_App()->auth->is_mobile_app() ){
                $user_settings[] = [
                    'id' => 'mobile_logout_button',
                    'title' => _x('Logout', 'Mobile App - User settings', 'bp-better-messages'),
                    'type' => 'mobile_logout'
                ];
            }*/

            return $user_settings;
        }
    }

endif;

function Better_Messages_Mobile_App_Hooks()
{
    return Better_Messages_Mobile_App_Hooks::instance();
}
