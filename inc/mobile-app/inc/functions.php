<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_Functions' ) ):

    class Better_Messages_Mobile_App_Functions
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Functions();
            }

            return $instance;
        }

        public function __construct(){
        }

        public function get_unsynced_devices()
        {
            global $wpdb;
            $table = Better_Messages_Mobile_App()->devices_table;
            return $wpdb->get_results( "SELECT `id`, `user_id`, `device_id`, `platform`, `environment`, `app_id`, `push_token`, `push_token_voip` FROM {$table} WHERE `waiting_for_sync` = 1", ARRAY_A );
        }

        public function mark_devices_synced( array $device_ids )
        {
            if( count( $device_ids ) === 0 ) return;
            global $wpdb;
            $table = Better_Messages_Mobile_App()->devices_table;
            $device_ids = array_map( 'intval', $device_ids );
            $device_ids = implode( ',', $device_ids );
            $wpdb->query( "UPDATE {$table} SET `waiting_for_sync` = 0 WHERE `id` IN ({$device_ids})" );
        }

        public function get_user_devices( int $user_id ){
            global $wpdb;

            $cache_group = Better_Messages_Mobile_App()->cache_group;

            $cache_key   = 'user_devices_' . $user_id;

            $cached = wp_cache_get( $cache_key, $cache_group );

            if( $cached !== false ){
                return $cached;
            }

            $table = Better_Messages_Mobile_App()->devices_table;

            $user_devices = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );

            wp_cache_set($cache_key, $user_devices, $cache_group);

            return $user_devices;
        }

        public function update_device_last_active( string $device_id, string $app_id )
        {
            global $wpdb;

            $table = Better_Messages_Mobile_App()->devices_table;

            return $wpdb->update($table,
                [
                    'last_active' => current_time('mysql', true )
                ],
                [
                    'device_id' => $device_id,
                    'app_id' => $app_id
                ]
            );
        }

        public function get_device_for_auth( int $user_id, string $device_id, string $app_id ){
            global $wpdb;
            $table = Better_Messages_Mobile_App()->devices_table;
            $device = $wpdb->get_row( $wpdb->prepare("SELECT `id`, `device_id`, `user_id`, `device_public_key`  FROM `{$table}` WHERE `device_id` = %s AND `user_id` = %d AND app_id = %s", $device_id, $user_id, $app_id ), ARRAY_A );
            $device['device_public_key'] = "-----BEGIN PUBLIC KEY-----\n" . $device['device_public_key'] . "\n-----END PUBLIC KEY-----";
            return $device;
        }

        public function update_user_device( int $user_id, string $device_id, string $app_id, array $device, $update_last_active = false, $update_last_login = false, $update_needs_sync = true ){
            global $wpdb;

            $table = Better_Messages_Mobile_App()->devices_table;

            // Define the fields that are expected in the post data
            $info_fields = [
                'name',
                'model',
                'platform',
                'operatingSystem',
                'osVersion',
                'iOSVersion',
                'manufacturer',
                'isVirtual',
                'memUsed',
                'diskFree',
                'diskTotal',
                'realDiskFree',
                'realDiskTotal',
                'webViewVersion',
            ];

            $data   = [];
            $update = [];
            $format = [];

            $fields = [
                'push_token',
                'push_token_voip',
            ];

            foreach ($fields as $field) {
                // Check if the field is available and not empty in the post data
                if ( isset( $device[$field] ) ) {
                    $field_format = is_numeric($device[$field]) ? '%d' : '%s';
                    $field_value = $device[$field];

                    // Add the field to the data array
                    $data[$field] = $field_value;
                    $update[] = $wpdb->prepare("{$field} = {$field_format}", $field_value );

                    $format[] = $field_format;

                    if( ! empty( $field_value ) ) {
                        $wpdb->update($table, [$field => '', 'waiting_for_sync' => 1], [$field => $field_value], ['%s', '%d'], ['%s']);
                    }
                }
            }

            $app_fields = [
                'build',
                'name',
                'version'
            ];

            if( isset( $device['app'] ) && is_array( $device['app'] ) ){
                foreach ($app_fields as $field) {
                    // Check if the field is available and not empty in the post data
                    if ( isset( $device['app'][$field] ) ) {
                        $field_format = is_numeric( $device['app'][$field] ) ? '%d' : '%s';
                        $field_value = $device['app'][$field];

                        $db_field = 'app_' . $field;

                        // Add the field to the data array
                        $data[$db_field] = $field_value;
                        $update[] = $wpdb->prepare("{$db_field} = {$field_format}", $field_value );

                        $format[] = $field_format;
                    }
                }
            }

            // Initialize the data and format arrays
            // Loop through each field
            foreach ($info_fields as $field) {
                // Check if the field is available and not empty in the post data
                if ( isset( $device['info'][$field] ) && ! empty( $device['info'][$field] ) ) {
                    $field_format = is_numeric($device['info'][$field]) ? '%d' : '%s';
                    $field_value = $device['info'][$field];

                    // Add the field to the data array
                    $data[$field] = $field_value;
                    $update[] = $wpdb->prepare("{$field} = {$field_format}", $field_value );
                    //return ["%s = {$field_format}", $field_value,  $wpdb->prepare("%s = {$field_format}", $field, $field_value ) ];

                    // Add the corresponding format to the format array
                    $format[] = $field_format;
                }
            }

            if( isset($device['lang']) ){
                $data['language'] = $device['lang'];
                $format[] = '%s'; // for lang
                $update[] = $wpdb->prepare("language = %s", $data['lang'] );
            }

            if( isset( $device['env'] ) ){
                if( $device['env'] !== 'production' ){
                    $device['env'] = 'development';
                }

                $data['environment'] = $device['env'];
                $format[] = '%s'; // for environment
                $update[] = $wpdb->prepare("environment = %s", $data['environment'] );
            }

            if( isset($device['device_public_key']) ){
                $data['device_public_key'] = $device['device_public_key'];
                $format[] = '%s'; // for device_public_key
                $update[] = $wpdb->prepare("device_public_key = %s", $data['device_public_key'] );
            }

            // Add the device_id, user_id, and last_login to the data and format arrays
            $data['device_id']  = $device_id;
            $format[] = '%s'; // for device_id
            $data['app_id']    = $app_id;
            $format[] = '%s'; // for $app_id
            $data['user_id']    = $user_id;
            $format[] = '%d'; // for user_id
            $update[] = $wpdb->prepare("user_id = %d", $data['user_id'] );

            if( $update_last_login ) {
                $data['last_login'] = current_time('mysql', true);
                $format[] = '%s'; // for last_login
                $update[] = $wpdb->prepare("last_login = %s", $data['last_login'] );
            }

            if( $update_last_active ) {
                $data['last_active'] = current_time('mysql', true);
                $format[] = '%s'; // for last_active
                $update[] = $wpdb->prepare("last_active = %s", $data['last_active'] );

                $data['last_ip'] = Better_Messages()->functions->get_client_ip();
                $format[] = '%s'; // for last_ip
                $update[] = $wpdb->prepare("last_ip = %s", $data['last_ip'] );
            }

            if( $update_needs_sync ){
                $data['waiting_for_sync'] = 1;
                $format[] = '%d'; // for waiting_for_sync
                $update[] = $wpdb->prepare("waiting_for_sync = %d", $data['waiting_for_sync'] );
            }

            $sqlData = array_values($data);

            // Prepare the SQL query
            $sql = $wpdb->prepare(
                "INSERT INTO `{$table}`
                (" . implode(', ', array_keys($data)) . ")
                VALUES (" . implode(', ', $format) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $update),
                $sqlData
            );

            // Execute the SQL query
            $wpdb->query($sql);

            $cache_group = Better_Messages_Mobile_App()->cache_group;
            $cache_key   = 'user_devices_' . $user_id;
            wp_cache_delete( $cache_key, $cache_group );
        }
    }

endif;

function Better_Messages_Mobile_App_Functions()
{
    return Better_Messages_Mobile_App_Functions::instance();
}
