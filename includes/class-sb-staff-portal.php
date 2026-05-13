<?php
/**
 * SmartBooking — Staff Frontend Portal
 * Shortcode: [sb_staff_portal]  (auto-routes: login / dashboard / schedule / profile)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Staff_Portal {

    const SESSION_KEY = 'sb_staff_portal_sid'; // staff id in session

    // ── BOOT ────────────────────────────────────────────────────────
    public static function init() {
        add_shortcode( 'sb_staff_portal', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_ajax_nopriv_sb_staff_login',        array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_sb_staff_login',               array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_sb_staff_logout',              array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_nopriv_sb_staff_logout',       array( __CLASS__, 'ajax_logout' ) );
        add_action( 'wp_ajax_sb_staff_save_profile',        array( __CLASS__, 'ajax_save_profile' ) );
        add_action( 'wp_ajax_nopriv_sb_staff_save_profile', array( __CLASS__, 'ajax_save_profile' ) );
        add_action( 'init', array( __CLASS__, 'start_session' ), 1 );
    }

    public static function start_session() {
        if ( ! session_id() ) session_start();
    }

    // ── SESSION ──────────────────────────────────────────────────────
    private static function get_logged_in_sid() {
        return isset( $_SESSION[ self::SESSION_KEY ] ) ? intval( $_SESSION[ self::SESSION_KEY ] ) : 0;
    }
    private static function set_session( $sid )   { $_SESSION[ self::SESSION_KEY ] = intval( $sid ); }
    private static function destroy_session()      { unset( $_SESSION[ self::SESSION_KEY ] ); }

    // ── PASSWORD (same wp_options pattern as customer portal) ────────
    private static function get_pw( $sid )              { return get_option( 'sb_spw_' . intval($sid), '' ); }
    private static function set_pw( $sid, $plain )      { update_option( 'sb_spw_' . intval($sid), wp_hash_password($plain), false ); }
    private static function has_pw( $sid )              { return (bool) self::get_pw($sid); }
    private static function check_pw( $sid, $plain )    { $h = self::get_pw($sid); return $h && wp_check_password($plain, $h); }

    private static function get_portal_url() {
        global $wpdb;
        $page = $wpdb->get_row("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_content LIKE '%[sb_staff_portal]%' LIMIT 1");
        return $page ? get_permalink($page->ID) : home_url('/');
    }

    // ── BRAND COLORS ─────────────────────────────────────────────────
    private static function brand() {
        $s  = get_option('sb_appearance', []);
        return [
            'p'  => sanitize_hex_color($s['primary'] ?? '#111111') ?: '#111111',
            'pd' => sanitize_hex_color($s['p_dark']  ?? '#3b1a5c') ?: '#3b1a5c',
            'ac' => sanitize_hex_color($s['accent']  ?? '#C9A84C') ?: '#C9A84C',
        ];
    }

    // ── SHORTCODE ROUTER ─────────────────────────────────────────────
    public static function shortcode( $atts ) {
        ob_start();

        // Admins can view the staff portal normally — no redirect or banner shown.

        $sid  = self::get_logged_in_sid();

        // Admin with ?sb_preview_staff=N can preview any staff member's dashboard.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) && isset( $_GET['sb_preview_staff'] ) ) {
            $preview_sid = intval( $_GET['sb_preview_staff'] );
            if ( $preview_sid > 0 ) {
                $sid = $preview_sid;
            }
        }

        $view = sanitize_key( $_GET['sb_view'] ?? ( $sid ? 'dashboard' : 'login' ) );

        // Admin without a staff session: show a staff-picker preview instead of the login form.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) && ! $sid ) {
            self::render_admin_preview_picker();
            return ob_get_clean();
        }

        if ( ! $sid ) {
            self::render_login();
        } elseif ( $view === 'schedule' ) {
            self::render_schedule( $sid );
        } elseif ( $view === 'profile' ) {
            self::render_profile( $sid );
        } else {
            self::render_dashboard( $sid );
        }
        return ob_get_clean();
    }

    // ── ADMIN PREVIEW PICKER ─────────────────────────────────────────
    // Shown to WP admins who visit [sb_staff_portal] without a staff session.
    // Lets them pick any staff member to preview their dashboard.
    private static function render_admin_preview_picker() {
        global $wpdb;
        $b     = self::brand();
        $staff = $wpdb->get_results(
            "SELECT id, name, email, bio FROM {$wpdb->prefix}sb_staff WHERE status='active' ORDER BY name ASC"
        );
        $portal_url = self::get_portal_url();
        ?>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        .sb-ap-wrap {
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
            --ap-p: <?php echo esc_attr( $b['p'] ); ?>;
            --ap-pd: <?php echo esc_attr( $b['pd'] ); ?>;
        }
        .sb-ap-header {
            background: linear-gradient(120deg, var(--ap-pd) 0%, var(--ap-p) 60%, #7c3aed 100%);
            padding: 22px 32px; margin-bottom: 24px; border-radius: 0;
            box-shadow: 0 6px 24px rgba(91,45,142,.3);
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
            position: relative; overflow: hidden;
        }
        .sb-ap-header::after { content:''; position:absolute; top:-40%; right:-5%; width:200px; height:200px; background:rgba(255,255,255,.06); border-radius:50%; pointer-events:none; }
        .sb-ap-header h2 { margin:0; font-size:1.2rem; font-weight:800; color:#fff; letter-spacing:-.02em; z-index:1; }
        .sb-ap-header p  { margin:4px 0 0; font-size:.82rem; color:rgba(255,255,255,.72); z-index:1; }
        .sb-ap-header-left { z-index: 1; }
        .sb-ap-admin-link { z-index:1; padding:7px 16px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.35); border-radius:8px; color:#fff; font-size:.8rem; font-weight:700; text-decoration:none; transition:background .15s; }
        .sb-ap-admin-link:hover { background:rgba(255,255,255,.3); color:#fff; }
        .sb-ap-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; padding: 0 32px; }
        .sb-ap-card {
            background:#fff; border:1px solid #ebe8f4; border-radius:14px;
            padding:20px 18px; cursor:pointer; text-decoration:none; display:block;
            box-shadow:0 1px 4px rgba(91,45,142,.06),0 4px 16px rgba(91,45,142,.06);
            transition:transform .18s,box-shadow .18s,border-color .18s;
        }
        .sb-ap-card:hover { transform:translateY(-3px); box-shadow:0 4px 20px rgba(91,45,142,.14); border-color:var(--ap-p); }
        .sb-ap-avatar {
            width:46px; height:46px; border-radius:50%;
            background:linear-gradient(135deg, var(--ap-pd), var(--ap-p));
            display:flex; align-items:center; justify-content:center;
            font-size:1.15rem; font-weight:800; color:#fff; margin-bottom:12px;
        }
        .sb-ap-name  { font-weight:800; font-size:.95rem; color:#18113a; margin-bottom:3px; }
        .sb-ap-spec  { font-size:.76rem; color:#8b91a7; font-weight:500; }
        .sb-ap-email { font-size:.73rem; color:#8b91a7; margin-top:4px; }
        .sb-ap-empty { text-align:center; padding:48px 20px; color:#8b91a7; }
        .sb-ap-empty p { font-size:.9rem; }
        </style>

        <div class="sb-ap-wrap">
            <div class="sb-ap-header">
                <div class="sb-ap-header-left">
                    <h2>🔍 Staff Portal — Admin Preview</h2>
                    <p>Select a staff member below to preview their dashboard</p>
                </div>
                <a href="<?php echo esc_url( admin_url('admin.php?page=smartbooking') ); ?>" class="sb-ap-admin-link">← Admin Dashboard</a>
            </div>

            <?php if ( empty($staff) ): ?>
                <div class="sb-ap-empty">
                    <p>No active staff found. <a href="<?php echo esc_url( admin_url('admin.php?page=sb-staff') ); ?>" style="color:<?php echo esc_attr($b['p']); ?>;font-weight:700;">Add staff →</a></p>
                </div>
            <?php else: ?>
                <div class="sb-ap-grid">
                <?php foreach ( $staff as $s ):
                    $initials = strtoupper( implode( '', array_map( fn($w) => $w[0], array_slice( explode(' ', $s->name), 0, 2 ) ) ) );
                    $preview_url = add_query_arg( [ 'view_as' => 'staff', 'sb_preview_staff' => $s->id ], $portal_url );
                ?>
                    <a class="sb-ap-card" href="<?php echo esc_url( $preview_url ); ?>">
                        <div class="sb-ap-avatar"><?php echo esc_html($initials); ?></div>
                        <div class="sb-ap-name"><?php echo esc_html($s->name); ?></div>
                        <?php if ( $s->bio ): ?>
                        <div class="sb-ap-spec"><?php echo esc_html( wp_trim_words($s->bio, 6, '…') ); ?></div>
                        <?php endif; ?>
                        <div class="sb-ap-email"><?php echo esc_html($s->email); ?></div>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── LOGIN PAGE ───────────────────────────────────────────────────
    private static function render_login() {
        $b    = self::brand();
        $ajax = admin_url('admin-ajax.php');
        $n    = wp_create_nonce('sb_staff_portal');
        $biz  = esc_html( get_option('sb_settings', [])['business_name'] ?? get_bloginfo('name') );
        ?>
        <style>
        .sb-staff-auth {
            min-height: 100vh;
            background: linear-gradient(135deg, <?php echo $b['pd']; ?> 0%, <?php echo $b['p']; ?> 60%, #7c3aed 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 48px 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .sb-staff-auth-card {
            background: #fff; border-radius: 16px; width: 100%; max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25); overflow: hidden;
        }
        .sb-staff-auth-head {
            background: linear-gradient(135deg, <?php echo $b['pd']; ?>, <?php echo $b['p']; ?>);
            padding: 28px 28px 24px; text-align: center; color: #fff;
        }
        .sb-staff-auth-icon {
            width: 56px; height: 56px; background: rgba(255,255,255,.15); border: 2px solid rgba(255,255,255,.3);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin: 0 auto 12px;
        }
        .sb-staff-auth-head h2 { margin: 0 0 4px; font-size: 1.15rem; font-weight: 700; }
        .sb-staff-auth-head p  { margin: 0; font-size: .82rem; color: rgba(255,255,255,.7); }
        .sb-staff-auth-body { padding: 28px; }
        .sb-staff-field { margin-bottom: 16px; }
        .sb-staff-field label { display: block; font-size: .78rem; font-weight: 700; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
        .sb-staff-field input {
            width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-size: .92rem; font-family: inherit; color: #1a1a2e; box-sizing: border-box;
            transition: border-color .15s; outline: none;
        }
        .sb-staff-field input:focus { border-color: <?php echo $b['p']; ?>; box-shadow: 0 0 0 3px <?php echo $b['p']; ?>18; }
        .sb-staff-login-btn {
            width: 100%; padding: 11px; background: <?php echo $b['p']; ?>; color: #fff;
            border: none; border-radius: 8px; font-size: .95rem; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: background .18s;
        }
        .sb-staff-login-btn:hover { background: <?php echo $b['pd']; ?>; }
        .sb-staff-login-btn:disabled { opacity: .65; cursor: not-allowed; }
        .sb-staff-msg { padding: 10px 14px; border-radius: 8px; font-size: .85rem; margin-bottom: 14px; display: none; }
        .sb-staff-msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        </style>

        <div class="sb-staff-auth">
            <div class="sb-staff-auth-card">
                <div class="sb-staff-auth-head">
                    <div class="sb-staff-auth-icon">👤</div>
                    <h2>Staff Portal</h2>
                    <p><?php echo $biz; ?></p>
                </div>
                <div class="sb-staff-auth-body">
                    <div class="sb-staff-msg" id="sbStaffMsg"></div>
                    <div class="sb-staff-field">
                        <label>Email Address</label>
                        <input type="email" id="sbStaffEmail" placeholder="your@email.com" autocomplete="email">
                    </div>
                    <div class="sb-staff-field">
                        <label>Password</label>
                        <input type="password" id="sbStaffPw" placeholder="••••••••" autocomplete="current-password">
                    </div>
                    <button class="sb-staff-login-btn" id="sbStaffLoginBtn">Sign In</button>
                </div>
            </div>
        </div>

        <script>
        (function($){
            var ajax = <?php echo json_encode($ajax); ?>, nonce = <?php echo json_encode($n); ?>;
            function msg(t, type){ var el=$('#sbStaffMsg'); el.removeClass('error').addClass(type||'error').text(t).show(); }
            function doLogin(){
                var email=$('#sbStaffEmail').val().trim(), pw=$('#sbStaffPw').val();
                if(!email||!pw){ msg('Please fill in all fields.'); return; }
                $('#sbStaffLoginBtn').prop('disabled',true).text('Signing in…');
                $.post(ajax,{action:'sb_staff_login',nonce:nonce,email:email,password:pw},function(r){
                    $('#sbStaffLoginBtn').prop('disabled',false).text('Sign In');
                    if(r.success){ window.location.href = r.data.redirect; }
                    else { msg(r.data.msg||'Login failed.'); }
                }).fail(function(){ $('#sbStaffLoginBtn').prop('disabled',false).text('Sign In'); msg('Connection error.'); });
            }
            $('#sbStaffLoginBtn').on('click', doLogin);
            $('#sbStaffPw').on('keydown', function(e){ if(e.key==='Enter') doLogin(); });
        })(jQuery);
        </script>
        <?php
    }

    // ── SHARED NAV WRAPPER ───────────────────────────────────────────
    private static function nav_open( $sid, $active = 'dashboard' ) {
        $b    = self::brand();
        $staff = self::get_staff( $sid );
        $biz  = esc_html( get_option('sb_settings', [])['business_name'] ?? get_bloginfo('name') );
        $url  = self::get_portal_url();
        $n    = wp_create_nonce('sb_staff_portal');
        ?>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        * { box-sizing: border-box; }
        /* ── Full-bleed breakout: escape the theme content container ── */
        .sb-sp {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            min-height: 100vh;
            background: #f8f6fd;
            position: relative;
            width: 100vw !important;
            max-width: 100vw !important;
            margin-left: calc(50% - 50vw) !important;
            margin-right: calc(50% - 50vw) !important;
            --sp-purple: <?php echo $b['p']; ?>;
            --sp-purple-dk: <?php echo $b['pd']; ?>;
            --sp-purple-lt: #f5f0ff;
            --sp-purple-mid: #ede0ff;
            --sp-border: #ebe8f4;
            --sp-card: #fff;
            --sp-text: #18113a;
            --sp-text2: #4b5563;
            --sp-muted: #8b91a7;
            --sp-shadow: 0 1px 4px rgba(91,45,142,.06), 0 4px 16px rgba(91,45,142,.06);
            --sp-shadow-md: 0 4px 20px rgba(91,45,142,.10), 0 1px 6px rgba(91,45,142,.06);
            --sp-radius: 14px;
        }
        .sb-sp-portal-active .entry-title,
        .sb-sp-portal-active .page-title,
        .sb-sp-portal-active h1.wp-block-post-title { display: none !important; }

        /* ── TOP NAV ── */
        .sb-sp-nav {
            background: linear-gradient(120deg, var(--sp-purple-dk) 0%, var(--sp-purple) 60%, #7c3aed 100%);
            color: #fff; display: flex; align-items: center; padding: 0 24px; height: 58px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 4px 16px rgba(91,45,142,.25);
        }
        .sb-sp-nav-brand {
            font-size: 1rem; font-weight: 800; color: #fff; text-decoration: none;
            margin-right: auto; display: flex; align-items: center; gap: 8px; letter-spacing: -.02em;
        }
        .sb-sp-nav-links { display: flex; gap: 2px; }
        .sb-sp-nav-link {
            padding: 6px 14px; border-radius: 8px; color: rgba(255,255,255,.75);
            text-decoration: none; font-size: .84rem; font-weight: 600; transition: all .15s;
        }
        .sb-sp-nav-link:hover, .sb-sp-nav-link.active { background: rgba(255,255,255,.18); color: #fff; }
        .sb-sp-nav-user { display: flex; align-items: center; gap: 10px; margin-left: 16px; }
        .sb-sp-nav-name { font-size: .82rem; font-weight: 600; color: rgba(255,255,255,.85); }
        .sb-sp-logout-btn {
            padding: 5px 14px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25);
            border-radius: 8px; color: rgba(255,255,255,.85); font-size: .78rem; font-weight: 700;
            cursor: pointer; font-family: inherit; transition: all .15s; letter-spacing: .01em;
        }
        .sb-sp-logout-btn:hover { background: rgba(255,255,255,.24); color: #fff; border-color: rgba(255,255,255,.4); }

        /* ── PAGE ── */
        .sb-sp-page { padding: 28px 32px; max-width: 1280px; margin: 0 auto; }

        /* ── PAGE HEADER ── */
        .sb-sp-page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; padding: 22px 28px;
            background: linear-gradient(120deg, var(--sp-purple) 0%, #7c3aed 60%, #4f46e5 100%);
            border-radius: var(--sp-radius); box-shadow: 0 6px 24px rgba(91,45,142,.3);
            position: relative; overflow: hidden;
        }
        .sb-sp-page-header::after {
            content:''; position:absolute; top:-40%; right:-5%; width:200px; height:200px;
            background:rgba(255,255,255,.06); border-radius:50%; pointer-events:none;
        }
        .sb-sp-page-title { font-size: 1.35rem; font-weight: 800; color: #fff; margin: 0; letter-spacing: -.025em; }
        .sb-sp-page-sub { color: rgba(255,255,255,.7); font-size: .84rem; margin: 4px 0 0; }

        /* ── STATS ── */
        .sb-sp-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); gap: 14px; margin-bottom: 24px; }
        .sb-sp-stat {
            background: var(--sp-card); border: 1px solid var(--sp-border); border-radius: var(--sp-radius);
            padding: 20px 18px; display: flex; align-items: center; gap: 14px;
            box-shadow: var(--sp-shadow); transition: all .18s; position: relative; overflow: hidden; cursor: default;
        }
        .sb-sp-stat::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius: var(--sp-radius) var(--sp-radius) 0 0; }
        .sb-sp-stat:hover { transform: translateY(-3px); box-shadow: var(--sp-shadow-md); }
        .sb-sp-stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; }
        .sb-sp-stat-val { font-size:1.35rem; font-weight:800; color:var(--sp-text); line-height:1; letter-spacing:-.03em; }
        .sb-sp-stat-lbl { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--sp-muted); margin-top:4px; display:block; }
        .sb-sp-stat-purple::before { background: var(--sp-purple); }
        .sb-sp-stat-purple .sb-sp-stat-icon { background: var(--sp-purple-lt); color: var(--sp-purple); }
        .sb-sp-stat-amber::before  { background: #d97706; }
        .sb-sp-stat-amber  .sb-sp-stat-icon { background: #fffbeb; color: #d97706; }
        .sb-sp-stat-green::before  { background: #16a34a; }
        .sb-sp-stat-green  .sb-sp-stat-icon { background: #f0fdf4; color: #16a34a; }
        .sb-sp-stat-blue::before   { background: #2563eb; }
        .sb-sp-stat-blue   .sb-sp-stat-icon { background: #eff6ff; color: #2563eb; }

        /* ── CARDS ── */
        .sb-sp-card { background:var(--sp-card); border:1px solid var(--sp-border); border-radius:var(--sp-radius); margin-bottom:20px; overflow:hidden; box-shadow:var(--sp-shadow); }
        .sb-sp-card-head { padding:16px 22px; border-bottom:1px solid var(--sp-border); display:flex; align-items:center; justify-content:space-between; }
        .sb-sp-card-title { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--sp-purple); margin:0; }
        .sb-sp-card-body { padding:0; }

        /* ── BOOKING ROWS ── */
        .sb-sp-booking {
            display:flex; align-items:center; gap:14px; padding:14px 22px;
            border-bottom:1px solid var(--sp-border); transition:background .12s;
        }
        .sb-sp-booking:last-child { border-bottom:none; }
        .sb-sp-booking:hover { background:var(--sp-purple-lt); }
        .sb-sp-booking-stripe { width:3px; height:38px; border-radius:3px; flex-shrink:0; }
        .sb-sp-booking-main { flex:1; min-width:0; }
        .sb-sp-booking-client { font-weight:700; font-size:.9rem; color:var(--sp-text); }
        .sb-sp-booking-svc { font-size:.78rem; color:var(--sp-text2); margin-top:2px; }
        .sb-sp-booking-meta { display:flex; gap:12px; font-size:.75rem; color:var(--sp-muted); margin-top:4px; flex-wrap:wrap; }
        .sb-sp-booking-price { font-weight:800; font-size:.88rem; color:var(--sp-text); flex-shrink:0; }

        /* ── BADGES ── */
        .sb-sp-badge { font-size:.65rem; font-weight:700; padding:3px 9px; border-radius:100px; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; flex-shrink:0; }
        .sb-sp-badge-confirmed  { background:#dcfce7; color:#166534; }
        .sb-sp-badge-pending    { background:#fef3c7; color:#92400e; }
        .sb-sp-badge-completed  { background:#dbeafe; color:#1e40af; }
        .sb-sp-badge-cancelled  { background:#fee2e2; color:#991b1b; }
        .sb-sp-badge-awaiting   { background:var(--sp-purple-mid); color:var(--sp-purple); }

        /* ── TODAY PILL ── */
        .sb-sp-today-pill { display:inline-flex; align-items:center; gap:6px; background:var(--sp-purple-lt); color:var(--sp-purple); padding:4px 12px; border-radius:100px; font-size:.76rem; font-weight:700; border:1px solid var(--sp-purple-mid); }

        /* ── SCHEDULE TIMELINE ── */
        .sb-sp-timeline { padding:0; }
        .sb-sp-timeline-item { display:flex; gap:16px; padding:14px 22px; border-bottom:1px solid var(--sp-border); align-items:flex-start; transition:background .12s; }
        .sb-sp-timeline-item:last-child { border-bottom:none; }
        .sb-sp-timeline-item:hover { background:var(--sp-purple-lt); }
        .sb-sp-timeline-time { width:70px; flex-shrink:0; text-align:right; }
        .sb-sp-timeline-time-main { font-size:.88rem; font-weight:800; color:var(--sp-purple); }
        .sb-sp-timeline-time-end  { font-size:.72rem; color:var(--sp-muted); }
        .sb-sp-timeline-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:5px; }
        .sb-sp-timeline-body { flex:1; }
        .sb-sp-timeline-client { font-weight:700; font-size:.9rem; color:var(--sp-text); }
        .sb-sp-timeline-svc { font-size:.78rem; color:var(--sp-text2); margin-top:1px; }
        .sb-sp-timeline-phone { font-size:.75rem; color:var(--sp-muted); margin-top:4px; }

        /* ── EMPTY ── */
        .sb-sp-empty { text-align:center; padding:48px 20px; color:var(--sp-muted); }
        .sb-sp-empty-icon { font-size:2.5rem; margin-bottom:10px; }
        .sb-sp-empty p { margin:0; font-size:.88rem; }

        /* ── PROFILE FORM ── */
        .sb-sp-form { padding:24px; }
        .sb-sp-form-group { margin-bottom:18px; }
        .sb-sp-form-group label { display:block; font-size:.72rem; font-weight:800; color:var(--sp-text2); margin-bottom:6px; text-transform:uppercase; letter-spacing:.05em; }
        .sb-sp-form-group input, .sb-sp-form-group textarea { width:100%; padding:9px 13px; border:1.5px solid var(--sp-border); border-radius:10px; font-size:.9rem; font-family:inherit; color:var(--sp-text); outline:none; transition:border-color .15s,box-shadow .15s; background:#fff; }
        .sb-sp-form-group input:focus, .sb-sp-form-group textarea:focus { border-color:var(--sp-purple); box-shadow:0 0 0 3px rgba(91,45,142,.1); }
        .sb-sp-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .sb-sp-save-btn { padding:10px 26px; background:var(--sp-purple); color:#fff; border:none; border-radius:10px; font-size:.9rem; font-weight:700; font-family:inherit; cursor:pointer; transition:all .18s; letter-spacing:.01em; }
        .sb-sp-save-btn:hover { background:var(--sp-purple-dk); transform:translateY(-1px); box-shadow:0 4px 12px rgba(91,45,142,.3); }
        .sb-sp-form-msg { padding:10px 14px; border-radius:10px; font-size:.85rem; margin-bottom:14px; display:none; }
        .sb-sp-form-msg.success { background:#f0fdf4; color:#166534; border:1px solid #86efac; }
        .sb-sp-form-msg.error   { background:#fff5f5; color:#991b1b; border:1px solid #fca5a5; }

        /* ── 7-DAY CHART ── */
        .sb-sp-chart-wrap { background:var(--sp-purple-lt); border:1px solid var(--sp-purple-mid); border-radius:12px; padding:18px 20px; margin-bottom:24px; }
        .sb-sp-chart-title { font-size:.7rem; font-weight:800; color:var(--sp-purple); text-transform:uppercase; letter-spacing:.08em; margin-bottom:14px; }
        .sb-sp-bar-chart { display:flex; align-items:flex-end; gap:6px; height:72px; }
        .sb-sp-bar-col { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; }
        .sb-sp-bar { width:100%; background:linear-gradient(180deg,#333 0%,var(--sp-purple) 100%); border-radius:5px 5px 0 0; min-height:4px; transition:opacity .18s; }
        .sb-sp-bar.today { background:linear-gradient(180deg,#a78bfa 0%,var(--sp-purple) 100%); }
        .sb-sp-bar:hover { opacity:.75; }
        .sb-sp-bar-val { font-size:.62rem; font-weight:700; color:var(--sp-purple); font-family:monospace; }
        .sb-sp-bar-day { font-size:.6rem; color:var(--sp-muted); font-weight:700; text-transform:uppercase; }
        .sb-sp-bar-day.today { color:var(--sp-purple); }

        /* ── RESPONSIVE ── */
        @media(max-width:768px){
            .sb-sp-stats { grid-template-columns: 1fr 1fr; }
            .sb-sp-nav-links { display: none; }
            .sb-sp-page { padding: 16px; }
            .sb-sp-form-row { grid-template-columns: 1fr; }
            .sb-sp-page-header { padding: 18px 20px; }
        }
        @media(max-width:480px){
            .sb-sp-stats { grid-template-columns: 1fr; }
        }
        </style>

        <script>document.body.classList.add('sb-sp-portal-active');</script>
        <div class="sb-sp">
        <!-- NAV -->
        <nav class="sb-sp-nav">
            <a class="sb-sp-nav-brand" href="<?php echo esc_url($url); ?>">📅 <?php echo $biz; ?></a>
            <div class="sb-sp-nav-links">
                <a class="sb-sp-nav-link <?php echo $active==='dashboard'?'active':''; ?>" href="<?php echo esc_url(add_query_arg('sb_view','dashboard',$url)); ?>">Dashboard</a>
                <a class="sb-sp-nav-link <?php echo $active==='schedule'?'active':''; ?>"  href="<?php echo esc_url(add_query_arg('sb_view','schedule',$url)); ?>">Schedule</a>
                <a class="sb-sp-nav-link <?php echo $active==='profile'?'active':''; ?>"   href="<?php echo esc_url(add_query_arg('sb_view','profile',$url)); ?>">Profile</a>
            </div>
            <div class="sb-sp-nav-user">
                <div class="sb-sp-nav-name"><?php echo esc_html(explode(' ', $staff->name)[0]); ?></div>
                <button class="sb-sp-logout-btn" id="sbStaffLogoutBtn">Log out</button>
            </div>
        </nav>
        <div class="sb-sp-page">
        <script>
        (function($){
            $('#sbStaffLogoutBtn').on('click', function(){
                $.post(<?php echo json_encode(admin_url('admin-ajax.php')); ?>,
                    {action:'sb_staff_logout', nonce:<?php echo json_encode($n); ?>},
                    function(){ window.location.href = <?php echo json_encode($url); ?>; }
                );
            });
        })(jQuery);
        </script>
        <?php
    }

    private static function nav_close() {
        echo '</div></div>'; // .sb-sp-page + .sb-sp
    }

    private static function get_staff( $sid ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}sb_staff WHERE id=%d", $sid) );
    }

    // ── STAFF DASHBOARD ──────────────────────────────────────────────
    private static function render_dashboard( $sid ) {
        global $wpdb;
        $staff = self::get_staff($sid);
        if ( ! $staff ) { self::destroy_session(); wp_redirect(self::get_portal_url()); exit; }

        $b     = self::brand();
        $cur   = get_option('sb_currency','FCFA');
        $today = date('Y-m-d');

        // All bookings for this staff member
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, s.name as service_name, s.color as service_color
             FROM {$wpdb->prefix}sb_bookings b
             LEFT JOIN {$wpdb->prefix}sb_services s ON b.service_id = s.id
             WHERE b.staff_id = %d
             ORDER BY b.booking_date DESC, b.start_time DESC
             LIMIT 200",
            $sid
        ) );

        $total      = count($bookings);
        $upcoming   = array_filter($bookings, fn($b) => in_array($b->status,['pending','confirmed','awaiting_deposit']) && $b->booking_date >= $today);
        $today_bks  = array_filter($bookings, fn($b) => $b->booking_date === $today && $b->status !== 'cancelled');
        $completed  = count(array_filter($bookings, fn($b) => $b->status === 'completed'));
        $pending    = count(array_filter($bookings, fn($b) => $b->status === 'pending'));
        $this_month = date('Y-m');
        $month_bks  = array_filter($bookings, fn($b) => str_starts_with($b->booking_date, $this_month) && $b->status !== 'cancelled');

        // Recent 8 bookings
        $recent = array_slice($bookings, 0, 8);

        // Upcoming sorted asc
        $upcoming_asc = array_reverse(array_values($upcoming));
        $next = $upcoming_asc[0] ?? null;

        self::nav_open($sid, 'dashboard');
        ?>
        <div class="sb-sp-page-header">
            <div>
                <div class="sb-sp-page-title">Staff Dashboard</div>
                <div class="sb-sp-page-sub">Welcome back, <?php echo esc_html(explode(' ', $staff->name)[0]); ?> 👋</div>
            </div>
            <div class="sb-sp-today-pill">📅 <?php echo date('D, d M'); ?></div>
        </div>

        <!-- Stats -->
        <div class="sb-sp-stats">
            <div class="sb-sp-stat sb-sp-stat-purple">
                <div class="sb-sp-stat-icon">📅</div>
                <div>
                    <div class="sb-sp-stat-val"><?php echo $total; ?></div>
                    <div class="sb-sp-stat-lbl">Total Bookings</div>
                </div>
            </div>
            <div class="sb-sp-stat sb-sp-stat-amber">
                <div class="sb-sp-stat-icon">⏳</div>
                <div>
                    <div class="sb-sp-stat-val"><?php echo count($upcoming); ?></div>
                    <div class="sb-sp-stat-lbl">Upcoming</div>
                </div>
            </div>
            <div class="sb-sp-stat sb-sp-stat-green">
                <div class="sb-sp-stat-icon">✅</div>
                <div>
                    <div class="sb-sp-stat-val"><?php echo $completed; ?></div>
                    <div class="sb-sp-stat-lbl">Completed</div>
                </div>
            </div>
            <div class="sb-sp-stat sb-sp-stat-blue">
                <div class="sb-sp-stat-icon">📆</div>
                <div>
                    <div class="sb-sp-stat-val"><?php echo count($month_bks); ?></div>
                    <div class="sb-sp-stat-lbl">This Month</div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

        <?php
        // 7-day chart
        $chart_days = [];
        for ( $i = 6; $i >= 0; $i-- ) {
            $d = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_days[ $d ] = 0;
        }
        foreach ( $bookings as $bk ) {
            if ( isset( $chart_days[ $bk->booking_date ] ) && $bk->status !== 'cancelled' ) {
                $chart_days[ $bk->booking_date ]++;
            }
        }
        $chart_max = max( array_values( $chart_days ) ) ?: 1;
        ?>
        <!-- 7-day chart — full width above the grid -->
        </div>
        <div class="sb-sp-chart-wrap" style="margin-bottom:20px;">
            <div class="sb-sp-chart-title">📊 My Bookings — Last 7 Days</div>
            <div class="sb-sp-bar-chart">
            <?php foreach ( $chart_days as $day => $count ):
                $height   = max( 4, intval( ($count / $chart_max) * 72 ) );
                $is_today = ( $day === $today );
                $lbl      = date( 'D', strtotime( $day ) );
            ?>
                <div class="sb-sp-bar-col">
                    <div class="sb-sp-bar-val"><?php echo $count ?: ''; ?></div>
                    <div class="sb-sp-bar<?php echo $is_today ? ' today' : ''; ?>" style="height:<?php echo $height; ?>px" title="<?php echo esc_attr($count); ?> on <?php echo esc_attr(date('D d M', strtotime($day))); ?>"></div>
                    <div class="sb-sp-bar-day<?php echo $is_today ? ' today' : ''; ?>"><?php echo $lbl; ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

            <!-- Today's Appointments -->
            <div>
                <div class="sb-sp-card">
                    <div class="sb-sp-card-head">
                        <div class="sb-sp-card-title">Today's Appointments</div>
                        <span class="sb-sp-today-pill">📅 <?php echo date('D, d M'); ?></span>
                    </div>
                    <div class="sb-sp-card-body sb-sp-timeline">
                    <?php if ( empty($today_bks) ): ?>
                        <div class="sb-sp-empty">
                            <div class="sb-sp-empty-icon">🗓</div>
                            <p>No appointments today</p>
                        </div>
                    <?php else:
                        $sorted = $today_bks;
                        usort($sorted, fn($a,$b) => strcmp($a->start_time, $b->start_time));
                        foreach ($sorted as $bk):
                            $col = $bk->service_color ?: $b['p'];
                    ?>
                        <div class="sb-sp-timeline-item">
                            <div class="sb-sp-timeline-time">
                                <div class="sb-sp-timeline-time-main"><?php echo date('g:i A', strtotime($bk->start_time)); ?></div>
                                <div class="sb-sp-timeline-time-end"><?php echo date('g:i A', strtotime($bk->end_time)); ?></div>
                            </div>
                            <div class="sb-sp-timeline-dot" style="background:<?php echo esc_attr($col); ?>;"></div>
                            <div class="sb-sp-timeline-body">
                                <div class="sb-sp-timeline-client"><?php echo esc_html($bk->customer_name); ?></div>
                                <div class="sb-sp-timeline-svc"><?php echo esc_html($bk->service_name ?? '—'); ?></div>
                                <?php if ($bk->customer_phone): ?>
                                <div class="sb-sp-timeline-phone">📞 <?php echo esc_html($bk->customer_phone); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="sb-sp-badge sb-sp-badge-<?php echo esc_attr($bk->status === 'awaiting_deposit' ? 'awaiting' : $bk->status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_',' ',$bk->status))); ?>
                            </span>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Recent bookings -->
                <div class="sb-sp-card">
                    <div class="sb-sp-card-head">
                        <div class="sb-sp-card-title">Recent Bookings</div>
                        <a href="<?php echo esc_url(add_query_arg('sb_view','schedule',self::get_portal_url())); ?>" style="font-size:.78rem;color:<?php echo $b['p']; ?>;text-decoration:none;font-weight:700;">View All →</a>
                    </div>
                    <div class="sb-sp-card-body">
                    <?php if ( empty($recent) ): ?>
                        <div class="sb-sp-empty"><div class="sb-sp-empty-icon">📋</div><p>No bookings yet</p></div>
                    <?php else:
                        foreach ($recent as $bk):
                            $col = $bk->service_color ?: $b['p'];
                            $badge_class = match($bk->status){
                                'confirmed'=>'confirmed','completed'=>'completed',
                                'cancelled'=>'cancelled','awaiting_deposit'=>'awaiting',
                                default=>'pending'
                            };
                    ?>
                        <div class="sb-sp-booking">
                            <div class="sb-sp-booking-stripe" style="background:<?php echo esc_attr($col); ?>;"></div>
                            <div class="sb-sp-booking-main">
                                <div class="sb-sp-booking-client"><?php echo esc_html($bk->customer_name); ?></div>
                                <div class="sb-sp-booking-svc"><?php echo esc_html($bk->service_name ?? '—'); ?></div>
                                <div class="sb-sp-booking-meta">
                                    <span>📅 <?php echo date('d M Y', strtotime($bk->booking_date)); ?></span>
                                    <span>🕐 <?php echo date('g:i A', strtotime($bk->start_time)); ?></span>
                                    <?php if ($bk->customer_phone): ?><span>📞 <?php echo esc_html($bk->customer_phone); ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <span class="sb-sp-badge sb-sp-badge-<?php echo $badge_class; ?>"><?php echo esc_html(ucfirst(str_replace('_',' ',$bk->status))); ?></span>
                                <div class="sb-sp-booking-price" style="margin-top:6px;"><?php echo number_format($bk->total_price); ?> <?php echo esc_html($cur); ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Next upcoming -->
                <?php if ($next): ?>
                <div class="sb-sp-card" style="margin-bottom:16px;">
                    <div class="sb-sp-card-head"><div class="sb-sp-card-title">Next Appointment</div></div>
                    <div style="padding:16px 18px;">
                        <div style="font-size:1rem;font-weight:800;color:#1a1a2e;margin-bottom:8px;"><?php echo esc_html($next->customer_name); ?></div>
                        <div style="font-size:.82rem;color:#64748b;margin-bottom:4px;">📋 <?php echo esc_html($next->service_name ?? '—'); ?></div>
                        <div style="font-size:.82rem;color:#64748b;margin-bottom:4px;">📅 <?php echo date('D, d M Y', strtotime($next->booking_date)); ?></div>
                        <div style="font-size:.82rem;color:#64748b;margin-bottom:8px;">🕐 <?php echo date('g:i A', strtotime($next->start_time)); ?></div>
                        <?php if ($next->customer_phone): ?>
                        <div style="font-size:.82rem;color:#64748b;">📞 <?php echo esc_html($next->customer_phone); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending -->
                <?php if ($pending > 0): ?>
                <div class="sb-sp-card" style="margin-bottom:16px;">
                    <div class="sb-sp-card-head">
                        <div class="sb-sp-card-title">Pending Confirmation</div>
                        <span style="background:#fef3c7;color:#92400e;font-size:.72rem;font-weight:800;padding:3px 9px;border-radius:20px;"><?php echo $pending; ?></span>
                    </div>
                    <div class="sb-sp-card-body">
                    <?php
                    $pend_list = array_filter($bookings, fn($b) => $b->status === 'pending');
                    foreach (array_slice(array_values($pend_list), 0, 5) as $bk): ?>
                        <div class="sb-sp-booking" style="padding:10px 18px;">
                            <div class="sb-sp-booking-main">
                                <div class="sb-sp-booking-client" style="font-size:.85rem;"><?php echo esc_html($bk->customer_name); ?></div>
                                <div class="sb-sp-booking-svc"><?php echo esc_html($bk->service_name ?? ''); ?> · <?php echo date('d M', strtotime($bk->booking_date)); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick links -->
                <div class="sb-sp-card">
                    <div class="sb-sp-card-head"><div class="sb-sp-card-title">Quick Links</div></div>
                    <div style="padding:14px 18px;display:flex;flex-direction:column;gap:8px;">
                        <a href="<?php echo esc_url(add_query_arg('sb_view','schedule',self::get_portal_url())); ?>" style="padding:9px 14px;background:<?php echo $b['p']; ?>;color:#fff;border-radius:8px;text-decoration:none;font-size:.84rem;font-weight:700;text-align:center;">📅 View Full Schedule</a>
                        <a href="<?php echo esc_url(add_query_arg('sb_view','profile',self::get_portal_url())); ?>" style="padding:9px 14px;background:#f4f6f9;color:#374151;border-radius:8px;text-decoration:none;font-size:.84rem;font-weight:700;text-align:center;border:1px solid #e5e7eb;">⚙ Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        self::nav_close();
    }

    // ── FULL SCHEDULE ────────────────────────────────────────────────
    private static function render_schedule( $sid ) {
        global $wpdb;
        $staff = self::get_staff($sid);
        if ( ! $staff ) { self::destroy_session(); wp_redirect(self::get_portal_url()); exit; }

        $b   = self::brand();
        $cur = get_option('sb_currency','FCFA');

        $filter = sanitize_key($_GET['sb_filter'] ?? 'upcoming');
        $today  = date('Y-m-d');

        $where = match($filter){
            'today'     => $wpdb->prepare("AND b.booking_date = %s", $today),
            'past'      => $wpdb->prepare("AND b.booking_date < %s", $today),
            'completed' => "AND b.status = 'completed'",
            'cancelled' => "AND b.status = 'cancelled'",
            default     => $wpdb->prepare("AND b.booking_date >= %s AND b.status NOT IN('cancelled','completed')", $today),
        };

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, s.name as service_name, s.color as service_color
             FROM {$wpdb->prefix}sb_bookings b
             LEFT JOIN {$wpdb->prefix}sb_services s ON b.service_id = s.id
             WHERE b.staff_id = %d $where
             ORDER BY b.booking_date ASC, b.start_time ASC
             LIMIT 200",
            $sid
        ) );

        $sched_url = add_query_arg('sb_view','schedule',self::get_portal_url());

        self::nav_open($sid, 'schedule');
        ?>
        <div class="sb-sp-page-header">
            <div>
                <div class="sb-sp-page-title">My Schedule</div>
                <div class="sb-sp-page-sub">All your upcoming and past appointments</div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;">
            <?php
            $tabs = ['upcoming'=>'Upcoming','today'=>'Today','past'=>'Past','completed'=>'Completed','cancelled'=>'Cancelled'];
            foreach ($tabs as $k=>$l):
            ?>
            <a href="<?php echo esc_url(add_query_arg('sb_filter',$k,$sched_url)); ?>"
               style="padding:6px 16px;border-radius:20px;font-size:.8rem;font-weight:700;text-decoration:none;border:1.5px solid <?php echo $filter===$k ? $b['p'] : '#e5e7eb'; ?>;background:<?php echo $filter===$k ? $b['p'] : '#fff'; ?>;color:<?php echo $filter===$k ? '#fff' : '#64748b'; ?>;">
               <?php echo $l; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="sb-sp-card">
            <div class="sb-sp-card-body">
            <?php if ( empty($bookings) ): ?>
                <div class="sb-sp-empty"><div class="sb-sp-empty-icon">📋</div><p>No bookings in this view</p></div>
            <?php else:
                $prev_date = '';
                foreach ($bookings as $bk):
                    $col = $bk->service_color ?: $b['p'];
                    $badge_class = match($bk->status){
                        'confirmed'=>'confirmed','completed'=>'completed',
                        'cancelled'=>'cancelled','awaiting_deposit'=>'awaiting',
                        default=>'pending'
                    };
                    if ($bk->booking_date !== $prev_date):
                        $prev_date = $bk->booking_date;
            ?>
                <div style="padding:8px 20px;background:#f8fafc;border-bottom:1px solid #f1f3f7;font-size:.73rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#64748b;">
                    <?php echo date('l, d F Y', strtotime($bk->booking_date)); ?>
                    <?php if ($bk->booking_date === $today): ?><span class="sb-sp-today-pill" style="margin-left:8px;">Today</span><?php endif; ?>
                </div>
            <?php endif; ?>
                <div class="sb-sp-booking">
                    <div class="sb-sp-booking-stripe" style="background:<?php echo esc_attr($col); ?>;"></div>
                    <div class="sb-sp-booking-main">
                        <div class="sb-sp-booking-client"><?php echo esc_html($bk->customer_name); ?></div>
                        <div class="sb-sp-booking-svc"><?php echo esc_html($bk->service_name ?? '—'); ?></div>
                        <div class="sb-sp-booking-meta">
                            <span>🕐 <?php echo date('g:i A', strtotime($bk->start_time)); ?> – <?php echo date('g:i A', strtotime($bk->end_time)); ?></span>
                            <?php if ($bk->customer_phone): ?><span>📞 <?php echo esc_html($bk->customer_phone); ?></span><?php endif; ?>
                            <?php if ($bk->customer_email): ?><span>✉ <?php echo esc_html($bk->customer_email); ?></span><?php endif; ?>
                            <?php if ($bk->notes): ?><span>📝 <?php echo esc_html(mb_substr($bk->notes,0,60)); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <span class="sb-sp-badge sb-sp-badge-<?php echo $badge_class; ?>"><?php echo esc_html(ucfirst(str_replace('_',' ',$bk->status))); ?></span>
                        <div class="sb-sp-booking-price" style="margin-top:6px;"><?php echo number_format($bk->total_price); ?> <?php echo esc_html($cur); ?></div>
                        <?php if ($bk->payment_status !== 'paid_in_full' && $bk->balance_amount > 0): ?>
                        <div style="font-size:.72rem;color:#dc2626;font-weight:700;margin-top:2px;">Bal: <?php echo number_format($bk->balance_amount); ?> <?php echo esc_html($cur); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            </div>
        </div>
        <?php
        self::nav_close();
    }

    // ── PROFILE ──────────────────────────────────────────────────────
    private static function render_profile( $sid ) {
        $staff = self::get_staff($sid);
        if ( ! $staff ) { self::destroy_session(); wp_redirect(self::get_portal_url()); exit; }

        $b    = self::brand();
        $ajax = admin_url('admin-ajax.php');
        $n    = wp_create_nonce('sb_staff_portal');

        self::nav_open($sid, 'profile');
        ?>
        <div class="sb-sp-page-header" style="margin-bottom:24px;">
            <div>
                <div class="sb-sp-page-title">My Profile</div>
                <div class="sb-sp-page-sub">Update your account details and password</div>
            </div>
        </div>
        <div class="sb-sp-card" style="max-width:600px;">
            <div class="sb-sp-card-head"><div class="sb-sp-card-title">Account Details</div></div>
            <div class="sb-sp-form">
                <div class="sb-sp-form-msg" id="sbStaffProfileMsg"></div>
                <div class="sb-sp-form-row">
                    <div class="sb-sp-form-group">
                        <label>Full Name</label>
                        <input type="text" id="sbSpName" value="<?php echo esc_attr($staff->name); ?>">
                    </div>
                    <div class="sb-sp-form-group">
                        <label>Phone</label>
                        <input type="text" id="sbSpPhone" value="<?php echo esc_attr($staff->phone); ?>">
                    </div>
                </div>
                <div class="sb-sp-form-group">
                    <label>Email</label>
                    <input type="email" id="sbSpEmail" value="<?php echo esc_attr($staff->email); ?>">
                </div>
                <div class="sb-sp-form-group">
                    <label>Bio</label>
                    <textarea id="sbSpBio" rows="3"><?php echo esc_textarea($staff->bio); ?></textarea>
                </div>
                <hr style="border:none;border-top:1px solid #e8eaf0;margin:20px 0;">
                <div class="sb-sp-card-title" style="margin-bottom:14px;">Change Password</div>
                <div class="sb-sp-form-row">
                    <div class="sb-sp-form-group">
                        <label>New Password</label>
                        <input type="password" id="sbSpPw" placeholder="Leave blank to keep current">
                    </div>
                    <div class="sb-sp-form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="sbSpPw2" placeholder="Repeat new password">
                    </div>
                </div>
                <button class="sb-sp-save-btn" id="sbSpSaveBtn">Save Changes</button>
            </div>
        </div>

        <script>
        (function($){
            $('#sbSpSaveBtn').on('click', function(){
                var pw=$('#sbSpPw').val(), pw2=$('#sbSpPw2').val();
                if(pw && pw!==pw2){ showMsg('Passwords do not match.','error'); return; }
                if(pw && pw.length<6){ showMsg('Password must be at least 6 characters.','error'); return; }
                $(this).prop('disabled',true).text('Saving…');
                $.post(<?php echo json_encode($ajax); ?>,{
                    action:'sb_staff_save_profile', nonce:<?php echo json_encode($n); ?>,
                    sid:<?php echo $sid; ?>, name:$('#sbSpName').val().trim(),
                    email:$('#sbSpEmail').val().trim(), phone:$('#sbSpPhone').val().trim(),
                    bio:$('#sbSpBio').val().trim(), password:pw
                }, function(r){
                    $('#sbSpSaveBtn').prop('disabled',false).text('Save Changes');
                    if(r.success){ showMsg(r.data.msg||'Saved!','success'); $('#sbSpPw,#sbSpPw2').val(''); }
                    else { showMsg(r.data.msg||'Error saving.','error'); }
                }).fail(function(){ $('#sbSpSaveBtn').prop('disabled',false).text('Save Changes'); showMsg('Connection error.','error'); });
            });
            function showMsg(t,type){$('#sbStaffProfileMsg').removeClass('success error').addClass(type).text(t).show();}
        })(jQuery);
        </script>
        <?php
        self::nav_close();
    }

    // ── AJAX: LOGIN ──────────────────────────────────────────────────
    public static function ajax_login() {
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'sb_staff_portal') ) wp_send_json_error(['msg'=>'Security error.']);
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $pw    = $_POST['password'] ?? '';
        if ( ! $email || ! $pw ) wp_send_json_error(['msg'=>'Please fill in all fields.']);

        $staff = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sb_staff WHERE email=%s AND status='active'", $email));
        if ( ! $staff ) wp_send_json_error(['msg'=>'No active staff account found with that email.']);

        if ( ! self::has_pw($staff->id) ) {
            wp_send_json_error(['msg'=>'No password set yet. Please contact your administrator to set up your account password.']);
        }
        if ( ! self::check_pw($staff->id, $pw) ) wp_send_json_error(['msg'=>'Incorrect password.']);

        self::set_session($staff->id);
        wp_send_json_success(['redirect' => add_query_arg('sb_view','dashboard',self::get_portal_url())]);
    }

    // ── AJAX: LOGOUT ─────────────────────────────────────────────────
    public static function ajax_logout() {
        self::destroy_session();
        wp_send_json_success();
    }

    // ── AJAX: SAVE PROFILE ───────────────────────────────────────────
    public static function ajax_save_profile() {
        if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'sb_staff_portal') ) wp_send_json_error(['msg'=>'Security error.']);
        $sid = self::get_logged_in_sid();
        if ( ! $sid ) wp_send_json_error(['msg'=>'Not logged in.']);

        global $wpdb;
        $data = [
            'name'  => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'bio'   => sanitize_textarea_field($_POST['bio'] ?? ''),
        ];
        if ( ! $data['name'] ) wp_send_json_error(['msg'=>'Name is required.']);

        $wpdb->update($wpdb->prefix.'sb_staff', $data, ['id'=>$sid]);

        $pw = $_POST['password'] ?? '';
        if ( $pw ) {
            if ( strlen($pw) < 6 ) wp_send_json_error(['msg'=>'Password must be at least 6 characters.']);
            self::set_pw($sid, $pw);
        }

        wp_send_json_success(['msg'=>'Profile updated successfully.']);
    }

    // ── ADMIN HELPER: Set staff password from admin panel ─────────────
    // Called via admin when setting up a staff member's portal access
    public static function admin_set_staff_password( $sid, $plain ) {
        self::set_pw(intval($sid), $plain);
    }
}