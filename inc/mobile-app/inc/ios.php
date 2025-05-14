<?php
defined('ABSPATH') || exit;

if (!class_exists('Better_Messages_Mobile_App_IOS')):

    class Better_Messages_Mobile_App_IOS
    {
        public $push_endpoint = 'https://api.development.push.apple.com';

        public static function instance(): ?Better_Messages_Mobile_App_IOS
        {
            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_IOS();
            }

            return $instance;
        }

        public function __construct()
        {
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1/admin/app', '/ios/connectToAppStore', array(
                'methods' => 'POST',
                'callback' => array( $this, 'connect_to_app_store' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/disconnectFromAppStore', array(
                'methods' => 'POST',
                'callback' => array( $this, 'disconnect_from_app_store' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/checkConnection', array(
                'methods' => 'POST',
                'callback' => array( $this, 'check_connection_to_app_store' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/createBundle', array(
                'methods' => 'POST',
                'callback' => array( $this, 'create_bundle' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/getBundles', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_bundles' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/uploadPushCertificate', array(
                'methods' => 'POST',
                'callback' => array( $this, 'upload_push_certificate' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );


            register_rest_route( 'better-messages/v1/admin/app', '/ios/getCertificates', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_certificates' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );


            register_rest_route( 'better-messages/v1/admin/app', '/ios/checkCertificate', array(
                'methods' => 'POST',
                'callback' => array( $this, 'check_certificate' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/createCertificate', array(
                'methods' => 'POST',
                'callback' => array( $this, 'create_certificate' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/uploadCertificate', array(
                'methods' => 'POST',
                'callback' => array( $this, 'upload_certificate' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/deleteCertificate', array(
                'methods' => 'POST',
                'callback' => array( $this, 'delete_certificate' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/getProfiles', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_profiles' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/getMatchedProfiles', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_matched_profiles' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            /*register_rest_route( 'better-messages/v1/admin/app', '/ios/createProfile', array(
                'methods' => 'POST',
                'callback' => array( $this, 'create_profile' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/getDevices', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_devices' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/ios/addDevice', array(
                'methods' => 'POST',
                'callback' => array( $this, 'add_device' ),
                'permission_callback' => array( $this, 'ensure_api_access' )
            ) );*/


        }

        public function ensure_api_access(){
            $access = get_option('better-messages-app-ios-auth', false);

            if( ! $access ){
                return new WP_Error(
                    'rest_error',
                    _x( 'Sorry, you are not allowed to do that', 'Rest API Error', 'better-pwa-and-mobile-app' ),
                    array( 'status' => 406 )
                );
            }

            return current_user_can( 'manage_options' );
        }

        public function get_settings(): array
        {
            $defaults = [
                'bundleDev'  => '',
                'bundleProd' => '',
            ];

            $settings = get_option('better-messages-ios-settings', $defaults);

            return wp_parse_args( $settings, $defaults );
        }

        public function generate_jwt_token( string $issuerID, string $keyID, string $apiKey, int $expiration = 15 ) : string {
            $claims = [
                'iss' => $issuerID,
                'iat' => time(),
                'exp' => time() + 60 * $expiration,
                'aud' => 'appstoreconnect-v1',
            ];

            $head = [
                "alg" => "ES256",
                "kid" => $keyID,
                "typ" => "JWT"
            ];

            return \BetterMessages\Firebase\JWT\JWT::encode($claims, $apiKey, 'ES256', null, $head);
        }

        public function get_jwt(){
            $access = get_option('better-messages-app-ios-auth', false);
            if( ! $access ) return "";

            return $this->generate_jwt_token( $access['issuerID'], $access['keyID'], $access['apiKey'] );
        }

        public function get_push_jwt(){
            $token = get_transient("better-messages-app-ios-push-token");

            if( $token ) return $token;

            $access = get_option('better-messages-app-ios-push-cert', false);
            if( ! $access ) return "";

            $claims = array(
                "iss" => $access['team_id'],
                "iat" => time(),
            );

            $token = \BetterMessages\Firebase\JWT\JWT::encode($claims, $access['certificate'], 'ES256', $access['key_id']);

            $expiration = 60 * 45; // 45 minutes

            set_transient("better-messages-app-ios-push-token", $token, $expiration);

            return $token;
        }

        public function hasApiErrors($response) {
            // Initialize an empty WP_Error object
            $wpError = new WP_Error();

            // Check if the errors property exists in the error object
            if (property_exists($response, 'errors')) {
                // Loop through each error
                foreach ($response->errors as $error) {
                    // Add each error to the WP_Error object
                    $wpError->add($error->code, $error->detail);
                }
            }

            return $wpError;
        }

        public function get_app_id_from_bundle( $bundleId )
        {
            $jwt = $this->get_jwt();

            $url = "https://api.appstoreconnect.apple.com/v1/apps?filter[bundleId]={$bundleId}";

            $response = wp_remote_get($url, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode($response['body'], true);

            $appId = false;

            if( $response['data'] && count( $response['data'] ) > 0 ){
                foreach ( $response['data'] as $app ){
                    if( $app['attributes']['bundleId'] === $bundleId ){
                        $appId = $app['id'];
                        break;
                    }
                }
            }

            if( ! $appId ){
                return new WP_Error('app_not_found', sprintf( 'The requested app was not found. Make sure that you have created an application for the %s Bundle ID in the Apple App Store.', $bundleId ));
            }

            return $appId;
        }

        public function get_last_build( $appId )
        {
            $jwt = $this->get_jwt();

            $url = "https://api.appstoreconnect.apple.com/v1/builds?filter[app]={$appId}&sort=-version&limit=1";

            $response = wp_remote_get($url, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode($response['body'], true);

            if( $response['data'] ){
                $build = $response['data'][0];

                return [
                    'id' => $build['id'],
                    'version' => $build['attributes']['version'],
                    'uploadedDate' => $build['attributes']['uploadedDate'],
                    'expirationDate' => $build['attributes']['expirationDate'],
                    'expired' => $build['attributes']['expired'],
                    'processingState' => $build['attributes']['processingState'],
                ];
            } else {
                return new WP_Error('build_not_found', 'The last build for application was not found.');
            }

        }

        public function get_last_version( $appId )
        {
            $jwt = $this->get_jwt();

            $url = "https://api.appstoreconnect.apple.com/v1/apps/{$appId}/appStoreVersions";

            $response = wp_remote_get($url, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode($response['body'], true);

            $versions = $response['data'];

            $latest_version = $versions[0];

            if( $latest_version ){
                return [
                    'id' => $latest_version['id'],
                    'platform' => $latest_version['attributes']['platform'],
                    'versionString' => $latest_version['attributes']['versionString'],
                    'appStoreState' => $latest_version['attributes']['appStoreState'],
                    'appVersionState' => $latest_version['attributes']['appVersionState'],
                    'copyright' => $latest_version['attributes']['copyright'],
                    'reviewType' => $latest_version['attributes']['reviewType'],
                    'releaseType' => $latest_version['attributes']['releaseType'],
                    'createdDate' => $latest_version['attributes']['createdDate'],
                ];
            } else {
                return new WP_Error('version_not_found', 'The last version for application was not found.');
            }
        }

        public function get_certificate( string $certificateId )
        {
            $jwt = $this->get_jwt();

            $url = 'https://api.appstoreconnect.apple.com/v1/certificates';

            $url_with_args = add_query_arg([
                'filter' => [ 'id' => $certificateId ]
            ], $url);

            $response = wp_remote_get($url_with_args, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode( $response['body'], true );

            if( $response['data'] ){
                $certificateData = $response['data'][0];

                return [
                    'id' => $certificateData['id'],
                    'serialNumber' => $certificateData['attributes']['serialNumber'],
                    'certificateContent' => $certificateData['attributes']['certificateContent'],
                    'displayName' => $certificateData['attributes']['displayName'],
                    'name' => $certificateData['attributes']['name'],
                    'expirationDate' => $certificateData['attributes']['expirationDate'],
                    'certificateType' => $certificateData['attributes']['certificateType'],
                ];
            } else {
                return new WP_Error('certificate_not_found', 'The requested certificate was not found.');
            }
        }

        public function get_bundle( string $bundleId )
        {
            $jwt = $this->get_jwt();

            $url = 'https://api.appstoreconnect.apple.com/v1/bundleIds';

            $url_with_args = add_query_arg([
                'filter' => [ 'id' => $bundleId ]
            ], $url);

            $response = wp_remote_get($url_with_args, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error( Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode( $response['body'], true );

            if( $response['data'] ){
                $bundleData = $response['data'][0];

                // Get bundle capabilities
                $capabilitiesUrl = 'https://api.appstoreconnect.apple.com/v1/bundleIds/' . $bundleData['id'] . '/bundleIdCapabilities';
                $capabilitiesResponse = wp_remote_get($capabilitiesUrl, [
                    'headers'     => array(
                        'Authorization' => 'Bearer ' . $jwt,
                    ),
                ]);

                if( is_wp_error(Better_Messages()->functions->is_response_good($capabilitiesResponse)) ) {
                    return Better_Messages()->functions->is_response_good($capabilitiesResponse);
                }

                $capabilitiesResponse = json_decode( $capabilitiesResponse['body'], true );

                $capabilities = array_map(function($capability) {
                    return $capability['attributes']['capabilityType'];
                }, $capabilitiesResponse['data']);

                return [
                    'id' => $bundleData['id'],
                    'identifier' => $bundleData['attributes']['identifier'],
                    'name' => $bundleData['attributes']['name'],
                    'platform' => $bundleData['attributes']['platform'],
                    'capabilities' => $capabilities,
                ];
            } else {
                return new WP_Error('bundle_not_found', 'The requested bundle was not found.');
            }
        }

        public function get_provisioning_profile( string $profileId )
        {
            $jwt = $this->get_jwt();

            $url = 'https://api.appstoreconnect.apple.com/v1/profiles';

            $url_with_args = add_query_arg([
                'filter' => [ 'id' => $profileId ],
                'include' => 'devices,certificates'
            ], $url);

            $response = wp_remote_get($url_with_args, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode( $response['body'], true );

            if( $response['data'] ){
                $profileData = $response['data'][0];

                $devices = [];
                $certificates = [];
                if (isset($response['included'])) {
                    foreach ($response['included'] as $included) {
                        if ($included['type'] === 'devices') {
                            $devices[] = [
                                'id' => $included['id'],
                                'name' => $included['attributes']['name'],
                                'udid' => $included['attributes']['udid'],
                                'platform' => $included['attributes']['platform'],
                            ];
                        }
                        if ($included['type'] === 'certificates') {
                            $certificates[] = [
                                'id' => $included['id'],
                                'serialNumber' => $included['attributes']['serialNumber'],
                                'certificateContent' => $included['attributes']['certificateContent'],
                                'displayName' => $included['attributes']['displayName'],
                                'name' => $included['attributes']['name'],
                                'expirationDate' => $included['attributes']['expirationDate'],
                                'certificateType' => $included['attributes']['certificateType'],
                            ];
                        }
                    }
                }

                return [
                    'id' => $profileData['id'],
                    'profileState' => $profileData['attributes']['profileState'],
                    'createdDate' => $profileData['attributes']['createdDate'],
                    'profileType' => $profileData['attributes']['profileType'],
                    'name' => $profileData['attributes']['name'],
                    'profileContent' => $profileData['attributes']['profileContent'],
                    'uuid' => $profileData['attributes']['uuid'],
                    'platform' => $profileData['attributes']['platform'],
                    'expirationDate' => $profileData['attributes']['expirationDate'],
                    'devices' => $devices,
                    'certificates' => $certificates,
                ];
            } else {
                return new WP_Error('profile_not_found', 'The requested provisioning profile was not found.');
            }
        }

        public function create_bundle( WP_REST_Request $request ){
            $jwt = $this->get_jwt();

            // Get the bundle identifier and name from the request
            $bundleIdentifier = $request->get_param('bundleIdentifier');
            $bundleName = $request->get_param('bundleName');

            // Prepare the data for the request
            $data = [
                'data' => [
                    'type' => 'bundleIds',
                    'attributes' => [
                        'identifier' => $bundleIdentifier,
                        'name' => $bundleName,
                        'platform' => 'IOS' // or 'MAC_OS' or 'UNIVERSAL'
                    ]
                ]
            ];

            // Send the POST request to the API
            $response = wp_remote_post('https://api.appstoreconnect.apple.com/v1/bundleIds', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($data)
            ]);

            // Check if the request was successful
            if (is_wp_error($response)) {
                // Handle the error
                return new WP_Error('request_failed', 'The network request failed.');
            }

            // Parse the response
            $response = json_decode($response['body'], true);

            // Check if the bundle was created successfully
            if ( isset( $response['data'] ) ) {
                // The bundle was created successfully
                return $response['data'];
            } else {
                // The bundle was not created successfully
                return new WP_Error('bundle_creation_failed', 'The bundle was not created successfully.');
            }
        }

        public function get_bundles( WP_REST_Request $request ){
            $jwt = $this->get_jwt();

            $response = wp_remote_get('https://api.appstoreconnect.apple.com/v1/bundleIds?limit=200', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            $response = json_decode($response['body']);
            $data = $response->data;

            $result = [];

            if( count( $data ) > 0 ) {
                foreach ($data as $item) {
                    $item_id = $item->id;
                    $name = $item->attributes->name;
                    $identifier = $item->attributes->identifier;
                    $result[] = [ 'id' => $item_id, 'identifier' => $identifier, 'name' => $name ];
                }
            }

            return $result;
        }

        public function check_certificate(WP_REST_Request $request)
        {
            $type = $request->get_param('type');


            if( $type === 'DEVELOPMENT' ){
                $settings_key = 'iosCertificateDev';
                $allowed_cert_types = [
                    'DEVELOPMENT',
                    'IOS_DEVELOPMENT'
                ];
            } elseif ( $type === 'DISTRIBUTION' ) {
                $settings_key = 'iosCertificateProd';
                $allowed_cert_types = [
                    'DISTRIBUTION',
                    'IOS_DISTRIBUTION'
                ];
            } else {
                return new WP_Error('certificate_type_error', 'The certificate type is not valid.');
            }

            $settings = Better_Messages()->mobile_app->settings->get_settings();

            if( ! isset( $settings[$settings_key] ) || empty( $settings[$settings_key] ) ){
                return new WP_Error('certificate_not_found', 'The certificate was not found.');
            }

            $db_certificate = get_option('better-messages-app-ios-certificate-' . $type, false);
            if( ! $db_certificate ){
                return new WP_Error('certificate_not_found', 'The certificate was not found.');
            }

            $certificate_id = $settings[$settings_key];

            $certificate = $this->get_certificate( $certificate_id );
            if( is_wp_error( $certificate ) ) return $certificate;

            if( ! in_array( $certificate['certificateType'], $allowed_cert_types )   ){
                return new WP_Error('certificate_type_error', 'The certificate type is not valid.');
            }

            return [
                'id' => $certificate['id'],
                'serialNumber' => $certificate['serialNumber'],
                'certificateType' => $certificate['certificateType'],
                'expirationDate' => $certificate['expirationDate'],
                'displayName' => $certificate['displayName'],
                'name' => $certificate['name'],
            ];
        }

        public function upload_push_certificate( WP_REST_Request $request )
        {
            if ( ! isset($_FILES['file']) ) {
                // Handle the error
                return new WP_Error('no_file_uploaded', 'No file was uploaded.');
            }

            // Access the uploaded file
            $file = $request->get_file_params()['file'];

            $team_id = $request->get_param('team_id');

            if( ! $team_id ){
                return new WP_Error('team_id_missing', 'The team ID is missing.');
            }

            $key_id = $request->get_param('key_id');

            if( ! $key_id ){
                return new WP_Error('key_id_missing', 'The key ID is missing.');
            }

            $certificate = file_get_contents($file['tmp_name']);

            // Create the JWT payload
            $payload = [
                'iss' => $team_id,
                'iat' => time(),
            ];

            try {
                // Sign the JWT
                \BetterMessages\Firebase\JWT\JWT::encode($payload, $certificate, 'ES256', $key_id);

                update_option( 'better-messages-app-ios-push-cert', [
                    'team_id' => $team_id,
                    'key_id' => $key_id,
                    'certificate' => $certificate
                ], false );

                delete_transient('better-messages-app-ios-push-token');
                return true;
            } catch (Exception $e) {
                return new WP_Error('certificate_error', 'The certificate is not valid.');
            }

            //$certificate = openssl_x509_parse($cer_content);

        }

        public function get_certificates( WP_REST_Request $request ){
            $jwt = $this->get_jwt();

            $response = wp_remote_get('https://api.appstoreconnect.apple.com/v1/certificates?limit=200', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            $response = json_decode($response['body']);
            $data = $response->data;

            $result = [];

            if( count( $data ) > 0 ) {
                foreach ($data as $item) {
                    $id = $item->id;
                    $serialNumber = $item->attributes->serialNumber;
                    $certificateContent = $item->attributes->certificateContent;
                    $displayName = $item->attributes->displayName;
                    $name = $item->attributes->name;
                    $expirationDate = $item->attributes->expirationDate;
                    $certificateType = $item->attributes->certificateType;

                    $result[] = [
                        'id' => $id,
                        'serialNumber' => $serialNumber,
                        'certificateContent' => $certificateContent,
                        'displayName' => $displayName,
                        'name' => $name,
                        'expirationDate' => $expirationDate,
                        'certificateType' => $certificateType,
                    ];
                }
            }

            return $result;
        }

        /*public function get_bundle_capabilities(){
            $jwt = $this->get_jwt();

            $response = wp_remote_get('https://api.appstoreconnect.apple.com/v1/bundleIds/54YSTNSYN4/bundleIdCapabilities', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                )
            ]);

            $response = json_decode($response['body']);

            $data = $response->data;

            $result = [];

            if( count( $data ) > 0 ) {
                foreach ($data as $item) {
                    $result[] =  $item->attributes->capabilityType;
                }
            }

            return $result;
        }*/

        public function get_matched_profiles( WP_REST_Request $request ){
            $bundleId      = $request->get_param('bundleId');
            $certificateId = $request->get_param( 'certificateId' );
            $type          = $request->get_param( 'type' );

            if( ! $bundleId || ! $certificateId || ! $type ){
                return new WP_Error('missing_params', 'The required parameters are missing.');
            }

            if( $type !== 'IOS_APP_DEVELOPMENT' && $type !== 'IOS_APP_STORE' ){
                return new WP_Error('profile_type_error', 'The profile type is not valid.');
            }

            $jwt = $this->get_jwt();

            $url = add_query_arg([
                'include' => 'bundleId,certificates',
                'filter' => [
                    'profileState' => 'ACTIVE',
                    'profileType' => $type
                ],
                'limit' => 200
            ], 'https://api.appstoreconnect.apple.com/v1/profiles');

            $response = wp_remote_get($url, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                )
            ]);

            $response = json_decode($response['body']);
            $data = $response->data;

            $result = [];

            if( count( $data ) > 0 ){
                foreach ( $data as $item ){
                    $itemBundleId = $item->relationships->bundleId->data->id;
                    if( $itemBundleId !== $bundleId ) continue;
                    $certificates = $item->relationships->certificates->data;

                    $has_certificate = false;
                    foreach ( $certificates as $certificate ){
                        if( $certificate->id === $certificateId ) $has_certificate = true;
                    }

                    if( $has_certificate ){
                        $result[] = [
                            'id' => $item->id,
                            'profileState' => $item->attributes->profileState,
                            'createdDate' => $item->attributes->createdDate,
                            'profileType' => $item->attributes->profileType,
                            'name' => $item->attributes->name,
                            'profileContent' => $item->attributes->profileContent,
                            'uuid' => $item->attributes->uuid,
                            'platform' => $item->attributes->IOS,
                            'expirationDate' => $item->attributes->expirationDate,
                        ];
                    }
                }
            }

            return $result;
        }

        /*public function add_device( WP_REST_Request $request ){
            $deviceId = $request->get_param('deviceId');
            $settings = Better_Messages()->mobile_app->settings->get_settings();

            $iosDevices = $settings['iosDevices'];
            $iosDevices[] = $deviceId;

            $settings['iosDevices'] = array_unique($iosDevices);

            Better_Messages()->mobile_app->settings->update_settings( $settings );

            return true;
        }*/

        public function get_devices(){
            $jwt = $this->get_jwt();

            $url = add_query_arg([
                'limit' => 200
            ], 'https://api.appstoreconnect.apple.com/v1/devices');

            $response = wp_remote_get($url, [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                )
            ]);

            if( is_wp_error( Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode($response['body']);
            $data = $response->data;

            $result = [];

            if( count( $data ) > 0 ) {
                foreach ($data as $item) {
                    $id = $item->id;
                    $status = $item->attributes->status;

                    if( $status === 'ENABLED' ) {
                        $result[] = [
                            'id' => $id,
                            'addedDate' => $item->attributes->addedDate,
                            'name' => $item->attributes->name,
                            'deviceClass' => $item->attributes->deviceClass,
                            'model' => $item->attributes->model,
                            'udid' => $item->attributes->udid,
                            'platform' => $item->attributes->platform,
                            'status' => $item->attributes->status
                        ];
                    }
                }
            }

            return $result;
        }

        public function get_profiles( WP_REST_Request $request ){
            $jwt = $this->get_jwt();

            $response = wp_remote_get('https://api.appstoreconnect.apple.com/v1/profiles?limit=200', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                )
            ]);

            $response = json_decode($response['body']);
            $data = $response->data;

            $result = [];

            if( count( $data ) > 0 ) {
                foreach ($data as $item) {
                    $id = $item->id;

                    $result[] = [
                        'id' => $id,
                        'profileState' => $item->attributes->profileState,
                        'createdDate' => $item->attributes->createdDate,
                        'profileType' => $item->attributes->profileType,
                        'name' => $item->attributes->name,
                        'profileContent' => $item->attributes->profileContent,
                        'uuid' => $item->attributes->uuid,
                        'platform' => $item->attributes->IOS,
                        'expirationDate' => $item->attributes->expirationDate,
                    ];
                }
            }

            return $result;
        }

        public function generate_csr(){
            $dn = array(
                "countryName" => "UA",
                "stateOrProvinceName" => "Odesska",
                "localityName" => "Odessa",
                "organizationName" => "WordPlus",
                "organizationalUnitName" => "Company",
                "commonName" => "WordPlus",
                "emailAddress" => "csr@wordplus.org"
            );

            $password = Better_Messages()->functions->generateRandomString(10);

            $privkey = openssl_pkey_new(array(
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ));

            openssl_pkey_export( $privkey, $pk, $password );

            $csr = openssl_csr_new($dn, $privkey, array('digest_alg' => 'sha256'));
            openssl_csr_export($csr, $csrString);

            return [
                'pass'  => $password,
                'pkcs8' => trim( $pk ),
                'csr'   => trim( $csrString )
            ];
        }


        public function create_certificate( WP_REST_Request $request ){
            $jwt  = $this->get_jwt();

            $certificateType = $request->get_param('type');

            if( $certificateType === 'DEVELOPMENT' ){
                $settings_key = 'iosCertificateDev';
                $target_type = 'IOS_DEVELOPMENT';
            } elseif ( $certificateType === 'DISTRIBUTION' ) {
                $settings_key = 'iosCertificateProd';
                $target_type = 'IOS_DISTRIBUTION';
            } else {
                return new WP_Error('certificate_type_error', 'The certificate type is not valid.');
            }
            $cert = $this->generate_csr();

            $response = wp_remote_post('https://api.appstoreconnect.apple.com/v1/certificates', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                ),
                'body'        => json_encode([
                    'data' => [
                        'attributes' => [
                            'certificateType' => $target_type,
                            'csrContent' => $cert['csr']
                        ],
                        'type' => 'certificates'
                    ]
                ]),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            $response = json_decode($response['body']);

            $api_errors = $this->hasApiErrors($response);

            if( $api_errors->has_errors() ){
                return $api_errors;
            }

            $data = $response->data;

            $certificate = [
                'id' => $data->id,
                'serialNumber' => $data->attributes->serialNumber,
                'certificateContent' => $data->attributes->certificateContent,
                'displayName' => $data->attributes->displayName,
                'name' => $data->attributes->name
            ];

            $cert['certificate'] = $certificate;

            $developmentCer = base64_decode($cert['certificate']['certificateContent']);

            $x509_cert = openssl_x509_read( $this->makeX509Cert( $developmentCer ) );
            $pk = $cert['pkcs8'];
            $password = $cert['pass'];

            $private_key = openssl_get_privatekey( $pk, $password );
            openssl_pkcs12_export( $x509_cert, $p12, $private_key, $password );

            $cert['p12'] = base64_encode($p12);

            $key = 'better-messages-app-ios-certificate-' . $certificateType;
            update_option( $key, $cert, false );

            $settings = Better_Messages()->mobile_app->settings->get_settings();

            $settings[$settings_key] = $data->id;

            Better_Messages()->mobile_app->settings->update_settings( $settings );

            return $settings;
        }

        public function upload_certificate( WP_REST_Request $request )
        {
            if (!isset($_FILES['file'])) {
                // Handle the error
                return new WP_Error('no_file_uploaded', 'No file was uploaded.');
            }

            // Access the uploaded file
            $file = $request->get_file_params()['file'];

            // Access the password
            $certificate_id = $request->get_param('certificate');


            $password = $request->get_param('password');
            $certificateType = $request->get_param('type');

            if( $certificateType === 'DEVELOPMENT' ){
                $settings_key = 'iosCertificateDev';
                $allowed_cert_types = [
                    'DEVELOPMENT',
                    'IOS_DEVELOPMENT'
                ];
            } elseif ( $certificateType === 'DISTRIBUTION' ) {
                $settings_key = 'iosCertificateProd';
                $allowed_cert_types = [
                    'DISTRIBUTION',
                    'IOS_DISTRIBUTION'
                ];
            } else {
                return new WP_Error('certificate_type_error', 'The certificate type is not valid.');
            }

            $certificate = $this->get_certificate( $certificate_id );

            if( is_wp_error($certificate) ){
                return $certificate;
            }

            if( ! in_array($certificate['certificateType'], $allowed_cert_types) ){
                return new WP_Error('certificate_type_mismatch', 'The certificate type does not allowed here.');
            }

            $p12_file_content = file_get_contents($file['tmp_name']);

            $result = openssl_pkcs12_read($p12_file_content, $certs, $password);

            if( ! $result ){
                return new WP_Error('p12_password_error', 'The .p12 file is not valid or the password is incorrect.');
            } else {
                $p12_cert = trim(str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n"], '', $certs['cert']));

                if( $p12_cert !== $certificate['certificateContent'] ){
                    return new WP_Error('p12_cert_error', 'The .p12 file is not matching the selected certificate.');
                }

                $cert = [
                    'id'  => 'manual',
                    'p12' => base64_encode($p12_file_content),
                    'pass' => $password
                ];

                $key = 'better-messages-app-ios-certificate-' . $certificateType;
                update_option( $key, $cert, false );

                $settings = Better_Messages()->mobile_app->settings->get_settings();

                $settings[$settings_key] = $certificate['id'];

                Better_Messages()->mobile_app->settings->update_settings( $settings );

                return $settings;
            }
        }

        public function delete_certificate( WP_REST_Request $request )
        {
            $certificateType = $request->get_param('type');

            if( $certificateType === 'DEVELOPMENT' ){
                $settings_key = 'iosCertificateDev';
            } elseif ( $certificateType === 'DISTRIBUTION' ) {
                $settings_key = 'iosCertificateProd';
            } else {
                return new WP_Error('certificate_type_error', 'The certificate type is not valid.');
            }

            $key = 'better-messages-app-ios-certificate-' . $certificateType;

            delete_option( $key );

            $settings = Better_Messages()->mobile_app->settings->get_settings();
            $settings[$settings_key] = '';
            Better_Messages()->mobile_app->settings->update_settings( $settings );

            return $settings;
        }

        public function makeX509Cert($bindata) {
            $beginpem = "-----BEGIN CERTIFICATE-----\n";
            $endpem = "-----END CERTIFICATE-----\n";

            $pem = $beginpem;
            $cbenc = base64_encode($bindata);
            for($i = 0; $i < strlen($cbenc); $i++) {
                $pem .= $cbenc[$i];
                if (($i + 1) % 64 == 0)
                    $pem .= "\n";
            }
            $pem .= "\n".$endpem;

            return $pem;
        }

        public function create_profile( WP_REST_Request $request ){
            $settings = Better_Messages()->mobile_app->settings->get_settings();

            $jwt = $this->get_jwt();

            $type = 'IOS_APP_DEVELOPMENT'; // https://developer.apple.com/documentation/appstoreconnectapi/profilecreaterequest/data/attributes

            $bundleId      = $request->get_param('bundleId');
            $certificateId = $request->get_param('certificateId');
            $key           = $request->get_param('key');

            switch ($key){
                case 'iosProfileDev':
                    $profileFor = 'dev-application';
                    break;
                case 'iosProfileServiceDev':
                    $profileFor = 'dev-service';
                    break;

                default:
                    return new WP_Error(
                        'rest_forbidden',
                        _x( 'Sorry, you are not allowed to do that', 'Rest API Error', 'better-pwa-and-mobile-app' ),
                        array( 'status' => 401 )
                    );
            }

            $devices = (array) $settings['iosDevices'];

            if( count( $devices ) === 0 ) {
                return new WP_Error(
                    'rest_forbidden',
                    'Development profile cant be created without selected devices',
                    array( 'status' => 401 )
                );
            }

            $devices_args = [];
            foreach ( $devices as $device ){
                $devices_args[] = [
                    'id'   => $device,
                    'type' => 'devices'
                ];
            }

            $args = [
                'data' => [
                    'attributes' => [
                        'name'        => 'Better Messages Dev Profile (BundleId ' . $bundleId . ')',
                        'profileType' => $type
                    ],
                    'relationships' => [
                        'bundleId' => [
                            'data' => [
                                'id' => $bundleId,
                                'type' => 'bundleIds'
                            ]
                        ],
                        'certificates' => [
                            'data' => [
                                [
                                    'id' => $certificateId,
                                    'type' => 'certificates'
                                ]
                            ]
                        ],
                        'devices' => [
                            'data' => $devices_args
                        ]
                    ],
                    'type' => 'profiles'
                ]
            ];

            $response = wp_remote_post('https://api.appstoreconnect.apple.com/v1/profiles', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json'
                ),
                'body'        => json_encode($args),
            ]);

            $response = json_decode($response['body']);
            $item = $response->data;
            $profile = [
                'id' => $item->id,
                'profileState' => $item->attributes->profileState,
                'createdDate' => $item->attributes->createdDate,
                'profileType' => $item->attributes->profileType,
                'name' => $item->attributes->name,
                'profileContent' => $item->attributes->profileContent,
                'uuid' => $item->attributes->uuid,
                'platform' => $item->attributes->IOS,
                'expirationDate' => $item->attributes->expirationDate,
            ];

            update_option( 'better-messages-ios-profile-' . $profileFor, $profile, false );
        }

        public function connect_to_app_store( WP_REST_Request $request ){
            $keyID    = $request->get_param('keyID');
            $issuerID = $request->get_param('issuerID');
            $files    = $request->get_file_params();

            if( ! isset( $files['apiKey'] ) ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Sorry, you are not allowed to do that', 'Rest API Error', 'better-pwa-and-mobile-app' ),
                    array( 'status' => 406 )
                );
            }

            $apiKey = file_get_contents($files['apiKey']['tmp_name']);

            $jwt = $this->generate_jwt_token($issuerID, $keyID, $apiKey);

            $response = wp_remote_get('https://api.appstoreconnect.apple.com/v1/apps', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            if( $response['response'] ){
                $code = $response['response']['code'];
                if( $code === 200 ){
                    update_option( 'better-messages-app-ios-auth', [
                        'keyID'    => $keyID,
                        'issuerID' => $issuerID,
                        'apiKey'   => $apiKey
                    ], false );

                    return [
                        'result'  => 'success'
                    ];
                } else {
                    return new WP_Error(
                        'rest_error',
                        _x( 'Connection to Apple Developer Account was not successful', 'WP Admin', 'better-pwa-and-mobile-app' ),
                        array( 'status' => $code )
                    );
                }
            }
        }

        public function check_connection_to_app_store()
        {
            $credentials = get_option('better-messages-app-ios-auth', false);

            if ( ! $credentials ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Credentials not found', 'Rest API Error', 'better-pwa-and-mobile-app' ),
                    array( 'status' => 406 )
                );
            }

            $keyID    = $credentials['keyID'];
            $issuerID = $credentials['issuerID'];
            $apiKey   = $credentials['apiKey'];

            $jwt = $this->generate_jwt_token($issuerID, $keyID, $apiKey);

            $response = wp_remote_get('https://api.appstoreconnect.apple.com/v1/apps', [
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $jwt,
                ),
            ]);

            if( is_wp_error(Better_Messages()->functions->is_response_good($response)) ) {
                return Better_Messages()->functions->is_response_good($response);
            }

            if( $response['response'] ){
                $code = $response['response']['code'];
                if( $code === 200 ){
                    return [
                        'result'  => 'success'
                    ];
                } else {
                    return new WP_Error(
                        'rest_error',
                        _x( 'Connection to Apple Developer Account was not successful', 'WP Admin', 'better-pwa-and-mobile-app' ),
                        array( 'status' => $code )
                    );
                }
            }
        }

        public function disconnect_from_app_store()
        {
            delete_option('better-messages-app-ios-auth');
            return true;
        }


    }

endif;

function Better_Messages_Mobile_App_IOS()
{
    return Better_Messages_Mobile_App_IOS::instance();
}
