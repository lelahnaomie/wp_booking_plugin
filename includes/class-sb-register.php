<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Register {

    public static function init() {
        add_shortcode( 'sb_register', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_ajax_nopriv_sb_do_register', array( __CLASS__, 'ajax_register' ) );
    }

    public static function shortcode() {
        ob_start();
        ?>

        <div class="sb-register-wrap">
            <form id="sbRegisterForm">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <select name="role">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                </select>

                <button type="submit">Register</button>
                <div id="sbRegisterMsg"></div>
            </form>
        </div>

        <script>
        document.getElementById('sbRegisterForm').addEventListener('submit', function(e){
            e.preventDefault();

            var formData = new FormData(this);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData.append('action','sb_do_register') || formData
            })
            .then(res => res.json())
            .then(res => {
                document.getElementById('sbRegisterMsg').innerText = res.data.message;
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }

    public static function ajax_register() {

        $username = sanitize_user($_POST['username']);
        $email    = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $role     = $_POST['role'];

        if ( username_exists($username) || email_exists($email) ) {
            wp_send_json_error(['message'=>'User already exists']);
        }

        $user_id = wp_create_user($username, $password, $email);

        if ( is_wp_error($user_id) ) {
            wp_send_json_error(['message'=>'Registration failed']);
        }

        wp_update_user([
            'ID' => $user_id,
            'role' => $role
        ]);

        wp_send_json_success(['message'=>'Registration successful. You can now login.']);
    }
}