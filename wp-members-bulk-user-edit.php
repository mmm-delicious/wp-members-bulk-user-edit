<?php
/**
 * Plugin Name: WP-Members Bulk User Edit
 * Description: Allows for upload of csv to bulk delete users from WP-Members
 * Version: 1.2.3
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

// Bulk delete handler — runs before page render
add_action('admin_init', function() {
    if (
        isset($_POST['action']) && $_POST['action'] === 'bulk_delete' &&
        isset($_GET['page']) && $_GET['page'] === 'bulk-edit-delete-users'
    ) {
        check_admin_referer('mmm_bulk_delete_users', 'mmm_bulk_delete_nonce');
        if (!current_user_can('delete_users')) wp_die('Unauthorized.');

        $ids = array_map('intval', (array)($_POST['user_ids'] ?? []));
        $ids = array_filter($ids, fn($id) => $id > 0);

        if (!empty($ids)) {
            // Safety cap: never delete more than 1% of total users at once
            $total_users = (int)(count_users()['total_users']);
            $max_allowed = max(1, (int)floor($total_users * 0.01));

            if (count($ids) > $max_allowed) {
                set_transient('mmm_bulk_delete_error_' . get_current_user_id(),
                    sprintf('⛔ Blocked: attempted to delete %d users which exceeds the 1%% safety cap (%d max for %d total users). Select fewer users and try again.',
                        count($ids), $max_allowed, $total_users),
                    60
                );
                wp_redirect(add_query_arg(['page' => 'bulk-edit-delete-users'], admin_url('users.php')));
                exit;
            }

            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($ids as $id) {
                wp_delete_user($id);
            }
            set_transient('mmm_bulk_delete_success_' . get_current_user_id(), count($ids), 60);
        }

        wp_redirect(add_query_arg(['page' => 'bulk-edit-delete-users'], admin_url('users.php')));
        exit;
    }
});

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
    $uid = get_current_user_id();
    $success_count = get_transient('mmm_bulk_delete_success_' . $uid);
    $error_msg     = get_transient('mmm_bulk_delete_error_' . $uid);
    if ($success_count !== false) {
        delete_transient('mmm_bulk_delete_success_' . $uid);
        echo '<div class="notice notice-success is-dismissible"><p>' . intval($success_count) . ' user(s) deleted.</p></div>';
    }
    if ($error_msg) {
        delete_transient('mmm_bulk_delete_error_' . $uid);
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_msg) . '</p></div>';
    }
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
            $headers = array_map('trim', $csv[0]);
            $headers = array_map('strtolower', $headers);
            $email_index = array_search('email', $headers);
            $id_index = array_search('id', $headers);

            if ($email_index === false && $id_index === false) {
                echo '<p style="color:red;">CSV must contain an "email" or "ID" column.</p>';
                return;
            }

            $matched_users = [];
            foreach (array_slice($csv, 1) as $row) {
                $email   = $email_index !== false ? sanitize_email($row[$email_index]) : null;
                $user_id = $id_index   !== false ? intval($row[$id_index])             : null;

                $user = false;
                if ($user_id) $user = get_userdata($user_id);
                if (!$user && $email) $user = get_user_by('email', $email);

                if ($user) {
                    $matched_users[] = $user;
                }
            }

            if (empty($matched_users)) {
                echo '<p>No matching users found.</p>';
                return;
            }

            $total_users = (int)(count_users()['total_users']);
            $max_allowed = max(1, (int)floor($total_users * 0.01));
            ?>
            <form method="post" id="bulk-delete-form">
                <?php wp_nonce_field('mmm_bulk_delete_users', 'mmm_bulk_delete_nonce'); ?>
                <input type="hidden" name="action" value="bulk_delete">

                <h2>Matched Users (<?php echo count($matched_users); ?>)</h2>
                <p style="color:#856404;background:#fff3cd;border:1px solid #ffc107;padding:8px 12px;border-radius:4px;">
                    ⚠️ <strong>Warning:</strong> Deletion is permanent and cannot be undone.
                    Safety cap: max <?php echo $max_allowed; ?> deletions at once (1% of <?php echo $total_users; ?> total users).
                </p>

                <table class="widefat">
                    <thead><tr>
                        <th><input type="checkbox" id="select-all" title="Select all"></th>
                        <th>ID</th><th>Email</th><th>Name</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($matched_users as $user) :
                        $edit_url   = get_edit_user_link($user->ID);
                        $delete_url = wp_nonce_url(admin_url("users.php?action=delete&user=$user->ID"), 'bulk-users');
                    ?>
                        <tr>
                            <td><input type="checkbox" class="user-select" name="user_ids[]" value="<?php echo esc_attr($user->ID); ?>"></td>
                            <td><?php echo esc_html($user->ID); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><a href="<?php echo esc_url($edit_url); ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:12px;">
                    <button type="submit" class="button button-secondary"
                            style="color:#dc3545;border-color:#dc3545;"
                            onclick="return mmm_confirm_bulk_delete();">
                        Delete Selected
                    </button>
                </p>
            </form>
            <script>
            document.getElementById('select-all').addEventListener('change', function() {
                document.querySelectorAll('.user-select').forEach(cb => cb.checked = this.checked);
            });
            function mmm_confirm_bulk_delete() {
                var checked = document.querySelectorAll('.user-select:checked');
                if (checked.length === 0) {
                    alert('No users selected.');
                    return false;
                }
                return confirm('Delete ' + checked.length + ' selected user(s)? This cannot be undone.');
            }
            </script>
            <?php
        }
        ?>
    </div>
    <?php
}
