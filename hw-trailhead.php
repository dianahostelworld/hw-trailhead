<?php
/**
 * Plugin Name:       Hostelworld Trailhead
 * Description:       The blog's content pipeline: opportunities, research, ideas, briefs, drafts and a refresh schedule. Claude does the writing through a queue that runs on your own Claude account; you approve everything. Needs Hostelworld Content Health for Search Console data.
 * Version:           0.4.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Hostelworld
 * Text Domain:       hw-trailhead
 */

defined( 'ABSPATH' ) || exit;

define( 'TH_VERSION', '0.4.1' );
define( 'TH_DIR', plugin_dir_path( __FILE__ ) );
define( 'TH_URL', plugin_dir_url( __FILE__ ) );

require_once TH_DIR . 'includes/class-th-settings.php';
require_once TH_DIR . 'includes/class-th-items.php';
require_once TH_DIR . 'includes/class-th-library.php';
require_once TH_DIR . 'includes/class-th-images.php';
require_once TH_DIR . 'includes/class-th-ahrefs.php';
require_once TH_DIR . 'includes/class-th-data.php';
require_once TH_DIR . 'includes/class-th-schedule.php';
require_once TH_DIR . 'includes/class-th-update.php';
require_once TH_DIR . 'includes/class-th-bridge.php';
require_once TH_DIR . 'includes/class-th-tasks.php';
require_once TH_DIR . 'includes/class-th-actions.php';
require_once TH_DIR . 'includes/class-th-admin.php';

register_activation_hook( __FILE__, array( 'TH_Items', 'install' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'th_daily' ); } );

// Once a day, articles that are due are queued for Claude to refresh.
add_action( 'th_daily', array( 'TH_Actions', 'auto_queue' ) );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'th_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 05:00' ), 'daily', 'th_daily' );
		}
	}
);

add_action( 'init', array( 'TH_Tasks', 'register' ) );
add_action( 'plugins_loaded', array( 'TH_Items', 'maybe_upgrade' ) );
TH_Actions::init();
TH_Update::init();
TH_Bridge::init();
TH_Admin::init();
