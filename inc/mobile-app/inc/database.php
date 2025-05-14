<?php

defined('ABSPATH') || exit;

if (!class_exists('Better_Messages_Mobile_App_Database')):

    class Better_Messages_Mobile_App_Database
    {

        public $db_version = 0.1;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Mobile_App_Database();
            }

            return $instance;
        }

        public function __construct(){
            add_action( 'admin_init', array( $this, 'update_db_if_needed' ) );
            add_filter( 'better_messages_tables_list', array( $this, 'add_tables' ) );
            add_action( 'better_messages_reset_database', array( $this, 'reset_database' ) );
        }

        public function update_db_if_needed(){
            if( current_user_can('manage_options') ) {
                $db_version = get_option( 'better_messages_mobile_app_db_version', 0 );

                if( $db_version === 0 ){
                    $this->first_install();
                } /*else if( $db_2_version != $this->db_version) {
                    $this->upgrade( $db_2_version );
                }*/
            }
        }

        public function add_tables( $tables )
        {
            $tables[] = Better_Messages_Mobile_App()->builds_table;
            $tables[] = Better_Messages_Mobile_App()->devices_table;

            return $tables;
        }

        public function first_install()
        {
            set_time_limit(0);
            ignore_user_abort(true);
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            $sql = [
                "CREATE TABLE `" . Better_Messages_Mobile_App()->builds_table ."` (
                   `id` bigint(20) NOT NULL AUTO_INCREMENT,
                   `site_id` bigint(20) NOT NULL,
                   `platform` enum('android','ios') NOT NULL,
                   `type` enum('development','production') NOT NULL,
                   `status` enum('in-queue', 'building', 'built', 'failed') NOT NULL,
                   `build_info` longtext NOT NULL,
                   `secret` varchar(20) DEFAULT NULL,
                   `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, 
                   PRIMARY KEY (`id`)
                ) ENGINE=InnoDB;",
                "CREATE TABLE `" . Better_Messages_Mobile_App()->devices_table ."` (
                  `id` bigint(20) NOT NULL AUTO_INCREMENT,
                  `device_id` varchar(255) NOT NULL,
                  `user_id` int(11) NOT NULL,
                  `name` varchar(100) DEFAULT '',
                  `model` varchar(20) DEFAULT '',
                  `platform` varchar(10) DEFAULT '',
                  `environment` enum('development','production') NOT NULL,
                  `language` varchar(10) DEFAULT '',
                  `operatingSystem` varchar(20) DEFAULT '',
                  `osVersion` varchar(10) DEFAULT '',
                  `iOSVersion` int(11) DEFAULT 0,
                  `androidSDKVersion` varchar(10) DEFAULT '',
                  `manufacturer` varchar(20) DEFAULT '',
                  `isVirtual` tinyint(4) NOT NULL DEFAULT '0',
                  `memUsed` bigint(20) DEFAULT 0,
                  `diskFree` bigint(20) DEFAULT 0,
                  `diskTotal` bigint(20) DEFAULT 0,
                  `realDiskFree` bigint(20) DEFAULT 0,
                  `realDiskTotal` bigint(20) DEFAULT 0,
                  `webViewVersion` varchar(20) DEFAULT '',
                  `app_build` varchar(10) DEFAULT '',
                  `app_name` varchar(32) DEFAULT '',
                  `app_version` varchar(10) DEFAULT '',
                  `app_id` varchar(50) DEFAULT '',
                  `push_token` varchar(255) NOT NULL DEFAULT '',
                  `push_token_voip` varchar(255) NOT NULL DEFAULT '',
                  `device_public_key` text NOT NULL,
                  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `last_login` datetime DEFAULT NULL,
                  `last_active` datetime DEFAULT NULL,
                  `last_ip` varchar(39) DEFAULT NULL,
                  `waiting_for_sync` tinyint(1) NOT NULL DEFAULT '1',
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `device_app_id` (`device_id`, `app_id`)
                ) ENGINE=InnoDB;",
            ];

            dbDelta($sql);

            $this->update_collate();

            update_option( 'better_messages_mobile_app_db_version', $this->db_version, false );
        }

        public function update_collate(){
            global $wpdb;

            $actions = [
                "ALTER TABLE `" . Better_Messages_Mobile_App()->builds_table ."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
                "ALTER TABLE `" . Better_Messages_Mobile_App()->devices_table ."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
            ];

            foreach( $actions as $sql ){
                $wpdb->query( $sql );
            }

            return null;
        }

        public function reset_database()
        {
            $this->drop_tables();
            $this->first_install();
        }

        public function drop_tables()
        {
            global $wpdb;

            $wpdb->query( "DROP TABLE IF EXISTS `" . Better_Messages_Mobile_App()->builds_table ."`" );
            $wpdb->query( "DROP TABLE IF EXISTS `" . Better_Messages_Mobile_App()->devices_table ."`" );

            delete_option( 'better_messages_mobile_app_db_version' );
        }
    }

endif;
