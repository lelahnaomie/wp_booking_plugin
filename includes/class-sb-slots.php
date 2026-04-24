<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Slots {

    /**
     * Return available time slots for a service + staff + date.
     *
     * staff_id = 0  (staff disabled):
     *   - All 7 days are open
     *   - Hours from Settings → Business Hours (default 08:00–18:00)
     *   - Booked slots checked across all staff for that service
     *
     * staff_id > 0  (staff enabled):
     *   - Only staff's working days available
     *   - Uses staff's configured working hours
     *   - Only checks that staff member's bookings
     */
    static function get_slots( $service_id, $staff_id, $date ) {
        $service = SB_DB::get_service( $service_id );
        if ( ! $service ) return array();

        $duration    = intval( $service->duration    ?: 60 );
        $padding     = intval( $service->padding_time ?? 0 );
        $slot_length = max( 1, $duration + $padding );

        if ( ! $staff_id ) {
            // ── NO STAFF MODE: all days open, global business hours ──────
            $open  = get_option( 'sb_global_open_time',  '08:00' );
            $close = get_option( 'sb_global_close_time', '18:00' );

            $start_ts = strtotime( $date . ' ' . $open  );
            $end_ts   = strtotime( $date . ' ' . $close );

            // Check all bookings for this service on this date (any staff)
            $booked = SB_DB::get_booked_slots_for_service( $service_id, $date );

        } else {
            // ── STAFF MODE: check staff working hours ────────────────────
            $staff = SB_DB::get_staff_member( $staff_id );
            if ( ! $staff ) return array();

            $dow   = intval( date( 'w', strtotime( $date ) ) );
            $hours = SB_DB::get_working_hours( $staff_id );

            if ( empty( $hours[ $dow ] ) || ! empty( $hours[ $dow ]->is_day_off ) ) {
                return array(); // Staff day off
            }

            $h        = $hours[ $dow ];
            $start_ts = strtotime( $date . ' ' . $h->start_time );
            $end_ts   = strtotime( $date . ' ' . $h->end_time );

            $booked = SB_DB::get_booked_slots( $staff_id, $date );
        }

        // Build booked ranges array
        $booked_ranges = array();
        foreach ( $booked as $b ) {
            $booked_ranges[] = array(
                'start' => strtotime( $date . ' ' . $b->start_time ),
                'end'   => strtotime( $date . ' ' . $b->end_time ),
            );
        }

        $slots   = array();
        $now     = time();
        $current = $start_ts;

        while ( $current + $duration * 60 <= $end_ts ) {
            $slot_end  = $current + $duration * 60;
            $available = true;

            if ( $current <= $now ) {
                $current += $slot_length * 60;
                continue;
            }

            foreach ( $booked_ranges as $br ) {
                if ( $current < $br['end'] && $slot_end > $br['start'] ) {
                    $available = false;
                    break;
                }
            }

            $slots[] = array(
                'time'      => date( 'H:i', $current ),
                'label'     => date( 'g:i A', $current ),
                'end_time'  => date( 'H:i', $slot_end ),
                'available' => $available,
            );

            $current += $slot_length * 60;
        }

        return $slots;
    }

    /**
     * Dates that are fully unavailable for a month.
     *
     * No-staff mode  → only past dates blocked; all future dates clickable.
     * Staff mode     → also blocks staff's days-off.
     */
    static function get_unavailable_dates( $service_id, $staff_id, $year, $month ) {
        $service = SB_DB::get_service( $service_id );
        if ( ! $service ) return array();

        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $today         = strtotime( date( 'Y-m-d' ) );
        $unavailable   = array();

        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date    = sprintf( '%04d-%02d-%02d', $year, $month, $d );
            $date_ts = strtotime( $date );

            // Past dates always blocked
            if ( $date_ts < $today ) {
                $unavailable[] = $date;
                continue;
            }

            if ( ! $staff_id ) {
                // No-staff mode: check if all slots are taken
                $slots    = self::get_slots( $service_id, 0, $date );
                $has_open = false;
                foreach ( $slots as $s ) {
                    if ( $s['available'] ) { $has_open = true; break; }
                }
                if ( ! $has_open ) $unavailable[] = $date;

            } else {
                // Staff mode: check working day first, then slots
                $dow   = intval( date( 'w', $date_ts ) );
                $hours = SB_DB::get_working_hours( $staff_id );

                if ( empty( $hours[ $dow ] ) || ! empty( $hours[ $dow ]->is_day_off ) ) {
                    $unavailable[] = $date;
                    continue;
                }

                $slots    = self::get_slots( $service_id, $staff_id, $date );
                $has_open = false;
                foreach ( $slots as $s ) {
                    if ( $s['available'] ) { $has_open = true; break; }
                }
                if ( ! $has_open ) $unavailable[] = $date;
            }
        }

        return $unavailable;
    }
}
