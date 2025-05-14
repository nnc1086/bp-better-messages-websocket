<?php
defined('ABSPATH') || exit;

if (!class_exists('Better_Messages_Mobile_App_Android')):

    class Better_Messages_Mobile_App_Android
    {
        public static function instance(): ?Better_Messages_Mobile_App_Android
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Android();
            }

            return $instance;
        }

        public function __construct()
        {
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1/admin/app', '/android/connectToPlayMarket', array(
                'methods' => 'POST',
                'callback' => array( $this, 'connect_to_play_market' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );


            register_rest_route( 'better-messages/v1/admin/app', '/android/disconnectFromPlayMarket', array(
                'methods' => 'POST',
                'callback' => array( $this, 'disconnect_from_play_market' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );

            register_rest_route( 'better-messages/v1/admin/app', '/android/checkConnection', array(
                'methods' => 'POST',
                'callback' => array( $this, 'check_connection' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );
        }

        public function get_push_jwt(){
            $token = get_transient("better-messages-app-android-push-token");

            if( $token ) return $token;

            $serviceAccount = json_decode('{
                "type": "service_account",
                "project_id": "messenger-development-b164f",
                "private_key_id": "91263761ae324158ae86844b91672d73108da285",
                "private_key": "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCIXQhaFh4xRoNl\napG+TPIEEpB2r5d2sDRA/IOM8FXR21PeAEyXXsEkWmfzB6l2olisILNj85aOnVdd\n+XaowLs7tVSsLLd4MbS8G3fTBDui6YAdTLhbGt+WzqNcdAhHrtW3bLdFPee54Gay\n4lEcE35kKPb5RbITDamqtKq0Ti/4TrHUBmWrJFn7PqAOosaPRsP+1R6JiM0F1Ptm\nelu4y6A0TqRVDWxmC0xNe2RXkTC4wapyO60Y8LO5VcVy2Of2HCycHME4hj9yy3IN\ns0OtavqSu26TNyI069uKGf3/KySfw7fBw5pSrewe//VdDKqQPvUAOPYX3cnEgmeb\nBu5lSoiXAgMBAAECggEAGk88z8jJrX8p1dTgZsOIxEFirwuE4SjxBALUTMqH/FPh\nVAlhvajSAfYRbUHyr8l160vp4KR8TWrNEvwRKVD6LvR28Ds2cNHCSbLRBR0hdnav\nubd2MFm4fuvCeBGJEW+Jm3i1yX2+qk3B8syYkp6uOZvvvrt0NpnhvOZbsysMtU8N\nhX8YeXR6R97dz4W5eH3buaDUzvTTF6b3r0nnyVi2Ccm7cff/inO4Epk4CXq8wsuo\n+ecWDDBe92yYNH43LTI4oaj16j2z/VCPwO2B3Lb89fXHpBBUp8VEUXgrU2VaKzFt\n2U2YIuNrpTZ9+vBeOQZdBExBBS2XWlfCrff0Y26tAQKBgQC8qMgK0R2+IA+iXu4/\n0qpAw5DRmBLKnNzaZejZQPu0OMT5fD9ot+L3NOzJ+s9nUB8ZMi2DQro01tri4qnV\n+HLj+Qowkse6vZfuRHfG9NRw6c7i/acDgz8EDqCDcOsUEK49pvVajk0987FfNm/A\nzey+7QBQxyvF7Vs39OtqUCCvAQKBgQC5CZURpCmUNM82u0kAC9EoaI5NAhxPCAUw\n3BfuX7WdUSHXZOCmjMbeLMRISpSnVYjZefUexEv+Y0NokcZYOdL+cB8oEBgFZqAp\n0DETLKQRj9gyLmZ4MwnMmv9rhY+mdYV6DKUPVtDFeszJKXNQhQO/R1bJGfAb1U7y\n4EBLrgJPlwKBgQC299pK41S9N8rx5q+aJm4IMaMaIyrWZhurlHqneWaj+wrOC7pT\njUQKDMI5gY303LfMb+XED8sXw+i1cq7UXgjPIJDJWxFqAsZ+xtiDlJ8Ugy2q5+Y6\need7v9Pcpn7XDvZtxKbgFHLFSrsTZHAtxYl+Acz0irXhV7nIIzjN+rg4AQKBgBfW\nhzDVoFGqmANqD1aFLzXwelyrZ/A6jUilIiQgimow+JYiNdrfCgO3arYRfaMtHss9\nrfl/unaUXSvMk+vrzyXeVfU4VY/kj7+zRY890gk9KdIVLjhQAvQsB7nXZBFC1KZL\nmLwoKA846ccEowl9iWUMEL8pq0g6q8gYYdAeI8gTAoGAWZxbawrziHkDhcrvxmWo\nDVrw9waA78l1xVC9Yvvo53KWGccGBIy9LpP+LHF9t7rRj4ryLy0tUaAwtLLHUJ71\n2M/VXpx/WSnoQtD+HLZYduER5j3UljV6oCx9xrh4yNhh7xIeDFbUOWt4F64LkhvM\nguBNWSJIY8HB4bpvaHaGSmM=\n-----END PRIVATE KEY-----\n",
                "client_email": "firebase-adminsdk-2oawf@messenger-development-b164f.iam.gserviceaccount.com",
                "client_id": "105873275812454361144",
                "auth_uri": "https://accounts.google.com/o/oauth2/auth",
                "token_uri": "https://oauth2.googleapis.com/token",
                "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
                "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-2oawf%40messenger-development-b164f.iam.gserviceaccount.com",
                "universe_domain": "googleapis.com"
            }', true);

            // create a JWT
            $now_seconds = time();

            $payload = array(
                "iss" => $serviceAccount['client_email'],
                "sub" => $serviceAccount['client_email'],
                "aud" => "https://www.googleapis.com/oauth2/v4/token",
                "iat" => $now_seconds,
                "exp" => $now_seconds+(60*60),  // Maximum expiration time is one hour
                "scope" => "https://www.googleapis.com/auth/cloud-platform"
            );

            $jwt = \BetterMessages\Firebase\JWT\JWT::encode($payload, $serviceAccount['private_key'], "RS256");

            // create a POST request to the Google OAuth2.0 server
            $data = array(
                "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
                "assertion" => $jwt
            );
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data)
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents("https://www.googleapis.com/oauth2/v4/token", false, $context);
            $response = json_decode($result, true);

            $token = $response['access_token'];
            $expiration = 60 * 45; // 45 minutes

            $result = [
                'project_id' => $serviceAccount['project_id'],
                'token' => $token
            ];

            set_transient("better-messages-app-android-push-token", $result, $expiration);

            return [
                'project_id' => $serviceAccount['project_id'],
                'token' => $token
            ];
        }

        public function connect_to_play_market( WP_REST_Request $request )
        {
            $files    = $request->get_file_params();

            $packageName = $request->get_param('packageName');

            if( ! $packageName ){
                return new WP_Error(
                    'rest_error',
                    _x( 'Invalid package name', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            if( ! isset( $files['apiKey'] ) ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Sorry, you are not allowed to do that', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $apiKey = file_get_contents($files['apiKey']['tmp_name']);

            // Try to decode the JSON file
            $json = json_decode($apiKey, true);

            // Check if the JSON file is valid
            if ( ! $json ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Invalid JSON file', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }


            // Check if the JSON file has the required fields
            if ( ! isset( $json['private_key'] ) || ! isset( $json['private_key_id'] ) || ! isset( $json['client_email'] ) || ! isset( $json['token_uri'] ) ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Invalid JSON file', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $scopes = [
                'https://www.googleapis.com/auth/androidpublisher'
            ];

            $jwt = $this->generate_jwt($json['private_key'], $json['private_key_id'], $json['client_email'], $scopes, $json['token_uri']);

            $response = wp_remote_post($json['token_uri'], array(
                'method' => 'POST',
                'body' => array(
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                )
            ));

            if ( is_wp_error($response) ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Failed to response from oauth server ', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $response_body = json_decode( wp_remote_retrieve_body($response), true );

            if (!isset($response_body['access_token'])) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Failed to get access token', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $access_token = $response_body['access_token'];

            $response = wp_remote_get("https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/reviews", array(
                'headers' => array(
                    "Authorization" => "Bearer " . $access_token,
                    "Accept" => "application/json"
                )
            ));

            if( is_wp_error( $response ) ){
                return new WP_Error(
                    'rest_error',
                    _x( 'Failed to get response from Google Developer API', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $response_body = json_decode($response['body'], true);

            if ( isset($response_body['error'] )) {
                // An error occurred
                return new WP_Error(
                    'rest_error',
                    _x( 'Google Developer API returned an error: ' . $response_body['error']['message'], 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => $response_body['error']['code'] )
                );
            }

            update_option( 'better-messages-app-android-auth', [
                'packageName' => $packageName,
                'apiKey'      => $apiKey
            ], false );

            return true;
        }

        public function disconnect_from_play_market( WP_REST_Request $request )
        {
            delete_option( 'better-messages-app-android-auth' );
            return true;
        }
        public function check_connection( WP_REST_Request $request )
        {
            $androidAuth = get_option( 'better-messages-app-android-auth', false );

            if( ! $androidAuth ){
                return new WP_Error(
                    'rest_error',
                    _x( 'No connection data found', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $json = json_decode($androidAuth['apiKey'], true);

            $scopes = [
                'https://www.googleapis.com/auth/androidpublisher'
            ];

            $jwt = $this->generate_jwt($json['private_key'], $json['private_key_id'], $json['client_email'], $scopes, $json['token_uri']);

            $response = wp_remote_post($json['token_uri'], array(
                'method' => 'POST',
                'body' => array(
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                )
            ));

            if ( is_wp_error($response) ) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Failed to get response from Google Developer API', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $response_body = json_decode( wp_remote_retrieve_body($response), true );

            if (!isset($response_body['access_token'])) {
                return new WP_Error(
                    'rest_error',
                    _x( 'Failed to get access token', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $access_token = $response_body['access_token'];

            $response = wp_remote_get("https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$androidAuth['packageName']}/reviews", array(
                'headers' => array(
                    "Authorization" => "Bearer " . $access_token,
                    "Accept" => "application/json"
                )
            ));

            if( is_wp_error( $response ) ){
                return new WP_Error(
                    'rest_error',
                    _x( 'Failed to get response from Google Developer API', 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => 406 )
                );
            }

            $response_body = json_decode($response['body'], true);

            if ( isset($response_body['error'] )) {
                // An error occurred
                return new WP_Error(
                    'rest_error',
                    _x( 'Google Developer API returned an error: ' . $response_body['error']['message'], 'Rest API Error', 'bp-better-messages' ),
                    array( 'status' => $response_body['error']['code'] )
                );
            }

            return true;
        }

        public function generate_jwt( $private_key, $private_key_id, $client_email, $scopes, $audience ): string
        {

            $claim_set = [
                "iss" => $client_email,
                "scope" => implode(' ', $scopes),
                "aud" => $audience,
                "exp" => time() + 3600,
                "iat" => time()
            ];

            return \BetterMessages\Firebase\JWT\JWT::encode($claim_set, $private_key, 'RS256', $private_key_id );
        }

    }

endif;
