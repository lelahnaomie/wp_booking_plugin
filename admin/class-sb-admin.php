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
        add_submenu_page( 'smartbooking', 'Settings',   'Settings',   'manage_options', 'sb-settings',    array( $this, 'page_settings' ) );
    }

    private function head( $title, $sub = '' ) {
        echo '<div class="sbw-head"><div><h1>' . esc_html($title) . '</h1>'
           . ( $sub ? '<p class="sbw-sub">' . esc_html($sub) . '</p>' : '' )
           . '</div><span class="sbw-ver">v' . SB_VER . '</span></div>';
    }

    // ── DASHBOARD ─────────────────────────────────────────────
    public function page_dashboard() {
        $s   = SB_DB::get_stats();
        $cur = get_option( 'sb_currency', 'FCFA' );
        $today_bookings = SB_DB::get_bookings( array( 'date' => date('Y-m-d') ) );
        $pending = SB_DB::get_bookings( array( 'status' => 'pending' ) );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Dashboard', "Today: {$s['today']} booking" . ($s['today']!=1?'s':'') ); ?>

        <div class="sbw-stats">
            <div class="sbw-stat sbw-stat-purple"><span class="dashicons dashicons-calendar-alt"></span><div><b><?php echo $s['total']; ?></b><small>Total Bookings</small></div></div>
            <div class="sbw-stat sbw-stat-amber"><span class="dashicons dashicons-clock"></span><div><b><?php echo $s['pending']; ?></b><small>Pending</small></div></div>
            <div class="sbw-stat sbw-stat-green"><span class="dashicons dashicons-yes-alt"></span><div><b><?php echo $s['confirmed']; ?></b><small>Confirmed</small></div></div>
            <div class="sbw-stat sbw-stat-blue"><span class="dashicons dashicons-money-alt"></span><div><b><?php echo number_format($s['revenue']); ?> <?php echo esc_html($cur); ?></b><small>Revenue</small></div></div>
            <div class="sbw-stat sbw-stat-indigo"><span class="dashicons dashicons-admin-users"></span><div><b><?php echo $s['customers']; ?></b><small>Customers</small></div></div>
            <div class="sbw-stat sbw-stat-teal"><span class="dashicons dashicons-businessman"></span><div><b><?php echo $s['staff']; ?></b><small>Staff</small></div></div>
        </div>

        <div class="sbw-cols">
        <div class="sbw-card" style="flex:1.6">
            <div class="sbw-card-head"><h2>Today's Appointments</h2>
                <a href="<?php echo admin_url('admin.php?page=sb-calendar'); ?>" class="sbw-lnk">Full Calendar →</a>
            </div>
            <?php if ( empty($today_bookings) ): ?>
            <div class="sbw-empty"><p>No appointments today.</p></div>
            <?php else: ?>
            <div class="sbw-timeline">
            <?php foreach ( $today_bookings as $b ): ?>
                <div class="sbw-appt" style="border-left-color:<?php echo esc_attr($b->service_color ?? '#5B2D8E'); ?>">
                    <div class="sbw-appt-time"><?php echo date('g:i A', strtotime($b->start_time)); ?></div>
                    <div class="sbw-appt-info">
                        <strong><?php echo esc_html($b->customer_name); ?></strong>
                        <span><?php echo esc_html($b->service_name); ?> · <?php echo esc_html($b->staff_name ?? 'Any'); ?></span>
                    </div>
                    <span class="sbw-badge sbw-<?php echo $b->status; ?>"><?php echo ucfirst($b->status); ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="sbw-card" style="flex:1">
            <div class="sbw-card-head"><h2>Pending Actions</h2></div> 
            
            <?php if ( empty($pending) ): ?>
            <div class="sbw-empty"><p>Nothing pending. </p></div>
            <?php else: ?>
            <div class="sbw-pending-list">
            <?php foreach ( array_slice($pending, 0, 8) as $b ): ?>
                <div class="sbw-pend-row" data-id="<?php echo $b->id; ?>">
                    <div>
                        <strong><?php echo esc_html($b->customer_name); ?></strong>
                        <span class="sbw-pend-meta"><?php echo esc_html($b->service_name); ?> · <?php echo date('d M', strtotime($b->booking_date)); ?> <?php echo date('g:i A', strtotime($b->start_time)); ?></span>
                    </div>
                    <div class="sbw-pend-actions">
                        <button class="sbw-ab sbw-confirm" data-id="<?php echo $b->id; ?>">✓</button>
                        <button class="sbw-ab sbw-cancel"  data-id="<?php echo $b->id; ?>">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        </div>
        </div><?php
    }

    // ── BOOKINGS ──
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
        <?php $this->head( 'Bookings', count($bookings) . ' bookings' ); ?>

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
            <thead><tr><th>#</th><th>Client</th><th>Service</th><th>Staff</th><th>Date &amp; Time</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ( $bookings as $b ): ?>
            <tr data-id="<?php echo $b->id; ?>">
                <td><b>#<?php echo $b->id; ?></b></td>
                <td>
                    <strong><?php echo esc_html($b->customer_name); ?></strong>
                    <?php if ($b->customer_phone): ?><br><small><?php echo esc_html($b->customer_phone); ?></small><?php endif; ?>
                </td>
                <td><span class="sbw-dot" style="background:<?php echo esc_attr($b->service_color ?? '#5B2D8E'); ?>"></span><?php echo esc_html($b->service_name); ?></td>
                <td><?php echo esc_html($b->staff_name ?? '—'); ?></td>
                <td><?php echo date('d M Y', strtotime($b->booking_date)); ?><br><small><?php echo date('g:i A', strtotime($b->start_time)); ?> – <?php echo date('g:i A', strtotime($b->end_time)); ?></small></td>
                <td><?php echo $b->total_price > 0 ? number_format($b->total_price) . ' ' . esc_html($currency) : '—'; ?></td>
                <td><span class="sbw-badge sbw-<?php echo $b->status; ?>"><?php echo ucfirst($b->status); ?></span></td>
                <td class="sbw-acts">
                    <select class="sbw-status-sel" data-id="<?php echo $b->id; ?>">
                        <?php foreach ( array('pending','confirmed','completed','cancelled','no_show') as $st ): ?>
                        <option value="<?php echo $st; ?>" <?php selected($b->status,$st); ?>><?php echo ucfirst(str_replace('_',' ',$st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="sbw-del-btn" data-id="<?php echo $b->id; ?>" title="Delete">🗑</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
        </div><?php
    }

    // ── SERVICES ──
    public function page_services() {
        $services = SB_DB::get_services();
        $cats     = SB_DB::get_categories();
        $currency = get_option( 'sb_currency', 'FCFA' );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Services', 'Manage your bookable services' ); ?>

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
                <thead><tr><th>Color</th><th>Name</th><th>Duration</th><th>Price</th><th>Deposit</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($services as $sv): ?>
                <tr>
                    <td><span class="sbw-color-dot" style="background:<?php echo esc_attr($sv->color); ?>"></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.6rem">
                        <?php if (!empty($sv->image_url)): ?>
                        <img src="<?php echo esc_url($sv->image_url); ?>" style="width:40px;height:40px;border-radius:6px;object-fit:cover;flex-shrink:0" alt="">
                        <?php else: ?>
                        <span style="width:40px;height:40px;border-radius:6px;background:<?php echo esc_attr($sv->color); ?>;flex-shrink:0;display:inline-block"></span>
                        <?php endif; ?>
                        <div>
                        <strong><?php echo esc_html($sv->name); ?></strong>
                        <?php if ($sv->category_name): ?><br><small><?php echo esc_html($sv->category_name); ?></small><?php endif; ?>
                        </div></div>
                    </td>
                    <td><?php echo SB_Services::format_duration($sv->duration); ?></td>
                    <td><?php echo $sv->price > 0 ? number_format($sv->price).' '.esc_html($currency) : 'Free'; ?></td>
                    <td><?php echo $sv->deposit_pct > 0 ? $sv->deposit_pct.'%' : '—'; ?></td>
                    <td><span class="sbw-badge sbw-<?php echo $sv->status; ?>"><?php echo ucfirst($sv->status); ?></span></td>
                    <td>
                        <button class="sbw-edit-svc" data-id="<?php echo $sv->id; ?>" data-name="<?php echo esc_attr($sv->name); ?>"
                            data-duration="<?php echo $sv->duration; ?>" data-padding="<?php echo $sv->padding_time; ?>"
                            data-price="<?php echo $sv->price; ?>" data-deposit="<?php echo $sv->deposit_pct; ?>"
                            data-color="<?php echo esc_attr($sv->color); ?>" data-cat="<?php echo $sv->category_id; ?>"
                            data-desc="<?php echo esc_attr($sv->description); ?>" data-cap="<?php echo $sv->capacity; ?>"
                            data-status="<?php echo $sv->status; ?>"
                            data-image="<?php echo esc_attr($sv->image_url ?? ''); ?>">Edit</button>
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
                <div class="sbw-modal-head"><h3 id="sbSvcModalTitle">Add Service</h3><button class="sbw-modal-close">✕</button></div>
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
                    <div class="sbw-row3">
                        <div class="sbw-fg"><label>Duration (min) *</label><input type="number" id="sbSvcDuration" value="60" min="5"></div>
                        <div class="sbw-fg"><label>Buffer</label><input type="number" id="sbSvcPadding" value="0" min="0"></div>
                        <div class="sbw-fg"><label>Max bookings</label><input type="number" id="sbSvcCap" value="1" min="1"></div>
                    </div>
                    <div class="sbw-row3">
                        <div class="sbw-fg"><label>Price (<?php echo esc_html($currency); ?>)</label><input type="number" id="sbSvcPrice" value="0" min="0"></div>
                        <div class="sbw-fg"><label>Deposit %</label><input type="number" id="sbSvcDeposit" value="0" min="0" max="100"></div>
                        <div class="sbw-fg"><label>Color</label><input type="text" id="sbSvcColor" class="sbw-color" value="#5B2D8E"></div>
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
                </div>
                <div class="sbw-modal-foot">
                    <button class="sbw-btn-ghost sbw-modal-close">Cancel</button>
                    <button class="sbw-btn" id="sbSaveSvcBtn">Save Service</button>
                </div>
            </div>
        </div>
        </div><?php
    }

    // ── STAFF ─
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
            <div class="sbw-empty"><p>No staff yet.</p></div>
            <?php else: ?>
            <div class="sbw-staff-list">
            <?php foreach ($staff_all as $st):
                $svc_ids = SB_DB::get_services_for_staff($st->id);
                $hours   = SB_DB::get_working_hours($st->id);
            ?>
            <div class="sbw-staff-row">
                <div class="sbw-staff-avatar" style="background:<?php echo esc_attr($st->color); ?>">
                    <?php if ($st->image_url): ?><img src="<?php echo esc_url($st->image_url); ?>" alt="">
                    <?php else: echo strtoupper(substr($st->name,0,1)); endif; ?>
                </div>
                <div class="sbw-staff-info">
                    <strong><?php echo esc_html($st->name); ?></strong>
                    <span><?php echo esc_html($st->email); ?></span>
                    <span><?php echo count($svc_ids); ?> service(s) assigned</span>
                </div>
                <div class="sbw-staff-acts">
                    <button class="sbw-btn sbw-edit-staff" data-id="<?php echo $st->id; ?>"
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
                <div class="sbw-modal-head"><h3 id="sbStaffModalTitle">Add Staff Member</h3><button class="sbw-modal-close-staff">✕</button></div>
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
                        <div class="sbw-hours-header"><span>Day</span><span>Off</span><span>From</span><span>To</span></div>
                        <?php foreach ($days as $d => $dname): ?>
                        <div class="sbw-hours-row" data-day="<?php echo $d; ?>">
                            <span><?php echo $dname; ?></span>
                            <input type="checkbox" class="sbw-day-off" data-day="<?php echo $d; ?>">
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

    // ── CUSTOMERS 
    public function page_customers() {
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $customers = SB_DB::get_customers( $search );
        $currency  = get_option( 'sb_currency', 'FCFA' );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Customers', count($customers) . ' customers' ); ?>
        <div class="sbw-card">
            <div class="sbw-card-head">
                <h2>Customer List</h2>
                <form method="get"><input type="hidden" name="page" value="sb-customers">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email, phone...">
                    <button type="submit" class="sbw-btn">Search</button>
                </form>
            </div>
            <?php if (empty($customers)): ?>
            <div class="sbw-empty"><p>No customers found.</p></div>
            <?php else: ?>
            <table class="sbw-tbl">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Total Spent</th><th>Since</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?php echo esc_html($c->name); ?></strong></td>
                    <td><?php echo esc_html($c->email ?: '—'); ?></td>
                    <td><?php echo esc_html($c->phone ?: '—'); ?></td>
                    <td><?php echo intval($c->total_bookings); ?></td>
                    <td><?php echo $c->total_spent > 0 ? number_format($c->total_spent).' '.esc_html($currency) : '—'; ?></td>
                    <td><?php echo date('d M Y', strtotime($c->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        </div><?php
    }

    public function page_calendar_redirect() {
        wp_redirect( admin_url('admin.php?page=sb-calendar') );
    }

    // ── SETTINGS ───
    public function page_settings() {
        $s  = get_option( 'sb_settings',    array() );
        $ap = get_option( 'sb_appearance',  array() );
        $saved = ! empty( $_GET['saved'] );
        ?>
        <div class="wrap sbw">
        <?php $this->head( 'Settings' ); ?>
        <?php if ($saved): ?><div class="sbw-notice sbw-notice-ok">Settings saved.</div><?php endif; ?>

        <div class="sbw-card">
        <form id="sbSettingsForm">
        <?php wp_nonce_field('sb_adm','sb_nonce'); ?>
        <input type="hidden" name="action" value="sb_save_settings">

        <h3 class="sbw-section-title">General</h3>
        <div class="sbw-row2">
            <div class="sbw-fg"><label>Business Name</label><input type="text" name="business_name" value="<?php echo esc_attr($s['business_name']??get_bloginfo('name')); ?>"></div>
            <div class="sbw-fg"><label>Currency</label>
                <select name="currency">
                    <?php foreach (array('FCFA','XOF','GHS','NGN','KES','USD','EUR','GBP') as $cur): ?>
                    <option value="<?php echo $cur; ?>" <?php selected(get_option('sb_currency','FCFA'),$cur); ?>><?php echo $cur; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="sbw-row2">
            <div class="sbw-fg"><label>Admin Notification Email</label><input type="email" name="notify_email" value="<?php echo esc_attr($s['notify_email']??get_option('admin_email')); ?>"></div>
            <div class="sbw-fg"><label>WhatsApp Business Number</label><input type="tel" name="whatsapp" value="<?php echo esc_attr(get_option('sb_whatsapp','')); ?>" placeholder="e.g. 237678899434"></div>
        </div>

        <h3 class="sbw-section-title">Deposit Settings</h3>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Default Deposit % (0 = no deposit required)</label>
                <input type="number" name="default_deposit_pct" min="0" max="100"
                    value="<?php echo esc_attr(get_option('sb_default_deposit_pct', 0)); ?>"
                    placeholder="e.g. 30">
                <small style="color:#64748b;font-size:.78rem;margin-top:4px">
                    Applied when a service has no individual deposit % set. Set to 0 to disable deposits globally.
                </small>
            </div>
            <div class="sbw-fg">
                <label>Appointment Rules (shown to client at booking)</label>
                <textarea name="appointment_rules" rows="3"
                    placeholder="e.g. Please confirm 6 hours before your appointment. Arriving 15+ minutes late may result in a cancellation fee."
                    style="font-size:.85rem"><?php echo esc_textarea(get_option('sb_appointment_rules',
                    'Please confirm your appointment at least 6 hours before the scheduled time. Arriving 15 or more minutes late may result in a cancellation fee of 10% of the service price. No-shows will lose their deposit. Cancellations with less than 24 hours notice are non-refundable.')); ?></textarea>
            </div>
        </div>

        <h3 class="sbw-section-title">Floating Button</h3>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label><input type="checkbox" name="float_on" value="1" <?php checked(!empty($s['float_on'])); ?>> Enable floating button</label>
            </div>
            <div class="sbw-fg"><label>Type</label>
                <select name="float_type">
                    <option value="whatsapp" <?php selected($s['float_type']??'whatsapp','whatsapp'); ?>>WhatsApp</option>
                    <option value="phone"    <?php selected($s['float_type']??'','phone'); ?>>Phone Call</option>
                </select>
            </div>
        </div>
        <div class="sbw-row2">
            <div class="sbw-fg"><label>Button Number</label><input type="tel" name="float_number" value="<?php echo esc_attr($s['float_number']??''); ?>"></div>
            <div class="sbw-fg"><label>Button Label</label><input type="text" name="float_label" value="<?php echo esc_attr($s['float_label']??'Chat with us'); ?>"></div>
        </div>

        <h3 class="sbw-section-title">Terms &amp; Conditions / Privacy Policy</h3>
        <div class="sbw-fg">
            <label>Terms &amp; Conditions Text</label>
            <textarea name="terms_text" rows="6"
                placeholder="Enter your full terms and conditions here..."
                style="font-size:.85rem;font-family:inherit"><?php echo esc_textarea(get_option('sb_terms_text',
                'By completing this booking you agree to our appointment policies. Deposits are non-refundable in the case of no-show or cancellation within 24 hours. We reserve the right to cancel bookings that arrive 15+ minutes late. Your personal data (name, phone, email) is used solely to manage your appointment and will not be shared with third parties.')); ?></textarea>
        </div>

        <h3 class="sbw-section-title">Appearance</h3>
        <div class="sbw-row3">
            <div class="sbw-fg"><label>Primary Color</label><input type="text" name="ap_primary" class="sbw-color" value="<?php echo esc_attr($ap['primary']??'#5B2D8E'); ?>"></div>
            <div class="sbw-fg"><label>Accent Color</label><input type="text" name="ap_accent" class="sbw-color" value="<?php echo esc_attr($ap['accent']??'#C9A84C'); ?>"></div>
            <div class="sbw-fg"><label>Corner Radius (px)</label><input type="number" name="ap_radius" value="<?php echo esc_attr($ap['radius']??10); ?>" min="0" max="30"></div>
        </div>

        <div class="sbw-fg" style="margin-top:20px">
            <h3 class="sbw-section-title">Shortcode</h3>
            <p>Place this shortcode on any page to display the booking widget:</p>
            <code>[smartbooking]</code>
            <p>To pre-select a service: <code>[smartbooking service="ID"]</code></p>
        </div>

        <button type="submit" class="sbw-btn" style="margin-top:20px">Save Settings</button>
        </form>
        </div>
        </div><?php
    }

    // ── AJAX HANDLERS ──
    public function ajax_save_service() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        // Include image_url from POST data
        $_POST['image_url'] = esc_url_raw( $_POST['image_url'] ?? '' );
        $id = SB_DB::save_service( $_POST );
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
            'business_name' => sanitize_text_field( $_POST['business_name'] ?? '' ),
            'notify_email'  => sanitize_email(      $_POST['notify_email']  ?? '' ),
            'float_on'      => ! empty( $_POST['float_on'] ) ? 1 : 0,
            'float_type'    => sanitize_text_field( $_POST['float_type']    ?? 'whatsapp' ),
            'float_number'  => sanitize_text_field( $_POST['float_number']  ?? '' ),
            'float_label'   => sanitize_text_field( $_POST['float_label']   ?? 'Chat with us' ),
        );
        update_option( 'sb_settings', $s );
        update_option( 'sb_default_deposit_pct', intval($_POST['default_deposit_pct'] ?? 0) );
        update_option( 'sb_appointment_rules', sanitize_textarea_field($_POST['appointment_rules'] ?? '') );
        update_option( 'sb_terms_text', wp_kses_post($_POST['terms_text'] ?? '') );
        update_option( 'sb_currency', sanitize_text_field( $_POST['currency'] ?? 'FCFA' ) );
        update_option( 'sb_whatsapp', sanitize_text_field( $_POST['whatsapp'] ?? '' ) );
        update_option( 'sb_appearance', array(
            'primary' => sanitize_hex_color( $_POST['ap_primary'] ?? '#5B2D8E' ),
            'accent'  => sanitize_hex_color( $_POST['ap_accent']  ?? '#C9A84C' ),
            'radius'  => intval( $_POST['ap_radius'] ?? 10 ),
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
}