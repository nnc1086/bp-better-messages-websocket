<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Better_Messages_User_Reports' ) ){

    class Better_Messages_User_Reports
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_User_Reports();
            }

            return $instance;
        }

        public function __construct(){
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
        }

        public function rest_api_init()
        {
            register_rest_route( 'better-messages/v1', '/reports/reportMessage', array(
                'methods' => 'POST',
                'callback' => array( $this, 'report_message' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/reports/getReasons', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_reasons' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/reports/getReasons', array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_reasons' ),
                'permission_callback' => array( Better_Messages_Rest_Api(), 'is_user_authorized' )
            ) );

            register_rest_route( 'better-messages/v1', '/reports/deleteReport', array(
                'methods' => 'POST',
                'callback' => array( $this, 'delete_report' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                }
            ) );

            //reports/deleteReport
        }

        public function delete_report( WP_REST_Request $request )
        {
            $message_id = (int) $request->get_param( 'messageId' );

            if ( ! $message_id ) {
                return new WP_Error( 'no_message_id', 'No message id provided', array( 'status' => 400 ) );
            }

            $user_id = (int) $request->get_param( 'userId' );

            if ( ! $user_id ) {
                return new WP_Error( 'no_user_id', 'No user id provided', array( 'status' => 400 ) );
            }

            $reports = $this->get_message_reports( $message_id );

            if( ! isset( $reports[ $user_id ] ) ){
                return new WP_Error( 'no_report', _x('No report found', 'User Reports', 'bp-better-messages'), array( 'status' => 400 ) );
            }

            unset( $reports[ $user_id ] );

            $this->save_message_reports( $message_id, $reports );

            return _x('Report deleted', 'User Reports', 'bp-better-messages');
        }

        public function get_reasons( WP_REST_Request $request )
        {
            $current_user_id = Better_Messages()->functions->get_current_user_id();

            $message_id = (int) $request->get_param( 'message_id' );

            if ( ! $message_id ) {
                return new WP_Error( 'no_message_id', 'No message id provided', array( 'status' => 400 ) );
            }

            $thread_id = (int) $request->get_param( 'thread_id' );

            if ( ! $thread_id ) {
                return new WP_Error( 'no_thread_id', 'No thread id provided', array( 'status' => 400 ) );
            }

            $reports = $this->get_message_reports( $message_id );

            if( isset( $reports[ $current_user_id ] ) ){
                return new WP_Error( 'already_reported', _x('You already reported this message', 'User Reports', 'bp-better-messages'), array( 'status' => 400 ) );
            }

            return $this->get_categories( $message_id, $thread_id );
        }

        public function get_categories( $message_id, $thread_id )
        {
            $reasons = array(
                'inappropriate' => _x('Inappropriate', 'Report reason', 'bp-better-messages'),
                'spam'          => _x('Spam', 'Report reason', 'bp-better-messages'),
                'harassment'    => _x('Harassment', 'Report reason', 'bp-better-messages'),
                'offensive'     => _x('Offensive', 'Report reason', 'bp-better-messages'),
                'other'         => _x('Other', 'Report reason', 'bp-better-messages')
            );

            return apply_filters( 'better_messages_get_report_reasons', $reasons, $message_id, $thread_id );
        }

        public function report_message( WP_REST_Request $request)
        {
            $current_user_id = Better_Messages()->functions->get_current_user_id();

            $message_id = (int) $request->get_param( 'message_id' );

            if ( ! $message_id ) {
                return new WP_Error( 'no_message_id', 'No message id provided', array( 'status' => 400 ) );
            }

            $thread_id = (int) $request->get_param( 'thread_id' );

            if ( ! $thread_id ) {
                return new WP_Error( 'no_thread_id', 'No thread id provided', array( 'status' => 400 ) );
            }

            $message = Better_Messages()->functions->get_message( $message_id );

            if ( ! $message || (int) $message->thread_id !== $thread_id ) {
                return new WP_Error( 'message_not_found', __( 'Message not found', 'bp-better-messages' ), array( 'status' => 404 ) );
            }

            if( (int) $message->sender_id === $current_user_id ){
                return new WP_Error( 'cannot_report_own_message', _x('Cannot report own message', 'User Reports', 'bp-better-messages'), array( 'status' => 400 ) );
            }

            $categories = $this->get_categories( $message_id, $thread_id );
            $category    = $request->get_param('category');

            if( ! isset( $categories[ $category ] ) ){
                return new WP_Error( 'invalid_category', _x('Invalid category', 'User Reports', 'bp-better-messages'), array( 'status' => 400 ) );
            }

            $description = $request->get_param('description');

            $reports     = $this->get_message_reports( $message_id );

            if( isset( $reports[$current_user_id] ) ){
                return new WP_Error( 'already_reported', _x('You already reported this message', 'User Reports', 'bp-better-messages'), array( 'status' => 400 ) );
            }

            $max_length = apply_filters( 'better_messages_report_description_max_length', 500 );

            if( mb_strlen( $description ) > $max_length ){
                return new WP_Error( 'description_too_long', sprintf( _x('Description is too long. Maximum length is %d characters', 'User Reports', 'bp-better-messages'), $max_length ), array( 'status' => 400 ) );
            }

            $reports[$current_user_id] = array(
                'category'    => $category,
                'description' => $description,
                'time'        => current_time( 'mysql' )
            );

            $this->save_message_reports( $message_id, $reports );

            do_action( 'better_messages_message_reported', $message_id, $thread_id, $current_user_id, $category, $description, $reports );

            return _x('Message reported', 'User Reports', 'bp-better-messages');
        }

        public function get_message_reports( $message_id )
        {
            $reports = Better_Messages()->functions->get_message_meta( $message_id, 'user_reports' );

            if( ! $reports || ! is_array( $reports ) ){
                return array();
            }

            return $reports;
        }

        public function save_message_reports( $message_id, $reports )
        {
            if( is_array($reports) && count( $reports ) > 0 ){
                Better_Messages()->functions->update_message_meta( $message_id, 'user_reports', $reports );
            } else {
                Better_Messages()->functions->delete_message_meta( $message_id, 'user_reports' );
            }
        }

        public function get_reported_messages_count()
        {
            global $wpdb;

            return (int) $wpdb->get_var( "
            SELECT COUNT(*)
            FROM `" . bm_get_table('messages') . "` `messages`
            LEFT JOIN `" . bm_get_table('meta') . "` `user_reports_meta`
                ON `messages`.`id` = `user_reports_meta`.`bm_message_id`
                AND `user_reports_meta`.`meta_key` = 'user_reports'
            WHERE `created_at` > 0
            AND `user_reports_meta`.`meta_value` IS NOT NULL
            AND `message` != '<!-- BBPM START THREAD -->'
            " );
        }
    }
}
