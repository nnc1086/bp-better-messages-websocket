<?php

use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Better_Messages_Fluent_Community' ) ) {

    class Better_Messages_Fluent_Community
    {

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Fluent_Community();
            }

            return $instance;
        }

        public function __construct()
        {
            add_action('better_messages_location_none', array( $this, 'bm_location_label' ), 10, 1 );

            add_action('fluent_community/portal_head', [$this, 'load_styles']);
            add_action( 'fluent_community/before_js_loaded', [$this, 'load_javascript'] );
            add_action( 'fluent_community/before_header_menu_items', [$this, 'add_messages_menu'] );

            if( Better_Messages()->settings['chatPage'] === '0' ) {
                add_filter('bp_better_messages_page', array($this, 'message_page_url'), 10, 2);
                add_action('admin_init', array( $this, 'admin_init' ) );
                add_filter('fluent_community/app_route_paths', array( $this, 'app_route_paths' ), 10, 1 );
                add_filter( 'better_messages_login_url', array( $this, 'login_url' ), 5, 1 );
            }

            $spaces_messages_enabled = Better_Messages()->settings['FCenableGroups'] === '1';

            if( $spaces_messages_enabled ){
                require_once 'fluent-community-spaces.php';
                Better_Messages_Fluent_Community_Spaces::instance();
            }

            add_filter( 'fluent_community/profile_view_data', array( $this, 'profile_button' ), 10, 2 );
            add_filter( 'better_messages_rest_user_item', array( $this, 'rest_user_item'), 20, 3 );
        }

        public function login_url( $url )
        {
            return Helper::baseUrl('?fcom_action=auth');
        }

        public function app_route_paths( $paths = [] )
        {
            if( is_array($paths) ) {
                $paths[] = 'messages';
            }

            return $paths;
        }

        public function admin_init(){
            remove_action( 'admin_notices', array( Better_Messages()->hooks, 'admin_notice') );
        }

        public function bm_location_label(){
            return _x('Show in FluentCommunity Portal', 'FluentCommunity Integration', 'bp-better-messages');
        }

        public function rest_user_item( $item, $user_id, $include_personal )
        {
            $xprofile = XProfile::where( 'user_id', $user_id )->first();

            if( $xprofile ){
                $item['name'] = $xprofile->display_name;
                $item['avatar'] = $xprofile->avatar;
                $item['verified'] = (int) $xprofile->is_verified;
                $item['url'] = Helper::baseUrl('u/') . $xprofile->username;
            }

            return $item;
        }

        public function load_styles()
        {
            Better_Messages()->load_scripts();
            wp_styles()->all_deps([ 'better-messages' ]);

            $base_url = site_url( '' );

            foreach( wp_styles()->to_do as $handle ) {
                $_style = wp_styles()->registered[$handle];
                $src = $_style->src;

                if( strpos($src, 'http', 0) === false ){
                    $src = $base_url . $src;
                }

                if( isset($_style->extra['data']) ){
                    echo '<style>' . $_style->extra['data'] . '</style>';
                }

                echo '<link rel="stylesheet" href="' . $src . '?v=' . $_style->ver . '" />';

                if( isset($_style->extra['after']) ){

                    if( ! is_array($_style->extra['after'] ) ){
                        $_style->extra['after'] = [ $_style->extra['after'] ];
                    }

                    echo '<style>' . implode('', $_style->extra['after']) . '</style>';
                }
            }

            Better_Messages_Customize()->header_output();

            ?>
            <style type="text/css">
                body.bp-messages-mobile[data-route="better_messages"] .fluent_com{
                    min-height: auto;
                }

                .fcom_mobile_menu .focm_menu_item span.bm-unread-badge{
                    background: var(--el-color-danger);
                    width: 15px;
                    height: 15px;
                    text-align: center;
                    align-items: center;
                    justify-content: center;
                    font-size: 10px;
                    line-height: 15px;
                    border-radius: 50%;
                    top: 3px;
                    position: absolute;
                    right: 10px;
                    color: #fff;
                }

                .better_messages_icon .el-icon{
                    width: 20px;height: 20px;
                }

                .better_messages_icon .el-icon svg{
                    height: 20px;
                    width: 20px;
                }

                .bp-messages-wrap.bp-messages-mobile .chat-header .mobileClose{
                    display: none;
                }

                .bp-messages-wrap-main .bp-messages-wrap:not(.bp-messages-full-screen, .bp-messages-mobile), .bp-messages-wrap-main .bp-messages-threads-wrapper{
                    height: calc( var(--bm-fcom-window-height) - var(--bm-fcom-menu-height, 55px) - 60px ) !important;
                }

                .bp-messages-wrap-main.bp-messages-mobile, .bp-messages-wrap-group.bp-messages-mobile, .bp-messages-chat-wrap.bp-messages-mobile, .bp-messages-single-thread-wrap.bp-messages-mobile{
                    top: var(--fcom-header-height);
                    height: calc(100% - var(--bm-fcom-footer-height, 41px) - var(--fcom-header-height));
                    z-index: 10;
                }

                body.bm-reply-area-focused{
                    .bp-messages-wrap-main.bp-messages-mobile, .bp-messages-wrap-group.bp-messages-mobile, .bp-messages-chat-wrap.bp-messages-mobile, .bp-messages-single-thread-wrap.bp-messages-mobile{
                        height: calc(100% - var(--fcom-header-height)) !important;
                    }
                }

                @media screen and (max-width: 1024px) {
                    .bp-messages-wrap .chat-header .bpbm-minimize,
                    .bp-better-messages-mini,
                    .bp-better-messages-list {
                        display: none !important;
                    }
                }

                @media (max-width: 425px) {
                    .user_header .object_menu .fcom_profile_menu_actions .fcom_bm_chat_button > span,
                    .user_header .object_menu .fcom_profile_menu_actions .fcom_bm_video_call_button > span,
                    .user_header .object_menu .fcom_profile_menu_actions .fcom_bm_audio_call_button > span{
                        display: none;
                    }
                }
            </style>
            <?php
        }

        public function load_javascript() {
            $version = Better_Messages()->version;

            Better_Messages()->load_scripts();

            wp_scripts()->all_deps(['better-messages']);

            $base_url = site_url( '' );

            foreach( wp_scripts()->to_do as $handle ){
                $_script = wp_scripts()->registered[$handle];

                $src = $_script->src;

                if (empty($src)) continue;

                if (strpos($src, 'http', 0) === false) {
                    $src = $base_url . $src;
                }


                $extra_data = '';

                if (isset($_script->extra['data'])) {
                    $extra_data = $_script->extra['data'];
                }

                if ($handle === 'better-messages') {
                    $translations = wp_scripts()->print_translations('better-messages', false);
                    $extra_data = $translations . $extra_data;
                }

                if( $extra_data ){
                    echo '<script>' . $extra_data . '</script>';
                }

                echo '<script src="' . $src . '?v=' . $_script->ver . '"></script>';


                if (isset($_script->extra['after'])) {
                    if (!is_array($_script->extra['after'])) {
                        $_script->extra['after'] = [$_script->extra['after']];
                    }

                    echo '<script>' . implode('', $_script->extra['after']) . '</script>';
                }
            }

            $src = Better_Messages()->url . 'addons/fluent-community/scripts.js?=' . $version;
            echo '<script src="' . $src . '"></script>';
        }

        public function message_page_url( $url, $user_id ){
            if( Better_Messages()->notifications->is_sending_notifications() ){
                return Better_Messages()->functions->redirect_to_messages_link( Better_Messages()->notifications->get_sending_thread_id() );
            }

            return Helper::baseUrl('messages');
        }

        public function profile_button( $data, $xprofile )
        {
            $current_user_id = get_current_user_id();
            if( ! $current_user_id ) return $data;

            $user_id = $xprofile->user_id;

            if( $user_id === $current_user_id ){
                return $data;
            }

            $class = 'fcom_bm_chat_button fcom_route el-button fcom_primary_button';
            $class .= ' bm-lc-button bm-no-loader bm-lc-user-' . $user_id;

            if( Better_Messages()->settings['fastStart'] == '1' ) {
                $url = add_query_arg([
                    'to' => $user_id,
                    'scrollToContainer' => '',
                    'bm-fast-start' => 1
                ], get_site_url());
            } else {
                $url = Better_Messages()->functions->private_message_link($user_id);
            }

            if( Better_Messages()->settings['FCenableMessageButton'] === '1' ){
                $data['profile_nav_actions'][] = [
                    'css_class' => $class,
                    'title'     => _x('Message', 'FluentCommunity Integration', 'bp-better-messages'),
                    'svg_icon'  => '<span class="bm-loader-container"><svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 16 16" height="200px" width="200px" xmlns="http://www.w3.org/2000/svg"><path d="M2.678 11.894a1 1 0 0 1 .287.801 11 11 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8 8 0 0 0 8 14c3.996 0 7-2.807 7-6s-3.004-6-7-6-7 2.808-7 6c0 1.468.617 2.83 1.678 3.894m-.493 3.905a22 22 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a10 10 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105"></path></svg></span>',
                    'url'       => $url
                ];
            }

            if( Better_Messages()->functions->can_use_premium_code() ) {
                if (Better_Messages()->settings['FCProfileVideoCall'] === '1') {
                    $args = [
                        'fast-call' => '',
                        'to' => $user_id,
                        'type' => 'video'
                    ];

                    $link = add_query_arg($args, get_site_url());

                    $data['profile_nav_actions'][] = [
                        'css_class' => 'fcom_bm_video_call_button fcom_route el-button fcom_primary_button bpbm-pm-button bm-no-loader bm-no-style video-call bm-user-' . $user_id,
                        'title' => _x('Video Call', 'FluentCommunity Integration', 'bp-better-messages'),
                        'svg_icon' => '<span class="bm-loader-container"><svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="200px" width="200px" xmlns="http://www.w3.org/2000/svg"><path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M374.79 308.78 457.5 367a16 16 0 0 0 22.5-14.62V159.62A16 16 0 0 0 457.5 145l-82.71 58.22A16 16 0 0 0 368 216.3v79.4a16 16 0 0 0 6.79 13.08z"></path><path fill="none" stroke-miterlimit="10" stroke-width="32" d="M268 384H84a52.15 52.15 0 0 1-52-52V180a52.15 52.15 0 0 1 52-52h184.48A51.68 51.68 0 0 1 320 179.52V332a52.15 52.15 0 0 1-52 52z"></path></svg></span>',
                        'url' => $link
                    ];
                }

                if (Better_Messages()->settings['FCProfileAudioCall'] === '1') {
                    $args = [
                        'fast-call' => '',
                        'to' => $user_id,
                        'type' => 'audio'
                    ];

                    $link = add_query_arg($args, get_site_url());

                    $data['profile_nav_actions'][] = [
                        'css_class' => 'fcom_bm_audio_call_button fcom_route el-button fcom_primary_button bpbm-pm-button bm-no-loader bm-no-style audio-call bm-user-' . $user_id,
                        'title' => _x('Audio Call', 'FluentCommunity Integration', 'bp-better-messages'),
                        'svg_icon' => '<span class="bm-loader-container"><svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="200px" width="200px" xmlns="http://www.w3.org/2000/svg"><path fill="none" stroke-miterlimit="10" stroke-width="32" d="M451 374c-15.88-16-54.34-39.35-73-48.76-24.3-12.24-26.3-13.24-45.4.95-12.74 9.47-21.21 17.93-36.12 14.75s-47.31-21.11-75.68-49.39-47.34-61.62-50.53-76.48 5.41-23.23 14.79-36c13.22-18 12.22-21 .92-45.3-8.81-18.9-32.84-57-48.9-72.8C119.9 44 119.9 47 108.83 51.6A160.15 160.15 0 0 0 83 65.37C67 76 58.12 84.83 51.91 98.1s-9 44.38 23.07 102.64 54.57 88.05 101.14 134.49S258.5 406.64 310.85 436c64.76 36.27 89.6 29.2 102.91 23s22.18-15 32.83-31a159.09 159.09 0 0 0 13.8-25.8C465 391.17 468 391.17 451 374z"></path></svg></span>',
                        'url' => $link
                    ];
                }
            }

            return $data;
        }

        public function add_messages_menu()
        {
            if( ! is_user_logged_in() ) return;
            ?>
            <li class="top_menu_item fcom_better_messages_menu_li  fcom_desktop_only">
                <a href="<?php echo Helper::baseUrl('messages'); ?>"
                   class="el-badge fcom_better_messages_menu item el-tooltip__trigger el-tooltip__trigger">
                    <div class="better_messages_icon">
                        <i class="el-icon">
                            <svg stroke="currentColor" fill="none" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" height="20px" width="20px" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"></path>
                            </svg>
                        </i>
                    </div>
                    <sup class="el-badge__content el-badge__content--danger bm-unread-badge is-fixed" style="display: none"></sup>
                </a>
            </li>
            <?php
        }
    }
}
