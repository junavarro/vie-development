<?php 
/*
Plugin Name: Fonts
Plugin URI: http://wpsites.net/plugins/fonts/
Description: Upgrade: <a href="http://wpsites.net/wordpress-admin/add-google-web-fonts-to-your-wordpress-editor/">Add Google Fonts</a> | <a href="http://wpsites.net/wordpress-themes/add-custom-fonts-to-the-wordpress-editor/">Add custom fonts</a> | <a href="https://www.facebook.com/wpsites.net/messages/">Support</a> | <a href="https://wordpress.org/support/plugin/fonts/reviews/?filter=5">Click here to leave a review</a>
Version: 2.3
Author: Brad Dalton - WP Sites
Author URI: http://wpsites.net/wordpress-admin/add-google-web-fonts-to-your-wordpress-editor/
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Sorry, you are not allowed to access this page directly.' );
}

function add_more_buttons($buttons) {
$buttons[] = 'fontselect';
$buttons[] = 'fontsizeselect';
return $buttons;
}

add_filter("mce_buttons_3", "add_more_buttons");

add_action('admin_notices', 'wpsites_fonts_plugin_notice');
function wpsites_fonts_plugin_notice() {
    global $current_user ;
        $user_id = $current_user->ID;
        /* Check that the user hasn't already clicked to ignore the message */
    if ( ! get_user_meta($user_id, 'example_ignore_notice') ) {
        echo '<div class="updated"><p>'; 
        printf(__('Please support fonts by leaving a review : <a href="https://wordpress.org/support/plugin/fonts/reviews/?filter=5">Click here to leave a quick review</a>  | <a href="%1$s">Hide Notice</a>'), '?example_nag_ignore=0');
        echo "</p></div>";
    }
}

add_action('admin_init', 'example_nag_ignore');
function example_nag_ignore() {
    global $current_user;
        $user_id = $current_user->ID;
        /* If user clicks to ignore the notice, add that to their user meta */
        if ( isset($_GET['example_nag_ignore']) && '0' == $_GET['example_nag_ignore'] ) {
             add_user_meta($user_id, 'example_ignore_notice', 'true', true);
    }
}

add_action('admin_menu', 'fonts_support_options');

function fonts_support_options() {

    $google_fonts = '<a href="https://wpsites.net/wordpress-admin/add-google-web-fonts-to-your-wordpress-editor/">Add Custom Fonts</a>';

    $custom_fonts = '<a href="https://wpsites.net/wordpress-themes/add-custom-fonts-to-the-wordpress-editor/">Add Google Fonts</a>';

    add_menu_page( 'Fonts', 'Fonts', 'manage_options', 'fonts_page', '', 'dashicons-editor-textcolor' );

    add_submenu_page( 'fonts_page', 'Add Google Fonts', $google_fonts, 'manage_options', 'fonts_page' );

    add_submenu_page( 'fonts_page', 'Add Custom Fonts', $custom_fonts, 'manage_options', 'fonts_sub' );
    
}
