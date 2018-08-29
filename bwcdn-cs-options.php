<?php
/*
 * BWCDN CS Menu & Sub-menu
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function bwcdn_cs_register_settings(  ) {
	if ( current_user_can( 'manage_network_options' ) || current_user_can( 'manage_options' ) ) {
			add_action( 'admin_menu', 'bwcdn_cs_main_menu' );
	}
}
function bwcdn_cs_main_menu() {
	add_menu_page(
		'BWCDN CS Options',
		'BWCDN CS',
		'manage_options',
		'bwcdn-cs-options.php',
		'bwcdn_cs_options',
		plugins_url( '../../assets/images/spinkx-ico.svg', __FILE__ ) ,
	'2.56' );

	add_submenu_page(
		'bwcdn-cs-options.php',
		'BWCDN Options | BWCDN CS',
		'My Site',
		'manage_options',
		'bwcdn-cs-options.php',
		'bwcdn_cs_options'
	);

}
function bwcdn_cs_options() {
	require_once plugins_url( 'includes/settings/site-registration.php' );
}
add_action( 'init', 'bwcdn_cs_register_settings' );

