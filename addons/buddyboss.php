<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_BuddyBoss' ) ) {

    class Better_Messages_BuddyBoss
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_BuddyBoss();
            }

            return $instance;
        }

        public function __construct()
        {
            add_filter( 'bp_better_messages_after_format_message', array($this, 'buddyboss_group_messages'), 10, 4);
            add_filter( 'heartbeat_received', array($this, 'heartbeat_unread_notifications'), 12);
            add_filter( 'bb_pusher_enabled_features', array( $this, 'disable_bb_pusher') );

            add_action('wp_ajax_buddyboss_theme_get_header_unread_messages', array($this, 'buddyboss_theme_get_header_unread_messages'), 9);

            /**
             * BuddyBoss moderation
             */
            if( function_exists('bp_is_moderation_member_blocking_enable') ){
                $bb_blocking_enabled = bp_is_moderation_member_blocking_enable();
                if( $bb_blocking_enabled ){
                    add_filter( 'better_messages_can_send_message', array($this, 'buddyboss_disable_message_to_blocked'), 10, 3);
                }
            }

            if( function_exists('bb_access_control_member_can_send_message') ) {
                add_filter( 'better_messages_can_send_message', array($this, 'buddyboss_blocked_message'), 10, 3);
            }

            add_filter('bp_messages_thread_current_threads', array( $this, 'buddyboss_notifications_fix' ), 10, 1 );

            add_filter('bb_exclude_endpoints_from_restriction', array( $this, 'buddyboss_disable_rest_api_block' ), 10, 2 );

            add_filter( 'bp_has_message_threads', array( $this, 'has_message_threads' ), 10, 3 );

            add_action( 'init', array( $this, 'register_bb_notifications' ) );

            if( Better_Messages()->settings['enableGroups'] === '1' ) {
                add_filter('better_messages_can_send_message', array($this, 'group_restrictions'), 20, 3);
            }

            if( ! is_admin() ){
                add_filter( 'bp_disable_group_messages', '__return_false' );
            }

            /**
             * BuddyBoss Pushs
             */
            if (function_exists('bb_onesingnal_send_notification') && $this->bb_pushs_active() ) {
                add_filter( 'better_messages_send_onesignal_push', array( $this, 'send_pushs' ), 10, 7 );
                add_filter( 'better_messages_bulk_pushs', array( $this, 'send_bulk_pushs' ), 10, 4 );

                add_filter( 'better_messages_3rd_party_push_active', '__return_true' );
                add_filter( 'better_messages_push_active', '__return_false' );
                add_filter('better_messages_push_message_in_settings', array($this, 'push_message_in_settings'));

                add_action('rest_api_init', array($this, 'rest_api_init'));
                add_action('wp_footer', array($this, 'frontend_script'), 99999);

            }

            add_action('wp_footer', array($this, 'override_messages_list'), 199 );
            add_action('wp_footer', array($this, 'javascript_injects'), 200 );

            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts') );

        }

        public function enqueue_scripts()
        {
            $script = "wp.hooks.addFilter('better_messages_avatar_attributes', 'better_messages_bb_hooks', function(attributes, user){
                    if( user && user.id ) {
                        attributes['data-bb-hp-profile'] = user.id;
                    }

                    return attributes;
            });";


            wp_add_inline_script( 'better-messages', Better_Messages()->functions->minify_js($script), 'before' );
        }

        public function register_bb_notifications()
        {
            if ( class_exists( 'BP_Core_Notification_Abstract' ) ) {
                add_action('bb_register_notification_preferences', array( $this, 'remove_bb_notification_settings') );

                require_once trailingslashit( dirname( __FILE__ ) ) . 'buddyboss/notifications/new-message.php';
                BetterMessagesNewMessageNotification::instance();

                add_filter( 'better_messages_is_user_web_push_enabled', array( $this, 'overwrite_user_web_push_enabled' ), 10, 2 );

                if( Better_Messages()->settings['bpAppPush'] === '1' && function_exists('bbapp_send_push_notification') && function_exists('bbapp_is_active') && bbapp_is_active( 'push_notification' ) ){
                    add_action('better_messages_message_sent', array( $this, 'send_bb_app_push' ), 10, 1 );
                }
            }
        }

        public function rest_api_init()
        {
            if ( class_exists( 'Better_Messages_OneSignal' ) ) return;

            register_rest_route('better-messages/v1', '/oneSignal/updateSubscription', array(
                'methods' => 'POST',
                'callback' => array($this, 'update_subscription'),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ));
        }

        public function frontend_script(){
            if ( class_exists( 'Better_Messages_OneSignal' ) ) return;
            if( ! wp_script_is( 'better-messages' ) || ! is_user_logged_in() ) return;

            $is_dev = defined( 'BM_DEV' );
            $suffix = ( $is_dev ? '' : '.min' );

            $url = Better_Messages()->url . "addons/onesignal/sub-update{$suffix}.js";

            echo '<script src="' . $url . '?ver=0.2"></script>';
        }

        public function update_subscription( WP_REST_Request $request )
        {
            if( ! function_exists('bb_onesingnal_send_notification') || ! function_exists('bb_onesignal_rest_api_key') || ! function_exists('bb_onesignal_app_id') ) return false;

            $user_id = Better_Messages()->functions->get_current_user_id();

            if( $user_id <= 0 ){
                return new WP_Error( 'onesignal_error', 'User ID is required', array( 'status' => 400 ) );
            }

            $onesignal_app_id      =  bb_onesignal_app_id();
            $onesignal_auth_key    =  bb_onesignal_rest_api_key();

            $subscription_id = (string) $request->get_param( 'subscription_id');

            if( ! $subscription_id ){
                return new WP_Error( 'onesignal_error', 'Subscription ID is required', array( 'status' => 400 ) );
            }

            $onesignal_post_url = "https://api.onesignal.com/apps/{$onesignal_app_id}/subscriptions/{$subscription_id}/user/identity";

            $fields = [
                'identity' => [
                    'external_id' => (string) $user_id
                ]
            ];

            $request = array(
                'method' => 'PATCH',
                'headers' => array(
                    'content-type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Basic ' . $onesignal_auth_key,
                ),
                'body' => wp_json_encode($fields),
                'timeout' => 3,
            );

            $response = wp_remote_request($onesignal_post_url, $request);

            if( is_wp_error($response) ){
                return new WP_Error( 'onesignal_error', $response->get_error_message(), array( 'status' => 500 ) );
            }

            return [
                'user_id' => $user_id,
                'subscription_id' => $subscription_id,
            ];
        }

        public function override_messages_list()
        {
            if( ! is_user_logged_in() ) return;
            ob_start();
            ?>
            <script type="text/javascript">
                let bmnd = document.querySelectorAll('#header-messages-dropdown-elem .notification-dropdown .notification-list');

                if( bmnd.length > 0 ) {
                    bmnd.forEach( function( el ){
                        el.innerHTML = <?php echo json_encode(Better_Messages()->functions->get_conversations_layout()) ?>;
                        el.style.padding = '0';
                        el.style.margin = '0';
                        el.classList.remove('notification-list');
                        el.classList.add('bm-notification-list');
                    });

                    jQuery(document).trigger("bp-better-messages-init-scrollers");
                }
            </script>
            <?php
            $script = ob_get_clean();

            echo Better_Messages()->functions->minify_js( $script );
        }

        public function javascript_injects()
        {
            if( ! is_user_logged_in() ) return '';
            ob_start();
            ?>
            <script type="text/javascript">
                if ('MutationObserver' in window) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'href' ){
                                const target = mutation.target;

                                if( target.matches('a.card-button.send-message') && target.hasAttribute('href') && target.getAttribute('href').trim() !== '') {
                                    const profileCard = target.closest('#profile-card');
                                    const userId = profileCard.getAttribute('data-bp-item-id');

                                    if( userId ) {
                                        target.classList.add('bpbm-pm-button', 'bm-no-style', 'bm-no-loader', 'open-mini-chat', 'bm-user-' + userId);
                                        target.style.minWidth = target.offsetWidth + 'px';
                                        target.style.minHeight = target.offsetHeight + 'px';
                                        target.style.display = 'block';

                                        target.innerHTML = '<span class="bm-loader-container">' + target.innerHTML + '</span>';

                                        if( target.href.endsWith('?&to') ){
                                            target.href += '=' + userId;
                                        } else if( target.href.endsWith('?bm-fast-start=1') ){
                                            target.href += '&to=' + userId;
                                        }
                                    }
                                }
                            }
                        });
                    });

                    // Observe changes in the body
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['href']
                    });
                }
            </script>
            <?php
            $script = ob_get_clean();
            echo Better_Messages()->functions->minify_js( $script );
        }

        public function group_restrictions( $allowed, $user_id, $thread_id ){
            if( function_exists('bp_disable_group_messages') && ! function_exists('groups_can_user_manage_messages') || ! function_exists('bp_group_get_message_status') ){
                return $allowed;
            }

            if( ! bp_disable_group_messages() ){
                return $allowed;
            }

            $type = Better_Messages()->functions->get_thread_type( $thread_id );

            if( $type !== 'group' ){
                return $allowed;
            }

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'group_id');

            if( ! groups_can_user_manage_messages( $user_id, $group_id ) ){
                $status = bp_group_get_message_status( $group_id ) ?? '';
                global $bp_better_messages_restrict_send_message;
                if ( 'admins' === $status ) {
                    $bp_better_messages_restrict_send_message['bb_restrict_group'] = __( 'Only group organizers can send messages to this group.', 'buddyboss' );
                    $allowed = false;
                } elseif ( 'mods' === $status ) {
                    $bp_better_messages_restrict_send_message['bb_restrict_group'] = __( 'Only group organizers and moderators can send messages to this group.', 'buddyboss' );
                    $allowed = false;
                }
            }

            return $allowed;
        }

        public function push_message_in_settings( $message ){
            $message = '<p style="color: #0c5460;background-color: #d1ecf1;border: 1px solid #d1ecf1;padding: 15px;line-height: 24px;max-width: 550px;">';
            $message .= _x('The BuddyBoss OneSignal integration is active and will be used, this option do not need to be enabled', 'Settings page', 'bp-better-messages');
            $message .= '</p>';

            return $message;
        }

        public function send_pushs( $result, $user_id, $notification, $type, $thread_id, $message_id, $sender_id ){
            if( ! function_exists('bb_onesingnal_send_notification') || ! function_exists('bb_onesignal_rest_api_key') || ! function_exists('bb_onesignal_app_id') ) {
                return $result;
            }

            if ( ! Better_Messages()->notifications->user_web_push_enabled( $user_id ) ) {
                return $result;
            }

            $rest_api_key = bb_onesignal_rest_api_key();
            $app_id       = bb_onesignal_app_id();

            if ( empty( $rest_api_key ) || empty( $app_id ) ) {
                return null;
            }

            $image = $notification['icon'];

            $fields = array(
                'app_id' => $app_id,
                'chrome_web_icon' => $image,
                'chrome_web_badge' => $image,
                'firefox_icon' => $image,
                'headings' => [ 'en' => stripslashes_deep(wp_specialchars_decode($notification['title'])) ],
                'url' => $notification['data']['url'],
                'contents' => [ 'en' => stripslashes_deep(wp_specialchars_decode($notification['body'])) ],
            );

            return [
                'onesignal_api_key' => $rest_api_key,
                'user_ids'          => array_map('strval', [ $user_id ]),
                'fields'            => $fields
            ];
        }

        public function send_bulk_pushs( $pushs, $all_recipients, $notification, $message )
        {
            if( ! function_exists('bb_onesingnal_send_notification') || ! function_exists('bb_onesignal_rest_api_key') || ! function_exists('bb_onesignal_app_id') ) return $pushs;

            $rest_api_key = bb_onesignal_rest_api_key();
            $app_id       = bb_onesignal_app_id();

            if ( empty( $rest_api_key ) || empty( $app_id ) ) {
                return $pushs;
            }

            $image = $notification['icon'];

            $fields = array(
                'app_id' => $app_id,
                'chrome_web_icon' => $image,
                'chrome_web_badge' => $image,
                'firefox_icon' => $image,
                'headings' => [ 'en' => stripslashes_deep(wp_specialchars_decode($notification['title'])) ],
                'url' => $notification['data']['url'],
                'contents' => [ 'en' => stripslashes_deep(wp_specialchars_decode($notification['body'])) ],
            );

            $pushs = [
                'onesignal_api_key' => $rest_api_key,
                'user_ids'          => array_map('strval', $all_recipients),
                'fields'            => $fields
            ];

            return $pushs;
        }

        public function has_message_threads( $bool, $messages_template, $r ){
            return true;
        }

        public function send_bb_app_push( $message ){
            $thread_id  = $message->thread_id;
            $send_push  = $message->send_push ?? false;

            if( ! $send_push ) return;

            $online = [];

            if( Better_Messages()->websocket && apply_filters('better_messages_bb_app_push_only_online', true) ) {
                $online = Better_Messages()->websocket->get_online_users();
            }

            $recipients = array_keys( $message->recipients );

            foreach ($recipients as $user_id) {
                if( isset( $online[ $user_id ] ) ) continue;

                if( Better_Messages()->functions->get_user_meta( $user_id, 'better_messages_new_message_app', true ) == 'no' ) {
                    continue;
                }

                // Check if user not muted the thread
                $muted_threads = Better_Messages()->functions->get_user_muted_threads( $user_id );

                if( isset($muted_threads[ $thread_id ]) ){
                    continue;
                }

                // Conversation URL
                $url = Better_Messages()->functions->get_user_thread_url( $thread_id, $user_id );
                $subject = sprintf( __('New message from %s', 'bp-better-messages'), Better_Messages()->functions->get_name( $message->sender_id ) );
                $content = sprintf( __('You have new message from %s', 'bp-better-messages'), Better_Messages()->functions->get_name( $message->sender_id ) );

                $args = [
                    'primary_text' => $subject,
                    'secondary_text' => $content,
                    'sent_as' => $message->sender_id,
                    'user_ids'  => [$user_id],
                    'data' => [
                        'link' => $url
                    ],
                    'type' => 'better_messages_better_messages_new_message',
                    'filter_users_by_subscription' => false
                ];

                bbapp_send_push_notification($args);
            }
        }

        public function overwrite_user_web_push_enabled($enabled, $user_id){
            return Better_Messages()->functions->get_user_meta( $user_id, 'better_messages_new_message_web', true ) != 'no';
        }

        public function remove_bb_notification_settings( $settings ){
            if( ! wp_doing_ajax() ) {
                if (isset($settings['messages'])) {
                    unset($settings['messages']);
                }
            }

            return $settings;
        }

        public function buddyboss_disable_rest_api_block( $default_exclude_endpoint, $current_endpoint ){
            if( strpos( $current_endpoint, 'better-messages/v1/' ) !== false ){
                $default_exclude_endpoint[] = $current_endpoint;
            }
            return $default_exclude_endpoint;
        }

        public function disable_bb_pusher( $options ){
            if( ! is_admin() ) {
                if (isset($options['live-messaging'])) {
                    $options['live-messaging'] = '0';
                }
            }
            return $options;
        }

        public function buddyboss_notifications_fix( $array ){
            if ( function_exists( 'buddyboss_theme_register_required_plugins' ) || class_exists('BuddyBoss_Theme') ) {
                if( count( $array['threads'] ) > 0 && isset( $array['total'] ) ) {
                    $new_threads = [];

                    foreach ($array['threads'] as $i => $thread) {
                        if ( ! isset($thread->last_message_date) || strtotime($thread->last_message_date) <= 0 ) {
                            unset($array['threads'][$i]);
                            $array['total']--;
                        } else {
                            $new_threads[] = $thread;
                        }
                    }


                    $array['threads'] = $new_threads;
                }
                if( $array['total'] < 0 ) $array['total'] = 0;
            }

            return $array;
        }

        public function heartbeat_unread_notifications( $response = array() ){
            if( Better_Messages()->settings['mechanism'] === 'websocket') {
                if (isset($response['total_unread_messages'])) {
                    unset($response['total_unread_messages']);
                }
            }

            return $response;
        }

        public function buddyboss_theme_get_header_unread_messages(){
            $response = array();
            ob_start();
            echo Better_Messages()->functions->get_conversations_layout();
            ?>
            <script type="text/javascript">
                var notification_list = jQuery('.site-header .messages-wrap .notification-list');
                notification_list.removeClass('notification-list').addClass('bm-notification-list');

                notification_list.css({'margin' : 0, 'padding' : 0});

                jQuery(document).trigger("bp-better-messages-init-scrollers");
            </script>
            <?php
            $response['contents'] = ob_get_clean();
            wp_send_json_success( $response );
        }

        public function buddyboss_group_messages( $message, $message_id, $context, $user_id ){
            global $wpdb;
            $group_id         = Better_Messages()->functions->get_message_meta( $message_id, 'group_id', true );
            $message_deleted  = Better_Messages()->functions->get_message_meta( $message_id, 'bp_messages_deleted', true );

            if( $group_id ) {
                if ( function_exists('bp_get_group_name') ) {
                    $group_name = bp_get_group_name(groups_get_group($group_id));
                } else {
                    $bp_prefix = bp_core_get_table_prefix();
                    $table = $bp_prefix . 'bp_groups';
                    $group_name = $wpdb->get_var( "SELECT `name` FROM `{$table}` WHERE `id` = '{$group_id}';" );
                }

                $message_left     = Better_Messages()->functions->get_message_meta( $message_id, 'group_message_group_left', true );
                $message_joined   = Better_Messages()->functions->get_message_meta( $message_id, 'group_message_group_joined', true );

                if ($message_left && 'yes' === $message_left) {
                    $message = '<i>' . sprintf(__('Left "%s"', 'bp-better-messages'), ucwords($group_name)) . '</i>';
                } else if ($message_joined && 'yes' === $message_joined) {
                    $message = '<i>' . sprintf(__('Joined "%s"', 'bp-better-messages'), ucwords($group_name)) . '</i>';
                }
            }

            if ( $message_deleted && 'yes' === $message_deleted ) {
                $message =  '<i>' . __( 'This message was deleted.', 'bp-better-messages' ) . '</i>';
            }

            return $message;
        }

        public function buddyboss_disable_message_to_blocked( $allowed, $user_id, $thread_id ){
            if ( ! bp_is_active( 'moderation' ) ) return $allowed;
            if( ! class_exists( 'BP_Moderation' ) ) return $allowed;
            if( ! function_exists( 'bp_moderation_is_user_blocked' ) ) return $allowed;

            $participants = Better_Messages()->functions->get_participants($thread_id);

            if( ! isset( $participants['recipients'] ) ) {
                return $allowed;
            }

            /**
             * Not block in group thread
             */
            if( count($participants['recipients']) > 1 ){
                return $allowed;
            }

            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'thread') return $allowed;

            foreach( $participants['recipients'] as $recipient_user_id ){
                if( bp_moderation_is_user_blocked( $recipient_user_id ) ){
                    global $bp_better_messages_restrict_send_message;
                    $bp_better_messages_restrict_send_message['bb_blocked_user'] = __( "You can't message a blocked member.", 'bp-better-messages' );
                    $allowed = false;

                    continue;
                }

                $moderation            = new BP_Moderation();
                $moderation->user_id   = $recipient_user_id;
                $moderation->item_id   = $user_id;
                $moderation->item_type = 'user';

                $id = BP_Moderation::check_moderation_exist( $user_id, 'user' );

                if ( ! empty( $id ) ) {
                    $moderation->id = (int) $id;
                    $moderation->populate();
                }

                $is_blocked = ( ! empty( $moderation->id ) && ! empty( $moderation->report_id ) );

                if( $is_blocked ){
                    global $bp_better_messages_restrict_send_message;
                    $bp_better_messages_restrict_send_message['bb_blocked_by_user'] = __("You can't message this member.", 'bp-better-messages');
                    $allowed = false;
                }
            }

            return $allowed;
        }

        public function buddyboss_blocked_message( $allowed, $user_id, $thread_id ){
            if( ! isset( $recipients ) ) return $allowed;
            if( ! is_array( $recipients ) ) return $allowed;
            if( count( $recipients ) === 0 ) return $allowed;

            $thread = new BP_Messages_Thread( $thread_id );

            $check_buddyboss_access = bb_access_control_member_can_send_message( $thread, $thread->recipients, 'wp_error' );

            if( is_wp_error($check_buddyboss_access) ){
                $allowed = false;
                global $bp_better_messages_restrict_send_message;
                $bp_better_messages_restrict_send_message['buddyboss_restricted'] = $check_buddyboss_access->get_error_message();
            }

            return $allowed;
        }

        public function bb_pushs_active(){
            if( function_exists('bb_onesignal_app_is_connected') ) {
                return bb_onesignal_app_is_connected();
            }

            return false;
        }
    }
}

