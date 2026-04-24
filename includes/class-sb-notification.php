<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Notification {

    /**
     * Called immediately when a new booking is saved.
     * Sends: email to customer + email to admin + WhatsApp to admin.
     */
    public function send_booking_created( $booking_id ) {
        $booking  = SB_DB::get_booking( $booking_id );
        if ( ! $booking ) {
            sb_debug_log( "send_booking_created: booking #{$booking_id} not found in DB" );
            return;
        }

        $currency = get_option( 'sb_currency', 'FCFA' );
        $settings = get_option( 'sb_settings', array() );
        $site     = get_bloginfo( 'name' );

        sb_debug_log( "send_booking_created: START for booking #{$booking_id} ({$booking->service_name} / {$booking->customer_name})" );

        // ── Email to customer ──────────────────────────────────────────────
        if ( $booking->customer_email ) {
            $sent = wp_mail(
                $booking->customer_email,
                "Booking Confirmation #{$booking_id} – {$site}",
                $this->customer_email_html( $booking, $currency, $site ),
                array( 'Content-Type: text/html; charset=UTF-8' )
            );
            sb_debug_log( "send_booking_created: customer email to {$booking->customer_email} — " . ($sent ? 'SENT' : 'FAILED') );
        }

        // ── Email to admin ─────────────────────────────────────────────────
        $admin_email = ! empty( $settings['notify_email'] )
            ? $settings['notify_email']
            : get_option( 'admin_email' );
        $sent2 = wp_mail(
            $admin_email,
            "New Booking #{$booking_id}: {$booking->service_name} – {$booking->customer_name}",
            $this->admin_email_html( $booking, $currency, $site ),
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
        sb_debug_log( "send_booking_created: admin email to {$admin_email} — " . ($sent2 ? 'SENT' : 'FAILED') );

        // ── WhatsApp to admin ──────────────────────────────────────────────
        // THIS IS THE FIX — was missing before, only payment events had WhatsApp
        sb_debug_log( "send_booking_created: calling send_whatsapp_admin for new_booking" );
        $this->send_whatsapp_admin( $booking_id, 'new_booking' );

        sb_debug_log( "send_booking_created: DONE for booking #{$booking_id}" );
    }

    private function customer_email_html( $b, $currency, $site ) {
        $ap    = get_option( 'sb_appearance', array() );
        $color = sanitize_hex_color( $ap['primary'] ?? '#5B2D8E' ) ?: '#5B2D8E';
        $date  = date( 'l, d F Y', strtotime( $b->booking_date ) );
        $time  = date( 'g:i A', strtotime( $b->start_time ) )
               . ' – ' . date( 'g:i A', strtotime( $b->end_time ) );
        $num_days = intval( $b->num_days ?? 1 );
        return "
<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px'>
  <h2 style='color:{$color};margin-top:0'>{$site}</h2>
  <p>Hi <strong>{$b->customer_name}</strong>, your booking is confirmed!</p>
  <table style='width:100%;border-collapse:collapse;margin:16px 0'>
    <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Service</td><td style='padding:8px 12px'>{$b->service_name}</td></tr>
    " . ( $b->staff_name ? "<tr><td style='padding:8px 12px;font-weight:bold'>Staff</td><td style='padding:8px 12px'>{$b->staff_name}</td></tr>" : '' ) . "
    " . ( $num_days > 1 ? "<tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Duration</td><td style='padding:8px 12px'>{$num_days} days</td></tr>" : '' ) . "
    <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Date</td><td style='padding:8px 12px'>{$date}</td></tr>
    <tr><td style='padding:8px 12px;font-weight:bold'>Arrival Time</td><td style='padding:8px 12px'>{$time}</td></tr>
    " . ( $b->total_price > 0 ? "
    <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Total</td><td style='padding:8px 12px;font-weight:bold;color:{$color}'>" . number_format($b->total_price) . " {$currency}</td></tr>
    " . ( $b->deposit_amount > 0 ? "<tr><td style='padding:8px 12px;font-weight:bold'>Deposit</td><td style='padding:8px 12px;color:{$color};font-weight:bold'>" . number_format($b->deposit_amount) . " {$currency}</td></tr>" : '' ) . "
    " : '' ) . "
  </table>
  " . ( $b->notes ? "<p><strong>Your notes:</strong> {$b->notes}</p>" : '' ) . "
  <p style='color:#64748b;font-size:.9em'>We look forward to seeing you. Contact us on WhatsApp if you need to reschedule.</p>
</div>";
    }

    private function admin_email_html( $b, $currency, $site ) {
        $ap    = get_option( 'sb_appearance', array() );
        $color = sanitize_hex_color( $ap['primary'] ?? '#5B2D8E' ) ?: '#5B2D8E';
        $date  = date( 'l, d F Y', strtotime( $b->booking_date ) );
        $time  = date( 'g:i A', strtotime( $b->start_time ) )
               . ' – ' . date( 'g:i A', strtotime( $b->end_time ) );
        $num_days = intval( $b->num_days ?? 1 );
        return "
<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px'>
  <h2 style='color:{$color}'>📅 New Booking #{$b->id} — {$site}</h2>
  <p>
    <strong>Service:</strong> {$b->service_name}<br>
    " . ( $b->staff_name ? "<strong>Staff:</strong> {$b->staff_name}<br>" : '' ) . "
    " . ( $num_days > 1 ? "<strong>Duration:</strong> {$num_days} days<br>" : '' ) . "
    <strong>Date:</strong> {$date}<br>
    <strong>Arrival Time:</strong> {$time}<br>
    <strong>Client:</strong> {$b->customer_name}<br>
    <strong>Phone:</strong> {$b->customer_phone}<br>
    <strong>Email:</strong> {$b->customer_email}
  </p>
  " . ( $b->total_price > 0 ? "<p><strong>Total:</strong> " . number_format($b->total_price) . " {$currency}"
        . ( $b->deposit_amount > 0 ? "<br><strong>Deposit:</strong> " . number_format($b->deposit_amount) . " {$currency}" : '' )
        . "</p>" : '' ) . "
  " . ( $b->notes ? "<p><strong>Notes:</strong> {$b->notes}</p>" : '' ) . "
</div>";
    }

    public function send_payment_confirmed( $booking_id ) {
        $b        = SB_DB::get_booking( $booking_id );
        $currency = get_option( 'sb_currency', 'FCFA' );
        $site     = get_bloginfo( 'name' );
        $ap       = get_option( 'sb_appearance', array() );
        $color    = sanitize_hex_color( $ap['primary'] ?? '#5B2D8E' ) ?: '#5B2D8E';
        $settings = get_option( 'sb_settings', array() );
        $date     = date( 'l, d F Y', strtotime( $b->booking_date ) );
        $time     = date( 'g:i A', strtotime($b->start_time) )
                  . ' – ' . date( 'g:i A', strtotime($b->end_time) );

        if ( $b->customer_email ) {
            wp_mail(
                $b->customer_email,
                "Booking #{$booking_id} Confirmed — {$site}",
                "<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px'>
                <h2 style='color:{$color}'>{$site}</h2>
                <p>Hi <strong>{$b->customer_name}</strong>, your deposit has been received and your booking is <strong style='color:#16a34a'>CONFIRMED</strong>!</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                  <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Service</td><td style='padding:8px 12px'>{$b->service_name}</td></tr>
                  <tr><td style='padding:8px 12px;font-weight:bold'>Date</td><td style='padding:8px 12px'>{$date}</td></tr>
                  <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Arrival Time</td><td style='padding:8px 12px'>{$time}</td></tr>
                  <tr><td style='padding:8px 12px;font-weight:bold'>Deposit Paid</td><td style='padding:8px 12px;color:#16a34a;font-weight:bold'>" . number_format($b->deposit_amount) . " {$currency}</td></tr>
                  <tr style='background:#f3f4f6'><td style='padding:8px 12px;font-weight:bold'>Balance Due</td><td style='padding:8px 12px'>" . number_format($b->balance_amount) . " {$currency}</td></tr>
                </table>
                <p style='color:#64748b;font-size:.9em'>Your balance is due on the day. See you soon!</p>
                </div>",
                array( 'Content-Type: text/html; charset=UTF-8' )
            );
        }
        $admin_email = $settings['notify_email'] ?? get_option( 'admin_email' );
        wp_mail(
            $admin_email,
            "💰 Deposit Received — Booking #{$booking_id}",
            "<p>Deposit of <strong>" . number_format($b->deposit_amount) . " {$currency}</strong> confirmed for booking #{$booking_id}.<br>
            Client: {$b->customer_name} · {$b->customer_phone}<br>
            Service: {$b->service_name} · {$date}</p>",
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
    }

    /**
     * Send WhatsApp to admin via Meta Cloud API.
     *
     * Uses admin_wa_number from settings ONLY — no hardcoded fallback.
     * Logs everything to wp-content/sb-debug.log for easy debugging.
     */
    public function send_whatsapp_admin( $booking_id, $event = 'new_booking' ) {
        $settings     = get_option( 'sb_settings', array() );
        $phone_id     = sanitize_text_field( $settings['meta_wa_phone_id'] ?? '' );
        $access_token = sanitize_text_field( $settings['meta_wa_token']    ?? '' );

        // FIXED: No hardcoded fallback number — uses admin setting only
        $to_number = preg_replace( '/[^0-9]/', '', $settings['admin_wa_number'] ?? '' );

        sb_debug_log( "send_whatsapp_admin: event={$event} booking={$booking_id}" );
        sb_debug_log( "send_whatsapp_admin: phone_id=" . ( $phone_id ? substr($phone_id,0,8).'...' : 'NOT SET' ) );
        sb_debug_log( "send_whatsapp_admin: token="    . ( $access_token ? '...'.substr($access_token,-6) : 'NOT SET' ) );
        sb_debug_log( "send_whatsapp_admin: to_number=" . ( $to_number ?: 'NOT SET' ) );

        if ( ! $phone_id ) {
            sb_debug_log( "send_whatsapp_admin: SKIP — Meta Phone Number ID not set in SmartBooking Settings" );
            return;
        }
        if ( ! $access_token ) {
            sb_debug_log( "send_whatsapp_admin: SKIP — Access Token not set in SmartBooking Settings" );
            return;
        }
        if ( ! $to_number ) {
            sb_debug_log( "send_whatsapp_admin: SKIP — Admin WhatsApp Number not set in SmartBooking Settings" );
            return;
        }

        $b = SB_DB::get_booking( $booking_id );
        if ( ! $b ) {
            sb_debug_log( "send_whatsapp_admin: SKIP — booking #{$booking_id} not found" );
            return;
        }

        $currency = get_option( 'sb_currency', 'FCFA' );
        $site     = get_bloginfo( 'name' );
        $date     = date( 'D d M Y', strtotime( $b->booking_date ) );
        $time     = date( 'g:i A', strtotime( $b->start_time ) )
                  . ' – ' . date( 'g:i A', strtotime( $b->end_time ) );
        $num_days = intval( $b->num_days ?? 1 );

        if ( $event === 'new_booking' ) {
            $msg  = "📅 *New Booking #{$booking_id} — {$site}*\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📋 Service: {$b->service_name}\n";
            if ( $b->staff_name ) $msg .= "👤 Staff: {$b->staff_name}\n";
            $msg .= "📆 Date: {$date}\n";
            $msg .= "🕐 Arrival Time: {$time}\n";
            if ( $num_days > 1 ) $msg .= "🗓 Duration: {$num_days} days\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "👤 Client: {$b->customer_name}\n";
            $msg .= "📞 Phone: {$b->customer_phone}\n";
            if ( $b->customer_email ) $msg .= "📧 Email: {$b->customer_email}\n";
            if ( $b->notes ) $msg .= "📝 Notes: {$b->notes}\n";
            if ( $b->total_price > 0 ) {
                $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "💰 Total: " . number_format( $b->total_price ) . " {$currency}";
                if ( $b->deposit_amount > 0 ) {
                    $msg .= "\n💳 Deposit: " . number_format( $b->deposit_amount ) . " {$currency}";
                }
            }

        } elseif ( $event === 'payment' ) {
            $msg  = "✅ *Payment Confirmed — Booking #{$booking_id}*\n";
            $msg .= "📋 Service: {$b->service_name}\n";
            $msg .= "👤 {$b->customer_name} · {$b->customer_phone}\n";
            $msg .= "📆 {$date} at {$time}\n";
            if ( $num_days > 1 ) $msg .= "🗓 Duration: {$num_days} days\n";
            if ( $b->deposit_amount > 0 ) {
                $msg .= "💰 Deposit Paid: " . number_format( $b->deposit_amount ) . " {$currency}\n";
                $msg .= "💳 Balance Due: " . number_format( $b->balance_amount ) . " {$currency}";
            } else {
                $msg .= "💰 Total Paid: " . number_format( $b->total_price ) . " {$currency}";
            }

        } elseif ( $event === 'proof' ) {
            $msg  = "📸 *Payment Proof Submitted — Booking #{$booking_id}*\n";
            $msg .= "👤 {$b->customer_name} · {$b->customer_phone}\n";
            $msg .= "📋 {$b->service_name} · {$date}\n";
            $msg .= "💰 Deposit: " . number_format( $b->deposit_amount ) . " {$currency}\n";
            $msg .= "➡ Please review in your admin dashboard.";

        } else {
            sb_debug_log( "send_whatsapp_admin: unknown event '{$event}' — skipping" );
            return;
        }

        // Use approved template: app_notification (2 params)
        // {{1}} = customer name
        // {{2}} = booking summary line
        if ( $event === 'new_booking' ) {
            $param1 = $b->customer_name ?? 'Customer';
            $param2 = 'New Booking #' . $booking_id . ' | ' . $b->service_name . ' | ' . $date . ' ' . $time . ' | Phone: ' . $b->customer_phone;
        } elseif ( $event === 'payment' ) {
            $param1 = $b->customer_name ?? 'Customer';
            $param2 = 'Payment Confirmed #' . $booking_id . ' | ' . $b->service_name . ' | Deposit: ' . number_format( $b->deposit_amount ?? 0 ) . ' ' . $currency;
        } elseif ( $event === 'proof' ) {
            $param1 = $b->customer_name ?? 'Customer';
            $param2 = 'Proof Submitted #' . $booking_id . ' | ' . $b->service_name . ' | ' . $date . ' | Deposit: ' . number_format( $b->deposit_amount ?? 0 ) . ' ' . $currency;
        }

        $payload = wp_json_encode( array(
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
                            array( 'type' => 'text', 'text' => $param1 ),
                            array( 'type' => 'text', 'text' => $param2 ),
                        ),
                    ),
                ),
            ),
        ) );

        sb_debug_log( "send_whatsapp_admin: POSTing to graph.facebook.com/v19.0/{$phone_id}/messages" );
        sb_debug_log( "send_whatsapp_admin: to={$to_number} payload_len=" . strlen($payload) );

        $response = wp_remote_post(
            "https://graph.facebook.com/v19.0/{$phone_id}/messages",
            array(
                'timeout'  => 20,
                'blocking' => true,
                'headers'  => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => $payload,
            )
        );

        if ( is_wp_error( $response ) ) {
            sb_debug_log( "send_whatsapp_admin: WP_ERROR — " . $response->get_error_message() );
            sb_debug_log( "send_whatsapp_admin: This usually means your server cannot reach graph.facebook.com" );
            sb_debug_log( "send_whatsapp_admin: FIX — ask your host to allow outbound HTTPS (port 443) to graph.facebook.com" );
            return;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $decoded   = json_decode( $body, true );

        sb_debug_log( "send_whatsapp_admin: HTTP {$http_code}" );
        sb_debug_log( "send_whatsapp_admin: response body = {$body}" );

        if ( $http_code === 200 && ! empty( $decoded['messages'][0]['id'] ) ) {
            sb_debug_log( "send_whatsapp_admin: ✅ SUCCESS — message ID " . $decoded['messages'][0]['id'] );
        } else {
            $err_msg  = $decoded['error']['message']  ?? 'Unknown error';
            $err_code = $decoded['error']['code']     ?? 0;
            sb_debug_log( "send_whatsapp_admin: ❌ FAILED — HTTP {$http_code} | Code {$err_code} | {$err_msg}" );

            if ( $err_code == 190 ) {
                sb_debug_log( "send_whatsapp_admin: FIX — Access token is expired. Generate a permanent System User token." );
            } elseif ( $err_code == 100 ) {
                sb_debug_log( "send_whatsapp_admin: FIX — Phone Number ID is wrong. Check Meta Developers → WhatsApp → API Setup." );
            } elseif ( in_array( $err_code, array(131030, 131026, 131047) ) ) {
                sb_debug_log( "send_whatsapp_admin: FIX — +{$to_number} is not an allowed recipient in test mode." );
                sb_debug_log( "send_whatsapp_admin: Go to Meta Developers → WhatsApp → API Setup → add +{$to_number} as recipient." );
            }
        }
    }

    public function send_proof_submitted( $booking_id ) {
        $b           = SB_DB::get_booking( $booking_id );
        $currency    = get_option( 'sb_currency', 'FCFA' );
        $settings    = get_option( 'sb_settings', array() );
        $ap          = get_option( 'sb_appearance', array() );
        $color       = sanitize_hex_color( $ap['primary'] ?? '#5B2D8E' ) ?: '#5B2D8E';
        $admin_email = $settings['notify_email'] ?? get_option( 'admin_email' );
        $admin_url   = admin_url( 'admin.php?page=sb-bookings' );

        wp_mail(
            $admin_email,
            "📸 Payment Proof Submitted — Booking #{$booking_id} (Needs Review)",
            "<p><strong>{$b->customer_name}</strong> submitted payment proof for booking #{$booking_id}.<br>
            Service: {$b->service_name}<br>
            Deposit: " . number_format($b->deposit_amount) . " {$currency}<br><br>
            <a href='{$admin_url}' style='background:{$color};color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none'>Review &amp; Confirm →</a></p>",
            array( 'Content-Type: text/html; charset=UTF-8' )
        );

        if ( $b->customer_email ) {
            wp_mail(
                $b->customer_email,
                "Payment Proof Received — Booking #{$booking_id}",
                "<div style='font-family:sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px'>
                <h2 style='color:{$color}'>Payment Proof Received</h2>
                <p>Hi <strong>{$b->customer_name}</strong>, we received your payment proof for booking #{$booking_id}. We will verify and confirm within a few minutes.</p>
                </div>",
                array( 'Content-Type: text/html; charset=UTF-8' )
            );
        }
        $this->send_whatsapp_admin( $booking_id, 'proof' );
    }
}

/**
 * Debug logger — writes to wp-content/sb-debug.log
 * Every booking action, every WhatsApp call, every result is logged here.
 * Open the file to see exactly what happened and why.
 */
function sb_debug_log( $message ) {
    if ( ! defined('SB_DEBUG') || ! SB_DEBUG ) return;
    $timestamp = date( 'Y-m-d H:i:s' );
    $line      = "[{$timestamp}] {$message}" . PHP_EOL;
    $log_file  = WP_CONTENT_DIR . '/sb-debug.log';
    file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
}
