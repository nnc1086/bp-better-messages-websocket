<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Peepso' ) ){

    class Better_Messages_Peepso
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Peepso();
            }

            return $instance;
        }

        public function __construct()
        {
            /**
             * Adding header button
             */
            add_filter('peepso_navigation', array(&$this, 'filter_peepso_navigation'));

            add_filter('peepso_profile_actions', array(&$this, 'profile_actions'), 99, 2);
            add_filter('peepso_friends_friend_options', array(&$this, 'member_options'), 10, 2);

            add_filter('peepso_friends_friend_buttons', array(&$this, 'member_buttons'), 20, 2);
            add_filter('peepso_member_buttons', array(&$this, 'member_buttons'), 20, 2);

            add_action('wp_head', array($this, 'counter_in_header'));

            add_action('wp_footer', array( $this, 'messages_list_popup' ) );

            if (Better_Messages()->settings['peepsoHeader'] === '1' && !wp_doing_ajax()) {
                add_action('bp_better_messages_before_main_template_rendered', array($this, 'before_main_template_rendered'));
                add_action('bp_better_messages_after_main_template_rendered', array($this, 'after_main_template_rendered'));
            }

            if ( class_exists('PeepSoFriendsPlugin') ) {
                add_filter('better_messages_friends_active', array($this, 'enabled'));
                add_filter('better_messages_get_friends', array($this, 'get_friends'), 10, 2);
                add_filter('better_messages_is_friends', array($this, 'is_friends'), 10, 3);
                add_filter('better_messages_search_friends', array( $this, 'search_friends'), 10, 3 );

                if (Better_Messages()->settings['PSonlyFriendsMode'] === '1') {
                    add_filter( 'better_messages_only_friends_mode', array($this, 'enabled') );
                    add_filter( 'better_messages_can_send_message', array($this, 'disable_non_friends_reply'), 10, 3 );
                    add_action( 'better_messages_before_new_thread', array($this, 'disable_start_thread_for_non_friends'), 15, 2 );
                }
            }

            if ( class_exists('PeepSoGroupsPlugin') ) {
                require_once Better_Messages()->path . 'addons/peepso-groups.php';
                Better_Messages_Peepso_Groups::instance();
            }

            add_filter('bp_better_messages_script_variable', array( $this, 'script_variables' ) );

            //add_filter('better_messages_is_verified', array( $this, 'user_verified' ), 20, 2 );
            add_filter('better_messages_rest_user_item', array( $this, 'rest_user_item'), 20, 3 );

            if( class_exists('PeepSoBlockUsers' ) && PeepSo::get_option('user_blocking_enable', 0) === 1 ) {
                add_filter('better_messages_can_send_message', array($this, 'disable_blocked_replies'), 20, 3);
                add_action('better_messages_before_new_thread', array($this, 'disable_start_thread_for_blocked_users'), 20, 2);
                add_filter( 'better_messages_rest_user_item', array( $this, 'blocked_user_item'), 10, 4 );
            }

            add_filter( 'better_messages_rest_user_item', array( $this, 'rest_user_item'), 10, 4 );
        }

        public function blocked_user_item( $item, $user_id, $include_personal ){
            if( $include_personal && Better_Messages()->functions->get_current_user_id() !== $user_id ){
                $PeepSoBlockUsers = new PeepSoBlockUsers();
                $item['blocked'] = (int) $PeepSoBlockUsers->is_user_blocking( Better_Messages()->functions->get_current_user_id(), $user_id );
            }

            return $item;
        }

        public function disable_start_thread_for_blocked_users(&$args, &$errors){
            if( current_user_can('manage_options' ) ) {
                return null;
            }

            $recipients = $args['recipients'];
            if( ! is_array( $recipients ) ) $recipients = [ $recipients ];

            $PeepSoBlockUsers = new PeepSoBlockUsers();

            foreach($recipients as $user_id) {
                if(  Better_Messages()->functions->is_valid_user_id( $user_id ) ) {
                    $is_blocked_1 = $PeepSoBlockUsers->is_user_blocking(Better_Messages()->functions->get_current_user_id(), $user_id );
                    if ($is_blocked_1) {
                        $errors[] = sprintf(_x('%s blocked by you', 'Error when starting new thread but user blocked', 'bp-better-messages'), Better_Messages()->functions->get_name($user_id));
                        continue;
                    }

                    $is_blocked_2 = $PeepSoBlockUsers->is_user_blocking($user_id, Better_Messages()->functions->get_current_user_id());
                    if ($is_blocked_2) {
                        $errors[] = sprintf(_x('%s blocked you', 'Error when starting new thread but user blocked', 'bp-better-messages'), Better_Messages()->functions->get_name($user_id));
                        continue;
                    }
                }
            }
        }

        public function disable_blocked_replies( $allowed, $user_id, $thread_id ){
            $current_user_id = Better_Messages()->functions->get_current_user_id();

            if( ! Better_Messages()->functions->is_valid_user_id( $user_id ) ) {
                return $allowed;
            }

            $roles = Better_Messages()->functions->get_user_roles( $current_user_id );

            if( in_array( 'administrator', $roles ) ){
                return $allowed;
            }

            $type = Better_Messages()->functions->get_thread_type( $thread_id );

            if( $type !== 'thread' ) return $allowed;

            $participants = Better_Messages()->functions->get_participants($thread_id);

            if( count($participants['recipients']) !== 1) return $allowed;

            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'thread' ) return $allowed;

            $user_id_2 = array_pop($participants['recipients']);

            $PeepSoBlockUsers = new PeepSoBlockUsers();

            /**
             *  Current user blocked other
             */
            $is_blocked_1 = $PeepSoBlockUsers->is_user_blocking( Better_Messages()->functions->get_current_user_id(), $user_id_2 );
            if( $is_blocked_1 ) {
                global $bp_better_messages_restrict_send_message;
                $bp_better_messages_restrict_send_message['user_blocked_messages'] = _x("You can't send message to user who was blocked by you", 'Message when user cant send message to user blocked by him' ,'bp-better-messages');
                return false;
            }

            /**
             *  Other user blocked current user
             */
            $is_blocked_2 = $PeepSoBlockUsers->is_user_blocking( $user_id_2, Better_Messages()->functions->get_current_user_id() );

            if( $is_blocked_2 ) {
                global $bp_better_messages_restrict_send_message;
                $bp_better_messages_restrict_send_message['user_blocked_messages'] = _x("You can't send message to user who blocked you", 'Message when user cant send message to user who blocked him' ,'bp-better-messages');
                return false;
            }

            return $allowed;
        }

        function rest_user_item( $item, $user_id, $include_personal ){
            if( $user_id <= 0 ) return $item;

            $user = PeepSoUser::get_instance( $user_id );

            if( $user ){
                $name = $user->get_fullname();

                if(class_exists('PeepSoVIP') ){
                    $vip = PeepSoVIP::get_instance();
                    $icons = $vip->get_user_icons( $user_id );
                    if( is_array( $icons ) && count($icons) > 0 ) {
                        //$display = PeepSo::get_option('vipso_where_to_display', 1);
                        $limit = PeepSo::get_option('vipso_display_how_many', 10);

                        $icons_html = '';
                        $i = 0;
                        foreach ($icons as $icon) {
                            if (intval($icon->published) == 1) {
                                if( $i >= $limit) {
                                    break;
                                }

                                $icons_html .= ' <img src="' . esc_url($icon->icon_url) . '" alt="'.esc_attr($icon->title).'" title="'.esc_attr($icon->title) .'" class="ps-vip__icon ps-js-vip-badge" data-id="'.esc_attr($user_id).'"> ';

                                $i++;
                            }
                        }

                        if( ! empty( $icons_html ) ) {
                            $name = $name . $icons_html;
                        }
                    }
                }

                $item['name'] = $name;
                $item['url']  = $user->get_profileurl();
                $avatar = $user->get_avatar();

                if( $avatar ) {
                    $item['avatar'] = $avatar;
                }
            }

            return $item;
        }

        function user_verified( $is_verified, $user_id ){
            $icons = get_the_author_meta( 'peepso_vip_user_icon', $user_id ) ;

            if( ! empty($icons) ) {
                return true;
            }

            // Otherwise return previous var
            return $is_verified;
        }

        public function search_friends( $result, $search, $user_id  ){
            if( ! class_exists('PeepSoFriendsModel') || $user_id < 0 ) return $result;

            $friends = PeepSoFriendsModel::get_instance()->get_friends_ids( $user_id );

            if( count( $friends ) > 0 ){
                global $wpdb;

                $sql = $wpdb->prepare("
                SELECT ID FROM `{$wpdb->users}`
                WHERE ( `user_nicename` LIKE %s OR `display_name` LIKE %s )
                AND `ID` IN (" . implode(',', array_map( 'intval', $friends ) ) . ")
                LIMIT 0, 10
                ", '%' . $search . '%', '%' . $search . '%', $user_id);

                $matched_friends = $wpdb->get_col($sql);

                if( count( $matched_friends ) > 0 ){
                    foreach( $matched_friends as $friend ){
                        $result[] = intval( $friend );
                    }
                }
            }

            return $result;
        }

        public function enabled( $var ){
            return true;
        }

        public function is_friends( $bool, $user_id_1, $user_id_2 ){
            if( ! class_exists('PeepSoFriendsModel') ) return false;
            return !! ( PeepSoFriendsModel::get_instance()->are_friends( $user_id_1, $user_id_2 ) );
        }

        public function get_friends( $friends, $user_id ){
            if( ! class_exists('PeepSoFriendsModel') ) return [];

            $friends = PeepSoFriendsModel::get_instance()->get_friends_ids( $user_id );

            $users = [];

            if( !! $friends && count( $friends ) > 0 ) {
                foreach($friends as $index => $friend_id){
                    $user = get_userdata($friend_id);
                    if( ! $user ) continue;

                    $users[] = Better_Messages()->functions->rest_user_item( $user->ID );
                }
            }

            return $users;
        }


        public function script_variables( $script_variables ){
            if( Better_Messages()->settings['PSminiGroupsEnable'] === '1' ) {
                $script_variables['miniGroups'] = '1';
            }
            if( Better_Messages()->settings['PScombinedGroupsEnable'] === '1' ) {
                $script_variables['combinedGroups'] = '1';
            }
            if( Better_Messages()->settings['PSmobileGroupsEnable'] === '1' ) {
                $script_variables['mobileGroups'] = '1';
            }

            if( Better_Messages()->settings['PSminiFriendsEnable'] === '1' ) {
                $script_variables['miniFriends'] = '1';
            }
            if( Better_Messages()->settings['PScombinedFriendsEnable'] === '1' ) {
                $script_variables['combinedFriends'] = '1';
            }
            if( Better_Messages()->settings['PSmobileFriendsEnable'] === '1' ) {
                $script_variables['mobileFriends'] = '1';
            }

            return $script_variables;
        }

        public function disable_start_thread_for_non_friends(&$args, &$errors){
            if( ! class_exists('PeepSoFriendsModel') ) {
                return null;
            }

            if( current_user_can('manage_options' ) ) {
                return null;
            }

            if( ! is_array( $errors) ) {
                $errors = [];
            }

            if( count( $errors ) > 0 ) return null;

            $recipients = $args['recipients'];

            if( ! is_array( $recipients ) ) $recipients = [ $recipients ];

            $notFriends = array();

            foreach($recipients as $recipient){
                if( $recipient < 0 ) continue;

                $user = get_userdata( $recipient );

                if( ! PeepSoFriendsModel::get_instance()->are_friends( get_current_user_id(), $user->ID ) ) {
                    $notFriends[] = Better_Messages()->functions->get_name($user->ID);
                }
            }

            if(count($notFriends) > 0){
                $message = sprintf(__('%s not on your friends list', 'bp-better-messages'), implode(', ', $notFriends));
                $errors[] = $message;
            }

        }

        public function disable_non_friends_reply( $allowed, $user_id, $thread_id ){
            if( ! class_exists('PeepSoFriendsModel') ) {
                return $allowed;
            }

            $type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $type !== 'thread' ) return $allowed;

            $participants = Better_Messages()->functions->get_participants($thread_id);
            if( count($participants['recipients']) !== 1) return $allowed;
            reset($participants['recipients']);

            $friend_id = key($participants['recipients']);

            /**
             * Allow users reply to admins even if not friends
             */
            if( current_user_can('manage_options') || user_can( $friend_id, 'manage_options' ) || $friend_id < 0 ) {
                return $allowed;
            }


            $allowed = PeepSoFriendsModel::get_instance()->are_friends( $user_id, $friend_id );

            if( ! $allowed ){
                global $bp_better_messages_restrict_send_message;
                $bp_better_messages_restrict_send_message['friendship_needed'] = __('You must become friends to send messages', 'bp-better-messages');
            }

            return $allowed;
        }

        public function before_main_template_rendered(){
            if( ! is_page() ) return;
            echo PeepSoTemplate::get_before_markup();
            echo '<div class="peepso">';
            echo '<div class="ps-page ps-page--messages">';
            PeepSoTemplate::exec_template('general','navbar');
        }

        public function after_main_template_rendered(){
            if( ! is_page() ) return;
            echo '</div></div>';
            echo PeepSoTemplate::get_after_markup();
        }

        /**
         * Add the send message button when a user is viewing the friends list
         * @param  array $options
         * @return array
         */
        public function member_options($options, $user_id)
        {
            $options['bm_message'] = array(
                'label' => _x('Send Message', 'PeepSo Integration', 'bp-better-messages'),
                'click'    => 'BPBMOpenUrlOrNewTab("' . Better_Messages()->functions->pm_link( $user_id ) . '"); event.preventDefault()',
                'icon' => 'comment',
                'loading' => FALSE,
            );

            return ($options);
        }

        /**
         * Add the send message button when a user is viewing the friends list
         * @param  array $options
         * @return array
         */
        public function member_buttons($options, $user_id)
        {

            $current_user = intval(get_current_user_id());

            if ($current_user !== $user_id ) {

                if( Better_Messages()->settings['psForceMiniChat'] === '0' ) {
                    $options['bm_message'] = array(
                        'class' => 'ps-member__action ps-member__action--message',
                        'click' => 'BPBMOpenUrlOrNewTab("' . Better_Messages()->functions->pm_link($user_id) . '"); event.preventDefault()',
                        'icon' => 'gcir gci-envelope',
                        'loading' => FALSE,
                    );
                } else {
                    $options['bm_message'] = array(
                        'class' => 'ps-member__action ps-member__action--message bpbm-pm-button open-mini-chat bm-no-style bm-no-loader',
                        'click' => 'event.preventDefault()',
                        'icon' => 'gcir gci-envelope',
                        'loading' => FALSE,
                        'extra' => 'data-user-id="' . $user_id . '"'
                    );
                }
            }
            return ($options);
        }

        public function profile_actions($act, $user_id)
        {

            $current_user = intval( get_current_user_id() );

            if ($current_user !== $user_id ) {
                if( Better_Messages()->settings['psForceMiniChat'] === '0' ) {
                    $act['bm_message'] = array(
                        'icon' => 'gcir gci-envelope',
                        'class' => 'ps-focus__cover-action',
                        'title' => _x('Start a conversation', 'PeepSo Integration', 'bp-better-messages'),
                        'click' => 'BPBMOpenUrlOrNewTab("' . Better_Messages()->functions->pm_link($user_id) . '"); event.preventDefault()',
                        'loading' => FALSE,
                        'extra' => 'data-user-id="' . $user_id . '"'
                    );
                } else {
                    $act['bm_message'] = array(
                        'icon' => 'gcir gci-envelope',
                        'class' => 'ps-focus__cover-action bpbm-pm-button open-mini-chat bm-no-style bm-no-loader',
                        'title' => _x('Start a conversation', 'PeepSo Integration', 'bp-better-messages'),
                        'click' => 'event.preventDefault()',
                        'loading' => FALSE,
                        'extra' => 'data-user-id="' . $user_id . '"'
                    );
                }

                $base_link = Better_Messages()->functions->get_link( get_current_user_id() );

                if( Better_Messages()->settings['peepsoProfileVideoCall'] === '1') {
                    $link = add_query_arg([
                        'fast-call' => '',
                        'to' => $user_id,
                        'type' => 'video'
                    ], $base_link);

                    $act['bm_video_call'] = array(
                        'icon' => 'gci gci-video',
                        'class' => 'ps-focus__cover-action bpbm-pm-button video-call bm-no-style bm-no-loader',
                        'title' => _x('Video Call', 'PeepSo Integration', 'bp-better-messages'),
                        'click' => 'event.preventDefault();',
                        'loading' => FALSE,
                        'extra' => 'data-user-id="' . $user_id . '" data-url="' . $link . '"'
                    );
                }

                if( Better_Messages()->settings['peepsoProfileAudioCall'] === '1') {
                    $link = add_query_arg([
                        'fast-call' => '',
                        'to' => $user_id,
                        'type' => 'audio'
                    ], $base_link);

                    $act['bm_audio_call'] = array(
                        'icon' => 'gci gci-phone',
                        'class' => 'ps-focus__cover-action bpbm-pm-button audio-call bm-no-style bm-no-loader',
                        'title' => _x('Audio Call', 'PeepSo Integration', 'bp-better-messages'),
                        'click' => 'event.preventDefault();',
                        'loading' => FALSE,
                        'extra' => 'data-user-id="' . $user_id . '" data-url="' . $link . '"'
                    );
                }
            }

            return ($act);
        }

        public function filter_peepso_navigation($navigation)
        {

            $received = array(
                'href'              => Better_Messages()->functions->get_link(),
                'icon'              => 'gcis gci-envelope',
                'class'             => 'ps-notif--better-messages',
                'title'             => _x('Messages', 'PeepSo Integration', 'bp-better-messages'),
                'label'             => _x('Messages', 'Peepso Integration', 'bp-better-messages'),
                'count'             => 0,
                'primary'           => FALSE,
                'secondary'         => TRUE,
                'mobile-primary'    => FALSE,
                'mobile-secondary'  => TRUE,
                'widget'            => FALSE,
                'notifications'     => TRUE,
                'icon-only'         => TRUE,
            );

            $navigation['better-messages-notification'] = $received;

            return ($navigation);
        }

        public function messages_list_popup(){
            if( ! is_user_logged_in() ) return false;

            $inbox_url = Better_Messages()->functions->get_user_messages_url( get_current_user_id() );
            ob_start(); ?>
            <script type="text/javascript">
                var headerButtons = document.querySelectorAll('.ps-notif--better-messages');

                headerButtons.forEach( function(headerButton) {
                    var html =
                        '<div class="ps-notif__box" style="display:none;">' +
                        '<div class="ps-notif__box-header">' +
                        '<div class="ps-notif__box-title"><?php echo esc_attr_x('Messages', 'PeepSo Integration', 'bp-better-messages'); ?></div>' +
                        '<div class="ps-notif__box-actions">' +
                        '<a href="#" onclick="event.preventDefault();BetterMessages.openNewConversationWidget();"><?php echo esc_attr_x('New message', 'PeepSo Integration', 'bp-better-messages'); ?></a>' +
                        '</div>' +
                        '</div>' +
                        '<div class="ps-notifications ps-notifications--empty" style="max-height: 400px !important; overflow: hidden;">' +
                        '<div class="bp-messages-wrap bm-threads-list" style="height:400px"></div>' +
                        '</div>' +
                        '<div class="ps-notif__box-footer"><a href="<?php echo $inbox_url; ?>"><?php echo esc_attr_x('View All', 'PeepSo Integration', 'bp-better-messages'); ?></a>' +
                        '</div>' +
                        '</div>';

                    headerButton.innerHTML += html;

                    var popup = headerButton.querySelector('.ps-notif__box');
                    var link = jQuery(headerButton).find('> a');

                    function handleClickOutside(event) {
                        if (!popup.contains(event.target) && !headerButton.contains(event.target)) {
                            if (jQuery(popup).is(':visible')) {
                                jQuery(popup).slideToggle();
                                document.removeEventListener('click', handleClickOutside);
                            }
                        }
                    }


                    if( link[0] ) {
                        link[0].onclick = function (event) {
                            event.preventDefault();
                            jQuery(popup).slideToggle();

                            if (jQuery(popup).is(':visible')) {
                                document.addEventListener('click', handleClickOutside);
                            } else {
                                document.removeEventListener('click', handleClickOutside);
                            }
                        };
                    }
                });

                jQuery(document).trigger("bp-better-messages-init-scrollers");
            </script>
            <?php
            $script = ob_get_clean();

            echo Better_Messages()->functions->minify_js( $script );
        }

        public function counter_in_header(){
            if( ! is_user_logged_in() ) return false;
            ob_start(); ?>
            <script type="text/javascript">
                jQuery(document).on('bp-better-messages-update-unread', function( event ) {
                    var unread = parseInt(event.detail.unread);
                    var private_messages = jQuery('.ps-notif--better-messages .js-counter');

                    private_messages.each(function(){
                        var item = jQuery(this);
                        if( unread > 0 ){
                            item.text(unread);
                        } else {
                            item.text('');
                        }
                    });
                });
            </script>
            <?php
            $script = ob_get_clean();

            echo Better_Messages()->functions->minify_js( $script );
        }
    }
}
