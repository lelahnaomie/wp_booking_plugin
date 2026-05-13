<?php
/**
 * Smart Booking — Frontend Admin Portal (v5.5.0)
 *
 * Individual shortcodes — one per admin section:
 *
 *   [sb_admin_dashboard]   Dashboard: stats, charts, today's schedule, pending actions
 *   [sb_admin_bookings]    Full bookings list with status filters & actions
 *   [sb_admin_services]    Services & categories management
 *   [sb_admin_staff]       Staff members & working hours
 *   [sb_admin_customers]   Customer list with search
 *   [sb_admin_calendar]    Weekly appointment calendar
 *   [sb_admin_finances]    Revenue reports, KPIs, transaction log
 *   [sb_admin_settings]    All plugin settings
 *
 * All shortcodes:
 *   - Require manage_options capability (redirects to login otherwise)
 *   - Render a sticky top-nav bar linking to all other sections
 *   - Are fully responsive (mobile-first)
 *
 * Legacy: [sb_admin_panel] still works and renders the dashboard.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Admin_Portal {

    /** Shortcode tag → section metadata */
    private static $sections = array(
        'sb_admin_dashboard' => array( 'key' => 'dashboard', 'label' => 'Dashboard',  'icon' => 'dashicons-dashboard'      ),
        'sb_admin_bookings'  => array( 'key' => 'bookings',  'label' => 'Bookings',   'icon' => 'dashicons-list-view'      ),
        'sb_admin_services'  => array( 'key' => 'services',  'label' => 'Services',   'icon' => 'dashicons-tag'            ),
        'sb_admin_staff'     => array( 'key' => 'staff',     'label' => 'Staff',      'icon' => 'dashicons-businessman'    ),
        'sb_admin_customers' => array( 'key' => 'customers', 'label' => 'Customers',  'icon' => 'dashicons-admin-users'    ),
        'sb_admin_calendar'  => array( 'key' => 'calendar',  'label' => 'Calendar',   'icon' => 'dashicons-calendar-alt'   ),
        'sb_admin_finances'  => array( 'key' => 'finances',  'label' => 'Finances',   'icon' => 'dashicons-chart-bar'      ),
        'sb_admin_settings'  => array( 'key' => 'settings',  'label' => 'Settings',   'icon' => 'dashicons-admin-settings' ),
    );

    /* ─────────────────────────────────────────
       INIT
    ───────────────────────────────────────── */
    public static function init() {
        foreach ( array_keys( self::$sections ) as $tag ) {
            add_shortcode( $tag, array( __CLASS__, 'shortcode_dispatch' ) );
        }
        // Legacy single-panel shortcode — renders dashboard
        add_shortcode( 'sb_admin_panel', array( __CLASS__, 'shortcode_dispatch' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
        // Redirect unauthenticated visitors BEFORE any output
        add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_to_login' ) );
        // Hide the WP admin bar on ALL our portal pages (even for admins — the sidebar replaces it)
        add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar_on_portal_pages' ) );
    }

    /**
     * Hide the WordPress admin bar on any page that contains one of our portal shortcodes.
     * The portal has its own sidebar nav, so the floating WP admin bar is not needed and
     * looks wrong on the frontend for both admins and business owners.
     */
    public static function hide_admin_bar_on_portal_pages( $show ) {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return $show;
        $all_tags = array_merge( array_keys( self::$sections ), array( 'sb_admin_panel', 'sb_portal_login' ) );
        foreach ( $all_tags as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) {
                return false; // Hide admin bar on this page
            }
        }
        return $show;
    }

    /**
     * If the current page has one of our portal shortcodes but the visitor
     * is not an admin, redirect to the frontend login page cleanly.
     */
    public static function maybe_redirect_to_login() {
        // Never run during REST API, admin, or AJAX requests
        if ( is_admin() ) return;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( wp_doing_ajax() ) return;

        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( current_user_can( 'manage_options' ) ) return;
        // Business owners also have portal access
        if ( current_user_can( SB_Roles::CAP ) ) return;

        $all_tags = array_merge( array_keys( self::$sections ), array( 'sb_admin_panel' ) );
        foreach ( $all_tags as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) {
                $login_url = SB_Login::get_login_page_url();
                $redirect  = add_query_arg( 'redirect_to', rawurlencode( get_permalink( $post->ID ) ), $login_url );
                wp_safe_redirect( $redirect );
                exit;
            }
        }
    }

    /* ─────────────────────────────────────────
       ENQUEUE assets on pages using our shortcodes
    ───────────────────────────────────────── */
    public static function maybe_enqueue() {
        if ( ! SB_Roles::can_access_portal() ) return;
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;

        $all_tags = array_merge( array_keys( self::$sections ), array( 'sb_admin_panel' ) );
        $found    = false;
        foreach ( $all_tags as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) { $found = true; break; }
        }
        if ( ! $found ) return;

        wp_enqueue_style(  'sb-adm-portal',       SB_URL . 'assets/css/admin.css',        array(), SB_VER );
        wp_enqueue_style(  'sb-adm-portal-extra', SB_URL . 'assets/css/admin-portal.css', array('sb-adm-portal'), SB_VER );
        wp_enqueue_script( 'sb-adm-portal',       SB_URL . 'assets/js/admin.js',          array('jquery'), SB_VER, true );
        wp_enqueue_script( 'sb-adm-portal-extra', SB_URL . 'assets/js/admin-portal.js',   array('sb-adm-portal'), SB_VER, true );
        wp_enqueue_media();
        wp_enqueue_style( 'dashicons' );

        $common = array(
            'ajax'           => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'sb_adm' ),
            'confirm_delete' => 'Are you sure? This cannot be undone.',
        );
        wp_localize_script( 'sb-adm-portal',       'SB',        $common );
        wp_localize_script( 'sb-adm-portal',       'SB_ADM',    $common );
        wp_localize_script( 'sb-adm-portal-extra', 'SB_PORTAL', array_merge( $common, array(
            'page_url' => get_permalink( $post->ID ),
        ) ) );
    }

    /* ─────────────────────────────────────────
       SHORTCODE DISPATCHER
    ───────────────────────────────────────── */
    public static function shortcode_dispatch( $atts, $content, $tag ) {
        if ( ! SB_Roles::can_access_portal() ) {
            // Redirect happens cleanly via template_redirect hook (see below).
            // This fallback only shows if headers were already sent somehow.
            $login_url = SB_Login::get_login_page_url();
            return '<div class="sbw-portal-denied">'
                 . '<p>⛔ You must be logged in to view this page.</p>'
                 . '<p><a href="' . esc_url( $login_url ) . '" class="sb-login-link">Go to Login</a></p>'
                 . '</div>';
        }
        $section = self::$sections[ $tag ] ?? self::$sections['sb_admin_dashboard'];
        ob_start();
        self::render_section( $section );
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────
       RENDER WRAPPER + TOP-NAV + CONTENT
    ───────────────────────────────────────── */
    private static function render_section( $section ) {
        $s          = get_option( 'sb_settings', array() );
        $biz_name   = esc_html( $s['business_name'] ?? get_bloginfo( 'name' ) );
        $logout_url = esc_url( add_query_arg( 'sb_logout', '1', SB_Login::get_login_page_url() ) );
        ?>
        <div class="sbw sbw-frontend-portal" id="sbFrontendPortal" data-section="<?php echo esc_attr( $section['key'] ); ?>">

            <!-- ── Mobile top bar ────────────────────────── -->
            <div class="sbw-portal-mobile-bar">
                <button class="sbw-portal-nav-toggle" id="sbPortalNavToggle" aria-label="Toggle menu">
                    <span class="dashicons dashicons-menu"></span>
                </button>
                <span class="sbw-portal-mobile-title">
                    <span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>"></span>
                    <?php echo esc_html( $section['label'] ); ?>
                </span>
                <a href="<?php echo $logout_url; ?>" class="sbw-mobile-logout" title="Sign out">
                    <span class="dashicons dashicons-exit"></span>
                </a>
            </div>

            <!-- ── Sidebar Navigation ────────────────────── -->
            <nav class="sbw-portal-nav" id="sbPortalSidebar" aria-label="Portal navigation">
                <div class="sbw-portal-brand">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span><?php echo $biz_name; ?></span>
                </div>

                <ul class="sbw-portal-menu">
                    <?php foreach ( self::$sections as $tag => $info ) : ?>
                    <li>
                        <a href="<?php echo esc_url( self::section_permalink( $info['key'] ) ); ?>"
                           class="sbw-portal-menu-item <?php echo $section['key'] === $info['key'] ? 'active' : ''; ?>"
                           title="<?php echo esc_attr( $info['label'] ); ?>">
                            <span class="dashicons <?php echo esc_attr( $info['icon'] ); ?>"></span>
                            <span><?php echo esc_html( $info['label'] ); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="sbw-portal-nav-footer">
                    <a href="<?php echo $logout_url; ?>" class="sbw-portal-wp-link sbw-logout-link">
                        <span class="dashicons dashicons-exit"></span>
                        <span>Sign Out</span>
                    </a>
                </div>
            </nav>

            <!-- ── Page body ─────────────────────────────── -->
            <div class="sbw-portal-content">
                <div class="sbw-portal-page">
                    <?php
                    switch ( $section['key'] ) {
                        case 'dashboard': self::tab_dashboard(); break;
                        case 'bookings':  self::tab_bookings();  break;
                        case 'services':  self::tab_services();  break;
                        case 'staff':     self::tab_staff();     break;
                        case 'customers': self::tab_customers(); break;
                        case 'calendar':  self::tab_calendar();  break;
                        case 'finances':  self::tab_finances();  break;
                        case 'settings':  self::tab_settings();  break;
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────
       PERMALINK RESOLVER — public alias
    ───────────────────────────────────────── */
    public static function get_section_url( $key ) {
        return self::section_permalink( $key );
    }

    /* ─────────────────────────────────────────
       PERMALINK RESOLVER
       1. Check by slug (set on activation — fastest)
       2. Fallback: search post_content for shortcode
       3. Last resort: current page + ?sb_section=key
    ───────────────────────────────────────── */
    private static function section_permalink( $key ) {
        static $cache = array();
        if ( isset( $cache[ $key ] ) ) return $cache[ $key ];

        // Slug map matches what create_portal_pages() uses
        $slug_map = array(
            'dashboard'  => 'sb-dashboard',
            'bookings'   => 'sb-bookings',
            'services'   => 'sb-services',
            'staff'      => 'sb-staff',
            'customers'  => 'sb-customers',
            'calendar'   => 'sb-calendar',
            'finances'   => 'sb-finances',
            'settings'   => 'sb-settings',
        );

        global $wpdb;
        $pid = 0;

        // 1. Lookup by slug
        if ( isset( $slug_map[ $key ] ) ) {
            $pid = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_name=%s AND post_type='page'
                   AND post_status='publish'
                 LIMIT 1",
                $slug_map[ $key ]
            ) );
        }

        // 2. Fallback: search shortcode in content
        if ( ! $pid ) {
            $tag = 'sb_admin_' . $key;
            $pid = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status='publish' AND post_type='page'
                   AND post_content LIKE %s
                 LIMIT 1",
                '%[' . $tag . ']%'
            ) );
        }

        if ( $pid ) {
            $url = get_permalink( $pid );
        } else {
            global $post;
            $url = $post ? get_permalink( $post->ID ) : home_url( '/' );
            $url = add_query_arg( 'sb_section', $key, $url );
        }

        $cache[ $key ] = $url;
        return $url;
    }

    /** Page header */
    private static function head( $title, $sub = '' ) {
        echo '<div class="sbw-head"><div><h1>' . esc_html( $title ) . '</h1>'
           . ( $sub ? '<p class="sbw-sub">' . wp_kses_post( $sub ) . '</p>' : '' )
           . '</div><span class="sbw-ver">v' . SB_VER . '</span></div>';
    }

    /** Section URL helper */
    private static function tab_url( $key ) {
        return esc_url( self::section_permalink( $key ) );
    }

    /* ══════════════════════════════════════════
       SECTION: DASHBOARD
    ══════════════════════════════════════════ */
    private static function tab_dashboard() {
        $s              = SB_DB::get_stats();
        $cur            = get_option( 'sb_currency', 'FCFA' );
        $today_bookings = SB_DB::get_bookings( array( 'date' => date('Y-m-d') ) );
        $pending        = SB_DB::get_bookings( array( 'status' => 'pending' ) );

        $chart_days = array();
        for ( $i = 6; $i >= 0; $i-- ) {
            $d = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_days[] = array(
                'label' => date( 'D', strtotime( $d ) ),
                'count' => count( SB_DB::get_bookings( array( 'date' => $d ) ) ),
                'today' => $i === 0,
            );
        }
        $max_chart = max( array_column( $chart_days, 'count' ) );
        if ( $max_chart < 1 ) $max_chart = 1;

        global $wpdb;
        $top_svcs = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.name, s.color, COUNT(b.id) as cnt FROM {$wpdb->prefix}sb_bookings b
             JOIN {$wpdb->prefix}sb_services s ON b.service_id=s.id
             WHERE b.booking_date >= %s GROUP BY b.service_id ORDER BY cnt DESC LIMIT 5",
            date('Y-m-01')
        ) );
        $max_svc = ( $top_svcs && $top_svcs[0]->cnt > 0 ) ? $top_svcs[0]->cnt : 1;
        ?>
        <?php self::head( 'Dashboard', 'Welcome back &mdash; ' . date('l, d F Y') ); ?>
        <div class="sbw-stats">
            <div class="sbw-stat sbw-stat-purple"><div class="sbw-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div><div class="sbw-stat-body"><b><?php echo $s['total']; ?></b><small>Total Bookings</small></div></div>
            <div class="sbw-stat sbw-stat-amber"><div class="sbw-stat-icon"><span class="dashicons dashicons-clock"></span></div><div class="sbw-stat-body"><b><?php echo $s['pending']; ?></b><small>Pending</small></div></div>
            <div class="sbw-stat sbw-stat-green"><div class="sbw-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sbw-stat-body"><b><?php echo $s['confirmed']; ?></b><small>Confirmed</small></div></div>
            <div class="sbw-stat sbw-stat-blue"><div class="sbw-stat-icon"><span class="dashicons dashicons-money-alt"></span></div><div class="sbw-stat-body"><b><?php echo number_format($s['revenue']); ?></b><small><?php echo esc_html($cur); ?> Revenue</small></div></div>
            <div class="sbw-stat sbw-stat-indigo"><div class="sbw-stat-icon"><span class="dashicons dashicons-admin-users"></span></div><div class="sbw-stat-body"><b><?php echo $s['customers']; ?></b><small>Customers</small></div></div>
            <div class="sbw-stat sbw-stat-teal"><div class="sbw-stat-icon"><span class="dashicons dashicons-businessman"></span></div><div class="sbw-stat-body"><b><?php echo $s['staff']; ?></b><small>Staff Members</small></div></div>
        </div>
        <div class="sbw-cols">
        <div class="sbw-card" style="flex:1.6">
            <div class="sbw-card-head"><h2>Bookings — Last 7 Days</h2><a href="<?php echo self::tab_url('bookings'); ?>" class="sbw-lnk">View All →</a></div>
            <div class="sbw-chart-wrap">
                <div class="sbw-chart-title">Daily Bookings</div>
                <div class="sbw-bar-chart">
                <?php foreach ( $chart_days as $cd ): $pct = max( round(($cd['count']/$max_chart)*72), 4 ); ?>
                <div class="sbw-bar-col">
                    <div class="sbw-bar-val"><?php echo $cd['count'] > 0 ? $cd['count'] : ''; ?></div>
                    <div class="sbw-bar<?php echo $cd['today']?' today':''; ?>" style="height:<?php echo $pct; ?>px;<?php echo $cd['today']?'':'opacity:.5'; ?>"></div>
                    <div class="sbw-bar-day<?php echo $cd['today']?' today-lbl':''; ?>"><?php echo $cd['label']; ?></div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="sbw-card-head" style="margin-top:10px">
                <h2>Today's Schedule <span style="color:#6b7280;font-weight:400;font-size:.84rem">(<?php echo count($today_bookings); ?> appointment<?php echo count($today_bookings)!=1?'s':''; ?>)</span></h2>
                <a href="<?php echo self::tab_url('calendar'); ?>" class="sbw-lnk">Calendar →</a>
            </div>
            <?php if ( empty($today_bookings) ): ?><div class="sbw-empty"><p>No appointments today.</p></div><?php else: ?>
            <div class="sbw-timeline">
            <?php foreach ( $today_bookings as $b ): ?>
                <div class="sbw-appt" style="border-left-color:<?php echo esc_attr($b->service_color??'#111111'); ?>">
                    <div class="sbw-appt-time"><?php echo date('g:i A',strtotime($b->start_time)); ?></div>
                    <div class="sbw-appt-info"><strong><?php echo esc_html($b->customer_name); ?></strong><span><?php echo esc_html($b->service_name); ?><?php if($b->staff_name): ?> &middot; <?php echo esc_html($b->staff_name); ?><?php endif; ?></span></div>
                    <span class="sbw-badge sbw-<?php echo esc_attr($b->status); ?>"><?php echo ucfirst($b->status); ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="sbw-col-r">
            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Pending Actions <?php if($s['pending']>0): ?><span style="background:#fef3c7;color:#92400e;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:6px"><?php echo $s['pending']; ?></span><?php endif; ?></h2></div>
                <?php if ( empty($pending) ): ?><div class="sbw-empty"><p>No pending bookings.</p></div><?php else: ?>
                <div class="sbw-pending-list">
                <?php foreach ( array_slice($pending,0,6) as $b ): ?>
                    <div class="sbw-pend-row" data-id="<?php echo $b->id; ?>">
                        <div style="min-width:0;flex:1"><strong><?php echo esc_html($b->customer_name); ?></strong><span class="sbw-pend-meta"><?php echo esc_html($b->service_name); ?> &middot; <?php echo date('d M',strtotime($b->booking_date)); ?> <?php echo date('g:i A',strtotime($b->start_time)); ?></span></div>
                        <div class="sbw-pend-actions"><button class="sbw-ab sbw-confirm" data-id="<?php echo $b->id; ?>">Confirm</button><button class="sbw-ab sbw-cancel" data-id="<?php echo $b->id; ?>">Cancel</button></div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php if(count($pending)>6): ?><div style="padding:10px 0 0;text-align:center"><a href="<?php echo self::tab_url('bookings'); ?>" class="sbw-lnk">View all <?php echo count($pending); ?> pending &rarr;</a></div><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if(!empty($top_svcs)): ?>
            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Top Services <span style="color:#6b7280;font-weight:400;font-size:.8rem">this month</span></h2></div>
                <div class="sbw-top-svc">
                <?php foreach($top_svcs as $ts): ?>
                <div class="sbw-tsvc-row"><div class="sbw-tsvc-info"><span class="sbw-tsvc-name"><span class="sbw-dot" style="background:<?php echo esc_attr($ts->color??'#111111'); ?>"></span><?php echo esc_html($ts->name); ?></span><span class="sbw-tsvc-count"><?php echo $ts->cnt; ?> booking<?php echo $ts->cnt!=1?'s':''; ?></span></div><div class="sbw-tsvc-bar-wrap"><div class="sbw-tsvc-bar" style="width:<?php echo round(($ts->cnt/$max_svc)*100); ?>%"></div></div></div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Quick Actions</h2></div>
                <div class="sbw-quick-actions">
                    <a href="<?php echo self::tab_url('bookings'); ?>"  class="sbw-qa"><span class="dashicons dashicons-list-view"></span> Bookings</a>
                    <a href="<?php echo self::tab_url('services'); ?>"  class="sbw-qa"><span class="dashicons dashicons-tag"></span> Services</a>
                    <a href="<?php echo self::tab_url('staff'); ?>"     class="sbw-qa"><span class="dashicons dashicons-businessman"></span> Staff</a>
                    <a href="<?php echo self::tab_url('customers'); ?>" class="sbw-qa"><span class="dashicons dashicons-admin-users"></span> Customers</a>
                    <a href="<?php echo self::tab_url('calendar'); ?>"  class="sbw-qa"><span class="dashicons dashicons-calendar-alt"></span> Calendar</a>
                    <a href="<?php echo self::tab_url('settings'); ?>"  class="sbw-qa"><span class="dashicons dashicons-admin-settings"></span> Settings</a>
                </div>
            </div>
        </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: BOOKINGS
    ══════════════════════════════════════════ */
    private static function tab_bookings() {
        $status    = sanitize_text_field( $_GET['status']  ?? '' );
        $service   = intval( $_GET['service'] ?? 0 );
        $staff     = intval( $_GET['staff']   ?? 0 );
        $bookings  = SB_DB::get_bookings( array_filter( array('status'=>$status,'service_id'=>$service,'staff_id'=>$staff) ) );
        $services  = SB_DB::get_services();
        $staff_all = SB_DB::get_staff();
        $currency  = get_option('sb_currency','FCFA');
        $base      = self::section_permalink('bookings');
        $tabs      = array(''=> 'All','pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled');
        ?>
        <?php self::head('Bookings', count($bookings).' booking'.(count($bookings)!=1?'s':'')); ?>
        <div class="sbw-filters">
            <div class="sbw-tabs sbw-tabs-scroll">
                <?php foreach($tabs as $k=>$l): $url=add_query_arg(array_filter(array('status'=>$k?:null,'service'=>$service?:null,'staff'=>$staff?:null)),$base); ?>
                <a href="<?php echo esc_url($url); ?>" class="sbw-tab <?php echo $status===$k?'active':''; ?>"><?php echo $l; ?></a>
                <?php endforeach; ?>
            </div>
            <div class="sbw-filter-selects">
                <select onchange="location.href=this.value">
                    <option value="<?php echo esc_url(add_query_arg(array('status'=>$status?:null),$base)); ?>">All services</option>
                    <?php foreach($services as $sv): ?><option value="<?php echo esc_url(add_query_arg(array('status'=>$status?:null,'service'=>$sv->id),$base)); ?>" <?php selected($service,$sv->id); ?>><?php echo esc_html($sv->name); ?></option><?php endforeach; ?>
                </select>
                <select onchange="location.href=this.value">
                    <option value="<?php echo esc_url(add_query_arg(array('status'=>$status?:null),$base)); ?>">All staff</option>
                    <?php foreach($staff_all as $st): ?><option value="<?php echo esc_url(add_query_arg(array('status'=>$status?:null,'staff'=>$st->id),$base)); ?>" <?php selected($staff,$st->id); ?>><?php echo esc_html($st->name); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="sbw-card sbw-tbl-card">
        <?php if(empty($bookings)): ?><div class="sbw-empty"><p>No bookings found.</p></div><?php else: ?>
        <div class="sbw-tbl-wrap">
        <table class="sbw-tbl">
            <thead><tr><th>#</th><th>Client</th><th>Service</th><th class="sbw-hide-xs">Staff</th><th>Date &amp; Time</th><th class="sbw-hide-xs">Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $b_colors=array('#111111','#4f46e5','#0d9488','#d97706','#dc2626','#16a34a','#2563eb');
            foreach($bookings as $b):
                $b_color=$b_colors[crc32($b->customer_name)%count($b_colors)];
                $b_init=strtoupper(substr(trim($b->customer_name),0,1));
            ?>
            <tr data-id="<?php echo $b->id; ?>">
                <td><span style="font-family:monospace;font-size:.78rem;color:#8b91a7;font-weight:600">#<?php echo $b->id; ?></span></td>
                <td>
                    <div class="sbw-cust-cell">
                        <div class="sbw-cust-avatar" style="background:<?php echo $b_color; ?>"><?php echo $b_init; ?></div>
                        <div class="sbw-cust-info"><strong><?php echo esc_html($b->customer_name); ?></strong><?php if($b->customer_phone): ?><small><?php echo esc_html($b->customer_phone); ?></small><?php endif; ?></div>
                    </div>
                </td>
                <td><span class="sbw-dot" style="background:<?php echo esc_attr($b->service_color??'#111111'); ?>"></span><span style="font-weight:600;font-size:.86rem"><?php echo esc_html($b->service_name); ?></span></td>
                <td class="sbw-hide-xs" style="color:#4b5563;font-size:.84rem"><?php echo esc_html($b->staff_name??'—'); ?></td>
                <td>
                    <span style="font-weight:600;font-size:.86rem"><?php echo date('d M Y',strtotime($b->booking_date)); ?></span>
                    <br><small style="color:#8b91a7"><?php echo date('g:i A',strtotime($b->start_time)); ?> – <?php echo date('g:i A',strtotime($b->end_time)); ?></small>
                    <?php if(!empty($b->num_days)&&intval($b->num_days)>1): ?><br><small style="color:#111111;font-weight:600"><?php echo intval($b->num_days); ?> Days</small><?php endif; ?>
                </td>
                <td class="sbw-hide-xs"><strong><?php echo $b->total_price>0?number_format($b->total_price).' '.esc_html($currency):'—'; ?></strong></td>
                <td><span class="sbw-badge sbw-<?php echo esc_attr($b->status); ?>"><?php echo ucfirst(str_replace('_',' ',$b->status)); ?></span></td>
                <td class="sbw-acts">
                    <select class="sbw-status-sel" data-id="<?php echo $b->id; ?>">
                        <?php foreach(array('pending','confirmed','completed','cancelled','no_show') as $st): ?><option value="<?php echo $st; ?>" <?php selected($b->status,$st); ?>><?php echo ucfirst(str_replace('_',' ',$st)); ?></option><?php endforeach; ?>
                    </select>
                    <button class="sbw-del-btn" data-id="<?php echo $b->id; ?>" title="Delete"></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: SERVICES
    ══════════════════════════════════════════ */
    private static function tab_services() {
        $services = SB_DB::get_services();
        $cats     = SB_DB::get_categories();
        $currency = get_option('sb_currency','FCFA');
        ?>
        <?php self::head('Services',count($services).' service'.(count($services)!=1?'s':'').' — manage your bookable offerings'); ?>
        <div class="sbw-cols">
        <div class="sbw-card" style="flex:1.5">
            <div class="sbw-card-head"><h2>Your Services</h2><button class="sbw-btn" id="sbAddServiceBtn">+ Add Service</button></div>
            <?php if(empty($services)): ?><div class="sbw-empty"><p>No services yet. Add your first service →</p></div><?php else: ?>
            <div class="sbw-tbl-wrap">
            <table class="sbw-tbl">
                <thead><tr><th>Service</th><th>Duration</th><th>Price</th><th class="sbw-hide-xs">Deposit</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($services as $sv): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:11px">
                        <?php if(!empty($sv->image_url)): ?><img src="<?php echo esc_url($sv->image_url); ?>" style="width:42px;height:42px;border-radius:8px;object-fit:cover;flex-shrink:0;border:1px solid #ede0ff" alt=""><?php else: ?><span style="width:42px;height:42px;border-radius:8px;background:<?php echo esc_attr($sv->color); ?>;flex-shrink:0;display:inline-block"></span><?php endif; ?>
                        <div><strong style="display:block;font-size:.9rem"><?php echo esc_html($sv->name); ?></strong><?php if($sv->category_name): ?><small style="color:#8b91a7;font-size:.76rem;font-weight:500"><?php echo esc_html($sv->category_name); ?></small><?php endif; ?></div>
                        </div>
                    </td>
                    <td style="color:#4b5563;font-size:.84rem"><?php echo SB_Services::format_duration($sv->duration); ?></td>
                    <td><strong><?php echo $sv->price>0?number_format($sv->price).' '.esc_html($currency):'Free'; ?></strong></td>
                    <td class="sbw-hide-xs" style="color:#4b5563"><?php echo $sv->deposit_pct>0?$sv->deposit_pct.'%':'—'; ?></td>
                    <td><span class="sbw-badge sbw-<?php echo $sv->status; ?>"><?php echo ucfirst($sv->status); ?></span></td>
                    <td>
                        <button class="sbw-edit-svc" data-id="<?php echo $sv->id; ?>" data-name="<?php echo esc_attr($sv->name); ?>" data-duration="<?php echo $sv->duration; ?>" data-padding="<?php echo $sv->padding_time; ?>" data-price="<?php echo $sv->price; ?>" data-deposit="<?php echo $sv->deposit_pct; ?>" data-color="<?php echo esc_attr($sv->color); ?>" data-cat="<?php echo $sv->category_id; ?>" data-desc="<?php echo esc_attr($sv->description); ?>" data-cap="<?php echo $sv->capacity; ?>" data-status="<?php echo $sv->status; ?>" data-image="<?php echo esc_attr($sv->image_url??''); ?>" data-policy="<?php echo esc_attr($sv->service_policy??''); ?>" data-gallery="<?php echo esc_attr($sv->gallery_images??''); ?>">Edit</button>
                        <button class="sbw-del-svc" data-id="<?php echo $sv->id; ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="sbw-card" style="flex:.8">
            <div class="sbw-card-head"><h2>Categories</h2></div>
            <div class="sbw-cat-list">
            <?php foreach($cats as $c): ?>
            <div class="sbw-cat-row"><span><?php echo esc_html($c->name); ?></span><button class="sbw-save-cat" data-id="<?php echo $c->id; ?>" data-name="<?php echo esc_attr($c->name); ?>">Edit</button></div>
            <?php endforeach; ?>
            </div>
            <div class="sbw-cat-add"><input type="text" id="sbNewCatName" placeholder="New category name"><button class="sbw-btn" id="sbAddCatBtn">Add</button></div>
        </div>
        </div>
        <!-- SERVICE MODAL -->
        <div class="sbw-modal" id="sbServiceModal" style="display:none">
            <div class="sbw-modal-box">
                <div class="sbw-modal-head"><h3 id="sbSvcModalTitle">Add Service</h3><button class="sbw-modal-close"></button></div>
                <div class="sbw-modal-body">
                    <input type="hidden" id="sbSvcId">
                    <div class="sbw-row2"><div class="sbw-fg"><label>Name *</label><input type="text" id="sbSvcName"></div><div class="sbw-fg"><label>Category</label><select id="sbSvcCat"><option value="0">Uncategorised</option><?php foreach($cats as $c): ?><option value="<?php echo $c->id; ?>"><?php echo esc_html($c->name); ?></option><?php endforeach; ?></select></div></div>
                    <div class="sbw-fg"><label>Description</label><textarea id="sbSvcDesc" rows="2"></textarea></div>
                    <div class="sbw-fg"><label>Service Policy / Terms</label><textarea id="sbSvcPolicy" rows="3" placeholder="e.g. No refunds after confirmation."></textarea></div>
                    <div class="sbw-row3"><div class="sbw-fg"><label>Duration (min) *</label><input type="number" id="sbSvcDuration" value="60" min="5"></div><div class="sbw-fg"><label>Buffer (min)</label><input type="number" id="sbSvcPadding" value="0" min="0"></div><div class="sbw-fg"><label>Max bookings</label><input type="number" id="sbSvcCap" value="1" min="1"></div></div>
                    <div class="sbw-row3"><div class="sbw-fg"><label>Price (<?php echo esc_html($currency); ?>)</label><input type="number" id="sbSvcPrice" value="0" min="0"></div><div class="sbw-fg"><label>Deposit %</label><input type="number" id="sbSvcDeposit" value="0" min="0" max="100"></div><div class="sbw-fg"><label>Color</label><input type="text" id="sbSvcColor" class="sbw-color" value="#111111"></div></div>
                    <div class="sbw-fg"><label>Status</label><select id="sbSvcStatus"><option value="active">Active</option><option value="disabled">Disabled</option></select></div>
                    <div class="sbw-fg"><label>Service Image</label><div class="sbw-img-row"><div class="sbw-img-preview" id="sbSvcImgPreview"><span class="sbw-img-ph">No image</span></div><div class="sbw-img-btns"><button type="button" class="sbw-btn" id="sbSvcPickImg">Choose Image</button><button type="button" class="sbw-btn-ghost" id="sbSvcRemoveImg" style="display:none">Remove</button></div></div><input type="hidden" id="sbSvcImageUrl" value=""></div>
                    <div class="sbw-fg"><label>Gallery Images <small style="color:#64748b">(optional)</small></label><div id="sbGalleryPreview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px"></div><button type="button" class="sbw-btn" id="sbSvcAddGalleryImg">+ Add Gallery Image</button><input type="hidden" id="sbSvcGalleryUrls" value=""></div>
                </div>
                <div class="sbw-modal-foot"><button class="sbw-btn-ghost sbw-modal-close">Cancel</button><button class="sbw-btn" id="sbSaveSvcBtn">Save Service</button></div>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: STAFF
    ══════════════════════════════════════════ */
    private static function tab_staff() {
        $staff_all    = SB_DB::get_staff();
        $all_services = SB_DB::get_services( array('status'=>'active') );
        $days = array(0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday');
        ?>
        <?php self::head('Staff Members','Manage who provides your services'); ?>
        <div class="sbw-cols">
        <div class="sbw-card" style="flex:1.5">
            <div class="sbw-card-head"><h2>Staff</h2><button class="sbw-btn" id="sbAddStaffBtn">+ Add Staff</button></div>
            <?php if(empty($staff_all)): ?><div class="sbw-empty"><p>No staff yet. Add your first team member.</p></div><?php else: ?>
            <div class="sbw-staff-grid">
            <?php foreach($staff_all as $st):
                $svc_ids=SB_DB::get_services_for_staff($st->id);
                $hours  =SB_DB::get_working_hours($st->id);
            ?>
            <div class="sbw-staff-card">
                <div class="sbw-staff-avatar" style="background:<?php echo esc_attr($st->color); ?>"><?php if($st->image_url): ?><img src="<?php echo esc_url($st->image_url); ?>" alt=""><?php else: echo strtoupper(substr($st->name,0,1)); endif; ?></div>
                <div class="sbw-staff-info"><strong><?php echo esc_html($st->name); ?></strong><span><?php echo esc_html($st->email?:$st->phone?:'No contact'); ?></span><span><?php echo count($svc_ids); ?> service<?php echo count($svc_ids)!=1?'s':''; ?> assigned</span></div>
                <div class="sbw-staff-acts">
                    <button class="sbw-edit-staff" data-id="<?php echo $st->id; ?>" data-name="<?php echo esc_attr($st->name); ?>" data-email="<?php echo esc_attr($st->email); ?>" data-phone="<?php echo esc_attr($st->phone); ?>" data-color="<?php echo esc_attr($st->color); ?>" data-services='<?php echo esc_attr(json_encode($svc_ids)); ?>' data-hours='<?php $hout=array();foreach($hours as $d=>$h)$hout[$d]=array('start'=>$h->start_time,'end'=>$h->end_time,'off'=>$h->is_day_off);echo esc_attr(json_encode($hout)); ?>'>Edit</button>
                    <button class="sbw-del-staff" data-id="<?php echo $st->id; ?>">Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        </div>
        <!-- STAFF MODAL -->
        <div class="sbw-modal" id="sbStaffModal" style="display:none">
            <div class="sbw-modal-box sbw-modal-wide">
                <div class="sbw-modal-head"><h3 id="sbStaffModalTitle">Add Staff Member</h3><button class="sbw-modal-close-staff"></button></div>
                <div class="sbw-modal-body">
                    <input type="hidden" id="sbStaffId">
                    <div class="sbw-row2"><div class="sbw-fg"><label>Name *</label><input type="text" id="sbStaffName"></div><div class="sbw-fg"><label>Color</label><input type="text" id="sbStaffColor" class="sbw-color" value="#C9A84C"></div></div>
                    <div class="sbw-row2"><div class="sbw-fg"><label>Email</label><input type="email" id="sbStaffEmail"></div><div class="sbw-fg"><label>Phone</label><input type="tel" id="sbStaffPhone"></div></div>
                    <h4>Assigned Services</h4>
                    <div class="sbw-svc-checks"><?php foreach($all_services as $sv): ?><label class="sbw-check-label"><input type="checkbox" name="staff_svc[]" value="<?php echo $sv->id; ?>"> <?php echo esc_html($sv->name); ?></label><?php endforeach; ?></div>
                    <h4>Working Hours</h4>
                    <div class="sbw-hours-grid">
                        <div class="sbw-hours-header"><span>Day</span><span>Open</span><span>From</span><span>To</span></div>
                        <?php foreach($days as $d=>$dname): ?>
                        <div class="sbw-hours-row" data-day="<?php echo $d; ?>"><span><?php echo $dname; ?></span><input type="checkbox" class="sbw-day-off" data-day="<?php echo $d; ?>" title="Open for bookings"><input type="time" class="sbw-hr-start" data-day="<?php echo $d; ?>" value="08:00"><input type="time" class="sbw-hr-end" data-day="<?php echo $d; ?>" value="18:00"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sbw-modal-foot"><button class="sbw-btn-ghost sbw-modal-close-staff">Cancel</button><button class="sbw-btn" id="sbSaveStaffBtn">Save Staff</button></div>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: CUSTOMERS
    ══════════════════════════════════════════ */
    private static function tab_customers() {
        $search    = sanitize_text_field($_GET['s']??'');
        $customers = SB_DB::get_customers($search);
        $currency  = get_option('sb_currency','FCFA');
        $base      = self::section_permalink('customers');
        ?>
        <?php self::head('Customers',count($customers).' customer'.(count($customers)!=1?'s':'')); ?>
        <div class="sbw-card">
            <div class="sbw-card-head">
                <h2>Customer List</h2>
                <form method="get" class="sbw-search-form" action="<?php echo esc_url($base); ?>">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email or phone…">
                    <button type="submit" class="sbw-btn">Search</button>
                </form>
            </div>
            <?php if(empty($customers)): ?><div class="sbw-empty"><p><?php echo $search?'No customers match &ldquo;'.esc_html($search).'&rdquo;.':'No customers yet.'; ?></p></div><?php else: ?>
            <div class="sbw-tbl-wrap">
            <table class="sbw-tbl">
                <thead><tr><th>Customer</th><th class="sbw-hide-xs">Phone</th><th>Bookings</th><th class="sbw-hide-xs">Total Spent</th><th class="sbw-hide-sm">Last Booking</th><th class="sbw-hide-sm">Member Since</th></tr></thead>
                <tbody>
                <?php
                $colors=array('#111111','#4f46e5','#0d9488','#d97706','#dc2626','#16a34a','#2563eb');
                foreach($customers as $c):
                    $init=strtoupper(substr(trim($c->name),0,1));
                    $col=$colors[crc32($c->name)%count($colors)];
                ?>
                <tr>
                    <td><div class="sbw-cust-cell"><div class="sbw-cust-avatar" style="background:<?php echo esc_attr($col); ?>;color:#fff"><?php echo $init; ?></div><div class="sbw-cust-info"><strong><?php echo esc_html($c->name); ?></strong><small><?php echo esc_html($c->email?:'—'); ?></small></div></div></td>
                    <td class="sbw-hide-xs"><?php echo esc_html($c->phone?:'—'); ?></td>
                    <td><strong><?php echo intval($c->total_bookings); ?></strong></td>
                    <td class="sbw-hide-xs"><?php echo $c->total_spent>0?'<strong>'.number_format($c->total_spent).'</strong> '.esc_html($currency):'—'; ?></td>
                    <td class="sbw-hide-sm"><?php echo!empty($c->last_booking)?date('d M Y',strtotime($c->last_booking)):'—'; ?></td>
                    <td class="sbw-hide-sm" style="color:var(--sb-muted);font-size:.82rem"><?php echo date('d M Y',strtotime($c->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: CALENDAR
    ══════════════════════════════════════════ */
    private static function tab_calendar() {
        $staff_all=SB_DB::get_staff();
        ?>
        <?php self::head('Calendar','Appointment overview'); ?>
        <div class="sbw-card">
            <div class="sbw-cal-controls">
                <div>
                    <button class="sbw-btn sbw-cal-prev-week">&#8592; Prev</button>
                    <span class="sbw-cal-range" id="sbAdminCalRange"></span>
                    <button class="sbw-btn sbw-cal-next-week">Next &#8594;</button>
                </div>
                <div>
                    <select id="sbAdminCalStaff"><option value="0">All Staff</option><?php foreach($staff_all as $st): ?><option value="<?php echo $st->id; ?>"><?php echo esc_html($st->name); ?></option><?php endforeach; ?></select>
                    <button class="sbw-btn" id="sbAdminCalToday">Today</button>
                </div>
            </div>
            <div class="sbw-admin-cal" id="sbAdminCal"></div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: FINANCES
    ══════════════════════════════════════════ */
    private static function tab_finances() {
        global $wpdb;
        $t  = $wpdb->prefix . 'sb_bookings';
        $cur= get_option('sb_currency','FCFA');
        $today     = date('Y-m-d');
        $date_from = sanitize_text_field($_GET['date_from']??date('Y-m-01'));
        $date_to   = sanitize_text_field($_GET['date_to']  ??$today);
        if($date_from>$date_to) $date_from=$date_to;

        $earnings        =(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_price),0) FROM $t WHERE status='completed' AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $earnings_count  =(int)  $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='completed' AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $booking_value   =(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_price),0) FROM $t WHERE status IN ('confirmed','completed') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $bv_count        =(int)  $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status IN ('confirmed','completed') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $deposits_paid   =(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(deposit_amount),0) FROM $t WHERE payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $deposits_count  =(int)  $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $campay_gross    =(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(deposit_amount),0) FROM $t WHERE payment_method='campay' AND payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $campay_fee      =round($campay_gross*0.05);
        $campay_net      =$campay_gross-$campay_fee;
        $campay_txn_count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE payment_method='campay' AND payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));
        $outstanding     =(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(balance_amount),0) FROM $t WHERE status IN ('confirmed') AND payment_status NOT IN ('paid_in_full') AND booking_date BETWEEN %s AND %s",$date_from,$date_to));

        $daily_rows=$wpdb->get_results($wpdb->prepare("SELECT booking_date as d,SUM(total_price) as total FROM $t WHERE status='completed' AND booking_date BETWEEN %s AND %s GROUP BY booking_date ORDER BY booking_date ASC",$date_from,$date_to));
        $daily_map=array();foreach($daily_rows as $dr)$daily_map[$dr->d]=(float)$dr->total;
        $chart_days=array();$cursor=new DateTime($date_from);$end_dt=new DateTime($date_to);
        while($cursor<=$end_dt){$ds=$cursor->format('Y-m-d');$chart_days[]=array('d'=>$ds,'label'=>$cursor->format('d M'),'total'=>$daily_map[$ds]??0);$cursor->modify('+1 day');}
        $max_day=max(array_column($chart_days,'total')?:array(1));if($max_day<1)$max_day=1;
        $chart_display=count($chart_days)>30?array_filter($chart_days,function($i)use($chart_days){return $i%ceil(count($chart_days)/30)===0;},ARRAY_FILTER_USE_KEY):$chart_days;

        $top_svcs=$wpdb->get_results($wpdb->prepare("SELECT s.name,s.color,COUNT(b.id) as cnt,COALESCE(SUM(b.total_price),0) as total FROM $t b JOIN {$wpdb->prefix}sb_services s ON b.service_id=s.id WHERE b.status='completed' AND b.booking_date BETWEEN %s AND %s GROUP BY b.service_id ORDER BY total DESC LIMIT 6",$date_from,$date_to));
        $max_svc_total=$top_svcs?max(array_map(function($r){return(float)$r->total;},$top_svcs)):1;if($max_svc_total<1)$max_svc_total=1;

        $recent_txns=$wpdb->get_results($wpdb->prepare("SELECT b.id,b.customer_name,b.booking_date,b.deposit_amount,b.total_price,b.payment_status,b.payment_method,b.payment_ref,b.payment_date,b.status,b.internal_note,s.name as service_name,s.color as service_color FROM $t b JOIN {$wpdb->prefix}sb_services s ON b.service_id=s.id WHERE b.payment_status IN ('deposit_paid','paid_in_full') AND b.booking_date BETWEEN %s AND %s ORDER BY b.payment_date DESC LIMIT 20",$date_from,$date_to));

        $fin_url=self::section_permalink('finances');
        $presets=array(
            'today' =>array('label'=>'Today',       'from'=>$today,                              'to'=>$today),
            'week'  =>array('label'=>'This Week',   'from'=>date('Y-m-d',strtotime('monday this week')),'to'=>$today),
            'month' =>array('label'=>'This Month',  'from'=>date('Y-m-01'),                      'to'=>$today),
            'last30'=>array('label'=>'Last 30 Days','from'=>date('Y-m-d',strtotime('-30 days')), 'to'=>$today),
            'year'  =>array('label'=>'This Year',   'from'=>date('Y-01-01'),                     'to'=>$today),
        );
        ?>
        <?php self::head('Finances','Financial overview — '.date('d M Y',strtotime($date_from)).' to '.date('d M Y',strtotime($date_to))); ?>

        <div class="sbw-card sbw-fin-filter-card">
            <form method="get" action="<?php echo esc_url($fin_url); ?>" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <div class="sbw-fin-preset-btns">
                <?php foreach($presets as $k=>$p):$active=($date_from===$p['from']&&$date_to===$p['to']); ?>
                <a href="<?php echo esc_url(add_query_arg(array('date_from'=>$p['from'],'date_to'=>$p['to']),$fin_url)); ?>" class="sbw-tab <?php echo $active?'active':''; ?>"><?php echo $p['label']; ?></a>
                <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                    <div class="sbw-fg" style="margin:0"><label style="font-size:.74rem;font-weight:700;color:var(--sb-muted);text-transform:uppercase;letter-spacing:.05em">From</label><input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="padding:7px 11px;border:1.5px solid var(--sb-border);border-radius:8px;font-size:.86rem;background:#fff;font-family:inherit"></div>
                    <div class="sbw-fg" style="margin:0"><label style="font-size:.74rem;font-weight:700;color:var(--sb-muted);text-transform:uppercase;letter-spacing:.05em">To</label><input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="padding:7px 11px;border:1.5px solid var(--sb-border);border-radius:8px;font-size:.86rem;background:#fff;font-family:inherit"></div>
                    <button type="submit" class="sbw-btn">Apply</button>
                </div>
            </form>
        </div>

        <div class="sbw-fin-kpis">
            <div class="sbw-fin-kpi sbw-fin-kpi-green"><div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sbw-fin-kpi-body"><span class="sbw-fin-kpi-label">Earnings</span><span class="sbw-fin-kpi-val"><?php echo number_format($earnings); ?> <small><?php echo esc_html($cur); ?></small></span><span class="sbw-fin-kpi-sub"><?php echo $earnings_count; ?> completed booking<?php echo $earnings_count!=1?'s':''; ?></span></div></div>
            <div class="sbw-fin-kpi sbw-fin-kpi-purple"><div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-chart-bar"></span></div><div class="sbw-fin-kpi-body"><span class="sbw-fin-kpi-label">Booking Value</span><span class="sbw-fin-kpi-val"><?php echo number_format($booking_value); ?> <small><?php echo esc_html($cur); ?></small></span><span class="sbw-fin-kpi-sub"><?php echo $bv_count; ?> confirmed + completed</span></div></div>
            <div class="sbw-fin-kpi sbw-fin-kpi-blue"><div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div><div class="sbw-fin-kpi-body"><span class="sbw-fin-kpi-label">Deposits Collected</span><span class="sbw-fin-kpi-val"><?php echo number_format($deposits_paid); ?> <small><?php echo esc_html($cur); ?></small></span><span class="sbw-fin-kpi-sub"><?php echo $deposits_count; ?> payment<?php echo $deposits_count!=1?'s':''; ?> received</span></div></div>
            <div class="sbw-fin-kpi sbw-fin-kpi-amber"><div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-clock"></span></div><div class="sbw-fin-kpi-body"><span class="sbw-fin-kpi-label">Outstanding Balance</span><span class="sbw-fin-kpi-val"><?php echo number_format($outstanding); ?> <small><?php echo esc_html($cur); ?></small></span><span class="sbw-fin-kpi-sub">Confirmed but unpaid</span></div></div>
        </div>

        <?php if($campay_gross>0): ?>
        <div class="sbw-fin-campay-strip"><div class="sbw-fin-campay-icon"><span class="dashicons dashicons-smartphone"></span></div><div class="sbw-fin-campay-body"><span class="sbw-fin-campay-label">CamPay MoMo — <?php echo $campay_txn_count; ?> transaction<?php echo $campay_txn_count!=1?'s':''; ?> this period</span><div class="sbw-fin-campay-nums"><span>Collected: <strong><?php echo number_format($campay_gross); ?> <?php echo esc_html($cur); ?></strong></span><span class="sbw-fin-campay-sep">−</span><span>CamPay 5% fee: <strong style="color:var(--sb-red)"><?php echo number_format($campay_fee); ?> <?php echo esc_html($cur); ?></strong></span><span class="sbw-fin-campay-sep">=</span><span>Net: <strong style="color:var(--sb-green)"><?php echo number_format($campay_net); ?> <?php echo esc_html($cur); ?></strong></span></div></div></div>
        <?php endif; ?>

        <div class="sbw-cols">
        <div class="sbw-card" style="flex:1.7">
            <div class="sbw-card-head"><h2>Daily Earnings</h2></div>
            <?php if(array_sum(array_column($chart_days,'total'))===0.0): ?><div class="sbw-empty"><p>No completed earnings in this period.</p></div><?php else: ?>
            <div class="sbw-fin-chart-wrap"><div class="sbw-fin-bar-chart">
            <?php foreach($chart_display as $cd):$pct=max(round(($cd['total']/$max_day)*100),2);$is_today=$cd['d']===$today; ?>
            <div class="sbw-fin-bar-col" title="<?php echo esc_attr($cd['label']); ?>: <?php echo number_format($cd['total']); ?> <?php echo esc_attr($cur); ?>"><div class="sbw-fin-bar<?php echo $is_today?' today':''; ?>" style="height:<?php echo $pct; ?>%"></div><?php if(count($chart_display)<=14): ?><div class="sbw-fin-bar-lbl"><?php echo $cd['label']; ?></div><?php endif; ?></div>
            <?php endforeach; ?>
            </div></div>
            <?php endif; ?>
        </div>
        <div class="sbw-col-r">
            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Top Services</h2><span style="font-size:.78rem;color:var(--sb-muted)">by earnings</span></div>
                <?php if(empty($top_svcs)): ?><div class="sbw-empty"><p>No completed bookings in this period.</p></div><?php else: ?>
                <div class="sbw-top-svc">
                <?php foreach($top_svcs as $ts): ?>
                <div class="sbw-tsvc-row"><div class="sbw-tsvc-info"><span class="sbw-tsvc-name"><span class="sbw-dot" style="background:<?php echo esc_attr($ts->color??'#111111'); ?>"></span><?php echo esc_html($ts->name); ?></span><span class="sbw-tsvc-count" style="font-weight:700"><?php echo number_format((float)$ts->total); ?> <span style="color:var(--sb-muted);font-weight:500"><?php echo esc_html($cur); ?></span></span></div><div class="sbw-tsvc-bar-wrap"><div class="sbw-tsvc-bar" style="width:<?php echo round(((float)$ts->total/$max_svc_total)*100); ?>%"></div></div></div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sbw-card sbw-fin-summary">
                <div class="sbw-card-head"><h2>Period Summary</h2></div>
                <div class="sbw-fin-summary-rows">
                    <div class="sbw-fin-sumrow"><span>Total Bookings</span><strong><?php echo(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE booking_date BETWEEN %s AND %s",$date_from,$date_to)); ?></strong></div>
                    <div class="sbw-fin-sumrow"><span>Completed</span><strong style="color:var(--sb-green)"><?php echo $earnings_count; ?></strong></div>
                    <div class="sbw-fin-sumrow"><span>Pending</span><strong style="color:var(--sb-amber)"><?php echo(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='pending' AND booking_date BETWEEN %s AND %s",$date_from,$date_to)); ?></strong></div>
                    <div class="sbw-fin-sumrow"><span>Cancelled</span><strong style="color:var(--sb-red)"><?php echo(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='cancelled' AND booking_date BETWEEN %s AND %s",$date_from,$date_to)); ?></strong></div>
                    <div class="sbw-fin-sumrow sbw-fin-sumrow-total"><span>Collection Rate</span><strong><?php echo $booking_value>0?round(($earnings/$booking_value)*100).'%':'—'; ?></strong></div>
                </div>
            </div>
        </div>
        </div>

        <div class="sbw-card">
            <div class="sbw-card-head"><h2>Payment Transactions</h2><span style="font-size:.8rem;color:var(--sb-muted)"><?php echo count($recent_txns); ?> in period</span></div>
            <?php if(empty($recent_txns)): ?><div class="sbw-empty"><p>No deposits received in this period.</p></div><?php else: ?>
            <div class="sbw-tbl-wrap">
            <table class="sbw-tbl">
                <thead><tr><th>#</th><th>Customer</th><th>Service</th><th class="sbw-hide-sm">Booking</th><th class="sbw-hide-sm">Paid</th><th>Method</th><th>Deposit</th><th class="sbw-hide-xs">Fee</th><th class="sbw-hide-xs">Net</th><th>Status</th></tr></thead>
                <tbody>
                <?php
                $b_colors=array('#111111','#4f46e5','#0d9488','#d97706','#dc2626','#16a34a','#2563eb');
                $mlabels=array('campay'=>'CamPay','paystack'=>'Paystack','flutterwave'=>'Flutterwave','manual'=>'Manual');
                foreach($recent_txns as $tx):
                    $b_color=$b_colors[crc32($tx->customer_name)%count($b_colors)];
                    $init=strtoupper(substr(trim($tx->customer_name),0,1));
                    $ml=$mlabels[$tx->payment_method]??ucfirst($tx->payment_method);
                ?>
                <tr>
                    <td><span style="font-family:monospace;font-size:.77rem;color:var(--sb-muted)">#<?php echo $tx->id; ?></span></td>
                    <td><div class="sbw-cust-cell"><div class="sbw-cust-avatar" style="background:<?php echo $b_color; ?>"><?php echo $init; ?></div><div class="sbw-cust-info"><strong><?php echo esc_html($tx->customer_name); ?></strong></div></div></td>
                    <td><span class="sbw-dot" style="background:<?php echo esc_attr($tx->service_color??'#111111'); ?>"></span><span style="font-weight:600;font-size:.85rem"><?php echo esc_html($tx->service_name); ?></span></td>
                    <td class="sbw-hide-sm" style="font-size:.84rem"><?php echo date('d M Y',strtotime($tx->booking_date)); ?></td>
                    <td class="sbw-hide-sm" style="font-size:.82rem;color:var(--sb-muted)"><?php echo $tx->payment_date?date('d M Y, g:i A',strtotime($tx->payment_date)):'—'; ?></td>
                    <td><span class="sbw-fin-method-badge sbw-fin-method-<?php echo esc_attr($tx->payment_method); ?>"><?php echo esc_html($ml); ?></span></td>
                    <td><strong style="color:var(--sb-green)"><?php echo number_format((float)$tx->deposit_amount); ?> <?php echo esc_html($cur); ?></strong></td>
                    <?php if($tx->payment_method==='campay'&&(float)$tx->deposit_amount>0):$cp_fee=round((float)$tx->deposit_amount*0.05);$cp_net=(float)$tx->deposit_amount-$cp_fee; ?>
                    <td class="sbw-hide-xs" style="color:var(--sb-red);font-size:.82rem">−<?php echo number_format($cp_fee); ?></td>
                    <td class="sbw-hide-xs"><strong style="color:#15803d"><?php echo number_format($cp_net); ?> <?php echo esc_html($cur); ?></strong></td>
                    <?php else: ?><td class="sbw-hide-xs" style="color:var(--sb-muted)">—</td><td class="sbw-hide-xs" style="color:var(--sb-muted)">—</td><?php endif; ?>
                    <td><span class="sbw-badge sbw-<?php echo esc_attr($tx->status); ?>"><?php echo ucfirst(str_replace('_',' ',$tx->status)); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════
       SECTION: SETTINGS
    ══════════════════════════════════════════ */
    private static function tab_settings() {
        $s    = get_option('sb_settings',  array());
        $ap   = get_option('sb_appearance',array());
        $saved= !empty($_GET['sb_saved']);
        ?>
        <?php self::head('Settings'); ?>
        <?php if($saved): ?><div class="sbw-notice sbw-notice-ok">✅ Settings saved successfully.</div><?php endif; ?>
        <form id="sbSettingsForm">
        <?php wp_nonce_field('sb_adm','nonce'); ?>
        <input type="hidden" name="action" value="sb_save_settings">

        <div class="sbw-settings-section"><div class="sbw-settings-section-head"><div class="sbw-ss-icon"><span class="dashicons dashicons-admin-home"></span></div><h3>General</h3></div><div class="sbw-settings-section-body">
            <div class="sbw-row2"><div class="sbw-fg"><label>Business Name</label><input type="text" name="business_name" value="<?php echo esc_attr($s['business_name']??get_bloginfo('name')); ?>"></div><div class="sbw-fg"><label>Currency</label><select name="currency"><?php $cur=get_option('sb_currency','FCFA');foreach(['FCFA','XAF','USD','EUR','GHS','NGN','KES','ZAR'] as $c): ?><option value="<?php echo $c; ?>" <?php selected($cur,$c); ?>><?php echo $c; ?></option><?php endforeach; ?></select></div></div>
            <div class="sbw-row2"><div class="sbw-fg"><label>Admin Notification Email</label><input type="email" name="notify_email" value="<?php echo esc_attr($s['notify_email']??get_option('admin_email')); ?>"></div><div class="sbw-fg"><label>WhatsApp Business Number</label><input type="tel" name="whatsapp" value="<?php echo esc_attr(get_option('sb_whatsapp','')); ?>" placeholder="e.g. 237678899434"></div></div>
        </div></div>

        <div class="sbw-settings-section"><div class="sbw-settings-section-head"><div class="sbw-ss-icon"><span class="dashicons dashicons-forms"></span></div><h3>Booking Form</h3></div><div class="sbw-settings-section-body">
            <div class="sbw-row2"><div class="sbw-fg"><label>Step 1 Heading</label><input type="text" name="step1_heading" value="<?php echo esc_attr(get_option('sb_step1_heading','Choose a Service')); ?>"></div><div class="sbw-fg"><label>Service Label</label><input type="text" name="service_label" value="<?php echo esc_attr($s['service_label']??'Choose a Service'); ?>"></div></div>
            <div class="sbw-row2"><div class="sbw-fg"><label>Form Max Width (px)</label><input type="number" name="form_max_width" min="400" max="1400" value="<?php echo esc_attr(get_option('sb_form_max_width',900)); ?>"></div><div class="sbw-fg"><label style="font-weight:500;display:block;margin-bottom:8px">Features</label><div style="display:flex;flex-direction:column;gap:8px"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400"><input type="checkbox" name="enable_staff" value="1" <?php checked(!empty($s['enable_staff'])); ?>> Enable Staff selection</label><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400"><input type="checkbox" name="enable_days" value="1" <?php checked(!empty($s['enable_days'])); ?>> Enable multi-day booking</label><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400"><input type="checkbox" name="show_categories" value="1" <?php checked(!empty(get_option('sb_show_categories'))); ?>> Show service categories</label><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400"><input type="checkbox" name="require_staff" value="1" <?php checked(!empty(get_option('sb_require_staff',1))); ?>> Require staff selection</label></div></div></div>
        </div></div>

        <div class="sbw-settings-section"><div class="sbw-settings-section-head"><div class="sbw-ss-icon"><span class="dashicons dashicons-money-alt"></span></div><h3>Deposit &amp; Policies</h3></div><div class="sbw-settings-section-body">
            <div class="sbw-row2"><div class="sbw-fg"><label>Default Deposit %</label><input type="number" name="default_deposit_pct" min="0" max="100" value="<?php echo esc_attr(get_option('sb_default_deposit_pct',0)); ?>"></div><div class="sbw-fg"><label>Cancellation / Late Policy</label><textarea name="appointment_rules" rows="3"><?php echo esc_textarea(get_option('sb_appointment_rules','')); ?></textarea></div></div>
            <div class="sbw-row2"><div class="sbw-fg"><label>Refund &amp; Deposit Policy</label><textarea name="refund_text" rows="3"><?php echo esc_textarea(get_option('sb_refund_text','')); ?></textarea></div><div class="sbw-fg"><label>Terms &amp; Conditions</label><textarea name="terms_text" rows="3"><?php echo esc_textarea(get_option('sb_terms_text','')); ?></textarea></div></div>
            <div class="sbw-fg"><label>Privacy Policy</label><textarea name="privacy_text" rows="3"><?php echo esc_textarea(get_option('sb_privacy_text','')); ?></textarea></div>
        </div></div>

        <div class="sbw-settings-section"><div class="sbw-settings-section-head"><div class="sbw-ss-icon"><span class="dashicons dashicons-format-chat"></span></div><h3>Floating Contact Button</h3></div><div class="sbw-settings-section-body">
            <div class="sbw-row2"><div class="sbw-fg"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;margin-bottom:10px"><input type="checkbox" name="float_on" value="1" <?php checked(!empty($s['float_on'])); ?>> <strong>Enable floating button</strong></label><label>Type</label><select name="float_type"><option value="whatsapp" <?php selected($s['float_type']??'whatsapp','whatsapp'); ?>>WhatsApp</option><option value="phone" <?php selected($s['float_type']??'','phone'); ?>>Phone Call</option></select></div><div class="sbw-fg"><label>Phone / WhatsApp Number</label><input type="tel" name="float_number" value="<?php echo esc_attr($s['float_number']??''); ?>" placeholder="237678899434"><label style="margin-top:10px;display:block">Button Label</label><input type="text" name="float_label" value="<?php echo esc_attr($s['float_label']??'Chat with us'); ?>"></div></div>
        </div></div>

        <?php if ( SB_Roles::is_business_owner() ) : ?>
        <div style="background:#f0f4ff;border-left:4px solid #6366f1;border-radius:6px;padding:12px 16px;margin-bottom:18px;font-size:.875rem;color:#3730a3;">
            <!-- <strong>ℹ️ Developer Settings</strong> — Payment gateway credentials, API keys, and WhatsApp API tokens are managed by your developer or administrator and are not shown here. -->
        </div>
        <?php endif; ?>

        <?php /* ── DEVELOPER-ONLY SETTINGS (hidden from frontend portal) ──────────────
           Payment Gateways (CamPay, Paystack, Flutterwave) and WhatsApp API
           credentials are managed via the WordPress admin dashboard only.
           They are intentionally not exposed here for security reasons.
        */ ?>

        <div class="sbw-settings-section"><div class="sbw-settings-section-head"><div class="sbw-ss-icon"><span class="dashicons dashicons-art"></span></div><h3>Appearance &amp; Colors</h3></div><div class="sbw-settings-section-body">
            <div class="sbw-row3">
                <div class="sbw-fg"><label>Primary Color</label><input type="text" name="ap_primary" class="sbw-color" value="<?php echo esc_attr($ap['primary']??'#111111'); ?>"></div>
                <div class="sbw-fg"><label>Header Bar Color</label><input type="text" name="ap_p_dark" class="sbw-color" value="<?php echo esc_attr($ap['p_dark']??'#3b1a5c'); ?>"></div>
                <div class="sbw-fg"><label>Header Text Color</label><input type="text" name="ap_hd_text" class="sbw-color" value="<?php echo esc_attr($ap['hd_text']??'#ffffff'); ?>"></div>
                <div class="sbw-fg"><label>Accent Color</label><input type="text" name="ap_accent" class="sbw-color" value="<?php echo esc_attr($ap['accent']??'#C9A84C'); ?>"></div>
                <div class="sbw-fg"><label>Hover Color</label><input type="text" name="ap_hover" class="sbw-color" value="<?php echo esc_attr($ap['hover']??'#3b1a5c'); ?>"></div>
                <div class="sbw-fg"><label>Form Background</label><input type="text" name="ap_bg" class="sbw-color" value="<?php echo esc_attr($ap['bg']??'#ffffff'); ?>"></div>
                <div class="sbw-fg"><label>Body Text Color</label><input type="text" name="ap_text" class="sbw-color" value="<?php echo esc_attr($ap['text']??'#1a1a2e'); ?>"></div>
                <div class="sbw-fg"><label>Button Text Color</label><input type="text" name="ap_btn_text" class="sbw-color" value="<?php echo esc_attr($ap['btn_text']??'#ffffff'); ?>"></div>
                <div class="sbw-fg"><label>Corner Radius (px)</label><input type="number" name="ap_radius" value="<?php echo esc_attr($ap['radius']??10); ?>" min="0" max="30"></div>
            </div>
        </div></div>

        <?php /* Active Portals / Active Pages section — hidden from frontend */ ?>

        <div style="padding:18px 0 10px">
            <button type="submit" class="sbw-btn" style="padding:10px 34px;font-size:.95rem">Save All Settings</button>
        </div>
        </form>
        <?php
    }
}