<?php
defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'Better_Messages_Files' ) ):

    class Better_Messages_Files
    {

        public static function instance()
        {
            static $instance = null;

            if ( null === $instance ) {
                $instance = new Better_Messages_Files();
            }

            return $instance;
        }


        public function __construct()
        {
            /**
             * Modify message before save
             */
            add_filter( 'bp_better_messages_pre_format_message', array( $this, 'nice_files' ), 90, 4 );
            add_action( 'better_messages_cleaner_job', array($this, 'remove_old_attachments') );
            add_filter( 'better_messages_rest_message_meta', array( $this, 'files_message_meta'), 10, 4 );

            if ( Better_Messages()->settings['attachmentsEnable'] === '1' ) {
                add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );
                add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ), 9 );
                add_action( 'better_messages_register_script_dependencies', array($this, 'load_scripts'), 10, 1);
                add_filter( 'bp_better_messages_script_variable', array( $this, 'attachments_script_vars' ), 10, 1 );
            }

            add_action( 'bp_better_chat_settings_updated', array($this, 'create_index_file') );
        }

        public $scripts_loaded = false;

        public function load_scripts( $context ){
            if( $this->scripts_loaded ) return;
            $this->scripts_loaded = true;

            $is_dev = defined( 'BM_DEV' );

            $version = Better_Messages()->version;
            $suffix = ( $is_dev ? '' : '.min' );

            $deps = [
                'better-messages-files-image-editor',
                'better-messages-files-react'
            ];

            if( Better_Messages()->settings['attachmentsAllowPhoto'] == '1' ) {
                wp_register_script(
                    'better-messages-files-webcam',
                    Better_Messages()->url . "assets/js/addons/files/webcam{$suffix}.js",
                    [],
                    $version
                );

                $deps[] = 'better-messages-files-webcam';
            }

            wp_register_script(
                'better-messages-files-image-editor',
                Better_Messages()->url . "assets/js/addons/files/image-editor{$suffix}.js",
                [],
                $version
            );

            wp_register_script(
                'better-messages-files-react',
                Better_Messages()->url . "assets/js/addons/files/react{$suffix}.js",
                [],
                $version
            );

            wp_register_script(
                'better-messages-files-core',
                Better_Messages()->url . "assets/js/addons/files/core{$suffix}.js",
                $deps,
                $version
            );

            add_filter('better_messages_script_dependencies', function( $deps ) {
                $deps[] = 'better-messages-files-core';
                return $deps;
            } );
        }

        public function files_message_meta( $meta, $message_id, $thread_id, $content ){
            if( $content === '<!-- BM-DELETED-MESSAGE -->' ){
                return $meta;
            }

            $attachments = Better_Messages()->functions->get_message_meta( $message_id, 'attachments', true );

            $files = [];

            if( is_array( $attachments) && count( $attachments ) > 0 ){
                foreach ( $attachments as $attachment_id => $url ) {
                    $attachment = get_post( $attachment_id );
                    if( ! $attachment ) continue;

                    $url = apply_filters('better_messages_attachment_url', $url, $attachment_id, $message_id, $thread_id );

                    $file = [
                        'id'       => $attachment->ID,
                        'thumb'    => wp_get_attachment_image_url($attachment->ID, array(200, 200)),
                        'url'      => $url,
                        'mimeType' => $attachment->post_mime_type
                    ];

                    $path = get_attached_file( $attachment_id );
                    $size = filesize($path);
                    $ext = pathinfo( $url, PATHINFO_EXTENSION );
                    $name = get_post_meta($attachment_id, 'bp-better-messages-original-name', true);
                    if( empty($name) ) $name = wp_basename( $url );

                    $file['name']  = $name;
                    $file['size'] = $size;
                    $file['ext']  = $ext;

                    $files[] = $file;
                }
            }

            if( count( $files ) > 0 ){
                $meta['files'] = $files;
            }

            return $meta;
        }

        public function attachments_script_vars( $vars ){
            $attachments = [
                'maxSize'    => intval(Better_Messages()->settings['attachmentsMaxSize']),
                'maxItems'   => intval(Better_Messages()->settings['attachmentsMaxNumber']),
                'formats'    => array_map(function ($str) { return ".$str"; }, Better_Messages()->settings['attachmentsFormats']),
                'allowPhoto' => (int) ( Better_Messages()->settings['attachmentsAllowPhoto'] == '1' ? '1' : '0' )
            ];

            $vars['attachments'] = $attachments;

            return $vars;
        }

        public function rest_api_init(){
            register_rest_route('better-messages/v1', '/thread/(?P<id>\d+)/upload', array(
                'methods' => 'POST',
                'callback' => array( $this, 'handle_upload' ),
                'permission_callback' => array( $this, 'user_can_upload_callback' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
            ));
        }

        public function remove_old_attachments(){
            // Removing attachments which were uploaded, but not attached to message
            global $wpdb;

            $sql = $wpdb->prepare( "SELECT `posts`.ID
            FROM {$wpdb->posts} `posts`
            INNER JOIN {$wpdb->postmeta} `meta`
                ON ( `posts`.ID = `meta`.post_id )
            WHERE  `meta`.meta_key = 'better-messages-waiting-for-message'
            AND `meta`.meta_value <= %d
            AND `posts`.`post_type` = 'attachment'
            LIMIT 0, 50", strtotime("-2 hours") );

            $expired_attachments = $wpdb->get_col( $sql );
            if( count( $expired_attachments ) > 0 ){
                foreach ( $expired_attachments as $attachment_id ){
                    wp_delete_attachment($attachment_id, true);
                }
            }

            // Removing old uploaded attachments
            $delete_after_days = (int) Better_Messages()->settings['attachmentsRetention'];
            if( $delete_after_days < 1 ) {
                return;
            }

            $delete_after = $delete_after_days * 24 * 60 * 60;
            $delete_after_time = time() - $delete_after;

            $sql = $wpdb->prepare("SELECT {$wpdb->posts}.ID
            FROM {$wpdb->posts}
            INNER JOIN {$wpdb->postmeta}
            ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id )
            INNER JOIN {$wpdb->postmeta} AS mt1
            ON ( {$wpdb->posts}.ID = mt1.post_id )
            WHERE 1=1
            AND ( ( {$wpdb->postmeta}.meta_key = 'bp-better-messages-attachment'
            AND {$wpdb->postmeta}.meta_value = '1' )
            AND ( mt1.meta_key = 'bp-better-messages-upload-time'
            AND mt1.meta_value < %d ) )
            AND {$wpdb->posts}.post_type = 'attachment'
            AND (({$wpdb->posts}.post_status = 'inherit'))
            GROUP BY {$wpdb->posts}.ID
            ORDER BY {$wpdb->posts}.post_date DESC
            LIMIT 0, 50", $delete_after_time);

            $old_attachments = $wpdb->get_col( $sql );

            foreach($old_attachments as $attachment){
                $this->remove_attachment($attachment);
            }
        }

        public function remove_attachment($attachment_id){
            global $wpdb;
            $message_id = get_post_meta($attachment_id, 'bp-better-messages-message-id', true);
            if( ! $message_id ) return false;

            // Get Message
            $table = bm_get_table('messages');
            $message_attachments = Better_Messages()->functions->get_message_meta($message_id, 'attachments', true);

            wp_delete_attachment($attachment_id, true);

            /**
             * Deleting attachment from message
             */
            if( isset( $message_attachments[$attachment_id] ) ) {
                $message = $wpdb->get_row( $wpdb->prepare("SELECT * FROM `{$table}` WHERE `id` = %d", $message_id) );

                if( ! $message ){
                    Better_Messages()->functions->delete_all_message_meta($message_id);
                    return true;
                }

                $content = str_replace( $message_attachments[$attachment_id], '', $message->message );

                if( empty( trim( $content ) ) ){
                    Better_Messages()->functions->delete_all_message_meta($message_id);
                    $wpdb->delete($table, array('id' => $message_id));
                } else {
                    unset($message_attachments[$attachment_id]);
                    Better_Messages()->functions->update_message_meta($message_id, 'attachments', $message_attachments);
                    $wpdb->update($table, array('content' => $content), array('id' => $message_id));
                }
            }

            return true;

        }

        public function nice_files( $message, $message_id, $context, $user_id )
        {
            if( $context === 'email' || $context === 'mobile_app' ) {
                if( class_exists('Better_Messages_Voice_Messages') ){
                    $is_voice_message = Better_Messages()->functions->get_message_meta( $message_id, 'bpbm_voice_messages', true );

                    if ( ! empty( $is_voice_message ) ) {
                        return __('Voice Message', 'bp-better-messages');
                    }
                }
            }

            $attachments = Better_Messages()->functions->get_message_meta( $message_id, 'attachments', true );

            $desc = false;
            if( is_array($attachments) ) {
                if (count($attachments) > 0) {
                    $desc = '';

                    if( $context !== 'mobile_app' ){
                        $desc .= "<i class=\"fas fa-file\"></i> ";
                    } else {
                        $desc .= "\n";
                        $message = str_replace("<!-- BM-ONLY-FILES -->", "", $message);
                    }

                    $desc .= count($attachments) . " " . __('attachments', 'bp-better-messages');
                }
            }

            if ( $context !== 'stack' ) {
                if( $desc !== false ){
                    foreach ( $attachments as $attachment ){
                        $message = str_replace($attachment, '', $message);
                    }

                    if( ! empty( trim($message) ) ){
                        $message .= "";
                    }

                    $message .= $desc;
                }

                return $message;
            }

            if ( !empty( $attachments ) ) {
                foreach ( $attachments as $attachment_id => $url ) {
                    $message = str_replace( array( $url . "\n", "" . $url, $url ), '', $message );
                }

            }

            return $message;
        }

        public function get_archive_extensions(){
            return array(
                "7z",
                "a",
                "apk",
                "ar",
                "cab",
                "cpio",
                "deb",
                "dmg",
                "egg",
                "epub",
                "iso",
                "jar",
                "mar",
                "pea",
                "rar",
                "s7z",
                "shar",
                "tar",
                "tbz2",
                "tgz",
                "tlz",
                "war",
                "whl",
                "xpi",
                "zip",
                "zipx"
            );
        }

        public function get_text_extensions(){
            return array(
                "txt", "rtf"
            );
        }

        public function random_string($length) {
            $key = '';
            $keys = array_merge(range(0, 9), range('a', 'z'));

            for ($i = 0; $i < $length; $i++) {
                $key .= $keys[array_rand($keys)];
            }

            return $key;
        }

        public function handle_delete()
        {
            $user_id       = (int) Better_Messages()->functions->get_current_user_id();
            $attachment_id = intval( $_POST[ 'file_id' ] );
            $thread_id     = intval( $_POST[ 'thread_id' ] );
            $attachment    = get_post( $attachment_id );

            $has_access = Better_Messages()->functions->check_access( $thread_id, $user_id );

            if( $thread_id === 0 ){
                $has_access = true;
            }
            // Security verify 1
            if ( ( ! $has_access && ! current_user_can('manage_options') ) ||
                ! wp_verify_nonce( $_POST[ 'nonce' ], 'file-delete-' . $thread_id ) ||
                ( (int) $attachment->post_author !== $user_id ) || ! $attachment
            ) {
                wp_send_json( false );
                exit;
            }

            // Security verify 2
            if ( (int) get_post_meta( $attachment->ID, 'bp-better-messages-thread-id', true ) !== $thread_id ) {
                wp_send_json( false );
                exit;
            }

            // Looks like we can delete it now!
            $result = wp_delete_attachment( $attachment->ID, true );
            if ( $result ) {
                wp_send_json( true );
            } else {
                wp_send_json( false );
            }

            exit;
        }

        public function create_index_file()
        {
            try {
                add_filter('upload_dir', array( $this, 'upload_dir'));
                $upload_dir = wp_upload_dir();
                $path = trailingslashit($upload_dir['path']);
                $index = $path . 'index.php';

                if ( ! file_exists($index) ) {
                    file_put_contents($index, "<?php\n // Silence is golden.");
                }
            } finally {
                remove_filter('upload_dir', array( $this, 'upload_dir'));
            }
        }

        public function upload_dir($dir){
            $dirName = apply_filters('bp_better_messages_upload_dir_name', 'bp-better-messages');

            return array(
                    'path'   => $dir['basedir'] . '/' . $dirName,
                    'url'    => $dir['baseurl'] . '/' . $dirName,
                    'subdir' => '/' . $dirName
                ) + $dir;
        }

        public function upload_mimes($mimes, $user){
            $allowedExtensions = Better_Messages()->settings['attachmentsFormats'];
            $allowed = array();


            foreach( wp_get_mime_types() as $extensions => $mime_type ){
                $key = array();

                foreach(explode('|', $extensions) as $ext){
                    if( in_array($ext, $allowedExtensions) ) $key[] = $ext;
                }

                if( ! empty($key) ){
                    $key = implode('|', $key);
                    $allowed[$key] = $mime_type;

                    if( str_contains( $key, 'jpg' ) || str_contains( $key, 'jpe' ) ){
                        $allowed['webp'] = 'image/webp';
                    }
                }
            }

            return $allowed;
        }

        public function handle_upload( WP_REST_Request $request )
        {
            add_filter( 'upload_dir', array( $this, 'upload_dir' ) );
            add_filter( 'upload_mimes', array( $this, 'upload_mimes' ), 10, 2 );

            $user_id    = Better_Messages()->functions->get_current_user_id();
            $thread_id  = intval($request->get_param('id'));

            $result = array(
                'result' => false,
                'error'  => ''
            );

            $files = $request->get_file_params();

            if ( isset( $files['file']) && ! empty( $files[ 'file' ] ) ) {

                $file = $files['file'];

                $extensions = apply_filters( 'bp_better_messages_attachment_allowed_extensions', Better_Messages()->settings['attachmentsFormats'], $thread_id, $user_id );

                $extension = pathinfo( $file['name'], PATHINFO_EXTENSION );

                if ( empty( $extension ) ) {
                    return new WP_Error(
                        'rest_forbidden',
                        _x( 'Sorry, you are not allowed to upload this file type', 'File Uploader Error', 'bp-better-messages' ),
                        array( 'status' => rest_authorization_required_code() )
                    );
                }


                $name = wp_basename($file['name']);

                $_FILES['file']['name'] = Better_Messages()->functions->random_string(30) . '.' . $extension;

                if( ! in_array( strtolower($extension), $extensions ) ){
                    return new WP_Error(
                        'rest_forbidden',
                        _x( 'Sorry, you are not allowed to upload this file type', 'File Uploader Error', 'bp-better-messages' ),
                        array( 'status' => rest_authorization_required_code() )
                    );
                }

                $maxSizeMb = apply_filters( 'bp_better_messages_attachment_max_size', Better_Messages()->settings['attachmentsMaxSize'], $thread_id, $user_id );
                $maxSize = $maxSizeMb * 1024 * 1024;

                if($file['size'] > $maxSize){
                    $result[ 'error' ] = sprintf(_x( '%s is too large! Please upload file up to %d MB.', 'File Uploader Error', 'bp-better-messages' ), $file['name'], $maxSizeMb);
                    status_header( 403 );
                    wp_send_json( $result );
                }
                // These files need to be included as dependencies when on the front end.
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );

                add_filter( 'intermediate_image_sizes', '__return_empty_array' );
                $attachment_id = media_handle_upload( 'file', 0 );
                remove_filter( 'intermediate_image_sizes', '__return_empty_array' );

                if ( is_wp_error( $attachment_id ) ) {
                    // There was an error uploading the image.
                    status_header( 400 );
                    $result[ 'error' ] = $attachment_id->get_error_message();
                } else {
                    // The image was uploaded successfully!
                    add_post_meta( $attachment_id, 'bp-better-messages-attachment', true, true );
                    add_post_meta( $attachment_id, 'bp-better-messages-thread-id', $thread_id, true );
                    add_post_meta( $attachment_id, 'bp-better-messages-uploader-user-id', $user_id, true );
                    add_post_meta( $attachment_id, 'bp-better-messages-upload-time', time(), true );
                    add_post_meta( $attachment_id, 'bp-better-messages-original-name', $name, true );
                    add_post_meta( $attachment_id, 'better-messages-waiting-for-message', time(), true );

                    $result[ 'id' ] = $attachment_id;

                    status_header( 200 );
                    //do_action('better_messages_user_file_uploaded', $attachment_id, $message_id, $thread_id );
                }
            } else {
                status_header( 406 );
                $result[ 'error' ] = _x( 'Your request is empty.', 'File Uploader Error', 'bp-better-messages' );
            }

            remove_filter( 'upload_dir', array( $this, 'upload_dir' ) );
            remove_filter( 'upload_mimes', array( $this, 'upload_mimes' ), 10 );

            if( $result['error'] ){
                return new WP_Error(
                    'rest_upload_failed',
                    $result['error'],
                    array( 'status' => 403 )
                );
            }

            return $result;
        }

        public function user_can_upload( $user_id, $thread_id ) {
            if ( Better_Messages()->settings['attachmentsEnable'] !== '1' ) return false;

            if( $thread_id === 0 ) return true;

            return apply_filters( 'bp_better_messages_user_can_upload_files', Better_Messages()->functions->check_access( $thread_id, $user_id, 'reply' ), $user_id, $thread_id );
        }

        public function user_can_upload_callback(WP_REST_Request $request) {
            if ( Better_Messages()->settings['attachmentsEnable'] !== '1' ) return false;

            if( ! Better_Messages_Rest_Api()->is_user_authorized( $request ) ){
                return false;
            }

            $user_id    = Better_Messages()->functions->get_current_user_id();

            $thread_id  = intval($request->get_param('id'));

            if( $thread_id === 0 ) return true;

            $can_upload = apply_filters( 'bp_better_messages_user_can_upload_files', Better_Messages()->functions->check_access( $thread_id, $user_id, 'reply' ), $user_id, $thread_id );

            if ( ! $can_upload ) {
                return new WP_Error(
                    'rest_forbidden',
                    _x( 'Sorry, you are not allowed to upload files', 'File Uploader Error', 'bp-better-messages' ),
                    array( 'status' => rest_authorization_required_code() )
                );
            }

            return $can_upload;
        }

    }

endif;


function Better_Messages_Files()
{
    return Better_Messages_Files::instance();
}
