<?php
defined('ABSPATH') || exit;

if ( ! class_exists('Better_Messages_Mobile_App_Builds') ) {
    class Better_Messages_Mobile_App_Builds
    {
        public $settings;

        public $defaults;

        public static function instance(): ?Better_Messages_Mobile_App_Builds
        {
            // Store the instance locally to avoid private static replication
            static $instance = null;
            // Only run these methods if they haven't been run previously

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Builds();
            }

            // Always return the instance
            return $instance;
            // The last metroid is in captivity. The galaxy is at peace.
        }

        public function __construct(){
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );

            add_action('init', array( $this, 'builds_checker' ) );
            add_action('check_build_statuses', array( $this, 'check_build_statuses' ) );
        }

        public function builds_checker()
        {
            if ( ! wp_next_scheduled('ba_check_build_statuses') ) {
                wp_schedule_event( time(), 'ba_every_five_minutes', 'ba_check_build_statuses' );
            }
        }

        public function check_build_statuses()
        {
            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $builds = $wpdb->get_results( "SELECT id, status, site_id, secret FROM $table WHERE status = 'in-queue' OR status = 'building'", ARRAY_A );

            if( count( $builds ) > 0 ) {
                foreach ($builds as $build) {
                    $request = wp_remote_get(add_query_arg( [
                        'id' => $build['id'],
                        'site_id' => $build['site_id'],
                        'secret' => $build['secret'],
                    ], 'https://builder.better-messages.com/api/getBuildStatus'));

                    if( ! Better_Messages()->functions->is_response_good( $request ) ){
                        continue;
                    }

                    $response = json_decode($request['body'], true );

                    if ( wp_remote_retrieve_response_code($request) != 200 ) {
                        continue;
                    }

                    if( $response['status'] !== $build['status'] ){
                        $wpdb->update( $table, [
                            'status' => $response['status'],
                        ], [
                            'id' => $build['id'],
                        ] );
                    }
                }
            }
        }

        public function rest_api_init(): void
        {
            register_rest_route('better-messages/v1/admin/app', '/prepareBuild', array(
                'methods' => 'POST',
                'callback' => array($this, 'prepare_build'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/createBuild', array(
                'methods' => 'POST',
                'callback' => array($this, 'create_build'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/getBuilds', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_builds'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/getBuild', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_build'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/deleteBuild', array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_build'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/releaseBuild', array(
                'methods' => 'POST',
                'callback' => array($this, 'release_build'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/uploadStatus', array(
                'methods' => 'GET',
                'callback' => array($this, 'upload_status'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));

            register_rest_route('better-messages/v1/admin/app', '/buildStatus', array(
                'methods' => 'GET',
                'callback' => array($this, 'build_status'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));
        }

        public function user_is_admin(): bool
        {
            return current_user_can('manage_options');
        }

        public function build_status( WP_REST_Request $request )
        {
            $build_id = (int) $request->get_param( 'build_id' );

            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $build = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status, site_id, secret FROM $table WHERE `id` = %d",
                    $build_id
                ),
                ARRAY_A
            );

            if( ! $build ){
                return new WP_Error('invalid_build', 'The build does not exist');
            }

            if( $build['status'] === 'built' || $build['status'] === 'failed' ){
                return $build['status'];
            }

            $request = wp_remote_get(add_query_arg( [
                'id' => $build['id'],
                'site_id' => $build['site_id'],
                'secret' => $build['secret'],
            ], 'https://builder.better-messages.com/api/getBuildStatus'));

            if( ! Better_Messages()->functions->is_response_good( $request ) ){
                return Better_Messages()->functions->is_response_good( $request );
            }

            $response = json_decode($request['body'], true );

            if ( wp_remote_retrieve_response_code($request) != 200 ) {
                return new WP_Error('request_failed', 'The network request failed.');
            }

            if( $response['status'] !== $build['status'] ){
                $wpdb->update( $table, [
                    'status' => $response['status'],
                ], [
                    'id' => $build['id'],
                ] );
            }

            return $response['status'];
        }

        public function upload_status( WP_REST_Request $request )
        {
            $build_id = (int) $request->get_param( 'build_id' );

            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $build = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status, site_id, secret FROM $table WHERE `id` = %d",
                    $build_id )
            );

            if( ! $build ){
                return new WP_Error('invalid_build', 'The build does not exist');
            }

            if( $build->status !== 'built' ){
                return new WP_Error('invalid_status', 'The build must be built');
            }

            $url = add_query_arg( [
                'build_id' => $build->id,
                'site_id'  => $build->site_id,
                'secret'   => $build->secret,
            ], 'https://builder.better-messages.com/api/getUploadStatus');

            $request = wp_remote_get( $url );

            if( ! Better_Messages()->functions->is_response_good( $request ) ){
                return Better_Messages()->functions->is_response_good( $request );
            }

            $result = json_decode($request['body']);

            $status = $result->status;

            $response = [
                'status' => $status,
            ];

            if( $result->errors ){
                $response['errors'] = $result->errors;
            }

            return $response;
        }

        public function delete_build( WP_REST_Request $request )
        {
            $build_id = (int) $request->get_param( 'build_id' );

            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $build = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, platform, type, status, site_id, secret FROM $table WHERE `id` = %d",
                    $build_id )
            );

            if( ! $build ){
                return new WP_Error('invalid_build', 'The build does not exist');
            }

            $request = wp_remote_request('https://builder.better-messages.com/api/deleteBuild', array(
                'method' => 'DELETE',
                'body' => [
                    'id' => $build->id,
                    'site_id' => $build->site_id,
                    'secret' => $build->secret,
                ]
            ));

            if( ! Better_Messages()->functions->is_response_good( $request ) ){
                return Better_Messages()->functions->is_response_good( $request );
            }

            $response = json_decode($request['body'], true );

            if ( wp_remote_retrieve_response_code($request) != 200 ) {
                if( isset( $response['error'] ) ){
                    return new WP_Error('request_failed', $response['error']);
                } else {
                    return new WP_Error('request_failed', 'The network request failed.');
                }
            }

            if( $response === 'deleted' ){
                $wpdb->delete( $table, [
                    'id' => $build->id,
                ], [ '%d' ] );
            }

            return true;
        }

        public function get_build( WP_REST_Request $request )
        {
            $id = (int) $request->get_param('id');

            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $build = $wpdb->get_row(
                $wpdb->prepare("SELECT id, platform, type, status, site_id, secret, build_info FROM $table WHERE `id` = %d", $id),
            ARRAY_A );

            if( ! $build ){
                return new WP_Error('invalid_build', 'The build does not exist');
            }

            $build_info = json_decode($build['build_info'], true);

            $app_name = $build_info['app_name'];
            $app_icon = $build_info['app_icon'];

            unset($build['build_info']);

            $build['app_name'] = $app_name;
            $build['app_icon'] = $app_icon;

            if( $build['type'] === 'production' ){
                $build['version'] = $build_info['version'];
                $build['mkt_version'] = $build_info['mkt_version'];
            }

            if( $build['type'] === 'development' ){
                $build['devices'] = $build_info['devices'];
            }


            $download_url = add_query_arg( array(
                'id' => $build['id'],
                'site_id' => $build['site_id'],
                'secret' => $build['secret'],
            ), 'https://builder.better-messages.com/api/getIpa/' );

            $build['download_url'] = $download_url;

            $install_destination = add_query_arg( array(
                'id' => $build['id'],
                'site_id' => $build['site_id'],
                'secret' => $build['secret'],
            ), 'https://builder.better-messages.com/api/getDevelopmentManifest/' );

            $install_url = 'itms-services://?action=download-manifest&amp;url=' . urlencode( $install_destination );

            $build['install_url'] = $install_url;

            return $build;
        }
        public function get_builds( WP_REST_Request $request )
        {
            $this->check_build_statuses();

            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $builds = $wpdb->get_results( "SELECT id, platform, type, status, site_id, secret, build_info FROM $table ORDER BY id DESC", ARRAY_A );

            if( count( $builds ) > 0 ) {
                foreach ($builds as $key => $build) {
                    $build_info = json_decode($build['build_info'], true);

                    $app_name = $build_info['app_name'];
                    $app_icon = $build_info['app_icon'];

                    unset($builds[$key]['build_info']);

                    $builds[$key]['app_name'] = $app_name;
                    $builds[$key]['app_icon'] = $app_icon;
                }
            }

            return $builds;
        }

        public function prepare_build( WP_REST_Request $request )
        {
            $platform = $request->get_param('platform');

            if ( $platform !== 'ios' && $platform !== 'android' ) {
                return new WP_Error('invalid_platform', 'Platform must be ios or android');
            }

            $type     = $request->get_param('type');
            if ( $type !== 'development' && $type !== 'production' ) {
                return new WP_Error('invalid_type', 'Type must be development or production');
            }

            $fs_site = bpbm_fs()->get_site();

            if( ! $fs_site ){
                return new WP_Error('invalid_site', 'The site is not valid');
            }

            if ($platform === 'ios' && $type === 'development') {
                // Code for iOS development
                return $this->get_ios_dev_build_info();
            } else if ($platform === 'ios' && $type === 'production') {
                // Code for iOS production
                return $this->get_ios_dist_build_info();
            } else if ($platform === 'android' && $type === 'development') {
                // Code for Android development
                return new WP_Error('not_implemented', 'Android development builds are not implemented yet');
            } else if ($platform === 'android' && $type === 'production') {
                // Code for Android production
                return new WP_Error('not_implemented', 'Android production builds are not implemented yet');
            } else {
                // Code for other cases
                return new WP_Error('not_implemented', 'This type of application is not implemented yet');
            }
        }

        public function release_build( WP_REST_Request $request )
        {
            $build_id = (int) $request->get_param( 'build_id' );
            $retry = $request->get_param( 'retry' ) === '1' ? true : false;

            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $build = $wpdb->get_row(
                $wpdb->prepare( "SELECT id, platform, type, status, site_id, secret FROM $table WHERE `id` = %d", $build_id )
            );

            if( ! $build ){
                return new WP_Error('invalid_build', 'The build does not exist');
            }

            $type = $build->type;

            if( $type !== 'production' ){
                return new WP_Error('invalid_type', 'The build type must be production');
            }

            if( $build->status !== 'built' ){
                return new WP_Error('invalid_status', 'The build must be built');
            }

            if( $build->platform === 'ios' ){

                $credentials = get_option('better-messages-app-ios-auth', false);

                $data = [
                    'id'          => $build->id,
                    'site_id'     => $build->site_id,
                    'secret'      => $build->secret,
                    'credentials' => $credentials,
                ];

                if( $retry ) {
                    $data['retry'] = true;
                }

                $request = wp_remote_post('https://builder.better-messages.com/api/releaseBuild', array(
                    'body' => $data
                ));

                if( ! Better_Messages()->functions->is_response_good( $request ) ){
                    return Better_Messages()->functions->is_response_good( $request );
                }

                return json_decode($request['body'], true );

                /*
                $response = json_decode($request['body'], true );

                if ( wp_remote_retrieve_response_code($request) != 200 ) {
                    if( isset( $response['error'] ) ){
                        return new WP_Error('request_failed', $response['error']);
                    } else {
                        return new WP_Error('request_failed', 'The network request failed.');
                    }
                }*/

            } else if( $build->platform === 'android' ){
                return new WP_Error('not_implemented', 'Android builds are not implemented yet');
            } else {
                return new WP_Error('not_implemented', 'This type of application is not implemented yet');
            }
        }

        public function create_build( WP_REST_Request $request )
        {
            $platform = $request->get_param('platform');

            if ( $platform !== 'ios' && $platform !== 'android' ) {
                return new WP_Error('invalid_platform', 'Platform must be ios or android');
            }

            $type     = $request->get_param('type');
            if ( $type !== 'development' && $type !== 'production' ) {
                return new WP_Error('invalid_type', 'Type must be development or production');
            }

            if ($platform === 'ios' && $type === 'development') {
                // Code for iOS development
                $info = $this->get_ios_dev_build_info(true);

                if( is_wp_error( $info ) ){
                    return $info;
                }

                return $this->process_build_request( $info );
            } else if ($platform === 'ios' && $type === 'production') {
                // Code for iOS production
                $info = $this->get_ios_dist_build_info(true);

                if( is_wp_error( $info ) ){
                    return $info;
                }

                return $this->process_build_request( $info );
            } else if ($platform === 'android' && $type === 'development') {
                // Code for Android development
                return new WP_Error('not_implemented', 'Android development builds are not implemented yet');
            } else if ($platform === 'android' && $type === 'production') {
                // Code for Android production
                return new WP_Error('not_implemented', 'Android production builds are not implemented yet');
            } else {
                // Code for other cases
                return new WP_Error('not_implemented', 'This type of application is not implemented yet');
            }
        }

        public function get_ios_dev_build_info($build_info = false)
        {
            $settings = Better_Messages()->mobile_app->settings->get_settings();

            $team_id     = trim($settings['iosAppTeamId']);
            $bundle_id   = $settings['iosBundleDev'];
            $notification_bundle_id = $settings['iosBundleServiceDev'];

            $site_url = get_site_url();
            $parse = parse_url($site_url);
            $domain = $parse['host'];

            $fs_site = bpbm_fs()->get_site();

            if( ! $fs_site ){
                return new WP_Error('site_not_connected', 'The site is not connected');
            }

            $jwt = Better_Messages()->mobile_app->ios->get_jwt();

            if( empty( $jwt ) ){
                return new WP_Error('invalid_access', 'Connection to Apple Developer Account is not configured');
            }

            if( ! bpbm_fs()->has_features_enabled_license() ){
                return new WP_Error( 'site_not_licensed', 'The website has no active WebSocket license' );
            }

            if( ! $team_id ){
                return new WP_Error('no_team_id', 'The Team ID is not set');
            }

            /* $certificate = Better_Messages()->mobile_app->ios->get_certificate( $certificate_id );

            if( is_wp_error( $certificate ) ){
                return $certificate;
            }

            $allowed_cert_types = [
                'DEVELOPMENT',
                'IOS_DEVELOPMENT'
            ];

            if( !in_array( $certificate['certificateType'], $allowed_cert_types ) ){
                return new WP_Error('invalid_certificate', 'The certificate is not a development certificate');
            } */

            $bundle = Better_Messages()->mobile_app->ios->get_bundle( $bundle_id );

            if( is_wp_error( $bundle ) ){
                return $bundle;
            }

            $existing_capabilities = $bundle['capabilities'];

            $required_capabilities = [
                'PUSH_NOTIFICATIONS',
                'USERNOTIFICATIONS_COMMUNICATION',
                'USERNOTIFICATIONS_TIMESENSITIVE',
                'ASSOCIATED_DOMAINS'
            ];

            $missing_capabilities = array_diff( $required_capabilities, $existing_capabilities );

            if( count( $missing_capabilities ) > 0 ){
                return new WP_Error('missing_capabilities', 'The bundle is missing required capabilities: ' . implode(', ', $missing_capabilities) );
            }

            $devices = Better_Messages()->mobile_app->ios->get_devices();

            if( is_wp_error( $devices ) ){
                return $devices;
            }

            if( count( $devices ) === 0 ){
                return new WP_Error('no_devices', 'There are no devices registered. Please ensure to add at least one device to the Apple Developer Account');
            }

            /*$profile = Better_Messages()->mobile_app->ios->get_provisioning_profile( $profile_id );

            if( is_wp_error( $profile ) ){
                return $profile;
            }

            if( $profile['profileState'] !== 'ACTIVE' ){
                return new WP_Error('invalid_profile', 'The provisioning profile is not active');
            }*/

            $notification_bundle = Better_Messages()->mobile_app->ios->get_bundle( $notification_bundle_id );

            if( is_wp_error( $notification_bundle ) ){
                return $notification_bundle;
            }

            /*$notification_profile = Better_Messages()->mobile_app->ios->get_provisioning_profile( $notification_profile_id );

            if( is_wp_error( $notification_profile ) ){
                return $notification_profile;
            }

            if( $notification_profile['profileState'] !== 'ACTIVE' ){
                return new WP_Error('invalid_notification_profile', 'The notification provisioning profile is not active');
            }*/

            if( ! $build_info ) {
                //unset($profile['profileContent']);
                //unset($certificate['certificateContent']);

                /*if ($profile['certificates'] && count($profile['certificates']) > 0) {
                    $profile['certificates'] = array_map(function ($certificate) {
                        unset($certificate['certificateContent']);
                        return $certificate;
                    }, $profile['certificates']);
                }*/
            }

            if( ! $settings['iosAppNameDev'] ){
                return new WP_Error('no_app_name', 'The Application Name is not set');
            }

            if( ! $settings['appIcon'] ){
                return new WP_Error('no_app_icon', 'The Application Icon is not set');
            }

            if( ! $settings['appSplash'] ){
                return new WP_Error('no_app_splash', 'The Application Splash Screen is not set');
            }

            if( ! $settings['loginLogo'] ){
                return new WP_Error('no_login_logo', 'The Application Login Logo is not set');
            }

            $api_url = esc_url_raw(get_rest_url(null, '/better-messages/v1/'));

            $credentials = get_option('better-messages-app-ios-auth', false);

            $return = [
                'platform' => 'ios',
                'type' => 'development',
                'site_id' => $fs_site->id,
                'build_info' => [
                    'domain'              => $domain,
                    'app_name'            => $settings['iosAppNameDev'],
                    'app_icon'            => $settings['appIcon'],
                    'app_splash'          => $settings['appSplash'],
                    'api_url'             => $api_url,
                    'bundle'              => $bundle,
                    'team_id'             => $team_id,
                    'devices'             => $devices,
                    'notification_bundle' => $notification_bundle,
                    'credentials'         => $credentials,
                    'plugin_version'      => Better_Messages()->version,
                    'created_at'          => time()
                ]
            ];

            if( $build_info ){
                //$return['build_info']['certificate'] = get_option('better-messages-app-ios-certificate-DEVELOPMENT');
                $return['build_info'] = json_encode( $return['build_info'] );
            }

            return $return;
        }

        public function get_ios_dist_build_info($build_info = false)
        {
            $settings = Better_Messages()->mobile_app->settings->get_settings();

            $bundle_id   = $settings['iosBundleProd'];
            $team_id     = trim($settings['iosAppTeamId']);

            $notification_bundle_id   = $settings['iosBundleService'];

            $site_url = get_site_url();
            $parse = parse_url($site_url);
            $domain = $parse['host'];

            $fs_site = bpbm_fs()->get_site();

            if( ! $fs_site ){
                return new WP_Error('site_not_connected', 'The site is not connected');
            }

            if( ! bpbm_fs()->has_features_enabled_license() ){
                return new WP_Error( 'site_not_licensed', 'The website has no active WebSocket license' );
            }

            $jwt = Better_Messages()->mobile_app->ios->get_jwt();

            if( empty( $jwt ) ){
                return new WP_Error('invalid_access', 'Connection to Apple Developer Account is not configured');
            }

            $bundle = Better_Messages()->mobile_app->ios->get_bundle( $bundle_id );

            if( is_wp_error( $bundle ) ){
                return $bundle;
            }

            $existing_capabilities = $bundle['capabilities'];

            $required_capabilities = [
                'PUSH_NOTIFICATIONS',
                'USERNOTIFICATIONS_COMMUNICATION',
                'USERNOTIFICATIONS_TIMESENSITIVE',
                'ASSOCIATED_DOMAINS'
            ];

            $missing_capabilities = array_diff( $required_capabilities, $existing_capabilities );

            if( count( $missing_capabilities ) > 0 ){
                return new WP_Error('missing_capabilities', 'The bundle is missing required capabilities: ' . implode(', ', $missing_capabilities) );
            }

            $devices = Better_Messages()->mobile_app->ios->get_devices();

            if( is_wp_error( $devices ) ){
                return $devices;
            }

            if( count( $devices ) === 0 ){
                return new WP_Error('no_devices', 'There are no devices registered. Please ensure to add at least one device to the Apple Developer Account');
            }

            $notification_bundle = Better_Messages()->mobile_app->ios->get_bundle( $notification_bundle_id );

            if( is_wp_error( $notification_bundle ) ){
                return $notification_bundle;
            }

            if( ! $settings['iosAppName'] ){
                return new WP_Error('no_app_name', 'The Application Name is not set');
            }

            if( ! $settings['appIcon'] ){
                return new WP_Error('no_app_icon', 'The Application Icon is not set');
            }

            if( ! $settings['appSplash'] ){
                return new WP_Error('no_app_splash', 'The Application Splash Screen is not set');
            }

            if( ! $settings['loginLogo'] ){
                return new WP_Error('no_login_logo', 'The Application Login Logo is not set');
            }

            $app_id = Better_Messages()->mobile_app->ios->get_app_id_from_bundle( $bundle['identifier'] );

            if( is_wp_error( $app_id ) ){
                return $app_id;
            }

            $last_build = Better_Messages()->mobile_app->ios->get_last_build( $app_id );

            if( is_wp_error( $last_build ) ){
                if( $last_build->get_error_code() !== 'build_not_found' ){
                    return $last_build;
                } else {
                    $last_version = 0;
                }
            } else {
                $last_version = (int) $last_build['version'];
            }

            $last_release = Better_Messages()->mobile_app->ios->get_last_version( $app_id );

            $invalid_states = [
                'READY_FOR_DISTRIBUTION'
            ];

            if( is_wp_error( $last_release ) ){
                if( $last_release->get_error_code() !== 'version_not_found' ){
                    return $last_release;
                } else {
                    $last_marketing_version = '1.0';
                }
            } else {
                if( in_array( $last_release['state'], $invalid_states ) ){
                    $last_marketing_version = $this->incrementMinorVersion( $last_release['versionString'] );
                } else {
                    $last_marketing_version = $last_release['versionString'];
                }
            }

            $api_url = esc_url_raw(get_rest_url(null, '/better-messages/v1/'));

            $credentials = get_option('better-messages-app-ios-auth', false);

            $return = [
                'platform' => 'ios',
                'type'     => 'production',
                'site_id'  => $fs_site->id,
                'build_info' => [
                    'domain'              => $domain,
                    'app_name'            => $settings['iosAppName'],
                    'app_icon'            => $settings['appIcon'],
                    'app_splash'          => $settings['appSplash'],
                    'version'             => $last_version + 1,
                    'mkt_version'         => $last_marketing_version,
                    'api_url'             => $api_url,
                    'bundle'              => $bundle,
                    'team_id'             => $team_id,
                    'notification_bundle' => $notification_bundle,
                    'credentials'         => $credentials,
                    'plugin_version'      => Better_Messages()->version,
                    'created_at'          => time()
                ]
            ];

            if( $build_info ){
                $return['build_info']['certificate'] = get_option('better-messages-app-ios-certificate-DISTRIBUTION');
                $return['build_info'] = json_encode( $return['build_info'] );
            }

            return $return;
        }

        public function incrementMinorVersion($version) {
            // Split the version string into major and minor parts
            list($major, $minor) = explode('.', $version);

            // Increment the minor version
            $minor++;

            // Return the new version string
            return $major . '.' . $minor;
        }

        public function process_build_request( $data )
        {
            global $wpdb;

            $table = Better_Messages_Mobile_App()->builds_table;

            $request = wp_remote_post('https://builder.better-messages.com/api/requestBuild', array(
                'body' => $data
            ));

            if( ! Better_Messages()->functions->is_response_good( $request ) ){
                return Better_Messages()->functions->is_response_good( $request );
            }

            $response = json_decode($request['body'], true );

            if ( wp_remote_retrieve_response_code($request) != 200 ) {
                if( isset( $response['error'] ) ){
                    return new WP_Error('request_failed', $response['error']);
                } else {
                    return new WP_Error('request_failed', 'The network request failed.');
                }
            }

            $wpdb->insert( $table, [
                'id'         => $response['build']['id'],
                'site_id'    => $data['site_id'],
                'platform'   => $data['platform'],
                'type'       => $data['type'],
                'status'     => 'in-queue',
                'secret'     => $response['build']['secret'],
                'build_info' => $data['build_info'],
            ] );

            return $response['message'];
        }
    }


}

