<?php
/**
 * Settings page
 */
defined( 'ABSPATH' ) || exit;

$messages_enabled = ! defined('BM_DISABLE_MESSAGES_VIEWER') && Better_Messages()->settings['messagesViewer'] !== '0';
$reports_enabled = class_exists('Better_Messages_User_Reports');
?>
<div class="wrap">
    <h1><?php _ex( 'Administration', 'WP Admin','bp-better-messages' ); ?></h1>

    <div id="messages-admin" data-messages="<?php echo json_encode($messages_enabled); ?>" data-reports="<?php echo json_encode($reports_enabled); ?>"></div>
</div>
