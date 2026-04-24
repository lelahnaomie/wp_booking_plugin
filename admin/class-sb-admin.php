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

    //  DASHBOARD 
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
                        <button class="sbw-ab sbw-confirm" data-id="<?php echo $b->id; ?>"></button>
                        <button class="sbw-ab sbw-cancel"  data-id="<?php echo $b->id; ?>"></button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
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
            <thead><tr><th>#</th><th>Client</th><th>Service</th><th>Staff</th><th>Date &amp; Arrival Time</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ( $bookings as $b ): ?>
            <tr data-id="<?php echo $b->id; ?>">
                <td><b>#<?php echo $b->id; ?></b></td>
                <td>
                    <strong><?php echo esc_html($b->customer_name); ?></strong>
                    <?php if ($b->customer_phone): ?><br><small><?php echo esc_html($b->customer_phone); ?></small><?php endif; ?>
                </td>
                <td><span class="sbw-dot" style="background:<?php echo esc_attr($b->service_color ?? '#00000'); ?>"></span><?php echo esc_html($b->service_name); ?></td>
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
        $cal = new SB_Calendar_Page();
        $cal->page_calendar();
    }

    //  SETTINGS 
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
        <?php wp_nonce_field('sb_adm','nonce'); ?>
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

        <h3 class="sbw-section-title">Admin WhatsApp Auto-Notify (Meta Cloud API)</h3>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Admin WhatsApp Number (receives notifications)</label>
                <input type="tel" name="admin_wa_number" value="<?php echo esc_attr($s['admin_wa_number'] ?? ''); ?>" placeholder="e.g. 237678899434">
                <small style="color:#64748b;font-size:.78rem;margin-top:4px">
                    Full number with country code, no + sign. e.g. <strong>237654231456</strong>
                </small>
            </div>
            <div class="sbw-fg">
                <label>Meta Phone Number ID</label>
                <input type="text" name="meta_wa_phone_id" value="<?php echo esc_attr($s['meta_wa_phone_id']??''); ?>" placeholder="e.g. 123456789012345">
                <small style="color:#64748b;font-size:.78rem;margin-top:4px">
                    Found in: Meta Developers → Your App → WhatsApp → API Setup → <strong>Phone Number ID</strong>
                </small>
            </div>
        </div>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Meta Permanent Access Token</label>
                <input type="text" name="meta_wa_token" value="<?php echo esc_attr($s['meta_wa_token']??''); ?>" placeholder="EAAxxxxxxxxxxxxxxx">
                <small style="color:#64748b;font-size:.78rem;margin-top:4px">
                    Found in: Meta Developers → Your App → WhatsApp → API Setup → <strong>Access Token</strong>. Use a <strong>permanent</strong> token (System User token), not the temporary one.
                </small>
            </div>
            <div class="sbw-fg" style="display:flex;align-items:center;padding-top:24px">
                <small style="color:#64748b;font-size:.82rem;line-height:1.6">
                    After saving, make sure your admin number <strong></strong> has been added as a recipient in your Meta App's WhatsApp sandbox or is verified on the API. Free tier allows up to 1,000 messages/month.
                </small>
            </div>
        </div>

        <div style="margin-top:16px">
            <button type="button" id="sbTestWa" class="button button-secondary" style="background:#25D366;color:#fff;border-color:#25D366;font-weight:600;padding:6px 20px;font-size:.9rem">
                📲 Send Test WhatsApp Now
            </button>
            <span id="sbTestWaSpinner" style="display:none;margin-left:10px;color:#64748b;font-size:.85rem">⏳ Sending…</span>
        </div>

        <div id="sbWaConsole" style="display:none;margin-top:14px;background:#0f172a;color:#e2e8f0;font-family:monospace;font-size:.8rem;line-height:1.7;padding:16px 20px;border-radius:8px;max-height:320px;overflow-y:auto;white-space:pre-wrap;border:2px solid #1e293b">
        </div>

        <script>
        (function($){
            // Nonce: use whichever global is available
            var sbTestNonce = (typeof SB_ADM !== 'undefined' && SB_ADM.nonce)
                ? SB_ADM.nonce
                : (typeof SB !== 'undefined' && SB.nonce ? SB.nonce : '<?php echo esc_js( wp_create_nonce("sb_adm") ); ?>');

            // HANDLE SETTINGS FORM SUBMISSION
            $('#sbSettingsForm').on('submit', function(e){
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');
                var btnText = $btn.text();
                var formData = $form.serialize();
                $btn.prop('disabled', true).text('💾 Saving...');
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData + '&nonce=' + sbTestNonce,
                    success: function(res) {
                        $btn.prop('disabled', false).text('✓ Saved!');
                        setTimeout(function() { location.reload(); }, 1500);
                    },
                    error: function(xhr, status, err) {
                        $btn.prop('disabled', false).text(btnText);
                        alert('Error saving settings:\n' + (xhr.responseJSON?.data?.msg || xhr.responseText || err || 'Unknown error'));
                    }
                });
            });

            // Test WhatsApp button
            $('#sbTestWa').on('click', function(){
                var $btn = $(this);
                var $spinner = $('#sbTestWaSpinner');
                var $console = $('#sbWaConsole');

                $btn.prop('disabled', true);
                $spinner.show();
                $console.show().html('<span style="color:#94a3b8">Running test… (may take up to 30s)</span>');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    timeout: 35000,
                    data: {
                        action: 'sb_test_whatsapp',
                        nonce: sbTestNonce
                    },
                    success: function(res) {
                        $btn.prop('disabled', false);
                        $spinner.hide();

                        var lines = res.data && res.data.log ? res.data.log : ['No response data received.'];
                        var html = '';
                        lines.forEach(function(line){
                            var color = '#e2e8f0';
                            if (line.indexOf('✅') !== -1) color = '#4ade80';
                            else if (line.indexOf('❌') !== -1 || line.indexOf('🛑') !== -1) color = '#f87171';
                            else if (line.indexOf('💡') !== -1) color = '#fbbf24';
                            else if (line.indexOf('📡') !== -1 || line.indexOf('📥') !== -1 || line.indexOf('📋') !== -1 || line.indexOf('🌐') !== -1) color = '#818cf8';
                            html += '<span style="color:' + color + '">' + $('<div>').text(line).html() + '</span>\n';
                        });
                        $console.html(html);
                        $console.scrollTop($console[0].scrollHeight);
                    },
                    error: function(xhr, status, err) {
                        $btn.prop('disabled', false);
                        $spinner.hide();
                        var detail = 'Status: ' + xhr.status + ' ' + xhr.statusText;
                        if (status === 'timeout') detail = 'Request timed out after 35s.\nYour server may be blocking outbound connections entirely.';
                        $console.show().html(
                            '<span style="color:#f87171">❌ AJAX request failed.\n' + detail + '\n\n' +
                            '<span style="color:#fbbf24">💡 Try: go to your WordPress dashboard → Tools → Site Health\n' +
                            '   Look for "Can perform loopback requests" and "HTTP requests".\n' +
                            '   Also check if your host blocks outbound HTTP from PHP.</span></span>'
                        );
                    }
                });
            });
        })(jQuery);
        </script>

        <h3 class="sbw-section-title">Booking Form Customization</h3>
           <div class="sbw-row2">
    <div class="sbw-fg">
        <label>Service Label</label>
        <input type="text" name="service_label"
            value="<?php echo esc_attr($s['service_label'] ?? 'Choose a Service'); ?>">
    </div>
</div>
<div class="sbw-row2">
    <div class="sbw-fg">
        <label>
            <input type="checkbox" name="enable_staff" value="1" id="sbEnableStaffCb"
                <?php checked($s['enable_staff'] ?? 0, 1); ?>>
            Enable Staff Selection
        </label>
        <small style="color:#64748b;font-size:.78rem;margin-top:5px;display:block">
            ✓ On — customers pick a staff member (salons, clinics, tutors).<br>
            ○ Off — staff step hidden; all 7 days open for booking (car rentals, halls, rooms).
        </small>
    </div>
    <div class="sbw-row2">
    <div class="sbw-fg">
        <label>
            <input type="checkbox" name="enable_days" value="1"
                <?php checked($s['enable_days'] ?? 0, 1); ?>>
            Enable Number of Days
        </label>
        <small style="color:#64748b;font-size:.78rem;margin-top:5px;display:block">
            ✓ Checked: Customers select <strong>number of days</strong>, price multiplies (1 day, 2 days, 3 days, etc.)<br>
            ○ Unchecked: Standard single-session booking (salons, clinics, etc.).<br>
            This is <strong>optional and independent</strong> of staff selection — works with or without staff enabled.
        </small>
    </div>
</div>
    <div class="sbw-fg" id="sbGlobalHoursWrap" <?php echo !empty($s['enable_staff']) ? 'style="display:none"' : ''; ?>>
        <label style="font-weight:600;display:block;margin-bottom:6px">
            Business Hours <small style="color:#64748b;font-weight:400">(used when Staff Selection is OFF)</small>
        </label>
        <div style="display:flex;gap:12px;align-items:center">
            <div>
                <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:3px">Opens</label>
                <input type="time" name="global_open_time"
                    value="<?php echo esc_attr(get_option('sb_global_open_time','08:00')); ?>"
                    style="padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:6px;font-size:.9rem">
            </div>
            <span style="color:#94a3b8;font-size:1.1rem;margin-top:14px">–</span>
            <div>
                <label style="font-size:.75rem;color:#64748b;display:block;margin-bottom:3px">Closes</label>
                <input type="time" name="global_close_time"
                    value="<?php echo esc_attr(get_option('sb_global_close_time','18:00')); ?>"
                    style="padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:6px;font-size:.9rem">
            </div>
        </div>
        <small style="color:#64748b;font-size:.78rem;margin-top:5px;display:block">
            Slots are generated between these hours, every day. Default: 08:00 – 18:00.
        </small>
    </div>
</div>
<script>
(function(){
    var cb=document.getElementById('sbEnableStaffCb');
    var box=document.getElementById('sbGlobalHoursWrap');
    if(cb&&box){
        cb.addEventListener('change',function(){ box.style.display=this.checked?'none':''; });
    }
})();
</script>
<div class="sbw-row2" style="display:none"><!-- placeholder to balance closing tags-->
    <div class="sbw-fg"><label>
            <input type="checkbox" name="enable_staff_REMOVE" value="1" style="display:none
        </label>
    </div>
</div>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Form Max Width on Desktop (pixels)</label>
                <input type="number" name="form_max_width" value="<?php echo esc_attr(get_option('sb_form_max_width', '900')); ?>" min="600" max="1400" placeholder="900" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px;width:100%">
                <small style="color:#64748b;font-size:.78rem;margin-top:4px;display:block">
                    Desktop form width: 600-1400px. Mobile is fixed at 520px. Default: 900px. Increase for wider displays.
                </small>
            </div>
        </div>

        <!-- <div class="sbw-row2">
            <div class="sbw-fg">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500">
                    <input type="checkbox" name="show_categories" value="1" <?php checked(get_option('sb_show_categories', 1), 1); ?> style="width:18px;height:18px;cursor:pointer">
                    <span>Show Category Tabs?</span>
                </label>
                <small style="color:#64748b;font-size:.78rem;margin-top:4px;display:block">
                    ✓ Checked: Shows category tabs (Automatic/Manual, Deluxe/Economy, etc.)<br>
                    ○ Unchecked: No tabs, all items display in one list
                </small>
            </div>
            <div class="sbw-fg">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500">
                    <input type="checkbox" name="require_staff" value="1" <?php checked(get_option('sb_require_staff', 1), 1); ?> style="width:18px;height:18px;cursor:pointer">
                    <span>Require Staff Selection?</span>
                </label>
                <small style="color:#64748b;font-size:.78rem;margin-top:4px;display:block">
                    ✓ Checked: Staff step is required (good for salons, beauty services)<br>
                    ○ Unchecked: Staff is optional/hidden (good for car rentals, property rentals)
                </small>
            </div>
        </div> -->

        <!-- <div class="sbw-row2">
            <div class="sbw-fg">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500">
                    <input type="checkbox" name="enable_daily_rental" value="1" <?php checked(get_option('sb_enable_daily_rental', 0), 1); ?> style="width:18px;height:18px;cursor:pointer">
                    <span>Enable Daily Rental Mode?</span>
                </label>
                <small style="color:#64748b;font-size:.78rem;margin-top:4px;display:block">
                    ✓ Checked: Customers select <strong>number of days</strong>, price multiplies (1 day, 2 days, 3 days, etc.)<br>
                    ○ Unchecked: Uses hourly time slots (default appointment mode)<br>
                    Perfect for: Guest houses, car rentals, halls, event spaces (billing by day)
                </small>
            </div> -->
            <div class="sbw-fg">
                <label style="font-weight:500;display:block;margin-bottom:8px">Notification Methods</label>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="notify_whatsapp" value="1" <?php checked(get_option('sb_notify_whatsapp', 1), 1); ?> style="width:18px;height:18px;cursor:pointer">
                        <span>WhatsApp (via Meta Cloud API)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="notify_email_method" value="1" <?php checked(get_option('sb_notify_email', 1), 1); ?> style="width:18px;height:18px;cursor:pointer">
                        <span>Email (reliable fallback)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="notify_sms" value="1" <?php checked(get_option('sb_notify_sms', 0), 1); ?> style="width:18px;height:18px;cursor:pointer">
                        <span>SMS (requires SMS gateway)</span>
                    </label>
                </div>
                <small style="color:#64748b;font-size:.78rem;margin-top:8px;display:block">
                    Select which notification methods to use when bookings are confirmed. Email is always sent as fallback if other methods fail.
                </small>
            </div>
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
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Button Color</label>
                <input type="text" name="float_color" class="sbw-color" value="<?php echo esc_attr($s['float_color'] ?? ''); ?>" placeholder="Leave empty to use site primary color">
                <small style="color:#64748b;font-size:.78rem;margin-top:4px;display:block">Leave blank to automatically inherit your site's primary color. Pick a custom color to override it.</small>
            </div>
        </div>

        <h3 class="sbw-section-title">Policies &amp; Terms — shown as popup on booking form</h3>
        <p style="font-size:.85rem;color:#64748b;margin:-10px 0 14px">
            Fill in the policies that apply to your business. Leave a section blank to hide it.
            These appear as a "View Policies" link on the booking form that opens a popup.
        </p>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Cancellation &amp; Late Policy</label>
                <textarea name="appointment_rules" rows="6" style="font-size:.85rem;font-family:inherit"
                    placeholder="e.g. Please cancel at least 6 hours before your appointment. Arriving 15+ minutes late may result in shortened service. No-shows forfeit their deposit."><?php echo esc_textarea(get_option('sb_appointment_rules','')); ?></textarea>
            </div>
            <div class="sbw-fg">
                <label>Refund &amp; Deposit Policy</label>
                <textarea name="refund_text" rows="6" style="font-size:.85rem;font-family:inherit"
                    placeholder="e.g. Deposits are non-refundable for cancellations within 24 hours. Full refunds for cancellations made 48+ hours in advance."><?php echo esc_textarea(get_option('sb_refund_text','')); ?></textarea>
            </div>
        </div>
        <div class="sbw-row2">
            <div class="sbw-fg">
                <label>Terms &amp; Conditions</label>
                <textarea name="terms_text" rows="6" style="font-size:.85rem;font-family:inherit"
                    placeholder="General terms: service scope, liability, age requirements, acceptable conduct..."><?php echo esc_textarea(get_option('sb_terms_text','')); ?></textarea>
            </div>
            <div class="sbw-fg">
                <label>Privacy Policy</label>
                <textarea name="privacy_text" rows="6" style="font-size:.85rem;font-family:inherit"
                    placeholder="e.g. We collect your name, phone, and email to manage your appointment. Your data is not shared with third parties. We may contact you via WhatsApp to confirm your appointment."><?php echo esc_textarea(get_option('sb_privacy_text','')); ?></textarea>
            </div>
        </div>

        <h3 class="sbw-section-title">Payment Gateways</h3>
        <p style="font-size:.85rem;color:#64748b;margin:-10px 0 14px">
            Add your gateway keys below to enable online deposit collection. Leave all fields empty to use WhatsApp / manual payment only.
        </p>

        <h4 style="margin:0 0 10px;font-size:.9rem;color:#374151">Paystack (Card / Bank Transfer — Nigeria, Ghana, Kenya, South Africa)</h4>
        <div class="sbw-row2">
            <div class="sbw-fg"><label>Paystack Public Key</label><input type="text" name="paystack_public_key" value="<?php echo esc_attr($s['paystack_public_key']??''); ?>" placeholder="pk_live_…"></div>
            <div class="sbw-fg"><label>Paystack Secret Key</label><input type="password" name="paystack_secret_key" value="<?php echo esc_attr($s['paystack_secret_key']??''); ?>" placeholder="sk_live_…"></div>
        </div>

        <h4 style="margin:14px 0 10px;font-size:.9rem;color:#374151">Flutterwave (MTN / Orange Mobile Money — Cameroon, Francophone Africa)</h4>
        <div class="sbw-row2">
            <div class="sbw-fg"><label>Flutterwave Public Key</label><input type="text" name="flw_public_key" value="<?php echo esc_attr($s['flw_public_key']??''); ?>" placeholder="FLWPUBK_TEST-…"></div>
            <div class="sbw-fg"><label>Flutterwave Secret Key</label><input type="password" name="flw_secret_key" value="<?php echo esc_attr($s['flw_secret_key']??''); ?>" placeholder="FLWSECK_TEST-…"></div>
        </div>

        <h4 style="margin:14px 0 10px;font-size:.9rem;color:#374151">CamPay (MTN / Orange Mobile Money — Cameroon only)</h4>
        <div class="sbw-row2">
            <div class="sbw-fg"><label>CamPay App Username</label><input type="text" name="campay_username" value="<?php echo esc_attr($s['campay_username']??''); ?>" placeholder="Your CamPay app username"></div>
            <div class="sbw-fg"><label>CamPay App Password</label><input type="password" name="campay_password" value="<?php echo esc_attr($s['campay_password']??''); ?>" placeholder="Your CamPay app password"></div>
        </div>
        <div class="sbw-fg" style="margin-top:6px">
            <label><input type="checkbox" name="campay_sandbox" value="1" <?php checked(!empty($s['campay_sandbox'])); ?>> Use CamPay Sandbox (test mode — uncheck for live)</label>
            <small style="color:#64748b;font-size:.78rem;display:block;margin-top:4px">Get credentials at <a href="https://demo.campay.net" target="_blank">demo.campay.net</a> (sandbox) or <a href="https://www.campay.net" target="_blank">campay.net</a> (live)</small>
        </div>

        <div class="sbw-card" style="margin-top:14px;padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px">
            <h4 style="margin:0 0 8px;font-size:.9rem;color:#15803d">⚡ CamPay Auto-Withdrawal (Direct Payment to MoMo)</h4>
            <p style="font-size:.82rem;color:#166534;margin:0 0 12px">When enabled, payments collected via CamPay will automatically be disbursed to your Mobile Money account (minus 5% platform fee). Leave blank to disable auto-withdrawal.</p>
            <div class="sbw-row2">
                <div class="sbw-fg">
                    <label>MoMo Account Name</label>
                    <input type="text" name="campay_momo_name" value="<?php echo esc_attr($s['campay_momo_name']??''); ?>" placeholder="e.g. Ismaeal Ndongo">
                </div>
                <div class="sbw-fg">
                    <label>MoMo Phone Number</label>
                    <input type="text" name="campay_momo_number" value="<?php echo esc_attr($s['campay_momo_number']??''); ?>" placeholder="e.g. 237671234567">
                    <small style="color:#64748b;font-size:.77rem">Include country code (e.g. 237 for Cameroon). MTN or Orange.</small>
                </div>
            </div>
            <p style="font-size:.8rem;color:#374151;margin:8px 0 0"><strong>How it works:</strong> Customer pays 100 FCFA → 95 FCFA goes straight to your MoMo number → 5 FCFA platform fee retained.</p>
        </div>

        <h3 class="sbw-section-title">Appearance</h3>
        <p style="font-size:.83rem;color:#64748b;margin:-8px 0 16px">These colors control every part of the booking widget. Changes take effect immediately after saving.</p>
        <div class="sbw-row3">
            <div class="sbw-fg"><label>Primary Color <small style="color:#64748b">buttons, selected cards, links</small></label><input type="text" name="ap_primary" class="sbw-color" value="<?php echo esc_attr($ap['primary']??'#5B2D8E'); ?>"></div>
            <div class="sbw-fg"><label>Header Bar Color <small style="color:#64748b">top strip background</small></label><input type="text" name="ap_p_dark" class="sbw-color" value="<?php echo esc_attr($ap['p_dark']??'#3b1a5c'); ?>"></div>
            <div class="sbw-fg"><label>Header Text Color <small style="color:#64748b">step labels in header</small></label><input type="text" name="ap_hd_text" class="sbw-color" value="<?php echo esc_attr($ap['hd_text']??'#ffffff'); ?>"></div>
            <div class="sbw-fg"><label>Accent Color <small style="color:#64748b">active step bubble</small></label><input type="text" name="ap_accent" class="sbw-color" value="<?php echo esc_attr($ap['accent']??'#C9A84C'); ?>"></div>
            <div class="sbw-fg"><label>Hover Color <small style="color:#64748b">button &amp; card hover</small></label><input type="text" name="ap_hover" class="sbw-color" value="<?php echo esc_attr($ap['hover']??'#3b1a5c'); ?>"></div>
            <div class="sbw-fg"><label>Arrow / Nav Color <small style="color:#64748b">calendar arrows</small></label><input type="text" name="ap_arrow" class="sbw-color" value="<?php echo esc_attr($ap['arrow']??'#5B2D8E'); ?>"></div>
            <div class="sbw-fg"><label>Form Background <small style="color:#64748b">widget background</small></label><input type="text" name="ap_bg" class="sbw-color" value="<?php echo esc_attr($ap['bg']??'#ffffff'); ?>"></div>
            <div class="sbw-fg"><label>Text Color <small style="color:#64748b">all body text</small></label><input type="text" name="ap_text" class="sbw-color" value="<?php echo esc_attr($ap['text']??'#1a1a2e'); ?>"></div>
            <div class="sbw-fg"><label>Button Text Color <small style="color:#64748b">text on primary buttons</small></label><input type="text" name="ap_btn_text" class="sbw-color" value="<?php echo esc_attr($ap['btn_text']??'#ffffff'); ?>"></div>
            <div class="sbw-fg"><label>Corner Radius (px) <small style="color:#64748b">0 = sharp, 20 = round</small></label><input type="number" name="ap_radius" value="<?php echo esc_attr($ap['radius']??10); ?>" min="0" max="30" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px;width:100%"></div>
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
            'float_on'           => ! empty( $_POST['float_on'] ) ? 1 : 0,
            'float_type'         => sanitize_text_field( $_POST['float_type']         ?? 'whatsapp' ),
            'float_number'       => sanitize_text_field( $_POST['float_number']       ?? '' ),
            'float_label'        => sanitize_text_field( $_POST['float_label']        ?? 'Chat with us' ),
            'float_color'        => sanitize_hex_color( $_POST['float_color'] ?? '' ),
            'service_label' => sanitize_text_field($_POST['service_label'] ?? 'Choose a Service'),
            'enable_staff' => isset($_POST['enable_staff']) ? 1 : 0,
            'enable_days' => isset($_POST['enable_days']) ? 1 : 0,
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
            'campay_sandbox'      => ! empty( $_POST['campay_sandbox'] ) ? 1 : 0,
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
        update_option( 'sb_whatsapp', sanitize_text_field( $_POST['whatsapp'] ?? '' ) );
        update_option( 'sb_step1_heading',    sanitize_text_field( $_POST['step1_heading']    ?? 'Choose a Service' ) );
        update_option( 'sb_global_open_time',  sanitize_text_field( $_POST['global_open_time']  ?? '08:00' ) );
        update_option( 'sb_global_close_time', sanitize_text_field( $_POST['global_close_time'] ?? '18:00' ) );
        update_option( 'sb_form_max_width', intval($_POST['form_max_width'] ?? 900) );
        update_option( 'sb_show_categories', ! empty($_POST['show_categories']) ? 1 : 0 );
        update_option( 'sb_require_staff', ! empty($_POST['require_staff']) ? 1 : 0 );
        update_option( 'sb_enable_daily_rental', ! empty($_POST['enable_daily_rental']) ? 1 : 0 );
        update_option( 'sb_notify_whatsapp', ! empty($_POST['notify_whatsapp']) ? 1 : 0 );
        update_option( 'sb_notify_email', ! empty($_POST['notify_email_method']) ? 1 : 0 );
        update_option( 'sb_notify_sms', ! empty($_POST['notify_sms']) ? 1 : 0 );
        update_option( 'sb_appearance', array(
            'primary'  => sanitize_hex_color( $_POST['ap_primary']  ?? '' ) ?: '#5B2D8E',
            'p_dark'   => sanitize_hex_color( $_POST['ap_p_dark']   ?? '' ) ?: '#3b1a5c',
            'hd_text'  => sanitize_hex_color( $_POST['ap_hd_text']  ?? '' ) ?: '#ffffff',
            'accent'   => sanitize_hex_color( $_POST['ap_accent']   ?? '' ) ?: '#C9A84C',
            'hover'    => sanitize_hex_color( $_POST['ap_hover']    ?? '' ) ?: '#3b1a5c',
            'arrow'    => sanitize_hex_color( $_POST['ap_arrow']    ?? '' ) ?: '#5B2D8E',
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