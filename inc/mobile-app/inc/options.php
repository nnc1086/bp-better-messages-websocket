<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_Options' ) ):

    class Better_Messages_Mobile_App_Options
    {
        public $settings;
        public $defaults;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Options();
            }

            return $instance;
        }

        public function __construct(){
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1/admin/app', '/getSettings', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_settings' ),
                'permission_callback' => array($this, 'user_is_admin'),
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/saveSettings', array(
                'methods' => 'POST',
                'callback' => array( $this, 'save_settings' ),
                'permission_callback' => array($this, 'user_is_admin'),
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/uploadFile', array(
                'methods' => 'POST',
                'callback' => array( $this, 'upload_file' ),
                'permission_callback' => array($this, 'user_is_admin'),
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/deleteFile', array(
                'methods' => 'POST',
                'callback' => array( $this, 'delete_file' ),
                'permission_callback' => array($this, 'user_is_admin'),
            ) );
        }

        public function user_is_admin(): bool
        {
            return current_user_can('manage_options');
        }

        public function init(): void
        {

            $this->defaults = array(
                'iosAppName'         => 'My Messenger',
                'iosAppNameDev'      => 'My Messenger Dev',
                'iosAppTeamId'       => '',
                'termsAndConditions' => '',

                'appIcon'            => '',
                'appSplash'          => '',

                'loginLogo'          => '',

                'iosCertificateDev'  => '',
                'iosCertificateProd' => '',

                'iosBundleDev'        => '',
                'iosBundleServiceDev' => '',
                'iosBundleProd'       => '',
                'iosBundleService'    => '',

                'iosProfileDev'      => '',
                'iosProfileServiceDev'  => '',
                'iosProfileProd'     => '',
                'iosProfileService'  => '',
            );

            $args  = get_option( 'better-messages-app-settings', array() );
            $iosApi = get_option('better-messages-app-ios-auth', false);
            $iosPush = get_option('better-messages-app-ios-push-cert', false);
            $androidApi = get_option('better-messages-app-android-auth', false);

            if( $iosApi ){
                if( $iosApi['apiKey'] ) {
                    unset($iosApi['apiKey']);
                }

                $args['iosApi'] = $iosApi;
            } else {
                $args['iosApi'] = (object) [];
            }

            if( $androidApi ){
                if( $androidApi['apiKey'] ) {
                    unset($androidApi['apiKey']);
                }

                $args['androidApi'] = $androidApi;
            } else {
                $args['androidApi'] = (object) [];
            }

            if( $iosPush ){
                $args['iosPush'] = true;
            } else {
                $args['iosPush'] = false;
            }

            $files = [
                'appIcon',
                'appSplash',
                'loginLogo'
            ];

            foreach ( $files as $file ){
                $args[$file] = get_option('better-messages-app-settings-file-' . $file, '');
                $args[$file . 'Extension'] = get_option('better-messages-app-settings-file-' . $file . '-extension', '');
            }


            $this->settings = wp_parse_args( $args, $this->defaults );
        }

        public function get_settings( WP_REST_Request $request = null ): array
        {
            $this->init();
            return $this->settings;
        }


        public function save_settings( WP_REST_Request $request ){
            $settings = (array) $request->get_param('settings');

            if( count( $settings ) > 0 ){
                $_settings = get_option('better-messages-app-settings', []);

                foreach( $settings as $key => $value ){
                    $_settings[$key] = $value;
                }

                $this->update_settings( $_settings );
            }
        }

        public function upload_file( WP_REST_Request $request ){
            $key  = $request->get_param('key');
            $files = $request->get_file_params();

            if( ! isset( $files['file'] ) ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Sorry, you are not allowed to do that', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $file = $files['file'];

            $path = $file['tmp_name'];

            $extension = pathinfo( $file['name'], PATHINFO_EXTENSION );

            switch ( $key ){
                case 'appIcon';
                    $size = getimagesize( $path );
                    if( ! $size ){
                        return new WP_Error(
                            'rest_error',
                            _x( 'Not possible to determine image size', 'Rest API Error', 'bp-better-messages' ),
                            array( 'status' => 406 )
                        );
                    }

                    $width  = $size[0];
                    $height = $size[1];

                    if( $width < 1024 || $height < 1024 || ( $width !== $height ) ){
                        return new WP_Error(
                            'rest_error',
                            sprintf(_x( 'Image must be equal height and at least %s', 'Rest API Error', 'bp-better-messages' ), '1024x1024px'),
                            array( 'status' => 406 )
                        );
                    }
                    break;
                case 'appSplash';
                    $size = getimagesize( $path );
                    if( ! $size ){
                        return new WP_Error(
                            'rest_error',
                            _x( 'Not possible to determine image size', 'Rest API Error', 'bp-better-messages' ),
                            array( 'status' => 406 )
                        );
                    }

                    $width  = $size[0];
                    $height = $size[1];

                    if( $width < 2732 || $height < 2732 || ( $width !== $height ) ){
                        return new WP_Error(
                            'rest_error',
                            sprintf(_x( 'Image must be equal height and at least %s', 'Rest API Error', 'bp-better-messages' ), '2732x2732px'),
                            array( 'status' => 406 )
                        );
                    }
                    break;
                case 'loginLogo':

                    break;
            }

            $file_content = base64_encode(file_get_contents($path));

            update_option('better-messages-app-settings-file-' . $key, $file_content, false );
            update_option('better-messages-app-settings-file-' . $key . '-extension', $extension, false );

            return $file_content;
        }

        public function delete_file( WP_REST_Request $request ){
            $key  = $request->get_param('key');
            delete_option('better-messages-app-settings-file-' . $key);
            delete_option('better-messages-app-settings-file-' . $key . '-extension');
            return true;
        }

        public function update_settings( $_settings ){
            update_option('better-messages-app-settings', $_settings, false);
        }
    }

endif;

function Better_Messages_Mobile_App_Options()
{
    return Better_Messages_Mobile_App_Options::instance();
}
