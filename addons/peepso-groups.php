<?php

defined( 'ABSPATH' ) || exit;
if ( !class_exists( 'Better_Messages_Peepso_Groups' ) ) {

    class Better_Messages_Peepso_Groups
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Peepso_Groups();
            }

            return $instance;
        }

        public function __construct(){
            if( class_exists( 'PeepSoGroupsPlugin' ) && Better_Messages()->settings['PSenableGroups'] === '1' ) {
                add_filter('better_messages_is_valid_group', array( $this, 'is_valid_group' ), 10, 2 );

                add_action('peepso_action_render_group_settings_form_after', array( $this, 'render_group_settings_form_after' ), 10, 2 );
                add_action('peepso_action_group_update_unknown_property', array( $this, 'update_unknown_property'),10 ,3 );

                add_filter('better_messages_groups_active', array($this, 'enabled') );
                add_filter('better_messages_has_access_to_group_chat', array( $this, 'has_access_to_group_chat'), 10, 3 );
                add_filter('better_messages_can_send_message', array( $this, 'can_reply_to_group_chat'), 10, 3 );

                add_filter( 'better_messages_get_groups', array($this, 'get_groups'), 10, 2 );

                add_filter('peepso_group_segment_menu_links', array($this, 'add_group_tab'), 10, 1);
                add_action('peepso_group_segment_messages', array(&$this, 'group_segment_messages'));

                add_action('peepso_action_group_user_join', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_cancel_join_request', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_delete', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_role_change_manager', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_role_change_owner', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_role_change_moderator', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_join_request_accept', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_role_change_member', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_user_delete', array($this, 'on_something_changed'), 10, 2);
                add_action('peepso_action_group_add', array($this, 'on_something_changed'), 10, 2);

                if (Better_Messages()->settings['PSenableGroupsFiles'] === '0') {
                    add_action('bp_better_messages_user_can_upload_files', array($this, 'disable_upload_files'), 10, 3);
                }

                add_filter('better_messages_thread_title', array( $this, 'group_thread_title' ), 10, 3 );
                add_filter('better_messages_thread_image', array( $this, 'group_thread_image' ), 10, 3 );
                add_filter('better_messages_thread_url',   array( $this, 'group_thread_url' ), 10, 3 );
            }
        }

        function update_unknown_property($key, $value, $group) {
            if($key == 'bpbm_messages') {
                $group_user = new PeepSoGroupUser($group->id);

                if($group_user->can('manage_group') && in_array($value, ["enabled","disabled"])) {
                    update_post_meta($group->id, 'bpbm_messages', $value);
                }

                return true;
            }
        }

        public function render_group_settings_form_after( $group, $group_user )
        {
            if( $group instanceof PeepSoGroup && $group_user instanceof PeepSoGroupUser && $group_user->can('manage_group') ) {
                $setting_key = 'bpbm_messages';
                $setting = get_post_meta($group->id, $setting_key, true);
                $setting = $setting ? $setting : 'enabled';
                ?>
                <div class="ps-group__edit-field ps-group__edit-field--bpbm_messages ps-js-group-bpbm_messages">
                    <div class="ps-group__edit-field-row">
                        <div class="ps-group__edit-field-header">
                            <div class="ps-group__edit-field-title">
                                <?php echo esc_html_x( 'Group Chat', 'PeepSo Integration', 'bp-better-messages' ); ?>
                            </div>


                            <div class="ps-group__edit-field-edit">
                                <button class="ps-btn ps-btn--xs ps-btn--app ps-js-btn-edit" onclick="ps_group.edit_property(this, <?php echo $group->id; ?>, 'bpbm_messages');">
                                    <?php echo __('Edit','groupso');?>
                                </button>
                            </div>

                            <div class="ps-group__edit-field-actions">
                                <button type="button" class="ps-btn ps-btn--xs ps-btn--app ps-js-btn-cancel"><?php echo __('Cancel', 'groupso'); ?></button>

                                <button type="button" class="ps-btn ps-btn--xs ps-btn--action ps-js-btn-submit">
                                    <img src="<?php echo PeepSo::get_asset('images/ajax-loader.gif'); ?>" class="ps-js-loading" alt="loading" style="display:none" />
                                    <?php echo __('Save', 'groupso'); ?>
                                </button>
                            </div>

                        </div>

                        <div class="ps-group__edit-field-static">
                            <div class="ps-group__edit-field-data ps-js-text">
                                <?php if($setting === 'disabled') {
                                    echo esc_html__( 'Disabled', 'bp-better-messages' );
                                } else {
                                    echo esc_html__( 'Enabled', 'bp-better-messages' );
                                }
                                ?>
                            </div>
                        </div>

                        <?php if ( $group_user->can('manage_group') ) { ?>
                            <div class="ps-group__edit-field-form ps-js-editor" style="display:none">
                                <div class="ps-input__wrapper">
                                    <select name="bpbm_messages" class="ps-input ps-input--sm ps-input--select">
                                        <option value="enabled" <?php if($setting=='enabled') echo "selected";?>><?php echo esc_html__( 'Enabled', 'bp-better-messages' ); ?></option>
                                        <option value="disabled" <?php if($setting=='disabled') echo "selected";?>><?php echo esc_html__( 'Disabled', 'bp-better-messages' ); ?></option>
                                    </select>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <?php
            }
        }

        public function is_valid_group( $is_valid_group, $thread_id ) {
            $group_id = (int) Better_Messages()->functions->get_thread_meta($thread_id, 'peepso_group_id');

            if ( !! $group_id && class_exists('PeepSoGroup') ) {
                $group = new PeepSoGroup( $group_id );

                if( !! $group->id ) {
                    if ( $this->is_group_messages_enabled($group_id) === 'enabled') {
                        $is_valid_group = true;
                    }
                }
            }

            return $is_valid_group;
        }
        /**
         * @param string $title
         * @param int $thread_id
         * @param BM_Thread $thread
         * @return string
         */
        public function group_thread_title(string $title, int $thread_id, $thread ){
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'group' ) return $title;

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'peepso_group_id');
            $group    = new PeepSoGroup( (int) $group_id );

            if( $group->name ) {
                return $group->name;
            } else {
                return $title;
            }
        }

        /**
         * @param string $title
         * @param int $thread_id
         * @param BM_Thread $thread
         * @return string
         */
        public function group_thread_image(string $title, int $thread_id, $thread ){
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'group' ) return $title;

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'peepso_group_id');
            $group    = new PeepSoGroup( (int) $group_id );
            return $group->get_avatar_url();
        }

        /**
         * @param string $title
         * @param int $thread_id
         * @param BM_Thread $thread
         * @return string
         */
        public function group_thread_url(string $title, int $thread_id, $thread ){
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'group' ) return $title;

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'peepso_group_id');
            $group    = new PeepSoGroup( (int) $group_id );
            return $group->get_url();
        }

        public function get_groups( $groups, $user_id ){
            $PeepSoGroups = new PeepSoGroups();
            $groups = $PeepSoGroups->get_groups(0, -1, 'post_title', 'ASC', '', Better_Messages()->functions->get_current_user_id() );

            $return = [];

            if( count( $groups ) > 0 ) {
                foreach ($groups as $group) {
                    if ($group->id === NULL) continue;

                    $group_id = $group->id;
                    $thread_id = Better_Messages_Peepso_Groups::instance()->get_group_thread_id( $group->id );
                    $avatar = $group->get_avatar_url();

                    $group_item = [
                        'group_id'  => (int) $group_id,
                        'name'      => html_entity_decode(esc_attr($group->name)),
                        'messages'  => (int) ( $this->is_group_messages_enabled( $group_id ) === 'enabled' ),
                        'thread_id' => (int) $thread_id,
                        'image'     => $avatar,
                        'url'       => $group->get_url()
                    ];

                    $return[] = $group_item;
                }
            }

            return $return;
        }


        public function can_reply_to_group_chat( $allowed, $user_id, $thread_id ){
            $type = Better_Messages()->functions->get_thread_type( $thread_id );

            if( $type === 'group' ){
                $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'peepso_group_id');
                if ( !! $group_id ) {
                    if( $this->is_group_messages_enabled( $group_id ) === 'enabled' && $this->user_has_access( $group_id, $user_id ) ){
                        return true;
                    } else {
                        return false;
                    }
                }
            }

            return $allowed;
        }

        public function has_access_to_group_chat( $has_access, $thread_id, $user_id ){
            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'peepso_group_id');
            if ( !! $group_id ) {
                if( $this->is_group_messages_enabled( $group_id ) === 'enabled' && $this->user_can_see( $group_id, $user_id ) ){
                    return true;
                }
            }

            return $has_access;
        }

        public function enabled( $var ){
            return true;
        }

        public function disable_upload_files( $can_upload, $user_id, $thread_id ){
            if( Better_Messages()->functions->get_thread_type( $thread_id ) === 'group' ) {
                return false;
            }

            return $can_upload;
        }

        public function is_group_messages_enabled( $group_id = false ){
            if(  Better_Messages()->settings['PSenableGroups'] !== '1' ) return 'disabled';

            $messages = 'enabled';
            if( !! $group_id ) {
                $messages = get_post_meta( $group_id, 'bpbm_messages', true );
                if( empty( $messages ) ) $messages = 'enabled';
            }

            return $messages;
        }

        public function group_segment_messages( $args ){
            $group = $args['group'];
            $group_segment = $args['group_segment'];
            ?>
            <div class="peepso">
                <div class="ps-page ps-page--group ps-page--group-bm-messages">
                    <?php PeepSoTemplate::exec_template('general','navbar'); ?>
                    <?php PeepSoTemplate::exec_template('general', 'register-panel'); ?>

                    <?php if(Better_Messages()->functions->get_current_user_id()) {

                        PeepSoTemplate::exec_template('groups', 'group-header', array('group'=>$group, 'group_segment'=>$group_segment));

                    }


                    $group_id = $group->id;

                    echo $this->get_group_page( $group_id );
                    ?>
                </div>
            </div>
            <?php
        }

        public function get_group_thread_id( $group_id ){
            global $wpdb;

            $thread_id = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT bm_thread_id 
            FROM `" . bm_get_table('threadsmeta') . "` 
            WHERE `meta_key` = 'peepso_group_id' 
            AND   `meta_value` = %s
            ", $group_id ) );

            $thread_exist = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*)  FROM `" . bm_get_table('threads') . "` WHERE `id` = %d", $thread_id));

            if( $thread_exist === 0 ){
                $thread_id = false;
            }

            if( ! $thread_id ) {
                $wpdb->query( $wpdb->prepare( "
                DELETE  
                FROM `" . bm_get_table('threadsmeta') . "` 
                WHERE `meta_key` = 'peepso_group_id' 
                AND   `meta_value` = %s
                ", $group_id ) );

                $group = new PeepSoGroup( $group_id );

                $wpdb->insert(
                    bm_get_table('threads'),
                    array(
                        'subject' => $group->name,
                        'type'    => 'group'
                    )
                );

                $thread_id = $wpdb->insert_id;

                Better_Messages()->functions->update_thread_meta( $thread_id, 'peepso_group_thread', true );
                Better_Messages()->functions->update_thread_meta( $thread_id, 'peepso_group_id', $group_id );

                $this->sync_thread_members( $thread_id );
            }

            return $thread_id;
        }

        public function on_something_changed( $group_id, $user_id = false ){
            $thread_id = $this->get_group_thread_id( $group_id );
            $this->sync_thread_members( $thread_id );
        }

        public function get_groups_members( $group_id ){
            global $wpdb;
            $table = $wpdb->prefix . PeepSoGroupUsers::TABLE;
            $query = $wpdb->prepare("SELECT `gm_user_id` FROM {$table} LEFT JOIN `{$wpdb->prefix}".PeepSoUser::TABLE."` as `f` ON `{$table}`.`gm_user_id` = `f`.`usr_id` WHERE `f`.`usr_role` NOT IN ('register', 'ban', 'verified') AND `gm_group_id` = %d AND `gm_user_status` LIKE 'member%'", $group_id);
            return $wpdb->get_col( $query );
        }

        public function sync_thread_members( $thread_id ){
            wp_cache_delete( 'thread_recipients_' . $thread_id, 'bm_messages' );
            wp_cache_delete( 'bm_thread_recipients_' . $thread_id, 'bm_messages' );
            $group_id = Better_Messages()->functions->get_thread_meta( $thread_id, 'peepso_group_id' );

            $members = $this->get_groups_members( $group_id );

            if( count($members) === 0 ) {
                return false;
            }

            global $wpdb;
            $array     = [];
            $user_ids  = [];
            $removed_ids  = [];
            /**
             * All users ids in thread
             */
            $recipients = Better_Messages()->functions->get_recipients( $thread_id );

            foreach( $members as $index => $user_id ){
                if( isset( $recipients[$user_id] ) ){
                    unset( $recipients[$user_id] );
                    continue;
                }

                $user_ids[] = $user_id;

                $array[] = [
                    $user_id,
                    $thread_id,
                    0,
                    0,
                ];
            }

            $changes = false;

            if( count($array) > 0 ) {
                $sql = "INSERT INTO " . bm_get_table('recipients') . "
                (user_id, thread_id, unread_count, is_deleted)
                VALUES ";

                $values = [];

                foreach ($array as $item) {
                    $values[] = $wpdb->prepare( "(%d, %d, %d, %d)", $item );
                }

                $sql .= implode( ',', $values );

                $wpdb->query( $sql );

                $changes = true;
            }

            if( count($recipients) > 0 ) {
                foreach ($recipients as $user_id => $recipient) {
                    global $wpdb;

                    $wpdb->delete( bm_get_table('recipients'), [
                        'thread_id' => $thread_id,
                        'user_id'   => $user_id
                    ], ['%d','%d'] );

                    $removed_ids[] = $user_id;
                }

                $changes = true;
            }

            Better_Messages()->hooks->clean_thread_cache( $thread_id );

            if( $changes ){
                do_action( 'better_messages_thread_updated', $thread_id );
                do_action( 'better_messages_info_changed', $thread_id );
                do_action( 'better_messages_participants_added', $thread_id, $user_ids );
                do_action( 'better_messages_participants_removed', $thread_id, $removed_ids );
            }

            return true;
        }

        public function user_can_see( $group_id, $user_id ){
            $PeepSoGroupUser  = new PeepSoGroupUser( $group_id, $user_id );
            $has_access = $PeepSoGroupUser->can('access');
            return $has_access;
        }

        public function user_has_access( $group_id, $user_id ){
            $PeepSoGroupUser  = new PeepSoGroupUser( $group_id, $user_id );
            $has_access = $PeepSoGroupUser->is_member;
            return $has_access;
        }

        public function user_can_moderate( $group_id, $user_id ){
            $PeepSoGroupUser  = new PeepSoGroupUser( $group_id, $user_id );
            $has_access = $PeepSoGroupUser->can('edit_content');
            return $has_access;
        }

        public function get_group_page( $group_id ){
            if (defined('WP_DEBUG') && true === WP_DEBUG) {
                // some debug to add later
            } else {
                error_reporting(0);
            }

            $path = apply_filters('bp_better_messages_views_path', Better_Messages()->path . '/views/');

            $thread_id = $this->get_group_thread_id( $group_id );

            $template = 'layout-peepso-group.php';

            ob_start();

            $template = apply_filters( 'bp_better_messages_current_template', $path . $template, $template );

            do_action('bp_better_messages_before_main_template_rendered');

            if($template !== false) {
                Better_Messages()->functions->pre_template_include();
                include($template);
                Better_Messages()->functions->after_template_include();
            }

            do_action('bp_better_messages_after_main_template_rendered');

            $content = ob_get_clean();

            return $content;
        }

        public function add_group_tab( $sections ){
            $user_id  = Better_Messages()->functions->get_current_user_id();
            $group_id = PeepSoGroupsShortcode::get_instance()->group_id;

            if( $this->is_group_messages_enabled( $group_id ) === 'enabled' && $this->user_can_see( $group_id, $user_id ) ){
                $sections[0]['bm_messages'] = [
                    'href'  => 'messages',
                    'title' => _x('Messages', 'PeepSo Group Section Label', 'bp-better-messages'),
                    'icon'  => 'gcis gci-comments'
                ];
            }

            return $sections;
        }
    }
}
