<?php
/**
 * Smart Booking — Frontend Login Page
 *
 * Shortcode: [sb_portal_login]
 *
 * - Renders a branded login form on any WordPress page
 * - On success, redirects to the Dashboard portal page
 * - If user is already logged in with manage_options, redirects straight to dashboard
 * - Handles logout via ?sb_logout=1
 *
 * Fix: redirects are done on template_redirect (before headers sent),
 * not inside the shortcode, which avoids the "not a valid JSON response"
 * WordPress block editor publish error.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Login {

    /** Stores a login error message across the request */
    private static $error = '';

    public static function init() {
        add_shortcode( 'sb_portal_login', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts',  array( __CLASS__, 'enqueue' ) );
        add_action( 'template_redirect',   array( __CLASS__, 'handle_requests' ) );
    }

    /* ─────────────────────────────────────────────────────────
       Handle logout + login POST before any output (headers safe)
    ───────────────────────────────────────────────────────── */
    public static function handle_requests() {

        // Never run during REST API, admin, or AJAX requests
        if ( is_admin() ) return;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( wp_doing_ajax() ) return;

        // ── Logout ───────────────────────────────────────────
        if ( isset( $_GET['sb_logout'] ) && '1' === $_GET['sb_logout'] ) {
            if ( is_user_logged_in() ) {
                wp_logout();
            }
            wp_safe_redirect( self::get_login_page_url() );
            exit;
        }

        // ── Only act on our login page ────────────────────────
        if ( ! self::is_login_page() ) return;

        // Already logged in as admin or business owner → go straight to dashboard
        if ( is_user_logged_in() && SB_Roles::can_access_portal() ) {
            wp_safe_redirect( SB_Admin_Portal::get_section_url( 'dashboard' ) );
            exit;
        }

        // ── Handle form POST ──────────────────────────────────
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) return;
        if ( ! isset( $_POST['sb_login_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sb_login_nonce'] ) ), 'sb_portal_login' ) ) {
            self::$error = 'Security check failed. Please try again.';
            return;
        }

        $username = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
        $password = wp_unslash( $_POST['pwd'] ?? '' );
        $remember = ! empty( $_POST['rememberme'] );

        $user = wp_signon( array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ), is_ssl() );

        if ( is_wp_error( $user ) ) {
            // Log the actual WP error code to help debug (visible in PHP error log, not to user)
            error_log( 'SB_Login wp_signon error: ' . $user->get_error_code() . ' — ' . $user->get_error_message() );
            $code = $user->get_error_code();
            if ( $code === 'invalid_email' || $code === 'invalid_username' ) {
                self::$error = 'No account found with that username or email. Please check and try again.';
            } elseif ( $code === 'incorrect_password' ) {
                self::$error = 'Password is incorrect. Please try again or use the Forgot Password link below.';
            } else {
                self::$error = 'Login failed (' . esc_html( $code ) . '). Please check your credentials and try again.';
            }
            return;
        }

        // Must be a full admin OR have the business owner portal capability
        if ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, SB_Roles::CAP ) ) {
            wp_logout();
            self::$error = 'Your account does not have access to this portal.';
            return;
        }

        // Validate that the chosen role matches the user's actual role
        $chosen_role = sanitize_key( $_POST['sb_role'] ?? 'sb_business_owner' );
        if ( $chosen_role === 'administrator' && ! user_can( $user, 'manage_options' ) ) {
            wp_logout();
            self::$error = 'You do not have Administrator access. Please sign in as Business Owner.';
            return;
        }
        if ( $chosen_role === 'sb_business_owner' && ! user_can( $user, SB_Roles::CAP ) ) {
            wp_logout();
            self::$error = 'Your account is not set up as a Business Owner. Please select Administrator or contact your developer.';
            return;
        }

        // ── Success ───────────────────────────────────────────
        $redirect = SB_Admin_Portal::get_section_url( 'dashboard' );

        // If we were redirected here from a portal page, go back there
        if ( ! empty( $_REQUEST['redirect_to'] ) ) {
            $requested = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
            // Only allow same-origin redirects
            if ( strpos( $requested, home_url() ) === 0 ) {
                $redirect = $requested;
            }
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /* ─────────────────────────────────────────────────────────
       Check if current page contains [sb_portal_login]
    ───────────────────────────────────────────────────────── */
    private static function is_login_page() {
        global $post;
        return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'sb_portal_login' );
    }

    /* ─────────────────────────────────────────────────────────
       Enqueue assets only on login page
    ───────────────────────────────────────────────────────── */
    public static function enqueue() {
        if ( ! self::is_login_page() ) return;

        wp_enqueue_style( 'sb-portal-login', SB_URL . 'assets/css/portal-login.css', array(), SB_VER );

        $ap = get_option( 'sb_appearance', array() );
        $p  = sanitize_hex_color( $ap['primary'] ?? '#111111' ) ?: '#111111';
        $pd = sanitize_hex_color( $ap['p_dark']  ?? '#3b1a5c' ) ?: '#3b1a5c';
        wp_add_inline_style( 'sb-portal-login', ":root{--sb-p:{$p};--sb-pd:{$pd};}" );
    }

    /* ─────────────────────────────────────────────────────────
       Shortcode — purely renders HTML, zero redirects
    ───────────────────────────────────────────────────────── */
    public static function shortcode() {
        $s        = get_option( 'sb_settings', array() );
        $biz_name = esc_html( $s['business_name'] ?? get_bloginfo( 'name' ) );
        $error    = self::$error;

        // Recover submitted username for re-fill after error
        $submitted_user = isset( $_POST['log'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['log'] ) ) ) : '';

        ob_start();
        ?>
        <div class="sb-login-wrap">
            <div class="sb-login-card">

                <!-- Logo / brand -->
                <div class="sb-login-header">
                    <div class="sb-login-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                            <path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5C3.89 3 3.01 3.9 3.01 5L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>
                        </svg>
                    </div>
                    <h1 class="sb-login-biz"><?php echo $biz_name; ?></h1>
                    <p class="sb-login-sub">Booking Management Portal</p>
                </div>

                <?php if ( $error ) : ?>
                    <div class="sb-login-error" role="alert"><?php echo esc_html( $error ); ?></div>
                <?php endif; ?>

                <form method="post" class="sb-login-form" novalidate>
                    <?php wp_nonce_field( 'sb_portal_login', 'sb_login_nonce' ); ?>

                    <div class="sb-login-field">
                        <label for="sb_log">Username or Email</label>
                        <input
                            type="text"
                            id="sb_log"
                            name="log"
                            value="<?php echo $submitted_user; ?>"
                            autocomplete="username"
                            placeholder="admin@yourbusiness.com"
                            required
                        />
                    </div>

                    <div class="sb-login-field">
                        <label for="sb_pwd">Password</label>
                        <div class="sb-pwd-wrap">
                            <input
                                type="password"
                                id="sb_pwd"
                                name="pwd"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                required
                            />
                            <button type="button" class="sb-pwd-toggle" aria-label="Show password">
                                <!-- Eye open -->
                                <svg class="sb-eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                <!-- Eye off -->
                                <svg class="sb-eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Role selector — COMMENTED OUT; role is determined automatically from user capabilities
                    <div class="sb-login-field">
                        <label for="sb_role">Sign in as</label>
                        <div class="sb-login-role-wrap">
                            <!-- Person icon - ->
                            <!-- <svg class="sb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"> - ->
                                <!-- <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/> - ->
                            </svg>
                            <select id="sb_role" name="sb_role" class="sb-login-role-select">
                                <option value="sb_business_owner">Business Owner</option>
                                <option value="administrator">Administrator</option>
                            </select>
                        </div>
                        <div class="sb-role-pills">
                            <button type="button" class="sb-role-pill active" data-role="sb_business_owner">
                                Business Owner
                            </button>
                            <button type="button" class="sb-role-pill" data-role="administrator">
                                Administrator
                            </button>
                        </div>
                    </div>
                    -->
                    <!-- Hidden field keeps backward-compat; role validated server-side against actual user capabilities -->
                    <input type="hidden" name="sb_role" value="sb_business_owner" />

                    <div class="sb-login-remember">
                        <label class="sb-remember-label">
                            <input type="checkbox" name="rememberme" value="forever" />
                            <span>Remember me</span>
                        </label>
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="sb-forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="sb-login-btn">Sign In to Portal</button>
                </form>

            </div>

            <p class="sb-login-footer-note">
                &copy; <?php echo date('Y'); ?> <?php echo $biz_name; ?> &mdash; Powered by SmartBooking
            </p>
        </div>

        <script>
        (function(){
            // ── Password toggle ───────────────────────────
            var btn = document.querySelector('.sb-pwd-toggle');
            if (btn) {
                btn.addEventListener('click', function(){
                    var inp = document.getElementById('sb_pwd');
                    if (!inp) return;
                    inp.type = inp.type === 'password' ? 'text' : 'password';
                    btn.classList.toggle('visible');
                });
            }

            // ── Role pill quick-pick ──────────────────────
            var roleSelect = document.getElementById('sb_role');
            var pills = document.querySelectorAll('.sb-role-pill');

            pills.forEach(function(pill) {
                pill.addEventListener('click', function() {
                    var role = this.dataset.role;
                    // Update the hidden select value
                    if (roleSelect) roleSelect.value = role;
                    // Toggle active class on pills
                    pills.forEach(function(p) { p.classList.remove('active'); });
                    this.classList.add('active');
                });
            });

            // Keep pills in sync if someone changes the select directly
            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    pills.forEach(function(p) {
                        p.classList.toggle('active', p.dataset.role === roleSelect.value);
                    });
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────────────────
       Get the URL of the page with [sb_portal_login]
    ───────────────────────────────────────────────────────── */
    public static function get_login_page_url() {
        // Try stored page ID first (set on activation)
        $pid = (int) get_option( 'sb_login_page_id', 0 );
        if ( $pid ) {
            $url = get_permalink( $pid );
            if ( $url ) return $url;
        }

        // Fallback: search by shortcode in content
        global $wpdb;
        $pid = (int) $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status='publish' AND post_type='page'
               AND post_content LIKE '%[sb_portal_login]%'
             LIMIT 1"
        );
        if ( $pid ) return get_permalink( $pid );

        // Last resort: WordPress login
        return wp_login_url();
    }
}
