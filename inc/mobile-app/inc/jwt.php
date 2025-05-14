<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_JWT' ) ):

    class Better_Messages_Mobile_App_JWT
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_JWT();
            }

            return $instance;
        }

        public function __construct(){
            require_once( Better_Messages()->path . 'vendor/php-jwt/php-jwt.php' );
        }


    }

endif;

function Better_Messages_Mobile_App_JWT()
{
    return Better_Messages_Mobile_App_JWT::instance();
}
