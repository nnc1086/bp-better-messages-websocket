<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_AI' ) ) {
    class Better_Messages_AI
    {

        public $api;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_AI();
            }

            return $instance;
        }

        public function __construct()
        {
            add_action( 'init',      array( $this, 'register_post_type' ) );

            add_filter( 'better_messages_rest_thread_item', array( $this, 'rest_thread_item'), 20, 5 );

            add_filter('better_messages_get_user_roles', array($this, 'get_user_roles'), 10, 2 );

            if ( version_compare(phpversion(), '8.1', '>=') ) {
                // Requires PHP 8.1+
                require_once "dependencies/autoload.php";
                require_once "api/open-ai.php";

                $this->api = Better_Messages_OpenAI_API::instance();

                add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
                add_action( 'save_post', array( $this, 'save_post' ), 1, 2 );

                add_action( 'bp_better_chat_settings_updated', array($this, 'check_if_api_key_valid'));

                add_action( 'better_messages_message_sent', array( $this, 'on_message_sent'), 10, 1 );

                add_action( 'bp_better_messages_new_thread_created', array( $this, 'on_new_thread_created'), 10, 2 );
                add_filter( 'better_messages_can_send_message', array( $this, 'block_reply_if_needed' ), 20, 3 );
                add_action( 'better_messages_before_new_thread',  array( $this, 'restrict_new_thread_if_needed'), 10, 2 );

                add_action('better_messages_ai_bot_ensure_completion', array( $this, 'ai_bot_ensure_completion'), 10, 2 );
            }
        }

        public function get_user_roles( $roles, $user_id )
        {
            if( $user_id < 0 ){
                $guest_id = absint($user_id);
                $guest = Better_Messages()->guests->get_guest_user( $guest_id );

                if( $guest && $guest->ip && str_starts_with($guest->ip, 'ai-chat-bot-') ){
                    $bot_id = str_replace('ai-chat-bot-', '', $guest->ip);

                    if( $this->bot_exists( $bot_id ) ){
                        $roles = ['bm-bot'];
                    }
                }
            }

            return $roles;
        }

        public function restrict_new_thread_if_needed( &$args, &$errors ){
            // Get array with recipients user ids, which user trying to start conversation with
            $recipients = $args['recipients'];

            if( $recipients && count( $recipients ) === 1 ){
                $recipient_id = reset( $recipients );

                if( $recipient_id < 0 ){
                    $guest_id = absint($recipient_id);
                    $guest = Better_Messages()->guests->get_guest_user( $guest_id );

                    if( $guest && $guest->ip && str_starts_with($guest->ip, 'ai-chat-bot-') ){
                        $bot_id = str_replace('ai-chat-bot-', '', $guest->ip);

                        if( $this->bot_exists( $bot_id ) ){
                            $bot_settings = $this->get_bot_settings( $bot_id );

                            if( $bot_settings['enabled'] !== '1' || empty( $bot_settings['model'] ) ){
                                $errors['bot_disabled'] = _x('The bot is currently disabled', 'AI Chat Bots', 'bp-better-messages');
                            }
                        }
                    }
                }
            }
        }

        public function on_new_thread_created( $thread_id, $message_id = null )
        {
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );

            if( $thread_type !== 'thread'){
                return;
            }

            $recipients = Better_Messages()->functions->get_recipients( $thread_id );

            if( count( $recipients ) === 2 ) {
                foreach ($recipients as $user) {
                    $user_id = $user->user_id;
                    if ($user_id < 0) {
                        $guest_id = absint($user_id);
                        $guest = Better_Messages()->guests->get_guest_user($guest_id);

                        if ($guest && $guest->ip && str_starts_with($guest->ip, 'ai-chat-bot-')) {
                            $bot_id = str_replace('ai-chat-bot-', '', $guest->ip);

                            if( $this->bot_exists( $bot_id ) ){
                                Better_Messages()->functions->update_thread_meta( $thread_id, 'ai_bot_thread', $bot_id );
                            }
                        }
                    }
                }
            }
        }

        public function bot_exists( $bot_id )
        {
            $post = get_post( $bot_id );

            if( $post && $post->post_type === 'bm-ai-chat-bot' && $post->post_status !== 'trash' ){
                return true;
            }

            return false;
        }

        public function is_bot_conversation( $bot_id, $thread_id )
        {
            $bot_thread_id = Better_Messages()->functions->get_thread_meta( $thread_id, 'ai_bot_thread' );

            if( empty( $bot_thread_id) ) return false;

            return (int) $bot_thread_id === (int) $bot_id;
        }

        public function block_reply_if_needed( $allowed, $user_id, $thread_id )
        {
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );

            if( $thread_type !== 'thread'){
                return $allowed;
            }

            $recipients = Better_Messages()->functions->get_recipients( $thread_id );

            if( count( $recipients ) === 2 ) {
                foreach ($recipients as $user) {
                    $user_id = $user->user_id;
                    if ($user_id < 0) {
                        $guest_id = absint($user_id);
                        $guest = Better_Messages()->guests->get_guest_user($guest_id);

                        if ($guest && $guest->ip && str_starts_with($guest->ip, 'ai-chat-bot-')) {
                            $bot_id = str_replace('ai-chat-bot-', '', $guest->ip);

                            if( $this->bot_exists( $bot_id ) && $this->is_bot_conversation( $bot_id, $thread_id ) ){
                                $bot_settings = $this->get_bot_settings($bot_id);

                                if ( $bot_settings['enabled'] !== '1'  || empty( $bot_settings['model'] ) ) {
                                    $allowed = false;
                                    global $bp_better_messages_restrict_send_message;
                                    $bp_better_messages_restrict_send_message['bot_is_disabled'] = _x('The bot is currently disabled', 'AI Chat Bots', 'bp-better-messages');
                                } else {
                                    $is_waiting_for_response = Better_Messages()->functions->get_thread_meta( $thread_id, 'ai_waiting_for_response' );

                                    if ($is_waiting_for_response) {
                                        $time_ago = time() - $is_waiting_for_response;
                                        $time_limit = 60 * 3; // 3 minutes

                                        if ($time_ago < $time_limit) {
                                            $allowed = false;
                                            global $bp_better_messages_restrict_send_message;
                                            $bp_better_messages_restrict_send_message['waiting_for_response'] = _x('Please wait until response is completed', 'AI Chat Bots', 'bp-better-messages');
                                        } else {
                                            Better_Messages()->functions->delete_thread_meta( $thread_id, 'ai_waiting_for_response' );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $allowed;
        }

        public function check_if_api_key_valid()
        {
            $api_key_exists = ! empty(Better_Messages()->settings['openAiApiKey']);

            if( $api_key_exists ){
                $this->api->check_api_key();
            } else {
                delete_option( 'better_messages_openai_error' );
            }
        }

        public function rest_api_init()
        {
            register_rest_route('better-messages/v1/ai', '/createResponse', array(
                'methods' => 'GET',
                'callback' => array( $this->api, 'reply_to_message'),
                'permission_callback' => '__return_true',
            ));

            register_rest_route('better-messages/v1/admin/ai', '/getModels', array(
                'methods' => 'GET',
                'callback' => array( $this->api, 'get_models'),
                'permission_callback' => array($this, 'user_is_admin'),
            ));
        }

        public function user_is_admin(){
            return current_user_can('manage_options');
        }

        public function register_post_type(){
            $args = array(
                'public'               => false,
                'labels'               => [
                    'name'          => _x( 'AI Chat Bots', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'singular_name' => _x( 'AI Chat Bot', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'add_new'       => _x( 'Create new AI Chat Bot', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'add_new_item'  => _x( 'Create new AI Chat Bot', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'edit_item'     => _x( 'Edit AI Chat Bot', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'new_item'      => _x( 'New AI Chat Bot', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'featured_image'        => _x( 'AI Chat Bot Avatar', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'set_featured_image'    => _x( 'Set AI Chat Bot avatar', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'remove_featured_image' => _x( 'Remove AI Chat Bot avatar', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                    'use_featured_image'    => _x( 'Use as AI Chat Bot avatar', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                ],
                'publicly_queryable'   => false,
                'show_ui'              => true,
                'show_in_menu'         => 'bp-better-messages',
                'menu_position'        => 1,
                'query_var'            => false,
                'capability_type'      => 'page',
                'has_archive'          => false,
                'hierarchical'         => false,
                'show_in_admin_bar'    => false,
                'show_in_nav_menus'    => false,
                'supports'             => array( 'title', 'thumbnail' ),
                'register_meta_box_cb' => array( $this, 'register_meta_box' )

            );

            register_post_type( 'bm-ai-chat-bot', $args );
        }

        public function register_meta_box()
        {
            add_meta_box(
                'bm-ai-chat-bot-settings',
                _x( 'Settings', 'Chat rooms settings page', 'bp-better-messages' ),
                array( $this, 'bot_settings' ),
                null,
                'advanced'
            );
        }

        public function bot_settings( $post )
        {
            $roles = get_editable_roles();
            if(isset($roles['administrator'])) unset( $roles['administrator'] );

            $roles['bm-guest'] = [
                'name' => _x('Guests', 'Settings page', 'bp-better-messages' )
            ];

            $settings = $this->get_bot_settings( $post->ID );

            wp_nonce_field( 'bm-save-ai-chat-bot-settings-' . $post->ID, 'bm_save_ai_chat_bot_nonce' );

            $bot = $this->get_bot_user( $post->ID );
            $voices = $this->get_voices();
            $bot_user_id = $bot ? absint($bot->id) * -1 : 0;

            $openai_error = get_option( 'better_messages_openai_error', false );
            $api_key_exists = ! empty(Better_Messages()->settings['openAiApiKey']) && empty($openai_error);

            $voice_messages_banner = '';
            if( ! class_exists('BP_Better_Messages_Voice_Messages') ){
                $voice_messages_banner = '<div class="bp-better-messages-banner bm-error">';
                $voice_messages_banner .= sprintf(_x('<a href="%s" target="_blank">Voice Messages</a> add-on is required to use audio models.', 'Settings page', 'bp-better-messages'), admin_url('admin.php?page=bp-better-messages-addons') );
                $voice_messages_banner .= '</div>';
            }

            if (version_compare(phpversion(), '8.1', '<')) { ?>
            <div class="bm-admin-error" style="font-size: 150%;margin: 10px 0">
                <?php echo sprintf(esc_html_x('Website must to have PHP version %s or higher, currently PHP version %s is used.', 'Settings page', 'bp-better-messages'), '<strong>8.1</strong>', '<strong>' . phpversion() . '</strong>' ); ?>
            </div>
            <?php } else if ( ! $api_key_exists ){ ?>
                <div class="bm-admin-error" style="font-size: 150%;margin: 10px 0">
                    <?php echo sprintf(_x('Website must have valid Open AI Api Key, setup key at <a href="%s">settings page</a>.', 'Settings page', 'bp-better-messages'), add_query_arg( 'page', 'bp-better-messages', admin_url('admin.php') ) . '#integrations_openai' ); ?>
                </div>
            <?php } else  { ?>
            <div class="bm-ai-chat-bot-settings"
                 data-bot-id="<?php echo esc_attr($post->ID); ?>"
                 data-bot-user-id="<?php echo $bot_user_id; ?>"
                 data-settings="<?php echo esc_attr(json_encode($settings)); ?>"
                 data-roles="<?php echo esc_attr(json_encode($roles)); ?>"
                 data-voices="<?php echo esc_attr(json_encode($voices)); ?>"
                 data-voice-messages-banner="<?php echo esc_attr($voice_messages_banner); ?>">
                <p style="text-align: center"><?php _ex( 'Loading',  'WP Admin', 'bp-better-messages' ); ?></p>
            </div>
            <?php
            }
        }

        public function get_default_settings()
        {
            $voices = $this->get_voices();

            $defaults = array(
                "enabled" => "0",
                "images"  => "0",
                "model"   => "",
                "instruction" => _x( 'You are a helpful assistant', 'AI Chat Bots (WP Admin)', 'bp-better-messages' ),
                "voice" => $voices[0]
            );

            return $defaults;
        }

        public function get_voices()
        {
            return [
                'alloy',
                'ash',
                'ballad',
                'coral',
                'echo',
                'sage',
                'shimmer',
                'verse'
            ];
        }

        public function get_bot_settings( $bot_id )
        {
            $defaults = $this->get_default_settings();

            $args = get_post_meta( $bot_id, 'bm-ai-chat-bot-settings', true );

            if( empty( $args ) || ! is_array( $args ) ){
                $args = array();
            }

            if( ! isset( $args['images'] ) && ! empty($args['model'] )  ){
                $args['images'] = str_contains($args['model'], 'gpt-4-turbo') || str_contains($args['model'], 'gpt-4o') ? '1' : '0';
            }

            if( isset( $args['voice'] ) ){
                $voices = $this->get_voices();

                if( ! in_array( $args['voice'], $voices ) ){
                    $args['voice'] = $defaults['voice'];
                }
            }

            $result = wp_parse_args( $args, $defaults );

            return $result;
        }

        public function save_post( $post_id, $post ){
            if( ! isset($_POST['bm_save_ai_chat_bot_nonce']) ){
                return $post->ID;
            }

            //Verify it came from proper authorization.
            if ( ! wp_verify_nonce($_POST['bm_save_ai_chat_bot_nonce'], 'bm-save-ai-chat-bot-settings-' . $post->ID ) ) {
                return $post->ID;
            }

            //Check if the current user can edit the post
            if ( ! current_user_can( 'manage_options' ) ) {
                return $post->ID;
            }

            if( isset( $_POST['bm'] ) && is_array($_POST['bm']) ){
                $old_settings = $this->get_bot_settings( $post->ID );

                $settings = (array) $_POST['bm'];

                if( ! $settings['model'] ){
                    $settings['model'] = $old_settings['model'];
                }

                $defaults = $this->get_default_settings();

                $settings = wp_parse_args( $settings, $defaults );

                update_post_meta( $post->ID, 'bm-ai-chat-bot-settings', $settings );

                $this->create_or_update_bot_user( $post->ID, $post->post_title );
            }
        }

        public function get_bot_user( $bot_id )
        {
            $bot_user = wp_cache_get( 'bot_user_' . $bot_id, 'bm_messages' );

            if( $bot_user ){
                return $bot_user;
            }

            global $wpdb;

            $query = $wpdb->prepare( "SELECT * FROM `" . bm_get_table('guests') . "` WHERE `ip` = %s AND `deleted_at` IS NULL", "ai-chat-bot-" . $bot_id );

            $guest_user = $wpdb->get_row( $query );

            if( $guest_user ){
                wp_cache_set( 'bot_user_' . $bot_id, $guest_user, 'bm_messages' );

                return $guest_user;
            } else {
                return false;
            }
        }

        public function create_or_update_bot_user( $bot_id, $name )
        {
            $bot = $this->get_bot_user( $bot_id );

            if( $bot ){
                if( $bot->name != $name ){
                    global $wpdb;

                    $wpdb->update( bm_get_table('guests'), ['name' => $name], ['id' => $bot->id] );
                    do_action( 'better_messages_guest_updated', absint($bot->id) * -1 );
                    do_action( 'better_messages_user_updated', absint($bot->id) * -1 );
                }
            } else {
                global $wpdb;

                $result = $wpdb->insert( bm_get_table('guests'), [
                    'ip'     => "ai-chat-bot-" . $bot_id,
                    'name'   => $name,
                    'secret' => ''
                ] );

                if( $result ) {
                    $bot_id = $wpdb->insert_id;
                    do_action('better_messages_guest_updated', absint($bot_id) * -1);
                    do_action('better_messages_user_updated', absint($bot_id) * -1);
                }
            }
        }

        public function rest_thread_item( $thread_item, $thread_id, $thread_type, $include_personal, $user_id ){
            if( $thread_type !== 'thread'){
                return $thread_item;
            }

            $recipients = $thread_item['participants'];
            if( count( $recipients ) === 2 ){
                foreach( $recipients as $user_id ){
                    if( $user_id < 0 ) {
                        $guest_id = absint($user_id);
                        $guest = Better_Messages()->guests->get_guest_user($guest_id);

                        if ( $guest && $guest->ip && str_starts_with($guest->ip, 'ai-chat-bot-') ) {
                            $bot_id = str_replace('ai-chat-bot-', '', $guest->ip);
                            if ( $this->is_bot_conversation($bot_id, $thread_id) ) {
                                $settings = $this->get_bot_settings($bot_id);

                                $thread_item['botId'] = (int) $bot_id;
                                $thread_item['permissions']['canAudioCall'] = false;
                                $thread_item['permissions']['canVideoCall'] = false;
                                $thread_item['permissions']['canEditOwnMessages'] = false;
                                $thread_item['permissions']['canDeleteOwnMessages'] = false;
                                $thread_item['permissions']['canDeleteAllMessages'] = false;
                                $thread_item['permissions']['canInvite'] = false;
                                $thread_item['permissions']['preventReplies'] = true;

                                $thread_item['permissions']['preventVoiceMessages'] = ( ! str_contains($settings['model'], '-audio-') || ! class_exists('BP_Better_Messages_Voice_Messages') );

                                if (isset($thread_item['permissions']['canUpload'])) {
                                    $support_images = $settings['images'];
                                    $thread_item['permissions']['canUpload'] = (bool) $support_images;

                                    if ($support_images) {
                                        $thread_item['permissions']['canUploadExtensions'] = ['.png', '.jpg', '.jpeg', '.gif', '.webp'];
                                        $thread_item['permissions']['canUploadMaxSize'] = 20;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $thread_item;
        }

        public function on_message_sent( $message )
        {
            // Sender ID
            $sender_id = (int) $message->sender_id;

            $new_thread = $message->new_thread;

            // Recipients User IDs
            $recipients = Better_Messages()->functions->get_recipients( (int) $message->thread_id );

            if( count( $recipients ) === 2 ){
                foreach ($recipients as $recipient){
                    $user_id = (int) $recipient->user_id;

                    if( $sender_id !== $user_id && $user_id < 0 ){
                        $guest_id = absint($user_id);
                        $guest = Better_Messages()->guests->get_guest_user( $guest_id );

                        if( $guest && $guest->ip && str_starts_with($guest->ip, 'ai-chat-bot-') ){
                            $bot_id = (int) str_replace('ai-chat-bot-', '', $guest->ip);

                            if( $this->bot_exists( $bot_id ) && ( $this->is_bot_conversation( $bot_id, $message->thread_id ) || $new_thread ) ){
                                $bot_settings = $this->get_bot_settings( $bot_id );
                                Better_Messages()->functions->update_message_meta($message->id, 'ai_bot_id', $bot_id);
                                Better_Messages()->functions->update_message_meta($message->id, 'ai_waiting_for_response', time());
                                Better_Messages()->functions->update_thread_meta($message->thread_id, 'ai_waiting_for_response', time());

                                do_action('better_messages_thread_self_update', $message->thread_id, $sender_id);
                                do_action('better_messages_thread_updated', $message->thread_id, $sender_id);

                                if ( ! empty( Better_Messages()->settings['openAiApiKey'] ) && ! empty( $bot_settings['model'] ) ) {
                                    if( ! wp_get_scheduled_event( 'better_messages_ai_bot_ensure_completion', [ $bot_id, $message->id ] ) ){
                                        wp_schedule_single_event( time() + 15, 'better_messages_ai_bot_ensure_completion', [ $bot_id, $message->id ] );
                                    }

                                    $url = add_query_arg([
                                        'bot_id' => $bot_id,
                                        'message_id' => $message->id
                                    ], Better_Messages()->functions->get_rest_api_url() . 'ai/createResponse');

                                    wp_remote_get($url, [
                                        'blocking' => false,
                                        'timeout' => 0
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        public function ai_bot_ensure_completion( $bot_id, $message_id )
        {
            $this->api->process_reply( $bot_id, $message_id );
        }
    }

    function Better_Messages_AI(){
        return Better_Messages_AI::instance();
    }
}
