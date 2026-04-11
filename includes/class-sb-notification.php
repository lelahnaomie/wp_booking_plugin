<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Notification {

    public function send_booking_created( $booking_id ) {
        $booking = SB_DB::get_booking( $booking_id );
        if ( ! $booking ) return;

        $currency = get_option( 'sb_currency', 'FCFA' );
        $settings = get_option( 'sb_settings', array() );
        $site     = get_bloginfo( 'name' );

        // Email to customer
        if ( $booking->customer_email ) {
            $subject = "Booking Confirmation #{$booking_id} – {$site}";
            $body    = $this->customer_email_html( $booking, $currency, $site );
            wp_mail( $booking->customer_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        }

        // Email to admin
        $admin_email = ! empty( $settings['notify_email'] ) ? $settings['notify_email'] : get_option( 'admin_email' );
        $subject2    = "New Booking #{$booking_id}: {$booking->service_name} – {$booking->customer_name}";
        $body2       = $this->admin_email_html( $booking, $currency, $site );
        wp_mail( $admin_email, $subject2, $body2, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    private function customer_email_html( $b, $currency, $site ) {
        $date = date( 'l, d F Y', strtotime( $b->booking_date ) );
        $time = date( 'g:i A', strtotime( $b->start_time ) ) . ' – ' . date( 'g:i A', strtotime( $b->end_time ) );
        return "
<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px'>
  <h2 style='color:#5B2D8E;margin-top:0'>{$site}</h2>
  <p>Hi <strong>{$b->customer_name}</strong>, your booking is confirmed!</p>
  <table style='width:100%;border-collapse:collapse;margin:16px 0'>
    <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Service</td><td style='padding:8px 12px'>{$b->service_name}</td></tr>
    <tr><td style='padding:8px 12px;font-weight:bold'>Staff</td><td style='padding:8px 12px'>{$b->staff_name}</td></tr>
    <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Date</td><td style='padding:8px 12px'>{$date}</td></tr>
    <tr><td style='padding:8px 12px;font-weight:bold'>Time</td><td style='padding:8px 12px'>{$time}</td></tr>
    " . ( $b->total_price > 0 ? "
    <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Total</td><td style='padding:8px 12px'>" . number_format($b->total_price) . " {$currency}</td></tr>
    " . ( $b->deposit_amount > 0 ? "<tr><td style='padding:8px 12px;font-weight:bold'>Deposit</td><td style='padding:8px 12px;color:#5B2D8E;font-weight:bold'>" . number_format($b->deposit_amount) . " {$currency}</td></tr>" : '' ) . "
    " : '' ) . "
  </table>
  " . ( $b->notes ? "<p><strong>Your notes:</strong> {$b->notes}</p>" : '' ) . "
  <p style='color:#64748b;font-size:.9em'>We look forward to seeing you. Reply to this email or WhatsApp us if you need to reschedule.</p>
</div>";
    }

    private function admin_email_html( $b, $currency, $site ) {
        $date = date( 'l, d F Y', strtotime( $b->booking_date ) );
        $time = date( 'g:i A', strtotime( $b->start_time ) ) . ' – ' . date( 'g:i A', strtotime( $b->end_time ) );
        return "
<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px'>
  <h2 style='color:#5B2D8E'>New Booking #{$b->id} — {$site}</h2>
  <p><strong>Service:</strong> {$b->service_name}<br>
  <strong>Staff:</strong> {$b->staff_name}<br>
  <strong>Date:</strong> {$date}<br>
  <strong>Time:</strong> {$time}<br>
  <strong>Client:</strong> {$b->customer_name}<br>
  <strong>Phone:</strong> {$b->customer_phone}<br>
  <strong>Email:</strong> {$b->customer_email}</p>
  " . ( $b->total_price > 0 ? "<p><strong>Total:</strong> " . number_format($b->total_price) . " {$currency}<br>
  <strong>Deposit:</strong> " . number_format($b->deposit_amount) . " {$currency}</p>" : '' ) . "
  " . ( $b->notes ? "<p><strong>Notes:</strong> {$b->notes}</p>" : '' ) . "
</div>";
    }

    public function send_payment_confirmed( $booking_id ) {
        $b        = SB_DB::get_booking($booking_id);
        $currency = get_option('sb_currency','FCFA');
        $site     = get_bloginfo('name');
        $date     = date('l, d F Y', strtotime($b->booking_date));
        $time     = date('g:i A', strtotime($b->start_time)) . ' – ' . date('g:i A', strtotime($b->end_time));
        if ($b->customer_email) {
            wp_mail($b->customer_email,
                "Booking #{$booking_id} Confirmed — {$site}",
                "<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px'>
                <h2 style='color:#5B2D8E'>{$site}</h2>
                <p>Hi <strong>{$b->customer_name}</strong>, your deposit has been received and your booking is <strong style='color:#16a34a'>CONFIRMED</strong>!</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                  <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Service</td><td style='padding:8px 12px'>{$b->service_name}</td></tr>
                  <tr><td style='padding:8px 12px;font-weight:bold'>Date</td><td style='padding:8px 12px'>{$date}</td></tr>
                  <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Time</td><td style='padding:8px 12px'>{$time}</td></tr>
                  <tr><td style='padding:8px 12px;font-weight:bold'>Deposit Paid</td><td style='padding:8px 12px;color:#16a34a;font-weight:bold'>" . number_format($b->deposit_amount) . " {$currency}</td></tr>
                  <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Balance Due</td><td style='padding:8px 12px'>" . number_format($b->balance_amount) . " {$currency}</td></tr>
                </table>
                <p style='color:#64748b;font-size:.9em'>Your balance is due on the day of your appointment. See you soon!</p>
                </div>",
                array('Content-Type: text/html; charset=UTF-8')
            );
        }
        // Notify admin
        $settings = get_option('sb_settings', array());
        $admin_email = $settings['notify_email'] ?? get_option('admin_email');
        wp_mail($admin_email,
            "💰 Deposit Received — Booking #{$booking_id}",
            "<p>Deposit of <strong>" . number_format($b->deposit_amount) . " {$currency}</strong> confirmed for booking #{$booking_id}.<br>
            Client: {$b->customer_name} · {$b->customer_phone}<br>
            Service: {$b->service_name} · {$date}</p>",
            array('Content-Type: text/html; charset=UTF-8')
        );
    }

    public function send_proof_submitted( $booking_id ) {
        $b        = SB_DB::get_booking($booking_id);
        $currency = get_option('sb_currency','FCFA');
        $settings = get_option('sb_settings', array());
        $admin_email = $settings['notify_email'] ?? get_option('admin_email');
        $admin_url = admin_url('admin.php?page=sb-bookings');
        wp_mail($admin_email,
            "📸 Payment Proof Submitted — Booking #{$booking_id} (Needs Review)",
            "<p><strong>{$b->customer_name}</strong> has submitted payment proof for booking #{$booking_id}.<br>
            Service: {$b->service_name}<br>
            Deposit: " . number_format($b->deposit_amount) . " {$currency}<br><br>
            <a href='{$admin_url}' style='background:#5B2D8E;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none'>Review &amp; Confirm →</a></p>",
            array('Content-Type: text/html; charset=UTF-8')
        );
        // Tell client we received their proof
        if ($b->customer_email) {
            wp_mail($b->customer_email,
                "Payment Proof Received — Booking #{$booking_id}",
                "<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px'>
                <h2 style='color:#5B2D8E'>Payment Proof Received</h2>
                <p>Hi <strong>{$b->customer_name}</strong>, we received your payment proof for booking #{$booking_id}.<br>
                We will verify and confirm your booking within a few minutes. You will receive another email once confirmed.</p>
                </div>",
                array('Content-Type: text/html; charset=UTF-8')
            );
        }
    }
}