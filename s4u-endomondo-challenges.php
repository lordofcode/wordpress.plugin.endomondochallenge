<?php
/**
 * @package S4U Endomondo Challenges
 * @version 1.0.0
 */
/*
Plugin Name: S4U Endomondo Challenges
Plugin URI: https://solution4u.nl/wordpress-plug-in-endomondo-challenges/
Description: Show the challenge(s) you accepted on Endomondo on your website.
Author: ing. Dirk Hornstra
Version: 1.0.0
Author URI: https://solution4u.nl/
*/
require('s4u-endomondo-challenges-html.php');
$adminHandler = null;
if (is_admin()) {
    require('s4u-endomondo-challenges-admin.php');
    if (isset($_POST['admin_action'])) {
        $adminHandler = new S4U_Endomondo_Challenges_Admin();
        $adminHandler->ProcessPost();
    }
}

class S4U_Endomondo_Challenges_Widget extends WP_Widget {
 
    function __construct() {
        parent::__construct( false, __( 'Endomondo Uitdaging(en)', 'textdomain' ) );
    }
 
    function widget( $args, $instance ) {
        $html = new S4U_Endomondo_Challenges_Html();
        $html->ShowSideBarWidget();
    }
 
    function update( $new_instance, $old_instance ) {
        return $new_instance;
    }
 
    function form( $instance ) {
        return 'ADMIN HTML';
    }
}

function s4uendomondochallenges_register_widgets() {
    register_widget( 'S4U_Endomondo_Challenges_Widget' );
}
add_action( 'widgets_init', 's4uendomondochallenges_register_widgets' );

function s4uendomondochallenges_admin_settings() {
    $hasError = false;
    if ($adminHandler != null) {
        $hasError = $adminHandler->HasError();
    }
    $html = new S4U_Endomondo_Challenges_Html();
    if ($hasError) {
        $html->ShowAdminError($adminHandler->GetError());
    }
    else {
        $html->ShowAdminHtmlForm();
    }
}

function s4uendomondochallenges_admin_menu_action() {
    add_options_page( 'Endomondo Uitdaging(en)', 'Endomondo', 'administrator', __FILE__, 's4uendomondochallenges_admin_settings', 1 );
}
add_action('admin_menu', 's4uendomondochallenges_admin_menu_action');

/* because some pages on Endomondo are slow extend default CURL time-out of 5 seconds to 15 */
// source: https://gist.github.com/sudar/4945588
function bal_http_request_args($r) //called on line 237
{
	$r['timeout'] = 15;
	return $r;
}
add_filter('http_request_args', 'bal_http_request_args', 100, 1);

function bal_http_api_curl($handle) //called on line 1315
{
	curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 15 );
	curl_setopt( $handle, CURLOPT_TIMEOUT, 15 );
}
add_action('http_api_curl', 'bal_http_api_curl', 100, 1);

function bal_custom_http_request_timeout( $timeout ) {
	return 15; // 15 seconds
}
add_filter( 'http_request_timeout', 'bal_custom_http_request_timeout', 9999 );
?>