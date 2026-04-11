<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Slots {

    /**
     * Return available time slots for a given service + staff + date.
     * Returns array of ['time'=>'09:00','label'=>'9:00 AM','available'=>true]
     */
    static function get_slots( $service_id, $staff_id, $date ) {
        $service = SB_DB::get_service( $service_id );
        if ( ! $service ) return array();

        $staff = SB_DB::get_staff_member( $staff_id );
        if ( ! $staff ) return array();

        $day_of_week = intval( date( 'w', strtotime( $date ) ) );
        $hours       = SB_DB::get_working_hours( $staff_id );

        if ( empty( $hours[ $day_of_week ] ) || $hours[ $day_of_week ]->is_day_off ) {
            return array(); // day off
        }

        $h = $hours[ $day_of_week ];
        $start_ts = strtotime( $date . ' ' . $h->start_time );
        $end_ts   = strtotime( $date . ' ' . $h->end_time );

        $duration    = intval( $service->duration );
        $padding     = intval( $service->padding_time );
        $slot_length = $duration + $padding;

        // Get already booked slots
        $booked = SB_DB::get_booked_slots( $staff_id, $date );
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
            $slot_end = $current + $duration * 60;
            $available = true;

            // Skip past slots
            if ( $current <= $now ) {
                $current += $slot_length * 60;
                continue;
            }

            // Check overlap with booked ranges
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
     * Get dates that are fully booked for a service+staff in a given month.
     * Used to grey out calendar days.
     */
    static function get_unavailable_dates( $service_id, $staff_id, $year, $month ) {
        $service = SB_DB::get_service( $service_id );
        if ( ! $service ) return array();

        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $unavailable   = array();

        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date = sprintf( '%04d-%02d-%02d', $year, $month, $d );
            if ( strtotime( $date ) < strtotime( date( 'Y-m-d' ) ) ) {
                $unavailable[] = $date;
                continue;
            }
            $slots = self::get_slots( $service_id, $staff_id, $date );
            $has_open = false;
            foreach ( $slots as $s ) {
                if ( $s['available'] ) { $has_open = true; break; }
            }
            if ( ! $has_open ) $unavailable[] = $date;
        }

        return $unavailable;
    }
}
