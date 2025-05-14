<?php
defined( 'ABSPATH' ) || exit;

class Better_Messages_Mini
{

    public static function instance()
    {

        // Store the instance locally to avoid private static replication
        static $instance = null;

        // Only run these methods if they haven't been run previously
        if ( null === $instance ) {
            $instance = new Better_Messages_Mini;
            $instance->setup_actions();
        }

        // Always return the instance
        return $instance;

        // The last metroid is in captivity. The galaxy is at peace.
    }

    public function setup_actions()
    {
        add_action('wp_footer', array( $this, 'html' ), 200);
        add_action('fluent_community/portal_footer', array( $this, 'html' ), 200);
    }

    public function html(){
        if( ! is_user_logged_in() && ! Better_Messages()->guests->guest_access_enabled() ) return false;
        ?>
        <div class="bp-messages-wrap bp-better-messages-mini <?php Better_Messages()->functions->messages_classes(); ?>">
            <div class="chats"></div>
        </div>
        <?php
    }
}

function Better_Messages_Mini()
{
    return Better_Messages_Mini::instance();
}
