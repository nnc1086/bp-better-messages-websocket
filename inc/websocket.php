<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class Better_Messages_WebSocket
 *
 * This used only when user using WebSocket version to communicate site with websocket server
 */
class Better_Messages_WebSocket
{

    public $site_id;
    public $secret_key;

    public static function instance()
    {

        // Store the instance locally to avoid private static replication
        static $instance = null;

        // Only run these methods if they haven't been run previously
        if ( null === $instance ) {
            $instance = new Better_Messages_WebSocket;
            $instance->setup_globals();
            $instance->setup_actions();
        }

        // Always return the instance
        return $instance;

        // The last metroid is in captivity. The galaxy is at peace.
    }

    public function setup_globals()
    {
        $site_url = site_url( '' );

        if( defined('BP_BETTER_MESSAGES_FORCE_DOMAIN') ){
            $site_url = BP_BETTER_MESSAGES_FORCE_DOMAIN;
        }

        $this->site_id = BP_Better_Messages()->functions->clean_site_url( $site_url );

        $license_key = "";

        if( defined('BP_BETTER_MESSAGES_FORCE_LICENSE_KEY') ){
            $license_key = BP_BETTER_MESSAGES_FORCE_LICENSE_KEY;
        } else {
            $license = bpbm_fs()->_get_license();
            if( $license ){
                $license_key = bpbm_fs()->_get_license()->secret_key;
            }
        }

        $this->secret_key = $license_key;
    }

    public function setup_actions()
    {
        add_action( 'better_messages_message_sent',   array( $this, 'on_message_sent' ) );

        add_action( 'better_messages_thread_self_update',   array( $this, 'thread_self_update'), 10, 2 );
        add_action( 'better_messages_info_changed',   array( $this, 'thread_info_changed'), 10, 2 );
        add_action( 'better_messages_thread_cleared', array( $this, 'thread_cleared'), 10, 1 );
        add_action( 'better_messages_thread_erased',  array( $this, 'thread_erased'), 10, 1 );
        add_action( 'better_messages_message_meta_updated', array( $this, 'message_meta_updated'), 10, 4 );

        add_action( 'better_messages_participant_removed', array( $this, 'participant_removed'), 10, 2 );

        add_action( 'better_messages_participants_added', array( $this, 'participants_added'), 10, 2 );
        add_action( 'better_messages_participants_removed', array( $this, 'participants_removed'), 10, 2 );

        add_action( 'init', array( $this, 'register_event' ) );

        add_action( 'better_messages_sync_unread', array( $this, 'update_last_activity' ), 9 );
        add_action( 'better_messages_sync_unread', array( $this, 'sync_unread' ) );

        add_action( 'better_messages_on_message_not_sent', array($this, 'on_message_not_sent'), 10, 3 );
        add_action( 'bp_better_messages_message_deleted', array( $this, 'on_message_deleted' ), 10, 2 );

        add_action( 'wp_ajax_bp_better_messages_save_user_push_subscription', array( $this, 'save_user_push_subscription' ) );
        add_action( 'wp_ajax_bp_better_messages_delete_user_push_subscription', array( $this, 'delete_user_push_subscription' ) );

        add_action( 'bp_better_chat_settings_updated', array( $this, 'install_push_workers_script' ) );

        add_action( 'bp_better_messages_thread_div', array( $this, 'add_thread_secret_key' ) );

        add_action( 'updated_user_meta', array( $this, 'on_usermeta_update'), 10, 4 );

        add_filter( 'bp_better_messages_avatar_extra_attr', array( $this, 'add_status_color_to_avatar'), 10, 2 );

        add_action( 'bp_notification_after_save', array( $this, 'buddypress_on_new_notification'), 10, 1 );

        add_action( 'bp_better_messages_thread_reaction', array( $this, 'thread_reaction' ), 10, 3 );

        add_action( 'better_messages_mark_all_read', array( $this, 'mark_all_read' ), 10, 1 );

        add_action( 'better_messages_user_updated', array( $this, 'on_user_updated'), 10, 1 );

        add_filter( 'better_messages_rest_api_update_data', array( $this, 'modify_api_update_data' ), 10, 3 );

        add_action( 'better_messages_set_user_thread_unread_count', array( $this, 'set_user_thread_unread_count' ), 10, 3 );

        add_action( 'better_messages_cleaner_job', array( $this, 'clean_old_tmp_ids' ) );
    }

    public function clean_old_tmp_ids()
    {
        global $wpdb;

        $table = bm_get_table('messages');

        $wpdb->query("UPDATE `{$table}` 
        SET `temp_id` = ''
        WHERE `temp_id` != ''
        AND LEFT(`created_at`, 10) <= UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY);");
    }

    public function set_user_thread_unread_count( $user_id, $thread_id, $unread_count ){
        $request = [
            'site_id'      => $this->site_id,
            'secret_key'   => sha1( $this->site_id . $this->secret_key ),
            'user_id'      => $user_id,
            'thread_id'    => $thread_id,
            'unread_count' => $unread_count,
            'server_time'  => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'updateUserUnread', array(
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function modify_api_update_data( $return, $current_user_id, $lastClient )
    {
        $return['mechanism'] = 'websocket';
        return $return;
    }

    public function on_user_updated( $user_id ){
        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'user'        => Better_Messages()->functions->rest_user_item( $user_id, false ),
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'userUpdated', array(
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function mark_all_read( $user_id ){
        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'user_id'     => $user_id,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'mark_all_read', array(
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function participant_removed($thread_id, $user_id){
        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'thread_id'   => $thread_id,
            'user_id'     => $user_id,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'v2/participantRemoved', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function participants_removed( $thread_id, $user_ids = [] ){
        if( count( $user_ids ) === 0 ) return;

        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'thread_id'   => $thread_id,
            'user_ids'    => $user_ids,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'v2/participantsRemoved', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function participants_added( $thread_id, $user_ids = [] ){
        if( count( $user_ids ) === 0 ) return;

        $data = [];
        foreach ( $user_ids as $user_id ){
            $data[] = [
                'user_id' => $user_id,
                'data' => Better_Messages()->api->get_threads( [ $thread_id ], true, true, true, true, $user_id )
            ];
        }

        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'thread_id'   => $thread_id,
            'data'        => $data,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'v2/participantsAdded', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function message_meta_updated($thread_id, $message_id, $meta_key, $meta_value){
        $message = Better_Messages()->functions->get_message( $message_id );

        $meta = apply_filters('better_messages_rest_message_meta', [], (int) $message_id, (int) $thread_id, $message->message );
        if( empty( $meta ) ) $meta = (object) [];

        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'thread_id'   => $thread_id,
            'message_id'  => $message_id,
            'meta'        => $meta,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'v2/messageMetaUpdated', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    private $forced_user_id = false;

    public function forced_user_id( $user_id )
    {
        if( $this->forced_user_id !== false ){
            return $this->forced_user_id;
        }

        return $user_id;
    }

    private function force_user_id( $user_id ){
        $this->forced_user_id = $user_id;
        add_filter( 'better_messages_forced_current_user_id', [ $this, 'forced_user_id' ] );
    }

    private function unforce_user_id()
    {
        $this->forced_user_id = false;
        remove_filter( 'better_messages_forced_current_user_id', [ $this, 'forced_user_id' ] );
    }

    public function thread_self_update( $thread_id, $user_id ){
        $this->force_user_id( $user_id );
        $get_threads = Better_Messages()->api->get_threads( [ $thread_id ], false, false, true, false, $user_id );
        $this->unforce_user_id();

        $thread = $get_threads['threads'][0];

        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'user_ids'    => [ $user_id ],
            'thread_id'   => (int) $thread_id,
            'thread'      => $thread,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'threadInfoSelfUpdate', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function thread_info_changed( $thread_id, $userIds = [] ){
        $get_threads = Better_Messages()->api->get_threads( [ $thread_id ], false, false, false, false );

        $thread = $get_threads['threads'][0];

        $request = [
            'site_id'     => $this->site_id,
            'secret_key'  => sha1( $this->site_id . $this->secret_key ),
            'user_ids'    => $userIds,
            'thread_id'   => (int) $thread_id,
            'thread'      => $thread,
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'threadInfoChanged', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function thread_erased($thread_id){
        $request = [
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'thread_id'  => (int) $thread_id,
            'type'       => 'thread_erased',
            'data'       => [],
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'v2/sendThreadEvent', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function thread_cleared( $thread_id ){
        $request = [
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'thread_id'  => $thread_id,
            'type'       => 'thread_cleared',
            'data'       => [],
            'server_time' => Better_Messages()->functions->get_microtime()
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'v2/sendThreadEvent', array(
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => json_encode($request),
            'blocking' => false
        ) );
    }

    public function thread_reaction( $message_id, $thread_id, $new_reactions ){
        $request = [
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'thread_id'  => $thread_id,
            'data'       => [
                'mid' => $message_id,
                'new' => $new_reactions
            ],
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'sendThreadEvent', array(
            'body' => $request,
            'blocking' => false
        ) );
    }

    public function buddypress_on_new_notification( &$notification ){
        global $bp;

        $action  = false;
        $user_id = false;

        $title = '';
        $image = '';
        $url   = '';

        if( $notification->component_name === 'groups' ){
            if( Better_Messages()->settings['groupsOnSiteNotifications'] === '1' ) {
                $group_id = $notification->item_id;
                $group = groups_get_group( $group_id );
                $group_link = bp_get_group_permalink( $group );
                $title = $group->name;

                $image = bp_core_fetch_avatar( array(
                    'item_id'    => $group_id,
                    'avatar_dir' => 'group-avatars',
                    'object'     => 'group',
                    'type'       => 'thumb',
                    'width'      => 50,
                    'height'     => 50,
                    'html'       => false
                ));

                $user_id = $notification->user_id;

                if ($notification->component_action === 'new_membership_request') {
                    $action = true;
                    $url = $group_link . 'admin/membership-requests/?n=1';
                }

                if ($notification->component_action === 'membership_request_rejected') {
                    $action = true;
                    $url = trailingslashit( Better_Messages()->functions->bp_core_get_user_domain($user_id) . bp_get_groups_slug() ) . '?n=1';
                }

                if ($notification->component_action === 'membership_request_accepted') {
                    $action = true;
                    $url = $group_link . '?n=1';
                }

                if ($notification->component_action === 'member_promoted_to_admin') {
                    $action = true;
                    $url = trailingslashit( Better_Messages()->functions->bp_core_get_user_domain($user_id) . bp_get_groups_slug() ) . '?n=1';
                }

                if ($notification->component_action === 'member_promoted_to_mod') {
                    $action = true;
                    $url = trailingslashit( Better_Messages()->functions->bp_core_get_user_domain($user_id) . bp_get_groups_slug() ) . '?n=1';
                }

                if ($notification->component_action === 'group_invite') {
                    $action = true;
                    $url = trailingslashit( Better_Messages()->functions->bp_core_get_user_domain($user_id) . bp_get_groups_slug() ) . '/invites/?n=1';
                }
            }
        }

        if( $notification->component_name === 'friends' ){

            if( Better_Messages()->settings['friendsOnSiteNotifications'] === '1' ) {

                if ($notification->component_action === 'friendship_request') {
                    $action = true;

                    $title = __('New friendship request', 'bp-better-messages');

                    if (isset($notification->item_id)) {
                        $image = Better_Messages()->functions->get_rest_avatar( $notification->item_id );
                    }

                    if (isset($notification->user_id)) {
                        $user_id = $notification->user_id;
                        $url = Better_Messages()->functions->bp_core_get_user_domain($user_id) . bp_get_friends_slug() . '/requests/?new';
                    }
                }

                if ($notification->component_action === 'friendship_accepted') {
                    $action = true;

                    $title = __('Friendship request accepted', 'bp-better-messages');

                    if (isset($notification->item_id)) {
                        $image = Better_Messages()->functions->get_rest_avatar($notification->item_id);
                    }

                    if (isset($notification->user_id)) {
                        $user_id = $notification->user_id;
                        $url = Better_Messages()->functions->bp_core_get_user_domain($user_id) . bp_get_friends_slug() . '/my-friends';
                    }
                }

            }
        }

        if( $action === false ){
            return;
        }

        if ( isset( $bp->{ $notification->component_name }->notification_callback ) && is_callable( $bp->{ $notification->component_name }->notification_callback ) ) {
            $description = call_user_func( $bp->{ $notification->component_name }->notification_callback, $notification->component_action, $notification->item_id, $notification->secondary_item_id, 1, 'string', $notification->id );
        } elseif ( isset( $bp->{ $notification->component_name }->format_notification_function ) && function_exists( $bp->{ $notification->component_name }->format_notification_function ) ) {
            $description = call_user_func( $bp->{ $notification->component_name }->format_notification_function, $notification->component_action, $notification->item_id, $notification->secondary_item_id, 1 );
        } else {
            $description = apply_filters_ref_array( 'bp_notifications_get_notifications_for_user', array( $notification->component_action, $notification->item_id, $notification->secondary_item_id, 1, 'string', $notification->component_action, $notification->component_name, $notification->id ) );
        }

        $url = apply_filters( 'better_messages_buddypress_notification_url', $url, $notification );

        $description = apply_filters( 'bp_get_the_notification_description', $description, $notification );

        $text = strip_tags( $description );

        $this->send_on_site_notification( $user_id, $title, $url, $image, $text );
    }

    public function send_on_site_notification( $user_id, $title, $url, $image, $text ){
        //var_dump( $user_id, $title, $url, $image, $text );
        $request = [
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'user_id'    => $user_id,
            'title'      => $this->encrypt_message_for_user($title, $user_id),
            'url'        => $this->encrypt_message_for_user($url, $user_id),
            'image'      => $this->encrypt_message_for_user($image, $user_id),
            'text'       => $this->encrypt_message_for_user($text, $user_id)
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'sendOnSiteNotification', array(
            'body' => $request,
            'blocking' => false
        ) );
    }

    public function send_realtime_event( array $user_ids, $data ){
        $request = [
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'user_ids'   => $user_ids,
            'data'       => $this->encrypt_message_for_website(json_encode($data))
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'sendRealtimeEvent', array(
            'body' => $request,
            'blocking' => false
        ) );
    }

    public function endCall( $thread_id, $message = '' ) {
        $request = [
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'thread_id'  => $thread_id,
            'user_ids'   => array_keys(Better_Messages()->functions->get_recipients( $thread_id )),
            'message'    => $message
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'endCall', array(
            'body' => $request,
            'blocking' => false
        ) );
    }

    public function install_push_workers_script( $settings ){
        $file = Better_Messages()->path . 'assets/js/bpbm-worker.min.js';
        $target_file = ABSPATH . 'bpbm-worker.js';

        if( $settings['enablePushNotifications'] === '1' ){
            file_put_contents( $target_file, file_get_contents( $file ) );
        } else {
            if( file_exists( $target_file ) ) {
                unlink($target_file);
            }
        }
    }

    public function save_user_push_subscription(){
        if ( ! wp_verify_nonce($_POST['nonce'], 'save_user_push_subscription' ) ) {
            wp_send_json_error();
        }

        $user_id      = Better_Messages()->functions->get_current_user_id();
        $subscription = json_decode(wp_unslash($_POST['sub']));

        $user_push_subscriptions = Better_Messages()->functions->get_user_meta( $user_id, 'bpbm_messages_push_subscriptions', true );
        if( empty( $user_push_subscriptions ) || ! is_array( $user_push_subscriptions ) ) $user_push_subscriptions = array();
        $user_push_subscriptions[ $subscription->endpoint ] = (array) $subscription->keys;
        Better_Messages()->functions->update_user_meta( $user_id, 'bpbm_messages_push_subscriptions', $user_push_subscriptions );
        wp_send_json_success();
    }

    public function delete_user_push_subscription(){
        if ( ! wp_verify_nonce($_POST['nonce'], 'save_user_push_subscription' ) ) {
            wp_send_json_error();
        }

        $user_id      = Better_Messages()->functions->get_current_user_id();
        $subscription = json_decode(wp_unslash($_POST['sub']));

        $user_push_subscriptions = Better_Messages()->functions->get_user_meta( $user_id, 'bpbm_messages_push_subscriptions', true );
        if( empty( $user_push_subscriptions ) || ! is_array( $user_push_subscriptions ) ) $user_push_subscriptions = array();

        if( isset( $user_push_subscriptions[ $subscription->endpoint ] ) ){
            unset( $user_push_subscriptions[ $subscription->endpoint ] );
        } #(array) $subscription->keys;

        Better_Messages()->functions->update_user_meta( $user_id, 'bpbm_messages_push_subscriptions', $user_push_subscriptions );

        wp_send_json_success();
    }

    public function get_bulk_push_notification( $notifications ){
        $prepare_bulk_data = [];

        foreach( $notifications as $user_id => $notification ){
            $user_push_subscriptions = Better_Messages()->functions->get_user_meta( $user_id, 'bpbm_messages_push_subscriptions', true );
            if( empty( $user_push_subscriptions ) ) {
                continue;
            }

            $subs = [];
            foreach( $user_push_subscriptions as $endpoint => $keys ){
                $subs[] = [
                    'endpoint' => $endpoint,
                    'keys'     => $keys,
                ];
            }

            $prepare_bulk_data[] = [
                'subs'         => $subs,
                'user_id'      => $user_id,
                'notification' => $notification
            ];
        }

        if( empty( $prepare_bulk_data ) ) return false;

        $email = get_option('admin_email');

        $request = [
            'email'         => $email,
            'pushs'         => $prepare_bulk_data,
            'vapidKeys'     => $this->get_vapid_keys()
        ];

        return $request;
    }

    public function send_push_notification( $user_id, $notification, $type = null, $thread_id = 0, $message_id = 0, $sender_id = 0 ){
        do_action('better_messages_send_pushs', [ $user_id ], $notification );

        $user_push_subscriptions = ( $this->is_bm_push_active() ) ? Better_Messages()->functions->get_user_meta( $user_id, 'bpbm_messages_push_subscriptions', true ) : [];

        $mobile_pushs = apply_filters( 'better_messages_send_mobile_pushs', [], $user_id, $notification, $type, $thread_id, $message_id, $sender_id );
        $onesignal_push = apply_filters( 'better_messages_send_onesignal_push', false, $user_id, $notification, $type, $thread_id, $message_id, $sender_id );

        if( empty( $user_push_subscriptions ) && empty( $mobile_pushs ) && $onesignal_push === false ) return false;

        $subs = array();

        if( is_array( $user_push_subscriptions ) && count( $user_push_subscriptions ) > 0) {
            foreach ($user_push_subscriptions as $endpoint => $keys) {
                $subs[] = [
                    'endpoint' => $endpoint,
                    'keys' => $keys
                ];
            }
        }

        $email = get_option('admin_email');

        $request = [
            'site_id'       => $this->site_id,
            'secret_key'    => sha1( $this->site_id . $this->secret_key ),
            'user_id'       => $user_id,
            'email'         => $email,
            'notification'  => $notification,
            'subs'          => $subs,
            'vapidKeys'     => $this->get_vapid_keys()
        ];

        if( count( $mobile_pushs ) ){
            $request['mobile_pushs'] = $mobile_pushs;
        }

        if( $onesignal_push !== false ){
            $request['onesignal_pushs'] = $onesignal_push;
        }

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'sendPushNotification', array(
            'headers'  => array('Content-Type' => 'application/json'),
            'body'     => json_encode( $request ),
            'blocking' => false
        ) );

        return true;
    }

    public function register_event()
    {
        if ( ! wp_next_scheduled( 'better_messages_sync_unread' ) ) {
            wp_schedule_event( time(), 'one_minute', 'better_messages_sync_unread' );
        }
    }

    public function get_vapid_keys(){
        $vapid_keys = get_option( 'bp_better_messages_vapid_keys', false );

        if( $vapid_keys !== false && ! empty( $vapid_keys ) ){
            return (array) $vapid_keys;
        }

        $data = array(
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key )
        );

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');
        $request = wp_remote_post( $socket_server . 'generateVAPIDKeys', array(
            'body'     => $data,
            'timeout'  => 120
        ) );

        if ( is_wp_error( $request ) ) {
            return false;
        }

        $vapid_keys = json_decode($request['body']);

        update_option('bp_better_messages_vapid_keys', $vapid_keys);

        return (array) $vapid_keys;
    }

    public function on_message_not_sent( $thread_id, $temp_id, $errors ){
        $this->on_tmp_message_deleted( $thread_id, $temp_id );
    }

    public function add_thread_secret_key($thread_id){
        if( Better_Messages()->settings['encryptionEnabled'] === '1') {
            $thread_key = Better_Messages()->functions->get_thread_meta( $thread_id, 'secret_key' );
            if( empty($thread_key) ){
                $thread_key = Better_Messages()->functions->random_string(20);
                Better_Messages()->functions->update_thread_meta( $thread_id, 'secret_key', $thread_key );
            }

            echo ' data-secret="' . $thread_key. '"';
        }
    }

    public function get_thread_secret_key($thread_id){
        $thread_key = Better_Messages()->functions->get_thread_meta( $thread_id, 'secret_key' );

        if( empty($thread_key) ){
            $thread_key = Better_Messages()->functions->random_string(20);
            Better_Messages()->functions->update_thread_meta( $thread_id, 'secret_key', $thread_key );
        }

        return $thread_key;
    }

    public function get_site_secret_key(){
        $secret_key = get_site_option('bm_site_secret_key', '');

        if( empty($secret_key) ) {
            $secret_key = get_option('bm_site_secret_key', '');

            if( ! empty( $secret_key ) ){
                update_site_option( 'bm_site_secret_key', $secret_key );
            }
        }

        if( empty( $secret_key ) ){
            $secret_key = Better_Messages()->functions->random_string(20);
            update_site_option( 'bm_site_secret_key', $secret_key );
        }

        return $secret_key;
    }

    public function encrypt_message( $message, $thread_id ){
        if( Better_Messages()->settings['encryptionEnabled'] !== '1') {
            return $message;
        }

        $thread_key = Better_Messages()->functions->get_thread_meta( $thread_id, 'secret_key' );
        return BPBM_AES256::encrypt( $message, $thread_key );
    }

    public function encrypt_message_for_user( $message, $user_id ){
        if( Better_Messages()->settings['encryptionEnabled'] !== '1') {
            return $message;
        }

        $secret_key = Better_Messages()->functions->get_user_secret_key( $user_id );
        return BPBM_AES256::encrypt( $message, $secret_key );
    }

    public function encrypt_message_for_website( $message ){
        if( Better_Messages()->settings['encryptionEnabled'] !== '1') {
            return $message;
        }

        $secret_key = $this->get_site_secret_key();
        return BPBM_AES256::encrypt( $message, $secret_key );
    }


    public function is_3rd_party_push_active(){
        return apply_filters( 'better_messages_3rd_party_push_active', false );
    }

    public function is_bm_push_active(){
        return apply_filters( 'better_messages_push_active', Better_Messages()->settings['enablePushNotifications'] === '1' );
    }

    public function on_message_sent( $message ){
        global $wpdb;

        $is_update = $message->is_update;

        if( isset( $message->sender_id ) ) {
            $user_id = $message->sender_id;
        } else {
            $user_id = Better_Messages()->functions->get_current_user_id();
        }

        $thread_id        = $message->thread_id;

        if( isset( $message->count_unread ) ){
            $count_unread = $message->count_unread;
        } else {
            $count_unread = true;
        }

        $not_send_to_sender = isset($message->bulk_hide) && $message->bulk_hide;

        $send_push = $message->send_push;
        $send_global = isset($message->send_global) && $message->send_global;

        $rest_thread  = Better_Messages_Rest_Api()->get_threads( [$thread_id], false, false, false );
        $rest_message = Better_Messages_Rest_Api()->get_messages( $thread_id, [ $message->id ], [], 50, false );

        $primary_thread  = (array) $rest_thread['threads'][0];
        if( isset( $primary_thread['permissions'] ) ) unset( $primary_thread['permissions'] );
        if( isset( $primary_thread['unread'] ) ) unset( $primary_thread['unread'] );
        if( isset( $primary_thread['mentions'] ) ) unset( $primary_thread['mentions'] );

        $primary_message = (array) $rest_message['messages'][0];
        $_sender         = $rest_message['users'][0];

        $original_message = $primary_message['message'];

        $primary_message['message']    = BP_Better_Messages()->functions->format_message( $primary_message['message'], $message->id, 'stack' );
        $primary_message['showOnSite'] = $message->show_on_site;

        $mentions = Better_Messages()->mentions->get_mentions_for_message( $thread_id, $message->id );

        $recipients = [];

        if( ! $not_send_to_sender ){
            $message->recipients[] = $user_id;
        }

        foreach ( $message->recipients as $recipient ) {
            if ( is_object($recipient) ) {
                $_user_id = intval($recipient->user_id);
            } else {
                $_user_id = intval($recipient);
            }

            $recipient = array(
                'uid'      => $_user_id,
            );

            $update_thread = [];
            $recipient_message = [];

            if( isset( $mentions[$_user_id] ) ){
                $update_thread['mentions'] = $mentions[$_user_id];
            }

            if( count( $update_thread ) > 0 ) $recipient['thread'] = $update_thread;

            $user_message = Better_Messages()->functions->format_message($original_message, (int) $message->id, 'stack', $_user_id);

            if( $user_message !== $primary_message['message'] ){
                $recipient_message['message'] = $this->encrypt_message_for_website( $user_message );
            }

            $favorited = ( $is_update ) ? ( Better_Messages()->functions->is_message_starred($message->id, $_user_id) ? 1 : 0 ) : 0;
            if( $favorited !== $primary_message['favorited'] ) {
                $recipient_message['favorited'] = $favorited;
            }

            if( count( $recipient_message ) > 0 ) $recipient['message'] = $recipient_message;

            $recipients[] = $recipient;
        }

        if( isset( $primary_message['message'] ) ){
            $primary_message['message'] = $this->encrypt_message_for_website( $primary_message['message'] );
        }

        if( isset( $primary_thread['subject'] ) ){
            $primary_thread['subject'] = $this->encrypt_message_for_website(  $primary_thread['subject'] );
        }

        if( isset( $primary_thread['title'] ) ){
            $primary_thread['title'] = $this->encrypt_message_for_website( $primary_thread['title'] );
        }

        if( isset( $primary_thread['image'] ) ){
            $primary_thread['image'] = $this->encrypt_message_for_website( $primary_thread['image'] );
        }

        if( isset( $primary_thread['url'] ) ){
            $primary_thread['url'] = $this->encrypt_message_for_website( $primary_thread['url'] );
        }

        if( isset( $_sender['name'] ) ){
            $_sender['name'] = $this->encrypt_message_for_website( $_sender['name'] );
        }

        if( isset( $_sender['avatar'] ) ){
            $_sender['avatar'] = $this->encrypt_message_for_website( $_sender['avatar'] );
        }

        if( isset( $_sender['url'] ) ){
            $_sender['url'] = $this->encrypt_message_for_website( $_sender['url'] );
        }

        $pushs = [];
        $push_logic = Better_Messages()->settings['pushNotificationsLogic'];

        if( $send_global && $send_push ) {
            $bm_push_active = $this->is_bm_push_active();
            $third_party_push_active = $this->is_3rd_party_push_active();

            if( $third_party_push_active || $bm_push_active ){
                $all_recipients = $wpdb->get_col( $wpdb->prepare(
                "SELECT user_id
                FROM `" . bm_get_table('recipients') . "` 
                WHERE thread_id = %d 
                AND is_muted = 0
                AND user_id != %d
                ", $thread_id, $message->sender_id ) );

                if( $push_logic === 'offline' ) {
                    $online_users = $this->get_online_users();
                    $push_recipients = array_diff($all_recipients, $online_users);
                } else {
                    $push_recipients = $all_recipients;
                }

                if( count( $push_recipients ) > 0 || count( $all_recipients ) > 0 ) {
                    $notification = array(
                        'title' => sprintf(__('New message from %s', 'bp-better-messages'), Better_Messages()->functions->get_name($message->sender_id)),
                        'body' => sprintf(__('You have new message from %s', 'bp-better-messages'), Better_Messages()->functions->get_name($message->sender_id)),
                        'icon' => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($message->sender_id)),
                        'tag' => 'bp-better-messages-thread-' . $message->thread_id,
                        'data' => array(
                            'url' => Better_Messages()->functions->redirect_to_messages_link($message->thread_id)
                        )
                    );

                    do_action('better_messages_send_pushs', $push_recipients, $notification, $message, $all_recipients );

                    if ( $bm_push_active && count( $push_recipients ) > 0 ) {
                        $bulk_notifications = [];

                        $sql = $wpdb->prepare("
                            SELECT user_id 
                            FROM `{$wpdb->usermeta}` 
                            WHERE `meta_key` = 'bpbm_messages_push_subscriptions' 
                            AND `user_id` IN ( 
                                SELECT user_id 
                                FROM `" . bm_get_table('recipients') . "` 
                                WHERE thread_id = %d 
                                AND is_muted = 0
                                AND user_id != %d
                            )
                            AND `meta_value` != 'a:0:{}'
                        ", $thread_id, $message->sender_id );

                        $push_recipients = array_map('intval', $wpdb->get_col($sql));

                        if ( count( $push_recipients ) ) {
                            foreach ( $push_recipients as $push_recipient ) {
                                if ( (int) $push_recipient === (int) $message->sender_id ) continue;

                                if ( ! Better_Messages()->notifications->user_web_push_enabled($push_recipient) ) continue;

                                $url = Better_Messages()->functions->get_user_thread_url($thread_id, $push_recipient);

                                $notification = array(
                                    'title' => sprintf(__('New message from %s', 'bp-better-messages'), Better_Messages()->functions->get_name($message->sender_id)),
                                    'body' => sprintf(__('You have new message from %s', 'bp-better-messages'), Better_Messages()->functions->get_name($message->sender_id)),
                                    'icon' => htmlspecialchars_decode(Better_Messages_Functions()->get_rest_avatar($message->sender_id)),
                                    'tag' => 'bp-better-messages-thread-' . $message->thread_id,
                                    'data' => array(
                                        'url' => $url
                                    )
                                );

                                $bulk_notifications[$push_recipient] = $notification;
                            }

                            $pushs = $this->get_bulk_push_notification($bulk_notifications);
                        }
                    } elseif ( $third_party_push_active && count( $all_recipients ) > 0 ) {
                        $pushs = apply_filters( 'better_messages_bulk_pushs', [], $all_recipients, $notification, $message );
                    }
                }
            }
        }

        $data = array(
            'site_id'      => $this->site_id,
            'secret_key'   => sha1( $this->site_id . $this->secret_key ),
            'from'         => $user_id,
            'thread'       => $primary_thread,
            'message'      => $primary_message,
            'recipients'   => $recipients,
            'status'       => (bool) Better_Messages()->settings['messagesStatus'],
            'count_unread' => $count_unread,
            'sender'       => $_sender,
            'notification' => $message->notification,
            'pushs'        => $pushs,
            'push_logic'   => $push_logic,
            'server_time'  => Better_Messages()->functions->get_microtime()
        );

        if( isset( $message->new_thread ) && $message->new_thread ){
            $data['new_thread'] = true;
        }

        $data = apply_filters( 'better_messages_realtime_server_send_data', $data, $message );

        if( isset($_POST['tempID']) ) $data['message']['temp_id'] = sanitize_text_field($_POST['tempID']);
        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');
        wp_remote_post( $socket_server . 'v2/send', array(
            'headers'  => array('Content-Type' => 'application/json'),
            'body'     => json_encode( $data ),
            'blocking' => false,
            'timeout'  => 30,
        ) );
    }

    public function get_online_users(){
        $data = array(
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key )
        );

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        $request = wp_remote_post( $socket_server . 'online_users', array(
            'body'     => $data
        ) );

        if ( is_wp_error( $request ) ) {
            return [];
        }

        $online_tabs = json_decode($request['body'], true);
        $online_users = [];

        if( is_array( $online_tabs ) ) {
            foreach ($online_tabs as $user_id => $online_tabs) {
                $online_users[$user_id] = $user_id;
            }
        }

        return $online_users;
    }

    public function update_last_activity(){
        $users = $this->get_online_users();

        if( is_array( $users ) && count( $users ) > 0 ) {
            foreach ($users as $user_id) {
                Better_Messages()->users->update_last_activity( $user_id );

                if( function_exists('bp_update_user_last_activity') ) {
                    bp_update_user_last_activity($user_id);
                }
            }
        }
    }

    public function sync_unread(){
        set_time_limit(0);
        ignore_user_abort(true);
        global $wpdb;

        $data = array(
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key )
        );

        $devices_synced = [];

        if( Better_Messages()->mobile_app && Better_Messages()->mobile_app->is_active ){
            $devices = Better_Messages()->mobile_app->functions->get_unsynced_devices();
            if( count( $devices ) > 0 ){
                $data['mobile_devices'] = $devices;
                $devices_synced = array_column($devices, 'id');
            }

            $data['ios_jwt'] = Better_Messages()->mobile_app->ios->get_push_jwt();
        }

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');
        $request = wp_remote_post( $socket_server . 'v2/sync', array(
            'timeout'  => 60,
            'body'     => $data
        ) );

        if ( is_wp_error( $request ) ) {
            return false;
        }

        if( $request['response']['code'] !== 200 ){
            return false;
        }

        if( Better_Messages()->mobile_app && Better_Messages()->mobile_app->is_active ){
            Better_Messages()->mobile_app->functions->mark_devices_synced($devices_synced);
        }

        $unread = json_decode($request['body']);

        if( isset( $unread->invalidEndpoints ) ){
            $invalidEndpoints = (array) $unread->invalidEndpoints;
            unset($unread->invalidEndpoints);

            if( count($invalidEndpoints) > 0 ) {
                foreach ($invalidEndpoints as $user_id => $invalidEndpoint) {
                    $user_endpoints = Better_Messages()->functions->get_user_meta($user_id, 'bpbm_messages_push_subscriptions', true);
                    foreach ($invalidEndpoint as $item) {
                        if (isset($user_endpoints[$item])) {
                            unset($user_endpoints[$item]);
                        }
                    }

                    Better_Messages()->functions->update_user_meta($user_id, 'bpbm_messages_push_subscriptions', $user_endpoints);
                }
            }
        }

        foreach($unread as $user_id => $threads){

            $updated_threads = [];

            foreach($threads as $thread_id => $_item){
                $updated_threads[] = intval( $thread_id );

                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT unread_count, last_read, last_delivered FROM " . bm_get_table('recipients') . " WHERE `user_id` = %d AND `thread_id` = %d",
                        $user_id, $thread_id
                    )
                );

                if( $row ){
                    $unread         = (int) $row->unread_count;
                    $last_read      = strtotime($row->last_read);
                    $last_delivered = strtotime($row->last_delivered);

                    $remote_last_read = strtotime($_item->last_read);
                    $remote_last_delivered = strtotime($_item->last_delivered);

                    $update_array = [];

                    if( $unread !== (int) $_item->unread ){
                        $update_array['unread_count'] = $_item->unread;

                        if( (int) $_item->unread === 0 ){
                            Better_Messages()->functions->messages_mark_thread_read($thread_id, $user_id);
                        }
                    }

                    if( $last_read < $remote_last_read ){
                        $update_array['last_read'] = date('Y-m-d H:i:s', $remote_last_read);
                    }

                    if( $last_delivered < $remote_last_delivered ){
                        $update_array['last_delivered'] = date('Y-m-d H:i:s', $remote_last_delivered);
                    }

                    if( count($update_array) > 0 ){
                        $wpdb->update(
                            bm_get_table('recipients'),
                            $update_array,
                            array(
                                'user_id' => $user_id,
                                'thread_id' => $thread_id
                            )
                        );
                    }

                }
            }

            if( function_exists('bp_notifications_mark_notification') ) {
                $notifications_table = bm_get_table('notifications');

                if( $notifications_table ) {
                    $unread_notifications_threads = $wpdb->get_results($wpdb->prepare("
                    SELECT `messages`.`thread_id`, `notifications`.`id` as `notification_id`
                    FROM `" . $notifications_table . "` as `notifications`
                    INNER JOIN `" . bm_get_table('messages') . "` as `messages`
                    ON `notifications`.`item_id` = `messages`.`id`
                    WHERE `notifications`.`user_id` = %d 
                    AND   `notifications`.`is_new` = 1 
                    AND   `notifications`.`component_name` = 'messages'", $user_id));

                    $notifications_per_thread = [];
                    foreach ($unread_notifications_threads as $item) {
                        $notifications_per_thread[$item->thread_id][] = $item->notification_id;
                    }

                    if (!empty($notifications_per_thread)) {

                        if (count($notifications_per_thread) > 0) {
                            $threads_in = '`thread_id` IN ("' . implode('","', array_keys($notifications_per_thread)) . '")';

                            $already_readed_threads = $wpdb->get_col($wpdb->prepare("SELECT `thread_id`  
                            FROM `" . bm_get_table('recipients') . "` 
                            WHERE $threads_in
                            AND `user_id` = %d
                            AND `unread_count` = 0", $user_id));

                            foreach ($already_readed_threads as $thread_id) {
                                if (isset($notifications_per_thread[$thread_id])) {
                                    foreach ($notifications_per_thread[$thread_id] as $notification_id) {
                                        BP_Notifications_Notification::update(
                                            array('is_new' => false),
                                            array('id' => $notification_id)
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        update_option( 'better_messages_last_sync', time(), false );

        return null;
    }

    public function mark_thread_read( $thread_id ){
        $recipients = Better_Messages()->functions->get_recipients( $thread_id );

        $user_ids = [];
        foreach( $recipients as $recipient ){
            $user_ids[] = $recipient->user_id;
        }

        $data = array(
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'thread_id'  => $thread_id,
            'user_ids'   => $user_ids
        );

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');
        $request = wp_remote_post( $socket_server . 'mark_thread_read', array(
            'body'     => $data,
            'blocking' => false
        ) );

        if ( is_wp_error( $request ) ) return false;

        return null;
    }

    public function on_tmp_message_deleted( $thread_id, $message_id ){
        $data = array(
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'thread_id'  => $thread_id,
            'message_id' => $message_id
        );

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');
        wp_remote_post( $socket_server . 'v2/tmpMessageDeleted', array(
            'headers'  => array('Content-Type' => 'application/json'),
            'body'     => json_encode($data),
            'blocking' => false
        ) );

        return null;
    }

    public function on_message_deleted( $message_id, $user_ids = [] ){
        $data = array(
            'site_id'    => $this->site_id,
            'secret_key' => sha1( $this->site_id . $this->secret_key ),
            'message_id' => intval($message_id)
        );

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        $data = apply_filters( 'better_messages_realtime_server_message_deleted_data', $data, $message_id );

        wp_remote_post( $socket_server . 'message_deleted', array(
            'headers'  => array('Content-Type' => 'application/json'),
            'body'     => json_encode($data),
            'blocking' => false
        ) );

        return null;
    }

    public function on_usermeta_update($meta_id, $user_id, $meta_key, $_meta_value){
        if( $meta_key !== 'bpbm_online_status' ) return $meta_id;

        $statuses = $this->get_all_statuses();
        $status = $this->get_user_status( $user_id );
        $newStatus = $statuses[$status];

        $newStatus['slug'] = $status;
        if( isset( $newStatus['icon'] ) ) unset( $newStatus['icon'] );
        if( isset( $newStatus['desc'] ) ) unset( $newStatus['desc'] );

        $request = [
            'site_id'       => $this->site_id,
            'secret_key'    => sha1( $this->site_id . $this->secret_key ),
            'user_id'       => $user_id,
            'status'        => $newStatus
        ];

        $socket_server = apply_filters('bp_better_messages_realtime_server', 'https://cloud.better-messages.com/');

        wp_remote_post( $socket_server . 'setNewStatus', array(
            'body' => $request,
            'blocking' => false
        ) );

        //Better_Messages()->hooks->on_user_update( $user_id );
        return $meta_id;
    }

    public function add_status_color_to_avatar( $extraattr, $user_id ){
        $statuses = $this->get_all_statuses();
        $status = $this->get_user_status( $user_id );

        $color = $statuses[$status]['color'];
        $extraattr .= ' data-bpbm-status-color="' . $color . '" ';
        return $extraattr;
    }

    public function get_all_statuses(){
        return apply_filters('bp_better_messages_all_statuses', [
            'online' => [
                'name'  => _x('Online', 'User status', 'bp-better-messages'),
                'icon'  => 'circle',
                'color' => '#3da512'
            ],
            'away'   => [
                'name'  => _x('Away', 'User status', 'bp-better-messages'),
                'icon'  => 'moon',
                'color' => '#e0a700'
            ],
            'dnd'    => [
                'name' => _x('Do not disturb', 'User status', 'bp-better-messages'),
                'desc' => _x('You will not receive sound notifications', 'User status description', 'bp-better-messages'),
                'icon' => 'stop',
                'color' => 'red'
            ],
        ]);
    }

    public function get_user_full_status( $user_id ){
        $statuses = $this->get_all_statuses();
        $status = $this->get_user_status( $user_id );

        return $statuses[$status];
    }

    public function get_user_status( $user_id ){
        $status = Better_Messages()->functions->get_user_meta($user_id, 'bpbm_online_status', true);

        $statuses = $this->get_all_statuses();

        if( empty($status) || ! isset( $statuses[$status]) ){
            $status = 'online';
        }

        return $status;
    }

    public function get_status_display_name( $status ){

        $statuses = $this->get_all_statuses();

        if( isset( $statuses[$status] ) ){
            return $statuses[$status]['name'];
        } else {
            return '';
        }
    }


}

function Better_Messages_WebSocket(): ?Better_Messages_WebSocket
{
    return Better_Messages_WebSocket::instance();
}

if( ! function_exists('Better_Messages_Premium') ) {
    function Better_Messages_Premium(): ?Better_Messages_WebSocket
    {
        return Better_Messages_WebSocket();
    }
}
