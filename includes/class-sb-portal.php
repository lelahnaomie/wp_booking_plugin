<?php
/**
 * SmartBooking — Customer Frontend Portal
 * Shortcodes: [sb_portal]  (auto-routes: login / forgot / dashboard / settings)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Portal {

    const SESSION_KEY   = 'sb_portal_cid';   // customer id stored in session
    const HASH_META_KEY = 'sb_pw_hash';       // stored in sb_customers.notes JSON? No — wp_options keyed by customer id

    // ── BOOT ────────────────────────────────────────────────────────
    public static function init() {
        add_shortcode( 'sb_portal', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_login',    array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_sb_portal_login',           array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_request_access', array( __CLASS__, 'ajax_request_access' ) );
        add_action( 'wp_ajax_sb_portal_request_access',        array( __CLASS__, 'ajax_request_access' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_register', array( __CLASS__, 'ajax_register' ) );
        add_action( 'wp_ajax_sb_portal_register',        array( __CLASS__, 'ajax_register' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_forgot',   array( __CLASS__, 'ajax_forgot' ) );
        add_action( 'wp_ajax_sb_portal_forgot',          array( __CLASS__, 'ajax_forgot' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_reset',    array( __CLASS__, 'ajax_reset' ) );
        add_action( 'wp_ajax_sb_portal_reset',           array( __CLASS__, 'ajax_reset' ) );
        add_action( 'wp_ajax_sb_portal_logout',          array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_logout',   array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_sb_portal_save_profile',    array( __CLASS__, 'ajax_save_profile' ) );
        add_action( 'wp_ajax_nopriv_sb_portal_save_profile', array( __CLASS__, 'ajax_save_profile' ) );

        // Start session early
        add_action( 'init', array( __CLASS__, 'start_session' ), 1 );
    }

    public static function start_session() {
        if ( ! session_id() ) {
            session_start();
        }
    }

    // ── SESSION HELPERS ──────────────────────────────────────────────
    private static function get_logged_in_cid() {
        return isset( $_SESSION[ self::SESSION_KEY ] ) ? intval( $_SESSION[ self::SESSION_KEY ] ) : 0;
    }

    private static function set_session( $cid ) {
        $_SESSION[ self::SESSION_KEY ] = intval( $cid );
    }

    private static function destroy_session() {
        unset( $_SESSION[ self::SESSION_KEY ] );
    }

    // Returns the front-end portal page URL (works in AJAX context too)
    private static function get_portal_url() {
        $page_id = get_option( 'sb_portal_page_id', 0 );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) return $url;
        }
        // Fallback: scan for page using [sb_portal] shortcode
        global $wpdb;
        $page = $wpdb->get_row( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_content LIKE '%[sb_portal]%' LIMIT 1" );
        if ( $page ) {
            $url = get_permalink( $page->ID );
            if ( $url ) return $url;
        }
        return home_url( '/' );
    }

    // ── PASSWORD STORAGE ─────────────────────────────────────────────
    // Stored in wp_options as sb_cpw_{customer_id} (hashed with wp_hash_password)
    private static function get_pw_option( $cid ) {
        return get_option( 'sb_cpw_' . intval( $cid ), '' );
    }

    private static function set_pw_option( $cid, $plain ) {
        update_option( 'sb_cpw_' . intval( $cid ), wp_hash_password( $plain ), false );
    }

    private static function has_password( $cid ) {
        return (bool) self::get_pw_option( $cid );
    }

    private static function check_password( $cid, $plain ) {
        $hash = self::get_pw_option( $cid );
        if ( ! $hash ) return false;
        return wp_check_password( $plain, $hash );
    }

    // ── RESET TOKEN ──────────────────────────────────────────────────
    private static function make_reset_token( $cid ) {
        $token = wp_generate_password( 32, false );
        set_transient( 'sb_rst_' . $cid, wp_hash( $token ), 30 * MINUTE_IN_SECONDS );
        return $token;
    }

    private static function verify_reset_token( $cid, $token ) {
        $stored = get_transient( 'sb_rst_' . $cid );
        if ( ! $stored ) return false;
        return hash_equals( $stored, wp_hash( $token ) );
    }

    private static function consume_reset_token( $cid ) {
        delete_transient( 'sb_rst_' . $cid );
    }

    // ── BRAND COLORS ─────────────────────────────────────────────────
    private static function brand() {
        $s = get_option( 'sb_appearance', [] );
        return [
            'p'  => sanitize_hex_color( $s['primary'] ?? '#111111' ) ?: '#111111',
            'pd' => sanitize_hex_color( $s['p_dark']  ?? '#3b1a5c' ) ?: '#3b1a5c',
            'ac' => sanitize_hex_color( $s['accent']  ?? '#C9A84C' ) ?: '#C9A84C',
        ];
    }

    // ── SHORTCODE ROUTER ─────────────────────────────────────────────
    public static function shortcode( $atts ) {
        ob_start();

        $cid = self::get_logged_in_cid();

        // Admin with ?sb_preview_customer=N can preview any customer's dashboard.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) && isset( $_GET['sb_preview_customer'] ) ) {
            $preview_cid = intval( $_GET['sb_preview_customer'] );
            if ( $preview_cid > 0 ) {
                $cid = $preview_cid;
            }
        }

        // Admin without a customer session: show customer-picker instead of login form.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) && ! $cid ) {
            self::render_admin_preview_picker();
            return ob_get_clean();
        }

        // Handle ?sb_reset=token&sb_rc=cid links from email
        if ( isset( $_GET['sb_reset'] ) && isset( $_GET['sb_rc'] ) ) {
            self::render_reset_form();
        } elseif ( ! $cid ) {
            $view = sanitize_key( $_GET['sb_view'] ?? 'login' );
            if ( $view === 'forgot' ) {
                self::render_forgot();
            } elseif ( $view === 'register' ) {
                self::render_register();
            } else {
                self::render_login();
            }
        } else {
            $view = sanitize_key( $_GET['sb_view'] ?? 'dashboard' );
            if ( $view === 'settings' ) {
                self::render_settings( $cid );
            } else {
                self::render_dashboard( $cid );
            }
        }
        return ob_get_clean();
    }

    // ── ADMIN CUSTOMER PREVIEW PICKER ────────────────────────────────
    // Shown to WP admins who visit [sb_portal] without a customer session.
    // Lets them pick any customer to preview their dashboard.
    private static function render_admin_preview_picker() {
        global $wpdb;
        $b           = self::brand();
        $customers   = $wpdb->get_results(
            "SELECT id, name, email, phone, total_bookings FROM {$wpdb->prefix}sb_customers ORDER BY name ASC LIMIT 200"
        );
        $portal_url  = self::get_portal_url();
        ?>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        .sb-cp-wrap {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #f8f6fd;
            min-height: 40vh;
            padding: 0 0 40px;
            position: relative;
            width: 100vw !important;
            max-width: 100vw !important;
            margin-left: calc(50% - 50vw) !important;
            margin-right: calc(50% - 50vw) !important;
            box-sizing: border-box;
            --cp-p: <?php echo esc_attr( $b['p'] ); ?>;
            --cp-pd: <?php echo esc_attr( $b['pd'] ); ?>;
        }
        .sb-cp-header {
            background: linear-gradient(120deg, var(--cp-pd) 0%, var(--cp-p) 60%, #7c3aed 100%);
            padding: 22px 32px; margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(91,45,142,.3);
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
            position: relative; overflow: hidden;
        }
        .sb-cp-header::after { content:''; position:absolute; top:-40%; right:-5%; width:200px; height:200px; background:rgba(255,255,255,.06); border-radius:50%; pointer-events:none; }
        .sb-cp-header h2 { margin:0; font-size:1.2rem; font-weight:800; color:#fff; letter-spacing:-.02em; z-index:1; }
        .sb-cp-header p  { margin:4px 0 0; font-size:.82rem; color:rgba(255,255,255,.72); z-index:1; }
        .sb-cp-header-left { z-index: 1; }
        .sb-cp-admin-link { z-index:1; padding:7px 16px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.35); border-radius:8px; color:#fff; font-size:.8rem; font-weight:700; text-decoration:none; transition:background .15s; }
        .sb-cp-admin-link:hover { background:rgba(255,255,255,.3); color:#fff; }
        .sb-cp-search-wrap { padding: 0 32px 16px; }
        .sb-cp-search { width: 100%; max-width: 420px; padding: 9px 14px; border: 1.5px solid #d4cce8; border-radius: 10px; font-size: .9rem; font-family: inherit; outline: none; }
        .sb-cp-search:focus { border-color: var(--cp-p); }
        .sb-cp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; padding: 0 32px; }
        .sb-cp-card {
            background:#fff; border:1px solid #ebe8f4; border-radius:14px;
            padding:18px 16px; cursor:pointer; text-decoration:none; display:block;
            box-shadow:0 1px 4px rgba(91,45,142,.06),0 4px 16px rgba(91,45,142,.06);
            transition:transform .18s,box-shadow .18s,border-color .18s;
        }
        .sb-cp-card:hover { transform:translateY(-3px); box-shadow:0 4px 20px rgba(91,45,142,.14); border-color:var(--cp-p); }
        .sb-cp-avatar {
            width:44px; height:44px; border-radius:50%;
            background:linear-gradient(135deg, var(--cp-pd), var(--cp-p));
            display:flex; align-items:center; justify-content:center;
            font-size:1.1rem; font-weight:800; color:#fff; margin-bottom:10px;
        }
        .sb-cp-name  { font-weight:800; font-size:.93rem; color:#18113a; margin-bottom:3px; }
        .sb-cp-email { font-size:.73rem; color:#8b91a7; margin-bottom:3px; }
        .sb-cp-meta  { font-size:.73rem; color:#8b91a7; }
        .sb-cp-badge { display:inline-block; background:#ede8f8; color:var(--cp-p); font-size:.7rem; font-weight:700; padding:2px 8px; border-radius:20px; margin-top:6px; }
        .sb-cp-empty { text-align:center; padding:48px 20px; color:#8b91a7; }
        .sb-cp-empty p { font-size:.9rem; }
        .sb-cp-card[style*="display:none"] { display:none!important; }
        </style>

        <div class="sb-cp-wrap">
            <div class="sb-cp-header">
                <div class="sb-cp-header-left">
                    <h2>👤 Customer Portal — Admin Preview</h2>
                    <p>Select a customer below to preview their dashboard</p>
                </div>
                <a href="<?php echo esc_url( admin_url('admin.php?page=smartbooking') ); ?>" class="sb-cp-admin-link">← Admin Dashboard</a>
            </div>

            <?php if ( empty($customers) ): ?>
                <div class="sb-cp-empty">
                    <p>No customers found yet.</p>
                </div>
            <?php else: ?>
                <div class="sb-cp-search-wrap">
                    <input type="text" class="sb-cp-search" placeholder="Search customers by name or email..." oninput="sbCpFilter(this.value)">
                </div>
                <div class="sb-cp-grid" id="sbCpGrid">
                <?php foreach ( $customers as $c ):
                    $initials    = strtoupper( implode( '', array_map( fn($w) => $w[0], array_slice( explode(' ', $c->name), 0, 2 ) ) ) );
                    $preview_url = add_query_arg( ['sb_preview_customer' => $c->id], $portal_url );
                    $bk_count    = intval($c->total_bookings ?? 0);
                ?>
                    <a class="sb-cp-card" href="<?php echo esc_url( $preview_url ); ?>"
                       data-name="<?php echo esc_attr( strtolower($c->name) ); ?>"
                       data-email="<?php echo esc_attr( strtolower($c->email) ); ?>">
                        <div class="sb-cp-avatar"><?php echo esc_html($initials); ?></div>
                        <div class="sb-cp-name"><?php echo esc_html($c->name); ?></div>
                        <div class="sb-cp-email"><?php echo esc_html($c->email); ?></div>
                        <?php if ( $c->phone ): ?>
                        <div class="sb-cp-meta"><?php echo esc_html($c->phone); ?></div>
                        <?php endif; ?>
                        <?php if ( $bk_count > 0 ): ?>
                        <span class="sb-cp-badge"><?php echo $bk_count; ?> booking<?php echo $bk_count !== 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                </div>
                <script>
                function sbCpFilter(q){
                    q=q.toLowerCase().trim();
                    document.querySelectorAll('#sbCpGrid .sb-cp-card').forEach(function(c){
                        var n=c.dataset.name||'',e=c.dataset.email||'';
                        c.style.display=(!q||n.includes(q)||e.includes(q))?'':'none';
                    });
                }
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── SHARED WRAPPER ───────────────────────────────────────────────
    private static function wrap_open( $title ) {
        $settings  = get_option( 'sb_settings', [] );
        $biz       = esc_html( $settings['business_name'] ?? get_bloginfo('name') );
        $brand_p   = $settings['color_primary'] ?? '#111111';
        $brand_pd  = $settings['color_dark']    ?? '#3b1a5c';
        $ajax      = admin_url('admin-ajax.php');
        $nonce     = wp_create_nonce('sb_portal_nonce');
        echo '<style>
        @import url(\'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap\');
        /* ── Full-bleed breakout: escape the theme content container ── */
        .sb-portal-wrap{
            position:relative;
            width:100vw!important;
            max-width:100vw!important;
            margin-left:calc(50% - 50vw)!important;
            margin-right:calc(50% - 50vw)!important;
            padding:0!important;
            border-radius:0!important;
            box-shadow:none!important;
            border:none!important;
            background:#f8f6fd!important;
            min-height:100vh;
            font-family:\'Plus Jakarta Sans\',system-ui,sans-serif;
            box-sizing:border-box;
        }
        /* Hide theme page title/header when portal is active */
        .sb-portal-active .entry-title,
        .sb-portal-active .page-title,
        .sb-portal-active h1.wp-block-post-title,
        .sb-portal-active .site-main>.wp-block-group:first-child h1 { display:none!important; }
        .sb-portal-topnav{background:linear-gradient(120deg,' . $brand_pd . ' 0%,' . $brand_p . ' 60%,#111111 100%);color:#fff;display:flex;align-items:center;padding:0 24px;height:58px;position:sticky;top:0;z-index:1000;box-shadow:0 4px 16px rgba(0,0,0,.25);}
        .sb-portal-topnav-brand{font-size:1rem;font-weight:800;color:#fff;text-decoration:none;margin-right:auto;letter-spacing:-.02em;}
        .sb-portal-topnav-links{display:flex;gap:2px;}
        .sb-portal-topnav-link{padding:6px 14px;border-radius:8px;color:rgba(255,255,255,.75);text-decoration:none;font-size:.84rem;font-weight:600;transition:all .15s;}
        .sb-portal-topnav-link:hover,.sb-portal-topnav-link.active{background:rgba(255,255,255,.18);color:#fff;}
        .sb-portal-topnav-logout{padding:5px 14px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:8px;color:rgba(255,255,255,.85);font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;margin-left:12px;}
        .sb-portal-topnav-logout:hover{background:rgba(255,255,255,.24);color:#fff;}
        .sb-portal-body{padding:28px 32px;max-width:1280px;margin:0 auto;}
        @media(max-width:768px){.sb-portal-topnav-links{display:none;}.sb-portal-body{padding:16px 14px;}}
        </style>
        <script>document.body.classList.add("sb-portal-active");</script>';
        echo '<div class="sb-wrap sb-portal-wrap">';
        echo '<nav class="sb-portal-topnav">';
        echo '<span class="sb-portal-topnav-brand">📅 ' . $biz . '</span>';
        if ( self::get_logged_in_cid() ) {
            $cur_url  = remove_query_arg( 'sb_view' );
            $dash_url = add_query_arg( 'sb_view', 'dashboard', $cur_url );
            $sett_url = add_query_arg( 'sb_view', 'settings',  $cur_url );
            $view     = sanitize_key( $_GET['sb_view'] ?? 'dashboard' );
            echo '<div class="sb-portal-topnav-links">';
            echo '<a href="' . esc_url($dash_url) . '" class="sb-portal-topnav-link ' . ($view==='settings'?'':'active') . '">My Bookings</a>';
            echo '<a href="' . esc_url($sett_url) . '" class="sb-portal-topnav-link ' . ($view==='settings'?'active':'') . '">Profile</a>';
            echo '</div>';
            echo '<button class="sb-portal-topnav-logout" id="sbPortalLogoutBtn">Log out</button>';
            echo '<script>
            (function($){ $("#sbPortalLogoutBtn").on("click",function(){
                $.post(' . json_encode($ajax) . ',{action:"sb_portal_logout",nonce:' . json_encode($nonce) . '},function(){ window.location.reload(); });
            }); })(jQuery);
            </script>';
        }
        echo '</nav>';
        echo '<div class="sb-portal-body">';
    }

    private static function wrap_close() {
        echo '</div></div>'; // body + wrap
    }

    // ── LOGIN + REGISTER (tabbed, full-page like WP login) ─────────
    private static function render_login() {
        $portal_url = self::get_portal_url();
        $forgot_url = add_query_arg( 'sb_view', 'forgot', $portal_url );
        $active_tab = ( sanitize_key( $_GET['sb_view'] ?? 'login' ) === 'register' ) ? 'register' : 'login';
        $biz        = esc_html( get_option( 'sb_settings', [] )['business_name'] ?? get_bloginfo('name') );
        $ajax_url   = admin_url('admin-ajax.php');
        $nonce      = wp_create_nonce('sb_portal');
        // Get brand colors from settings
        $settings   = get_option( 'sb_settings', [] );
        $brand_p    = esc_attr( $settings['color_primary']  ?? '#111111' );
        $brand_pd   = esc_attr( $settings['color_dark']     ?? '#3b1a5c' );
        $booking_page_id = get_option( 'sb_booking_page_id', 0 );
        $booking_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url('/');
        ?>
        <style>
        /* ── Customer portal auth page — full-screen, matches WP login ── */
        body, html { margin:0; padding:0; }
        .sb-customer-auth-fullpage {
            min-height: 100vh;
            background: linear-gradient(135deg, <?php echo $brand_pd; ?> 0%, <?php echo $brand_p; ?> 55%, #7c3aed 100%);
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 16px 64px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            box-sizing: border-box;
        }
        /* Brand block */
        .sb-ca-brand {
            text-align: center;
            margin-bottom: 30px;
        }
        .sb-ca-brand-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,.15);
            border: 2px solid rgba(255,255,255,.30);
            border-radius: 18px;
            margin: 0 auto 14px;
            display: flex; align-items: center; justify-content: center;
        }
        .sb-ca-brand-icon svg { width: 30px; height: 30px; fill: #fff; }
        .sb-ca-brand-name {
            font-size: 1.55rem; font-weight: 800; color: #fff;
            letter-spacing: -.025em; line-height: 1.15;
        }
        .sb-ca-brand-sub {
            font-size: .7rem; color: rgba(255,255,255,.52);
            letter-spacing: .09em; text-transform: uppercase; margin-top: 6px;
        }
        /* White card */
        .sb-ca-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0,0,0,.28);
            padding: 36px 40px;
            width: 100%;
            max-width: 460px;
        }
        /* Tab switcher */
        .sb-ca-tabs {
            display: flex; position: relative;
            background: #f1f5f9; border-radius: 12px;
            padding: 4px; margin-bottom: 26px; overflow: hidden;
        }
        .sb-ca-tab {
            flex: 1; position: relative; z-index: 2;
            background: transparent; border: none; border-radius: 9px;
            padding: 10px 0;
            font-family: inherit; font-size: .875rem; font-weight: 600;
            color: #64748b; cursor: pointer; transition: color .2s;
        }
        .sb-ca-tab--active { color: #1e293b; }
        .sb-ca-tab-slider {
            position: absolute; top: 4px; left: 4px;
            width: calc(50% - 4px); height: calc(100% - 8px);
            background: #fff; border-radius: 9px;
            box-shadow: 0 2px 10px rgba(0,0,0,.10);
            transition: transform .22s cubic-bezier(.4,0,.2,1); z-index: 1;
        }
        /* Panels */
        .sb-ca-panel { display: none; }
        .sb-ca-panel--active { display: block; animation: sbCaPanelIn .22s ease; }
        @keyframes sbCaPanelIn { from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:translateY(0)} }
        .sb-ca-sub { font-size: .88rem; color: #64748b; margin: 0 0 20px; }
        /* Fields */
        .sb-ca-form { display: flex; flex-direction: column; gap: 14px; }
        .sb-ca-field { display: flex; flex-direction: column; gap: 5px; }
        .sb-ca-field label {
            font-size: .78rem; font-weight: 700; color: #374151;
            text-transform: uppercase; letter-spacing: .04em;
        }
        .sb-ca-field input {
            padding: 11px 14px; border: 1.5px solid #e5e7eb;
            border-radius: 9px; font-size: .92rem;
            font-family: inherit; color: #111827;
            background: #f9fafb; width: 100%; box-sizing: border-box;
            transition: border-color .15s, box-shadow .15s; outline: none;
        }
        .sb-ca-field input:focus {
            border-color: <?php echo $brand_p; ?>;
            background: #fff;
            box-shadow: 0 0 0 3px <?php echo $brand_p; ?>22;
        }
        /* Password wrap */
        .sb-ca-pw-wrap { position: relative; }
        .sb-ca-pw-wrap input { padding-right: 44px; }
        .sb-ca-pw-toggle {
            position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            font-size: 15px; color: #9ca3af; padding: 2px 4px; line-height: 1;
        }
        /* Submit button */
        .sb-ca-btn-primary {
            width: 100%; padding: 13px;
            background: <?php echo $brand_p; ?>; color: #fff;
            border: none; border-radius: 9px; cursor: pointer;
            font-family: inherit; font-size: .95rem; font-weight: 700;
            box-shadow: 0 4px 16px <?php echo $brand_p; ?>55;
            letter-spacing: .01em; margin-top: 4px;
            transition: background .18s, transform .12s, box-shadow .18s;
        }
        .sb-ca-btn-primary:hover {
            background: <?php echo $brand_pd; ?>;
            transform: translateY(-1px);
            box-shadow: 0 6px 22px <?php echo $brand_p; ?>66;
        }
        /* Success state */
        .sb-ca-success-box {
            text-align: center; padding: 8px 0 4px;
        }
        .sb-ca-success-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #dcfce7; color: #16a34a;
            font-size: 1.8rem; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 14px;
            animation: sbCaPop .4s ease;
        }
        @keyframes sbCaPop { 0%{transform:scale(.4)} 75%{transform:scale(1.1)} 100%{transform:scale(1)} }
        .sb-ca-success-box h3 { font-size: 1.1rem; font-weight: 800; color: #16a34a; margin: 0 0 6px; }
        .sb-ca-success-box p  { font-size: .88rem; color: #64748b; margin: 0 0 18px; }
        /* Links */
        .sb-ca-links { margin-top: 14px; text-align: center; font-size: .85rem; }
        .sb-ca-links a { color: <?php echo $brand_p; ?>; text-decoration: none; font-weight: 500; }
        .sb-ca-links a:hover { text-decoration: underline; }
        /* Messages */
        .sb-ca-msg { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: .875rem; display: none; }
        .sb-ca-msg-ok    { background: #f0fdf4; border: 1.5px solid #bbf7d0; color: #15803d; }
        .sb-ca-msg-error { background: #fef2f2; border: 1.5px solid #fecaca; color: #dc2626; }
        /* Lost password link below card */
        .sb-ca-below { margin-top: 18px; text-align: center; }
        .sb-ca-below a { color: rgba(255,255,255,.65); font-size: .83rem; text-decoration: none; transition: color .15s; }
        .sb-ca-below a:hover { color: #fff; text-decoration: underline; }
        /* Responsive */
        @media (max-width: 520px) {
            .sb-ca-card { padding: 28px 22px; border-radius: 16px; }
            .sb-customer-auth-fullpage { padding: 28px 12px 48px; }
        }
        </style>

        <div class="sb-customer-auth-fullpage">

            <!-- Brand header -->
            <div class="sb-ca-brand">
                <div class="sb-ca-brand-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                </div>
                <div class="sb-ca-brand-name"><?php echo $biz; ?></div>
                <div class="sb-ca-brand-sub">Booking Management</div>
            </div>

            <!-- White card -->
            <div class="sb-ca-card">

                <!-- Tab switcher -->
                <div class="sb-ca-tabs">
                    <button class="sb-ca-tab <?php echo $active_tab==='login'?'sb-ca-tab--active':''; ?>" data-tab="login">Sign In</button>
                    <button class="sb-ca-tab <?php echo $active_tab==='register'?'sb-ca-tab--active':''; ?>" data-tab="register">Sign Up</button>
                    <span class="sb-ca-tab-slider"></span>
                </div>

                <!-- Messages -->
                <div id="sbCaMsg" class="sb-ca-msg"></div>

                <!-- Login panel -->
                <div class="sb-ca-panel <?php echo $active_tab==='login'?'sb-ca-panel--active':''; ?>" id="sbCaPanelLogin">
                    <p class="sb-ca-sub">Welcome back — sign in to view your bookings.</p>
                    <div class="sb-ca-form" id="sbCaLoginForm">
                        <div class="sb-ca-field">
                            <label>Email address</label>
                            <input type="email" id="sbPEmail" placeholder="you@example.com" autocomplete="email">
                        </div>
                        <div class="sb-ca-field">
                            <label>Password</label>
                            <div class="sb-ca-pw-wrap">
                                <input type="password" id="sbPPassword" placeholder="Your password" autocomplete="current-password">
                                <button type="button" class="sb-ca-pw-toggle" data-target="sbPPassword">&#128065;</button>
                            </div>
                        </div>
                        <button class="sb-ca-btn-primary" id="sbCaLoginBtn">Sign In</button>
                    </div>
                    <div class="sb-ca-links">
                        <a href="<?php echo esc_url($forgot_url); ?>">Forgot password?</a>
                        &nbsp;·&nbsp;
                        <a href="#" id="sbGetAccessLink" style="color:var(--sb-p);font-weight:700;">Already booked? Get access →</a>
                    </div>
                </div>

                <!-- Get Access panel (for existing customers who booked before portal) -->
                <div class="sb-ca-panel" id="sbCaPanelGetAccess" style="display:none;">
                    <div style="text-align:center;margin-bottom:18px;">
                        <div style="font-size:2rem;margin-bottom:8px;">📬</div>
                        <p style="font-size:.9rem;color:#4b5563;margin:0 0 4px;font-weight:600;">Already booked with us?</p>
                        <p style="font-size:.82rem;color:#8b91a7;margin:0;">Enter the email you used when booking and we'll send your login details.</p>
                    </div>
                    <div id="sbGetAccessMsg" style="display:none;padding:12px 16px;border-radius:10px;font-size:.85rem;margin-bottom:14px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;"></div>
                    <div class="sb-ca-field">
                        <label>Your booking email</label>
                        <input type="email" id="sbGAEmail" placeholder="you@example.com" autocomplete="email">
                    </div>
                    <button class="sb-ca-btn-primary" id="sbGetAccessBtn" style="background:linear-gradient(135deg,<?php echo $brand_pd;?>,<?php echo $brand_p;?>);">Send my login details</button>
                    <div class="sb-ca-links" style="margin-top:14px;"><a href="#" id="sbBackToLoginLink">← Back to login</a></div>
                </div>

                <!-- Register panel -->
                <div class="sb-ca-panel <?php echo $active_tab==='register'?'sb-ca-panel--active':''; ?>" id="sbCaPanelRegister">

                    <!-- Success state (hidden initially) -->
                    <div class="sb-ca-success-box" id="sbCaRegSuccess" style="display:none;">
                        <div class="sb-ca-success-icon">✓</div>
                        <h3>Account created!</h3>
                        <p>Welcome aboard! You're being redirected to your dashboard&hellip;</p>
                    </div>

                    <!-- Register form -->
                    <div id="sbCaRegFormWrap">
                        <p class="sb-ca-sub">Create an account to manage all your bookings in one place.</p>
                        <div class="sb-ca-form" id="sbCaRegisterForm">
                            <div class="sb-ca-field">
                                <label>Full name</label>
                                <input type="text" id="sbRName" placeholder="Jane Smith" autocomplete="name">
                            </div>
                            <div class="sb-ca-field">
                                <label>Email address</label>
                                <input type="email" id="sbREmail" placeholder="you@example.com" autocomplete="email">
                            </div>
                            <div class="sb-ca-field">
                                <label>Phone <span style="font-weight:400;opacity:.55;text-transform:none;">(optional)</span></label>
                                <input type="tel" id="sbRPhone" placeholder="+237 6XX XXX XXX" autocomplete="tel">
                            </div>
                            <div class="sb-ca-field">
                                <label>Password</label>
                                <div class="sb-ca-pw-wrap">
                                    <input type="password" id="sbRPassword" placeholder="Min. 6 characters" autocomplete="new-password">
                                    <button type="button" class="sb-ca-pw-toggle" data-target="sbRPassword">&#128065;</button>
                                </div>
                            </div>
                            <div class="sb-ca-field">
                                <label>Confirm password</label>
                                <div class="sb-ca-pw-wrap">
                                    <input type="password" id="sbRPassword2" placeholder="Repeat password" autocomplete="new-password">
                                    <button type="button" class="sb-ca-pw-toggle" data-target="sbRPassword2">&#128065;</button>
                                </div>
                            </div>
                            <button class="sb-ca-btn-primary" id="sbCaRegisterBtn">Create Account</button>
                        </div>
                    </div>

                </div>

            </div><!-- /.sb-ca-card -->

            <div class="sb-ca-below">
                <a href="<?php echo esc_url($forgot_url); ?>">Lost your password?</a>
            </div>

        </div><!-- /.sb-customer-auth-fullpage -->

        <script>
        (function($){
            var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
            var nonce   = <?php echo json_encode($nonce); ?>;

            $(document).ready(function(){

                /* ---- Tab switching ---- */
                var $tabs = $('.sb-ca-tab'), $slider = $('.sb-ca-tab-slider');
                function activateTab(tab){
                    $tabs.removeClass('sb-ca-tab--active');
                    $tabs.filter('[data-tab="'+tab+'"]').addClass('sb-ca-tab--active');
                    $('.sb-ca-panel').removeClass('sb-ca-panel--active');
                    $('#sbCaPanel'+tab.charAt(0).toUpperCase()+tab.slice(1)).addClass('sb-ca-panel--active');
                    sbCaMsg('','');
                    $slider.css('transform','translateX('+($tabs.index($tabs.filter('.sb-ca-tab--active'))*100)+'%)');
                }
                $tabs.on('click', function(){ activateTab($(this).data('tab')); });
                $slider.css('transform','translateX('+($tabs.index($tabs.filter('.sb-ca-tab--active'))*100)+'%)');

                /* ---- Login ---- */
                function doLogin(){
                    var email=$('#sbPEmail').val().trim(), pw=$('#sbPPassword').val();
                    if(!email||!pw){ sbCaMsg('Please fill in all fields.','error'); return; }
                    $('#sbCaLoginBtn').prop('disabled',true).text('Signing in…');
                    $.post(ajaxUrl,{action:'sb_portal_login',nonce:nonce,email:email,password:pw},function(r){
                        $('#sbCaLoginBtn').prop('disabled',false).text('Sign In');
                        if(r.success){ window.location.href = r.data.redirect || window.location.href; }
                        else{ sbCaMsg(r.data.msg||'Login failed.','error'); }
                    }).fail(function(){ $('#sbCaLoginBtn').prop('disabled',false).text('Sign In'); sbCaMsg('Connection error.','error'); });
                }
                $('#sbCaLoginBtn').on('click', doLogin);
                $('#sbCaLoginForm input').on('keydown', function(e){ if(e.key==='Enter') doLogin(); });

                /* ---- Register ---- */
                function doRegister(){
                    var name=$('#sbRName').val().trim(), email=$('#sbREmail').val().trim(),
                        phone=$('#sbRPhone').val().trim(), pw=$('#sbRPassword').val(), pw2=$('#sbRPassword2').val();
                    if(!name||!email||!pw){ sbCaMsg('Name, email and password are required.','error'); return; }
                    if(pw!==pw2){ sbCaMsg('Passwords do not match.','error'); return; }
                    if(pw.length<6){ sbCaMsg('Password must be at least 6 characters.','error'); return; }
                    $('#sbCaRegisterBtn').prop('disabled',true).text('Creating account…');
                    $.post(ajaxUrl,{action:'sb_portal_register',nonce:nonce,name:name,email:email,phone:phone,password:pw},function(r){
                        if(r.success){
                            // Show success state, hide form
                            $('#sbCaRegFormWrap').fadeOut(200, function(){
                                $('#sbCaRegSuccess').fadeIn(300);
                            });
                            sbCaMsg('','');
                            setTimeout(function(){ window.location.href = r.data.redirect || window.location.href; }, 2000);
                        } else {
                            $('#sbCaRegisterBtn').prop('disabled',false).text('Create Account');
                            sbCaMsg(r.data.msg||'Registration failed.','error');
                        }
                    }).fail(function(){ $('#sbCaRegisterBtn').prop('disabled',false).text('Create Account'); sbCaMsg('Connection error.','error'); });
                }
                $('#sbCaRegisterBtn').on('click', doRegister);
                $('#sbCaRegisterForm input').on('keydown', function(e){ if(e.key==='Enter') doRegister(); });

                /* ---- Get Access (existing customer) ---- */
                $('#sbGetAccessLink').on('click', function(e){
                    e.preventDefault();
                    $('#sbCaPanelLogin,#sbCaPanelRegister').hide();
                    $('#sbCaPanelGetAccess').show();
                    // Hide tabs
                    $('.sb-ca-tabs').hide();
                    $('#sbCaMsg').hide();
                });
                $('#sbBackToLoginLink').on('click', function(e){
                    e.preventDefault();
                    $('#sbCaPanelGetAccess').hide();
                    $('#sbCaPanelLogin').show();
                    $('.sb-ca-tabs').show();
                });
                $('#sbGetAccessBtn').on('click', function(){
                    var email = $('#sbGAEmail').val().trim();
                    if (!email) { return; }
                    var $b = $(this);
                    $b.prop('disabled',true).text('Sending…');
                    $.post(ajaxUrl, {action:'sb_portal_request_access', nonce:nonce, email:email}, function(r){
                        $b.prop('disabled',false).text('Send my login details');
                        var $msg = $('#sbGetAccessMsg');
                        $msg.html(r.data.msg || 'Done.').show();
                        if (r.success) {
                            $msg.css({'background':'#f0fdf4','color':'#166534','border-color':'#bbf7d0'});
                            $('#sbGAEmail').val('');
                        } else {
                            $msg.css({'background':'#fef2f2','color':'#991b1b','border-color':'#fecaca'});
                        }
                    }).fail(function(){ $b.prop('disabled',false).text('Send my login details'); });
                });
                $('#sbGAEmail').on('keydown', function(e){ if(e.key==='Enter') $('#sbGetAccessBtn').trigger('click'); });

                /* ---- Password toggles ---- */
                $(document).on('click', '.sb-ca-pw-toggle', function(){
                    var t=$('#'+$(this).data('target'));
                    t.attr('type', t.attr('type')==='password'?'text':'password');
                });
            });
        })(jQuery);
        // sbCaMsg must use jQuery() directly, not $, since it's outside the wrapper
        function sbCaMsg(msg,type){
            var $m=jQuery('#sbCaMsg');
            if(!msg){ $m.hide().html('').removeClass('sb-ca-msg-ok sb-ca-msg-error'); return; }
            $m.removeClass('sb-ca-msg-ok sb-ca-msg-error').addClass('sb-ca-msg-'+(type||'ok')).html(msg).show();
        }
        </script>
        <?php
    }

    // ── REGISTER (alias — register tab lives inside render_login) ─────
    private static function render_register() {
        self::render_login();
    }

        // ── FORGOT PASSWORD ──────────────────────────────────────────────
    private static function render_forgot() {
        $login_url = remove_query_arg( 'sb_view' );
        self::wrap_open( 'Reset Password' );
        ?>
        <div class="sb-portal-auth-box">
            <h2 class="sb-portal-title">Reset your password</h2>
            <p class="sb-portal-sub">Enter your email and we'll send you a reset link</p>
            <div id="sbPortalMsg"></div>
            <div class="sb-portal-form" id="sbForgotForm">
                <div class="sb-portal-field">
                    <label>Email address</label>
                    <input type="email" id="sbFEmail" placeholder="you@example.com" autocomplete="email">
                </div>
                <button class="sb-btn sb-btn-primary sb-portal-submit" id="sbForgotBtn">Send Reset Link</button>
            </div>
            <div class="sb-portal-links"><a href="<?php echo esc_url($login_url); ?>">← Back to login</a></div>
        </div>
        <script>
        (function($){
            $(document).ready(function(){
                $('#sbForgotBtn').on('click',function(){
                    var email=$('#sbFEmail').val().trim();
                    if(!email){sbPMsg('Please enter your email.','error');return;}
                    $(this).prop('disabled',true).text('Sending…');
                    var $btn=$(this);
                    $.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>,{
                        action:'sb_portal_forgot',
                        nonce:<?php echo json_encode(wp_create_nonce('sb_portal')); ?>,
                        email:email
                    },function(r){
                        $btn.prop('disabled',false).text('Send Reset Link');
                        sbPMsg(r.data.msg||(r.success?'Check your email.':'Failed.'),'ok');
                    }).fail(function(){$btn.prop('disabled',false).text('Send Reset Link');sbPMsg('Connection error.','error');});
                });
                $('#sbForgotForm input').on('keydown',function(e){if(e.key==='Enter')$('#sbForgotBtn').trigger('click');});
            });
        })(jQuery);
        function sbPMsg(msg,type){var $m=$('#sbPortalMsg');$m.removeClass('sb-portal-msg-error sb-portal-msg-ok').addClass('sb-portal-msg-'+(type||'ok')).html(msg).show();}
        </script>
        <?php
        self::wrap_close();
    }

    // ── RESET PASSWORD FORM ──────────────────────────────────────────
    private static function render_reset_form() {
        $token = sanitize_text_field( $_GET['sb_reset'] ?? '' );
        $cid   = intval( $_GET['sb_rc'] ?? 0 );
        $valid = $cid && self::verify_reset_token( $cid, $token );
        self::wrap_open( 'Set New Password' );
        ?>
        <div class="sb-portal-auth-box">
            <h2 class="sb-portal-title">Set new password</h2>
            <?php if ( ! $valid ): ?>
            <div class="sb-portal-msg sb-portal-msg-error">This link is invalid or has expired. Please request a new one.</div>
            <?php else: ?>
            <div id="sbPortalMsg"></div>
            <div class="sb-portal-form">
                <div class="sb-portal-field">
                    <label>New password</label>
                    <div class="sb-portal-pw-wrap">
                        <input type="password" id="sbNewPw" placeholder="Min 6 characters" autocomplete="new-password">
                        <button type="button" class="sb-portal-pw-toggle" data-target="sbNewPw">👁</button>
                    </div>
                </div>
                <div class="sb-portal-field">
                    <label>Confirm password</label>
                    <input type="password" id="sbNewPw2" placeholder="Repeat password" autocomplete="new-password">
                </div>
                <button class="sb-btn sb-btn-primary sb-portal-submit" id="sbResetBtn">Set Password &amp; Sign In</button>
            </div>
            <script>
            (function($){
                $(document).ready(function(){
                    $('.sb-portal-pw-toggle').on('click',function(){var t=$('#'+$(this).data('target'));t.attr('type',t.attr('type')==='password'?'text':'password');});
                    $('#sbResetBtn').on('click',function(){
                        var pw=$('#sbNewPw').val(),pw2=$('#sbNewPw2').val();
                        if(pw.length<6){sbPMsg('Password must be at least 6 characters.','error');return;}
                        if(pw!==pw2){sbPMsg('Passwords do not match.','error');return;}
                        $(this).prop('disabled',true).text('Saving…');
                        var $btn=$(this);
                        $.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>,{
                            action:'sb_portal_reset',
                            nonce:<?php echo json_encode(wp_create_nonce('sb_portal')); ?>,
                            cid:<?php echo intval($cid); ?>,
                            token:<?php echo json_encode($token); ?>,
                            password:pw
                        },function(r){
                            $btn.prop('disabled',false).text('Set Password & Sign In');
                            if(r.success){sbPMsg('Password set! Redirecting…','ok');setTimeout(function(){window.location.href=<?php echo json_encode(remove_query_arg(['sb_reset','sb_rc'])); ?>;},1200);}
                            else{sbPMsg(r.data.msg||'Failed.','error');}
                        }).fail(function(){$btn.prop('disabled',false).text('Set Password & Sign In');sbPMsg('Connection error.','error');});
                    });
                });
            })(jQuery);
            function sbPMsg(msg,type){var $m=$('#sbPortalMsg');$m.removeClass('sb-portal-msg-error sb-portal-msg-ok').addClass('sb-portal-msg-'+(type||'ok')).html(msg).show();}
            </script>
            <?php endif; ?>
        </div>
        <?php
        self::wrap_close();
    }

    // ── DASHBOARD ────────────────────────────────────────────────────
    private static function render_dashboard( $cid ) {
        global $wpdb;
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sb_customers WHERE id=%d", $cid
        ) );
        if ( ! $customer ) { self::destroy_session(); wp_redirect( get_permalink() ); exit; }

        $cur      = get_option( 'sb_currency', 'FCFA' );
        $settings = get_option( 'sb_settings', [] );
        $brand_p  = $settings['color_primary'] ?? '#111111';
        $brand_pd = $settings['color_dark']    ?? '#3b1a5c';

        // Booking page URL
        $booking_page_id = get_option( 'sb_booking_page_id', 0 );
        $booking_url     = $booking_page_id ? get_permalink( $booking_page_id ) : home_url('/');

        // Bookings strictly belonging to this customer (email match is primary key; phone as fallback only if no email)
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, s.name as service_name, s.color as service_color, st.name as staff_name
             FROM {$wpdb->prefix}sb_bookings b
             LEFT JOIN {$wpdb->prefix}sb_services s  ON b.service_id = s.id
             LEFT JOIN {$wpdb->prefix}sb_staff    st ON b.staff_id   = st.id
             WHERE b.customer_email = %s
             ORDER BY b.booking_date DESC, b.start_time DESC
             LIMIT 200",
            $customer->email
        ) );

        // Stats
        $today           = date('Y-m-d');
        $total_bookings  = count( $bookings );
        $completed       = count( array_filter( $bookings, fn($b) => $b->status === 'completed' ) );
        $cancelled       = count( array_filter( $bookings, fn($b) => $b->status === 'cancelled' ) );
        $upcoming        = array_filter( $bookings, fn($b) => in_array($b->status, ['pending','confirmed','awaiting_deposit']) && $b->booking_date >= $today );
        $upcoming_count  = count( $upcoming );
        $total_spent     = array_sum( array_map( fn($b) => $b->status==='completed' ? (float)$b->total_price : 0, $bookings ) );
        $deposits_paid   = array_sum( array_map( fn($b) => (float)$b->deposit_amount,
            array_filter($bookings, fn($b) => in_array($b->payment_status, ['deposit_paid','paid_in_full']))
        ) );
        $outstanding     = array_sum( array_map( fn($b) => (float)$b->balance_amount,
            array_filter($bookings, fn($b) => $b->payment_status !== 'paid_in_full' && !in_array($b->status,['cancelled','completed']))
        ) );

        // Next upcoming booking
        $next_booking = null;
        foreach ( $bookings as $b ) {
            if ( in_array($b->status, ['pending','confirmed','awaiting_deposit']) && $b->booking_date >= $today ) {
                $next_booking = $b; break;
            }
        }
        // Reverse to get actual soonest (query is DESC)
        $upcoming_asc = array_reverse( array_values( $upcoming ) );
        if ( $upcoming_asc ) $next_booking = $upcoming_asc[0];

        // Filter tabs data
        $status_map = ['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled','awaiting_deposit'=>'Awaiting Deposit'];
        $pay_map    = ['unpaid'=>'Unpaid','deposit_paid'=>'Deposit Paid','paid_in_full'=>'Paid in Full'];

        self::wrap_open( 'Dashboard' );
        ?>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        /* ── Customer Dashboard — matches Admin design system ── */
        .sb-cd {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            --cd-purple: <?php echo $brand_p; ?>;
            --cd-purple-dk: <?php echo $brand_pd; ?>;
            --cd-purple-lt: #f5f0ff;
            --cd-purple-mid: #ede0ff;
            --cd-border: #ebe8f4;
            --cd-card: #fff;
            --cd-text: #18113a;
            --cd-text2: #4b5563;
            --cd-muted: #8b91a7;
            --cd-shadow: 0 1px 4px rgba(91,45,142,.06), 0 4px 16px rgba(91,45,142,.06);
            --cd-shadow-md: 0 4px 20px rgba(91,45,142,.10), 0 1px 6px rgba(91,45,142,.06);
            --cd-radius: 14px;
            background: #f8f6fd;
            min-height: 60vh;
        }

        /* ── PAGE HEADER (gradient, matches admin) ── */
        .sb-cd-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; padding: 22px 28px; margin: 0 0 24px;
            background: linear-gradient(120deg, var(--cd-purple-dk) 0%, var(--cd-purple) 60%, #7c3aed 100%);
            border-radius: var(--cd-radius); box-shadow: 0 6px 24px rgba(91,45,142,.3);
            position: relative; overflow: hidden;
        }
        .sb-cd-header::after { content:''; position:absolute; top:-40%; right:-5%; width:200px; height:200px; background:rgba(255,255,255,.06); border-radius:50%; pointer-events:none; }
        .sb-cd-header-left { display:flex; align-items:center; gap:14px; z-index:1; }
        .sb-cd-avatar { width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,.2); border:2px solid rgba(255,255,255,.35); display:flex; align-items:center; justify-content:center; font-size:1.3rem; font-weight:800; color:#fff; flex-shrink:0; }
        .sb-cd-greet-text h2 { margin:0 0 2px; font-size:1.25rem; font-weight:800; color:#fff; letter-spacing:-.02em; }
        .sb-cd-greet-text p  { margin:0; font-size:.82rem; color:rgba(255,255,255,.7); }
        .sb-cd-book-btn { flex-shrink:0; padding:9px 20px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.35); border-radius:10px; color:#fff; font-size:.84rem; font-weight:700; text-decoration:none; transition:background .18s; z-index:1; white-space:nowrap; }
        .sb-cd-book-btn:hover { background:rgba(255,255,255,.30); color:#fff; }

        /* ── STATS ── */
        .sb-cd-stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr)); gap:14px; margin-bottom:24px; }
        .sb-cd-stat { background:var(--cd-card); border:1px solid var(--cd-border); border-radius:var(--cd-radius); padding:20px 18px; display:flex; align-items:center; gap:14px; box-shadow:var(--cd-shadow); transition:all .18s; position:relative; overflow:hidden; cursor:default; }
        .sb-cd-stat::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:var(--cd-radius) var(--cd-radius) 0 0; }
        .sb-cd-stat:hover { transform:translateY(-3px); box-shadow:var(--cd-shadow-md); }
        .sb-cd-stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .sb-cd-stat-val { font-size:1.35rem; font-weight:800; color:var(--cd-text); line-height:1; letter-spacing:-.03em; }
        .sb-cd-stat-lbl { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--cd-muted); margin-top:4px; display:block; }
        .sb-cd-stat-p::before { background:var(--cd-purple); } .sb-cd-stat-p .sb-cd-stat-icon { background:var(--cd-purple-lt); color:var(--cd-purple); }
        .sb-cd-stat-a::before { background:#d97706; } .sb-cd-stat-a .sb-cd-stat-icon { background:#fffbeb; color:#d97706; }
        .sb-cd-stat-g::before { background:#16a34a; } .sb-cd-stat-g .sb-cd-stat-icon { background:#f0fdf4; color:#16a34a; }
        .sb-cd-stat-b::before { background:#2563eb; } .sb-cd-stat-b .sb-cd-stat-icon { background:#eff6ff; color:#2563eb; }

        /* ── LAYOUT ── */
        .sb-cd-layout { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:flex-start; }

        /* ── CARDS ── */
        .sb-cd-card { background:var(--cd-card); border:1px solid var(--cd-border); border-radius:var(--cd-radius); overflow:hidden; box-shadow:var(--cd-shadow); margin-bottom:20px; }
        .sb-cd-card-head { padding:16px 22px; border-bottom:1px solid var(--cd-border); display:flex; align-items:center; justify-content:space-between; }
        .sb-cd-card-title { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--cd-purple); margin:0; }

        /* ── FILTER TABS ── */
        .sb-cd-tabs { display:flex; gap:4px; padding:14px 22px 0; flex-wrap:wrap; }
        .sb-cd-tab { padding:5px 12px; border-radius:100px; font-size:.78rem; font-weight:700; border:1.5px solid var(--cd-border); background:transparent; color:var(--cd-muted); cursor:pointer; transition:all .15s; font-family:inherit; }
        .sb-cd-tab:hover { border-color:var(--cd-purple); color:var(--cd-purple); }
        .sb-cd-tab.active { background:var(--cd-purple); color:#fff; border-color:var(--cd-purple); }

        /* ── BOOKING ROWS ── */
        .sb-cd-booking-list { display:flex; flex-direction:column; padding:12px 22px; gap:10px; }
        .sb-cd-booking { display:flex; border:1.5px solid var(--cd-border); border-radius:12px; overflow:hidden; background:#fff; transition:box-shadow .18s, transform .15s; }
        .sb-cd-booking:hover { box-shadow:var(--cd-shadow-md); transform:translateY(-1px); }
        .sb-cd-booking.past { opacity:.7; }
        .sb-cd-booking-stripe { width:4px; flex-shrink:0; }
        .sb-cd-booking-body  { flex:1; padding:12px 14px; }
        .sb-cd-booking-top   { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
        .sb-cd-booking-svc   { font-weight:700; font-size:.92rem; color:var(--cd-text); }
        .sb-cd-booking-meta  { display:flex; gap:10px; font-size:.78rem; color:var(--cd-muted); flex-wrap:wrap; margin-bottom:6px; }
        .sb-cd-booking-fin   { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .sb-cd-booking-price { font-weight:700; font-size:.9rem; color:var(--cd-text); }
        .sb-cd-booking-notes { font-size:.76rem; color:var(--cd-muted); font-style:italic; margin-top:4px; }
        .sb-cd-ref { font-size:.72rem; color:var(--cd-muted); font-family:monospace; }
        .sb-cd-balance-due { font-size:.76rem; font-weight:700; color:#dc2626; }

        /* ── BADGES ── */
        .sb-cd-status { font-size:.65rem; font-weight:700; padding:3px 9px; border-radius:100px; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; }
        .sb-cd-status-pending          { background:#fef3c7; color:#92400e; }
        .sb-cd-status-confirmed        { background:#dbeafe; color:#1e40af; }
        .sb-cd-status-completed        { background:#dcfce7; color:#15803d; }
        .sb-cd-status-cancelled        { background:#fee2e2; color:#dc2626; }
        .sb-cd-status-awaiting_deposit { background:var(--cd-purple-mid); color:var(--cd-purple); }
        .sb-cd-pay-badge { font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:100px; }
        .sb-cd-pay-unpaid       { background:#fee2e2; color:#dc2626; }
        .sb-cd-pay-deposit_paid { background:#d1fae5; color:#065f46; }
        .sb-cd-pay-paid_in_full { background:#bbf7d0; color:#14532d; }

        /* ── SIDEBAR ── */
        .sb-cd-next-card { background:linear-gradient(135deg, var(--cd-purple-dk), var(--cd-purple)); border-radius:12px; padding:18px; color:#fff; margin-bottom:16px; box-shadow:var(--cd-shadow); }
        .sb-cd-next-label { font-size:.65rem; text-transform:uppercase; letter-spacing:.08em; opacity:.7; font-weight:800; margin-bottom:8px; }
        .sb-cd-next-svc   { font-size:1rem; font-weight:800; margin-bottom:8px; }
        .sb-cd-next-row   { display:flex; align-items:center; gap:6px; font-size:.8rem; opacity:.85; margin-bottom:4px; }
        .sb-cd-balance-card { background:#fffbeb; border:1.5px solid #fde68a; border-radius:12px; padding:16px; margin-bottom:16px; }
        .sb-cd-balance-label { font-size:.65rem; text-transform:uppercase; letter-spacing:.08em; color:#92400e; font-weight:800; margin-bottom:8px; }
        .sb-cd-balance-row { display:flex; justify-content:space-between; font-size:.84rem; color:#78350f; padding:3px 0; }
        .sb-cd-balance-row strong { font-weight:800; }
        .sb-cd-sidebar-card { background:var(--cd-card); border:1px solid var(--cd-border); border-radius:var(--cd-radius); overflow:hidden; box-shadow:var(--cd-shadow); }
        .sb-cd-sidebar-head { padding:14px 18px; border-bottom:1px solid var(--cd-border); }
        .sb-cd-sidebar-title { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--cd-purple); margin:0; }
        .sb-cd-sidebar-body { padding:14px 18px; }

        /* ── EMPTY ── */
        .sb-cd-empty { text-align:center; padding:48px 20px; }
        .sb-cd-empty-icon { font-size:3rem; margin-bottom:12px; }
        .sb-cd-empty p { color:var(--cd-muted); margin:0 0 16px; font-size:.9rem; }

        .sb-cd-booking[data-status].hidden { display: none; }

        /* ── RESPONSIVE ── */
        @media(max-width:680px){
            .sb-cd-layout { grid-template-columns:1fr; }
            .sb-cd-stats  { grid-template-columns:1fr 1fr; }
            .sb-cd-header { padding:18px; flex-wrap:wrap; }
            .sb-cd-book-btn { margin-left:0; width:100%; text-align:center; }
        }
        @media(max-width:400px){
            .sb-cd-stats { grid-template-columns:1fr; }
        }

        /* ── 7-DAY CHART (matches admin sbw-chart-wrap) ── */
        .sb-cd-chart-wrap { background:var(--cd-purple-lt); border:1px solid var(--cd-purple-mid); border-radius:12px; padding:18px 20px; margin-bottom:24px; }
        .sb-cd-chart-title { font-size:.7rem; font-weight:800; color:var(--cd-purple); text-transform:uppercase; letter-spacing:.08em; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }
        .sb-cd-bar-chart { display:flex; align-items:flex-end; gap:6px; height:72px; }
        .sb-cd-bar-col { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; }
        .sb-cd-bar { width:100%; background:linear-gradient(180deg,#333 0%,var(--cd-purple) 100%); border-radius:5px 5px 0 0; min-height:4px; transition:opacity .18s; }
        .sb-cd-bar.today { background:linear-gradient(180deg,#a78bfa 0%,var(--cd-purple) 100%); }
        .sb-cd-bar:hover { opacity:.75; }
        .sb-cd-bar-val { font-size:.62rem; font-weight:700; color:var(--cd-purple); font-family:monospace; }
        .sb-cd-bar-day { font-size:.6rem; color:var(--cd-muted); font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
        .sb-cd-bar-day.today { color:var(--cd-purple); }
        </style>

        <div class="sb-cd">

        <!-- Page header — admin-style gradient -->
        <div class="sb-cd-header">
            <div class="sb-cd-header-left">
                <div class="sb-cd-avatar"><?php echo esc_html( strtoupper( substr( trim($customer->name), 0, 1 ) ) ); ?></div>
                <div class="sb-cd-greet-text">
                    <h2>Hello, <?php echo esc_html( explode(' ', trim($customer->name) )[0] ); ?>! 👋</h2>
                    <p><?php echo date('l, d F Y'); ?></p>
                </div>
            </div>
            <a href="<?php echo esc_url($booking_url); ?>" class="sb-cd-book-btn">＋ New Booking</a>
        </div>

        <!-- Stats row -->
        <?php if ( $total_bookings > 0 ): ?>
        <div class="sb-cd-stats">
            <div class="sb-cd-stat sb-cd-stat-p">
                <div class="sb-cd-stat-icon">📅</div>
                <div>
                    <div class="sb-cd-stat-val"><?php echo $total_bookings; ?></div>
                    <div class="sb-cd-stat-lbl">Total Bookings</div>
                </div>
            </div>
            <div class="sb-cd-stat sb-cd-stat-a">
                <div class="sb-cd-stat-icon">⏳</div>
                <div>
                    <div class="sb-cd-stat-val"><?php echo $upcoming_count; ?></div>
                    <div class="sb-cd-stat-lbl">Upcoming</div>
                </div>
            </div>
            <div class="sb-cd-stat sb-cd-stat-g">
                <div class="sb-cd-stat-icon">✅</div>
                <div>
                    <div class="sb-cd-stat-val"><?php echo $completed; ?></div>
                    <div class="sb-cd-stat-lbl">Completed</div>
                </div>
            </div>
            <div class="sb-cd-stat sb-cd-stat-b">
                <div class="sb-cd-stat-icon">💰</div>
                <div>
                    <div class="sb-cd-stat-val"><?php echo number_format($total_spent); ?></div>
                    <div class="sb-cd-stat-lbl">Spent (<?php echo esc_html($cur); ?>)</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Two-column layout -->
        <?php
        // 7-day booking activity chart (matching admin dashboard bar chart)
        $chart_days = [];
        for ( $i = 6; $i >= 0; $i-- ) {
            $d = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_days[ $d ] = 0;
        }
        foreach ( $bookings as $bk ) {
            if ( isset( $chart_days[ $bk->booking_date ] ) ) {
                $chart_days[ $bk->booking_date ]++;
            }
        }
        $chart_max = max( array_values( $chart_days ) ) ?: 1;
        $today_str = date( 'Y-m-d' );
        ?>
        <?php if ( $total_bookings > 0 ): ?>
        <div class="sb-cd-chart-wrap">
            <div class="sb-cd-chart-title">
                <span>📊 My Activity — Last 7 Days</span>
            </div>
            <div class="sb-cd-bar-chart">
            <?php foreach ( $chart_days as $day => $count ):
                $pct    = round( ($count / $chart_max) * 100 );
                $height = max( 4, intval( $pct * 0.72 ) );
                $is_today = ( $day === $today_str );
                $lbl    = date( 'D', strtotime( $day ) );
            ?>
                <div class="sb-cd-bar-col">
                    <div class="sb-cd-bar-val"><?php echo $count ?: ''; ?></div>
                    <div class="sb-cd-bar<?php echo $is_today ? ' today' : ''; ?>" style="height:<?php echo $height; ?>px" title="<?php echo esc_attr($count); ?> bookings on <?php echo esc_attr(date('D d M', strtotime($day))); ?>"></div>
                    <div class="sb-cd-bar-day<?php echo $is_today ? ' today' : ''; ?>"><?php echo $lbl; ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="sb-cd-layout<?php echo $total_bookings ? '' : ' sb-cd-layout--full'; ?>">

            <!-- MAIN: bookings list -->
            <div>
            <div class="sb-cd-card">
                <div class="sb-cd-card-head">
                    <div class="sb-cd-card-title">My Bookings</div>
                    <?php if($total_bookings): ?><span style="font-size:.78rem;color:var(--cd-muted);font-weight:600;"><?php echo $total_bookings; ?> total</span><?php endif; ?>
                </div>

                <?php if ( empty($bookings) ): ?>
                <div class="sb-cd-empty">
                    <div class="sb-cd-empty-icon">📅</div>
                    <p>You don't have any bookings yet.<br><small>Once you book a service, it will appear here.</small></p>
                    <a href="<?php echo esc_url($booking_url); ?>" class="sb-btn sb-btn-primary" style="display:inline-flex;text-decoration:none;">＋ Make a Booking</a>
                </div>
                <?php else: ?>

                <!-- Filter tabs -->
                <div class="sb-cd-tabs">
                    <button class="sb-cd-tab active" data-filter="all">All (<?php echo $total_bookings; ?>)</button>
                    <?php if ($upcoming_count): ?><button class="sb-cd-tab" data-filter="upcoming">Upcoming (<?php echo $upcoming_count; ?>)</button><?php endif; ?>
                    <?php if ($completed): ?><button class="sb-cd-tab" data-filter="completed">Completed (<?php echo $completed; ?>)</button><?php endif; ?>
                    <?php if ($cancelled): ?><button class="sb-cd-tab" data-filter="cancelled">Cancelled (<?php echo $cancelled; ?>)</button><?php endif; ?>
                </div>

                <div class="sb-cd-booking-list" id="sbCdList">
                <?php foreach ( $bookings as $b ):
                    $is_past   = $b->booking_date < $today || in_array($b->status, ['completed','cancelled']);
                    $is_future = in_array($b->status, ['pending','confirmed','awaiting_deposit']) && $b->booking_date >= $today;
                    $status_lbl = $status_map[$b->status] ?? ucfirst(str_replace('_',' ',$b->status));
                    $pay_lbl    = $pay_map[$b->payment_status] ?? ucfirst($b->payment_status);
                    $svc_color  = $b->service_color ?: $brand_p;
                    $num_days   = intval($b->num_days ?: 1);
                    $filter_tag = $is_future ? 'upcoming' : $b->status;
                ?>
                <div class="sb-cd-booking<?php echo $is_past ? ' past' : ''; ?>"
                     data-status="<?php echo esc_attr($b->status); ?>"
                     data-future="<?php echo $is_future ? '1' : '0'; ?>">
                    <div class="sb-cd-booking-stripe" style="background:<?php echo esc_attr($svc_color); ?>"></div>
                    <div class="sb-cd-booking-body">
                        <div class="sb-cd-booking-top">
                            <span class="sb-cd-booking-svc"><?php echo esc_html($b->service_name ?? '—'); ?></span>
                            <span class="sb-cd-status sb-cd-status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html($status_lbl); ?></span>
                        </div>
                        <div class="sb-cd-booking-meta">
                            <span>📅 <?php echo date('D, d M Y', strtotime($b->booking_date)); ?></span>
                            <span>🕐 <?php echo date('g:i A', strtotime($b->start_time)); ?></span>
                            <?php if ($num_days > 1): ?><span>⏱ <?php echo $num_days; ?> days</span><?php endif; ?>
                            <?php if ($b->staff_name): ?><span>👤 <?php echo esc_html($b->staff_name); ?></span><?php endif; ?>
                        </div>
                        <div class="sb-cd-booking-fin">
                            <span class="sb-cd-booking-price"><?php echo number_format((float)$b->total_price); ?> <?php echo esc_html($cur); ?></span>
                            <?php if ((float)$b->deposit_amount > 0): ?>
                            <span class="sb-cd-pay-badge sb-cd-pay-<?php echo esc_attr($b->payment_status); ?>"><?php echo esc_html($pay_lbl); ?></span>
                            <?php if ($b->payment_ref): ?><span class="sb-cd-ref">Ref: <?php echo esc_html($b->payment_ref); ?></span><?php endif; ?>
                            <?php endif; ?>
                            <?php if ((float)$b->balance_amount > 0 && $b->payment_status !== 'paid_in_full' && !in_array($b->status, ['cancelled','completed'])): ?>
                            <span class="sb-cd-balance-due">⚠ Balance: <?php echo number_format((float)$b->balance_amount); ?> <?php echo esc_html($cur); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($b->notes): ?>
                        <div class="sb-cd-booking-notes">📝 <?php echo esc_html($b->notes); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div><!-- /.sb-cd-booking-list -->

                <?php endif; ?>
            </div><!-- /.card -->
            </div><!-- /.main col -->

            <!-- ASIDE: next booking + balance summary — only if has bookings -->
            <?php if ( $total_bookings > 0 ): ?>
            <div>

                <?php if ( $next_booking ): ?>
                <div class="sb-cd-next-card">
                    <div class="sb-cd-next-label">Next Booking</div>
                    <div class="sb-cd-next-svc"><?php echo esc_html($next_booking->service_name ?? '—'); ?></div>
                    <div class="sb-cd-next-row">📅 <?php echo date('D, d M Y', strtotime($next_booking->booking_date)); ?></div>
                    <div class="sb-cd-next-row">🕐 <?php echo date('g:i A', strtotime($next_booking->start_time)); ?></div>
                    <?php if ($next_booking->staff_name): ?>
                    <div class="sb-cd-next-row">👤 <?php echo esc_html($next_booking->staff_name); ?></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="sb-cd-sidebar-card" style="margin-bottom:16px;">
                    <div class="sb-cd-sidebar-body" style="text-align:center;padding:24px 16px;">
                        <div style="font-size:2rem;margin-bottom:8px;">🗓</div>
                        <div style="font-size:.84rem;color:var(--cd-muted);margin-bottom:12px;">No upcoming bookings</div>
                        <a href="<?php echo esc_url($booking_url); ?>" class="sb-btn sb-btn-primary" style="display:inline-flex;text-decoration:none;font-size:.82rem;padding:.55rem 1.1rem;">＋ Book Now</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $deposits_paid > 0 || $outstanding > 0 ): ?>
                <div class="sb-cd-balance-card">
                    <div class="sb-cd-balance-label">💳 Payment Summary</div>
                    <?php if ($deposits_paid > 0): ?>
                    <div class="sb-cd-balance-row"><span>Deposits Paid</span><strong><?php echo number_format($deposits_paid); ?> <?php echo esc_html($cur); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($outstanding > 0): ?>
                    <div class="sb-cd-balance-row"><span>Balance Outstanding</span><strong><?php echo number_format($outstanding); ?> <?php echo esc_html($cur); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($total_spent > 0): ?>
                    <div class="sb-cd-balance-row" style="margin-top:6px;padding-top:6px;border-top:1px solid #fde68a;"><span>Total Spent</span><strong><?php echo number_format($total_spent); ?> <?php echo esc_html($cur); ?></strong></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="sb-cd-sidebar-card">
                    <div class="sb-cd-sidebar-head"><div class="sb-cd-sidebar-title">Quick Actions</div></div>
                    <div class="sb-cd-sidebar-body" style="display:flex;flex-direction:column;gap:8px;">
                        <a href="<?php echo esc_url($booking_url); ?>" class="sb-btn sb-btn-primary" style="display:flex;text-decoration:none;">📅 &nbsp;New Booking</a>
                        <?php
                        $cur_url  = remove_query_arg('sb_view');
                        $sett_url = add_query_arg('sb_view', 'settings', $cur_url);
                        ?>
                        <a href="<?php echo esc_url($sett_url); ?>" class="sb-btn sb-btn-back" style="display:flex;text-decoration:none;">⚙ &nbsp;Edit Profile</a>
                    </div>
                </div>

            </div><!-- /.aside col -->
            <?php endif; ?>

        </div><!-- /.sb-cd-layout -->
        </div><!-- /.sb-cd -->

        <script>
        (function($){
            // Logout
            $('#sbPortalLogout').on('click', function(e){
                e.preventDefault();
                $.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, {
                    action: 'sb_portal_logout',
                    nonce:  <?php echo json_encode(wp_create_nonce('sb_portal')); ?>
                }, function(){ window.location.reload(); });
            });

            // Filter tabs
            $(document).on('click', '.sb-cd-tab', function(){
                var filter = $(this).data('filter');
                $('.sb-cd-tab').removeClass('active');
                $(this).addClass('active');
                $('#sbCdList .sb-cd-booking').each(function(){
                    var $b = $(this), status = $b.data('status'), isFuture = $b.data('future') == '1';
                    if (filter === 'all') { $b.removeClass('hidden'); }
                    else if (filter === 'upcoming') { $b.toggleClass('hidden', !isFuture); }
                    else { $b.toggleClass('hidden', status !== filter); }
                });
                // Show message if no results
                var visible = $('#sbCdList .sb-cd-booking:not(.hidden)').length;
                $('#sbCdNoResults').remove();
                if (!visible) {
                    $('#sbCdList').after('<p id="sbCdNoResults" style="text-align:center;color:#64748b;padding:20px;font-size:.88rem;">No bookings in this category.</p>');
                }
            });
        })(jQuery);
        </script>
        <?php
        self::wrap_close();
    }

    // ── SETTINGS / PROFILE ───────────────────────────────────────────
    private static function render_settings( $cid ) {
        global $wpdb;
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sb_customers WHERE id=%d", $cid
        ) );
        if ( ! $customer ) { self::destroy_session(); wp_redirect( get_permalink() ); exit; }

        $has_pw   = self::has_password( $cid );
        self::wrap_open( 'Profile Settings' );
        ?>
        <div class="sb-portal-settings">
            <h2 class="sb-portal-title">My Profile</h2>
            <div id="sbPortalMsg"></div>

            <!-- Personal Info -->
            <div class="sb-portal-settings-card">
                <div class="sb-portal-settings-card-head">Personal Information</div>
                <div class="sb-portal-form">
                    <div class="sb-portal-row2">
                        <div class="sb-portal-field">
                            <label>Full Name</label>
                            <input type="text" id="sbSName" value="<?php echo esc_attr($customer->name); ?>" placeholder="Your name">
                        </div>
                        <div class="sb-portal-field">
                            <label>Email Address</label>
                            <input type="email" id="sbSEmail" value="<?php echo esc_attr($customer->email); ?>" placeholder="you@example.com">
                        </div>
                    </div>
                    <div class="sb-portal-field" style="max-width:300px">
                        <label>Phone Number</label>
                        <input type="tel" id="sbSPhone" value="<?php echo esc_attr($customer->phone); ?>" placeholder="e.g. 237678899434">
                    </div>
                    <button class="sb-btn sb-btn-primary" id="sbSaveProfileBtn" style="width:auto;padding:10px 28px">Save Changes</button>
                </div>
            </div>

            <!-- Password -->
            <div class="sb-portal-settings-card">
                <div class="sb-portal-settings-card-head"><?php echo $has_pw ? 'Change Password' : 'Set a Password'; ?></div>
                <?php if ( ! $has_pw ): ?>
                <p class="sb-portal-help">You don't have a password yet. Set one to log in with your email and password.</p>
                <?php endif; ?>
                <div class="sb-portal-form">
                    <?php if ( $has_pw ): ?>
                    <div class="sb-portal-field">
                        <label>Current Password</label>
                        <div class="sb-portal-pw-wrap">
                            <input type="password" id="sbSCurPw" placeholder="Current password">
                            <button type="button" class="sb-portal-pw-toggle" data-target="sbSCurPw">👁</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="sb-portal-row2">
                        <div class="sb-portal-field">
                            <label>New Password</label>
                            <div class="sb-portal-pw-wrap">
                                <input type="password" id="sbSNewPw" placeholder="Min 6 characters">
                                <button type="button" class="sb-portal-pw-toggle" data-target="sbSNewPw">👁</button>
                            </div>
                        </div>
                        <div class="sb-portal-field">
                            <label>Confirm New Password</label>
                            <input type="password" id="sbSNewPw2" placeholder="Repeat password">
                        </div>
                    </div>
                    <button class="sb-btn sb-btn-primary" id="sbSavePwBtn" style="width:auto;padding:10px 28px"><?php echo $has_pw ? 'Update Password' : 'Set Password'; ?></button>
                </div>
            </div>

            <!-- Stats (read-only) -->
            <div class="sb-portal-settings-card">
                <div class="sb-portal-settings-card-head">Account Summary</div>
                <div class="sb-portal-stats-grid">
                    <div><span class="sb-portal-stat-val"><?php echo intval($customer->total_bookings); ?></span><span class="sb-portal-stat-lbl">Total Bookings</span></div>
                    <div><span class="sb-portal-stat-val"><?php echo number_format((float)$customer->total_spent); ?></span><span class="sb-portal-stat-lbl">Total Spent (<?php echo esc_html(get_option('sb_currency','FCFA')); ?>)</span></div>
                    <div><span class="sb-portal-stat-val"><?php echo date('M Y', strtotime($customer->created_at)); ?></span><span class="sb-portal-stat-lbl">Member Since</span></div>
                </div>
            </div>
        </div>

        <script>
        (function($){
            // Show/hide password
            $(document).on('click','.sb-portal-pw-toggle',function(){var t=$('#'+$(this).data('target'));t.attr('type',t.attr('type')==='password'?'text':'password');});

            // Logout
            $('#sbPortalLogout').on('click',function(e){e.preventDefault();$.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>,{action:'sb_portal_logout',nonce:<?php echo json_encode(wp_create_nonce('sb_portal')); ?>},function(){window.location.href=<?php echo json_encode(remove_query_arg('sb_view')); ?>;});});

            // Save profile
            $('#sbSaveProfileBtn').on('click',function(){
                var data={action:'sb_portal_save_profile',nonce:<?php echo json_encode(wp_create_nonce('sb_portal')); ?>,
                    save:'info',name:$('#sbSName').val().trim(),email:$('#sbSEmail').val().trim(),phone:$('#sbSPhone').val().trim()};
                if(!data.name||!data.email){sbPMsg('Name and email are required.','error');return;}
                $(this).prop('disabled',true).text('Saving…');
                var $b=$(this);
                $.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>,data,function(r){
                    $b.prop('disabled',false).text('Save Changes');
                    sbPMsg(r.data.msg||(r.success?'Saved!':'Failed.'),r.success?'ok':'error');
                });
            });

            // Save password
            $('#sbSavePwBtn').on('click',function(){
                var cur=$('#sbSCurPw').val()||'',np=$('#sbSNewPw').val(),np2=$('#sbSNewPw2').val();
                if(np.length<6){sbPMsg('Password must be at least 6 characters.','error');return;}
                if(np!==np2){sbPMsg('Passwords do not match.','error');return;}
                $(this).prop('disabled',true).text('Saving…');
                var $b=$(this);
                $.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>,{
                    action:'sb_portal_save_profile',nonce:<?php echo json_encode(wp_create_nonce('sb_portal')); ?>,
                    save:'password',current_pw:cur,new_pw:np
                },function(r){
                    $b.prop('disabled',false).text(<?php echo $has_pw?'"Update Password"':'"Set Password"'; ?>);
                    sbPMsg(r.data.msg||(r.success?'Password updated!':'Failed.'),r.success?'ok':'error');
                    if(r.success){$('#sbSCurPw,#sbSNewPw,#sbSNewPw2').val('');}
                });
            });
        })(jQuery);
        function sbPMsg(msg,type){var $m=$('#sbPortalMsg');$m.removeClass('sb-portal-msg-error sb-portal-msg-ok').addClass('sb-portal-msg-'+(type||'ok')).html(msg).show();}
        </script>
        <?php
        self::wrap_close();
    }

    // ── AJAX: REQUEST ACCESS (existing customers who booked before portal existed) ──
    public static function ajax_request_access() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_portal' ) ) wp_send_json_error( ['msg'=>'Security error.'] );
        global $wpdb;
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! $email || ! is_email( $email ) ) wp_send_json_error( ['msg'=>'Please enter a valid email address.'] );

        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sb_customers WHERE email=%s", $email
        ) );

        // Always respond the same to avoid email enumeration
        if ( $customer ) {
            $page_id  = get_option( 'sb_portal_page_id', 0 );
            $page_url = $page_id ? get_permalink( $page_id ) : home_url('/');

            // Generate a fresh temp password (overwrite old one if any — this is the "forgot" path too)
            $temp_pw = strtoupper( substr( md5( uniqid('', true) ), 0, 4 ) ) . rand( 10, 99 );
            self::set_pw_option( $customer->id, $temp_pw );

            $biz     = get_option('sb_settings',[])['business_name'] ?? get_bloginfo('name');
            $subject = "Your dashboard access — {$biz}";
            $message  = "Hi {$customer->name},\n\n";
            $message .= "Here are your login details for your booking dashboard:\n\n";
            $message .= "──────────────────────────────\n";
            $message .= "  Portal:    {$page_url}\n";
            $message .= "  Email:     {$email}\n";
            $message .= "  Password:  {$temp_pw}\n";
            $message .= "──────────────────────────────\n\n";
            $message .= "All your past and future bookings with {$biz} will be visible once you log in.\n\n";
            $message .= "You can change this password from your Profile page after logging in.\n\n";
            $message .= "— {$biz}";
            wp_mail( $email, $subject, $message );
        }

        wp_send_json_success( ['msg'=>'If that email matches a booking, you\'ll receive your login details shortly. Check your inbox (and spam folder).'] );
    }

    // ── AJAX: LOGIN ──────────────────────────────────────────────────
    public static function ajax_login() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_portal' ) ) wp_send_json_error( ['msg'=>'Security error.'] );
        global $wpdb;
        $email = sanitize_email( $_POST['email'] ?? '' );
        $pw    = $_POST['password'] ?? '';
        if ( ! $email || ! $pw ) wp_send_json_error( ['msg'=>'Please fill in all fields.'] );

        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sb_customers WHERE email=%s", $email
        ) );
        if ( ! $customer ) wp_send_json_error( ['msg'=>'No account found with that email.'] );

        if ( ! self::has_password( $customer->id ) ) {
            wp_send_json_error( ['msg'=>'You haven\'t set a password yet. Use <a href="' . esc_url(add_query_arg('sb_view','forgot',get_permalink())) . '">Forgot password</a> to create one.'] );
        }
        if ( ! self::check_password( $customer->id, $pw ) ) {
            wp_send_json_error( ['msg'=>'Incorrect password.'] );
        }

        self::set_session( $customer->id );
        $redirect = add_query_arg( 'sb_view', 'dashboard', self::get_portal_url() );
        wp_send_json_success( [ 'msg' => 'Welcome back!', 'redirect' => $redirect ] );
    }

    // ── AJAX: REGISTER ───────────────────────────────────────────────
    public static function ajax_register() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_portal' ) ) wp_send_json_error( ['msg'=>'Security error.'] );
        global $wpdb;

        $name  = sanitize_text_field( $_POST['name']     ?? '' );
        $email = sanitize_email(      $_POST['email']    ?? '' );
        $phone = sanitize_text_field( $_POST['phone']    ?? '' );
        $pw    =                      $_POST['password'] ?? '';

        if ( ! $name || ! $email )  wp_send_json_error( ['msg'=>'Name and email are required.'] );
        if ( ! is_email( $email ) ) wp_send_json_error( ['msg'=>'Please enter a valid email address.'] );
        if ( strlen( $pw ) < 6 )    wp_send_json_error( ['msg'=>'Password must be at least 8 characters.'] );

        // Check if email already taken
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sb_customers WHERE email=%s", $email
        ) );
        if ( $existing ) {
            $login_url = self::get_portal_url();
            wp_send_json_error( ['msg' => 'An account with that email already exists. <a href="' . esc_url( $login_url ) . '">Sign in instead</a>.'] );
        }

        // Create customer
        $wpdb->insert( $wpdb->prefix . 'sb_customers', [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
        ] );
        $cid = $wpdb->insert_id;
        if ( ! $cid ) wp_send_json_error( ['msg'=>'Could not create account. Please try again.'] );

        // Set password and log in
        self::set_pw_option( $cid, $pw );
        self::set_session( $cid );

        $redirect = add_query_arg( 'sb_view', 'dashboard', self::get_portal_url() );
        wp_send_json_success( [ 'msg' => 'Account created! Welcome aboard ', 'redirect' => $redirect ] );
    }

    // ── AJAX: FORGOT ─────────────────────────────────────────────────
    public static function ajax_forgot() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_portal' ) ) wp_send_json_error( ['msg'=>'Security error.'] );
        global $wpdb;
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! $email ) wp_send_json_error( ['msg'=>'Please enter your email.'] );

        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sb_customers WHERE email=%s", $email
        ) );
        // Always say "check your email" to avoid exposing whether email exists
        if ( $customer ) {
            $token    = self::make_reset_token( $customer->id );
            $page_url = get_permalink( get_option('sb_portal_page_id') ?: null ) ?: home_url();
            $link     = add_query_arg( ['sb_reset'=>$token,'sb_rc'=>$customer->id], $page_url );
            $biz      = get_option('sb_settings',[])['business_name'] ?? get_bloginfo('name');
            $subject  = 'Reset your password — ' . $biz;
            $message  = "Hi " . $customer->name . ",\n\n";
            $message .= "You requested a password reset. Click the link below to set a new password:\n\n";
            $message .= $link . "\n\n";
            $message .= "This link expires in 30 minutes. If you did not request this, you can ignore this email.\n\n";
            $message .= "— " . $biz;
            wp_mail( $email, $subject, $message );
        }
        wp_send_json_success( ['msg'=>'If that email matches an account, you\'ll receive a reset link shortly. Check your inbox (and spam).'] );
    }

    // ── AJAX: RESET PASSWORD ─────────────────────────────────────────
    public static function ajax_reset() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_portal' ) ) wp_send_json_error( ['msg'=>'Security error.'] );
        $cid   = intval( $_POST['cid'] ?? 0 );
        $token = sanitize_text_field( $_POST['token'] ?? '' );
        $pw    = $_POST['password'] ?? '';
        if ( ! $cid || ! $token ) wp_send_json_error( ['msg'=>'Invalid request.'] );
        if ( strlen($pw) < 6 )     wp_send_json_error( ['msg'=>'Password must be at least 6 characters.'] );
        if ( ! self::verify_reset_token( $cid, $token ) ) wp_send_json_error( ['msg'=>'This link is invalid or has expired.'] );

        self::consume_reset_token( $cid );
        self::set_pw_option( $cid, $pw );
        self::set_session( $cid );
        wp_send_json_success( ['msg'=>'Password set successfully!'] );
    }

    // ── AJAX: LOGOUT ─────────────────────────────────────────────────
    public static function ajax_logout() {
        self::destroy_session();
        wp_send_json_success();
    }

    // ── AJAX: SAVE PROFILE ───────────────────────────────────────────
    public static function ajax_save_profile() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_portal' ) ) wp_send_json_error( ['msg'=>'Security error.'] );
        $cid = self::get_logged_in_cid();
        if ( ! $cid ) wp_send_json_error( ['msg'=>'Not logged in.'] );

        global $wpdb;
        $save = sanitize_key( $_POST['save'] ?? 'info' );

        if ( $save === 'info' ) {
            $name  = sanitize_text_field( $_POST['name']  ?? '' );
            $email = sanitize_email( $_POST['email'] ?? '' );
            $phone = preg_replace( '/[^0-9+]/', '', $_POST['phone'] ?? '' );
            if ( ! $name || ! $email ) wp_send_json_error( ['msg'=>'Name and email are required.'] );
            // Ensure email not taken by another customer
            $conflict = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sb_customers WHERE email=%s AND id!=%d", $email, $cid
            ) );
            if ( $conflict ) wp_send_json_error( ['msg'=>'That email is already linked to another account.'] );
            $wpdb->update( $wpdb->prefix . 'sb_customers', compact('name','email','phone'), ['id'=>$cid] );
            wp_send_json_success( ['msg'=>'Profile updated.'] );
        }

        if ( $save === 'password' ) {
            $has_pw  = self::has_password( $cid );
            $cur_pw  = $_POST['current_pw'] ?? '';
            $new_pw  = $_POST['new_pw'] ?? '';
            if ( strlen($new_pw) < 6 ) wp_send_json_error( ['msg'=>'Password must be at least 6 characters.'] );
            if ( $has_pw && ! self::check_password( $cid, $cur_pw ) ) {
                wp_send_json_error( ['msg'=>'Current password is incorrect.'] );
            }
            self::set_pw_option( $cid, $new_pw );
            wp_send_json_success( ['msg'=>'Password updated successfully.'] );
        }

        wp_send_json_error( ['msg'=>'Unknown action.'] );
    }

    // ── SEND WELCOME / FIRST LOGIN EMAIL (called from booking submission) ──
    public static function send_portal_invite( $customer_id, $email, $name ) {
        if ( ! $email ) return;
        $page_id  = get_option( 'sb_portal_page_id', 0 );
        if ( ! $page_id ) return;
        $page_url = get_permalink( $page_id );
        if ( ! $page_url ) return;

        // Only send if customer has no password yet (first booking)
        if ( self::has_password( $customer_id ) ) return;

        // Generate a readable temporary password and set it immediately
        $temp_pw  = strtoupper( substr( md5( uniqid( '', true ) ), 0, 4 ) ) . rand( 10, 99 );
        self::set_pw_option( $customer_id, $temp_pw );

        $biz = get_option('sb_settings',[])['business_name'] ?? get_bloginfo('name');

        $subject = "Your booking is confirmed — your dashboard is ready";
        $message  = "Hi {$name},\n\n";
        $message .= "Your booking with {$biz} is confirmed! 🎉\n\n";
        $message .= "We've created your personal dashboard so you can track your bookings, check payment status, and manage your profile.\n\n";
        $message .= "──────────────────────────────\n";
        $message .= "YOUR DASHBOARD LOGIN\n";
        $message .= "──────────────────────────────\n";
        $message .= "  Portal:    {$page_url}\n";
        $message .= "  Email:     {$email}\n";
        $message .= "  Password:  {$temp_pw}\n";
        $message .= "──────────────────────────────\n\n";
        $message .= "👉 We recommend changing your password after your first login\n";
        $message .= "   (Profile → Change Password).\n\n";
        $message .= "From your dashboard you can:\n";
        $message .= "  • View all your bookings and their status\n";
        $message .= "  • See upcoming appointments\n";
        $message .= "  • Check payment balances\n";
        $message .= "  • Update your profile\n\n";
        $message .= "— {$biz}";
        wp_mail( $email, $subject, $message );
    }
}
