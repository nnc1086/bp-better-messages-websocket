<?php
global $wpdb;

if( ! function_exists('bp_core_get_table_prefix') ){
    function bp_core_get_table_prefix() {
        global $wpdb;

        /**
         * Filters the $wpdb base prefix.
         *
         * Intended primarily for use in multinetwork installations.
         *
         * @since 1.2.6
         *
         * @param string $base_prefix Base prefix to use.
         */
        return apply_filters( 'bp_core_get_table_prefix', $wpdb->base_prefix );
    }
}

if( ! function_exists('bp_core_number_format') ){
    function bp_core_number_format( $number = 0, $decimals = false ) {

        // Force number to 0 if needed.
        if ( ! is_numeric( $number ) ) {
            $number = 0;
        }

        /**
         * Filters the BuddyPress formatted number.
         *
         * @since 1.2.4
         *
         * @param string $value    BuddyPress formatted value.
         * @param int    $number   The number to be formatted.
         * @param bool   $decimals Whether or not to use decimals.
         */
        return apply_filters( 'bp_core_number_format', number_format_i18n( $number, $decimals ), $number, $decimals );
    }
}

if( ! function_exists('bp_core_fetch_avatar') ){
    function bp_core_fetch_avatar( $args = '' ) {

        global $current_blog;

        // Set the default variables array and parse it against incoming $args array.
        $params = wp_parse_args( $args, array(
            'item_id'       => false,
            'object'        => 'user',
            'type'          => 'thumb',
            'avatar_dir'    => false,
            'width'         => false,
            'height'        => false,
            'class'         => 'avatar',
            'css_id'        => false,
            'alt'           => '',
            'email'         => false,
            'no_grav'       => null,
            'html'          => true,
            'title'         => '',
            'extra_attr'    => '',
            'scheme'        => null,
            'rating'        => get_option( 'avatar_rating' ),
            'force_default' => false,
        ) );

        $params['class'] .= ' bbpm-avatar';
        $params['extra_attr'] .= ' data-user-id="' . $params['item_id'] . '"';


        $size = isset( $args['width'] ) ? $args['width'] : 50;

        $removed_um_filter = false;

        if( has_filter( 'get_avatar', 'um_get_avatar' ) ){
            remove_filter( 'get_avatar', 'um_get_avatar', 99999 );
            $removed_um_filter = true;
        }

        $removed_ps_filter = false;
        if( class_exists('PeepSo') && has_filter( 'get_avatar', array( PeepSo::get_instance(), 'filter_avatar' ) ) ){
            remove_filter( 'get_avatar', array( PeepSo::get_instance(), 'filter_avatar'), 20, 5 );
            $removed_ps_filter = true;
        }

        if( $params['html'] === false ){
            if( function_exists('get_wp_user_avatar_src') ){
                $return = get_wp_user_avatar_src( $params['item_id'], ['size' => $size] );
            } else {
                $return = get_avatar_url($params['item_id'], ['size' => $size]);
            }
        } else {
            if( function_exists('get_wp_user_avatar') ){
                $return = get_wp_user_avatar($params['item_id'], $size, '', '');
            } else {
                $return = get_avatar($params['item_id'], $size, '', '', $params);
            }
        }

        if( $removed_um_filter ) {
            add_filter( 'get_avatar', 'um_get_avatar', 99999, 5 );
        }

        if( $removed_ps_filter ) {
            add_filter( 'get_avatar', array( PeepSo::get_instance(), 'filter_avatar'), 20, 5 );
        }

        return $return;
    }
}


if( ! function_exists('bp_loggedin_user_id') ) {
    function bp_loggedin_user_id() {
        return Better_Messages()->functions->get_current_user_id();
    }
}

if( ! function_exists('bp_displayed_user_id') ) {
    function bp_displayed_user_id() {
        global $authordata;
        if ( isset($authordata->ID) ) {
            return $authordata->ID;
        } else {
            return get_current_user_id();
        }
    }
}

if( ! function_exists('bp_parse_args') ) {
function bp_parse_args( $args, $defaults = array(), $filter_key = '' ) {

    // Setup a temporary array from $args.
    if ( is_object( $args ) ) {
        $r = get_object_vars( $args );
    } elseif ( is_array( $args ) ) {
        $r =& $args;
    } else {
        wp_parse_str( $args, $r );
    }

    // Passively filter the args before the parse.
    if ( !empty( $filter_key ) ) {

        /**
         * Filters the arguments key before parsing if filter key provided.
         *
         * This is a dynamic filter dependent on the specified key.
         *
         * @since 2.0.0
         *
         * @param array $r Array of arguments to use.
         */
        $r = apply_filters( 'bp_before_' . $filter_key . '_parse_args', $r );
    }

    // Parse.
    if ( is_array( $defaults ) && !empty( $defaults ) ) {
        $r = array_merge( $defaults, $r );
    }

    // Aggressively filter the args after the parse.
    if ( !empty( $filter_key ) ) {

        /**
         * Filters the arguments key after parsing if filter key provided.
         *
         * This is a dynamic filter dependent on the specified key.
         *
         * @since 2.0.0
         *
         * @param array $r Array of parsed arguments.
         */
        $r = apply_filters( 'bp_after_' . $filter_key . '_parse_args', $r );
    }

    // Return the parsed results.
    return $r;
}
}

if( ! function_exists('bp_core_current_time') ) {
function bp_core_current_time( $gmt = true, $type = 'mysql' ) {

    /**
     * Filters the current GMT time to save into the DB.
     *
     * @since 1.2.6
     *
     * @param string $value Current GMT time.
     */
    return apply_filters( 'bp_core_current_time', current_time( $type, $gmt ) );
}
}
