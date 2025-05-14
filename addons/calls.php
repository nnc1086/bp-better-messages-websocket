<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Calls' ) ):

    class Better_Messages_Calls
    {
        public $audio = false;

        public $video = false;

        public $revertIcons = false;

        public $fastCall    = false;

        public static function instance()
        {
            static $instance = null;

            if ( null === $instance ) {
                $instance = new Better_Messages_Calls();
            }

            return $instance;
        }


        public function __construct()
        {
            $this->audio       = Better_Messages()->settings['audioCalls'] === '1';
            $this->video       = Better_Messages()->settings['videoCalls'] === '1';
            $this->revertIcons = Better_Messages()->settings['callsRevertIcons'] === '0';

            if( Better_Messages()->settings['callsLimitFriends'] === '1' ){
                add_filter('bp_better_messages_can_audio_call', array( $this, 'restrict_non_friends_calls'), 10, 3 );
                add_filter('bp_better_messages_can_video_call', array( $this, 'restrict_non_friends_calls'), 10, 3 );
            }

            if( Better_Messages()->settings['profileAudioCall'] === '1' || Better_Messages()->settings['profileVideoCall'] === '1' ) {

                if ( function_exists('bp_get_theme_package_id') && bp_get_theme_package_id() == 'nouveau' ) {
                    add_action('bp_nouveau_get_members_buttons', array($this, 'profile_call_button'), 10, 3);
                } else {
                    add_action( 'bp_member_header_actions', array( $this, 'profile_call_button_legacy' ), 21 );
                }

                #add_action('youzify_social_buttons', array( $this, 'profile_call_button_legacy' ), 10, 1 );
            }

            if( isset(Better_Messages()->settings['restrictCalls'])
                && is_array(Better_Messages()->settings['restrictCalls'])
                && count(Better_Messages()->settings['restrictCalls']) > 0
            ) {
                add_filter( 'bp_better_messages_script_variable', array( $this, 'disable_calls_for_restricted_role' ), 10, 1 );
            }


            add_action( 'init', array($this, 'catch_fast_call') );

            /**
             * Grimlock profile call button
             */
            if( defined('GRIMLOCK_BUDDYPRESS_VERSION') ) {
                add_action('bp_member_header_actions', array($this, 'grimlock_profile_call_button'), 20);
            }

            if( defined('YOUZIFY_VERSION') ) {
                add_action('youzify_social_buttons', array($this, 'youzify_profile_call_button'), 20, 1);
            }

            add_filter( 'bp_nouveau_customizer_user_profile_actions', array($this, 'bp_nouveau_customizer_user_profile_actions'), 20, 1 );

            add_filter( 'better_messages_rest_thread_item', array( $this, 'rest_thread_item'), 10, 4 );
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ), 9 );
            add_action( 'better_messages_register_script_dependencies', array( $this, 'load_scripts' ), 9 );
        }

        public $scripts_loaded = false;

        public function load_scripts(){
            if( $this->scripts_loaded ) return;

            $this->scripts_loaded = true;

            $is_dev = defined( 'BM_DEV' );
            $version = Better_Messages()->version;
            $suffix = ( $is_dev ? '' : '.min' );

            wp_register_script(
                'better-messages-media',
                Better_Messages()->url . "assets/js/addons/calls/media{$suffix}.js",
                [],
                $version
            );

            add_filter('better_messages_script_dependencies', function( $deps ) {
                if( ! in_array( 'better-messages-media', $deps ) ) {
                    $deps[] = 'better-messages-media';
                }

                return $deps;
            } );
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1', '/callStart', array(
                'methods' => 'POST',
                'callback' => array( $this, 'call_start' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/callCreate', array(
                'methods' => 'POST',
                'callback' => array( $this, 'call_create' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/joinCall', array(
                'methods' => 'POST',
                'callback' => array( $this, 'join_call' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/callStarted', array(
                'methods' => 'POST',
                'callback' => array( $this, 'register_started_call' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/callUsage', array(
                'methods' => 'POST',
                'callback' => array( $this, 'register_call_usage' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/callMissed', array(
                'methods' => 'POST',
                'callback' => array( $this, 'record_missed_call' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/offlineCall', array(
                'methods' => 'POST',
                'callback' => array( $this, 'record_offline_call' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

        }

        public function calculate_call_traffic( $message_id ): array
        {
            $stats = Better_Messages()->functions->get_message_meta( $message_id, 'stats' );

            $bytesReceived = 0;
            $bytesSent = 0;

            if( $stats ) {
                if( $stats['bytes_received'] ){
                    $bytesReceived = (int) $stats['bytes_received'];
                }

                if( $stats['bytes_sent'] ){
                    $bytesSent = (int) $stats['bytes_sent'];
                }
            }

            return [
                'sent'     => $bytesSent,
                'received' => $bytesReceived
            ];
        }

        public function call_has_confirmed_traffic( $message_id ): bool
        {
            $traffic = $this->calculate_call_traffic( $message_id );

            if( $traffic['sent'] > 20000 || $traffic['received'] > 20000 ){
                return true;
            }

            return false;
        }

        public function join_call( WP_REST_Request $request )
        {
            $user_id = Better_Messages()->functions->get_current_user_id();
            $thread_id       = intval( $request->get_param('thread_id') );
            $message_id      = intval( $request->get_param('message_id') );
            $type            = $request->get_param('type');

            $thread = Better_Messages()->functions->get_thread( $thread_id );

            if( ! $thread ){
                return [
                    'result'     => 'not_allowed',
                    'errors' => _x('Conversation not found', 'Private Call - Rest API Error', 'bp-better-messages')
                ];
            }

            $custom_error = apply_filters('better_messages_call_join_custom_error', "", $thread_id, $user_id, $type );

            if( ! empty( $custom_error ) ){
                return [
                    'result' => 'not_allowed',
                    'errors' =>  $custom_error
                ];
            }

            $is_participant = Better_Messages()->functions->is_thread_participant( $user_id, $thread_id );

            if( ! $is_participant ){
                return [
                    'result' => 'not_allowed',
                    'errors' =>  _x('You are not a participant of this conversation', 'Private Call - Rest API Error', 'bp-better-messages')
                ];
            }

            $is_call = Better_Messages()->functions->get_message_meta( $message_id, 'bpbm_call' );

            if( ! $is_call ){
                return [
                    'result' => 'not_allowed',
                    'errors' =>  _x('This is not a call', 'Private Call - Rest API Error', 'bp-better-messages')
                ];
            }

            $encryption_key = Better_Messages()->functions->get_message_meta( $message_id, 'call_encryption_key', true );

            $token = $this->joinRoom( $thread_id, $message_id, $user_id );

            return [
                'result'         => 'allowed',
                'token'          => $token,
                'encryption_key' => $encryption_key
            ];
        }

        public function call_create( WP_REST_Request $request )
        {
            $current_user_id = Better_Messages()->functions->get_current_user_id();
            $thread_id       = intval( $request->get_param('thread_id') );
            $type            = $request->get_param('type');

            $thread = Better_Messages()->functions->get_thread( $thread_id );

            if( ! $thread ){
                return [
                    'result'     => 'not_allowed',
                    'errors' => _x('Conversation not found', 'Private Call - Rest API Error', 'bp-better-messages')
                ];
            }

            $custom_error = apply_filters('better_messages_call_create_custom_error', "", $thread_id, $current_user_id, $type );

            if( ! empty( $custom_error ) ){
                return [
                    'result' => 'not_allowed',
                    'errors' =>  $custom_error
                ];
            }

            $check = Better_Messages()->functions->can_reply_in_conversation( $current_user_id, $thread_id );

            if( $check['result'] === 'not_allowed' ){
                return $check;
            }

            $recipient_ids = Better_Messages()->functions->get_recipients_ids( $thread_id );

            /*if( count( $recipient_ids ) > 2 ){
                return [
                    'result'     => 'not_allowed',
                    'errors' =>  _x('You can make private call only in private conversation', 'Private Call - Rest API Error', 'bp-better-messages')
                ];
            }*/

            foreach( $recipient_ids as $uid => $recipient_id ){
                if( $recipient_id === $current_user_id ){
                    unset( $recipient_ids[ $uid ] );
                }
            }

            if( count( $recipient_ids ) <= 2 ) {
                $target_user_id = array_values($recipient_ids)[0];

                $mins = 0;
                $secs = 0;
                $seconds = sprintf('%02d:%02d', $mins, $secs);

                $call_data = [
                    'caller_id' => $current_user_id,
                    'thread_id' => $thread_id,
                    'type' => $type,
                    'mins' => $mins,
                    'secs' => $secs,
                    'duration' => $seconds,
                    'call_requested' => Better_Messages()->functions->current_time(),
                ];

                $url = Better_Messages()->functions->get_user_thread_url($thread_id, $target_user_id);

                if ($type === 'video') {
                    $message = '<span class="bpbm-call bpbm-call-video call-incoming">' . sprintf(_x('Video call', 'Private Call - Message Entry', 'bp-better-messages'), $seconds) . '</span>';

                    $notification = array(
                        'title' => sprintf(_x('Incoming video call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name($current_user_id)),
                        'body' => sprintf(_x('You have incoming video call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name($current_user_id)),
                        'icon' => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($current_user_id)),
                        'tag' => 'bp-better-messages-thread-' . $thread_id,
                        'data' => array(
                            'url' => $url
                        )
                    );
                } else if ($type === 'audio') {
                    $message = '<span class="bpbm-call bpbm-call-audio call-incoming">' . sprintf(_x('Audio call', 'Private Call - Message Entry', 'bp-better-messages'), $seconds) . '</span>';

                    $notification = array(
                        'title' => sprintf(_x('Incoming audio call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name($current_user_id)),
                        'body' => sprintf(_x('You have incoming audio call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name($current_user_id)),
                        'icon' => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($current_user_id)),
                        'tag' => 'bp-better-messages-thread-' . $thread_id,
                        'data' => array(
                            'url' => $url
                        )
                    );
                } else {
                    return false;
                }
            }

            $args = array(
                'sender_id'    => $current_user_id,
                'thread_id'    => $thread_id,
                'content'      => $message,
                'send_push'    => false,
                'mobile_push'  => false,
                'count_unread' => false,
                'show_on_site' => false,
                'return'       => 'message_id'
            );

            $message_id = Better_Messages()->functions->new_message($args);

            Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_call', true );
            Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_missed_call', false );

            $encryption_key = Better_Messages()->functions->random_string(20);

            Better_Messages()->functions->update_message_meta( $message_id, 'call_encryption_key', $encryption_key );

            foreach( $call_data as $key => $value ){
                Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
            }

            $recipient_ids = Better_Messages()->functions->get_recipients_ids( $thread_id );

            foreach( $recipient_ids as $uid => $recipient_id ){
                if( $recipient_id === $current_user_id ){
                    unset( $recipient_ids[ $uid ] );
                }
            }

            $token = $this->joinRoom( $thread_id, $message_id, $current_user_id );

            if( isset($target_user_id) && isset($notification) ) {
                $muted_threads = Better_Messages()->functions->get_user_muted_threads($target_user_id);

                if ( ! isset( $muted_threads[ $thread->id ] ) ) {
                    Better_Messages_WebSocket()->send_push_notification($target_user_id, $notification, 'call_request', $thread_id, $message_id, $current_user_id);
                }
            }

            return [
                'result'         => 'allowed',
                'message_id'     => $message_id,
                'thread_id'      => $thread_id,
                'user_ids'       => array_values($recipient_ids),
                'token'          => $token,
                'encryption_key' => $encryption_key
            ];

        }

        public function joinRoom( $thread_id, $message_id, $user_id )
        {
            $user = Better_Messages()->functions->rest_user_item( $user_id );

            if( ! $user['avatar'] ){
                $user['avatar'] = Better_Messages()->url . 'assets/images/avatar.png';
            }

            $request = [
                'site_id'    => Better_Messages()->websocket->site_id,
                'secret_key' => sha1( Better_Messages()->websocket->site_id . Better_Messages()->websocket->secret_key ),
                'user_id'    => $user_id,
                'ip' => Better_Messages()->functions->get_client_ip(),
                'thread_id'  => $thread_id,
                'message_id' => $message_id,
                'meta'       => json_encode([
                    'name'   => $user['name'],
                    'avatar' => $user['avatar'],
                    'link'   => $user['url']
                ]),
                'is_admin'         => '0',
                'can_publish'      => '1',
                'can_publish_data' => '1',
                'can_subscribe'    => '1',
                'is_hidden'        => '0',
            ];

            $video_management_server = apply_filters( 'bp_better_messages_realtime_server', 'https://cloud.better-messages.com/' );

            $request = wp_remote_post( $video_management_server . 'connectRoom', array(
                'body' => $request,
                'blocking' => true,
                'timeout' => 60
            ) );

            $token = $request['body'];

            return $token;
        }

        public function call_start( WP_REST_Request $request ){
            $thread_id = intval( $request->get_param('thread_id') );
            $user_id   = intval( $request->get_param('user_id') );
            $type      = $request->get_param('type');

            $current_user_id = Better_Messages()->functions->get_current_user_id();
            $target_user_id  = $user_id;

            if( $thread_id === 0 ) {
                $check = Better_Messages()->functions->get_pm_thread_id($target_user_id, $current_user_id, true);
            } else {
                $thread = Better_Messages()->functions->get_thread( $thread_id );

                if( ! $thread ){
                    return [
                        'result'     => 'not_allowed',
                        'errors' => _x('Conversation not found', 'Private Call - Rest API Error', 'bp-better-messages')
                    ];
                }

                $participants = Better_Messages()->functions->get_participants( $thread_id );

                if( $thread->type !== 'thread' || count( $participants['recipients'] ) > 1 ){
                    return [
                        'result'     => 'not_allowed',
                        'errors' =>  _x('You can make private call only in private conversation', 'Private Call - Rest API Error', 'bp-better-messages')
                    ];
                }

                if( ! in_array( $target_user_id, $participants['recipients'] ) ){
                    return [
                        'result'     => 'not_allowed',
                        'errors' =>  _x('Something went wrong. Member you tried to call not found in this conversation.', 'Private Call - Rest API Error', 'bp-better-messages')
                    ];
                }

                $custom_error = apply_filters('better_messages_private_call_allowed_error', "", $thread_id, $current_user_id, $target_user_id );

                if( ! empty( $custom_error ) ){
                    return [
                        'result'     => 'not_allowed',
                        'errors' =>  $custom_error
                    ];
                }

                $check = [
                    'result'    => 'allowed',
                    'thread_id' => $thread_id
                ];
            }

            if( $check['result'] === 'not_allowed' ){
                return $check;
            }

            $thread_id = (int) $check['thread_id'];

            $check = Better_Messages()->functions->can_reply_in_conversation( $current_user_id, $thread_id );

            if( $check['result'] === 'not_allowed' ){
                return $check;
            }

            $duration  = 0;
            $mins    = 0;
            $secs    = 0;
            $seconds = sprintf('%02d:%02d', $mins, $secs);

            $call_data = [
                'caller_id'      => $current_user_id,
                'thread_id'      => $thread_id,
                'type'           => $type,
                'mins'           => $mins,
                'secs'           => $secs,
                'duration'       => $seconds,
                'call_requested' => bp_core_current_time(),
            ];

            $url = Better_Messages()->functions->get_user_thread_url( $thread_id, $target_user_id );

            if( $type === 'video' ){
                $message    = '<span class="bpbm-call bpbm-call-video call-incoming">' . sprintf( _x( 'Video call', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';

                $notification = array(
                    'title' => sprintf( _x('Incoming video call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $current_user_id ) ),
                    'body'  => sprintf( _x('You have incoming video call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $current_user_id ) ),
                    'icon'  => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($current_user_id)),
                    'tag'   => 'bp-better-messages-thread-' . $thread_id,
                    'data'  => array(
                        'url' => $url
                    )
                );
            } else if( $type === 'audio' ){
                $message    = '<span class="bpbm-call bpbm-call-audio call-incoming">' . sprintf( _x( 'Audio call', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';

                $notification = array(
                    'title' => sprintf( _x('Incoming audio call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $current_user_id ) ),
                    'body'  => sprintf( _x('You have incoming audio call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $current_user_id ) ),
                    'icon'  => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($current_user_id)),
                    'tag'   => 'bp-better-messages-thread-' . $thread_id,
                    'data'  => array(
                        'url' => $url
                    )
                );
            } else {
                return false;
            }

            $args = array(
                'sender_id'    => $current_user_id,
                'thread_id'    => $thread_id,
                'content'      => $message,
                'send_push'    => false,
                'mobile_push'  => false,
                'count_unread' => false,
                'show_on_site' => false,
                'notification' => [
                    'type' => 'call_request',
                    'title' => $notification['title'],
                    'body'  => $notification['body'],
                ],
                'return'       => 'message_id'
            );

            $message_id = Better_Messages()->functions->new_message($args);

            $muted_threads = Better_Messages()->functions->get_user_muted_threads( $target_user_id );

            Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_call', true );
            Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_missed_call', false );

            foreach( $call_data as $key => $value ){
                Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
            }

            if( ! isset( $muted_threads[ $thread->id ]) ){
                Better_Messages_WebSocket()->send_push_notification( $target_user_id, $notification, 'call_request', $thread_id, $message_id, $current_user_id );
            }

            return [
                'result'     => 'allowed',
                'message_id' => $message_id,
                'thread_id'  => $thread_id
            ];
        }

        public function register_started_call( WP_REST_Request $request )
        {
            global $call_data;

            $user_id    = Better_Messages()->functions->get_current_user_id();
            $thread_id  = intval($request->get_param('thread_id'));
            $message_id = intval($request->get_param('message_id'));
            $type       = $request->get_param('type');
            $debug      = $request->has_param('debug') ? $request->get_param('debug') : false;

            $duration   = 0;

            $mins       = floor($duration / 60 % 60);
            $secs       = floor($duration % 60);
            $seconds    = sprintf('%02d:%02d', $mins, $secs);

            $call_data = [
                'caller_id'    => $user_id,
                'thread_id'    => $thread_id,
                'type'         => $type,
                'mins'         => $mins,
                'secs'         => $secs,
                'duration'     => $seconds,
                'call_started' => bp_core_current_time()
            ];

            if( $debug ){
                $call_data['debug'] = json_encode($debug);
            }

            $check = Better_Messages()->functions->can_reply_in_conversation( $user_id, $thread_id );

            if( $check['result'] === 'not_allowed' ){
                return $check;
            }

            if( $type === 'audio' ){
                $can_audio_call = $this->can_audio_call_in_thread( $thread_id, $user_id );
                if( ! $can_audio_call ) return false;

                $message = '<span class="bpbm-call bpbm-call-audio call-accepted">' . sprintf( _x( 'Audio call accepted <span class="bpbm-call-duration">(%s)</span>', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds )  . '</span>';

                $args = array(
                    'sender_id' => $user_id,
                    'thread_id' => $thread_id,
                    'content'   => $message,
                    'return'    => 'message_id',
                    'send_push' => false,
                    'mobile_push'  => false,
                    'meta'      => [
                        'type' => 'call_start'
                    ]
                );

                $args['message_id'] = $message_id;
                Better_Messages()->functions->update_message( $args );

                foreach( $call_data as $key => $value ){
                    Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
                }

                do_action('better_messages_register_call_usage', $message_id, $thread_id, $user_id );
            }


            if( $type === 'video' ){
                $can_video_call = $this->can_video_call_in_thread( $thread_id, $user_id );

                if( ! $can_video_call ) return false;
                $message = '<span class="bpbm-call bpbm-call-video call-accepted">' . sprintf( _x( 'Video call accepted <span class="bpbm-call-duration">(%s)</span>', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';

                $args = array(
                    'sender_id'   => $user_id,
                    'thread_id'   => $thread_id,
                    'content'     => $message,
                    'return'      => 'message_id',
                    'send_push'   => false,
                    'mobile_push'  => false,
                    'meta'        => [
                        'type' => 'call_start'
                    ]
                );

                $args['message_id'] = $message_id;

                Better_Messages()->functions->update_message( $args );

                foreach( $call_data as $key => $value ){
                    Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
                }

                do_action('better_messages_register_call_usage', $message_id, $thread_id, $user_id );
            }


            return $message_id;
        }

        public function register_call_usage( WP_REST_Request $request ){
            global $call_data;

            $user_id    = Better_Messages()->functions->get_current_user_id();
            $thread_id  = intval($request->get_param('thread_id'));
            $message_id = intval($request->get_param('message_id'));
            $duration   = intval($request->get_param('duration'));

            $message    = new BM_Messages_Message( $message_id );

            $mins       = (int) floor($duration / 60 % 60);
            $secs       = (int) floor($duration % 60);
            $seconds    = sprintf('%02d:%02d', $mins, $secs);

            $call_data = [
                'mins'      => $mins,
                'secs'      => $secs,
                'duration'  => $seconds
            ];

            if( $user_id !== $message->sender_id ) return false;

            $type = Better_Messages()->functions->get_message_meta( $message_id, 'type', true );

            if( $type === 'video' ){
                $message    = '<span class="bpbm-call bpbm-call-video call-accepted">' . sprintf( _x( 'Video call accepted <span class="bpbm-call-duration">(%s)</span>', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';
            } else if( $type === 'audio' ){
                $message    = '<span class="bpbm-call bpbm-call-audio call-accepted">' . sprintf( _x( 'Audio call accepted <span class="bpbm-call-duration">(%s)</span>', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';
            } else {
                return false;
            }

            foreach( $call_data as $key => $value ){
                Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
            }

            $stats = $request->has_param('stats') ? $request->get_param('stats') : false;

            if( $stats ){
                if( isset($stats['bytes_received'] ) && isset($stats['bytes_sent']) && is_numeric( $stats['bytes_received'] ) && is_numeric( $stats['bytes_sent'] ) ){
                    Better_Messages()->functions->update_message_meta( $message_id, 'stats', [
                        'bytes_received' => $stats['bytes_received'],
                        'bytes_sent'     => $stats['bytes_sent']
                    ] );
                }
            }

            $args = array(
                'sender_id'   => $user_id,
                'thread_id'   => $thread_id,
                'content'     => $message,
                'message_id'  => $message_id,
                'send_push'   => false,
                'mobile_push'  => false,
                'return'      => 'message_id'
            );

            Better_Messages()->functions->update_message( $args );

            do_action('better_messages_register_call_usage', $message_id, $thread_id, $user_id );

            return true;
        }

        public function rest_thread_item( $thread_item, $thread_id, $thread_type, $include_personal ){
            if( $include_personal ) {
                if ($thread_type === 'thread') {
                    $thread_item['permissions']['canVideoCall'] = $this->can_video_call_in_thread($thread_id, Better_Messages()->functions->get_current_user_id());
                    $thread_item['permissions']['canAudioCall'] = $this->can_audio_call_in_thread($thread_id, Better_Messages()->functions->get_current_user_id());
                }
            }

            return $thread_item;
        }

        public function disable_calls_for_restricted_role( $variables ){
            $user_id          = Better_Messages()->functions->get_current_user_id();
            $restricted_roles = (array) Better_Messages()->settings['restrictCalls'];

            $is_restricted    = Better_Messages()->functions->user_has_role( $user_id, $restricted_roles );

            if( $is_restricted ) {
                $variables['callRestrict'] = Better_Messages()->settings['restrictCallsMessage'];
            }

            return $variables;
        }

        public function bp_nouveau_customizer_user_profile_actions($buttons){
            $buttons['bpbm_audio_call'] = __( 'Audio Call', 'bp-better-messages' );
            $buttons['bpbm_video_call'] = __( 'Video Call', 'bp-better-messages' );
            return $buttons;
        }

        public function youzify_profile_call_button( $user_id ){
            if( ! $user_id ){
                $user_id = bp_displayed_user_id();
            }

            $can_call = true;

            if( Better_Messages()->settings['callsLimitFriends'] === '1' ){
                if( function_exists( 'friends_check_friendship' ) ){

                    if( current_user_can('manage_options') ){
                        /*
                         * Admin always can call
                         */
                        $can_call = true;
                    } else {
                        $can_call = friends_check_friendship(Better_Messages()->functions->get_current_user_id(), $user_id);
                    }
                }
            }

            if( ! $can_call ){
                return false;
            }

            $base_link = Better_Messages()->functions->get_link( Better_Messages()->functions->get_current_user_id() );


            if( $this->audio && Better_Messages()->settings['profileAudioCall'] === '1' ){
                $link = add_query_arg([
                    'fast-call' => '',
                    'to' => $user_id,
                    'type' => 'audio'
                ], $base_link);


                echo '<div class="bpbm-youzify-btn generic-button" id="bpbm-audio-call"><a href="' . $link . '" data-user-id="' . $user_id .'" class="audio-call bpbm-audio-call"><i class="fas fa-phone"></i>' . __( 'Audio Call', 'bp-better-messages' ) . '</a></div>';
            }


            if( $this->video && Better_Messages()->settings['profileVideoCall'] === '1' ) {
                $link = add_query_arg([
                    'fast-call' => '',
                    'to' => $user_id,
                    'type' => 'video'
                ], $base_link);


                echo '<div class="bpbm-youzify-btn generic-button" id="bpbm-video-call"><a href="' . $link . '" data-user-id="' . $user_id .'" class="video-call bpbm-video-call"><i class="fas fa-video"></i>' . __( 'Video Call', 'bp-better-messages' ) . '</a></div>';

            }

        }

        public function grimlock_profile_call_button(){

            $can_call = true;

            $user_id = bp_displayed_user_id();
            if( Better_Messages()->settings['callsLimitFriends'] === '1' ){
                if( function_exists( 'friends_check_friendship' ) ){

                    if( current_user_can('manage_options') ){
                        /*
                         * Admin always can call
                         */
                        $can_call = true;
                    } else {
                        $can_call = friends_check_friendship(Better_Messages()->functions->get_current_user_id(), $user_id);
                    }
                }
            }

            if( ! $can_call ){
                return false;
            }

            $base_link = Better_Messages()->functions->get_link( Better_Messages()->functions->get_current_user_id() );


            if( $this->audio && Better_Messages()->settings['profileAudioCall'] === '1' ){
                $link = add_query_arg([
                    'fast-call' => '',
                    'to' => $user_id,
                    'type' => 'audio'
                ], $base_link);


                echo '<div class="generic-button" id="bpbm-audio-call"><a href="' . $link . '" data-user-id="' . $user_id .'" class="audio-call grimlock-btn bpbm-audio-call">' . __( 'Audio Call', 'bp-better-messages' ) . '</a></div>';
            }


            if( $this->video && Better_Messages()->settings['profileVideoCall'] === '1' ) {
                $link = add_query_arg([
                    'fast-call' => '',
                    'to' => $user_id,
                    'type' => 'video'
                ], $base_link);

                echo '<div class="generic-button" id="bpbm-video-call"><a href="' . $link . '" data-user-id="' . $user_id .'" class="video-call grimlock-btn bpbm-video-call">' . __( 'Video Call', 'bp-better-messages' ) . '</a></div>';

            }

        }

        public function catch_fast_call(){
            if( isset($_GET['fast-call'])
                && isset($_GET['to'])
                && isset($_GET['type'])
                && ! empty($_GET['to'])
                && ! empty($_GET['type'])
            ){
                $type = $_GET['type'];

                if( $type !== 'audio' && $type !== 'video' ){
                    return false;
                }

                $to = get_userdata(intval($_GET['to']));
                if( ! $to ) return false;

                $result = Better_Messages()->functions->get_pm_thread_id($to->ID);

                $url = Better_Messages()->functions->add_hash_arg('conversation/' . $result['thread_id'], [
                        'scrollToContainer' => ''
                ], Better_Messages()->functions->get_link() );

                if( $type === 'audio' ){
                    $url .= '&audioCall';
                }

                if( $type === 'video' ){
                    $url .= '&videoCall';
                }

                wp_redirect($url);
                exit;
            }
        }

        public function profile_call_button_legacy(){
            if ( bp_is_my_profile() || ! is_user_logged_in() ) {
                return false;
            }

            $user_id = bp_displayed_user_id();

            $can_call = true;

            if( Better_Messages()->settings['callsLimitFriends'] === '1' ){
                if( function_exists( 'friends_check_friendship' ) ){
                    if( current_user_can('manage_options') ){
                        /*
                         * Admin always can call
                         */
                        $can_call = true;
                    } else {
                        $can_call = friends_check_friendship(Better_Messages()->functions->get_current_user_id(), $user_id);
                    }
                }
            }


            if( ! $can_call ) {
                return false;
            }

            $base_link = Better_Messages()->functions->get_link( Better_Messages()->functions->get_current_user_id() );


            if( $this->audio && Better_Messages()->settings['profileAudioCall'] === '1' ) {
                $link = add_query_arg([
                    'fast-call' => '',
                    'to' => $user_id,
                    'type' => 'audio'
                ], $base_link);
                echo bp_get_button(array(
                    'id' => 'bpbm_audio_call',
                    'component' => 'messages',
                    'must_be_logged_in' => true,
                    'block_self' => true,
                    'wrapper_id' => 'bpbm-audio-call',
                    'link_href' => $link,
                    'link_text' => __('Audio Call', 'bp-better-messages'),
                    'link_class' => 'bpbm-audio-call',
                    'button_attr' => [
                        'data-user-id' => $user_id
                    ]
                ));
            }

            if( $this->video && Better_Messages()->settings['profileVideoCall'] === '1' ) {
                $link = add_query_arg([
                    'fast-call' => '',
                    'to' => $user_id,
                    'type' => 'video'
                ], $base_link);
                echo bp_get_button(array(
                    'id' => 'bpbm_video_call',
                    'component' => 'messages',
                    'must_be_logged_in' => true,
                    'block_self' => true,
                    'wrapper_id' => 'bpbm-video-call',
                    'link_href' => $link,
                    'link_text' => __('Video Call', 'bp-better-messages'),
                    'link_class' => 'bpbm-video-call',
                    'button_attr' => [
                        'data-user-id' => $user_id
                    ]
                ));
            }

        }

        public function profile_call_button( $buttons, $user_id, $type ){

            if ( ! is_user_logged_in() ) {
                return $buttons;
            }

            if( $type === 'profile' && (int) $user_id !== Better_Messages()->functions->get_current_user_id() ){
                $can_call = true;

                if( Better_Messages()->settings['callsLimitFriends'] === '1' ){
                    if( function_exists( 'friends_check_friendship' ) ){

                        if( current_user_can('manage_options') ){
                            /*
                             * Admin always can call
                             */
                            $can_call = true;
                        } else {
                            $can_call = friends_check_friendship(Better_Messages()->functions->get_current_user_id(), $user_id);
                        }
                    }
                }

                if( ! $can_call ){
                    return $buttons;
                }

                $base_link = Better_Messages()->functions->get_link( Better_Messages()->functions->get_current_user_id() );

                $tag = 'li';

                if( defined('BP_PLATFORM_VERSION') ){
                    $tag = 'div';
                }

                if( $this->audio && Better_Messages()->settings['profileAudioCall'] === '1' ){
                    $link = add_query_arg([
                        'fast-call' => '',
                        'to' => $user_id,
                        'type' => 'audio'
                    ], $base_link);

                    $buttons['audio_call'] = array(
                        'id'                => 'bpbm_audio_call',
                        'component'         => 'messages',
                        'must_be_logged_in' => true,
                        'block_self'        => true,
                        'parent_element'    => $tag,
                        'wrapper_id'        => 'bpbm-audio-call',
                        'link_href'         => $link,
                        'link_text'         => __( 'Audio Call', 'bp-better-messages' ),
                        'link_class'        => 'bpbm-audio-call',
                        'button_attr'       => [
                            'data-user-id' => $user_id
                        ]
                    );
                }


                if( $this->video && Better_Messages()->settings['profileVideoCall'] === '1' ) {
                    $link = add_query_arg([
                        'fast-call' => '',
                        'to' => $user_id,
                        'type' => 'video'
                    ], $base_link);

                    $buttons['video_call'] = array(
                        'id' => 'bpbm_video_call',
                        'component' => 'messages',
                        'must_be_logged_in' => true,
                        'block_self' => true,
                        'parent_element' => $tag,
                        'wrapper_id' => 'bpbm-video-call',
                        'link_href' => $link,
                        'link_text' => __('Video Call', 'bp-better-messages'),
                        'link_class' => 'bpbm-video-call',
                        'button_attr' => [
                            'data-user-id' => $user_id
                        ]
                    );
                }
            }

            return $buttons;

        }

        public function restrict_non_friends_calls( $can_call, $user_id, $thread_id ){
            if( ! Better_Messages()->functions->is_friends_active() ) return $can_call;

            $participants = Better_Messages()->functions->get_participants($thread_id);
            if( $participants['count'] !== 2 ) return false;

            unset($participants['recipients'][$user_id]);
            reset($participants['recipients']);

            $friend_id = key($participants['recipients']);

            /**
             * Allow users reply to calls even if not friends
             */
            if( current_user_can('manage_options') || user_can( $friend_id, 'manage_options' ) ) {
                return $can_call;
            }

            return Better_Messages()->functions->is_friends($user_id, $friend_id);
        }

        public function can_audio_call_in_thread( $thread_id, $user_id ){
            if( Better_Messages()->settings['audioCalls'] !== '1' ) return false;


            $can_send_message = Better_Messages()->functions->can_send_message_filter( Better_Messages()->functions->check_access( $thread_id ), $user_id, $thread_id );
            $can_send_message = apply_filters('bp_better_messages_can_start_call', $can_send_message, $user_id, $thread_id );
            if( ! $can_send_message  ) return false;

            return apply_filters('bp_better_messages_can_audio_call', $can_send_message, $user_id, $thread_id );
        }

        public function can_video_call_in_thread( $thread_id, $user_id ){
            if( Better_Messages()->settings['videoCalls'] !== '1' ) return false;

            $can_send_message = Better_Messages()->functions->can_send_message_filter( Better_Messages()->functions->check_access( $thread_id ), $user_id, $thread_id );
            $can_send_message = apply_filters('bp_better_messages_can_start_call', $can_send_message, $user_id, $thread_id );
            if( ! $can_send_message  ) return false;

            return apply_filters('bp_better_messages_can_video_call', $can_send_message, $user_id, $thread_id );
        }

        public function record_offline_call( WP_REST_Request $request ){
            global $call_data;

            $user_id   = Better_Messages()->functions->get_current_user_id();
            $thread_id = intval( $request->get_param( 'thread_id' ) );
            $type      = sanitize_text_field( $request->get_param('type') );

            $call_data = [
                'caller_id' => $user_id,
                'thread_id' => $thread_id,
                'type'      => $type,
            ];

            $can_send_message = Better_Messages()->functions->can_send_message_filter( Better_Messages()->functions->check_access( $thread_id ), $user_id, $thread_id );
            if( ! $can_send_message  ) return false;

            if( $type === 'audio' ){
                $can_audio_call = $this->can_audio_call_in_thread($thread_id, $user_id);

                if( ! $can_audio_call ) return false;
                $message = '<span class="bpbm-call bpbm-call-audio missed missed-offline">' . _x( 'I tried to make an audio call, but you were offline', 'Private Call - Message Entry', 'bp-better-messages' )  . '</span>';

                $args = array(
                    'sender_id'   => $user_id,
                    'thread_id'   => $thread_id,
                    'content'     => $message
                );

                add_action( 'better_messages_message_sent', array( $this, 'record_missed_call_data' ) );
                Better_Messages()->functions->new_message( $args );
                remove_action( 'better_messages_message_sent', array( $this, 'record_missed_call_data' ) );
            }


            if( $type === 'video' ){
                $can_video_call = $this->can_video_call_in_thread($thread_id, $user_id);

                if( ! $can_video_call ) return false;
                $message = '<span class="bpbm-call bpbm-call-video missed missed-offline">' . _x( 'I tried to make a video call, but you were offline', 'Private Call - Message Entry', 'bp-better-messages' ) . '</span>';

                $args = array(
                    'sender_id'   => $user_id,
                    'thread_id'   => $thread_id,
                    'content'     => $message
                );

                add_action( 'better_messages_message_sent', array( $this, 'record_missed_call_data' ) );
                Better_Messages()->functions->new_message( $args );
                remove_action( 'better_messages_message_sent', array( $this, 'record_missed_call_data' ) );
            }

            return true;
        }

        public function record_missed_call( WP_REST_Request $request ){
            global $call_data;

            $user_id    = Better_Messages()->functions->get_current_user_id();
            $thread_id  = intval( $request->get_param('thread_id') );
            $type       = sanitize_text_field( $request->get_param('type') );
            $duration   = intval( $request->get_param('duration') );
            $message_id = intval( $request->get_param('message_id') );

            $mins    = floor($duration / 60 % 60);
            $secs    = floor($duration % 60);
            $seconds = sprintf('%02d:%02d', $mins, $secs);

            $call_data = [
                'caller_id' => $user_id,
                'thread_id' => $thread_id,
                'type'      => $type,
                'mins'      => $mins,
                'secs'      => $secs,
                'duration'  => $seconds,
            ];

            if( $message_id === 0 ){
                return false;
            }

            $notification   = false;

            $participants   = Better_Messages()->functions->get_participants( $thread_id );
            $target_user_id = array_values($participants['recipients'])[0];

            $url = Better_Messages()->functions->get_user_thread_url( $thread_id, $target_user_id );

            if( $type === 'audio' ){
                $can_audio_call = $this->can_audio_call_in_thread( $thread_id, $user_id );

                if( ! $can_audio_call ) return false;
                $message = '<span class="bpbm-call bpbm-call-audio missed">' . sprintf( _x( 'Missed audio call <span class="bpbm-call-duration">(%s)</span>', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';

                $notification = array(
                    'title' => sprintf( _x('Missed audio call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $user_id ) ),
                    'body'  => sprintf( _x('You have missed audio call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $user_id ) ),
                    'icon'  => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($user_id)),
                    'tag'   => 'bp-better-messages-thread-' . $thread_id,
                    'data'  => array(
                        'url' => $url
                    )
                );

                $args = array(
                    'sender_id'    => $user_id,
                    'thread_id'    => $thread_id,
                    'message_id'   => $message_id,
                    'content'      => $message,
                    'send_push'    => false,
                    'mobile_push'  => false,
                    'count_unread' => true
                );

                Better_Messages()->functions->update_message( $args );

                Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_call', true );
                Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_missed_call', true );
                foreach( $call_data as $key => $value ){
                    Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
                }
            }

            if( $type === 'video' ){
                $can_video_call = $this->can_video_call_in_thread( $thread_id, $user_id );
                if( ! $can_video_call ) return false;
                $message = '<span class="bpbm-call bpbm-call-video missed">' . sprintf( _x( 'Missed video call <span class="bpbm-call-duration">(%s)</span>', 'Private Call - Message Entry', 'bp-better-messages' ), $seconds ) . '</span>';

                $notification = array(
                    'title' => sprintf( _x('Missed video call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $user_id ) ),
                    'body'  => sprintf( _x('You have missed video call from %s', 'Private Call - Web Push', 'bp-better-messages'), Better_Messages()->functions->get_name( $user_id ) ),
                    'icon'  => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($user_id)),
                    'tag'   => 'bp-better-messages-thread-' . $thread_id,
                    'data'  => array(
                        'url' => $url
                    )
                );

                $args = array(
                    'sender_id'    => $user_id,
                    'thread_id'    => $thread_id,
                    'message_id'   => $message_id,
                    'content'      => $message,
                    'send_push'    => false,
                    'mobile_push'  => false,
                    'notification' => [
                        'type' => 'call_missed',
                        'title' => $notification['title'],
                        'body'  => $notification['body'],
                    ],
                    'count_unread' => true
                );

                Better_Messages()->functions->update_message( $args );

                Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_call', true );
                Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_missed_call', true );
                foreach( $call_data as $key => $value ){
                    Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
                }
            }


            if( $notification ){
                $muted_threads = Better_Messages()->functions->get_user_muted_threads( $target_user_id );

                if( ! isset($muted_threads[ $thread_id ]) ){
                    Better_Messages_WebSocket()->send_push_notification( $target_user_id, $notification, 'call_missed', $thread_id, $message_id, $user_id );
                }
            }

            return true;
        }

        public function record_missed_call_data( $message ){
            global $call_data;

            $message_id = $message->id;

            Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_call', true );
            Better_Messages()->functions->update_message_meta( $message_id, 'bpbm_missed_call', true );
            foreach( $call_data as $key => $value ){
                Better_Messages()->functions->update_message_meta( $message_id, $key, sanitize_text_field( $value ) );
            }
        }

        public function is_group_call_active( $thread_type, $participants_count ){
            $groupsCallActive = false;

            if( $thread_type === 'thread' && $participants_count > 2 ){
                $groupsCallActive = Better_Messages()->settings['groupCallsThreads'] === '1';
            }

            if( $thread_type === 'chat-room' ){
                $groupsCallActive = Better_Messages()->settings['groupCallsChats'] === '1';
            }

            if( $thread_type === 'group' ){
                $groupsCallActive = Better_Messages()->settings['groupCallsGroups'] === '1';
            }

            return $groupsCallActive;
        }

        public function is_audio_group_call_active( $thread_type, $participants_count ){
            $groupsCallActive = false;

            if( $thread_type === 'thread' && $participants_count > 2 ){
                $groupsCallActive = Better_Messages()->settings['groupAudioCallsThreads'] === '1';
            }

            if( $thread_type === 'chat-room' ){
                $groupsCallActive = Better_Messages()->settings['groupAudioCallsChats'] === '1';
            }

            if( $thread_type === 'group' ){
                $groupsCallActive = Better_Messages()->settings['groupAudioCallsGroups'] === '1';
            }

            return $groupsCallActive;
        }

    }

endif;


function Better_Messages_Calls()
{
    return Better_Messages_Calls::instance();
}
