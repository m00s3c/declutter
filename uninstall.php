<?php
/**
 * Removes Declutter's stored options when the plugin is deleted.
 *
 * @package Declutter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'declutter_settings' );
delete_option( 'declutter_seen' );
