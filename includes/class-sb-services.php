<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Services {
    // Thin wrapper — main logic is in SB_DB
    // Add here any business-logic helpers specific to services

    static function get_active() {
        return SB_DB::get_services( array( 'status' => 'active', 'visibility' => 1 ) );
    }

    static function grouped_by_category() {
        $services = self::get_active();
        $cats     = SB_DB::get_categories();
        $map      = array();
        foreach ( $cats as $c ) $map[ $c->id ] = array( 'cat' => $c, 'services' => array() );
        $uncategorised = array();
        foreach ( $services as $s ) {
            if ( isset( $map[ $s->category_id ] ) ) {
                $map[ $s->category_id ]['services'][] = $s;
            } else {
                $uncategorised[] = $s;
            }
        }
        // Remove empty cats
        $out = array_filter( $map, fn($g) => ! empty($g['services']) );
        if ( $uncategorised ) {
            array_unshift( $out, array( 'cat' => (object)array('id'=>0,'name'=>'Services'), 'services' => $uncategorised ) );
        }
        return array_values( $out );
    }

    static function format_duration( $minutes ) {
        if ( $minutes < 60 ) return $minutes . ' min';
        $h = floor( $minutes / 60 );
        $m = $minutes % 60;
        return $m ? "{$h}h {$m}min" : "{$h}h";
    }
}
