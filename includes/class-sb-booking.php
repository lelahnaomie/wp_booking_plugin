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
            self::$ajax_done = true;
        }
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
                <div class="sb-step-bubble active" data-step="1"><span>1</span><small>Service</small></div>
                <div class="sb-step-line"></div>
                <div class="sb-step-bubble" data-step="2"><span>2</span><small>Date &amp; Time</small></div>
                <div class="sb-step-line"></div>
                <div class="sb-step-bubble" data-step="3"><span>3</span><small>Your Info</small></div>
                <div class="sb-step-line"></div>
                <div class="sb-step-bubble" data-step="4"><span>&#10003;</span><small>Confirm</small></div>
            </div>

            <!-- ERROR BAR -->
            <div class="sb-error-bar" id="sbError" style="display:none"></div>

            <!-- ── SERVICE ── -->
            <div class="sb-panel active" id="sbStep1">
                <h2 class="sb-panel-title">Choose a Service</h2>
                <?php if ( empty( $groups ) ): ?>
                    <p class="sb-notice">No services available. Please add services in the admin panel.</p>
                <?php else: ?>
                <?php foreach ( $groups as $group ): ?>
                    <div class="sb-cat-group">
                        <h3 class="sb-cat-title"><?php echo esc_html( $group['cat']->name ); ?></h3>
                        <div class="sb-service-grid">
                        <?php foreach ( $group['services'] as $svc ): ?>
                            <?php
                            $svc_staff = SB_DB::get_staff_for_service($svc->id);
                            if(empty($svc_staff)) $svc_staff = SB_DB::get_staff(true);
                            $svc_staff_json = json_encode(array_map(function($st){return array(
                                'id'=>$st->id,'name'=>$st->name,'color'=>$st->color,'image'=>$st->image_url,'bio'=>$st->bio
                            );}, $svc_staff));
                            ?>
                            <div class="sb-service-card" data-id="<?php echo $svc->id; ?>"
                                 data-price="<?php echo floatval( $svc->price ); ?>"
                                 data-deposit="<?php echo floatval( $svc->deposit_pct ); ?>"
                                 data-duration="<?php echo intval( $svc->duration ); ?>"
                                 data-name="<?php echo esc_attr($svc->name); ?>"
                                 data-desc="<?php echo esc_attr($svc->description); ?>"
                                 data-color="<?php echo esc_attr($svc->color); ?>"
                                 data-image="<?php echo esc_attr($svc->image_url); ?>"
                                 data-staff='<?php echo esc_attr($svc_staff_json); ?>'
                                 style="--svc-color:<?php echo esc_attr( $svc->color ); ?>">
                                <?php if ( $svc->image_url ): ?>
                                <div class="sb-svc-img" style="background-image:url('<?php echo esc_url( $svc->image_url ); ?>')"></div>
                                <?php else: ?>
                                <div class="sb-svc-color-bar"></div>
                                <?php endif; ?>
                                <div class="sb-svc-body">
                                    <strong><?php echo esc_html( $svc->name ); ?></strong>
                                    <?php if ( $svc->description ): ?>
                                    <p class="sb-svc-desc"><?php echo esc_html( $svc->description ); ?></p>
                                    <?php endif; ?>
                                    <div class="sb-svc-meta">
                                        <span class="sb-svc-dur">⏱ <?php echo SB_Services::format_duration( $svc->duration ); ?></span>
                                        <?php if ( $svc->price > 0 ): ?>
                                        <span class="sb-svc-price"><?php echo number_format( $svc->price ); ?> <?php echo esc_html( $currency ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php
                $terms_text = get_option('sb_terms_text','');
                $rules_text = get_option('sb_appointment_rules','');
                if($terms_text || $rules_text): ?>
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
                            <h4>Appointment Rules</h4>
                            <p><?php echo nl2br(esc_html($rules_text)); ?></p>
                            <?php endif; ?>
                            <?php if($terms_text): ?>
                            <h4>Terms &amp; Conditions</h4>
                            <p><?php echo nl2br(esc_html($terms_text)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>



            <!-- ── DATE & TIME ── -->
            <div class="sb-panel" id="sbStep2">
                <h2 class="sb-panel-title">Pick a Date &amp; Time</h2>
                <div class="sb-datetime-wrap">
                    <div class="sb-calendar-wrap">
                        <div class="sb-cal-nav">
                            <button type="button" class="sb-cal-prev" id="sbCalPrev">&#8592;</button>
                            <span class="sb-cal-month" id="sbCalMonth"></span>
                            <button type="button" class="sb-cal-next" id="sbCalNext">&#8594;</button>
                        </div>
                        <div class="sb-cal-grid" id="sbCalGrid"></div>
                    </div>
                    <div class="sb-slots-wrap">
                        <h3 class="sb-slots-title" id="sbSlotsTitle">Select a date to see available times</h3>
                        <div class="sb-slots-grid" id="sbSlotsGrid"></div>
                    </div>
                </div>
                <div class="sb-nav">
                    <button class="sb-btn sb-btn-back" onclick="SBApp.goTo(1)">&#8592; Back</button>
                    <button class="sb-btn sb-btn-primary"
                        onclick="if(window.SBApp.state.time){window.SBApp.goTo(3);}else{alert('Please select a date and time first.');}">
                        Continue &#8594;
                    </button>
                </div>
            </div>

            <!-- ── CUSTOMER INFO ── -->
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

            <!-- ── REVIEW & CONFIRM ── -->
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

            <!-- ── SUCCESS ── -->
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
        $nonce = wp_create_nonce( 'sb_pub' );
        echo "<script>window.SB_NONCE='{$nonce}';</script>";
        return ob_get_clean();
    }

    // ── AJAX: staff for service ────────────────────────────────
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

    // ── AJAX: slots for service+staff+date ────────────────────
    public function ajax_get_slots() {
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $staff_id   = intval( $_GET['staff_id']   ?? 0 );
        $date       = sanitize_text_field( $_GET['date'] ?? '' );
        $slots      = SB_Slots::get_slots( $service_id, $staff_id, $date );
        wp_send_json_success( $slots );
    }

    // ── AJAX: unavailable dates for month ─────────────────────
    public function ajax_get_unavailable() {
        $service_id = intval( $_GET['service_id'] ?? 0 );
        $staff_id   = intval( $_GET['staff_id']   ?? 0 );
        $year       = intval( $_GET['year']  ?? date('Y') );
        $month      = intval( $_GET['month'] ?? date('m') );
        $dates      = SB_Slots::get_unavailable_dates( $service_id, $staff_id, $year, $month );
        wp_send_json_success( $dates );
    }

    // ── AJAX: submit booking ───────────────────────────────────
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
        $total            = floatval( $service->price );
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
            'total_price'    => $total,
            'deposit_amount' => $deposit,
            'balance_amount' => $balance,
            'payment_method' => 'whatsapp',
        ) );

        if ( ! $booking_id ) wp_send_json_error( array( 'msg' => 'Could not save booking. Please try again.' ) );

        // WhatsApp message
        $staff   = SB_DB::get_staff_member( $staff_id );
        $wa_num  = preg_replace( '/[^0-9]/', '', get_option( 'sb_whatsapp', '237678899434' ) );
        $wa_msg  = "New Booking #$booking_id\n";
        $wa_msg .= "Service: {$service->name}\n";
        $wa_msg .= "Staff: " . ( $staff ? $staff->name : 'Any' ) . "\n";
        $wa_msg .= "Date: " . date( 'D d M Y', strtotime( $date ) ) . "\n";
        $wa_msg .= "Time: $start_time – $end_time\n";
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

        // Send notifications
        $notifier = new SB_Notification();
        $notifier->send_booking_created( $booking_id );

        $settings    = get_option('sb_settings', array());
        $paystack_pk = sanitize_text_field($settings['paystack_public_key'] ?? '');
        $flw_pk      = sanitize_text_field($settings['flw_public_key']      ?? '');

        // Only trigger in-plugin payment if a REAL gateway (Paystack or Flutterwave) is configured.
        // If neither is set, the booking is saved with status awaiting_deposit,
        // the WhatsApp message tells the client the deposit amount,
        // and the business owner collects payment in person / via phone.
        $has_real_gateway = ( ! empty($paystack_pk) || ! empty($flw_pk) );

        $appointment_rules = get_option('sb_appointment_rules', '');
        wp_send_json_success( array(
            'booking_id'       => $booking_id,
            'wa_url'           => $wa_url,
            'pdf_url'          => $pdf_url,
            'deposit_amount'   => $deposit,
            'deposit_required' => $deposit > 0 && $has_real_gateway, // only trigger payment UI if real gateway exists
            'currency'         => get_option('sb_currency','FCFA'),
            'customer_email'   => $cust_email,
            'customer_phone'   => $cust_phone,
            'customer_name'    => $cust_name,
            'service_name'     => $service->name,
            'appointment_rules'=> $appointment_rules,
            'paystack_pk'      => $paystack_pk,
            'flw_pk'           => $flw_pk,
        ) );
    }

    // ── AJAX: Verify Paystack payment ─────────────────────────
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
        $b = SB_DB::get_booking($booking_id);
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'pdf_url'    => add_query_arg(array('sb_pdf'=>$booking_id,'sb_nonce'=>wp_create_nonce('sb_pdf_'.$booking_id)), home_url()),
        ));
    }

    // ── AJAX: Verify Flutterwave payment ──────────────────────
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
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'pdf_url'    => add_query_arg(array('sb_pdf'=>$booking_id,'sb_nonce'=>wp_create_nonce('sb_pdf_'.$booking_id)), home_url()),
        ));
    }

}