<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Calls_Group' ) ):

    class Better_Messages_Calls_Group
    {
        public static function instance()
        {
            static $instance = null;

            if ( null === $instance ) {
                $instance = new Better_Messages_Calls_Group();
            }

            return $instance;
        }


        public function __construct()
        {
            add_filter( 'better_messages_rest_thread_item', array( $this, 'rest_thread_item' ), 10, 4 );
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

        public function rest_thread_item( $thread_item, $thread_id, $thread_type, $include_personal ){
            if( $include_personal ) {
                $current_user_id = Better_Messages()->functions->get_current_user_id();

                if( $current_user_id > 0 && in_array( $current_user_id, $thread_item['participants'] ) ) {
                    $can_reply = Better_Messages()->functions->can_reply_in_conversation( $current_user_id, $thread_id );
                    $thread_item['permissions']['canGroupAudio'] = $this->is_audio_group_call_active($thread_type, $thread_item['participantsCount']) && $can_reply['result'] === 'allowed';
                    $thread_item['permissions']['canGroupVideo'] = $this->is_group_call_active($thread_type, $thread_item['participantsCount']) && $can_reply['result'] === 'allowed';
                }
            }

            return $thread_item;
        }

        public function rest_api_init(){
            register_rest_route( 'better-messages/v1', '/groupCallStart', array(
                'methods' => 'POST',
                'callback' => array( $this, 'call_start' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/groupCallAdmin', array(
                'methods' => 'POST',
                'callback' => array( $this, 'thread_group_call_admin' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );
        }

        public function call_start( WP_REST_Request $request ){
            $user_id   = Better_Messages()->functions->get_current_user_id();
            $thread_id = intval( $request->get_param('thread_id') );
            $type      = $request->get_param('type');

            $can_join = false;

            $can_moderate = Better_Messages()->functions->is_thread_super_moderator( $user_id, $thread_id );

            if( $can_moderate ){
                $can_join = true;
            }

            if( ! $can_join ){
                $can_reply = Better_Messages()->functions->can_reply_in_conversation( $user_id, $thread_id );
                if( $can_reply['result'] === 'allowed' ){
                    $can_join = true;
                }
            }

            if( ! $can_join ){
                return false;
            }

            $user = Better_Messages()->functions->rest_user_item( $user_id );

            if( ! $user['avatar'] ){
                $user['avatar'] = Better_Messages()->url . 'assets/images/avatar.png';
            }

            $request = [
                'site_id'    => Better_Messages()->websocket->site_id,
                'secret_key' => sha1( Better_Messages()->websocket->site_id . Better_Messages()->websocket->secret_key ),
                'user_id'    => $user_id,
                'ip'         => Better_Messages()->functions->get_client_ip(),
                'thread_id'  => $thread_id,
                'meta'       => json_encode([
                    'name'     => $user['name'],
                    'avatar'   => $user['avatar'],
                    'link'     => $user['url'],
                    'canAdmin' => $can_moderate
                ]),
                'type'             => $type,
                'is_admin'         => ($can_moderate) ? '1' : '0',
                'can_publish'      => '1',
                'can_publish_data' => '1',
                'can_subscribe'    => '1',
                'is_hidden'        => '0',
            ];

            $video_management_server = apply_filters( 'bp_better_messages_realtime_server', 'https://cloud.better-messages.com/' );

            $request = wp_remote_post( $video_management_server . 'connectGroupRoom', array(
                'body' => $request,
                'blocking' => true,
                'timeout' => 60
            ) );

            $token = $request['body'];

            return $token;
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

        public function thread_group_call_admin( WP_REST_Request $request ){
            $user_id   = Better_Messages()->functions->get_current_user_id();
            $thread_id = intval( $request->get_param('thread_id' ));
            $type      = sanitize_text_field( $request->get_param('type') );
            $token     = sanitize_text_field( $request->get_param('token') );

            $action = sanitize_text_field( $request->get_param('act') );

            $video_cloud_server = apply_filters( 'better_messages_video_server', 'video-cloud.better-messages.com' );
            $can_moderate = Better_Messages()->functions->is_thread_super_moderator( $user_id, $thread_id );

            if( ! $can_moderate ) {
               return false;
            }

            $headers =  [
                'Content-Type'   => 'application/json',
                'Authorization'  => 'Bearer ' . $token,
            ];

            if( $action === 'remove_participant' ) {
                $identity = sanitize_text_field( $request->get_param('identity') );

                $payload = json_encode([
                    'room'     => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                    'identity' => $identity
                ]);

                $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/RemoveParticipant', array(
                    'body'    => $payload,
                    'headers' => $headers
                ));

                if ( ! is_wp_error( $request ) ) {
                    return $request['body'];
                }
            }

            if( $action === 'mute_participant' ) {
                $identity = sanitize_text_field( $request->get_param('identity') );

                $payload = json_encode([
                    'room'        => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                    'identity'    => $identity,
                    'permission'  => [
                        'can_publish' => false,
                        'can_publish_data' => true,
                        'can_subscribe' => true
                    ]
                ]);

                $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/UpdateParticipant', array(
                    'body'    => $payload,
                    'headers' => $headers
                ));

                if ( ! is_wp_error( $request ) ) {
                    return $request['body'];
                }
            }

            if( $action === 'unmute_participant' ) {
                $identity = sanitize_text_field( $request->get_param('identity') );

                $payload = json_encode([
                    'room'        => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                    'identity'    => $identity,
                    'permission'  => [
                        'can_publish' => true,
                        'can_publish_data' => true,
                        'can_subscribe' => true
                    ]
                ]);

                $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/UpdateParticipant', array(
                    'body'    => $payload,
                    'headers' => $headers
                ));

                if ( ! is_wp_error( $request ) ) {
                    return $request['body'];
                }
            }

            if( $action === 'pin_participant' ){
                $identity = sanitize_text_field( $request->get_param('identity') );

                $payload = json_encode([
                    'room'      => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                    'identity'  => $identity,
                ]);

                $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/GetParticipant', array(
                    'body'    => $payload,
                    'headers' => $headers
                ));

                if ( ! is_wp_error( $request ) ) {
                    $participant = json_decode($request['body'], true);

                    if( ! $participant ) {
                        return false;
                    }

                    $metadata = json_decode($participant['metadata'], true);
                    $metadata['isPinned'] = true;

                    $payload = json_encode([
                        'room'      => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                        'identity'  => $identity,
                        'metadata'  => json_encode($metadata)
                    ]);

                    $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/UpdateParticipant', array(
                        'body'    => $payload,
                        'headers' => $headers,
                    ));

                    if ( ! is_wp_error( $request ) ) {
                        return $request['body'];
                    }
                }
            }

            if( $action === 'unpin_participant' ){
                $identity = sanitize_text_field( $request->get_param('identity') );

                $payload = json_encode([
                    'room'      => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                    'identity'  => $identity,
                ]);

                $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/GetParticipant', array(
                    'body'    => $payload,
                    'headers' => $headers
                ));

                if ( ! is_wp_error( $request ) ) {
                    $participant = json_decode($request['body'], true);

                    if( ! $participant ) {
                        return false;
                    }

                    $metadata = json_decode($participant['metadata'], true);
                    $metadata['isPinned'] = false;

                    $payload = json_encode([
                        'room'      => Better_Messages()->websocket->site_id . '_' . $thread_id . '_' . $type,
                        'identity'  => $identity,
                        'metadata'  => json_encode($metadata)
                    ]);

                    $request = wp_remote_post('https://' . $video_cloud_server . '/twirp/livekit.RoomService/UpdateParticipant', array(
                        'body'    => $payload,
                        'headers' => $headers,
                    ));

                    if ( ! is_wp_error( $request ) ) {
                        return $request['body'];
                    }
                }
            }

            return false;
        }
    }

endif;


function Better_Messages_Calls_Group()
{
    return Better_Messages_Calls_Group::instance();
}
