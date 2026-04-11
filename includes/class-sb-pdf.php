<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_PDF {

    static function maybe_serve() {
        if ( empty( $_GET['sb_pdf'] ) ) return;
        $id    = intval( $_GET['sb_pdf'] );
        $nonce = $_GET['sb_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'sb_pdf_' . $id ) ) wp_die( 'Invalid receipt link.' );
        self::serve( $id );
        exit;
    }

    static function serve( $id ) {
        $b = SB_DB::get_booking( $id );
        if ( ! $b ) wp_die( 'Booking not found.' );

        $currency = get_option( 'sb_currency', 'FCFA' );
        $site     = get_bloginfo( 'name' );
        $date     = date( 'D d M Y', strtotime( $b->booking_date ) );
        $time     = date( 'g:i A', strtotime( $b->start_time ) ) . ' – ' . date( 'g:i A', strtotime( $b->end_time ) );

        header( 'Content-Type: text/html; charset=UTF-8' );
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Receipt #' . $id . '</title>
        <style>
          body{font-family:sans-serif;max-width:500px;margin:40px auto;padding:20px;color:#1a1a2e}
          h1{color:#5B2D8E;font-size:1.4rem;margin-bottom:4px}
          .sub{color:#64748b;font-size:.9rem;margin-bottom:24px}
          table{width:100%;border-collapse:collapse}
          td{padding:10px 14px;border-bottom:1px solid #f1f5f9}
          tr:nth-child(even) td{background:#f8f5ff}
          .label{font-weight:bold;width:140px}
          .total{font-size:1.1rem;color:#5B2D8E;font-weight:bold}
          .footer{margin-top:32px;font-size:.85rem;color:#94a3b8;text-align:center}
          @media print{body{margin:0}}
        </style></head><body>
        <h1>' . esc_html($site) . ' — Booking Receipt</h1>
        <p class="sub">Booking #' . $id . ' &bull; Issued ' . date('d M Y') . '</p>
        <table>
          <tr><td class="label">Service</td><td>' . esc_html($b->service_name) . '</td></tr>
          <tr><td class="label">Staff</td><td>' . esc_html($b->staff_name ?: 'Any') . '</td></tr>
          <tr><td class="label">Date</td><td>' . esc_html($date) . '</td></tr>
          <tr><td class="label">Time</td><td>' . esc_html($time) . '</td></tr>
          <tr><td class="label">Client</td><td>' . esc_html($b->customer_name) . '</td></tr>
          <tr><td class="label">Phone</td><td>' . esc_html($b->customer_phone) . '</td></tr>
          ' . ($b->total_price > 0 ? '
          <tr><td class="label">Total</td><td class="total">' . number_format($b->total_price) . ' ' . esc_html($currency) . '</td></tr>
          ' . ($b->deposit_amount > 0 ? '<tr><td class="label">Deposit</td><td>' . number_format($b->deposit_amount) . ' ' . esc_html($currency) . '</td></tr>
          <tr><td class="label">Balance</td><td>' . number_format($b->balance_amount) . ' ' . esc_html($currency) . '</td></tr>' : '') . '
          ' : '') . '
          <tr><td class="label">Status</td><td>' . ucfirst(esc_html($b->status)) . '</td></tr>
        </table>
        <div class="footer">Thank you for your booking. Please keep this receipt.<br>
        <button onclick="window.print()" style="margin-top:12px;padding:8px 20px;background:#5B2D8E;color:#fff;border:none;border-radius:6px;cursor:pointer">Print / Save PDF</button>
        </div></body></html>';
    }
}

add_action( 'template_redirect', array( 'SB_PDF', 'maybe_serve' ) );
