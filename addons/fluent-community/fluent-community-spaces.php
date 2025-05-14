<?php

use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Better_Messages_Fluent_Community_Spaces' ) ) {

    class Better_Messages_Fluent_Community_Spaces
    {
        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Fluent_Community_Spaces();
            }

            return $instance;
        }

        public function __construct()
        {
            add_filter('better_messages_is_valid_group', array( $this, 'is_valid_group' ), 10, 2 );

            add_filter('fluent_community/space_header_links', array( $this, 'group_link_to_chat' ), 10, 2);
            add_filter('better_messages_has_access_to_group_chat', array( $this, 'has_access_to_group_chat'), 10, 3 );

            add_filter('better_messages_thread_title', array( $this, 'group_thread_title' ), 10, 3 );
            add_filter('better_messages_thread_image', array( $this, 'group_thread_image' ), 10, 3 );
            add_filter('better_messages_thread_url',   array( $this, 'group_thread_url' ), 10, 3 );

            add_action('fluent_community/space/created', array( $this, 'on_something_changed' ), 10, 3 );
            add_action('fluent_community/space/updated', array( $this, 'on_something_changed' ), 10, 3 );

            add_action('fluent_community/space/join_requested', array( $this, 'on_something_changed' ), 10, 3 );
            add_action('fluent_community/space/joined', array( $this, 'on_something_changed' ), 10, 3 );
            add_action('fluent_community/space/user_left', array( $this, 'on_something_changed' ), 10, 3 );

            if (Better_Messages()->settings[ 'FCenableGroupsFiles' ] === '0') {
                add_action('bp_better_messages_user_can_upload_files', array($this, 'disable_upload_files'), 10, 3);
            }
        }

        public function is_valid_group( $is_valid_group, $thread_id )
        {
            $group_id = (int) Better_Messages()->functions->get_thread_meta($thread_id, 'fluentcommunity_group_id');

            if ( !! $group_id ) {
                $group = Space::find( $group_id );

                if( $group ) {
                    if ( $this->is_group_messages_enabled($group_id) === 'enabled') {
                        $is_valid_group = true;
                    }
                }
            }

            return $is_valid_group;
        }

        public function disable_upload_files( $can_upload, $user_id, $thread_id ){
            if( Better_Messages()->functions->get_thread_type( $thread_id ) === 'group' ) {
                return false;
            }

            return $can_upload;
        }

        public function on_something_changed( $space, $userId = null, $initiator = null ){
            $thread_id = $this->get_group_thread_id( $space->id );
            $this->sync_thread_members( $thread_id );
        }

        /**
         * @param string $url
         * @param int $thread_id
         * @param BM_Thread $thread
         * @return string
         */
        public function group_thread_url(string $url, int $thread_id, $thread ){
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'group' ) return $url;

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'fluentcommunity_group_id');

            $space = Space::find( $group_id );

            if( $space ){
                $url = Helper::baseUrl('space/' . $space->slug . '/home');
            }

            return $url;
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

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'fluentcommunity_group_id');

            $space = Space::find( $group_id );
            if( $space ){
                return $space->title;
            } else {
                return $title;
            }
        }

        /**
         * @param string $image
         * @param int $thread_id
         * @param BM_Thread $thread
         * @return string
         */
        public function group_thread_image(string $image, int $thread_id, $thread ){
            $thread_type = Better_Messages()->functions->get_thread_type( $thread_id );
            if( $thread_type !== 'group' ) return $image;

            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'fluentcommunity_group_id');

            $space = Space::find( $group_id );

            if( $space ){
                $space = $space->toArray();

                if( $space['logo'] ){
                    $image = $space['logo'];
                } else if( $space['settings'] && ! empty( trim($space['settings']['emoji']) ) ){
                    $image = 'html:<span class="bm-thread-emoji">' . trim($space['settings']['emoji']) . '</span>';
                } else if( $space['settings'] && ! empty( trim($space['settings']['shape_svg']) ) ) {
                    $image = 'html:<span class="bm-thread-svg">' . trim($space['settings']['shape_svg']) . '</span>';
                } else {
                    $image = 'html:<span style="margin:auto" class="fcom_no_avatar"></span>';
                }
            }

            return $image;
        }


        public function group_link_to_chat( $links, $space ) {
            $space_id = $space->id;

            if( ! $this->is_group_messages_enabled( $space_id ) ){
                return $links;
            }

            if( ! $this->user_has_access( $space_id, get_current_user_id() ) ){
                return $links;
            }

            $thread_id = $this->get_group_thread_id( $space_id );

            $url = Better_Messages()->functions->get_user_messages_url( get_current_user_id(), $thread_id );

            $links[] = [
                'title' => _x('Messages', 'FluentCommunity Integration (Button in Spaces)', 'bp-better-messages'),
                'url'   => $url
            ];

            return $links;
        }

        public function is_group_messages_enabled( $group_id ){
            return apply_filters('better_messages_fluent_community_group_chat_enabled', true, $group_id );
        }

        public function user_has_access( $group_id, $user_id ){
            $allowed = false;

            if( $user_id > 0 ) {
                $user = User::find($user_id);

                if( $user ) {
                    $group = Space::find($group_id);

                    if( $group ) {
                        $role = $user->getSpaceRole($group);

                        if (!empty($role)) {
                            $allowed = true;
                        }
                    }
                }
            }

            return apply_filters('better_messages_fluent_community_group_chat_user_has_access', $allowed, $group_id, $user_id );
        }

        public function user_can_moderate( $group_id, $user_id )
        {
            $allowed = false;

            if( $user_id > 0 ) {
                $user = User::find($user_id);

                if( $user ) {
                    $group = Space::find($group_id);

                    if( $group ) {
                        $role = $user->getSpaceRole($group);

                        if ( $role === 'moderator' || $role === 'admin' ) {
                            $allowed = true;
                        }
                    }
                }
            }

            return apply_filters('better_messages_fluent_community_group_chat_user_can_moderate', $allowed, $group_id, $user_id );
        }

        public function has_access_to_group_chat( $has_access, $thread_id, $user_id ){
            $group_id = Better_Messages()->functions->get_thread_meta($thread_id, 'fluentcommunity_group_id');

            if ( !! $group_id ) {
                if( $this->is_group_messages_enabled( $group_id ) && $this->user_has_access( $group_id, $user_id ) ){
                    return true;
                }
            }

            return $has_access;
        }

        public function get_group_thread_id( $group_id ){
            global $wpdb;

            $thread_id = (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT bm_thread_id 
            FROM `" . bm_get_table('threadsmeta') . "` 
            WHERE `meta_key` = 'fluentcommunity_group_id' 
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
                WHERE `meta_key` = 'fluentcommunity_group_id' 
                AND   `meta_value` = %s
                ", $group_id ) );

                $space = Space::find( $group_id );

                if( $space ){
                    $title = $space->title;

                    $wpdb->insert(
                        bm_get_table('threads'),
                        array(
                            'subject' => $title,
                            'type'    => 'group'
                        )
                    );

                    $thread_id = $wpdb->insert_id;

                    Better_Messages()->functions->update_thread_meta( $thread_id, 'fluentcommunity_group_thread', true );
                    Better_Messages()->functions->update_thread_meta( $thread_id, 'fluentcommunity_group_id', $group_id );

                    $this->sync_thread_members( $thread_id );
                }
            }

            return $thread_id;
        }

        public function get_group_members( $group_id ){
            $result = [];

            $space = Space::find( $group_id );

            if( $space ){
                $members = $space->members->toArray();

                foreach ( $members as $member ){
                    if( $member['pivot']['status'] === 'active' ){
                        $result[] = $member['ID'];
                    }
                }
            }

            return $result;
        }


        public function sync_thread_members( $thread_id ){
            wp_cache_delete( 'thread_recipients_' . $thread_id, 'bm_messages' );
            wp_cache_delete( 'bm_thread_recipients_' . $thread_id, 'bm_messages' );

            $group_id = Better_Messages()->functions->get_thread_meta( $thread_id, 'fluentcommunity_group_id' );

            $members = $this->get_group_members( $group_id );

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

            foreach( $members as $user_id ){
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

    }
}
