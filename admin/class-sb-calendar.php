<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Calendar_Page {

    public function __construct() {
        add_action( 'wp_ajax_sb_cal_bookings', array( $this, 'ajax_cal_bookings' ) );
    }

    public function page_calendar() {
        $staff_all = SB_DB::get_staff();
        ?>
        <div class="wrap sbw">
        <div class="sbw-head"><div><h1>Calendar</h1><p class="sbw-sub">Appointment overview</p></div></div>

        <div class="sbw-card">
            <div class="sbw-cal-controls">
                <div>
                    <button class="sbw-btn sbw-cal-prev-week">&#8592; Prev</button>
                    <span class="sbw-cal-range" id="sbAdminCalRange"></span>
                    <button class="sbw-btn sbw-cal-next-week">Next &#8594;</button>
                </div>
                <div>
                    <select id="sbAdminCalStaff">
                        <option value="0">All Staff</option>
                        <?php foreach ($staff_all as $st): ?>
                        <option value="<?php echo $st->id; ?>"><?php echo esc_html($st->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="sbw-btn" id="sbAdminCalToday">Today</button>
                </div>
            </div>
            <div class="sbw-admin-cal" id="sbAdminCal"></div>
        </div>
        </div><?php
    }

    public function ajax_cal_bookings() {
        check_ajax_referer( 'sb_adm', 'nonce' );
        $from     = sanitize_text_field( $_GET['from']     ?? date('Y-m-d') );
        $to       = sanitize_text_field( $_GET['to']       ?? date('Y-m-d') );
        $staff_id = intval( $_GET['staff_id'] ?? 0 );
        $args     = array_filter( array( 'date_from' => $from, 'date_to' => $to, 'staff_id' => $staff_id ) );
        $bookings = SB_DB::get_bookings( $args );
        $out      = array();
        foreach ( $bookings as $b ) {
            $out[] = array(
                'id'          => $b->id,
                'title'       => $b->customer_name . ' — ' . $b->service_name,
                'date'        => $b->booking_date,
                'start'       => $b->start_time,
                'end'         => $b->end_time,
                'color'       => $b->service_color ?? '#111111',
                'status'      => $b->status,
                'staff'       => $b->staff_name,
                'phone'       => $b->customer_phone,
            );
        }
        wp_send_json_success( $out );
    }
}
