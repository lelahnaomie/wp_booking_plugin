<?php
/**
 * Smart Booking — User Role Management
 *
 * Creates the 'sb_business_owner' role with only the capabilities
 * needed to use the Smart Booking frontend portal — no WP admin access.
 *
 * Key behaviours:
 *  - Admin bar is hidden on all frontend pages for this role
 *  - Role can log in through [sb_portal_login] and reach the portal
 *  - Role CANNOT access wp-admin (redirected away)
 *  - Settings page hides developer/technical sections for this role
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Roles {

    /** Capability that identifies our custom role */
    const CAP = 'sb_manage_bookings';

    /* ─────────────────────────────────────────────────────────
       INIT — attach all hooks
    ───────────────────────────────────────────────────────── */
    public static function init() {

        // Hide admin bar for business owners on the frontend
        add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar_for_owner' ) );

        // Block business owners from accessing wp-admin backend
        add_action( 'admin_init', array( __CLASS__, 'block_admin_access' ) );

        // Extend portal access checks to include our role
        add_filter( 'sb_user_can_access_portal', array( __CLASS__, 'user_can_access_portal' ) );
    }

    /* ─────────────────────────────────────────────────────────
       REGISTER / REMOVE ROLE  (called on plugin activate/deactivate)
    ───────────────────────────────────────────────────────── */
    public static function register_role() {
        // Only add if it doesn't already exist
        if ( get_role( 'sb_business_owner' ) ) return;

        add_role(
            'sb_business_owner',
            'Business Owner',
            array(
                'read'               => true,   // Required for basic WP login
                self::CAP            => true,   // Our custom capability gate
                'upload_files'       => false,
                'edit_posts'         => false,
                'manage_options'     => false,  // Explicitly NO admin access
            )
        );
    }

    public static function remove_role() {
        remove_role( 'sb_business_owner' );
    }

    /* ─────────────────────────────────────────────────────────
       HIDE ADMIN BAR for business owners on the frontend
    ───────────────────────────────────────────────────────── */
    public static function hide_admin_bar_for_owner( $show ) {
        // If this user is a business owner, hide the admin bar
        if ( is_user_logged_in() && current_user_can( self::CAP ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return $show;
    }

    /* ─────────────────────────────────────────────────────────
       BLOCK WP-ADMIN BACKEND ACCESS for business owners
       They should only ever use the frontend portal pages.
    ───────────────────────────────────────────────────────── */
    public static function block_admin_access() {
        // Allow AJAX — it goes through wp-admin/admin-ajax.php
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

        // If they have manage_options they're a real admin — let them through
        if ( current_user_can( 'manage_options' ) ) return;

        // If they have our cap but NOT manage_options — redirect to frontend portal
        if ( current_user_can( self::CAP ) ) {
            $portal_url = SB_Admin_Portal::get_section_url( 'dashboard' );
            wp_safe_redirect( $portal_url ?: home_url() );
            exit;
        }
    }

    /* ─────────────────────────────────────────────────────────
       PORTAL ACCESS FILTER
       Lets other classes check via filter whether user can access portal
    ───────────────────────────────────────────────────────── */
    public static function user_can_access_portal( $can ) {
        if ( $can ) return true; // Already allowed (e.g. manage_options)
        return current_user_can( self::CAP );
    }

    /* ─────────────────────────────────────────────────────────
       HELPER — is current user a business owner (but not full admin)?
    ───────────────────────────────────────────────────────── */
    public static function is_business_owner() {
        return is_user_logged_in()
            && current_user_can( self::CAP )
            && ! current_user_can( 'manage_options' );
    }

    /* ─────────────────────────────────────────────────────────
       HELPER — can current user access the portal at all?
       Returns true for both WP admins and business owners.
    ───────────────────────────────────────────────────────── */
    public static function can_access_portal() {
        if ( ! is_user_logged_in() ) return false;
        if ( current_user_can( 'manage_options' ) ) return true;
        return current_user_can( self::CAP );
    }
}
