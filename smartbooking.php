<?php
/**
 * Plugin Name: Smart Booking
 * Description: Professional service booking system; services with images, staff, time slots, calendar, deposits, WhatsApp notifications. Built for African businesses.
 * Version:     4.1.0
 * Author:      Lelah Naomie
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SB_VER',  '4.1.0' );
define( 'SB_PATH', plugin_dir_path( __FILE__ ) );
define( 'SB_URL',  plugin_dir_url( __FILE__ ) );
define( 'SB_SLUG', 'smartbooking' );

foreach ( array(
    'includes/class-sb-db.php',
    'includes/class-sb-services.php',
    'includes/class-sb-staff.php',
    'includes/class-sb-slots.php',
    'includes/class-sb-booking.php',
    'includes/class-sb-notification.php',
    'includes/class-sb-pdf.php',
    'admin/class-sb-admin.php',
    'admin/class-sb-calendar.php',
) as $f ) {
    require_once SB_PATH . $f;
}

register_activation_hook( __FILE__, array( 'SB_DB', 'install' ) );

add_action( 'plugins_loaded', function() {
    new SB_Admin();
    new SB_Calendar_Page();
    new SB_Booking_Handler();
} );

add_action( 'wp_enqueue_scripts',    'sb_public_assets' );
add_action( 'admin_enqueue_scripts', 'sb_admin_assets'  );
add_action( 'wp_head',               'sb_inline_css'    );
add_action( 'wp_footer',             'sb_float_btn'     );
add_shortcode( 'smartbooking', array( 'SB_Booking_Handler', 'shortcode' ) );

add_action( 'send_headers', function() {
    if ( ! is_admin() ) return;
    $our = array('smartbooking','sb-services','sb-staff','sb-bookings','sb-calendar','sb-settings','sb-customers');
    $page = sanitize_key( $_GET['page'] ?? '' );
    if ( in_array( $page, $our, true ) ) {
        @header_remove('Content-Security-Policy');
        @header_remove('X-Content-Security-Policy');
        @header_remove('X-WebKit-CSP');
    }
});
add_filter( 'wp_headers', function( $headers ) {
    if ( ! is_admin() ) return $headers;
    $our = array('smartbooking','sb-services','sb-staff','sb-bookings','sb-calendar','sb-settings','sb-customers');
    if ( in_array( sanitize_key( $_GET['page'] ?? '' ), $our, true ) ) {
        unset($headers['Content-Security-Policy']);
        unset($headers['X-Content-Security-Policy']);
    }
    return $headers;
});
add_action( 'admin_init', function() {
    $our = array('smartbooking','sb-services','sb-staff','sb-bookings','sb-calendar','sb-settings','sb-customers');
    if ( in_array( sanitize_key( $_GET['page'] ?? '' ), $our, true ) && ! headers_sent() ) {
        @header_remove('Content-Security-Policy');
        @header_remove('X-Content-Security-Policy');
    }
});

function sb_public_assets() {
    wp_enqueue_style(  'sb-pub', SB_URL . 'assets/css/public.css', array(), SB_VER );
    wp_enqueue_script( 'sb-pub', SB_URL . 'assets/js/public.js',   array( 'jquery' ), SB_VER, true );
    wp_localize_script( 'sb-pub', 'SB', array(
        'ajax'     => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'sb_pub' ),
        'currency' => get_option( 'sb_currency', 'FCFA' ),
    ) );
}

function sb_admin_assets( $hook ) {
    // Triple-method matching — reliable on all WordPress configurations
    $page      = sanitize_key( $_GET['page'] ?? '' );
    $our_get   = array('smartbooking','sb-services','sb-staff','sb-bookings','sb-calendar','sb-settings','sb-customers');
    $our_hooks = array(
        'toplevel_page_smartbooking',
        'smartbooking_page_sb-services',  'smartbooking_page_sb-staff',
        'smartbooking_page_sb-bookings',  'smartbooking_page_sb-calendar',
        'smartbooking_page_sb-settings',  'smartbooking_page_sb-customers',
    );
    $load = in_array( $page, $our_get, true )
         || in_array( $hook, $our_hooks, true )
         || strpos( $hook, 'smartbooking' ) !== false;
    if ( ! $load ) return;

    wp_enqueue_style(  'wp-color-picker' );
    wp_enqueue_style(  'sb-adm', SB_URL . 'assets/css/admin.css',  array('wp-color-picker'), SB_VER );
    wp_enqueue_script( 'sb-adm', SB_URL . 'assets/js/admin.js',    array( 'jquery', 'wp-color-picker' ), SB_VER, true );
    wp_enqueue_media(); // enables WordPress media picker for image uploads
    wp_localize_script( 'sb-adm', 'SB', array(
        'ajax'           => admin_url( 'admin-ajax.php' ),
        'nonce'          => wp_create_nonce( 'sb_adm' ),
        'confirm_delete' => 'Are you sure? This cannot be undone.',
    ) );
}

function sb_inline_css() {
    $s  = get_option( 'sb_appearance', array() );
    $p  = sanitize_hex_color( $s['primary']  ?? '#5B2D8E' );
    $pd = sanitize_hex_color( $s['p_dark']   ?? '#4a2070' );
    $ac = sanitize_hex_color( $s['accent']   ?? '#C9A84C' );
    $bg = sanitize_hex_color( $s['bg']       ?? '#ffffff' );
    $tx = sanitize_hex_color( $s['text']     ?? '#1a1a2e' );
    $rd = intval( $s['radius'] ?? 10 );
    echo "<style>.sb-wrap{--p:{$p};--pd:{$pd};--ac:{$ac};--bg:{$bg};--tx:{$tx};--rd:{$rd}px}</style>\n";
}

function sb_float_btn() {
    $s = get_option( 'sb_settings', array() );
    if ( empty( $s['float_on'] ) || empty( $s['float_number'] ) ) return;
    $num  = preg_replace( '/[^0-9]/', '', $s['float_number'] );
    $type = $s['float_type'] ?? 'whatsapp';
    $href = $type === 'whatsapp' ? 'https://wa.me/' . $num : 'tel:+' . $num;
    $lbl  = esc_html( $s['float_label'] ?? 'Chat with us' );
    echo '<a href="' . esc_url( $href ) . '" class="sb-float" target="_blank" rel="noopener">' . $lbl . '</a>' . "\n";
}
