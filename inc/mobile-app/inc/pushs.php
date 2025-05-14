<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mobile_App_Pushs' ) ):

    class Better_Messages_Mobile_App_Pushs
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Pushs();
            }

            return $instance;
        }

        public function __construct(){
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
            add_filter( 'better_messages_realtime_server_send_data', array( $this, 'sendMessagePush' ), 10, 2 );
            add_filter( 'better_messages_send_mobile_pushs', array( $this, 'sendMobilePush' ), 10, 7 );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1/app', '/savePushToken', array(
                'methods' => 'POST',
                'callback' => array( $this, 'save_push_token' ),
                'permission_callback' => array(Better_Messages_Rest_Api(), 'is_user_authorized')
            ) );
        }

        public function get_user_call_devices( $user_id )
        {
            $devices_table = Better_Messages()->mobile_app->devices_table;

            global $wpdb;

            $sql = $wpdb->prepare("
            SELECT `device_id`, `platform`, `environment`, `app_id`, `push_token`, `push_token_voip`, `device_public_key`
            FROM `{$devices_table}` `devices`
                WHERE user_id = %d
               AND `app_id` != ''
               AND `device_public_key` != ''
               AND ( 
                ( `platform` = 'android' AND  push_token IS NOT NULL  AND `push_token` != '' )
                OR 
                ( `platform` = 'ios' AND  push_token_voip IS NOT NULL  AND `push_token_voip` != '' )
              )
            ", $user_id );

            return $wpdb->get_results( $sql, ARRAY_A );
        }

        public function sendMobilePush( $mobile_pushs, $user_id, $notification, $type, $thread_id, $message_id, $sender_id )
        {

            if( $type === 'call_request' ){
                $mobile_pushs = $this->processCallStart( $user_id, $thread_id, $message_id, $sender_id );
            }

            if( $type === 'call_missed' ){
                //$mobile_pushs = $this->processCallStart( $user_id, $thread_id, $message_id, $sender_id );
            }

            return $mobile_pushs;
        }

        public function processCallStart( $user_id, $thread_id, $message_id, $sender_id )
        {
            $mobileDevices = $this->get_user_call_devices( $user_id );

            $call_type = Better_Messages()->functions->get_message_meta( $message_id, 'type' );

            $payload = [];

            if( count($mobileDevices) > 0 ){
                $platforms = [
                    'ios' => [],
                    'android' => []
                ];

                foreach( $mobileDevices as $mobileDevice ){
                    $platform    = $mobileDevice['platform'];
                    $environment = $mobileDevice['environment'];

                    unset( $mobileDevice['environment'], $mobileDevice['platform'] );
                    if( $platform === 'ios' ){
                        if( ! isset( $platforms['ios'][$environment] ) ){
                            $platforms['ios'][$environment] = [];
                        }

                        if( ! isset( $platforms['ios'][$environment][$user_id] ) ) {
                            $platforms['ios'][$environment][$user_id] = [];
                        }

                        $platforms['ios'][$environment][$user_id][] = $mobileDevice;
                    } else if( $platform === 'android' ) {
                        if( ! isset( $platforms['android'][$environment] ) ){
                            $platforms['android'][$environment] = [];
                        }

                        if( ! isset( $platforms['android'][$environment][$user_id] ) ) {
                            $platforms['android'][$environment][$user_id] = [];
                        }
                        $platforms['android'][$environment][$user_id][] = $mobileDevice;
                    }
                }

                if( $call_type === 'video' ){
                    $title         =  _x('Incoming video call', 'Private Call - Mobile App Push', 'bp-better-messages');
                    $body          = _x('You have incoming video call', 'Private Call - Mobile App Push', 'bp-better-messages');
                } else {
                    $title         =  _x('Incoming voice call', 'Private Call - Mobile App Push', 'bp-better-messages');
                    $body          = _x('You have incoming voice call', 'Private Call - Mobile App Push', 'bp-better-messages');
                }

                $sender_name   = BP_Better_Messages()->functions->get_name( $sender_id );

                if( count( $platforms['ios'] ) > 0 ){
                    $ios_push = [
                        'category' => 'INCOMING_CALL',
                        'expiration' => 0,
                        'type' => 'voip',
                        'alert' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'data' => [
                            "type"      => "incoming_call",
                            "threadId"  => $thread_id,
                            "senderId"  => $sender_id,
                            "messageId" => $message_id,
                            "callType"  => $call_type
                        ],
                        'threadId' => $thread_id
                    ];

                    $has_ios_push = false;

                    $mobile_pushs = [];

                    foreach( $platforms['ios'] as $environment => $users ){
                        foreach( $users as $devices ){
                            foreach( $devices as $device ){
                                $app_id     = $device['app_id'] . '.voip';
                                $push_token = $device['push_token_voip'];
                                $device_id  = $device['device_id'];

                                $public_key = "-----BEGIN PUBLIC KEY-----\n" . $device['device_public_key'] . "\n-----END PUBLIC KEY-----";

                                $push = [
                                    'id'      => $device_id,
                                    'token'   => $push_token,
                                    'push'    => []
                                ];

                                $isValid = openssl_pkey_get_public($public_key);

                                if( $isValid ){
                                    openssl_public_encrypt($sender_name, $encryptedSender, $public_key);
                                    $push['push']['data']['sender'] = base64_encode($encryptedSender);
                                }

                                $has_ios_push = true;

                                if( ! isset( $mobile_pushs[$environment] ) ){
                                    $mobile_pushs[$environment] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id] ) ){
                                    $mobile_pushs[$environment][$app_id] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id][$user_id] ) ){
                                    $mobile_pushs[$environment][$app_id][$user_id] = [];
                                }

                                $mobile_pushs[$environment][$app_id][$user_id][] = $push;
                            }
                        }
                    }

                    if( $has_ios_push ){
                        $jwt = Better_Messages()->mobile_app->ios->get_push_jwt();

                        if( $jwt ) {
                            $payload['ios'] = [
                                'push' => $ios_push,
                                'jwt' =>  $jwt,
                                'pushs' => $mobile_pushs
                            ];
                        }
                    }
                }

                if( count( $platforms['android'] ) > 0 ){
                    $android_push = [
                        'data' => [
                            'category' => 'INCOMING_CALL',
                            'threadId'  => (string) $thread_id,
                            'senderId'  => (string) $sender_id,
                            'messageId' => (string) $message_id
                        ]
                    ];

                    $has_android_push = false;

                    $mobile_pushs = [];


                    $sender_avatar = BP_Better_Messages()->functions->get_rest_avatar( $sender_id );
                    if( defined('BM_DEV') && BM_DEV ){
                        $sender_avatar = 'https://www.wordplus.org/wp-content/uploads/avatars/1/5820b7b52c22b-bpfull.png';
                    }

                    foreach( $platforms['android'] as $environment => $users ){
                        foreach( $users as $user_id => $devices ){
                            foreach( $devices as $device ){
                                $app_id     = $device['app_id'];
                                $push_token = $device['push_token'];
                                $device_id  = $device['device_id'];

                                $public_key = "-----BEGIN PUBLIC KEY-----\n" . $device['device_public_key'] . "\n-----END PUBLIC KEY-----";

                                $push = [
                                    'id'      => $device_id,
                                    'token'   => $push_token,
                                    'push'    => []
                                ];

                                $isValid = openssl_pkey_get_public($public_key);

                                if( $isValid ){
                                    openssl_public_encrypt($sender_name, $encryptedSender, $public_key);
                                    $push['push']['data']['sender'] = base64_encode($encryptedSender);

                                    openssl_public_encrypt($sender_avatar, $encryptedAvatar, $public_key);
                                    $push['push']['data']['avatar'] = base64_encode($encryptedAvatar);
                                }

                                $has_android_push = true;

                                if( ! isset( $mobile_pushs[$environment] ) ){
                                    $mobile_pushs[$environment] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id] ) ){
                                    $mobile_pushs[$environment][$app_id] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id][$user_id] ) ){
                                    $mobile_pushs[$environment][$app_id][$user_id] = [];
                                }

                                $mobile_pushs[$environment][$app_id][$user_id][] = $push;
                            }
                        }
                    }

                    if( $has_android_push ){
                        $payload['android'] = [
                            'push'  => $android_push,
                            'jwt'   => Better_Messages()->mobile_app->android->get_push_jwt(),
                            'pushs' => $mobile_pushs
                        ];
                    }
                }
            }

            return $payload;
        }

        public function processCallEnd( $user_id, $thread_id, $message_id, $sender_id )
        {
            $mobileDevices = $this->get_user_call_devices( $user_id );

            $payload = [];

            if( count($mobileDevices) > 0 ){
                $platforms = [
                    'ios' => [],
                    'android' => []
                ];

                foreach( $mobileDevices as $mobileDevice ){
                    $platform    = $mobileDevice['platform'];
                    $environment = $mobileDevice['environment'];

                    unset( $mobileDevice['environment'], $mobileDevice['platform'] );
                    if( $platform === 'ios' ){
                        if( ! isset( $platforms['ios'][$environment] ) ){
                            $platforms['ios'][$environment] = [];
                        }

                        if( ! isset( $platforms['ios'][$environment][$user_id] ) ) {
                            $platforms['ios'][$environment][$user_id] = [];
                        }

                        $platforms['ios'][$environment][$user_id][] = $mobileDevice;
                    } else if( $platform === 'android' ) {
                        continue;
                        if( ! isset( $platforms['android'][$environment] ) ){
                            $platforms['android'][$environment] = [];
                        }

                        if( ! isset( $platforms['android'][$environment][$user_id] ) ) {
                            $platforms['android'][$environment][$user_id] = [];
                        }
                        $platforms['android'][$environment][$user_id] = $mobileDevice;
                    }
                }

                $title    =  _x('Incoming video call', 'Private Call - Mobile App Push', 'bp-better-messages');
                $body  = _x('You have incoming video call', 'Private Call - Mobile App Push', 'bp-better-messages');

                if( count( $platforms['ios'] ) > 0 ){
                    $ios_push = [
                        'category' => 'CALLS',
                        'expiration' => 0,
                        'type' => 'voip',
                        'alert' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'data' => [
                            "type"      => "incoming_call",
                            "threadId"  => $thread_id,
                            "senderId"  => $sender_id,
                            "messageId" => $message_id,
                        ],
                        'threadId' => $thread_id
                    ];

                    $sender_name   = BP_Better_Messages()->functions->get_name( $sender_id );

                    $has_ios_push = false;

                    $mobile_pushs = [];

                    foreach( $platforms['ios'] as $environment => $users ){
                        foreach( $users as $devices ){
                            foreach( $devices as $device ){
                                $app_id     = $device['app_id'] . '.voip';
                                $push_token = $device['push_token_voip'];
                                $device_id  = $device['device_id'];

                                $public_key = "-----BEGIN PUBLIC KEY-----\n" . $device['device_public_key'] . "\n-----END PUBLIC KEY-----";

                                $push = [
                                    'id'      => $device_id,
                                    'token'   => $push_token,
                                    'push'    => []
                                ];

                                $isValid = openssl_pkey_get_public($public_key);

                                if( $isValid ){
                                    openssl_public_encrypt($sender_name, $encryptedSender, $public_key);
                                    $push['push']['data']['sender'] = base64_encode($encryptedSender);
                                }

                                $has_ios_push = true;

                                if( ! isset( $mobile_pushs[$environment] ) ){
                                    $mobile_pushs[$environment] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id] ) ){
                                    $mobile_pushs[$environment][$app_id] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id][$user_id] ) ){
                                    $mobile_pushs[$environment][$app_id][$user_id] = [];
                                }

                                $mobile_pushs[$environment][$app_id][$user_id][] = $push;
                            }
                        }
                    }

                    if( $has_ios_push ){
                        $jwt = Better_Messages()->mobile_app->ios->get_push_jwt();

                        if( $jwt ) {
                            $payload['ios'] = [
                                'push'  => $ios_push,
                                'jwt'   => $jwt,
                                'pushs' => $mobile_pushs
                            ];
                        }
                    }
                }
            }

            return $payload;
        }


        public function sendMessagePush( $payloadData, $message ){
            if( ! $message->mobile_push ){
                return $payloadData;
            }

            global $wpdb;

            $message_id = (int) $message->id;
            $thread_id  = (int) $message->thread_id;
            $sender_id  = (int) $message->sender_id;
            $is_update  = (bool) $message->is_update;

            $devices_table = Better_Messages()->mobile_app->devices_table;
            $recipients_table = bm_get_table('recipients');

            $sql = $wpdb->prepare("
            SELECT `user_id`, `device_id`, `platform`, `environment`, `app_id`, `push_token`, `device_public_key`
            FROM `{$devices_table}` `devices`
                WHERE user_id IN (SELECT user_id
            FROM `{$recipients_table}` as recipients
               WHERE thread_id = %d
               AND user_id != %d AND is_deleted = 0 AND is_muted = 0 )
               AND push_token IS NOT NULL 
               AND `push_token` != ''
               AND `app_id` != ''
               AND `device_public_key` != ''
            ", $message->thread_id, $message->sender_id );

            $mobileDevices = $wpdb->get_results( $sql, ARRAY_A );
            $payload = [];

            if( count($mobileDevices) > 0 ){
                $platforms = [
                    'ios' => [],
                    'android' => []
                ];

                foreach( $mobileDevices as $mobileDevice ){
                    $platform    = $mobileDevice['platform'];
                    $user_id     = $mobileDevice['user_id'];
                    $environment = $mobileDevice['environment'];

                    unset( $mobileDevice['user_id'], $mobileDevice['environment'], $mobileDevice['platform'] );
                    if( $platform === 'ios' ){
                        if( ! isset( $platforms['ios'][$environment] ) ){
                            $platforms['ios'][$environment] = [];
                        }

                        if( ! isset( $platforms['ios'][$environment][$user_id] ) ) {
                            $platforms['ios'][$environment][$user_id] = [];
                        }

                        $platforms['ios'][$environment][$user_id][] = $mobileDevice;
                    } else if( $platform === 'android' ) {
                        if( ! isset( $platforms['android'][$environment] ) ){
                            $platforms['android'][$environment] = [];
                        }

                        if( ! isset( $platforms['android'][$environment][$user_id] ) ) {
                            $platforms['android'][$environment][$user_id] = [];
                        }
                        $platforms['android'][$environment][$user_id][] = $mobileDevice;
                    }
                }

                $title    = __('New message', 'bp-better-messages');
                $subtitle = Better_Messages()->settings['disableSubject'] === '1' ? "" : BP_Better_Messages()->functions->get_thread_title($thread_id);
                $body  = __('You have new message', 'bp-better-messages');
                $participants = Better_Messages()->functions->get_participants( $thread_id );

                $participants_count    = $participants['count'];
                $multiple_participants = $participants_count > 2;

                $name   = BP_Better_Messages()->functions->get_name( $sender_id );
                $avatar = BP_Better_Messages()->functions->get_rest_avatar( $sender_id );

                $sender_avatar = $avatar;

                if( $multiple_participants ){
                    if( $subtitle !== "" ){
                        $sender_name = $subtitle;
                    } else {
                        $sender_name = sprintf( _x('%s Participants', 'Thread Title (when subjects are disabled)', 'bp-better-messages'), $participants_count );
                    }
                } else {
                    $sender_name = $name;
                }

                if( count( $platforms['ios'] ) > 0 && ! $is_update ){
                    $data = [
                        "threadId"  => $thread_id,
                        "senderId"  => $sender_id,
                        "messageId" => $message_id
                    ];

                    $ios_push = [
                        'collapseId' => 'thread_' . $thread_id . '_message_' . $message_id,
                        'category' => 'NEW_MESSAGE',
                        'alert' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'sound' => 'default',
                        'data' => $data,
                        'aps' => [
                            'mutable-content' => '1'
                        ],
                        'threadId' => $thread_id
                    ];

                    // @todo After tests IOS pushs, did not find a way to update already delivered push notifications, recheck in future
                    //if( $is_update ){
                    //$ios_push['category'] = 'UPDATE_MESSAGE';
                    //}

                    $has_ios_push = false;

                    $mobile_pushs = [];

                    foreach( $platforms['ios'] as $environment => $users ){
                        foreach( $users as $user_id => $devices ){
                            $content = BP_Better_Messages()->functions->format_message($message->message, $message->id, 'mobile_app', $user_id);

                            if( $multiple_participants ){
                                $content = $name . ': ' . $content;
                            }

                            foreach( $devices as $device ){
                                $app_id     = $device['app_id'];
                                $push_token = $device['push_token'];
                                $device_id  = $device['device_id'];

                                $public_key = "-----BEGIN PUBLIC KEY-----\n" . $device['device_public_key'] . "\n-----END PUBLIC KEY-----";

                                $push = [
                                    'id'      => $device_id,
                                    'token'   => $push_token,
                                    'push'    => []
                                ];

                                $isValid = openssl_pkey_get_public($public_key);

                                if( $isValid ){
                                    if( $subtitle !== "" ){
                                        openssl_public_encrypt($subtitle, $encryptedSubtitle, $public_key);
                                        $push['alert']['subtitle'] = base64_encode($encryptedSubtitle);
                                    }

                                    openssl_public_encrypt($sender_name, $encryptedSender, $public_key);
                                    $push['push']['data']['sender'] = base64_encode($encryptedSender);
                                    openssl_public_encrypt($sender_avatar, $encryptedAvatar, $public_key);
                                    $push['push']['data']['avatar'] = base64_encode($encryptedAvatar);
                                    openssl_public_encrypt($content, $encryptedContent, $public_key);
                                    $push['push']['data']['message'] = base64_encode($encryptedContent);
                                }

                                $has_ios_push = true;

                                if( ! isset( $mobile_pushs[$environment] ) ){
                                    $mobile_pushs[$environment] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id] ) ){
                                    $mobile_pushs[$environment][$app_id] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id][$user_id] ) ){
                                    $mobile_pushs[$environment][$app_id][$user_id] = [];
                                }

                                $mobile_pushs[$environment][$app_id][$user_id][] = $push;
                            }
                        }
                    }

                    if( $has_ios_push ){
                        $jwt = Better_Messages()->mobile_app->ios->get_push_jwt();

                        if( $jwt ) {
                            $payload['ios'] = [
                                'push'  => $ios_push,
                                'jwt'   => $jwt,
                                'pushs' => $mobile_pushs
                            ];
                        }
                    }
                }

                if( count( $platforms['android'] ) > 0 ){
                    $android_push = [
                        'data' => [
                            'category' => 'NEW_MESSAGE',
                            'threadId'  => (string) $thread_id,
                            'senderId'  => (string) $sender_id,
                            'messageId' => (string) $message_id
                        ],
                        "android" => [
                            "priority" => "high",
                            "ttl" => "60s"
                        ]
                    ];

                    $has_android_push = false;

                    $mobile_pushs = [];

                    foreach( $platforms['android'] as $environment => $users ){
                        foreach( $users as $user_id => $devices ){
                            $content = BP_Better_Messages()->functions->format_message($message->message, $message->id, 'mobile_app', $user_id );

                            foreach( $devices as $device ){
                                $app_id     = $device['app_id'];
                                $push_token = $device['push_token'];
                                $device_id  = $device['device_id'];

                                $public_key = "-----BEGIN PUBLIC KEY-----\n" . $device['device_public_key'] . "\n-----END PUBLIC KEY-----";

                                $push = [
                                    'id'      => $device_id,
                                    'token'   => $push_token,
                                    'push'    => []
                                ];

                                $isValid = openssl_pkey_get_public($public_key);

                                if( $isValid ){
                                    if( $subtitle !== "" ){
                                        openssl_public_encrypt($subtitle, $encryptedSubtitle, $public_key);
                                        $push['alert']['subtitle'] = base64_encode($encryptedSubtitle);
                                    }

                                    openssl_public_encrypt($sender_name, $encryptedSender, $public_key);
                                    $push['push']['data']['sender'] = base64_encode($encryptedSender);
                                    openssl_public_encrypt($sender_avatar, $encryptedAvatar, $public_key);
                                    $push['push']['data']['avatar'] = base64_encode($encryptedAvatar);
                                    openssl_public_encrypt($content, $encryptedContent, $public_key);
                                    $push['push']['data']['message'] = base64_encode($encryptedContent);
                                }

                                $has_android_push = true;

                                if( ! isset( $mobile_pushs[$environment] ) ){
                                    $mobile_pushs[$environment] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id] ) ){
                                    $mobile_pushs[$environment][$app_id] = [];
                                }

                                if( ! isset( $mobile_pushs[$environment][$app_id][$user_id] ) ){
                                    $mobile_pushs[$environment][$app_id][$user_id] = [];
                                }

                                $mobile_pushs[$environment][$app_id][$user_id][] = $push;
                            }
                        }
                    }

                    if( $has_android_push ){
                        $payload['android'] = [
                            'push'  => $android_push,
                            'jwt'   => Better_Messages()->mobile_app->android->get_push_jwt(),
                            'pushs' => $mobile_pushs
                        ];
                    }
                }
            }

            if( count( $payload ) > 0 ){
                $payloadData['mobile_pushs'] = $payload;
            }

            return $payloadData;
        }

        public function save_push_token( WP_REST_Request $request ): string
        {
            $user_id    = Better_Messages()->functions->get_current_user_id();

            $update = [];

            if( $request->has_param('token') ){
                $update['push_token'] = sanitize_text_field($request->get_param('token'));
            }

            if( $request->has_param('voip_token') ){
                $update['push_token_voip'] = sanitize_text_field($request->get_param('voip_token'));
            }

            if( count( $update ) === 0 ){
                return 'NO';
            }

            Better_Messages_Mobile_App()->functions->update_user_device(
                $user_id,
                Better_Messages()->mobile_app->auth->current_device_id,
                Better_Messages()->mobile_app->auth->current_app_id,
                $update,
                true, false, true
            );

            return 'OK';
        }
    }

endif;

function Better_Messages_Mobile_App_Pushs()
{
    return Better_Messages_Mobile_App_Pushs::instance();
}
