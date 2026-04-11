<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Staff {
    static function get_for_service( $service_id ) {
        return SB_DB::get_staff_for_service( $service_id );
    }
}
