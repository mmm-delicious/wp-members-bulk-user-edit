<?php
/**
 * Plugin Name: WP-Members Bulk User Edit
 * Description: Allows for upload of csv to bulk delete users from WP-Members
 * Version: 1.2.2
 * Author: MMM Delicious
 * Developer: Mark McDonnell
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.7
 */

defined( 'ABSPATH' ) || exit;

// Auto-updates via GitHub
require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/mmm-delicious/wp-members-bulk-user-edit/',
    __FILE__,
    'wp-members-bulk-user-edit'
);

add_action('admin_menu', function() {
    add_users_page(
        'Bulk Edit/Delete Users',
        'Bulk Edit/Delete Users',
        'manage_options',
        'bulk-edit-delete-users',
        'render_bulk_user_admin_page'
    );
});

function render_bulk_user_admin_page() {
    ?>
    <div class="wrap">
        <h1>Bulk Edit/Delete Users</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <?php wp_nonce_field( 'bulk_user_csv_upload', 'bulk_user_nonce' ); ?>
            <?php submit_button('Upload CSV'); ?>
        </form>

        <?php
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            check_admin_referer( 'bulk_user_csv_upload', 'bulk_user_nonce' );

            if (!is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                echo '<p style="color:red;">Invalid file upload.</p>';
                return;
            }

            if ($_FILES['csv_file']['size'] > $max_size) {
                echo '<p style="color:red;">File too large. Maximum size is 2MB.</p>';
                return;
            }

            $csv = array_map('str_getcsv', file($_FILES['csv_file']['tmp_name']));
            $headers = array_map('trim', $csv[0]); // Already trims spaces
            $headers = array_map('strtolower', $headers); // Make headers lowercase
            $email_index = array_search('email', $headers);
            $id_index = array_search('id', $headers);

            if ($email_index === false && $id_index === false) {
                echo '<p style="color:red;">CSV must contain an "email" or "ID" column.</p>';
                return;
            }

            echo '<h2>Matched Users</h2><table class="widefat"><thead><tr><th>ID</th><th>Email</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
            foreach (array_slice($csv, 1) as $row) {
                $email = $email_index !== false ? sanitize_email($row[$email_index]) : null;
                $user_id = $id_index !== false ? intval($row[$id_index]) : null;

                $user = false;
                if ($user_id) $user = get_userdata($user_id);
                if (!$user && $email) $user = get_user_by('email', $email);

                if ($user) {
                    $edit_url = get_edit_user_link($user->ID);
                    $delete_url = wp_nonce_url(admin_url("users.php?action=delete&user=$user->ID"), 'bulk-users');
                    echo '<tr>';
                    echo '<td>' . esc_html( $user->ID ) . '</td>';
                    echo '<td>' . esc_html( $user->user_email ) . '</td>';
                    echo '<td>' . esc_html( $user->display_name ) . '</td>';
                    echo '<td>';
                    echo '<a href="' . esc_url( $edit_url ) . '">Edit</a> | ';
                    echo '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Are you sure you want to delete this user?\');">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';
        }
        ?>
    </div>
    <?php
}
