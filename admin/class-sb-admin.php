<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Admin {

    private static $done = false;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menus' ) );
        if ( ! self::$done ) {
            add_action( 'wp_ajax_sb_save_service',     array( $this, 'ajax_save_service' ) );
            add_action( 'wp_ajax_sb_delete_service',   array( $this, 'ajax_delete_service' ) );
            add_action( 'wp_ajax_sb_save_category',    array( $this, 'ajax_save_category' ) );
            add_action( 'wp_ajax_sb_save_staff',       array( $this, 'ajax_save_staff' ) );
            add_action( 'wp_ajax_sb_delete_staff',     array( $this, 'ajax_delete_staff' ) );
            add_action( 'wp_ajax_sb_save_hours',       array( $this, 'ajax_save_hours' ) );
            add_action( 'wp_ajax_sb_update_status',    array( $this, 'ajax_update_status' ) );
            add_action( 'wp_ajax_sb_delete_booking',   array( $this, 'ajax_delete_booking' ) );
            add_action( 'wp_ajax_sb_save_settings',    array( $this, 'ajax_save_settings' ) );
            add_action( 'wp_ajax_sb_upload_image',     array( $this, 'ajax_upload_image' ) );
            add_action( 'wp_ajax_sb_test_whatsapp',    array( $this, 'ajax_test_whatsapp' ) );
            add_action( 'wp_ajax_sb_test_campay',      array( $this, 'ajax_test_campay' ) );
            self::$done = true;
        }
    }

    public function menus() {
        add_menu_page( 'Smart Booking', 'Smart Booking', 'manage_options',
            'smartbooking', array( $this, 'page_dashboard' ), 'dashicons-calendar-alt', 25 );
        add_submenu_page( 'smartbooking', 'Dashboard',  'Dashboard',  'manage_options', 'smartbooking',   array( $this, 'page_dashboard' ) );
        add_submenu_page( 'smartbooking', 'Bookings',   'Bookings',   'manage_options', 'sb-bookings',    array( $this, 'page_bookings' ) );
        add_submenu_page( 'smartbooking', 'Services',   'Services',   'manage_options', 'sb-services',    array( $this, 'page_services' ) );
        add_submenu_page( 'smartbooking', 'Staff',      'Staff',      'manage_options', 'sb-staff',       array( $this, 'page_staff' ) );
        add_submenu_page( 'smartbooking', 'Customers',  'Customers',  'manage_options', 'sb-customers',   array( $this, 'page_customers' ) );
        add_submenu_page( 'smartbooking', 'Calendar',   'Calendar',   'manage_options', 'sb-calendar',    array( $this, 'page_calendar_redirect' ) );
        add_submenu_page( 'smartbooking', 'Finances',   'Finances',   'manage_options', 'sb-finances',    array( $this, 'page_finances' ) );
        add_submenu_page( 'smartbooking', 'Settings',   'Settings',   'manage_options', 'sb-settings',    array( $this, 'page_settings' ) );
    }

    private function head( $title, $sub = '' ) {
        echo '<div class="sbw-head"><div><h1>' . esc_html($title) . '</h1>'
           . ( $sub ? '<p class="sbw-sub">' . esc_html($sub) . '</p>' : '' )
           . '</div><span class="sbw-ver">v' . SB_VER . '</span></div>';
    }

    //  DASHBOARD
    public function page_dashboard() {
        $s   = SB_DB::get_stats();
        $cur = get_option( 'sb_currency', 'FCFA' );
        $today_bookings = SB_DB::get_bookings( array( 'date' => date('Y-m-d') ) );
        $pending        = SB_DB::get_bookings( array( 'status' => 'pending' ) );

        // Last 7 days chart data
        $chart_days = array();
        for ( $i = 6; $i >= 0; $i-- ) {
            $d = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $day_bookings = SB_DB::get_bookings( array( 'date' => $d ) );
            $chart_days[] = array(
                'label' => date( 'D', strtotime( $d ) ),
                'count' => count( $day_bookings ),
                'today' => $i === 0,
            );
        }
        $max_chart = max( array_column( $chart_days, 'count' ) );
        if ( $max_chart < 1 ) $max_chart = 1;

        // Top services this month
        global $wpdb;
        $month_start = date('Y-m-01');
        $top_svcs = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.name, s.color, COUNT(b.id) as cnt
             FROM {$wpdb->prefix}sb_bookings b
             JOIN {$wpdb->prefix}sb_services s ON b.service_id = s.id
             WHERE b.booking_date >= %s
             GROUP BY b.service_id ORDER BY cnt DESC LIMIT 5",
            $month_start
        ) );
        $max_svc = ( $top_svcs && $top_svcs[0]->cnt > 0 ) ? $top_svcs[0]->cnt : 1;
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Dashboard', 'Welcome back &mdash; ' . date('l, d F Y') ); ?>

        <div class="sbw-stats">
            <div class="sbw-stat sbw-stat-purple">
                <div class="sbw-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                <div class="sbw-stat-body"><b><?php echo $s['total']; ?></b><small>Total Bookings</small></div>
            </div>
            <div class="sbw-stat sbw-stat-amber">
                <div class="sbw-stat-icon"><span class="dashicons dashicons-clock"></span></div>
                <div class="sbw-stat-body"><b><?php echo $s['pending']; ?></b><small>Pending</small></div>
            </div>
            <div class="sbw-stat sbw-stat-green">
                <div class="sbw-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="sbw-stat-body"><b><?php echo $s['confirmed']; ?></b><small>Confirmed</small></div>
            </div>
            <div class="sbw-stat sbw-stat-blue">
                <div class="sbw-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="sbw-stat-body"><b><?php echo number_format($s['revenue']); ?></b><small><?php echo esc_html($cur); ?> Revenue</small></div>
            </div>
            <div class="sbw-stat sbw-stat-indigo">
                <div class="sbw-stat-icon"><span class="dashicons dashicons-admin-users"></span></div>
                <div class="sbw-stat-body"><b><?php echo $s['customers']; ?></b><small>Customers</small></div>
            </div>
            <div class="sbw-stat sbw-stat-teal">
                <div class="sbw-stat-icon"><span class="dashicons dashicons-businessman"></span></div>
                <div class="sbw-stat-body"><b><?php echo $s['staff']; ?></b><small>Staff Members</small></div>
            </div>
        </div>

        <div class="sbw-cols">
        <div class="sbw-card" style="flex:1.6">

            <div class="sbw-card-head">
                <h2>Bookings — Last 7 Days</h2>
                <a href="<?php echo admin_url('admin.php?page=sb-bookings'); ?>" class="sbw-lnk">View All →</a>
            </div>
            <div class="sbw-chart-wrap">
                <div class="sbw-chart-title">Daily Bookings</div>
                <div class="sbw-bar-chart">
                <?php foreach ( $chart_days as $cd ):
                    $pct = round( ($cd['count'] / $max_chart) * 72 );
                    $pct = max( $pct, 4 );
                ?>
                <div class="sbw-bar-col">
                    <div class="sbw-bar-val"><?php echo $cd['count'] > 0 ? $cd['count'] : ''; ?></div>
                    <div class="sbw-bar<?php echo $cd['today'] ? ' today' : ''; ?>" style="height:<?php echo $pct; ?>px;<?php echo $cd['today'] ? '' : 'opacity:.5'; ?>"></div>
                    <div class="sbw-bar-day<?php echo $cd['today'] ? ' today-lbl' : ''; ?>"><?php echo $cd['label']; ?></div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            <div class="sbw-card-head" style="margin-top:10px">
                <h2>Today's Schedule <span style="color:#6b7280;font-weight:400;font-size:.84rem">(<?php echo count($today_bookings); ?> appointment<?php echo count($today_bookings)!=1?'s':''; ?>)</span></h2>
                <a href="<?php echo admin_url('admin.php?page=sb-calendar'); ?>" class="sbw-lnk">Calendar →</a>
            </div>
            <?php if ( empty($today_bookings) ): ?>
            <div class="sbw-empty"><p>No appointments scheduled today.</p></div>
            <?php else: ?>
            <div class="sbw-timeline">
            <?php foreach ( $today_bookings as $b ): ?>
                <div class="sbw-appt" style="border-left-color:<?php echo esc_attr($b->service_color ?? '#111111'); ?>">
                    <div class="sbw-appt-time"><?php echo date('g:i A', strtotime($b->start_time)); ?></div>
                    <div class="sbw-appt-info">
                        <strong><?php echo esc_html($b->customer_name); ?></strong>
                        <span><?php echo esc_html($b->service_name); ?><?php if ($b->staff_name): ?> &middot; <?php echo esc_html($b->staff_name); ?><?php endif; ?></span>
                    </div>
                    <span class="sbw-badge sbw-<?php echo esc_attr($b->status); ?>"><?php echo ucfirst($b->status); ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="sbw-col-r">

            <div class="sbw-card">
                <div class="sbw-card-head">
                    <h2>Pending Actions <?php if($s['pending']>0): ?><span style="background:#fef3c7;color:#92400e;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:6px"><?php echo $s['pending']; ?></span><?php endif; ?></h2>
                </div>
                <?php if ( empty($pending) ): ?>
                <div class="sbw-empty"><p>No pending bookings.</p></div>
                <?php else: ?>
                <div class="sbw-pending-list">
                <?php foreach ( array_slice($pending, 0, 6) as $b ): ?>
                    <div class="sbw-pend-row" data-id="<?php echo $b->id; ?>">
                        <div style="min-width:0;flex:1">
                            <strong><?php echo esc_html($b->customer_name); ?></strong>
                            <span class="sbw-pend-meta"><?php echo esc_html($b->service_name); ?> &middot; <?php echo date('d M', strtotime($b->booking_date)); ?> <?php echo date('g:i A', strtotime($b->start_time)); ?></span>
                        </div>
                        <div class="sbw-pend-actions">
                            <button class="sbw-ab sbw-confirm" data-id="<?php echo $b->id; ?>">Confirm</button>
                            <button class="sbw-ab sbw-cancel"  data-id="<?php echo $b->id; ?>">Cancel</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php if ( count($pending) > 6 ): ?>
                <div style="padding:10px 0 0;text-align:center">
                    <a href="<?php echo admin_url('admin.php?page=sb-bookings&status=pending'); ?>" class="sbw-lnk">View all <?php echo count($pending); ?> pending &rarr;</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ( ! empty($top_svcs) ): ?>
            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Top Services <span style="color:#6b7280;font-weight:400;font-size:.8rem">this month</span></h2></div>
                <div class="sbw-top-svc">
                <?php foreach ( $top_svcs as $ts ): ?>
                <div class="sbw-tsvc-row">
                    <div class="sbw-tsvc-info">
                        <span class="sbw-tsvc-name">
                            <span class="sbw-dot" style="background:<?php echo esc_attr($ts->color ?? '#111111'); ?>"></span>
                            <?php echo esc_html($ts->name); ?>
                        </span>
                        <span class="sbw-tsvc-count"><?php echo $ts->cnt; ?> booking<?php echo $ts->cnt != 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="sbw-tsvc-bar-wrap">
                        <div class="sbw-tsvc-bar" style="width:<?php echo round(($ts->cnt / $max_svc) * 100); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Quick Actions</h2></div>
                <div class="sbw-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=sb-bookings'); ?>" class="sbw-qa"><span class="dashicons dashicons-list-view"></span> Bookings</a>
                    <a href="<?php echo admin_url('admin.php?page=sb-services'); ?>" class="sbw-qa"><span class="dashicons dashicons-tag"></span> Services</a>
                    <a href="<?php echo admin_url('admin.php?page=sb-staff'); ?>" class="sbw-qa"><span class="dashicons dashicons-businessman"></span> Staff</a>
                    <a href="<?php echo admin_url('admin.php?page=sb-customers'); ?>" class="sbw-qa"><span class="dashicons dashicons-admin-users"></span> Customers</a>
                    <a href="<?php echo admin_url('admin.php?page=sb-calendar'); ?>" class="sbw-qa"><span class="dashicons dashicons-calendar-alt"></span> Calendar</a>
                    <a href="<?php echo admin_url('admin.php?page=sb-settings'); ?>" class="sbw-qa"><span class="dashicons dashicons-admin-settings"></span> Settings</a>
                </div>
            </div>
        </div>

        </div>
        </div><?php
    }

    //  BOOKINGS 
    public function page_bookings() {
        $status   = sanitize_text_field( $_GET['status']  ?? '' );
        $service  = intval( $_GET['service'] ?? 0 );
        $staff    = intval( $_GET['staff']   ?? 0 );
        $args     = array_filter( array( 'status' => $status, 'service_id' => $service, 'staff_id' => $staff ) );
        $bookings = SB_DB::get_bookings( $args );
        $services = SB_DB::get_services();
        $staff_all = SB_DB::get_staff();
        $currency = get_option( 'sb_currency', 'FCFA' );
        $tabs     = array( '' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled' );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Bookings', count($bookings) . ' booking' . (count($bookings)!=1?'s':'') ); ?>

        <div class="sbw-filters">
            <div class="sbw-tabs">
                <?php foreach ( $tabs as $k => $l ):
                    $active = $status === $k ? 'active' : '';
                    $url    = admin_url( 'admin.php?page=sb-bookings' . ($k ? "&status=$k" : '') . ($service ? "&service=$service" : '') . ($staff ? "&staff=$staff" : '') );
                ?>
                <a href="<?php echo esc_url($url); ?>" class="sbw-tab <?php echo $active; ?>"><?php echo $l; ?></a>
                <?php endforeach; ?>
            </div>
            <div class="sbw-filter-selects">
                <select onchange="location.href=this.value">
                    <option value="<?php echo admin_url('admin.php?page=sb-bookings&status='.esc_attr($status)); ?>">All services</option>
                    <?php foreach ( $services as $sv ): ?>
                    <option value="<?php echo admin_url('admin.php?page=sb-bookings&status='.esc_attr($status).'&service='.$sv->id); ?>" <?php selected($service, $sv->id); ?>><?php echo esc_html($sv->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select onchange="location.href=this.value">
                    <option value="<?php echo admin_url('admin.php?page=sb-bookings&status='.esc_attr($status)); ?>">All staff</option>
                    <?php foreach ( $staff_all as $st ): ?>
                    <option value="<?php echo admin_url('admin.php?page=sb-bookings&status='.esc_attr($status).'&staff='.$st->id); ?>" <?php selected($staff, $st->id); ?>><?php echo esc_html($st->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="sbw-card">
        <?php if ( empty($bookings) ): ?>
        <div class="sbw-empty"><p>No bookings found.</p></div>
        <?php else: ?>
        <table class="sbw-tbl">
            <thead><tr><th>#</th><th>Client</th><th>Service</th><th>Staff</th><th>Date &amp; Arrival Time</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ( $bookings as $b ): ?>
            <?php
                $b_initials = strtoupper(substr(trim($b->customer_name),0,1));
                $b_colors = array('#111111','#4f46e5','#0d9488','#d97706','#dc2626','#16a34a','#2563eb');
                $b_color = $b_colors[crc32($b->customer_name) % count($b_colors)];
            ?>
            <tr data-id="<?php echo $b->id; ?>">
                <td><span style="font-family:monospace;font-size:.78rem;color:#8b91a7;font-weight:600">#<?php echo $b->id; ?></span></td>
                <td>
                    <div class="sbw-cust-cell">
                        <div class="sbw-cust-avatar" style="background:<?php echo $b_color; ?>"><?php echo $b_initials; ?></div>
                        <div class="sbw-cust-info">
                            <strong><?php echo esc_html($b->customer_name); ?></strong>
                            <?php if ($b->customer_phone): ?><small><?php echo esc_html($b->customer_phone); ?></small><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="sbw-dot" style="background:<?php echo esc_attr($b->service_color ?? '#111111'); ?>"></span>
                    <span style="font-weight:600;font-size:.86rem"><?php echo esc_html($b->service_name); ?></span>
                </td>
                <td style="color:#4b5563;font-size:.84rem"><?php echo esc_html($b->staff_name ?? '—'); ?></td>
                <td>
                    <span style="font-weight:600;font-size:.86rem"><?php echo date('d M Y', strtotime($b->booking_date)); ?></span>
                    <br><small style="color:#8b91a7"><?php echo date('g:i A', strtotime($b->start_time)); ?> – <?php echo date('g:i A', strtotime($b->end_time)); ?></small>
                    <?php if ( ! empty($b->num_days) && intval($b->num_days) > 1 ): ?><br><small style="color:#111111;font-weight:600"><?php echo intval($b->num_days); ?> Days</small><?php endif; ?>
                </td>
                <td><strong><?php echo $b->total_price > 0 ? number_format($b->total_price) . ' ' . esc_html($currency) : '—'; ?></strong></td>
                <td><span class="sbw-badge sbw-<?php echo esc_attr($b->status); ?>"><?php echo ucfirst(str_replace('_',' ',$b->status)); ?></span></td>
                <td class="sbw-acts">
                    <select class="sbw-status-sel" data-id="<?php echo $b->id; ?>">
                        <?php foreach ( array('pending','confirmed','completed','cancelled','no_show') as $st ): ?>
                        <option value="<?php echo $st; ?>" <?php selected($b->status,$st); ?>><?php echo ucfirst(str_replace('_',' ',$st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="sbw-del-btn" data-id="<?php echo $b->id; ?>" title="Delete"></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
        </div><?php
    }

    //  SERVICES 
    public function page_services() {
        $services = SB_DB::get_services();
        $cats     = SB_DB::get_categories();
        $currency = get_option( 'sb_currency', 'FCFA' );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Services', count($services) . ' service' . (count($services)!=1?'s':'') . ' — manage your bookable offerings' ); ?>

        <div class="sbw-cols">
        <!-- SERVICE LIST -->
        <div class="sbw-card" style="flex:1.5">
            <div class="sbw-card-head"><h2>Your Services</h2>
                <button class="sbw-btn" id="sbAddServiceBtn">+ Add Service</button>
            </div>
            <?php if ( empty($services) ): ?>
            <div class="sbw-empty"><p>No services yet. Add your first service →</p></div>
            <?php else: ?>
            <table class="sbw-tbl">
                <thead><tr><th>Service</th><th>Duration</th><th>Price</th><th>Deposit</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($services as $sv): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:11px">
                        <?php if (!empty($sv->image_url)): ?>
                        <img src="<?php echo esc_url($sv->image_url); ?>" style="width:42px;height:42px;border-radius:8px;object-fit:cover;flex-shrink:0;border:1px solid #ede0ff" alt="">
                        <?php else: ?>
                        <span style="width:42px;height:42px;border-radius:8px;background:<?php echo esc_attr($sv->color); ?>;flex-shrink:0;display:inline-block"></span>
                        <?php endif; ?>
                        <div>
                            <strong style="display:block;font-size:.9rem"><?php echo esc_html($sv->name); ?></strong>
                            <?php if ($sv->category_name): ?><small style="color:#8b91a7;font-size:.76rem;font-weight:500"><?php echo esc_html($sv->category_name); ?></small><?php endif; ?>
                        </div>
                        </div>
                    </td>
                    <td style="color:#4b5563;font-size:.84rem"><?php echo SB_Services::format_duration($sv->duration); ?></td>
                    <td><strong><?php echo $sv->price > 0 ? number_format($sv->price).' '.esc_html($currency) : 'Free'; ?></strong></td>
                    <td style="color:#4b5563"><?php echo $sv->deposit_pct > 0 ? $sv->deposit_pct.'%' : '—'; ?></td>
                    <td><span class="sbw-badge sbw-<?php echo $sv->status; ?>"><?php echo ucfirst($sv->status); ?></span></td>
                    <td>
                        <button class="sbw-edit-svc" data-id="<?php echo $sv->id; ?>" data-name="<?php echo esc_attr($sv->name); ?>"
                            data-duration="<?php echo $sv->duration; ?>" data-padding="<?php echo $sv->padding_time; ?>"
                            data-price="<?php echo $sv->price; ?>" data-deposit="<?php echo $sv->deposit_pct; ?>"
                            data-color="<?php echo esc_attr($sv->color); ?>" data-cat="<?php echo $sv->category_id; ?>"
                            data-desc="<?php echo esc_attr($sv->description); ?>" data-cap="<?php echo $sv->capacity; ?>"
                            data-status="<?php echo $sv->status; ?>"
                            data-image="<?php echo esc_attr($sv->image_url ?? ''); ?>" data-policy="<?php echo esc_attr($sv->service_policy ?? ''); ?>"
                            data-gallery="<?php echo esc_attr($sv->gallery_images ?? ''); ?>">Edit</button>
                        <button class="sbw-del-svc" data-id="<?php echo $sv->id; ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- CATEGORIES -->
        <div class="sbw-card" style="flex:.8">
            <div class="sbw-card-head"><h2>Categories</h2></div>
            <div class="sbw-cat-list">
            <?php foreach ($cats as $c): ?>
            <div class="sbw-cat-row">
                <span><?php echo esc_html($c->name); ?></span>
                <button class="sbw-save-cat" data-id="<?php echo $c->id; ?>" data-name="<?php echo esc_attr($c->name); ?>">Edit</button>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="sbw-cat-add">
                <input type="text" id="sbNewCatName" placeholder="New category name">
                <button class="sbw-btn" id="sbAddCatBtn">Add</button>
            </div>
        </div>
        </div>

        <!-- SERVICE MODAL -->
        <div class="sbw-modal" id="sbServiceModal" style="display:none">
            <div class="sbw-modal-box">
                <div class="sbw-modal-head"><h3 id="sbSvcModalTitle">Add Service</h3><button class="sbw-modal-close"></button></div>
                <div class="sbw-modal-body">
                    <input type="hidden" id="sbSvcId">
                    <div class="sbw-row2">
                        <div class="sbw-fg"><label>Name *</label><input type="text" id="sbSvcName"></div>
                        <div class="sbw-fg"><label>Category</label>
                            <select id="sbSvcCat">
                                <option value="0">Uncategorised</option>
                                <?php foreach ($cats as $c): ?><option value="<?php echo $c->id; ?>"><?php echo esc_html($c->name); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="sbw-fg"><label>Description</label><textarea id="sbSvcDesc" rows="2"></textarea></div>
                    <div class="sbw-fg"><label>Service Policy / Terms (shown to client before booking)</label><textarea id="sbSvcPolicy" rows="3" placeholder="e.g. No refunds after confirmation. Please arrive 5 minutes early. Patch test required 48h before."></textarea></div>
                    <div class="sbw-row3">
                        <div class="sbw-fg"><label>Duration (min) *</label><input type="number" id="sbSvcDuration" value="60" min="5"></div>
                        <div class="sbw-fg"><label>Buffer (min) <small style="color:#64748b;font-weight:400">Gap after booking</small></label><input type="number" id="sbSvcPadding" value="0" min="0"></div>
                        <div class="sbw-fg"><label>Max bookings</label><input type="number" id="sbSvcCap" value="1" min="1"></div>
                    </div>
                    <div class="sbw-row3">
                        <div class="sbw-fg"><label>Price (<?php echo esc_html($currency); ?>)</label><input type="number" id="sbSvcPrice" value="0" min="0"></div>
                        <div class="sbw-fg"><label>Deposit %</label><input type="number" id="sbSvcDeposit" value="0" min="0" max="100"></div>
                        <div class="sbw-fg"><label>Color</label><input type="text" id="sbSvcColor" class="sbw-color" value="#111111"></div>
                    </div>
                    <div class="sbw-fg"><label>Status</label>
                        <select id="sbSvcStatus"><option value="active">Active</option><option value="disabled">Disabled</option></select>
                    </div>
                    <div class="sbw-fg">
                        <label>Service Image <small></small></label>
                        <div class="sbw-img-row">
                            <div class="sbw-img-preview" id="sbSvcImgPreview">
                                <span class="sbw-img-ph">No image</span>
                            </div>
                            <div class="sbw-img-btns">
                                <button type="button" class="sbw-btn" id="sbSvcPickImg">Choose Image</button>
                                <button type="button" class="sbw-btn-ghost" id="sbSvcRemoveImg" style="display:none">Remove</button>
                            </div>
                        </div>
                        <input type="hidden" id="sbSvcImageUrl" value="">
                    </div>
                    <div class="sbw-fg">
                        <label>Gallery Images <small style="color:#64748b">(optional — shown under main image in popup)</small></label>
                        <div id="sbGalleryPreview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px"></div>
                        <button type="button" class="sbw-btn" id="sbSvcAddGalleryImg">+ Add Gallery Image</button>
                        <input type="hidden" id="sbSvcGalleryUrls" value="">
                    </div>
                </div>
                <div class="sbw-modal-foot">
                    <button class="sbw-btn-ghost sbw-modal-close">Cancel</button>
                    <button class="sbw-btn" id="sbSaveSvcBtn">Save Service</button>
                </div>
            </div>
        </div>
        </div><?php
    }

    //  STAFF 
    public function page_staff() {
        $staff_all = SB_DB::get_staff();
        $all_services = SB_DB::get_services( array('status'=>'active') );
        $days = array( 0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday' );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Staff Members', 'Manage who provides your services' ); ?>

        <div class="sbw-cols">
        <!-- STAFF LIST -->
        <div class="sbw-card" style="flex:1.5">
            <div class="sbw-card-head"><h2>Staff</h2>
                <button class="sbw-btn" id="sbAddStaffBtn">+ Add Staff</button>
            </div>
            <?php if ( empty($staff_all) ): ?>
            <div class="sbw-empty"><p>No staff yet. Add your first team member.</p></div>
            <?php else: ?>
            <div class="sbw-staff-grid">
            <?php foreach ($staff_all as $st):
                $svc_ids = SB_DB::get_services_for_staff($st->id);
                $hours   = SB_DB::get_working_hours($st->id);
            ?>
            <div class="sbw-staff-card">
                <div class="sbw-staff-avatar" style="background:<?php echo esc_attr($st->color); ?>">
                    <?php if ($st->image_url): ?><img src="<?php echo esc_url($st->image_url); ?>" alt="">
                    <?php else: echo strtoupper(substr($st->name,0,1)); endif; ?>
                </div>
                <div class="sbw-staff-info">
                    <strong><?php echo esc_html($st->name); ?></strong>
                    <span><?php echo esc_html($st->email ?: $st->phone ?: 'No contact'); ?></span>
                    <span><?php echo count($svc_ids); ?> service<?php echo count($svc_ids)!=1?'s':''; ?> assigned</span>
                </div>
                <div class="sbw-staff-acts">
                    <button class="sbw-edit-staff" data-id="<?php echo $st->id; ?>"
                        data-name="<?php echo esc_attr($st->name); ?>" data-email="<?php echo esc_attr($st->email); ?>"
                        data-phone="<?php echo esc_attr($st->phone); ?>" data-color="<?php echo esc_attr($st->color); ?>"
                        data-services='<?php echo esc_attr(json_encode($svc_ids)); ?>'
                        data-hours='<?php
                            $hout = array();
                            foreach ($hours as $d=>$h) $hout[$d]=array('start'=>$h->start_time,'end'=>$h->end_time,'off'=>$h->is_day_off);
                            echo esc_attr(json_encode($hout));
                        ?>'>Edit</button>
                    <button class="sbw-del-staff" data-id="<?php echo $st->id; ?>">Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- STAFF MODAL -->
        <div class="sbw-modal" id="sbStaffModal" style="display:none">
            <div class="sbw-modal-box sbw-modal-wide">
                <div class="sbw-modal-head"><h3 id="sbStaffModalTitle">Add Staff Member</h3><button class="sbw-modal-close-staff"></button></div>
                <div class="sbw-modal-body">
                    <input type="hidden" id="sbStaffId">
                    <div class="sbw-row2">
                        <div class="sbw-fg"><label>Name *</label><input type="text" id="sbStaffName"></div>
                        <div class="sbw-fg"><label>Color</label><input type="text" id="sbStaffColor" class="sbw-color" value="#C9A84C"></div>
                    </div>
                    <div class="sbw-row2">
                        <div class="sbw-fg"><label>Email</label><input type="email" id="sbStaffEmail"></div>
                        <div class="sbw-fg"><label>Phone</label><input type="tel" id="sbStaffPhone"></div>
                    </div>

                    <h4>Assigned Services</h4>
                    <div class="sbw-svc-checks">
                    <?php foreach ($all_services as $sv): ?>
                    <label class="sbw-check-label">
                        <input type="checkbox" name="staff_svc[]" value="<?php echo $sv->id; ?>">
                        <?php echo esc_html($sv->name); ?>
                    </label>
                    <?php endforeach; ?>
                    </div>

                    <h4>Working Hours</h4>
                    <div class="sbw-hours-grid">
                        <div class="sbw-hours-header"><span>Day</span><span>Open</span><span>From</span><span>To</span></div>
                        <?php foreach ($days as $d => $dname): ?>
                        <div class="sbw-hours-row" data-day="<?php echo $d; ?>">
                            <span><?php echo $dname; ?></span>
                            <input type="checkbox" class="sbw-day-off" data-day="<?php echo $d; ?>" title="Check to mark this day as open for bookings">
                            <input type="time" class="sbw-hr-start" data-day="<?php echo $d; ?>" value="08:00">
                            <input type="time" class="sbw-hr-end"   data-day="<?php echo $d; ?>" value="18:00">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sbw-modal-foot">
                    <button class="sbw-btn-ghost sbw-modal-close-staff">Cancel</button>
                    <button class="sbw-btn" id="sbSaveStaffBtn">Save Staff</button>
                </div>
            </div>
        </div>
        </div><?php
    }

    //  CUSTOMERS
    public function page_customers() {
        $search    = sanitize_text_field( $_GET['s'] ?? '' );
        $customers = SB_DB::get_customers( $search );
        $currency  = get_option( 'sb_currency', 'FCFA' );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Customers', count($customers) . ' customer' . (count($customers)!=1?'s':'') ); ?>
        <div class="sbw-card">
            <div class="sbw-card-head">
                <h2>Customer List</h2>
                <form method="get" class="sbw-search-form">
                    <input type="hidden" name="page" value="sb-customers">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email or phone...">
                    <button type="submit" class="sbw-btn">Search</button>
                </form>
            </div>
            <?php if (empty($customers)): ?>
            <div class="sbw-empty"><p><?php echo $search ? 'No customers match &ldquo;'.esc_html($search).'&rdquo;.' : 'No customers yet.'; ?></p></div>
            <?php else: ?>
            <table class="sbw-tbl">
                <thead><tr><th>Customer</th><th>Phone</th><th>Bookings</th><th>Total Spent</th><th>Last Booking</th><th>Member Since</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c):
                    $initials = strtoupper( substr(trim($c->name), 0, 1) );
                    $colors   = array('#111111','#4f46e5','#0d9488','#d97706','#dc2626','#16a34a','#2563eb');
                    $color    = $colors[ crc32($c->name) % count($colors) ];
                ?>
                <tr>
                    <td>
                        <div class="sbw-cust-cell">
                            <div class="sbw-cust-avatar" style="background:<?php echo esc_attr($color); ?>;color:#fff"><?php echo $initials; ?></div>
                            <div class="sbw-cust-info">
                                <strong><?php echo esc_html($c->name); ?></strong>
                                <small><?php echo esc_html($c->email ?: '—'); ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?php echo esc_html($c->phone ?: '—'); ?></td>
                    <td><strong><?php echo intval($c->total_bookings); ?></strong></td>
                    <td><?php echo $c->total_spent > 0 ? '<strong>'.number_format($c->total_spent).'</strong> '.esc_html($currency) : '—'; ?></td>
                    <td><?php echo ! empty($c->last_booking) ? date('d M Y', strtotime($c->last_booking)) : '—'; ?></td>
                    <td style="color:var(--sb-muted);font-size:.82rem"><?php echo date('d M Y', strtotime($c->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        </div><?php
    }

    public function page_calendar_redirect() {
        $cal = new SB_Calendar_Page();
        $cal->page_calendar();
    }

    //  FINANCES
    public function page_finances() {
        global $wpdb;
        $t   = $wpdb->prefix . 'sb_bookings';
        $cur = get_option( 'sb_currency', 'FCFA' );

        // Date range from GET params, default to current month
        $today      = date('Y-m-d');
        $month_from = date('Y-m-01');
        $date_from  = sanitize_text_field( $_GET['date_from'] ?? $month_from );
        $date_to    = sanitize_text_field( $_GET['date_to']   ?? $today );

        // Clamp — ensure from <= to
        if ( $date_from > $date_to ) $date_from = $date_to;

        // ── KPI QUERIES ──────────────────────────────────────────
        // Earnings: completed bookings, sum of total_price
        $earnings = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(total_price),0) FROM $t
             WHERE status='completed' AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        $earnings_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t
             WHERE status='completed' AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Booking Value: confirmed bookings total (even without payment)
        $booking_value = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(total_price),0) FROM $t
             WHERE status IN ('confirmed','completed') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        $booking_value_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t
             WHERE status IN ('confirmed','completed') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Deposits: online deposits actually paid
        $deposits_paid = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(deposit_amount),0) FROM $t
             WHERE payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        $deposits_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t
             WHERE payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // CamPay fee stats: 5% of deposits paid via CamPay
        $campay_gross = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(deposit_amount),0) FROM $t
             WHERE payment_method='campay' AND payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        $campay_fee        = round( $campay_gross * 0.05 );
        $campay_net        = $campay_gross - $campay_fee;
        $campay_txn_count  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t
             WHERE payment_method='campay' AND payment_status IN ('deposit_paid','paid_in_full') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // Balance outstanding (confirmed but payment_status = unpaid/pending)
        $outstanding = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(balance_amount),0) FROM $t
             WHERE status IN ('confirmed') AND payment_status NOT IN ('paid_in_full') AND booking_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));

        // ── DAILY CHART DATA (earnings per day in range) ─────────
        $daily_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT booking_date as d, SUM(total_price) as total
             FROM $t WHERE status='completed' AND booking_date BETWEEN %s AND %s
             GROUP BY booking_date ORDER BY booking_date ASC",
            $date_from, $date_to
        ));
        $daily_map = array();
        foreach ($daily_rows as $dr) $daily_map[$dr->d] = (float)$dr->total;

        // Fill all days in range for chart
        $chart_days = array();
        $cursor = new DateTime($date_from);
        $end_dt = new DateTime($date_to);
        while ($cursor <= $end_dt) {
            $ds = $cursor->format('Y-m-d');
            $chart_days[] = array('d' => $ds, 'label' => $cursor->format('d M'), 'total' => $daily_map[$ds] ?? 0);
            $cursor->modify('+1 day');
        }
        $max_day = max(array_column($chart_days, 'total') ?: array(1));
        if ($max_day < 1) $max_day = 1;
        // Limit chart to max 30 points for readability
        $chart_display = count($chart_days) > 30
            ? array_filter($chart_days, function($i) use ($chart_days) {
                return $i % ceil(count($chart_days)/30) === 0;
              }, ARRAY_FILTER_USE_KEY)
            : $chart_days;

        // ── TOP SERVICES (earnings) ───────────────────────────────
        $top_svcs = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.name, s.color, COUNT(b.id) as cnt, COALESCE(SUM(b.total_price),0) as total
             FROM $t b JOIN {$wpdb->prefix}sb_services s ON b.service_id=s.id
             WHERE b.status='completed' AND b.booking_date BETWEEN %s AND %s
             GROUP BY b.service_id ORDER BY total DESC LIMIT 6",
            $date_from, $date_to
        ));
        $max_svc_total = $top_svcs ? max(array_map(function($r){return (float)$r->total;}, $top_svcs)) : 1;
        if ($max_svc_total < 1) $max_svc_total = 1;

        // ── RECENT TRANSACTIONS (payments received) ───────────────
        $recent_txns = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id, b.customer_name, b.booking_date, b.deposit_amount, b.total_price,
                    b.payment_status, b.payment_method, b.payment_ref, b.payment_date, b.status, b.internal_note,
                    s.name as service_name, s.color as service_color
             FROM $t b JOIN {$wpdb->prefix}sb_services s ON b.service_id=s.id
             WHERE b.payment_status IN ('deposit_paid','paid_in_full')
               AND b.booking_date BETWEEN %s AND %s
             ORDER BY b.payment_date DESC LIMIT 20",
            $date_from, $date_to
        ));

        // ── QUICK PRESETS ─────────────────────────────────────────
        $presets = array(
            'today'    => array('label'=>'Today',        'from'=>$today,                      'to'=>$today),
            'week'     => array('label'=>'This Week',    'from'=>date('Y-m-d',strtotime('monday this week')), 'to'=>$today),
            'month'    => array('label'=>'This Month',   'from'=>date('Y-m-01'),              'to'=>$today),
            'last30'   => array('label'=>'Last 30 Days', 'from'=>date('Y-m-d',strtotime('-30 days')), 'to'=>$today),
            'quarter'  => array('label'=>'This Quarter', 'from'=>date('Y-m-01',strtotime('first day of '.date('Y-').sprintf('%02d',ceil(date('n')/3)*3-2).date('-01'))), 'to'=>$today),
            'year'     => array('label'=>'This Year',    'from'=>date('Y-01-01'),              'to'=>$today),
        );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Finances', 'Financial overview — ' . date('d M Y', strtotime($date_from)) . ' to ' . date('d M Y', strtotime($date_to)) ); ?>

        <!-- DATE RANGE FILTER -->
        <div class="sbw-card sbw-fin-filter-card">
            <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="page" value="sb-finances">
                <div class="sbw-fin-preset-btns">
                <?php foreach ($presets as $key => $p):
                    $active = ($date_from === $p['from'] && $date_to === $p['to']);
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sb-finances&date_from='.$p['from'].'&date_to='.$p['to'])); ?>"
                   class="sbw-tab <?php echo $active ? 'active' : ''; ?>"><?php echo $p['label']; ?></a>
                <?php endforeach; ?>
                </div>
                <div class="sbw-fin-date-row">
                    <div class="sbw-fg sbw-fin-date-fg">
                        <label style="font-size:.74rem;font-weight:700;color:var(--sb-muted);text-transform:uppercase;letter-spacing:.05em">From</label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="sbw-fin-date-input">
                    </div>
                    <div class="sbw-fg sbw-fin-date-fg">
                        <label style="font-size:.74rem;font-weight:700;color:var(--sb-muted);text-transform:uppercase;letter-spacing:.05em">To</label>
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="sbw-fin-date-input">
                    </div>
                    <button type="submit" class="sbw-btn sbw-fin-apply-btn">Apply</button>
                </div>
            </form>
        </div>

        <!-- KPI CARDS -->
        <div class="sbw-fin-kpis">
            <div class="sbw-fin-kpi sbw-fin-kpi-green">
                <div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="sbw-fin-kpi-body">
                    <span class="sbw-fin-kpi-label">Earnings</span>
                    <span class="sbw-fin-kpi-val"><?php echo number_format($earnings); ?> <small><?php echo esc_html($cur); ?></small></span>
                    <span class="sbw-fin-kpi-sub"><?php echo $earnings_count; ?> completed booking<?php echo $earnings_count!=1?'s':''; ?></span>
                    <span class="sbw-fin-kpi-hint">Revenue from completed services</span>
                </div>
            </div>
            <div class="sbw-fin-kpi sbw-fin-kpi-purple">
                <div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-chart-bar"></span></div>
                <div class="sbw-fin-kpi-body">
                    <span class="sbw-fin-kpi-label">Booking Value</span>
                    <span class="sbw-fin-kpi-val"><?php echo number_format($booking_value); ?> <small><?php echo esc_html($cur); ?></small></span>
                    <span class="sbw-fin-kpi-sub"><?php echo $booking_value_count; ?> confirmed + completed</span>
                    <span class="sbw-fin-kpi-hint">Total value of confirmed bookings (with or without payment)</span>
                </div>
            </div>
            <div class="sbw-fin-kpi sbw-fin-kpi-blue">
                <div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="sbw-fin-kpi-body">
                    <span class="sbw-fin-kpi-label">Deposits Collected</span>
                    <span class="sbw-fin-kpi-val"><?php echo number_format($deposits_paid); ?> <small><?php echo esc_html($cur); ?></small></span>
                    <span class="sbw-fin-kpi-sub"><?php echo $deposits_count; ?> payment<?php echo $deposits_count!=1?'s':''; ?> received</span>
                    <span class="sbw-fin-kpi-hint">Online deposits paid via CamPay / Paystack / Flutterwave</span>
                </div>
            </div>
            <div class="sbw-fin-kpi sbw-fin-kpi-amber">
                <div class="sbw-fin-kpi-icon"><span class="dashicons dashicons-clock"></span></div>
                <div class="sbw-fin-kpi-body">
                    <span class="sbw-fin-kpi-label">Outstanding Balance</span>
                    <span class="sbw-fin-kpi-val"><?php echo number_format($outstanding); ?> <small><?php echo esc_html($cur); ?></small></span>
                    <span class="sbw-fin-kpi-sub">Confirmed but unpaid balance</span>
                    <span class="sbw-fin-kpi-hint">Remaining balance owed by confirmed customers</span>
                </div>
            </div>
        </div>

        <?php if ($campay_gross > 0): ?>
        <!-- CAMPAY FEE BREAKDOWN -->
        <div class="sbw-fin-campay-strip">
            <div class="sbw-fin-campay-icon"><span class="dashicons dashicons-smartphone"></span></div>
            <div class="sbw-fin-campay-body">
                <span class="sbw-fin-campay-label">CamPay MoMo — <?php echo $campay_txn_count; ?> transaction<?php echo $campay_txn_count!=1?'s':''; ?> this period</span>
                <div class="sbw-fin-campay-nums">
                    <span>Collected: <strong><?php echo number_format($campay_gross); ?> <?php echo esc_html($cur); ?></strong></span>
                    <span class="sbw-fin-campay-sep">−</span>
                    <span>CamPay 5% fee: <strong style="color:var(--sb-red)"><?php echo number_format($campay_fee); ?> <?php echo esc_html($cur); ?></strong></span>
                    <span class="sbw-fin-campay-sep">=</span>
                    <span>Net disbursed to your MoMo: <strong style="color:var(--sb-green)"><?php echo number_format($campay_net); ?> <?php echo esc_html($cur); ?></strong></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CHART + TOP SERVICES -->
        <div class="sbw-cols">

        <div class="sbw-card" style="flex:1.7">
            <div class="sbw-card-head">
                <h2>Daily Earnings</h2>
                <span style="font-size:.8rem;color:var(--sb-muted);font-weight:500"><?php echo date('d M', strtotime($date_from)); ?> – <?php echo date('d M Y', strtotime($date_to)); ?></span>
            </div>
            <?php if (array_sum(array_column($chart_days,'total')) === 0.0): ?>
            <div class="sbw-empty"><p>No completed earnings in this period.</p></div>
            <?php else: ?>
            <div class="sbw-fin-chart-wrap">
                <div class="sbw-fin-bar-chart">
                <?php foreach ($chart_display as $cd):
                    $pct = round(($cd['total'] / $max_day) * 100);
                    $pct = max($pct, 2);
                    $is_today = $cd['d'] === $today;
                ?>
                <div class="sbw-fin-bar-col" title="<?php echo esc_attr(date('d M', strtotime($cd['d']))); ?>: <?php echo number_format($cd['total']); ?> <?php echo esc_attr($cur); ?>">
                    <div class="sbw-fin-bar<?php echo $is_today ? ' today' : ''; ?>" style="height:<?php echo $pct; ?>%"></div>
                    <?php if (count($chart_display) <= 14): ?>
                    <div class="sbw-fin-bar-lbl"><?php echo $cd['label']; ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div style="margin-top:10px;font-size:.75rem;color:var(--sb-muted);text-align:center">
                Hover bars to see daily amounts. Chart shows completed bookings only.
            </div>
            <?php endif; ?>
        </div>

        <div class="sbw-col-r">
            <!-- TOP SERVICES BY EARNINGS -->
            <div class="sbw-card">
                <div class="sbw-card-head"><h2>Top Services</h2><span style="font-size:.78rem;color:var(--sb-muted)">by earnings</span></div>
                <?php if (empty($top_svcs)): ?>
                <div class="sbw-empty"><p>No completed bookings in this period.</p></div>
                <?php else: ?>
                <div class="sbw-top-svc">
                <?php foreach ($top_svcs as $ts): ?>
                <div class="sbw-tsvc-row">
                    <div class="sbw-tsvc-info">
                        <span class="sbw-tsvc-name">
                            <span class="sbw-dot" style="background:<?php echo esc_attr($ts->color??'#111111'); ?>"></span>
                            <?php echo esc_html($ts->name); ?>
                        </span>
                        <span class="sbw-tsvc-count" style="font-weight:700;color:var(--sb-text)"><?php echo number_format((float)$ts->total); ?> <span style="color:var(--sb-muted);font-weight:500"><?php echo esc_html($cur); ?></span></span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <div class="sbw-tsvc-bar-wrap" style="flex:1">
                            <div class="sbw-tsvc-bar" style="width:<?php echo round(((float)$ts->total/$max_svc_total)*100); ?>%"></div>
                        </div>
                        <span style="font-size:.72rem;color:var(--sb-muted);min-width:30px;text-align:right"><?php echo $ts->cnt; ?> job<?php echo $ts->cnt!=1?'s':''; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- SUMMARY BOX -->
            <div class="sbw-card sbw-fin-summary">
                <div class="sbw-card-head"><h2>Period Summary</h2></div>
                <div class="sbw-fin-summary-rows">
                    <div class="sbw-fin-sumrow">
                        <span>Total Bookings</span>
                        <strong><?php echo (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE booking_date BETWEEN %s AND %s",$date_from,$date_to)); ?></strong>
                    </div>
                    <div class="sbw-fin-sumrow">
                        <span>Completed</span>
                        <strong style="color:var(--sb-green)"><?php echo $earnings_count; ?></strong>
                    </div>
                    <div class="sbw-fin-sumrow">
                        <span>Pending</span>
                        <strong style="color:var(--sb-amber)"><?php echo (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='pending' AND booking_date BETWEEN %s AND %s",$date_from,$date_to)); ?></strong>
                    </div>
                    <div class="sbw-fin-sumrow">
                        <span>Cancelled</span>
                        <strong style="color:var(--sb-red)"><?php echo (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='cancelled' AND booking_date BETWEEN %s AND %s",$date_from,$date_to)); ?></strong>
                    </div>
                    <div class="sbw-fin-sumrow sbw-fin-sumrow-total">
                        <span>Collection Rate</span>
                        <strong><?php
                            $bv = $booking_value;
                            echo $bv > 0 ? round(($earnings / $bv) * 100) . '%' : '—';
                        ?></strong>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- TRANSACTIONS TABLE -->
        <div class="sbw-card">
            <div class="sbw-card-head">
                <h2>Payment Transactions</h2>
                <span style="font-size:.8rem;color:var(--sb-muted)"><?php echo count($recent_txns); ?> payment<?php echo count($recent_txns)!=1?'s':''; ?> in period</span>
            </div>
            <?php if (empty($recent_txns)): ?>
            <div class="sbw-empty"><p>No deposits received in this period.</p></div>
            <?php else: ?>
            <table class="sbw-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Booking Date</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Deposit Paid</th>
                        <th>Total Value</th>
                        <th>CamPay Fee (5%)</th>
                        <th>Net to MoMo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_txns as $tx):
                    $b_colors = array('#111111','#4f46e5','#0d9488','#d97706','#dc2626','#16a34a','#2563eb');
                    $b_color  = $b_colors[crc32($tx->customer_name) % count($b_colors)];
                    $initials = strtoupper(substr(trim($tx->customer_name),0,1));
                    $method_labels = array('campay'=>'CamPay','paystack'=>'Paystack','flutterwave'=>'Flutterwave','whatsapp'=>'WhatsApp','manual'=>'Manual');
                    $method_label = $method_labels[$tx->payment_method] ?? ucfirst($tx->payment_method);
                ?>
                <tr>
                    <td><span style="font-family:monospace;font-size:.77rem;color:var(--sb-muted)">#<?php echo $tx->id; ?></span></td>
                    <td>
                        <div class="sbw-cust-cell">
                            <div class="sbw-cust-avatar" style="background:<?php echo $b_color; ?>"><?php echo $initials; ?></div>
                            <div class="sbw-cust-info"><strong><?php echo esc_html($tx->customer_name); ?></strong></div>
                        </div>
                    </td>
                    <td>
                        <span class="sbw-dot" style="background:<?php echo esc_attr($tx->service_color??'#111111'); ?>"></span>
                        <span style="font-weight:600;font-size:.85rem"><?php echo esc_html($tx->service_name); ?></span>
                    </td>
                    <td style="font-size:.84rem"><?php echo date('d M Y', strtotime($tx->booking_date)); ?></td>
                    <td style="font-size:.82rem;color:var(--sb-muted)"><?php echo $tx->payment_date ? date('d M Y, g:i A', strtotime($tx->payment_date)) : '—'; ?></td>
                    <td>
                        <span class="sbw-fin-method-badge sbw-fin-method-<?php echo esc_attr($tx->payment_method); ?>"><?php echo esc_html($method_label); ?></span>
                    </td>
                    <td><strong style="color:var(--sb-green)"><?php echo number_format((float)$tx->deposit_amount); ?> <?php echo esc_html($cur); ?></strong></td>
                    <?php if($tx->payment_method==='campay' && (float)$tx->deposit_amount>0): $cp_fee=round((float)$tx->deposit_amount*0.05); $cp_net=(float)$tx->deposit_amount-$cp_fee; ?>
                    <td style="color:var(--sb-red);font-size:.82rem">−<?php echo number_format($cp_fee); ?> <?php echo esc_html($cur); ?></td>
                    <td><strong style="color:#15803d"><?php echo number_format($cp_net); ?> <?php echo esc_html($cur); ?></strong></td>
                    <?php else: ?>
                    <td style="color:var(--sb-muted);font-size:.8rem">—</td>
                    <td style="color:var(--sb-muted);font-size:.8rem">—</td>
                    <?php endif; ?>
                    <td style="color:var(--sb-text-2)"><?php echo number_format((float)$tx->total_price); ?> <?php echo esc_html($cur); ?></td>
                    <td><span class="sbw-badge sbw-<?php echo esc_attr($tx->status); ?>"><?php echo ucfirst(str_replace('_',' ',$tx->status)); ?></span></td>
                    <td style="font-size:.75rem;color:#6b7280;max-width:180px">
                        <?php if(!empty($tx->internal_note) && strpos($tx->internal_note,'CamPay disburse')!==false): ?>
                        <span style="color:#15803d;font-weight:600">✅ <?php echo esc_html(trim(ltrim($tx->internal_note,' |'))); ?></span>
                        <?php elseif(!empty($tx->internal_note)): ?>
                        <?php echo esc_html($tx->internal_note); ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        </div><?php
    }


    //  SETTINGS 
    public function page_settings() {
        $s  = get_option( 'sb_settings',   array() );
        $ap = get_option( 'sb_appearance', array() );
        $saved = ! empty( $_GET['saved'] );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Settings' ); ?>
        <?php if ($saved): ?><div class="sbw-notice sbw-notice-ok">Settings saved successfully.</div><?php endif; ?>

        <!-- Login / Access Info -->
        <!-- <div style="background:#f0f7ff;border-left:4px solid #3b82f6;margin-bottom:18px;padding:11px 16px;border-radius:6px;font-size:.875rem;line-height:1.6">
            <strong> Client Dashboard Access</strong>&nbsp;&nbsp;
            Login URL: <code style="background:#dbeafe;padding:1px 7px;border-radius:4px"><?php echo esc_url(wp_login_url()); ?></code>
            &nbsp;→&nbsp; then go to <strong>SmartBooking</strong> in the left sidebar.
        </div> -->

        <form id="sbSettingsForm">
        <?php wp_nonce_field('sb_adm','nonce'); ?>
        <input type="hidden" name="action" value="sb_save_settings">

        <!-- ① GENERAL ──────────────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-admin-home"></span></div>
            <h3>General</h3>
        </div>
        <div class="sbw-settings-section-body">
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>Business Name</label>
                    <input type="text" name="business_name" value="<?php echo esc_attr($s['business_name'] ?? get_bloginfo('name')); ?>">
                </div>
                <div class="sbw-fg">
                    <label>Currency</label>
                    <select name="currency">
                        <?php $cur = get_option('sb_currency','FCFA');
                        foreach(['FCFA','XAF','USD','EUR','GHS','NGN','KES','ZAR'] as $c): ?>
                        <option value="<?php echo $c; ?>" <?php selected($cur,$c); ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>Admin Notification Email</label>
                    <input type="email" name="notify_email" value="<?php echo esc_attr($s['notify_email'] ?? get_option('admin_email')); ?>">
                </div>
                <div class="sbw-fg">
                    <label>WhatsApp Business Number <small style="font-weight:400;color:#64748b">(with country code)</small></label>
                    <input type="tel" name="whatsapp" value="<?php echo esc_attr(get_option('sb_whatsapp','')); ?>" placeholder="e.g. 237678899434">
                </div>
            </div>
        </div></div><!-- /General -->

        <!-- ② BOOKING FORM ──────────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-forms"></span></div>
            <h3>Booking Form</h3>
        </div>
        <div class="sbw-settings-section-body">
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>Step 1 Heading</label>
                    <input type="text" name="step1_heading" value="<?php echo esc_attr(get_option('sb_step1_heading','Choose a Service')); ?>" placeholder="Choose a Service">
                </div>
                <div class="sbw-fg">
                    <label>Service Label</label>
                    <input type="text" name="service_label" value="<?php echo esc_attr($s['service_label'] ?? 'Choose a Service'); ?>">
                </div>
            </div>
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>Form Max Width on Desktop (px)</label>
                    <input type="number" name="form_max_width" min="400" max="1400" value="<?php echo esc_attr(get_option('sb_form_max_width',900)); ?>">
                </div>
                <div class="sbw-fg">
                    <label style="font-weight:500;display:block;margin-bottom:8px">Features</label>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="enable_staff" value="1" id="sbEnableStaff" <?php checked(!empty($s['enable_staff'])); ?>>
                            Enable Staff / Specialists selection
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="enable_days" value="1" <?php checked(!empty($s['enable_days'])); ?>>
                            Enable multi-day / rental booking
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="show_categories" value="1" <?php checked(!empty(get_option('sb_show_categories'))); ?>>
                            Show service categories
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="require_staff" value="1" <?php checked(!empty(get_option('sb_require_staff',1))); ?>>
                            Require staff selection
                        </label>
                    </div>
                </div>
            </div>
            <div class="sbw-fg" id="sbGlobalHoursWrap"<?php echo !empty($s['enable_staff']) ? ' style="display:none"' : ''; ?>>
                <label style="font-weight:600;display:block;margin-bottom:6px">Global Business Hours <small style="font-weight:400;color:#64748b">(used when staff is disabled)</small></label>
                <div style="display:flex;gap:16px;align-items:center">
                    <div>
                        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:3px">Opens</label>
                        <input type="time" name="global_open_time"  value="<?php echo esc_attr(get_option('sb_global_open_time','08:00')); ?>" style="width:120px">
                    </div>
                    <div>
                        <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:3px">Closes</label>
                        <input type="time" name="global_close_time" value="<?php echo esc_attr(get_option('sb_global_close_time','18:00')); ?>" style="width:120px">
                    </div>
                </div>
            </div>
            <div class="sbw-row2" style="margin-top:16px">
                <div class="sbw-fg">
                    <label style="font-weight:500;display:block;margin-bottom:8px">Notification Methods</label>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="notify_whatsapp" value="1" <?php checked(!empty(get_option('sb_notify_whatsapp'))); ?>>
                            WhatsApp notification on new booking
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="notify_email_method" value="1" <?php checked(!empty(get_option('sb_notify_email'))); ?>>
                            Email notification on new booking
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="notify_sms" value="1" <?php checked(!empty(get_option('sb_notify_sms'))); ?>>
                            SMS notification (requires SMS provider)
                        </label>
                    </div>
                </div>
                <div class="sbw-fg">
                    <label style="font-weight:500;display:block;margin-bottom:8px">Daily Rental Mode</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                        <input type="checkbox" name="enable_daily_rental" value="1" <?php checked(!empty(get_option('sb_enable_daily_rental'))); ?>>
                        Enable daily rental (shows "Number of Days" stepper, prices shown per day)
                    </label>
                    <small style="color:#64748b;font-size:.8rem;margin-top:6px;display:block">When enabled, set service duration in minutes as multiples of 1440 (1 day = 1440 min).</small>
                </div>
                <div class="sbw-fg">
                    <label style="font-weight:500;display:block;margin-bottom:8px">Number of Days Label</label>
                    <input type="text" name="sb_days_label"
                        value="<?php echo esc_attr( get_option( 'sb_days_label', 'Number of Days' ) ); ?>"
                        placeholder="Number of Days"
                        style="max-width:260px;">
                    <small style="color:#64748b;font-size:.8rem;margin-top:6px;display:block">Label shown above the days stepper in the booking form. Default: <em>Number of Days</em>.</small>
                </div>
            </div>
        </div></div><!-- /Booking Form -->

        <!-- ③ DEPOSIT & POLICIES ──────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <h3>Deposit &amp; Policies</h3>
        </div>
        <div class="sbw-settings-section-body">
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>Default Deposit % <small style="font-weight:400;color:#64748b">(0 = no deposit required)</small></label>
                    <input type="number" name="default_deposit_pct" min="0" max="100"
                        value="<?php echo esc_attr(get_option('sb_default_deposit_pct',0)); ?>"
                        placeholder="e.g. 30">
                    <small>Applied when a service has no individual deposit % set. Set to 0 to disable deposits globally.</small>
                </div>
                <div class="sbw-fg">
                    <label>Cancellation / Late Policy</label>
                    <textarea name="appointment_rules" rows="4" style="font-size:.85rem"
                        placeholder="e.g. Please cancel at least 6 hours before your appointment. No-shows forfeit their deposit."><?php echo esc_textarea(get_option('sb_appointment_rules','')); ?></textarea>
                </div>
            </div>
            <div class="sbw-row2" style="margin-top:12px">
                <div class="sbw-fg">
                    <label>Refund &amp; Deposit Policy</label>
                    <textarea name="refund_text" rows="4" style="font-size:.85rem"
                        placeholder="e.g. Deposits are non-refundable for cancellations within 24 hours."><?php echo esc_textarea(get_option('sb_refund_text','')); ?></textarea>
                </div>
                <div class="sbw-fg">
                    <label>Terms &amp; Conditions</label>
                    <textarea name="terms_text" rows="4" style="font-size:.85rem"
                        placeholder="General terms: service scope, liability, age requirements..."><?php echo esc_textarea(get_option('sb_terms_text','')); ?></textarea>
                </div>
            </div>
            <div class="sbw-fg" style="margin-top:12px">
                <label>Privacy Policy</label>
                <textarea name="privacy_text" rows="3" style="font-size:.85rem"
                    placeholder="e.g. We collect your name, phone, and email to manage your appointment. Your data is not shared with third parties."><?php echo esc_textarea(get_option('sb_privacy_text','')); ?></textarea>
            </div>
        </div></div><!-- /Deposit & Policies -->

        <!-- ④ FLOATING BUTTON ──────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-format-chat"></span></div>
            <h3>Floating Contact Button</h3>
        </div>
        <div class="sbw-settings-section-body">
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;margin-bottom:10px">
                        <input type="checkbox" name="float_on" value="1" <?php checked(!empty($s['float_on'])); ?>>
                        <strong>Enable floating button</strong>
                    </label>
                    <label>Type</label>
                    <select name="float_type">
                        <option value="whatsapp" <?php selected($s['float_type']??'whatsapp','whatsapp'); ?>>WhatsApp</option>
                        <option value="phone"    <?php selected($s['float_type']??'','phone'); ?>>Phone Call</option>
                    </select>
                </div>
                <div class="sbw-fg">
                    <label>Phone / WhatsApp Number <small style="font-weight:400;color:#64748b">(with country code)</small></label>
                    <input type="tel" name="float_number" value="<?php echo esc_attr($s['float_number']??''); ?>" placeholder="e.g. 237678899434">
                    <label style="margin-top:10px;display:block">Button Label</label>
                    <input type="text" name="float_label" value="<?php echo esc_attr($s['float_label']??'Chat with us'); ?>">
                </div>
            </div>
            <div class="sbw-fg" style="max-width:300px">
                <label>Button Color <small style="font-weight:400;color:#64748b">(leave blank to use primary color)</small></label>
                <input type="text" name="float_color" class="sbw-color" value="<?php echo esc_attr($s['float_color']??''); ?>" placeholder="Leave blank = use primary color">
            </div>
        </div></div><!-- /Floating Button -->

        <!-- ⑤ PAYMENT GATEWAYS — admin only ──────────────────── -->
        <?php if ( current_user_can( 'manage_options' ) ) : ?>
        <!-- ⑤ PAYMENT GATEWAYS ─────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-cart"></span></div>
            <h3>Payment Gateways</h3>
        </div>
        <div class="sbw-settings-section-body">
            <p class="sbw-help-text">Add your gateway keys to enable online deposit collection. Leave all fields empty to use WhatsApp / manual payment only.</p>

            <h4 style="margin:0 0 10px;font-size:.9rem;color:#374151">Paystack — Card / Bank Transfer (Nigeria, Ghana, Kenya, South Africa)</h4>
            <div class="sbw-row2">
                <div class="sbw-fg"><label>Public Key</label><input type="text" name="paystack_public_key" value="<?php echo esc_attr($s['paystack_public_key']??''); ?>" placeholder="pk_live_…"></div>
                <div class="sbw-fg"><label>Secret Key</label><input type="password" name="paystack_secret_key" value="<?php echo esc_attr($s['paystack_secret_key']??''); ?>" placeholder="sk_live_…"></div>
            </div>

            <h4 style="margin:18px 0 10px;font-size:.9rem;color:#374151">Flutterwave — MTN / Orange MoMo (Cameroon, Francophone Africa)</h4>
            <div class="sbw-row2">
                <div class="sbw-fg"><label>Public Key</label><input type="text" name="flw_public_key" value="<?php echo esc_attr($s['flw_public_key']??''); ?>" placeholder="FLWPUBK_TEST-…"></div>
                <div class="sbw-fg"><label>Secret Key</label><input type="password" name="flw_secret_key" value="<?php echo esc_attr($s['flw_secret_key']??''); ?>" placeholder="FLWSECK_TEST-…"></div>
            </div>

            <h4 style="margin:18px 0 10px;font-size:.9rem;color:#374151">CamPay — MTN / Orange MoMo (Cameroon only)</h4>
            <div class="sbw-row2">
                <div class="sbw-fg"><label>App Username</label><input type="text" name="campay_username" value="<?php echo esc_attr($s['campay_username']??''); ?>" placeholder="Your CamPay app username"></div>
                <div class="sbw-fg"><label>App Password</label><input type="password" name="campay_password" value="<?php echo esc_attr($s['campay_password']??''); ?>" placeholder="Your CamPay app password"></div>
            </div>
            <div class="sbw-row2" style="margin-top:8px">
                <div class="sbw-fg">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                        <input type="checkbox" name="campay_sandbox" value="1" <?php checked(!empty($s['campay_sandbox'])); ?>>
                        Use CamPay Sandbox (test mode — uncheck for live)
                    </label>
                    <small>Get credentials at <a href="https://demo.campay.net" target="_blank">demo.campay.net</a> (sandbox) or <a href="https://www.campay.net" target="_blank">campay.net</a> (live)</small>
                </div>
                <div class="sbw-fg" style="display:flex;align-items:flex-end;padding-bottom:4px">
                    <button type="button" id="sbTestCampay" class="sbw-btn sbw-btn-ghost" style="width:auto">
                        <span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:4px"></span> Test CamPay Connection
                    </button>
                </div>
            </div>
            <div id="sbCampayTestResult" style="margin-top:8px;font-size:.83rem;display:none"></div>

            <div class="sbw-info-card" style="margin-top:16px">
                <h4>CamPay Auto-Withdrawal (Direct to MoMo)</h4>
                <p>Collected payments auto-disbursed to your Mobile Money account (minus 5% platform fee). Leave blank to disable.</p>
                <div class="sbw-row2">
                    <div class="sbw-fg">
                        <label>MoMo Account Name</label>
                        <input type="text" name="campay_momo_name" value="<?php echo esc_attr($s['campay_momo_name']??''); ?>" placeholder="e.g. Ismaeal Ndongo">
                    </div>
                    <div class="sbw-fg">
                        <label>MoMo Phone Number</label>
                        <input type="text" name="campay_momo_number" value="<?php echo esc_attr($s['campay_momo_number']??''); ?>" placeholder="e.g. 237671234567">
                        <small>Include country code (237 for Cameroon). MTN or Orange.</small>
                    </div>
                </div>
            </div>
        </div></div><!-- /Payment Gateways -->
        <?php endif; // end admin-only: Payment Gateways ?>

        <!-- ⑥ WHATSAPP AUTO-NOTIFY — admin only ──────────────── -->
        <?php if ( current_user_can( 'manage_options' ) ) : ?>
        <!-- ⑥ WHATSAPP AUTO-NOTIFY ─────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-whatsapp"></span></div>
            <h3>WhatsApp Auto-Notify <small style="font-weight:400;font-size:.8rem;color:#64748b">(Meta Cloud API)</small></h3>
        </div>
        <div class="sbw-settings-section-body">
            <p class="sbw-help-text">Optional. Fill these in to automatically send WhatsApp confirmation messages to customers via Meta Cloud API. Leave blank to use manual WhatsApp links instead.</p>
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>Admin WhatsApp Number <small style="color:#64748b">(receives booking alerts)</small></label>
                    <input type="tel" name="admin_wa_number" value="<?php echo esc_attr($s['admin_wa_number']??''); ?>" placeholder="e.g. 237678899434">
                </div>
                <div class="sbw-fg">
                    <label>Meta Phone Number ID</label>
                    <input type="text" name="meta_wa_phone_id" value="<?php echo esc_attr($s['meta_wa_phone_id']??''); ?>" placeholder="From Meta Business → WhatsApp API">
                </div>
            </div>
            <div class="sbw-fg" style="max-width:600px;margin-top:8px">
                <label>Meta Permanent Access Token</label>
                <input type="password" name="meta_wa_token" value="<?php echo esc_attr($s['meta_wa_token']??''); ?>" placeholder="EAAxxxxxx…">
            </div>
        </div></div><!-- /WhatsApp -->
        <?php endif; // end admin-only: WhatsApp Auto-Notify ?>

        <!-- ⑦ APPEARANCE ────────────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-art"></span></div>
            <h3>Appearance &amp; Colors</h3>
        </div>
        <div class="sbw-settings-section-body">
            <p class="sbw-help-text">Controls the booking widget colors. Does <strong>not</strong> affect other plugins or your theme. Changes take effect after saving.</p>
            <div class="sbw-row3">
                <div class="sbw-fg"><label>Primary Color <small style="color:#64748b">buttons, selected items, links</small></label><input type="text" name="ap_primary" class="sbw-color" value="<?php echo esc_attr($ap['primary']??'#111111'); ?>"></div>
                <div class="sbw-fg"><label>Header Bar Color <small style="color:#64748b">top strip background</small></label><input type="text" name="ap_p_dark" class="sbw-color" value="<?php echo esc_attr($ap['p_dark']??'#3b1a5c'); ?>"></div>
                <div class="sbw-fg"><label>Header Text Color <small style="color:#64748b">step labels in header</small></label><input type="text" name="ap_hd_text" class="sbw-color" value="<?php echo esc_attr($ap['hd_text']??'#ffffff'); ?>"></div>
                <div class="sbw-fg"><label>Accent Color <small style="color:#64748b">active step bubble</small></label><input type="text" name="ap_accent" class="sbw-color" value="<?php echo esc_attr($ap['accent']??'#C9A84C'); ?>"></div>
                <div class="sbw-fg"><label>Hover Color <small style="color:#64748b">button &amp; card hover</small></label><input type="text" name="ap_hover" class="sbw-color" value="<?php echo esc_attr($ap['hover']??'#3b1a5c'); ?>"></div>
                <div class="sbw-fg"><label>Form Background <small style="color:#64748b">widget background</small></label><input type="text" name="ap_bg" class="sbw-color" value="<?php echo esc_attr($ap['bg']??'#ffffff'); ?>"></div>
                <div class="sbw-fg"><label>Body Text Color</label><input type="text" name="ap_text" class="sbw-color" value="<?php echo esc_attr($ap['text']??'#1a1a2e'); ?>"></div>
                <div class="sbw-fg"><label>Button Text Color</label><input type="text" name="ap_btn_text" class="sbw-color" value="<?php echo esc_attr($ap['btn_text']??'#ffffff'); ?>"></div>
                <div class="sbw-fg"><label>Arrow / Nav Color <small style="color:#64748b">calendar nav arrows</small></label><input type="text" name="ap_arrow" class="sbw-color" value="<?php echo esc_attr($ap['arrow']??'#111111'); ?>"></div>
                <div class="sbw-fg"><label>Corner Radius (px) <small style="color:#64748b">0 = sharp, 20 = round</small></label><input type="number" name="ap_radius" value="<?php echo esc_attr($ap['radius']??10); ?>" min="0" max="30"></div>
            </div>
        </div></div><!-- /Appearance -->

        <!-- ⑧ ACTIVE PAGES ──────────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-admin-page"></span></div>
            <h3>Active Pages</h3>
        </div>
        <div class="sbw-settings-section-body">
            <!-- <p class="sbw-help-text">These pages are auto-created and assigned when the plugin is installed. You can change them here if needed.</p> -->

            <div class="sbw-fg" style="max-width:420px;margin-bottom:20px">
                <label>Customer Portal Page <small style="color:#64748b;font-weight:400">(the page where you placed <code>[sb_portal]</code>)</small></label>
                <?php
                $portal_page = intval(get_option('sb_portal_page_id',0));
                wp_dropdown_pages(array(
                    'name'             => 'sb_portal_page_id',
                    'show_option_none' => '— Select a page —',
                    'option_none_value'=> 0,
                    'selected'         => $portal_page,
                    'echo'             => true,
                    'post_status'      => 'publish',
                ));
                ?>
                <?php if(!empty($portal_page)): ?>
                <small style="margin-top:5px;display:block;color:#111">URL: <a href="<?php echo esc_url(get_permalink($portal_page)); ?>" target="_blank"><?php echo esc_url(get_permalink($portal_page)); ?></a></small>
                <?php endif; ?>
            </div>

            <div class="sbw-fg" style="max-width:420px">
                <label>Staff Portal Page <small style="color:#64748b;font-weight:400">(the page where you placed <code>[sb_staff_portal]</code>)</small></label>
                <?php
                $staff_portal_page = intval( get_option('sb_staff_portal_page_id', 0) );
                wp_dropdown_pages( array(
                    'name'             => 'sb_staff_portal_page_id',
                    'show_option_none' => '— Select a page —',
                    'option_none_value'=> 0,
                    'selected'         => $staff_portal_page,
                    'echo'             => true,
                    'post_status'      => 'publish',
                ) );
                ?>
                <?php if(!empty($staff_portal_page)): ?>
                <small style="margin-top:5px;display:block;color:#111">URL: <a href="<?php echo esc_url(get_permalink($staff_portal_page)); ?>" target="_blank"><?php echo esc_url(get_permalink($staff_portal_page)); ?></a></small>
                <?php endif; ?>
            </div>
        </div></div><!-- /Active Pages -->

        <!-- ⑧ SHORTCODE ─────────────────────────────────────── -->
        <div class="sbw-settings-section">
        <div class="sbw-settings-section-head">
            <div class="sbw-ss-icon"><span class="dashicons dashicons-shortcode"></span></div>
            <h3>Shortcode</h3>
        </div>
        <div class="sbw-settings-section-body">
            <p style="margin:0 0 12px;font-size:.88rem;color:#4b5563">Place this shortcode on any page to display the booking widget:</p>
            <div class="sbw-shortcode-block">
                <code>[smartbooking]</code>
                <button type="button" class="sbw-copy-btn" onclick="navigator.clipboard.writeText('[smartbooking]').then(function(){this.textContent='Copied!';setTimeout(function(){document.querySelectorAll('.sbw-copy-btn')[0].textContent='Copy';},1500);}.bind(this))">Copy</button>
            </div>
            <p style="margin:10px 0 0;font-size:.82rem;color:#6b7280">Pre-select a service: <code style="background:#f0f0f0;padding:3px 8px;border-radius:5px;color:#111;font-family:monospace">[smartbooking service="ID"]</code></p>
        </div></div><!-- /Shortcode -->

        <!-- Bottom Save -->
        <div style="padding:18px 0 10px;display:flex;align-items:center;gap:12px">
            <button type="submit" class="sbw-btn" style="padding:10px 34px;font-size:.95rem">Save All Settings</button>
        </div>
        </form>
        </div><?php
    }


    //  AJAX HANDLERS 
    public function ajax_save_service() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        // Validate required field
        if ( empty( $_POST['name'] ) ) {
            wp_send_json_error( array( 'msg' => 'Service name is required.' ) );
        }
        $_POST['image_url'] = esc_url_raw( $_POST['image_url'] ?? '' );
        $_POST['gallery_images'] = sanitize_textarea_field( $_POST['gallery_images'] ?? '' );
        $id = SB_DB::save_service( $_POST );
        if ( ! $id ) {
            global $wpdb;
            wp_send_json_error( array( 'msg' => 'Could not save service. Database error: ' . $wpdb->last_error ) );
        }
        wp_send_json_success( array( 'id' => $id ) );
    }

    public function ajax_delete_service() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        SB_DB::delete_service( intval($_POST['id']) );
        wp_send_json_success();
    }

    public function ajax_save_category() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        $id = SB_DB::save_category( $_POST );
        wp_send_json_success( array( 'id' => $id, 'name' => sanitize_text_field($_POST['name']) ) );
    }

    public function ajax_save_staff() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        $id  = SB_DB::save_staff( $_POST );
        $svc = isset($_POST['services']) ? array_map('intval',(array)$_POST['services']) : array();
        SB_DB::set_staff_services( $id, $svc );
        $hours = $_POST['hours'] ?? array();
        if ( $hours ) SB_DB::save_working_hours( $id, $hours );
        wp_send_json_success( array('id'=>$id) );
    }

    public function ajax_delete_staff() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        SB_DB::delete_staff( intval($_POST['id']) );
        wp_send_json_success();
    }

    public function ajax_save_hours() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        SB_DB::save_working_hours( intval($_POST['staff_id']), $_POST['hours'] );
        wp_send_json_success();
    }

    public function ajax_update_status() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        SB_DB::update_booking_status( intval($_POST['id']), sanitize_text_field($_POST['status']) );
        wp_send_json_success();
    }

    public function ajax_delete_booking() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        SB_DB::delete_booking( intval($_POST['id']) );
        wp_send_json_success();
    }

    public function ajax_save_settings() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        $s = array(
            'business_name'      => sanitize_text_field( $_POST['business_name']      ?? '' ),
            'notify_email'       => sanitize_email(      $_POST['notify_email']       ?? '' ),
            'float_on'           => ( ! empty( $_POST['float_on'] ) && $_POST['float_on'] !== '0' ) ? 1 : 0,
            'float_type'         => sanitize_text_field( $_POST['float_type']         ?? 'whatsapp' ),
            'float_number'       => sanitize_text_field( $_POST['float_number']       ?? '' ),
            'float_label'        => sanitize_text_field( $_POST['float_label']        ?? 'Chat with us' ),
            'float_color'        => sanitize_hex_color( $_POST['float_color'] ?? '' ),
            'service_label' => sanitize_text_field($_POST['service_label'] ?? 'Choose a Service'),
            'enable_staff' => !empty($_POST['enable_staff']) && $_POST['enable_staff'] !== '0' ? 1 : 0,
            'enable_days'  => !empty($_POST['enable_days'])  && $_POST['enable_days']  !== '0' ? 1 : 0,
            'primary_color' => sanitize_text_field($_POST['primary_color'] ?? '#0073aa'),
            // Admin WhatsApp auto-notify via Meta Cloud API
            'admin_wa_number'    => preg_replace( '/[^0-9]/', '', $_POST['admin_wa_number'] ?? '' ),
            'meta_wa_phone_id'   => sanitize_text_field( $_POST['meta_wa_phone_id']   ?? '' ),
            'meta_wa_token'      => sanitize_text_field( $_POST['meta_wa_token']       ?? '' ),
            // Payment gateways
            'paystack_public_key' => sanitize_text_field( $_POST['paystack_public_key'] ?? '' ),
            'paystack_secret_key' => sanitize_text_field( $_POST['paystack_secret_key'] ?? '' ),
            'flw_public_key'      => sanitize_text_field( $_POST['flw_public_key']      ?? '' ),
            'flw_secret_key'      => sanitize_text_field( $_POST['flw_secret_key']      ?? '' ),
            'campay_username'     => sanitize_text_field( $_POST['campay_username']     ?? '' ),
            'campay_password'     => sanitize_text_field( $_POST['campay_password']     ?? '' ),
            'campay_sandbox'      => ( ! empty( $_POST['campay_sandbox'] ) && $_POST['campay_sandbox'] !== '0' ) ? 1 : 0,
            'campay_momo_name'    => sanitize_text_field( $_POST['campay_momo_name']    ?? '' ),
            'campay_momo_number'  => preg_replace( '/[^0-9]/', '', $_POST['campay_momo_number'] ?? '' ),
        );
        update_option( 'sb_settings', $s );
        update_option( 'sb_default_deposit_pct', intval($_POST['default_deposit_pct'] ?? 0) );
        update_option( 'sb_terms_text',        wp_kses_post($_POST['terms_text']        ?? '') );
        update_option( 'sb_privacy_text',       wp_kses_post($_POST['privacy_text']       ?? '') );
        update_option( 'sb_refund_text',        wp_kses_post($_POST['refund_text']         ?? '') );
        update_option( 'sb_appointment_rules',  sanitize_textarea_field($_POST['appointment_rules'] ?? '') );
        update_option( 'sb_currency', sanitize_text_field( $_POST['currency'] ?? 'FCFA' ) );
        update_option( 'sb_portal_page_id', intval( $_POST['sb_portal_page_id'] ?? 0 ) );
        update_option( 'sb_staff_portal_page_id', intval( $_POST['sb_staff_portal_page_id'] ?? 0 ) );
        update_option( 'sb_whatsapp', sanitize_text_field( $_POST['whatsapp'] ?? '' ) );
        update_option( 'sb_step1_heading',    sanitize_text_field( $_POST['step1_heading']    ?? 'Choose a Service' ) );
        update_option( 'sb_global_open_time',  sanitize_text_field( $_POST['global_open_time']  ?? '08:00' ) );
        update_option( 'sb_global_close_time', sanitize_text_field( $_POST['global_close_time'] ?? '18:00' ) );
        update_option( 'sb_form_max_width', intval($_POST['form_max_width'] ?? 900) );
        update_option( 'sb_show_categories', ( ! empty($_POST['show_categories']) && $_POST['show_categories'] !== '0' ) ? 1 : 0 );
        update_option( 'sb_require_staff', ( ! empty($_POST['require_staff']) && $_POST['require_staff'] !== '0' ) ? 1 : 0 );
        update_option( 'sb_enable_daily_rental', ( ! empty($_POST['enable_daily_rental']) && $_POST['enable_daily_rental'] !== '0' ) ? 1 : 0 );
        update_option( 'sb_days_label', sanitize_text_field( $_POST['sb_days_label'] ?? 'Number of Days' ) );
        update_option( 'sb_notify_whatsapp', ( ! empty($_POST['notify_whatsapp']) && $_POST['notify_whatsapp'] !== '0' ) ? 1 : 0 );
        update_option( 'sb_notify_email', ( ! empty($_POST['notify_email_method']) && $_POST['notify_email_method'] !== '0' ) ? 1 : 0 );
        update_option( 'sb_notify_sms', ( ! empty($_POST['notify_sms']) && $_POST['notify_sms'] !== '0' ) ? 1 : 0 );
        update_option( 'sb_appearance', array(
            'primary'  => sanitize_hex_color( $_POST['ap_primary']  ?? '' ) ?: '#111111',
            'p_dark'   => sanitize_hex_color( $_POST['ap_p_dark']   ?? '' ) ?: '#3b1a5c',
            'hd_text'  => sanitize_hex_color( $_POST['ap_hd_text']  ?? '' ) ?: '#ffffff',
            'accent'   => sanitize_hex_color( $_POST['ap_accent']   ?? '' ) ?: '#C9A84C',
            'hover'    => sanitize_hex_color( $_POST['ap_hover']    ?? '' ) ?: '#3b1a5c',
            'arrow'    => sanitize_hex_color( $_POST['ap_arrow']    ?? '' ) ?: '#111111',
            'bg'       => sanitize_hex_color( $_POST['ap_bg']       ?? '' ) ?: '#ffffff',
            'text'     => sanitize_hex_color( $_POST['ap_text']     ?? '' ) ?: '#1a1a2e',
            'btn_text' => sanitize_hex_color( $_POST['ap_btn_text'] ?? '' ) ?: '#ffffff',
            'radius'   => intval( $_POST['ap_radius'] ?? 10 ),
        ) );
        wp_send_json_success();
    }

    public function ajax_upload_image() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        if ( ! function_exists('media_handle_upload') ) require_once ABSPATH.'wp-admin/includes/image.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        $id  = media_handle_upload( 'file', 0 );
        if ( is_wp_error($id) ) wp_send_json_error( array('msg'=>$id->get_error_message()) );
        wp_send_json_success( array('url' => wp_get_attachment_url($id)) );
    }

    public function ajax_test_campay() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'msg' => 'Unauthorized' ) );

        // Accept credentials from the POST (live form values) OR fall back to saved settings.
        // This lets the user test before clicking Save.
        $settings = get_option( 'sb_settings', array() );
        $username = sanitize_text_field( $_POST['campay_username'] ?? $settings['campay_username'] ?? '' );
        $password = sanitize_text_field( $_POST['campay_password'] ?? $settings['campay_password'] ?? '' );
        $sandbox  = isset( $_POST['campay_sandbox'] ) ? ! empty( $_POST['campay_sandbox'] ) : ! empty( $settings['campay_sandbox'] );
        $base     = $sandbox ? 'https://demo.campay.net/api' : 'https://www.campay.net/api';

        $log = array();
        $log[] = '📋 CONFIG CHECK';
        $log[] = 'Username : ' . ( $username ? '✅ Set' : '❌ MISSING' );
        $log[] = 'Password : ' . ( $password ? '✅ Set' : '❌ MISSING' );
        $log[] = 'Mode     : ' . ( $sandbox  ? '🧪 Sandbox (demo.campay.net)' : '🚀 Live (campay.net)' );

        if ( ! $username || ! $password ) {
            $log[] = '';
            $log[] = '🛑 Cannot test — add your CamPay username and password in Settings first.';
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }

        $log[] = '';
        $log[] = '🌐 CONNECTIVITY CHECK';
        $ping = wp_remote_get( $base . '/token/', array( 'timeout' => 8 ) );
        if ( is_wp_error( $ping ) ) {
            $log[] = '❌ Cannot reach ' . $base;
            $log[] = '   Error: ' . $ping->get_error_message();
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }
        $log[] = '✅ CamPay server reachable (HTTP ' . wp_remote_retrieve_response_code( $ping ) . ')';

        $log[] = '';
        $log[] = '🔑 AUTHENTICATION TEST';
        $auth_resp = wp_remote_post( $base . '/token/', array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => json_encode( array( 'username' => $username, 'password' => $password ) ),
            'timeout' => 15,
        ));
        if ( is_wp_error( $auth_resp ) ) {
            $log[] = '❌ Authentication request failed: ' . $auth_resp->get_error_message();
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }
        $auth_body = json_decode( wp_remote_retrieve_body( $auth_resp ), true );
        $token = $auth_body['token'] ?? '';
        if ( ! $token ) {
            $err = $auth_body['detail'] ?? ( $auth_body['message'] ?? 'Authentication failed.' );
            $log[] = '❌ Login failed: ' . $err;
            $log[] = '   → Double-check your CamPay username and password.';
            $log[] = '   → Make sure you\'re using the right mode (sandbox vs live).';
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }
        $log[] = '✅ Login successful! Token received.';

        $log[] = '';
        $log[] = '✅ ALL CHECKS PASSED — CamPay is configured correctly.';
        $log[] = '   Customers will see "Pay via MTN / Orange MoMo" when a deposit is required.';
        if ( $sandbox ) {
            $log[] = '';
            $log[] = '⚠️  You are in SANDBOX mode. Switch to Live when ready to accept real payments.';
        }
        wp_send_json_success( array( 'log' => $log ) );
    }

    public function ajax_test_whatsapp() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array('msg' => 'Unauthorized') );

        // Catch any fatal errors and return them instead of hanging
        @set_time_limit( 30 );

        $settings     = get_option( 'sb_settings', array() );
        $phone_id     = sanitize_text_field( $settings['meta_wa_phone_id'] ?? '' );
        $access_token = sanitize_text_field( $settings['meta_wa_token']    ?? '' );
        $to_number    = preg_replace( '/[^0-9]/', '', $settings['admin_wa_number'] ?? '' );

        $log = array();

        // --- Config checks ---
        $log[] = '📋 CONFIG CHECK';
        $log[] = 'Phone Number ID : ' . ( $phone_id    ? '✅ Set (' . substr($phone_id, 0, 6) . '…)' : '❌ MISSING — paste your Phone Number ID in Settings' );
        $log[] = 'Access Token    : ' . ( $access_token ? '✅ Set (…' . substr($access_token, -6) . ')' : '❌ MISSING — paste your Access Token in Settings' );
        $log[] = 'Recipient Number: ' . ( $to_number   ? '✅ ' . $to_number : '❌ MISSING' );

        if ( ! $phone_id || ! $access_token || ! $to_number ) {
            $log[] = '';
            $log[] = '🛑 Cannot send — fix missing fields above first, then Save Settings.';
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }

        // --- Test server connectivity first (fast, no auth needed) ---
        $log[] = '';
        $log[] = '🌐 CONNECTIVITY CHECK';
        $ping = wp_remote_get( 'https://graph.facebook.com/', array( 'timeout' => 8, 'blocking' => true ) );
        if ( is_wp_error( $ping ) ) {
            $log[] = '❌ Your server CANNOT reach graph.facebook.com.';
            $log[] = '   Error: ' . $ping->get_error_message();
            $log[] = '';
            $log[] = '💡 FIX: Your hosting provider is blocking outbound HTTPS requests.';
            $log[] = '   Contact your host and ask them to whitelist outbound port 443 to graph.facebook.com.';
            $log[] = '   This is the #1 reason WhatsApp notifications fail on shared hosting.';
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }
        $log[] = '✅ Server can reach graph.facebook.com (HTTP ' . wp_remote_retrieve_response_code($ping) . ')';

        // --- Send test message using approved template ---
        $url  = "https://graph.facebook.com/v19.0/{$phone_id}/messages";
        $body = wp_json_encode( array(
            'messaging_product' => 'whatsapp',
            'to'                => $to_number,
            'type'              => 'template',
            'template'          => array(
                'name'       => 'app_notification',
                'language'   => array( 'code' => 'en_US' ),
                'components' => array(
                    array(
                        'type'       => 'body',
                        'parameters' => array(
                            array( 'type' => 'text', 'text' => 'Admin' ),
                            array( 'type' => 'text', 'text' => 'SmartBooking test — notifications are working! ✅ Sent: ' . date('D d M Y, g:i A') ),
                        ),
                    ),
                ),
            ),
        ) );

        $log[] = '';
        $log[] = '📡 SENDING MESSAGE';
        $log[] = 'URL : POST ' . $url;
        $log[] = 'To  : +' . $to_number;

        $response = wp_remote_post( $url, array(
            'timeout'  => 15,
            'blocking' => true,
            'headers'  => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => $body,
        ) );

        $log[] = '';
        $log[] = '📥 RESPONSE';

        if ( is_wp_error( $response ) ) {
            $log[] = '❌ HTTP Error: ' . $response->get_error_message();
            $log[] = '   Code: ' . $response->get_error_code();
            wp_send_json_error( array( 'log' => $log ) );
            return;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $decoded     = json_decode( $raw_body, true );

        $log[] = 'HTTP Status : ' . $status_code;
        $log[] = 'Raw Body    : ' . $raw_body;

        if ( $status_code === 200 && ! empty( $decoded['messages'][0]['id'] ) ) {
            $log[] = '';
            $log[] = '✅ SUCCESS! Message sent. ID: ' . $decoded['messages'][0]['id'];
            $log[] = '   Check WhatsApp on +' . $to_number . ' now.';
            wp_send_json_success( array( 'log' => $log ) );
            return;
        }

        // --- Parse Meta error ---
        $log[] = '';
        $log[] = '❌ FAILED — Meta API error:';

        if ( ! empty( $decoded['error'] ) ) {
            $err = $decoded['error'];
            $log[] = 'Code    : ' . ( $err['code']            ?? 'N/A' );
            $log[] = 'Subcode : ' . ( $err['error_subcode']   ?? 'N/A' );
            $log[] = 'Message : ' . ( $err['message']         ?? 'N/A' );
            $log[] = 'Type    : ' . ( $err['type']            ?? 'N/A' );
            $log[] = '';

            $code = intval( $err['code'] ?? 0 );
            if ( $code === 190 ) {
                $log[] = '💡 FIX: Token is expired or invalid.';
                $log[] = '   Generate a permanent System User token at:';
                $log[] = '   Business Settings → System Users → Generate Token';
                $log[] = '   Select your WhatsApp app + whatsapp_business_messaging permission.';
            } elseif ( $code === 100 ) {
                $log[] = '💡 FIX: Phone Number ID is wrong.';
                $log[] = '   Copy it exactly: Meta Developers → WhatsApp → API Setup → "Phone Number ID"';
            } elseif ( in_array( $code, array(131030, 131026, 131047) ) ) {
                $log[] = '💡 FIX: +' . $to_number . ' is not an allowed recipient.';
                $log[] = '   In test/sandbox mode you must manually add recipients:';
                $log[] = '   Meta Developers → WhatsApp → API Setup → "To" field';
                $log[] = '   Type +' . $to_number . ', click Send, then reply to the opt-in message on your phone.';
            } elseif ( $code === 4 || $code === 80007 ) {
                $log[] = '💡 FIX: Rate limit hit. Wait 1 minute and try again.';
            } else {
                $log[] = '💡 Look up error code ' . $code . ' at:';
                $log[] = '   developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes';
            }
        } else {
            $log[] = 'Unexpected response — see raw body above.';
        }

        wp_send_json_error( array( 'log' => $log ) );
    }
}