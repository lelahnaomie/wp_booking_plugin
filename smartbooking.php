<?php
/**
 * Plugin Name: Smart Booking
 * Description: Professional service booking system; services with images, staff, time slots, calendar, deposits, WhatsApp notifications. Built for African businesses.
 * Version:     5.5.4
 * Author:      Lelah Naomie
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SB_VER',  '5.5.4' );
define( 'SB_PATH', plugin_dir_path( __FILE__ ) );
define( 'SB_URL',  plugin_dir_url( __FILE__ ) );
define( 'SB_SLUG', 'smartbooking' );
define( 'SB_DEBUG', false ); // Set to true only for local debugging

foreach ( array(
    'includes/class-sb-roles.php',       // ← Role management (load first)
    'includes/class-sb-db.php',
    'includes/class-sb-services.php',
    'includes/class-sb-staff.php',
    'includes/class-sb-slots.php',
    'includes/class-sb-booking.php',
    'includes/class-sb-portal.php',
    'includes/class-sb-staff-portal.php',
    'includes/class-sb-login.php',
    'includes/class-sb-admin-portal.php',
    'includes/class-sb-notification.php',
    'includes/class-sb-pdf.php',
    'admin/class-sb-admin.php',
    'admin/class-sb-calendar.php',
) as $f ) {
    require_once SB_PATH . $f;
}

register_activation_hook( __FILE__, array( 'SB_DB',    'install'        ) );
register_activation_hook( __FILE__, array( 'SB_Roles', 'register_role'  ) );
register_deactivation_hook( __FILE__, array( 'SB_Roles', 'remove_role'  ) );

add_action( 'plugins_loaded', function() {
    // Always check and add any missing DB columns (safe, fast, idempotent)
    SB_DB::maybe_upgrade();
    SB_Roles::register_role(); // Safe: checks if role exists first — runs every load to catch skipped activations
    SB_Roles::init();          // ← Role hooks (admin bar, access control)
    new SB_Admin();
    new SB_Calendar_Page();
    new SB_Booking_Handler();
    SB_Portal::init();
    SB_Staff_Portal::init();
    SB_Login::init();
    SB_Admin_Portal::init();
} );

add_action( 'wp_enqueue_scripts',    'sb_public_assets' );
add_action( 'admin_enqueue_scripts', 'sb_admin_assets'  );
add_action( 'wp_head',               'sb_inline_css', 999    );
add_action( 'admin_head',            'sb_inline_css', 999    );
add_action( 'wp_footer',             'sb_float_btn'     );

/**
 * Force Elementor Canvas template on any page that carries a portal shortcode.
 * Acts as a runtime safety-net — catches pages where the post-meta wasn't set.
 */
add_filter( 'template_include', 'sb_maybe_canvas_template', 99 );
function sb_maybe_canvas_template( $template ) {
    if ( is_admin() ) return $template;
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) return $template;

    $portal_tags = array(
        'sb_admin_dashboard', 'sb_admin_bookings', 'sb_admin_services',
        'sb_admin_staff', 'sb_admin_customers', 'sb_admin_calendar',
        'sb_admin_finances', 'sb_admin_settings', 'sb_admin_panel',
        'sb_portal', 'sb_staff_portal', 'sb_portal_login',
    );

    $has_portal = false;
    foreach ( $portal_tags as $tag ) {
        if ( has_shortcode( $post->post_content, $tag ) ) {
            $has_portal = true;
            break;
        }
    }

    if ( ! $has_portal ) return $template;

    // Try Elementor canvas first, fall back to blank/full-width alternatives
    $candidates = array(
        get_template_directory() . '/elementor/templates/canvas.php',   // Elementor in parent theme
        get_stylesheet_directory() . '/elementor/templates/canvas.php', // Elementor in child theme
        // Elementor stores its templates inside the plugin folder:
        WP_PLUGIN_DIR . '/elementor/modules/page-templates/templates/canvas.php',
    );

    foreach ( $candidates as $path ) {
        if ( file_exists( $path ) ) {
            // Also persist the meta so WP picks it up consistently
            update_post_meta( $post->ID, '_wp_page_template', 'elementor_canvas' );
            return $path;
        }
    }

    return $template; // Elementor not installed — return whatever was set
}
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
    $sb_settings = get_option( 'sb_settings', array() );
    wp_localize_script( 'sb-pub', 'SB', array(
        'ajax'                => admin_url( 'admin-ajax.php' ),
        'nonce'               => wp_create_nonce( 'sb_pub' ),
        'currency'            => get_option( 'sb_currency', 'FCFA' ),
        'step1_heading'       => sanitize_text_field( $sb_settings['service_label'] ?? get_option( 'sb_step1_heading', 'Choose a Service' ) ),
        'enable_daily_rental' => (int) ( $sb_settings['enable_days']   ?? get_option( 'sb_enable_daily_rental', 0 ) ),
        'show_categories'     => (int) get_option( 'sb_show_categories', 1 ),
        'require_staff'       => (int) ( $sb_settings['enable_staff'] ?? get_option( 'sb_require_staff', 1 ) ),
    ) );
    
    // Add form width CSS
    $desktop_width = intval( get_option( 'sb_form_max_width', 900 ) );
    wp_add_inline_style( 'sb-pub', "
        @media (min-width: 1200px) {
            .sb-wrap { max-width: {$desktop_width}px !important; }
            .sb-modal-box { max-width: {$desktop_width}px !important; }
            .sb-svc-modal-box { max-width: {$desktop_width}px !important; }
        }
    " );
}

function sb_admin_assets( $hook ) {
    // Triple-method matching — reliable on all WordPress configurations
    $page      = sanitize_key( $_GET['page'] ?? '' );
    $our_get   = array('smartbooking','sb-services','sb-staff','sb-bookings','sb-calendar','sb-settings','sb-customers','sb-finances');
    $our_hooks = array(
        'toplevel_page_smartbooking',
        'smartbooking_page_sb-services',  'smartbooking_page_sb-staff',
        'smartbooking_page_sb-bookings',  'smartbooking_page_sb-calendar',
        'smartbooking_page_sb-settings',  'smartbooking_page_sb-customers',
        'smartbooking_page_sb-finances',
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
    wp_localize_script( 'sb-adm', 'SB_ADM', array(
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'sb_adm' ),
    ) );
}

function sb_inline_css() {
    $s   = get_option( 'sb_appearance', array() );
    $p   = sanitize_hex_color( $s['primary']  ?? '#5B2D8E' ) ?: '#5B2D8E';
    $pd  = sanitize_hex_color( $s['p_dark']   ?? '#3b1a5c' ) ?: '#3b1a5c';
    $hdt = sanitize_hex_color( $s['hd_text']  ?? '#ffffff' ) ?: '#ffffff';
    $ac  = sanitize_hex_color( $s['accent']   ?? '#C9A84C' ) ?: '#C9A84C';
    $hv  = sanitize_hex_color( $s['hover']    ?? '#3b1a5c' ) ?: '#3b1a5c';
    $ar  = sanitize_hex_color( $s['arrow']    ?? '#5B2D8E' ) ?: '#5B2D8E';
    $bg  = sanitize_hex_color( $s['bg']       ?? '#ffffff' ) ?: '#ffffff';
    $tx  = sanitize_hex_color( $s['text']     ?? '#1a1a2e' ) ?: '#1a1a2e';
    $bt  = sanitize_hex_color( $s['btn_text'] ?? '#ffffff' ) ?: '#ffffff';
    $rd  = intval( $s['radius'] ?? 10 );
    $rd2 = max( 0, $rd - 3 );
    // Applied to :root AND every container — modals appended to <body> inherit too
    echo "<style>
:root{--p:{$p};--pd:{$pd};--hd-text:{$hdt};--ac:{$ac};--hover:{$hv};--arrow:{$ar};--bg:{$bg};--tx:{$tx};--btn-text:{$bt};--rd:{$rd}px;--rd2:{$rd2}px}
.sb-wrap,#sbSvcModal,#sbPolicyModal,.sb-modal-overlay,.sb-svc-modal-box{
  --p:{$p};--pd:{$pd};--hd-text:{$hdt};--ac:{$ac};--hover:{$hv};--arrow:{$ar};--bg:{$bg};--tx:{$tx};--btn-text:{$bt};--rd:{$rd}px;--rd2:{$rd2}px
}
/* Direct overrides so color changes are always visible even with aggressive caching */
.sb-wrap .sb-btn-primary,.sb-wrap #sbConfirmBtn{background:{$p} !important;border-color:{$p} !important;}
.sb-wrap .sb-btn-primary:hover,.sb-wrap #sbConfirmBtn:hover{background:{$pd} !important;border-color:{$pd} !important;}
.sb-wrap .sb-step-bubble.active,.sb-wrap .sb-step-bubble.done{background:{$p} !important;border-color:{$p} !important;}
.sb-wrap .sb-cal-day.sb-cal-selected{background:{$p} !important;color:#fff !important;}
.sb-wrap .sb-slot.selected{background:{$p} !important;color:#fff !important;border-color:{$p} !important;}
.sb-wrap .sb-service-card.selected,.sb-wrap .sb-service-card:hover{border-color:{$p} !important;}
.sb-wrap .sb-svc-color-bar{background:{$p} !important;}
.sb-wrap a{color:{$p} !important;}
.sb-wrap .sb-pay-btn.sb-pay-campay,.sb-wrap .sb-pay-btn.sb-pay-card{background:{$p} !important;}
/* Days stepper — transparent bg, black +/- always visible on any theme */
#sbWrap .sb-days-stepper .sb-days-btn,.sb-wrap .sb-days-stepper .sb-days-btn,
#sbWrap button.sb-days-btn,.sb-wrap button.sb-days-btn{
  background:transparent !important;background-color:transparent !important;
  color:#000000 !important;border:none !important;border-radius:0 !important;
  width:40px !important;height:40px !important;min-width:40px !important;min-height:40px !important;
  font-size:1.3rem !important;font-weight:700 !important;padding:0 !important;margin:0 !important;
  box-shadow:none !important;display:flex !important;align-items:center !important;justify-content:center !important;
  opacity:1 !important;visibility:visible !important;line-height:1 !important;cursor:pointer !important;
}
#sbWrap .sb-days-stepper .sb-days-btn:hover,#sbWrap .sb-days-stepper .sb-days-btn:focus,
.sb-wrap .sb-days-stepper .sb-days-btn:hover,.sb-wrap .sb-days-stepper .sb-days-btn:focus{
  background:transparent !important;background-color:transparent !important;color:#000000 !important;opacity:.7 !important;
}
</style>\n";
}

function sb_float_btn() {
    $s = get_option( 'sb_settings', array() );
    if ( empty( $s['float_on'] ) || empty( $s['float_number'] ) ) return;
    $num  = preg_replace( '/[^0-9]/', '', $s['float_number'] );
    $type = $s['float_type'] ?? 'whatsapp';
    $href = $type === 'whatsapp' ? 'https://wa.me/' . $num : 'tel:+' . $num;
    $lbl        = esc_html( $s['float_label'] ?? 'Chat with us' );
    $appearance = get_option( 'sb_appearance', array() );
    $float_color = ! empty( $s['float_color'] ) ? $s['float_color'] : ( $appearance['primary'] ?? '#5B2D8E' );
    $shadow_color = $float_color . '66';
    $style = 'background:' . esc_attr( $float_color ) . ';box-shadow:0 4px 18px ' . esc_attr( $shadow_color ) . ';';
    echo '<a href="' . esc_url( $href ) . '" class="sb-float" style="' . $style . '" target="_blank" rel="noopener">' . $lbl . '</a>' . "\n";
}

// ===== LOGIN PAGE CUSTOMIZATION =====
add_action( 'login_enqueue_scripts', 'sb_login_assets'    );
add_action( 'login_head',            'sb_login_head_css'   );
add_filter( 'login_headerurl',       'sb_login_logo_url'   );
add_filter( 'login_headertext',      'sb_login_logo_text'  );
add_filter( 'login_body_class',      'sb_login_body_class' );

function sb_login_assets() {
    $ap = get_option( 'sb_appearance', array() );
    $p  = sanitize_hex_color( $ap['primary'] ?? '#5B2D8E' ) ?: '#5B2D8E';
    $pd = sanitize_hex_color( $ap['p_dark']  ?? '#3b1a5c' ) ?: '#3b1a5c';
    wp_enqueue_style( 'sb-login', SB_URL . 'assets/css/login.css', array(), SB_VER );
    wp_add_inline_style( 'sb-login', ":root{--sb-p:{$p};--sb-pd:{$pd};}" );
}

function sb_login_head_css() {
    $s   = get_option( 'sb_settings', array() );
    $biz = esc_html( $s['business_name'] ?? get_bloginfo('name') );
    echo '<title>' . $biz . ' &mdash; Sign In</title>' . "\n";
}

function sb_login_logo_url()  { return home_url(); }

function sb_login_logo_text() {
    $s = get_option( 'sb_settings', array() );
    return esc_attr( $s['business_name'] ?? get_bloginfo('name') );
}

function sb_login_body_class( $classes ) {
    $classes[] = 'sb-login-page';
    return $classes;
}
