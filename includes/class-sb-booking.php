<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Booking_Handler {

    private static $ajax_done = false;

    public function __construct() {
        if ( ! self::$ajax_done ) {
            add_action( 'wp_ajax_sb_get_staff',        array( $this, 'ajax_get_staff' ) );
            add_action( 'wp_ajax_nopriv_sb_get_staff', array( $this, 'ajax_get_staff' ) );
            add_action( 'wp_ajax_sb_get_slots',        array( $this, 'ajax_get_slots' ) );
            add_action( 'wp_ajax_nopriv_sb_get_slots', array( $this, 'ajax_get_slots' ) );
            add_action( 'wp_ajax_sb_get_unavailable',        array( $this, 'ajax_get_unavailable' ) );
            add_action( 'wp_ajax_nopriv_sb_get_unavailable', array( $this, 'ajax_get_unavailable' ) );
            add_action( 'wp_ajax_sb_submit_booking',        array( $this, 'ajax_submit' ) );
            add_action( 'wp_ajax_nopriv_sb_submit_booking', array( $this, 'ajax_submit' ) );
            // Payment verification handlers
            add_action( 'wp_ajax_sb_paystack_verify',        array( $this, 'ajax_paystack_verify' ) );
            add_action( 'wp_ajax_nopriv_sb_paystack_verify', array( $this, 'ajax_paystack_verify' ) );
            add_action( 'wp_ajax_sb_flw_verify',             array( $this, 'ajax_flw_verify' ) );
            add_action( 'wp_ajax_nopriv_sb_flw_verify',      array( $this, 'ajax_flw_verify' ) );
            add_action( 'wp_ajax_sb_campay_initiate',        array( $this, 'ajax_campay_initiate' ) );
            add_action( 'wp_ajax_nopriv_sb_campay_initiate', array( $this, 'ajax_campay_initiate' ) );
            add_action( 'wp_ajax_sb_campay_check',           array( $this, 'ajax_campay_check' ) );
            add_action( 'wp_ajax_nopriv_sb_campay_check',    array( $this, 'ajax_campay_check' ) );
            // Fresh nonce endpoint — bypasses page cache so live sites always get a valid nonce
            add_action( 'wp_ajax_sb_get_nonce',              array( $this, 'ajax_get_nonce' ) );
            add_action( 'wp_ajax_nopriv_sb_get_nonce',       array( $this, 'ajax_get_nonce' ) );
            self::$ajax_done = true;
        }
    }

    /**
     * Returns a fresh nonce via AJAX — not cached by page caches.
     * JS fetches this just before any sensitive POST so live sites
     * never hit a stale cached nonce.
     */
    public function ajax_get_nonce() {
        nocache_headers();
        wp_send_json_success( array( 'nonce' => wp_create_nonce( 'sb_pub' ) ) );
    }

    static function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'service' => '',   // pre-select service id
            'staff'   => '',   // pre-select staff id
        ), $atts, 'smartbooking' );

        $groups     = SB_Services::grouped_by_category();
        $all_staff  = SB_DB::get_staff( true );
        $currency   = get_option( 'sb_currency', 'FCFA' );
        $settings   = get_option( 'sb_settings', array() );
        $appearance = get_option( 'sb_appearance', array() );

        ob_start();
        ?>
        <div class="sb-wrap" id="sbWrap" data-currency="<?php echo esc_attr( $currency ); ?>">

            <!-- STEPS HEADER — clickable tabs -->
            <div class="sb-steps-header">
                <div class="sb-step-bubble active" data-step="1" onclick="SBApp.tabTo(1)" title="Go to Service"><span>1</span><small>Service</small></div>
                <div class="sb-step-line"></div>
                <div class="sb-step-bubble" data-step="2" onclick="SBApp.tabTo(2)" title="Go to Date &amp; Time"><span>2</span><small>Date &amp; Time</small></div>
                <div class="sb-step-line"></div>
                <div class="sb-step-bubble" data-step="3" onclick="SBApp.tabTo(3)" title="Go to Your Info"><span>3</span><small>Your Info</small></div>
                <div class="sb-step-line"></div>
                <div class="sb-step-bubble" data-step="4" onclick="SBApp.tabTo(4)" title="Confirm"><span>&#10003;</span><small>Confirm</small></div>
            </div>

            <!-- ERROR BAR -->
            <div class="sb-error-bar" id="sbError" style="display:none"></div>

            <!--  SERVICE  -->
            <div class="sb-panel active" id="sbStep1">
                <?php if (!empty($settings['enable_days'])): ?>
<div class="sb-field sb-days-field sb-days-field-step1">
    <label><?php echo esc_html( get_option( 'sb_days_label', 'Number of Days' ) ); ?></label>
    <div class="sb-days-stepper">
        <button type="button" class="sb-days-btn sb-days-minus" onclick="sbAdjDays(-1)">−</button>
        <input type="number" id="sbDaysInput" name="sb_days" min="1" value="1" readonly>
        <button type="button" class="sb-days-btn sb-days-plus" onclick="sbAdjDays(1)">+</button>
    </div>
</div>
<?php endif; ?>
                <h2 class="sb-panel-title"><?php echo esc_html( $settings['service_label'] ?? 'Choose a Service' ); ?></h2>
                <?php if ( empty( $groups ) ): ?>
                    <p class="sb-notice">No services available. Please add services in the admin panel.</p>
                <?php else: ?>
                <?php foreach ( $groups as $group ): ?>
                    <div class="sb-cat-group">
                        <h3 class="sb-cat-title"><?php echo esc_html( $group['cat']->name ); ?></h3>
                        <div class="sb-swipe-hint" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            Swipe to see more
                        </div>
                        <div class="sb-service-grid">
                        <?php foreach ( $group['services'] as $svc ): ?>
                            <?php
                            if ( ! empty( $settings['enable_staff'] ) ) {
                                $svc_staff = SB_DB::get_staff_for_service($svc->id);
                                if ( empty($svc_staff) ) $svc_staff = SB_DB::get_staff(true);
                                $svc_staff_json = json_encode(array_map(function($st){
                                    return array('id'=>$st->id,'name'=>$st->name,'color'=>$st->color,'image'=>$st->image_url,'bio'=>$st->bio);
                                }, $svc_staff));
                            } else {
                                $svc_staff_json = '[]';
                            }
                            ?>
                            <div class="sb-service-card" data-id="<?php echo $svc->id; ?>"
                                 data-price="<?php echo floatval( $svc->price ); ?>"
                                 data-deposit="<?php echo floatval( $svc->deposit_pct ); ?>"
                                 data-duration="<?php echo intval( $svc->duration ); ?>"
                                 data-name="<?php echo esc_attr($svc->name); ?>"
                                 data-desc="<?php echo esc_attr($svc->description); ?>"
                                 data-color="<?php echo esc_attr($svc->color); ?>"
                                 data-image="<?php echo esc_attr($svc->image_url); ?>"
                                 data-gallery="<?php echo esc_attr($svc->gallery_images ?? ''); ?>"
                                 data-policy="<?php echo esc_attr($svc->service_policy ?? ''); ?>"
                                 data-staff='<?php echo esc_attr($svc_staff_json); ?>'
                                 style="--svc-color:<?php echo esc_attr( $svc->color ); ?>">
                                <?php if ( $svc->image_url ): ?>
                                <div class="sb-svc-img sb-svc-img-full" style="background-image:url('<?php echo esc_url( $svc->image_url ); ?>')"></div>
                                <?php else: ?>
                                <div class="sb-svc-color-bar"></div>
                                <?php endif; ?>
                                <?php /* Gallery images shown only in popup modal, not on cards */ ?>
                                <div class="sb-svc-body">
                                    <strong><?php echo esc_html( $svc->name ); ?></strong>
                                    <?php if ( $svc->description ): ?>
                                    <p class="sb-svc-desc"><?php echo esc_html( $svc->description ); ?></p>
                                    <?php endif; ?>
                                    <div class="sb-svc-meta">
                                        <span class="sb-svc-dur">⏱ <?php echo ! empty( $settings['enable_days'] ) ? '1 Day' : SB_Services::format_duration( $svc->duration ); ?></span>
                                        <?php if ( $svc->price > 0 ): ?>
                                        <span class="sb-svc-price"><?php echo number_format( $svc->price ); ?> <?php echo esc_html( $currency ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="sb-view-details-btn" type="button">View Details</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php
                $terms_text   = get_option('sb_terms_text','');
                $rules_text   = get_option('sb_appointment_rules','');
                $privacy_text = get_option('sb_privacy_text','');
                $refund_text  = get_option('sb_refund_text','');
                if($terms_text || $rules_text || $privacy_text || $refund_text): ?>
                <p class="sb-policy-link">
                    By booking you agree to our
                    <a href="#" class="sb-tc-link" id="sbOpenPolicy">Terms &amp; Conditions</a>.
                </p>
                <div class="sb-modal-overlay" id="sbPolicyModal" style="display:none">
                    <div class="sb-modal-box">
                        <div class="sb-modal-header">
                            <h3>Terms, Conditions &amp; Booking Policy</h3>
                            <button class="sb-modal-x" id="sbClosePolicy">&#10005;</button>
                        </div>
                        <div class="sb-modal-body">
                            <?php if($rules_text): ?>
                            <div class="sb-policy-section">
                            <h4> Cancellation &amp; Late Policy</h4>
                            <p><?php echo nl2br(esc_html($rules_text)); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if($refund_text): ?>
                            <div class="sb-policy-section">
                            <h4> Refund &amp; Deposit Policy</h4>
                            <p><?php echo nl2br(esc_html($refund_text)); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if($terms_text): ?>
                            <div class="sb-policy-section">
                            <h4> Terms &amp; Conditions</h4>
                            <p><?php echo nl2br(esc_html($terms_text)); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if($privacy_text): ?>
                            <div class="sb-policy-section">
                            <h4> Privacy Policy</h4>
                            <p><?php echo nl2br(esc_html($privacy_text)); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>



            <!--  DATE & TIME  -->
            <div class="sb-panel" id="sbStep2">
                <h2 class="sb-panel-title sb-dt-main-heading">Pick an Arrival Date &amp; Time</h2>
                <div class="sb-datetime-wrap">
                    <div class="sb-calendar-wrap">
                        <h3 class="sb-datetime-section-heading sb-dt-sub-heading">Select an Arrival Date</h3>
                        <div class="sb-cal-nav">
                            <button type="button" class="sb-cal-prev" id="sbCalPrev">&#8592;</button>
                            <span class="sb-cal-month" id="sbCalMonth"></span>
                            <button type="button" class="sb-cal-next" id="sbCalNext">&#8594;</button>
                        </div>
                        <div class="sb-cal-grid" id="sbCalGrid"></div>
                    </div>
                    <div class="sb-slots-wrap">
                        <h3 class="sb-datetime-section-heading sb-dt-sub-heading">Select an Arrival Time</h3>
                        <h3 class="sb-slots-title" id="sbSlotsTitle">Select a date to see available arrival times</h3>
                        <div class="sb-slots-grid" id="sbSlotsGrid"></div>
                    </div>
                </div>
                <div class="sb-nav">
                    <button class="sb-btn sb-btn-back" onclick="SBApp.goTo(1)">&#8592; Back</button>
                    <button class="sb-btn sb-btn-primary"
                        onclick="if(window.SBApp.state.time){window.SBApp.goTo(3);}else{alert('Please select a date and arrival time first.');}">
                        Continue &#8594;
                    </button>
                </div>
            </div>

            <!--  CUSTOMER INFO  -->
            <div class="sb-panel" id="sbStep3">
                <h2 class="sb-panel-title">Your Information</h2>
                <div class="sb-form-fields">
                    <div class="sb-field-group">
                        <label>Name <span class="sb-req">*</span></label>
                        <input type="text" id="sbCustName" placeholder="e.g. Marie Ngonfi" autocomplete="name">
                        <span class="sb-ferr" id="sbErrName"></span>
                    </div>
                    <div class="sb-field-group">
                        <label>Phone Number <span class="sb-req">*</span></label>
                        <input type="tel" id="sbCustPhone" placeholder="e.g. +237 6XX XXX XXX" autocomplete="tel">
                        <span class="sb-ferr" id="sbErrPhone"></span>
                    </div>
                    <div class="sb-field-group">
                        <label>Email Address(optional)</label>
                        <input type="email" id="sbCustEmail" placeholder="your@email.com" autocomplete="email">
                    </div>
                    <div class="sb-field-group">
                        <label>Notes for the staff (optional)</label>
                        <textarea id="sbCustNotes" rows="3" placeholder="Any special requests or instructions..."></textarea>
                    </div>
                </div>
                <div class="sb-nav">
                    <button class="sb-btn sb-btn-back" onclick="SBApp.goTo(2)">&#8592; Back</button>
                    <button class="sb-btn sb-btn-primary" onclick="SBApp.goToConfirm()">Review Booking &#8594;</button>
                </div>
            </div>

            <!--  REVIEW & CONFIRM  -->
            <div class="sb-panel" id="sbStep4">
                <h2 class="sb-panel-title">Review Your Booking</h2>
                <div class="sb-review-card" id="sbReviewCard">
                    <!-- built by JS -->
                </div>
                <div class="sb-nav">
                    <button class="sb-btn sb-btn-back" onclick="SBApp.goTo(3)">&#8592; Edit</button>
                    <button class="sb-btn sb-btn-primary" id="sbConfirmBtn" onclick="SBApp.submitBooking()">
                        <span class="sb-btn-label">Confirm Booking</span>
                        <span class="sb-btn-spinner" style="display:none">&#8987; Saving...</span>
                    </button>
                </div>
            </div>

            <!--  SUCCESS  -->
            <div class="sb-panel" id="sbSuccess" style="display:none">
                <div class="sb-success-wrap">
                    <div class="sb-success-icon">&#10003;</div>
                    <h2>Booking Confirmed!</h2>
                    <p id="sbSuccessMsg">Your appointment has been received. We will confirm with you shortly.</p>
                    <div id="sbSuccessRules"></div>
                    <div class="sb-success-actions">
                        <a href="#" class="sb-btn sb-btn-wa" id="sbWaBtn" target="_blank" rel="noopener">&#128172; WhatsApp</a>
                        <a href="#" class="sb-btn sb-btn-pdf" id="sbPdfBtn" target="_blank" rel="noopener">&#128196; Receipt PDF</a>
                        <button class="sb-btn sb-btn-ghost" onclick="SBApp.reset()">Book Again</button>
                    </div>
                </div>
            </div>

        </div><!-- .sb-wrap -->
        <?php
        // NOTE: Nonce is now fetched live via sb_get_nonce AJAX to bypass page cache
        return ob_get_clean();
    }

    //  AJAX: staff for service 
    public function ajax_get_staff() {
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $staff      = SB_DB::get_staff_for_service( $service_id );
        if ( empty( $staff ) ) {
            // fallback: all active staff
            $staff = SB_DB::get_staff( true );
        }
        $out = array();
        foreach ( $staff as $s ) {
            $out[] = array(
                'id'    => $s->id,
                'name'  => $s->name,
                'image' => $s->image_url,
                'color' => $s->color,
            );
        }
        wp_send_json_success( $out );
    }

    //  AJAX: slots for service+staff+date 
    public function ajax_get_slots() {
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $staff_id   = intval( $_GET['staff_id']   ?? 0 );
        $date       = sanitize_text_field( $_GET['date'] ?? '' );
        $slots      = SB_Slots::get_slots( $service_id, $staff_id, $date );
        wp_send_json_success( $slots );
    }

    //  AJAX: unavailable dates for month 
    public function ajax_get_unavailable() {
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $staff_id   = intval( $_GET['staff_id']   ?? 0 );
        $year       = intval( $_GET['year']  ?? date('Y') );
        $month      = intval( $_GET['month'] ?? date('m') );
        $dates      = SB_Slots::get_unavailable_dates( $service_id, $staff_id, $year, $month );
        wp_send_json_success( $dates );
    }

    //  AJAX: submit booking 
    public function ajax_submit() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_pub' ) ) {
            wp_send_json_error( array( 'msg' => 'Security check failed.' ) );
        }

        $service_id  = intval( $_POST['service_id']  ?? 0 );
        $staff_id    = intval( $_POST['staff_id']    ?? 0 );
        $date        = sanitize_text_field( $_POST['date']   ?? '' );
        $start_time  = sanitize_text_field( $_POST['time']   ?? '' );
        $cust_name   = sanitize_text_field( $_POST['name']   ?? '' );
        $cust_phone  = sanitize_text_field( $_POST['phone']  ?? '' );
        $cust_email  = sanitize_email(      $_POST['email']  ?? '' );
        $notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $num_days    = intval( $_POST['sb_days'] ?? 1 );

        // Validate
        if ( ! $service_id || ! $date || ! $start_time || ! $cust_name || ! $cust_phone ) {
            wp_send_json_error( array( 'msg' => 'Please fill all required fields.' ) );
        }

        $service = SB_DB::get_service( $service_id );
        if ( ! $service ) wp_send_json_error( array( 'msg' => 'Service not found.' ) );

        // Re-verify slot is still available
        $slots = SB_Slots::get_slots( $service_id, $staff_id, $date );
        $slot_ok = false;
        $end_time = '';
        foreach ( $slots as $slot ) {
            if ( $slot['time'] === $start_time && $slot['available'] ) {
                $slot_ok  = true;
                $end_time = $slot['end_time'];
                break;
            }
        }
        if ( ! $slot_ok ) {
            wp_send_json_error( array( 'msg' => 'Sorry, that time slot was just taken. Please choose another.' ) );
        }

        // Pricing — use service deposit_pct, fall back to admin global default, then 0
        $base_price       = floatval( $service->price );
        $total            = $base_price * $num_days;
        // Only charge deposit if service has deposit_pct set, OR admin set a global default
        // If neither is set (both 0), never show deposit step
        $svc_dep_pct      = floatval( $service->deposit_pct );
        $global_dep_pct   = intval( get_option('sb_default_deposit_pct', 0) );
        $dep_pct          = $svc_dep_pct > 0 ? $svc_dep_pct : $global_dep_pct;
        // Only calculate deposit if admin has actually set a deposit % (service-level OR global)
        $deposit = ( $dep_pct > 0 && $total > 0 ) ? round( $total * $dep_pct / 100, 2 ) : 0;
        $balance = $total - $deposit;

        $booking_id = SB_DB::save_booking( array(
            'service_id'     => $service_id,
            'staff_id'       => $staff_id,
            'customer_name'  => $cust_name,
            'customer_email' => $cust_email,
            'customer_phone' => $cust_phone,
            'notes'          => $notes,
            'booking_date'   => $date,
            'start_time'     => $start_time,
            'end_time'       => $end_time,
            'num_days'       => $num_days,
            'total_price'    => $total,
            'deposit_amount' => $deposit,
            'balance_amount' => $balance,
            'payment_method' => 'whatsapp',
        ) );

        if ( ! $booking_id ) wp_send_json_error( array( 'msg' => 'Could not save booking. Please try again.' ) );

        // WhatsApp message
        $staff   = SB_DB::get_staff_member( $staff_id );
        // Use sb_whatsapp (manual WA link number) with fallback to admin_wa_number (Meta API number)
        $wa_num_raw = get_option( 'sb_whatsapp', '' );
        if ( empty( trim( $wa_num_raw ) ) ) {
            $sb_settings_tmp = get_option( 'sb_settings', array() );
            $wa_num_raw = $sb_settings_tmp['admin_wa_number'] ?? '';
        }
        $wa_num  = preg_replace( '/[^0-9]/', '', $wa_num_raw );
        $wa_msg  = "New Booking #$booking_id\n";
        $wa_msg .= "Service: {$service->name}\n";
        $wa_msg .= "Staff: " . ( $staff ? $staff->name : 'Any' ) . "\n";
        $wa_msg .= "Arrival Date: " . date( 'D d M Y', strtotime( $date ) ) . "\n";
        $wa_msg .= "Arrival Time: $start_time – $end_time\n";
        $wa_msg .= "Client: $cust_name\n";
        $wa_msg .= "Phone: $cust_phone\n";
        if ( $total > 0 ) {
            $currency = get_option( 'sb_currency', 'FCFA' );
            $wa_msg .= "Total: " . number_format( $total ) . " $currency\n";
            if ( $deposit > 0 ) $wa_msg .= "Deposit ({$dep_pct}%): " . number_format( $deposit ) . " $currency\n";
        }
        $wa_url = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_msg );

        // PDF url
        $pdf_url = add_query_arg( array(
            'sb_pdf'   => $booking_id,
            'sb_nonce' => wp_create_nonce( 'sb_pdf_' . $booking_id ),
        ), home_url() );

        // Send notifications — WhatsApp + Email fired here
        sb_debug_log( "ajax_submit: booking #{$booking_id} saved — calling send_booking_created now" );
        $notifier = new SB_Notification();
        $notifier->send_booking_created( $booking_id );
        // Send portal invite email to new customers (lets them set a password and access dashboard)
        $bk_email = sanitize_email( $_POST['customer_email'] ?? '' );
        $bk_name  = sanitize_text_field( $_POST['customer_name'] ?? '' );
        if ( $bk_email && class_exists('SB_Portal') ) {
            global $wpdb;
            $cust = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sb_customers WHERE email=%s", $bk_email
            ) );
            if ( $cust ) SB_Portal::send_portal_invite( $cust->id, $bk_email, $bk_name );
        }
        sb_debug_log( "ajax_submit: send_booking_created finished" );

        $settings    = get_option('sb_settings', array());
        $paystack_pk      = sanitize_text_field($settings['paystack_public_key'] ?? '');
        $flw_pk           = sanitize_text_field($settings['flw_public_key']      ?? '');
        $campay_username  = sanitize_text_field($settings['campay_username']     ?? '');

        // deposit_required is true whenever a deposit amount > 0 is set.
        // Gateway buttons are shown/hidden in JS based on whether keys are configured.
        // If no gateway is configured, JS shows a manual/WhatsApp payment notice instead.
        // NOTE: Do NOT gate deposit_required on has_real_gateway — that would silently
        // skip the payment step entirely if credentials were ever cleared.
        $has_real_gateway = ( ! empty($paystack_pk) || ! empty($flw_pk) || ! empty($campay_username) );

        $appointment_rules = get_option('sb_appointment_rules', '');
        wp_send_json_success( array(
            'booking_id'       => $booking_id,
            'wa_url'           => $wa_url,
            'pdf_url'          => $pdf_url,
            'deposit_amount'   => $deposit,
            'deposit_required' => $deposit > 0,   // Always show payment step when deposit > 0
            'currency'         => get_option('sb_currency','FCFA'),
            'customer_email'   => $cust_email,
            'customer_phone'   => $cust_phone,
            'customer_name'    => $cust_name,
            'service_name'     => $service->name,
            'appointment_rules'=> $appointment_rules,
            'paystack_pk'      => $paystack_pk,
            'flw_pk'           => $flw_pk,
            'campay_enabled'   => ! empty($campay_username) ? 1 : 0,
        ) );
    }

    //  AJAX: Verify Paystack payment 
    public function ajax_paystack_verify() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_pub' ) ) wp_send_json_error(array('msg'=>'Security error'));
        $ref        = sanitize_text_field( $_POST['reference'] ?? '' );
        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $settings   = get_option('sb_settings', array());
        $secret_key = sanitize_text_field( $settings['paystack_secret_key'] ?? '' );
        if ( ! $ref || ! $secret_key ) wp_send_json_error(array('msg'=>'Payment configuration error'));

        $resp = wp_remote_get( 'https://api.paystack.co/transaction/verify/' . $ref, array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key),
            'timeout' => 30,
        ));
        if ( is_wp_error($resp) ) wp_send_json_error(array('msg','Payment verification failed'));
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ( empty($body['data']['status']) || $body['data']['status'] !== 'success' ) {
            wp_send_json_error(array('msg'=>'Payment not confirmed by Paystack'));
        }

        // Payment confirmed — update DB
        SB_DB::update_payment_status($booking_id, 'deposit_paid', $ref);
        $notifier = new SB_Notification();
        $notifier->send_payment_confirmed($booking_id);
        $notifier->send_whatsapp_admin($booking_id, 'payment');
        $b = SB_DB::get_booking($booking_id);
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'pdf_url'    => add_query_arg(array('sb_pdf'=>$booking_id,'sb_nonce'=>wp_create_nonce('sb_pdf_'.$booking_id)), home_url()),
        ));
    }

    //  AJAX: Verify Flutterwave payment 
    public function ajax_flw_verify() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_pub' ) ) wp_send_json_error(array('msg'=>'Security error'));
        $tx_id      = sanitize_text_field( $_POST['transaction_id'] ?? '' );
        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $settings   = get_option('sb_settings', array());
        $secret_key = sanitize_text_field( $settings['flw_secret_key'] ?? '' );
        if ( ! $tx_id || ! $secret_key ) wp_send_json_error(array('msg'=>'Payment configuration error'));

        $resp = wp_remote_get( 'https://api.flutterwave.com/v3/transactions/' . $tx_id . '/verify', array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key),
            'timeout' => 30,
        ));
        if ( is_wp_error($resp) ) wp_send_json_error(array('msg'=>'Verification failed'));
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ( empty($body['data']['status']) || $body['data']['status'] !== 'successful' ) {
            wp_send_json_error(array('msg'=>'Payment not confirmed by Flutterwave'));
        }

        SB_DB::update_payment_status($booking_id, 'deposit_paid', $tx_id);
        $notifier = new SB_Notification();
        $notifier->send_payment_confirmed($booking_id);
        $notifier->send_whatsapp_admin($booking_id, 'payment');
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'pdf_url'    => add_query_arg(array('sb_pdf'=>$booking_id,'sb_nonce'=>wp_create_nonce('sb_pdf_'.$booking_id)), home_url()),
        ));
    }

    //  AJAX: Initiate CamPay payment 
    public function ajax_campay_initiate() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_pub' ) ) wp_send_json_error(array('msg'=>'Security error'));

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
        $settings   = get_option('sb_settings', array());
        $username   = sanitize_text_field( $settings['campay_username'] ?? '' );
        $password   = sanitize_text_field( $settings['campay_password'] ?? '' );
        $sandbox    = ! empty( $settings['campay_sandbox'] );

        if ( ! $username || ! $password ) wp_send_json_error(array('msg'=>'CamPay is not configured. Please add credentials in Settings.'));
        if ( ! $booking_id ) wp_send_json_error(array('msg'=>'Invalid booking.'));

        $booking = SB_DB::get_booking( $booking_id );
        if ( ! $booking ) wp_send_json_error(array('msg'=>'Booking not found.'));

        $amount = round( floatval($booking->deposit_amount) );
        if ( $amount <= 0 ) wp_send_json_error(array('msg'=>'No deposit required.'));

        $base            = $sandbox ? 'https://demo.campay.net/api' : 'https://www.campay.net/api';

        // Obtain CamPay token using configured credentials
        $token_resp = wp_remote_post( $base . '/token/', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode(array('username' => $username, 'password' => $password)),
            'timeout' => 20,
        ));
        if ( is_wp_error($token_resp) ) wp_send_json_error(array('msg'=>'Could not connect to CamPay. Check your credentials.'));
        $token_body = json_decode(wp_remote_retrieve_body($token_resp), true);
        $permanent_token = $token_body['token'] ?? '';
        if ( ! $permanent_token ) {
            $err = $token_body['detail'] ?? 'Invalid CamPay credentials. Check your username and password in Settings.';
            wp_send_json_error(array('msg' => $err));
        }

        $currency        = get_option('sb_currency','XAF');
        $campay_currency = ( $currency === 'FCFA' ) ? 'XAF' : $currency;
        $phone_clean     = preg_replace('/[^0-9]/', '', $phone);
        if ( strlen($phone_clean) === 9 ) $phone_clean = '237' . $phone_clean;

        $collect_resp = wp_remote_post( $base . '/collect/', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Token ' . $permanent_token,
            ),
            'body' => json_encode(array(
                'amount'             => (string) $amount,
                'currency'           => $campay_currency,
                'from'               => $phone_clean,
                'description'        => 'Booking deposit #' . $booking_id,
                'external_reference' => 'SB-' . $booking_id . '-' . time(),
            )),
            'timeout' => 45,
        ));
        if ( is_wp_error($collect_resp) ) wp_send_json_error(array('msg'=>'Payment request failed. Please try again.'));
        $collect_body = json_decode(wp_remote_retrieve_body($collect_resp), true);

        $ref = $collect_body['reference'] ?? '';
        if ( ! $ref ) {
            $err = $collect_body['detail'] ?? ($collect_body['message'] ?? 'Could not initiate payment. Ensure the phone number is a valid MTN or Orange Cameroon number.');
            wp_send_json_error(array('msg' => $err));
        }

        set_transient( 'sb_campay_ref_' . $booking_id, $ref, 30 * MINUTE_IN_SECONDS );

        wp_send_json_success(array(
            'reference'  => $ref,
            'booking_id' => $booking_id,
            'phone'      => $phone_clean,
            'amount'     => $amount,
        ));
    }

    //  AJAX: Poll CamPay payment status 
    public function ajax_campay_check() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sb_pub' ) ) wp_send_json_error(array('msg'=>'Security error'));

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $reference  = sanitize_text_field( $_POST['reference'] ?? '' );
        $settings   = get_option('sb_settings', array());
        $username   = sanitize_text_field( $settings['campay_username'] ?? '' );
        $password   = sanitize_text_field( $settings['campay_password'] ?? '' );
        $sandbox    = ! empty( $settings['campay_sandbox'] );

        if ( ! $reference || ! $booking_id ) wp_send_json_error(array('msg'=>'Missing reference.'));

        $base            = $sandbox ? 'https://demo.campay.net/api' : 'https://www.campay.net/api';

        // Obtain token from credentials
        $token_resp2 = wp_remote_post( $base . '/token/', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode(array('username' => $username, 'password' => $password)),
            'timeout' => 20,
        ));
        $token_body2 = is_wp_error($token_resp2) ? array() : json_decode(wp_remote_retrieve_body($token_resp2), true);
        $permanent_token = $token_body2['token'] ?? '';
        if ( ! $permanent_token ) wp_send_json_error(array('msg'=>'CamPay authentication failed. Check credentials in Settings.'));

        $check_resp = wp_remote_get( $base . '/transaction/' . $reference . '/', array(
            'headers' => array('Authorization' => 'Token ' . $permanent_token),
            'timeout' => 30,
        ));
        if ( is_wp_error($check_resp) ) wp_send_json_error(array('msg'=>'Status check failed.'));
        $body   = json_decode(wp_remote_retrieve_body($check_resp), true);
        $status = strtoupper( $body['status'] ?? '' );

        if ( $status === 'SUCCESSFUL' ) {
            SB_DB::update_payment_status($booking_id, 'deposit_paid', $reference);
            $notifier = new SB_Notification();
            $notifier->send_payment_confirmed($booking_id);
            $notifier->send_whatsapp_admin($booking_id, 'payment');
            delete_transient( 'sb_campay_ref_' . $booking_id );

            // Auto-withdrawal: disburse (amount - 5%) to owner's MoMo using permanent token
            $momo_number    = sanitize_text_field( $settings['campay_momo_number'] ?? '' );
            $momo_name      = sanitize_text_field( $settings['campay_momo_name']   ?? '' );
            // $permanent_token already obtained above from credentials
            if ( $momo_number ) {
                $booking_obj     = SB_DB::get_booking( $booking_id );
                $paid_amount     = round( floatval( $booking_obj ? $booking_obj->deposit_amount : 0 ) );
                $disburse_amount = round( $paid_amount * 0.95 );
                if ( $disburse_amount > 0 ) {
                    $sb_currency     = get_option( 'sb_currency', 'XAF' );
                    $campay_currency = ( $sb_currency === 'FCFA' ) ? 'XAF' : $sb_currency;
                    $momo_clean      = preg_replace( '/[^0-9]/', '', $momo_number );
                    if ( strlen( $momo_clean ) === 9 ) $momo_clean = '237' . $momo_clean;
                    $disburse_body = array(
                        'amount'             => (string) $disburse_amount,
                        //'currency'           => $campay_currency,
                        'to'                 => $momo_clean,
                        'description'        => 'Booking #' . $booking_id . ' auto-withdrawal',
                        'external_reference' => 'SB-OUT-' . $booking_id,
                    );
                    if ( $momo_name ) {
                        $disburse_body['to_name'] = $momo_name;
                    }
                    $disburse_resp = wp_remote_post( $base . '/withdraw/', array(
                        'headers' => array(
                            'Content-Type'  => 'application/json',
                            'Authorization' => 'Token ' . $permanent_token,
                        ),
                        'body'    => json_encode( $disburse_body ),
                        'timeout' => 45,
                    ) );
                    // Log the disburse result so admin can verify it worked
                    if ( is_wp_error( $disburse_resp ) ) {
                        sb_debug_log( 'CamPay disburse FAILED for booking #' . $booking_id . ': ' . $disburse_resp->get_error_message() );
                    } else {
                        $disburse_result = json_decode( wp_remote_retrieve_body( $disburse_resp ), true );
                        $disburse_ref    = $disburse_result['reference'] ?? ( $disburse_result['id'] ?? 'no-ref' );
                        $disburse_status = $disburse_result['status'] ?? wp_remote_retrieve_response_code( $disburse_resp );
                        sb_debug_log( 'CamPay disburse booking #' . $booking_id . ' — ' . $disburse_amount . ' XAF to ' . $momo_clean . ' — ref: ' . $disburse_ref . ' status: ' . $disburse_status );
                        // Save disburse info on the booking internal note
                        global $wpdb;
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}sb_bookings SET internal_note = CONCAT(IFNULL(internal_note,''), %s) WHERE id = %d",
                            ' | CamPay disburse ' . $disburse_amount . ' XAF → ' . $momo_clean . ' ref:' . $disburse_ref,
                            $booking_id
                        ) );
                    }
                }
            }

            wp_send_json_success(array(
                'paid'    => true,
                'pdf_url' => add_query_arg(array('sb_pdf'=>$booking_id,'sb_nonce'=>wp_create_nonce('sb_pdf_'.$booking_id)), home_url()),
            ));
        } elseif ( $status === 'FAILED' ) {
            wp_send_json_error(array('msg'=>'Payment was declined or failed. Please try again.','failed'=>true));
        } else {
            wp_send_json_success(array('paid'=>false,'status'=>$status));
        }
    }
}
