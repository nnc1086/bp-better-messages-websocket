<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Mentions' ) ) {

    class Better_Messages_Mentions
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mentions();
            }

            return $instance;
        }

        public function __construct()
        {
            add_action( 'bp_better_messages_message_deleted', array( $this, 'message_deleted' ), 10, 1 );
            add_filter( 'better_messages_rest_api_update_data', array( $this, 'add_mentions_to_update' ), 10, 3 );
            add_action( 'better_messages_mark_thread_read', array( $this, 'clean_mentions_on_thread_read' ), 10, 2 );
        }

        public function clean_mentions_on_thread_read( $thread_id, $user_id )
        {
            global $wpdb;

            $wpdb->delete( bm_get_table('mentions'), array(
                'thread_id' => $thread_id,
                'user_id' => $user_id,
                'type' => 'mention'
            ), [ '%d', '%d', '%s' ] );
        }

        public function add_mentions_to_update( $data, $user_id, $last_time ){
            global $wpdb;

            if( isset( $data['threads']) && is_array( $data['threads'] ) && count( $data['threads'] ) > 0 ){
                $mentions_map = [];

                foreach( $data['threads'] as $thread_index => $thread ){
                    $mentions_map[ (int) $thread['thread_id'] ] = $thread_index ;
                }

                $sql = $wpdb->prepare("SELECT `mentions`.`thread_id`, MAX(mentions.message_id) as `message_id`
                FROM " . bm_get_table('mentions') . " `mentions`
                RIGHT JOIN `" . bm_get_table('messages') . "` `messages` ON
                    `messages`.`id` = `mentions`.`message_id` 
                    AND `messages`.`updated_at` > %d
                WHERE `mentions`.`user_id`  = %d 
                AND `mentions`.`thread_id` IN (" . implode(',', array_keys($mentions_map) ). ")
                AND `mentions`.`type` = 'mention'
                GROUP BY `mentions`.`thread_id`
                ", $last_time, $user_id );

                $mentions = $wpdb->get_results( $sql, ARRAY_A );

                if( count( $mentions ) > 0 ){
                    foreach( $mentions as $mention ){
                        $thread_index = $mentions_map[ (int) $mention['thread_id'] ];

                        $data['threads'][ $thread_index ]['mentions'] = [ [
                            'message_id' => (int) $mention['message_id'],
                            'type' => 'mention',
                            'seen' => false
                        ] ];
                    }
                }

            }

            return $data;
        }

        public function message_deleted( $message_id ){
            global $wpdb;

            $sql = $wpdb->prepare(
                "DELETE FROM " . bm_get_table('mentions') . " WHERE message_id = %d AND type = %s",
                $message_id, 'mention'
            );

            $wpdb->query( $sql );
        }

        public function get_mentions_for_message( $thread_id, $message_id ){
            global $wpdb;

            $return = [];
            $mentions =  $wpdb->get_results($wpdb->prepare("
            SELECT user_id, type 
            FROM `" . bm_get_table('mentions') . "`
            WHERE `thread_id`  = %d
            AND   `message_id` = %d
            ", $thread_id, $message_id ), ARRAY_A);

            if( count( $mentions ) > 0 ){
                foreach ( $mentions as $mention ){
                    $user_id = intval($mention['user_id']);
                    if( ! isset($return[ $user_id ]) ) {
                        $return[ $user_id ] = [];
                    }

                    $return[ $user_id ][] = $mention['type'];
                }
            }

            return $return;
        }

        public function process_mentions( $thread_id, $message_id, $str ){
            global $wpdb;

            $mention_match = '&lt;span class=&quot;bm-mention&quot; data-user-id=&quot;';

            preg_match_all('/' . $mention_match . '/', $str, $matches, PREG_OFFSET_CAPTURE);
            $results = $matches[0];


            $mentioned_users = [];
            if( count( $results ) > 0 ){
                foreach ( $results as $mention ){
                    $startIndex = $mention[1] + strlen( $mention_match );
                    $endIndex   = strpos( $str, '&quot;&gt;', $startIndex );
                    $user_id    = (int) substr( $str, $startIndex, ( $endIndex - $startIndex ) );
                    $mentioned_users[] = $user_id;
                }
            }

            $sql = $wpdb->prepare(
                "DELETE FROM " . bm_get_table('mentions') . " WHERE thread_id = %d AND message_id = %d AND type = %s",
                $thread_id, $message_id, 'mention'
            );

            $wpdb->query( $sql );

            if( count( $mentioned_users ) > 0 ) {
                $mentioned_users = array_unique( $mentioned_users );

                $sql = "INSERT INTO " . bm_get_table('mentions') . "
                (thread_id, message_id, user_id, type)
                VALUES ";

                $values = [];
                foreach( $mentioned_users as $mentioned_user_id ){
                    $values[] = $wpdb->prepare( "(%d, %d, %d, %s)", $thread_id, $message_id, $mentioned_user_id, 'mention' );
                }

                $sql .= implode( ',', $values );

                $wpdb->query( $sql );
            }

            return true;
        }
    }

    function Better_Messages_Mentions(){
        return Better_Messages_Mentions::instance();
    }
}
