<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_DB {

    static function install() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── SERVICES ──────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_services (
            id           INT(11)       NOT NULL AUTO_INCREMENT,
            category_id  INT(11)       DEFAULT 0,
            name         VARCHAR(255)  NOT NULL,
            description  TEXT          DEFAULT '',
            duration     INT(11)       NOT NULL DEFAULT 60,
            padding_time INT(11)       DEFAULT 0,
            price        DECIMAL(10,2) DEFAULT 0,
            deposit_pct  DECIMAL(5,2)  DEFAULT 0,
            color        VARCHAR(10)   DEFAULT '#5B2D8E',
            image_url    VARCHAR(500)  DEFAULT '',
            capacity     INT(11)       DEFAULT 1,
            visibility   TINYINT(1)    DEFAULT 1,
            sort_order   INT(11)       DEFAULT 0,
            status       VARCHAR(20)   DEFAULT 'active',
            created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $c;" );

        // ── SERVICE CATEGORIES ────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_service_cats (
            id         INT(11)      NOT NULL AUTO_INCREMENT,
            name       VARCHAR(255) NOT NULL,
            sort_order INT(11)      DEFAULT 0,
            PRIMARY KEY (id)
        ) $c;" );

        // ── STAFF ─────────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_staff (
            id         INT(11)      NOT NULL AUTO_INCREMENT,
            wp_user_id INT(11)      DEFAULT 0,
            name       VARCHAR(255) NOT NULL,
            email      VARCHAR(255) DEFAULT '',
            phone      VARCHAR(50)  DEFAULT '',
            bio        TEXT         DEFAULT '',
            image_url  VARCHAR(500) DEFAULT '',
            color      VARCHAR(10)  DEFAULT '#C9A84C',
            visibility TINYINT(1)   DEFAULT 1,
            status     VARCHAR(20)  DEFAULT 'active',
            created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $c;" );

        // ── STAFF ↔ SERVICE pivot ─────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_staff_services (
            staff_id   INT(11) NOT NULL,
            service_id INT(11) NOT NULL,
            PRIMARY KEY (staff_id, service_id)
        ) $c;" );

        // ── STAFF WORKING HOURS ───────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_staff_hours (
            id         INT(11)     NOT NULL AUTO_INCREMENT,
            staff_id   INT(11)     NOT NULL,
            day_of_week TINYINT(1) NOT NULL,
            start_time  TIME       DEFAULT '08:00:00',
            end_time    TIME       DEFAULT '18:00:00',
            is_day_off  TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY staff_day (staff_id, day_of_week)
        ) $c;" );

        // ── BOOKINGS ──────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_bookings (
            id             INT(11)       NOT NULL AUTO_INCREMENT,
            service_id     INT(11)       NOT NULL,
            staff_id       INT(11)       DEFAULT 0,
            customer_name  VARCHAR(255)  NOT NULL,
            customer_email VARCHAR(255)  DEFAULT '',
            customer_phone VARCHAR(50)   DEFAULT '',
            notes          TEXT          DEFAULT '',
            booking_date   DATE          NOT NULL,
            start_time     TIME          NOT NULL,
            end_time       TIME          NOT NULL,
            total_price    DECIMAL(10,2) DEFAULT 0,
            deposit_amount DECIMAL(10,2) DEFAULT 0,
            balance_amount DECIMAL(10,2) DEFAULT 0,
            payment_method VARCHAR(30)   DEFAULT 'whatsapp',
            payment_status VARCHAR(30)   DEFAULT 'unpaid',
            payment_ref    VARCHAR(255)  DEFAULT '',
            payment_date   DATETIME      DEFAULT NULL,
            status         VARCHAR(20)   DEFAULT 'pending',
            internal_note  TEXT          DEFAULT '',
            google_event_id VARCHAR(255) DEFAULT '',
            created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY staff_id (staff_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $c;" );

        // ── CUSTOMERS ─────────────────────────────────────────
        dbDelta( "CREATE TABLE {$wpdb->prefix}sb_customers (
            id           INT(11)      NOT NULL AUTO_INCREMENT,
            wp_user_id   INT(11)      DEFAULT 0,
            name         VARCHAR(255) NOT NULL,
            email        VARCHAR(255) DEFAULT '',
            phone        VARCHAR(50)  DEFAULT '',
            notes        TEXT         DEFAULT '',
            total_bookings INT(11)    DEFAULT 0,
            total_spent  DECIMAL(10,2) DEFAULT 0,
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY phone (phone)
        ) $c;" );

        // Seed default data if empty
        self::maybe_seed();
    }

    static function maybe_seed() {
        global $wpdb;
        if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_staff" ) > 0 ) return;

        // Default staff
        $wpdb->insert( $wpdb->prefix . 'sb_staff', array(
            'name'  => 'Default Staff',
            'email' => get_option( 'admin_email' ),
            'color' => '#C9A84C',
        ), array( '%s', '%s', '%s' ) );
        $staff_id = $wpdb->insert_id;

        // Default working hours Mon–Sat
        for ( $d = 1; $d <= 6; $d++ ) {
            $wpdb->insert( $wpdb->prefix . 'sb_staff_hours', array(
                'staff_id'    => $staff_id,
                'day_of_week' => $d,
                'start_time'  => '08:00:00',
                'end_time'    => '18:00:00',
                'is_day_off'  => 0,
            ), array( '%d', '%d', '%s', '%s', '%d' ) );
        }
        // Sunday off
        $wpdb->insert( $wpdb->prefix . 'sb_staff_hours', array(
            'staff_id'    => $staff_id,
            'day_of_week' => 0,
            'start_time'  => '08:00:00',
            'end_time'    => '18:00:00',
            'is_day_off'  => 1,
        ), array( '%d', '%d', '%s', '%s', '%d' ) );
    }

    // ── SERVICES ──────────────────────────────────────────────
    static function get_services( $args = array() ) {
        global $wpdb;
        $where = "WHERE 1=1";
        if ( ! empty( $args['status'] ) ) $where .= $wpdb->prepare( " AND status=%s", $args['status'] );
        if ( isset( $args['visibility'] ) ) $where .= $wpdb->prepare( " AND visibility=%d", $args['visibility'] );
        return $wpdb->get_results( "SELECT s.*, c.name as category_name FROM {$wpdb->prefix}sb_services s
            LEFT JOIN {$wpdb->prefix}sb_service_cats c ON s.category_id=c.id $where ORDER BY s.sort_order,s.name" );
    }

    static function get_service( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sb_services WHERE id=%d", $id ) );
    }

    static function save_service( $d ) {
        global $wpdb;
        $data = array(
            'name'        => sanitize_text_field( $d['name'] ),
            'category_id' => intval( $d['category_id'] ?? 0 ),
            'description' => sanitize_textarea_field( $d['description'] ?? '' ),
            'duration'    => intval( $d['duration'] ?? 60 ),
            'padding_time'=> intval( $d['padding_time'] ?? 0 ),
            'price'       => floatval( $d['price'] ?? 0 ),
            'deposit_pct' => floatval( $d['deposit_pct'] ?? 0 ),
            'color'       => sanitize_hex_color( $d['color'] ?? '#5B2D8E' ),
            'image_url'   => esc_url_raw( $d['image_url'] ?? '' ),
            'capacity'    => intval( $d['capacity'] ?? 1 ),
            'visibility'  => intval( $d['visibility'] ?? 1 ),
            'sort_order'  => intval( $d['sort_order'] ?? 0 ),
            'status'      => sanitize_text_field( $d['status'] ?? 'active' ),
        );
        if ( ! empty( $d['id'] ) ) {
            $wpdb->update( $wpdb->prefix . 'sb_services', $data, array( 'id' => intval( $d['id'] ) ) );
            return intval( $d['id'] );
        }
        $wpdb->insert( $wpdb->prefix . 'sb_services', $data );
        return $wpdb->insert_id;
    }

    static function delete_service( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'sb_services', array( 'id' => intval( $id ) ) );
        $wpdb->delete( $wpdb->prefix . 'sb_staff_services', array( 'service_id' => intval( $id ) ) );
    }

    // ── CATEGORIES ────────────────────────────────────────────
    static function get_categories() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sb_service_cats ORDER BY sort_order,name" );
    }

    static function save_category( $d ) {
        global $wpdb;
        if ( ! empty( $d['id'] ) ) {
            $wpdb->update( $wpdb->prefix . 'sb_service_cats', array( 'name' => sanitize_text_field( $d['name'] ) ), array( 'id' => intval( $d['id'] ) ) );
            return intval( $d['id'] );
        }
        $wpdb->insert( $wpdb->prefix . 'sb_service_cats', array( 'name' => sanitize_text_field( $d['name'] ) ) );
        return $wpdb->insert_id;
    }

    // ── STAFF ─────────────────────────────────────────────────
    static function get_staff( $active_only = false ) {
        global $wpdb;
        $where = $active_only ? "WHERE status='active' AND visibility=1" : "WHERE 1=1";
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sb_staff $where ORDER BY name" );
    }

    static function get_staff_member( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sb_staff WHERE id=%d", $id ) );
    }

    static function save_staff( $d ) {
        global $wpdb;
        $data = array(
            'name'       => sanitize_text_field( $d['name'] ),
            'email'      => sanitize_email( $d['email'] ?? '' ),
            'phone'      => sanitize_text_field( $d['phone'] ?? '' ),
            'bio'        => sanitize_textarea_field( $d['bio'] ?? '' ),
            'color'      => sanitize_hex_color( $d['color'] ?? '#C9A84C' ),
            'visibility' => intval( $d['visibility'] ?? 1 ),
            'status'     => sanitize_text_field( $d['status'] ?? 'active' ),
        );
        if ( ! empty( $d['id'] ) ) {
            $wpdb->update( $wpdb->prefix . 'sb_staff', $data, array( 'id' => intval( $d['id'] ) ) );
            return intval( $d['id'] );
        }
        $wpdb->insert( $wpdb->prefix . 'sb_staff', $data );
        return $wpdb->insert_id;
    }

    static function delete_staff( $id ) {
        global $wpdb;
        $id = intval( $id );
        $wpdb->delete( $wpdb->prefix . 'sb_staff',          array( 'id'       => $id ) );
        $wpdb->delete( $wpdb->prefix . 'sb_staff_services', array( 'staff_id' => $id ) );
        $wpdb->delete( $wpdb->prefix . 'sb_staff_hours',    array( 'staff_id' => $id ) );
    }

    // Staff ↔ Service links
    static function get_staff_for_service( $service_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}sb_staff s
             INNER JOIN {$wpdb->prefix}sb_staff_services ss ON s.id=ss.staff_id
             WHERE ss.service_id=%d AND s.status='active' AND s.visibility=1 ORDER BY s.name",
            $service_id
        ) );
    }

    static function get_services_for_staff( $staff_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT service_id FROM {$wpdb->prefix}sb_staff_services WHERE staff_id=%d", $staff_id
        ) );
    }

    static function set_staff_services( $staff_id, $service_ids ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'sb_staff_services', array( 'staff_id' => intval( $staff_id ) ) );
        foreach ( (array) $service_ids as $sid ) {
            $wpdb->insert( $wpdb->prefix . 'sb_staff_services', array(
                'staff_id'   => intval( $staff_id ),
                'service_id' => intval( $sid ),
            ) );
        }
    }

    // Working hours
    static function get_working_hours( $staff_id ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sb_staff_hours WHERE staff_id=%d ORDER BY day_of_week", $staff_id
        ) );
        // index by day
        $out = array();
        foreach ( $rows as $r ) $out[ $r->day_of_week ] = $r;
        return $out;
    }

    static function save_working_hours( $staff_id, $hours ) {
        global $wpdb;
        $staff_id = intval( $staff_id );
        foreach ( $hours as $day => $h ) {
            $wpdb->replace( $wpdb->prefix . 'sb_staff_hours', array(
                'staff_id'    => $staff_id,
                'day_of_week' => intval( $day ),
                'start_time'  => sanitize_text_field( $h['start'] ?? '08:00' ),
                'end_time'    => sanitize_text_field( $h['end']   ?? '18:00' ),
                'is_day_off'  => intval( $h['off'] ?? 0 ),
            ) );
        }
    }

    // ── BOOKINGS ──────────────────────────────────────────────
    static function save_booking( $d ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'sb_bookings', array(
            'service_id'     => intval( $d['service_id'] ),
            'staff_id'       => intval( $d['staff_id'] ?? 0 ),
            'customer_name'  => sanitize_text_field( $d['customer_name'] ),
            'customer_email' => sanitize_email( $d['customer_email'] ?? '' ),
            'customer_phone' => sanitize_text_field( $d['customer_phone'] ?? '' ),
            'notes'          => sanitize_textarea_field( $d['notes'] ?? '' ),
            'booking_date'   => sanitize_text_field( $d['booking_date'] ),
            'start_time'     => sanitize_text_field( $d['start_time'] ),
            'end_time'       => sanitize_text_field( $d['end_time'] ),
            'total_price'    => floatval( $d['total_price'] ),
            'deposit_amount' => floatval( $d['deposit_amount'] ?? 0 ),
            'balance_amount' => floatval( $d['balance_amount'] ?? 0 ),
            'payment_method' => sanitize_text_field( $d['payment_method'] ?? 'whatsapp' ),
            'status'         => floatval($d['deposit_amount'] ?? 0) > 0 ? 'awaiting_deposit' : 'pending',
        ) );
        $id = $wpdb->insert_id;
        // upsert customer record
        if ( $id ) self::upsert_customer( $d );
        return $id;
    }

    static function upsert_customer( $d ) {
        global $wpdb;
        $email = sanitize_email( $d['customer_email'] ?? '' );
        $phone = sanitize_text_field( $d['customer_phone'] ?? '' );
        if ( ! $email && ! $phone ) return;
        $existing = $email
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sb_customers WHERE email=%s", $email ) )
            : $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sb_customers WHERE phone=%s", $phone ) );
        if ( $existing ) {
            $wpdb->update( $wpdb->prefix . 'sb_customers', array(
                'total_bookings' => $existing->total_bookings + 1,
                'total_spent'    => $existing->total_spent + floatval( $d['total_price'] ?? 0 ),
            ), array( 'id' => $existing->id ) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'sb_customers', array(
                'name'           => sanitize_text_field( $d['customer_name'] ?? '' ),
                'email'          => $email,
                'phone'          => $phone,
                'total_bookings' => 1,
                'total_spent'    => floatval( $d['total_price'] ?? 0 ),
            ) );
        }
    }

    static function get_bookings( $args = array() ) {
        global $wpdb;
        $where = "WHERE 1=1";
        $params = array();
        if ( ! empty( $args['status'] ) )     { $where .= " AND b.status=%s";       $params[] = $args['status']; }
        if ( ! empty( $args['service_id'] ) ) { $where .= " AND b.service_id=%d";   $params[] = intval( $args['service_id'] ); }
        if ( ! empty( $args['staff_id'] ) )   { $where .= " AND b.staff_id=%d";     $params[] = intval( $args['staff_id'] ); }
        if ( ! empty( $args['date'] ) )       { $where .= " AND b.booking_date=%s"; $params[] = $args['date']; }
        if ( ! empty( $args['date_from'] ) )  { $where .= " AND b.booking_date>=%s";$params[] = $args['date_from']; }
        if ( ! empty( $args['date_to'] ) )    { $where .= " AND b.booking_date<=%s";$params[] = $args['date_to']; }
        $sql = "SELECT b.*, s.name as service_name, s.color as service_color,
                st.name as staff_name
                FROM {$wpdb->prefix}sb_bookings b
                LEFT JOIN {$wpdb->prefix}sb_services s  ON b.service_id=s.id
                LEFT JOIN {$wpdb->prefix}sb_staff    st ON b.staff_id=st.id
                $where ORDER BY b.booking_date DESC, b.start_time DESC";
        return $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) )
            : $wpdb->get_results( $sql );
    }

    static function get_booking( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, s.name as service_name, s.duration, s.color as service_color,
             st.name as staff_name, st.phone as staff_phone
             FROM {$wpdb->prefix}sb_bookings b
             LEFT JOIN {$wpdb->prefix}sb_services s  ON b.service_id=s.id
             LEFT JOIN {$wpdb->prefix}sb_staff    st ON b.staff_id=st.id
             WHERE b.id=%d", $id
        ) );
    }

    static function update_booking_status( $id, $status ) {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'sb_bookings',
            array( 'status' => sanitize_text_field( $status ) ),
            array( 'id'     => intval( $id ) )
        );
    }

    static function update_payment_status( $id, $payment_status, $ref = '' ) {
        global $wpdb;
        $data = array(
            'payment_status' => sanitize_text_field( $payment_status ),
            'payment_ref'    => sanitize_text_field( $ref ),
        );
        if ( $payment_status === 'deposit_paid' || $payment_status === 'paid_in_full' ) {
            $data['status']       = 'confirmed'; // auto-confirm on real payment
            $data['payment_date'] = current_time('mysql');
        }
        $wpdb->update( $wpdb->prefix . 'sb_bookings', $data, array('id' => intval($id)) );
    }

    static function delete_booking( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'sb_bookings', array( 'id' => intval( $id ) ) );
    }

    // Get booked slots for a staff+date combo (for availability)
    static function get_booked_slots( $staff_id, $date ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}sb_bookings
             WHERE staff_id=%d AND booking_date=%s AND status NOT IN ('cancelled')",
            intval( $staff_id ), sanitize_text_field( $date )
        ) );
    }

    // Customers
    static function get_customers( $search = '' ) {
        global $wpdb;
        if ( $search ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sb_customers WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s ORDER BY created_at DESC",
                "%$search%", "%$search%", "%$search%"
            ) );
        }
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sb_customers ORDER BY created_at DESC" );
    }

    // Stats
    static function get_stats() {
        global $wpdb;
        $t = $wpdb->prefix . 'sb_bookings';
        $today = date( 'Y-m-d' );
        return array(
            'total'      => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM $t" ),
            'today'      => (int)   $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE booking_date=%s", $today ) ),
            'pending'    => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status='pending'" ),
            'confirmed'  => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status='confirmed'" ),
            'revenue'    => (float) $wpdb->get_var( "SELECT SUM(total_price) FROM $t WHERE status NOT IN ('cancelled')" ),
            'deposits'   => (float) $wpdb->get_var( "SELECT SUM(deposit_amount) FROM $t WHERE payment_status IN ('deposit_paid','paid_in_full')" ),
            'services'   => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_services WHERE status='active'" ),
            'staff'      => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_staff WHERE status='active'" ),
            'customers'  => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sb_customers" ),
        );
    }
}